<?php

namespace App\Services;

use App\Enums\InventoryTransactionType;
use App\Enums\TrackingType;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\Pop;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Saldo stok PER-TANGGAL (bukan cuma "sekarang") — dipakai Laporan Gudang
 * (ADHOC-79) buat kolom Barang Masuk/Stok Awal/Stok Akhir. `InventoryBalance`/
 * `inventory_serials.status`/`inventory_rolls.status` cuma proyeksi status
 * TERKINI, gak ada cara "mundur ke tanggal lalu" dari situ — dihitung
 * langsung dari ledger `inventory_transactions` (append-only, satu-satunya
 * sumber kebenaran, docs/warehouse/business-logic.md §10).
 *
 * Baris terakhir per SERIALIZED/ROLL menentukan lokasi stok pada cutoff.
 * DEPLOY dan REGISTER_INFRA mencatat lokasi POP aset terpasang, sehingga
 * keduanya dikecualikan dari saldo gudang. Replay QUANTITY juga mengecualikan
 * kedua tipe itu karena ISSUE sudah mengurangi stok gudang.
 *
 * **"Barang Masuk"** (ADHOC-79 ronde ke-6) = SEMUA arrival ke POP ini dalam
 * satu periode, TERLEPAS dari `type`-nya (RECEIVE di Pusat MAUPUN
 * TRANSFER-confirm di Cabang) — niru cara laporan manual lama nyatet
 * "Stok Beli" buat SEMUA cabang, bukan cuma Pusat (dibuktikan langsung dari
 * `laporan_admin_gudang_per_pop.md`: sheet cabang tetap punya angka "Stok
 * Beli" walau cabang gak pernah RECEIVE langsung dari distributor).
 */
class WarehouseStockAsOfService
{
    private array $preloadedBalances = [];

    /**
     * "Barang Masuk" (`receivedInPeriod()`) CUMA ngitung tipe yang beneran
     * "barang fisik nyampe" — RECEIVE/TRANSFER(confirm)/RETURN. SENGAJA
     * TIDAK termasuk ADJUSTMENT (delta-nya bisa POSITIF juga, mis. opname
     * ketemu lebih — itu koreksi, bukan "barang masuk baru", tetap punya
     * kolom sendiri di Rusak/Hilang atau gak diplot sama sekali) —
     * ketauan lewat test manual: RUSAK -8 numpang ke_to_pop_id`
     * `adjustPopBalance()` bikin "Barang Masuk" salah baca 100 jadi 92
     * kalau filter type ini gak dipasang.
     */
    private const ARRIVAL_TYPES = [
        InventoryTransactionType::RECEIVE->value,
        InventoryTransactionType::TRANSFER->value,
        InventoryTransactionType::RETURN->value,
    ];

    /**
     * Dispatcher — pilih perhitungan sesuai `tracking_type` item, dibungkus
     * SATU bentuk return biar caller (export Laporan Gudang) gak perlu tau
     * bedanya. QUANTITY, SERIALIZED, dan ROLL semuanya didistribusikan ke
     * 2-slot (Lama & Baru) sesuai kelompok harga snapshot unit masing-masing.
     *
     * @return array{lama: array{qty: float, harga: ?float}, baru: array{qty: float, harga: ?float}, nilai: float}
     */
    public function balanceAsOf(Pop $pop, Item $item, Carbon $exclusiveCutoff, bool $isStokAwal = false): array
    {
        $cacheKey = $pop->id.':'.$item->id.':'.$exclusiveCutoff->toDateTimeString().':'.(int) $isStokAwal;
        if (array_key_exists($cacheKey, $this->preloadedBalances)) {
            return $this->preloadedBalances[$cacheKey];
        }

        if ($item->tracking_type === TrackingType::QUANTITY) {
            return $this->quantityAsOf($pop, $item, $exclusiveCutoff, $isStokAwal);
        }

        $idColumn = $item->tracking_type === TrackingType::ROLL ? 'roll_id' : 'serial_id';

        return $this->unitsAsOf($pop, $item, $exclusiveCutoff, $idColumn, $isStokAwal);
    }

    /**
     * Siapkan saldo QUANTITY/SERIALIZED/ROLL untuk dua cutoff laporan
     * dalam query per jenis, bukan query per kombinasi POP dan item. Hanya POP yang diberikan pemanggil
     * yang boleh masuk hasil cache.
     *
     * @param  Collection<int, Pop>  $pops
     * @param  Collection<int, Item>  $items
     */
    public function preloadBalances(Collection $pops, Collection $items, Carbon $start, Carbon $end): void
    {
        $this->preloadedBalances = [];
        $unitItems = $items->filter(fn (Item $item) => $item->tracking_type !== TrackingType::QUANTITY)->keyBy('id');
        $popIds = $pops->pluck('id');

        if ($popIds->isEmpty() || $items->isEmpty()) {
            return;
        }

        $quantityItems = $items->filter(fn (Item $item) => $item->tracking_type === TrackingType::QUANTITY)->keyBy('id');
        $this->preloadQuantityBalances($pops, $quantityItems, $start, $end);

        if ($unitItems->isEmpty()) {
            return;
        }

        $firstIds = InventoryTransaction::query()
            ->whereIn('item_id', $unitItems->keys())
            ->whereIn('to_pop_id', $popIds)
            ->whereNotNull('unit_price_snapshot')
            ->selectRaw('MIN(id) as first_id')
            ->groupBy('item_id', 'to_pop_id');

        $firstPrices = InventoryTransaction::query()
            ->joinSub($firstIds, 'first_arrivals', fn ($join) => $join->on('inventory_transactions.id', '=', 'first_arrivals.first_id'))
            ->get(['inventory_transactions.item_id', 'inventory_transactions.to_pop_id', 'inventory_transactions.unit_price_snapshot']);

        $earliestPrices = [];
        foreach ($firstPrices as $row) {
            $earliestPrices[$row->to_pop_id][$row->item_id] = (float) $row->unit_price_snapshot;
        }

        $itemIdsByUnitColumn = [
            'serial_id' => $unitItems->filter(fn (Item $item) => $item->tracking_type === TrackingType::SERIALIZED)->keys(),
            'roll_id' => $unitItems->filter(fn (Item $item) => $item->tracking_type === TrackingType::ROLL)->keys(),
        ];

        foreach ([$start, $end] as $cutoffIndex => $cutoff) {
            foreach ($itemIdsByUnitColumn as $idColumn => $itemIds) {
                if ($itemIds->isEmpty()) {
                    continue;
                }

                $latestIds = InventoryTransaction::query()
                    ->whereIn('item_id', $itemIds)
                    ->whereNotNull($idColumn)
                    ->where('created_at', '<', $cutoff)
                    ->selectRaw('MAX(id) as max_id')
                    ->groupBy('item_id', $idColumn);

                $rowsByPopItem = InventoryTransaction::query()
                    ->joinSub($latestIds, 'latest_units', fn ($join) => $join->on('inventory_transactions.id', '=', 'latest_units.max_id'))
                    ->whereIn('inventory_transactions.to_pop_id', $popIds)
                    ->whereNotIn('inventory_transactions.type', [
                        InventoryTransactionType::DEPLOY->value,
                        InventoryTransactionType::REGISTER_INFRA->value,
                    ])
                    ->orderBy('inventory_transactions.id')
                    ->get([
                        'inventory_transactions.id',
                        'inventory_transactions.item_id',
                        'inventory_transactions.to_pop_id',
                        'inventory_transactions.qty',
                        'inventory_transactions.unit_price_snapshot',
                    ])
                    ->groupBy(fn (InventoryTransaction $row) => $row->to_pop_id.':'.$row->item_id);

                foreach ($pops as $pop) {
                    foreach ($itemIds as $itemId) {
                        $item = $unitItems->get($itemId);
                        $rows = $rowsByPopItem->get($pop->id.':'.$itemId, collect());
                        $cacheKey = $pop->id.':'.$itemId.':'.$cutoff->toDateTimeString().':'.(int) ($cutoffIndex === 0);
                        $this->preloadedBalances[$cacheKey] = $this->splitUnitRowsIntoSlots(
                            $rows, $cutoffIndex === 0, $pop, $item, $cutoff, $earliestPrices
                        );
                    }
                }
            }
        }
    }

    /**
     * Replay QUANTITY untuk seluruh item/POP laporan dalam dua query ledger.
     *
     * @param  Collection<int, Pop>  $pops
     * @param  Collection<int, Item>  $items
     */
    private function preloadQuantityBalances(Collection $pops, Collection $items, Carbon $start, Carbon $end): void
    {
        if ($items->isEmpty()) {
            return;
        }

        $popIds = $pops->pluck('id');
        foreach ([$start, $end] as $cutoffIndex => $cutoff) {
            $rows = InventoryTransaction::query()
                ->whereIn('item_id', $items->keys())
                ->where(fn ($query) => $query->whereIn('to_pop_id', $popIds)->orWhereIn('from_pop_id', $popIds))
                ->where('created_at', '<', $cutoff)
                ->whereNotIn('type', [InventoryTransactionType::DEPLOY->value, InventoryTransactionType::REGISTER_INFRA->value])
                ->orderBy('id')
                ->toBase()
                ->get(['id', 'item_id', 'lot_no', 'qty', 'to_pop_id', 'from_pop_id', 'unit_price_snapshot']);

            $rowsByPopItem = [];
            foreach ($rows as $row) {
                foreach (array_unique(array_filter([$row->to_pop_id, $row->from_pop_id])) as $popId) {
                    if ($popIds->contains((int) $popId)) {
                        $rowsByPopItem[$popId][$row->item_id][] = $row;
                    }
                }
            }

            foreach ($pops as $pop) {
                foreach ($items as $item) {
                    $itemRows = collect($rowsByPopItem[$pop->id][$item->id] ?? []);
                    $cacheKey = $pop->id.':'.$item->id.':'.$cutoff->toDateTimeString().':'.(int) ($cutoffIndex === 0);
                    $this->preloadedBalances[$cacheKey] = $this->quantityAsOfRows($itemRows, $pop);
                }
            }
        }
    }

    /**
     * "Barang Masuk" — SUM arrival (`to_pop_id`=POP ini) dalam rentang
     * `[$periodStart, $periodEndExclusive)`, bukan snapshot saldo.
     *
     * @return array{lama: array{qty: float, harga: ?float}, baru: array{qty: float, harga: ?float}, nilai: float}
     */
    public function receivedInPeriod(Pop $pop, Item $item, Carbon $periodStart, Carbon $periodEndExclusive): array
    {
        if ($item->tracking_type === TrackingType::QUANTITY) {
            return $this->quantityReceivedInPeriod($pop, $item, $periodStart, $periodEndExclusive);
        }

        $idColumn = $item->tracking_type === TrackingType::ROLL ? 'roll_id' : 'serial_id';
        $rows = InventoryTransaction::query()
            ->where('item_id', $item->id)
            ->where('to_pop_id', $pop->id)
            ->whereIn('type', self::ARRIVAL_TYPES)
            ->whereNotNull($idColumn)
            ->where('created_at', '>=', $periodStart)
            ->where('created_at', '<', $periodEndExclusive)
            ->orderBy('id')
            ->get(['id', 'qty', 'unit_price_snapshot']);

        return $this->splitUnitRowsIntoSlots($rows, false, $pop, $item, $periodStart);
    }

    /**
     * QUANTITY — replay saldo per lot (Lama/Baru) sampai `$exclusiveCutoff`.
     */
    private function quantityAsOf(Pop $pop, Item $item, Carbon $exclusiveCutoff, bool $isStokAwal = false): array
    {
        $rows = InventoryTransaction::query()
            ->where('item_id', $item->id)
            ->where(fn ($q) => $q->where('to_pop_id', $pop->id)->orWhere('from_pop_id', $pop->id))
            ->where('created_at', '<', $exclusiveCutoff)
            ->whereNotIn('type', [InventoryTransactionType::DEPLOY->value, InventoryTransactionType::REGISTER_INFRA->value])
            ->orderBy('id')
            ->get(['id', 'lot_no', 'qty', 'to_pop_id', 'from_pop_id', 'unit_price_snapshot']);

        return $this->quantityAsOfRows($rows, $pop);
    }

    /**
     * @param  Collection<int, InventoryTransaction|object>  $rows
     * @return array{lama: array{qty: float, harga: ?float}, baru: array{qty: float, harga: ?float}, nilai: float}
     */
    private function quantityAsOfRows(Collection $rows, Pop $pop): array
    {
        $balances = [];
        $firstSeenId = [];
        $lastPrice = [];

        foreach ($rows as $row) {
            $lot = $row->lot_no ?? '';
            $balances[$lot] ??= 0.0;

            if ((int) $row->to_pop_id === $pop->id) {
                $balances[$lot] += (float) $row->qty;
                $firstSeenId[$lot] ??= $row->id;
                if ($row->unit_price_snapshot !== null) {
                    $lastPrice[$lot] = (float) $row->unit_price_snapshot;
                }
            }

            if ((int) $row->from_pop_id === $pop->id) {
                $balances[$lot] -= (float) $row->qty;
            }
        }

        // Toleransi float kecil — hasil pengurangan desimal berulang bisa
        // nyisa -0.0000001 alih-alih pas nol.
        $activeLots = collect($balances)->filter(fn ($qty) => $qty > 0.001);

        [$lamaLot, $baruLot] = $this->orderLotsByFirstArrival($activeLots->keys(), $firstSeenId);

        $qtyLama = $lamaLot !== null ? $activeLots->get($lamaLot, 0.0) : 0.0;
        $qtyBaru = $baruLot !== null ? $activeLots->get($baruLot, 0.0) : 0.0;
        $hargaLama = $lamaLot !== null ? ($lastPrice[$lamaLot] ?? null) : null;
        $hargaBaru = $baruLot !== null ? ($lastPrice[$baruLot] ?? null) : null;

        return [
            'lama' => ['qty' => $qtyLama, 'harga' => $hargaLama],
            'baru' => ['qty' => $qtyBaru, 'harga' => $hargaBaru],
            'nilai' => $qtyLama * ($hargaLama ?? 0) + $qtyBaru * ($hargaBaru ?? 0),
        ];
    }

    /**
     * QUANTITY versi "Barang Masuk".
     */
    private function quantityReceivedInPeriod(Pop $pop, Item $item, Carbon $periodStart, Carbon $periodEndExclusive): array
    {
        [$lamaLot, $baruLot] = $this->activeLotsOrderedAsOf($pop, $item, $periodEndExclusive);

        if ($lamaLot === null && $baruLot === null) {
            return ['lama' => ['qty' => 0.0, 'harga' => null], 'baru' => ['qty' => 0.0, 'harga' => null], 'nilai' => 0.0];
        }

        $rows = InventoryTransaction::query()
            ->where('item_id', $item->id)
            ->where('to_pop_id', $pop->id)
            ->whereIn('type', self::ARRIVAL_TYPES)
            ->where('created_at', '>=', $periodStart)
            ->where('created_at', '<', $periodEndExclusive)
            ->get(['lot_no', 'qty', 'unit_price_snapshot']);

        $qtyByLot = [];
        $priceByLot = [];
        foreach ($rows as $row) {
            $lot = $row->lot_no ?? '';
            $qtyByLot[$lot] = ($qtyByLot[$lot] ?? 0.0) + (float) $row->qty;
            if ($row->unit_price_snapshot !== null) {
                $priceByLot[$lot] = (float) $row->unit_price_snapshot;
            }
        }

        $qtyLama = $lamaLot !== null ? ($qtyByLot[$lamaLot] ?? 0.0) : 0.0;
        $qtyBaru = $baruLot !== null ? ($qtyByLot[$baruLot] ?? 0.0) : 0.0;
        $hargaLama = $lamaLot !== null ? ($priceByLot[$lamaLot] ?? null) : null;
        $hargaBaru = $baruLot !== null ? ($priceByLot[$baruLot] ?? null) : null;

        return [
            'lama' => ['qty' => $qtyLama, 'harga' => $hargaLama],
            'baru' => ['qty' => $qtyBaru, 'harga' => $hargaBaru],
            'nilai' => $qtyLama * ($hargaLama ?? 0) + $qtyBaru * ($hargaBaru ?? 0),
        ];
    }

    /**
     * Lot mana yang "Lama" (arrival PERTAMA) / "Baru" (lainnya) buat item
     * QUANTITY di POP ini, sampai `$exclusiveCutoff`.
     *
     * @return array{0: ?string, 1: ?string} [lamaLot, baruLot]
     */
    private function activeLotsOrderedAsOf(Pop $pop, Item $item, Carbon $exclusiveCutoff): array
    {
        $rows = InventoryTransaction::query()
            ->where('item_id', $item->id)
            ->where(fn ($q) => $q->where('to_pop_id', $pop->id)->orWhere('from_pop_id', $pop->id))
            ->where('created_at', '<', $exclusiveCutoff)
            ->whereNotIn('type', [InventoryTransactionType::DEPLOY->value, InventoryTransactionType::REGISTER_INFRA->value])
            ->orderBy('id')
            ->get(['id', 'lot_no', 'qty', 'to_pop_id', 'from_pop_id']);

        $balances = [];
        $firstSeenId = [];

        foreach ($rows as $row) {
            $lot = $row->lot_no ?? '';
            $balances[$lot] ??= 0.0;

            if ((int) $row->to_pop_id === $pop->id) {
                $balances[$lot] += (float) $row->qty;
                $firstSeenId[$lot] ??= $row->id;
            }
            if ((int) $row->from_pop_id === $pop->id) {
                $balances[$lot] -= (float) $row->qty;
            }
        }

        $activeLots = collect($balances)->filter(fn ($qty) => $qty > 0.001)->keys();

        return $this->orderLotsByFirstArrival($activeLots, $firstSeenId);
    }

    /**
     * @param  Collection<int, string>  $lots
     * @param  array<string, int>  $firstSeenId
     * @return array{0: ?string, 1: ?string}
     */
    private function orderLotsByFirstArrival(Collection $lots, array $firstSeenId): array
    {
        if ($lots->isEmpty()) {
            return [null, null];
        }

        $ordered = $lots->sort(fn ($a, $b) => ($firstSeenId[$a] ?? PHP_INT_MAX) <=> ($firstSeenId[$b] ?? PHP_INT_MAX))->values();

        return [$ordered->get(0), $ordered->get(1)];
    }

    /**
     * SERIALIZED/ROLL — cari posisi unit aktif per tanggal cutoff, lalu
     * pecah ke 2 slot (Lama & Baru) berdasarkan snapshot harga unit.
     *
     * @return array{lama: array{qty: float, harga: ?float}, baru: array{qty: float, harga: ?float}, nilai: float}
     */
    private function unitsAsOf(Pop $pop, Item $item, Carbon $exclusiveCutoff, string $idColumn, bool $isStokAwal = false): array
    {
        $latestIds = InventoryTransaction::query()
            ->where('item_id', $item->id)
            ->whereNotNull($idColumn)
            ->where('created_at', '<', $exclusiveCutoff)
            ->selectRaw("{$idColumn} as unit_id, MAX(id) as max_id")
            ->groupBy($idColumn)
            ->pluck('max_id');

        if ($latestIds->isEmpty()) {
            return ['lama' => ['qty' => 0.0, 'harga' => null], 'baru' => ['qty' => 0.0, 'harga' => null], 'nilai' => 0.0];
        }

        $rows = InventoryTransaction::query()
            ->whereIn('id', $latestIds)
            ->where('to_pop_id', $pop->id)
            ->whereNotIn('type', [InventoryTransactionType::DEPLOY->value, InventoryTransactionType::REGISTER_INFRA->value])
            ->orderBy('id')
            ->get(['id', 'qty', 'unit_price_snapshot']);

        return $this->splitUnitRowsIntoSlots($rows, $isStokAwal, $pop, $item, $exclusiveCutoff);
    }

    /**
     * Kelompokkan baris unit Serialized/Roll ke 2 slot harga (Lama & Baru).
     *
     * @param  Collection<int, InventoryTransaction>  $rows
     * @return array{lama: array{qty: float, harga: ?float}, baru: array{qty: float, harga: ?float}, nilai: float}
     */
    private function splitUnitRowsIntoSlots(Collection $rows, bool $isStokAwal, ?Pop $pop = null, ?Item $item = null, ?Carbon $cutoff = null, ?array $earliestPrices = null): array
    {
        if ($rows->isEmpty()) {
            return ['lama' => ['qty' => 0.0, 'harga' => null], 'baru' => ['qty' => 0.0, 'harga' => null], 'nilai' => 0.0];
        }

        // Kelompokkan per harga unit
        $groupsByPrice = [];
        foreach ($rows as $row) {
            $priceKey = $row->unit_price_snapshot !== null ? (string) round((float) $row->unit_price_snapshot, 2) : 'null';
            if (! isset($groupsByPrice[$priceKey])) {
                $groupsByPrice[$priceKey] = [
                    'price' => $row->unit_price_snapshot !== null ? (float) $row->unit_price_snapshot : null,
                    'qty' => 0.0,
                    'first_id' => $row->id,
                ];
            }
            $groupsByPrice[$priceKey]['qty'] += (float) $row->qty;
            $groupsByPrice[$priceKey]['first_id'] = min($groupsByPrice[$priceKey]['first_id'], $row->id);
        }

        // Urutkan grup harga berdasarkan kedatangan paling awal
        $sortedGroups = collect($groupsByPrice)
            ->sortBy('first_id')
            ->values();

        $totalNilai = (float) $rows->sum(fn ($r) => (float) $r->qty * (float) ($r->unit_price_snapshot ?? 0));

        // Jika hanya ada 1 harga:
        if ($sortedGroups->count() === 1) {
            $single = $sortedGroups->first();

            // Pada Stok Awal (saldo sebelum periode mulai), harga tunggal adalah Harga Awal (Lama).
            // Pada Stok Akhir atau Barang Masuk, cek apakah ada harga baseline sebelumnya di Stok Awal.
            if ($isStokAwal) {
                return [
                    'lama' => ['qty' => $single['qty'], 'harga' => $single['price']],
                    'baru' => ['qty' => 0.0, 'harga' => null],
                    'nilai' => $totalNilai,
                ];
            }

            // Untuk Barang Masuk / Stok Akhir:
            // Cek harga pertama kali item ini pernah diterima di POP ini
            if ($earliestPrices !== null) {
                $earliestPrice = $pop && $item ? ($earliestPrices[$pop->id][$item->id] ?? null) : null;
            } else {
                $earliestTx = ($pop && $item) ? InventoryTransaction::query()
                    ->where('item_id', $item->id)
                    ->where('to_pop_id', $pop->id)
                    ->whereNotNull('unit_price_snapshot')
                    ->orderBy('id')
                    ->first(['unit_price_snapshot', 'created_at']) : null;
                $earliestPrice = $earliestTx ? (float) $earliestTx->unit_price_snapshot : null;
            }

            if ($earliestPrice !== null && $single['price'] !== null && abs($single['price'] - $earliestPrice) > 0.01) {
                // Harga saat ini berbeda dari harga awal yang pernah ada -> masuk slot Baru
                return [
                    'lama' => ['qty' => 0.0, 'harga' => null],
                    'baru' => ['qty' => $single['qty'], 'harga' => $single['price']],
                    'nilai' => $totalNilai,
                ];
            }

            // Jika sama dengan harga awal atau ini adalah batch awal pertama -> masuk slot Lama
            return [
                'lama' => ['qty' => $single['qty'], 'harga' => $single['price']],
                'baru' => ['qty' => 0.0, 'harga' => null],
                'nilai' => $totalNilai,
            ];
        }

        // Jika ada 2 atau lebih kelompok harga:
        $lamaGroup = $sortedGroups->get(0);
        $remainingGroups = $sortedGroups->slice(1);

        $qtyBaru = (float) $remainingGroups->sum('qty');
        $nilaiBaru = (float) $remainingGroups->sum(fn ($g) => (float) $g['qty'] * (float) ($g['price'] ?? 0));
        $hargaBaru = $qtyBaru > 0 ? round($nilaiBaru / $qtyBaru, 2) : null;

        return [
            'lama' => ['qty' => $lamaGroup['qty'], 'harga' => $lamaGroup['price']],
            'baru' => ['qty' => $qtyBaru, 'harga' => $hargaBaru],
            'nilai' => $totalNilai,
        ];
    }
}
