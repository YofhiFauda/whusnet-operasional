<?php

namespace App\Console\Commands;

use App\Models\InfrastructureReconciliationRun;
use App\Services\InfrastructureReconciliationService;
use Illuminate\Console\Command;

/**
 * Bukti cutover §16.4 #6 — dijadwalkan setelah tutup periode (routes/console.php).
 * Default periode = bulan sebelumnya (periode yang baru ditutup).
 */
class InfrastructureReconcileCommand extends Command
{
    protected $signature = 'infrastructure:reconcile {--period= : Periode YYYY-MM (default bulan lalu)}';

    protected $description = 'Rekonsiliasi metrik infrastruktur lama vs proyeksi hierarki baru per POP/Cabang';

    public function handle(InfrastructureReconciliationService $reconciliation): int
    {
        $period = $this->option('period') ?: now()->subMonthNoOverflow()->format('Y-m');
        $rows = $reconciliation->run($period);

        $this->table(['POP/Cabang', 'Lama', 'Proyeksi', 'Selisih', 'Nol?'], $rows->map(fn (InfrastructureReconciliationRun $row): array => [
            $row->pop?->name ?? '(tanpa Cabang)',
            json_encode($row->legacy_metrics),
            json_encode($row->projection_metrics),
            json_encode($row->diff),
            $row->is_zero ? 'ya' : 'TIDAK',
        ]));

        $this->line($reconciliation->zeroForPeriod($period)
            ? "Periode {$period}: selisih nol di semua POP/Cabang."
            : "Periode {$period}: ADA selisih — review sebelum menutup jalur tulis lama.");

        return self::SUCCESS;
    }
}
