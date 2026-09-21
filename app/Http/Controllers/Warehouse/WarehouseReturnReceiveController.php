<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\ItemCondition;
use App\Enums\OwnershipMode;
use App\Enums\SerialStatus;
use App\Enums\TrackingType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Warehouse\Concerns\AuthorizesWarehousePop;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Services\EffectiveAccessService;
use App\Services\InventoryReassignService;
use App\Services\LegacyDeviceHintService;
use App\Support\RupiahInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * "Terima Retur" — konfirmasi gudang cabang atas modem hasil pengambilan alat
 * (task DEAC), ADHOC-86. Modem berstatus `RETURNED` (transit, dipegang
 * teknisi) sampai staf di sini memeriksa fisiknya dan menerimanya jadi
 * `AVAILABLE`. Rancangan: docs/plan/warehouse/analisa-ambil-modem-deac-ke-gudang.md.
 *
 * Mutasi data → halaman create tersendiri, bukan modal (aturan CLAUDE.md
 * pola 2). Permission REUSE `warehouse_reassign.create`, satu payung dengan
 * "Sudah Dicek" dan Reassign (keputusan user 2026-09-19).
 */
class WarehouseReturnReceiveController extends Controller
{
    use AuthorizesWarehousePop;

    public function index(EffectiveAccessService $access): View
    {
        $user = auth()->user();

        $serials = InventorySerial::query()
            ->where('status', SerialStatus::RETURNED->value)
            ->when(! $access->hasAllPopAccess($user), fn ($q) => $q->whereIn('issued_from_pop_id', $access->getAllowedPopIds($user)))
            ->with(['item', 'currentTechnician', 'customer', 'issuedFromPop'])
            ->orderBy('updated_at')
            ->paginate(25);

        return view('warehouse.returns.index', compact('serials'));
    }

    public function create(InventorySerial $serial, EffectiveAccessService $access, LegacyDeviceHintService $hints): View|RedirectResponse
    {
        $this->assertPopIdInScope($serial->issued_from_pop_id, auth()->user(), $access);

        if ($serial->status !== SerialStatus::RETURNED) {
            return redirect()->route('warehouse.returns.index')->with('error', "SN {$serial->serial_number} tidak sedang menunggu diterima.");
        }

        $serial->load(['item', 'currentTechnician', 'customer', 'issuedFromPop']);

        $items = Item::active()
            ->where('tracking_type', TrackingType::SERIALIZED->value)
            ->where('ownership_mode', OwnershipMode::INSTALLABLE->value)
            ->orderBy('name')
            ->get();

        // Petunjuk merek dari data lama pelanggan (bantuan, bukan kebenaran):
        // membantu staf mengoreksi model "Modem Legacy" saat memegang fisiknya.
        $legacyHint = $serial->customer ? $hints->forCustomer($serial->customer) : null;

        return view('warehouse.returns.receive', compact('serial', 'items', 'legacyHint'));
    }

    public function store(Request $request, InventorySerial $serial, InventoryReassignService $service, EffectiveAccessService $access): RedirectResponse
    {
        $user = auth()->user();
        $this->assertPopIdInScope($serial->issued_from_pop_id, $user, $access);

        // Nominal diketik dengan titik ribuan ("150.000") — dinormalkan di
        // server, bukan cuma masking JS (lihat App\Support\RupiahInput).
        $request->merge(['estimated_value' => RupiahInput::parse($request->input('estimated_value'))]);

        $validated = $request->validate([
            'condition' => ['required', Rule::in([ItemCondition::USED_GOOD->value, ItemCondition::USED_DAMAGED->value])],
            'item_id' => ['nullable', 'integer', 'exists:items,id'],
            // Opsional: modem legacy tidak punya harga beli. Kosong = Rp 0 di laporan.
            'estimated_value' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $service->confirmReturnedSerial(
                $serial,
                ItemCondition::from($validated['condition']),
                ! empty($validated['item_id']) ? Item::findOrFail($validated['item_id']) : null,
                $user,
                $validated['notes'] ?? null,
                isset($validated['estimated_value']) ? (float) $validated['estimated_value'] : null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('warehouse.returns.index')->with('success', "SN {$serial->serial_number} diterima di Gudang.");
    }
}
