<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Task;
use App\Services\FileUploadService;
use App\Services\TaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskMaintenanceController extends Controller
{
    public function report(Task $task)
    {
        $this->authorize('statusComplete', $task);

        if (! in_array($task->status->value, [TaskStatus::IN_PROGRESS->value, TaskStatus::PENDING->value])) {
            return redirect()->route('tasks.show', $task)->with('error', 'Status task tidak valid untuk pelaporan maintenance.');
        }

        // Pastikan bukan task survey atau pemasangan (yang punya form laporan khusus)
        if (in_array($task->task_type, [TaskType::SURVEY, TaskType::PEMASANGAN])) {
            return redirect()->route('tasks.show', $task)->with('error', 'Gunakan form laporan khusus untuk Survey / Pemasangan.');
        }

        return view('tasks.maintenance-report', compact('task'));
    }

    public function store(Request $request, Task $task, TaskService $taskService)
    {
        $this->authorize('statusComplete', $task);

        $validated = $request->validate([
            'kendala_teknis' => 'required|string',
            'kabel' => 'nullable|string|max:100',
            'modem' => 'nullable|string|max:100',
            'patchcord' => 'nullable|string|max:100',
            'sleeve' => 'nullable|string|max:100',
            'lainnya' => 'nullable|string|max:255',
            'opm_photo' => 'required|image|max:2048',
            'speedtest_photo' => 'required|image|max:2048',
        ]);

        try {
            DB::beginTransaction();

            $task->loadMissing('customer');
            $opmPhotoPath = null;
            if ($request->hasFile('opm_photo')) {
                $opmPhotoPath = FileUploadService::uploadMaintenancePhoto($request->file('opm_photo'), $task->customer, 'opm');
            }

            $speedtestPhotoPath = null;
            if ($request->hasFile('speedtest_photo')) {
                $speedtestPhotoPath = FileUploadService::uploadMaintenancePhoto($request->file('speedtest_photo'), $task->customer, 'speedtest');
            }

            $task->maintenanceReport()->create([
                'kendala_teknis' => $validated['kendala_teknis'],
                'kabel' => $validated['kabel'] ?? null,
                'modem' => $validated['modem'] ?? null,
                'patchcord' => $validated['patchcord'] ?? null,
                'sleeve' => $validated['sleeve'] ?? null,
                'lainnya' => $validated['lainnya'] ?? null,
                'opm_photo' => $opmPhotoPath,
                'speedtest_photo' => $speedtestPhotoPath,
            ]);

            // Selesaikan task
            $taskService->complete($task, auth()->user());

            DB::commit();

            return redirect()->route('tasks.show', $task)->with('success', 'Laporan maintenance berhasil disimpan dan task diselesaikan.');

        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->withInput()->with('error', 'Terjadi kesalahan: '.$e->getMessage());
        }
    }
}
