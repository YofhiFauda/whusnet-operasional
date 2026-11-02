<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\DeviceOwnership;
use App\Enums\RollStatus;
use App\Enums\SerialStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Warehouse\Concerns\AuthorizesWarehousePop;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\Pop;
use App\Models\TechnicianCustody;
use App\Services\EffectiveAccessService;
use App\Services\FileUploadService;
use App\Services\InventoryAdjustmentService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Adjustment — lapor rusak/hilang/opname (ADHOC-54). `InventoryAdjustmentService`
 * udah ada sejak Fase 6, controller ini ketinggalan waktu Fase 8 UI pertama
 * kali dibangun (cuma Dashboard/Transfer/Issue/Custody/Traceability).
 *
 * TANPA gerbang approval berjenjang — keputusan eksplisit user (kontrol-
 * anti-manipulasi.md §1, revisi): masih rancangan awal, threshold nominal
 * belum bisa ditentukan tanpa data operasional riil. `reason`+`notes` wajib,
 * tercatat ke ledger, dipantau lewat Dashboard — bukan diblok di depan.
 */
class WarehouseAdjustmentController extends Controller
{
    use AuthorizesWarehousePop;

    /**
     * Pintu "Koreksi Stok" (spec 2026-10-31-koreksi-stok-gudang). Dulu ada 4
     * halaman terpisah berdasar MEKANISME (opname qty, opname unit, delta
     * manual, status SN) — end user gak bisa bedain. Sekarang user pilih
     * berdasar SITUASI; form-nya yang menyesuaikan jenis barang.
     */
    public function index(): View
    {
        return view('warehouse.adjustments.index');
    }

    /** Kartu 1 — hitung fisik. Simpan ke `opname.store` / `unit-opname.store`. */
    public function createCheck(Request $request, EffectiveAccessService $access): View
    {
        return view('warehouse.adjustments.check', $this->correctionFormData($request, $access));
    }

    /** Kartu 2 — barang bermasalah. Simpan ke `serial.store` / `balance.store`. */
    public function createProblem(Request $request, EffectiveAccessService $access): View
    {
        return view('warehouse.adjustments.problem', $this->correctionFormData($request, $access));
    }

    /**
     * Link lama "Penyesuaian Saldo" — inti form-nya (delta manual) sekarang
     * kejadian di kartu "Ada barang bermasalah". Query dibawa supaya link
     * yang terlewat diganti tetap terisi.
     */
    public function createBalance(Request $request): RedirectResponse
    {
        return redirect()->route('warehouse.adjustments.problem.create', $request->query());
    }

    /**
     * Data bersama dua form Koreksi Stok. Prefill dari query (link "Sesuaikan"
     * papan stok) cuma dipakai kalau gudangnya dalam scope — tanpa cek ini,
     * pop_id gudang lain di URL bocorin saldo cabang lain.
     *
     * @return array{pops: Collection<int, Pop>, prefill: array{pop_id: ?int, q: ?string, lot_no: string}, resolveUrl: string}
     */
    private function correctionFormData(Request $request, EffectiveAccessService $access): array
    {
        $user = auth()->user();

        $pops = Pop::query()->warehouse()
            ->when(! $access->hasAllPopAccess($user), fn ($q) => $q->whereIn('id', $access->getAllowedPopIds($user)))
            ->orderBy('type')->orderBy('name')->get();

        // ->integer()/->string() cast ke skalar — ->query() balikin array
        // mentah kalau URL dirakit manual (?item_id[]=1), dan find($array)
        // balik Collection, bukan Model (crash di $item?->code di bawah).
        $popIdRaw = $request->integer('pop_id');
        $popId = $popIdRaw && $pops->contains('id', $popIdRaw) ? $popIdRaw : null;
        $itemId = $request->integer('item_id');
        $item = $popId && $itemId
            ? Item::active()->whereDoesntHave('category', fn ($query) => $query->where('is_operational', false))->find($itemId)
            : null;

        return [
            'pops' => $pops,
            'prefill' => [
                'pop_id' => $popId,
                // Kode barang, bukan id — kolom pencarian yang sama yang
                // ngisi mode form, jadi prefill lewat jalur resolve juga.
                'q' => $item?->code ?? (is_string($request->query('q')) ? $request->query('q') : null),
                'lot_no' => is_string($request->query('lot_no')) ? $request->query('lot_no') : '',
            ],
            'resolveUrl' => route('warehouse.adjustments.resolve'),
        ];
    }

    public function storeBalance(Request $request, InventoryAdjustmentService $service, EffectiveAccessService $access): RedirectResponse
    {
        $validated = $request->validate([
            'pop_id' => 'required|integer|exists:pops,id',
            'item_id' => 'required|integer|exists:items,id',
            'lot_no' => 'nullable|string|max:50',
            'qty_delta' => 'required|numeric',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        if ((float) $validated['qty_delta'] === 0.0) {
            return back()->withInput()->with('error', 'Qty penyesuaian tidak boleh nol.');
        }

        $pop = Pop::findOrFail($validated['pop_id']);
        // Dropdown createBalance() cuma nyaring TAMPILAN — tanpa cek ini,
        // pop_admin scoped bisa POST pop_id gudang lain langsung.
        $this->assertPopInScope($pop, auth()->user(), $access);

        try {
            $service->adjustPopBalance(
                $pop,
                (int) $validated['item_id'],
                (float) $validated['qty_delta'],
                $validated['reason'],
                auth()->user(),
                $validated['lot_no'] ?? null,
                $validated['notes'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('warehouse.stock.index')->with('success', 'Penyesuaian stok tercatat.');
    }

    /**
     * Stock Opname (Fase 2 P1, gap #3) — BEDA dari `storeBalance()`: opname
     * input JUMLAH FISIK HASIL HITUNG (bukan delta), boleh hasilnya PAS
     * (selisih nol) — `storeBalance()` sengaja menolak delta nol karena itu
     * jalur koreksi manual, bukan opname. Form-nya sekarang kartu "Cek &
     * cocokkan stok"; GET lama tinggal redirect.
     */
    public function createOpname(Request $request): RedirectResponse
    {
        return redirect()->route('warehouse.adjustments.check.create', $request->query());
    }

    public function storeOpname(Request $request, InventoryAdjustmentService $service, EffectiveAccessService $access): RedirectResponse
    {
        $validated = $request->validate([
            'pop_id' => 'required|integer|exists:pops,id',
            'item_id' => 'required|integer|exists:items,id',
            'lot_no' => 'nullable|string|max:50',
            'counted_qty' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $pop = Pop::findOrFail($validated['pop_id']);
        $this->assertPopInScope($pop, auth()->user(), $access);

        try {
            $service->recordStockOpname(
                $pop,
                (int) $validated['item_id'],
                (float) $validated['counted_qty'],
                auth()->user(),
                $validated['lot_no'] ?? null,
                $validated['notes'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('warehouse.stock.index')->with('success', 'Stock opname tercatat.');
    }

    /** Opname SN/Roll ikut kartu "Cek & cocokkan stok" (deteksi jenis otomatis). */
    public function createUnitOpname(Request $request): RedirectResponse
    {
        return redirect()->route('warehouse.adjustments.check.create', $request->query());
    }

    public function storeUnitOpname(Request $request, InventoryAdjustmentService $service, EffectiveAccessService $access): RedirectResponse
    {
        $validated = $request->validate([
            'pop_id' => ['required', 'integer', 'exists:pops,id'],
            'tracking_type' => ['required', Rule::in(['serialized', 'roll'])],
            'code' => ['required', 'string', 'max:100'],
            'is_present' => ['required_if:tracking_type,serialized', 'nullable', 'boolean'],
            'counted_length' => ['required_if:tracking_type,roll', 'nullable', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:500'],
            'evidence' => ['required', 'image', 'max:4096'],
        ]);

        $pop = Pop::findOrFail($validated['pop_id']);
        $this->assertPopInScope($pop, auth()->user(), $access);
        $this->assertPopAdminNotBlockedByActivePicGudang($pop, auth()->user());
        $this->assertPicGudangCanActOnPop($pop, auth()->user());

        $unit = $validated['tracking_type'] === 'serialized'
            ? InventorySerial::query()->where('serial_number', $validated['code'])->where('current_pop_id', $pop->id)->firstOrFail()
            : InventoryRoll::query()->where('roll_code', $validated['code'])->where('current_pop_id', $pop->id)->firstOrFail();
        $evidencePath = FileUploadService::uploadWarehouseEvidence($request->file('evidence'), 'stock-opname');

        try {
            if ($unit instanceof InventorySerial) {
                $service->recordSerialStockOpname($unit, $pop, (bool) $validated['is_present'], auth()->user(), $validated['reason'], $evidencePath);
            } else {
                $service->recordRollStockOpname($unit, $pop, (float) $validated['counted_length'], auth()->user(), $validated['reason'], $evidencePath);
            }
        } catch (InvalidArgumentException $e) {
            Storage::disk('public')->delete($evidencePath);

            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('warehouse.stock.index')->with('success', 'Opname unit fisik berhasil dicatat.');
    }

    public function createCustody(TechnicianCustody $custody, EffectiveAccessService $access): View
    {
        $this->assertPopIdInScope($custody->issued_from_pop_id, auth()->user(), $access);

        $custody->load(['technician', 'item']);
        $reasonCategories = InventoryAdjustmentService::REASON_CATEGORIES;

        return view('warehouse.adjustments.custody', compact('custody', 'reasonCategories'));
    }

    public function storeCustody(Request $request, TechnicianCustody $custody, InventoryAdjustmentService $service, EffectiveAccessService $access): RedirectResponse
    {
        $this->assertPopIdInScope($custody->issued_from_pop_id, auth()->user(), $access);

        $validated = $request->validate([
            'qty_delta' => 'required|numeric',
            'reason' => ['required', Rule::in(array_keys(InventoryAdjustmentService::REASON_CATEGORIES))],
            'notes' => 'nullable|string|max:500',
            // required_if di sini cuma UX (pesan error cepat) — penegak
            // SEBENARNYA tetap InventoryAdjustmentService::assertEvidenceIfRequired(),
            // biar request langsung ke endpoint (skip form) gak bisa lolos.
            'evidence' => ['required_if:reason,lost,damaged', 'nullable', 'image', 'max:4096'],
        ]);

        if ((float) $validated['qty_delta'] === 0.0) {
            return back()->withInput()->with('error', 'Qty penyesuaian tidak boleh nol.');
        }

        $evidencePath = $request->hasFile('evidence')
            ? FileUploadService::uploadWarehouseEvidence($request->file('evidence'), $validated['reason'])
            : null;

        try {
            $service->adjustCustody($custody, (float) $validated['qty_delta'], $validated['reason'], auth()->user(), $validated['notes'] ?? null, $evidencePath);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('warehouse.custody.index')->with('success', 'Penyesuaian custody tercatat.');
    }

    public function createSerial(InventorySerial $serial, EffectiveAccessService $access): View
    {
        // issued_from_pop_id keisi begitu SN pernah di-issue ke teknisi;
        // fallback current_pop_id buat SN yang masih AVAILABLE di gudang
        // (belum pernah issued, jadi belum py issued_from_pop_id).
        $this->assertPopIdInScope($serial->issued_from_pop_id ?? $serial->current_pop_id, auth()->user(), $access);

        $serial->load(['item', 'currentTechnician', 'currentPop']);

        return view('warehouse.adjustments.serial', compact('serial'));
    }

    public function storeSerial(Request $request, InventorySerial $serial, InventoryAdjustmentService $service, EffectiveAccessService $access): RedirectResponse
    {
        $this->assertPopIdInScope($serial->issued_from_pop_id ?? $serial->current_pop_id, auth()->user(), $access);

        $validated = $request->validate([
            'new_status' => ['required', Rule::in(['lost', 'damaged', 'scrapped', 'quarantine'])],
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string|max:500',
            // required_if UX doang — penegak sebenarnya
            // InventoryAdjustmentService::adjustSerialStatus() sendiri.
            'evidence' => ['required_if:new_status,lost,damaged,scrapped', 'nullable', 'image', 'max:4096'],
        ]);

        $evidencePath = $request->hasFile('evidence')
            ? FileUploadService::uploadWarehouseEvidence($request->file('evidence'), $validated['new_status'])
            : null;

        try {
            $service->adjustSerialStatus(
                $serial,
                SerialStatus::from($validated['new_status']),
                $validated['reason'],
                auth()->user(),
                $validated['notes'] ?? null,
                $evidencePath,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('warehouse.custody.index')->with('success', "SN {$serial->serial_number} ditandai {$validated['new_status']}.");
    }

    /**
     * Padanan `createSerial()`/`storeSerial()` buat roll kabel —
     * `length_remaining` TIDAK diubah (lihat docblock
     * `InventoryAdjustmentService::adjustRollStatus()`), cuma status +
     * evidence.
     */
    public function createRepairSerial(InventorySerial $serial, EffectiveAccessService $access): View
    {
        $this->assertPopIdInScope($serial->current_pop_id, auth()->user(), $access);
        abort_unless(in_array($serial->status, [SerialStatus::DAMAGED, SerialStatus::QUARANTINE], true)
            && $serial->current_technician_id === null
            && $serial->ownership !== DeviceOwnership::CUSTOMER, 404);

        $serial->load(['item', 'currentPop']);

        return view('warehouse.adjustments.repair-serial', compact('serial'));
    }

    public function storeRepairSerial(Request $request, InventorySerial $serial, InventoryAdjustmentService $service, EffectiveAccessService $access): RedirectResponse
    {
        $this->assertPopIdInScope($serial->current_pop_id, auth()->user(), $access);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'evidence' => ['required', 'image', 'max:4096'],
        ]);

        $evidencePath = FileUploadService::uploadWarehouseEvidence($request->file('evidence'), 'repaired');

        try {
            $service->restoreRepairedSerial($serial, $validated['reason'], auth()->user(), $evidencePath);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('warehouse.damaged.index')->with('success', "SN {$serial->serial_number} selesai diperbaiki dan kembali tersedia.");
    }

    public function createRoll(InventoryRoll $roll, EffectiveAccessService $access): View
    {
        $this->assertPopIdInScope($roll->issued_from_pop_id ?? $roll->current_pop_id, auth()->user(), $access);

        $roll->load(['item', 'currentTechnician', 'currentPop']);

        return view('warehouse.adjustments.roll', compact('roll'));
    }

    public function storeRoll(Request $request, InventoryRoll $roll, InventoryAdjustmentService $service, EffectiveAccessService $access): RedirectResponse
    {
        $this->assertPopIdInScope($roll->issued_from_pop_id ?? $roll->current_pop_id, auth()->user(), $access);

        $validated = $request->validate([
            'new_status' => ['required', Rule::in(['lost', 'damaged', 'scrapped', 'quarantine'])],
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string|max:500',
            'evidence' => ['required_if:new_status,lost,damaged,scrapped', 'nullable', 'image', 'max:4096'],
        ]);

        $evidencePath = $request->hasFile('evidence')
            ? FileUploadService::uploadWarehouseEvidence($request->file('evidence'), $validated['new_status'])
            : null;

        try {
            $service->adjustRollStatus(
                $roll,
                RollStatus::from($validated['new_status']),
                $validated['reason'],
                auth()->user(),
                $validated['notes'] ?? null,
                $evidencePath,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('warehouse.custody.index')->with('success', "Roll {$roll->roll_code} ditandai {$validated['new_status']}.");
    }
}
