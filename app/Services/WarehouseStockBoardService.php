<?php

namespace App\Services;

use App\Enums\InventoryTransactionType;
use App\Enums\RollStatus;
use App\Enums\SerialStatus;
use App\Enums\TrackingType;
use App\Models\InventoryBalance;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Support\BoardFilters;
use App\Support\LikeSearch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-model papan Kelola Stok: Gudang → Kategori → Barang.
 *
 * Murni baca. Angka "tersedia" diambil dari `WarehouseStockPositionService::
 * availableByPopAndItem()` — sumber yang sama dengan layar lama — sehingga
 * custody teknisi, barang dalam perjalanan, dan unit rusak/karantina TIDAK ikut
 * terhitung. Service ini hanya menambah pengelompokan, ambang, harga, dan
 * ringkasan; ia tidak menghitung stok sendiri.
 *
 * `$popIds` WAJIB sudah lolos POP scope (`EffectiveAccessService`) oleh
 * pemanggil. `BoardFilters::$popId` hanya boleh MENYEMPITKAN scope itu: id di
 * luar `$popIds` menghasilkan koleksi kosong, bukan data gudang tersebut.
 */
class WarehouseStockBoardService
{
    /** Batas pasangan (gudang, barang) hasil pencarian SN/roll: pengaman beban, bukan paginasi. */
    private const SEARCH_PAIR_CAP = 50;

    public function __construct(private readonly WarehouseStockPositionService $position) {}

    /**
     * @param  Collection<int, int>  $popIds  POP dalam scope aktor.
     * @return Collection<int, array{pop: Pop, sku_count: int, low_count: int, search_truncated: bool, categories: Collection<int, array{category: ItemCategory|null, sku_count: int, rows: Collection<int, array<string, mixed>>}>}>
     */
    public function build(Collection $popIds, BoardFilters $f): Collection
    {
        // Sanitasi defensif: pemanggil yang merakit BoardFilters langsung
        // (bukan lewat fromRequest) tetap tidak bisa melebarkan LIKE dengan
        // `%`/`_`. BoardFilters baru dipakai seterusnya supaya isFiltering()
        // konsisten dengan teks yang benar-benar dicari.
        $clean = LikeSearch::sanitize($f->search);
        if ($clean !== $f->search) {
            $f = new BoardFilters($f->popId, $f->categoryId, $f->tracking, $clean, $f->lowOnly);
        }

        $pops = $this->scopedPops($popIds, $f);

        if ($pops->isEmpty()) {
            return collect();
        }

        $warehouseIds = $pops->pluck('id');
        $available = $this->position->availableByPopAndItem($warehouseIds);

        // Pencarian SN/roll hanya melihat gudang dalam scope ($warehouseIds sudah
        // dipersempit scope): gudang luar scope tak boleh menghasilkan row,
        // label, atau nama barang.
        $searching = $f->search !== '';
        $matches = $searching
            ? $this->searchMatches($warehouseIds, $f)
            : ['pairs' => collect(), 'truncated' => false];
        $nameMatchedIds = $searching
            ? Item::query()
                ->where(fn ($q) => $q->where('name', 'like', "%{$f->search}%")->orWhere('code', 'like', "%{$f->search}%"))
                ->pluck('id')
                ->flip()
            : collect();

        $items = $this->loadItems(array_keys($available), $f, $matches['pairs']->pluck('item_id')->unique()->all());

        $thresholds = $this->thresholds($warehouseIds, $items->keys());
        $prices = $this->lastPrices($items);
        $rollCounts = $this->rollCounts($warehouseIds, $items);
        $opnames = $this->lastOpnames($warehouseIds, $items->keys());
        $conditions = $this->position->conditionByPopAndItem($warehouseIds);

        $categoryOrder = ItemCategory::query()->ordered()->pluck('id')->flip();

        $groups = $pops->map(function (Pop $pop) use ($items, $available, $thresholds, $prices, $rollCounts, $opnames, $conditions, $categoryOrder, $f, $matches, $nameMatchedIds) {
            $rows = collect();

            foreach ($items as $itemId => $item) {
                $slot = $available[$itemId][$pop->id] ?? null;
                if ($slot === null) {
                    continue;
                }

                // Cocok lewat nama/kode: lolos di semua gudang. Cocok hanya lewat
                // SN/roll: lolos di gudang tempat unitnya ada.
                $pair = $matches['pairs']->get($pop->id.'-'.$itemId);
                if ($f->search !== '' && ! $nameMatchedIds->has($itemId) && $pair === null) {
                    continue;
                }

                $row = $this->makeRow($pop, $item, $slot, $thresholds, $prices, $rollCounts, $opnames, $conditions);
                if ($pair !== null) {
                    $row['match_label'] = $pair['label'];
                }

                if ($f->lowOnly && ! $row['is_low']) {
                    continue;
                }

                $rows->push($row);
            }

            $categories = $rows
                ->groupBy(fn (array $row) => $row['item']->item_category_id ?? 0)
                ->map(function (Collection $perCategory) {
                    $totals = $this->formatCategoryTotal($perCategory);

                    return [
                        'category' => $perCategory->first()['item']->category,
                        'sku_count' => $perCategory->count(),
                        'total_qty' => $totals['total_qty'],
                        'total_rolls' => $totals['total_rolls'],
                        'total_display' => $totals['total_display'],
                        'rows' => $perCategory
                            ->sortBy(fn (array $row) => $row['item']->name, SORT_NATURAL | SORT_FLAG_CASE)
                            ->values(),
                    ];
                })
                // Kategori nonaktif tetap ikut urutan master; tanpa kategori
                // (id 0) selalu di akhir.
                ->sortBy(fn (array $group) => $group['category'] === null
                    ? PHP_INT_MAX
                    : ($categoryOrder[$group['category']->id] ?? PHP_INT_MAX - 1))
                ->values();

            return [
                'pop' => $pop,
                'sku_count' => $rows->count(),
                'low_count' => $rows->where('is_low', true)->count(),
                'search_truncated' => $matches['truncated'],
                'categories' => $categories,
            ];
        });

        // Gudang tanpa baris: tampil sebagai band kosong hanya bila tidak ada
        // filter/pencarian aktif. Saat menyaring, gudang kosong cuma noise.
        if ($f->isFiltering()) {
            $groups = $groups->filter(fn (array $group) => $group['sku_count'] > 0);
        }

        return $groups->values();
    }

    /**
     * Matriks barang × gudang untuk memantau banyak gudang sekaligus.
     *
     * Murni pivot dari `build()` — angka TIDAK dihitung ulang, jadi sel matriks
     * pasti sama dengan baris tabel. Kolom = SEMUA gudang dalam scope (setelah
     * `popId` menyempitkan), termasuk yang tidak punya baris lolos-filter,
     * supaya lebar kolom tidak melompat saat user mengetik pencarian.
     *
     * Filter menentukan BARANG mana yang tampil (cukup satu gudang yang lolos),
     * tetapi isi sel diambil dari build() TANPA filter. Kalau tidak, filter
     * "Menipis" atau pencarian SN membuat gudang yang stoknya aman tampil "0 ⚠
     * kosong" padahal ada stok — angka bohong di layar pemantauan.
     *
     * @param  Collection<int, int>  $popIds  POP dalam scope aktor.
     * @return array{search_truncated: bool, pops: Collection<int, Pop>, categories: Collection<int, array{category: ItemCategory|null, rows: Collection<int, array{item: Item, tracking: TrackingType, unit: string, cells: array<int, array{qty: float, is_low: bool, row: array<string, mixed>|null}>}>}>}
     */
    public function matrix(Collection $popIds, BoardFilters $f): array
    {
        $pops = $this->scopedPops($popIds, $f);

        if ($pops->isEmpty()) {
            return ['pops' => $pops, 'categories' => collect(), 'search_truncated' => false];
        }

        $visible = $this->build($popIds, $f);

        $truncated = $visible->contains(fn (array $group) => $group['search_truncated']);

        // Barang yang lolos filter di setidaknya satu gudang.
        $visibleItemIds = $visible
            ->flatMap(fn (array $group) => $group['categories']->flatMap(fn (array $c) => $c['rows']))
            ->map(fn (array $row) => $row['item']->id)
            ->unique()
            ->flip();

        if ($visibleItemIds->isEmpty()) {
            return ['pops' => $pops, 'categories' => collect(), 'search_truncated' => $truncated];
        }

        $unfiltered = $f->isFiltering()
            ? $this->build($popIds, new BoardFilters($f->popId, null, null, '', false))
            : $visible;

        $items = [];
        $cellsByItem = [];
        foreach ($unfiltered as $group) {
            foreach ($group['categories'] as $categoryGroup) {
                foreach ($categoryGroup['rows'] as $row) {
                    $itemId = $row['item']->id;
                    if (! $visibleItemIds->has($itemId)) {
                        continue;
                    }
                    $items[$itemId] ??= $row;
                    $cellsByItem[$itemId][$group['pop']->id] = [
                        'qty' => $row['qty'],
                        'is_low' => $row['is_low'],
                        'row' => $row,
                    ];
                }
            }
        }

        $empty = ['qty' => 0.0, 'is_low' => false, 'row' => null];
        $categoryOrder = ItemCategory::query()->ordered()->pluck('id')->flip();

        $categories = collect($items)
            ->map(fn (array $row, int $itemId) => [
                'item' => $row['item'],
                'tracking' => $row['tracking'],
                'unit' => $row['unit'],
                'cells' => $pops->mapWithKeys(fn (Pop $pop) => [
                    $pop->id => $cellsByItem[$itemId][$pop->id] ?? $empty,
                ])->all(),
            ])
            ->groupBy(fn (array $row) => $row['item']->item_category_id ?? 0)
            ->map(function (Collection $perCategory) use ($pops) {
                $flattened = collect();
                foreach ($perCategory as $itemRow) {
                    foreach ($pops as $pop) {
                        $cell = $itemRow['cells'][$pop->id] ?? null;
                        if ($cell && isset($cell['row']) && $cell['row'] !== null) {
                            $flattened->push($cell['row']);
                        }
                    }
                }
                $totals = $this->formatCategoryTotal($flattened);

                return [
                    'category' => $perCategory->first()['item']->category,
                    'sku_count' => $perCategory->count(),
                    'total_qty' => $totals['total_qty'],
                    'total_rolls' => $totals['total_rolls'],
                    'total_display' => $totals['total_display'],
                    'rows' => $perCategory
                        ->sortBy(fn (array $row) => $row['item']->name, SORT_NATURAL | SORT_FLAG_CASE)
                        ->values(),
                ];
            })
            ->sortBy(fn (array $group) => $group['category'] === null
                ? PHP_INT_MAX
                : ($categoryOrder[$group['category']->id] ?? PHP_INT_MAX - 1))
            ->values();

        return ['pops' => $pops, 'categories' => $categories, 'search_truncated' => $truncated];
    }

    /**
     * Format ringkas total barang per kategori (misal: "2.500 meter (3 roll)", "15 pcs", dsb).
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{total_qty: float, total_rolls: int, total_display: string}
     */
    private function formatCategoryTotal(Collection $rows): array
    {
        $totalQty = (float) $rows->sum('qty');
        $fmt = fn ($val) => rtrim(rtrim(number_format((float) $val, 2, ',', '.'), '0'), ',');

        $units = $rows
            ->map(fn (array $r) => trim((string) ($r['unit'] ?? '')))
            ->filter(fn ($u) => $u !== '')
            ->unique()
            ->values();

        $allRolls = $rows->isNotEmpty() && $rows->every(fn (array $r) => ($r['tracking'] ?? null) === TrackingType::ROLL);
        $hasRolls = $rows->contains(fn (array $r) => ($r['tracking'] ?? null) === TrackingType::ROLL);
        $totalRolls = (int) $rows->sum(fn (array $r) => (int) ($r['roll_count'] ?? 0));

        $totalDisplay = match (true) {
            $allRolls => $fmt($totalQty).' meter ('.$totalRolls.' roll)',
            $hasRolls && $totalRolls > 0 => $fmt($totalQty).' unit/m ('.$totalRolls.' roll)',
            $units->count() === 1 => $fmt($totalQty).' '.$units->first(),
            default => $fmt($totalQty).' barang',
        };

        return [
            'total_qty' => $totalQty,
            'total_rolls' => $totalRolls,
            'total_display' => $totalDisplay,
        ];
    }

    /**
     * Gudang dalam scope, setelah `popId` menyempitkan. Pusat paling depan,
     * lalu cabang menurut nama. Satu sumber untuk build() dan matrix() supaya
     * urutan kolom matriks = urutan band tabel.
     *
     * @param  Collection<int, int>  $popIds
     * @return Collection<int, Pop>
     */
    private function scopedPops(Collection $popIds, BoardFilters $f): Collection
    {
        $scopedIds = $popIds->map(fn ($id) => (int) $id)->unique()->values();

        // Menyempitkan, bukan memperluas: popId di luar scope jatuh ke kosong.
        if ($f->popId !== null) {
            $scopedIds = $scopedIds->filter(fn ($id) => $id === $f->popId)->values();
        }

        if ($scopedIds->isEmpty()) {
            return collect();
        }

        // `orderBy('type')` tidak cukup: 'cabang' < 'pusat' secara alfabet,
        // jadi Pusat diletakkan paling depan lewat sort PHP.
        return Pop::query()
            ->warehouse()
            ->whereIn('id', $scopedIds)
            ->with(['gudangPics' => fn ($q) => $q->where('status', 'active')->orderBy('name')])
            ->get()
            ->sortBy([
                fn (Pop $a, Pop $b) => $b->isPusat() <=> $a->isPusat(),
                fn (Pop $a, Pop $b) => strnatcasecmp($a->name, $b->name),
            ])
            ->values();
    }

    /**
     * Alasan papan kosong, supaya view memilih pesan yang jujur.
     *
     * @param  Collection<int, int>  $popIds  POP scope aktor (bukan hasil build).
     * @param  Collection<int, array<string, mixed>>  $groups  Hasil build().
     * @return 'no_access'|'no_match'|'no_stock'|null
     */
    public function emptyState(Collection $popIds, Collection $groups, BoardFilters $f): ?string
    {
        if ($popIds->isEmpty()) {
            return 'no_access';
        }

        if ($groups->contains(fn (array $group) => $group['sku_count'] > 0)) {
            return null;
        }

        return $f->isFiltering() ? 'no_match' : 'no_stock';
    }

    /**
     * Pasangan (gudang, barang) yang punya SN AVAILABLE / roll AVAILABLE|RECEIVED
     * dengan kode memuat `$term`. WAJIB `whereIn(current_pop_id, $popIds)` agar
     * SN di luar scope tidak bocor. Dibatasi SEARCH_PAIR_CAP pasangan;
     * `truncated` true bila masih ada sisa (diambil cap+1 untuk mendeteksinya).
     *
     * @param  Collection<int, int>  $popIds
     * @return array{pairs: Collection<string, array{pop_id: int, item_id: int, label: string}>, truncated: bool}
     */
    private function searchMatches(Collection $popIds, BoardFilters $f): array
    {
        $cap = self::SEARCH_PAIR_CAP;
        $term = $f->search;

        // Filter kategori/tracking diterapkan SEBELUM cap: kalau tidak, 50
        // pasangan pertama bisa habis di kategori lain dan hasil yang sah
        // terpotong diam-diam.
        $scoped = $f->categoryId !== null || $f->tracking !== null;
        $itemIds = fn () => Item::query()
            ->when($f->categoryId !== null, fn ($q) => $q->where('item_category_id', $f->categoryId))
            ->when($f->tracking !== null, fn ($q) => $q->where('tracking_type', $f->tracking))
            ->select('id');

        $serials = InventorySerial::query()
            ->whereIn('current_pop_id', $popIds)
            ->where('status', SerialStatus::AVAILABLE->value)
            ->where('serial_number', 'like', "%{$term}%")
            ->when($scoped, fn ($q) => $q->whereIn('item_id', $itemIds()))
            ->selectRaw('current_pop_id as pop_id, item_id, MIN(serial_number) as first_code, COUNT(*) as total')
            ->groupBy('current_pop_id', 'item_id')
            ->orderBy('current_pop_id')->orderBy('item_id')
            ->limit($cap + 1)
            ->get()
            ->map(fn ($r) => ['pop_id' => (int) $r->pop_id, 'item_id' => (int) $r->item_id, 'code' => 'SN '.$r->first_code, 'total' => (int) $r->total]);

        $rolls = InventoryRoll::query()
            ->whereIn('current_pop_id', $popIds)
            ->whereIn('status', [RollStatus::AVAILABLE->value, RollStatus::RECEIVED->value])
            ->where('roll_code', 'like', "%{$term}%")
            ->when($scoped, fn ($q) => $q->whereIn('item_id', $itemIds()))
            ->selectRaw('current_pop_id as pop_id, item_id, MIN(roll_code) as first_code, COUNT(*) as total')
            ->groupBy('current_pop_id', 'item_id')
            ->orderBy('current_pop_id')->orderBy('item_id')
            ->limit($cap + 1)
            ->get()
            ->map(fn ($r) => ['pop_id' => (int) $r->pop_id, 'item_id' => (int) $r->item_id, 'code' => 'roll '.$r->first_code, 'total' => (int) $r->total]);

        $all = $serials->concat($rolls);

        $pairs = $all->take($cap)->mapWithKeys(fn (array $m) => [
            $m['pop_id'].'-'.$m['item_id'] => [
                'pop_id' => $m['pop_id'],
                'item_id' => $m['item_id'],
                'label' => 'Cocok: '.$m['code'].($m['total'] > 1 ? ' +'.($m['total'] - 1).' lagi' : ''),
            ],
        ]);

        return ['pairs' => $pairs, 'truncated' => $all->count() > $cap];
    }

    /**
     * Item yang punya stok tersedia, sudah disaring. Tidak memfilter kategori
     * aktif: barang di kategori nonaktif tetap harus terlihat selama ada stok.
     *
     * @param  array<int, int>  $itemIds
     * @param  array<int, int>  $matchedItemIds  Cocok lewat SN/roll (union dengan nama/kode).
     * @return Collection<int, Item>
     */
    private function loadItems(array $itemIds, BoardFilters $f, array $matchedItemIds = []): Collection
    {
        if ($itemIds === []) {
            return collect();
        }

        return Item::query()
            ->with('category')
            ->whereIn('id', $itemIds)
            ->when($f->categoryId !== null, fn ($q) => $q->where('item_category_id', $f->categoryId))
            ->when($f->tracking !== null, fn ($q) => $q->where('tracking_type', $f->tracking))
            ->when($f->search !== '', function ($q) use ($f, $matchedItemIds) {
                $q->where(fn ($qq) => $qq->where('name', 'like', "%{$f->search}%")
                    ->orWhere('code', 'like', "%{$f->search}%")
                    ->orWhereIn('id', $matchedItemIds));
            })
            ->get()
            ->keyBy('id');
    }

    /**
     * Ambang `minimum_stock` disimpan di baris `lot_no=''` (juga untuk
     * SERIALIZED/ROLL yang qty-nya 0 — lihat storeThreshold()).
     *
     * @param  Collection<int, int>  $popIds
     * @param  Collection<int, int>  $itemIds
     * @return Collection<string, float|null>
     */
    private function thresholds(Collection $popIds, Collection $itemIds): Collection
    {
        if ($itemIds->isEmpty()) {
            return collect();
        }

        return InventoryBalance::query()
            ->whereIn('pop_id', $popIds)
            ->whereIn('item_id', $itemIds)
            ->where('lot_no', '')
            ->get(['pop_id', 'item_id', 'minimum_stock'])
            ->mapWithKeys(fn ($row) => [
                $row->pop_id.'-'.$row->item_id => $row->minimum_stock !== null ? (float) $row->minimum_stock : null,
            ]);
    }

    /**
     * Harga terakhir per `(item_id, lot_no)` dari ledger RECEIVE, urut id asc
     * lalu ditimpa — baris terakhir menang (last-cost). Hanya QUANTITY.
     *
     * @param  Collection<int, Item>  $items
     * @return array<string, float>
     */
    private function lastPrices(Collection $items): array
    {
        $quantityIds = $items->filter(fn (Item $i) => $i->tracking_type === TrackingType::QUANTITY)->keys();
        if ($quantityIds->isEmpty()) {
            return [];
        }

        $prices = [];
        // Hanya baris terakhir per (item, lot) yang dibaca — ledger RECEIVE
        // tumbuh tanpa batas, jangan tarik semuanya ke memori.
        $latestIds = InventoryTransaction::query()
            ->where('type', InventoryTransactionType::RECEIVE->value)
            ->whereIn('item_id', $quantityIds)
            ->whereNotNull('unit_price_snapshot')
            ->groupBy('item_id', 'lot_no')
            ->selectRaw('MAX(id)');

        $rows = InventoryTransaction::query()
            ->whereIn('id', $latestIds)
            ->get(['item_id', 'lot_no', 'unit_price_snapshot']);

        foreach ($rows as $row) {
            $prices[$row->item_id.'-'.($row->lot_no ?? '')] = (float) $row->unit_price_snapshot;
        }

        return $prices;
    }

    /**
     * Jumlah roll yang masih di gudang (bukan panjang meter) per `"{pop}-{item}"`.
     *
     * @param  Collection<int, int>  $popIds
     * @param  Collection<int, Item>  $items
     * @return Collection<string, int>
     */
    private function rollCounts(Collection $popIds, Collection $items): Collection
    {
        $rollIds = $items->filter(fn (Item $i) => $i->tracking_type === TrackingType::ROLL)->keys();
        if ($rollIds->isEmpty()) {
            return collect();
        }

        return InventoryRoll::query()
            ->whereIn('current_pop_id', $popIds)
            ->whereIn('item_id', $rollIds)
            ->whereIn('status', [RollStatus::AVAILABLE->value, RollStatus::RECEIVED->value])
            ->selectRaw('current_pop_id as pop_id, item_id, COUNT(*) as total')
            ->groupBy('current_pop_id', 'item_id')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->pop_id.'-'.$row->item_id => (int) $row->total]);
    }

    /**
     * Opname terakhir per `"{pop}-{item}"` (lintas lot: opname dipantau per
     * barang di gudang, bukan per harga).
     *
     * @param  Collection<int, int>  $popIds
     * @param  Collection<int, int>  $itemIds
     * @return Collection<string, Carbon>
     */
    private function lastOpnames(Collection $popIds, Collection $itemIds): Collection
    {
        if ($itemIds->isEmpty()) {
            return collect();
        }

        return InventoryTransaction::query()
            ->where('type', InventoryTransactionType::STOCK_OPNAME->value)
            ->whereIn('to_pop_id', $popIds)
            ->whereIn('item_id', $itemIds)
            ->selectRaw('to_pop_id, item_id, MAX(created_at) as last_opname_at')
            ->groupBy('to_pop_id', 'item_id')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->to_pop_id.'-'.$row->item_id => Carbon::parse($row->last_opname_at)]);
    }

    /**
     * Satu baris papan. Kunci harga QUANTITY:
     * - `lot_no` + `price` (?float, last-cost RECEIVE): hanya QUANTITY SATU harga;
     *   null untuk SN/ROLL dan untuk QUANTITY dua harga.
     * - `price_tiers` ([lot_no, qty, price]): hanya QUANTITY DUA harga, selain itu [].
     *
     * @param  array{pop_id: int, qty: float, lots: array<int, array{lot_no: string, qty: float}>}  $slot
     * @param  Collection<string, float|null>  $thresholds
     * @param  array<string, float>  $prices
     * @param  Collection<string, int>  $rollCounts
     * @param  Collection<string, Carbon>  $opnames
     * @param  Collection<string, Collection>  $conditions
     * @return array<string, mixed>
     */
    private function makeRow(Pop $pop, Item $item, array $slot, Collection $thresholds, array $prices, Collection $rollCounts, Collection $opnames, Collection $conditions): array
    {
        $key = $pop->id.'-'.$item->id;
        $tracking = $item->tracking_type;
        $qty = (float) $slot['qty'];
        $min = $thresholds->get($key);

        $lotNo = null;
        $price = null;
        $priceTiers = [];
        if ($tracking === TrackingType::QUANTITY) {
            // `price_tiers` sengaja hanya terisi untuk DUA harga (tampilan
            // "Harga lama/baru" & konsumen Task 2 bergantung padanya). Harga
            // satu-satunya dikirim terpisah lewat `price` supaya drawer tetap
            // bisa menampilkan Harga & Nilai tanpa mengubah arti price_tiers.
            if (count($slot['lots']) > 1) {
                $priceTiers = array_map(fn (array $lot) => [
                    'lot_no' => $lot['lot_no'],
                    'qty' => $lot['qty'],
                    'price' => $prices[$item->id.'-'.$lot['lot_no']] ?? null,
                ], $slot['lots']);
            } elseif (count($slot['lots']) === 1) {
                $lotNo = $slot['lots'][0]['lot_no'];
                $price = $prices[$item->id.'-'.$lotNo] ?? null;
            }
        }

        return [
            'item' => $item,
            'tracking' => $tracking,
            'qty' => $qty,
            'unit' => $tracking === TrackingType::ROLL ? 'meter' : (string) $item->unit,
            'specification' => collect([
                $item->short_specification,
                $tracking === TrackingType::ROLL && $item->meter_per_roll !== null
                    ? 'Panjang per roll: '.rtrim(rtrim(number_format((float) $item->meter_per_roll, 2, ',', '.'), '0'), ',').' meter'
                    : null,
            ])->filter()->implode(' · '),
            'roll_count' => $tracking === TrackingType::ROLL ? (int) $rollCounts->get($key, 0) : null,
            'min' => $min,
            'is_low' => $min !== null && $qty < $min,
            'lot_no' => $lotNo,
            'price' => $price,
            'price_tiers' => $priceTiers,
            'condition_summary' => $tracking === TrackingType::SERIALIZED ? ($conditions->get($key) ?? collect()) : null,
            'last_opname_at' => $opnames->get($key),
            'match_label' => null,
        ];
    }
}
