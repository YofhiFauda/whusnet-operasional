<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\District;
use App\Models\InternetPackage;
use App\Models\Pop;
use App\Models\Role;
use App\Models\SubscriptionStatus;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\Village;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\InternetPackageSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PonorogoRegionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SubscriptionStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerToggleSuspendTest extends TestCase
{
    use RefreshDatabase;

    protected Pop $pop;

    protected InternetPackage $package;

    protected User $helpdeskUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SubscriptionStatusSeeder::class);
        $this->seed(InternetPackageSeeder::class);
        $this->seed(PonorogoRegionSeeder::class);

        $this->pop = Pop::create([
            'name' => 'POP Ponorogo Kota',
            'code' => 'PNO-01',
            'pop_code' => 'PNO',
            'cid_prefix' => 'P',
            'type' => 'branch',
            'status' => 'active',
        ]);

        $this->package = InternetPackage::first();

        // Helpdesk SENGAJA, bukan Admin — role Admin tidak memegang
        // `customers.isolir` (lihat RolePermissionSeeder), jadi tidak cocok
        // dipakai memverifikasi akses isolir.
        $helpdeskRole = Role::where('name', 'Helpdesk')->firstOrFail();
        $this->helpdeskUser = User::factory()->create([
            'role_id' => $helpdeskRole->id,
            'status' => 'active',
        ]);

        UserRoleScope::create([
            'user_id' => $this->helpdeskUser->id,
            'role_id' => $this->helpdeskUser->role_id,
            'scope_type' => 'all_pop',
        ]);
    }

    protected function createCustomer(string $status = 'active', string $serviceStatus = 'aktif'): Customer
    {
        $city = City::query()->where('name', 'Ponorogo')->firstOrFail();
        $district = District::query()->where('city_id', $city->id)->firstOrFail();
        $village = Village::query()->where('district_id', $district->id)->firstOrFail();

        $customer = Customer::create([
            'customer_code' => 'RQ000001',
            'cid' => 'P00RQ000001',
            'full_name' => 'Ahmad Pelanggan',
            'gender' => 'Laki-laki',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => $status,
            'pop_id' => $this->pop->id,
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. Diponegoro No. 10',
            'data_completeness_status' => 'siap_billing',
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Diponegoro No. 10',
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
        ]);

        CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $this->package->id,
            'package_name_snapshot' => $this->package->name,
            'service_status' => $serviceStatus,
            'billing_status' => 'active',
            'monthly_price' => $this->package->monthly_price,
            'total_monthly_bill' => $this->package->monthly_price,
            'billing_cycle' => 'monthly',
        ]);

        return $customer;
    }

    public function test_can_suspend_active_customer_via_ajax(): void
    {
        $customer = $this->createCustomer('active', 'aktif');

        $response = $this->actingAs($this->helpdeskUser)
            ->postJson(route('customers.toggle-suspend', $customer->id), [
                'note' => 'Menunggak 2 bulan',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'status' => 'suspended',
                'raw_status' => 'suspended',
                'status_label' => 'Suspended',
            ]);

        $customer->refresh();
        $this->assertEquals('suspended', $customer->status);
        $this->assertEquals('isolir', $customer->customerService->service_status);

        $this->assertDatabaseHas('customer_status_logs', [
            'customer_id' => $customer->id,
            'from_status' => 'active',
            'to_status' => 'suspended',
            'changed_by' => $this->helpdeskUser->id,
            'note' => 'Menunggak 2 bulan',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'customers',
            'action' => 'isolir',
            'auditable_type' => Customer::class,
            'auditable_id' => $customer->id,
        ]);
    }

    public function test_can_reactivate_suspended_customer_via_ajax(): void
    {
        $customer = $this->createCustomer('suspended', 'isolir');

        $response = $this->actingAs($this->helpdeskUser)
            ->postJson(route('customers.toggle-suspend', $customer->id), [
                'note' => 'Sudah melakukan pembayaran',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'status' => 'active',
                'raw_status' => 'active',
                'status_label' => 'Active',
            ]);

        $customer->refresh();
        $this->assertEquals('active', $customer->status);
        $this->assertEquals('aktif', $customer->customerService->service_status);

        $this->assertDatabaseHas('customer_status_logs', [
            'customer_id' => $customer->id,
            'from_status' => 'suspended',
            'to_status' => 'active',
            'changed_by' => $this->helpdeskUser->id,
            'note' => 'Sudah melakukan pembayaran',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'customers',
            'action' => 'aktivasi_kembali',
            'auditable_type' => Customer::class,
            'auditable_id' => $customer->id,
        ]);
    }

    /**
     * Bug user 2026-11-20: badge List Pelanggan tampil "ISOLIR" (optimistik,
     * hardcode literal lama) lalu balik jadi "Suspended" setelah refresh
     * (server-render pakai nama master SubscriptionStatus). status_label
     * respons toggle HARUS == nama master, sumber yang sama dengan
     * $customer->subscriptionStatus->name yang dipakai _status.blade.php,
     * supaya optimistik & hasil refresh selalu sinkron.
     */
    public function test_status_label_matches_subscription_status_master_name(): void
    {
        $customer = $this->createCustomer('active', 'aktif');

        $response = $this->actingAs($this->helpdeskUser)
            ->postJson(route('customers.toggle-suspend', $customer->id));

        $expectedLabel = SubscriptionStatus::where('code', 'suspended')->value('name');
        $response->assertOk()->assertJson(['status_label' => $expectedLabel]);

        $customer->refresh();
        $this->assertSame($expectedLabel, $customer->subscriptionStatus->name);
    }

    public function test_cannot_toggle_suspend_for_non_active_or_suspended_customer(): void
    {
        $customer = $this->createCustomer('waiting_survey', 'pending');

        $response = $this->actingAs($this->helpdeskUser)
            ->postJson(route('customers.toggle-suspend', $customer->id));

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $customer->refresh();
        $this->assertEquals('waiting_survey', $customer->status);
    }

    public function test_user_without_pop_scope_access_is_forbidden(): void
    {
        $otherPop = Pop::create([
            'name' => 'POP Siman',
            'code' => 'SMN-01',
            'pop_code' => 'SMN',
            'type' => 'branch',
            'status' => 'active',
        ]);

        $restrictedAdmin = User::factory()->create([
            'role_id' => $this->helpdeskUser->role_id,
            'status' => 'active',
        ]);

        $scope = UserRoleScope::create([
            'user_id' => $restrictedAdmin->id,
            'role_id' => $restrictedAdmin->role_id,
            'scope_type' => 'selected_pop',
        ]);
        $scope->targets()->create(['pop_id' => $otherPop->id]);

        $customer = $this->createCustomer('active', 'aktif');

        $response = $this->actingAs($restrictedAdmin)
            ->postJson(route('customers.toggle-suspend', $customer->id));

        $response->assertStatus(403);
    }

    public function test_sales_cannot_isolir_or_edit_customer(): void
    {
        // Sales SENGAJA tanpa `customers.isolir` maupun `customers.update`
        // (RolePermissionSeeder, 2026-11-10) — isolir & edit data pelanggan
        // bukan kewenangan Sales.
        $salesRole = Role::where('name', 'Sales')->firstOrFail();
        $salesUser = User::factory()->create([
            'role_id' => $salesRole->id,
            'status' => 'active',
        ]);

        UserRoleScope::create([
            'user_id' => $salesUser->id,
            'role_id' => $salesUser->role_id,
            'scope_type' => 'all_pop',
        ]);

        $customer = $this->createCustomer('active', 'aktif');

        $this->actingAs($salesUser)
            ->postJson(route('customers.toggle-suspend', $customer->id))
            ->assertStatus(403);

        $this->actingAs($salesUser)
            ->get(route('customers.edit', $customer->id))
            ->assertStatus(403);
    }
}
