<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use App\Services\CustomerBalanceService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lebih bayar tercatat sebagai `overpay_amount` (informatif, tak berubah)
 * DAN sebagai kredit AKTIF di CustomerBalanceService — dua sumber yang
 * sengaja tumpang tindih (§D-5 di-override 2026-08-18). Kredit dibalik
 * kalau payment sumbernya ditolak.
 */
class CustomerBalanceCreditOnOverpayTest extends TestCase
{
    use RefreshDatabase;

    protected InternetPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();
    }

    public function test_overpay_creates_active_customer_balance_credit(): void
    {
        $admin = $this->loginAsAdmin();
        $pop = $this->createPop('POP-OVP-1', 'OVP1', 'POP Overpay Test');
        $invoice = $this->createInvoice($pop, 'INV-OVP-0001');

        $response = $this->actingAs($admin)->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 200000, // sisa tagihan cuma 150.000
        ]);

        $response->assertRedirect(route('invoices.show', $invoice->id));

        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame('50000.00', $payment->overpay_amount);

        $customer = $invoice->customer;
        $balance = app(CustomerBalanceService::class)->balance($customer);
        $this->assertSame(50000.0, $balance);

        $this->assertDatabaseHas('customer_balance_mutations', [
            'customer_id' => $customer->id,
            'type' => 'credit',
            'amount' => '50000.00',
            'payment_id' => $payment->id,
        ]);
    }

    public function test_rejecting_overpay_payment_reverses_the_credit(): void
    {
        $admin = $this->loginAsAdmin();
        $pop = $this->createPop('POP-OVP-2', 'OVP2', 'POP Overpay Test 2');
        $invoice = $this->createInvoice($pop, 'INV-OVP-0002');

        $this->actingAs($admin)->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 200000,
        ]);

        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();
        $customer = $invoice->customer;

        $this->assertSame(50000.0, app(CustomerBalanceService::class)->balance($customer));

        $this->actingAs($admin)->post(route('payments.reject', $payment->id), [
            'reject_reason' => 'Salah input nominal.',
        ]);

        $payment->refresh();
        $this->assertSame('ditolak', $payment->payment_status->value);

        $this->assertSame(0.0, app(CustomerBalanceService::class)->balance($customer));
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
            'full_name' => 'Customer Overpay Test',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. Overpay Test',
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Overpay Test',
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
