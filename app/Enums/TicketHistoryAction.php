<?php

namespace App\Enums;

/**
 * Jenis kejadian di riwayat Ticketing. Sengaja dipisah dari
 * `fop_task_status_history` (riwayat sisi FOP) — satu pembatalan menghasilkan
 * DUA jejak: satu di Task FOP (operasional teknisi) dan satu di sini (sisi
 * pengirim tiket). Lihat FopTaskObserver.
 */
enum TicketHistoryAction: string
{
    case DIBUAT     = 'dibuat';
    case DIBATALKAN = 'dibatalkan';

    public function label(): string
    {
        return match ($this) {
            self::DIBUAT     => 'Ticket dikirim',
            self::DIBATALKAN => 'Ticket dibatalkan',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::DIBUAT     => 'bg-sky-50 border-sky-200 text-sky-700',
            self::DIBATALKAN => 'bg-red-50 border-red-200 text-red-700',
        };
    }
}
