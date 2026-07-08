<?php

namespace App\Models;

use App\Enums\TaskType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'internet_package_id',
    'task_type',
    'sla_duration',
    'sla_unit',
    'is_active',
    'created_by',
    'updated_by',
])]
class PackageSlaSetting extends Model
{
    protected function casts(): array
    {
        return [
            'task_type' => TaskType::class,
            'is_active' => 'boolean',
        ];
    }

    public function internetPackage(): BelongsTo
    {
        return $this->belongsTo(InternetPackage::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Normalisasi sla_duration + sla_unit ke jam.
     */
    public function getSlaHoursAttribute(): int
    {
        return $this->sla_unit === 'day' ? $this->sla_duration * 24 : $this->sla_duration;
    }
}
