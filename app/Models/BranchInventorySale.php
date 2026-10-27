<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'reference_number',
    'inventory_transfer_id',
    'from_pop_id',
    'to_pop_id',
    'posted_total',
    'payment_status',
    'posted_at',
    'created_by',
])]
class BranchInventorySale extends Model
{
    protected function casts(): array
    {
        return [
            'posted_total' => 'decimal:2',
            'posted_at' => 'datetime',
        ];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(InventoryTransfer::class, 'inventory_transfer_id');
    }

    public function fromPop(): BelongsTo
    {
        return $this->belongsTo(Pop::class, 'from_pop_id');
    }

    public function toPop(): BelongsTo
    {
        return $this->belongsTo(Pop::class, 'to_pop_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BranchInventorySaleLine::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
