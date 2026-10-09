<?php

namespace Database\Seeders;

use App\Enums\FeatureType;
use App\Models\Feature;
use App\Services\PermissionGeneratorService;
use Illuminate\Database\Seeder;

/**
 * BillingWaiverFeatureSeeder (ADHOC-87)
 *
 * Menanamkan root Feature 'billing_waivers' (Pembebasan Tagihan Periode —
 * Request Putus Langganan + Cuti Berlangganan). Permission-nya
 * (billing_waivers.create/delete) digenerate otomatis oleh
 * PermissionGeneratorService dari config/rbac.php.
 *
 * Idempoten — aman dijalankan ulang.
 * Jalankan: php artisan db:seed --class=BillingWaiverFeatureSeeder
 */
class BillingWaiverFeatureSeeder extends Seeder
{
    public function run(): void
    {
        Feature::updateOrCreate(
            ['code' => 'billing_waivers'],
            [
                'name' => 'Pembebasan Tagihan Periode',
                'type' => FeatureType::ROOT,
                'sort_order' => 30,
                'is_active' => true,
                'parent_id' => null,
            ]
        );

        app(PermissionGeneratorService::class)->generate();

        $this->command?->info('BillingWaiverFeatureSeeder: feature billing_waivers + permission digenerate.');
    }
}
