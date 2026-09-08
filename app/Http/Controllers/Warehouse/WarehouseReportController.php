<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\InventoryTransactionType;
use App\Http\Controllers\Controller;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\Pop;
use App\Services\EffectiveAccessService;
use App\Services\InventoryAdjustmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Laporan Gudang — agregat periodik (Fase 2 P2, fase-2-adaptasi-wms.md).
 * Read-only murni, reuse data yang UDAH tercatat di `inventory_transactions`
 * — cuma nyusun ulang jadi angka per periode, BUKAN sumber kebenaran baru.
 *
 * Realtime SENGAJA SKIP (keputusan sadar Fase 1, rancangan-ui.md §2.1) —
 * volume transaksi gudang ISP lokal gak sebanding kompleksitas broadcast
 * channel tambahan. Reload manual cukup, sama pola Dashboard Gudang.
 *
 * SATU halaman, DUA tab (movement + adjustment) — bukan dua route terpisah,
 * biar filter periode/POP-nya konsisten satu form buat dua-duanya.
 */
class WarehouseReportController extends Controller
{
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

        $period = $request->query('period') ?: now()->format('Y-m');
        $popFilter = $request->integer('pop_id') ?: null;

        $periodStart = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();

        $movementRows = $this->buildMovementSummary($popIds, $popFilter, $periodStart, $periodEnd);
        $adjustmentRows = $this->buildAdjustmentSummary($popIds, $popFilter, $periodStart, $periodEnd);
        $movementCounts = $this->buildMovementCounts($popIds, $popFilter, $periodStart, $periodEnd);

        // KPI ringkasan periode — SENGAJA hitung JUMLAH TRANSAKSI, bukan
        // SUM(qty), dengan alasan yang sama kayak di atas: campur item beda
        // satuan gak valid dijumlah jadi satu angka headline.
        $scopedPops = $popFilter ? collect([$popFilter])->intersect($popIds) : $popIds;
        $kpi = [
            'receive_count' => InventoryTransaction::query()->where('type', InventoryTransactionType::RECEIVE->value)
                ->whereIn('to_pop_id', $scopedPops)->whereBetween('created_at', [$periodStart, $periodEnd])->count(),
            'transfer_out_count' => InventoryTransaction::query()->where('type', InventoryTransactionType::TRANSFER->value)
                ->whereIn('from_pop_id', $scopedPops)->whereBetween('created_at', [$periodStart, $periodEnd])->count(),
            'issue_count' => InventoryTransaction::query()->where('type', InventoryTransactionType::ISSUE->value)
                ->whereIn('from_pop_id', $scopedPops)->whereBetween('created_at', [$periodStart, $periodEnd])->count(),
            'adjustment_count' => collect($adjustmentRows)->sum('count'),
        ];

        // Chart Kerugian: JUMLAH KEJADIAN per lokasi per kategori — turunan
        // dari $adjustmentRows (yang granularitasnya per-item), aman dijumlah
        // ulang lintas item di sini karena metriknya COUNT, bukan qty.
        $lossChartData = collect($adjustmentRows)
            ->groupBy('pop_label')
            ->map(function ($rows, $popLabel) {
                $byReason = $rows->groupBy('reason')->map(fn ($g) => $g->sum('count'));

                return array_merge(['pop_label' => $popLabel], $byReason->all());
            })
            ->values()
            ->all();

        return view('warehouse.reports.index', compact(
            'pops', 'period', 'popFilter', 'movementRows', 'adjustmentRows', 'movementCounts', 'kpi', 'lossChartData'
        ));
    }

    /**
     * Versi COUNT(*) dari movement summary — khusus buat bahan bakar bar
     * chart. Aman digabung lintas item (ngitung JUMLAH TRANSAKSI, bukan
     * qty), beda dari `buildMovementSummary()` yang qty-nya cuma valid
     * ditampilkan per-item (lihat komentar di sana).
     *
     * @param  Collection<int, int>  $popIds
     * @return array<int, array{pop_name: string, receive: int, transfer_in: int, transfer_out: int, issue: int}>
     */
    private function buildMovementCounts($popIds, ?int $popFilter, Carbon $start, Carbon $end): array
    {
        $scopedPops = $popFilter ? collect([$popFilter])->intersect($popIds) : $popIds;

        $countBy = fn (string $type, string $column) => InventoryTransaction::query()
            ->where('type', $type)
            ->whereIn($column, $scopedPops)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw("{$column} as pop_id, COUNT(*) as total")
            ->groupBy($column)
            ->pluck('total', 'pop_id');

        $receive = $countBy(InventoryTransactionType::RECEIVE->value, 'to_pop_id');
        $transferIn = $countBy(InventoryTransactionType::TRANSFER->value, 'to_pop_id');
        $transferOut = $countBy(InventoryTransactionType::TRANSFER->value, 'from_pop_id');
        $issue = $countBy(InventoryTransactionType::ISSUE->value, 'from_pop_id');

        return Pop::query()
            ->whereIn('id', $scopedPops)
            ->orderBy('type')->orderBy('name')
            ->get()
            ->map(fn (Pop $pop) => [
                'pop_name' => $pop->name,
                'receive' => (int) ($receive[$pop->id] ?? 0),
                'transfer_in' => (int) ($transferIn[$pop->id] ?? 0),
                'transfer_out' => (int) ($transferOut[$pop->id] ?? 0),
                'issue' => (int) ($issue[$pop->id] ?? 0),
            ])
            ->filter(fn ($row) => $row['receive'] + $row['transfer_in'] + $row['transfer_out'] + $row['issue'] > 0)
            ->values()
            ->all();
    }

    /**
     * Agregat qty RECEIVE/TRANSFER(masuk+keluar)/ISSUE per gudang per
     * periode. Kolom `from_pop_id`/`to_pop_id` udah lengkap di SEMUA baris
     * tipe ini (beda dari ADJUSTMENT custody, lihat catatan `buildAdjustmentSummary()`)
     * — jadi atribusi per-gudang di sini akurat penuh, bukan perkiraan.
     *
     * @param  Collection<int, int>  $popIds
     * @return array<int, array{pop: Pop, receive: float, transfer_in: float, transfer_out: float, issue: float}>
     */
    private function buildMovementSummary($popIds, ?int $popFilter, Carbon $start, Carbon $end): array
    {
        $scopedPops = $popFilter ? collect([$popFilter])->intersect($popIds) : $popIds;

        $receive = InventoryTransaction::query()
            ->where('type', InventoryTransactionType::RECEIVE->value)
            ->whereIn('to_pop_id', $scopedPops)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('to_pop_id as pop_id, SUM(qty) as total')
            ->groupBy('to_pop_id')
            ->pluck('total', 'pop_id');

        $transferIn = InventoryTransaction::query()
            ->where('type', InventoryTransactionType::TRANSFER->value)
            ->whereIn('to_pop_id', $scopedPops)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('to_pop_id as pop_id, SUM(qty) as total')
            ->groupBy('to_pop_id')
            ->pluck('total', 'pop_id');

        $transferOut = InventoryTransaction::query()
            ->where('type', InventoryTransactionType::TRANSFER->value)
            ->whereIn('from_pop_id', $scopedPops)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('from_pop_id as pop_id, SUM(qty) as total')
            ->groupBy('from_pop_id')
            ->pluck('total', 'pop_id');

        $issue = InventoryTransaction::query()
            ->where('type', InventoryTransactionType::ISSUE->value)
            ->whereIn('from_pop_id', $scopedPops)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('from_pop_id as pop_id, SUM(qty) as total')
            ->groupBy('from_pop_id')
            ->pluck('total', 'pop_id');

        // Rincian PER ITEM (2026-09-07 — sebelumnya SUM(qty) di atas
        // digabung LINTAS ITEM beda satuan dalam 1 gudang: ONT (unit) +
        // kabel (meter) diterima bulan yang sama bakal kejumlah jadi satu
        // angka campur unit. Angka pop-level di atas TETAP dihitung apa
        // adanya (dipakai test lama & agregat kasar), tapi VIEW render dari
        // `items` di bawah ini — satu baris tabel = satu item, satu satuan,
        // gak pernah dijumlah lintas unit.
        $itemBreakdown = function (string $type, string $direction) use ($scopedPops, $start, $end) {
            $popColumn = $direction === 'to' ? 'to_pop_id' : 'from_pop_id';

            return InventoryTransaction::query()
                ->where('type', $type)
                ->whereIn($popColumn, $scopedPops)
                ->whereBetween('created_at', [$start, $end])
                ->selectRaw("{$popColumn} as pop_id, item_id, SUM(qty) as total")
                ->groupBy($popColumn, 'item_id')
                ->get()
                ->keyBy(fn ($row) => $row->pop_id.'-'.$row->item_id);
        };

        $receiveByItem = $itemBreakdown(InventoryTransactionType::RECEIVE->value, 'to');
        $transferInByItem = $itemBreakdown(InventoryTransactionType::TRANSFER->value, 'to');
        $transferOutByItem = $itemBreakdown(InventoryTransactionType::TRANSFER->value, 'from');
        $issueByItem = $itemBreakdown(InventoryTransactionType::ISSUE->value, 'from');

        $itemIds = collect([$receiveByItem, $transferInByItem, $transferOutByItem, $issueByItem])
            ->flatMap(fn ($c) => $c->pluck('item_id'))
            ->unique();
        $itemsById = Item::query()->whereIn('id', $itemIds)->get()->keyBy('id');

        return Pop::query()
            ->whereIn('id', $scopedPops)
            ->orderBy('type')->orderBy('name')
            ->get()
            ->map(function (Pop $pop) use ($receive, $transferIn, $transferOut, $issue, $receiveByItem, $transferInByItem, $transferOutByItem, $issueByItem, $itemsById) {
                $itemIdsForPop = collect([$receiveByItem, $transferInByItem, $transferOutByItem, $issueByItem])
                    ->flatMap(fn ($c) => $c->filter(fn ($r) => $r->pop_id === $pop->id)->pluck('item_id'))
                    ->unique();

                $items = $itemIdsForPop->map(function ($itemId) use ($pop, $receiveByItem, $transferInByItem, $transferOutByItem, $issueByItem, $itemsById) {
                    $key = $pop->id.'-'.$itemId;
                    $item = $itemsById->get($itemId);

                    return [
                        'item_name' => $item?->name ?? "(item #{$itemId})",
                        'unit' => $item?->unit ?? '',
                        'receive' => (float) ($receiveByItem[$key]->total ?? 0),
                        'transfer_in' => (float) ($transferInByItem[$key]->total ?? 0),
                        'transfer_out' => (float) ($transferOutByItem[$key]->total ?? 0),
                        'issue' => (float) ($issueByItem[$key]->total ?? 0),
                    ];
                })->values();

                return [
                    'pop' => $pop,
                    'receive' => (float) ($receive[$pop->id] ?? 0),
                    'transfer_in' => (float) ($transferIn[$pop->id] ?? 0),
                    'transfer_out' => (float) ($transferOut[$pop->id] ?? 0),
                    'issue' => (float) ($issue[$pop->id] ?? 0),
                    'items' => $items,
                ];
            })
            // Baris gudang yang gak py pergerakan sama sekali di periode ini
            // gak usah tampil — kebisingan, bukan info (fase-2-adaptasi-wms.md
            // P2: "item tanpa transaksi di periode tidak muncul, bukan baris nol").
            ->filter(fn ($row) => $row['receive'] + $row['transfer_in'] + $row['transfer_out'] + $row['issue'] > 0)
            ->values()
            ->all();
    }

    /**
     * Rekap kerugian (LOST/DAMAGED/SCRAPPED/QUARANTINE/dst — kategori
     * `InventoryAdjustmentService::REASON_CATEGORIES`) per kategori per
     * periode.
     *
     * KETERBATASAN JUJUR (bukan bug, keterbatasan data ledger): atribusi
     * per-CABANG cuma akurat buat adjustment yang nyentuh saldo POP
     * (`adjustPopBalance()`, kolom `to_pop_id` keisi). Klaim LOST/DAMAGED
     * lewat `adjustSerialStatus()`/`adjustCustody()` (custody TEKNISI, bukan
     * gudang) `from_pop_id`-nya SERING null — barang lagi di tangan teknisi,
     * bukan di POP manapun saat itu (`current_pop_id` null by design, itu
     * makna "sedang di custody"). Baris begini ditandai POP "—" (Custody
     * Teknisi) di rekap, BUKAN dipaksa nebak gudang asalnya — nebak salah
     * lebih berbahaya daripada jujur bilang gak tau.
     *
     * @param  Collection<int, int>  $popIds
     * @return array<int, array{reason: string, reason_label: string, pop_label: string, count: int, total_qty: float}>
     */
    private function buildAdjustmentSummary($popIds, ?int $popFilter, Carbon $start, Carbon $end): array
    {
        $scopedPops = $popFilter ? collect([$popFilter])->intersect($popIds) : $popIds;

        $rows = InventoryTransaction::query()
            ->where('type', InventoryTransactionType::ADJUSTMENT->value)
            ->where(function ($q) use ($scopedPops, $popFilter) {
                $q->whereIn('to_pop_id', $scopedPops)
                    ->orWhereIn('from_pop_id', $scopedPops)
                    // Custody teknisi (from_pop_id null) TETAP ikut kalau gak
                    // ada filter POP eksplisit — kerugian custody tetap
                    // relevan dipantau HQ walau gak bisa diatribusi ke cabang.
                    ->when(! $popFilter, fn ($qq) => $qq->orWhere(fn ($qqq) => $qqq->whereNull('to_pop_id')->whereNull('from_pop_id')));
            })
            ->whereBetween('created_at', [$start, $end])
            ->with(['toPop', 'fromPop', 'item'])
            ->get();

        $reasonLabels = InventoryAdjustmentService::REASON_CATEGORIES;

        // Grouping key WAJIB ikut item_id (2026-09-07) — sebelumnya cuma
        // reason+lokasi, jadi `total_qty` bisa gabung ONT (unit) + kabel
        // (meter) kalau kebetulan sama-sama "damaged" di gudang yang sama
        // bulan itu. Satu baris = satu reason + satu lokasi + satu item =
        // satu satuan, aman dijumlah.
        return $rows->groupBy(function (InventoryTransaction $row) {
            $popLabel = $row->toPop->name ?? $row->fromPop->name ?? '— (Custody Teknisi)';

            return $row->reason.'|'.$popLabel.'|'.$row->item_id;
        })->map(function ($group) use ($reasonLabels) {
            $first = $group->first();
            $popLabel = $first->toPop->name ?? $first->fromPop->name ?? '— (Custody Teknisi)';

            return [
                'reason' => $first->reason,
                'reason_label' => $reasonLabels[$first->reason] ?? $first->reason,
                'pop_label' => $popLabel,
                'item_name' => $first->item->name ?? '(item dihapus)',
                'unit' => $first->item->unit ?? '',
                'count' => $group->count(),
                'total_qty' => (float) $group->sum(fn ($r) => abs((float) $r->qty)),
            ];
        })->sortBy('reason_label')->values()->all();
    }
}
