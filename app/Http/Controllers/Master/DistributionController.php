<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Distribution;
use App\Models\Pop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class DistributionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $pop_id = $request->query('pop_id');

        $distributions = Distribution::query()
            ->with('pop')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                      ->orWhere('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($pop_id, function ($query) use ($pop_id) {
                $query->where('pop_id', $pop_id);
            })
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString();

        $pops = Pop::where('status', 'active')->orderBy('name')->get();

        return view('master.distribusi.index', compact('distributions', 'search', 'pop_id', 'pops'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $pops = Pop::where('status', 'active')->orderBy('name')->get();

        return view('master.distribusi.create', compact('pops'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->normalizeInput($request);

        $validated = $request->validate([
            'pop_id' => 'required|exists:pops,id',
            'code' => [
                'required',
                'string',
                'max:50',
                // Kode distribusi harus unik secara GLOBAL di seluruh sistem
                // (bukan per POP) sesuai spesifikasi-pop-distribusi-cid.md
                Rule::unique('distributions', 'code'),
            ],
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:255',
        ]);

        Distribution::create($validated);

        return redirect()
            ->route('master.distribusi.index')
            ->with('success', 'Distribusi berhasil ditambahkan.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Distribution $distribusi): View
    {
        $pops = Pop::where('status', 'active')->orderBy('name')->get();

        return view('master.distribusi.edit', compact('distribusi', 'pops'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Distribution $distribusi): RedirectResponse
    {
        $this->normalizeInput($request);

        $validated = $request->validate([
            'pop_id' => 'required|exists:pops,id',
            'code' => [
                'required',
                'string',
                'max:50',
                // Kode distribusi harus unik secara GLOBAL di seluruh sistem
                // (bukan per POP) sesuai spesifikasi-pop-distribusi-cid.md
                Rule::unique('distributions', 'code')->ignore($distribusi->id),
            ],
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:255',
        ]);

        $distribusi->update($validated);

        return redirect()
            ->route('master.distribusi.index')
            ->with('success', 'Distribusi berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Distribution $distribusi): RedirectResponse
    {
        // Optional: Check if there are related customers before deleting
        // if ($distribusi->customers()->exists()) { ... }

        $distribusi->delete();

        return redirect()
            ->route('master.distribusi.index')
            ->with('success', 'Distribusi berhasil dihapus.');
    }

    private function normalizeInput(Request $request): void
    {
        $request->merge([
            'code' => strtoupper(trim((string) $request->input('code'))),
            'name' => trim((string) $request->input('name')),
        ]);
    }
}
