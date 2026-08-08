<?php

namespace App\Observers;

use App\Models\Payment;
use InvalidArgumentException;

class PaymentObserver
{
    /**
     * Reject zero/negative-amount payments regardless of caller (controller,
     * artisan command, import script, tinker). PaymentController::store already
     * validates this at the request layer, but that guard only protects one
     * entry point — the legacy migration had "payment" rows with BAYAR=0 that
     * were really activation log placeholders, not real payments. Enforcing it
     * here closes that gap for every future insert path too.
     */
    public function creating(Payment $payment): void
    {
        if ((float) $payment->amount <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

        $this->rejectBurstDuplicate($payment);
    }

    /**
     * Tolak insert identik (invoice + amount + payment_date) dalam jendela
     * pendek — pola sama InvoiceObserver (§A-4). Ini menggantikan unique index
     * `payments_invoice_date_amount_unique` yang di-drop karena memblokir
     * cicilan sah (nominal sama, hari sama) — lihat docs/plan/analisa-billing-
     * tagihan-pembayaran-kolektor.md §A-7 #6 & #8, §C-2(a), §D-9 no. 2.
     *
     * Bukan cuma proteksi jalur batch: index lama adalah SATU-SATUNYA guard
     * dobel-submit di PaymentController::store (single-payment, non-batch).
     * Tanpa guard ini, admin yang klik dobel tombol "Bayar" akan membuat dua
     * payment identik tanpa penjagaan apa pun — invoice ter-update dua kali.
     *
     * Burst window (300 detik) = heuristik dobel-klik/retry, BUKAN jaminan
     * integritas mutlak — cicilan sah dengan nominal sama di hari yang sama
     * tapi berjarak lebih dari 300 detik (mis. pagi vs sore) tetap lolos,
     * sesuai maksud aturan bisnis (cicilan boleh, dobel-submit tidak).
     */
    private function rejectBurstDuplicate(Payment $payment): void
    {
        $burstWindowSeconds = 300;

        $duplicate = Payment::where('invoice_id', $payment->invoice_id)
            ->where('customer_id', $payment->customer_id)
            ->where('amount', $payment->amount)
            ->where('payment_date', $payment->payment_date)
            ->where('created_at', '>=', now()->subSeconds($burstWindowSeconds))
            ->exists();

        if ($duplicate) {
            throw new InvalidArgumentException('Duplicate payment detected: same invoice, amount, and date was just recorded.');
        }
    }
}
