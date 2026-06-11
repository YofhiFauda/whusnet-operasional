<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Pop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PopController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $type = $request->query('type');
        $status = $request->query('status');

        $pops = Pop::query()
            ->forUser()
            ->with('parent')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%")
                      ->orWhere('pop_code', 'like', "%{$search}%")
                      ->orWhere('pic_name', 'like', "%{$search}%");
                });
            })
            ->when($type && in_array($type, ['pusat', 'cabang', 'mini_pop']), function ($query) use ($type) {
                $query->where('type', $type);
            })
            ->when($status && in_array($status, ['active', 'inactive']), function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('master.pop.index', compact('pops', 'search', 'type', 'status'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $parents = Pop::where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('master.pop.create', compact('parents'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->normalizeIdentifierInput($request);

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:pops,code',
            'pop_code' => 'required|string|max:20|regex:/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/|unique:pops,pop_code',
            'registration_prefix' => 'required|string|max:10|regex:/^[A-Z0-9]+$/',
            'cid_prefix' => 'required|string|max:10|regex:/^[A-Z0-9]+$/',
            'name' => 'required|string|max:150',
            'type' => 'required|string|in:pusat,cabang,mini_pop',
            'parent_id' => 'nullable|integer|exists:pops,id',
            'address' => 'nullable|string',
            'village' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'pic_name' => 'nullable|string|max:150',
            'pic_phone' => 'nullable|string|max:30',
        ]);

        Pop::create($validated);

        return redirect()
            ->route('master.pop.index')
            ->with('success', 'POP/Cabang berhasil ditambahkan.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Pop $pop): View
    {
        $pop->load(['parent', 'children' => function ($q) {
            $q->orderBy('name');
        }]);

        return view('master.pop.show', compact('pop'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Pop $pop): View
    {
        // Get all descendant IDs to exclude from parent options (prevent circular ref)
        $excludeIds = array_merge([$pop->id], $this->getDescendantIds($pop));

        $parents = Pop::where('status', 'active')
            ->whereNotIn('id', $excludeIds)
            ->orderBy('name')
            ->get();

        return view('master.pop.edit', compact('pop', 'parents'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Pop $pop): RedirectResponse
    {
        $this->normalizeIdentifierInput($request);

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:pops,code,' . $pop->id,
            'pop_code' => 'required|string|max:20|regex:/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/|unique:pops,pop_code,' . $pop->id,
            'registration_prefix' => 'required|string|max:10|regex:/^[A-Z0-9]+$/',
            'cid_prefix' => 'required|string|max:10|regex:/^[A-Z0-9]+$/',
            'name' => 'required|string|max:150',
            'type' => 'required|string|in:pusat,cabang,mini_pop',
            'parent_id' => 'nullable|integer|exists:pops,id',
            'address' => 'nullable|string',
            'village' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'pic_name' => 'nullable|string|max:150',
            'pic_phone' => 'nullable|string|max:30',
            'status' => 'required|string|in:active,inactive',
        ]);

        // Security check for circular reference
        $excludeIds = array_merge([$pop->id], $this->getDescendantIds($pop));
        if ($request->filled('parent_id') && in_array((int) $request->parent_id, $excludeIds)) {
            return back()
                ->withErrors(['parent_id' => 'Pilihan parent POP tidak valid karena akan menyebabkan hubungan melingkar.'])
                ->withInput();
        }

        $pop->update($validated);

        return redirect()
            ->route('master.pop.index')
            ->with('success', 'POP/Cabang berhasil diperbarui.');
    }

    /**
     * Toggle status of the specified resource.
     */
    public function toggleStatus(Pop $pop): RedirectResponse
    {
        $pop->status = $pop->status === 'active' ? 'inactive' : 'active';
        $pop->save();

        $statusText = $pop->status === 'active' ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "POP/Cabang {$pop->name} berhasil {$statusText}.");
    }

    /**
     * Recursively fetch all descendant IDs for a given POP.
     */
    private function getDescendantIds(Pop $pop): array
    {
        $ids = [];
        foreach ($pop->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $this->getDescendantIds($child));
        }
        return $ids;
    }

    private function normalizeIdentifierInput(Request $request): void
    {
        $request->merge([
            'pop_code' => strtoupper(trim((string) $request->input('pop_code'))),
            'registration_prefix' => strtoupper(trim((string) $request->input('registration_prefix'))),
            'cid_prefix' => strtoupper(trim((string) $request->input('cid_prefix'))),
        ]);
    }
}
