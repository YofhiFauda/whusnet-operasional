<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Traits\HasPopScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Payment extends Model
{
    use HasPopScope;

    protected $fillable = [
        'payment_number',
        'old_payment_id',
        'old_transaction_id',
        'old_request_id',
        'billing_period',
        'received_by_old',
        'deposited_by_old',
        'invoice_id',
        'customer_id',
        'pop_id',
        'payment_date',
        'payment_method',
        'amount',
        'received_by',
        'proof_file',
        'payment_status',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'payment_status' => PaymentStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Payment $payment): void {
            $payment->writeAuditLog('create', null, $payment->auditPayload());
        });

        static::updated(function (Payment $payment): void {
            $changed = array_keys($payment->getChanges());
            $changed = array_values(array_diff($changed, ['updated_at']));

            if ($changed === []) {
                return;
            }

            $action = $payment->wasChanged('payment_status') && $payment->payment_status === PaymentStatus::DITOLAK
                ? 'cancel'
                : 'update';

            $oldValues = [];
            $newValues = [];

            foreach ($changed as $field) {
                $oldValues[$field] = $payment->getOriginal($field);
                $newValues[$field] = $payment->{$field};
            }

            $payment->writeAuditLog($action, $oldValues, $newValues);
        });

        static::deleted(function (Payment $payment): void {
            $payment->writeAuditLog('delete', $payment->auditPayload(), null);
        });
    }

    /**
     * Get the invoice associated with this payment.
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the customer associated with this payment.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the POP associated with this payment.
     *
     * @return BelongsTo<Pop, $this>
     */
    public function pop(): BelongsTo
    {
        return $this->belongsTo(Pop::class);
    }

    /**
     * Get the user who received this payment.
     *
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * Get audit logs for this payment.
     *
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('created_at');
    }

    /**
     * @return array<string, mixed>
     */
    private function auditPayload(): array
    {
        return $this->only([
            'payment_number',
            'old_payment_id',
            'old_transaction_id',
            'old_request_id',
            'billing_period',
            'received_by_old',
            'deposited_by_old',
            'invoice_id',
            'customer_id',
            'pop_id',
            'payment_date',
            'payment_method',
            'amount',
            'received_by',
            'proof_file',
            'payment_status',
            'note',
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function writeAuditLog(string $action, ?array $oldValues, ?array $newValues): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'Pembayaran',
            'action' => $action,
            'auditable_type' => self::class,
            'auditable_id' => $this->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
