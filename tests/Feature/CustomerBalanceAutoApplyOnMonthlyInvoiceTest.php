<?php

namespace Tests\Feature;

use App\Enums\BalanceMutationSource;
use App\Enums\InvoiceStatus;
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
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * ADHOC-92 — auto-pakai saldo pelanggan ke tagihan BULANAN begitu terbit
 * (GenerateMonthlyInvoicesCommand + CustomerBalanceService::applyToOpenInvoices()).
 * Studi kasus rancangan: paket 150k, AWAL prorate 100k dibayar 550k sekaligus
 * → 450k jadi saldo, otomatis melunasi 3 bulan berikutnya.
 */
class CustomerBalanceAutoApplyOnMonthlyInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected InternetPackage $package;

    protected Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-20 10:00:00'));

        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();
        $this->pop = Pop::create([
            'code' => 'POP-SALDO-1',
            'pop_code' => 'SLD1',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Saldo Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    public function test_saldo_dari_bayar_di_muka_otomatis_melunasi_tagihan_bulanan_berikutnya(): void
    {
        $this->travelTo(Carbon::parse('2026-07-01 10:00:00'));
        [$customer] = $this->createCustomerWithAwalInvoice('C-SLD-0001', '2026-07-01');

        // Bayar 550k sekaligus di AWAL 100k → 450k jadi saldo.
        $awal = Invoice::where('customer_id', $customer->id)->where('invoice_type', 'awal')->firstOrFail();
        $this->recordPayment($awal, 550000);

        $balances = app(CustomerBalanceService::class);
        $this->assertSame(450000.0, $balances->balance($customer));

        // Generator September: tagihan bulanan terbit + langsung auto-lunas dari saldo.
        $this->travelTo(Carbon::parse('2026-09-01 01:00:00'));
        $this->artisan('billing:generate-monthly-invoices', ['--period' => '2026-09'])->assertSuccessful();

        $sept = Invoice::where('customer_id', $customer->id)->where('billing_period', '2026-09')->firstOrFail();
        $this->assertSame(InvoiceStatus::LUNAS, $sept->invoice_status);

        $payment = Payment::where('invoice_id', $sept->id)->where('payment_method', 'saldo')->firstOrFail();
        $this->assertSame('150000.00', $payment->amount);
        $this->assertSame('150000.00', $payment->balance_used_amount);

        $this->assertSame(300000.0, $balances->balance($customer));
    }

    public function test_saldo_habis_setelah_tiga_bulan_bulan_keempat_tetap_normal(): void
    {
        $this->travelTo(Carbon::parse('2026-06-01 10:00:00'));
        [$customer] = $this->createCustomerWithAwalInvoice('C-SLD-0002', '2026-06-01');
        $awal = Invoice::where('customer_id', $customer->id)->where('invoice_type', 'awal')->firstOrFail();
        $this->recordPayment($awal, 550000);

        foreach (['2026-07', '2026-08', '2026-09'] as $period) {
            $this->travelTo(Carbon::parse("{$period}-01 01:00:00"));
            $this->artisan('billing:generate-monthly-invoices', ['--period' => $period])->assertSuccessful();
        }

        $this->assertSame(0.0, app(CustomerBalanceService::class)->balance($customer));

        $this->travelTo(Carbon::parse('2026-10-01 01:00:00'));
        $this->artisan('billing:generate-monthly-invoices', ['--period' => '2026-10'])->assertSuccessful();

        $okt = Invoice::where('customer_id', $customer->id)->where('billing_period', '2026-10')->firstOrFail();
        $this->assertSame(InvoiceStatus::BELUM_DIBAYAR, $okt->invoice_status);
        $this->assertFalse(Payment::where('invoice_id', $okt->id)->exists());
    }

    public function test_saldo_kurang_dari_tagihan_tetap_dipakai_semua_jadi_cicilan(): void
    {
        $this->travelTo(Carbon::parse('2026-08-01 10:00:00'));
        [$customer] = $this->createCustomerWithAwalInvoice('C-SLD-0003', '2026-08-01');
        $awal = Invoice::where('customer_id', $customer->id)->where('invoice_type', 'awal')->firstOrFail();
        // 100k tagihan AWAL + 50k lebih → saldo cuma 50k, tagihan bulanan 150k.
        $this->recordPayment($awal, 150000);

        $this->assertSame(50000.0, app(CustomerBalanceService::class)->balance($customer));

        $this->travelTo(Carbon::parse('2026-09-01 01:00:00'));
        $this->artisan('billing:generate-monthly-invoices', ['--period' => '2026-09'])->assertSuccessful();

        $sept = Invoice::where('customer_id', $customer->id)->where('billing_period', '2026-09')->firstOrFail();
        $this->assertSame(InvoiceStatus::SEBAGIAN, $sept->invoice_status);
        $this->assertSame('100000.00', $sept->remaining_amount);

        $payment = Payment::where('invoice_id', $sept->id)->where('payment_method', 'saldo')->firstOrFail();
        $this->assertSame('50000.00', $payment->amount);
        $this->assertSame([1, false], [
            $payment->installmentContext()['number'],
            $payment->installmentContext()['settles'],
        ]);

        $this->assertSame(0.0, app(CustomerBalanceService::class)->balance($customer));
    }

    public function test_fifo_periode_terlama_dulu_saat_ada_dua_tagihan_bulanan_terbuka(): void
    {
        [$customer] = $this->createCustomerWithAwalInvoice('C-SLD-0004', '2026-06-01');

        $juli = $this->makeBulananInvoice($customer, '2026-07');
        $agustus = $this->makeBulananInvoice($customer, '2026-08');

        // Saldo 200k dari kredit non-payment (pola creditWithoutPayment,
        // ADHOC-68) — cukup buat Juli (150k) + sebagian Agustus (50k).
        app(CustomerBalanceService::class)->creditWithoutPayment(
            $customer, 200000, $this->pop->id, 'Test saldo FIFO', BalanceMutationSource::BACKFILL
        );

        app(CustomerBalanceService::class)->applyToOpenInvoices($customer);

        $juli->refresh();
        $agustus->refresh();

        $this->assertSame(InvoiceStatus::LUNAS, $juli->invoice_status);
        $this->assertSame(InvoiceStatus::SEBAGIAN, $agustus->invoice_status);
        $this->assertSame('100000.00', $agustus->remaining_amount);
    }

    public function test_invoice_batal_dan_tak_tertagih_dilewati(): void
    {
        [$customer] = $this->createCustomerWithAwalInvoice('C-SLD-0005', '2026-06-01');

        $batal = $this->makeBulananInvoice($customer, '2026-07');
        $batal->update(['invoice_status' => InvoiceStatus::BATAL->value]);

        $takTertagih = $this->makeBulananInvoice($customer, '2026-08');
        $takTertagih->update(['invoice_status' => InvoiceStatus::TAK_TERTAGIH->value]);

        app(CustomerBalanceService::class)->creditWithoutPayment(
            $customer, 200000, $this->pop->id, 'Test skip batal', BalanceMutationSource::BACKFILL
        );

        $created = app(CustomerBalanceService::class)->applyToOpenInvoices($customer);

        $this->assertSame([], $created);
        $this->assertSame(200000.0, app(CustomerBalanceService::class)->balance($customer));
    }

    public function test_invoice_awal_tidak_ikut_auto_pay(): void
    {
        [$customer] = $this->createCustomerWithAwalInvoice('C-SLD-0006', '2026-09-01');
        $awal = Invoice::where('customer_id', $customer->id)->where('invoice_type', 'awal')->firstOrFail();

        app(CustomerBalanceService::class)->creditWithoutPayment(
            $customer, 500000, $this->pop->id, 'Test skip AWAL', BalanceMutationSource::BACKFILL
        );

        $created = app(CustomerBalanceService::class)->applyToOpenInvoices($customer);

        $this->assertSame([], $created);
        $awal->refresh();
        $this->assertSame(InvoiceStatus::BELUM_DIBAYAR, $awal->invoice_status);
    }

    public function test_saldo_nol_tidak_membuat_payment_apa_pun(): void
    {
        [$customer] = $this->createCustomerWithAwalInvoice('C-SLD-0007', '2026-08-01');
        $this->makeBulananInvoice($customer, '2026-09');

        $created = app(CustomerBalanceService::class)->applyToOpenInvoices($customer);

        $this->assertSame([], $created);
    }

    public function test_dijalankan_dua_kali_tidak_menggandakan_payment(): void
    {
        $this->travelTo(Carbon::parse('2026-06-01 10:00:00'));
        [$customer] = $this->createCustomerWithAwalInvoice('C-SLD-0008', '2026-06-01');
        $awal = Invoice::where('customer_id', $customer->id)->where('invoice_type', 'awal')->firstOrFail();
        $this->recordPayment($awal, 550000);

        $this->travelTo(Carbon::parse('2026-09-01 01:00:00'));
        $this->artisan('billing:generate-monthly-invoices', ['--period' => '2026-09'])->assertSuccessful();

        $countBefore = Payment::where('payment_method', 'saldo')->count();

        // Dipanggil manual sekali lagi (simulasi command catch-up dijalankan
        // ulang) — invoice September sudah lunas, tidak ada yang disentuh lagi.
        app(CustomerBalanceService::class)->applyToOpenInvoices($customer);

        $this->assertSame($countBefore, Payment::where('payment_method', 'saldo')->count());
    }

    /**
     * @return array{0: Customer}
     */
    protected function createCustomerWithAwalInvoice(string $customerCode, string $activationDate): array
    {
        $customer = Customer::create([
            'customer_code' => $customerCode,
            'full_name' => 'Customer Saldo Test '.$customerCode,
            'primary_phone' => '081234567890',
            'registration_date' => $activationDate,
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $this->pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. Saldo Test',
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Saldo Test',
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
            'activation_date' => $activationDate,
            'due_date' => Carbon::parse($activationDate)->addDays(14)->format('Y-m-d'),
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        Invoice::create([
            'invoice_number' => 'INV-'.$customerCode.'-AWAL',
            'invoice_type' => 'awal',
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $this->package->id,
            'billing_period' => Carbon::parse($activationDate)->format('Y-m'),
            'issue_date' => $activationDate,
            'due_date' => $activationDate,
            'subtotal' => 100000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 100000,
            'paid_amount' => 0,
            'remaining_amount' => 100000,
            'invoice_status' => 'belum_dibayar',
        ]);

        return [$customer];
    }

    protected function makeBulananInvoice(Customer $customer, string $billingPeriod): Invoice
    {
        $service = CustomerService::where('customer_id', $customer->id)->firstOrFail();

        return Invoice::create([
            'customer_service_id' => $service->id,
            'internet_package_id' => $this->package->id,
            'invoice_number' => 'INV-'.$customer->customer_code.'-'.$billingPeriod,
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'billing_period' => $billingPeriod,
            'issue_date' => $billingPeriod.'-01',
            'due_date' => $billingPeriod.'-10',
            'subtotal' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'invoice_status' => 'belum_dibayar',
        ]);
    }

    protected function recordPayment(Invoice $invoice, float $amount): Payment
    {
        $admin = $this->loginAsAdmin();

        $this->actingAs($admin)->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => $invoice->issue_date->format('Y-m-d'),
            'payment_method' => 'cash',
            'amount' => $amount,
        ])->assertRedirect();

        return Payment::where('invoice_id', $invoice->id)->latest('id')->firstOrFail();
    }
}
