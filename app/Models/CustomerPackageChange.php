<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit trail satu ganti paket (upgrade/downgrade, ADHOC-68) yang mengenai
 * invoice periode berjalan. Lihat CustomerPackageService::change() untuk
 * bagaimana baris-baris ini direkonstruksi jadi n-segmen kalau paket diganti
 * berkali-kali dalam satu periode.
 */
class CustomerPackageChange extends Model
{
    protected $fillable = [
        'customer_id',
        'customer_service_id',
        'old_internet_package_id',
        'new_internet_package_id',
        'old_monthly_price',
        'new_monthly_price',
        'billing_period',
        'effective_date',
        'days_in_period',
        'days_old_used',
        'days_new_used',
        'prorate_old_amount',
        'prorate_new_amount',
        'total_recomputed',
        'previously_paid',
        'resulting_invoice_id',
        'deposit_mutation_id',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_monthly_price' => 'decimal:2',
            'new_monthly_price' => 'decimal:2',
            'effective_date' => 'date',
            'prorate_old_amount' => 'decimal:2',
            'prorate_new_amount' => 'decimal:2',
            'total_recomputed' => 'decimal:2',
            'previously_paid' => 'decimal:2',
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
     * @return BelongsTo<CustomerService, $this>
     */
    public function customerService(): BelongsTo
    {
        return $this->belongsTo(CustomerService::class);
    }

    /**
     * @return BelongsTo<InternetPackage, $this>
     */
    public function oldInternetPackage(): BelongsTo
    {
        return $this->belongsTo(InternetPackage::class, 'old_internet_package_id');
    }

    /**
     * @return BelongsTo<InternetPackage, $this>
     */
    public function newInternetPackage(): BelongsTo
    {
        return $this->belongsTo(InternetPackage::class, 'new_internet_package_id');
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function resultingInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'resulting_invoice_id');
    }

    /**
     * @return BelongsTo<CustomerBalanceMutation, $this>
     */
    public function depositMutation(): BelongsTo
    {
        return $this->belongsTo(CustomerBalanceMutation::class, 'deposit_mutation_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
