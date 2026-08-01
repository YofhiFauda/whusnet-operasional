<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Master barang/material. Minimum by design — stok, harga, dan lokasi gudang
 * menyusul bareng modul Inventory. Tujuannya sekarang cuma satu: penamaan
 * barang seragam sejak baris pertama dicatat.
 *
 * Kategori dulu kolom string ber-cast enum `MaterialType`; sekarang relasi ke
 * master `item_categories`. Master menunjuk relasi, BUKAN menyimpan snapshot —
 * kalau admin mengubah nama kategori, master barang harus ikut berubah. Yang
 * menyimpan snapshot cuma `task_materials` (riwayat).
 */
#[Fillable([
    'code',
    'name',
    'item_category_id',
    'unit',
    'is_active',
])]
class Item extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'item_category_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function taskMaterials(): HasMany
    {
        return $this->hasMany(TaskMaterial::class);
    }
}
