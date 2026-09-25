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
            // Nilai DB tetap `ditolak` (tanpa migrasi data); di UI aksinya
            // "Kembalikan" — membalik transaksi yang salah input.
            self::DITOLAK => 'Dikembalikan',
        };
    }
}
