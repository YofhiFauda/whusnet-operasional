<?php

namespace App\Enums;

enum InvoiceType: string
{
    case AWAL = 'awal';
    case BULANAN = 'bulanan';
    case REAKTIVASI = 'reaktivasi';

    public function label(): string
    {
        return match ($this) {
            self::AWAL => 'Tagihan Awal (PSB)',
            self::BULANAN => 'Tagihan Bulanan Rutin',
            self::REAKTIVASI => 'Tagihan Reaktivasi',
        };
    }

    public function prefix(): string
    {
        return match ($this) {
            self::AWAL => 'pembayaran-awal',
            self::BULANAN => 'bulan',
            self::REAKTIVASI => 'reaktivasi',
        };
    }
}
