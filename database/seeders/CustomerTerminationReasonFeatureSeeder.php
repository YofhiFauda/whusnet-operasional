<?php

namespace Database\Seeders;

use App\Enums\FeatureType;
use App\Models\Feature;
use App\Services\PermissionGeneratorService;
use Illuminate\Database\Seeder;

/**
 * CustomerTerminationReasonFeatureSeeder (ADHOC-69)
 *
 * Menanamkan root Feature 'termination_reasons' (Master Alasan Putus
 * Langganan). Permission-nya (termination_reasons.view/create/update/delete)
 * digenerate otomatis oleh PermissionGeneratorService dari config/rbac.php.
 *
 * Idempotent — aman dijalankan ulang.
 * Jalankan: php artisan db:seed --class=CustomerTerminationReasonFeatureSeeder
 */
class CustomerTerminationReasonFeatureSeeder extends Seeder
{
    public function run(): void
    {
        Feature::updateOrCreate(
            ['code' => 'termination_reasons'],
            [
                'name' => 'Master Alasan Putus Langganan',
                'type' => FeatureType::ROOT,
                'sort_order' => 29,
                'is_active' => true,
                'parent_id' => null,
            ]
        );

        app(PermissionGeneratorService::class)->generate();

        $this->command?->info('CustomerTerminationReasonFeatureSeeder: feature termination_reasons + permission digenerate.');
    }
}
