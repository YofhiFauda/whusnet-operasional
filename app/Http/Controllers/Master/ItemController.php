<?php

namespace App\Http\Controllers\Master;

use App\Enums\OwnershipMode;
use App\Enums\TrackingType;
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
        $trackingTypes = TrackingType::cases();
        $ownershipModes = OwnershipMode::cases();

        return view('master.items.create', compact('categories', 'trackingTypes', 'ownershipModes'));
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
        $trackingTypes = TrackingType::cases();
        $ownershipModes = OwnershipMode::cases();
        $trackingTypeLocked = $item->inventoryTransactions()->exists();

        return view('master.items.edit', compact('item', 'categories', 'trackingTypes', 'ownershipModes', 'trackingTypeLocked'));
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
        // Barang yang udah punya pergerakan ledger (RECEIVE/TRANSFER/ISSUE dst)
        // KUNCI tracking_type/ownership_mode-nya — ganti cara hitung stok
        // barang yang udah py saldo/custody/SN berjalan bikin data yang ada
        // gak konsisten sama definisi barunya (mis. SN yang udah kepegang
        // teknisi tiba-tiba "bukan serialized lagi"). Barang baru (belum ada
        // pergerakan) bebas dipilih.
        $locked = $item && $item->inventoryTransactions()->exists();

        $rules = [
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
        ];

        if (! $locked) {
            $rules['tracking_type'] = ['required', Rule::enum(TrackingType::class)];
            $rules['ownership_mode'] = ['nullable', Rule::enum(OwnershipMode::class)];
        }

        $validated = $request->validate($rules);

        if (! $locked) {
            // Qty/Batch gak relevan sama sumbu kepemilikan (cuma SERIALIZED yang
            // bisa jadi Aset Perusahaan) — dipaksa installable biar gak ada
            // kombinasi ganjil "kabel drum berstatus aset perusahaan".
            $trackingType = TrackingType::from($validated['tracking_type']);
            $validated['ownership_mode'] = $trackingType === TrackingType::SERIALIZED
                ? ($validated['ownership_mode'] ?? OwnershipMode::INSTALLABLE->value)
                : OwnershipMode::INSTALLABLE->value;
        }

        return $validated;
    }
}
