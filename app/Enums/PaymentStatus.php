<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case VALID = 'valid';
    case DITOLAK = 'ditolak';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::VALID => 'Valid',
            self::DITOLAK => 'Ditolak',
        };
    }
}
