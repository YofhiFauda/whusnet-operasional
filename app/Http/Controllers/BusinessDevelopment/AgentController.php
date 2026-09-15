<?php

namespace App\Http\Controllers\BusinessDevelopment;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Master Agent (Skema 3, 2026-09-12) — mitra akuisisi pelanggan, BUKAN
 * akun login. Dikelola Business Development, dipakai sebagai dropdown saat
 * Busdev mendaftarkan pelanggan atas nama Agent.
 */
class AgentController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $agents = Agent::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('business-development.agents.index', compact('agents', 'search'));
    }

    public function create(): View
    {
        return view('business-development.agents.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateAgent($request);

        Agent::create($validated);

        return redirect()
            ->route('business-development.agents.index')
            ->with('success', "Agent \"{$validated['name']}\" berhasil ditambahkan.");
    }

    public function edit(Agent $agent): View
    {
        return view('business-development.agents.edit', compact('agent'));
    }

    public function update(Request $request, Agent $agent): RedirectResponse
    {
        $validated = $this->validateAgent($request, $agent);

        $agent->update($validated);

        return redirect()
            ->route('business-development.agents.index')
            ->with('success', "Agent \"{$agent->name}\" berhasil diperbarui.");
    }

    public function toggleStatus(Agent $agent): RedirectResponse
    {
        $agent->update(['is_active' => ! $agent->is_active]);

        $statusText = $agent->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "Agent \"{$agent->name}\" berhasil {$statusText}.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validateAgent(Request $request, ?Agent $agent = null): array
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('agents', 'code')->ignore($agent)],
            'name' => 'required|string|max:150',
            'phone' => 'nullable|string|max:20',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        return $validated;
    }
}
