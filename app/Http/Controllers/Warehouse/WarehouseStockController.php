<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\InventoryTransactionType;
use App\Enums\SerialStatus;
use App\Enums\TrackingType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Warehouse\Concerns\AuthorizesWarehousePop;
use App\Models\InventoryBalance;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\Pop;
use App\Services\EffectiveAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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

        // Indikator Mode + gating Quick Action Bar (rancangan-layout.md §3.3-A)
        // — MURNI informasional/kemudahan link, BUKAN cara baru buat pilih
        // scope (scope tetap 100% dari EffectiveAccessService, gak ada
        // dropdown yang mengubah apa yang di-query).
        $hasAllAccess = $access->hasAllPopAccess($user);
        $canActAsPusat = $hasAllAccess || $pops->contains('type', 'pusat');
        $canActAsCabang = $hasAllAccess || $pops->contains('type', 'cabang');
        $lockedSinglePop = ! $hasAllAccess && $pops->count() === 1 ? $pops->first() : null;
        $modeLabel = match (true) {
            $hasAllAccess => 'Semua Gudang (Nasional)',
            $lockedSinglePop !== null => $lockedSinglePop->name.' ('.strtoupper($lockedSinglePop->type).')',
            default => $pops->count().' Gudang Terjangkau',
        };

        $popFilter = $request->integer('pop_id') ?: null;
        $search = trim((string) $request->query('search', ''));
        $lowStockOnly = $request->boolean('low_stock_only');
        $trackingFilter = $request->query('tracking_type');
        $trackingFilter = in_array($trackingFilter, array_column(TrackingType::cases(), 'value'), true) ? $trackingFilter : null;

        // Barang QUANTITY/BATCH — saldo asli dari `inventory_balances`
        // (ditulis InventoryReceiveService::receiveQuantity() dkk).
        $quantityBalances = InventoryBalance::query()
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
            // Jenis Tracking difilter lewat relasi item (kolomnya di
            // `items.tracking_type`, bukan di `inventory_balances`).
            ->when($trackingFilter, function ($q) use ($trackingFilter) {
                $q->whereHas('item', fn ($itemQuery) => $itemQuery->where('tracking_type', $trackingFilter));
            })
            ->with(['item.category', 'pop'])
            ->get();

        // Barang SERIALIZED (modem/ONT via SN) — 2026-09-07, laporan user
        // "modem input by SN gak masuk Kelola Stok". `receiveSerialized()`
        // CUMA nulis ke `inventory_serials`, gak pernah nyentuh
        // `inventory_balances` sama sekali — jadi item serialized SELALU
        // absen dari tabel di atas, padahal view-nya udah py logic render
        // badge SN (kolomnya nunggu data yang gak pernah dateng). Di sini
        // dihitung count(*) AVAILABLE per gudang+item lalu dibungkus jadi
        // instance InventoryBalance SINTETIS (gak disimpan — cuma dipakai
        // biar Blade & `isLowStock()` kepake apa adanya, gak perlu view
        // baru/logic bercabang).
        $serializedBalances = collect();
        if ($trackingFilter === null || $trackingFilter === TrackingType::SERIALIZED->value) {
            $serialGroups = InventorySerial::query()
                ->whereIn('current_pop_id', $popIds)
                ->when($popFilter, fn ($q) => $q->where('current_pop_id', $popFilter))
                ->where('status', SerialStatus::AVAILABLE->value)
                ->when($search !== '', function ($q) use ($search) {
                    $q->whereHas('item', function ($itemQuery) use ($search) {
                        $itemQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
                })
                ->selectRaw('current_pop_id as pop_id, item_id, COUNT(*) as qty')
                ->groupBy('current_pop_id', 'item_id')
                ->get();

            if ($serialGroups->isNotEmpty()) {
                $itemsById = Item::with('category')->whereIn('id', $serialGroups->pluck('item_id')->unique())->get()->keyBy('id');
                $popsById = $pops->keyBy('id');

                // Threshold (minimum_stock) buat item serialized DISIMPAN di
                // `inventory_balances` juga (lot_no='', qty tetap 0 — cuma
                // wadah angka ambang, lihat storeThreshold()) — ambil di
                // sini biar badge "Stok Rendah" tetap kepake buat serialized.
                $thresholds = InventoryBalance::query()
                    ->whereIn('pop_id', $serialGroups->pluck('pop_id')->unique())
                    ->whereIn('item_id', $serialGroups->pluck('item_id')->unique())
                    ->where('lot_no', '')
                    ->get(['pop_id', 'item_id', 'minimum_stock', 'maximum_stock'])
                    ->keyBy(fn ($row) => $row->pop_id.'-'.$row->item_id);

                $serializedBalances = $serialGroups->map(function ($group) use ($itemsById, $popsById, $thresholds) {
                    $key = $group->pop_id.'-'.$group->item_id;
                    $threshold = $thresholds->get($key);

                    $balance = new InventoryBalance([
                        'pop_id' => $group->pop_id,
                        'item_id' => $group->item_id,
                        'lot_no' => '',
                        'qty' => $group->qty,
                        'minimum_stock' => $threshold?->minimum_stock,
                        'maximum_stock' => $threshold?->maximum_stock,
                    ]);
                    $balance->setRelation('item', $itemsById->get($group->item_id));
                    $balance->setRelation('pop', $popsById->get($group->pop_id));

                    return $balance;
                });

                if ($lowStockOnly) {
                    $serializedBalances = $serializedBalances->filter(fn ($b) => $b->isLowStock())->values();
                }
            }
        }

        // Gabung dua sumber jadi SATU list — ini yang bikin item serialized
        // AKHIRNYA muncul di Kelola Stok. Paginasi di-handle manual di PHP
        // (bukan DB::paginate() lagi) karena datanya sekarang gabungan 2
        // query beda tabel — wajar buat skala jumlah SKU gudang ISP lokal
        // (puluhan-ratusan per gudang, bukan jutaan baris).
        $merged = $quantityBalances->concat($serializedBalances)
            ->sortBy([['pop_id', 'asc'], ['item_id', 'asc']])
            ->values();

        $perPage = 25;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $balances = new LengthAwarePaginator(
            $merged->slice(($page - 1) * $perPage, $perPage)->values(),
            $merged->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

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

        return view('warehouse.stock.index', compact(
            'pops', 'balances', 'popFilter', 'search', 'lowStockOnly', 'trackingFilter', 'lastOpnameByKey',
            'canActAsPusat', 'canActAsCabang', 'lockedSinglePop', 'modeLabel'
        ));
    }

    /**
     * Daftar SN AVAILABLE buat 1 kombinasi gudang+item (dipanggil AJAX dari
     * badge "SERIAL NUMBER" di baris Kelola Stok — sebelumnya kolom Qty
     * Tersedia cuma nunjuk ANGKA agregat, gak ada cara lihat SN mana aja
     * konkretnya tanpa buka Traceability satu-satu).
     *
     * Discope sama pola `AuthorizesWarehousePop` — pop_id di luar scope
     * ditolak keras (beda dari index() yang cuma "gak match apa-apa"),
     * karena endpoint ini nembak 1 gudang spesifik atas permintaan eksplisit
     * klien, bukan filter list yang aman diam-diam dikosongkan.
     */
    public function serials(Request $request, EffectiveAccessService $access): JsonResponse
    {
        $validated = $request->validate([
            'pop_id' => 'required|integer|exists:pops,id',
            'item_id' => 'required|integer|exists:items,id',
        ]);

        $pop = Pop::findOrFail($validated['pop_id']);
        $this->assertPopInScope($pop, auth()->user(), $access);

        $serials = InventorySerial::query()
            ->where('current_pop_id', $pop->id)
            ->where('item_id', $validated['item_id'])
            ->where('status', SerialStatus::AVAILABLE->value)
            ->orderBy('serial_number')
            ->limit(200) // pengaman tampilan — bukan pagination, cukup buat quick-look
            ->pluck('serial_number');

        return response()->json(['serials' => $serials]);
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
