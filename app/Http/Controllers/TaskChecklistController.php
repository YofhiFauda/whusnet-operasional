<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskChecklist;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskChecklistController extends Controller
{
    public function __construct(private readonly TaskService $taskService) {}

    /**
     * Toggle centang/uncentang satu item checklist.
     * Guard: task.checklist.update — hanya anggota tim
     */
    public function update(Request $request, Task $task, TaskChecklist $checklist): JsonResponse
    {
        $this->authorize('updateChecklist', $task);

        abort_unless($checklist->task_id === $task->id, 404);

        $validated = $request->validate([
            'is_checked' => 'required|boolean',
        ]);

        $updated = $this->taskService->updateChecklist($checklist, $validated['is_checked'], auth()->user());

        // Kembalikan status canComplete agar tombol Selesai bisa update realtime
        $task->refresh();

        return response()->json([
            'success'      => true,
            'is_checked'   => $updated->is_checked,
            'checked_by'   => $updated->checkedByUser?->name,
            'checked_at'   => $updated->checked_at?->diffForHumans(),
            'can_complete' => $task->canComplete(),
            'pending_required' => $task->pendingRequiredChecklists(),
        ]);
    }
}
