<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\SerialStatus;
use App\Enums\TrackingType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Warehouse\Concerns\AuthorizesWarehousePop;
use App\Models\InventoryBalance;
use App\Models\InventorySerial;
use App\Models\InventoryTransfer;
use App\Models\Item;
use App\Models\Pop;
use App\Services\EffectiveAccessService;
use App\Services\InventoryTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Transfer Pusat→Cabang (ADHOC-54, rancangan-ui.md §2.2-2.3). Dua fase:
 * `create()`/`store()` = dispatch (Pusat), `show()`+`receive()` = confirm
 * (Cabang) — di HALAMAN YANG SAMA (`show`), bukan dua halaman terpisah.
 *
 * Controller SENGAJA tipis — validasi request + delegasi penuh ke
 * `InventoryTransferService`. Semua invariant bisnis (tipe pop, cukup
 * stok, dst) ditegakkan di Service, bukan diulang di sini.
 */
class WarehouseTransferController extends Controller
{
    use AuthorizesWarehousePop;

    public function create(EffectiveAccessService $access): View
    {
        $user = auth()->user();

        $fromPops = Pop::query()->where('type', 'pusat')->orderBy('name')->get();

        $toPops = Pop::query()
            ->where('type', 'cabang')
            ->when(! $access->hasAllPopAccess($user), fn ($q) => $q->whereIn('id', $access->getAllowedPopIds($user)))
            ->orderBy('name')
            ->get();

        $items = Item::active()->with('category')->orderBy('name')->get();

        return view('warehouse.transfers.create', compact('fromPops', 'toPops', 'items'));
    }

    /**
     * Referensi read-only "apa aja yang beneran ada di Pusat ini sekarang" —
     * pola sama `WarehouseIssueController::availableStock()` (gap yang sama:
     * dispatch Transfer sebelumnya sama-sama "ketik SN blind, taunya salah
     * pas submit" kayak Issue dulu, cuma belum ke-tutup barengan waktu itu).
     */
    public function availableStock(Request $request, EffectiveAccessService $access): JsonResponse
    {
        $validated = $request->validate(['pop_id' => 'required|integer|exists:pops,id']);
        $this->assertPopIdInScope((int) $validated['pop_id'], auth()->user(), $access);

        $balanceItems = InventoryBalance::query()
            ->where('pop_id', $validated['pop_id'])
            ->where('qty', '>', 0)
            ->with('item')
            ->get()
            ->groupBy('item_id')
            ->map(function ($rows) {
                $item = $rows->first()->item;

                return [
                    'item_id' => $item->id,
                    'name' => $item->name,
                    'unit' => $item->unit,
                    'tracking_type' => $item->tracking_type->value,
                    'lots' => $rows->map(fn ($b) => ['lot_no' => $b->lot_no, 'qty' => (float) $b->qty])->values(),
                ];
            })
            ->values();

        $serialItems = InventorySerial::query()
            ->where('current_pop_id', $validated['pop_id'])
            ->where('status', SerialStatus::AVAILABLE->value)
            ->with('item')
            ->get()
            ->groupBy('item_id')
            ->map(function ($rows) {
                $item = $rows->first()->item;

                return [
                    'item_id' => $item->id,
                    'name' => $item->name,
                    'unit' => $item->unit,
                    'tracking_type' => $item->tracking_type->value,
                    'serials' => $rows->pluck('serial_number')->values(),
                ];
            })
            ->values();

        return response()->json(['items' => $balanceItems->concat($serialItems)->values()]);
    }

    public function store(Request $request, InventoryTransferService $service): RedirectResponse
    {
        $validated = $request->validate([
            'from_pop_id' => 'required|integer|exists:pops,id',
            'to_pop_id' => 'required|integer|exists:pops,id|different:from_pop_id',
            'lines' => 'required|array|min:1',
            'lines.*.item_id' => 'required|integer|exists:items,id',
            'lines.*.qty' => 'nullable|numeric|min:0.01',
            'lines.*.lot_no' => 'nullable|string|max:50',
            'lines.*.serial_numbers' => 'nullable|string',
        ]);

        $fromPop = Pop::findOrFail($validated['from_pop_id']);
        $toPop = Pop::findOrFail($validated['to_pop_id']);

        try {
            $lines = $this->normalizeLines($validated['lines']);
            $transfer = $service->createTransfer($fromPop, $toPop, $lines, auth()->user());
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('warehouse.transfers.show', $transfer)
            ->with('success', "Transfer {$transfer->reference_number} berhasil dibuat, menunggu konfirmasi terima di {$toPop->name}.");
    }

    public function show(InventoryTransfer $transfer, EffectiveAccessService $access): View
    {
        $transfer->load(['fromPop', 'toPop', 'createdBy', 'receivedBy']);

        // Transfer harus kelihatan dari DUA sisi (Pusat pengirim & Cabang
        // penerima) — scope lolos kalau salah satu pop-nya ada di allowed
        // scope aktor, bukan cuma to_pop_id.
        $user = auth()->user();
        if (! $access->hasAllPopAccess($user)) {
            $allowed = $access->getAllowedPopIds($user);
            if (! in_array($transfer->from_pop_id, $allowed, true) && ! in_array($transfer->to_pop_id, $allowed, true)) {
                abort(403, 'Anda tidak memiliki akses ke Transfer ini.');
            }
        }

        $dispatchLines = $transfer->transactions()->whereNotNull('from_pop_id')->with(['item.category', 'serial', 'fromPop', 'toPop', 'fromTechnician', 'toTechnician'])->get();
        $confirmedLines = $transfer->transactions()->whereNotNull('to_pop_id')->with(['item.category', 'serial', 'fromPop', 'toPop', 'fromTechnician', 'toTechnician'])->get();

        // Tombol "Konfirmasi Penerimaan" cuma buat sisi Cabang TUJUAN — beda
        // dari show() di atas yang boleh dua sisi. Sebelumnya view cuma cek
        // permission (bukan scope), jadi admin Pusat/cabang lain yang
        // kebetulan bisa buka halaman ini (dari sisi Pusat) tetap ngeliat
        // tombol Konfirmasi walau `receive()` bakal nolak 403 pas beneran
        // diklik — dirapikan biar tombolnya emang gak nongol kalau bakal
        // ditolak (ketauan audit tata-letak 2026-09-03).
        $canReceive = $access->hasAllPopAccess($user) || in_array($transfer->to_pop_id, $access->getAllowedPopIds($user), true);

        return view('warehouse.transfers.show', compact('transfer', 'dispatchLines', 'confirmedLines', 'canReceive'));
    }

    public function receive(Request $request, InventoryTransfer $transfer, InventoryTransferService $service, EffectiveAccessService $access): RedirectResponse
    {
        // Cuma Cabang TUJUAN yang boleh konfirmasi terima — beda dari show()
        // yang boleh dua sisi, receive() itu aksi tulis milik satu sisi doang.
        $this->assertPopInScope($transfer->toPop, auth()->user(), $access);

        $validated = $request->validate([
            'confirmed_serial_numbers' => 'nullable|array',
            'confirmed_serial_numbers.*' => 'string',
            'confirmed_quantities' => 'nullable|array',
        ]);

        try {
            $transfer = $service->receiveTransfer(
                $transfer,
                $validated['confirmed_serial_numbers'] ?? [],
                $validated['confirmed_quantities'] ?? [],
                auth()->user()
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('warehouse.transfers.show', $transfer)
            ->with('success', "Transfer {$transfer->reference_number} dikonfirmasi: {$transfer->status->label()}.");
    }

    /**
     * Baris form (item_id + qty/lot_no ATAU serial_numbers teks multi-baris)
     * → bentuk array yang diharapkan `InventoryTransferService::createTransfer()`.
     *
     * Cabang diputuskan dari `tracking_type` BARANG-nya (query balik ke DB),
     * BUKAN dari ada/enggaknya key `serial_numbers` di request — sebelumnya
     * item serialized yang textarea SN-nya dikosongin (lupa isi) kepeleset
     * ke cabang qty (`filled()` gagal → qty=0 diam-diam), gak ada error
     * jelas "SN wajib diisi" (ketauan audit 2026-09-02).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<array{item_id:int, qty?:float, lot_no?:?string, serial_numbers?:list<string>}>
     */
    private function normalizeLines(array $rows): array
    {
        $itemIds = collect($rows)->pluck('item_id')->map(fn ($id) => (int) $id)->unique();
        $trackingTypes = Item::whereIn('id', $itemIds)->pluck('tracking_type', 'id');

        return collect($rows)->map(function (array $row) use ($trackingTypes) {
            $itemId = (int) $row['item_id'];
            $isSerialized = ($trackingTypes[$itemId] ?? null) === TrackingType::SERIALIZED;

            if ($isSerialized) {
                $serials = collect(preg_split('/[\r\n,]+/', (string) ($row['serial_numbers'] ?? '')))
                    ->map(fn ($s) => trim($s))
                    ->filter()
                    ->values()
                    ->all();

                if ($serials === []) {
                    throw new InvalidArgumentException("Barang #{$itemId} bertipe Serial Number — daftar SN wajib diisi, gak boleh kosong.");
                }

                return ['item_id' => $itemId, 'serial_numbers' => $serials];
            }

            if (filled($row['serial_numbers'] ?? null)) {
                throw new InvalidArgumentException("Barang #{$itemId} bukan tipe Serial Number — kosongkan kolom Serial Number, isi Qty.");
            }

            return [
                'item_id' => $itemId,
                'qty' => (float) ($row['qty'] ?? 0),
                'lot_no' => $row['lot_no'] ?? null,
            ];
        })->all();
    }
}
