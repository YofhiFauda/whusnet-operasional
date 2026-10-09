<?php

namespace App\Services;

use App\Enums\ItemCondition;
use App\Enums\RollStatus;
use App\Enums\SerialStatus;
use App\Enums\TrackingType;
use App\Enums\TransferStatus;
use App\Models\InventoryBalance;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\InventoryTransfer;
use App\Models\Item;
use App\Models\TechnicianCustody;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Ringkasan "Posisi Stok" per item (analisa-ui-ux-warehouse.md, Fase 3).
 *
 * Menjawab pertanyaan yang sebelumnya harus dijumlah sendiri user dari
 * banyak baris per lot/POP: berapa total item, berapa tersedia per gudang,
 * berapa dipegang teknisi, dan berapa bermasalah (karantina/rusak).
 *
 * Semua query dibatasi `$popIds` — caller WAJIB memberi POP yang sudah lolos
 * `EffectiveAccessService` (scope), bukan input mentah dari request.
 *
 * Satuan mengikuti tracking: QUANTITY = unit barang, SERIALIZED = jumlah SN,
 * ROLL = meter. Total per item aman dijumlah karena satu item = satu satuan.
 */
class WarehouseStockPositionService
{
    /**
     * @param  Collection<int, int>  $popIds  POP dalam scope aktor.
     * @return Collection<int, array{item: Item, total: float, per_pop: Collection<int, array{pop_id: int, qty: float, lots: array<int, array{lot_no: string, qty: float}>}>, held: float, problem: float, in_transit: float, holders: Collection<int, array{user_id: int, name: string, qty: float}>, last_activity: array{type: string, by: string, at: Carbon|null, ref: string|null}|null, condition_summary: Collection<int, array{condition: ItemCondition, count: int}>|null}>
     */
    public function summarize(
        Collection $popIds,
        ?int $popFilter = null,
        ?int $itemFilter = null,
        ?int $categoryFilter = null,
        ?string $search = null,
        ?string $trackingFilter = null,
    ): Collection {
        // Filter gudang hanya boleh menyempitkan scope, tidak pernah melebarkannya.
        $scopedPopIds = $popFilter !== null
            ? $popIds->filter(fn ($id) => (int) $id === $popFilter)->values()
            : $popIds->values();

        if ($scopedPopIds->isEmpty()) {
            return collect();
        }

        $available = $this->availableByPopAndItem($scopedPopIds);
        $held = $this->heldByItem($scopedPopIds);
        $problem = $this->problemByItem($scopedPopIds);
        $inTransit = $this->inTransitByItem($scopedPopIds);

        $itemIds = collect(array_keys($available))
            ->merge($held->keys())
            ->merge($problem->keys())
            ->merge($inTransit->keys())
            ->unique()
            ->values();

        if ($itemIds->isEmpty()) {
            return collect();
        }

        $items = Item::query()
            ->with('category')
            ->whereIn('id', $itemIds)
            ->when($itemFilter, fn ($q) => $q->where('id', $itemFilter))
            ->when($categoryFilter, fn ($q) => $q->where('item_category_id', $categoryFilter))
            ->when($trackingFilter, fn ($q) => $q->where('tracking_type', $trackingFilter))
            ->when($search !== null && $search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get();

        $holders = $this->holdersByItem($scopedPopIds, $itemIds);
        $lastActivity = $this->lastActivityByItem($itemIds, $scopedPopIds);
        $conditions = $this->conditionByItem($scopedPopIds);

        return $items->map(function (Item $item) use ($available, $held, $problem, $inTransit, $holders, $lastActivity, $conditions) {
            $perPop = collect($available[$item->id] ?? [])->values();

            // Kondisi fisik (analisa-ui-ux §U5, keputusan user 2026-10-07):
            // HANYA SN yang punya kondisi asli (axis independen di InventorySerial).
            // Roll tidak punya alur retur/pengecekan yang bisa mengubah kondisinya
            // (beda dari SN yang berubah pas Terima Retur), jadi nilainya akan
            // statis "Baru" selamanya — tidak ada gunanya dilacak. Roll & Quantity
            // disamakan: null di sini berarti "tidak dilacak per unit" (ditangani
            // di view), bukan "kondisinya kosong".
            $conditionSummary = $item->tracking_type === TrackingType::SERIALIZED
                ? $conditions->get($item->id, collect())
                : null;

            return [
                'item' => $item,
                'total' => (float) $perPop->sum('qty'),
                'per_pop' => $perPop,
                'held' => (float) ($held[$item->id] ?? 0),
                'problem' => (float) ($problem[$item->id] ?? 0),
                'in_transit' => (float) ($inTransit[$item->id] ?? 0),
                'holders' => $holders->get($item->id, collect()),
                'last_activity' => $this->describeActivity($lastActivity->get($item->id)),
                'condition_summary' => $conditionSummary,
            ];
        })->values();
    }

    /**
     * Breakdown kondisi fisik SN yang AVAILABLE per item (analisa-ui-ux §U5).
     * Dibatasi ke unit AVAILABLE dalam scope — sama populasi dengan yang
     * dijumlah di `availableByPopAndItem()`, supaya totalnya konsisten.
     *
     * @param  Collection<int, int>  $popIds
     * @return Collection<int, Collection<int, array{condition: ItemCondition, count: int}>>
     */
    private function conditionByItem(Collection $popIds): Collection
    {
        $rows = InventorySerial::query()
            ->whereIn('current_pop_id', $popIds)
            ->where('status', SerialStatus::AVAILABLE->value)
            ->select('item_id', 'condition')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('item_id', 'condition')
            ->get();

        return $rows->groupBy('item_id')->map(function ($perItem) {
            return $perItem->map(fn ($row) => [
                'condition' => $row->condition,
                'count' => (int) $row->count,
            ])->values();
        });
    }

    /**
     * Qty per item yang sedang DALAM PERJALANAN (transfer IN_TRANSIT yang asal
     * atau tujuannya dalam scope) — analisa-ui-ux §U1. Dihitung dari leg
     * dispatch (`from_pop_id` terisi) transfer in-transit; satuan mengikuti
     * tracking (serial = 1/baris, roll = qty meter, quantity = qty).
     *
     * @param  Collection<int, int>  $popIds
     * @return Collection<int|string, float>
     */
    private function inTransitByItem(Collection $popIds): Collection
    {
        $transferIds = InventoryTransfer::query()
            ->where('status', TransferStatus::IN_TRANSIT->value)
            ->where(fn ($q) => $q->whereIn('from_pop_id', $popIds)->orWhereIn('to_pop_id', $popIds))
            ->pluck('id');

        if ($transferIds->isEmpty()) {
            return collect();
        }

        return InventoryTransaction::query()
            ->whereIn('inventory_transfer_id', $transferIds)
            ->whereNotNull('from_pop_id') // leg dispatch (barang keluar), bukan leg confirm
            ->selectRaw('item_id, SUM(qty) as qty')
            ->groupBy('item_id')
            ->pluck('qty', 'item_id')
            ->map(fn ($q) => (float) $q);
    }

    /**
     * Pemegang per item: siapa teknisinya dan berapa yang dipegang. Sumbernya
     * sama dengan `heldByItem()` supaya total pemegang = kolom "Dipegang Teknisi".
     *
     * @param  Collection<int, int>  $popIds
     * @param  Collection<int, int>  $itemIds
     * @return Collection<int, Collection<int, array{user_id: int, name: string, qty: float}>>
     */
    private function holdersByItem(Collection $popIds, Collection $itemIds): Collection
    {
        $custody = TechnicianCustody::query()
            ->active()
            ->whereIn('issued_from_pop_id', $popIds)
            ->whereIn('item_id', $itemIds)
            ->selectRaw('item_id, technician_id as user_id, SUM(qty_remaining) as qty')
            ->groupBy('item_id', 'technician_id')
            ->get();

        $serials = InventorySerial::query()
            ->whereIn('issued_from_pop_id', $popIds)
            ->whereIn('item_id', $itemIds)
            ->whereIn('status', [SerialStatus::ISSUED->value, SerialStatus::IN_USE->value])
            ->whereNotNull('current_technician_id')
            ->selectRaw('item_id, current_technician_id as user_id, COUNT(*) as qty')
            ->groupBy('item_id', 'current_technician_id')
            ->get();

        $rolls = InventoryRoll::query()
            ->whereIn('issued_from_pop_id', $popIds)
            ->whereIn('item_id', $itemIds)
            ->whereIn('status', [RollStatus::ISSUED->value, RollStatus::IN_USE->value])
            ->whereNotNull('current_technician_id')
            ->selectRaw('item_id, current_technician_id as user_id, SUM(length_remaining) as qty')
            ->groupBy('item_id', 'current_technician_id')
            ->get();

        $rows = $custody->concat($serials)->concat($rolls);
        $names = User::query()->whereIn('id', $rows->pluck('user_id')->unique())->pluck('name', 'id');

        return $rows->groupBy('item_id')->map(function ($perItem) use ($names) {
            return $perItem->groupBy('user_id')
                ->map(fn ($perUser) => [
                    'user_id' => (int) $perUser->first()->user_id,
                    'name' => (string) ($names->get($perUser->first()->user_id) ?? '—'),
                    'qty' => (float) $perUser->sum('qty'),
                ])
                ->sortByDesc('qty')
                ->values();
        });
    }

    /**
     * Transaksi terakhir per item di gudang dalam scope — siapa yang menginput
     * dan kapan (analisa §V4). Diambil dari ledger, bukan kolom baru.
     *
     * @param  Collection<int, int>  $itemIds
     * @param  Collection<int, int>  $popIds
     * @return Collection<int, InventoryTransaction>
     */
    private function lastActivityByItem(Collection $itemIds, Collection $popIds): Collection
    {
        $lastIds = InventoryTransaction::query()
            ->whereIn('item_id', $itemIds)
            ->where(function ($q) use ($popIds) {
                $q->whereIn('from_pop_id', $popIds)->orWhereIn('to_pop_id', $popIds);
            })
            ->selectRaw('item_id, MAX(id) as last_id')
            ->groupBy('item_id')
            ->pluck('last_id');

        return InventoryTransaction::query()
            ->with('createdBy:id,name')
            ->whereIn('id', $lastIds)
            ->get()
            ->keyBy('item_id');
    }

    /**
     * @return array{type: string, by: string, at: Carbon|null, ref: string|null}|null
     */
    private function describeActivity(?InventoryTransaction $transaction): ?array
    {
        if ($transaction === null) {
            return null;
        }

        return [
            'type' => $transaction->type?->label() ?? (string) $transaction->type?->value,
            'by' => $transaction->createdBy?->name ?? '—',
            'at' => $transaction->created_at,
            'ref' => $transaction->reference_number,
        ];
    }

    /**
     * Stok tersedia di gudang, dikelompokkan per item lalu per POP.
     * Ketiga tracking dibaca dari tabelnya masing-masing, sama seperti
     * `WarehouseStockController::index()`, supaya angka kedua layar sama.
     *
     * @return array<int, array<int, array{pop_id: int, qty: float, lots: array<int, array{lot_no: string, qty: float}>}>>
     */
    private function availableByPopAndItem(Collection $popIds): array
    {
        $result = [];

        $quantityRows = InventoryBalance::query()
            ->whereIn('pop_id', $popIds)
            ->where('qty', '>', 0)
            ->get(['pop_id', 'item_id', 'lot_no', 'qty']);

        foreach ($quantityRows as $row) {
            $this->addAvailable($result, (int) $row->item_id, (int) $row->pop_id, (float) $row->qty, [
                'lot_no' => (string) $row->lot_no,
                'qty' => (float) $row->qty,
            ]);
        }

        $serialRows = InventorySerial::query()
            ->whereIn('current_pop_id', $popIds)
            ->where('status', SerialStatus::AVAILABLE->value)
            ->selectRaw('current_pop_id as pop_id, item_id, COUNT(*) as qty')
            ->groupBy('current_pop_id', 'item_id')
            ->get();

        foreach ($serialRows as $row) {
            $this->addAvailable($result, (int) $row->item_id, (int) $row->pop_id, (float) $row->qty);
        }

        $rollRows = InventoryRoll::query()
            ->whereIn('current_pop_id', $popIds)
            ->whereIn('status', [RollStatus::AVAILABLE->value, RollStatus::RECEIVED->value])
            ->selectRaw('current_pop_id as pop_id, item_id, SUM(length_remaining) as qty')
            ->groupBy('current_pop_id', 'item_id')
            ->get();

        foreach ($rollRows as $row) {
            $this->addAvailable($result, (int) $row->item_id, (int) $row->pop_id, (float) $row->qty);
        }

        return $result;
    }

    /**
     * Tambah satu sumber stok ke peta item→POP. $lot hanya diisi QUANTITY,
     * karena SN & roll tidak punya rincian lot untuk ditampilkan.
     *
     * @param  array<int, array<int, array{pop_id: int, qty: float, lots: array<int, array{lot_no: string, qty: float}>}>>  $result
     * @param  array{lot_no: string, qty: float}|null  $lot
     */
    private function addAvailable(array &$result, int $itemId, int $popId, float $qty, ?array $lot = null): void
    {
        $slot = $result[$itemId][$popId] ?? ['pop_id' => $popId, 'qty' => 0.0, 'lots' => []];
        $slot['qty'] += $qty;
        if ($lot !== null) {
            $slot['lots'][] = $lot;
        }
        $result[$itemId][$popId] = $slot;
    }

    /**
     * Jumlah yang sedang dipegang teknisi dari gudang dalam scope.
     * Custody QUANTITY dihitung dari sisa qty; SN & roll yang status ISSUED/IN_USE.
     *
     * @return Collection<int|string, float>
     */
    private function heldByItem(Collection $popIds): Collection
    {
        $custody = TechnicianCustody::query()
            ->active()
            ->whereIn('issued_from_pop_id', $popIds)
            ->selectRaw('item_id, SUM(qty_remaining) as qty')
            ->groupBy('item_id')
            ->pluck('qty', 'item_id');

        $serials = InventorySerial::query()
            ->whereIn('issued_from_pop_id', $popIds)
            ->whereIn('status', [SerialStatus::ISSUED->value, SerialStatus::IN_USE->value])
            ->selectRaw('item_id, COUNT(*) as qty')
            ->groupBy('item_id')
            ->pluck('qty', 'item_id');

        $rolls = InventoryRoll::query()
            ->whereIn('issued_from_pop_id', $popIds)
            ->whereIn('status', [RollStatus::ISSUED->value, RollStatus::IN_USE->value])
            ->selectRaw('item_id, SUM(length_remaining) as qty')
            ->groupBy('item_id')
            ->pluck('qty', 'item_id');

        return $this->sumByItem($custody, $serials, $rolls);
    }

    /**
     * Unit yang sedang bermasalah (karantina/rusak) di gudang dalam scope.
     *
     * @return Collection<int|string, float>
     */
    private function problemByItem(Collection $popIds): Collection
    {
        $problemSerialStatuses = [SerialStatus::QUARANTINE->value, SerialStatus::DAMAGED->value];
        $problemRollStatuses = [RollStatus::QUARANTINE->value, RollStatus::DAMAGED->value];

        $serials = InventorySerial::query()
            ->whereIn('current_pop_id', $popIds)
            ->whereIn('status', $problemSerialStatuses)
            ->selectRaw('item_id, COUNT(*) as qty')
            ->groupBy('item_id')
            ->pluck('qty', 'item_id');

        $rolls = InventoryRoll::query()
            ->whereIn('current_pop_id', $popIds)
            ->whereIn('status', $problemRollStatuses)
            ->selectRaw('item_id, SUM(length_remaining) as qty')
            ->groupBy('item_id')
            ->pluck('qty', 'item_id');

        return $this->sumByItem($serials, $rolls);
    }

    /**
     * Gabung beberapa peta item→qty jadi satu peta (penjumlahan per item).
     *
     * @return Collection<int|string, float>
     */
    private function sumByItem(Collection ...$maps): Collection
    {
        $total = collect();
        foreach ($maps as $map) {
            foreach ($map as $itemId => $qty) {
                $total[$itemId] = ($total[$itemId] ?? 0) + (float) $qty;
            }
        }

        return $total;
    }
}
