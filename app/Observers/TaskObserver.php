<?php

namespace App\Observers;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\FopTask;
use App\Models\FopTaskStatusHistory;
use App\Models\Task;
use App\Models\TaskReport;

class TaskObserver
{
    /**
     * Sync status `Task` eksekusi teknisi ke `FopTask` (Task 9, direvisi
     * 2026-07-20 unifikasi enum — `FopTaskStatus` dihapus, `fop_tasks.status`
     * sekarang share vocab persis sama `TaskStatus`, jadi sync-nya CUMA copy
     * langsung, gak ada mapping bucket lagi). Nuansa granular ("Pending
     * (reschedule)" vs "Lapor Nanti" vs "Pending (FOP)", "Selesai" vs "Selesai
     * (perlu review)") tetap disimpan di `fop_task_status_history.to_status`
     * (kolom string bebas) sebagai label histori/badge overlay — BUKAN
     * mengubah nilai `status` utama, biar dashboard 6-kolom (docs/
     * project_status_label_unifikasi.md) tetap 1:1 sama lifecycle Task asli.
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
        if ($fopTask->status === TaskStatus::DIBATALKAN) {
            return;
        }

        [$targetStatus, $historyLabel] = $this->resolveTarget($task);

        $fromStatus = $fopTask->status instanceof TaskStatus ? $fopTask->status->value : $fopTask->status;

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

        if ($task->status === TaskStatus::PENDING) {
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
     * Mapping kondisi `Task` eksekusi → [`TaskStatus` target (dicopy apa
     * adanya ke `FopTask.status`), label histori granular]. Sesuai tabel
     * "Mapping status teknisi → status FopTask" di
     * docs/fop-task/analisa-auto-team.md § 10 (direvisi 2026-07-20).
     *
     * Status utama sekarang 1:1 sama lifecycle `Task` — nuansa "Selesai (perlu
     * review)" vs "Selesai (approved)" vs "Selesai (ditolak verifikasi)" CUMA
     * beda di label histori/badge overlay (`fop_task_status_history.to_status`
     * + `FopTask::verificationStatus()`), BUKAN status yang beda — tiket yang
     * laporannya masih ditinjau tetap tampil di kolom "Selesai" dashboard,
     * dikasih badge "Perlu Review" di atasnya (view layer), bukan didemosikan
     * balik ke kolom "Sedang Dikerjakan".
     */
    private function resolveTarget(Task $task): array
    {
        $isCustomerDecisionTask = in_array($task->task_type, [TaskType::SURVEY, TaskType::PEMASANGAN], true);

        $historyLabel = match (true) {
            $task->status === TaskStatus::PENDING && $task->report_deferred => 'lapor_nanti',
            $task->status === TaskStatus::PENDING && !$task->report_deferred => 'pending_fop',
            $task->status === TaskStatus::SELESAI && $task->fop_review_status === 'approved' => 'selesai',
            $task->status === TaskStatus::SELESAI && $isCustomerDecisionTask && $task->fop_review_status === 'rejected' => 'selesai_ditolak_verifikasi',
            $task->status === TaskStatus::SELESAI && $isCustomerDecisionTask => 'selesai_menunggu_verifikasi',
            $task->status === TaskStatus::SELESAI => 'selesai_menunggu_verifikasi',
            default => $task->status->value,
        };

        return [$task->status, $historyLabel];
    }
}
