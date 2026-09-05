<?php

namespace App\Models;

use App\Enums\EquipmentClass;
use App\Enums\OwnershipMode;
use App\Enums\TrackingType;
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
 *
 * Tiga kolom Inventory (ADHOC-54) — axis INDEPENDEN, jangan digabung:
 * `tracking_type` (cara hitung stok), `ownership_mode` (boleh/gak transisi ke
 * `SerialStatus::INSTALLED`), `equipment_class_override` (override PER-ITEM
 * dari default kategori — lihat `getEffectiveEquipmentClassAttribute()`).
 */
#[Fillable([
    'code',
    'name',
    'item_category_id',
    'unit',
    'is_active',
    'tracking_type',
    'ownership_mode',
    'equipment_class_override',
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
            'tracking_type' => TrackingType::class,
            'ownership_mode' => OwnershipMode::class,
            'equipment_class_override' => EquipmentClass::class,
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

    public function inventoryBalances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class);
    }

    public function inventorySerials(): HasMany
    {
        return $this->hasMany(InventorySerial::class);
    }

    public function technicianCustodies(): HasMany
    {
        return $this->hasMany(TechnicianCustody::class);
    }

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    /**
     * Resolusi dua-level (§3.1 rancangan-ui.md): override PER-ITEM kalau
     * diisi, kalau tidak ikut default kategorinya, kalau kategorinya sendiri
     * entah kenapa gak ada baru jatuh ke fallback absolut PASIF. Kategori
     * catch-all `lainnya` default PASIF — item aktif di dalamnya pakai
     * `equipment_class_override`, bukan bikin kategori baru.
     */
    public function getEffectiveEquipmentClassAttribute(): EquipmentClass
    {
        if ($this->equipment_class_override !== null) {
            return $this->equipment_class_override;
        }

        if ($this->relationLoaded('category')) {
            return $this->category?->equipment_class ?? EquipmentClass::PASIF;
        }

        if (! $this->item_category_id) {
            return EquipmentClass::PASIF;
        }

        $category = ItemCategory::find($this->item_category_id);

        return $category?->equipment_class ?? EquipmentClass::PASIF;
    }
}
