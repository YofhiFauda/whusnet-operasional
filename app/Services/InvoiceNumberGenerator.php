<?php

namespace App\Services;

use App\Models\Invoice;

/**
 * Penomoran tagihan `INV-{YYYYMM}-{NNNN}`.
 *
 * Diekstrak dari dua salinan identik (CustomerController::storeManualInvoice
 * dan GenerateMonthlyInvoicesCommand) waktu jalur tagihan manual dipindah ke
 * modul Tagihan (ADHOC-60). Dua salinan itu menulis ke deret yang SAMA, jadi
 * begitu keduanya menyimpang formatnya nomor tagihan langsung bentrok —
 * masalah yang persis sama sudah pernah didokumentasikan untuk `TFOP-` di
 * CLAUDE.md. Satu penghasil nomor menutup peluang itu.
 */
class InvoiceNumberGenerator
{
    /**
     * Nomor berikutnya untuk satu periode billing (`YYYY-MM`).
     *
     * WAJIB dipanggil di dalam transaksi: `lockForUpdate()` di sini yang
     * menahan dua request bersamaan mengambil urutan yang sama, dan kuncinya
     * baru dilepas saat transaksi selesai. Dipanggil di luar transaksi,
     * kuncinya lepas seketika dan guard-nya jadi hiasan.
     */
    public function nextFor(string $billingPeriod): string
    {
        $periodCode = str_replace('-', '', $billingPeriod);

        $lastInvoice = Invoice::where('invoice_number', 'like', "INV-{$periodCode}-%")
            ->orderBy('invoice_number', 'desc')
            ->lockForUpdate()
            ->first();

        $nextSeq = 1;
        if ($lastInvoice) {
            $parts = explode('-', $lastInvoice->invoice_number);
            if (count($parts) === 3) {
                $nextSeq = ((int) $parts[2]) + 1;
            }
        }

        return sprintf('INV-%s-%04d', $periodCode, $nextSeq);
    }
}
