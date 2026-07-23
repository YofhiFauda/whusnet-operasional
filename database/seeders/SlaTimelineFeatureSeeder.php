<?php

namespace Database\Seeders;

use App\Enums\FeatureType;
use App\Models\Feature;
use App\Services\PermissionGeneratorService;
use Illuminate\Database\Seeder;

/**
 * SlaTimelineFeatureSeeder
 *
 * Menanamkan root Feature 'sla_timeline' (Master Timeline SLA). Permission-nya
 * (sla_timeline.view, sla_timeline.update) digenerate otomatis oleh
 * PermissionGeneratorService dari config/rbac.php. Assignment ke role diatur
 * di RolePermissionSeeder (source of truth permission per role), jalankan
 * RolePermissionSeeder lagi setelah seeder ini biar ke-sync.
 *
 * Idempotent — aman dijalankan ulang.
 * Jalankan: php artisan db:seed --class=SlaTimelineFeatureSeeder
 */
class SlaTimelineFeatureSeeder extends Seeder
{
    public function run(): void
    {
        // sort_order fixed = 7, biar nyambung sama blok Master Data lain
        // (Wilayah=2, POP/Cabang=3, Distribusi=4, Paket Internet=5, Status
        // Pelanggan=6) yang diatur di FeatureSeeder.php — jangan pakai
        // max()+1 lagi, itu bikin fitur ini selalu jatuh di paling bawah.
        Feature::updateOrCreate(
            ['code' => 'sla_timeline'],
            [
                'name' => 'Master Timeline SLA',
                'type' => FeatureType::ROOT,
                'sort_order' => 7,
                'is_active' => true,
                'parent_id' => null,
            ]
        );

        app(PermissionGeneratorService::class)->generate();

        $this->command->info('SlaTimelineFeatureSeeder: feature sla_timeline + permission digenerate. Jalankan RolePermissionSeeder biar ke-assign ke role.');
    }
}
