<?php

namespace App\Console\Commands;

use App\Enums\NotificationType;
use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Models\FopTask;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Cek FopTask yang Handling SLA-nya (kecepatan FOP respons/assign — CLAUDE.md
 * § SLA, BUKAN SLA Pengerjaan teknisi) udah kelewat, notif role `fop` di
 * POP-nya. Sebelumnya nol alert — dashboard NOC/FOP murni pull (docs/plan/
 * analisa-status-implementasi-notifikasi.md §5).
 *
 * Dedup lewat `sla_breach_notified_at` — sekali notif per breach, gak diulang
 * tiap kali command ini jalan. Reuse `FopTask::slaDeadline()` (bukan hitung
 * ulang dari `handling_sla_hours`) — itu SATU-SATUNYA sumber kebenaran
 * deadline yang sama dipakai badge countdown FOP dashboard, sengaja gak
 * diduplikasi biar dua tempat gak diam-diam menyimpang.
 */
class CheckFopTaskSlaBreach extends Command
{
    protected $signature = 'fop-tasks:check-sla-breach';

    protected $description = 'Notifikasi role FOP untuk FopTask yang Handling SLA-nya sudah lewat deadline';

    public function handle(): int
    {
        $candidates = FopTask::query()
            ->whereNotIn('status', [TaskStatus::SELESAI->value, TaskStatus::DIBATALKAN->value])
            ->whereNull('sla_breach_notified_at')
            ->with(['task', 'customer.tasks', 'pop'])
            ->get();

        $breached = $candidates->filter(fn (FopTask $fopTask) => now()->greaterThan($fopTask->slaDeadline()));

        if ($breached->isEmpty()) {
            $this->info('Tidak ada FopTask yang SLA-nya breach.');

            return self::SUCCESS;
        }

        $usersByPop = [];

        foreach ($breached as $fopTask) {
            if (! $fopTask->pop_id) {
                continue;
            }

            $usersByPop[$fopTask->pop_id] ??= $this->usersWithRoleInPop('fop', $fopTask->pop_id);

            foreach ($usersByPop[$fopTask->pop_id] as $user) {
                $user->notify(new AppNotification(
                    title: 'SLA Handling Terlewat: '.$fopTask->task_number,
                    message: "{$fopTask->tugas} — batas waktu respons/assign sudah lewat sejak {$fopTask->slaDeadline()->diffForHumans()}.",
                    actionUrl: route('fop-tasks.index'),
                    type: NotificationType::WARNING
                ));
            }

            $fopTask->update(['sla_breach_notified_at' => now()]);
        }

        $this->info("Berhasil notif {$breached->count()} FopTask yang SLA-nya breach.");

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, User>
     */
    private function usersWithRoleInPop(string $roleCode, int $popId): Collection
    {
        return User::whereHas('role', fn ($q) => $q->where('code', $roleCode))
            ->where(function ($query) use ($popId) {
                $query->whereHas('roleScopes', fn ($q) => $q->where('scope_type', ScopeType::ALL_POP->value))
                    ->orWhereHas('roleScopes', fn ($q) => $q->whereIn('scope_type', [ScopeType::SELECTED_POP->value, ScopeType::POP_TREE->value])
                        ->whereHas('targets', fn ($t) => $t->where('pop_id', $popId))
                    );
            })
            ->get();
    }
}
