<?php

namespace Tests\Unit;

use App\Console\Commands\MigrateLegacyDataCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Gejala yang dijaga: tagihan hasil import legacy jatuh tempo tanggal 11,
 * sementara tagihan hasil `billing:generate-monthly-invoices` jatuh tempo
 * tanggal 10. Satu aturan bisnis, dua hasil, tergantung jalur mana yang
 * menerbitkan tagihannya.
 *
 * `legacyDueDate()` privat dan command-nya butuh dump SQL untuk jalan penuh,
 * jadi aturan tanggalnya diuji langsung lewat refleksi — bagian inilah yang
 * gampang menyimpang lagi, bukan pipeline import-nya.
 */
class LegacyInvoiceJatuhTempoTanggal10Test extends TestCase
{
    private function dueDate(string $issueDate): string
    {
        $method = new ReflectionMethod(MigrateLegacyDataCommand::class, 'legacyDueDate');

        return $method->invoke(app(MigrateLegacyDataCommand::class), $issueDate);
    }

    #[DataProvider('tagihanBulananProvider')]
    public function test_tagihan_terbit_tanggal_1_tempo_tanggal_10(string $issueDate, string $expected): void
    {
        $this->assertSame($expected, $this->dueDate($issueDate));
    }

    public static function tagihanBulananProvider(): array
    {
        return [
            'bulan biasa' => ['2026-06-01', '2026-06-10'],
            'februari' => ['2026-02-01', '2026-02-10'],
            'akhir tahun' => ['2025-12-01', '2025-12-10'],
        ];
    }

    public function test_tagihan_awal_terbit_setelah_tanggal_10_digeser_ke_bulan_berikutnya(): void
    {
        // Invoice AWAL terbit di tanggal aktivasi. Tempo tidak boleh mendahului
        // terbit — kalau tanggal 10 bulan itu sudah lewat, pakai bulan setelahnya.
        $this->assertSame('2026-06-10', $this->dueDate('2026-05-14'));
    }

    public function test_tagihan_awal_terbit_tepat_tanggal_10_tidak_digeser(): void
    {
        $this->assertSame('2026-05-10', $this->dueDate('2026-05-10'));
    }

    public function test_pergeseran_dari_bulan_31_hari_tidak_lompat_bulan(): void
    {
        // addMonth() dari 31 Januari mendarat di Maret. Tanggalnya dipatok 10,
        // tapi bulannya harus tetap Februari.
        $this->assertSame('2026-02-10', $this->dueDate('2026-01-31'));
    }
}
