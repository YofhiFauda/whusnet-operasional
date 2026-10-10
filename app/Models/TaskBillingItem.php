<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris breakdown biaya verifikasi (C-REQ & MTN). Invoice induknya tetap
 * flat; rincian per-item hanya hidup di tabel ini. Lihat migration
 * `create_task_billing_items_table`.
 */
class TaskBillingItem extends Model
{
    protected $fillable = [
        'task_id',
        'invoice_id',
        'name',
        'amount',
        'inventory_serial_sale_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** Terisi hanya untuk baris "Jual Modem" yang diturunkan dari InventorySerialSale. */
    public function inventorySerialSale(): BelongsTo
    {
        return $this->belongsTo(InventorySerialSale::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
