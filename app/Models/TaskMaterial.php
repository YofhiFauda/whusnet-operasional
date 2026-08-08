<?php

namespace App\Models;

use App\Enums\MaterialKind;
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
    'item_category_id',
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
            'qty' => 'decimal:2',
            'unit_price_snapshot' => 'decimal:2',
        ];
    }

    /**
     * `item_type` SENGAJA tanpa cast enum.
     *
     * Kategori sekarang data (`item_categories`), jadi code di baris lama bisa
     * berupa kategori buatan admin yang tidak punya case enum. Cast enum akan
     * melempar ValueError saat baris itu dibaca — riwayat material yang
     * seharusnya cuma ditampilkan malah bikin halaman verifikasi 500.
     * Bacanya lewat `category_label` (nama saat ini) atau `category` (relasi).
     */
    public function getCategoryLabelAttribute(): string
    {
        return ItemCategory::labelFor($this->item_type);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'item_category_id');
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
