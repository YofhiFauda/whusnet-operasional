<?php

namespace App\Http\Controllers;

use App\Enums\TicketBucket;

/**
 * Halaman Ticket Selesai — route (`/tickets/selesai`), permission
 * (`tickets.selesai.view`), dan view (`tickets/selesai.blade.php`) sendiri,
 * terpisah dari Ticket Dibatalkan maupun modul Ticketing lainnya.
 */
class TicketSelesaiController extends TicketArchiveController
{
    protected function bucket(): TicketBucket
    {
        return TicketBucket::SELESAI;
    }

    protected function permission(): string
    {
        return 'tickets.selesai.view';
    }

    protected function view(): string
    {
        return 'tickets.selesai';
    }
}
