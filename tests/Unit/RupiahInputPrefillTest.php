<?php

namespace Tests\Unit;

use App\Helpers\FormatHelper;
use App\Support\RupiahInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Nilai bawaan kolom bermasking harus bisa PULANG-PERGI tanpa berubah nilainya:
 *
 *     DB → FormatHelper::rupiahInput() → layar → RupiahInput::parse() → DB
 *
 * Versi pertama memformat semua prefill dengan `number_format($v, 0, ...)`.
 * Rapi di layar, tapi membulatkan nominal: sisa tagihan 150.000,50 tampil
 * 150.001 lalu ditolak validasi "melebihi sisa tagihan" — baris yang tidak
 * disentuh siapa pun jadi tak bisa dibayar. Di form setoran akibatnya lebih
 * jauh: uang fisik yang benar terkirim dibulatkan, setoran ditandai SELISIH,
 * dan kolektor ditagih uang yang sudah dia serahkan.
 */
class RupiahInputPrefillTest extends TestCase
{
    #[DataProvider('nilaiBawaan')]
    public function test_prefill_tidak_mengubah_nominal(mixed $dariDb, string $tampil): void
    {
        $this->assertSame($tampil, FormatHelper::rupiahInput($dariDb));

        // Bolak-balik: apa yang tampil harus terbaca kembali jadi nilai semula.
        $this->assertEquals((float) ($dariDb ?? 0), (float) RupiahInput::parse($tampil));
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function nilaiBawaan(): array
    {
        return [
            'bulat' => [150000, '150.000'],
            'bulat desimal nol' => [150000.00, '150.000'],
            'ber-sen' => [150000.5, '150.000,50'],
            'ber-sen dua digit' => [150000.55, '150.000,55'],
            'jutaan ber-sen' => [1250000.25, '1.250.000,25'],
            'nol' => [0, '0'],
            'null' => [null, '0'],
            'string dari kolom decimal' => ['150000.50', '150.000,50'],
        ];
    }
}
