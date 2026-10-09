<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\SerialStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Warehouse\Concerns\AuthorizesWarehousePop;
use App\Models\InventorySerial;
use App\Models\Pop;
use App\Services\EffectiveAccessService;
use App\Services\InventoryReturnTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * TAHAP 2 (ADHOC-108) — Cabang kirim modem hasil retur (sudah lewat Tahap 1,
 * `WarehouseReturnReceiveController`) ke Gudang Pusat untuk verifikasi final.
 * SN di sini `status=RETURNED`, `current_technician_id` NULL,
 * `current_pop_id` terisi (ada di Cabang, bukan lagi di tangan teknisi).
 *
 * Permission REUSE `warehouse_reassign.create`, satu payung dengan Terima
 * Retur — konsisten pola reuse yang sudah ada di modul ini.
 */
class WarehouseReturnDispatchController extends Controller
{
    use AuthorizesWarehousePop;

    public function index(Request $request, EffectiveAccessService $access): View
    {
        $user = auth()->user();
        $popFilter = $request->integer('pop_id') ?: null;

        if ($popFilter) {
            $this->assertPopIdInScope($popFilter, $user, $access);
        }

        $serials = InventorySerial::query()
            ->where('status', SerialStatus::RETURNED->value)
            ->whereNull('current_technician_id')
            ->whereNotNull('current_pop_id')
            ->when(! $access->hasAllPopAccess($user), fn ($q) => $q->whereIn('current_pop_id', $access->getAllowedPopIds($user)))
            ->when($popFilter, fn ($q) => $q->where('current_pop_id', $popFilter))
            ->with(['item', 'currentPop', 'latestRetrievalLog'])
            ->orderByDesc('updated_at')
            ->get();

        $pusatOptions = Pop::where('type', 'pusat')->where('status', 'active')->orderBy('name')->get(['id', 'name']);

        $popOptions = Pop::where('type', 'cabang')->where('status', 'active')
            ->when(! $access->hasAllPopAccess($user), fn ($q) => $q->whereIn('id', $access->getAllowedPopIds($user)))
            ->orderBy('name')->get(['id', 'name']);

        return view('warehouse.returns.dispatch', compact('serials', 'pusatOptions', 'popOptions', 'popFilter'));
    }

    public function store(Request $request, InventoryReturnTransferService $service, EffectiveAccessService $access): RedirectResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'serial_ids' => ['required', 'array', 'min:1'],
            'serial_ids.*' => ['integer', 'exists:inventory_serials,id'],
            'pusat_id' => ['required', 'integer', 'exists:pops,id'],
        ], [
            'serial_ids.required' => 'Pilih minimal 1 SN untuk dikirim.',
        ]);

        $pusat = Pop::findOrFail($validated['pusat_id']);
        $serials = InventorySerial::query()->whereIn('id', $validated['serial_ids'])->get();

        // Dikelompokkan per Cabang asal — satu pengiriman (`InventoryTransfer`)
        // per Cabang, biar `from_pop_id` header-nya konsisten satu nilai
        // (bukan dipaksa campur kalau SN kebetulan ada di Cabang berbeda).
        $byCabang = $serials->groupBy('current_pop_id');

        try {
            foreach ($byCabang as $cabangId => $group) {
                $cabang = Pop::findOrFail($cabangId);
                $this->assertPopInScope($cabang, $user, $access);
                $service->dispatchToPusat($cabang, $pusat, $group->pluck('id')->all(), $user);
            }
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('warehouse.returns.dispatch.index')->with('success', 'Modem retur dikirim ke Gudang Pusat — menunggu konfirmasi terima.');
    }
}
