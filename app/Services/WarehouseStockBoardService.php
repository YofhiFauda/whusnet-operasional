<?php

namespace App\Services;

use App\Enums\InventoryTransactionType;
use App\Enums\RollStatus;
use App\Enums\TrackingType;
use App\Models\InventoryBalance;
use App\Models\InventoryRoll;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Support\BoardFilters;
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
    public function __construct(private readonly WarehouseStockPositionService $position) {}

    /**
     * @param  Collection<int, int>  $popIds  POP dalam scope aktor.
     * @return Collection<int, array{pop: Pop, sku_count: int, low_count: int, categories: Collection<int, array{category: ItemCategory|null, sku_count: int, rows: Collection<int, array<string, mixed>>}>}>
     */
    public function build(Collection $popIds, BoardFilters $f): Collection
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
        $pops = Pop::query()
            ->warehouse()
            ->whereIn('id', $scopedIds)
            ->get()
            ->sortBy([
                fn (Pop $a, Pop $b) => ($b->type === 'pusat') <=> ($a->type === 'pusat'),
                fn (Pop $a, Pop $b) => strnatcasecmp($a->name, $b->name),
            ])
            ->values();

        $warehouseIds = $pops->pluck('id');
        $available = $this->position->availableByPopAndItem($warehouseIds);

        $items = $this->loadItems(array_keys($available), $f);

        $thresholds = $this->thresholds($warehouseIds, $items->keys());
        $prices = $this->lastPrices($items);
        $rollCounts = $this->rollCounts($warehouseIds, $items);
        $opnames = $this->lastOpnames($warehouseIds, $items->keys());
        $conditions = $this->position->conditionByPopAndItem($warehouseIds);

        $categoryOrder = ItemCategory::query()->ordered()->pluck('id')->flip();

        $groups = $pops->map(function (Pop $pop) use ($items, $available, $thresholds, $prices, $rollCounts, $opnames, $conditions, $categoryOrder, $f) {
            $rows = collect();

            foreach ($items as $itemId => $item) {
                $slot = $available[$itemId][$pop->id] ?? null;
                if ($slot === null) {
                    continue;
                }

                $row = $this->makeRow($pop, $item, $slot, $thresholds, $prices, $rollCounts, $opnames, $conditions);

                if ($f->lowOnly && ! $row['is_low']) {
                    continue;
                }

                $rows->push($row);
            }

            $categories = $rows
                ->groupBy(fn (array $row) => $row['item']->item_category_id ?? 0)
                ->map(fn (Collection $perCategory) => [
                    'category' => $perCategory->first()['item']->category,
                    'sku_count' => $perCategory->count(),
                    'rows' => $perCategory
                        ->sortBy(fn (array $row) => $row['item']->name, SORT_NATURAL | SORT_FLAG_CASE)
                        ->values(),
                ])
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
     * Item yang punya stok tersedia, sudah disaring. Tidak memfilter kategori
     * aktif: barang di kategori nonaktif tetap harus terlihat selama ada stok.
     *
     * @param  array<int, int>  $itemIds
     * @return Collection<int, Item>
     */
    private function loadItems(array $itemIds, BoardFilters $f): Collection
    {
        if ($itemIds === []) {
            return collect();
        }

        return Item::query()
            ->with('category')
            ->whereIn('id', $itemIds)
            ->when($f->categoryId !== null, fn ($q) => $q->where('item_category_id', $f->categoryId))
            ->when($f->tracking !== null, fn ($q) => $q->where('tracking_type', $f->tracking))
            ->when($f->search !== '', function ($q) use ($f) {
                $q->where(fn ($qq) => $qq->where('name', 'like', "%{$f->search}%")
                    ->orWhere('code', 'like', "%{$f->search}%"));
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
        $rows = InventoryTransaction::query()
            ->where('type', InventoryTransactionType::RECEIVE->value)
            ->whereIn('item_id', $quantityIds)
            ->whereNotNull('unit_price_snapshot')
            ->orderBy('id')
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
        $priceTiers = [];
        if ($tracking === TrackingType::QUANTITY) {
            if (count($slot['lots']) > 1) {
                $priceTiers = array_map(fn (array $lot) => [
                    'lot_no' => $lot['lot_no'],
                    'qty' => $lot['qty'],
                    'price' => $prices[$item->id.'-'.$lot['lot_no']] ?? null,
                ], $slot['lots']);
            } elseif (count($slot['lots']) === 1) {
                $lotNo = $slot['lots'][0]['lot_no'];
            }
        }

        return [
            'item' => $item,
            'tracking' => $tracking,
            'qty' => $qty,
            'unit' => $tracking === TrackingType::ROLL ? 'meter' : (string) $item->unit,
            'roll_count' => $tracking === TrackingType::ROLL ? (int) $rollCounts->get($key, 0) : null,
            'min' => $min,
            'is_low' => $min !== null && $qty < $min,
            'lot_no' => $lotNo,
            'price_tiers' => $priceTiers,
            'condition_summary' => $tracking === TrackingType::SERIALIZED ? ($conditions->get($key) ?? collect()) : null,
            'last_opname_at' => $opnames->get($key),
            'match_label' => null,
        ];
    }
}
