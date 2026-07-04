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
    }
}
