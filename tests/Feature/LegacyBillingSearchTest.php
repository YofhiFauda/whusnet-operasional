<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use App\Models\InternetPackage;
use App\Models\CustomerService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyBillingSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_can_be_searched_by_old_customer_id(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $pop = Pop::first() ?? Pop::create([
            'code' => 'SMN',
            'pop_code' => 'SMN',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Sooko',
            'type' => 'cabang',
            'status' => 'active',
        ]);
        
        $package = InternetPackage::first() ?? InternetPackage::create([
            'package_code' => 'PKG001',
            'name' => 'Test Package',
            'monthly_price' => 100000,
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'customer_code' => 'C-NEW-001',
            'old_customer_id' => 'OLD-CUST-999',
            'full_name' => 'Legacy Customer',
            'phone' => '08123456789',
            'primary_phone' => '08123456789',
            'registration_date' => now(),
            'pop_id' => $pop->id,
            'status' => 'active',
        ]);

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'monthly_price' => 100000,
            'total_monthly_bill' => 100000,
            'service_status' => 'active',
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-2025-001',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $package->id,
            'billing_period' => '2025-01',
            'issue_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(10)->format('Y-m-d'),
            'subtotal' => 100000,
            'total_amount' => 100000,
            'paid_amount' => 0,
            'remaining_amount' => 100000,
            'invoice_status' => 'belum_dibayar',
        ]);

        // Search by old_customer_id
        $response = $this->get('/invoices?search=OLD-CUST-999');
        $response->assertStatus(200);
        $response->assertSee('INV-2025-001');
        $response->assertSee('Legacy Customer');
    }

    public function test_invoice_can_be_searched_by_old_invoice_id(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $pop = Pop::first() ?? Pop::create([
            'code' => 'SMN2',
            'pop_code' => 'SMN2',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Sooko 2',
            'type' => 'cabang',
            'status' => 'active',
        ]);
        
        $package = InternetPackage::first() ?? InternetPackage::create([
            'package_code' => 'PKG002',
            'name' => 'Test Package 2',
            'monthly_price' => 100000,
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'customer_code' => 'C-NEW-002',
            'old_customer_id' => 'OLD-CUST-888',
            'full_name' => 'Legacy Customer 2',
            'phone' => '08123456788',
            'primary_phone' => '08123456788',
            'registration_date' => now(),
            'pop_id' => $pop->id,
            'status' => 'active',
        ]);

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'monthly_price' => 100000,
            'total_monthly_bill' => 100000,
            'service_status' => 'active',
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-NEW-002',
            'old_invoice_id' => 'OLD-INV-777',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $package->id,
            'billing_period' => '2025-01',
            'issue_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(10)->format('Y-m-d'),
            'subtotal' => 100000,
            'total_amount' => 100000,
            'paid_amount' => 0,
            'remaining_amount' => 100000,
            'invoice_status' => 'belum_dibayar',
        ]);

        // Search by old_invoice_id
        $response = $this->get('/invoices?search=OLD-INV-777');
        $response->assertStatus(200);
        $response->assertSee('INV-NEW-002');
        $response->assertSee('OLD-INV-777');
    }

    public function test_payment_can_be_searched_by_old_ids(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $pop = Pop::first() ?? Pop::create([
            'code' => 'SMN3',
            'pop_code' => 'SMN3',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Sooko 3',
            'type' => 'cabang',
            'status' => 'active',
        ]);
        
        $package = InternetPackage::first() ?? InternetPackage::create([
            'package_code' => 'PKG003',
            'name' => 'Test Package 3',
            'monthly_price' => 100000,
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'customer_code' => 'C-NEW-003',
            'old_customer_id' => 'OLD-CUST-777',
            'full_name' => 'Legacy Customer 3',
            'phone' => '08123456787',
            'primary_phone' => '08123456787',
            'registration_date' => now(),
            'pop_id' => $pop->id,
            'status' => 'active',
        ]);

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'monthly_price' => 100000,
            'total_monthly_bill' => 100000,
            'service_status' => 'active',
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-NEW-003',
            'old_invoice_id' => 'OLD-INV-666',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $package->id,
            'billing_period' => '2025-01',
            'issue_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(10)->format('Y-m-d'),
            'subtotal' => 100000,
            'total_amount' => 100000,
            'paid_amount' => 100000,
            'remaining_amount' => 0,
            'invoice_status' => 'lunas',
        ]);

        $payment = Payment::create([
            'payment_number' => 'PAY-NEW-001',
            'old_payment_id' => 'OLD-PAY-555',
            'old_transaction_id' => 'OLD-TRANS-444',
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'payment_date' => now(),
            'amount' => 100000,
            'payment_method' => 'cash',
            'payment_status' => 'valid',
        ]);

        // Search by old_customer_id
        $response = $this->get('/payments?search=OLD-CUST-777');
        $response->assertStatus(200);
        $response->assertSee('PAY-NEW-001');
        $response->assertSee('Legacy Customer 3');

        // Search by old_payment_id
        $response = $this->get('/payments?search=OLD-PAY-555');
        $response->assertStatus(200);
        $response->assertSee('PAY-NEW-001');

        // Search by old_transaction_id
        $response = $this->get('/payments?search=OLD-TRANS-444');
        $response->assertStatus(200);
        $response->assertSee('PAY-NEW-001');

        // Search by old_invoice_id
        $response = $this->get('/payments?search=OLD-INV-666');
        $response->assertStatus(200);
        $response->assertSee('PAY-NEW-001');
    }
}
