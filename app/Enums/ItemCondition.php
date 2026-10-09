<?php

namespace App\Enums;

/**
 * Kondisi FISIK unit `inventory_serials` — axis independen dari `SerialStatus`
 * (posisi/lifecycle di alur gudang→lapangan). SENGAJA flat 3-case, BUKAN
 * duplikat `SerialStatus::DAMAGED`: `used_damaged` di sini nempel di SN yang
 * balik dari pelanggan tapi BELUM (atau gak perlu) masuk alur formal Lapor
 * Rusak (`InventoryAdjustmentService::adjustSerialStatus()`) — kalau nanti
 * dieskalasi ke situ, `SerialStatus` tetap status utama/otoritatif, kondisi
 * cuma metadata tambahan. Lihat docs/plan/warehouse/analisa-gap-kondisi-barang.md.
 */
enum ItemCondition: string
{
    case NEW = 'new';
    case USED_GOOD = 'used_good';
    case USED_DAMAGED = 'used_damaged';

    public function label(): string
    {
        return match ($this) {
            self::NEW => 'Baru',
            self::USED_GOOD => 'Bekas — Kondisi Baik',
            self::USED_DAMAGED => 'Bekas — Rusak',
        };
    }

    /**
     * Varian badge (dipetakan ke .badge-* design token di app.css) — SATU
     * sumber kebenaran warna kondisi, dipakai `<x-warehouse.condition-badge>`
     * biar tidak lagi di-hardcode beda-beda per view (analisa-ui-ux §U4/V6).
     * "Belum dicek" bukan case enum (itu kombinasi condition + checked flag),
     * jadi ditangani di komponen, bukan di sini.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::NEW => 'success',
            self::USED_GOOD => 'info',
            self::USED_DAMAGED => 'error',
        };
    }
}
