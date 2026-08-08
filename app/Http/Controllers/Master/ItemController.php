<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
            ->with('category')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            // Filter tetap memakai code kategori di query string supaya URL/bookmark
            // lama tidak putus waktu kategori pindah dari enum ke tabel.
            ->when($type, fn ($query) => $query->whereRelation('category', 'code', $type))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $categories = ItemCategory::active()->ordered()->get();

        return view('master.items.index', compact('items', 'categories', 'search', 'type', 'status'));
    }

    public function create(): View
    {
        $categories = ItemCategory::active()->ordered()->get();

        return view('master.items.create', compact('categories'));
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
        // Kategori nonaktif tetap ikut kalau barang ini masih memakainya —
        // kalau tidak, membuka form edit lalu menyimpan diam-diam memindahkan
        // barang ke kategori lain karena pilihannya tidak ada di dropdown.
        $categories = ItemCategory::query()
            ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $item->item_category_id))
            ->ordered()
            ->get();

        return view('master.items.edit', compact('item', 'categories'));
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
            'item_category_id' => ['required', 'integer', Rule::exists('item_categories', 'id')],
            'unit' => ['required', 'string', 'max:20'],
            'is_active' => 'required|boolean',
        ]);
    }
}
