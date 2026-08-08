<?php

namespace App\Models;

use App\Enums\FopTaskPriority;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Master Issue/Kategori Keluhan — klasifikasi terstruktur untuk dropdown
 * "Kategori Issue" di form Create Service Ticket. Bukan pengganti
 * `detail_keluhan` (tetap wajib diisi, narasi bebas pelanggan).
 */
#[Fillable([
    'name',
    'default_priority',
    'sla_source',
    'is_active',
])]
class TicketIssueCategory extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_priority' => FopTaskPriority::class,
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'issue_category_id');
    }
}
