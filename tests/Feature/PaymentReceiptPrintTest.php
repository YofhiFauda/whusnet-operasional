<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\Customer;
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

/**
 * Struk/kwitansi pembayaran (payments.receipt) — tombol "Cetak Struk" di Modal
 * Hub List Pelanggan sebelumnya tidak punya backend sama sekali.
 *
 * Struk memuat identitas pelanggan + nominal, jadi wajib tunduk POP scope:
 * ID pembayaran yang ketebak tidak boleh membocorkan data cabang lain.
 */
class PaymentReceiptPrintTest extends TestCase
{
    use RefreshDatabase;

    protected InternetPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();
    }

    public function test_receipt_shows_payment_and_customer_data(): void
    {
        $this->loginAsAdmin();
        $pop = $this->createPop('POP-RCP-1', 'RCP1', 'POP Struk 1');
        $payment = $this->createPayment($pop, 'INV-202606-9001', 'PAY-202606-9001');

        $response = $this->get(route('payments.receipt', $payment->id));

        $response->assertStatus(200);
        $response->assertSee('STRUK PEMBAYARAN', false);
        $response->assertSee('PAY-202606-9001', false);
        $response->assertSee('INV-202606-9001', false);
        $response->assertSee('Customer Struk Test', false);
        // Nominal dibayar diformat rupiah tanpa desimal.
        $response->assertSee('Rp 75.000', false);
    }

    /**
     * Struk yang dicetak untuk pelanggan tidak boleh membawa header/footer
     * bawaan browser — di sana tercetak tanggal, judul dokumen, dan URL
     * internal sistem. Teks itu hidup di kotak margin halaman; satu-satunya
     * cara mematikannya adalah menolkan margin @page.
     */
    public function test_struk_menolak_header_footer_bawaan_browser(): void
    {
        $this->loginAsAdmin();
        $pop = $this->createPop('POP-RCP-PG', 'RCPG', 'POP Struk Page');
        $payment = $this->createPayment($pop, 'INV-202606-9010', 'PAY-202606-9010');

        $response = $this->get(route('payments.receipt', $payment->id));

        $response->assertStatus(200);
        $response->assertSee('@page { margin: 0; }', false);
        // Jarak ke tepi kertas pindah ke struk-nya sendiri, bukan hilang.
        $response->assertSee('padding: 6mm 5mm', false);
    }

    public function test_receipt_blocked_for_user_outside_pop_scope(): void
    {
        $popLain = $this->createPop('POP-RCP-2', 'RCP2', 'POP Struk 2');
        $payment = $this->createPayment($popLain, 'INV-202606-9002', 'PAY-202606-9002');

        // Admin yang scope-nya cuma POP lain — punya payments.view tapi bukan POP ini.
        $role = Role::where('name', 'Admin')->firstOrFail();
        $popSendiri = $this->createPop('POP-RCP-3', 'RCP3', 'POP Struk 3');
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $user->pops()->attach($popSendiri->id);
        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create([
            'user_role_scope_id' => $scope->id,
            'pop_id' => $popSendiri->id,
        ]);

        $response = $this->actingAs($user)->get(route('payments.receipt', $payment->id));

        $response->assertForbidden();
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

    protected function createPayment(Pop $pop, string $invoiceNumber, string $paymentNumber): Payment
    {
        $customer = Customer::create([
            'customer_code' => str_replace('INV', 'C', $invoiceNumber),
            'full_name' => 'Customer Struk Test',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. Struk Test',
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

        $invoice = Invoice::create([
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
            'paid_amount' => 75000,
            'remaining_amount' => 75000,
            'invoice_status' => 'sebagian',
        ]);

        return Payment::create([
            'payment_number' => $paymentNumber,
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 75000,
            'received_by' => null,
            'payment_status' => 'valid',
        ]);
    }
}
