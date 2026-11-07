<?php

namespace App\Enums;

/**
 * Status Surat Pengajuan Barang (barang yang BELUM ada di gudang, diajukan
 * buat kebutuhan kegiatan/ekspansi — keputusan user 2026-11-06, gap #2 dari
 * 3 feature report 2026-11-05). SATU keputusan: pihak FOP approve atau
 * reject. Tanda tangan "Pengaju" di dokumen cetak otomatis (submit = tanda
 * tangan dia) — BUKAN langkah approval terpisah di sistem, jadi TIDAK ada
 * status "menunggu pengaju".
 *
 * Reject FINAL (keputusan user) — tidak ada revisi di dokumen yang sama,
 * pengaju bikin pengajuan baru kalau mau coba lagi.
 */
enum PengajuanBarangStatus: string
{
    case MENUNGGU_FOP = 'menunggu_fop';
    case DISETUJUI = 'disetujui';
    case DITOLAK = 'ditolak';

    public function label(): string
    {
        return match ($this) {
            self::MENUNGGU_FOP => 'Menunggu Persetujuan FOP',
            self::DISETUJUI => 'Disetujui',
            self::DITOLAK => 'Ditolak',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::MENUNGGU_FOP => 'warning',
            self::DISETUJUI => 'success',
            self::DITOLAK => 'error',
        };
    }
}
