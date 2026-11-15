<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu anggota tim yang dilepas dari task saat Pending — sumber entri
 * "Pending" di Riwayat Task Saya. Ditulis HANYA oleh
 * `TaskService::releaseTeamAndSetPending()` (satu logic Pending untuk semua
 * jalur: teknisi, FOP, review FOP).
 */
class TaskMemberRelease extends Model
{
    protected $fillable = [
        'task_id',
        'user_id',
        'role_in_task',
        'reason',
        'source',
        'released_by',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'released_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }
}
