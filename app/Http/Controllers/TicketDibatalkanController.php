<?php

namespace App\Http\Controllers;

use App\Enums\TicketBucket;

/**
 * Halaman Ticket Dibatalkan — route (`/tickets/dibatalkan`), permission
 * (`tickets.dibatalkan.view`), dan view (`tickets/dibatalkan.blade.php`)
 * sendiri. Kembaran TicketSelesaiController.
 */
class TicketDibatalkanController extends TicketArchiveController
{
    protected function bucket(): TicketBucket
    {
        return TicketBucket::DIBATALKAN;
    }

    protected function permission(): string
    {
        return 'tickets.dibatalkan.view';
    }

    protected function view(): string
    {
        return 'tickets.dibatalkan';
    }
}
