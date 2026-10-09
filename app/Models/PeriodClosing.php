<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot beku Laporan Bulanan Admin Collector untuk satu (periode, POP).
 * Lihat migration `create_period_closings_table` untuk alasan desainnya.
 */
class PeriodClosing extends Model
{
    protected $fillable = [
        'period',
        'pop_id',
        'figures',
        'closed_by',
        'closed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'figures' => 'array',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Pop, $this>
     */
    public function pop(): BelongsTo
    {
        return $this->belongsTo(Pop::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
