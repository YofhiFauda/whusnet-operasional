<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\ItemCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Master Kategori Barang/Material.
 *
 * Sengaja tanpa delete, alasan sama dengan Master Barang: `task_materials`
 * dan `customer_technical_details.passive_device_type` menyimpan `code`
 * kategori sebagai snapshot, jadi menghapus barisnya bikin laporan lama
 * kehilangan nama kategori. Yang tidak dipakai lagi dinonaktifkan.
 *
 * Tujuh kategori bawaan (`is_system`) tidak bisa diubah code-nya —
 * `CustomerSurveyController` merakit baris dropcore otomatis dengan code
 * `kabel_dropcore` yang di-hardcode, dan `MigrateLegacyDataCommand` serta data
 * lama juga menyimpan code itu.
 */
class ItemCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');

        $categories = ItemCategory::query()
            ->withCount('items')
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

        return view('master.item_categories.index', compact('categories', 'search', 'status'));
    }

    public function create(): View
    {
        return view('master.item_categories.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateCategory($request);

        ItemCategory::create($validated);
        ItemCategory::flushLabelCache();

        return redirect()
            ->route('master.item-categories.index')
            ->with('success', 'Kategori "'.$validated['name'].'" berhasil ditambahkan.');
    }

    public function edit(ItemCategory $itemCategory): View
    {
        return view('master.item_categories.edit', ['category' => $itemCategory]);
    }

    public function update(Request $request, ItemCategory $itemCategory): RedirectResponse
    {
        $validated = $this->validateCategory($request, $itemCategory);

        // Code kategori bawaan dikunci: nilainya sudah tersimpan sebagai snapshot
        // di baris material lama dan di-hardcode di alur dropcore otomatis.
        // Mengubahnya memutus dua-duanya sekaligus tanpa error yang kelihatan.
        if ($itemCategory->is_system) {
            unset($validated['code']);
        }

        $itemCategory->update($validated);
        ItemCategory::flushLabelCache();

        return redirect()
            ->route('master.item-categories.index')
            ->with('success', 'Kategori "'.$itemCategory->name.'" berhasil diperbarui.');
    }

    public function toggleStatus(ItemCategory $itemCategory): RedirectResponse
    {
        // Kategori bawaan boleh dinonaktifkan (mis. ISP ini tidak pakai radio),
        // KECUALI "lainnya": itu tujuan jatuh terakhir buat barang di luar
        // master, dan tanpa dia baris manual kehilangan kategori.
        if ($itemCategory->code === ItemCategory::CODE_LAINNYA && $itemCategory->is_active) {
            return back()->with('error', 'Kategori "Lainnya" tidak bisa dinonaktifkan — dipakai sebagai kategori jatuh terakhir untuk barang di luar master.');
        }

        $itemCategory->update(['is_active' => ! $itemCategory->is_active]);
        ItemCategory::flushLabelCache();

        $statusText = $itemCategory->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "Kategori \"{$itemCategory->name}\" berhasil {$statusText}.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCategory(Request $request, ?ItemCategory $category = null): array
    {
        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                // Code masuk URL filter & disimpan sebagai snapshot — dibatasi
                // huruf kecil/angka/underscore biar tidak ada spasi & kapital
                // yang bikin dua kategori terlihat sama tapi tak pernah cocok.
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('item_categories', 'code')->ignore($category),
            ],
            'name' => ['required', 'string', 'max:100'],
            'default_unit' => ['required', 'string', 'max:20'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['required', 'boolean'],
        ], [
            'code.regex' => 'Kode kategori hanya boleh huruf kecil, angka, dan underscore.',
        ]);
    }
}
