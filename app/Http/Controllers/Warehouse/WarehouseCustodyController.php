<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\RollStatus;
use App\Enums\SerialStatus;
use App\Http\Controllers\Controller;
use App\Models\DeviceRetrievalLog;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\Pop;
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

        $pops = Pop::query()
            ->warehouse()
            ->when(! $hasAllAccess, fn ($q) => $q->whereIn('id', $allowedPopIds))
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $technicianFilter = $request->query('technician_id');
        $popFilter = $request->integer('pop_id') ?: null;
        $search = trim((string) $request->query('search', ''));

        $custodies = TechnicianCustody::query()
            ->active()
            ->when(! $hasAllAccess, fn ($q) => $q->whereIn('issued_from_pop_id', $allowedPopIds))
            ->when($technicianFilter, fn ($q) => $q->where('technician_id', $technicianFilter))
            ->when($popFilter, fn ($q) => $q->where('issued_from_pop_id', $popFilter))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('lot_no', 'like', "%{$search}%")
                        ->orWhereHas('item', fn ($iq) => $iq->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
                });
            })
            ->with(['technician', 'item.category', 'issuedFromPop'])
            ->orderBy('issued_at')
            ->get();

        $serials = InventorySerial::query()
            ->status(SerialStatus::ISSUED)
            ->when(! $hasAllAccess, fn ($q) => $q->whereIn('issued_from_pop_id', $allowedPopIds))
            ->when($technicianFilter, fn ($q) => $q->where('current_technician_id', $technicianFilter))
            ->when($popFilter, fn ($q) => $q->where('issued_from_pop_id', $popFilter))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('serial_number', 'like', "%{$search}%")
                        ->orWhereHas('item', fn ($iq) => $iq->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
                });
            })
            ->with(['item.category', 'currentTechnician', 'issuedFromPop'])
            ->get();

        // Roll kabel (App\Enums\TrackingType::ROLL) aktif di custody teknisi
        // — status ISSUED (belum dipotong) atau IN_USE (sisa sebagian).
        // DEPLETED sengaja gak muncul di sini (custody-nya sendiri udah
        // habis, gak ada lagi yang perlu dipantau).
        $rolls = InventoryRoll::query()
            ->whereIn('status', [RollStatus::ISSUED->value, RollStatus::IN_USE->value])
            ->when(! $hasAllAccess, fn ($q) => $q->whereIn('issued_from_pop_id', $allowedPopIds))
            ->when($technicianFilter, fn ($q) => $q->where('current_technician_id', $technicianFilter))
            ->when($popFilter, fn ($q) => $q->where('issued_from_pop_id', $popFilter))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('roll_code', 'like', "%{$search}%")
                        ->orWhereHas('item', fn ($iq) => $iq->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
                });
            })
            ->with(['item.category', 'currentTechnician', 'issuedFromPop'])
            ->get();

        // Return dari pelanggan (ADHOC-88): modem hasil pengambilan alat (DEAC)
        // yang MASIH dipegang teknisi — status `RETURNED` (transit), belum
        // diterima gudang. Terpisah dari tab "Perangkat Serial Number" (ISSUED,
        // barang yang DIBAWA teknisi ke lapangan) karena arahnya berlawanan:
        // ini barang yang dibawa PULANG dan menunggu konfirmasi gudang di
        // Terima Retur. Scope lewat `issued_from_pop_id` = gudang tujuan.
        $returned = InventorySerial::query()
            ->status(SerialStatus::RETURNED)
            ->when(! $hasAllAccess, fn ($q) => $q->whereIn('issued_from_pop_id', $allowedPopIds))
            ->when($technicianFilter, fn ($q) => $q->where('current_technician_id', $technicianFilter))
            ->when($popFilter, fn ($q) => $q->where('issued_from_pop_id', $popFilter))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('serial_number', 'like', "%{$search}%")
                        ->orWhereHas('item', fn ($iq) => $iq->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                        ->orWhereHas('customer', fn ($cq) => $cq->where('full_name', 'like', "%{$search}%"));
                });
            })
            ->with(['item.category', 'currentTechnician', 'issuedFromPop', 'customer'])
            ->orderBy('updated_at')
            ->get();

        // Tanggal pengambilan per SN dari log (yang belum diterima) — kolom
        // `updated_at` SN bisa berubah karena hal lain, log tidak.
        $returnedRetrievedAt = DeviceRetrievalLog::query()
            ->whereIn('serial_id', $returned->pluck('id'))
            ->whereNull('received_at')
            ->orderBy('retrieved_at')
            ->get(['serial_id', 'retrieved_at'])
            ->pluck('retrieved_at', 'serial_id');

        // Dropdown filter teknisi — cuma yang KEBETULAN lagi py custody dalam
        // scope, biar gak nawarin nama yang query-nya bakal kosong. Dihitung
        // dari query TANPA filter teknisi (technician_id sengaja gak dioper
        // ke sini) supaya daftar dropdown gak menyusut begitu 1 teknisi
        // dipilih — filter POP/search tetap ikut biar konsisten sama tabel.
        $technicianIds = $custodies->pluck('technician_id')->merge($serials->pluck('current_technician_id'))->merge($rolls->pluck('current_technician_id'))->merge($returned->pluck('current_technician_id'))->unique()->filter();
        $technicians = User::whereIn('id', $technicianIds)->orderBy('name')->get();

        // KPI ringkasan (rancangan-layout.md §8.1) — SENGAJA dipisah per
        // SATUAN, bukan dijumlah rata lintas kategori: kabel/material meteran
        // (unit "meter") dijumlah qty jadi "Total Kabel Lapangan", sisanya
        // (pcs/batch non-meter — connector, patchcord, dst) cuma DIHITUNG
        // BARISNYA ("Material Pasif"), bukan qty-nya, karena satuannya
        // macem-macem (pcs vs pack vs unit) dan gak valid dijumlah jadi satu
        // angka. Prinsip sama kayak Dashboard/Laporan Gudang.
        $meterCustodies = $custodies->filter(fn ($c) => $c->item->unit === 'meter');
        $nonMeterCustodies = $custodies->filter(fn ($c) => $c->item->unit !== 'meter');

        $kpi = [
            'serial_count' => $serials->count(),
            'meter_total' => (float) $meterCustodies->sum('qty_remaining') + (float) $rolls->sum('length_remaining'),
            'material_batch_count' => $nonMeterCustodies->count(),
            'roll_count' => $rolls->count(),
            'technician_count' => $custodies->pluck('technician_id')->merge($serials->pluck('current_technician_id'))->merge($rolls->pluck('current_technician_id'))->merge($returned->pluck('current_technician_id'))->unique()->filter()->count(),
        ];

        return view('warehouse.custody.index', compact(
            'custodies', 'serials', 'rolls', 'returned', 'returnedRetrievedAt', 'technicians', 'technicianFilter', 'pops', 'popFilter', 'search', 'kpi'
        ));
    }
}
