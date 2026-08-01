<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Master alat kerja — peralatan yang dibawa teknisi lalu dibawa pulang
 * (tangga, bor, splicer, OPM, OTDR).
 *
 * Bukan material: material habis dipakai dan ditinggal di pelanggan, dicatat
 * di `task_materials` lengkap dengan qty. Alat kerja cuma checklist.
 */
#[Fillable([
    'code',
    'name',
    'note',
    'is_active',
    'sort_order',
])]
class WorkTool extends Model
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

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function taskWorkTools(): HasMany
    {
        return $this->hasMany(TaskWorkTool::class);
    }

    /**
     * Alat aktif untuk checklist form.
     *
     * @return Collection<int, self>
     */
    public static function options()
    {
        return static::active()->ordered()->get();
    }
}
