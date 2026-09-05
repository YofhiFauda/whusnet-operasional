<?php

namespace App\Models;

use App\Enums\InventoryTransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ledger APPEND-ONLY — satu-satunya sumber histori inventory. `inventory_balances`/
 * `technician_custody`/`inventory_serials` adalah proyeksi yang DITURUNKAN dari
 * tabel ini, bukan sebaliknya (§25 warehouse_inventory_asset_traceability_analysis.md).
 *
 * TIDAK BOLEH di-`update()`/`delete()` siapa pun termasuk owner. Guard-nya
 * BELUM dipasang di model ini — itu tugas `InventoryTransactionObserver`
 * (Fase Observer berikutnya). Model ini SENGAJA cuma struktur data dulu,
 * jangan dianggap sudah aman dari edit sebelum Observer-nya benar-benar ada.
 *
 * Kombinasi kolom `from_*`/`to_*`/`reason`/`notes` yang valid beda-beda per
 * `type` — lihat komentar lengkap di migration `create_inventory_transactions_table`.
 * Validasi kombinasi itu tugas Service, bukan model ini.
 */
#[Fillable([
    'type',
    'reference_number',
    'inventory_transfer_id',
    'item_id',
    'lot_no',
    'serial_id',
    'qty',
    'unit_price_snapshot',
    'from_pop_id',
    'to_pop_id',
    'from_technician_id',
    'to_technician_id',
    'fop_task_id',
    'reason',
    'notes',
    'evidence_file_path',
    'created_by',
])]
class InventoryTransaction extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => InventoryTransactionType::class,
            'qty' => 'decimal:2',
            'unit_price_snapshot' => 'decimal:2',
        ];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(InventoryTransfer::class, 'inventory_transfer_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function serial(): BelongsTo
    {
        return $this->belongsTo(InventorySerial::class, 'serial_id');
    }

    public function fromPop(): BelongsTo
    {
        return $this->belongsTo(Pop::class, 'from_pop_id');
    }

    public function toPop(): BelongsTo
    {
        return $this->belongsTo(Pop::class, 'to_pop_id');
    }

    public function fromTechnician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_technician_id');
    }

    public function toTechnician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_technician_id');
    }

    public function fopTask(): BelongsTo
    {
        return $this->belongsTo(FopTask::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeType($query, InventoryTransactionType $type)
    {
        return $query->where('type', $type->value);
    }
}
