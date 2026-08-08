<?php

namespace Database\Seeders;

use App\Enums\FeatureType;
use App\Models\Feature;
use App\Services\PermissionGeneratorService;
use Illuminate\Database\Seeder;

/**
 * ItemCategoryFeatureSeeder
 *
 * Menanamkan root Feature 'item_categories' (Master Kategori Barang).
 * Permission-nya (item_categories.view/create/update/delete) digenerate otomatis
 * oleh PermissionGeneratorService dari config/rbac.php — bukan hardcode.
 *
 * Feature terpisah dari 'items', bukan sub-feature: mengubah kategori efeknya
 * ke SELURUH data material lintas modul (survey, pemasangan, perangkat pasif
 * pelanggan), sedangkan menambah barang cuma menambah pilihan. Dua kewenangan
 * itu tidak selalu jatuh ke orang yang sama.
 *
 * Idempotent — aman dijalankan ulang.
 * Jalankan: php artisan db:seed --class=ItemCategoryFeatureSeeder
 */
class ItemCategoryFeatureSeeder extends Seeder
{
    public function run(): void
    {
        Feature::updateOrCreate(
            ['code' => 'item_categories'],
            [
                'name' => 'Master Kategori Barang',
                'type' => FeatureType::ROOT,
                'sort_order' => 11,
                'is_active' => true,
                'parent_id' => null,
            ]
        );

        app(PermissionGeneratorService::class)->generate();

        $this->command->info('ItemCategoryFeatureSeeder: feature item_categories + permission digenerate.');
    }
}
