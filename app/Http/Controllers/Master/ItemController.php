<?php

namespace App\Http\Controllers\Master;

use App\Enums\MaterialType;
use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

/**
 * Master Barang/Material.
 *
 * Sengaja tanpa delete: baris material yang sudah tercatat menunjuk ke item ini,
 * dan menghapusnya bikin laporan lama kehilangan rujukan. Barang yang tidak
 * dipakai lagi dinonaktifkan (is_active=false) — sama seperti master lain.
 */
class ItemController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $type = $request->query('type');
        $status = $request->query('status');

        $items = Item::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($type, fn ($query) => $query->where('type', $type))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('master.items.index', compact('items', 'search', 'type', 'status'));
    }

    public function create(): View
    {
        return view('master.items.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateItem($request);

        Item::create($validated);

        return redirect()
            ->route('master.items.index')
            ->with('success', 'Barang "'.$validated['name'].'" berhasil ditambahkan.');
    }

    public function edit(Item $item): View
    {
        return view('master.items.edit', compact('item'));
    }

    public function update(Request $request, Item $item): RedirectResponse
    {
        $validated = $this->validateItem($request, $item);

        $item->update($validated);

        return redirect()
            ->route('master.items.index')
            ->with('success', 'Barang "'.$item->name.'" berhasil diperbarui.');
    }

    public function toggleStatus(Item $item): RedirectResponse
    {
        $item->update(['is_active' => ! $item->is_active]);

        $statusText = $item->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "Barang \"{$item->name}\" berhasil {$statusText}.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validateItem(Request $request, ?Item $item = null): array
    {
        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('items', 'code')->ignore($item),
            ],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', new Enum(MaterialType::class)],
            'unit' => ['required', 'string', 'max:20'],
            'is_active' => 'required|boolean',
        ]);
    }
}
