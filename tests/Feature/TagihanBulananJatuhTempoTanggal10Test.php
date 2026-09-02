<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use Database\Seeders\InternetPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gejala yang dijaga: jatuh tempo tagihan bulanan melenceng dari tanggal 10.
 *
 * Aturannya jendela kalender tetap — terbit tanggal 1, tempo tanggal 10 — bukan
 * "tanggal cron jalan + 10 hari". Implementasinya dulu `addDays(9)`, yang cuma
 * kebetulan mendarat di tanggal 10 karena `$periodStart` selalu tanggal 1.
 * Offset semacam itu diam-diam salah begitu ada yang menggeser `$periodStart`.
 *
 * Command tidak butuh sesi/permission, jadi test ini sengaja tanpa seeder RBAC.
 */
class TagihanBulananJatuhTempoTanggal10Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(InternetPackageSeeder::class);
    }

    private function createPelangganAktif(float $monthlyPrice = 110000): Customer
    {
        $package = InternetPackage::query()->firstOrFail();

        $customer = Customer::factory()->create([
            'status' => 'active',
            'internet_package_id' => $package->id,
        ]);

        CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'monthly_price' => $monthlyPrice,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => $monthlyPrice,
            'activation_date' => '2026-05-10',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        return $customer;
    }

    public function test_terbit_tanggal_1_tempo_tanggal_10(): void
    {
        $customer = $this->createPelangganAktif();

        $this->travelTo('2026-07-01 00:30:00');
        $this->artisan('billing:generate-monthly-invoices')->assertExitCode(0);
        $this->travelBack();

        $invoice = Invoice::where('customer_id', $customer->id)->firstOrFail();

        $this->assertSame('2026-07-01', $invoice->issue_date->format('Y-m-d'));
        $this->assertSame('2026-07-10', $invoice->due_date->format('Y-m-d'));
    }

    public function test_tempo_tetap_tanggal_10_walau_cron_telat_jalan(): void
    {
        $customer = $this->createPelangganAktif();

        // Cron telat/dipicu manual pertengahan bulan — tempo tidak boleh ikut geser.
        $this->travelTo('2026-07-18 09:00:00');
        $this->artisan('billing:generate-monthly-invoices')->assertExitCode(0);
        $this->travelBack();

        $invoice = Invoice::where('customer_id', $customer->id)->firstOrFail();

        $this->assertSame('2026-07-01', $invoice->issue_date->format('Y-m-d'));
        $this->assertSame('2026-07-10', $invoice->due_date->format('Y-m-d'));
    }

    public function test_periode_lampau_bisa_ditambal_lewat_opsi_period(): void
    {
        // Gejala aslinya: cron mati saat tanggal 1, bulan itu lewat, dan tagihannya
        // tidak pernah terbit karena command dipatok bulan berjalan.
        $customer = $this->createPelangganAktif();

        $this->travelTo('2026-08-10 09:00:00');
        $this->artisan('billing:generate-monthly-invoices', ['--period' => '2026-07'])->assertExitCode(0);
        $this->travelBack();

        $invoice = Invoice::where('customer_id', $customer->id)->firstOrFail();

        $this->assertSame('2026-07', $invoice->billing_period);
        $this->assertSame('2026-07-01', $invoice->issue_date->format('Y-m-d'));
        $this->assertSame('2026-07-10', $invoice->due_date->format('Y-m-d'));
    }

    public function test_menambal_periode_yang_sudah_punya_tagihan_tidak_bikin_dobel(): void
    {
        $customer = $this->createPelangganAktif();

        $this->travelTo('2026-08-10 09:00:00');
        $this->artisan('billing:generate-monthly-invoices', ['--period' => '2026-07'])->assertExitCode(0);
        $this->artisan('billing:generate-monthly-invoices', ['--period' => '2026-07'])->assertExitCode(0);
        $this->travelBack();

        $this->assertSame(1, Invoice::where('customer_id', $customer->id)->count());
    }

    public function test_period_format_salah_ditolak_tanpa_bikin_tagihan(): void
    {
        $customer = $this->createPelangganAktif();

        $this->artisan('billing:generate-monthly-invoices', ['--period' => '2026-7'])->assertExitCode(1);

        $this->assertSame(0, Invoice::where('customer_id', $customer->id)->count());
    }

    public function test_tempo_tanggal_10_juga_di_bulan_februari(): void
    {
        // Bulan pendek: offset hari gampang meleset, tanggal tetap 10.
        $customer = $this->createPelangganAktif();

        $this->travelTo('2027-02-01 00:30:00');
        $this->artisan('billing:generate-monthly-invoices')->assertExitCode(0);
        $this->travelBack();

        $invoice = Invoice::where('customer_id', $customer->id)->firstOrFail();

        $this->assertSame('2027-02-01', $invoice->issue_date->format('Y-m-d'));
        $this->assertSame('2027-02-10', $invoice->due_date->format('Y-m-d'));
    }
}
