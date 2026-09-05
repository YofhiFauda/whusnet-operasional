<?php

namespace App\Models;

use App\Enums\StockRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Permintaan Stok Cabang→Pusat (2026-09-03) — lihat docblock lengkap di
 * migration `create_stock_requests_table` buat alasan kenapa ini dibikin.
 */
#[Fillable([
    'reference_number',
    'cabang_pop_id',
    'status',
    'notes',
    'requested_by',
    'decided_by',
    'decided_at',
    'decision_notes',
])]
class StockRequest extends Model
{
    protected function casts(): array
    {
        return [
            'status' => StockRequestStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public function cabangPop(): BelongsTo
    {
        return $this->belongsTo(Pop::class, 'cabang_pop_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockRequestItem::class);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', StockRequestStatus::PENDING->value);
    }
}
