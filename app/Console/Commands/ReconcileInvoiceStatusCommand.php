<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('billing:reconcile-invoice-status
    {--period= : Batasi ke satu periode saja, format YYYY-MM}
    {--fix : Perbaiki penyimpangan yang ditemukan (default: laporan saja, tidak mengubah apa pun)}
    {--fix-threshold=100000 : Nominal maksimum selisih paid_amount yang boleh diperbaiki otomatis dengan --fix; di atasnya wajib direview manual}')]
#[Description('Deteksi (dan opsional perbaiki) invoice yang paid_amount/remaining_amount/invoice_status-nya tidak konsisten dengan payment valid yang benar-benar tercatat.')]
class ReconcileInvoiceStatusCommand extends Command
{
    /**
     * `invoice_status`/`paid_amount`/`remaining_amount` adalah kolom
     * TERSIMPAN, bukan turunan live — konsistensinya cuma terjaga selama
     * semua jalur bayar lewat Invoice::recalculateFromPayments() (§A-5).
     * Command ini jaring pengaman untuk yang lolos: koreksi manual data,
     * migrasi legacy, atau bug di jalur yang belum ketahuan.
     *
     * Default dry-run. `--fix` HANYA memperbaiki selisih di bawah
     * `--fix-threshold` — selisih besar bisa jadi gejala sesuatu yang lebih
     * serius (input ganda, kesalahan sistemik) yang butuh mata manusia
     * sebelum ditimpa, bukan auto-koreksi buta. Setiap koreksi otomatis
     * tercatat di audit log lewat RecordsAuditLogs pada Invoice::update()
     * di dalam recalculateFromPayments() — tidak perlu logging terpisah.
     *
     * docs/plan/analisa-billing-tagihan-pembayaran-kolektor.md §A-7 #2, §D-7a.
     */
    public function handle(): int
    {
        $period = $this->option('period');

        if ($period !== null && preg_match('/^\d{4}-\d{2}$/', $period) !== 1) {
            $this->error('Format --period harus YYYY-MM, contoh: --period=2026-07');

            return self::INVALID;
        }

        $fix = (bool) $this->option('fix');
        $threshold = (float) $this->option('fix-threshold');

        $invoices = Invoice::query()
            ->where('invoice_status', '!=', InvoiceStatus::BATAL->value)
            ->when($period !== null, fn ($q) => $q->where('billing_period', $period))
            ->with(['customer'])
            ->orderBy('billing_period')
            ->orderBy('id')
            ->get();

        $judul = $period !== null
            ? "Rekonsiliasi status tagihan — periode {$period}"
            : 'Rekonsiliasi status tagihan — semua periode';

        $this->info($judul.($fix ? ' (mode --fix aktif)' : ' (dry-run, tidak mengubah apa pun)'));

        $rows = [];
        $fixed = 0;
        $needsReview = 0;

        foreach ($invoices as $invoice) {
            $actualPaid = round(
                (float) $invoice->payments()->where('payment_status', PaymentStatus::VALID->value)->sum('amount'),
                2
            );
            $actualRemaining = max(0, round((float) $invoice->total_amount - $actualPaid, 2));
            $actualStatus = match (true) {
                $actualPaid <= 0 => InvoiceStatus::BELUM_DIBAYAR,
                $actualRemaining <= 0 => InvoiceStatus::LUNAS,
                default => InvoiceStatus::SEBAGIAN,
            };

            $storedPaid = round((float) $invoice->paid_amount, 2);
            $storedStatus = $invoice->invoice_status;

            $selisih = round($actualPaid - $storedPaid, 2);

            if ($selisih === 0.0 && $actualStatus->value === $storedStatus->value) {
                continue;
            }

            $bolehAutoFix = abs($selisih) <= $threshold;

            $rows[] = [
                $invoice->customer->customer_code ?? '—',
                mb_strimwidth((string) ($invoice->customer->full_name ?? '—'), 0, 24, '…'),
                $invoice->invoice_number,
                number_format($storedPaid, 0, ',', '.'),
                number_format($actualPaid, 0, ',', '.'),
                $storedStatus->value.' → '.$actualStatus->value,
                $bolehAutoFix ? 'auto-fix' : 'PERLU REVIEW MANUAL',
            ];

            if ($fix && $bolehAutoFix) {
                $invoice->recalculateFromPayments();
                $fixed++;
            } else {
                $needsReview++;
            }
        }

        if ($rows === []) {
            $this->line('Tidak ada temuan — semua invoice konsisten dengan payment-nya.');

            return self::SUCCESS;
        }

        $this->table(
            ['Kode', 'Nama', 'No. Invoice', 'paid_amount tersimpan', 'paid_amount aktual', 'Status tersimpan → aktual', 'Aksi'],
            $rows
        );

        $this->newLine();
        $this->line('Total temuan   : '.count($rows));
        $this->line('Diperbaiki     : '.($fix ? $fixed : 0).($fix ? '' : ' (jalankan ulang dengan --fix)'));
        $this->line('Perlu review manual (selisih > Rp'.number_format($threshold, 0, ',', '.').'): '.$needsReview);

        if ($needsReview > 0) {
            $this->warn('Ada selisih besar yang TIDAK di-auto-fix. Periksa manual — bisa jadi gejala input ganda atau kesalahan sistemik, bukan sekadar drift kecil.');
        }

        return self::SUCCESS;
    }
}
