<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditLogs;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Perangkat tambahan pelanggan (AP, STB, dll.) di luar perangkat utama.
 *
 * Sengaja TIDAK memakai boot()/recalculateCompleteness() seperti CustomerDevice:
 * device tambahan tidak mempengaruhi data_completeness_status pelanggan.
 */
#[Fillable([
    'customer_id',
    'device_type',
    'brand',
    'model',
    'serial_number',
    'mac_address',
    'technical_note',
    'inventory_serial_id',
    'status',
    'retrieved_at',
])]
class CustomerAdditionalDevice extends Model
{
    use RecordsAuditLogs;

    protected string $auditModule = 'Data Teknis';

    protected array $auditEvents = ['created', 'updated', 'deleted'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'retrieved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<InventorySerial, $this>
     */
    public function inventorySerial(): BelongsTo
    {
        return $this->belongsTo(InventorySerial::class);
    }
}
