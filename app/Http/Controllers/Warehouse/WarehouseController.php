<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\InventoryTransactionType;
use App\Enums\SerialStatus;
use App\Http\Controllers\Controller;
use App\Models\InventoryBalance;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Pop;
use App\Services\EffectiveAccessService;
use Illuminate\View\View;

/**
 * Dashboard Gudang (ADHOC-54, rancangan-ui.md §2.1) — cuma VIEW, semua
 * penulisan data lewat WarehouseTransferController/WarehouseIssueController.
 *
 * Discope lewat POP scope existing (`EffectiveAccessService`) — pop_admin
 * cuma liat gudang cabangnya sendiri, admin/owner liat semua. Ini query
 * langsung (bukan `Pop::scopeForUser()`) karena butuh gabung `scopeWarehouse()`
 * (pusat/cabang doang, exclude mini_pop) SEKALIGUS scope user.
 */
class WarehouseController extends Controller
{
    public function index(EffectiveAccessService $access): View
    {
        $user = auth()->user();

        $pops = Pop::query()
            ->warehouse()
            ->when(! $access->hasAllPopAccess($user), fn ($q) => $q->whereIn('id', $access->getAllowedPopIds($user)))
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $popIds = $pops->pluck('id');

        // Tabel "Stok Saat Ini" mentah (semua balance qty>0, tanpa filter/
        // pagination) PINDAH ke Management Stock (`WarehouseStockController`,
        // koreksi IA 2026-09-03) — dashboard cuma nyisain ringkasan (stats +
        // stok rendah + ledger terbaru), jangan didobel di sini.
        $totalBarangKetrack = InventoryBalance::query()
            ->whereIn('pop_id', $popIds)
            ->where('qty', '>', 0)
            ->distinct('item_id')
            ->count('item_id');

        $lowStock = InventoryBalance::query()
            ->whereIn('pop_id', $popIds)
            ->lowStock()
            ->with(['item.category', 'pop'])
            ->get();

        $stats = [
            'total_gudang' => $pops->count(),
            'total_barang_ketrack' => $totalBarangKetrack,
            'low_stock_count' => $lowStock->count(),
            'serial_tersedia' => InventorySerial::query()
                ->whereIn('current_pop_id', $popIds)
                ->where('status', SerialStatus::AVAILABLE->value)
                ->count(),
        ];

        // Ledger terbaru — bukan realtime (§2.1 rancangan-ui.md, beda dari
        // Setoran Kas yang butuh live-update), reload manual cukup.
        $recentLedger = InventoryTransaction::query()
            ->where(fn ($q) => $q->whereIn('from_pop_id', $popIds)->orWhereIn('to_pop_id', $popIds))
            ->whereNotIn('type', [InventoryTransactionType::TRANSFER_CUSTODY->value]) // custody teknisi bukan urusan gudang di sini
            ->with(['item.category', 'fromPop', 'toPop', 'fromTechnician', 'toTechnician', 'serial'])
            ->latest('id')
            ->limit(25)
            ->get();

        return view('warehouse.index', compact('pops', 'lowStock', 'stats', 'recentLedger'));
    }
}
