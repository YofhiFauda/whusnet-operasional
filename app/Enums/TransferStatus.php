<?php

namespace App\Enums;

/**
 * Status header `inventory_transfers` — MUTABLE, beda dari status ledger
 * (`InventoryTransactionType` dkk) yang append-only. Satu kali transisi
 * `IN_TRANSIT` → `RECEIVED`/`RECEIVED_PARTIAL`, gak berulang-ulang diedit —
 * pola sama `Ticket::status` (mutable) berdampingan `ticket_histories`
 * (append-only). Lihat migration `create_inventory_transfers_table` +
 * docs/plan/warehouse/rancangan-ui.md §3.6.
 */
enum TransferStatus: string
{
    /** Sudah dikirim dari Pusat, belum dikonfirmasi diterima Cabang. */
    case IN_TRANSIT = 'in_transit';

    /** Diterima penuh — semua SN/qty cocok expected vs actual. */
    case RECEIVED = 'received';

    /** Diterima sebagian — ada SN mismatch/qty kurang (§2.3 rancangan-ui.md). */
    case RECEIVED_PARTIAL = 'received_partial';

    public function label(): string
    {
        return match ($this) {
            self::IN_TRANSIT => 'Dalam Perjalanan',
            self::RECEIVED => 'Diterima Penuh',
            self::RECEIVED_PARTIAL => 'Diterima Sebagian',
        };
    }

    /**
     * Varian badge (.badge-* di app.css) — dipakai daftar Transfer (analisa-ui-ux
     * §S3). Kuning = masih dalam perjalanan (perlu konfirmasi), hijau = beres,
     * merah = ada selisih yang perlu ditindaklanjuti.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::IN_TRANSIT => 'warning',
            self::RECEIVED => 'success',
            self::RECEIVED_PARTIAL => 'error',
        };
    }
}
