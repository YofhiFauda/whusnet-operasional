<?php

namespace App\Enums;

/**
 * Cara stok satu Item dihitung di gudang — bukan klasifikasi bisnis (itu
 * `EquipmentClass`) dan bukan siapa boleh pegangnya (itu `OwnershipMode`).
 * Tiga axis independen, jangan digabung jadi satu kolom/enum. Lihat
 * docs/plan/warehouse/warehouse_inventory_asset_traceability_analysis_advanced
 * §16.1.
 *
 * SENGAJA cuma 3 nilai. `BATCH` (lot_no manual diketik staf) DIHAPUS
 * 2026-09-16 (ADHOC-75) — 0 item pernah pakainya, dan begitu dicek pembukuan
 * real (`docs/plan/warehouse/laporan/LAPORAN ADMIN GUDANG PER AGST 26.xlsx`)
 * polanya BUKAN lot bebas ala BATCH, cuma persis 2 slot harga (Lama/Baru) per
 * barang. Mekanisme itu sekarang jadi bawaan `QUANTITY` (auto, staf gak
 * pernah isi lot manual) — lihat `InventoryReceiveService::resolveQuantityLot()`.
 * Rancangan: `docs/plan/warehouse/analisa-2-slot-harga-quantity.md`.
 */
enum TrackingType: string
{
    /**
     * Setiap unit py identitas unik (SN) — modem, ONT, router, OLT module.
     * Dilacak di `inventory_serials`, satu baris per unit fisik.
     */
    case SERIALIZED = 'serialized';

    /**
     * Dikelola berdasar jumlah polos — RJ45, cable tie, baut, splitter,
     * connector. Gak ada identitas per-unit yang berguna dilacak, TAPI kalau
     * harga beli berubah, sistem otomatis pecah jadi maksimal 2 baris
     * `inventory_balances` (lot bertag harga Lama/Baru, `lot_no` digenerate
     * sistem — staf gak pernah ketik) — lihat
     * `InventoryReceiveService::resolveQuantityLot()`.
     */
    case QUANTITY = 'quantity';

    /**
     * SERIALIZED + qty per-unit — identitas unik PER-ROLL (bukan vendor SN,
     * digenerate sistem) + `length_remaining` yang berkurang sebagian-sebagian
     * (bukan atomik seperti SERIALIZED). Dipakai khusus kabel (drum/roll fiber
     * atau UTP) yang butuh dilacak "roll mana dipakai di mana, sisa berapa
     * meter". Tabel `inventory_rolls`, status `App\Enums\RollStatus`. Lihat
     * docs/TASKS.md ADHOC kabel-per-roll.
     */
    case ROLL = 'roll';

    public function label(): string
    {
        return match ($this) {
            self::SERIALIZED => 'Bernomor Seri (Per Unit)',
            self::QUANTITY => 'Kuantitas (Qty)',
            self::ROLL => 'Roll Kabel (Per Roll, Meter)',
        };
    }
}
