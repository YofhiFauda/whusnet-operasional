<?php

namespace App\Models;

use App\Enums\TransferStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Header MUTABLE buat Transfer Pusat→Cabang. SENGAJA BUKAN bagian dari
 * `inventory_transactions` (ledger append-only) — py fase "dikirim, belum
 * dikonfirmasi" (`in_transit`) yang bisa berlangsung berhari-hari, keadaan
 * mutable yang gak bisa ditaruh di ledger immutable.
 *
 * Preseden: `Ticket` (mutable) + `ticket_histories` (append-only) — pola yang
 * sama dipakai ulang di sini. Baris `inventory_transactions` (dispatch +
 * confirm, dua baris independen) nunjuk balik ke sini lewat
 * `inventory_transfer_id`. Lihat migration `create_inventory_transfers_table`
 * dan docs/plan/warehouse/rancangan-ui.md §3.6.
 */
#[Fillable([
    'reference_number',
    'from_pop_id',
    'to_pop_id',
    'status',
    'created_by',
    'received_by',
    'received_at',
])]
class InventoryTransfer extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TransferStatus::class,
            'received_at' => 'datetime',
        ];
    }

    public function fromPop(): BelongsTo
    {
        return $this->belongsTo(Pop::class, 'from_pop_id');
    }

    public function toPop(): BelongsTo
    {
        return $this->belongsTo(Pop::class, 'to_pop_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class, 'inventory_transfer_id');
    }

    public function isInTransit(): bool
    {
        return $this->status === TransferStatus::IN_TRANSIT;
    }
}
