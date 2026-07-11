<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskStatusController extends Controller
{
    public function __construct(private readonly TaskService $taskService) {}

    /**
     * Mulai task.
     * Guard: task.status.start — hanya anggota tim
     */
    public function start(Task $task): RedirectResponse
    {
        $this->authorize('statusStart', $task);

        $memberIds = $task->teamMembers()->pluck('user_id')->toArray();
        if (!in_array(auth()->id(), $memberIds)) {
            $memberIds[] = auth()->id();
        }

        $activeTask = Task::where('id', '!=', $task->id)
            ->where('status', \App\Enums\TaskStatus::IN_PROGRESS->value)
            ->whereHas('teamMembers', fn ($q) => $q->whereIn('user_id', $memberIds))
            ->first();

        if ($activeTask) {
            return back()->with('error', "Tidak dapat memulai task karena teknisi dalam tim sedang mengerjakan task lain [{$activeTask->task_number}]. Selesaikan atau laporkan (pending) task sebelumnya terlebih dahulu.");
        }

        $this->taskService->start($task, auth()->user());

        return back()->with('success', "Task [{$task->task_number}] dimulai.");
    }

    /**
     * Selesaikan task.
     * Guard: task.status.complete — hanya anggota tim
     * Syarat: semua checklist wajib + min 1 bukti
     */
    public function complete(Task $task): RedirectResponse
    {
        $this->authorize('statusComplete', $task);

        $this->taskService->complete($task, auth()->user());

        return back()->with('success', "Task [{$task->task_number}] berhasil diselesaikan.");
    }

    /**
     * Set task ke Pending.
     * Guard: task.status.pending — hanya anggota tim
     */
    public function pending(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('statusPending', $task);

        $validated = $request->validate([
            'pending_reason'  => 'required|string|max:500',
            'report_deferred' => 'sometimes|boolean',
        ]);

        $this->taskService->setPending(
            $task,
            auth()->user(),
            $validated['pending_reason'],
            (bool) ($validated['report_deferred'] ?? false)
        );

        return back()->with('success', "Task [{$task->task_number}] dipending.");
    }
}
