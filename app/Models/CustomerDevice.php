<?php

namespace App\Models;

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
