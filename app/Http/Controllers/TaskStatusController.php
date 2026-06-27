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
            'pending_reason' => 'required|string|max:500',
        ]);

        $this->taskService->setPending($task, auth()->user(), $validated['pending_reason']);

        return back()->with('success', "Task [{$task->task_number}] dipending.");
    }
}
