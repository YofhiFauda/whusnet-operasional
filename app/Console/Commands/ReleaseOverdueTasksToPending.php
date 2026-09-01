<?php

namespace App\Console\Commands;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Console\Command;

/**
 * Task `terjadwal`/`in_progress` yang `scheduled_at`-nya sudah lewat hari ini
 * tapi belum `selesai` otomatis di-pending (lepas tim, balik ke antrian FOP)
 * tiap tengah malam. Sebelum command ini, task ketinggalan diam-diam nyangkut
 * di status lama selamanya — gak muncul lagi di papan FOP buat di-assign
 * ulang, dan teknisi yang timnya masih nempel keliru masih dianggap "sibuk"
 * (guard di `TaskService::start()`) walau task-nya sudah gak relevan.
 *
 * BUKAN pengulangan `fop:reset-cancelled-tasks` yang dihapus 2026-08-13 —
 * itu MENGHIDUPKAN task `dibatalkan` (keputusan final manusia) balik jadi
 * `in_progress` diam-diam. Command ini arahnya kebalikan: task yang MASIH
 * aktif (belum final) tapi kelewat tanggal dipensiunkan ke `pending` —
 * status yang sengaja dirancang buat "butuh keputusan ulang", bukan
 * menimpa keputusan yang sudah final.
 *
 * Lewat `TaskService::releaseTeamAndSetPending()` — jalur SAMA PERSIS yang
 * dipakai `TaskController::pending()`/`reschedule()` (FOP/teknisi manual),
 * jadi sinkron ke FopTask + rebuild tim jadwal otomatis ikut kebawa,
 * bukan reimplementasi kedua yang gampang menyimpang.
 */
class ReleaseOverdueTasksToPending extends Command
{
    protected $signature = 'tasks:auto-pending-overdue';

    protected $description = 'Auto-pending task terjadwal/in_progress yang tanggal jadwalnya sudah lewat tapi belum selesai';

    public function handle(TaskService $taskService): int
    {
        $tasks = Task::query()
            ->whereIn('status', [TaskStatus::TERJADWAL->value, TaskStatus::IN_PROGRESS->value])
            ->whereNotNull('scheduled_at')
            ->whereDate('scheduled_at', '<', now()->toDateString())
            ->get();

        if ($tasks->isEmpty()) {
            $this->info('Tidak ada task overdue yang perlu di-pending.');

            return self::SUCCESS;
        }

        foreach ($tasks as $task) {
            $reason = 'Otomatis: melewati tanggal jadwal ('.$task->scheduled_at->toDateString().') tanpa selesai.';

            $taskService->releaseTeamAndSetPending($task, $reason, 'auto_pending_overdue');
        }

        $this->info("Berhasil auto-pending {$tasks->count()} task overdue.");

        return self::SUCCESS;
    }
}
