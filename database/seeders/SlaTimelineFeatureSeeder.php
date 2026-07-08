<?php

namespace Database\Seeders;

use App\Enums\FeatureType;
use App\Models\Feature;
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
        $sortOrder = (int) (Feature::whereNull('parent_id')->max('sort_order') ?? 0) + 1;

        Feature::updateOrCreate(
            ['code' => 'sla_timeline'],
            [
                'name'       => 'Master Timeline SLA',
                'type'       => FeatureType::ROOT,
                'sort_order' => $sortOrder,
                'is_active'  => true,
                'parent_id'  => null,
            ]
        );

        app(\App\Services\PermissionGeneratorService::class)->generate();

        $this->command->info('SlaTimelineFeatureSeeder: feature sla_timeline + permission digenerate. Jalankan RolePermissionSeeder biar ke-assign ke role.');
    }
}
