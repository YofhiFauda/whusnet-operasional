<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case BELUM_DIBAYAR = 'belum_dibayar';
    case SEBAGIAN = 'sebagian';
    case LUNAS = 'lunas';
    case BATAL = 'batal';

    public function label(): string
    {
        return match ($this) {
            self::BELUM_DIBAYAR => 'Belum Dibayar',
            self::SEBAGIAN => 'Sebagian',
            self::LUNAS => 'Lunas',
            self::BATAL => 'Batal',
        };
    }
}
