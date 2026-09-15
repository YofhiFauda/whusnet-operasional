<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pelanggan terdampak di tiket batch (mis. satu ODP LOS berdampak ke 30
 * pelanggan) — child murni informasional, BUKAN Ticket/FopTask sendiri. Lihat
 * docblock migration create_ticket_batch_members_table & CLAUDE.md §
 * Sinkronisasi Ticket ↔ FopTask ↔ Task.
 */
#[Fillable([
    'ticket_id',
    'customer_id',
    'cid',
    'customer_name',
    'phone',
    'added_by',
])]
class TicketBatchMember extends Model
{
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
