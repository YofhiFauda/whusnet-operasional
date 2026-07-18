<?php

namespace App\Models;

use App\Enums\FopTaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\TicketBucket;
use App\Traits\HasPopScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ticket — tiket internal PERUSAHAAN (helpdesk/NOC/sales/admin), beda dari
 * FopTask yang internal FOP. Tiket adalah *permintaan*; FopTask adalah
 * *penugasan* hasil auto-sync dari permintaan itu (lihat TicketService::create()).
 *
 * Cuma berlaku buat tipe MTN & C-REQ — lihat TaskType::ticketValues().
 */
#[Fillable([
    'ticket_number',
    'type',
    'customer_id',
    'pop_id',
    'customer_name',
    'customer_address',
    'customer_phone',
    'customer_odp',
    'customer_package',
    'customer_device',
    'customer_latitude',
    'customer_longitude',
    'detail_keluhan',
    'catatan_teknis',
    'priority',
    'created_by',
    'fop_task_id',
])]
class Ticket extends Model
{
    use HasPopScope;

    protected function casts(): array
    {
        return [
            'type' => TaskType::class,
            'priority' => FopTaskPriority::class,
            'customer_latitude' => 'decimal:7',
            'customer_longitude' => 'decimal:7',
        ];
    }

    /**
     * Link Google Maps dari koordinat snapshot — null kalau salah satu
     * kosong (pelanggan belum punya titik koordinat saat ticket dibuat).
     */
    public function customerMapsUrl(): ?string
    {
        if (!$this->customer_latitude || !$this->customer_longitude) {
            return null;
        }

        return "https://www.google.com/maps/search/?api=1&query={$this->customer_latitude},{$this->customer_longitude}";
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function pop(): BelongsTo
    {
        return $this->belongsTo(Pop::class);
    }

    /**
     * Pengirim tiket — ini yang tampil sebagai "Assign by" di UI.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fopTask(): BelongsTo
    {
        return $this->belongsTo(FopTask::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    /**
     * Riwayat sisi Ticketing. Kembaran FopTask::statusHistories() — satu
     * pembatalan nulis ke dua-duanya (lihat FopTaskObserver).
     */
    public function histories(): HasMany
    {
        return $this->hasMany(TicketHistory::class)->orderByDesc('happened_at');
    }

    /**
     * Saring tiket per bucket submenu Ticketing. Status-nya nempel di FopTask
     * hasil sync, jadi filternya lewat relasi — bukan kolom di tabel ini.
     */
    public function scopeInBucket(Builder $query, TicketBucket $bucket): Builder
    {
        return $query->where(function (Builder $q) use ($bucket) {
            $q->whereHas('fopTask', fn (Builder $f) => $f->whereIn('status', $bucket->statusValues()));

            if ($bucket->includesOrphans()) {
                $q->orWhereNull('fop_task_id');
            }
        });
    }

    /**
     * Bucket submenu tiket ini — versi single-instance dari `scopeInBucket()`
     * (gak query ulang, tinggal cocokin status yang udah di-load). Dipakai
     * buat aksen visual di daftar inbox; gunakan `fopTask` yang udah
     * eager-loaded, JANGAN dipanggil di loop tanpa eager load atau kena N+1.
     */
    public function bucket(): TicketBucket
    {
        $status = $this->resolveStatus();

        if (!$status) {
            return TicketBucket::DIBATALKAN;
        }

        foreach (TicketBucket::cases() as $bucket) {
            if (in_array($status, $bucket->statuses(), true)) {
                return $bucket;
            }
        }

        return TicketBucket::DIBATALKAN;
    }

    /**
     * Status tiket TIDAK disimpan sebagai kolom — selalu diturunkan dari
     * FopTask hasil sync, biar gak ada dua sumber kebenaran yang bisa
     * melenceng (kasus yang sama kayak unifikasi FopTaskStatus → TaskStatus
     * di migration 2026_07_20_000001).
     *
     * SENGAJA dinamai `resolveStatus()`, BUKAN `status()`. Eloquent nebak
     * method zero-argument bernama sama kayak attribute access itu relasi —
     * begitu ada kode yang nulis `$ticket->status` (properti, wajar banget
     * ditulis padahal method-nya `status()`), Eloquent manggil `status()`,
     * dapet balik `TaskStatus` (bukan instance Relation), lalu lempar
     * `LogicException: Ticket::status must return a relationship instance.`
     * Ini pernah beneran kejadian (500 di /tickets/masuk) waktu blade nulis
     * `$ticket->status->value` — lihat index.blade.php.
     */
    public function resolveStatus(): ?TaskStatus
    {
        return $this->fopTask?->status;
    }

    /**
     * Label status buat UI. null fopTask = FopTask-nya udah dihapus FOP
     * (fop_task_id ke-null lewat nullOnDelete), tiketnya sendiri tetap ada.
     */
    public function statusLabel(): string
    {
        $status = $this->resolveStatus();

        if (!$status) {
            return 'Terputus';
        }

        return $status->displayLabel();
    }

    public function statusBadgeClasses(): string
    {
        $status = $this->resolveStatus();

        if (!$status) {
            return 'bg-slate-100 text-slate-600 border-slate-200';
        }

        return $status->displayBadgeClasses();
    }
}
