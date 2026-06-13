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
    'ip_address',
    'vlan_id',
    'odp',
    'odp_port',
    'signal_rx_power',
    'connection_mode',
    'technical_note',
])]
class CustomerDevice extends Model
{
    use RecordsAuditLogs;

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
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
