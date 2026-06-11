<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MiddlewarePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles and permissions for tests
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        // Register temporary testing routes to verify specific permission requirements
        Route::middleware(['web', 'auth', 'permission:create_payments'])->get('/test-simulasi-pembayaran', function () {
            return response('pembayaran-ok');
        });

        Route::middleware(['web', 'auth', 'permission:fill_device'])->get('/test-simulasi-perangkat-modem', function () {
            return response('device-ok');
        });

        Route::middleware(['web', 'auth', 'permission:create_invoices'])->get('/test-simulasi-tagihan', function () {
            return response('tagihan-ok');
        });
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/customers');
        $response->assertRedirect('/login');
    }

    public function test_user_without_required_permission_gets_403_forbidden(): void
    {
        // Customer Service doesn't have 'import_customers' permission by default
        $csRole = Role::where('name', 'Customer Service')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $csRole->id,
        ]);

        $response = $this->actingAs($user)->get('/customers/import');
        $response->assertStatus(403);
    }

    public function test_user_with_required_permission_can_access_route(): void
    {
        // Customer Service has 'view_customers' permission
        $csRole = Role::where('name', 'Customer Service')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $csRole->id,
        ]);

        $response = $this->actingAs($user)->get('/customers');
        $response->assertStatus(200);
    }

    public function test_owner_and_admin_pusat_have_access_to_all_permissions(): void
    {
        $ownerRole = Role::where('name', 'Owner')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $ownerRole->id,
        ]);

        // Access pop management which requires 'view_pop'
        $response = $this->actingAs($user)->get('/master/wilayah');
        $response->assertStatus(200);
    }

    public function test_acceptance_criteria_teknisi_cannot_access_payments(): void
    {
        // CS/Teknisi/any role that doesn't have create_payments
        $teknisiRole = Role::where('name', 'Teknisi')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $teknisiRole->id,
        ]);

        // Attempting to open payments simulation route
        $response = $this->actingAs($user)->get('/test-simulasi-pembayaran');
        $response->assertStatus(403);
    }

    public function test_acceptance_criteria_finance_cannot_access_modem_devices(): void
    {
        $financeRole = Role::where('name', 'Finance/Kasir')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $financeRole->id,
        ]);

        // Attempting to open device management simulation route
        $response = $this->actingAs($user)->get('/test-simulasi-perangkat-modem');
        $response->assertStatus(403);
    }

    public function test_acceptance_criteria_customer_service_cannot_edit_invoice_nominal(): void
    {
        $csRole = Role::where('name', 'Customer Service')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $csRole->id,
        ]);

        // Attempting to open invoice creation simulation route
        $response = $this->actingAs($user)->get('/test-simulasi-tagihan');
        $response->assertStatus(403);
    }
}
