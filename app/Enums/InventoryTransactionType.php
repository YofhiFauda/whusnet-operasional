<?php

namespace App\Enums;

/**
 * Tipe baris `inventory_transactions` — ledger APPEND-ONLY (gak boleh
 * di-update/dihapus siapa pun, ditegakkan Observer, bukan cuma konvensi;
 * lihat docs/plan/warehouse/kontrol-anti-manipulasi.md §6). Stok (`inventory_balances`)
 * adalah state yang DITURUNKAN dari ledger ini, bukan sumber kebenaran sendiri
 * — lihat warehouse_inventory_asset_traceability_analysis.md §25.
 *
 * SENGAJA gak ada tipe `SHRINKAGE` terpisah — selisih return direkam sebagai
 * ADJUSTMENT dengan reason `shrinkage_on_return` (kontrol-anti-manipulasi.md
 * §7), biar vocabulary tipe ledger gak numpuk tiap ada skenario baru.
 */
enum InventoryTransactionType: string
{
    /** Barang masuk dari supplier ke Gudang Pusat. */
    case RECEIVE = 'receive';

    /** Gudang ↔ Gudang (Pusat→Cabang). Mengubah `inventory_balances` DUA gudang. */
    case TRANSFER = 'transfer';

    /** Gudang → custody Teknisi. Mengubah `inventory_balances` gudang DAN custody. */
    case ISSUE = 'issue';

    /** Teknisi → Gudang (fisik balik, dikonfirmasi admin — bukan klaim sepihak). */
    case RETURN = 'return';

    /**
     * Koreksi stok non-alur-normal: opname, kerugian (LOST/DAMAGED/SCRAPPED),
     * selisih return (shrinkage). Selalu wajib `reason` + `notes` terisi —
     * lihat kontrol-anti-manipulasi.md §1-2.
     */
    case ADJUSTMENT = 'adjustment';

    /**
     * Teknisi → Teknisi Lain, LANGSUNG (reassign custody, mis. resign/cuti).
     * TIDAK menyentuh `inventory_balances` gudang sama sekali — beda dari
     * TRANSFER (gudang↔gudang) dan ISSUE (gudang→teknisi). Lihat
     * rancangan-ui.md §3.6.
     */
    case TRANSFER_CUSTODY = 'transfer_custody';

    /**
     * Fase 2 P1 (2026-09-03) — hasil Stock Opname (hitung fisik gudang),
     * TERMASUK yang selisihnya NOL (kontrol-anti-manipulasi.md §5: "supaya
     * 'belum pernah opname' vs 'baru saja opname hasilnya pas' tetap beda
     * status yang kelihatan di ledger"). SATU-SATUNYA jalur yang boleh nulis
     * `qty` nol — dibuat lewat `InventoryAdjustmentService::recordStockOpname()`
     * + form terpisah (`warehouse.adjustments.opname.create`), BUKAN numpang
     * form Penyesuaian Stok biasa (`adjustPopBalance()`, yang justru MENOLAK
     * delta nol — itu jalur koreksi manual, beda tujuan).
     */
    case STOCK_OPNAME = 'stock_opname';

    /**
     * Unit SERIALIZED terpasang ke pelanggan — titik terminal traceability
     * (§21 analisa pertama, Asset Traceability). Ketahuan perlu pas nulis
     * integrasi Fase form laporan: SN yang sampai ke pelanggan tetap butuh
     * jejak ledger, gak cukup cuma baca `inventory_serials.status` saat ini
     * (itu proyeksi, bukan histori). Gak mengubah `inventory_balances` gudang
     * ATAU custody teknisi (dua-duanya udah beres di ISSUE) — custody teknisi
     * yang bersangkutan cukup di-null-kan di `inventory_serials` sendiri.
     */
    case INSTALL = 'install';

    public function label(): string
    {
        return match ($this) {
            self::RECEIVE => 'Barang Masuk (Pengadaan)',
            self::TRANSFER => 'Transfer Antar Gudang',
            self::ISSUE => 'Keluar ke Teknisi',
            self::RETURN => 'Pengembalian ke Gudang',
            self::ADJUSTMENT => 'Penyesuaian Stok',
            self::TRANSFER_CUSTODY => 'Alih Custody Teknisi',
            self::STOCK_OPNAME => 'Stok Opname',
            self::INSTALL => 'Terpasang di Pelanggan',
        };
    }
}
