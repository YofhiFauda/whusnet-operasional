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
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * ADHOC-84 — dropdown Alokasi Pembayaran dihapus (§4.1), peringatan piutang
 * non-blokir (§4.3), dan klasifikasi majemuk Bulanan/Piutang/Cicilan/Lebih
 * Bayar (§8) yang dihitung dari data, bukan diketik kasir.
 *
 * docs/plan/billing/analisa-skema-alokasi-pembayaran-dan-saldo.md
 */
class PaymentAllocationClassificationTest extends TestCase
{
    use RefreshDatabase;

    protected InternetPackage $package;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-06-20 10:00:00'));
        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();
    }

    private function owner(): User
    {
        return User::where('email', 'owner@whusnet.net')->firstOrFail();
    }

    private function createPop(string $code): Pop
    {
        return Pop::create([
            'code' => 'POP-'.$code,
            'pop_code' => $code,
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP '.$code,
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function createInvoice(Pop $pop, float $totalAmount, string $billingPeriod, ?Customer $customer = null): Invoice
    {
        $customer ??= Customer::create([
            'customer_code' => 'C-ALC-'.random_int(1000, 9999),
            'full_name' => 'Pelanggan Alokasi Test',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-01-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. Alokasi Test',
        ]);

        CustomerAddress::firstOrCreate(['customer_id' => $customer->id], [
            'full_address' => 'Jl. Alokasi Test',
            'village' => 'Desa Test',
            'district' => 'Kecamatan Test',
            'city' => 'Kota Test',
            'province' => 'Jawa Timur',
        ]);

        $service = CustomerService::firstOrCreate(['customer_id' => $customer->id], [
            'internet_package_id' => $this->package->id,
            'package_name_snapshot' => $this->package->name,
            'monthly_price' => $totalAmount,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => $totalAmount,
            'activation_date' => '2026-01-01',
            'due_date' => '2026-01-15',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        return Invoice::create([
            'invoice_number' => 'INV-ALC-'.random_int(1000, 9999),
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $this->package->id,
            'billing_period' => $billingPeriod,
            'issue_date' => $billingPeriod.'-01',
            'due_date' => $billingPeriod.'-15',
            'subtotal' => $totalAmount,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => $totalAmount,
            'paid_amount' => 0,
            'remaining_amount' => $totalAmount,
            'invoice_status' => 'belum_dibayar',
        ]);
    }

    private function addPayment(Invoice $invoice, float $amount, string $date, ?float $overpay = null): Payment
    {
        $payment = Payment::create([
            'payment_number' => 'PAY-ALC-'.random_int(10000, 99999),
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $invoice->pop_id,
            'payment_date' => $date,
            'payment_method' => 'cash',
            'amount' => $amount,
            'overpay_amount' => $overpay,
            'received_by' => $this->owner()->id,
            'payment_status' => 'valid',
        ]);

        $invoice->recalculateFromPayments();

        return $payment;
    }

    // ---------------------------------------------------------------
    // §4.1 — dropdown Alokasi dihapus
    // ---------------------------------------------------------------

    public function test_payment_create_page_has_no_allocation_dropdown(): void
    {
        $pop = $this->createPop('NOALC1');
        $invoice = $this->createInvoice($pop, 150000, '2026-06');

        $response = $this->actingAs($this->owner())->get(route('invoices.payments.create', $invoice->id));

        $response->assertOk();
        $response->assertDontSee('Alokasi Pembayaran');
        $response->assertDontSee('qp-allocation', false);
    }

    public function test_note_is_stored_as_is_without_allocation_prefix(): void
    {
        $pop = $this->createPop('NOALC2');
        $invoice = $this->createInvoice($pop, 150000, '2026-06');

        $this->actingAs($this->owner())->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 150000,
            'note' => 'Catatan asli kasir',
        ]);

        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame('Catatan asli kasir', $payment->note);
    }

    // ---------------------------------------------------------------
    // §4.3 — peringatan piutang, non-blokir
    // ---------------------------------------------------------------

    public function test_older_unpaid_invoice_warning_shown_when_customer_has_older_unpaid_invoice(): void
    {
        $pop = $this->createPop('PIUT1');
        $older = $this->createInvoice($pop, 150000, '2026-05');
        $newer = $this->createInvoice($pop, 150000, '2026-06', $older->customer);

        $response = $this->actingAs($this->owner())->get(route('invoices.payments.create', $newer->id));

        $response->assertOk();
        $response->assertSee('Pelanggan ini masih punya tagihan lebih lama');
        $response->assertSee($older->invoice_number);
    }

    public function test_older_unpaid_invoice_warning_absent_when_no_older_invoice(): void
    {
        $pop = $this->createPop('PIUT2');
        $invoice = $this->createInvoice($pop, 150000, '2026-06');

        $response = $this->actingAs($this->owner())->get(route('invoices.payments.create', $invoice->id));

        $response->assertOk();
        $response->assertDontSee('Pelanggan ini masih punya tagihan lebih lama');
    }

    public function test_payment_still_saves_despite_older_unpaid_invoice_warning(): void
    {
        $pop = $this->createPop('PIUT3');
        $older = $this->createInvoice($pop, 150000, '2026-05');
        $newer = $this->createInvoice($pop, 150000, '2026-06', $older->customer);

        $response = $this->actingAs($this->owner())->post(route('invoices.payments.store', $newer->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 150000,
        ]);

        $response->assertRedirect(route('invoices.show', $newer->id));
        $newer->refresh();
        $this->assertSame('lunas', $newer->invoice_status->value);
    }

    public function test_invoice_json_payload_includes_older_unpaid_invoices(): void
    {
        $pop = $this->createPop('PIUT4');
        $older = $this->createInvoice($pop, 150000, '2026-05');
        $newer = $this->createInvoice($pop, 150000, '2026-06', $older->customer);

        $response = $this->actingAs($this->owner())->getJson(route('invoices.show', $newer->id));

        $response->assertOk();
        $response->assertJsonPath('older_unpaid_invoices.0.invoice_number', $older->invoice_number);
    }

    // ---------------------------------------------------------------
    // §8.1 — Payment::classification()
    // ---------------------------------------------------------------

    public function test_classification_is_bulanan_only_for_full_payment_same_period(): void
    {
        $pop = $this->createPop('CLS1');
        $invoice = $this->createInvoice($pop, 150000, '2026-06');
        $payment = $this->addPayment($invoice, 150000, '2026-06-13');

        $labels = $payment->classification();

        $this->assertSame([PaymentPeriodType::BULANAN], $labels);
    }

    public function test_classification_is_piutang_for_older_period_settled_in_full(): void
    {
        $pop = $this->createPop('CLS2');
        $invoice = $this->createInvoice($pop, 150000, '2026-05');
        $payment = $this->addPayment($invoice, 150000, '2026-06-13');

        $labels = $payment->classification();

        $this->assertSame([PaymentPeriodType::PIUTANG], $labels);
    }

    public function test_classification_adds_cicilan_when_payment_does_not_settle_invoice(): void
    {
        $pop = $this->createPop('CLS3');
        $invoice = $this->createInvoice($pop, 150000, '2026-06');
        $payment = $this->addPayment($invoice, 50000, '2026-06-13');

        $labels = $payment->classification();

        $this->assertSame([PaymentPeriodType::BULANAN, PaymentPeriodType::CICILAN], $labels);
    }

    public function test_classification_adds_lebih_bayar_when_overpay(): void
    {
        $pop = $this->createPop('CLS4');
        $invoice = $this->createInvoice($pop, 150000, '2026-06');
        $payment = $this->addPayment($invoice, 150000, '2026-06-13', overpay: 50000);

        $labels = $payment->classification();

        $this->assertSame([PaymentPeriodType::BULANAN, PaymentPeriodType::LEBIH_BAYAR], $labels);
    }

    public function test_classification_combines_piutang_and_cicilan(): void
    {
        $pop = $this->createPop('CLS5');
        $invoice = $this->createInvoice($pop, 150000, '2026-05');
        $payment = $this->addPayment($invoice, 50000, '2026-06-13');

        $labels = $payment->classification();

        $this->assertSame([PaymentPeriodType::PIUTANG, PaymentPeriodType::CICILAN], $labels);
    }

    // ---------------------------------------------------------------
    // §8.2 — filter "Jenis" di Laporan Pembayaran
    // ---------------------------------------------------------------

    public function test_payment_report_classification_filter_narrows_results(): void
    {
        $pop = $this->createPop('RPT1');
        $bulananInvoice = $this->createInvoice($pop, 150000, '2026-06');
        $this->addPayment($bulananInvoice, 150000, '2026-06-13');

        $piutangInvoice = $this->createInvoice($pop, 150000, '2026-05');
        $this->addPayment($piutangInvoice, 150000, '2026-06-14');

        $response = $this->actingAs($this->owner())
            ->get(route('reports.payments.index', ['classification' => 'piutang']));

        $response->assertOk();
        $response->assertSee($piutangInvoice->invoice_number);
        $response->assertDontSee($bulananInvoice->invoice_number);
    }
}
