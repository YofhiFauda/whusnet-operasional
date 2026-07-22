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
            'customer_status' => 'aktif',
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
