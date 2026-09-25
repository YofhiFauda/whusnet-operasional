<?php

namespace App\Console\Commands;

use App\Enums\InvoiceType;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\CustomerBalanceService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('billing:apply-balance {--customer= : Customer ID, batasi ke satu pelanggan} {--period= : Batasi ringkasan output ke billing_period ini (YYYY-MM) — tidak membatasi invoice yang disentuh} {--dry-run : Tampilkan yang AKAN terjadi tanpa menulis apa pun}')]
#[Description('Auto-pakai saldo pelanggan ke tagihan BULANAN terbuka (FIFO) — catch-up manual untuk saldo yang masuk setelah tagihan terbit (ADHOC-92).')]
class ApplyCustomerBalanceCommand extends Command
{
    /**
     * KEPUTUSAN 2026-09-21: auto-pay utama sudah dipicu otomatis dari
     * GenerateMonthlyInvoicesCommand begitu invoice BULANAN terbit. Command
     * ini untuk kasus PINGGIRAN (asumsi rancangan §3.1 poin 3) — saldo yang
     * masuk SETELAH tagihan bulan itu sudah terbit — dan untuk audit
     * `--dry-run` sebelum go-live (§4.5, risiko utama: credit lama langsung
     * terpakai di generator pertama setelah deploy).
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $customerId = $this->option('customer');
        $period = trim((string) $this->option('period'));

        $query = Customer::query()->whereHas('customerBalanceMutations');

        if ($customerId) {
            $query->whereKey($customerId);
        }

        $customers = $query->get();

        $totalDipakai = 0.0;
        $totalPelanggan = 0;
        $service = app(CustomerBalanceService::class);

        foreach ($customers as $customer) {
            $saldo = $service->balance($customer);

            if ($saldo <= 0) {
                continue;
            }

            if ($dryRun) {
                $invoices = $customer->invoices()
                    ->where('invoice_type', InvoiceType::BULANAN->value)
                    ->whereIn('invoice_status', Invoice::OUTSTANDING_STATUSES)
                    ->when($period !== '', fn ($q) => $q->where('billing_period', $period))
                    ->orderBy('billing_period')
                    ->get(['id', 'invoice_number', 'billing_period', 'remaining_amount']);

                if ($invoices->isEmpty()) {
                    continue;
                }

                $this->line("Pelanggan {$customer->customer_code} — saldo Rp ".number_format($saldo, 0, ',', '.').':');

                foreach ($invoices as $invoice) {
                    $this->line("  {$invoice->invoice_number} ({$invoice->billing_period}) sisa Rp ".number_format((float) $invoice->remaining_amount, 0, ',', '.'));
                }

                $totalPelanggan++;

                continue;
            }

            $payments = $service->applyToOpenInvoices($customer);

            if ($payments !== []) {
                $totalPelanggan++;
                $totalDipakai += array_sum(array_map(fn ($p) => (float) $p->amount, $payments));
                $this->info("Pelanggan {$customer->customer_code}: ".count($payments).' tagihan terbayar dari saldo.');
            }
        }

        if ($dryRun) {
            $this->info("Dry-run selesai: {$totalPelanggan} pelanggan punya saldo yang BISA dipakai ke tagihan terbuka. Jalankan tanpa --dry-run untuk eksekusi.");
        } else {
            $this->info("Selesai: {$totalPelanggan} pelanggan, total Rp ".number_format($totalDipakai, 0, ',', '.').' dipakai dari saldo.');
        }

        return self::SUCCESS;
    }
}
