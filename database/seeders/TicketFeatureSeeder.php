<?php

namespace Database\Seeders;

use App\Enums\FeatureType;
use App\Models\Feature;
use App\Services\PermissionGeneratorService;
use Illuminate\Database\Seeder;

/**
 * TicketFeatureSeeder
 *
 * Menanamkan root Feature 'tickets' (Ticketing internal perusahaan).
 * Permission-nya (tickets.view, tickets.create) digenerate otomatis oleh
 * PermissionGeneratorService dari config/rbac.php. Assignment ke role diatur
 * di RolePermissionSeeder (source of truth permission per role), jalankan
 * RolePermissionSeeder lagi setelah seeder ini biar ke-sync.
 *
 * Idempotent — aman dijalankan ulang.
 * Jalankan: php artisan db:seed --class=TicketFeatureSeeder
 */
class TicketFeatureSeeder extends Seeder
{
    public function run(): void
    {
        Feature::updateOrCreate(
            ['code' => 'tickets'],
            [
                'name' => 'Ticketing',
                'type' => FeatureType::ROOT,
                'sort_order' => 8,
                'is_active' => true,
                'parent_id' => null,
            ]
        );

        app(PermissionGeneratorService::class)->generate();

        $this->command->info('TicketFeatureSeeder: feature tickets + permission digenerate. Jalankan RolePermissionSeeder biar ke-assign ke role.');
    }
}
