<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\District;
use App\Models\InternetPackage;
use App\Models\Pop;
use App\Models\Invoice;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Role;
use App\Models\Village;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->seed(\Database\Seeders\SubscriptionStatusSeeder::class);
        $this->seed(\Database\Seeders\InternetPackageSeeder::class);
        $this->seed(\Database\Seeders\PonorogoRegionSeeder::class);
    }

    /**
     * Helper to create a complete customer for testing.
     */
    protected function createTestCustomer(Pop $pop, InternetPackage $package, string $status = 'active', string $completeness = 'siap_billing'): Customer
    {
        $city = City::query()->where('name', 'Ponorogo')->firstOrFail();
        $district = District::query()->where('city_id', $city->id)->firstOrFail();
        $village = Village::query()->where('district_id', $district->id)->firstOrFail();

        $customer = Customer::create([
            'customer_code' => 'WHUS-2026-0001',
            'full_name' => 'Budi Santoso',
            'gender' => 'Laki-laki',
            'phone' => '081234567890',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => $status,
            'customer_status' => $status === 'active' ? 'aktif' : 'calon_pelanggan',
            'data_completeness_status' => $completeness,
            'pop_id' => $pop->id,
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'internet_package_id' => $package->id,
            'address' => 'Jl. Raya Ponorogo No. 12',
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Raya Ponorogo No. 12',
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'province' => 'Jawa Timur',
            'city' => 'Ponorogo',
            'district' => $district->name,
            'village' => $village->name,
        ]);

        CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'download_speed_snapshot' => '20 Mbps',
            'upload_speed_snapshot' => '20 Mbps',
            'monthly_price' => $package->monthly_price,
            'discount' => 10000.00,
            'ppn' => 11.00,
            'total_monthly_bill' => ($package->monthly_price - 10000.00) * 1.11,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-07-01',
            'service_status' => $status === 'active' ? 'aktif' : 'calon_pelanggan',
            'billing_status' => $status === 'active' ? 'active' : 'pending',
        ]);

        return $customer;
    }

    public function test_authorized_user_can_create_manual_invoice_for_eligible_customer()
    {
        $role = Role::where('name', 'Owner')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id]);

        $pop = Pop::create([
            'code' => 'POP-TEST',
            'pop_code' => 'TST',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $package = InternetPackage::query()->firstOrFail();
        $customer = $this->createTestCustomer($pop, $package);

        $response = $this->actingAs($user)->post(route('customers.invoices.manual', $customer->id), [
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-15',
        ]);

        $response->assertRedirect(route('customers.show', $customer->id));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('invoices', [
            'customer_id' => $customer->id,
            'billing_period' => '2026-06',
            'invoice_number' => 'INV-202606-0001',
            'invoice_status' => 'belum_dibayar',
            'subtotal' => $package->monthly_price,
            'discount' => 10000.00,
            'ppn' => 11.00,
            'total_amount' => ($package->monthly_price - 10000.00) * 1.11,
            'remaining_amount' => ($package->monthly_price - 10000.00) * 1.11,
            'created_by' => $user->id,
        ]);

        // Assert AuditLog was created
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'module' => 'Tagihan',
            'action' => 'create',
        ]);
    }

    public function test_invoice_number_generates_sequentially()
    {
        $role = Role::where('name', 'Owner')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id]);

        $pop = Pop::create([
            'code' => 'POP-TEST',
            'pop_code' => 'TST',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $package = InternetPackage::query()->firstOrFail();
        $customer1 = $this->createTestCustomer($pop, $package);
        
        // Create customer2
        $customer2 = Customer::create([
            'customer_code' => 'WHUS-2026-0002',
            'full_name' => 'Rudi Santoso',
            'gender' => 'Laki-laki',
            'phone' => '081234567891',
            'primary_phone' => '081234567891',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'customer_status' => 'aktif',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
        ]);
        $customer2->customerService()->create([
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'monthly_price' => $package->monthly_price,
            'discount' => 0.00,
            'ppn' => 11.00,
            'total_monthly_bill' => $package->monthly_price * 1.11,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-07-01',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        // First invoice
        $this->actingAs($user)->post(route('customers.invoices.manual', $customer1->id), [
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-15',
        ]);

        // Second invoice (same period, different customer)
        $this->actingAs($user)->post(route('customers.invoices.manual', $customer2->id), [
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-15',
        ]);

        $this->assertDatabaseHas('invoices', [
            'customer_id' => $customer1->id,
            'invoice_number' => 'INV-202606-0001',
        ]);

        $this->assertDatabaseHas('invoices', [
            'customer_id' => $customer2->id,
            'invoice_number' => 'INV-202606-0002',
        ]);
    }

    public function test_cannot_create_duplicate_invoice_for_same_customer_and_period()
    {
        $role = Role::where('name', 'Owner')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id]);

        $pop = Pop::create([
            'code' => 'POP-TEST',
            'pop_code' => 'TST',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $package = InternetPackage::query()->firstOrFail();
        $customer = $this->createTestCustomer($pop, $package);

        // First creation succeeds
        $this->actingAs($user)->post(route('customers.invoices.manual', $customer->id), [
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-15',
        ]);

        // Second creation fails
        $response = $this->actingAs($user)->post(route('customers.invoices.manual', $customer->id), [
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-15',
        ]);

        $response->assertSessionHasErrors('billing_period');
        $this->assertEquals(1, Invoice::where('customer_id', $customer->id)->count());
    }

    public function test_unauthorized_user_cannot_create_manual_invoice()
    {
        $role = Role::where('name', 'Teknisi')->firstOrFail(); // No create_invoices permission
        $user = User::factory()->create(['role_id' => $role->id]);

        $pop = Pop::create([
            'code' => 'POP-TEST',
            'pop_code' => 'TST',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $package = InternetPackage::query()->firstOrFail();
        $customer = $this->createTestCustomer($pop, $package);

        $response = $this->actingAs($user)->post(route('customers.invoices.manual', $customer->id), [
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-15',
        ]);

        $response->assertStatus(403);
    }

    public function test_cannot_create_invoice_for_ineligible_customer()
    {
        $role = Role::where('name', 'Owner')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id]);

        $pop = Pop::create([
            'code' => 'POP-TEST',
            'pop_code' => 'TST',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $package = InternetPackage::query()->firstOrFail();
        // Create draft/not ready customer
        $customer = $this->createTestCustomer($pop, $package, 'registered', 'draft');

        $response = $this->actingAs($user)->post(route('customers.invoices.manual', $customer->id), [
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-15',
        ]);

        $response->assertSessionHasErrors('error');
        $this->assertEquals(0, Invoice::count());
    }

    public function test_admin_cabang_can_only_create_invoice_for_assigned_pop()
    {
        $role = Role::where('name', 'Admin Cabang')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id]);

        $pop1 = Pop::create(['code' => 'POP-1', 'pop_code' => 'P1', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'POP 1', 'type' => 'cabang', 'status' => 'active']);
        $pop2 = Pop::create(['code' => 'POP-2', 'pop_code' => 'P2', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'POP 2', 'type' => 'cabang', 'status' => 'active']);

        // Assign user to pop1 only
        $user->pops()->attach($pop1->id);

        $package = InternetPackage::query()->firstOrFail();
        $customerInPop1 = $this->createTestCustomer($pop1, $package);
        
        $customerInPop2 = Customer::create([
            'customer_code' => 'WHUS-2026-0003',
            'full_name' => 'Siti Santoso',
            'gender' => 'Perempuan',
            'phone' => '081234567895',
            'primary_phone' => '081234567895',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'customer_status' => 'aktif',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop2->id,
        ]);
        $customerInPop2->customerService()->create([
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'monthly_price' => $package->monthly_price,
            'discount' => 0.00,
            'ppn' => 11.00,
            'total_monthly_bill' => $package->monthly_price * 1.11,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-07-01',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        // Creating invoice for POP1 succeeds
        $response1 = $this->actingAs($user)->post(route('customers.invoices.manual', $customerInPop1->id), [
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-15',
        ]);
        $response1->assertRedirect();
        $response1->assertSessionHas('success');

        // Creating invoice for POP2 fails with 403
        $response2 = $this->actingAs($user)->post(route('customers.invoices.manual', $customerInPop2->id), [
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-15',
        ]);
        $response2->assertStatus(403);
    }
}
