<?php

namespace App\Http\Controllers\Master;

use App\Enums\TaskType;
use App\Http\Controllers\Controller;
use App\Models\InternetPackage;
use App\Models\PackageSlaSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SlaTimelineController extends Controller
{
    public function index(): View
    {
        $packages = InternetPackage::active()
            ->with('slaSettings')
            ->orderBy('category')
            ->orderBy('package_code')
            ->get();

        $taskTypes = TaskType::cases();

        return view('master.sla-timeline.index', compact('packages', 'taskTypes'));
    }

    public function update(Request $request, InternetPackage $paket): JsonResponse
    {
        $validated = $request->validate([
            'task_type' => ['required', Rule::in(array_column(TaskType::cases(), 'value'))],
            'sla_duration' => ['required', 'integer', 'min:1'],
            'sla_unit' => ['required', 'in:hour,day'],
        ]);

        $existing = PackageSlaSetting::where('internet_package_id', $paket->id)
            ->where('task_type', $validated['task_type'])
            ->first();

        $setting = PackageSlaSetting::updateOrCreate(
            [
                'internet_package_id' => $paket->id,
                'task_type' => $validated['task_type'],
            ],
            [
                'sla_duration' => $validated['sla_duration'],
                'sla_unit' => $validated['sla_unit'],
                'is_active' => true,
                'created_by' => $existing->created_by ?? auth()->id(),
                'updated_by' => auth()->id(),
            ]
        );

        return response()->json([
            'success' => true,
            'sla_hours' => $setting->sla_hours,
        ]);
    }
}
