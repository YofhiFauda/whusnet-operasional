<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Pop;
use App\Models\User;
use App\Models\Role;
use App\Models\InternetPackage;
use App\Models\SubscriptionStatus;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerListTest extends TestCase
{
    use RefreshDatabase;

    private Pop $defaultPop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->defaultPop = Pop::create([
            'code' => 'DFT',
            'pop_code' => 'DFT',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'Default POP',
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    public function test_owner_can_see_all_customers(): void
    {
        $owner = User::where('email', 'owner@whusnet.net')->firstOrFail();
        $this->loginAsAdmin($owner);

        // Create two POPs
        $pop1 = Pop::create([
            'code' => 'PON1',
            'pop_code' => 'PON1',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Ponorogo 1',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $pop2 = Pop::create([
            'code' => 'PON2',
            'pop_code' => 'PON2',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Ponorogo 2',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        // Create a customer in each POP
        $c1 = Customer::create([
            'customer_code' => 'C-PON1-000001',
            'full_name' => 'Customer Satu',
            'primary_phone' => '08122222222',
            'phone' => '08122222222',
            'registration_date' => '2026-06-11',
            'pop_id' => $pop1->id,
            'status' => 'suspended',
            'data_completeness_status' => 'draft',
        ]);

        $c2 = Customer::create([
            'customer_code' => 'C-PON2-000001',
            'full_name' => 'Customer Dua',
            'primary_phone' => '08133333333',
            'phone' => '08133333333',
            'registration_date' => '2026-06-11',
            'pop_id' => $pop2->id,
            'status' => 'active',
            'data_completeness_status' => 'lengkap',
        ]);

        $response = $this->get('/customers?status=');

        $response->assertStatus(200);
        $response->assertSee('Customer Satu');
        $response->assertSee('Customer Dua');
    }

    public function test_branch_admin_only_sees_assigned_pop_customers(): void
    {
        // Get POP Admin role
        $branchAdminRole = Role::where('name', 'POP Admin')->firstOrFail();

        // Create Admin Cabang User
        $adminCabang = User::factory()->create([
            'status' => 'active',
            'role_id' => $branchAdminRole->id,
        ]);

        // Create two POPs
        $pop1 = Pop::create([
            'code' => 'PON1',
            'pop_code' => 'PON1',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Ponorogo 1',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $pop2 = Pop::create([
            'code' => 'PON2',
            'pop_code' => 'PON2',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Ponorogo 2',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        // Assign Admin to POP 1 only
        $adminCabang->pops()->attach($pop1->id);

        $scope = \App\Models\UserRoleScope::create([
            'user_id' => $adminCabang->id,
            'role_id' => $branchAdminRole->id,
            'scope_type' => \App\Enums\ScopeType::SELECTED_POP,
        ]);
        
        \App\Models\UserRoleScopeTarget::create([
            'user_role_scope_id' => $scope->id,
            'pop_id' => $pop1->id,
        ]);

        // Create customers
        $c1 = Customer::create([
            'customer_code' => 'C-PON1-000001',
            'full_name' => 'Customer Ponorogo Satu',
            'primary_phone' => '08122222222',
            'phone' => '08122222222',
            'registration_date' => '2026-06-11',
            'pop_id' => $pop1->id,
            'status' => 'suspended',
            'data_completeness_status' => 'draft',
        ]);

        $c2 = Customer::create([
            'customer_code' => 'C-PON2-000001',
            'full_name' => 'Customer Ponorogo Dua',
            'primary_phone' => '08133333333',
            'phone' => '08133333333',
            'registration_date' => '2026-06-11',
            'pop_id' => $pop2->id,
            'status' => 'active',
            'data_completeness_status' => 'lengkap',
        ]);

        $this->actingAs($adminCabang);

        $response = $this->get('/customers?status=');

        $response->assertStatus(200);
        $response->assertSee('Customer Ponorogo Satu');
        $response->assertDontSee('Customer Ponorogo Dua');
    }

    public function test_customer_list_can_be_filtered_by_search_query(): void
    {
        $owner = User::where('email', 'owner@whusnet.net')->firstOrFail();
        $this->loginAsAdmin($owner);

        $pop = $this->defaultPop;

        $c1 = Customer::create([
            'customer_code' => 'C-PON-000001',
            'old_customer_id' => 'PE-LEGACY-0001',
            'full_name' => 'Ahmad Subarjo',
            'primary_phone' => '08111111111',
            'phone' => '08111111111',
            'registration_date' => '2026-06-11',
            'pop_id' => $pop->id,
            'status' => 'suspended',
            'identity_number' => '3502999999999999',
            'data_completeness_status' => 'draft',
        ]);

        $c2 = Customer::create([
            'customer_code' => 'C-PON-000002',
            'full_name' => 'Bambang Tri',
            'primary_phone' => '08222222222',
            'phone' => '08222222222',
            'registration_date' => '2026-06-11',
            'pop_id' => $pop->id,
            'status' => 'active',
            'data_completeness_status' => 'lengkap',
        ]);

        // Search by name
        $response = $this->get('/customers?search=Subarjo&status=');
        $response->assertSee('Ahmad Subarjo');
        $response->assertDontSee('Bambang Tri');

        // Search by phone
        $response = $this->get('/customers?search=08222222222&status=');
        $response->assertSee('Bambang Tri');
        $response->assertDontSee('Ahmad Subarjo');

        // Search by code
        $response = $this->get('/customers?search=C-PON-000001&status=');
        $response->assertSee('Ahmad Subarjo');
        $response->assertDontSee('Bambang Tri');

        // Search by NIK
        $response = $this->get('/customers?search=3502999999999999&status=');
        $response->assertSee('Ahmad Subarjo');
        $response->assertDontSee('Bambang Tri');

        // Search by legacy customer ID
        $response = $this->get('/customers?search=PE-LEGACY-0001&status=');
        $response->assertSee('Ahmad Subarjo');
        $response->assertDontSee('Bambang Tri');
    }

    public function test_customer_list_can_be_filtered_by_pop(): void
    {
        $owner = User::where('email', 'owner@whusnet.net')->firstOrFail();
        $this->loginAsAdmin($owner);

        $pop1 = Pop::create([
            'code' => 'PON1',
            'pop_code' => 'PON1',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Ponorogo 1',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $pop2 = Pop::create([
            'code' => 'PON2',
            'pop_code' => 'PON2',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Ponorogo 2',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $c1 = Customer::create([
            'customer_code' => 'C-PON1-000001',
            'full_name' => 'Ahmad Subarjo',
            'primary_phone' => '08111111111',
            'phone' => '08111111111',
            'registration_date' => '2026-06-11',
            'pop_id' => $pop1->id,
            'status' => 'suspended',
            'data_completeness_status' => 'draft',
        ]);

        $c2 = Customer::create([
            'customer_code' => 'C-PON2-000001',
            'full_name' => 'Bambang Tri',
            'primary_phone' => '08222222222',
            'phone' => '08222222222',
            'registration_date' => '2026-06-11',
            'pop_id' => $pop2->id,
            'status' => 'active',
            'data_completeness_status' => 'lengkap',
        ]);

        $response = $this->get("/customers?pop_id={$pop1->id}&status=");
        $response->assertSee('Ahmad Subarjo');
        $response->assertDontSee('Bambang Tri');

        $response = $this->get("/customers?pop_id={$pop2->id}&status=");
        $response->assertSee('Bambang Tri');
        $response->assertDontSee('Ahmad Subarjo');
    }

    public function test_customer_list_can_be_filtered_by_completeness_status(): void
    {
        $owner = User::where('email', 'owner@whusnet.net')->firstOrFail();
        $this->loginAsAdmin($owner);

        $pop = $this->defaultPop;

        $c1 = Customer::create([
            'customer_code' => 'C-PON-000001',
            'full_name' => 'Ahmad Subarjo',
            'primary_phone' => '08111111111',
            'phone' => '08111111111',
            'registration_date' => '2026-06-11',
            'pop_id' => $pop->id,
            'status' => 'suspended',
            'data_completeness_status' => 'draft',
        ]);

        $city = \App\Models\City::firstOrFail();
        $district = \App\Models\District::where('city_id', $city->id)->firstOrFail();
        $village = \App\Models\Village::where('district_id', $district->id)->firstOrFail();
        $package = \App\Models\InternetPackage::firstOrFail();

        $c2 = Customer::create([
            'customer_code' => 'C-PON-000002',
            'full_name' => 'Bambang Tri',
            'primary_phone' => '08222222222',
            'phone' => '08222222222',
            'registration_date' => '2026-06-11',
            'pop_id' => $pop->id,
            'status' => 'active',
            'address' => 'Jl. Diponegoro No. 45',
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'internet_package_id' => $package->id,
        ]);

        $c2->customerAddress()->create([
            'full_address' => $c2->address,
            'city_id' => $c2->city_id,
            'district_id' => $c2->district_id,
            'village_id' => $c2->village_id,
        ]);

        $c2->customerService()->create([
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'monthly_price' => $package->monthly_price,
            'discount' => 0,
            'ppn' => 11,
            'total_monthly_bill' => $package->monthly_price * 1.11,
            'activation_date' => $c2->registration_date,
            'due_date' => \Carbon\Carbon::parse($c2->registration_date)->addMonth(),
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        $response = $this->get("/customers?completeness_status=draft&status=");
        $response->assertSee('Ahmad Subarjo');
        $response->assertDontSee('Bambang Tri');

        $response = $this->get("/customers?completeness_status=lengkap&status=");
        $response->assertSee('Bambang Tri');
        $response->assertDontSee('Ahmad Subarjo');
    }
}
