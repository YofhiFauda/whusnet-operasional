<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case VALID = 'valid';
    case DITOLAK = 'ditolak';

    public function label(): string
    {
        return match ($this) {
            self::VALID => 'Valid',
            self::DITOLAK => 'Ditolak',
        };
    }
}
