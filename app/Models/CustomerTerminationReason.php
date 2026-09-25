<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Master alasan Putus Langganan (ADHOC-69) — dropdown di form putus, bisa
 * di-filter/sort di List Putus. `default_penalty_amount` cuma prefill/titik
 * awal untuk pelanggan masa langganan <=1 tahun, TIDAK pernah dipakai
 * otomatis (lihat CustomerTerminationService).
 */
#[Fillable([
    'name',
    'default_penalty_amount',
    'is_active',
])]
class CustomerTerminationReason extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_penalty_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'termination_reason_id');
    }
}
