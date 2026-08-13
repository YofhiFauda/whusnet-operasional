<?php

namespace App\Models;

use App\Enums\VisitResult;
use App\Traits\HasPopScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu kunjungan kolektor ke satu pelanggan pada satu hari.
 *
 * docs/plan/kolektor/analisa-alur-kolektor-2.0.md §12.
 */
class CollectorVisit extends Model
{
    use HasPopScope;

    protected $fillable = [
        'collector_id',
        'customer_id',
        'pop_id',
        'visited_at',
        'result',
        'promised_date',
        'note',
        'payment_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visited_at' => 'date',
            'promised_date' => 'date',
            'result' => VisitResult::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
