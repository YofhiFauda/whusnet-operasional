<?php

namespace Tests\Feature;

use App\Enums\PaymentPeriodType;
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
 * `Payment::periodType()` — badge "Keterangan" (Bayar Bulanan / Piutang /
 * Lebih Bayar) yang ditampilkan di tabel Sudah Bayar (Worklist Kolektor,
 * Worksheet Admin) dan Laporan Bayar Kolektor.
 *
 * Klasifikasi WAJIB beku terhadap bulan payment itu SENDIRI diterima
 * (`collected_date` ?: `payment_date`), bukan `now()` — kalau tidak, label
 * payment lama diam-diam berubah tiap pergantian bulan.
 */
class PaymentPeriodTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function createPop(): Pop
    {
        return Pop::create([
            'code' => 'POP-PPT',
            'pop_code' => 'PPT',
            'registration_prefix' => 'CP',
            'cid_prefix' => 'DP',
            'name' => 'POP Payment Period Type',
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function createInvoice(Pop $pop, string $code, string $billingPeriod): Invoice
    {
        $package = InternetPackage::query()->firstOrFail();

        $customer = Customer::create([
            'customer_code' => $code,
            'full_name' => 'Pelanggan '.$code,
            'primary_phone' => '081234567890',
            'registration_date' => '2026-01-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $package->id,
            'address' => 'Jl. '.$code,
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. '.$code,
            'village' => 'Desa Test',
            'district' => 'Kecamatan Test',
            'city' => 'Kota Test',
            'province' => 'Jawa Timur',
        ]);

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'monthly_price' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => 150000,
            'activation_date' => '2026-01-01',
            'due_date' => '2026-01-15',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        return Invoice::create([
            'invoice_number' => 'INV-'.$code,
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $package->id,
            'billing_period' => $billingPeriod,
            'issue_date' => $billingPeriod.'-01',
            'due_date' => $billingPeriod.'-15',
            'subtotal' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'invoice_status' => 'belum_dibayar',
        ]);
    }

    private function payment(Invoice $invoice, string $collectedDate, float $amount = 150000, ?float $overpay = null): Payment
    {
        return Payment::create([
            'payment_number' => 'PAY-TEST-'.$invoice->id,
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $invoice->pop_id,
            'payment_date' => $collectedDate,
            'collected_date' => $collectedDate,
            'payment_method' => 'cash',
            'amount' => $amount,
            'overpay_amount' => $overpay,
            'payment_status' => 'valid',
        ]);
    }

    public function test_bulanan_when_billing_period_matches_collection_month(): void
    {
        $pop = $this->createPop();
        $invoice = $this->createInvoice($pop, 'C-PPT-A', '2026-08');

        $payment = $this->payment($invoice, '2026-08-10');

        $this->assertSame(PaymentPeriodType::BULANAN, $payment->periodType());
        $this->assertSame('Bayar Bulanan', $payment->periodType()->label());
    }

    /** Piutang: invoice periode Juni dibayar di Agustus — nombokin bulan lalu. */
    public function test_piutang_when_billing_period_is_before_collection_month(): void
    {
        $pop = $this->createPop();
        $invoice = $this->createInvoice($pop, 'C-PPT-B', '2026-06');

        $payment = $this->payment($invoice, '2026-08-10');

        $this->assertSame(PaymentPeriodType::PIUTANG, $payment->periodType());
        $this->assertSame('Piutang', $payment->periodType()->label());
    }

    /**
     * Overpay MENANG atas piutang — payment dengan `overpay_amount` > 0
     * selalu diberi label Lebih Bayar, apa pun periode invoice-nya.
     */
    public function test_lebih_bayar_wins_over_piutang_when_overpay_amount_is_positive(): void
    {
        $pop = $this->createPop();
        $invoice = $this->createInvoice($pop, 'C-PPT-C', '2026-06');

        $payment = $this->payment($invoice, '2026-08-10', amount: 150000, overpay: 25000);

        $this->assertSame(PaymentPeriodType::LEBIH_BAYAR, $payment->periodType());
        $this->assertSame('Lebih Bayar (Overpay)', $payment->periodType()->label());
    }
}
