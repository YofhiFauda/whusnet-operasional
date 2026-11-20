<?php

namespace App\Models;

use App\Enums\IsolationActionStatus;
use App\Enums\IsolationActionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu eksekusi isolir/pemulihan (docs/plan/Rancangan_Monitoring_Isolir_Pelanggan.md
 * §12.4). Batch pertama selalu `succeeded` sinkron (klik = langsung
 * selesai) — lihat `IsolationCaseService`.
 */
#[Fillable([
    'isolation_case_id', 'action_type', 'status', 'idempotency_key', 'attempt_number',
    'requested_by', 'executed_by', 'started_at', 'finished_at', 'result_code',
    'result_message', 'device_reference', 'metadata',
])]
class IsolationAction extends Model
{
    protected function casts(): array
    {
        return [
            'action_type' => IsolationActionType::class,
            'status' => IsolationActionStatus::class,
            'metadata' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<IsolationCase, $this>
     */
    public function isolationCase(): BelongsTo
    {
        return $this->belongsTo(IsolationCase::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }
}
