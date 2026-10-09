<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditLogs;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Master data mitra Agent — BUKAN akun login. Agent tidak pernah masuk
 * sistem; Business Development yang mendaftarkan pelanggan atas namanya
 * lewat dropdown ini (Skema 3, 2026-09-12).
 */
#[Fillable(['code', 'name', 'phone', 'is_active'])]
class Agent extends Model
{
    use RecordsAuditLogs;

    protected string $auditModule = 'Master Agent';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
