<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\InventoryTransactionType;
use App\Enums\RollStatus;
use App\Enums\SerialStatus;
use App\Enums\TrackingType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Warehouse\Concerns\AuthorizesWarehousePop;
use App\Http\Controllers\Warehouse\Concerns\ResolvesSelectedPop;
use App\Models\InventoryBalance;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\User;
use App\Services\EffectiveAccessService;
use App\Services\WarehouseStockBoardService;
use App\Support\BoardFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
    use ResolvesSelectedPop;

    public function index(Request $request, EffectiveAccessService $access): View|RedirectResponse
    {
        if (in_array($request->query('view'), ['rincian', 'ringkasan'], true)) {
            $query = ['view' => 'papan'];
            foreach (['search', 'pop_id', 'category_id', 'tracking', 'low_stock_only'] as $key) {
                if ($request->query->has($key) && is_scalar($request->query($key))) {
                    $query[$key] = $request->query($key);
                }
            }

            if ($request->query->has('pop_id') && $request->query('pop_id') === null) {
                $query['pop_id'] = '';
            }

            $oldTracking = $request->query('tracking_type');
            if (! isset($query['tracking']) && in_array($oldTracking, array_column(TrackingType::cases(), 'value'), true)) {
                $query['tracking'] = $oldTracking;
            }

            return redirect()->route('warehouse.stock.index', $query);
        }

        $user = auth()->user();
        $pops = Pop::query()
            ->warehouse()
            ->with(['gudangPics' => fn ($q) => $q->where('status', 'active')->orderBy('name')])
            ->when(! $access->hasAllPopAccess($user), fn ($query) => $query->whereIn('id', $access->getAllowedPopIds($user)))
            ->orderBy('type')
            ->orderBy('name')
            ->get();
        $popIds = $pops->pluck('id');

        $hasAllAccess = $access->hasAllPopAccess($user);
        $lockedSinglePop = ! $hasAllAccess && $pops->count() === 1 ? $pops->first() : null;
        $modeLabel = match (true) {
            $hasAllAccess => 'Semua Gudang (Nasional)',
            $lockedSinglePop !== null => $lockedSinglePop->name.' ('.strtoupper($lockedSinglePop->type).')',
            default => $pops->count().' Gudang Terjangkau',
        };

        $popFilter = $this->resolveSelectedPopId($request, $access, $user);

        return $this->board($request, $user, $pops, $popIds, $popFilter, $lockedSinglePop, $modeLabel, $request->query('view') === 'matrix');
    }

    /**
     * Mode papan. `$popIds` sudah hasil POP scope (EffectiveAccessService) di
     * index(); `$popFilter` hanya menyempitkan — id di luar scope membuat
     * build() kosong, bukan membuka data gudang itu.
     *
     * Izin aksi dihitung di sini (bukan di Blade seperti tampilan lama) supaya
     * partial papan — dan menu aksi Task 5 — menerima satu sumber yang sama.
     *
     * @param  Collection<int, Pop>  $pops
     * @param  Collection<int, int>  $popIds
     */
    private function board(Request $request, User $user, Collection $pops, Collection $popIds, ?int $popFilter, ?Pop $lockedSinglePop, string $modeLabel, bool $asMatrix = false): View
    {
        $boardFilters = BoardFilters::fromRequest($request, $popFilter);
        $board = app(WarehouseStockBoardService::class);

        if ($asMatrix) {
            $matrix = $board->matrix($popIds, $boardFilters);
            // emptyState() membaca `sku_count` per gudang; untuk matriks cukup
            // satu "gudang" semu yang berisi jumlah barang — alasan kosongnya
            // (no_access/no_match/no_stock) tetap satu sumber.
            $groups = collect([['sku_count' => $matrix['categories']->sum(fn (array $c) => $c['rows']->count())]]);
        } else {
            $groups = $board->build($popIds, $boardFilters);
        }

        return view('warehouse.stock.index', [
            'viewMode' => $asMatrix ? 'matrix' : 'papan',
            'groups' => $asMatrix ? collect() : $groups,
            'matrix' => $matrix ?? null,
            'searchTruncated' => $asMatrix ? $matrix['search_truncated'] : null,
            'emptyState' => $board->emptyState($popIds, $groups, $boardFilters),
            'boardFilters' => $boardFilters,
            'categories' => ItemCategory::active()->ordered()->get(),
            'pops' => $pops,
            'lockedSinglePop' => $lockedSinglePop,
            'modeLabel' => $modeLabel,
            'canAdjust' => $user->hasPermission('warehouse_adjustment.create'),
            'canTransfer' => $user->hasPermission('warehouse_transfer.create'),
            'canIssue' => $user->hasPermission('warehouse_issue.create'),
            'canRequestStock' => $user->hasPermission('warehouse_stock_request.create'),
        ]);
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
