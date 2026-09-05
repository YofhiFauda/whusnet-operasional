<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\SerialStatus;
use App\Http\Controllers\Controller;
use App\Models\InventorySerial;
use App\Models\TechnicianCustody;
use App\Models\User;
use App\Services\EffectiveAccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Custody — Lihat Semua Teknisi (ADHOC-54, rancangan-ui.md §2.6). Read-only.
 *
 * Discope lewat `issued_from_pop_id` (kolom baru — lihat migration
 * `add_issued_from_pop_id_for_custody_scoping`), BUKAN teknisinya sendiri:
 * repo ini gak punya pemetaan "teknisi ini anggota cabang mana" yang bisa
 * dipercaya (`user_pops` legacy, gak paham pop_tree — sama alasan
 * `EffectiveAccessService` dipilih di atas jalur lama di seluruh modul lain).
 */
class WarehouseCustodyController extends Controller
{
    public function index(Request $request, EffectiveAccessService $access): View
    {
        $user = auth()->user();
        $hasAllAccess = $access->hasAllPopAccess($user);
        $allowedPopIds = $hasAllAccess ? [] : $access->getAllowedPopIds($user);

        $technicianFilter = $request->query('technician_id');

        $custodies = TechnicianCustody::query()
            ->active()
            ->when(! $hasAllAccess, fn ($q) => $q->whereIn('issued_from_pop_id', $allowedPopIds))
            ->when($technicianFilter, fn ($q) => $q->where('technician_id', $technicianFilter))
            ->with(['technician', 'item.category', 'issuedFromPop'])
            ->orderBy('issued_at')
            ->get();

        $serials = InventorySerial::query()
            ->status(SerialStatus::ISSUED)
            ->when(! $hasAllAccess, fn ($q) => $q->whereIn('issued_from_pop_id', $allowedPopIds))
            ->when($technicianFilter, fn ($q) => $q->where('current_technician_id', $technicianFilter))
            ->with(['item.category', 'currentTechnician', 'issuedFromPop'])
            ->get();

        // Dropdown filter teknisi — cuma yang KEBETULAN lagi py custody dalam
        // scope, biar gak nawarin nama yang query-nya bakal kosong.
        $technicianIds = $custodies->pluck('technician_id')->merge($serials->pluck('current_technician_id'))->unique()->filter();
        $technicians = User::whereIn('id', $technicianIds)->orderBy('name')->get();

        return view('warehouse.custody.index', compact('custodies', 'serials', 'technicians', 'technicianFilter'));
    }
}
