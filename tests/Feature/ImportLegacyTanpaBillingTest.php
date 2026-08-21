<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gejala yang dijaga: skenario go-live "pelanggan saja" ikut menyeret riwayat
 * tagihan legacy yang bolong (~5% cakupan bulan) ke sistem baru, lalu terbaca
 * sebagai tunggakan.
 *
 * `--without-billing` harus membuang tagihan & pembayaran legacy TAPI tetap
 * membawa yang dibutuhkan penagihan ke depan: `monthly_price` (dasar nominal
 * generator bulanan) dan `activation_date` (penjaga tagihan dobel bulan
 * aktivasi). Keduanya berasal dari tabel legacy yang berbeda, jadi gampang
 * ikut hilang kalau ada yang menyederhanakan flag ini jadi "skip billing".
 *
 * Sumber datanya `tests/Fixtures/legacy-mini.sql` — potongan asli dari
 * jetis_db_aplikasi_jetis.sql (3 pelanggan yang punya biaya + bukti transaksi).
 */
class ImportLegacyTanpaBillingTest extends TestCase
{
    use RefreshDatabase;

    private string $fixturePath = 'tests/fixtures/legacy-mini.sql';

    protected function setUp(): void
    {
        parent::setUp();

        // Importer memanggil jalur import pelanggan yang asli — butuh master data
        // (POP, wilayah, paket, RBAC) persis seperti `db:seed` sebelum migrasi
        // dijalankan di server. Tanpa ini pelanggan tetap masuk tapi layanannya
        // gagal dipetakan, dan test gagal karena alasan yang salah.
        $this->seed();
    }

    private function import(array $options = []): void
    {
        $this->artisan('app:import-legacy-sql', array_merge([
            'file' => $this->fixturePath,
            '--branch-code' => 'C',
            '--branch-name' => 'Jetis',
        ], $options))->assertExitCode(0);
    }

    public function test_impor_biasa_membawa_tagihan_legacy(): void
    {
        $this->import();

        $this->assertGreaterThan(0, Customer::count());
        $this->assertGreaterThan(0, Invoice::count(), 'Impor default seharusnya tetap membawa tagihan legacy.');
    }

    public function test_without_billing_tidak_membuat_tagihan_dan_pembayaran(): void
    {
        $this->import(['--without-billing' => true]);

        $this->assertGreaterThan(0, Customer::count(), 'Pelanggan tetap harus terimpor.');
        $this->assertSame(0, Invoice::count());
        $this->assertSame(0, Payment::count());
    }

    public function test_without_billing_tetap_membawa_harga_langganan(): void
    {
        // Tanpa monthly_price, `billing:generate-monthly-invoices` melewati
        // pelanggan tersebut diam-diam — sistem "bersih" tapi tidak pernah menagih.
        $this->import(['--without-billing' => true]);

        $services = CustomerService::query()->get();

        $this->assertGreaterThan(0, $services->count());
        $this->assertGreaterThan(
            0,
            $services->where('monthly_price', '>', 0)->count(),
            'Harga langganan legacy harus tetap ikut walau tagihannya tidak diimpor.'
        );
    }

    public function test_without_billing_tetap_membawa_tanggal_aktivasi(): void
    {
        $this->import(['--without-billing' => true]);

        $this->assertGreaterThan(
            0,
            CustomerService::whereNotNull('activation_date')->count(),
            'activation_date bersumber dari prosedure_permintaan_wifi, bukan dari invoice.'
        );
    }
}
