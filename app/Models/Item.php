<?php

namespace App\Models;

use App\Enums\MaterialType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Master barang/material. Minimum by design — stok, harga, dan lokasi gudang
 * menyusul bareng modul Inventory. Tujuannya sekarang cuma satu: penamaan
 * barang seragam sejak baris pertama dicatat.
 */
#[Fillable([
    'code',
    'name',
    'type',
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
            'type' => MaterialType::class,
            'is_active' => 'boolean',
        ];
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
