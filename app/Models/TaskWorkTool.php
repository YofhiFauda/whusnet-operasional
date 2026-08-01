<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu alat kerja yang dibutuhkan sebuah FopTask. Ditulis lewat
 * TaskWorkToolService, jangan langsung dari controller.
 */
#[Fillable([
    'fop_task_id',
    'customer_id',
    'work_tool_id',
    'tool_name',
    'note',
    'recorded_by',
])]
class TaskWorkTool extends Model
{
    public function fopTask(): BelongsTo
    {
        return $this->belongsTo(FopTask::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function workTool(): BelongsTo
    {
        return $this->belongsTo(WorkTool::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
