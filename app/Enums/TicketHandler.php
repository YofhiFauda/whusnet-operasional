<?php

namespace App\Enums;

/**
 * Siapa yang sedang pegang tiket SEBELUM (atau tanpa pernah) nyampe FOP.
 *
 * Selalu mulai HELPDESK saat tiket dibuat (docs/plan/RANCANGAN_WORKSHEET_TICKETING.MD
 * — kedua skenario, baik Helpdesk maupun NOC, entry point-nya Helpdesk). Begitu
 * `handler` jadi FOP, field ini BEKU permanen di FOP — Ticket::bucket() pakai
 * itu buat mbedain "belum pernah ke FOP" vs "FopTask-nya kehapus" (dua-duanya
 * sama-sama fop_task_id null, gak bisa dibedain dari fop_task_id doang).
 */
enum TicketHandler: string
{
    case HELPDESK = 'helpdesk';
    case NOC = 'noc';
    case FOP = 'fop';

    public function label(): string
    {
        return match ($this) {
            self::HELPDESK => 'Helpdesk',
            self::NOC => 'NOC',
            self::FOP => 'FOP',
        };
    }
}
