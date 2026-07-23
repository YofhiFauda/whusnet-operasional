<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
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

    public function test_user_without_dashboard_permission_is_blocked(): void
    {
        $emptyRole = Role::create([
            'name' => 'No Dashboard Role',
            'guard_name' => 'web',
            'description' => 'No dashboard access',
        ]);

        $user = User::factory()->create([
            'role_id' => $emptyRole->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get('/');
        $response->assertStatus(403);
    }

    public function test_user_without_dashboard_but_with_own_tasks_permission_is_redirected_to_own_tasks(): void
    {
        $role = Role::create([
            'name' => 'Technician No Dashboard',
            'guard_name' => 'web',
            'description' => 'Technician with own task view',
        ]);

        $taskOwnPerm = Permission::firstOrCreate([
            'code' => 'task.view.own',
        ], [
            'name' => 'Lihat Task Sendiri',
        ]);
        $role->permissions()->attach($taskOwnPerm->id);

        $user = User::factory()->create([
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get('/');
        $response->assertRedirect(route('tasks.own'));
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
        // Check placeholders exist with dynamic names
        $response->assertSee('Tagihan Periode');
        $response->assertSee('Pembayaran Periode');
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
        $response->assertSee('Registrasi Pelanggan');
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
        $csRole = Role::where('name', 'Helpdesk')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $csRole->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get('/');
        $response->assertStatus(200);

        $response->assertSee('PELANGGAN');
        $response->assertSee('List Pelanggan');
        $response->assertSee('Registrasi Pelanggan');
        $response->assertDontSee('Import Pelanggan'); // No permission

        $response->assertSee('Master Data');
        $response->assertSee('Master Data Wilayah'); // view_pop
        $response->assertSee('Master Paket Internet'); // view_packages
        $response->assertSee('Master Status Pelanggan'); // view_packages
    }

    public function test_restricted_user_with_no_permissions_hides_entire_dropdowns(): void
    {
        // Let's create a custom role with zero permissions except dashboard.view
        $emptyRole = Role::create([
            'name' => 'Empty Role',
            'guard_name' => 'web',
            'description' => 'Role with no permissions',
        ]);
        $dashboardViewPerm = Permission::where('code', 'dashboard.view')->first();
        if ($dashboardViewPerm) {
            $emptyRole->permissions()->attach($dashboardViewPerm->id);
        }

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

    public function test_admin_cabang_only_sees_assigned_pop_data(): void
    {
        $adminCabangRole = Role::where('name', 'POP Admin')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $adminCabangRole->id,
            'status' => 'active',
        ]);

        // Create 2 POPs
        $popA = Pop::create([
            'name' => 'POP Sidoarjo',
            'code' => 'SDA',
            'pop_code' => 'SDA',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $popB = Pop::create([
            'name' => 'POP Surabaya',
            'code' => 'SBY',
            'pop_code' => 'SBY',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        // Assign popA to the user
        $user->pops()->attach($popA->id);

        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $adminCabangRole->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create([
            'user_role_scope_id' => $scope->id,
            'pop_id' => $popA->id,
        ]);

        // Clear existing customers to have clean assertion counts
        Customer::query()->delete();

        // Create customer in popA (assigned)
        Customer::create([
            'full_name' => 'Customer POP A',
            'customer_code' => 'C-SDA-000001',
            'phone' => '081122334455',
            'primary_phone' => '081122334455',
            'gender' => 'Laki-laki',
            'pop_id' => $popA->id,
            'status' => 'registered',
            'customer_status' => 'calon_pelanggan',
            'data_completeness_status' => 'draft',
            'registration_date' => '2026-06-01',
        ]);

        // Create customer in popB (unassigned)
        Customer::create([
            'full_name' => 'Customer POP B',
            'customer_code' => 'C-SBY-000001',
            'phone' => '089988776655',
            'primary_phone' => '089988776655',
            'gender' => 'Laki-laki',
            'pop_id' => $popB->id,
            'status' => 'registered',
            'customer_status' => 'calon_pelanggan',
            'data_completeness_status' => 'draft',
            'registration_date' => '2026-06-01',
        ]);

        $response = $this->actingAs($user)->get('/');
        $response->assertStatus(200);

        // Statistics total_customers for popA should be 1, popB's customer should not be included
        $this->assertEquals(1, $response->viewData('stats')['total_customers']);

        // Also check if popA is in the pop list dropdown, but not popB
        $response->assertSee('POP Sidoarjo');
        $response->assertDontSee('POP Surabaya');
    }

    public function test_dashboard_filtering_by_pop(): void
    {
        $ownerRole = Role::where('name', 'Owner')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $ownerRole->id,
            'status' => 'active',
        ]);

        $popA = Pop::create([
            'name' => 'POP Kediri',
            'code' => 'KDR',
            'pop_code' => 'KDR',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $popB = Pop::create([
            'name' => 'POP Malang',
            'code' => 'MLG',
            'pop_code' => 'MLG',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        Customer::query()->delete();

        // Create customer in popA
        Customer::create([
            'full_name' => 'Customer Kediri',
            'customer_code' => 'C-KDR-000001',
            'phone' => '081122334455',
            'primary_phone' => '081122334455',
            'gender' => 'Laki-laki',
            'pop_id' => $popA->id,
            'status' => 'registered',
            'customer_status' => 'calon_pelanggan',
            'data_completeness_status' => 'draft',
            'registration_date' => '2026-06-01',
        ]);

        // Create customer in popB
        Customer::create([
            'full_name' => 'Customer Malang',
            'customer_code' => 'C-MLG-000001',
            'phone' => '089988776655',
            'primary_phone' => '089988776655',
            'gender' => 'Laki-laki',
            'pop_id' => $popB->id,
            'status' => 'registered',
            'customer_status' => 'calon_pelanggan',
            'data_completeness_status' => 'draft',
            'registration_date' => '2026-06-01',
        ]);

        // Request without filter
        $responseAll = $this->actingAs($user)->get('/');
        $this->assertEquals(2, $responseAll->viewData('stats')['total_customers']);

        // Request with pop_id = popA
        $responseFiltered = $this->actingAs($user)->get('/?pop_id='.$popA->id);
        $this->assertEquals(1, $responseFiltered->viewData('stats')['total_customers']);
    }

    public function test_dashboard_filtering_by_period(): void
    {
        $ownerRole = Role::where('name', 'Owner')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $ownerRole->id,
            'status' => 'active',
        ]);

        $pop = Pop::create([
            'name' => 'POP Jember',
            'code' => 'JMR',
            'pop_code' => 'JMR',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        // Create customer first
        $customer = Customer::create([
            'full_name' => 'Customer Period Test',
            'customer_code' => 'C-JMR-000001',
            'phone' => '081234567890',
            'primary_phone' => '081234567890',
            'gender' => 'Laki-laki',
            'pop_id' => $pop->id,
            'status' => 'registered',
            'customer_status' => 'calon_pelanggan',
            'data_completeness_status' => 'draft',
            'registration_date' => '2026-06-01',
        ]);

        $package = InternetPackage::firstOrFail();

        // Create CustomerService for relation
        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'download_speed_snapshot' => '20 Mbps',
            'upload_speed_snapshot' => '20 Mbps',
            'monthly_price' => $package->monthly_price,
            'discount' => 0.00,
            'ppn' => 11.00,
            'total_monthly_bill' => $package->monthly_price * 1.11,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-07-01',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        // Clean invoices
        Invoice::query()->delete();

        // Invoice month 1 (2026-05)
        Invoice::create([
            'invoice_number' => 'INV-0001',
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $package->id,
            'billing_period' => '2026-05',
            'issue_date' => '2026-05-01',
            'due_date' => '2026-05-15',
            'subtotal' => 100000,
            'discount' => 0,
            'ppn' => 11000,
            'total_amount' => 111000,
            'paid_amount' => 0,
            'remaining_amount' => 111000,
            'invoice_status' => 'belum_dibayar',
        ]);

        // Invoice month 2 (2026-06)
        Invoice::create([
            'invoice_number' => 'INV-0002',
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $package->id,
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'subtotal' => 150000,
            'discount' => 0,
            'ppn' => 16500,
            'total_amount' => 166500,
            'paid_amount' => 0,
            'remaining_amount' => 166500,
            'invoice_status' => 'belum_dibayar',
        ]);

        // Request with period 2026-05 to 2026-05
        $responseMay = $this->actingAs($user)->get('/?period_from=2026-05&period_to=2026-05');
        $this->assertEquals(111000, $responseMay->viewData('stats')['total_invoices_amount']);

        // Request with period 2026-06 to 2026-06
        $responseJune = $this->actingAs($user)->get('/?period_from=2026-06&period_to=2026-06');
        $this->assertEquals(166500, $responseJune->viewData('stats')['total_invoices_amount']);

        // Request with range 2026-05 to 2026-06
        $responseBoth = $this->actingAs($user)->get('/?period_from=2026-05&period_to=2026-06');
        $this->assertEquals(277500, $responseBoth->viewData('stats')['total_invoices_amount']);
    }
}
