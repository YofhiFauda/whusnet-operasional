<?php

namespace App\Observers;

use App\Enums\FopTaskStatus;
use App\Enums\TaskStatus;
use App\Models\FopTask;
use App\Models\FopTaskStatusHistory;
use App\Models\Task;
use App\Models\TaskReport;

class TaskObserver
{
    /**
     * Sync status `Task` eksekusi teknisi ke `FopTask` (Task 9, kebutuhan poin 9).
     * `fop_tasks.status` sengaja TETAP dalam 4 nilai enum existing (Proses/Pending/
     * Selesai/Cancel) — bedanya "Pending (reschedule)" vs "Lapor Nanti" vs "Pending
     * (FOP)" cuma granular di `fop_task_status_history.to_status` (kolom string
     * bebas), BUKAN nilai baru di `FopTaskStatus` enum. Nambah case baru di enum itu
     * akan mecahin banyak `whereIn('status', ['Proses', 'Pending'])` yang tersebar
     * di codebase (rebuildTeamsForDate, index(), dst) — di luar scope Task 9.
     */
    public function updated(Task $task): void
    {
        $this->syncTaskReport($task);

        if (!$task->wasChanged(['status', 'report_deferred', 'fop_review_status'])) {
            return;
        }

        $fopTask = FopTask::where('task_id', $task->id)->first();

        if (!$fopTask) {
            return;
        }

        // FOP yang udah manual Cancel-kan tiket (Task 12, jalur eksplisit yang
        // sengaja tetap manual) gak boleh ke-overwrite diam-diam oleh sync
        // otomatis ini kalau Task eksekusinya belakangan berubah status lagi.
        if ($fopTask->status === FopTaskStatus::CANCEL) {
            return;
        }

        [$targetStatus, $historyLabel] = $this->resolveTarget($task);

        if ($targetStatus === null) {
            return;
        }

        $fromStatus = $fopTask->status instanceof FopTaskStatus ? $fopTask->status->value : $fopTask->status;

        if ($fopTask->status !== $targetStatus) {
            $fopTask->status = $targetStatus;
            $fopTask->save();
        }

        FopTaskStatusHistory::create([
            'fop_task_id' => $fopTask->id,
            'from_status' => $fromStatus,
            'to_status'   => $historyLabel,
            'changed_by'  => auth()->id(),
            'changed_at'  => now(),
        ]);
    }

    /**
     * Catat siklus pengerjaan (Task 10, kebutuhan poin 10 — Riwayat + SLA
     * dual-cycle) ke `task_reports`. Independen dari sync `FopTask` di atas
     * (jalan duluan, gak nunggu/butuh `FopTask` terkait ada) — durasi kerja
     * teknisi harus tetap tercatat walau task itu gak (lagi) punya FopTask
     * terhubung. `total_duration_minutes` diakumulasi tiap siklus ditutup
     * (masuk Pending/Reschedule/Selesai), bukan cuma selisih timestamp
     * pertama-terakhir — supaya jeda reschedule/pending gak ikut kehitung
     * sebagai waktu kerja.
     */
    private function syncTaskReport(Task $task): void
    {
        if (!$task->wasChanged('status')) {
            return;
        }

        if ($task->status === TaskStatus::IN_PROGRESS) {
            $report = TaskReport::firstOrNew(['task_id' => $task->id]);

            if (!$report->exists) {
                $report->started_at = $task->started_at ?? now();
                $report->sla_target_minutes = $task->sla_minutes;
            } else {
                $report->resumed_at = now();
            }

            $report->save();

            return;
        }

        if (in_array($task->status, [TaskStatus::PENDING, TaskStatus::RESCHEDULE], true)) {
            $report = TaskReport::where('task_id', $task->id)->first();

            if (!$report) {
                return;
            }

            $this->accumulateCycle($report);
            $report->pending_at = now();
            $report->save();

            return;
        }

        if ($task->status === TaskStatus::SELESAI) {
            $report = TaskReport::where('task_id', $task->id)->first();

            if (!$report) {
                return;
            }

            $this->accumulateCycle($report);
            $report->completed_at = now();

            if ($report->sla_target_minutes !== null) {
                $report->sla_status = $report->total_duration_minutes <= $report->sla_target_minutes ? 'on_time' : 'over';
                $report->sla_overrun_minutes = max(0, $report->total_duration_minutes - $report->sla_target_minutes);
            }

            $report->save();
        }
    }

    /**
     * Tambahkan durasi siklus yang baru saja ditutup (dari `resumed_at` kalau
     * ini siklus ke-2+, atau `started_at` kalau siklus pertama) ke akumulator
     * `total_duration_minutes`.
     */
    private function accumulateCycle(TaskReport $report): void
    {
        $cycleStart = $report->resumed_at ?? $report->started_at;

        if ($cycleStart) {
            $report->total_duration_minutes += (int) $cycleStart->diffInMinutes(now());
        }
    }

    /**
     * Mapping kondisi `Task` eksekusi → [`FopTaskStatus` target, label histori
     * granular]. Sesuai tabel "Mapping status teknisi → status FopTask" di
     * docs/fop-task/analisa-auto-team.md § 10.
     *
     * @return array{0: FopTaskStatus|null, 1: string|null}
     */
    private function resolveTarget(Task $task): array
    {
        return match (true) {
            $task->status === TaskStatus::TERJADWAL => [FopTaskStatus::PROSES, 'proses'],
            $task->status === TaskStatus::IN_PROGRESS => [FopTaskStatus::PROSES, 'proses_dikerjakan'],
            $task->status === TaskStatus::RESCHEDULE => [FopTaskStatus::PENDING, 'pending_reschedule'],
            $task->status === TaskStatus::PENDING && $task->report_deferred => [FopTaskStatus::PENDING, 'lapor_nanti'],
            $task->status === TaskStatus::PENDING && !$task->report_deferred => [FopTaskStatus::PENDING, 'pending_fop'],
            $task->status === TaskStatus::SELESAI && $task->fop_review_status === 'approved' => [FopTaskStatus::SELESAI, 'selesai'],
            $task->status === TaskStatus::SELESAI => [FopTaskStatus::PROSES, 'proses_review'],
            $task->status === TaskStatus::DIBATALKAN => [FopTaskStatus::CANCEL, 'cancel'],
            default => [null, null],
        };
    }
}
