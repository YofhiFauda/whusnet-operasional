<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\CustomerBalanceService;
use Illuminate\Console\Command;

/**
 * Konversi `overpay_amount` lama (catatan informatif, tak pernah bisa
 * dipakai) jadi kredit AKTIF di `customer_balance_mutations`.
 *
 * SENGAJA manual, bukan dijalankan otomatis dari migration: overpay lama
 * mungkin sudah "dianggap selesai" di luar sistem (dikembalikan tunai,
 * dianggap hangus) — backfill buta bisa melahirkan saldo phantom. Jalankan
 * `--dry-run` dulu, review daftarnya, baru eksekusi tanpa flag.
 */
class BackfillCustomerBalanceFromOverpay extends Command
{
    protected $signature = 'payments:backfill-customer-balance {--dry-run : Tampilkan daftar tanpa menulis apa pun}';

    protected $description = 'Backfill saldo pelanggan dari overpay_amount payment lama yang belum punya mutasi ledger';

    public function handle(CustomerBalanceService $balances): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $candidates = Payment::query()
            ->where('payment_status', PaymentStatus::VALID->value)
            ->where('overpay_amount', '>', 0)
            ->whereDoesntHave('balanceMutations')
            ->with('customer')
            ->orderBy('payment_date')
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('Tidak ada payment overpay yang perlu di-backfill.');

            return self::SUCCESS;
        }

        $this->table(
            ['Payment', 'Tanggal', 'Pelanggan', 'Overpay'],
            $candidates->map(fn (Payment $p) => [
                $p->payment_number,
                $p->payment_date?->toDateString(),
                $p->customer?->full_name ?? "customer_id #{$p->customer_id}",
                number_format((float) $p->overpay_amount, 2),
            ])
        );

        if ($dryRun) {
            $this->warn("{$candidates->count()} payment akan di-backfill. Jalankan tanpa --dry-run untuk eksekusi.");

            return self::SUCCESS;
        }

        if (! $this->confirm("Backfill {$candidates->count()} payment di atas jadi saldo aktif pelanggan?")) {
            $this->info('Dibatalkan.');

            return self::SUCCESS;
        }

        $created = 0;

        foreach ($candidates as $payment) {
            if (! $payment->customer) {
                $this->warn("Dilewati: {$payment->payment_number} tak terhubung pelanggan mana pun.");

                continue;
            }

            $balances->credit(
                $payment->customer,
                (float) $payment->overpay_amount,
                $payment,
                "Backfill saldo dari overpay_amount lama ({$payment->payment_number})"
            );

            $created++;
        }

        $this->info("Selesai — {$created} mutasi kredit dibuat.");

        return self::SUCCESS;
    }
}
