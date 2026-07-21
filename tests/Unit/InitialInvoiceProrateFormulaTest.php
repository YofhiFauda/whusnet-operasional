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
 *   sekarang : hari_dalam_bulan - tanggal_aktivasi + 1   (hari aktivasi DITAGIH)
 *   legacy   : hari_dalam_bulan - tanggal_aktivasi       (hari aktivasi GRATIS)
 *
 * Angka kanonik hasil konfirmasi bisnis 2026-07-21: pasang 21 Juli, paket
 * Rp 110.000 → Rp 39.032. Kalau test ini mendarat di 35.484, konvensinya
 * diam-diam kembali ke legacy. Lihat
 * docs/billing-pembayaran/perbandingan-tagihan-awal-vs-bulanan-legacy.md.
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
    public function pasang_21_juli_paket_110rb_menghasilkan_39032(): void
    {
        $result = (new InitialInvoiceService)->calculate($this->serviceWith(110000), '2026-07-21');

        $this->assertSame(11, $result['prorate_days'], 'Hari aktivasi harus ikut ditagih (21-31 Juli = 11 hari).');
        $this->assertSame(31, $result['days_in_month']);
        $this->assertEqualsWithDelta(39032, $result['prorate_amount'], 0.01);
        $this->assertEqualsWithDelta(39032, $result['total_amount'], 0.01);
    }

    #[Test]
    public function konvensi_legacy_ditolak(): void
    {
        $result = (new InitialInvoiceService)->calculate($this->serviceWith(110000), '2026-07-21');

        // 10/31 × 110.000 = 35.484 — angka yang muncul kalau hari aktivasi digratiskan.
        $this->assertNotEqualsWithDelta(35484, $result['prorate_amount'], 0.01);
    }

    /**
     * @return array<string, array{0: float, 1: string, 2: int, 3: int, 4: float}>
     */
    public static function proratePeriodProvider(): array
    {
        return [
            // label => [harga, tanggal aktivasi, hari ditagih, hari sebulan, prorata]
            'aktivasi tanggal 1 = sebulan penuh' => [110000, '2026-07-01', 31, 31, 110000],
            'aktivasi hari terakhir = 1 hari' => [110000, '2026-07-31', 1, 31, 3548],
            'bulan 30 hari' => [110000, '2026-06-21', 10, 30, 36667],
            'februari tahun kabisat' => [110000, '2028-02-21', 9, 29, 34138],
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
        $this->assertEqualsWithDelta(39032, $result['prorate_amount'], 0.01);
        $this->assertEqualsWithDelta(164032, $result['subtotal'], 0.01);
    }
}
