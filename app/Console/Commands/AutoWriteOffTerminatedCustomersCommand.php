<?php

namespace App\Console\Commands;

use App\Services\TerminatedCustomerWriteOffService;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('billing:write-off-terminated {--dry-run : Hanya tampilkan hitungan, tanpa mengubah data/mengirim notifikasi} {--as-of= : Tanggal patokan Y-m-d (default: sekarang)}')]
#[Description('Hapus buku otomatis utang pelanggan putus yang masa tenggangnya (sisa bulan putus + 1 bulan) sudah habis.')]
class AutoWriteOffTerminatedCustomersCommand extends Command
{
    /**
     * Dijadwalkan tanggal 1 sesudah `billing:close-period` (routes/console.php).
     * Idempoten: invoice yang sudah `tak_tertagih` tidak ikut terpilih lagi,
     * jadi aman diulang. `--dry-run` untuk memeriksa sebelum dijalankan di
     * produksi.
     */
    public function handle(TerminatedCustomerWriteOffService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $asOf = $this->option('as-of') ? Carbon::parse((string) $this->option('as-of')) : now();

        try {
            $actor = $service->systemActor();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $customers = $service->eligibleCustomers($asOf);

        $customerCount = 0;
        $invoiceCount = 0;
        $skipped = 0;
        $total = 0.0;

        foreach ($customers as $customer) {
            $result = $service->writeOffCustomer($customer, $actor, $dryRun);

            $invoiceCount += $result['count'];
            $skipped += $result['skipped'];
            $total += $result['total'];

            if ($result['count'] > 0) {
                $customerCount++;
            }
        }

        $prefix = $dryRun ? '[DRY-RUN] ' : '';
        $this->info("{$prefix}{$invoiceCount} tagihan dari {$customerCount} pelanggan putus dihapus buku (total Rp ".number_format($total, 0, ',', '.').").{$this->skippedNote($skipped)}");

        return self::SUCCESS;
    }

    private function skippedNote(int $skipped): string
    {
        return $skipped > 0 ? " {$skipped} tagihan dilewati (bukan piutang)." : '';
    }
}
