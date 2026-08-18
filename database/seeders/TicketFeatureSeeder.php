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
 *     ├─ noc_worksheet.masuk        — DINONAKTIFKAN (ADHOC-06), tab dilebur
 *     └─ noc_worksheet.diproses     — DINONAKTIFKAN (ADHOC-06), tab dilebur
 *   noc_dashboard            (root) — Dashboard NOC
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

        // Dua tab Worksheet NOC dilebur jadi satu halaman (ADHOC-06,
        // 2026-07-29) — feature-nya DINONAKTIFKAN, bukan dihapus: role yang
        // terlanjur punya permission ini gak boleh error, dan barisnya masih
        // dipakai buat membaca audit log lama. Halaman Worksheet NOC sekarang
        // digerbangi `noc_worksheet.view` (feature root).
        //
        // updateOrCreate (bukan cuma update) — di DB baru (fresh install) baris
        // ini belum pernah ada sama sekali, cuma warisan DB lama yang sempat
        // punya dua tab aktif. Tanpa create, config/rbac.php tetap minta
        // permission `noc_worksheet.masuk.view`/`.diproses.view` digenerate
        // tapi Feature-nya gak ada → rbac:generate-permissions error.
        $retiredNocTabs = [
            ['parent' => 'noc_worksheet', 'code' => 'noc_worksheet.masuk', 'name' => 'Ticket Masuk (Nonaktif)', 'sort_order' => 1],
            ['parent' => 'noc_worksheet', 'code' => 'noc_worksheet.diproses', 'name' => 'Ticket Diproses (Nonaktif)', 'sort_order' => 2],
        ];

        foreach ($retiredNocTabs as $sf) {
            Feature::updateOrCreate(
                ['code' => $sf['code']],
                [
                    'name' => $sf['name'],
                    'type' => FeatureType::SUB_FEATURE,
                    'sort_order' => $sf['sort_order'],
                    'is_active' => false,
                    'parent_id' => $rootIds[$sf['parent']],
                ]
            );
        }

        app(PermissionGeneratorService::class)->generate();

        $this->command?->info('TicketFeatureSeeder: feature Ticketing + Worksheet/Dashboard NOC + permission digenerate. Jalankan RolePermissionSeeder biar ke-assign ke role.');
    }
}
