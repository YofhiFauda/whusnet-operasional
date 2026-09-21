<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\InventoryTransactionType;
use App\Http\Controllers\Controller;
use App\Models\InventoryTransaction;
use App\Models\Pop;
use App\Services\EffectiveAccessService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Riwayat Mutasi (koreksi IA Gudang, 2026-09-03) — satu halaman buat browse
 * SEMUA ledger, dipaginasi+filter, tiap baris nge-link ke dokumen sumbernya
 * (Transfer/Issue/Receive show()). Sebelumnya SATU-SATUNYA cara balik ke
 * halaman detail Transfer/Issue/Receive cuma lewat redirect pas create/
 * konfirmasi — begitu ditinggal, dokumennya ilang gak bisa ditemu lagi
 * kecuali nebak URL (laporan user: "list/detail tersembunyi", 2026-09-03).
 * Dashboard punya versi ringkas (25 baris, gak dipaginasi) buat sekilas —
 * halaman ini yang buat BENERAN nyari.
 *
 * Cuma VIEW — permission reuse `warehouse.view` (sama kayak Dashboard),
 * discope sama persis pola `WarehouseController`/`WarehouseStockController`.
 *
 * **Grouping per dokumen (2026-09-16)** — satu Input/Transfer/Serah Terima
 * bisa punya puluhan baris ledger (1 baris per SN/lot), sebelumnya semua
 * baris itu tampil terpisah di sini (30 modem + 20 roll kabel = 50 baris
 * berjejer). Sekarang dikelompokkan jadi SATU baris per dokumen
 * (`reference_number`), diklik baru masuk ke halaman detail yang sudah ADA
 * & sudah nampilin rincian per barang (`WarehouseReceiveController::show()`
 * / `WarehouseIssueController::show()` — dua-duanya query
 * `where('reference_number', $reference)`, jadi TIDAK butuh perubahan sama
 * sekali). RECEIVE & ISSUE dikelompokkan per `reference_number`. TRANSFER
 * dikelompokkan per `reference_number` **+ arah leg** (`from_pop_id` terisi
 * = leg "Dikirim", `to_pop_id` terisi = leg "Diterima") — dua leg itu
 * SENGAJA tetap kartu terpisah (ditambahkan 2026-09-03 justru buat
 * membedakan dua leg biar gak kelihatan kayak duplikat, lihat komentar
 * `$typeLabel` di bawah). Tipe lain (return/adjustment/stock_opname/
 * transfer_custody/install) TIDAK punya halaman detail per-dokumen sama
 * sekali (dicek: nihil route show() buat tipe-tipe itu) — tetap tampil apa
 * adanya, 1 kartu per baris, gak dikelompokkan.
 *
 * Grouping dilakukan di PHP (bukan `GROUP BY` SQL) sengaja — kunci grup beda
 * bentuk per tipe (reference_number polos vs reference_number+arah leg),
 * nulis itu sebagai satu ekspresi SQL portable across sqlite/mysql
 * (dua-duanya dipakai environment ini) bikin query jauh lebih ribet buat
 * manfaat yang gak sepadan. Makanya paginasi juga manual
 * (`LengthAwarePaginator` atas Collection hasil grouping) — pola yang sama
 * dipakai `WarehouseStockController::index()`.
 */
class WarehouseHistoryController extends Controller
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

        $typeFilter = $request->query('type');
        $popFilter = $request->integer('pop_id') ?: null;
        $search = trim((string) $request->query('search', ''));
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $conditionFilter = $request->query('condition');
        $adjustmentReasonFilter = $request->query('adjustment_reason');

        $ledger = InventoryTransaction::query()
            ->where(fn ($q) => $q->whereIn('from_pop_id', $popIds)->orWhereIn('to_pop_id', $popIds))
            ->when($typeFilter, fn ($q) => $q->where('type', $typeFilter))
            ->when($popFilter, fn ($q) => $q->where(fn ($qq) => $qq->where('from_pop_id', $popFilter)->orWhere('to_pop_id', $popFilter)))
            // Filter Kondisi (analisa-gap-kondisi-barang.md rancangan poin 6)
            // — SERIALIZED-only, baca `serial.condition`/`condition_checked_at`,
            // BUKAN kolom sendiri di ledger (kondisi nempel di unit fisiknya,
            // bukan per-transaksi). 4 opsi ini niru persis badge UI poin 8.
            ->when($conditionFilter, function ($q) use ($conditionFilter) {
                $q->whereHas('serial', function ($sq) use ($conditionFilter) {
                    match ($conditionFilter) {
                        'new' => $sq->where(fn ($w) => $w->where('condition', 'new')->orWhereNull('condition')),
                        'unchecked' => $sq->where('condition', '!=', 'new')->whereNull('condition_checked_at'),
                        'checked_good' => $sq->where('condition', 'used_good')->whereNotNull('condition_checked_at'),
                        'damaged' => $sq->where('condition', 'used_damaged'),
                        default => null,
                    };
                });
            })
            // Filter "Alasan" — CUMA relevan buat type=ADJUSTMENT (rancangan
            // poin 7). Baca `resulting_status` (snapshot eksplisit dari
            // adjustSerialStatus()/adjustRollStatus()) buat 4 kategori
            // lifecycle; `shrinkage_on_return` khusus dibaca dari `reason`
            // (adjustCustody() gak py resulting_status — bukan transisi
            // status SN/Roll, cuma pengurangan qty custody). "Opname" TIDAK
            // dimasukkan di sini — itu type ledger TERPISAH (STOCK_OPNAME),
            // sudah punya filter Tipe sendiri, taruh di sini cuma bikin
            // dobel & salah kaprah.
            ->when($adjustmentReasonFilter, function ($q) use ($adjustmentReasonFilter) {
                match ($adjustmentReasonFilter) {
                    'lost', 'damaged', 'scrapped', 'quarantine' => $q->where('resulting_status', $adjustmentReasonFilter),
                    'shrinkage_on_return' => $q->whereNull('resulting_status')->where('reason', 'shrinkage_on_return'),
                    'other' => $q->whereNull('resulting_status')->where('reason', '!=', 'shrinkage_on_return'),
                    default => null,
                };
            })
            // Cari SN / SKU nama barang / nomor referensi — 3 kolom beda tabel
            // (item, serial, transaksi itu sendiri), makanya whereHas ganda.
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('reference_number', 'like', "%{$search}%")
                        ->orWhereHas('item', fn ($iq) => $iq->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                        ->orWhereHas('serial', fn ($sq) => $sq->where('serial_number', 'like', "%{$search}%"));
                });
            })
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            // `transfer.toPop` — leg dispatch TRANSFER (to_pop_id masih
            // NULL, belum ditambahkan ke saldo tujuan, lihat docblock
            // migration `create_inventory_transactions_table`) butuh ini
            // buat nampilin tujuan SEBENARNYA, bukan fallback "Pelanggan /
            // Luar" yang nyasar (laporan user 2026-09-07: transfer yang
            // masih in-transit kelihatan kayak dikirim ke pelanggan).
            ->with(['item.category', 'fromPop', 'toPop', 'fromTechnician', 'toTechnician', 'serial', 'roll', 'createdBy', 'transfer.toPop'])
            ->latest('id')
            ->get();

        $groups = $this->groupByDocument($ledger);

        $perPage = 30;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $ledger = new LengthAwarePaginator(
            $groups->slice(($page - 1) * $perPage, $perPage)->values(),
            $groups->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $types = InventoryTransactionType::cases();

        return view('warehouse.history.index', compact('ledger', 'pops', 'types', 'typeFilter', 'popFilter', 'search', 'dateFrom', 'dateTo', 'conditionFilter', 'adjustmentReasonFilter'));
    }

    /**
     * Kelompokkan baris ledger jadi 1 entri per dokumen (RECEIVE/ISSUE per
     * `reference_number`, TRANSFER per `reference_number`+arah leg) — lihat
     * docblock kelas. Tipe lain gak dikelompokkan (1 baris = 1 entri, kunci
     * pakai `id` sendiri supaya gak ketiban gabung gara-gara reference_number
     * sama-sama NULL).
     *
     * @return Collection<int, object{
     *     key: string, type: InventoryTransactionType, typeLabel: string,
     *     representative: InventoryTransaction, lines: Collection<int, InventoryTransaction>,
     *     itemCount: int, lineCount: int, createdAt: Carbon
     * }>
     */
    private function groupByDocument(Collection $ledger): Collection
    {
        // Tipe yang PUNYA halaman detail per-dokumen (show() query by
        // reference_number) — cuma ini yang aman dikelompokkan, sisanya
        // gak ada tempat buat "lihat rincian" jadi percuma digabung.
        $groupableTypes = [InventoryTransactionType::RECEIVE->value, InventoryTransactionType::ISSUE->value, InventoryTransactionType::TRANSFER->value];

        return $ledger
            ->groupBy(function (InventoryTransaction $txn) use ($groupableTypes) {
                $type = $txn->type->value ?? '';

                if (! in_array($type, $groupableTypes, true) || ! $txn->reference_number) {
                    return 'single:'.$txn->id;
                }

                if ($type === InventoryTransactionType::TRANSFER->value) {
                    // Dua leg (dispatch/confirm) sengaja tetap kartu terpisah
                    // walau reference_number sama — lihat docblock kelas.
                    $leg = $txn->from_pop_id !== null ? 'out' : 'in';

                    return "transfer:{$txn->reference_number}:{$leg}";
                }

                return "{$type}:{$txn->reference_number}";
            })
            ->map(function (Collection $lines, string $key) {
                $representative = $lines->first();

                // Sama persis logic pembeda "Transfer Dikirim"/"Transfer
                // Diterima" yang sudah ada di view sebelum grouping ini
                // — dipindah ke sini karena view sekarang cuma lihat 1
                // representative per kartu, bukan tiap baris mentah.
                $typeLabel = match (true) {
                    $representative->type->value === InventoryTransactionType::TRANSFER->value && $representative->from_pop_id !== null => 'Transfer Dikirim',
                    $representative->type->value === InventoryTransactionType::TRANSFER->value && $representative->to_pop_id !== null => 'Transfer Diterima',
                    default => $representative->type->label(),
                };

                return (object) [
                    'key' => $key,
                    'type' => $representative->type,
                    'typeLabel' => $typeLabel,
                    'representative' => $representative,
                    'lines' => $lines->values(),
                    'itemCount' => $lines->pluck('item_id')->unique()->count(),
                    'lineCount' => $lines->count(),
                    'createdAt' => $lines->min('created_at'),
                ];
            })
            ->values()
            ->sortByDesc(fn ($group) => $group->createdAt)
            ->values();
    }
}
