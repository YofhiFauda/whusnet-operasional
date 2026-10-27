<?php

namespace Database\Seeders;

use App\Enums\FeatureType;
use App\Models\Feature;
use App\Services\PermissionGeneratorService;
use Illuminate\Database\Seeder;

/**
 * CustomerCableCrossingFeatureSeeder (ADHOC-205)
 *
 * Menanamkan root Feature 'customer_cable_crossings' (Kabel Melintang Jalan) —
 * cuma `view` untuk daftar pemantauan. Permission-nya digenerate dari
 * config/rbac.php. Owner dapat lewat `*`; admin & pop_admin dapat lewat
 * RolePermissionSeeder (dibatasi POP scope).
 *
 * Idempotent — aman dijalankan ulang.
 * Jalankan: php artisan db:seed --class=CustomerCableCrossingFeatureSeeder
 */
class CustomerCableCrossingFeatureSeeder extends Seeder
{
    public function run(): void
    {
        Feature::updateOrCreate(
            ['code' => 'customer_cable_crossings'],
            [
                'name' => 'Kabel Melintang Jalan',
                'type' => FeatureType::ROOT,
                'sort_order' => 15,
                'is_active' => true,
                'parent_id' => null,
            ]
        );

        app(PermissionGeneratorService::class)->generate();

        $this->command?->info('CustomerCableCrossingFeatureSeeder: feature customer_cable_crossings + permission digenerate.');
    }
}
