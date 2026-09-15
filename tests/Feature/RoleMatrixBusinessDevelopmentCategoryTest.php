<?php

namespace Tests\Feature;

use App\Models\Role;
use Database\Seeders\ActionSeeder;
use Database\Seeders\BusinessDevelopmentFeatureSeeder;
use Database\Seeders\CustomerAcquisitionFeatureSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sebelumnya `agents`/`package_restrictions`/`sales_omset_dashboard` (dan
 * `customer_acquisitions` lama) gak punya kategori sendiri di Permission
 * Matrix — kebuang ke bucket "Modul Tambahan Lainnya" tanpa label ramah
 * (laporan user, 2026-09-12). Sekarang dikelompokkan "Business Development".
 */
class RoleMatrixBusinessDevelopmentCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_matrix_page_renders_with_busdev_category(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(CustomerAcquisitionFeatureSeeder::class);
        $this->seed(BusinessDevelopmentFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->loginAsAdmin();

        $role = Role::where('code', 'business_development')->firstOrFail();
        $response = $this->get(route('roles.matrix', $role));

        $response->assertOk();
        $response->assertSee('Business Development');
        $response->assertSee('Restriksi Paket per Role');
        $response->assertSee('Master Agent');
        $response->assertSee('Dashboard Omset Sales');
    }
}
