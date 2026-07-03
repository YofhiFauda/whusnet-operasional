<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'task_number',
    'task_date',
    'category',
    'task_id',
    'tugas',
    'village_id',
    'pop_id',
    'customer_id',
    'issue',
    'notes',
    'status',
    'priority',
    'pending_reason',
    'client_request_date',
    'cancelled_at'
])]
class FopTask extends Model
{
    protected function casts(): array
    {
        return [
            'task_date' => 'datetime',
            'client_request_date' => 'date',
            'cancelled_at' => 'datetime',
            'status' => \App\Enums\FopTaskStatus::class,
            'priority' => \App\Enums\FopTaskPriority::class,
            'category' => \App\Enums\TaskType::class,
        ];
    }

    /**
     * Get the village/area associated with the FOP task.
     */
    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    /**
     * Get the POP / Cabang associated with the FOP task.
     */
    public function pop(): BelongsTo
    {
        return $this->belongsTo(Pop::class);
    }

    /**
     * Get the customer associated with the FOP task (optional).
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the technicians assigned to this FOP task.
     */
    public function technicians(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'fop_task_user', 'fop_task_id', 'user_id')
                    ->withTimestamps();
    }

    /**
     * Get the Technician Task associated with this FOP Task.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
