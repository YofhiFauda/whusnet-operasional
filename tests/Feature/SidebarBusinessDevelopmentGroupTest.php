<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\EffectiveAccessService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\BusinessDevelopmentFeatureSeeder;
use Database\Seeders\CustomerAcquisitionFeatureSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sidebar (2026-09-12, permintaan user) — 4 link lepas (Busdev/Omset
 * Sales/Master Agent/Restriksi Paket) digabung jadi 1 group collapsible
 * "Business Development". Label "Busdev" diganti "Pelanggan Aktif < 30
 * Hari" biar gak redundan dengan judul group.
 */
class SidebarBusinessDevelopmentGroupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(CustomerAcquisitionFeatureSeeder::class);
        $this->seed(BusinessDevelopmentFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_sidebar_shows_single_business_development_group_for_busdev_role(): void
    {
        $role = Role::where('code', 'business_development')->firstOrFail();
        $busdev = User::factory()->create(['status' => 'active', 'role_id' => $role->id]);
        // RefreshDatabase mengulang ID user dari 1 tiap test, tapi cache
        // permission (array store) TIDAK ikut ke-reset — tanpa ini user
        // bisa mewarisi cache permission user lain (lihat catatan
        // CustomerAcquisitionModuleTest).
        app(EffectiveAccessService::class)->clearCache($busdev);

        $response = $this->actingAs($busdev)->get(route('business-development.sales-omset.index'));

        $response->assertOk();
        $response->assertSee('Business Development');
        $response->assertSee('Pelanggan Aktif &lt; 30 Hari', false);
        $response->assertSee('Omset Sales');
        $response->assertSee('Master Agent');
        $response->assertSee('Restriksi Paket');
        $response->assertDontSee('title="Busdev"', false);
    }

    /**
     * Sales TETAP lihat group ini (dia dapat `customer_acquisitions.view`
     * default — "dipantau sales/busdev buat rekap komisi", lihat
     * RolePermissionSeeder), tapi cuma sub-item "Pelanggan Aktif < 30
     * Hari" — 3 sub-item lain (Omset Sales/Master Agent/Restriksi Paket)
     * gak dia punya permission-nya.
     */
    public function test_sales_role_only_sees_the_sub_item_it_has_permission_for(): void
    {
        $role = Role::where('code', 'sales')->firstOrFail();
        $sales = User::factory()->create(['status' => 'active', 'role_id' => $role->id]);
        app(EffectiveAccessService::class)->clearCache($sales);

        $response = $this->actingAs($sales)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Business Development');
        $response->assertSee('Pelanggan Aktif &lt; 30 Hari', false);
        $response->assertDontSee('Omset Sales');
        $response->assertDontSee('Master Agent');
        $response->assertDontSee('Restriksi Paket');
    }
}
