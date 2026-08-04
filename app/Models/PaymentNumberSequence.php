<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Counter terkunci untuk generator `payment_number` per periode (Ym).
 * Lihat Payment::generatePaymentNumber() untuk logika increment-nya, dan
 * migration create_payment_number_sequences_table untuk alasan tabel ini
 * ada (pengganti MAX+1 yang rawan race).
 */
class PaymentNumberSequence extends Model
{
    protected $fillable = [
        'period_code',
        'current_number',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_number' => 'integer',
        ];
    }
}
