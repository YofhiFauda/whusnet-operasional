<?php

namespace App\Http\Controllers;

use App\Models\FopTask;
use App\Models\Pop;
use App\Models\Task;
use App\Services\InfrastructureLocationOptionsService;
use App\Services\TaskWorkToolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Endpoint baca-saja untuk dropdown lokasi bertingkat laporan O-REQ/INFR-REQ
 * dan form akar scope Task FOP. Tidak ada mutasi di sini; validasi simpan
 * tetap di `InfrastructureReportLocationParser` / `FopTaskInfrastructureScopeService`.
 */
class InfrastructureLocationOptionsController extends Controller
{
    public function __construct(private readonly InfrastructureLocationOptionsService $options) {}

    public function taskOptions(Request $request, Task $task): JsonResponse
    {
        $this->authorize('statusComplete', $task);
        $validated = $request->validate([
            'level' => ['required', Rule::in(array_keys(InfrastructureLocationOptionsService::LEVEL_DEPTH))],
            'parent' => ['nullable', 'integer'],
        ]);

        return response()->json($this->options->forTask(
            $this->anchorFor($task), $request->user(), $validated['level'], isset($validated['parent']) ? (int) $validated['parent'] : null
        ));
    }

    public function taskAssets(Request $request, Task $task): JsonResponse
    {
        $this->authorize('statusComplete', $task);
        $validated = $request->validate([
            'kind' => ['required', Rule::in(['pop', 'mini_pop', 'node', 'slot', 'port'])],
            'id' => ['required', 'integer'],
        ]);

        return response()->json($this->options->assetsAt($this->anchorFor($task), $request->user(), $validated['kind'], (int) $validated['id']));
    }

    public function scopeOptions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pop_id' => ['required', 'integer', Rule::exists('pops', 'id')],
            'level' => ['required', Rule::in(['mini_pop', 'device', 'slot', 'port', 'odp'])],
            'parent' => ['required', 'integer'],
        ]);

        return response()->json($this->options->forScopeForm(
            Pop::findOrFail($validated['pop_id']), $request->user(), $validated['level'], (int) $validated['parent']
        ));
    }

    /**
     * Anchor FopTask sama dengan yang dipakai laporan (`resolveTaskFor`).
     * Task tanpa anchor (dibuat saat laporan disimpan) memakai FopTask
     * SEMENTARA yang tidak disimpan — GET tidak boleh menulis data; akarnya
     * jatuh ke Cabang task, sama seperti anchor yang nanti dibuat.
     */
    private function anchorFor(Task $task): FopTask
    {
        return app(TaskWorkToolService::class)->resolveTaskFor($task)
            ?? new FopTask(['pop_id' => $task->pop_id, 'category' => $task->task_type]);
    }
}
