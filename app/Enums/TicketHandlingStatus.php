<?php

namespace App\Enums;

/**
 * Status internal tiket SELAMA masih di tangan Helpdesk/NOC (belum/gak pernah
 * ke FOP). Begitu `handler` jadi FOP, field ini gak lagi dipakai buat nentuin
 * bucket — bucket-nya balik turunan dari FopTask.status kayak biasa (lihat
 * Ticket::bucket()). Sengaja dinamai "Handling", BUKAN "TicketStatus" —
 * `App\Enums\TaskStatus` udah dipakai buat status FopTask/Task, jangan sampai
 * ketuker dua konsep beda ini.
 */
enum TicketHandlingStatus: string
{
    case OPEN = 'open';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Terbuka',
            self::CLOSED => 'Selesai',
            self::CANCELLED => 'Dibatalkan',
        };
    }
}
