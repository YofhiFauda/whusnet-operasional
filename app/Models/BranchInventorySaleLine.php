<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'branch_inventory_sale_id',
    'inventory_serial_id',
    'sale_price',
])]
class BranchInventorySaleLine extends Model
{
    protected function casts(): array
    {
        return ['sale_price' => 'decimal:2'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(BranchInventorySale::class, 'branch_inventory_sale_id');
    }

    public function serial(): BelongsTo
    {
        return $this->belongsTo(InventorySerial::class, 'inventory_serial_id');
    }
}
