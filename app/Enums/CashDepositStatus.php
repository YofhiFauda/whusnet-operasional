<?php

namespace App\Enums;

/**
 * Status Setoran Kas Admin — satu tingkat di atas Setoran Kolektor.
 *
 * Rantai uangnya: pelanggan → kolektor → **admin** → owner/bank. Enum ini
 * mengurus anak panah ketiga, yang sebelum modul ini tidak pernah tercatat
 * sama sekali.
 *
 * Sengaja BUKAN memakai ulang `DepositStatus`: dua arah selisihnya punya
 * konsekuensi yang berbeda dari sisi kolektor (lihat masing-masing case), dan
 * status yang artinya berbeda tidak boleh berbagi nama.
 *
 * docs/plan/kolektor/analisa-setoran-kas-admin.md §4.2.
 */
enum CashDepositStatus: string
{
    case MENUNGGU_VERIFIKASI = 'menunggu_verifikasi';
    case TERVERIFIKASI = 'terverifikasi';

    /**
     * Uang fisik yang diserahkan KURANG dari yang tercatat sistem —
     * kewajiban admin, harus dijelaskan dan ditutup Owner.
     */
    case SELISIH_KURANG = 'selisih_kurang';

    /**
     * Uang fisik LEBIH dari yang tercatat.
     *
     * **Beda dari `DepositStatus::LEBIH_SETOR` yang terminal.** Pada kolektor,
     * uang lebih dikembalikan fisik saat itu juga sehingga urusannya selesai
     * di meja. Pada kas admin tidak ada siapa pun yang menerima pengembalian:
     * uangnya tetap ada di brankas kantor dan asalnya tetap belum jelas. Kalau
     * dibuat terminal, kelebihan kas jadi temuan yang menutup dirinya sendiri —
     * persis jenis selisih yang paling perlu diperiksa.
     */
    case SELISIH_LEBIH = 'selisih_lebih';

    case DIHAPUS_BUKU = 'dihapus_buku';

    /**
     * Sentinel TITIK NOL pencatatan kas — satu baris, dibuat oleh migrasi.
     *
     * Seluruh setoran kolektor & pembayaran manual yang sudah ada SEBELUM modul
     * ini hidup ditautkan ke baris ini, supaya tidak muncul sebagai kewajiban
     * setor yang belum ditunaikan di hari pertama. Uangnya sudah lama masuk
     * bank di dunia nyata; sistem cuma tidak pernah mencatatnya.
     *
     * TERMINAL KERAS: tak bisa diverifikasi, tak bisa dihapus buku, tak pernah
     * masuk daftar setoran maupun laporan Owner. Ia tidak mengklaim uang apa
     * pun pernah disetorkan — `declared_amount` 0 dan `depositor_id` NULL —
     * ia cuma menyatakan "sebelum titik ini tidak tercatat".
     *
     * Dipilih ketimbang cutoff berbasis tanggal supaya aturan query tetap
     * SATU (`cash_deposit_id IS NULL`); cutoff tanggal menuntut syarat kedua
     * yang harus diulang di tiap query kas dan pasti ada yang terlewat.
     *
     * docs/plan/kolektor/analisa-setoran-kas-admin.md §7.
     */
    case SALDO_AWAL = 'saldo_awal';

    public function label(): string
    {
        return match ($this) {
            self::MENUNGGU_VERIFIKASI => 'Menunggu Verifikasi',
            self::TERVERIFIKASI => 'Terverifikasi',
            self::SELISIH_KURANG => 'Kurang Setor',
            self::SELISIH_LEBIH => 'Lebih Setor (belum jelas asalnya)',
            self::DIHAPUS_BUKU => 'Dihapus Buku',
            self::SALDO_AWAL => 'Saldo Awal (titik nol pencatatan)',
        };
    }

    /**
     * Sudah selesai diperiksa kantor — termasuk yang berakhir selisih.
     */
    public function isVerified(): bool
    {
        return $this !== self::MENUNGGU_VERIFIKASI && $this !== self::SALDO_AWAL;
    }

    /**
     * Selisih yang masih menggantung dan menuntut keputusan Owner.
     */
    public function isOpenDifference(): bool
    {
        return $this === self::SELISIH_KURANG || $this === self::SELISIH_LEBIH;
    }

    /**
     * Baris yang tidak boleh muncul di daftar setoran, laporan, maupun aksi
     * apa pun. Hanya sentinel titik nol.
     */
    public function isSentinel(): bool
    {
        return $this === self::SALDO_AWAL;
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::MENUNGGU_VERIFIKASI => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
            self::TERVERIFIKASI => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
            self::SELISIH_KURANG => 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',
            self::SELISIH_LEBIH => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-400',
            self::DIHAPUS_BUKU => 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300',
            self::SALDO_AWAL => 'bg-slate-100 text-slate-500 dark:bg-slate-700/50 dark:text-slate-400',
        };
    }
}
