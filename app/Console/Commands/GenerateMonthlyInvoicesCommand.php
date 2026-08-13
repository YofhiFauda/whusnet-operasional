<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\WorkflowTransition;
use App\Models\Customer;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('billing:generate-monthly-invoices {--period= : Billing period YYYY-MM (default: bulan berjalan)} {--dry-run : List what would be created without inserting anything}')]
#[Description('Generate flat BULANAN invoices for active customers for a billing period, skipping anyone who already has one.')]
class GenerateMonthlyInvoicesCommand extends Command
{
    /**
     * Execute the console command.
     *
     * Recommendation #4 from docs/RENCANA_PENCEGAHAN_DAN_UX_TAGIHAN_PEMBAYARAN.md:
     * remove the human factor from monthly billing amounts by generating them
     * from customer_service.monthly_price instead of manual per-admin entry.
     * Idempotent: re-running for the same month never creates a second BULANAN
     * invoice for a customer (checked here, and backstopped by InvoiceObserver).
     */
    public function handle(): int
    {
        // Tiap `migrate:fresh` + import legacy meninggalkan lubang antara bulan
        // terakhir yang ada di dump legacy dan bulan berjalan. Tanpa opsi ini
        // lubang itu permanen: command dipatok `now()`, jadi bulan yang sudah
        // lewat tidak akan pernah punya tagihan sekalipun cron akhirnya hidup
        // lagi. Idempotensinya dijaga Invoice::hasActiveSubscriptionInvoiceForPeriod()
        // yang sama, jadi menambal periode lama aman diulang.
        $billingPeriod = $this->resolveBillingPeriod();

        if ($billingPeriod === null) {
            $this->error('Format --period harus YYYY-MM, contoh: --period=2026-07.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        // Enum, bukan string literal — nama status pelanggan yang berubah tanpa
        // ubah kode ini dulu akan bikin generator diam-diam skip semua
        // pelanggan (tanpa tagihan terbit, tanpa error) sampai ada yang sadar
        // pelanggan tak dapat tagihan (docs/plan/analisa-billing-tagihan-
        // pembayaran-kolektor.md §A-7 #4).
        $customers = Customer::with('customerService')
            ->whereIn('status', [WorkflowTransition::ACTIVE->value, WorkflowTransition::SUSPENDED->value])
            ->whereHas('customerService')
            ->get();

        $created = 0;
        $skipped = 0;
        $failed = 0;

        $periodStart = Carbon::parse($billingPeriod.'-01');

        foreach ($customers as $customer) {
            $service = $customer->customerService;

            if (! $service || (float) $service->monthly_price <= 0) {
                $skipped++;

                continue;
            }

            // Activation month is already billed by the AWAL invoice (prorate +
            // biaya lain) created at CustomerVerificationController::finalVerify.
            // Generating a flat BULANAN here too would double-bill that same
            // period — recurring billing only starts the month AFTER activation.
            if ($service->activation_date && $service->activation_date->isSameMonth($periodStart)) {
                $skipped++;

                continue;
            }

            // Pertanyaannya "sudah ada tagihan LANGGANAN untuk periode ini?",
            // bukan "sudah ada tagihan BULANAN?". Dulu dicek per invoice_type,
            // sehingga invoice AWAL bulan aktivasi tidak terlihat di sini dan
            // satu-satunya yang menahan dobel adalah pengecekan activation_date
            // di atas — satu kolom, tanpa cadangan. Kalau kolom itu salah isi
            // (dulu terisi registration_date), pelanggan menerima AWAL + BULANAN
            // untuk periode yang sama.
            //
            // REAKTIVASI sengaja tidak dihitung: pelanggan yang disuspend lalu
            // aktif lagi di bulan yang sama memang boleh punya dua record.
            // Invoice BATAL juga tidak dihitung, kalau tidak tagihan yang sudah
            // dibatalkan akan memblokir penerbitan penggantinya. Aturan ini
            // sama persis dengan InvoiceObserver::rejectSecondSubscriptionInvoice
            // — satu method bersama, lihat Invoice::hasActiveSubscriptionInvoiceForPeriod()
            // (docs/plan/analisa-billing-tagihan-pembayaran-kolektor.md §A-7 #3).
            $alreadyExists = Invoice::hasActiveSubscriptionInvoiceForPeriod($customer->id, $billingPeriod);

            if ($alreadyExists) {
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $this->line("Would create BULANAN invoice for {$customer->customer_code} ({$billingPeriod}, Rp ".number_format((float) $service->monthly_price, 0, ',', '.').')');
                $created++;

                continue;
            }

            try {
                DB::transaction(function () use ($customer, $service, $billingPeriod, $periodStart) {
                    $subtotal = (float) $service->monthly_price;
                    $discount = (float) ($service->discount ?? 0);
                    $ppnPercent = (float) ($service->ppn ?? 0);
                    $afterDiscount = max(0, $subtotal - $discount);
                    $ppnAmount = round($afterDiscount * ($ppnPercent / 100), 2);
                    $totalAmount = $afterDiscount + $ppnAmount;

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

                    Invoice::create([
                        'invoice_number' => sprintf('INV-%s-%04d', $periodCode, $nextSeq),
                        'invoice_type' => InvoiceType::BULANAN->value,
                        'customer_id' => $customer->id,
                        'pop_id' => $customer->pop_id,
                        'customer_service_id' => $service->id,
                        'internet_package_id' => $service->internet_package_id,
                        'billing_period' => $billingPeriod,
                        // Fixed calendar window: bill issued the 1st, due the 10th —
                        // not "run date + 10 days", which would drift if the
                        // scheduled command runs late or is triggered manually.
                        // `day(10)` states that rule directly. The previous
                        // `addDays(9)` only landed on the 10th because
                        // $periodStart happens to be the 1st — an offset that
                        // silently produces the wrong due date the moment anyone
                        // changes where $periodStart points.
                        'issue_date' => $periodStart->format('Y-m-d'),
                        'due_date' => $periodStart->copy()->day(10)->format('Y-m-d'),
                        'subtotal' => $subtotal,
                        'discount' => $discount,
                        'ppn' => $ppnPercent,
                        'total_amount' => $totalAmount,
                        'paid_amount' => 0,
                        'remaining_amount' => $totalAmount,
                        'invoice_status' => InvoiceStatus::BELUM_DIBAYAR->value,
                        'created_by' => null,
                    ]);
                });

                $created++;
            } catch (\Throwable $e) {
                $failed++;
                $this->error("Gagal generate invoice untuk {$customer->customer_code}: {$e->getMessage()}");
            }
        }

        $this->info("Periode {$billingPeriod}: ".($dryRun ? 'akan dibuat' : 'dibuat')." {$created}, dilewati {$skipped}, gagal {$failed}.");

        return self::SUCCESS;
    }

    /**
     * Periode tagihan yang mau digenerate, `null` kalau --period tidak valid.
     *
     * Sengaja divalidasi ketat (bukan Carbon::parse yang permisif): `--period=2026-7`
     * atau `--period=juli` akan menghasilkan billing_period yang tidak cocok dengan
     * format `Y-m` di seluruh sistem, dan tagihannya jadi tidak terlihat oleh
     * pengecekan dobel maupun daftar tagihan.
     */
    private function resolveBillingPeriod(): ?string
    {
        $period = trim((string) $this->option('period'));

        if ($period === '') {
            return now()->format('Y-m');
        }

        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period) !== 1) {
            return null;
        }

        return $period;
    }
}
