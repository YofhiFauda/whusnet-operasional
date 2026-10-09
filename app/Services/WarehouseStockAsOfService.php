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
 * Aturan turunan buat SERIALIZED/ROLL SENGAJA sesederhana mungkin, BUKAN
 * state machine baru — cuma manfaatin konvensi kolom yang SUDAH konsisten
 * dipakai `InventoryReceiveService`/`InventoryTransferService`/
 * `InventoryIssueService`/`InventoryAdjustmentService` sejak awal (§2):
 * transaksi TERAKHIR sebuah unit SEBELUM tanggal X menentukan semuanya —
 * kalau baris itu `to_pop_id` = POP yang dicari, unit itu "stok" di situ
 * (RECEIVE/TRANSFER-confirm/RETURN/REASSIGN semua nulis `to_pop_id` pas
 * barang nyata nyampe gudang). Baris LAIN (ISSUE/TRANSFER-dispatch/
 * ADJUSTMENT/INSTALL/TRANSFER_CUSTODY) TIDAK PERNAH nulis `to_pop_id` —
 * otomatis gak terhitung stok tanpa percabangan per `type` sama sekali.
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
        if ($item->tracking_type === TrackingType::QUANTITY) {
            return $this->quantityAsOf($pop, $item, $exclusiveCutoff, $isStokAwal);
        }

        $idColumn = $item->tracking_type === TrackingType::ROLL ? 'roll_id' : 'serial_id';

        return $this->unitsAsOf($pop, $item, $exclusiveCutoff, $idColumn, $isStokAwal);
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
            ->orderBy('id')
            ->get(['id', 'lot_no', 'qty', 'to_pop_id', 'from_pop_id', 'unit_price_snapshot']);

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
    private function splitUnitRowsIntoSlots(Collection $rows, bool $isStokAwal, ?Pop $pop = null, ?Item $item = null, ?Carbon $cutoff = null): array
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
            $earliestTx = ($pop && $item) ? InventoryTransaction::query()
                ->where('item_id', $item->id)
                ->where('to_pop_id', $pop->id)
                ->whereNotNull('unit_price_snapshot')
                ->orderBy('id')
                ->first(['unit_price_snapshot', 'created_at']) : null;

            $earliestPrice = $earliestTx ? (float) $earliestTx->unit_price_snapshot : null;

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
