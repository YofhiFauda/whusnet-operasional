<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris bukti rekonsiliasi cutover per Cabang per eksekusi (§16.4 #6).
 */
#[Fillable(['run_key', 'period', 'pop_id', 'legacy_metrics', 'projection_metrics', 'diff', 'is_zero', 'created_by', 'trigger', 'kind', 'observed_at', 'evidence_version'])]
class InfrastructureReconciliationRun extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pop_id' => 'integer',
            'observed_at' => 'immutable_datetime',
            'evidence_version' => 'integer',
            'legacy_metrics' => 'array',
            'projection_metrics' => 'array',
            'diff' => 'array',
            'is_zero' => 'boolean',
        ];
    }

    public function pop(): BelongsTo
    {
        return $this->belongsTo(Pop::class);
    }
}
