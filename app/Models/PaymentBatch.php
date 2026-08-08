<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Wadah ringan satu sesi submit batch pembayaran kolektor — dedup
 * (idempotency_key) + pengelompokan. Lihat migration create_payment_batches
 * untuk kenapa ini sengaja tanpa declared_total/variance (Setoran di-drop
 * dari scope, docs/plan/analisa-billing-tagihan-pembayaran-kolektor.md §B-11).
 */
class PaymentBatch extends Model
{
    protected $fillable = [
        'idempotency_key',
        'submitted_by',
        'collector_id',
        'submitted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
