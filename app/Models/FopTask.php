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
    'cancelled_at',
    'team_id'
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

    /**
     * Get the daily team this FOP task belongs to.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(FopTaskTeam::class, 'team_id');
    }

    /**
     * Deadline SLA — single source of truth, sinkron sama aturan yang dipakai
     * di halaman Antrean Survey (1x24 jam sejak registrasi) & Verif Pemasangan
     * (3x24 jam sejak survey selesai) di FopDashboardController.
     *
     * - Udah ditugaskan (ada Task asli) → task_date-nya (scheduled_at) + SLA per tipe.
     * - Survey belum ditugaskan → customer.created_at + 1 hari.
     * - Pemasangan belum ditugaskan → completed_at survey terakhir (fallback customer.updated_at) + 3 hari.
     * - Tipe lain belum ditugaskan → task_date (create/auto-sync) + SLA per tipe.
     */
    public function slaDeadline(): \Illuminate\Support\Carbon
    {
        if ($this->task?->scheduled_at) {
            return \Illuminate\Support\Carbon::parse($this->task->scheduled_at)
                ->addMinutes($this->category->slaMinutes());
        }

        if ($this->category === \App\Enums\TaskType::SURVEY && $this->customer) {
            return \Illuminate\Support\Carbon::parse($this->customer->created_at)->addDay();
        }

        if ($this->category === \App\Enums\TaskType::PEMASANGAN && $this->customer) {
            $surveyTask = $this->customer->tasks
                ->where('task_type', \App\Enums\TaskType::SURVEY->value)
                ->where('status', 'selesai')
                ->sortByDesc('completed_at')
                ->first();

            $ref = $surveyTask?->completed_at ?? $this->customer->updated_at;

            return \Illuminate\Support\Carbon::parse($ref)->addDays(3);
        }

        return \Illuminate\Support\Carbon::parse($this->task_date)->addMinutes($this->category->slaMinutes());
    }

    /**
     * Total durasi SLA (detik) — dipakai buat progress bar countdown.
     */
    public function slaTotalSeconds(): int
    {
        if (!$this->task?->scheduled_at) {
            if ($this->category === \App\Enums\TaskType::SURVEY) {
                return 86400;
            }
            if ($this->category === \App\Enums\TaskType::PEMASANGAN) {
                return 259200;
            }
        }

        return $this->category->slaMinutes() * 60;
    }
}
