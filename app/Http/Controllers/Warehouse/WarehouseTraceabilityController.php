<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\ItemCondition;
use App\Http\Controllers\Controller;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\User;
use App\Services\EffectiveAccessService;
use App\Services\InventoryReassignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    public function index(Request $request, EffectiveAccessService $access): View
    {
        $serialNumber = trim((string) $request->query('sn', ''));
        $rollCode = trim((string) $request->query('roll', ''));
        $serial = null;
        $roll = null;
        $ledger = collect();
        $notFound = false;

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

        return view('warehouse.traceability.index', compact('serialNumber', 'rollCode', 'serial', 'roll', 'ledger', 'notFound'));
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

    private function isSerialInScope(InventorySerial $serial, EffectiveAccessService $access, User $user): bool
    {
        if ($access->hasAllPopAccess($user)) {
            return true;
        }

        $allowed = $access->getAllowedPopIds($user);

        if ($serial->current_pop_id && in_array($serial->current_pop_id, $allowed, true)) {
            return true;
        }

        if ($serial->issued_from_pop_id && in_array($serial->issued_from_pop_id, $allowed, true)) {
            return true;
        }

        if ($serial->customer && in_array($serial->customer->pop_id, $allowed, true)) {
            return true;
        }

        return false;
    }

    private function isRollInScope(InventoryRoll $roll, EffectiveAccessService $access, User $user): bool
    {
        if ($access->hasAllPopAccess($user)) {
            return true;
        }

        $allowed = $access->getAllowedPopIds($user);

        if ($roll->current_pop_id && in_array($roll->current_pop_id, $allowed, true)) {
            return true;
        }

        if ($roll->issued_from_pop_id && in_array($roll->issued_from_pop_id, $allowed, true)) {
            return true;
        }

        return false;
    }
}
