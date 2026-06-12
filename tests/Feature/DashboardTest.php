<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed initial data
        $this->seed(DatabaseSeeder::class);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_dashboard(): void
    {
        $ownerRole = Role::where('name', 'Owner')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $ownerRole->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get('/');
        $response->assertStatus(200);
        $response->assertSee('Dashboard');
        $response->assertSee('Total Pelanggan');
        $response->assertSee('Data Belum Lengkap');
        // Check placeholders exist
        $response->assertSee('Tagihan Bulan Ini');
        $response->assertSee('Pembayaran Bulan Ini');
        $response->assertSee('Total Tunggakan');
    }

    public function test_owner_sees_all_sidebar_menus(): void
    {
        $ownerRole = Role::where('name', 'Owner')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $ownerRole->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get('/');
        $response->assertStatus(200);

        // Owner has all permissions, so should see everything
        $response->assertSee('PELANGGAN');
        $response->assertSee('List Pelanggan');
        $response->assertSee('Input Pelanggan');
        $response->assertSee('Import Pelanggan');
        $response->assertSee('Master Data');
        $response->assertSee('Master Data Wilayah');
        $response->assertSee('Master Paket Internet');
        $response->assertSee('Master Status Pelanggan');
    }

    public function test_limited_user_sidebar_visibility(): void
    {
        // CS Role permissions: view_pop, view_packages, create_customers, edit_customers, view_customers
        // CS role has NO 'import_customers' permission by default, but has view/create
        $csRole = Role::where('name', 'Customer Service')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $csRole->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get('/');
        $response->assertStatus(200);

        $response->assertSee('PELANGGAN');
        $response->assertSee('List Pelanggan');
        $response->assertSee('Input Pelanggan');
        $response->assertDontSee('Import Pelanggan'); // No permission

        $response->assertSee('Master Data');
        $response->assertSee('Master Data Wilayah'); // view_pop
        $response->assertSee('Master Paket Internet'); // view_packages
        $response->assertSee('Master Status Pelanggan'); // view_packages
    }

    public function test_restricted_user_with_no_permissions_hides_entire_dropdowns(): void
    {
        // Let's create a custom role with zero permissions
        $emptyRole = Role::create([
            'name' => 'Empty Role',
            'guard_name' => 'web',
            'description' => 'Role with no permissions',
        ]);

        $user = User::factory()->create([
            'role_id' => $emptyRole->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get('/');
        $response->assertStatus(200);

        // Should not see Pelanggan or Master Data dropdowns at all
        $response->assertDontSee('PELANGGAN');
        $response->assertDontSee('Master Data');
        
        // Quick Actions should show empty state message
        $response->assertSee('Tidak ada akses cepat yang tersedia untuk peran Anda.');
    }
}
