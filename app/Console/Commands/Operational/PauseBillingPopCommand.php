<?php

namespace App\Console\Commands\Operational;

use App\Console\Commands\Scheduled\GenerateMonthlyInvoicesCommand;
use App\Models\Pop;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('billing:pause-pop
                {--pop-id= : ID Pop yang mau ditahan dari generate tagihan bulanan}
                {--pop-name= : Nama Pop, alternatif --pop-id}
                {--note= : Alasan opsional, mis. "data paket sedang dibenarkan"}')]
#[Description('Tahan satu Pop dari billing:generate-monthly-invoices (cron maupun manual) sampai dicabut dengan billing:resume-pop — buat dipakai pas data pelanggan/paket Pop itu sedang dibenarkan massal dan belum rapi.')]
class PauseBillingPopCommand extends Command
{
    public function handle(): int
    {
        $pop = $this->resolvePop();

        if (! $pop) {
            $this->error('Pop tidak ditemukan. Isi --pop-id atau --pop-name.');

            return self::FAILURE;
        }

        $pausedPops = Cache::get(GenerateMonthlyInvoicesCommand::PAUSED_POPS_CACHE_KEY, []);

        $pausedPops[$pop->id] = [
            'pop_name' => $pop->name,
            'note' => trim((string) $this->option('note')) ?: null,
            'paused_at' => now()->toDateTimeString(),
        ];

        Cache::forever(GenerateMonthlyInvoicesCommand::PAUSED_POPS_CACHE_KEY, $pausedPops);

        $this->info("Pop {$pop->name} (id={$pop->id}) ditahan dari generate tagihan bulanan. Cabut dengan: php artisan billing:resume-pop --pop-id={$pop->id}");

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
