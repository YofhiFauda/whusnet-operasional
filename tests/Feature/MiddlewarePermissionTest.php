<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MiddlewarePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $compiledPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'whusnet-test-views';
        if (! is_dir($compiledPath)) {
            @mkdir($compiledPath, 0777, true);
        }

        config()->set('view.compiled', $compiledPath);

        // Seed roles and permissions for tests
        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        // Register temporary testing routes to verify specific permission requirements
        Route::middleware(['web', 'auth', 'permission:payments.create'])->get('/test-simulasi-pembayaran', function () {
            return response('pembayaran-ok');
        });

        Route::middleware(['web', 'auth', 'permission:customers.detail.devices.update'])->get('/test-simulasi-perangkat-modem', function () {
            return response('device-ok');
        });

        Route::middleware(['web', 'auth', 'permission:invoices.create'])->get('/test-simulasi-tagihan', function () {
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
        // Helpdesk doesn't have 'customers.import.import' permission by default
        $csRole = Role::where('name', 'Helpdesk')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $csRole->id,
        ]);

        $response = $this->actingAs($user)->get('/customers/import');
        $response->assertStatus(403);
    }

    public function test_user_with_required_permission_can_access_route(): void
    {
        // Helpdesk has 'customers.view' permission
        $csRole = Role::where('name', 'Helpdesk')->firstOrFail();
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

        // Access pop management which requires 'pops.view'
        $response = $this->actingAs($user)->get('/master/pop');
        $response->assertStatus(200);
    }

    public function test_admin_has_access_to_all_permissions_like_owner(): void
    {
        $adminRole = Role::where('name', 'Admin')->firstOrFail();
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);

        $this->actingAs($admin)->get('/master/pop')->assertStatus(200);
        $this->actingAs($admin)->get('/users')->assertStatus(200);
        $this->actingAs($admin)->get('/customers/import')->assertStatus(200);
        $this->actingAs($admin)->get('/invoices')->assertStatus(200);
        $this->actingAs($admin)->get('/payments')->assertStatus(200);
    }

    public function test_acceptance_criteria_teknisi_cannot_access_payments(): void
    {
        // Teknisi doesn't have payments.create
        $teknisiRole = Role::where('name', 'Teknisi')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $teknisiRole->id,
        ]);

        // Attempting to open payments simulation route
        $response = $this->actingAs($user)->get('/test-simulasi-pembayaran');
        $response->assertStatus(403);
    }

    public function test_teknisi_cannot_access_invoice_and_billing_routes(): void
    {
        $teknisiRole = Role::where('name', 'Teknisi')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $teknisiRole->id,
        ]);

        $this->actingAs($user)->get('/invoices')->assertStatus(403);
        $this->actingAs($user)->get('/payments')->assertStatus(403);
    }

    public function test_acceptance_criteria_finance_cannot_access_modem_devices(): void
    {
        $financeRole = Role::where('name', 'Helpdesk')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $financeRole->id,
        ]);

        // Attempting to open device management simulation route
        $response = $this->actingAs($user)->get('/test-simulasi-perangkat-modem');
        $response->assertStatus(403);
    }

    public function test_acceptance_criteria_customer_service_cannot_edit_invoice_nominal(): void
    {
        $csRole = Role::where('name', 'Teknisi')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $csRole->id,
        ]);

        // Attempting to open invoice creation simulation route
        $response = $this->actingAs($user)->get('/test-simulasi-tagihan');
        $response->assertStatus(403);
    }
}
