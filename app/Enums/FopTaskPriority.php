<?php

namespace App\Enums;

enum FopTaskPriority: string
{
    case LOW = 'low';
    case MEDIUM = 'Medium';
    case HIGH = 'High';
    case URGENT = 'Urgent';

    /**
     * Urutan sort utk antrian FOP task (1 = paling atas/mendesak). Dipisah dari
     * urutan deklarasi enum (LOW..URGENT) krn urutan sort justru kebalikannya
     * (URGENT di atas). Dipakai FopTaskController::index() buat generate CASE
     * SQL dinamis, biar gak hardcode literal string per value lagi.
     */
    public function sortOrder(): int
    {
        return match ($this) {
            self::URGENT => 1,
            self::HIGH => 2,
            self::MEDIUM => 3,
            self::LOW => 4,
        };
    }
}
