<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Counter terkunci untuk generator `payment_number` per periode (Ym) —
 * desain SEBELUM BUG 13 (2026-10-01). Sejak `Payment::generatePaymentNumber()`
 * pindah ke format `PAY-{invoice_number}-{NN}` (urutan dihitung per
 * `invoice_id`, bukan per periode global), tabel ini TIDAK DIBACA generator
 * lagi — dibiarkan apa adanya (bukan dihapus, baris lama tak perlu
 * dibersihkan), lihat `docs/plan/billing/rancangan-prefix-nomor-invoice.md` §8.
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
