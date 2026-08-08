<?php

namespace App\Observers;

use App\Models\Invoice;
use BackedEnum;
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

        $type = $invoice->invoice_type instanceof BackedEnum
            ? $invoice->invoice_type->value
            : $invoice->invoice_type;

        $burstWindowSeconds = 300;

        $duplicate = Invoice::where('customer_id', $invoice->customer_id)
            ->where('invoice_type', $type)
            ->where('billing_period', $invoice->billing_period)
            ->where('total_amount', $invoice->total_amount)
            ->where('created_at', '>=', now()->subSeconds($burstWindowSeconds))
            ->exists();

        if ($duplicate) {
            throw new InvalidArgumentException('Duplicate invoice detected: same customer, type, period, and amount was just created.');
        }

        $this->rejectSecondSubscriptionInvoice($invoice, $type);
    }

    /**
     * Satu pelanggan hanya boleh punya SATU tagihan langganan per periode,
     * lintas jenis. Aturan lama di sini di-scope per `invoice_type`, sehingga
     * AWAL dan BULANAN pada periode yang sama lolos — persis kombinasi yang
     * bikin pelanggan menerima tagihan dobel waktu `activation_date` salah isi.
     *
     * Ditegakkan di observer, bukan cuma di GenerateMonthlyInvoicesCommand,
     * supaya jalur lain ikut tertutup: input manual admin, import, tinker.
     *
     * Dua pengecualian yang disengaja:
     *
     * 1. Invoice ber-`old_invoice_id` (replay data legacy) dilewati. Data lama
     *    memang menyimpan pelanggaran historis — ada pelanggan dengan AWAL dan
     *    BULANAN di periode sama — dan migrasi harus tetap bisa memuatnya apa
     *    adanya. Pembersihannya ranah BILLING-B0e, bukan tugas guard ini.
     * 2. Invoice berstatus BATAL tidak dihitung, kalau tidak tagihan yang sudah
     *    dibatalkan akan memblokir penerbitan penggantinya.
     */
    private function rejectSecondSubscriptionInvoice(Invoice $invoice, ?string $type): void
    {
        if (! in_array($type, Invoice::SUBSCRIPTION_TYPES, true)) {
            return;
        }

        if (! empty($invoice->old_invoice_id)) {
            return;
        }

        if (empty($invoice->customer_id) || empty($invoice->billing_period)) {
            return;
        }

        $exists = Invoice::hasActiveSubscriptionInvoiceForPeriod($invoice->customer_id, $invoice->billing_period);

        if ($exists) {
            throw new InvalidArgumentException(
                "Pelanggan ini sudah punya tagihan langganan untuk periode {$invoice->billing_period}. "
                .'Satu periode hanya boleh punya satu tagihan langganan (AWAL atau BULANAN, tidak keduanya).'
            );
        }
    }
}
