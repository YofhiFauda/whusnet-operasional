<?php

namespace App\Models;

use App\Enums\TicketHistoryAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riwayat sisi Ticketing — kembaran `FopTaskStatusHistory` yang mencatat
 * kejadian yang sama dari sudut pandang pengirim tiket.
 */
#[Fillable([
    'ticket_id',
    'action',
    'from_status',
    'to_status',
    'reason',
    'actor_id',
    'happened_at',
])]
class TicketHistory extends Model
{
    protected function casts(): array
    {
        return [
            'action' => TicketHistoryAction::class,
            'happened_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
