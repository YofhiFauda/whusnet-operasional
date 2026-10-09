<?php

namespace App\Enums;

enum InvoiceType: string
{
    case AWAL = 'awal';
    case BULANAN = 'bulanan';

    /**
     * Tagihan Manual (ADHOC-70) — Perbaikan / Lainnya / Pindah Lokasi, diisi
     * dari `/invoices/create`.
     * Di luar `Invoice::SUBSCRIPTION_TYPES` — boleh terbit bareng tagihan Bulanan.
     */
    case MANUAL = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::AWAL => 'Aktivasi',
            self::BULANAN => 'Tagihan Bulanan Rutin',
            self::MANUAL => 'Tagihan Manual',
        };
    }

    public function prefix(): string
    {
        return match ($this) {
            self::AWAL => 'pembayaran-awal',
            self::BULANAN => 'bulan',
            self::MANUAL => 'manual',
        };
    }
}
