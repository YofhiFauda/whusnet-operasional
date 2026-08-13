<?php

namespace Tests\Unit;

use App\Support\RupiahInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Titik ribuan yang salah baca = pembayaran 1.000 kali lebih kecil, tanpa satu
 * pun error. Test ini mengunci batas antara "dinormalkan" dan "tidak ditebak".
 */
class RupiahInputTest extends TestCase
{
    #[DataProvider('ketikanKasir')]
    public function test_nominal_ketikan_dinormalkan(mixed $ketikan, mixed $harapan): void
    {
        $this->assertSame($harapan, RupiahInput::parse($ketikan));
    }

    /**
     * @return array<string, array{mixed, mixed}>
     */
    public static function ketikanKasir(): array
    {
        return [
            'ribuan indonesia' => ['150.000', '150000'],
            'jutaan indonesia' => ['1.500.000', '1500000'],
            'dengan prefiks Rp' => ['Rp 150.000', '150000'],
            'dengan spasi' => [' 150.000 ', '150000'],
            'desimal koma' => ['150.000,50', '150000.50'],
            'desimal koma tanpa ribuan' => ['150000,50', '150000.50'],
            'angka polos' => ['150000', '150000'],
            'desimal titik format mesin' => ['150000.50', '150000.50'],

            // Yang TIDAK boleh ditebak: dibiarkan apa adanya supaya ditolak
            // validator, bukan diam-diam berubah nilainya.
            'desimal titik dua digit' => ['1.50', '1.50'],
            'titik ganda tak beraturan' => ['12.34.56', '12.34.56'],
            'huruf' => ['seratus ribu', 'seratus ribu'],
            'kosong' => ['', ''],

            // Nilai non-teks (angka dari JSON) lewat tanpa disentuh.
            'integer' => [150000, 150000],
            'float' => [150000.5, 150000.5],
            'null' => [null, null],
        ];
    }

    public function test_parse_keys_menormalkan_field_terpilih(): void
    {
        $hasil = RupiahInput::parseKeys(
            ['amount' => '150.000', 'note' => '1.500 pelanggan'],
            'amount'
        );

        $this->assertSame('150000', $hasil['amount']);
        // Field yang tidak disebut tidak ikut diubah — kalau tidak, teks bebas
        // yang kebetulan berformat angka ikut jadi korban.
        $this->assertSame('1.500 pelanggan', $hasil['note']);
    }
}
