<?php

namespace App\Enums;

/**
 * Status kanonik `inventory_rolls` (barang tracking_type=ROLL — kabel per
 * roll). SATU-SATUNYA acuan status buat entitas ini, sama semangat
 * `SerialStatus` — jangan bikin status lain di tempat lain.
 *
 * BEDA dari `SerialStatus`: roll gak pernah "terpasang" atomik (gak ada
 * INSTALLED) — dia habis dipotong-potong ke banyak pelanggan/task berbeda
 * sampai `length_remaining` = 0. `IN_USE` = sudah mulai dipotong tapi belum
 * habis; `DEPLETED` = sisa 0 meter (terminal, sejalan tapi bukan sama makna
 * dengan INSTALLED di SerialStatus).
 */
enum RollStatus: string
{
    case RECEIVED = 'received';
    case AVAILABLE = 'available';
    case TRANSFERRED = 'transferred';
    case ISSUED = 'issued';
    case IN_USE = 'in_use';
    case DEPLETED = 'depleted';
    case RETURNED = 'returned';
    case DAMAGED = 'damaged';
    case LOST = 'lost';
    case SCRAPPED = 'scrapped';
    case QUARANTINE = 'quarantine';

    public function label(): string
    {
        return match ($this) {
            self::RECEIVED => 'Diterima',
            self::AVAILABLE => 'Tersedia',
            self::TRANSFERRED => 'Dalam Transfer',
            self::ISSUED => 'Dikeluarkan ke Teknisi',
            self::IN_USE => 'Sedang Dipakai (Sisa Sebagian)',
            self::DEPLETED => 'Habis',
            self::RETURNED => 'Dikembalikan',
            self::DAMAGED => 'Rusak',
            self::LOST => 'Hilang',
            self::SCRAPPED => 'Dimusnahkan',
            self::QUARANTINE => 'Karantina',
        };
    }
}
