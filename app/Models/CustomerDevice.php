<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditLogs;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'customer_id',
    'device_type',
    'brand',
    'model',
    'serial_number',
    'mac_address',
    'pppoe_username',
    'pppoe_password',
    'wifi_ssid',
    'wifi_password',
    'vlan_id',
    'odp',
    'odp_port',
    'signal_rx_power',
    'connection_mode',
    'technical_note',
    'device_retrieved_at',
])]
class CustomerDevice extends Model
{
    use RecordsAuditLogs;

    /**
     * `device_type` wajib diisi; baris untuk pelanggan hasil migrasi (yang tak
     * pernah lewat alur instalasi sistem baru) diberi placeholder ini. Sama
     * dengan literal di import pelanggan & BackfillDeviceRetrievedStatusCommand.
     */
    public const LEGACY_DEVICE_TYPE = 'Data Migrasi Legacy';

    protected string $auditModule = 'Data Teknis';

    protected array $auditEvents = ['created', 'updated', 'deleted'];

    protected array $auditHidden = [
        'pppoe_password',
        'wifi_password',
    ];

    protected function casts(): array
    {
        return [
            'signal_rx_power' => 'decimal:2',
            'device_retrieved_at' => 'datetime',
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
     * Trigger completeness recalculation on parent Customer.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saved(function (CustomerDevice $device) {
            $device->customer?->recalculateCompleteness();
        });

        static::deleted(function (CustomerDevice $device) {
            $device->customer?->recalculateCompleteness();
        });
    }
}
