<?php

namespace Tests\Feature;

use App\Enums\InvoiceType;
use App\Enums\ScopeType;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserPopScopeTest extends TestCase
{
    use RefreshDatabase;

    protected InternetPackage $package;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();
    }

    public function test_customer_invoice_and_payment_scopes_follow_assigned_pop_ids(): void
    {
        $role = Role::where('name', 'POP Admin')->firstOrFail();
        $user = User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        $popA = $this->createPop('POP-SCOPE-A', 'SCA', 'POP Scope A');
        $popB = $this->createPop('POP-SCOPE-B', 'SCB', 'POP Scope B');

        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);

        UserRoleScopeTarget::create([
            'user_role_scope_id' => $scope->id,
            'pop_id' => $popA->id,
        ]);

        $customerA = $this->createCustomerBundle($popA, 'Customer Scope A', 'C-SCOPE-A', 'INV-SCOPE-A', 'PAY-SCOPE-A');
        $customerB = $this->createCustomerBundle($popB, 'Customer Scope B', 'C-SCOPE-B', 'INV-SCOPE-B', 'PAY-SCOPE-B');

        $customerNames = Customer::applyUserScope($user)->pluck('full_name')->all();
        $invoiceNumbers = Invoice::applyUserScope($user)->pluck('invoice_number')->all();
        $paymentNumbers = Payment::applyUserScope($user)->pluck('payment_number')->all();

        $this->assertContains('Customer Scope A', $customerNames);
        $this->assertNotContains('Customer Scope B', $customerNames);
        $this->assertContains('INV-SCOPE-A', $invoiceNumbers);
        $this->assertNotContains('INV-SCOPE-B', $invoiceNumbers);
        $this->assertContains('PAY-SCOPE-A', $paymentNumbers);
        $this->assertNotContains('PAY-SCOPE-B', $paymentNumbers);
    }

    public function test_full_access_user_sees_all_scope_data(): void
    {
        $owner = User::where('email', 'owner@whusnet.net')->firstOrFail();

        // Clear existing data to isolate the count assertions and avoid SQLite FK constraints
        DB::table('fop_task_team_user')->delete();
        DB::table('fop_task_user')->delete();
        DB::table('fop_tasks')->delete();
        DB::table('task_maintenances')->delete();
        DB::table('task_checklists')->delete();
        DB::table('task_evidences')->delete();
        DB::table('tasks')->delete();
        DB::table('payments')->delete();
        DB::table('invoices')->delete();
        DB::table('customer_services')->delete();
        DB::table('customer_addresses')->delete();
        DB::table('customer_surveys')->delete();
        DB::table('customer_installations')->delete();
        DB::table('customer_devices')->delete();
        DB::table('customer_documents')->delete();
        DB::table('customers')->delete();

        $popA = $this->createPop('POP-SCOPE-OWN-A', 'OSA', 'POP Owner A');
        $popB = $this->createPop('POP-SCOPE-OWN-B', 'OSB', 'POP Owner B');

        $this->createCustomerBundle($popA, 'Owner Scope A', 'C-OWN-A', 'INV-OWN-A', 'PAY-OWN-A');
        $this->createCustomerBundle($popB, 'Owner Scope B', 'C-OWN-B', 'INV-OWN-B', 'PAY-OWN-B');

        $this->assertCount(2, Customer::applyUserScope($owner)->get());
        $this->assertCount(2, Invoice::applyUserScope($owner)->get());
        $this->assertCount(2, Payment::applyUserScope($owner)->get());
    }

    protected function createPop(string $code, string $popCode, string $name): Pop
    {
        return Pop::create([
            'code' => $code,
            'pop_code' => $popCode,
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => $name,
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    protected function createCustomerBundle(Pop $pop, string $customerName, string $customerCode, string $invoiceNumber, string $paymentNumber): Customer
    {
        $customer = Customer::create([
            'customer_code' => $customerCode,
            'full_name' => $customerName,
            'phone' => '081234567890',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'customer_status' => 'aktif',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. Scope Test',
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Scope Test',
            'village' => 'Desa Scope',
            'district' => 'Kecamatan Scope',
            'city' => 'Kota Scope',
            'province' => 'Jawa Timur',
        ]);

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $this->package->id,
            'package_name_snapshot' => 'Paket Scope 20 Mbps',
            'download_speed_snapshot' => '20 Mbps',
            'upload_speed_snapshot' => '10 Mbps',
            'monthly_price' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => 150000,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        $invoice = Invoice::create([
            'invoice_number' => $invoiceNumber,
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $this->package->id,
            'invoice_type' => InvoiceType::BULANAN->value,
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'subtotal' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 150000,
            'paid_amount' => 150000,
            'remaining_amount' => 0,
            'invoice_status' => 'lunas',
        ]);

        Payment::create([
            'payment_number' => $paymentNumber,
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 150000,
            'received_by' => User::where('email', 'owner@whusnet.net')->value('id'),
            'payment_status' => 'valid',
            'note' => 'Scope test payment.',
        ]);

        return $customer;
    }
}
