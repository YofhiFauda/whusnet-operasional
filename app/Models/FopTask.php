<?php

namespace App\Models;

use App\Enums\FopTaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Traits\HasPopScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

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
    'sla_breach_notified_at',
])]
class FopTask extends Model
{
    use HasPopScope;

    protected function casts(): array
    {
        return [
            'task_date' => 'datetime',
            'client_request_date' => 'date',
            'cancelled_at' => 'datetime',
            'sla_breach_notified_at' => 'datetime',
            'status' => TaskStatus::class,
            'priority' => FopTaskPriority::class,
            'category' => TaskType::class,
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

        // task_date berubah (reschedule) → deadline SLA ikut geser
        // (slaDeadline()), jadi flag dedup CheckFopTaskSlaBreach wajib reset
        // biar breach di jadwal BARU tetap kena-notif, bukan diam-diam
        // ke-skip gara-gara udah pernah notif buat jadwal lama.
        static::updating(function (self $fopTask) {
            if ($fopTask->isDirty('task_date') && $fopTask->sla_breach_notified_at !== null) {
                $fopTask->sla_breach_notified_at = null;
            }
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
     * Ticket asal (kalau tiket ini hasil auto-sync dari Ticketing — lihat
     * TicketService::syncToFopTask()). Null buat FopTask yang dibuat langsung
     * dari /fop-tasks tanpa lewat Ticketing, termasuk category MTN/C-REQ yang
     * dibuat manual oleh FOP.
     */
    public function ticket(): HasOne
    {
        return $this->hasOne(Ticket::class, 'fop_task_id');
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
        if (! in_array($this->category, [TaskType::SURVEY, TaskType::PEMASANGAN], true)) {
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
     * Material yang tercatat pada task ini — estimasi (survey) + terpakai
     * (pemasangan). Ditulis lewat TaskMaterialService.
     */
    public function materials(): HasMany
    {
        return $this->hasMany(TaskMaterial::class);
    }

    /**
     * Alat kerja yang perlu dibawa — checklist, bukan konsumsi. Lihat
     * TaskWorkTool untuk alasan tabelnya dipisah dari materials().
     */
    public function workTools(): HasMany
    {
        return $this->hasMany(TaskWorkTool::class);
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
    public function slaDeadline(): Carbon
    {
        if ($this->task?->scheduled_at) {
            return Carbon::parse($this->task->scheduled_at)
                ->addMinutes($this->category->slaMinutes());
        }

        if ($this->category === TaskType::SURVEY && $this->customer) {
            return Carbon::parse($this->customer->created_at)->addHours($this->handlingSlaHours());
        }

        if ($this->category === TaskType::PEMASANGAN && $this->customer) {
            // Tanggal yang diminta pelanggan mengalahkan SLA turunan survey.
            // Deadline-nya AKHIR HARI tanggal itu, bukan tanggal + handlingSlaHours():
            // yang dijanjikan ke pelanggan adalah "dipasang tanggal 20", jadi lewat
            // tengah malam tanggal 20 = sudah telat. Kalau dipakai +SLA jam, badge
            // baru merah 2-3 hari setelah tanggal janji — timernya bohong terhadap
            // janji yang dipegang pelanggan.
            if ($this->client_request_date) {
                return Carbon::parse($this->client_request_date)->endOfDay();
            }

            // NB: ini Collection::where (in-memory), bukan query builder — 'task_type'
            // & 'status' attribute-nya di-cast ke enum (TaskType/TaskStatus), jadi
            // bandingnya harus ke enum instance, BUKAN ->value string. Enum vs
            // string loose-compare gak pernah match (sama kelas bug kayak §0) —
            // sebelum fix ini, $surveyTask SELALU null (2 kondisi ini dulu gak
            // pernah match sekaligus).
            $surveyTask = $this->customer->tasks
                ->where('task_type', TaskType::SURVEY)
                ->where('status', TaskStatus::SELESAI)
                ->sortByDesc('completed_at')
                ->first();

            $ref = $surveyTask?->completed_at ?? $this->customer->updated_at;

            return Carbon::parse($ref)->addHours($this->handlingSlaHours());
        }

        return Carbon::parse($this->task_date)->addHours($this->handlingSlaHours());
    }

    /**
     * Total durasi SLA (detik) — dipakai buat progress bar countdown.
     */
    public function slaTotalSeconds(): int
    {
        // Deadline-nya endOfDay tanggal request (lihat slaDeadline()), jadi
        // jendela yang bermakna buat ambang warna countdown adalah satu hari
        // penuh — bukan handlingSlaHours() yang gak dipakai di jalur ini.
        if ($this->usesClientRequestDeadline()) {
            return 86400;
        }

        if (! $this->task?->scheduled_at) {
            return $this->handlingSlaHours() * 3600;
        }

        return $this->category->slaMinutes() * 60;
    }

    /**
     * True kalau deadline task ini ditentukan tanggal request pelanggan, bukan
     * SLA turunan survey.
     *
     * SATU-SATUNYA gerbang untuk perilaku "request client": dipakai barengan
     * oleh slaDeadline(), slaTotalSeconds(), tampilan papan FOP, dan
     * FopTaskController::autoSyncAndCalculatePriority(). Jangan duplikasi
     * kondisinya di tempat lain — kalau timer dan badge prioritas menghitung
     * dari syarat yang beda, satu task bisa nampilin dua kebenaran sekaligus.
     */
    public function usesClientRequestDeadline(): bool
    {
        // Urutan syaratnya WAJIB cermin slaDeadline(): task yang sudah punya
        // jadwal eksekusi (task.scheduled_at) memakai SLA pengerjaan, jadi
        // tanggal request tidak lagi jadi deadline-nya.
        return ! $this->task?->scheduled_at
            && $this->category === TaskType::PEMASANGAN
            && $this->customer !== null
            && $this->client_request_date !== null;
    }

    /**
     * True selama tanggal request pelanggan masih di masa depan — task belum
     * boleh dihitung telat, dan di papan FOP ditampilkan sebagai badge
     * "Dijadwalkan", bukan countdown.
     */
    public function isScheduledForFutureClientDate(): bool
    {
        return $this->usesClientRequestDeadline()
            && $this->client_request_date->isAfter(Carbon::now()->startOfDay());
    }
}
