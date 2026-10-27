<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'source_transaction_id',
    'retrieve_transaction_id',
    'task_id',
    'item_id',
    'roll_id',
    'technician_id',
    'qty',
    'verified_qty',
    'status',
    'inspection_note',
    'inspected_by',
    'inspected_at',
    'recovered_custody_id',
    'recovered_roll_id',
])]
class InfrastructureRecovery extends Model
{
    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'verified_qty' => 'decimal:2',
            'inspected_at' => 'datetime',
        ];
    }

    public function sourceTransaction(): BelongsTo
    {
        return $this->belongsTo(InventoryTransaction::class, 'source_transaction_id');
    }

    public function retrieveTransaction(): BelongsTo
    {
        return $this->belongsTo(InventoryTransaction::class, 'retrieve_transaction_id');
    }
}
