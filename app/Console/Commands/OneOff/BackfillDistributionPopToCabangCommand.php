<?php

namespace App\Console\Commands\OneOff;

use App\Models\AuditLog;
use App\Models\Distribution;
use App\Models\Pop;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('distributions:backfill-pop-to-cabang
    {--force : Benar-benar menulis. Tanpa ini command hanya mencetak daftar usulan}')]
#[Description('Betulkan distributions.pop_id yang terlanjur menunjuk Mini POP spesifik jadi Cabang induknya (ADHOC-312: satu Distribusi lazim dipakai bareng lebih dari satu Mini POP dalam Cabang yang sama). Default hanya mencetak usulan.')]
class BackfillDistributionPopToCabangCommand extends Command
{
    /**
     * Sebelum ADHOC-312, aturan hierarki (ADHOC-107 R6) memvalidasi
     * `distributions.pop_id === mini_pop_id` — satu Distribusi cuma sah
     * nempel ke SATU Mini POP. Laporan user (2026-11-23, Cabang Siman D1/D2
     * dipakai lintas Mini POP) membuktikan itu salah baca kenyataan lapangan:
     * Distribusi (ODP) adalah milik Cabang, dibagi bareng semua Mini POP di
     * bawahnya.
     *
     * Command ini membetulkan baris `distributions` lama yang `pop_id`-nya
     * masih menunjuk Mini POP (`pops.type = 'mini_pop'`) jadi Cabang induknya
     * (`pops.parent_id`). Distribusi yang `pop_id`-nya SUDAH menunjuk Cabang
     * (dibuat manual oleh admin yang menebak benar lebih dulu) tidak disentuh.
     *
     * Tidak menyentuh `customers.mini_pop_id`/`distribution_id` sama sekali —
     * assignment pelanggan tetap sah selama Distribusi tetap ada, cuma
     * `distributions.pop_id`-nya yang berubah makna.
     *
     * Default dry-run. `--force` baru menulis, dan setiap perubahan masuk
     * audit log.
     */
    public function handle(): int
    {
        $write = (bool) $this->option('force');

        $this->info($write
            ? 'Backfill distributions.pop_id (Mini POP -> Cabang) — MODE TULIS'
            : 'Backfill distributions.pop_id (Mini POP -> Cabang) — mode daftar saja (tambahkan --force untuk menulis)');
        $this->newLine();

        $distributions = Distribution::query()
            ->whereHas('pop', fn ($q) => $q->where('type', 'mini_pop'))
            ->with('pop')
            ->orderBy('code')
            ->get();

        if ($distributions->isEmpty()) {
            $this->line('Tidak ada Distribusi yang pop_id-nya masih menunjuk Mini POP.');

            return self::SUCCESS;
        }

        $cabangIds = $distributions->pluck('pop.parent_id')->filter()->unique()->values();
        $cabangById = Pop::whereIn('id', $cabangIds)->get()->keyBy('id');

        $rows = [];
        $akanDiubah = [];

        foreach ($distributions as $distribution) {
            $miniPop = $distribution->pop;
            $cabang = $cabangById->get($miniPop->parent_id);

            if (! $cabang) {
                $rows[] = [$distribution->code, $miniPop->name, '—', 'DILEWATI (Mini POP tanpa Cabang induk)'];

                continue;
            }

            $akanDiubah[] = [$distribution, $miniPop->id, $cabang->id];
            $rows[] = [$distribution->code, $miniPop->name, $cabang->name, $write ? 'DITULIS' : 'usulan'];
        }

        $this->table(['Kode Distribusi', 'Mini POP Lama', 'Cabang Baru', 'Status'], $rows);

        if ($write) {
            $this->tulis($akanDiubah);
        }

        $this->newLine();
        $this->line('Diperiksa   : '.$distributions->count());
        $this->line(($write ? 'Diubah      : ' : 'Akan diubah : ').count($akanDiubah));

        if (! $write) {
            $this->newLine();
            $this->warn('Belum ada yang ditulis. Periksa daftar di atas lalu jalankan ulang dengan --force.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<array{0: Distribution, 1: int, 2: int}>  $akanDiubah
     */
    private function tulis(array $akanDiubah): void
    {
        foreach ($akanDiubah as [$distribution, $miniPopIdLama, $cabangIdBaru]) {
            DB::transaction(function () use ($distribution, $miniPopIdLama, $cabangIdBaru) {
                $distribution->update(['pop_id' => $cabangIdBaru]);

                AuditLog::create([
                    'user_id' => null,
                    'module' => 'Master Distribusi',
                    'action' => 'backfill_distribution_pop_to_cabang',
                    'auditable_type' => Distribution::class,
                    'auditable_id' => $distribution->id,
                    'old_values' => ['pop_id' => $miniPopIdLama],
                    'new_values' => ['pop_id' => $cabangIdBaru],
                    'created_at' => now(),
                ]);
            });
        }
    }
}
