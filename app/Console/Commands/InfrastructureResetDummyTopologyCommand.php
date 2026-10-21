<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\InfrastructureDummyResetService;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Reset topologi dummy yang terverifikasi (§16.4). Default dry-run; --force
 * untuk benar-benar menghapus. Ditolak otomatis bila node menyentuh data
 * nyata (pelanggan, stok, ledger, histori).
 */
class InfrastructureResetDummyTopologyCommand extends Command
{
    protected $signature = 'infrastructure:reset-dummy-topology {codes* : Kode node dummy} {--operator= : ID user operator} {--force : Eksekusi (tanpa ini hanya dry-run)}';

    protected $description = 'Reset node topologi dummy yang terdaftar eksplisit (menolak bila menyentuh data nyata)';

    public function handle(InfrastructureDummyResetService $reset): int
    {
        $operator = User::findOrFail((int) $this->option('operator'));

        try {
            $plan = $reset->reset($this->argument('codes'), $operator, dryRun: ! $this->option('force'));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(($this->option('force') ? 'Direset: ' : 'Dry-run, akan direset: ').implode(', ', $plan['nodes'])
            ." (slot {$plan['slots']}, port {$plan['ports']})");

        return self::SUCCESS;
    }
}
