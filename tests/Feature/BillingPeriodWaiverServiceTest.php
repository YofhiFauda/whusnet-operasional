<?php

namespace Tests\Feature;

use App\Enums\BillingWaiverSource;
use App\Events\InvoiceStatusUpdated;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Pop;
use App\Services\BillingPeriodWaiverService;
use Database\Seeders\InternetPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ADHOC-87 — guard G1-G7 `BillingPeriodWaiverService`. Rancangan:
 * docs/plan/billing/analisa-rancangan-request-deaktivasi-bebas-tagihan-periode.md §4.3/§6.
 */
class BillingPeriodWaiverServiceTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    private BillingPeriodWaiverService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InternetPackageSeeder::class);
        $this->service = app(BillingPeriodWaiverService::class);

        $this->pop = Pop::create([
            'code' => 'POP-WAIVER',
            'pop_code' => 'WVR',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Waiver Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function customerWithService(string $status = 'active'): array
    {
        static $seq = 0;
        $seq++;

        $customer = Customer::create([
            'customer_code' => 'C-WVR-'.str_pad((string) $seq, 6, '0', STR_PAD_LEFT),
            'full_name' => 'Pelanggan Waiver '.$seq,
            'primary_phone' => '08111222'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT),
            'registration_date' => now()->subYears(2),
            'pop_id' => $this->pop->id,
            'status' => $status,
        ]);

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => InternetPackage::first()->id,
            'service_status' => 'aktif',
            'billing_cycle' => 'monthly',
            'package_name_snapshot' => 'Paket Test',
            'package_price_snapshot' => 150000,
            'monthly_price' => 150000,
            'total_monthly_bill' => 150000,
            'activation_date' => now()->subYears(2),
        ]);

        return [$customer, $service];
    }

    private function invoiceFor(Customer $customer, CustomerService $service, string $period, array $overrides = []): Invoice
    {
        return Invoice::create(array_merge([
            'invoice_number' => 'INV-'.str_replace('-', '', $period).'-'.random_int(1000, 9999),
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $customer->pop_id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $service->internet_package_id,
            'billing_period' => $period,
            'issue_date' => $period.'-01',
            'due_date' => $period.'-10',
            'subtotal' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'invoice_status' => 'belum_dibayar',
        ], $overrides));
    }

    #[Test]
    public function waive_invoice_belum_dibayar_menjadi_batal_tanpa_ubah_total_amount(): void
    {
        $actor = $this->loginAsAdmin();
        [$customer, $service] = $this->customerWithService();
        $period = now()->format('Y-m');
        $invoice = $this->invoiceFor($customer, $service, $period);

        $waivers = $this->service->waive($customer, [$period], 'Koneksi mati sebelum putus', BillingWaiverSource::TERMINATION, $actor);

        $this->assertCount(1, $waivers);
        $invoice->refresh();
        $this->assertSame('batal', $invoice->invoice_status->value);
        $this->assertEquals(0.0, (float) $invoice->remaining_amount);
        $this->assertEquals(150000.0, (float) $invoice->total_amount);
        $this->assertSame($invoice->id, $waivers->first()->invoice_id);
        $this->assertSame('termination', $waivers->first()->source->value);
    }

    #[Test]
    public function g1_invoice_bukan_bulanan_ditolak(): void
    {
        $actor = $this->loginAsAdmin();
        [$customer, $service] = $this->customerWithService();
        $period = now()->format('Y-m');
        $this->invoiceFor($customer, $service, $period, ['invoice_type' => 'awal', 'invoice_number' => 'INV-AWAL-TEST']);

        $this->expectException(ValidationException::class);
        $this->service->waive($customer, [$period], 'Coba bebaskan aktivasi', BillingWaiverSource::TERMINATION, $actor);
    }

    #[Test]
    public function g2_invoice_sebagian_dibayar_ditolak(): void
    {
        $actor = $this->loginAsAdmin();
        [$customer, $service] = $this->customerWithService();
        $period = now()->format('Y-m');
        $this->invoiceFor($customer, $service, $period, [
            'paid_amount' => 50000,
            'remaining_amount' => 100000,
            'invoice_status' => 'sebagian',
        ]);

        $this->expectException(ValidationException::class);
        $this->service->waive($customer, [$period], 'Coba bebaskan yang sudah dibayar', BillingWaiverSource::TERMINATION, $actor);
    }

    #[Test]
    public function g2_invoice_lunas_ditolak(): void
    {
        $actor = $this->loginAsAdmin();
        [$customer, $service] = $this->customerWithService();
        $period = now()->format('Y-m');
        $this->invoiceFor($customer, $service, $period, [
            'paid_amount' => 150000,
            'remaining_amount' => 0,
            'invoice_status' => 'lunas',
        ]);

        $this->expectException(ValidationException::class);
        $this->service->waive($customer, [$period], 'Coba bebaskan yang lunas', BillingWaiverSource::TERMINATION, $actor);
    }

    #[Test]
    public function g3_periode_di_luar_jendela_mundur_ditolak(): void
    {
        $actor = $this->loginAsAdmin();
        [$customer, $service] = $this->customerWithService();
        $oldPeriod = now()->subMonths(2)->format('Y-m');
        $this->invoiceFor($customer, $service, $oldPeriod, ['invoice_number' => 'INV-OLD-TEST']);

        $this->expectException(ValidationException::class);
        $this->service->waive($customer, [$oldPeriod], 'Coba bebaskan piutang lama', BillingWaiverSource::TERMINATION, $actor);

        $this->assertSame('belum_dibayar', Invoice::where('invoice_number', 'INV-OLD-TEST')->first()->invoice_status->value);
    }

    #[Test]
    public function g3_periode_masa_depan_cuma_boleh_lewat_cuti(): void
    {
        $actor = $this->loginAsAdmin();
        [$customer] = $this->customerWithService();
        $future = now()->addMonth()->format('Y-m');

        $this->expectException(ValidationException::class);
        $this->service->waive($customer, [$future], 'Coba bebaskan masa depan via terminate', BillingWaiverSource::TERMINATION, $actor);
    }

    #[Test]
    public function cuti_periode_belum_terbit_membuat_waiver_tanpa_invoice(): void
    {
        $actor = $this->loginAsAdmin();
        [$customer] = $this->customerWithService();
        $future = now()->addMonth()->format('Y-m');

        $waivers = $this->service->waive($customer, [$future], 'Cuti bulan depan', BillingWaiverSource::LEAVE, $actor);

        $this->assertCount(1, $waivers);
        $this->assertNull($waivers->first()->invoice_id);
        $this->assertSame('active', $customer->fresh()->status);
    }

    #[Test]
    public function g6_pelanggan_terminated_tetap_boleh_dibebaskan_tagihannya(): void
    {
        // Disederhanakan 2026-09-24: "Bebaskan Tagihan Periode" jadi
        // SATU-SATUNYA pintu (CustomerTerminationService tidak lagi
        // memanggil waive() sama sekali), jadi harus tetap bisa dipakai
        // buat pelanggan yang sudah putus — mis. admin kelupaan bebasin
        // tagihan sebelum klik Putus Langganan.
        $actor = $this->loginAsAdmin();
        [$customer, $service] = $this->customerWithService('terminated');
        $period = now()->format('Y-m');
        $this->invoiceFor($customer, $service, $period, ['invoice_number' => 'INV-TERM-TEST']);

        $waivers = $this->service->waive($customer, [$period], 'Bebasin tagihan yang kelupaan sebelum putus', BillingWaiverSource::LEAVE, $actor);

        $this->assertCount(1, $waivers);
        $this->assertSame('terminated', $customer->fresh()->status);
    }

    #[Test]
    public function g6_ditolak_untuk_pelanggan_yang_belum_pernah_berlangganan(): void
    {
        $actor = $this->loginAsAdmin();
        [$customer] = $this->customerWithService('waiting_survey');
        $period = now()->format('Y-m');

        $this->expectException(ValidationException::class);
        $this->service->waive($customer, [$period], 'Coba bebaskan sebelum pernah aktif', BillingWaiverSource::LEAVE, $actor);
    }

    #[Test]
    public function g7_periode_yang_sudah_diwaive_ditolak_bukan_500(): void
    {
        $actor = $this->loginAsAdmin();
        [$customer, $service] = $this->customerWithService();
        $period = now()->format('Y-m');
        $this->invoiceFor($customer, $service, $period);

        $this->service->waive($customer, [$period], 'Pertama', BillingWaiverSource::TERMINATION, $actor);

        $this->expectException(ValidationException::class);
        $this->service->waive($customer, [$period], 'Coba lagi', BillingWaiverSource::TERMINATION, $actor);
    }

    #[Test]
    public function dispatch_invoice_status_updated_saat_pembatalan(): void
    {
        Event::fake([InvoiceStatusUpdated::class]);
        $actor = $this->loginAsAdmin();
        [$customer, $service] = $this->customerWithService();
        $period = now()->format('Y-m');
        $invoice = $this->invoiceFor($customer, $service, $period);

        $this->service->waive($customer, [$period], 'Test dispatch', BillingWaiverSource::TERMINATION, $actor);

        Event::assertDispatched(InvoiceStatusUpdated::class, fn ($event) => $event->invoice->id === $invoice->id);
    }

    #[Test]
    public function revoke_menghapus_baris_tanpa_menghidupkan_invoice(): void
    {
        $actor = $this->loginAsAdmin();
        [$customer, $service] = $this->customerWithService();
        $period = now()->format('Y-m');
        $invoice = $this->invoiceFor($customer, $service, $period);

        $waiver = $this->service->waive($customer, [$period], 'Bebaskan dulu', BillingWaiverSource::TERMINATION, $actor)->first();

        $this->service->revoke($waiver, $actor, 'Ternyata salah, mau ditagih lagi');

        $this->assertDatabaseMissing('customer_billing_waivers', ['id' => $waiver->id]);
        $this->assertSame('batal', $invoice->fresh()->invoice_status->value);
    }

    #[Test]
    public function eligible_periods_menandai_alasan_untuk_yang_tidak_eligible(): void
    {
        $actor = $this->loginAsAdmin();
        [$customer, $service] = $this->customerWithService();
        $oldPeriod = now()->subMonths(2)->format('Y-m');
        $this->invoiceFor($customer, $service, $oldPeriod, ['invoice_number' => 'INV-ELIG-OLD']);

        $rows = $this->service->eligiblePeriods($customer, BillingWaiverSource::TERMINATION);
        $row = $rows->firstWhere('billing_period', $oldPeriod);

        $this->assertNotNull($row);
        $this->assertFalse($row['eligible']);
        $this->assertNotNull($row['reason']);
    }
}
