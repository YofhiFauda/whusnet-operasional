<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentListTest extends TestCase
{
    use RefreshDatabase;

    protected InternetPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();
    }

    public function test_payment_list_can_be_filtered_by_date_pop_method_status_and_customer_or_invoice(): void
    {
        $owner = User::where('email', 'owner@whusnet.net')->firstOrFail();
        $popA = $this->createPop('POP-PAY-LIST-A', 'PLA', 'POP Payment List A');
        $popB = $this->createPop('POP-PAY-LIST-B', 'PLB', 'POP Payment List B');

        $invoiceA = $this->createInvoice($popA, 'Ahmad Payment Filter', 'INV-202606-7001');
        $invoiceB = $this->createInvoice($popB, 'Budi Payment Filter', 'INV-202607-7002');
        $paymentA = $this->createPayment($invoiceA, 'PAY-202606-7001', '2026-06-13', 'cash', 'valid');
        $paymentB = $this->createPayment($invoiceB, 'PAY-202607-7002', '2026-07-13', 'transfer', 'pending');

        $response = $this->actingAs($owner)->get(route('payments.index', [
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-30',
            'pop_id' => $popA->id,
            'method' => 'cash',
            'status' => 'valid',
            'search' => 'Ahmad',
        ]));

        $response->assertOk();
        $response->assertSee($paymentA->payment_number);
        $response->assertSee($invoiceA->invoice_number);
        $response->assertSee('Ahmad Payment Filter');
        $response->assertDontSee($paymentB->payment_number);
        $response->assertDontSee($invoiceB->invoice_number);
        $response->assertDontSee('Budi Payment Filter');
    }

    public function test_admin_cabang_only_sees_assigned_pop_payments(): void
    {
        $role = Role::where('name', 'POP Admin')->firstOrFail();
        $adminCabang = User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        $popA = $this->createPop('POP-PAY-CABANG-A', 'PCA', 'POP Payment Cabang A');
        $popB = $this->createPop('POP-PAY-CABANG-B', 'PCB', 'POP Payment Cabang B');
        $adminCabang->pops()->attach($popA->id);
        $scope = \App\Models\UserRoleScope::create([
            'user_id' => $adminCabang->id,
            'role_id' => $role->id,
            'scope_type' => \App\Enums\ScopeType::SELECTED_POP,
        ]);
        \App\Models\UserRoleScopeTarget::create([
            'user_role_scope_id' => $scope->id,
            'pop_id' => $popA->id,
        ]);

        $invoiceA = $this->createInvoice($popA, 'Customer Payment Cabang Sendiri', 'INV-202606-7101');
        $invoiceB = $this->createInvoice($popB, 'Customer Payment Cabang Lain', 'INV-202606-7102');
        $paymentA = $this->createPayment($invoiceA, 'PAY-202606-7101', '2026-06-13', 'qris', 'valid');
        $paymentB = $this->createPayment($invoiceB, 'PAY-202606-7102', '2026-06-13', 'qris', 'valid');

        $response = $this->actingAs($adminCabang)->get(route('payments.index'));

        $response->assertOk();
        $response->assertSee($paymentA->payment_number);
        $response->assertSee('Customer Payment Cabang Sendiri');
        $response->assertDontSee($paymentB->payment_number);
        $response->assertDontSee('Customer Payment Cabang Lain');

        $detailResponse = $this->actingAs($adminCabang)->get(route('payments.show', $paymentB->id));
        $detailResponse->assertForbidden();
    }

    public function test_payment_detail_displays_invoice_customer_pop_status_and_proof(): void
    {
        $owner = User::where('email', 'owner@whusnet.net')->firstOrFail();
        $pop = $this->createPop('POP-PAY-DETAIL', 'PPD', 'POP Payment Detail');
        $invoice = $this->createInvoice($pop, 'Detail Payment Customer', 'INV-202606-7201');
        $payment = $this->createPayment($invoice, 'PAY-202606-7201', '2026-06-13', 'transfer', 'valid', 'payments/proof-detail.jpg');

        $response = $this->actingAs($owner)->get(route('payments.show', $payment->id));

        $response->assertOk();
        $response->assertSee($payment->payment_number);
        $response->assertSee($invoice->invoice_number);
        $response->assertSee('Detail Payment Customer');
        $response->assertSee('POP Payment Detail');
        $response->assertSee('TRANSFER');
        $response->assertSee('Valid');
        $response->assertSee('Lihat Bukti Pembayaran');
        $response->assertSee('payments/proof-detail.jpg');
    }

    public function test_user_without_payment_view_permission_cannot_open_payment_pages(): void
    {
        $role = Role::where('name', 'Teknisi')->firstOrFail();
        $teknisi = User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        $pop = $this->createPop('POP-PAY-NOACCESS', 'PPN', 'POP Payment No Access');
        $invoice = $this->createInvoice($pop, 'No Access Payment Customer', 'INV-202606-7301');
        $payment = $this->createPayment($invoice, 'PAY-202606-7301', '2026-06-13', 'cash', 'valid');

        $this->actingAs($teknisi)->get(route('payments.index'))->assertForbidden();
        $this->actingAs($teknisi)->get(route('payments.show', $payment->id))->assertForbidden();
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
            'phone' => '081234567890',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'customer_status' => 'aktif',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. Payment List Test',
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Payment List Test',
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
        ?string $proofFile = null
    ): Payment {
        return Payment::create([
            'payment_number' => $paymentNumber,
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $invoice->pop_id,
            'payment_date' => $paymentDate,
            'payment_method' => $method,
            'amount' => 150000,
            'received_by' => User::where('email', 'owner@whusnet.net')->value('id'),
            'proof_file' => $proofFile,
            'payment_status' => $status,
            'note' => 'Pembayaran test.',
        ]);
    }
}
