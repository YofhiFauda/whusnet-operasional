<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Events\TaskCompleted;
use App\Events\TaskScheduled;
use App\Events\TaskStarted;
use App\Jobs\SendTaskNotificationJob;
use App\Models\Task;
use App\Models\TaskTeam;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\FopTask;
use App\Services\FopTaskTeamService;

class TaskService
{
    // ─── Pembuatan Task ──────────────────────────────────────────

    /**
     * Buat task baru beserta tim.
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

            $this->syncToFopTask($task);

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

        abort_unless(
            $task->scheduled_at && !$task->scheduled_at->startOfDay()->isFuture(),
            422,
            'Task hanya bisa dimulai pada atau setelah hari yang dijadwalkan.'
        );

        $memberIds = $task->teamMembers()->pluck('user_id')->toArray();
        if (!in_array($actor->id, $memberIds)) {
            $memberIds[] = $actor->id;
        }

        $activeTask = Task::where('id', '!=', $task->id, 'and')
            ->where('status', TaskStatus::IN_PROGRESS->value)
            ->whereHas('teamMembers', fn ($q) => $q->whereIn('user_id', $memberIds))
            ->first();

        if ($activeTask !== null) {
            abort(
                422,
                "Tidak dapat memulai task karena teknisi dalam tim sedang mengerjakan task lain [{$activeTask->task_number}]. Selesaikan atau laporkan (pending) task sebelumnya terlebih dahulu."
            );
        }

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
     */
    public function complete(Task $task, User $actor): Task
    {
        abort_unless(
            in_array($task->status, [TaskStatus::IN_PROGRESS, TaskStatus::PENDING]),
            422,
            'Task hanya bisa diselesaikan dari status In Progress atau Pending.'
        );

        abort_unless(
            $task->canComplete(),
            422,
            'Syarat penyelesaian task belum terpenuhi.'
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
        $fopUsers = User::whereHas('role', fn ($q) => $q->where('code', 'fop'))
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
                type: NotificationType::INFO
            ));
        }

        \App\Models\AuditLog::log($task, 'completed', ['status' => TaskStatus::IN_PROGRESS->value], ['status' => TaskStatus::SELESAI->value]);

        return $task;
    }

    /**
     * Teknisi set task ke Pending (butuh reschedule).
     */
    public function setPending(Task $task, User $actor, string $reason, bool $reportDeferred = false): Task
    {
        abort_unless(
            $task->status === TaskStatus::IN_PROGRESS,
            422,
            'Task hanya bisa di-pending dari status In Progress.'
        );

        DB::transaction(function () use ($task, $reason, $actor, $reportDeferred) {
            $task->update([
                'status'          => TaskStatus::PENDING->value,
                'pending_reason'  => $reason,
                'report_deferred' => $reportDeferred,
                'updated_by'      => $actor->id,
            ]);

            // Stop the timer on CustomerSurvey or CustomerInstallation if task type is survey or pemasangan
            if ($task->customer_id) {
                if ($task->task_type === TaskType::SURVEY) {
                    $survey = \App\Models\CustomerSurvey::where('customer_id', '=', $task->customer_id, 'and')
                        ->latest()
                        ->first();
                    if ($survey && $survey->started_at && !$survey->completed_at) {
                        $completedAt = now();
                        $survey->completed_at = $completedAt;
                        $survey->duration_minutes = $survey->started_at->diffInMinutes($completedAt);
                        $survey->end_date = $completedAt->toDateString();
                        $survey->end_time = $completedAt->toTimeString();
                        $survey->save();
                    }
                } elseif ($task->task_type === TaskType::PEMASANGAN) {
                    $installation = \App\Models\CustomerInstallation::where('customer_id', '=', $task->customer_id, 'and')
                        ->latest()
                        ->first();
                    if ($installation && $installation->started_at && !$installation->completed_at) {
                        $completedAt = now();
                        $installation->completed_at = $completedAt;
                        $installation->end_time = $completedAt->toTimeString();
                        $installation->finished_date = $completedAt->toDateString();
                        $installation->save();
                    }
                }
            }
        });

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

        $wasInProgress = $task->status === TaskStatus::IN_PROGRESS;

        $task->update([
            'status'       => TaskStatus::DIBATALKAN->value,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
            'updated_by'   => $actor->id,
        ]);

        \App\Models\AuditLog::log($task, 'cancelled', ['status' => $task->getOriginal('status')], ['status' => TaskStatus::DIBATALKAN->value, 'cancel_reason' => $reason]);

        // Task 12 — teknisi yang lagi ngerjain (in_progress) harus tau begitu
        // task-nya dibatalkan dari atas, gak cukup cuma keliatan diam-diam ilang
        // dari /tasks-saya.
        if ($wasInProgress) {
            $this->notifyTeam($task, "Task dibatalkan: {$reason}", 'cancelled');
        }

        return $task->refresh();
    }

    // ─── Validasi Konflik ────────────────────────────────────────

    /**
     * Reassign a technician.
     */
    public function reassignTeam(Task $task, int $oldUserId, int $newUserId, int $reassignerId, ?string $newScheduledAt = null): void
    {
        if (!in_array($task->status, [TaskStatus::TERJADWAL, TaskStatus::IN_PROGRESS], true)) {
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

        // Check if old user is in the team — cek duluan sebelum conflict-detection
        // biar gak buang query conflict kalau oldUserId emang bukan member.
        $hasMember = $task->teamMembers()->where('user_id', $oldUserId)->exists();
        if (!$hasMember) {
            throw new \Exception("Teknisi lama tidak ditemukan dalam tim task ini.");
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
            $taskNum = $conflict?->task?->task_number ?? 'Task';
            $sched = $conflict?->task?->scheduled_at ?? $targetScheduledAt;
            throw new \Exception("Teknisi memiliki jadwal yang bentrok: {$taskNum} pada {$sched}.");
        }

        // Update the team member ID + task, dibungkus transaction sendiri biar
        // service ini self-contained gak gantung transaction dari caller.
        DB::transaction(function () use ($task, $oldUserId, $newUserId, $reassignerId, $scheduleChanged, $targetScheduledAt) {
            $task->teamMembers()->where('user_id', $oldUserId)->update(['user_id' => $newUserId]);

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
        });

        // Log the change — batch-load all relevant users to avoid N+1
        $allUserIds = array_unique(array_filter(array_merge([$reassignerId, $oldUserId, $newUserId], $otherUserIds)));
        $usersById = User::whereIn('id', $allUserIds)->get()->keyBy('id');
        $reassigner = $usersById->get($reassignerId);
        $oldUser = $usersById->get($oldUserId);
        $newUser = $usersById->get($newUserId);

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
                type: NotificationType::INFO
            ));
        }

        // Notify old tech
        if ($oldUser) {
            $url = route('tasks.show', $task->id);
            $oldUser->notify(new \App\Notifications\AppNotification(
                title: 'Task Di-unassign: ' . $task->task_number,
                message: "Tugas ini telah dialihkan ke teknisi lain.",
                actionUrl: $url,
                type: NotificationType::ERROR
            ));
        }

        // Notify remaining team members if schedule changed
        if ($scheduleChanged && count($otherUserIds) > 0) {
            $formattedTime = Carbon::parse($targetScheduledAt)->format('d M Y H:i');
            $url = route('tasks.show', $task->id);
            foreach ($otherUserIds as $otherId) {
                $otherUser = $usersById->get($otherId);
                if ($otherUser) {
                    $otherUser->notify(new \App\Notifications\AppNotification(
                        title: 'Jadwal Task Diperbarui: ' . $task->task_number,
                        message: "Jadwal task diubah ke {$formattedTime}.",
                        actionUrl: $url,
                        type: NotificationType::INFO
                    ));
                }
            }
        }

        // Broadcast Event to notify techs/frontend about schedule/team change
        broadcast(new TaskScheduled($task, $scheduleChanged ? 'rescheduled' : 'created'));

        $this->syncToFopTask($task);
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

                if (DB::connection()->getDriverName() === 'sqlite') {
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
     * Validasi batas task aktif per tim per hari (dihilangkan batas hariannya).
     */
    public function teamCanAddTask(array $userIds, string $date): bool
    {
        return true;
    }

    // ─── Helper ──────────────────────────────────────────────────

    private function generateTaskNumber(): string
    {
        $year  = date('Y');
        $count = Task::whereBetween('created_at', [
            Carbon::createFromDate($year)->startOfYear(),
            Carbon::createFromDate($year)->endOfYear(),
        ], 'and', false)->count() + 1;
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
                    type: $eventType === 'cancelled' || $eventType === 'rejected' ? NotificationType::ERROR : NotificationType::INFO
                ));

                // Optional: keep Telegram if already setup
                SendTaskNotificationJob::dispatch($task->id, $member->user_id, $message);
            }
        }

        broadcast(new TaskScheduled($task, $eventType));
    }

    /**
     * Synchronize Task changes (technicians and schedule) to corresponding FopTask.
     */
    private function syncToFopTask(Task $task): void
    {
        $fopTask = FopTask::where('task_id', '=', $task->id, 'and')->first();
        if (!$fopTask) {
            return;
        }

        $oldTaskDate = $fopTask->task_date ? $fopTask->task_date->copy() : null;
        $newTaskDate = $task->scheduled_at;

        // Sync technicians
        $technicianIds = $task->teamMembers()->pluck('user_id')->toArray();
        $fopTask->technicians()->sync($technicianIds);

        // Sync task_date
        if ($newTaskDate) {
            $fopTask->task_date = $newTaskDate;
        }
        $fopTask->save();

        // Rebuild teams for old and new dates if scheduled date changed
        $fopTeamService = app(FopTaskTeamService::class);
        if ($oldTaskDate && $newTaskDate && !$oldTaskDate->isSameDay($newTaskDate)) {
            $fopTeamService->rebuildTeamsForDate($oldTaskDate);
        }
        if ($fopTask->task_date) {
            $fopTeamService->rebuildTeamsForDate($fopTask->task_date);
        }
    }
}
