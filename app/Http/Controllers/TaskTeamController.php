<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskTeamController extends Controller
{
    public function __construct(private TaskService $taskService) {}

    public function update(Request $request, Task $task)
    {
        $validated = $request->validate([
            'old_user_id'  => 'required|exists:users,id',
            'new_user_id'  => 'required|exists:users,id|different:old_user_id',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        try {
            DB::beginTransaction();
            $this->taskService->reassignTeam(
                $task, 
                $validated['old_user_id'], 
                $validated['new_user_id'], 
                auth()->id(),
                $validated['scheduled_at'] ?? null
            );
            DB::commit();

            return back()->with('success', 'Teknisi berhasil diganti.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal mengganti teknisi: ' . $e->getMessage());
        }
    }
}
