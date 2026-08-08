<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\WorkTool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Master Alat Kerja.
 *
 * Sengaja tanpa delete, alasan sama dengan Master Barang: checklist yang sudah
 * tersimpan menunjuk ke baris ini. Yang tidak dipakai lagi dinonaktifkan —
 * `task_work_tools.tool_name` menyimpan snapshot nama, jadi laporan lama tetap
 * terbaca kalau pun barisnya hilang.
 */
class WorkToolController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');

        $tools = WorkTool::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->ordered()
            ->paginate(15)
            ->withQueryString();

        return view('master.work_tools.index', compact('tools', 'search', 'status'));
    }

    public function create(): View
    {
        return view('master.work_tools.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateTool($request);

        WorkTool::create($validated);

        return redirect()
            ->route('master.work-tools.index')
            ->with('success', 'Alat kerja "'.$validated['name'].'" berhasil ditambahkan.');
    }

    public function edit(WorkTool $workTool): View
    {
        return view('master.work_tools.edit', ['tool' => $workTool]);
    }

    public function update(Request $request, WorkTool $workTool): RedirectResponse
    {
        $validated = $this->validateTool($request, $workTool);

        $workTool->update($validated);

        return redirect()
            ->route('master.work-tools.index')
            ->with('success', 'Alat kerja "'.$workTool->name.'" berhasil diperbarui.');
    }

    public function toggleStatus(WorkTool $workTool): RedirectResponse
    {
        $workTool->update(['is_active' => ! $workTool->is_active]);

        $statusText = $workTool->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "Alat kerja \"{$workTool->name}\" berhasil {$statusText}.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTool(Request $request, ?WorkTool $tool = null): array
    {
        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('work_tools', 'code')->ignore($tool),
            ],
            'name' => ['required', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
