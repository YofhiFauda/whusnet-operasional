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
use Illuminate\Support\Carbon;

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
        'work_finished_at',
        'completed_at',
        'completed_by',
        'cancelled_at',
        'cancel_reason',
        'pending_reason',
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
        'work_finished_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'conflict_override' => 'boolean',
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

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
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

    /**
     * Detail kategori C-REQ + status verifikasi biaya oleh CS (helpdesk) —
     * hanya terisi untuk `task_type = CREQ`. Lihat docs/plan/task-teknisi/
     * rancangan-biaya-creq-verifikasi-cs.md.
     */
    public function creqDetail(): HasOne
    {
        return $this->hasOne(TaskCreqDetail::class);
    }

    public function report(): HasOne
    {
        return $this->hasOne(TaskReport::class);
    }

    /**
     * Laporan task Ambil Modem (DEAC) — ADHOC-86. Hanya terisi untuk
     * `task_type = DEAC`.
     */
    public function deviceRetrieval(): HasOne
    {
        return $this->hasOne(TaskDeviceRetrieval::class);
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
        $finishedAt = $this->work_finished_at ?? $this->completed_at;

        if (! $this->started_at || ! $finishedAt) {
            return null;
        }

        return (int) $this->started_at->diffInMinutes($finishedAt);
    }

    /**
     * Titik waktu pembanding SLA pengerjaan: kapan kerja lapangan berhenti.
     *
     * Lapor Nanti (2026-09-28, keputusan user: SLA = waktu kerja lapangan,
     * konsisten dengan task_reports) — `work_finished_at` diisi saat teknisi
     * menunda laporan, jadi jeda menunggu laporan gak bikin countdown habis
     * atau badge "Melewati SLA" nyala, dan laporan yang dikirim besoknya
     * (`completed_at`) juga gak dihitung telat. Task biasa: `completed_at`;
     * masih dikerjakan: sekarang.
     */
    public function slaReferenceTime(): Carbon
    {
        return $this->work_finished_at ?? $this->completed_at ?? now();
    }

    /**
     * Deadline SLA (wall-clock kapan laporan wajib rampung diinput).
     *
     * Khusus SURVEY: deadline SELALU akhir hari jadwal (23:59:59) di semua
     * paket — laporan survey wajib diinput hari itu juga, gak peduli jam
     * berapa FOP menjadwalkan atau jam berapa teknisi mulai. Beda dari
     * `sla_minutes` (durasi kerja tetap dipakai TaskReport/conflict window),
     * ini murni batas kalender. Tipe lain tetap pakai started_at + sla_minutes.
     * Kecuali task di-pending atau ditolak, deadline ini gak bisa ditunda.
     */
    public function slaDeadline(): ?Carbon
    {
        if ($this->task_type === TaskType::SURVEY) {
            return $this->scheduled_at?->copy()->endOfDay();
        }

        if (! $this->started_at || ! $this->sla_minutes) {
            return null;
        }

        return $this->started_at->copy()->addMinutes($this->sla_minutes);
    }

    /**
     * Titik mulai jendela SLA (buat hitung total budget countdown).
     *
     * Khusus SURVEY: mulai dari `scheduled_at` (jam yang FOP jadwalkan),
     * BUKAN `started_at` — SLA-nya jalan sesuai jam jadwal, gak nunggu
     * teknisi tekan tombol Mulai. Tipe lain tetap dari `started_at` karena
     * deadline-nya sendiri (`slaDeadline()`) baru ada begitu task dimulai.
     */
    public function slaWindowStart(): ?Carbon
    {
        if ($this->task_type === TaskType::SURVEY) {
            return $this->scheduled_at;
        }

        return $this->started_at;
    }

    /**
     * Apakah task melewati SLA.
     */
    public function isOverSla(): bool
    {
        $deadline = $this->slaDeadline();

        if (! $deadline) {
            return false;
        }

        $reference = $this->slaReferenceTime();

        return $deadline->lt($reference);
    }
}
