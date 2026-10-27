<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu titik koordinat pada rute kabel melintang (ADHOC-205). Urutan 1 = titik
 * awal, urutan terakhir = pelanggan.
 */
#[Fillable([
    'customer_cable_crossing_id',
    'urutan',
    'label',
    'latitude',
    'longitude',
])]
class CustomerCableCrossingPoint extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    /**
     * @return BelongsTo<CustomerCableCrossing, $this>
     */
    public function crossing(): BelongsTo
    {
        return $this->belongsTo(CustomerCableCrossing::class, 'customer_cable_crossing_id');
    }
}
