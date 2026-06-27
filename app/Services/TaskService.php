<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Events\TaskCompleted;
use App\Events\TaskScheduled;
use App\Events\TaskStarted;
use App\Jobs\SendTaskNotificationJob;
use App\Models\Task;
use App\Models\TaskChecklist;
use App\Models\TaskChecklistTemplate;
use App\Models\TaskTeam;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TaskService
{
    private const MAX_ACTIVE_TASKS_PER_TEAM_PER_DAY = 4;

    // ─── Pembuatan Task ──────────────────────────────────────────

    /**
     * Buat task baru beserta tim dan checklist dari template.
     *
     * @param array $data   Validated data dari TaskRequest
     * @param User  $actor  User yang membuat task (FOP)
     * @return Task
     */
    public function create(array $data, User $actor): Task
    {
        return DB::transaction(function () use ($data, $actor) {
            $taskType = TaskType::from($data['task_type']);

            $memberIds = $data['team_member_ids'] ?? [];
            $scheduledAt = $data['scheduled_at'] ?? null;
            $status = (!empty($memberIds) && !empty($scheduledAt))
                ? TaskStatus::TERJADWAL->value
                : TaskStatus::PENDING->value;

            $task = Task::create([
                'task_number'       => $this->generateTaskNumber(),
                'customer_id'       => $data['customer_id'] ?? null,
                'pop_id'            => $data['pop_id'],
                'task_type'         => $taskType->value,
                'title'             => $data['title'],
                'description'       => $data['description'] ?? null,
                'status'            => $status,
                'scheduled_at'      => $scheduledAt,
                'fop_id'            => $actor->id,
                'sla_minutes'       => $taskType->slaMinutes(),
                'conflict_override' => (bool) ($data['conflict_override'] ?? false),
                'created_by'        => $actor->id,
                'updated_by'        => $actor->id,
            ]);

            // Simpan tim teknisi (1–3 orang)
            foreach ($memberIds as $index => $userId) {
                TaskTeam::create([
                    'task_id'      => $task->id,
                    'user_id'      => $userId,
                    'role_in_task' => $index === 0 ? 'lead' : 'teknisi',
                ]);
            }

            // Salin checklist dari template
            $templates = TaskChecklistTemplate::forType($taskType);
            foreach ($templates as $template) {
                TaskChecklist::create([
                    'task_id'     => $task->id,
                    'template_id' => $template->id,
                    'item'        => $template->item,
                    'is_required' => $template->is_required,
                    'sort_order'  => $template->sort_order,
                ]);
            }

            // Kirim notifikasi async ke semua anggota tim
            if (!empty($memberIds)) {
                $this->notifyTeam($task, 'Task baru dijadwalkan untuk Anda', 'created');
            }

            \App\Models\AuditLog::log($task, 'created', null, $task->toArray());

            return $task->refresh();
        });
    }

    /**
     * Update jadwal dan/atau tim task (guard: task.edit + task.schedule).
     */
    public function update(Task $task, array $data, User $actor): Task
    {
        return DB::transaction(function () use ($task, $data, $actor) {
            $oldValues = $task->toArray();

            $rescheduled = isset($data['scheduled_at'])
                && $task->scheduled_at?->ne(Carbon::parse($data['scheduled_at']));

            $task->update(array_merge($data, ['updated_by' => $actor->id]));

            // Update tim jika dikirim
            if (isset($data['team_member_ids'])) {
                $task->teamMembers()->delete();
                foreach ($data['team_member_ids'] as $index => $userId) {
                    TaskTeam::create([
                        'task_id'      => $task->id,
                        'user_id'      => $userId,
                        'role_in_task' => $index === 0 ? 'lead' : 'teknisi',
                    ]);
                }
            }

            if ($rescheduled) {
                $this->notifyTeam($task, "Jadwal task diubah ke {$task->scheduled_at->format('d M Y H:i')}", 'rescheduled');
            }

            \App\Models\AuditLog::log($task, 'updated', $oldValues, $task->toArray());

            return $task->refresh();
        });
    }

    /**
     * Jadwalkan tiket dari antrean (status pending → terjadwal).
     */
    public function scheduleTask(Task $task, array $data, User $actor): Task
    {
        abort_unless(
            $task->status === TaskStatus::PENDING,
            422,
            'Hanya tiket dalam antrean (Pending) yang dapat dijadwalkan.'
        );

        return DB::transaction(function () use ($task, $data, $actor) {
            $oldValues = $task->toArray();

            $task->update([
                'scheduled_at' => $data['scheduled_at'],
                'status'       => TaskStatus::TERJADWAL->value,
                'updated_by'   => $actor->id,
            ]);

            $memberIds = $data['team_member_ids'] ?? [];
            $task->teamMembers()->delete();
            foreach ($memberIds as $index => $userId) {
                TaskTeam::create([
                    'task_id'      => $task->id,
                    'user_id'      => $userId,
                    'role_in_task' => $index === 0 ? 'lead' : 'teknisi',
                ]);
            }

            // Parse checklist
            $items = array_filter(
                array_map('trim',
                    preg_split('/[\r\n,]+/', $data['checklist_items'] ?? '')
                )
            );
            
            $sort = 1;
            foreach ($items as $item) {
                \App\Models\TaskChecklist::create([
                    'task_id' => $task->id,
                    'item' => $item,
                    'is_checked' => false,
                    'is_required' => true,
                    'sort_order' => $sort++
                ]);
            }

            $this->notifyTeam($task, 'Tiket antrean baru dijadwalkan untuk Anda', 'created');

            \App\Models\AuditLog::log($task, 'scheduled', $oldValues, $task->toArray());

            return $task->refresh();
        });
    }

    // ─── Transisi Status ─────────────────────────────────────────

    /**
     * Teknisi mulai mengerjakan task.
     */
    public function start(Task $task, User $actor): Task
    {
        abort_unless(
            $task->status === TaskStatus::TERJADWAL,
            422,
            'Task hanya bisa dimulai dari status Terjadwal.'
        );

        $task->update([
            'status'     => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'updated_by' => $actor->id,
        ]);

        $task = $task->refresh();
        broadcast(new TaskStarted($task));

        return $task;
    }

    /**
     * Teknisi menandai task selesai.
     * Syarat: semua checklist wajib tercentang + min 1 foto bukti.
     */
    public function complete(Task $task, User $actor): Task
    {
        abort_unless(
            $task->status === TaskStatus::IN_PROGRESS,
            422,
            'Task hanya bisa diselesaikan dari status In Progress.'
        );

        abort_unless(
            $task->canComplete(),
            422,
            'Semua checklist wajib harus dicentang dan minimal 1 foto bukti harus diupload.'
        );

        $task->update([
            'status'            => TaskStatus::SELESAI->value,
            'fop_review_status' => 'pending',
            'completed_at'      => now(),
            'updated_by'        => $actor->id,
        ]);

        $task = $task->refresh();
        broadcast(new TaskCompleted($task));

        // Notify FOP users in the same POP
        $fopUsers = \App\Models\User::whereHas('role', fn ($q) => $q->where('code', 'fop'))
            ->where(function ($query) use ($task) {
                $query->whereHas('roleScopes', fn ($q) => $q->where('scope_type', \App\Enums\ScopeType::ALL_POP->value))
                    ->orWhereHas('roleScopes', fn ($q) => $q->whereIn('scope_type', [\App\Enums\ScopeType::SELECTED_POP->value, \App\Enums\ScopeType::POP_TREE->value])
                        ->whereHas('targets', fn ($t) => $t->where('pop_id', $task->pop_id))
                    );
            })
            ->get();

        foreach ($fopUsers as $fop) {
            /** @var \App\Models\User $fop */
            $fop->notify(new \App\Notifications\AppNotification(
                title: 'Laporan Task Selesai: ' . $task->task_number,
                message: "Teknisi {$actor->name} telah menyelesaikan task dan mengunggah laporan. Butuh review Anda.",
                actionUrl: route('tasks.show', $task->id),
                type: 'info'
            ));
        }

        \App\Models\AuditLog::log($task, 'completed', ['status' => TaskStatus::IN_PROGRESS->value], ['status' => TaskStatus::SELESAI->value]);

        return $task;
    }

    /**
     * Teknisi set task ke Pending (butuh reschedule).
     */
    public function setPending(Task $task, User $actor, string $reason): Task
    {
        abort_unless(
            $task->status === TaskStatus::IN_PROGRESS,
            422,
            'Task hanya bisa di-pending dari status In Progress.'
        );

        $task->update([
            'status'         => TaskStatus::PENDING->value,
            'pending_reason' => $reason,
            'updated_by'     => $actor->id,
        ]);

        return $task->refresh();
    }

    /**
     * FOP membatalkan task.
     */
    public function cancel(Task $task, User $actor, string $reason): Task
    {
        abort_unless(
            !in_array($task->status, [TaskStatus::SELESAI, TaskStatus::DIBATALKAN]),
            422,
            'Task yang sudah selesai atau dibatalkan tidak bisa dibatalkan lagi.'
        );

        $task->update([
            'status'       => TaskStatus::DIBATALKAN->value,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
            'updated_by'   => $actor->id,
        ]);

        \App\Models\AuditLog::log($task, 'cancelled', ['status' => $task->getOriginal('status')], ['status' => TaskStatus::DIBATALKAN->value, 'cancel_reason' => $reason]);

        return $task->refresh();
    }

    // ─── Checklist & Bukti ───────────────────────────────────────

    /**
     * Teknisi centang/uncentang satu item checklist.
     */
    public function updateChecklist(TaskChecklist $checklist, bool $isChecked, User $actor): TaskChecklist
    {
        $checklist->update([
            'is_checked' => $isChecked,
            'checked_by' => $isChecked ? $actor->id : null,
            'checked_at' => $isChecked ? now() : null,
        ]);

        return $checklist->refresh();
    }

    // ─── Validasi Konflik ────────────────────────────────────────

    /**
     * Reassign a technician while preserving checklist progress.
     */
    public function reassignTeam(Task $task, int $oldUserId, int $newUserId, int $reassignerId, ?string $newScheduledAt = null): void
    {
        if (!in_array($task->status, ['terjadwal', 'in_progress'])) {
            throw new \Exception("Hanya task terjadwal atau in progress yang bisa di-reassign.");
        }

        $scheduleChanged = false;
        $targetScheduledAt = $task->scheduled_at?->toIso8601String() ?? $task->scheduled_at;

        if ($newScheduledAt) {
            $parsedNewTime = Carbon::parse($newScheduledAt);
            if (!$task->scheduled_at || !$parsedNewTime->eq($task->scheduled_at)) {
                $scheduleChanged = true;
                $targetScheduledAt = $parsedNewTime->toDateTimeString();
            }
        }

        // Determine user IDs to check for conflicts
        $otherUserIds = $task->teamMembers()->where('user_id', '!=', $oldUserId)->pluck('user_id')->toArray();
        if ($scheduleChanged) {
            $userIdsToCheck = array_merge($otherUserIds, [$newUserId]);
        } else {
            $userIdsToCheck = [$newUserId];
        }

        // Check if tech is available (conflict check)
        $conflicts = $this->detectConflicts($userIdsToCheck, $targetScheduledAt, (int) $task->sla_minutes, $task->id);
        if (count($conflicts) > 0) {
            $conflict = collect($conflicts)->first();
            throw new \Exception("Teknisi memiliki jadwal yang bentrok: Task {$conflict['task_number']} pada {$conflict['scheduled_at']}.");
        }

        // Validate max active tasks limit
        $scheduleDate = Carbon::parse($targetScheduledAt)->format('Y-m-d');
        if ($scheduleChanged) {
            if (!$this->teamCanAddTask($userIdsToCheck, $scheduleDate)) {
                throw new \Exception("Tim ini sudah memiliki " . self::MAX_ACTIVE_TASKS_PER_TEAM_PER_DAY . " task aktif pada tanggal tersebut.");
            }
        } else {
            if (!$this->teamCanAddTask([$newUserId], $scheduleDate)) {
                throw new \Exception("Teknisi baru sudah memiliki " . self::MAX_ACTIVE_TASKS_PER_TEAM_PER_DAY . " task aktif pada tanggal tersebut.");
            }
        }

        // Check if old user is in the team
        $teamMember = $task->teamMembers()->where('user_id', $oldUserId)->first();
        if (!$teamMember) {
            throw new \Exception("Teknisi lama tidak ditemukan dalam tim task ini.");
        }

        // Update the team member ID
        $teamMember->update(['user_id' => $newUserId]);

        // If schedule changed, update task's scheduled_at
        if ($scheduleChanged) {
            $task->update([
                'scheduled_at' => $targetScheduledAt,
                'updated_by'   => $reassignerId,
            ]);
        } else {
            $task->update([
                'updated_by'   => $reassignerId,
            ]);
        }

        // Log the change
        $reassigner = \App\Models\User::find($reassignerId);
        $oldUser = \App\Models\User::find($oldUserId);
        $newUser = \App\Models\User::find($newUserId);

        \App\Models\AuditLog::log(
            $task,
            'reassigned',
            [
                'user_id' => $oldUserId,
                'name' => $oldUser?->name,
                'scheduled_at' => $task->getOriginal('scheduled_at')?->toDateTimeString()
            ],
            [
                'user_id' => $newUserId,
                'name' => $newUser?->name,
                'scheduled_at' => $task->scheduled_at?->toDateTimeString()
            ]
        );

        // Notify new tech
        if ($newUser) {
            $url = route('tasks.show', $task->id);
            $msg = "Anda telah ditugaskan menggantikan {$oldUser?->name} untuk task ini.";
            if ($scheduleChanged) {
                $msg .= " Jadwal diubah ke " . Carbon::parse($targetScheduledAt)->format('d M Y H:i');
            }
            $newUser->notify(new \App\Notifications\AppNotification(
                title: 'Task Baru Di-assign: ' . $task->task_number,
                message: $msg,
                actionUrl: $url,
                type: 'info'
            ));
        }

        // Notify old tech
        if ($oldUser) {
            $url = route('tasks.show', $task->id);
            $oldUser->notify(new \App\Notifications\AppNotification(
                title: 'Task Di-unassign: ' . $task->task_number,
                message: "Tugas ini telah dialihkan ke teknisi lain.",
                actionUrl: $url,
                type: 'error'
            ));
        }

        // Notify remaining team members if schedule changed
        if ($scheduleChanged && count($otherUserIds) > 0) {
            $formattedTime = Carbon::parse($targetScheduledAt)->format('d M Y H:i');
            foreach ($otherUserIds as $otherId) {
                $otherUser = \App\Models\User::find($otherId);
                if ($otherUser) {
                    $url = route('tasks.show', $task->id);
                    $otherUser->notify(new \App\Notifications\AppNotification(
                        title: 'Jadwal Task Diperbarui: ' . $task->task_number,
                        message: "Jadwal task diubah ke {$formattedTime}.",
                        actionUrl: $url,
                        type: 'info'
                    ));
                }
            }
        }

        // Broadcast Event to notify techs/frontend about schedule/team change
        broadcast(new \App\Events\TaskScheduled($task, $scheduleChanged ? 'rescheduled' : 'created'));
    }

    /**
     * Deteksi apakah ada teknisi dalam daftar yang konflik jadwal pada waktu tersebut.
     *
     * @param  array<int>     $userIds
     * @param  string         $scheduledAt  ISO datetime
     * @param  int            $durationMinutes
     * @param  int|null       $excludeTaskId  Abaikan task ini (saat edit)
     * @return \Illuminate\Support\Collection     User IDs yang konflik
     */
    public function detectConflicts(
        array $userIds,
        string $scheduledAt,
        int $durationMinutes = 120,
        ?int $excludeTaskId = null
    ): \Illuminate\Support\Collection {
        $start = Carbon::parse($scheduledAt);
        $end   = $start->copy()->addMinutes($durationMinutes);

        $conflictingTeams = TaskTeam::with(['user', 'task'])
            ->whereIn('user_id', $userIds)
            ->whereHas('task', function ($q) use ($start, $end, $excludeTaskId) {
                $q->whereIn('status', [TaskStatus::TERJADWAL->value, TaskStatus::IN_PROGRESS->value])
                  ->where('scheduled_at', '<', $end->toDateTimeString());

                if (\Illuminate\Support\Facades\DB::connection()->getDriverName() === 'sqlite') {
                    $q->whereRaw("datetime(scheduled_at, '+' || sla_minutes || ' minutes') > ?", [$start->toDateTimeString()]);
                } else {
                    $q->whereRaw("DATE_ADD(scheduled_at, INTERVAL sla_minutes MINUTE) > ?", [$start->toDateTimeString()]);
                }

                if ($excludeTaskId) {
                    $q->where('id', '!=', $excludeTaskId);
                }
            })
            ->get();

        return $conflictingTeams;
    }

    /**
     * Validasi batas 4 task aktif per tim per hari.
     * Returns true jika masih bisa ditambah.
     */
    public function teamCanAddTask(array $userIds, string $date): bool
    {
        $activeCount = Task::where('scheduled_at', 'like', Carbon::parse($date)->format('Y-m-d') . '%')
            ->whereIn('status', [TaskStatus::TERJADWAL->value, TaskStatus::IN_PROGRESS->value])
            ->whereHas('teamMembers', fn ($q) => $q->whereIn('user_id', $userIds))
            ->count();

        return $activeCount < self::MAX_ACTIVE_TASKS_PER_TEAM_PER_DAY;
    }

    // ─── Helper ──────────────────────────────────────────────────

    private function generateTaskNumber(): string
    {
        $year  = date('Y');
        $count = Task::whereYear('created_at', $year)->count() + 1;
        return sprintf('TASK-%s-%04d', $year, $count);
    }

    private function notifyTeam(Task $task, string $message, string $eventType = 'created'): void
    {
        $members = $task->teamMembers()->with('user')->get();
        $url = route('tasks.show', $task->id);

        foreach ($members as $member) {
            if ($member->user) {
                // Send in-app notification + broadcast
                $member->user->notify(new \App\Notifications\AppNotification(
                    title: 'Update Task: ' . $task->task_number,
                    message: $message,
                    actionUrl: $url,
                    type: $eventType === 'cancelled' || $eventType === 'rejected' ? 'error' : 'info'
                ));

                // Optional: keep Telegram if already setup
                \App\Jobs\SendTaskNotificationJob::dispatch($task->id, $member->user_id, $message);
            }
        }

        broadcast(new \App\Events\TaskScheduled($task, $eventType));
    }
}
