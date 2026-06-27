<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskEvidence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskEvidenceController extends Controller
{
    /**
     * Upload foto bukti task.
     * Guard: task.evidence.upload — hanya anggota tim
     */
    public function store(Request $request, Task $task): JsonResponse
    {
        $this->authorize('uploadEvidence', $task);

        $validated = $request->validate([
            'photo'   => 'required|image|mimes:jpg,jpeg,png|max:5120', // max 5MB
            'caption' => 'nullable|string|max:255',
        ]);

        $file = $validated['photo'];
        $path = $file->store("task-evidences/{$task->id}", 'public');

        $evidence = TaskEvidence::create([
            'task_id'     => $task->id,
            'uploaded_by' => auth()->id(),
            'file_path'   => $path,
            'caption'     => $validated['caption'] ?? null,
        ]);

        $task->refresh();

        return response()->json([
            'success'      => true,
            'evidence'     => [
                'id'          => $evidence->id,
                'url'         => asset('storage/' . $evidence->file_path),
                'caption'     => $evidence->caption,
                'uploaded_by' => auth()->user()->name,
            ],
            'can_complete' => $task->canComplete(),
            'evidence_count' => $task->evidences()->count(),
        ]);
    }

    /**
     * Hapus bukti foto (FOP/creator saja).
     * Guard: task.edit
     */
    public function destroy(Task $task, TaskEvidence $evidence): JsonResponse
    {
        $this->authorize('edit', $task);

        abort_unless($evidence->task_id === $task->id, 404);

        // Hapus file dari storage
        if (\Illuminate\Support\Facades\Storage::disk('public')->exists($evidence->file_path)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($evidence->file_path);
        }

        $evidence->delete();

        return response()->json([
            'success'        => true,
            'evidence_count' => $task->evidences()->count(),
        ]);
    }
}
