<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak pemetaan representasi lama → posisi baru (§7.3). `status`:
 * `mapped` (terbukti FK + nomor unik), `ambiguous` (menunggu keputusan
 * operator — JANGAN disambung otomatis), `rejected` (operator menolak).
 */
#[Fillable([
    'legacy_type',
    'legacy_id',
    'new_type',
    'new_id',
    'status',
    'pop_id',
    'reason',
    'evidence',
    'decision',
    'decided_by',
    'decided_at',
])]
class InfrastructureLegacyMapping extends Model
{
    public const MAPPED = 'mapped';

    public const AMBIGUOUS = 'ambiguous';

    public const REJECTED = 'rejected';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'legacy_id' => 'integer',
            'new_id' => 'integer',
            'pop_id' => 'integer',
            'evidence' => 'array',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Pop, $this>
     */
    public function pop(): BelongsTo
    {
        return $this->belongsTo(Pop::class);
    }
}
