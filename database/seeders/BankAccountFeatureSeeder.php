<?php

namespace Database\Seeders;

use App\Enums\FeatureType;
use App\Models\Feature;
use App\Services\PermissionGeneratorService;
use Illuminate\Database\Seeder;

/**
 * BankAccountFeatureSeeder (ADHOC-95)
 *
 * Menanamkan root Feature 'master_rekening' (Master Rekening Bank).
 * Permission-nya (master_rekening.view/create/update) digenerate otomatis
 * oleh PermissionGeneratorService dari config/rbac.php — bukan hardcode.
 * Owner dapat lewat `*`; role lain diatur di Role Matrix sesuai kebutuhan
 * operasional (keputusan rancangan: tidak dikunci ke role tertentu).
 *
 * Idempotent — aman dijalankan ulang.
 * Jalankan: php artisan db:seed --class=BankAccountFeatureSeeder
 */
class BankAccountFeatureSeeder extends Seeder
{
    public function run(): void
    {
        Feature::updateOrCreate(
            ['code' => 'master_rekening'],
            [
                'name' => 'Master Rekening Bank',
                'type' => FeatureType::ROOT,
                'sort_order' => 13,
                'is_active' => true,
                'parent_id' => null,
            ]
        );

        app(PermissionGeneratorService::class)->generate();

        $this->command?->info('BankAccountFeatureSeeder: feature master_rekening + permission digenerate.');
    }
}
