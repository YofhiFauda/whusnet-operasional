<?php

namespace App\Console\Commands\Operational;

use App\Console\Commands\Scheduled\GenerateMonthlyInvoicesCommand;
use App\Models\Pop;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('billing:resume-pop
                {--pop-id= : ID Pop yang mau dicabut dari pause}
                {--pop-name= : Nama Pop, alternatif --pop-id}')]
#[Description('Cabut pause billing:pause-pop — Pop ini ikut lagi di billing:generate-monthly-invoices (cron maupun manual).')]
class ResumeBillingPopCommand extends Command
{
    public function handle(): int
    {
        $pop = $this->resolvePop();

        if (! $pop) {
            $this->error('Pop tidak ditemukan. Isi --pop-id atau --pop-name.');

            return self::FAILURE;
        }

        $pausedPops = Cache::get(GenerateMonthlyInvoicesCommand::PAUSED_POPS_CACHE_KEY, []);

        if (! array_key_exists($pop->id, $pausedPops)) {
            $this->info("Pop {$pop->name} (id={$pop->id}) memang tidak sedang di-pause.");

            return self::SUCCESS;
        }

        unset($pausedPops[$pop->id]);
        Cache::forever(GenerateMonthlyInvoicesCommand::PAUSED_POPS_CACHE_KEY, $pausedPops);

        $this->info("Pop {$pop->name} (id={$pop->id}) dicabut dari pause — ikut lagi di generate tagihan bulanan berikutnya.");

        return self::SUCCESS;
    }

    private function resolvePop(): ?Pop
    {
        if ($this->option('pop-id')) {
            return Pop::find((int) $this->option('pop-id'));
        }

        if ($this->option('pop-name')) {
            return Pop::where('name', $this->option('pop-name'))->first();
        }

        return null;
    }
}
