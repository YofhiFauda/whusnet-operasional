<?php

namespace App\Models;

use App\Enums\FeatureType;
use App\Models\Concerns\RecordsAuditLogs;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'parent_id',
    'code',
    'name',
    'type',
    'sort_order',
    'is_active',
])]
class Feature extends Model
{
    use RecordsAuditLogs;

    protected string $auditModule = 'Master Fitur RBAC';

    protected array $auditEvents = ['created', 'updated', 'deleted'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'type' => FeatureType::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Relasi ke parent feature.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Relasi ke child features (sub-features / mini-features).
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * Scope untuk memfilter fitur aktif.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope untuk memfilter root features (fitur utama).
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Helper static untuk mengambil seluruh hierarki Feature Tree.
     */
    public static function getTree(): Collection
    {
        return self::root()
            ->active()
            ->with(['children' => function ($query) {
                $query->active()->with(['children' => function ($subQuery) {
                    $subQuery->active();
                }]);
            }])
            ->orderBy('sort_order')
            ->get();
    }
}
