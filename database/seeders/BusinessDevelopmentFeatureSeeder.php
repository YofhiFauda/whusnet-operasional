<?php

namespace Database\Seeders;

use App\Enums\FeatureType;
use App\Models\Feature;
use App\Services\PermissionGeneratorService;
use Illuminate\Database\Seeder;

/**
 * BusinessDevelopmentFeatureSeeder
 *
 * Menanamkan 3 Feature ROOT baru untuk modul Business Development
 * (2026-09-12): `agents` (master mitra Agent), `package_restrictions`
 * (restriksi paket per role — Skema 1), `sales_omset_dashboard` (dashboard
 * omset Sales — Skema 2). Permission-nya digenerate dari config/rbac.php
 * oleh PermissionGeneratorService, assignment ke role diatur di
 * RolePermissionSeeder (jalankan lagi setelah seeder ini, pola sama
 * CustomerAcquisitionFeatureSeeder).
 *
 * Idempotent — aman dijalankan ulang.
 * Jalankan: php artisan db:seed --class=BusinessDevelopmentFeatureSeeder
 */
class BusinessDevelopmentFeatureSeeder extends Seeder
{
    public function run(): void
    {
        Feature::updateOrCreate(
            ['code' => 'agents'],
            [
                'name' => 'Master Agent',
                'type' => FeatureType::ROOT,
                'sort_order' => 25,
                'is_active' => true,
                'parent_id' => null,
            ]
        );

        Feature::updateOrCreate(
            ['code' => 'package_restrictions'],
            [
                'name' => 'Restriksi Paket per Role',
                'type' => FeatureType::ROOT,
                'sort_order' => 26,
                'is_active' => true,
                'parent_id' => null,
            ]
        );

        Feature::updateOrCreate(
            ['code' => 'sales_omset_dashboard'],
            [
                'name' => 'Dashboard Omset Sales',
                'type' => FeatureType::ROOT,
                'sort_order' => 27,
                'is_active' => true,
                'parent_id' => null,
            ]
        );

        app(PermissionGeneratorService::class)->generate();

        $this->command->info('BusinessDevelopmentFeatureSeeder: feature agents/package_restrictions/sales_omset_dashboard + permission digenerate.');
    }
}
