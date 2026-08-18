<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Metode Bayar "Transfer" pada Modal Bayar Invoice wajib mengisi Nama Bank
 * + Nomer Rekening (app/Enums/PaymentMethod::requiresBankDetails()).
 */
class PaymentMethodTransferBankFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected InternetPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();
    }

    public function test_transfer_without_bank_name_is_rejected(): void
    {
        $admin = $this->loginAsAdmin();
        $pop = $this->createPop('POP-TRF-1', 'TRF1', 'POP Transfer Test');
        $invoice = $this->createInvoice($pop, 'INV-TRF-0001');

        $response = $this->actingAs($admin)->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'transfer',
            'account_number' => '1234567890',
            'amount' => 150000,
        ]);

        $response->assertSessionHasErrors('bank_name');
        $this->assertSame(0, Payment::count());
    }

    public function test_transfer_with_complete_bank_details_is_stored_and_audited(): void
    {
        $admin = $this->loginAsAdmin();
        $pop = $this->createPop('POP-TRF-2', 'TRF2', 'POP Transfer Test 2');
        $invoice = $this->createInvoice($pop, 'INV-TRF-0002');

        $response = $this->actingAs($admin)->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'transfer',
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'amount' => 150000,
        ]);

        $response->assertRedirect(route('invoices.show', $invoice->id));

        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame('BCA', $payment->bank_name);
        $this->assertSame('1234567890', $payment->account_number);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Payment::class,
            'auditable_id' => $payment->id,
            'action' => 'create',
        ]);

        $auditLog = AuditLog::where('auditable_type', Payment::class)
            ->where('auditable_id', $payment->id)
            ->where('action', 'create')
            ->firstOrFail();

        $this->assertSame('BCA', $auditLog->new_values['bank_name']);
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

    protected function createInvoice(Pop $pop, string $invoiceNumber): Invoice
    {
        $customer = Customer::create([
            'customer_code' => str_replace('INV', 'C', $invoiceNumber),
            'full_name' => 'Customer Transfer Test',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. Transfer Test',
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Transfer Test',
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
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'invoice_status' => 'belum_dibayar',
        ]);
    }
}
