<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditLogs;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'customer_id',
    'full_address',
    'province',
    'city',
    'district',
    'village',
    'city_id',
    'district_id',
    'village_id',
    'latitude',
    'longitude',
    'house_photo',
    'ktp_photo',
    'contract_photo',
])]
class CustomerAddress extends Model
{
    use RecordsAuditLogs;

    protected string $auditModule = 'Data Pelanggan';

    protected array $auditHidden = [
        'house_photo',
        'ktp_photo',
        'contract_photo',
    ];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * @return BelongsTo<District, $this>
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /**
     * @return BelongsTo<Village, $this>
     */
    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    /**
     * Trigger completeness recalculation on parent Customer.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saved(function (CustomerAddress $address) {
            $address->customer?->recalculateCompleteness();
        });

        static::deleted(function (CustomerAddress $address) {
            $address->customer?->recalculateCompleteness();
        });
    }
}
