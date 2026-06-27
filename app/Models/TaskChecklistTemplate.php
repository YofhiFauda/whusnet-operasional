<?php

namespace App\Models;

use App\Enums\TaskType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskChecklistTemplate extends Model
{
    protected $fillable = [
        'task_type',
        'item',
        'is_required',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'task_type'   => TaskType::class,
        'is_required' => 'boolean',
        'is_active'   => 'boolean',
    ];

    public function checklists(): HasMany
    {
        return $this->hasMany(TaskChecklist::class, 'template_id');
    }

    /**
     * Ambil template aktif untuk task_type tertentu, urut sort_order.
     */
    public static function forType(TaskType|string $taskType): \Illuminate\Database\Eloquent\Collection
    {
        $typeValue = $taskType instanceof TaskType ? $taskType->value : $taskType;

        return static::where('task_type', $typeValue)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }
}
