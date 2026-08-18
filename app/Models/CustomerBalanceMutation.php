<?php

namespace App\Models;

use App\Enums\CustomerBalanceMutationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris ledger append-only saldo pelanggan. Jangan pernah di-update
 * atau dihapus setelah dibuat — koreksi selalu berupa baris pembalik baru
 * (lihat CustomerBalanceService::reverseCreditForPayment()), sama seperti
 * pola koreksi payment di PaymentController::reject().
 */
class CustomerBalanceMutation extends Model
{
    protected $fillable = [
        'customer_id',
        'type',
        'amount',
        'payment_id',
        'pop_id',
        'created_by',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CustomerBalanceMutationType::class,
            'amount' => 'decimal:2',
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
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<Pop, $this>
     */
    public function pop(): BelongsTo
    {
        return $this->belongsTo(Pop::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
