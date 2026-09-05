<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\EquipmentClass;
use App\Enums\TrackingType;
use App\Http\Controllers\Controller;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\User;
use App\Services\EffectiveAccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Asset Traceability (ADHOC-54, rancangan-ui.md §2.8) — cari SN, tampilin
 * riwayat lengkap dari ledger (RECEIVE→TRANSFER→ISSUE→INSTALL→...).
 *
 * Scoping (§1.3 matrix, pop_admin "cabangnya saja"): pop_admin cuma boleh
 * nemu SN yang PERNAH nyentuh gudang/pelanggan dalam scope-nya — current_pop,
 * issued_from_pop, ATAU pop pelanggan tempat dia terpasang. Kalau gak
 * masuk scope, dianggap "tidak ditemukan" (bukan pesan 403 eksplisit) —
 * biar keberadaan SN di cabang lain gak ikut bocor lewat pesan error yang
 * beda.
 */
class WarehouseTraceabilityController extends Controller
{
    public function index(Request $request, EffectiveAccessService $access): View
    {
        $serialNumber = trim((string) $request->query('sn', ''));
        $serial = null;
        $ledger = collect();
        $notFound = false;

        if ($serialNumber !== '') {
            $found = InventorySerial::query()
                ->where('serial_number', $serialNumber)
                ->with(['item.category', 'currentPop', 'currentTechnician', 'customer.pop', 'issuedFromPop'])
                ->first();

            if ($found && $this->isInScope($found, $access, auth()->user())) {
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
        }

        // Data buat tab "Scan Barang Masuk" — logic assign SEKARANG beneran
        // jalan (POST ke WarehouseReceiveController::storeScanned(), lihat
        // routes/web.php). Kategori & item difilter equipment_class=AKTIF —
        // SEMUA perangkat aktif (modem/ONT, router, OLT module, AP Wireless,
        // SFP Transceiver, dst), bukan cuma modem. Pasif gak relevan buat
        // alur scan SN (gak wajib SN).
        $categories = ItemCategory::active()
            ->where('equipment_class', EquipmentClass::AKTIF)
            ->ordered()
            ->get();

        $items = Item::active()
            ->where('tracking_type', TrackingType::SERIALIZED)
            ->whereHas('category', fn ($q) => $q->where('equipment_class', EquipmentClass::AKTIF))
            ->with('category')
            ->orderBy('name')
            ->get();

        // Gudang tujuan = Gudang PUSAT SAJA — beda dari keputusan awal
        // (pusat+cabang), dikoreksi begitu logic assign disambungkan ke
        // `InventoryReceiveService`: tab ini SECARA BISNIS adalah RECEIVE
        // (barang baru pertama kali masuk sistem), dan `assertPusat()` di
        // service itu MENOLAK Cabang — "Cabang terima barang lewat
        // Transfer" (dari Pusat, bukan dari supplier langsung). Pola query
        // sama persis `WarehouseReceiveController::create()`.
        $pusatPops = Pop::query()
            ->where('type', 'pusat')
            ->when(! $access->hasAllPopAccess(auth()->user()), fn ($q) => $q->whereIn('id', $access->getAllowedPopIds(auth()->user())))
            ->orderBy('name')
            ->get();

        return view('warehouse.traceability.index', compact('serialNumber', 'serial', 'ledger', 'notFound', 'categories', 'items', 'pusatPops'));
    }

    private function isInScope(InventorySerial $serial, EffectiveAccessService $access, User $user): bool
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
}
