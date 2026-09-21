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

            // Sub-konfigurasi identitas SERIALIZED, dikunci bareng tracking_type
            // (bukan operasional lepas kayak meter_per_roll) — begitu barang
            // udah punya pergerakan, sumber SN-nya (manual vs auto-generate)
            // gak boleh ganti diam-diam.
            $rules['auto_generate_serial'] = $request->input('tracking_type') === TrackingType::SERIALIZED->value
                ? ['required', 'boolean']
                : ['prohibited'];
        }

        // Konversi Roll→Meter + ambang "Sisa Kecil" — cuma relevan buat
        // tracking_type=roll, TAPI kedua kolom TETAP boleh diedit walau
        // tracking_type-nya udah locked (ini konfigurasi operasional, bukan
        // sumbu cara-hitung-stok yang dikunci `$locked`). `meter_per_roll`
        // WAJIB begitu tracking_type roll dipilih — tanpa ini
        // `InventoryReceiveService::receiveRoll()` bakal nolak generate roll
        // sama sekali (ketauan gap 2026-09-15: kolom+service udah ada dari
        // awal fitur ROLL dibangun, tapi form Master Barang gak pernah kasih
        // jalan buat ngisinya). `minimum_length` OPSIONAL — ambang "roll sisa
        // kecil nganggur" baru berarti kalau admin tau angka yang masuk akal
        // buat jenis kabel itu, gak dipaksa.
        $isRollType = $locked
            ? $item?->tracking_type === TrackingType::ROLL
            : ($request->input('tracking_type') === TrackingType::ROLL->value);

        if ($isRollType) {
            $rules['meter_per_roll'] = ['required', 'numeric', 'min:0.01'];
            $rules['minimum_length'] = ['nullable', 'numeric', 'min:0.01', 'lt:meter_per_roll'];
        } else {
            $rules['meter_per_roll'] = ['prohibited'];
            $rules['minimum_length'] = ['prohibited'];
        }

        $validated = $request->validate($rules);

        // Server-side, jangan cuma percaya field `unit` disabled di klien
        // (bisa diakalin lewat devtools) — roll SELALU meter, gak ada jalan
        // lain (koreksi 2026-09-16, akar laporan "tampilan 1.000 roll
        // seharusnya 1.000 meter": item lama sempat kesimpen unit="roll"
        // gara-gara placeholder form yang dulu nyaranin itu).
        if ($isRollType) {
            $validated['unit'] = 'meter';
        } else {
            $validated['meter_per_roll'] = null;
            $validated['minimum_length'] = null;
        }

        if (! $locked) {
            // Qty/Batch gak relevan sama sumbu kepemilikan (cuma SERIALIZED yang
            // bisa jadi Aset Perusahaan) — dipaksa installable biar gak ada
            // kombinasi ganjil "kabel drum berstatus aset perusahaan".
            $trackingType = TrackingType::from($validated['tracking_type']);
            $validated['ownership_mode'] = $trackingType === TrackingType::SERIALIZED
                ? ($validated['ownership_mode'] ?? OwnershipMode::INSTALLABLE->value)
                : OwnershipMode::INSTALLABLE->value;

            // Non-SERIALIZED gak punya konsep sumber SN sama sekali — paksa
            // false, bukan cuma andalkan `prohibited` (kolom tetap kudu keisi
            // buat `Item::create()`/`update()`).
            $validated['auto_generate_serial'] = $trackingType === TrackingType::SERIALIZED
                ? (bool) $validated['auto_generate_serial']
                : false;
        }

        return $validated;
    }
}
