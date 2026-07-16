<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    'cancel_reason',
    'team_id',
    'handling_sla_hours',
])]
class FopTask extends Model
{
    protected function casts(): array
    {
        return [
            'task_date' => 'datetime',
            'client_request_date' => 'date',
            'cancelled_at' => 'datetime',
            'status' => \App\Enums\TaskStatus::class,
            'priority' => \App\Enums\FopTaskPriority::class,
            'category' => \App\Enums\TaskType::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $fopTask) {
            if ($fopTask->handling_sla_hours !== null) {
                return;
            }

            $package = $fopTask->customer?->internetPackage;

            $fopTask->handling_sla_hours = $package
                ? $package->getHandlingSla($fopTask->category)
                : $fopTask->category->defaultHandlingSlaHours();
        });
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
     * Nasib keputusan customer (approve/pending/reject) buat tiket kategori
     * Survey/Pemasangan — murni overlay INFORMASIONAL, gak pernah dipakai buat
     * nentuin bucket `status` utama (`FopTask.status` selalu `Selesai` begitu
     * `Task.status=selesai`, independen dari ini — lihat `TaskObserver::resolveTarget()`).
     * Keputusan sebenarnya tetap di halaman Verif & Pemasangan
     * (`CustomerVerificationController`), bukan di modul FopTask ini.
     *
     * @return 'pending'|'approved'|'rejected'|null null kalau task_type bukan Survey/Pemasangan atau belum ada Task terkait
     */
    public function verificationStatus(): ?string
    {
        if (!in_array($this->category, [\App\Enums\TaskType::SURVEY, \App\Enums\TaskType::PEMASANGAN], true)) {
            return null;
        }

        return $this->task?->fop_review_status;
    }

    /**
     * Riwayat transisi status realtime (Task 9) — ditulis oleh `TaskObserver`
     * tiap kali `Task` eksekusi terkait berubah status. Dipakai buat badge
     * status granular (mis. "Lapor Nanti" vs "Pending (Reschedule)") yang
     * gak bisa dibedain dari kolom `fop_tasks.status` mentah (cuma 4 nilai).
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(FopTaskStatusHistory::class)->orderByDesc('changed_at');
    }

    /**
     * Batas waktu wajib mulai ditangani (Master Timeline SLA) dalam jam.
     * Pakai snapshot handling_sla_hours (di-freeze saat tiket dibuat, resolve
     * dari paket internet customer saat itu). Fallback ke default global
     * kalau snapshot belum ada (data lama sebelum kolom ini ditambahkan).
     */
    protected function handlingSlaHours(): int
    {
        return $this->handling_sla_hours ?? $this->category->defaultHandlingSlaHours();
    }

    /**
     * Deadline SLA — single source of truth, sinkron sama aturan yang dipakai
     * di halaman Antrean Survey (1x24 jam sejak registrasi) & Verif Pemasangan
     * (3x24 jam sejak survey selesai) di FopDashboardController. Durasinya
     * (1x24, 3x24, dst) sekarang bervariasi per paket internet customer —
     * lihat Master Timeline SLA (docs/master/sla-timeline).
     *
     * - Udah ditugaskan (ada Task asli) → task_date-nya (scheduled_at) + SLA pengerjaan per tipe (di luar scope Master Timeline).
     * - Survey belum ditugaskan → customer.created_at + Master Timeline (jam).
     * - Pemasangan belum ditugaskan → completed_at survey terakhir (fallback customer.updated_at) + Master Timeline (jam).
     * - Tipe lain belum ditugaskan → task_date (create/auto-sync) + Master Timeline (jam).
     */
    public function slaDeadline(): \Illuminate\Support\Carbon
    {
        if ($this->task?->scheduled_at) {
            return \Illuminate\Support\Carbon::parse($this->task->scheduled_at)
                ->addMinutes($this->category->slaMinutes());
        }

        if ($this->category === \App\Enums\TaskType::SURVEY && $this->customer) {
            return \Illuminate\Support\Carbon::parse($this->customer->created_at)->addHours($this->handlingSlaHours());
        }

        if ($this->category === \App\Enums\TaskType::PEMASANGAN && $this->customer) {
            $surveyTask = $this->customer->tasks
                ->where('task_type', \App\Enums\TaskType::SURVEY->value)
                ->where('status', 'selesai')
                ->sortByDesc('completed_at')
                ->first();

            $ref = $surveyTask?->completed_at ?? $this->customer->updated_at;

            return \Illuminate\Support\Carbon::parse($ref)->addHours($this->handlingSlaHours());
        }

        return \Illuminate\Support\Carbon::parse($this->task_date)->addHours($this->handlingSlaHours());
    }

    /**
     * Total durasi SLA (detik) — dipakai buat progress bar countdown.
     */
    public function slaTotalSeconds(): int
    {
        if (!$this->task?->scheduled_at) {
            return $this->handlingSlaHours() * 3600;
        }

        return $this->category->slaMinutes() * 60;
    }
}
