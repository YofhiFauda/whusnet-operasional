<?php

namespace App\Enums;

/**
 * Sumber satu baris [[CustomerBalanceMutation]] — G2 di
 * docs/plan/billing/analisa-rancangan-saldo-pelanggan.md. Dipisah dari
 * `type` (credit/debit) karena satu payment bisa punya beberapa baris
 * ber-`type` sama (mis. kredit overpay ASLI + kredit REFUND saldo yang
 * dibalik saat payment itu ditolak) — unique index ledger bersandar ke
 * kombinasi (payment_id, type, source), bukan (payment_id, type) saja.
 */
enum BalanceMutationSource: string
{
    /** Overpay dari invoice AWAL — pelanggan sengaja titip saldo di muka. */
    case BAYAR_DI_MUKA = 'bayar_di_muka';

    /** Overpay dari invoice selain AWAL — kelebihan bayar biasa. */
    case KELEBIHAN_BAYAR = 'kelebihan_bayar';

    /** Dipakai sistem — auto-pay tagihan BULANAN begitu terbit (ADHOC-92). */
    case PAKAI_OTOMATIS = 'pakai_otomatis';

    /** Dipakai manual oleh kasir lewat `use_balance_amount` di form bayar. */
    case PAKAI_MANUAL = 'pakai_manual';

    /** Pembalikan kredit/debit karena payment sumbernya ditolak. */
    case PEMBATALAN = 'pembatalan';

    /** Baris migrasi/backfill data lama tanpa konteks payment spesifik. */
    case BACKFILL = 'backfill';

    public function label(): string
    {
        return match ($this) {
            self::BAYAR_DI_MUKA => 'Bayar di Muka',
            self::KELEBIHAN_BAYAR => 'Kelebihan Bayar',
            self::PAKAI_OTOMATIS => 'Dipakai Otomatis',
            self::PAKAI_MANUAL => 'Dipakai Manual',
            self::PEMBATALAN => 'Pembatalan',
            self::BACKFILL => 'Backfill',
        };
    }
}
