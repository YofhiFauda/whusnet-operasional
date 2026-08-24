<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditLogs;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'customer_id',
    'old_report_id',
    'old_customer_id',
    'old_request_id',
    'connection_type',
    'test_upload',
    'test_download',
    'ssid',
    'antenna_mac',
    'router_mac',
    'router_or_ont_serial',
    'odp_number',
    'odp_port',
    'olt_port',
    'olt_number',
    'olt_slot',
    'vlan',
    'wireless_signal',
    'fiber_signal',
    'location_source',
    'note',
    'speedtest_photo',
    'form_photo',
    'signed_form_photo',
    'router_photo',
    'cable_photo',
    'passive_device',
    'passive_device_type',
    'passive_device_qty',
    'passive_device_note',
    'branch_number',
    'pop_number',
    'router_number',
    'initial_attenuation',
    'actual_attenuation',
    'test_date',
    'test_time',
    'jitter_ms',
    'latency_ms',
    'packet_loss_percent',
    'speed_conformity_percent',
    'quality_score',
])]
class CustomerTechnicalDetail extends Model
{
    use RecordsAuditLogs;

    protected string $auditModule = 'Detail Teknis Pelanggan';

    protected array $auditEvents = ['created', 'updated', 'deleted'];

    protected array $auditHidden = [
        'speedtest_photo',
        'form_photo',
        'signed_form_photo',
        'router_photo',
        'cable_photo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jitter_ms' => 'float',
            'latency_ms' => 'float',
            'packet_loss_percent' => 'float',
            'speed_conformity_percent' => 'float',
            'initial_attenuation' => 'float',
            'actual_attenuation' => 'float',
            'quality_score' => 'integer',
        ];
    }

    /**
     * Accessor for test_download to handle comma decimal separator from legacy data.
     */
    protected function testDownload(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value !== null ? (float) str_replace(',', '.', (string) $value) : null,
        );
    }

    /**
     * Accessor for test_upload to handle comma decimal separator from legacy data.
     */
    protected function testUpload(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value !== null ? (float) str_replace(',', '.', (string) $value) : null,
        );
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
