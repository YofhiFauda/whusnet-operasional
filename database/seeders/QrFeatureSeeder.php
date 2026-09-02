<?php

namespace Database\Seeders;

use App\Enums\FeatureType;
use App\Models\Feature;
use App\Services\PermissionGeneratorService;
use Illuminate\Database\Seeder;

/**
 * QrFeatureSeeder
 *
 * Menanamkan Feature buat modul QR Pelanggan
 * (docs/plan/qr-code/rancangan-qr-pelanggan-final.md §5, §10 Fase 1):
 *
 *   customers.qr       (sub dari root `customers`) — lihat/terbitkan/cabut/cetak token
 *   tasks.qr_attendance (sub dari root `tasks`)    — absen via QR, Fase 3 (diseed sekarang)
 *   qr_scan_logs        (root baru)                — dashboard anomali scan, Fase 2/3
 *   qr_scan             (root baru, 2026-08-27)     — halaman Scan QR Internal (staf), kamera
 *                                                      di dalam app, gantikan asumsi app scanner
 *                                                      luar (lihat resources/js/qr-scan.js)
 *   tickets.qr          (sub dari root `tickets`, 2026-08-29)  — create tiket lewat Portal
 *                                                      setelah scan QR (`tickets.qr.create`).
 *                                                      TERPISAH dari `tickets.create` dashboard
 *                                                      biasa — channel Portal pakai token
 *                                                      one-shot (StaffPortalToken), risikonya
 *                                                      beda, jadi permission-nya pun beda
 *                                                      (docs/plan/qr-code/
 *                                                      analisa-unifikasi-qr-staff-portal.md §1.4).
 *                                                      CATATAN: role dengan `tickets.*` (helpdesk/
 *                                                      noc/fop) SUDAH otomatis lolos lewat feature
 *                                                      wildcard (EffectiveAccessService::userCan()),
 *                                                      jadi tidak perlu baris tambahan di
 *                                                      RolePermissionSeeder untuk role-role itu.
 *   kolektor.qr         (sub dari root `kolektor`, 2026-08-29) — catat pembayaran lewat Portal
 *                                                      setelah scan QR (`kolektor.qr.pay`).
 *                                                      Role `kolektor` TIDAK punya wildcard
 *                                                      `kolektor.*` (sengaja, lihat komentar di
 *                                                      RolePermissionSeeder), jadi permission ini
 *                                                      WAJIB ditambah eksplisit di sana.
 *
 * Root `customers`, `tasks`, `tickets`, `kolektor` sudah dibuat seeder lain
 * (FeatureSeeder/TaskFeatureSeeder/dst) — seeder ini HARUS jalan setelah
 * semuanya (lihat urutan di DatabaseSeeder).
 *
 * Permission-nya digenerate otomatis oleh PermissionGeneratorService dari
 * config/rbac.php. Assignment ke role diatur di RolePermissionSeeder,
 * jalankan lagi setelah seeder ini biar ke-sync.
 *
 * Idempotent — aman dijalankan ulang.
 * Jalankan: php artisan db:seed --class=QrFeatureSeeder
 */
class QrFeatureSeeder extends Seeder
{
    public function run(): void
    {
        $customersRootId = Feature::where('code', 'customers')->value('id');
        $tasksRootId = Feature::where('code', 'tasks')->value('id');

        if (! $customersRootId || ! $tasksRootId) {
            $this->command?->error('QrFeatureSeeder: root feature `customers`/`tasks` belum ada — jalankan FeatureSeeder & TaskFeatureSeeder dulu.');

            return;
        }

        // `tickets`/`kolektor` root DICEK TERPISAH (bukan digabung ke guard
        // di atas) — beberapa test lama (mis. QrInAppScanPageTest,
        // QrStaffPageSmokeTest) sengaja cuma seed FeatureSeeder+QrFeatureSeeder
        // TANPA TicketFeatureSeeder, karena mereka tidak butuh fitur ticketing
        // sama sekali. Kalau guard ini digabung jadi satu early-return,
        // seeder BERHENTI TOTAL sebelum sempat bikin `customers.qr`/`qr_scan`
        // — regresi 403 di semua test itu (ketahuan 2026-08-29). Jadi kalau
        // roots ini belum ada, cuma LEWATI blok `tickets.qr`/`kolektor.qr`
        // di bawah, bukan gagalkan seeder-nya.
        $ticketsRootId = Feature::where('code', 'tickets')->value('id');
        $kolektorRootId = Feature::where('code', 'kolektor')->value('id');

        Feature::updateOrCreate(
            ['code' => 'customers.qr'],
            [
                'name' => 'QR Pelanggan',
                'type' => FeatureType::SUB_FEATURE,
                'sort_order' => 20,
                'is_active' => true,
                'parent_id' => $customersRootId,
            ]
        );

        Feature::updateOrCreate(
            ['code' => 'tasks.qr_attendance'],
            [
                'name' => 'Absen Task via QR',
                'type' => FeatureType::SUB_FEATURE,
                'sort_order' => 20,
                'is_active' => true,
                'parent_id' => $tasksRootId,
            ]
        );

        Feature::updateOrCreate(
            ['code' => 'qr_scan_logs'],
            [
                'name' => 'Dashboard Anomali Scan QR',
                'type' => FeatureType::ROOT,
                'sort_order' => 11,
                'is_active' => true,
                'parent_id' => null,
            ]
        );

        Feature::updateOrCreate(
            ['code' => 'qr_scan'],
            [
                'name' => 'Scan QR Internal',
                'type' => FeatureType::ROOT,
                'sort_order' => 12,
                'is_active' => true,
                'parent_id' => null,
            ]
        );

        if ($ticketsRootId) {
            Feature::updateOrCreate(
                ['code' => 'tickets.qr'],
                [
                    'name' => 'Create Tiket via QR (Portal)',
                    'type' => FeatureType::SUB_FEATURE,
                    'sort_order' => 20,
                    'is_active' => true,
                    'parent_id' => $ticketsRootId,
                ]
            );
        }

        if ($kolektorRootId) {
            Feature::updateOrCreate(
                ['code' => 'kolektor.qr'],
                [
                    'name' => 'Catat Pembayaran via QR (Portal)',
                    'type' => FeatureType::SUB_FEATURE,
                    'sort_order' => 20,
                    'is_active' => true,
                    'parent_id' => $kolektorRootId,
                ]
            );
        }

        app(PermissionGeneratorService::class)->generate();

        $this->command?->info('QrFeatureSeeder: feature QR Pelanggan digenerate. Jalankan RolePermissionSeeder biar ke-assign ke role.');
    }
}
