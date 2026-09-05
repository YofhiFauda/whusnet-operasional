<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\InventoryTransactionType;
use App\Http\Controllers\Controller;
use App\Models\InventoryTransaction;
use App\Models\Pop;
use App\Services\EffectiveAccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Riwayat Mutasi (koreksi IA Gudang, 2026-09-03) — satu halaman buat browse
 * SEMUA ledger, dipaginasi+filter, tiap baris nge-link ke dokumen sumbernya
 * (Transfer/Issue/Receive show()). Sebelumnya SATU-SATUNYA cara balik ke
 * halaman detail Transfer/Issue/Receive cuma lewat redirect pas create/
 * konfirmasi — begitu ditinggal, dokumennya ilang gak bisa ditemu lagi
 * kecuali nebak URL (laporan user: "list/detail tersembunyi", 2026-09-03).
 * Dashboard punya versi ringkas (25 baris, gak dipaginasi) buat sekilas —
 * halaman ini yang buat BENERAN nyari.
 *
 * Cuma VIEW — permission reuse `warehouse.view` (sama kayak Dashboard),
 * discope sama persis pola `WarehouseController`/`WarehouseStockController`.
 */
class WarehouseHistoryController extends Controller
{
    public function index(Request $request, EffectiveAccessService $access): View
    {
        $user = auth()->user();

        $pops = Pop::query()
            ->warehouse()
            ->when(! $access->hasAllPopAccess($user), fn ($q) => $q->whereIn('id', $access->getAllowedPopIds($user)))
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $popIds = $pops->pluck('id');

        $typeFilter = $request->query('type');
        $popFilter = $request->integer('pop_id') ?: null;

        $ledger = InventoryTransaction::query()
            ->where(fn ($q) => $q->whereIn('from_pop_id', $popIds)->orWhereIn('to_pop_id', $popIds))
            ->when($typeFilter, fn ($q) => $q->where('type', $typeFilter))
            ->when($popFilter, fn ($q) => $q->where(fn ($qq) => $qq->where('from_pop_id', $popFilter)->orWhere('to_pop_id', $popFilter)))
            ->with(['item.category', 'fromPop', 'toPop', 'fromTechnician', 'toTechnician', 'serial', 'createdBy'])
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        $types = InventoryTransactionType::cases();

        return view('warehouse.history.index', compact('ledger', 'pops', 'types', 'typeFilter', 'popFilter'));
    }
}
