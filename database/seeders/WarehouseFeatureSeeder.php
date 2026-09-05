<?php

namespace Database\Seeders;

use App\Enums\FeatureType;
use App\Models\Feature;
use App\Services\PermissionGeneratorService;
use Illuminate\Database\Seeder;

/**
 * WarehouseFeatureSeeder
 *
 * Menanamkan 5 Feature ROOT buat modul Gudang/Inventory (ADHOC-54,
 * docs/plan/warehouse/rancangan-ui.md §1.2):
 *
 *   warehouse               — Dashboard stok + Ledger/riwayat transaksi (§2.1, §2.9)
 *   warehouse_transfer      — Transfer Pusat→Cabang, buat & terima (§2.2, §2.3)
 *   warehouse_issue         — Issue ke Teknisi (§2.4)
 *   warehouse_custody       — Lihat custody SEMUA teknisi (admin/gudang).
 *                              "Lihat custody SENDIRI" (teknisi) SENGAJA TIDAK
 *                              pakai feature/permission ini — itu widget
 *                              embedded di halaman Task teknisi existing,
 *                              discope `custodian=auth()->id()` di query
 *                              (§1.2/§2.5 rancangan-ui.md). Jangan bikin
 *                              feature/permission `warehouse_custody.view_own`.
 *   warehouse_traceability  — Asset Traceability, cari SN → riwayat (§2.8)
 *   warehouse_adjustment    — Lapor rusak/hilang/opname (LOST/DAMAGED/
 *                              SCRAPPED/ADJUSTMENT). Ketahuan kelewat pas
 *                              nutup Fase 8 UI — Service-nya udah ada sejak
 *                              Fase 6, permission-nya baru nyusul.
 *   warehouse_reassign      — Reassign custody teknisi resign/cuti (§3.6).
 *                              Sama, Service Fase 6, permission nyusul Fase 8.
 *   warehouse_report        — Laporan agregat periodik (movement & kerugian
 *                              per gudang/cabang) — Fase 2 P2 (2026-09-03,
 *                              fase-2-adaptasi-wms.md).
 *   warehouse_stock_request — Permintaan Stok Cabang→Pusat (2026-09-03) —
 *                              jawaban gap "cabang habis stok, Pusat gak
 *                              sadar" (sebelumnya cuma badge Stok Rendah
 *                              pasif, gak ada sinyal aktif ke Pusat).
 *
 * Tujuh root TERPISAH (bukan satu root `warehouse` dengan banyak sub-feature)
 * karena masing-masing punya audience beda: `warehouse_transfer`/`warehouse_issue`
 * cuma buat operator gudang, `warehouse_custody`/`warehouse_traceability` juga
 * dipakai `fop` (read-only, tanpa akses transfer/issue sama sekali) — kalau
 * digabung satu root, gak bisa granting sebagian ke `fop` tanpa ikut kebuka
 * yang lain lewat wildcard `warehouse.*`.
 *
 * Permission-nya (`{feature_code}.{action_code}`) digenerate otomatis oleh
 * PermissionGeneratorService dari config/rbac.php. Assignment ke role diatur
 * di RolePermissionSeeder, jalankan lagi setelah seeder ini biar ke-sync
 * (pola sama QrFeatureSeeder).
 *
 * Idempotent — aman dijalankan ulang.
 * Jalankan: php artisan db:seed --class=WarehouseFeatureSeeder
 */
class WarehouseFeatureSeeder extends Seeder
{
    public function run(): void
    {
        $roots = [
            ['code' => 'warehouse', 'name' => 'Dashboard & Ledger Gudang', 'sort_order' => 13],
            ['code' => 'warehouse_transfer', 'name' => 'Transfer Antar Gudang', 'sort_order' => 14],
            ['code' => 'warehouse_issue', 'name' => 'Issue ke Teknisi', 'sort_order' => 15],
            ['code' => 'warehouse_custody', 'name' => 'Custody Teknisi (Semua)', 'sort_order' => 16],
            ['code' => 'warehouse_traceability', 'name' => 'Asset Traceability', 'sort_order' => 17],
            ['code' => 'warehouse_adjustment', 'name' => 'Adjustment Stok (Rusak/Hilang/Opname)', 'sort_order' => 18],
            ['code' => 'warehouse_reassign', 'name' => 'Reassign Custody Teknisi', 'sort_order' => 19],
            ['code' => 'warehouse_report', 'name' => 'Laporan Gudang (Agregat Periodik)', 'sort_order' => 20],
            ['code' => 'warehouse_stock_request', 'name' => 'Permintaan Stok Cabang', 'sort_order' => 21],
        ];

        foreach ($roots as $root) {
            Feature::updateOrCreate(
                ['code' => $root['code']],
                [
                    'name' => $root['name'],
                    'type' => FeatureType::ROOT,
                    'sort_order' => $root['sort_order'],
                    'is_active' => true,
                    'parent_id' => null,
                ]
            );
        }

        app(PermissionGeneratorService::class)->generate();

        $this->command?->info('WarehouseFeatureSeeder: 9 feature gudang digenerate. Jalankan RolePermissionSeeder biar ke-assign ke role.');
    }
}
