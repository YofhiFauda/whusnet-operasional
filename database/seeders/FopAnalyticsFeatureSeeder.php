<?php

namespace Database\Seeders;

use App\Enums\FeatureType;
use App\Models\Feature;
use App\Services\PermissionGeneratorService;
use Illuminate\Database\Seeder;

/**
 * FopAnalyticsFeatureSeeder
 *
 * Menanamkan Feature ROOT `fop_analytics` — Dashboard Analitik FOP
 * (docs/plan/analisa-dashboard-analitik-fop.md), halaman terpisah dari
 * `/fop` (dashboard operasional harian, guard `task.view.all`). Isinya
 * agregat lintas periode: alat kerja terpakai, wilayah pemasangan/
 * komplain/gagal, beban & solving teknisi, backlog, dan durasi
 * pengerjaan terlama — dibuka mingguan/bulanan buat evaluasi, bukan
 * tiap shift, jadi sengaja diberi permission SENDIRI (`fop_analytics.view`)
 * biar bisa dimatikan per-role independen dari akses `/fop` sehari-hari.
 *
 * Permission-nya digenerate otomatis oleh PermissionGeneratorService dari
 * config/rbac.php. Assignment ke role diatur di RolePermissionSeeder,
 * jalankan lagi setelah seeder ini biar ke-sync (pola sama WarehouseFeatureSeeder).
 *
 * Idempotent — aman dijalankan ulang.
 * Jalankan: php artisan db:seed --class=FopAnalyticsFeatureSeeder
 */
class FopAnalyticsFeatureSeeder extends Seeder
{
    public function run(): void
    {
        Feature::updateOrCreate(
            ['code' => 'fop_analytics'],
            [
                'name' => 'Dashboard Analitik FOP',
                'type' => FeatureType::ROOT,
                'sort_order' => 22,
                'is_active' => true,
                'parent_id' => null,
            ]
        );

        app(PermissionGeneratorService::class)->generate();

        $this->command?->info('FopAnalyticsFeatureSeeder: feature fop_analytics digenerate. Jalankan RolePermissionSeeder biar ke-assign ke role.');
    }
}
