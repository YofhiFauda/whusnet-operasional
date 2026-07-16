<?php

namespace App\Console\Commands;

use App\Enums\TaskStatus;
use App\Models\FopTask;
use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ResetCancelledFopTasks extends Command
{
    protected $signature = 'fop:reset-cancelled-tasks';
    protected $description = 'Reset FOP tasks with status dibatalkan back to in_progress on the next day';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = Carbon::today();

        // Query tasks that are Cancelled and the cancel time is before today
        $tasksToReset = FopTask::where('status', TaskStatus::DIBATALKAN->value)
            ->where('cancelled_at', '<', $today)
            ->get();

        if ($tasksToReset->isEmpty()) {
            $this->info('Tidak ada task FOP Cancelled yang perlu di-reset hari ini.');
            return 0;
        }

        $count = 0;
        foreach ($tasksToReset as $task) {
            DB::transaction(function () use ($task, &$count) {
                $oldValues = $task->load('technicians')->toArray();

                $task->status = TaskStatus::IN_PROGRESS->value;
                $task->cancelled_at = null;
                $task->pending_reason = null;
                $task->client_request_date = null;
                $task->save();

                $newValues = $task->load('technicians')->toArray();

                // Log activity to audit log
                if (class_exists(AuditLog::class)) {
                    AuditLog::log($task, 'update', $oldValues, $newValues);
                }

                $count++;
            });

            $this->info("Task FOP {$task->task_number} berhasil di-reset kembali ke status 'in_progress'.");
        }

        $this->info("Berhasil me-reset {$count} task FOP.");
        return 0;
    }
}
