<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Warehouse\Concerns\AuthorizesWarehousePop;
use App\Models\InventorySerial;
use App\Models\Pop;
use App\Models\TechnicianCustody;
use App\Models\User;
use App\Services\EffectiveAccessService;
use App\Services\InventoryReassignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Reassign custody teknisi resign/cuti/rotasi (ADHOC-54, rancangan-ui.md
 * §3.6). `InventoryReassignService` udah ada sejak Fase 6, controller ini
 * ketinggalan waktu Fase 8 UI pertama kali dibangun.
 *
 * `created_by` di ledger SELALU admin yang eksekusi (`auth()->user()`) —
 * BUKAN teknisi lama, yang mungkin udah gak bisa diajak konfirmasi apa pun.
 * Lihat docblock `InventoryReassignService` buat alasan lengkap + TODO
 * acknowledgment sisi teknisi baru yang masih ditunda.
 */
class WarehouseReassignController extends Controller
{
    use AuthorizesWarehousePop;

    public function createCustody(TechnicianCustody $custody, EffectiveAccessService $access): View
    {
        $user = auth()->user();
        $this->assertPopIdInScope($custody->issued_from_pop_id, $user, $access);

        $custody->load(['technician', 'item']);
        $cabangPops = Pop::where('type', 'cabang')
            ->when(! $access->hasAllPopAccess($user), fn ($q) => $q->whereIn('id', $access->getAllowedPopIds($user)))
            ->orderBy('name')->get();
        $technicians = User::whereHas('role', fn ($q) => $q->whereIn('code', ['teknisi', 'fop']))
            ->where('id', '!=', $custody->technician_id)
            ->orderBy('name')
            ->get();

        return view('warehouse.reassign.custody', compact('custody', 'cabangPops', 'technicians'));
    }

    public function storeCustody(Request $request, TechnicianCustody $custody, InventoryReassignService $service, EffectiveAccessService $access): RedirectResponse
    {
        $user = auth()->user();
        $this->assertPopIdInScope($custody->issued_from_pop_id, $user, $access);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['return', 'transfer'])],
            'cabang_pop_id' => 'required_if:action,return|nullable|integer|exists:pops,id',
            'new_technician_id' => 'required_if:action,transfer|nullable|integer|exists:users,id',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            if ($validated['action'] === 'return') {
                $cabang = Pop::findOrFail($validated['cabang_pop_id']);
                $this->assertPopInScope($cabang, $user, $access);
                $service->returnToWarehouse($custody, $cabang, $validated['reason'], auth()->user(), $validated['notes'] ?? null);
            } else {
                $newTechnician = User::findOrFail($validated['new_technician_id']);
                $service->transferCustodyToTechnician($custody, $newTechnician, $validated['reason'], auth()->user(), $validated['notes'] ?? null);
            }
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('warehouse.custody.index')->with('success', 'Barang berhasil dialihkan.');
    }

    public function createSerial(InventorySerial $serial, EffectiveAccessService $access): View
    {
        $user = auth()->user();
        $this->assertPopIdInScope($serial->issued_from_pop_id ?? $serial->current_pop_id, $user, $access);

        $serial->load(['item', 'currentTechnician']);
        $cabangPops = Pop::where('type', 'cabang')
            ->when(! $access->hasAllPopAccess($user), fn ($q) => $q->whereIn('id', $access->getAllowedPopIds($user)))
            ->orderBy('name')->get();
        $technicians = User::whereHas('role', fn ($q) => $q->whereIn('code', ['teknisi', 'fop']))
            ->where('id', '!=', $serial->current_technician_id)
            ->orderBy('name')
            ->get();

        return view('warehouse.reassign.serial', compact('serial', 'cabangPops', 'technicians'));
    }

    public function storeSerial(Request $request, InventorySerial $serial, InventoryReassignService $service, EffectiveAccessService $access): RedirectResponse
    {
        $user = auth()->user();
        $this->assertPopIdInScope($serial->issued_from_pop_id ?? $serial->current_pop_id, $user, $access);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['return', 'transfer'])],
            'cabang_pop_id' => 'required_if:action,return|nullable|integer|exists:pops,id',
            'new_technician_id' => 'required_if:action,transfer|nullable|integer|exists:users,id',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            if ($validated['action'] === 'return') {
                $cabang = Pop::findOrFail($validated['cabang_pop_id']);
                $this->assertPopInScope($cabang, $user, $access);
                $service->returnSerialToWarehouse($serial, $cabang, $validated['reason'], auth()->user(), $validated['notes'] ?? null);
            } else {
                $newTechnician = User::findOrFail($validated['new_technician_id']);
                $service->transferSerialToTechnician($serial, $newTechnician, $validated['reason'], auth()->user(), $validated['notes'] ?? null);
            }
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('warehouse.custody.index')->with('success', "SN {$serial->serial_number} berhasil dialihkan.");
    }
}
