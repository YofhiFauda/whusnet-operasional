<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use Database\Seeders\InternetPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Gejala yang dijaga: pelanggan menerima DUA tagihan untuk periode yang sama —
 * satu AWAL (bulan aktivasi, prorata) dan satu BULANAN (bulan penuh).
 *
 * Dulu satu-satunya yang menahan kombinasi itu adalah pengecekan
 * `activation_date` di GenerateMonthlyInvoicesCommand. Dua penjaga lain
 * (`alreadyExists` dan InvoiceObserver::creating) sama-sama di-scope
 * `invoice_type`, jadi AWAL dan BULANAN dianggap bukan duplikat satu sama lain.
 * Satu kolom salah isi = tagihan dobel, tanpa cadangan.
 *
 * Sekarang pertanyaannya lintas jenis: "sudah ada tagihan LANGGANAN untuk
 * periode ini?". Test ini sengaja merusak `activation_date` untuk membuktikan
 * lapis kedua berdiri sendiri tanpa bergantung pada lapis pertama.
 *
 * Latar lengkap: docs/billing-pembayaran/analisa-pencegahan-tagihan-dobel.md
 */
class SatuTagihanLanggananPerPeriodeTest extends TestCase
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
            // Sengaja SALAH: bulan aktivasi seolah Mei, padahal invoice AWAL
            // di bawah berperiode Juli. Inilah kondisi yang dulu bikin dobel.
            'activation_date' => '2026-05-10',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        return $customer->fresh();
    }

    private function buatInvoice(Customer $customer, string $type, string $period, array $override = []): Invoice
    {
        $service = $customer->customerService;

        return Invoice::create(array_merge([
            'invoice_number' => 'INV-'.strtoupper(uniqid()),
            'invoice_type' => $type,
            'customer_id' => $customer->id,
            'pop_id' => $customer->pop_id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $service->internet_package_id,
            'billing_period' => $period,
            'issue_date' => $period.'-01',
            'due_date' => $period.'-10',
            'subtotal' => 110000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 110000,
            'paid_amount' => 0,
            'remaining_amount' => 110000,
            'invoice_status' => InvoiceStatus::BELUM_DIBAYAR->value,
        ], $override));
    }

    public function test_cron_tidak_menerbitkan_bulanan_kalau_sudah_ada_awal_di_periode_sama(): void
    {
        $customer = $this->createPelangganAktif();

        $this->buatInvoice($customer, InvoiceType::AWAL->value, '2026-07');

        // activation_date menunjuk Mei, jadi lapis 1 TIDAK menahan apa pun di sini.
        $this->travelTo('2026-07-05 07:00:00');
        $this->artisan('billing:generate-monthly-invoices')->assertExitCode(0);
        $this->travelBack();

        $this->assertSame(
            1,
            Invoice::where('customer_id', $customer->id)->count(),
            'Invoice AWAL periode sama harus menahan penerbitan BULANAN, tanpa bergantung activation_date.'
        );
    }

    public function test_observer_menolak_bulanan_kedua_lintas_jenis(): void
    {
        $customer = $this->createPelangganAktif();

        $this->buatInvoice($customer, InvoiceType::AWAL->value, '2026-07');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/sudah punya tagihan langganan untuk periode 2026-07/');

        // Jalur manual/import/tinker — bukan lewat command.
        $this->buatInvoice($customer, InvoiceType::BULANAN->value, '2026-07', [
            'total_amount' => 99000,
            'remaining_amount' => 99000,
        ]);
    }

    public function test_tagihan_dibatalkan_tidak_memblokir_penggantinya(): void
    {
        $customer = $this->createPelangganAktif();

        $this->buatInvoice($customer, InvoiceType::BULANAN->value, '2026-07', [
            'invoice_status' => InvoiceStatus::BATAL->value,
        ]);

        $pengganti = $this->buatInvoice($customer, InvoiceType::BULANAN->value, '2026-07', [
            'total_amount' => 99000,
            'remaining_amount' => 99000,
        ]);

        $this->assertNotNull($pengganti->id);
        $this->assertSame(2, Invoice::where('customer_id', $customer->id)->count());
    }

    public function test_reaktivasi_boleh_berdampingan_dengan_tagihan_langganan(): void
    {
        $customer = $this->createPelangganAktif();

        $this->buatInvoice($customer, InvoiceType::BULANAN->value, '2026-07');

        // Suspend lalu aktif lagi di bulan yang sama — bukan dobel.
        $reaktivasi = $this->buatInvoice($customer, InvoiceType::REAKTIVASI->value, '2026-07', [
            'total_amount' => 50000,
            'remaining_amount' => 50000,
        ]);

        $this->assertNotNull($reaktivasi->id);
    }

    public function test_replay_data_legacy_tetap_boleh_masuk(): void
    {
        $customer = $this->createPelangganAktif();

        $this->buatInvoice($customer, InvoiceType::AWAL->value, '2026-07', [
            'old_invoice_id' => 'IDBIAYA001-AWAL',
        ]);

        // Data lama memang menyimpan pelanggaran historis; migrasi harus tetap
        // bisa memuatnya apa adanya. Pembersihannya ranah BILLING-B0e.
        $legacyBulanan = $this->buatInvoice($customer, InvoiceType::BULANAN->value, '2026-07', [
            'old_invoice_id' => 'IDBIAYA001-BULANAN',
            'total_amount' => 99000,
            'remaining_amount' => 99000,
        ]);

        $this->assertNotNull($legacyBulanan->id);
        $this->assertSame(2, Invoice::where('customer_id', $customer->id)->count());
    }

    public function test_periode_berbeda_tetap_boleh(): void
    {
        $customer = $this->createPelangganAktif();

        $this->buatInvoice($customer, InvoiceType::AWAL->value, '2026-07');
        $agustus = $this->buatInvoice($customer, InvoiceType::BULANAN->value, '2026-08');

        $this->assertNotNull($agustus->id);
    }
}
