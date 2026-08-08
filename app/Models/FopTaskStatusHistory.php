<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'fop_task_id',
    'from_status',
    'to_status',
    'changed_by',
    'changed_at',
])]
class FopTaskStatusHistory extends Model
{
    protected $table = 'fop_task_status_history';

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    public function fopTask(): BelongsTo
    {
        return $this->belongsTo(FopTask::class);
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Label human-readable buat `to_status` — granular (bisa beda dari raw
     * `fop_tasks.status` enum yang cuma 4 nilai kasar), dipakai badge status
     * realtime di FOP-side. Sumber nilai `to_status`: TaskObserver::resolveTarget().
     */
    public function label(): string
    {
        return match ($this->to_status) {
            'draft' => 'Draft',
            'terjadwal' => 'Terjadwal',
            'in_progress' => 'Sedang Dikerjakan',
            'lapor_nanti' => 'Lapor Nanti',
            'pending_fop' => 'Pending',
            'selesai' => 'Selesai',
            'selesai_menunggu_verifikasi' => 'Selesai — Menunggu Verifikasi',
            'selesai_ditolak_verifikasi' => 'Selesai — Ditolak Verifikasi',
            'dibatalkan' => 'Dibatalkan',
            default => $this->to_status,
        };
    }
}
