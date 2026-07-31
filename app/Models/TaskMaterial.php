<?php

namespace App\Models;

use App\Enums\MaterialKind;
use App\Enums\MaterialType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris material pada sebuah FopTask — estimasi (dari survey) atau
 * terpakai (dari pemasangan). Ditulis lewat TaskMaterialService, jangan
 * langsung dari controller.
 */
#[Fillable([
    'fop_task_id',
    'customer_id',
    'kind',
    'item_id',
    'item_type',
    'item_name',
    'qty',
    'unit',
    'unit_price_snapshot',
    'note',
    'recorded_by',
])]
class TaskMaterial extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => MaterialKind::class,
            'item_type' => MaterialType::class,
            'qty' => 'decimal:2',
            'unit_price_snapshot' => 'decimal:2',
        ];
    }

    public function fopTask(): BelongsTo
    {
        return $this->belongsTo(FopTask::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopeEstimasi($query)
    {
        return $query->where('kind', MaterialKind::ESTIMASI->value);
    }

    public function scopeTerpakai($query)
    {
        return $query->where('kind', MaterialKind::TERPAKAI->value);
    }
}
