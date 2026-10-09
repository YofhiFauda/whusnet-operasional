<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Warehouse\Concerns\ChecksAssetScope;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\InventoryTransfer;
use App\Models\User;
use App\Services\EffectiveAccessService;
use App\Support\LikeSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Pencarian universal gudang (analisa-ui-ux-warehouse.md §S2) — SATU kotak di
 * header (`components/warehouse/header.blade.php`), terjangkau dari SEMUA
 * halaman gudang. Cari SN, roll, nomor transfer, dan nomor surat jalan
 * sekaligus — pelanggan/teknisi dicari lewat kecocokan di SN/roll (sudah
 * tercakup, bukan tipe hasil terpisah; "Pelanggan" di analisa §S2 berarti
 * "SN yang terpasang di pelanggan itu", bukan halaman detail pelanggan baru
 * yang permission-nya beda modul).
 *
 * Scope SN/roll pakai `ChecksAssetScope` — ATURAN YANG SAMA dengan
 * Traceability, bukan disalin ulang. Scope Transfer: `assertViewableByEitherSide`
 * (sisi manapun, meniru `WarehouseTransferController`) — tapi di sini list
 * filter diam-diam gak match (pola list), bukan 403, karena ini pencarian,
 * bukan buka 1 dokumen spesifik.
 *
 * Permission reuse `warehouse.view` (sama kayak Dashboard/Stok) — satu-satunya
 * permission yang DIPASTIKAN dipegang user yang berhak lihat kotak cari ini
 * di header (`$canViewWarehouse`). User yang cuma punya
 * `warehouse_custody.view`/`warehouse_traceability.view` tanpa `warehouse.view`
 * TIDAK kehilangan apa pun — search per-field yang sudah ada di halaman
 * masing-masing (Custody, Traceability) tetap jalan apa adanya.
 */
class WarehouseSearchController extends Controller
{
    use ChecksAssetScope;

    public function index(Request $request, EffectiveAccessService $access): View
    {
        $user = $request->user();
        $query = LikeSearch::sanitize((string) $request->query('q', ''));

        $results = $query !== '' ? $this->search($query, $access, $user) : collect();

        return view('warehouse.search.index', compact('query', 'results'));
    }

    /**
     * @return Collection<int, array{type: string, label: string, sub: string, url: string}>
     */
    private function search(string $query, EffectiveAccessService $access, User $user): Collection
    {
        $like = '%'.$query.'%';

        // Scope POP diterapkan DI QUERY (`scopeSerialQuery`/`scopeRollQuery`,
        // 2026-10-08 — sama aturan dengan Traceability, lihat docblock
        // `ChecksAssetScope`), bukan ambil 100 baris mentah lalu difilter di
        // PHP baru dipotong N. `limit(20)` beneran ambil 20 baris yang sudah
        // valid scope-nya.
        $serials = $this->scopeSerialQuery(
            InventorySerial::query()
                ->where(function ($w) use ($like) {
                    $w->where('serial_number', 'like', $like)
                        ->orWhereHas('customer', fn ($c) => $c->where('full_name', 'like', $like))
                        ->orWhereHas('currentTechnician', fn ($t) => $t->where('name', 'like', $like))
                        ->orWhereHas('item', fn ($i) => $i->where('name', 'like', $like)->orWhere('code', 'like', $like));
                }),
            $access, $user
        )
            ->with(['item', 'currentPop', 'currentTechnician', 'customer'])
            ->orderBy('serial_number')
            ->limit(20)->get();

        $rolls = $this->scopeRollQuery(
            InventoryRoll::query()
                ->where(function ($w) use ($like) {
                    $w->where('roll_code', 'like', $like)
                        ->orWhereHas('currentTechnician', fn ($t) => $t->where('name', 'like', $like))
                        ->orWhereHas('item', fn ($i) => $i->where('name', 'like', $like)->orWhere('code', 'like', $like));
                }),
            $access, $user
        )
            ->with(['item', 'currentPop', 'currentTechnician'])
            ->orderBy('roll_code')
            ->limit(20)->get();

        $transfers = $this->searchTransfers($query, $access, $user);

        $serialResults = $serials->map(fn ($s) => [
            'type' => 'serial',
            'label' => $s->serial_number,
            'sub' => $s->item->name.' · '.($s->currentPop->name ?? $s->currentTechnician->name ?? $s->customer->full_name ?? '—').' · '.$s->status->label(),
            'url' => route('warehouse.traceability.index', ['sn' => $s->serial_number]),
        ]);

        $rollResults = $rolls->map(fn ($r) => [
            'type' => 'roll',
            'label' => $r->roll_code,
            'sub' => $r->item->name.' · '.($r->currentPop->name ?? $r->currentTechnician->name ?? '—').' · '.$r->status->label(),
            'url' => route('warehouse.traceability.index', ['roll' => $r->roll_code]),
        ]);

        $transferResults = $transfers->map(fn (InventoryTransfer $t) => [
            'type' => 'transfer',
            'label' => $t->reference_number,
            'sub' => ($t->fromPop->name ?? '—').' → '.($t->toPop->name ?? '—').' · '.$t->status->label(),
            'url' => route('warehouse.transfers.show', $t),
        ]);

        return $serialResults->concat($rollResults)->concat($transferResults)->values();
    }

    /**
     * Transfer by `reference_number` ATAU nomor surat jalan (format
     * `SJ/WHUS/{tahun}/{bulan}/{urut}`, lihat `WarehouseTransferController::suratJalanNumber()`
     * — nomornya deterministik dari id+created_at transfer, bukan kolom
     * tersimpan, jadi dicari dengan membalik rumus itu, bukan `LIKE` ke kolom.
     *
     * @return Collection<int, InventoryTransfer>
     */
    private function searchTransfers(string $query, EffectiveAccessService $access, User $user): Collection
    {
        $hasAllAccess = $access->hasAllPopAccess($user);
        $allowedPopIds = $hasAllAccess ? [] : $access->getAllowedPopIds($user);

        $byReference = InventoryTransfer::query()
            ->where('reference_number', 'like', '%'.$query.'%')
            ->when(! $hasAllAccess, fn ($q) => $q->where(
                fn ($qq) => $qq->whereIn('from_pop_id', $allowedPopIds)->orWhereIn('to_pop_id', $allowedPopIds)
            ))
            ->with(['fromPop', 'toPop'])
            ->limit(20)->get();

        $bySuratJalan = collect();
        if (preg_match('#SJ[/\-]?WHUS[/\-]?(\d{4})[/\-]?(\d{2})[/\-]?0*(\d+)#i', $query, $m)) {
            [$year, $month, $id] = [$m[1], $m[2], (int) $m[3]];
            $transfer = InventoryTransfer::query()->with(['fromPop', 'toPop'])->find($id);

            if ($transfer
                && $transfer->created_at->format('Y') === $year
                && $transfer->created_at->format('m') === $month
                && ($hasAllAccess || in_array($transfer->from_pop_id, $allowedPopIds, true) || in_array($transfer->to_pop_id, $allowedPopIds, true))) {
                $bySuratJalan->push($transfer);
            }
        }

        return $byReference->concat($bySuratJalan)->unique('id')->values();
    }
}
