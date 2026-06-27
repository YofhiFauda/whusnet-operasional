<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskChecklist extends Model
{
    protected $fillable = [
        'task_id',
        'template_id',
        'item',
        'is_required',
        'is_checked',
        'checked_by',
        'checked_at',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_checked'  => 'boolean',
        'checked_at'  => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TaskChecklistTemplate::class, 'template_id');
    }

    public function checkedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
