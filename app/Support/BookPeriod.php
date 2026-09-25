<?php

namespace App\Support;

/**
 * SATU-SATUNYA definisi "periode pembukuan terkunci".
 *
 * Tutup buku bukan tombol: begitu bulan berganti, buku baru terbuka dan
 * seluruh periode sebelumnya terkunci PERMANEN — tidak ada buka ulang.
 * Transaksi yang menyentuh periode terkunci (bayar mundur, tolak pembayaran
 * lama, batal hapus buku lama) ditolak; koreksinya dicatat di periode
 * berjalan. Piutang yang dibayar belakangan masuk bulan uangnya diterima,
 * bukan menggeser bulan tagihannya.
 *
 * Kuncinya diturunkan dari kalender, BUKAN dari ada/tidaknya baris
 * `period_closings`: snapshot itu cuma pembeku angka laporan yang dibuat
 * scheduler. Kalau kunci bergantung pada snapshot, scheduler yang telat
 * jalan membuka celah otak-atik periode lama.
 *
 * `$period` berformat 'Y-m', jadi perbandingan string aman.
 */
final class BookPeriod
{
    public static function current(): string
    {
        return now()->format('Y-m');
    }

    public static function isLocked(?string $period): bool
    {
        return $period !== null && $period < self::current();
    }

    /**
     * Tanggal paling awal yang masih boleh dipakai transaksi (Y-m-d).
     */
    public static function firstOpenDate(): string
    {
        return now()->startOfMonth()->toDateString();
    }
}
