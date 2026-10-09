<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris per unit SN yang dijual dari gudang ke pelanggan (Beli dari Gudang).
 * Lihat migration `create_inventory_serial_sales_table` untuk alasan FK & nullable
 * `fop_task_id`.
 */
#[Fillable([
    'inventory_serial_id',
    'customer_id',
    'fop_task_id',
    'price',
    'created_by',
])]
class InventorySerialSale extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function inventorySerial(): BelongsTo
    {
        return $this->belongsTo(InventorySerial::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function fopTask(): BelongsTo
    {
        return $this->belongsTo(FopTask::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
