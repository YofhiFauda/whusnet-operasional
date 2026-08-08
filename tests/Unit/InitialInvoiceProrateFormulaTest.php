<?php

namespace Tests\Unit;

use App\Models\CustomerService;
use App\Services\InitialInvoiceService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Mengunci konvensi hari prorata tagihan awal.
 *
 * Rumusnya satu: `hari_ditagih × (harga_paket / hari_dalam_bulan)`. Yang rawan
 * bergeser adalah cara menghitung `hari_ditagih`:
 *
 *   berlaku : hari_dalam_bulan - tanggal_aktivasi       (hari aktivasi GRATIS)
 *   salah   : hari_dalam_bulan - tanggal_aktivasi + 1   (hari aktivasi ditagih)
 *
 * Angka kanonik hasil konfirmasi bisnis 2026-07-21: pasang 21 Juli, paket
 * Rp 110.000 → Rp 35.484 (10 hari, dibulatkan `round` seperti legacy). Kalau
 * test ini mendarat di 39.032, ada yang menambahkan hari aktivasi ke hitungan.
 * Lihat docs/billing-pembayaran/perbandingan-tagihan-awal-vs-bulanan-legacy.md.
 *
 * Unit test murni: tidak menyentuh database, model cuma wadah atribut.
 */
class InitialInvoiceProrateFormulaTest extends TestCase
{
    private function serviceWith(float $monthlyPrice, float $ppn = 0, float $discount = 0): CustomerService
    {
        $service = new CustomerService;
        $service->monthly_price = $monthlyPrice;
        $service->ppn = $ppn;
        $service->discount = $discount;

        return $service;
    }

    #[Test]
    public function pasang_21_juli_paket_110rb_menghasilkan_35484(): void
    {
        $result = (new InitialInvoiceService)->calculate($this->serviceWith(110000), '2026-07-21');

        $this->assertSame(10, $result['prorate_days'], 'Hari aktivasi digratiskan (22-31 Juli = 10 hari).');
        $this->assertSame(31, $result['days_in_month']);
        $this->assertEqualsWithDelta(35484, $result['prorate_amount'], 0.01);
        $this->assertEqualsWithDelta(35484, $result['total_amount'], 0.01);
    }

    #[Test]
    public function hari_aktivasi_tidak_boleh_ikut_ditagih(): void
    {
        $result = (new InitialInvoiceService)->calculate($this->serviceWith(110000), '2026-07-21');

        // 11/31 × 110.000 = 39.032 — angka yang muncul kalau hari aktivasi ikut ditagih.
        $this->assertNotEqualsWithDelta(39032, $result['prorate_amount'], 0.01);
    }

    #[Test]
    public function pembulatan_mengikuti_legacy_round_bukan_floor(): void
    {
        // 10 × (110.000 / 31) = 35.483,87. Legacy memakai round() — nominal
        // tagihan awal hasil migrasi dicocokkan angka per angka dengan ini.
        $result = (new InitialInvoiceService)->calculate($this->serviceWith(110000), '2026-07-21');

        $this->assertEqualsWithDelta(35484, $result['prorate_amount'], 0.01);
        $this->assertNotEqualsWithDelta(35483, $result['prorate_amount'], 0.01);
    }

    #[Test]
    public function aktivasi_hari_terakhir_bulan_ditagih_sebulan_penuh(): void
    {
        // 31 - 31 = 0 hari sisa. Keputusan bisnis: tagih penuh, bukan gratis dan
        // bukan 1 hari. Tebing di ujung bulan disengaja — lihat docblock service.
        $result = (new InitialInvoiceService)->calculate($this->serviceWith(110000), '2026-07-31');

        $this->assertSame(31, $result['prorate_days']);
        $this->assertEqualsWithDelta(110000, $result['prorate_amount'], 0.01);
    }

    /**
     * @return array<string, array{0: float, 1: string, 2: int, 3: int, 4: float}>
     */
    public static function proratePeriodProvider(): array
    {
        return [
            // label => [harga, tanggal aktivasi, hari ditagih, hari sebulan, prorata]
            'aktivasi tanggal 1 = sebulan kurang sehari' => [110000, '2026-07-01', 30, 31, 106452],
            'aktivasi hari terakhir = sebulan penuh' => [110000, '2026-07-31', 31, 31, 110000],
            'aktivasi sehari sebelum akhir bulan = 1 hari' => [110000, '2026-07-30', 1, 31, 3548],
            'bulan 30 hari' => [110000, '2026-06-21', 9, 30, 33000],
            'februari tahun kabisat' => [110000, '2028-02-21', 8, 29, 30345],
        ];
    }

    #[Test]
    #[DataProvider('proratePeriodProvider')]
    public function prorata_mengikuti_panjang_bulan(
        float $monthlyPrice,
        string $issueDate,
        int $expectedDays,
        int $expectedDaysInMonth,
        float $expectedProrate,
    ): void {
        $result = (new InitialInvoiceService)->calculate($this->serviceWith($monthlyPrice), $issueDate);

        $this->assertSame($expectedDays, $result['prorate_days']);
        $this->assertSame($expectedDaysInMonth, $result['days_in_month']);
        $this->assertEqualsWithDelta($expectedProrate, $result['prorate_amount'], 0.01);
    }

    #[Test]
    public function ppn_nol_tidak_menambah_apa_pun(): void
    {
        // PPN sudah termasuk harga paket untuk semua paket; master ppn = 0.
        // Field-nya tetap ada sebagai cadangan, tapi tidak boleh memungut ulang.
        $result = (new InitialInvoiceService)->calculate($this->serviceWith(110000, ppn: 0), '2026-07-21');

        $this->assertEqualsWithDelta(0, $result['ppn_amount'], 0.01);
        $this->assertEqualsWithDelta($result['subtotal'], $result['total_amount'], 0.01);
    }

    #[Test]
    public function biaya_sekali_bayar_masuk_subtotal_tapi_bukan_basis_prorata(): void
    {
        $result = (new InitialInvoiceService)->calculate($this->serviceWith(110000), '2026-07-21', [
            'extra_installation_fee' => 100000,
            'extra_cable_fee' => 25000,
            'extra_pole_fee' => 0,
        ]);

        // Prorata tetap murni dari harga paket — biaya pemasangan tidak ikut diprorata.
        $this->assertEqualsWithDelta(35484, $result['prorate_amount'], 0.01);
        $this->assertEqualsWithDelta(160484, $result['subtotal'], 0.01);
    }

    #[Test]
    public function materai_masuk_subtotal_tapi_bukan_basis_prorata(): void
    {
        $result = (new InitialInvoiceService)->calculate($this->serviceWith(110000), '2026-07-21', [
            'extra_installation_fee' => 125000,
            'other_fee' => 10000,
        ]);

        $this->assertEqualsWithDelta(35484, $result['prorate_amount'], 0.01);
        $this->assertEqualsWithDelta(10000, $result['other_fee'], 0.01);
        $this->assertEqualsWithDelta(170484, $result['subtotal'], 0.01);
        $this->assertEqualsWithDelta(170484, $result['total_amount'], 0.01);
    }

    #[Test]
    public function materai_negatif_dianggap_nol(): void
    {
        $result = (new InitialInvoiceService)->calculate($this->serviceWith(110000), '2026-07-21', [
            'other_fee' => -50000,
        ]);

        $this->assertEqualsWithDelta(0, $result['other_fee'], 0.01);
        $this->assertEqualsWithDelta(35484, $result['subtotal'], 0.01);
    }

    #[Test]
    public function nominal_bulan_berikutnya_penuh_tanpa_prorata_dan_materai(): void
    {
        // Baris terakhir kwitansi: "Mulai bulan depan Rp X/bulan". Harus sama
        // dengan yang nanti diterbitkan GenerateMonthlyInvoicesCommand, kalau
        // tidak admin menjanjikan angka yang berbeda dari tagihan yang datang.
        $result = (new InitialInvoiceService)->calculate($this->serviceWith(110000), '2026-07-21', [
            'extra_installation_fee' => 125000,
            'other_fee' => 10000,
        ]);

        $this->assertEqualsWithDelta(110000, $result['next_month_amount'], 0.01);
    }

    #[Test]
    public function nominal_bulan_berikutnya_ikut_diskon_dan_ppn(): void
    {
        $result = (new InitialInvoiceService)->calculate(
            $this->serviceWith(200000, ppn: 11, discount: 20000),
            '2026-07-21'
        );

        // (200.000 - 20.000) + 11% = 199.800
        $this->assertEqualsWithDelta(199800, $result['next_month_amount'], 0.01);
    }
}
