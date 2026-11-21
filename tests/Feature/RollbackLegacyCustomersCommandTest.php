<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerInstallation;
use App\Models\CustomerService;
use App\Models\CustomerSurvey;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RollbackLegacyCustomersCommandTest extends TestCase
{
    use RefreshDatabase;

    protected InternetPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();
    }

    public function test_dry_run_tanpa_force_tidak_menghapus_apa_pun(): void
    {
        $pop = $this->createPop('POP-RB-A', 'RBA', 'Cabang Rollback A');
        $customer = $this->createLegacyCustomer($pop, 'RBA001', 'OLD-RBA001', 'Pelanggan Legacy A');
        $this->createLegacyInvoice($customer, $pop, 'INV-RBA-001', 'OLD-INV-RBA-001');

        $this->artisan('app:rollback-legacy-customers', ['pop_code' => 'RBA'])
            ->assertSuccessful();

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    public function test_force_menghapus_customer_legacy_beserta_invoice_dan_payment_bersih(): void
    {
        $pop = $this->createPop('POP-RB-B', 'RBB', 'Cabang Rollback B');
        $customer = $this->createLegacyCustomer($pop, 'RBB001', 'OLD-RBB001', 'Pelanggan Legacy B');
        $invoice = $this->createLegacyInvoice($customer, $pop, 'INV-RBB-001', 'OLD-INV-RBB-001');
        $this->createLegacyPayment($invoice, 'PAY-RBB-001', 'OLD-PAY-RBB-001');

        $this->artisan('app:rollback-legacy-customers', ['pop_code' => 'RBB', '--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseMissing('payments', ['invoice_id' => $invoice->id]);
    }

    public function test_customer_bukan_hasil_import_tidak_disentuh(): void
    {
        $pop = $this->createPop('POP-RB-C', 'RBC', 'Cabang Rollback C');
        $manualCustomer = Customer::create([
            'customer_code' => 'RBC-MANUAL',
            'full_name' => 'Pelanggan Manual',
            'primary_phone' => '081200000000',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. Manual',
            'old_customer_id' => null,
        ]);

        $this->artisan('app:rollback-legacy-customers', ['pop_code' => 'RBC', '--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('customers', ['id' => $manualCustomer->id]);
    }

    public function test_customer_legacy_dengan_tiket_terkunci_tidak_dihapus(): void
    {
        $pop = $this->createPop('POP-RB-D', 'RBD', 'Cabang Rollback D');
        $customer = $this->createLegacyCustomer($pop, 'RBD001', 'OLD-RBD001', 'Pelanggan Legacy Dengan Tiket');
        $staff = User::factory()->create(['status' => 'active']);

        Ticket::create([
            'ticket_number' => 'TKT-20261120-000001',
            'type' => 'MTN',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'detail_keluhan' => 'Koneksi putus-putus',
            'priority' => 'Medium',
            'created_by' => $staff->id,
        ]);

        $this->artisan('app:rollback-legacy-customers', ['pop_code' => 'RBD', '--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    public function test_customer_legacy_dengan_invoice_baru_terkunci_tidak_dihapus(): void
    {
        $pop = $this->createPop('POP-RB-E', 'RBE', 'Cabang Rollback E');
        $customer = $this->createLegacyCustomer($pop, 'RBE001', 'OLD-RBE001', 'Pelanggan Legacy Dengan Tagihan Baru');
        // Invoice TANPA old_invoice_id = tagihan baru yang terbit pasca-migrasi (bukan hasil import).
        $this->createLegacyInvoice($customer, $pop, 'INV-RBE-002', null);

        $this->artisan('app:rollback-legacy-customers', ['pop_code' => 'RBE', '--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    public function test_rollback_cabang_ikut_menghapus_customer_di_mini_pop_anaknya(): void
    {
        $cabang = $this->createPop('POP-RB-F', 'RBF', 'Cabang Rollback F');
        $miniPop = Pop::create([
            'code' => 'POP-RB-F1',
            'pop_code' => 'RBF1',
            'registration_prefix' => 'RQ',
            'cid_prefix' => 'RBF',
            'name' => 'Mini POP Rollback F1',
            'type' => 'mini_pop',
            'parent_id' => $cabang->id,
            'status' => 'active',
        ]);
        $customer = $this->createLegacyCustomer($miniPop, 'RBF1001', 'OLD-RBF1001', 'Pelanggan Legacy Mini POP');

        $this->artisan('app:rollback-legacy-customers', ['pop_code' => 'RBF', '--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    public function test_data_survey_dan_pemasangan_hasil_import_ikut_terhapus_tanpa_mengunci_rollback(): void
    {
        $pop = $this->createPop('POP-RB-G', 'RBG', 'Cabang Rollback G');
        $customer = $this->createLegacyCustomer($pop, 'RBG001', 'OLD-RBG001', 'Pelanggan Legacy Survey Pemasangan');

        CustomerSurvey::create([
            'customer_id' => $customer->id,
            'survey_status' => 'completed',
            'survey_date' => '2026-05-20',
            'survey_note' => 'Hasil migrasi legacy.',
        ]);

        CustomerInstallation::create([
            'customer_id' => $customer->id,
            'installation_status' => 'completed',
            'finished_date' => '2026-05-25',
            'installation_note' => 'Hasil migrasi legacy.',
        ]);

        $this->artisan('app:rollback-legacy-customers', ['pop_code' => 'RBG', '--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
        $this->assertDatabaseMissing('customer_surveys', ['customer_id' => $customer->id]);
        $this->assertDatabaseMissing('customer_installations', ['customer_id' => $customer->id]);
    }

    protected function createPop(string $code, string $popCode, string $name): Pop
    {
        return Pop::create([
            'code' => $code,
            'pop_code' => $popCode,
            'registration_prefix' => 'RQ',
            'cid_prefix' => $popCode,
            'name' => $name,
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    protected function createLegacyCustomer(Pop $pop, string $customerCode, string $oldCustomerId, string $fullName): Customer
    {
        $customer = Customer::create([
            'customer_code' => $customerCode,
            'full_name' => $fullName,
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. Rollback Test',
            'old_customer_id' => $oldCustomerId,
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Rollback Test',
            'village' => 'Desa Test',
            'district' => 'Kecamatan Test',
            'city' => 'Kota Test',
            'province' => 'Jawa Timur',
        ]);

        CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $this->package->id,
            'package_name_snapshot' => 'Paket Rollback Test',
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

        return $customer;
    }

    protected function createLegacyInvoice(Customer $customer, Pop $pop, string $invoiceNumber, ?string $oldInvoiceId): Invoice
    {
        $service = $customer->customerService()->firstOrFail();

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
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'invoice_status' => 'belum_dibayar',
            'old_invoice_id' => $oldInvoiceId,
        ]);
    }

    protected function createLegacyPayment(Invoice $invoice, string $paymentNumber, string $oldPaymentId): Payment
    {
        $staff = User::factory()->create(['status' => 'active']);

        return Payment::create([
            'payment_number' => $paymentNumber,
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $invoice->pop_id,
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 150000,
            'received_by' => $staff->id,
            'payment_status' => 'valid',
            'note' => 'Pembayaran rollback test.',
            'old_payment_id' => $oldPaymentId,
        ]);
    }
}
