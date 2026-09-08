<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'stock_request_id',
    'item_id',
    'qty_requested',
    'qty_fulfilled',
    'lot_no',
])]
class StockRequestItem extends Model
{
    protected function casts(): array
    {
        return [
            'qty_requested' => 'decimal:2',
            'qty_fulfilled' => 'decimal:2',
        ];
    }

    public function stockRequest(): BelongsTo
    {
        return $this->belongsTo(StockRequest::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Sisa yang belum tercatat terkirim — dipakai buat clamp input qty di
     * form "Catat Pengiriman" (gak boleh nyatet lebih dari yang diminta).
     */
    public function remaining(): float
    {
        return max(0.0, (float) $this->qty_requested - (float) $this->qty_fulfilled);
    }

    public function isFullyFulfilled(): bool
    {
        return $this->remaining() <= 0.0;
    }
}
