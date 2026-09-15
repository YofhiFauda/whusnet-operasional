<?php

namespace Tests\Feature;

use App\Enums\CustomerBalanceMutationType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerBalanceMutation;
use App\Models\CustomerService;
use App\Models\District;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Pop;
use App\Models\Role;
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

class CustomerQuickHubPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected Pop $pop;

    protected InternetPackage $package;

    protected User $adminUser;

    protected User $collectorUser;

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

        $adminRole = Role::where('name', 'Admin')->firstOrFail();
        $this->adminUser = User::factory()->create([
            'role_id' => $adminRole->id,
            'status' => 'active',
        ]);

        UserRoleScope::create([
            'user_id' => $this->adminUser->id,
            'role_id' => $this->adminUser->role_id,
            'scope_type' => 'all_pop',
        ]);

        $collectorRole = Role::firstOrCreate(['name' => 'Kolektor', 'code' => 'kolektor'], [
            'display_name' => 'Kolektor',
            'description' => 'Petugas penagih',
        ]);

        $this->collectorUser = User::factory()->create([
            'name' => 'Pak Kolektor',
            'role_id' => $collectorRole->id,
            'status' => 'active',
        ]);
    }

    protected function createCustomer(): Customer
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
            'status' => 'active',
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
            'service_status' => 'aktif',
            'billing_status' => 'active',
            'monthly_price' => $this->package->monthly_price,
            'total_monthly_bill' => $this->package->monthly_price,
            'billing_cycle' => 'monthly',
        ]);

        $customer->refresh();

        return $customer;
    }

    public function test_payment_info_returns_expected_payload(): void
    {
        $customer = $this->createCustomer();

        // Seed some customer balance
        CustomerBalanceMutation::create([
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'type' => CustomerBalanceMutationType::CREDIT->value,
            'amount' => 50000,
            'note' => 'overpayment_deposit',
            'created_by' => $this->adminUser->id,
        ]);

        // Create an unpaid invoice
        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'customer_service_id' => $customer->customerService->id,
            'internet_package_id' => $this->package->id,
            'pop_id' => $this->pop->id,
            'invoice_number' => 'INV-202606-0001',
            'invoice_type' => InvoiceType::BULANAN->value,
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-10',
            'subtotal' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'invoice_status' => InvoiceStatus::BELUM_DIBAYAR->value,
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('customers.payment-info', $customer->id));

        $response->assertOk()
            ->assertJson([
                'invoice_id' => $invoice->id,
                'payment_store_url' => route('invoices.payments.store', $invoice->id),
                'total_amount' => 150000,
                'remaining_amount' => 150000,
                'customer_balance' => 50000,
            ])
            ->assertJsonFragment([
                'id' => $this->collectorUser->id,
                'name' => 'Pak Kolektor',
            ]);
    }

    public function test_quick_payment_submission_via_ajax_records_payment_and_updates_invoice(): void
    {
        $customer = $this->createCustomer();

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'customer_service_id' => $customer->customerService->id,
            'internet_package_id' => $this->package->id,
            'pop_id' => $this->pop->id,
            'invoice_number' => 'INV-202606-0002',
            'invoice_type' => InvoiceType::BULANAN->value,
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-10',
            'subtotal' => 200000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 200000,
            'paid_amount' => 0,
            'remaining_amount' => 200000,
            'invoice_status' => InvoiceStatus::BELUM_DIBAYAR->value,
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('invoices.payments.store', $invoice->id), [
                'payment_date' => '2026-06-05',
                'amount' => 200000,
                'payment_method' => 'kolektor',
                'collected_by' => $this->collectorUser->id,
                'notes' => 'Pembayaran via Quick Hub Modal',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $invoice->refresh();
        $this->assertEquals(200000, (float) $invoice->paid_amount);
        $this->assertEquals(0, (float) $invoice->remaining_amount);
        $this->assertEquals(InvoiceStatus::LUNAS->value, $invoice->invoice_status->value ?? $invoice->invoice_status);

        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'amount' => 200000,
            'payment_method' => 'kolektor',
            'collected_by' => $this->collectorUser->id,
        ]);
    }

    public function test_quick_payment_submission_can_use_customer_balance_to_settle_invoice(): void
    {
        $customer = $this->createCustomer();

        // Saldo pelanggan Rp 50.000
        CustomerBalanceMutation::create([
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'type' => CustomerBalanceMutationType::CREDIT->value,
            'amount' => 50000,
            'note' => 'overpayment_deposit',
            'created_by' => $this->adminUser->id,
        ]);

        // Tagihan Rp 150.000
        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'customer_service_id' => $customer->customerService->id,
            'internet_package_id' => $this->package->id,
            'pop_id' => $this->pop->id,
            'invoice_number' => 'INV-202606-0003',
            'invoice_type' => InvoiceType::BULANAN->value,
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-10',
            'subtotal' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'invoice_status' => InvoiceStatus::BELUM_DIBAYAR->value,
            'created_by' => $this->adminUser->id,
        ]);

        // Bayar tunai Rp 100.000 + pakai saldo Rp 50.000
        $response = $this->actingAs($this->adminUser)
            ->postJson(route('invoices.payments.store', $invoice->id), [
                'payment_date' => '2026-06-05',
                'amount' => 100000,
                'use_balance_amount' => 50000,
                'payment_method' => 'cash',
                'notes' => 'Pembayaran kombinasi tunai dan saldo',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $invoice->refresh();
        $this->assertEquals(150000, (float) $invoice->paid_amount);
        $this->assertEquals(0, (float) $invoice->remaining_amount);
        $this->assertEquals(InvoiceStatus::LUNAS->value, $invoice->invoice_status->value ?? $invoice->invoice_status);

        // Mutasi debit saldo Rp 50.000 tercatat di ledger
        $this->assertDatabaseHas('customer_balance_mutations', [
            'customer_id' => $customer->id,
            'type' => CustomerBalanceMutationType::DEBIT->value,
            'amount' => 50000,
        ]);
    }
}
