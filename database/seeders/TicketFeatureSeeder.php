<?php

namespace Database\Seeders;

use App\Enums\FeatureType;
use App\Models\Feature;
use App\Services\PermissionGeneratorService;
use Illuminate\Database\Seeder;

/**
 * TicketFeatureSeeder
 *
 * Menanamkan seluruh Feature modul Ticketing internal perusahaan:
 *
 *   tickets                  (root) — Ticketing (buat/lihat/aksi tiket)
 *     ├─ tickets.selesai            — halaman arsip Ticket Selesai
 *     ├─ tickets.dibatalkan         — halaman arsip Ticket Dibatalkan
 *     └─ tickets.history            — halaman History Ticketing (semua tiket + ekspor)
 *   noc_worksheet            (root) — Worksheet NOC (SATU halaman, tanpa tab)
 *   noc_dashboard            (root) — Dashboard NOC
 *     └─ noc_dashboard.performance   — Leaderboard performa individu Helpdesk/NOC
 *
 * Tiap halaman punya feature (=permission) SENDIRI — dulu semuanya numpang
 * `tickets.view` lewat route bucket generik `/tickets/{bucket}`, jadi gak bisa
 * di-toggle per-halaman di Role Matrix. Pola sama persis customers.terminated/
 * customers.failed (lihat FeatureSeeder).
 *
 * Permission-nya digenerate otomatis oleh PermissionGeneratorService dari
 * config/rbac.php. Assignment ke role diatur di RolePermissionSeeder (source of
 * truth permission per role), jalankan RolePermissionSeeder lagi setelah seeder
 * ini biar ke-sync.
 *
 * Idempotent — aman dijalankan ulang.
 * Jalankan: php artisan db:seed --class=TicketFeatureSeeder
 */
class TicketFeatureSeeder extends Seeder
{
    public function run(): void
    {
        $roots = [
            ['code' => 'tickets', 'name' => 'Ticketing', 'sort_order' => 8],
            ['code' => 'noc_worksheet', 'name' => 'Worksheet NOC', 'sort_order' => 9],
            ['code' => 'noc_dashboard', 'name' => 'Dashboard NOC', 'sort_order' => 10],
        ];

        $rootIds = [];
        foreach ($roots as $root) {
            $rootIds[$root['code']] = Feature::updateOrCreate(
                ['code' => $root['code']],
                [
                    'name' => $root['name'],
                    'type' => FeatureType::ROOT,
                    'sort_order' => $root['sort_order'],
                    'is_active' => true,
                    'parent_id' => null,
                ]
            )->id;
        }

        $subFeatures = [
            ['parent' => 'tickets', 'code' => 'tickets.selesai', 'name' => 'Ticket Selesai', 'sort_order' => 1],
            ['parent' => 'tickets', 'code' => 'tickets.dibatalkan', 'name' => 'Ticket Dibatalkan', 'sort_order' => 2],
            ['parent' => 'tickets', 'code' => 'tickets.history', 'name' => 'History Ticketing', 'sort_order' => 3],
            ['parent' => 'noc_dashboard', 'code' => 'noc_dashboard.performance', 'name' => 'Leaderboard Performa Individu', 'sort_order' => 1],
        ];

        foreach ($subFeatures as $sf) {
            Feature::updateOrCreate(
                ['code' => $sf['code']],
                [
                    'name' => $sf['name'],
                    'type' => FeatureType::SUB_FEATURE,
                    'sort_order' => $sf['sort_order'],
                    'is_active' => true,
                    'parent_id' => $rootIds[$sf['parent']],
                ]
            );
        }

        // Dua tab lama Worksheet NOC (noc_worksheet.masuk/diproses, dilebur
        // ADHOC-06 2026-07-29) dulu sengaja dipertahankan nonaktif di sini.
        // Dibersihkan total atas permintaan user (2026-10-02) — lihat migration
        // `remove_retired_noc_worksheet_tab_features`. JANGAN tambah lagi
        // Feature::updateOrCreate buat kode ini, nanti seeder balik
        // menghidupkan baris yang sudah sengaja dihapus migration itu.

        app(PermissionGeneratorService::class)->generate();

        $this->command?->info('TicketFeatureSeeder: feature Ticketing + Worksheet/Dashboard NOC + permission digenerate. Jalankan RolePermissionSeeder biar ke-assign ke role.');
    }
}
