<?php

namespace App\Enums;

/**
 * Status kanonik `inventory_serials` (barang SERIALIZED — unit atomic, gak
 * bisa "terpasang sebagian"). SATU-SATUNYA acuan status buat entitas ini —
 * warehouse_inventory_asset_traceability_analysis_advanced.md §15 sempat
 * kasih daftar lebih pendek, itu SENGAJA dianggap subset ilustratif, bukan
 * definisi ulang (lihat koreksi §16.6 dokumen yang sama).
 *
 * BEDA total dari `CustodyStatus` (technician_custody, barang QUANTITY/BATCH)
 * — dua vocabulary terpisah karena sifat lifecycle-nya emang beda (unit
 * serial atomic vs qty yang bisa parsial), bukan kelupaan nyatuin.
 */
enum SerialStatus: string
{
    case RECEIVED = 'received';
    case AVAILABLE = 'available';
    case RESERVED = 'reserved';
    case ISSUED = 'issued';
    case IN_USE = 'in_use';
    case INSTALLED = 'installed';
    case TRANSFERRED = 'transferred';
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
            self::RESERVED => 'Dipesan/Diambil (Belum Dipasang)',
            self::ISSUED => 'Dikeluarkan ke Teknisi',
            self::IN_USE => 'Sedang Digunakan',
            self::INSTALLED => 'Terpasang di Pelanggan',
            self::TRANSFERRED => 'Dalam Transfer',
            self::RETURNED => 'Dikembalikan',
            self::DAMAGED => 'Rusak',
            self::LOST => 'Hilang',
            self::SCRAPPED => 'Dimusnahkan',
            self::QUARANTINE => 'Karantina',
        };
    }
}
