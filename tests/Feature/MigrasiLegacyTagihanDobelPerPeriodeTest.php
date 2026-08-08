<?php

namespace Tests\Feature;

use App\Enums\InvoiceType;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Regresi migrasi legacy: satu tagihan per periode, bukan satu tagihan per
 * pelanggan yang menelan semua pembayaran.
 *
 * Gejala yang dijaga (lihat
 * docs/billing-pembayaran/analisa-duplikasi-tagihan-pembayaran-migrasi-legacy.md):
 *  - Ardiyanto: paket 165.000 tapi tagihan jadi 330.000 dengan dua "pembayaran awal"
 *  - Wiyono Wonoketro: utang hantu Rp 11.000 dari baris log klik "Berhasil Active"
 *  - Tagihan bertanggal TGLINSERT yang meleset bertahun-tahun
 *  - subtotal yang tidak nyambung dengan total_amount
 */
class MigrasiLegacyTagihanDobelPerPeriodeTest extends TestCase
{
    use RefreshDatabase;

    private const FIXTURE = 'tests/fixtures/legacy/duplikasi-tagihan-migrasi.sql';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $this->artisan('app:import-legacy-sql', [
            'file' => self::FIXTURE,
            '--branch-code' => 'C',
            '--branch-name' => 'Jetis',
        ])->assertSuccessful();
    }

    private function invoicesOf(string $legacyRequestId): Collection
    {
        $customer = Customer::where('old_request_id', $legacyRequestId)->first();
        $this->assertNotNull($customer, "Pelanggan {$legacyRequestId} harus ikut terimport.");

        return Invoice::where('customer_id', $customer->id)->orderBy('billing_period')->get();
    }

    public function test_bukti_bayar_dengan_bulan_tagihan_sama_tidak_jadi_dua_pembayaran(): void
    {
        // IN000035 punya dua bukti dengan BULANTAGIHAN identik (2022-11-02) tapi
        // INSERTED_AT berbeda dua bulan — itu satu pembayaran yang tercatat dobel
        // oleh batch re-insert sistem lama, bukan pembayaran bulan berikutnya.
        $invoices = $this->invoicesOf('RQ000004');

        $this->assertCount(1, $invoices, 'Ardiyanto harus punya tepat satu tagihan.');

        $invoice = $invoices->first();
        $this->assertSame('165000.00', (string) $invoice->total_amount, 'Total tidak boleh jadi 2 x harga paket.');
        $this->assertSame(1, Payment::where('invoice_id', $invoice->id)->count());
        $this->assertSame('165000.00', (string) $invoice->paid_amount);
    }

    public function test_periode_tagihan_diambil_dari_bulan_tagihan_bukan_tglinsert(): void
    {
        // TGLINSERT baris IN000035 bernilai 2025-05-08 (kolom ON UPDATE), padahal
        // pembayarannya November 2022. Periode invoice harus ikut pembayaran.
        $invoice = $this->invoicesOf('RQ000004')->first();

        $this->assertSame('2022-11', $invoice->billing_period);
        $this->assertSame('2022-11', $invoice->issue_date->format('Y-m'));
    }

    public function test_baris_biaya_tanpa_biaya_pasang_bukan_tagihan_awal(): void
    {
        // BIAYALAINLAIN (materai Rp 11.000) menempel di hampir semua baris legacy,
        // jadi tidak boleh dipakai sebagai penanda tagihan registrasi.
        $invoice = $this->invoicesOf('RQ000004')->first();

        $this->assertSame(InvoiceType::BULANAN->value, $invoice->invoice_type->value);
    }

    public function test_bukti_bayar_beda_periode_terbit_jadi_dua_tagihan_terpisah(): void
    {
        // IN000905 punya dua bukti dengan BULANTAGIHAN berbeda (Agustus & September
        // 2023) — dua tagihan yang sah, bukan duplikat.
        $invoices = $this->invoicesOf('RQ000821');

        $this->assertCount(2, $invoices);
        $this->assertSame(['2023-08', '2023-09'], $invoices->pluck('billing_period')->all());

        foreach ($invoices as $invoice) {
            $this->assertSame('110645.00', (string) $invoice->total_amount);
            $this->assertSame(1, Payment::where('invoice_id', $invoice->id)->count());
        }
    }

    public function test_baris_log_aktivasi_tidak_menerbitkan_utang_hantu(): void
    {
        // Wiyono: sembilan klik "Berhasil Active" bikin baris biaya tanpa biaya
        // pasang & bulanan. Baris itu bukan tagihan — dulu jadi invoice Rp 11.000
        // yang tidak pernah ada di sistem lama.
        $invoices = $this->invoicesOf('RQ001191');

        $this->assertCount(1, $invoices, 'Hanya tagihan reaktivasi yang boleh terbit.');
        $this->assertSame('120032.00', (string) $invoices->first()->total_amount);
        $this->assertSame('2025-05', $invoices->first()->billing_period);

        $this->assertSame(
            0,
            Invoice::where('total_amount', 11000)->count(),
            'Tidak boleh ada tagihan yang isinya cuma materai.'
        );
    }

    public function test_baris_registrasi_asli_terbit_awal_lalu_bulanan(): void
    {
        // Hanya BIAYAPASANG > 0 yang benar-benar tagihan pemasangan. Periode
        // pertama jadi AWAL (prorata), periode berikutnya BULANAN.
        $invoices = $this->invoicesOf('RQ000032');

        $this->assertCount(2, $invoices);

        $awal = $invoices->firstWhere('billing_period', '2024-03');
        $this->assertNotNull($awal);
        $this->assertSame(InvoiceType::AWAL->value, $awal->invoice_type->value);
        $this->assertSame('180000.00', (string) $awal->total_amount);
        // 180.000 < 250.000 + 11.000 + 110.000, jadi ini tagihan yang diprorata manual.
        $this->assertEqualsWithDelta(180000, (float) $awal->prorate_amount, 0.01);

        $bulanan = $invoices->firstWhere('billing_period', '2024-04');
        $this->assertNotNull($bulanan);
        $this->assertSame(InvoiceType::BULANAN->value, $bulanan->invoice_type->value);
        $this->assertSame('110000.00', (string) $bulanan->total_amount);
        $this->assertSame('0.00', (string) $bulanan->other_fee, 'Materai hanya sekali di tagihan awal.');
    }

    public function test_metode_dan_penerima_pembayaran_diambil_per_periode(): void
    {
        // apikeuangan_buktitransaksilunas juga di-key IDTRANSAKSI yang konstan
        // seumur hidup pelanggan. Dulu satu baris dicap ke semua pembayaran cost id
        // itu, jadi kuitansi bulan kedua mencatut metode & penerima bulan pertama.
        $invoices = $this->invoicesOf('RQ000821');

        $agustus = Payment::where('invoice_id', $invoices->firstWhere('billing_period', '2023-08')->id)->first();
        $september = Payment::where('invoice_id', $invoices->firstWhere('billing_period', '2023-09')->id)->first();

        $this->assertSame('cash', $agustus->payment_method);
        $this->assertSame('transfer', $september->payment_method);
        $this->assertNotSame($agustus->received_by_old, $september->received_by_old);
        $this->assertSame('Setor via bank', $september->note);
    }

    public function test_subtotal_selalu_nyambung_dengan_total_amount(): void
    {
        // Rumus lama menjumlah ulang komponen dan menghitung materai dua kali,
        // bikin subtotal Rp 11.000 untuk tagihan ratusan ribu.
        $invoices = Invoice::all();
        $this->assertNotEmpty($invoices);

        foreach ($invoices as $invoice) {
            $expected = (float) $invoice->total_amount - (float) $invoice->ppn + (float) $invoice->discount;
            $this->assertEqualsWithDelta(
                $expected,
                (float) $invoice->subtotal,
                0.01,
                "Subtotal {$invoice->invoice_number} tidak nyambung dengan totalnya."
            );
        }
    }

    public function test_tidak_ada_invoice_dengan_lebih_dari_satu_pembayaran_periode_sama(): void
    {
        $duplikat = Payment::selectRaw('invoice_id, billing_period, count(*) as jumlah')
            ->groupBy('invoice_id', 'billing_period')
            ->havingRaw('count(*) > 1')
            ->get();

        $this->assertCount(0, $duplikat, 'Satu invoice tidak boleh menampung dua pembayaran periode yang sama.');
    }
}
