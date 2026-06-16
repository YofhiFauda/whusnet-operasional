<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditLogs;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
    'ip_address',
    'antenna_mac',
    'router_mac',
    'router_or_ont_serial',
    'odp_number',
    'odp_port',
    'olt_port',
    'wireless_signal',
    'fiber_signal',
    'location_source',
    'note',
    'speedtest_photo',
    'form_photo',
    'signed_form_photo',
    'router_photo',
    'cable_photo',
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
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
