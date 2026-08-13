<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

/**
 * Uang tidak boleh dihitung dengan float mentah. Test ini menahan dua hal:
 * hasilnya eksak, dan PERBANDINGANNYA eksak — karena yang ditentukan bukan
 * tampilan angka, melainkan cabang keputusan (Lunas vs Sebagian,
 * Terverifikasi vs Selisih).
 */
class MoneyTest extends TestCase
{
    public function test_penjumlahan_banyak_baris_tidak_menumpuk_galat(): void
    {
        $baris = array_fill(0, 1000, 33333.33);

        // Float mentah menghasilkan 33.333.329,9999991469 — meleset −0,00000085.
        $floatMentah = 0.0;
        foreach ($baris as $satu) {
            $floatMentah += $satu;
        }
        $this->assertNotSame(33333330.0, $floatMentah);

        $this->assertSame(33333330.0, Money::sum($baris));
    }

    public function test_perbandingan_tidak_bergantung_pada_representasi_biner(): void
    {
        // 0.1 + 0.2 !== 0.3 di float biner.
        $this->assertNotSame(0.3, 0.1 + 0.2);

        $this->assertTrue(Money::equals(Money::add(0.1, 0.2), 0.3));
        $this->assertTrue(Money::isZero(Money::sub(0.3, Money::add(0.1, 0.2))));
    }

    public function test_sisa_tagihan_yang_terbayar_penuh_benar_benar_nol(): void
    {
        // Tiga cicilan menutup tagihan persis. Dengan float, sisa bisa jadi
        // −0,0000001 dan tagihan LUNAS tetap berstatus Sebagian.
        $sisa = Money::sub(100000, Money::sum([33333.33, 33333.33, 33333.34]));

        $this->assertTrue(Money::isZero($sisa));
        $this->assertSame(0.0, Money::atLeastZero($sisa));
    }

    public function test_pemisahan_lebih_bayar_selalu_utuh(): void
    {
        $diterima = 200000.55;
        $sisaTagihan = 150000.35;

        $masukTagihan = Money::min($diterima, $sisaTagihan);
        $lebihBayar = Money::sub($diterima, $masukTagihan);

        // Dua bagian WAJIB berjumlah persis sama dengan yang diterima —
        // kalau tidak, ada uang yang hilang atau lahir dari pembulatan.
        $this->assertSame(150000.35, $masukTagihan);
        $this->assertSame(50000.20, $lebihBayar);
        $this->assertTrue(Money::equals(Money::add($masukTagihan, $lebihBayar), $diterima));
    }

    public function test_bayar_pas_tidak_melahirkan_lebih_bayar_hantu(): void
    {
        $sisaTagihan = 33333.33;

        $lebihBayar = Money::sub($sisaTagihan, Money::min($sisaTagihan, $sisaTagihan));

        $this->assertTrue(Money::isZero($lebihBayar));
    }

    public function test_atleastzero_tidak_pernah_negatif(): void
    {
        $this->assertSame(0.0, Money::atLeastZero(-0.004));
        $this->assertSame(0.0, Money::atLeastZero(-50000));
        $this->assertSame(1234.56, Money::atLeastZero(1234.56));
    }

    public function test_pembulatan_ke_sen_terjadi_sekali_per_suku(): void
    {
        $this->assertSame(1, Money::cents(0.005));
        $this->assertSame(150000, Money::cents('1500.00'));
        $this->assertSame(0.0, Money::of(null));
        $this->assertSame(1500.0, Money::of('1500'));
    }

    public function test_urutan_perbandingan(): void
    {
        $this->assertTrue(Money::greaterThan(0.02, 0.01));
        $this->assertTrue(Money::lessThan(-30000, 0));
        $this->assertSame(0, Money::compare(1000, 1000.004));
        $this->assertSame(-1, Money::compare(999.99, 1000));
    }
}
