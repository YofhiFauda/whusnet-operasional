<?php

namespace App\Observers;

use App\Models\Invoice;
use InvalidArgumentException;

class InvoiceObserver
{
    /**
     * Reject invoices with no invoice_type, and reject an exact-duplicate burst
     * insert (same customer, type, billing period, and amount, seconds/minutes
     * apart). This is the same signature-based dedup rule retrofitted into
     * MigrateLegacyDataCommand for the retry/duplicate-submit bug found in
     * biaya_tagihan — enforcing it here means any future insert path (manual
     * form, import command, API) gets the same protection automatically
     * instead of needing its own copy of the dedup logic.
     */
    public function creating(Invoice $invoice): void
    {
        if (empty($invoice->invoice_type)) {
            throw new InvalidArgumentException('Invoice type must be set explicitly.');
        }

        $burstWindowSeconds = 300;

        $duplicate = Invoice::where('customer_id', $invoice->customer_id)
            ->where('invoice_type', $invoice->invoice_type instanceof \BackedEnum ? $invoice->invoice_type->value : $invoice->invoice_type)
            ->where('billing_period', $invoice->billing_period)
            ->where('total_amount', $invoice->total_amount)
            ->where('created_at', '>=', now()->subSeconds($burstWindowSeconds))
            ->exists();

        if ($duplicate) {
            throw new InvalidArgumentException('Duplicate invoice detected: same customer, type, period, and amount was just created.');
        }
    }
}
