<?php

namespace Database\Seeders;

use App\Enums\FeatureType;
use App\Models\Feature;
use App\Services\PermissionGeneratorService;
use Illuminate\Database\Seeder;

/**
 * CustomerBalanceFeatureSeeder (ADHOC-92)
 *
 * Menanamkan root Feature 'customer_balance' (Saldo Pelanggan) — cuma
 * `view`, sesuai rancangan §4.3 (penyesuaian saldo manual di luar scope).
 * Permission-nya (customer_balance.view) digenerate otomatis oleh
 * PermissionGeneratorService dari config/rbac.php — bukan hardcode.
 * Owner dapat lewat `*`; admin & pop_admin dapat `.view` lewat
 * RolePermissionSeeder (§4.3: sales & teknisi tidak dapat — aturan keuangan).
 *
 * Idempotent — aman dijalankan ulang.
 * Jalankan: php artisan db:seed --class=CustomerBalanceFeatureSeeder
 */
class CustomerBalanceFeatureSeeder extends Seeder
{
    public function run(): void
    {
        Feature::updateOrCreate(
            ['code' => 'customer_balance'],
            [
                'name' => 'Saldo Pelanggan',
                'type' => FeatureType::ROOT,
                'sort_order' => 14,
                'is_active' => true,
                'parent_id' => null,
            ]
        );

        app(PermissionGeneratorService::class)->generate();

        $this->command?->info('CustomerBalanceFeatureSeeder: feature customer_balance + permission digenerate.');
    }
}
