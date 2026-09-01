<?php

namespace App\Exceptions;

use App\Models\Ticket;
use RuntimeException;

/**
 * Dilempar `TicketService::create()` saat `$enforceDuplicateGuard` aktif dan
 * pelanggan masih punya tiket terbuka yang belum dikonfirmasi staf sebagai
 * "tetap buat baru" (docs/plan/qr-code/
 * analisa-unifikasi-qr-staff-portal.md §1.2). Caller (controller) yang
 * menerjemahkan ini jadi HTTP 409 — service tidak tahu apa-apa soal HTTP.
 */
class DuplicateTicketException extends RuntimeException
{
    public function __construct(public readonly Ticket $existingTicket)
    {
        parent::__construct("Pelanggan ini masih punya tiket terbuka: {$existingTicket->ticket_number}.");
    }
}
