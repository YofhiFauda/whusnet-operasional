<?php

namespace App\Console\Commands;

use App\Models\InfrastructureReconciliationRun;
use App\Services\InfrastructureReconciliationService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class InfrastructureReconcileCommand extends Command
{
    protected $signature = 'infrastructure:reconcile {--period= : Periode tertutup YYYY-MM (default bulan lalu)} {--observe : Snapshot harian pada waktu aktual}';

    protected $description = 'Mencatat snapshot rekonsiliasi; bukti siklus harus melalui scheduler';

    public function handle(InfrastructureReconciliationService $reconciliation): int
    {
        if ($this->option('observe') && $this->option('period')) {
            $this->error('Observasi memakai waktu aktual dan tidak menerima --period.');

            return self::FAILURE;
        }
        try {
            $rows = $this->option('observe') ? $reconciliation->observe() : $reconciliation->run(
                $this->option('period') ?: now()->subMonthNoOverflow()->format('Y-m'));
        } catch (ValidationException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->table(['POP/Cabang', 'Lama', 'Proyeksi', 'Selisih', 'Nol?'], $rows->map(fn (InfrastructureReconciliationRun $row): array => [
            $row->pop?->name ?? '(tanpa Cabang)', json_encode($row->legacy_metrics),
            json_encode($row->projection_metrics), json_encode($row->diff), $row->is_zero ? 'ya' : 'TIDAK',
        ]));
        $this->line('Snapshot manual tersimpan; snapshot manual tidak melengkapi bukti siklus terjadwal.');

        return self::SUCCESS;
    }
}
