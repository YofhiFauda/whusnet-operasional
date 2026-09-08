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
        $search = trim((string) $request->query('search', ''));
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $ledger = InventoryTransaction::query()
            ->where(fn ($q) => $q->whereIn('from_pop_id', $popIds)->orWhereIn('to_pop_id', $popIds))
            ->when($typeFilter, fn ($q) => $q->where('type', $typeFilter))
            ->when($popFilter, fn ($q) => $q->where(fn ($qq) => $qq->where('from_pop_id', $popFilter)->orWhere('to_pop_id', $popFilter)))
            // Cari SN / SKU nama barang / nomor referensi — 3 kolom beda tabel
            // (item, serial, transaksi itu sendiri), makanya whereHas ganda.
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('reference_number', 'like', "%{$search}%")
                        ->orWhereHas('item', fn ($iq) => $iq->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                        ->orWhereHas('serial', fn ($sq) => $sq->where('serial_number', 'like', "%{$search}%"));
                });
            })
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            // `transfer.toPop` — leg dispatch TRANSFER (to_pop_id masih
            // NULL, belum ditambahkan ke saldo tujuan, lihat docblock
            // migration `create_inventory_transactions_table`) butuh ini
            // buat nampilin tujuan SEBENARNYA, bukan fallback "Pelanggan /
            // Luar" yang nyasar (laporan user 2026-09-07: transfer yang
            // masih in-transit kelihatan kayak dikirim ke pelanggan).
            ->with(['item.category', 'fromPop', 'toPop', 'fromTechnician', 'toTechnician', 'serial', 'createdBy', 'transfer.toPop'])
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        $types = InventoryTransactionType::cases();

        return view('warehouse.history.index', compact('ledger', 'pops', 'types', 'typeFilter', 'popFilter', 'search', 'dateFrom', 'dateTo'));
    }
}
