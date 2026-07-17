<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditLogs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use RecordsAuditLogs, \App\Traits\HasPopScope;

    protected string $auditModule = 'Tagihan';

    protected array $auditEvents = ['updated', 'deleted'];

    protected $fillable = [
        'invoice_number',
        'invoice_type',
        'old_invoice_id',
        'old_cost_id',
        'old_request_id',
        'customer_id',
        'pop_id',
        'customer_service_id',
        'internet_package_id',
        'billing_period',
        'issue_date',
        'due_date',
        'subtotal',
        'discount',
        'ppn',
        'total_amount',
        'paid_amount',
        'remaining_amount',
        'invoice_status',
        'created_by',
        'prorate_amount',
        'extra_cable_fee',
        'other_fee',
        'extra_installation_fee',
        'extra_pole_fee',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'invoice_type' => \App\Enums\InvoiceType::class,
            'invoice_status' => \App\Enums\InvoiceStatus::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'ppn' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'other_fee' => 'decimal:2',
        ];
    }

    /**
     * Get the customer associated with this invoice.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the POP associated with this invoice.
     *
     * @return BelongsTo<Pop, $this>
     */
    public function pop(): BelongsTo
    {
        return $this->belongsTo(Pop::class);
    }

    /**
     * Get the customer service associated with this invoice.
     *
     * @return BelongsTo<CustomerService, $this>
     */
    public function customerService(): BelongsTo
    {
        return $this->belongsTo(CustomerService::class);
    }

    /**
     * Get the internet package associated with this invoice.
     *
     * @return BelongsTo<InternetPackage, $this>
     */
    public function internetPackage(): BelongsTo
    {
        return $this->belongsTo(InternetPackage::class);
    }

    /**
     * Get the user who created this invoice.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the payments recorded for this invoice.
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }


}
