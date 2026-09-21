<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\InventoryTransactionType;
use App\Enums\RollStatus;
use App\Enums\SerialStatus;
use App\Enums\TrackingType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Warehouse\Concerns\AuthorizesWarehousePop;
use App\Models\InventoryBalance;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
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
        $categoryFilter = $request->integer('category_id') ?: null;
        $itemFilter = $request->integer('item_id') ?: null;
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
            ->when($itemFilter, fn ($q) => $q->where('item_id', $itemFilter))
            ->when($categoryFilter, function ($q) use ($categoryFilter) {
                $q->whereHas('item', fn ($itemQuery) => $itemQuery->where('item_category_id', $categoryFilter));
            })
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
                ->when($itemFilter, fn ($q) => $q->where('item_id', $itemFilter))
                ->when($categoryFilter, function ($q) use ($categoryFilter) {
                    $q->whereHas('item', fn ($itemQuery) => $itemQuery->where('item_category_id', $categoryFilter));
                })
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

        // Barang ROLL (kabel per-roll via `InventoryReceiveService::receiveRoll()`)
        // — sama gap persis yang dulu kejadian ke SERIALIZED (2026-09-07):
        // `receiveRoll()` CUMA nulis ke `inventory_rolls`, gak pernah nyentuh
        // `inventory_balances`, jadi barang yang UDAH diterima ke Gudang Pusat
        // gak pernah muncul di Kelola Stok (laporan user 2026-09-16). Pola
        // SAMA persis $serializedBalances: agregat SINTETIS per pop+item,
        // qty = SUM(length_remaining) dalam METER (bukan hitung roll) — biar
        // "Jumlah Tersedia" langsung kebaca sebagai stok fisik kabel, bukan
        // "berapa roll". Cuma roll yang MASIH DI GUDANG (current_pop_id
        // keisi, status AVAILABLE/RECEIVED) yang ikut dihitung — roll yang
        // lagi di custody teknisi bukan stok gudang lagi.
        $rollBalances = collect();
        if ($trackingFilter === null || $trackingFilter === TrackingType::ROLL->value) {
            $rollGroups = InventoryRoll::query()
                ->whereIn('current_pop_id', $popIds)
                ->when($popFilter, fn ($q) => $q->where('current_pop_id', $popFilter))
                ->whereIn('status', [RollStatus::AVAILABLE->value, RollStatus::RECEIVED->value])
                ->when($itemFilter, fn ($q) => $q->where('item_id', $itemFilter))
                ->when($categoryFilter, function ($q) use ($categoryFilter) {
                    $q->whereHas('item', fn ($itemQuery) => $itemQuery->where('item_category_id', $categoryFilter));
                })
                ->when($search !== '', function ($q) use ($search) {
                    $q->whereHas('item', function ($itemQuery) use ($search) {
                        $itemQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
                })
                ->selectRaw('current_pop_id as pop_id, item_id, SUM(length_remaining) as qty')
                ->groupBy('current_pop_id', 'item_id')
                ->get();

            if ($rollGroups->isNotEmpty()) {
                $itemsById = Item::with('category')->whereIn('id', $rollGroups->pluck('item_id')->unique())->get()->keyBy('id');
                $popsById = $pops->keyBy('id');

                $thresholds = InventoryBalance::query()
                    ->whereIn('pop_id', $rollGroups->pluck('pop_id')->unique())
                    ->whereIn('item_id', $rollGroups->pluck('item_id')->unique())
                    ->where('lot_no', '')
                    ->get(['pop_id', 'item_id', 'minimum_stock', 'maximum_stock'])
                    ->keyBy(fn ($row) => $row->pop_id.'-'.$row->item_id);

                $rollBalances = $rollGroups->map(function ($group) use ($itemsById, $popsById, $thresholds) {
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
                    $rollBalances = $rollBalances->filter(fn ($b) => $b->isLowStock())->values();
                }
            }
        }

        // Gabung TIGA sumber jadi SATU list — ini yang bikin item serialized/
        // roll AKHIRNYA muncul di Kelola Stok. Paginasi di-handle manual di
        // PHP (bukan DB::paginate() lagi) karena datanya sekarang gabungan
        // beberapa query beda tabel — wajar buat skala jumlah SKU gudang ISP
        // lokal (puluhan-ratusan per gudang, bukan jutaan baris).
        $merged = $quantityBalances->concat($serializedBalances)->concat($rollBalances)
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

        // Harga per lot barang QUANTITY (ADHOC-75, 2026-09-16) — Kelola Stok
        // sebelumnya cuma nunjuk qty polos, jadi 2 baris lot (Harga Lama/Baru)
        // gak bisa dibedain staf sama sekali dari tampilan. Last-cost per
        // `(item_id, lot_no)` dari ledger RECEIVE, dibatasi ke item yang
        // KEBETULAN tampil di halaman ini — pola sama `$lastOpnameByKey` di
        // atas, bukan full-table scan.
        $lastPriceByKey = [];
        $multiLotPopItemKeys = [];
        if ($balances->isNotEmpty()) {
            $quantityRows = $balances->getCollection()
                ->filter(fn ($b) => $b->item->tracking_type === TrackingType::QUANTITY);

            // Label "Harga Lama"/"Harga Baru" cuma masuk akal kalau barang
            // itu BENERAN lagi punya 2 lot aktif di gudang yang sama —
            // barang yang cuma py 1 lot (belum pernah ganti harga) tampil
            // harga polos aja, gak usah dilabel "Lama" (nyesatkan, kesannya
            // ada "Baru" yang lain padahal enggak).
            $multiLotPopItemKeys = $quantityRows
                ->countBy(fn ($b) => $b->pop_id.'-'.$b->item_id)
                ->filter(fn ($count) => $count > 1)
                ->keys()->flip()->all();

            $quantityItemIds = $quantityRows->pluck('item_id')->unique();

            if ($quantityItemIds->isNotEmpty()) {
                $priceRows = InventoryTransaction::query()
                    ->where('type', InventoryTransactionType::RECEIVE->value)
                    ->whereIn('item_id', $quantityItemIds)
                    ->whereNotNull('unit_price_snapshot')
                    ->orderBy('id')
                    ->get(['item_id', 'lot_no', 'unit_price_snapshot']);

                foreach ($priceRows as $row) {
                    // Diurutkan ASC lalu ditimpa terus — baris TERAKHIR yang
                    // ke-assign menang, itu last-cost per lot yang bener.
                    $lastPriceByKey[$row->item_id.'-'.($row->lot_no ?? '')] = (float) $row->unit_price_snapshot;
                }
            }
        }

        $categories = ItemCategory::active()->ordered()->get();
        $items = Item::query()
            ->where(function ($q) use ($popIds, $itemFilter) {
                $q->whereHas('inventoryBalances', fn ($b) => $b->whereIn('pop_id', $popIds)->where('qty', '>', 0))
                    ->orWhereHas('inventorySerials', fn ($s) => $s->whereIn('current_pop_id', $popIds)->where('status', SerialStatus::AVAILABLE->value))
                    ->orWhereHas('inventoryRolls', fn ($r) => $r->whereIn('current_pop_id', $popIds)->whereIn('status', [RollStatus::AVAILABLE->value, RollStatus::RECEIVED->value]));

                if ($itemFilter) {
                    $q->orWhere('id', $itemFilter);
                }
            })
            ->when($categoryFilter, fn ($q) => $q->where('item_category_id', $categoryFilter))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->with('category')
            ->orderBy('name')
            ->get();

        return view('warehouse.stock.index', compact(
            'pops', 'categories', 'items', 'balances', 'popFilter', 'categoryFilter', 'itemFilter', 'search', 'lowStockOnly', 'trackingFilter', 'lastOpnameByKey', 'lastPriceByKey', 'multiLotPopItemKeys',
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
            ->get(['id', 'serial_number', 'created_at', 'condition', 'condition_checked_at']);

        // Harga per SN gak disimpan di `inventory_serials` sendiri (cuma
        // snapshot di ledger) — diambil dari baris RECEIVE pertama SN itu,
        // sejalan `resolveLastCost()` di Service (last-cost dari ledger,
        // bukan tabel harga terpisah).
        $prices = InventoryTransaction::query()
            ->whereIn('serial_id', $serials->pluck('id'))
            ->where('type', InventoryTransactionType::RECEIVE->value)
            ->pluck('unit_price_snapshot', 'serial_id');

        $result = $serials->map(fn ($s) => [
            'serial_number' => $s->serial_number,
            'unit_price_snapshot' => $prices->get($s->id) !== null ? (float) $prices->get($s->id) : null,
            'received_at' => $s->created_at?->translatedFormat('d M Y'),
            // Badge Kondisi (analisa-gap-kondisi-barang.md poin 8) — SN
            // gak lolos Issue kalau bekas & belum dicek, jadi admin gudang
            // perlu lihat ini dari daftar quick-look sebelum ngarahin staf.
            'condition' => $s->condition?->value ?? 'new',
            'condition_checked' => $s->condition_checked_at !== null,
        ])->values();

        return response()->json(['serials' => $result]);
    }

    /**
     * Padanan `serials()` buat roll kabel — dipicu badge "ROLL KABEL" di
     * baris Kelola Stok. Balikin `roll_code`+`length_remaining` (bukan cuma
     * kode doang kayak SN) karena sisa meter per roll itu informasinya,
     * bukan cuma identitas.
     */
    public function rolls(Request $request, EffectiveAccessService $access): JsonResponse
    {
        $validated = $request->validate([
            'pop_id' => 'required|integer|exists:pops,id',
            'item_id' => 'required|integer|exists:items,id',
        ]);

        $pop = Pop::findOrFail($validated['pop_id']);
        $this->assertPopInScope($pop, auth()->user(), $access);

        $item = Item::findOrFail($validated['item_id']);
        $meterPerRoll = (float) $item->meter_per_roll;

        $rolls = InventoryRoll::query()
            ->where('current_pop_id', $pop->id)
            ->where('item_id', $validated['item_id'])
            ->whereIn('status', [RollStatus::AVAILABLE->value, RollStatus::RECEIVED->value])
            ->orderBy('roll_code')
            ->limit(200)
            ->get(['roll_code', 'length_remaining', 'length_total', 'unit_price_snapshot', 'vendor', 'received_at'])
            ->map(function ($roll) use ($meterPerRoll) {
                $pricePerMeter = $roll->unit_price_snapshot !== null ? (float) $roll->unit_price_snapshot : null;
                $pricePerRoll = $pricePerMeter !== null && $meterPerRoll > 0 ? $pricePerMeter * $meterPerRoll : null;
                $totalValue = $pricePerMeter !== null ? (float) $roll->length_remaining * $pricePerMeter : null;

                return [
                    'roll_code' => $roll->roll_code,
                    'length_remaining' => (float) $roll->length_remaining,
                    'length_total' => (float) $roll->length_total,
                    'meter_per_roll' => $meterPerRoll,
                    'unit_price_snapshot' => $pricePerMeter,
                    'price_per_meter' => $pricePerMeter,
                    'price_per_roll' => $pricePerRoll,
                    'total_value' => $totalValue,
                    'vendor' => $roll->vendor,
                    'received_at' => $roll->received_at?->translatedFormat('d M Y'),
                ];
            });

        return response()->json(['rolls' => $rolls]);
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
