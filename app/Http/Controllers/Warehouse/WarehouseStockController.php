<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\InventoryTransactionType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Warehouse\Concerns\AuthorizesWarehousePop;
use App\Models\InventoryBalance;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\Pop;
use App\Services\EffectiveAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Management Stock (koreksi IA Gudang, 2026-09-03) — hub tunggal buat lihat
 * stok Pusat→Cabang sekaligus jadi titik masuk 4 aksi (Tambah Barang/
 * Receive, Penyesuaian Stok/Adjustment, Transfer ke Gudang Lain, Serahkan ke
 * Teknisi/Issue). Sebelumnya 4 aksi ini nav item terpisah + tabel stok
 * mentah tanpa filter/pagination numpuk di Dashboard (`WarehouseController`)
 * — user ngerasa kebanyakan menu, jadi digabung SATU halaman.
 *
 * Cuma VIEW — permission reuse `warehouse.view` (sama kayak Dashboard),
 * BUKAN permission baru. 4 tombol aksi di view tetap masing-masing digerbangi
 * permission aslinya sendiri (warehouse_transfer.create dst) — controller di
 * balik tombol itu TIDAK berubah, cuma dipindah titik aksesnya.
 *
 * Discope sama persis pola `WarehouseController` — query balance dibatasi ke
 * POP dalam scope aktor, BUKAN 403 kalau filter `pop_id` di luar scope
 * (list controller: filter di luar scope cukup gak match apa-apa, beda dari
 * controller mutasi yang wajib tolak keras — lihat `AuthorizesWarehousePop`).
 */
class WarehouseStockController extends Controller
{
    use AuthorizesWarehousePop;

    public function index(Request $request, EffectiveAccessService $access): View
    {
        $user = auth()->user();

        $pops = Pop::query()
            ->warehouse()
            ->when(! $access->hasAllPopAccess($user), fn ($q) => $q->whereIn('id', $access->getAllowedPopIds($user)))
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $popIds = $pops->pluck('id');

        $popFilter = $request->integer('pop_id') ?: null;
        $search = trim((string) $request->query('search', ''));
        $lowStockOnly = $request->boolean('low_stock_only');

        $balances = InventoryBalance::query()
            ->whereIn('pop_id', $popIds) // scope dulu, baru filter user — pop_id di luar scope otomatis gak match
            ->when($popFilter, fn ($q) => $q->where('pop_id', $popFilter))
            ->where('qty', '>', 0)
            ->when($search !== '', function ($q) use ($search) {
                $q->whereHas('item', function ($itemQuery) use ($search) {
                    $itemQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($lowStockOnly, fn ($q) => $q->lowStock())
            ->with(['item.category', 'pop'])
            ->orderBy('pop_id')
            ->orderBy('item_id')
            ->paginate(25)
            ->withQueryString();

        // "Opname terakhir per item per gudang" (Fase 2 P1, gap #3 —
        // kontrol-anti-manipulasi.md §5). Query dibatasi ke kombinasi
        // pop_id+item_id+lot_no yang KEBETULAN tampil di halaman ini
        // (bukan full-table scan) — cukup murah karena cuma jalan per
        // halaman paginated, bukan per baris N+1.
        $lastOpnameByKey = [];
        if ($balances->isNotEmpty()) {
            $pairs = $balances->getCollection()->map(fn ($b) => [
                'pop_id' => $b->pop_id, 'item_id' => $b->item_id, 'lot_no' => $b->lot_no,
            ]);

            $opnameRows = InventoryTransaction::query()
                ->where('type', InventoryTransactionType::STOCK_OPNAME->value)
                ->whereIn('to_pop_id', $pairs->pluck('pop_id')->unique())
                ->whereIn('item_id', $pairs->pluck('item_id')->unique())
                ->selectRaw('to_pop_id, item_id, lot_no, MAX(created_at) as last_opname_at')
                ->groupBy('to_pop_id', 'item_id', 'lot_no')
                ->get();

            foreach ($opnameRows as $row) {
                $key = $row->to_pop_id.'-'.$row->item_id.'-'.($row->lot_no ?? '');
                $lastOpnameByKey[$key] = $row->last_opname_at;
            }
        }

        return view('warehouse.stock.index', compact('pops', 'balances', 'popFilter', 'search', 'lowStockOnly', 'lastOpnameByKey'));
    }

    /**
     * Atur ambang Stok Rendah (`minimum_stock`/`maximum_stock` di
     * `InventoryBalance`) — celah yang ketauan user (2026-09-03): kolomnya
     * ADA & dipakai `isLowStock()`/badge "Stok Rendah", tapi sebelum ini
     * GAK ADA form manapun buat ngisinya, jadi badge itu praktis gak pernah
     * nyala (`minimum_stock` selalu null).
     *
     * SENGAJA bukan lewat `InventoryAdjustmentService`/ledger — ini
     * KONFIGURASI ambang (kapan dianggap "rendah"), bukan PERGERAKAN barang.
     * `qty` di baris yang sama tetap cuma boleh berubah lewat Service
     * (ledger-backed) — di sini cuma `minimum_stock`/`maximum_stock` yang
     * disentuh, `qty` gak pernah kena update.
     */
    public function createThreshold(Request $request, EffectiveAccessService $access): View
    {
        $user = auth()->user();

        $pops = Pop::query()->warehouse()
            ->when(! $access->hasAllPopAccess($user), fn ($q) => $q->whereIn('id', $access->getAllowedPopIds($user)))
            ->orderBy('type')->orderBy('name')->get();
        $items = Item::active()->with('category')->orderBy('name')->get();

        $currentBalance = null;
        if ($request->filled('pop_id') && $request->filled('item_id')) {
            $currentBalance = InventoryBalance::where('pop_id', $request->query('pop_id'))
                ->where('item_id', $request->query('item_id'))
                ->where('lot_no', $request->query('lot_no', ''))
                ->first();
        }

        return view('warehouse.stock.threshold', compact('pops', 'items', 'currentBalance'));
    }

    public function storeThreshold(Request $request, EffectiveAccessService $access): RedirectResponse
    {
        $validated = $request->validate([
            'pop_id' => 'required|integer|exists:pops,id',
            'item_id' => 'required|integer|exists:items,id',
            'lot_no' => 'nullable|string|max:50',
            'minimum_stock' => 'nullable|numeric|min:0',
            'maximum_stock' => 'nullable|numeric|min:0|gte:minimum_stock',
        ]);

        $pop = Pop::findOrFail($validated['pop_id']);
        $this->assertPopInScope($pop, auth()->user(), $access);

        $lotNo = $validated['lot_no'] ?? '';

        InventoryBalance::updateOrCreate(
            ['pop_id' => $pop->id, 'item_id' => $validated['item_id'], 'lot_no' => $lotNo],
            ['minimum_stock' => $validated['minimum_stock'] ?? null, 'maximum_stock' => $validated['maximum_stock'] ?? null]
        );

        return redirect()->route('warehouse.stock.index')->with('success', 'Ambang stok rendah tersimpan.');
    }
}
