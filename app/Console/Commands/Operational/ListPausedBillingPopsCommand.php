<?php

namespace App\Console\Commands\Operational;

use App\Console\Commands\Scheduled\GenerateMonthlyInvoicesCommand;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('billing:paused-pops')]
#[Description('Daftar Pop yang sedang ditahan dari billing:generate-monthly-invoices lewat billing:pause-pop.')]
class ListPausedBillingPopsCommand extends Command
{
    public function handle(): int
    {
        $pausedPops = Cache::get(GenerateMonthlyInvoicesCommand::PAUSED_POPS_CACHE_KEY, []);

        if ($pausedPops === []) {
            $this->info('Tidak ada Pop yang di-pause.');

            return self::SUCCESS;
        }

        $this->table(
            ['Pop ID', 'Nama', 'Sejak', 'Catatan'],
            collect($pausedPops)->map(fn (array $entry, int $popId) => [
                $popId,
                $entry['pop_name'] ?? '-',
                $entry['paused_at'] ?? '-',
                $entry['note'] ?? '-',
            ])->values()->all(),
        );

        return self::SUCCESS;
    }
}
