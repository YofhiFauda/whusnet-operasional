<?php

namespace App\Models;

use App\Enums\DeviceRetrievalSource;
use App\Enums\ItemCondition;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak pengambilan modem dari pelanggan, satu baris per SN (ADHOC-88).
 * Lihat migrasi `create_device_retrieval_logs_table` untuk alasan tabel ini
 * berdiri sendiri: tidak boleh hilang saat SN dipakai pelanggan lain atau
 * `device_retrieved_at` direset karena Langganan Lagi.
 */
#[Fillable([
    'customer_id',
    'serial_id',
    'serial_number',
    'item_id',
    'source',
    'task_id',
    'retrieved_by',
    'received_by',
    'warehouse_pop_id',
    'condition',
    'estimated_value',
    'condition_photo',
    'accessories',
    'notes',
    'retrieved_at',
    'received_at',
])]
class DeviceRetrievalLog extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => DeviceRetrievalSource::class,
            'condition' => ItemCondition::class,
            'accessories' => 'array',
            'estimated_value' => 'decimal:2',
            'retrieved_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function serial(): BelongsTo
    {
        return $this->belongsTo(InventorySerial::class, 'serial_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function retrievedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'retrieved_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function warehousePop(): BelongsTo
    {
        return $this->belongsTo(Pop::class, 'warehouse_pop_id');
    }

    /**
     * Sudah diterima gudang? Selama belum, modem masih dipegang teknisi (transit).
     */
    public function isReceived(): bool
    {
        return $this->received_at !== null;
    }

    /**
     * Foto kondisi: milik log sendiri (jalur diantar pelanggan) atau milik
     * laporan task DEAC. Path relatif ke disk `public`.
     */
    public function photoPath(): ?string
    {
        return $this->condition_photo ?? $this->task?->deviceRetrieval?->condition_photo;
    }
}
