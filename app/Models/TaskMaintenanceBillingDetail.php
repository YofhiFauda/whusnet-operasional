<?php

namespace App\Models;

use App\Enums\CReqVerificationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Antrean verifikasi biaya untuk task MTN polos (bukan C-REQ). Lihat
 * migration `create_task_maintenance_billing_details_table`.
 */
class TaskMaintenanceBillingDetail extends Model
{
    protected $fillable = [
        'task_id',
        'is_billable',
        'billing_note',
        'verification_status',
        'verified_by',
        'verified_at',
        'rejection_reason',
        'invoice_id',
    ];

    protected function casts(): array
    {
        return [
            'is_billable' => 'boolean',
            'verification_status' => CReqVerificationStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
