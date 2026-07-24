<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportInvoiceTest extends TestCase
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
        $response = $this->get('/reports/invoices');
        $response->assertRedirect('/login');

        $responseExport = $this->get('/reports/invoices/export');
        $responseExport->assertRedirect('/login');
    }

    public function test_user_without_permission_cannot_access_reports(): void
    {
        // Teknisi has no report permission by default
        $csRole = Role::where('name', 'Teknisi')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $csRole->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get('/reports/invoices');
        $response->assertStatus(403);

        $responseExport = $this->actingAs($user)->get('/reports/invoices/export');
        $responseExport->assertStatus(403);
    }

    public function test_owner_can_access_reports_and_see_all_pops(): void
    {
        $ownerRole = Role::where('name', 'Owner')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $ownerRole->id,
            'status' => 'active',
        ]);

        $popA = $this->createPop('SDA', 'SDA', 'POP Sidoarjo');
        $popB = $this->createPop('SBY', 'SBY', 'POP Surabaya');

        $response = $this->actingAs($user)->get('/reports/invoices');
        $response->assertStatus(200);
        $response->assertSee('POP Sidoarjo');
        $response->assertSee('POP Surabaya');
    }

    public function test_admin_cabang_only_sees_assigned_pop_in_filters_and_data(): void
    {
        $adminCabangRole = Role::where('name', 'POP Admin')->firstOrFail();
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

        $invoiceA = $this->createInvoice($popA, 'Pelanggan SDA', 'INV-SDA-001', '2026-06', 'belum_dibayar');
        $invoiceB = $this->createInvoice($popB, 'Pelanggan SBY', 'INV-SBY-001', '2026-06', 'belum_dibayar');

        $response = $this->actingAs($user)->get('/reports/invoices');
        $response->assertStatus(200);

        // Should see popA but not popB in filters/tables
        $response->assertSee('POP Sidoarjo');
        $response->assertDontSee('POP Surabaya');
        $response->assertSee('Pelanggan SDA');
        $response->assertDontSee('Pelanggan SBY');
    }

    public function test_invoice_report_filtering(): void
    {
        $ownerRole = Role::where('name', 'Owner')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $ownerRole->id,
            'status' => 'active',
        ]);

        $popA = $this->createPop('SDA', 'SDA', 'POP Sidoarjo');
        $popB = $this->createPop('SBY', 'SBY', 'POP Surabaya');

        // 1. POP A, status: lunas, period: 2026-06, date: 2026-06-01
        $invoice1 = $this->createInvoice($popA, 'Pelanggan Satu', 'INV-001', '2026-06', 'lunas', 150000, 150000, '2026-06-01');

        // 2. POP B, status: belum_dibayar, period: 2026-07, date: 2026-07-10
        $invoice2 = $this->createInvoice($popB, 'Pelanggan Dua', 'INV-002', '2026-07', 'belum_dibayar', 150000, 0, '2026-07-10');

        // Filter pop_id = popA
        $responsePop = $this->actingAs($user)->get('/reports/invoices?pop_id='.$popA->id);
        $responsePop->assertSee('Pelanggan Satu');
        $responsePop->assertDontSee('Pelanggan Dua');

        // Filter status = belum_dibayar
        $responseStatus = $this->actingAs($user)->get('/reports/invoices?status=belum_dibayar');
        $responseStatus->assertSee('Pelanggan Dua');
        $responseStatus->assertDontSee('Pelanggan Satu');

        // Filter billing_period = 2026-07
        $responsePeriod = $this->actingAs($user)->get('/reports/invoices?billing_period=2026-07');
        $responsePeriod->assertSee('Pelanggan Dua');
        $responsePeriod->assertDontSee('Pelanggan Satu');

        // Filter date range: 2026-06-15 to 2026-07-15
        $responseDate = $this->actingAs($user)->get('/reports/invoices?start_date=2026-06-15&end_date=2026-07-15');
        $responseDate->assertSee('Pelanggan Dua');
        $responseDate->assertDontSee('Pelanggan Satu');

        // Filter show_tunggakan = 1
        $responseTunggakan = $this->actingAs($user)->get('/reports/invoices?show_tunggakan=1');
        $responseTunggakan->assertSee('Pelanggan Dua'); // has remaining_amount > 0
        $responseTunggakan->assertDontSee('Pelanggan Satu'); // lunas, remaining_amount = 0
    }

    public function test_export_csv_enforces_pop_boundaries_for_admin_cabang(): void
    {
        $adminCabangRole = Role::where('name', 'POP Admin')->firstOrFail();
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

        $this->createInvoice($popA, 'Export Pelanggan SDA', 'INV-SDA-991', '2026-06', 'belum_dibayar');
        $this->createInvoice($popB, 'Export Pelanggan SBY', 'INV-SBY-992', '2026-06', 'belum_dibayar');

        // Export without specific pop_id parameter (should only return POP A data)
        $response = $this->actingAs($user)->get('/reports/invoices/export');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('Export Pelanggan SDA', $content);
        $this->assertStringNotContainsString('Export Pelanggan SBY', $content);

        // Export specifically POP B (which they don't have access to) -> should return 403
        $responseUnauthorizedExport = $this->actingAs($user)->get('/reports/invoices/export?pop_id='.$popB->id);
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

    protected function createInvoice(
        Pop $pop,
        string $customerName,
        string $invoiceNumber,
        string $period,
        string $status,
        float $totalAmount = 150000.0,
        float $paidAmount = 0.0,
        ?string $issueDate = null
    ): Invoice {
        $customer = Customer::create([
            'customer_code' => str_replace('INV', 'C', $invoiceNumber),
            'full_name' => $customerName,
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. Test Invoice',
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Test Invoice',
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
            'monthly_price' => $totalAmount,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => $totalAmount,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        $resolvedIssueDate = $issueDate ?? "{$period}-01";
        $resolvedDueDate = $issueDate ? date('Y-m-d', strtotime($resolvedIssueDate.' + 14 days')) : "{$period}-15";

        return Invoice::create([
            'invoice_number' => $invoiceNumber,
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $this->package->id,
            'billing_period' => $period,
            'issue_date' => $resolvedIssueDate,
            'due_date' => $resolvedDueDate,
            'subtotal' => $totalAmount,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'remaining_amount' => $totalAmount - $paidAmount,
            'invoice_status' => $status,
        ]);
    }
}
