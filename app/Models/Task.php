<?php

namespace App\Models;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Concerns\RecordsAuditLogs;
use App\Traits\HasPopScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Task extends Model
{
    use HasPopScope, RecordsAuditLogs;

    protected string $auditModule = 'Task Management';

    protected array $auditEvents = ['created', 'updated', 'deleted'];

    protected $fillable = [
        'task_number',
        'customer_id',
        'pop_id',
        'task_type',
        'title',
        'description',
        'status',
        'scheduled_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'cancel_reason',
        'pending_reason',
        'report_deferred',
        'reject_reason',
        'fop_review_status',
        'fop_id',
        'sla_minutes',
        'conflict_override',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'task_type' => TaskType::class,
        'status' => TaskStatus::class,
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'conflict_override' => 'boolean',
        'report_deferred' => 'boolean',
    ];

    // ─── Relasi ─────────────────────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function pop(): BelongsTo
    {
        return $this->belongsTo(Pop::class);
    }

    public function fop(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fop_id');
    }

    public function fopTask(): HasOne
    {
        return $this->hasOne(FopTask::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(TaskTeam::class);
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->orderBy('created_at', 'desc');
    }

    public function maintenanceReport()
    {
        return $this->hasOne(TaskMaintenance::class);
    }

    public function report(): HasOne
    {
        return $this->hasOne(TaskReport::class);
    }

    // ─── Helper Methods ─────────────────────────────────────────

    /**
     * Apakah user termasuk anggota tim task ini.
     */
    public function isMember(int $userId): bool
    {
        return $this->teamMembers()->where('user_id', $userId)->exists();
    }

    /**
     * Apakah task boleh di-mark Selesai.
     */
    public function canComplete(): bool
    {
        return true;
    }

    /**
     * Hitung durasi aktual dalam menit (started_at → completed_at).
     */
    public function actualDurationMinutes(): ?int
    {
        if (! $this->started_at || ! $this->completed_at) {
            return null;
        }

        return (int) $this->started_at->diffInMinutes($this->completed_at);
    }

    /**
     * Apakah task melewati SLA.
     */
    public function isOverSla(): bool
    {
        if (! $this->sla_minutes || ! $this->started_at) {
            return false;
        }

        $reference = $this->completed_at ?? now();

        return $this->started_at->addMinutes($this->sla_minutes)->lt($reference);
    }
}
