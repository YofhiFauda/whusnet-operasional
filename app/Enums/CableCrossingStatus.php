<?php

namespace App\Enums;

/**
 * Status rute kabel melintang jalan (ADHOC-205).
 *
 * `TERPASANG` = masih melintang jalan dan wajib ditarik saat Deact. Dua status
 * lain adalah hasil penarikan di task DEAC. Ini TIDAK sama dengan TaskStatus —
 * rute kabel bukan task, jadi jangan dipetakan ke TicketBucket.
 */
enum CableCrossingStatus: string
{
    case TERPASANG = 'terpasang';
    case DITARIK = 'ditarik';
    case TIDAK_BISA_DITARIK = 'tidak_bisa_ditarik';

    public function label(): string
    {
        return match ($this) {
            self::TERPASANG => 'Terpasang melintang jalan',
            self::DITARIK => 'Sudah ditarik',
            self::TIDAK_BISA_DITARIK => 'Tidak bisa ditarik',
        };
    }

    /**
     * Rute yang masih perlu tindak lanjut — dipakai daftar pemantauan. Penghalang
     * penutupan task DEAC hanya `TERPASANG`, `TIDAK_BISA_DITARIK` tidak menghalangi.
     */
    public function isOpen(): bool
    {
        return $this !== self::DITARIK;
    }
}
