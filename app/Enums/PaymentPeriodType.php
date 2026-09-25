<?php

namespace App\Enums;

/**
 * Klasifikasi SATU payment terhadap periode tagihannya — dipakai sebagai
 * badge "Keterangan" di tabel Sudah Bayar (Worklist Kolektor, Worksheet
 * Admin) dan Laporan Bayar Kolektor, supaya kolektor/admin langsung tahu
 * tanpa buka detail invoice: ini bayar rutin, nombokin piutang bulan lalu,
 * atau bayar lebih dari sisa tagihan.
 *
 * Prioritas SALING LEPAS, dicek `Payment::periodType()` dalam urutan ini:
 *   1. LEBIH_BAYAR — `overpay_amount` > 0.
 *   2. PIUTANG — invoice yang dilunasi ber-`billing_period` SEBELUM bulan
 *      payment ini diterima.
 *   3. BULANAN — selain dua di atas.
 */
enum PaymentPeriodType: string
{
    case BULANAN = 'bulanan';
    case PIUTANG = 'piutang';
    case LEBIH_BAYAR = 'lebih_bayar';

    // ADHOC-84 §8.1 — label KEEMPAT dipakai badge majemuk Payment::classification(),
    // BUKAN cabang periodType() (yang tetap 3 nilai lama, saling lepas). Cicilan
    // itu independen dari bulanan/piutang: bisa cicilan tagihan berjalan MAUPUN
    // cicilan piutang lama sekaligus (tampil sebagai dua pil bersebelahan).
    case CICILAN = 'cicilan';

    public function label(): string
    {
        return match ($this) {
            self::BULANAN => 'Bayar Bulanan',
            self::PIUTANG => 'Piutang',
            self::LEBIH_BAYAR => 'Lebih Bayar (Overpay)',
            self::CICILAN => 'Cicilan',
        };
    }

    /**
     * Kelas Tailwind badge — dipakai sama di semua tabel yang menampilkan
     * klasifikasi ini, supaya warnanya tidak menyimpang antar halaman.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::BULANAN => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-400 border border-sky-200 dark:border-sky-500/20',
            self::PIUTANG => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400 border border-amber-200 dark:border-amber-500/20',
            self::LEBIH_BAYAR => 'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-400 border border-violet-200 dark:border-violet-500/20',
            self::CICILAN => 'bg-orange-50 text-orange-700 dark:bg-orange-500/10 dark:text-orange-400 border border-orange-200 dark:border-orange-500/20',
        };
    }
}
