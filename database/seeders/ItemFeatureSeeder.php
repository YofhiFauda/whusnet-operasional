<?php

namespace Database\Seeders;

use App\Enums\FeatureType;
use App\Models\Feature;
use App\Services\PermissionGeneratorService;
use Illuminate\Database\Seeder;

/**
 * ItemFeatureSeeder
 *
 * Menanamkan root Feature 'items' (Master Barang/Material). Permission-nya
 * (items.view/create/update/delete) digenerate otomatis oleh
 * PermissionGeneratorService dari config/rbac.php — bukan hardcode.
 *
 * Akses CRUD sementara cuma owner (lewat wildcard `*` di RolePermissionSeeder);
 * role lain ditambahkan lewat UI Role Management, mengikuti pola
 * TicketIssueCategoryFeatureSeeder.
 *
 * Idempotent — aman dijalankan ulang.
 * Jalankan: php artisan db:seed --class=ItemFeatureSeeder
 */
class ItemFeatureSeeder extends Seeder
{
    public function run(): void
    {
        Feature::updateOrCreate(
            ['code' => 'items'],
            [
                'name' => 'Master Barang/Material',
                'type' => FeatureType::ROOT,
                'sort_order' => 10,
                'is_active' => true,
                'parent_id' => null,
            ]
        );

        app(PermissionGeneratorService::class)->generate();

        $this->command->info('ItemFeatureSeeder: feature items + permission digenerate.');
    }
}
