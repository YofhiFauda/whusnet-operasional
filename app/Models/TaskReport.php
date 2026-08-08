<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'task_id',
    'started_at',
    'pending_at',
    'resumed_at',
    'completed_at',
    'total_duration_minutes',
    'sla_target_minutes',
    'sla_status',
    'sla_overrun_minutes',
])]
class TaskReport extends Model
{
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'pending_at' => 'datetime',
            'resumed_at' => 'datetime',
            'completed_at' => 'datetime',
            'total_duration_minutes' => 'integer',
            'sla_target_minutes' => 'integer',
            'sla_overrun_minutes' => 'integer',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Durasi akumulasi (menit) sampai sekarang — termasuk siklus yang lagi
     * jalan (task belum `completed_at`), dipakai buat tampilan live di
     * halaman Detail Riwayat.
     */
    public function accumulatedDurationMinutes(): int
    {
        $minutes = $this->total_duration_minutes;

        if (! $this->completed_at) {
            $cycleStart = $this->resumed_at ?? $this->started_at;
            if ($cycleStart) {
                $minutes += $cycleStart->diffInMinutes(now());
            }
        }

        return $minutes;
    }
}
