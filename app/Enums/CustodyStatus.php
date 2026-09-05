<?php

namespace App\Enums;

/**
 * Status `technician_custody` — custody barang QUANTITY/BATCH yang dipegang
 * teknisi (kabel per meter, RJ45 per pcs). BEDA vocabulary dari `SerialStatus`
 * SENGAJA: qty custody bisa habis SEBAGIAN (PARTIALLY_USED), sesuatu yang
 * gak mungkin terjadi buat unit serial yang atomic. Bukan pelanggaran aturan
 * "satu acuan status" — itu dua entitas dengan sifat lifecycle fundamental
 * beda. Lihat docs/plan/warehouse/kontrol-anti-manipulasi.md §7.
 */
enum CustodyStatus: string
{
    /** Custody terbentuk penuh dari ISSUE, belum ada yang diklaim terpakai. */
    case ISSUED = 'issued';

    /**
     * Sebagian qty udah diklaim lewat `task_materials` (kind=terpakai),
     * sisanya masih "menggantung" — wajib direturn, visible di dashboard
     * admin gudang (badge durasi, bukan alert ambang waktu — rancangan-ui.md §3).
     */
    case PARTIALLY_USED = 'partially_used';

    /**
     * Sisa custody sudah fisik balik ke gudang & dikonfirmasi admin
     * (qty_actual vs qty_expected — selisih dicatat ADJUSTMENT reason
     * shrinkage_on_return kalau ada).
     */
    case RETURNED = 'returned';

    /** qty_remaining = 0 murni karena terpakai semua (bukan return). */
    case CONSUMED = 'consumed';

    public function label(): string
    {
        return match ($this) {
            self::ISSUED => 'Dipegang Teknisi',
            self::PARTIALLY_USED => 'Sebagian Terpakai',
            self::RETURNED => 'Sudah Dikembalikan',
            self::CONSUMED => 'Habis Terpakai',
        };
    }
}
