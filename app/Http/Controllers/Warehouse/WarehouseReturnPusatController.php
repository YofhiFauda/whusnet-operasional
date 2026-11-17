<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\InventoryTransactionType;
use App\Enums\ItemCondition;
use App\Enums\OwnershipMode;
use App\Enums\TrackingType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Warehouse\Concerns\AuthorizesWarehousePop;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Services\ActionQueueBadgeService;
use App\Services\EffectiveAccessService;
use App\Services\InventoryReturnTransferService;
use App\Support\RupiahInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * TAHAP 3 (ADHOC-108) — Gudang Pusat konfirmasi retur modem yang dikirim
 * Cabang (Tahap 2, `WarehouseReturnDispatchController`). Kondisi FINAL
 * ditentukan DI SINI, gate `isClearedForIssue()` dilepas, stok Pusat baru
 * bertambah — penutup alur "SEMUA retur wajib verifikasi Pusat" (keputusan
 * user 2026-10-08).
 *
 * Permission REUSE `warehouse_reassign.create`, sama payung dengan Tahap 1/2.
 */
class WarehouseReturnPusatController extends Controller
{
    use AuthorizesWarehousePop;

    /**
     * Daftar & guard memakai query yang SAMA dengan badge "Terima di Pusat"
     * (`ActionQueueBadgeService::pendingPusatReturns()`) — kalau dipisah,
     * badge bisa bilang "3" sementara daftarnya kosong (atau sebaliknya).
     */
    public function index(Request $request, ActionQueueBadgeService $queues): View
    {
        Gate::authorize('warehouse_transfer.create');

        $serials = $queues->pendingPusatReturns(auth()->user())
            ->with(['item', 'latestRetrievalLog.task.creqDetail'])
            ->orderByDesc('updated_at')
            ->get();

        return view('warehouse.returns.pusat-index', compact('serials'));
    }

    public function create(InventorySerial $serial, ActionQueueBadgeService $queues): View|RedirectResponse
    {
        Gate::authorize('warehouse_transfer.create');

        if (! $queues->pendingPusatReturns(auth()->user())->whereKey($serial->id)->exists()) {
            return redirect()->route('warehouse.returns.pusat.index')->with('error', "SN {$serial->serial_number} tidak sedang menunggu konfirmasi Pusat.");
        }

        $serial->load(['item', 'latestRetrievalLog.task.creqDetail', 'latestRetrievalLog.customer']);

        $items = Item::active()
            ->where('tracking_type', TrackingType::SERIALIZED->value)
            ->where('ownership_mode', OwnershipMode::INSTALLABLE->value)
            ->orderBy('name')
            ->get();

        return view('warehouse.returns.pusat-receive', compact('serial', 'items'));
    }

    private function returnDestinationPopId(InventorySerial $serial): ?int
    {
        return InventoryTransaction::query()
            ->where('serial_id', $serial->id)
            ->where('type', InventoryTransactionType::TRANSFER->value)
            ->whereNotNull('from_pop_id')
            ->whereNull('to_pop_id')
            ->latest('id')
            ->with('transfer')
            ->first()?->transfer?->to_pop_id;
    }

    public function store(Request $request, InventorySerial $serial, InventoryReturnTransferService $service, EffectiveAccessService $access): RedirectResponse
    {
        Gate::authorize('warehouse_transfer.create');
        $this->assertPopIdInScope($this->returnDestinationPopId($serial), auth()->user(), $access);

        $request->merge(['estimated_value' => RupiahInput::parse($request->input('estimated_value'))]);
        $validated = $request->validate([
            'condition' => ['required', Rule::in([ItemCondition::USED_GOOD->value, ItemCondition::USED_DAMAGED->value])],
            'item_id' => ['nullable', 'integer', 'exists:items,id'],
            'estimated_value' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $service->confirmAtPusat(
                $serial,
                ItemCondition::from($validated['condition']),
                ! empty($validated['item_id']) ? Item::findOrFail($validated['item_id']) : null,
                auth()->user(),
                $validated['notes'] ?? null,
                isset($validated['estimated_value']) ? (float) $validated['estimated_value'] : null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('warehouse.returns.pusat.index')->with('success', "SN {$serial->serial_number} diterima di Gudang Pusat.");
    }
}
