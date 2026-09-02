<?php

namespace App\Support;

/**
 * Aritmetika uang yang eksak: semua operasi dikerjakan dalam **sen bulat**,
 * hasilnya dikembalikan sebagai rupiah float supaya pemanggil lama tak berubah.
 *
 * Kenapa perlu, padahal kolom DB sudah `decimal(12,2)` yang eksak: begitu nilai
 * itu masuk PHP ia jadi float biner, dan pecahan desimal tidak punya
 * representasi persis di biner. Terukur di repo ini:
 *
 *   1.000 baris × 33.333,33  → 33.333.329,9999991469  (meleset −0,00000085)
 *   0.1 + 0.2 === 0.3        → FALSE
 *
 * Selisih sekecil itu tidak pernah terlihat di layar (dibulatkan 2 desimal),
 * tapi ia MENENTUKAN CABANG:
 *
 *   - `remaining <= 0` → invoice Lunas atau tetap Sebagian;
 *   - `difference === 0` → setoran Terverifikasi atau ditandai SELISIH.
 *
 * Setoran yang salah ditandai selisih berarti kolektor ditagih uang yang sudah
 * dia serahkan. Karena itu perbandingan uang TIDAK boleh memakai `==`/`<=`
 * langsung atas float, dan tidak boleh memakai epsilon karangan sendiri yang
 * ditulis ulang di tiap file (`abs($x) > 0.001` sempat tersebar di beberapa
 * tempat, tiap tempat bebas memilih angkanya).
 *
 * Batas: sen disimpan sebagai int 64-bit — aman sampai ±92 triliun rupiah,
 * jauh di atas `decimal(12,2)` (maks 9.999.999.999,99) yang dipakai skema.
 *
 * Kelas ini SENGAJA tidak menyentuh cara nilai dibaca dari request (itu
 * tugas [[RupiahInput]]) maupun cara ditulis ke DB.
 */
class Money
{
    /** Rupiah → sen bulat. Satu-satunya tempat pembulatan terjadi. */
    public static function cents(mixed $rupiah): int
    {
        return (int) round(((float) $rupiah) * 100);
    }

    /** Sen bulat → rupiah. */
    public static function fromCents(int $cents): float
    {
        return $cents / 100;
    }

    /** Normalkan satu nilai ke rupiah 2 desimal. */
    public static function of(mixed $rupiah): float
    {
        return static::fromCents(static::cents($rupiah));
    }

    /**
     * Jumlahkan tanpa akumulasi galat: penjumlahan terjadi di ranah integer,
     * pembulatan cuma sekali per suku.
     *
     * @param  iterable<mixed>  $nilai
     */
    public static function sum(iterable $nilai): float
    {
        $total = 0;

        foreach ($nilai as $satu) {
            $total += static::cents($satu);
        }

        return static::fromCents($total);
    }

    public static function add(mixed $a, mixed $b): float
    {
        return static::fromCents(static::cents($a) + static::cents($b));
    }

    public static function sub(mixed $a, mixed $b): float
    {
        return static::fromCents(static::cents($a) - static::cents($b));
    }

    /** -1, 0, atau 1 — perbandingan eksak di ranah sen. */
    public static function compare(mixed $a, mixed $b): int
    {
        return static::cents($a) <=> static::cents($b);
    }

    public static function equals(mixed $a, mixed $b): bool
    {
        return static::cents($a) === static::cents($b);
    }

    public static function isZero(mixed $nilai): bool
    {
        return static::cents($nilai) === 0;
    }

    public static function greaterThan(mixed $a, mixed $b): bool
    {
        return static::compare($a, $b) > 0;
    }

    public static function lessThan(mixed $a, mixed $b): bool
    {
        return static::compare($a, $b) < 0;
    }

    /** Tidak pernah negatif — dipakai sisa tagihan & sisa kewajiban. */
    public static function atLeastZero(mixed $nilai): float
    {
        return static::fromCents(max(0, static::cents($nilai)));
    }

    /** Yang terkecil di antara dua nominal (mis. porsi yang menutup tagihan). */
    public static function min(mixed $a, mixed $b): float
    {
        return static::fromCents(min(static::cents($a), static::cents($b)));
    }

    /**
     * Rupiah → string desimal presisi (`"150000.00"`), untuk serialisasi JSON API
     * (docs/api/api-portal-pelanggan/). SENGAJA tidak lewat `number_format($float, 2)`
     * — itu balik ke float dulu, persis kelas galat yang dihindari kelas ini. Dirakit
     * langsung dari sen bulat (`intdiv`/`%`), bukan pembulatan kedua.
     */
    public static function decimalString(mixed $rupiah): string
    {
        $cents = static::cents($rupiah);
        $negative = $cents < 0;
        $abs = abs($cents);

        return ($negative ? '-' : '').intdiv($abs, 100).'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }
}
