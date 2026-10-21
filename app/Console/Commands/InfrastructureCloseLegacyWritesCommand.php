<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\InfrastructureReconciliationService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use JsonException;

class InfrastructureCloseLegacyWritesCommand extends Command
{
    protected $signature = 'infrastructure:close-legacy-writes {--period= : Periode bukti YYYY-MM} {--actor= : ID operator penanggung jawab} {--evidence= : File JSON tujuh bukti bernomor 1 sampai 7, masing-masing status complete dan reference}';

    protected $description = 'Menutup tulis legacy setelah tujuh bukti cutover lengkap, dengan audit permanen';

    public function handle(InfrastructureReconciliationService $reconciliation): int
    {
        $actor = User::query()->find($this->option('actor'));
        $path = $this->option('evidence');
        if (! $actor || ! is_string($path) || ! is_file($path) || ! is_readable($path) || ! $this->option('period')) {
            $this->error('--period, --actor valid, dan file --evidence wajib diberikan.');

            return self::FAILURE;
        }
        try {
            $evidence = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($evidence)) {
                $this->error('Bukti harus berupa objek JSON bernomor 1 sampai 7.');

                return self::FAILURE;
            }
            $reconciliation->closeLegacyWrites($this->option('period'), $evidence, $actor);
        } catch (ValidationException|JsonException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->info('Jalur tulis legacy ditutup; tujuh bukti dan operator tersimpan di audit.');

        return self::SUCCESS;
    }
}
