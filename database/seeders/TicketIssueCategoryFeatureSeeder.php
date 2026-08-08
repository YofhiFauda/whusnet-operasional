<?php

namespace Database\Seeders;

use App\Enums\FeatureType;
use App\Models\Feature;
use App\Services\PermissionGeneratorService;
use Illuminate\Database\Seeder;

/**
 * TicketIssueCategoryFeatureSeeder
 *
 * Menanamkan root Feature 'ticket_issue_categories' (Master Issue/Kategori
 * Keluhan). Permission-nya (ticket_issue_categories.view/create/update/delete)
 * digenerate otomatis oleh PermissionGeneratorService dari config/rbac.php.
 *
 * Akses CRUD sementara cuma owner (lewat wildcard `*` di RolePermissionSeeder)
 * — role lain ditambahkan manual lewat UI Role Management nanti, JANGAN
 * di-hardcode ke RolePermissionSeeder untuk feature ini dulu. Lihat
 * docs/plan/RANCANGAN_MASTER_ISSUE_TICKETING.md bagian B & G.
 *
 * Idempotent — aman dijalankan ulang.
 * Jalankan: php artisan db:seed --class=TicketIssueCategoryFeatureSeeder
 */
class TicketIssueCategoryFeatureSeeder extends Seeder
{
    public function run(): void
    {
        Feature::updateOrCreate(
            ['code' => 'ticket_issue_categories'],
            [
                'name' => 'Master Issue/Kategori Keluhan',
                'type' => FeatureType::ROOT,
                'sort_order' => 9,
                'is_active' => true,
                'parent_id' => null,
            ]
        );

        app(PermissionGeneratorService::class)->generate();

        $this->command->info('TicketIssueCategoryFeatureSeeder: feature ticket_issue_categories + permission digenerate.');
    }
}
