<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditLogs;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'customer_id',
    'internet_package_id',
    'old_request_id',
    'old_cost_id',
    'request_status',
    'installation_status',
    'network_type',
    'member_type',
    'reason',
    'package_name_snapshot',
    'download_speed_snapshot',
    'upload_speed_snapshot',
    'monthly_price',
    'discount',
    'ppn',
    'total_monthly_bill',
    'activation_date',
    'due_date',
    'billing_cycle',
    'service_status',
    'billing_status',
])]
class CustomerService extends Model
{
    use RecordsAuditLogs;

    protected string $auditModule = 'Data Pelanggan';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monthly_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'ppn' => 'decimal:2',
            'total_monthly_bill' => 'decimal:2',
            'activation_date' => 'date',
            'due_date' => 'date',
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
     * @return BelongsTo<InternetPackage, $this>
     */
    public function internetPackage(): BelongsTo
    {
        return $this->belongsTo(InternetPackage::class);
    }

    /**
     * Trigger completeness recalculation on parent Customer.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saved(function (CustomerService $service) {
            $service->customer?->recalculateCompleteness();
        });

        static::deleted(function (CustomerService $service) {
            $service->customer?->recalculateCompleteness();
        });
    }
}
