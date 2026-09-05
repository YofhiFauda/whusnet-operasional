<?php

namespace App\Enums;

/**
 * Klasifikasi BISNIS "Perangkat Aktif" vs "Material Pasif/Support" — dipakai
 * buat grouping tampilan di Laporan Pemasangan/Maintenance/INFR/O-REQ (dua
 * seksi form terpisah), BUKAN cara hitung stok (itu `TrackingType`).
 * Korelasinya tinggi (Aktif≈SERIALIZED, Pasif≈QUANTITY/BATCH) tapi sengaja
 * kolom/enum terpisah — dua hal itu bisa saja gak sinkron kalau digabung.
 *
 * Resolusi dua-level: default disetel di `ItemCategory::equipment_class`,
 * bisa di-override per baris lewat `Item::equipment_class_override`
 * (nullable). Accessor `Item::getEffectiveEquipmentClassAttribute()` yang
 * nentuin nilai final — lihat `rancangan-ui.md` §3.1. Kategori `lainnya`
 * (catch-all) default PASIF; item spesifik di dalamnya yang ternyata
 * perangkat aktif pakai override, BUKAN pecah kategori baru.
 */
enum EquipmentClass: string
{
    case AKTIF = 'aktif';
    case PASIF = 'pasif';

    public function label(): string
    {
        return match ($this) {
            self::AKTIF => 'Perangkat Aktif',
            self::PASIF => 'Material Pasif/Support',
        };
    }
}
