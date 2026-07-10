<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\CustomerService;

class FixLegacyBillingBatch2Command extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fix-legacy-billing-batch2';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'BATCH 2: Perbaikan label LEGACY pada nomor tagihan & pembayaran serta penyesuaian biaya bulanan rutin (tanpa biaya diluar standar)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Mulai pembersihan data BATCH 2...');

        DB::transaction(function () {
            // 1. Perbaiki Nomor Invoice (Hapus prefix INV-LEGACY-)
            $invoices = Invoice::where('invoice_number', 'like', 'INV-LEGACY-%')->get();
            $invCount = 0;
            foreach ($invoices as $inv) {
                $newNumber = preg_replace('/^INV-LEGACY-/', 'INV-', $inv->invoice_number);
                // Cek agar tidak duplikat dengan nomor yang sudah ada
                if (!Invoice::where('invoice_number', $newNumber)->where('id', '!=', $inv->id)->exists()) {
                    $inv->updateQuietly(['invoice_number' => $newNumber]);
                    $invCount++;
                }
            }
            $this->info("Berhasil memperbarui {$invCount} nomor tagihan migrasi (hapus kata LEGACY).");

            // 2. Perbaiki Nomor Payment (Hapus prefix PAY-LEGACY-)
            $payments = Payment::where('payment_number', 'like', 'PAY-LEGACY-%')->get();
            $payCount = 0;
            foreach ($payments as $pay) {
                $newNumber = preg_replace('/^PAY-LEGACY-/', 'PAY-', $pay->payment_number);
                if (!Payment::where('payment_number', $newNumber)->where('id', '!=', $pay->id)->exists()) {
                    $pay->updateQuietly(['payment_number' => $newNumber]);
                    $payCount++;
                }
            }
            $this->info("Berhasil memperbarui {$payCount} nomor pembayaran migrasi (hapus kata LEGACY).");

            // 3. Penyesuaian total_monthly_bill pada CustomerService
            // Biaya di luar standar (other_fee) seharusnya hanya untuk awal, tidak ikut bulanan rutin
            $services = CustomerService::whereNotNull('other_fee')->where('other_fee', '>', 0)->get();
            $srvCount = 0;
            foreach ($services as $srv) {
                $monthlyPrice = (float)$srv->monthly_price;
                $discount = (float)($srv->discount ?? 0);
                $ppnPercent = (float)($srv->ppn ?? 0);

                $discountedPrice = max(0, $monthlyPrice - $discount);
                $correctTotalMonthlyBill = round($discountedPrice * (1 + ($ppnPercent / 100)), 2);

                if ((float)$srv->total_monthly_bill != $correctTotalMonthlyBill) {
                    $srv->updateQuietly(['total_monthly_bill' => $correctTotalMonthlyBill]);
                    $srvCount++;
                }
            }
            $this->info("Berhasil mengoreksi biaya bulanan rutin pada {$srvCount} layanan pelanggan (tanpa memasukkan other_fee).");
        });

        $this->info('Pembersihan data BATCH 2 selesai dengan sukses!');
        return 0;
    }
}
