<?php

namespace App\Models;

use App\Enums\IsolationEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris timeline kasus isolir — APPEND-ONLY
 * (docs/plan/Rancangan_Monitoring_Isolir_Pelanggan.md §12.3/§4.4). Dibuat
 * HANYA lewat `IsolationCaseService` (`create()`), jangan `update()`/
 * `delete()` baris di sini dari kode manapun.
 */
#[Fillable(['isolation_case_id', 'event_type', 'from_status', 'to_status', 'actor_type', 'actor_id', 'description', 'metadata', 'occurred_at', 'created_at'])]
class IsolationEvent extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'event_type' => IsolationEventType::class,
            'metadata' => 'array',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
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
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
