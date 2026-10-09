<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\InventoryTransactionType;
use App\Enums\ItemCondition;
use App\Enums\OwnershipMode;
use App\Enums\SerialStatus;
use App\Enums\TrackingType;
use App\Enums\TransferStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Warehouse\Concerns\AuthorizesWarehousePop;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Services\EffectiveAccessService;
use App\Services\InventoryReturnTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
     * SN `TRANSFERRED` yang baris dispatch-nya (`type=TRANSFER`,
     * `from_pop_id` terisi, `to_pop_id` KOSONG) menuju transfer berarah ke
     * Pusat — dibedakan dari transfer stok BIASA (Pusat→Cabang, TIDAK
     * PERNAH sebaliknya) murni lewat arah `to_pop.type`, tanpa kolom
     * penanda baru (lihat docblock `InventoryReturnTransferService`).
     */
    private function pendingSerialIdsQuery(EffectiveAccessService $access, $user)
    {
        return InventoryTransaction::query()
            ->where('type', InventoryTransactionType::TRANSFER->value)
            ->whereNotNull('from_pop_id')
            ->whereNull('to_pop_id')
            ->whereNotNull('inventory_transfer_id')
            ->whereHas('transfer', function ($q) use ($access, $user) {
                $q->where('status', TransferStatus::IN_TRANSIT->value)
                    ->whereHas('toPop', fn ($p) => $p->where('type', 'pusat'))
                    ->when(! $access->hasAllPopAccess($user), fn ($q2) => $q2->whereIn('to_pop_id', $access->getAllowedPopIds($user)));
            })
            ->pluck('serial_id');
    }

    public function index(Request $request, EffectiveAccessService $access): View
    {
        $user = auth()->user();

        $serialIds = $this->pendingSerialIdsQuery($access, $user);

        $serials = InventorySerial::query()
            ->whereIn('id', $serialIds)
            ->where('status', SerialStatus::TRANSFERRED->value)
            ->with(['item', 'latestRetrievalLog'])
            ->orderByDesc('updated_at')
            ->get();

        return view('warehouse.returns.pusat-index', compact('serials'));
    }

    public function create(InventorySerial $serial, EffectiveAccessService $access): View|RedirectResponse
    {
        $user = auth()->user();

        if ($serial->status !== SerialStatus::TRANSFERRED || ! $this->pendingSerialIdsQuery($access, $user)->contains($serial->id)) {
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

    public function store(Request $request, InventorySerial $serial, InventoryReturnTransferService $service): RedirectResponse
    {
        $validated = $request->validate([
            'condition' => ['required', Rule::in([ItemCondition::USED_GOOD->value, ItemCondition::USED_DAMAGED->value])],
            'item_id' => ['nullable', 'integer', 'exists:items,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $service->confirmAtPusat(
                $serial,
                ItemCondition::from($validated['condition']),
                ! empty($validated['item_id']) ? Item::findOrFail($validated['item_id']) : null,
                auth()->user(),
                $validated['notes'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('warehouse.returns.pusat.index')->with('success', "SN {$serial->serial_number} diterima di Gudang Pusat.");
    }
}
