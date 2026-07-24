<?php

namespace Tests\Feature;

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
use Tests\TestCase;

class ReportPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected InternetPackage $package;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/reports/payments');
        $response->assertRedirect('/login');

        $responseExport = $this->get('/reports/payments/export');
        $responseExport->assertRedirect('/login');
    }

    public function test_user_without_permission_cannot_access_reports(): void
    {
        // Teknisi has no report permission by default
        $csRole = Role::where('name', '=', 'Teknisi', 'and')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $csRole->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get('/reports/payments');
        $response->assertStatus(403);

        $responseExport = $this->actingAs($user)->get('/reports/payments/export');
        $responseExport->assertStatus(403);
    }

    public function test_owner_can_access_reports_and_see_all_pops(): void
    {
        $ownerRole = Role::where('name', '=', 'Owner', 'and')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $ownerRole->id,
            'status' => 'active',
        ]);

        $popA = $this->createPop('SDA', 'SDA', 'POP Sidoarjo');
        $popB = $this->createPop('SBY', 'SBY', 'POP Surabaya');

        $response = $this->actingAs($user)->get('/reports/payments');
        $response->assertStatus(200);
        $response->assertSee('POP Sidoarjo');
        $response->assertSee('POP Surabaya');
    }

    public function test_admin_cabang_only_sees_assigned_pop_in_filters_and_data(): void
    {
        $adminCabangRole = Role::where('name', '=', 'POP Admin', 'and')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $adminCabangRole->id,
            'status' => 'active',
        ]);

        $popA = $this->createPop('SDA', 'SDA', 'POP Sidoarjo');
        $popB = $this->createPop('SBY', 'SBY', 'POP Surabaya');

        // Assign popA only
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

        $invoiceA = $this->createInvoice($popA, 'Pelanggan SDA', 'INV-SDA-001');
        $invoiceB = $this->createInvoice($popB, 'Pelanggan SBY', 'INV-SBY-001');

        $paymentA = $this->createPayment($invoiceA, 'PAY-SDA-001', '2026-06-05', 'cash', 'valid', 150000.0);
        $paymentB = $this->createPayment($invoiceB, 'PAY-SBY-001', '2026-06-05', 'cash', 'valid', 150000.0);

        $response = $this->actingAs($user)->get('/reports/payments');
        $response->assertStatus(200);

        // Should see popA but not popB in filters/tables
        $response->assertSee('POP Sidoarjo');
        $response->assertDontSee('POP Surabaya');
        $response->assertSee('PAY-SDA-001');
        $response->assertDontSee('PAY-SBY-001');
    }

    public function test_payment_report_filtering(): void
    {
        $ownerRole = Role::where('name', '=', 'Owner', 'and')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $ownerRole->id,
            'status' => 'active',
        ]);

        $popA = $this->createPop('SDA', 'SDA', 'POP Sidoarjo');
        $popB = $this->createPop('SBY', 'SBY', 'POP Surabaya');

        $invoiceA = $this->createInvoice($popA, 'Pelanggan Satu', 'INV-001');
        $invoiceB = $this->createInvoice($popB, 'Pelanggan Dua', 'INV-002');

        // 1. POP A, method: cash, status: valid, date: 2026-06-01
        $payment1 = $this->createPayment($invoiceA, 'PAY-001', '2026-06-01', 'cash', 'valid', 150000);

        // 2. POP B, method: transfer, status: pending, date: 2026-07-10
        $payment2 = $this->createPayment($invoiceB, 'PAY-002', '2026-07-10', 'transfer', 'pending', 120000);

        // Filter pop_id = popA
        $responsePop = $this->actingAs($user)->get('/reports/payments?pop_id='.$popA->id);
        $responsePop->assertSee('PAY-001');
        $responsePop->assertDontSee('PAY-002');

        // Filter payment_method = transfer
        $responseMethod = $this->actingAs($user)->get('/reports/payments?payment_method=transfer');
        $responseMethod->assertSee('PAY-002');
        $responseMethod->assertDontSee('PAY-001');

        // Filter status = pending
        $responseStatus = $this->actingAs($user)->get('/reports/payments?status=pending');
        $responseStatus->assertSee('PAY-002');
        $responseStatus->assertDontSee('PAY-001');

        // Filter date range: 2026-06-15 to 2026-07-15
        $responseDate = $this->actingAs($user)->get('/reports/payments?start_date=2026-06-15&end_date=2026-07-15');
        $responseDate->assertSee('PAY-002');
        $responseDate->assertDontSee('PAY-001');
    }

    public function test_export_csv_enforces_pop_boundaries_for_admin_cabang(): void
    {
        $adminCabangRole = Role::where('name', '=', 'POP Admin', 'and')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $adminCabangRole->id,
            'status' => 'active',
        ]);

        $popA = $this->createPop('SDA', 'SDA', 'POP Sidoarjo');
        $popB = $this->createPop('SBY', 'SBY', 'POP Surabaya');
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

        $invoiceA = $this->createInvoice($popA, 'Export Pelanggan SDA', 'INV-SDA-991');
        $invoiceB = $this->createInvoice($popB, 'Export Pelanggan SBY', 'INV-SBY-992');

        $paymentA = $this->createPayment($invoiceA, 'PAY-SDA-991', '2026-06-13', 'cash', 'valid', 150000);
        $paymentB = $this->createPayment($invoiceB, 'PAY-SBY-992', '2026-06-13', 'cash', 'valid', 150000);

        // Export without specific pop_id parameter (should only return POP A data)
        $response = $this->actingAs($user)->get('/reports/payments/export');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('PAY-SDA-991', $content);
        $this->assertStringNotContainsString('PAY-SBY-992', $content);

        // Export specifically POP B (which they don't have access to) -> should return 403
        $responseUnauthorizedExport = $this->actingAs($user)->get('/reports/payments/export?pop_id='.$popB->id);
        $responseUnauthorizedExport->assertStatus(403);
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

    protected function createInvoice(Pop $pop, string $customerName, string $invoiceNumber): Invoice
    {
        $customer = Customer::create([
            'customer_code' => str_replace('INV', 'C', $invoiceNumber),
            'full_name' => $customerName,
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. Test Payment Report',
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Test Payment Report',
            'village' => 'Desa Test',
            'district' => 'Kecamatan Test',
            'city' => 'Kota Test',
            'province' => 'Jawa Timur',
        ]);

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $this->package->id,
            'package_name_snapshot' => 'Paket Test 20 Mbps',
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

        return Invoice::create([
            'invoice_number' => $invoiceNumber,
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $this->package->id,
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
    }

    protected function createPayment(
        Invoice $invoice,
        string $paymentNumber,
        string $paymentDate,
        string $method,
        string $status,
        float $amount = 150000
    ): Payment {
        return Payment::create([
            'payment_number' => $paymentNumber,
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $invoice->pop_id,
            'payment_date' => $paymentDate,
            'payment_method' => $method,
            'amount' => $amount,
            'received_by' => User::where('email', '=', 'owner@whusnet.net', 'and')->value('id'),
            'payment_status' => $status,
            'note' => 'Pembayaran test laporan.',
        ]);
    }
}
