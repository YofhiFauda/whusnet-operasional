<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\SerialStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Warehouse\Concerns\AuthorizesWarehousePop;
use App\Models\InventoryBalance;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\Pop;
use App\Models\TechnicianCustody;
use App\Services\EffectiveAccessService;
use App\Services\FileUploadService;
use App\Services\InventoryAdjustmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function createBalance(Request $request, EffectiveAccessService $access): View
    {
        $user = auth()->user();

        $pops = Pop::query()->warehouse()
            ->when(! $access->hasAllPopAccess($user), fn ($q) => $q->whereIn('id', $access->getAllowedPopIds($user)))
            ->orderBy('type')->orderBy('name')->get();
        $items = Item::active()->with('category')->orderBy('name')->get();

        // Kalau kesini lewat link "Sesuaikan" Dashboard (bawa pop_id+item_id
        // di query), tunjukin stok SAAT INI — sebelumnya form ini satu-satunya
        // dari 3 form Adjustment yang gak nunjukin angka current (Custody &
        // Serial nunjukin, Balance enggak, ketauan audit 2026-09-02). Kalau
        // dropdown diganti manual sesudahnya, angka ini gak ikut update live
        // (bukan AJAX) — cukup buat jalur paling umum (link dari Dashboard).
        $currentBalance = null;
        if ($request->filled('pop_id') && $request->filled('item_id')) {
            $currentBalance = InventoryBalance::where('pop_id', $request->query('pop_id'))
                ->where('item_id', $request->query('item_id'))
                ->where('lot_no', $request->query('lot_no', ''))
                ->first();
        }

        return view('warehouse.adjustments.balance', compact('pops', 'items', 'currentBalance'));
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
     * Stock Opname (Fase 2 P1, gap #3) — BEDA dari `createBalance()`/
     * `storeBalance()`: opname input JUMLAH FISIK HASIL HITUNG (bukan
     * delta), boleh hasilnya PAS (selisih nol) — `storeBalance()` sengaja
     * menolak delta nol karena itu jalur koreksi manual, bukan opname.
     */
    public function createOpname(Request $request, EffectiveAccessService $access): View
    {
        $user = auth()->user();

        $pops = Pop::query()->warehouse()
            ->when(! $access->hasAllPopAccess($user), fn ($q) => $q->whereIn('id', $access->getAllowedPopIds($user)))
            ->orderBy('type')->orderBy('name')->get();
        $items = Item::active()->with('category')->orderBy('name')->get();

        $currentBalance = null;
        if ($request->filled('pop_id') && $request->filled('item_id')) {
            $currentBalance = InventoryBalance::where('pop_id', $request->query('pop_id'))
                ->where('item_id', $request->query('item_id'))
                ->where('lot_no', $request->query('lot_no', ''))
                ->first();
        }

        return view('warehouse.adjustments.opname', compact('pops', 'items', 'currentBalance'));
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
}
