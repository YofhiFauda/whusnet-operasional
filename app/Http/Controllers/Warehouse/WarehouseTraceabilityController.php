<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\ItemCondition;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Warehouse\Concerns\ChecksAssetScope;
use App\Http\Controllers\Warehouse\Concerns\ResolvesSelectedPop;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Pop;
use App\Models\User;
use App\Services\EffectiveAccessService;
use App\Services\InventoryReassignService;
use App\Support\LikeSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Asset Traceability (ADHOC-54, rancangan-ui.md §2.8) — cari SN atau roll
 * kabel, tampilin riwayat lengkap dari ledger (RECEIVE→TRANSFER→ISSUE→
 * INSTALL/pemakaian...).
 *
 * Scoping (§1.3 matrix, pop_admin "cabangnya saja"): pop_admin cuma boleh
 * nemu SN/roll yang PERNAH nyentuh gudang/pelanggan dalam scope-nya —
 * current_pop, issued_from_pop, ATAU pop pelanggan tempat dia terpasang.
 * Kalau gak masuk scope, dianggap "tidak ditemukan" (bukan pesan 403
 * eksplisit) — biar keberadaan SN/roll di cabang lain gak ikut bocor lewat
 * pesan error yang beda.
 */
class WarehouseTraceabilityController extends Controller
{
    use ChecksAssetScope;
    use ResolvesSelectedPop;

    public function index(Request $request, EffectiveAccessService $access): View
    {
        $serialNumber = trim((string) $request->query('sn', ''));
        $rollCode = trim((string) $request->query('roll', ''));
        $serial = null;
        $roll = null;
        $ledger = collect();
        $notFound = false;

        $user = auth()->user();

        // Filter POP (analisa-ui-ux §M1) — Traceability sebelumnya satu-satunya
        // halaman gudang TANPA filter cabang. Dropdown dibatasi ke POP dalam
        // scope aktor; filter ini menyempitkan pencarian daftar `q`, bukan
        // lookup langsung sn/roll (lookup langsung tetap dicek scope per item).
        $pops = Pop::query()
            ->warehouse()
            ->when(! $access->hasAllPopAccess($user), fn ($qq) => $qq->whereIn('id', $access->getAllowedPopIds($user)))
            ->orderBy('type')->orderBy('name')
            ->get();
        // Konteks cabang global (analisa-ui-ux-warehouse.md §S1).
        $popFilter = $this->resolveSelectedPopId($request, $access, $user);

        // Pencarian daftar (analisa-ui-ux §M2) — trace tidak lagi cuma lewat
        // SN/roll yang diketik persis: cari juga by nama pelanggan, nama
        // teknisi pemegang, nomor transfer/dokumen, nama/kode barang. Hasil
        // berupa kandidat yang di-scope POP, user klik buat buka detail.
        $query = LikeSearch::sanitize((string) $request->query('q', ''));
        $results = collect();

        if ($serialNumber !== '') {
            $found = InventorySerial::query()
                ->where('serial_number', $serialNumber)
                ->with(['item.category', 'currentPop', 'currentTechnician', 'customer.pop', 'issuedFromPop'])
                ->first();

            if ($found && $this->isSerialInScope($found, $access, auth()->user())) {
                $serial = $found;
                // `transfer.fromPop`/`transfer.toPop` & `fopTask.customer`
                // ditambah buat nutup 2 gap "timeline gak sesuai log asli"
                // (ketauan 2026-09-03):
                //  - Baris TRANSFER py DUA leg terpisah (dispatch cuma
                //    `from_pop_id`, confirm cuma `to_pop_id`) — leg confirm
                //    tanpa ini kelihatan kayak "Pengadaan (Baru)" padahal
                //    asalnya jelas (Pusat pengirim), cuma gak kesimpen ulang
                //    di baris ledger confirm-nya sendiri.
                //  - Baris INSTALL cuma py `from_technician_id`, gak ada
                //    tujuan sama sekali di kolom manapun — pelanggannya
                //    cuma bisa ditelusuri lewat `fop_task_id`.
                $ledger = InventoryTransaction::query()
                    ->where('serial_id', $serial->id)
                    ->with(['fromPop', 'toPop', 'fromTechnician', 'toTechnician', 'createdBy', 'transfer.fromPop', 'transfer.toPop', 'fopTask.customer'])
                    ->orderBy('id')
                    ->get();
            } else {
                $notFound = true;
            }
        } elseif ($rollCode !== '') {
            $found = InventoryRoll::query()
                ->where('roll_code', $rollCode)
                ->with(['item.category', 'currentPop', 'currentTechnician', 'issuedFromPop'])
                ->first();

            if ($found && $this->isRollInScope($found, $access, auth()->user())) {
                $roll = $found;
                // Roll gak punya baris ledger buat pemakaian harian (potong
                // meter) — itu SENGAJA cuma TaskMaterial, bukan
                // inventory_transactions (lihat docblock InventoryRoll).
                // Ledger di sini cuma RECEIVE/TRANSFER/ISSUE/RETURN/
                // ADJUSTMENT, view yang render "sisa X dari Y meter"
                // terpisah dari daftar ledger ini.
                $ledger = InventoryTransaction::query()
                    ->where('roll_id', $roll->id)
                    ->with(['fromPop', 'toPop', 'fromTechnician', 'toTechnician', 'createdBy', 'transfer.fromPop', 'transfer.toPop'])
                    ->orderBy('id')
                    ->get();
            } else {
                $notFound = true;
            }
        }

        // Jalankan pencarian daftar hanya kalau tidak sedang lookup langsung.
        if ($serialNumber === '' && $rollCode === '' && $query !== '') {
            $results = $this->searchCandidates($query, $popFilter, $access, $user);
        }

        return view('warehouse.traceability.index', compact(
            'serialNumber', 'rollCode', 'serial', 'roll', 'ledger', 'notFound',
            'query', 'results', 'pops', 'popFilter'
        ));
    }

    /**
     * Cari kandidat SN & roll (analisa-ui-ux §M2). Match: SN/roll code, nama
     * pelanggan, nama teknisi pemegang, nama/kode barang, dan nomor dokumen
     * transaksi (TRF/ISS/surat jalan via ledger). Scope POP diterapkan DI
     * QUERY (`scopeSerialQuery`/`scopeRollQuery` dari `ChecksAssetScope`,
     * 2026-10-08 — sebelumnya ambil 100 baris mentah lalu difilter scope di
     * PHP baru dipotong N; sekarang `limit(50)` beneran ambil 50 baris yang
     * SUDAH valid scope-nya). Jalur otorisasi yang SAMA dengan lookup
     * langsung (`isSerialInScope`/`isRollInScope`), bukan aturan kedua.
     *
     * @return Collection<int, array{type: string, serial: ?InventorySerial, roll: ?InventoryRoll}>
     */
    private function searchCandidates(string $query, ?int $popFilter, EffectiveAccessService $access, User $user): Collection
    {
        $like = '%'.$query.'%';

        // ID SN/roll yang dokumen transaksinya cocok (nomor transfer / surat jalan).
        $serialIdsByRef = InventoryTransaction::query()
            ->where('reference_number', 'like', $like)
            ->whereNotNull('serial_id')
            ->distinct()->pluck('serial_id');
        $rollIdsByRef = InventoryTransaction::query()
            ->where('reference_number', 'like', $like)
            ->whereNotNull('roll_id')
            ->distinct()->pluck('roll_id');

        $serials = $this->scopeSerialQuery(
            InventorySerial::query()
                ->where(function ($w) use ($like, $serialIdsByRef) {
                    $w->where('serial_number', 'like', $like)
                        ->orWhereHas('customer', fn ($c) => $c->where('full_name', 'like', $like))
                        ->orWhereHas('currentTechnician', fn ($t) => $t->where('name', 'like', $like))
                        ->orWhereHas('item', fn ($i) => $i->where('name', 'like', $like)->orWhere('code', 'like', $like))
                        ->orWhereIn('id', $serialIdsByRef);
                })
                ->when($popFilter, fn ($qq) => $qq->where(fn ($w) => $w->where('current_pop_id', $popFilter)->orWhere('issued_from_pop_id', $popFilter))),
            $access, $user
        )
            ->with(['item', 'currentPop', 'currentTechnician', 'customer.pop', 'issuedFromPop'])
            ->orderBy('serial_number')
            ->limit(50)->get();

        $rolls = $this->scopeRollQuery(
            InventoryRoll::query()
                ->where(function ($w) use ($like, $rollIdsByRef) {
                    $w->where('roll_code', 'like', $like)
                        ->orWhereHas('currentTechnician', fn ($t) => $t->where('name', 'like', $like))
                        ->orWhereHas('item', fn ($i) => $i->where('name', 'like', $like)->orWhere('code', 'like', $like))
                        ->orWhereIn('id', $rollIdsByRef);
                })
                ->when($popFilter, fn ($qq) => $qq->where(fn ($w) => $w->where('current_pop_id', $popFilter)->orWhere('issued_from_pop_id', $popFilter))),
            $access, $user
        )
            ->with(['item', 'currentPop', 'currentTechnician', 'issuedFromPop'])
            ->orderBy('roll_code')
            ->limit(50)->get();

        return $serials->map(fn ($s) => ['type' => 'serial', 'serial' => $s, 'roll' => null])
            ->concat($rolls->map(fn ($r) => ['type' => 'roll', 'serial' => null, 'roll' => $r]))
            ->values();
    }

    /**
     * Aksi "Sudah Dicek" (analisa-gap-kondisi-barang.md rancangan poin 5) —
     * inline toggle di halaman Detail SN (pola-3 CLAUDE.md), redirect balik
     * ke halaman ini dengan `?sn=` yang sama (PRG). Scope POP dicek sama
     * persis `isSerialInScope()` biar staf gak bisa "Sudah Dicek" SN di luar
     * cabangnya cuma dengan nebak ID di URL.
     */
    public function checkCondition(Request $request, InventorySerial $serial, InventoryReassignService $service, EffectiveAccessService $access): RedirectResponse
    {
        if (! $this->isSerialInScope($serial, $access, auth()->user())) {
            abort(404);
        }

        $validated = $request->validate([
            'condition' => ['required', Rule::in([ItemCondition::USED_GOOD->value, ItemCondition::USED_DAMAGED->value])],
        ]);

        try {
            $service->markSerialConditionChecked($serial, ItemCondition::from($validated['condition']), auth()->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['condition' => $e->getMessage()]);
        }

        return redirect()->route('warehouse.traceability.index', ['sn' => $serial->serial_number])
            ->with('success', "SN {$serial->serial_number} ditandai sudah dicek fisik.");
    }

    // isSerialInScope()/isRollInScope() dipindah ke trait ChecksAssetScope
    // (2026-10-07) — dipakai bareng WarehouseSearchController (§S2), SATU
    // aturan scope, bukan disalin dua tempat.
}
