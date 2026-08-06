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

    /**
     * Batas waktu wajib mulai ditangani (jam) buat kategori tiket yang
     * `sla_source`-nya `'prioritas'` (bukan paket internet pelanggan) —
     * lihat `TicketIssueCategory::sla_source` & docs/plan/analisa-target-sla-ticketing.md.
     * Dipakai TicketService saat resolve `Ticket::sla_hours` di titik tiket
     * dibuat, sejajar `InternetPackage::getHandlingSla()` buat jalur paket.
     */
    public function slaHours(): int
    {
        return match ($this) {
            self::URGENT => 4,
            self::HIGH => 8,
            self::MEDIUM => 24,
            self::LOW => 48,
        };
    }
}
