<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regresi 2026-11-11: audit migrasi 5 dump cabang menemukan pelanggan,
 * layanan, dan pembayaran yang hilang TANPA error di terminal. Fixture
 * `tests/fixtures/legacy-data-tidak-hilang.sql` memuat satu baris per kasus.
 */
class MigrasiLegacyTidakAdaDataHilangTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        Pop::create([
            'code' => 'PST',
            'pop_code' => 'PST',
            'name' => 'Pusat',
            'type' => 'pusat',
            'status' => 'active',
            'registration_prefix' => 'RQ',
            'cid_prefix' => 'P',
        ]);
    }

    private function import(): PendingCommand
    {
        return $this->artisan('app:import-legacy-sql', [
            'file' => 'tests/fixtures/legacy-data-tidak-hilang.sql',
            '--branch-code' => 'X',
            '--branch-name' => 'Uji',
            '--pusat-code' => 'PST',
        ]);
    }

    #[Test]
    public function pelanggan_sesudah_alamat_bertitik_koma_tetap_terimpor(): void
    {
        $this->import()->assertExitCode(0);

        $this->assertTrue(Customer::where('old_customer_id', 'PE000071')->exists(), 'PE000071 hilang — INSERT terpotong oleh ";" di alamat PE000070.');
        $this->assertSame(
            'luworo rt;09 rw;02 pilangkenceng',
            Customer::where('old_customer_id', 'PE000070')->value('address')
        );
    }

    #[Test]
    public function biaya_tanpa_pasang_dan_bulanan_yang_dibayar_tetap_jadi_tagihan_lunas(): void
    {
        $this->import()->assertExitCode(0);

        $payment = Payment::where('old_request_id', 'RQ000013')->first();
        $this->assertNotNull($payment, 'Pembayaran Rp 12.000 hilang karena baris biaya-nya dianggap log aktivasi.');
        $this->assertEquals(12000, (float) $payment->amount);
        $this->assertEquals(12000, (float) $payment->invoice->total_amount);
        $this->assertSame(InvoiceStatus::LUNAS, $payment->invoice->invoice_status);
    }

    #[Test]
    public function biaya_materai_tanpa_pembayaran_tetap_bukan_tagihan(): void
    {
        $this->import()->assertExitCode(0);

        $this->assertFalse(Invoice::where('old_request_id', 'RQ000014')->exists(), 'Log klik "Berhasil Active" tidak boleh jadi utang hantu.');
        $this->assertTrue(CustomerService::where('old_request_id', 'RQ000014')->exists());
    }

    #[Test]
    public function kode_paket_yang_tidak_dikenal_tidak_membuang_layanan(): void
    {
        $this->import()->assertExitCode(0);

        $this->assertTrue(CustomerService::where('old_request_id', 'RQ000026')->exists(), "Layanan dengan IDPAKET 'undefined' ditolak importer.");
    }

    #[Test]
    public function status_diproses_dipetakan_ke_pemasangan_berjalan(): void
    {
        $this->import()->assertExitCode(0);

        $service = CustomerService::where('old_request_id', 'RQ001910')->first();
        $this->assertNotNull($service, "Layanan berstatus 'DIPROSES' ditolak importer.");
        $this->assertSame('installation_in_progress', $service->service_status);
    }

    #[Test]
    public function rq_yatim_aktif_jadi_pelanggan_aktif_ber_cid(): void
    {
        $this->import()
            ->expectsOutputToContain('RQ001634→PE001630 (ACTIVE)')
            ->assertExitCode(0);

        $customer = Customer::where('old_customer_id', 'PE001630')->first();
        $this->assertNotNull($customer, 'Pelanggan aktif tanpa baris pengguna tidak boleh hilang.');
        $this->assertSame('PE001630', $customer->full_name);
        $this->assertSame('active', CustomerService::where('old_request_id', 'RQ001634')->value('service_status'));
        $this->assertNotEmpty($customer->cid);
    }

    #[Test]
    public function rq_yatim_putus_masuk_kelompok_putus_beserta_tagihan_dan_pembayarannya(): void
    {
        $this->import()->assertExitCode(0);

        $this->assertSame('terminated', CustomerService::where('old_request_id', 'RQ000170')->value('service_status'));
        $this->assertTrue(Invoice::where('old_request_id', 'RQ000170')->exists());
        $this->assertEquals(121000, (float) Payment::where('old_request_id', 'RQ000170')->sum('amount'));
    }

    #[Test]
    public function rq_yatim_gagal_masuk_kelompok_gagal(): void
    {
        $this->import()->assertExitCode(0);

        $this->assertSame('rejected', CustomerService::where('old_request_id', 'RQ000194')->value('service_status'));
        $this->assertTrue(Customer::where('old_customer_id', 'PE000190')->exists());
    }

    #[Test]
    public function tanggal_nol_legacy_tidak_ditulis_sebagai_tanggal_tahun_minus_satu(): void
    {
        // SQLite menerima '-0001-11-30', MySQL strict menolak dan me-rollback
        // seluruh batch — jadi yang dijaga di sini nilainya, bukan error-nya.
        $this->import()->assertExitCode(0);

        $rejectedAt = Customer::where('old_customer_id', 'PE000040')->value('rejected_at');
        $this->assertNotNull($rejectedAt);
        $this->assertGreaterThanOrEqual(2000, Carbon::parse($rejectedAt)->year);
    }

    #[Test]
    public function tidak_ada_baris_legacy_yang_ditolak_importer(): void
    {
        $this->import()
            ->expectsOutputToContain('Rekonsiliasi: tidak ada baris legacy yang ditolak importer.')
            ->assertExitCode(0);
    }

    #[Test]
    public function akun_dummy_legacy_yang_terdaftar_tidak_ikut_migrasi_tapi_pelanggan_asli_tetap_masuk(): void
    {
        // Nama file fixture SAMA PERSIS dengan dump Sambit asli karena
        // MigrateLegacyDataCommand::DUMMY_LEGACY_ACCOUNTS di-key per basename.
        // PE000060 = salinan baris Faker lorem-ipsum asli dari dump produksi
        // (audit 2026-11); PE000099 pelanggan asli pembanding.
        $this->artisan('app:import-legacy-sql', [
            'file' => 'tests/fixtures/samb_db_aplikasi_sambit.sql',
            '--branch-code' => 'G',
            '--branch-name' => 'Sambit',
            '--pusat-code' => 'PST',
        ])
            ->expectsOutputToContain('akun dummy/test legacy dikeluarkan dari migrasi')
            ->assertExitCode(0);

        $this->assertFalse(Customer::where('old_customer_id', 'PE000060')->exists(), 'Akun dummy Faker lorem-ipsum tidak boleh ikut ter-migrasi.');
        $this->assertFalse(Invoice::where('old_request_id', 'RQ000061')->exists());
        $this->assertFalse(Payment::where('old_request_id', 'RQ000061')->exists());

        $this->assertTrue(Customer::where('old_customer_id', 'PE000099')->exists(), 'Pelanggan asli jangan ikut terbuang gara-gara exclude dummy.');
        $this->assertTrue(Invoice::where('old_request_id', 'RQ000099')->exists());
    }
}
