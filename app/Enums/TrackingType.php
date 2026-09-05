<?php

namespace App\Enums;

/**
 * Cara stok satu Item dihitung di gudang — bukan klasifikasi bisnis (itu
 * `EquipmentClass`) dan bukan siapa boleh pegangnya (itu `OwnershipMode`).
 * Tiga axis independen, jangan digabung jadi satu kolom/enum. Lihat
 * docs/plan/warehouse/warehouse_inventory_asset_traceability_analysis_advanced
 * §16.1.
 *
 * SENGAJA cuma 3 nilai, bukan 7 (draf awal sempat usul
 * Serialized/Batch/Quantity/Asset/Consumable/Sparepart/Returnable) —
 * Consumable/Sparepart/Returnable itu properti PEMAKAIAN barang, bukan cara
 * hitung stoknya. "Asset" juga bukan tipe ke-4: itu SERIALIZED +
 * `OwnershipMode::COMPANY_ASSET`, bukan mekanisme stok yang beda.
 */
enum TrackingType: string
{
    /**
     * Setiap unit py identitas unik (SN) — modem, ONT, router, OLT module.
     * Dilacak di `inventory_serials`, satu baris per unit fisik.
     */
    case SERIALIZED = 'serialized';

    /**
     * Dikelola berdasar jumlah polos — RJ45, cable tie, baut. Gak ada
     * identitas per-unit yang berguna dilacak.
     */
    case QUANTITY = 'quantity';

    /**
     * QUANTITY + tag `lot_no` opsional (drum/roll kabel fiber) — BUKAN
     * genealogy batch penuh ala farmasi/food (split/merge/expiry). Cukup
     * jawab "drum LOT-2026-001 sisa berapa meter". Lihat
     * `rancangan-ui.md` §3.8 buat alur lengkap (ISSUE multi-lot, FIFO saat
     * konsumsi, harga per-lot).
     */
    case BATCH = 'batch';

    public function label(): string
    {
        return match ($this) {
            self::SERIALIZED => 'Bernomor Seri (Per Unit)',
            self::QUANTITY => 'Kuantitas (Qty)',
            self::BATCH => 'Batch/Lot',
        };
    }
}
