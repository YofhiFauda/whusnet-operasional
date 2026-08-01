<?php

namespace Database\Seeders;

use App\Enums\FeatureType;
use App\Models\Feature;
use App\Services\PermissionGeneratorService;
use Illuminate\Database\Seeder;

/**
 * WorkToolFeatureSeeder
 *
 * Menanamkan root Feature 'work_tools' (Master Alat Kerja). Permission-nya
 * (work_tools.view/create/update/delete) digenerate otomatis oleh
 * PermissionGeneratorService dari config/rbac.php — bukan hardcode.
 *
 * Idempotent — aman dijalankan ulang.
 * Jalankan: php artisan db:seed --class=WorkToolFeatureSeeder
 */
class WorkToolFeatureSeeder extends Seeder
{
    public function run(): void
    {
        Feature::updateOrCreate(
            ['code' => 'work_tools'],
            [
                'name' => 'Master Alat Kerja',
                'type' => FeatureType::ROOT,
                'sort_order' => 12,
                'is_active' => true,
                'parent_id' => null,
            ]
        );

        app(PermissionGeneratorService::class)->generate();

        $this->command->info('WorkToolFeatureSeeder: feature work_tools + permission digenerate.');
    }
}
