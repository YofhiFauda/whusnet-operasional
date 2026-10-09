<?php

namespace App\Console\Commands;

use App\Services\CollectorMonthlyReportService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

#[Signature('billing:close-period {--period= : Periode YYYY-MM (default: bulan lalu)}')]
#[Description('Tutup buku otomatis: bekukan angka Laporan Bulanan Admin periode lalu untuk semua POP pusat/cabang.')]
class ClosePeriodCommand extends Command
{
    /**
     * Pengganti tombol "Tutup Periode" manual. Kunci periode sendiri sudah
     * berlaku sejak bulan berganti (`BookPeriod::isLocked()`); command ini
     * cuma membekukan angka laporannya. `--period` untuk menambal bulan yang
     * terlewat kalau scheduler sempat mati — aman diulang (idempoten).
     */
    public function handle(CollectorMonthlyReportService $report): int
    {
        $period = (string) ($this->option('period') ?: now()->subMonthNoOverflow()->format('Y-m'));

        try {
            $closed = $report->closePeriod($period);
        } catch (ValidationException $e) {
            $this->error(collect($e->errors())->flatten()->first());

            return self::FAILURE;
        }

        $this->info("Periode {$period} dibekukan untuk {$closed} POP.");

        return self::SUCCESS;
    }
}
