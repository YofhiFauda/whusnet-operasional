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
 *
 * Root `customers` & `tasks` sudah dibuat FeatureSeeder/TaskFeatureSeeder —
 * seeder ini HARUS jalan setelah keduanya (lihat urutan di DatabaseSeeder).
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

        app(PermissionGeneratorService::class)->generate();

        $this->command?->info('QrFeatureSeeder: feature QR Pelanggan digenerate. Jalankan RolePermissionSeeder biar ke-assign ke role.');
    }
}
