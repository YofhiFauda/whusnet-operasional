<?php

namespace App\Models;

use App\Enums\CReqCategory;
use App\Enums\CReqVerificationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskCreqDetail extends Model
{
    protected $fillable = [
        'task_id',
        'category',
        'category_custom_name',
        'tikor_lama_lat',
        'tikor_lama_lng',
        'tikor_baru_lat',
        'tikor_baru_lng',
        'is_billable',
        'billing_note',
        'verification_status',
        'verified_by',
        'verified_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'category' => CReqCategory::class,
            'verification_status' => CReqVerificationStatus::class,
            'tikor_lama_lat' => 'decimal:7',
            'tikor_lama_lng' => 'decimal:7',
            'tikor_baru_lat' => 'decimal:7',
            'tikor_baru_lng' => 'decimal:7',
            'is_billable' => 'boolean',
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
}
