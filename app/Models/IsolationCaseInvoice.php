<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tautan kasus isolir ↔ invoice yang jadi dasarnya
 * (docs/plan/Rancangan_Monitoring_Isolir_Pelanggan.md §12.2).
 */
#[Fillable(['isolation_case_id', 'invoice_id', 'amount_at_link', 'created_at'])]
class IsolationCaseInvoice extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'amount_at_link' => 'decimal:2',
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
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
