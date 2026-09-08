<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\InventoryTransactionType;
use App\Enums\TrackingType;
use App\Http\Controllers\Controller;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\Pop;
use App\Services\EffectiveAccessService;
use App\Services\InventoryReceiveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Barang Masuk (RECEIVE) — titik masuk barang baru dari distributor ke
 * Gudang Pusat. Ketinggalan waktu Fase 8 UI pertama kali dibangun —
 * `InventoryReceiveService` udah ada sejak Fase 6, tapi gak pernah dipanggil
 * controller manapun (ketauan pas user tanya cara input 100 SN modem ZTE
 * baru, 2026-09-02).
 *
 * Permission REUSE `warehouse_transfer.create` (bukan feature baru) — RECEIVE
 * dan Transfer-dispatch sama-sama pekerjaan staf Gudang Pusat, jangan pecah
 * jadi permission terpisah buat satu aksi yang aktor & lokasinya identik.
 */
class WarehouseReceiveController extends Controller
{
    public function create(EffectiveAccessService $access): View
    {
        $pusatPops = Pop::query()
            ->where('type', 'pusat')
            ->when(! $access->hasAllPopAccess(auth()->user()), fn ($q) => $q->whereIn('id', $access->getAllowedPopIds(auth()->user())))
            ->orderBy('name')
            ->get();
        $items = Item::active()->with('category')->orderBy('name')->get();

        return view('warehouse.receive.create', compact('pusatPops', 'items'));
    }

    public function store(Request $request, InventoryReceiveService $service): RedirectResponse
    {
        $validated = $request->validate([
            'pop_id' => 'required|integer|exists:pops,id',
            'notes' => 'nullable|string|max:500',
            'lines' => 'required|array|min:1',
            'lines.*.item_id' => 'required|integer|exists:items,id',
            'lines.*.qty' => 'nullable|numeric|min:0.01',
            'lines.*.lot_no' => 'nullable|string|max:50',
            'lines.*.serial_numbers' => 'nullable|string',
            'lines.*.unit_price' => 'required|numeric|min:1',
        ]);

        $pusat = Pop::findOrFail($validated['pop_id']);
        $actor = auth()->user();
        $notes = $validated['notes'] ?? null;

        try {
            $lines = $this->normalizeLines($validated['lines']);
            $reference = $service->receiveBatch($pusat, $lines, $actor, $notes);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('warehouse.receive.show', $reference)
            ->with('success', "Barang masuk {$reference} tercatat di {$pusat->name}.");
    }

    public function show(string $reference): View
    {
        $transactions = InventoryTransaction::query()
            ->where('reference_number', $reference)
            ->where('type', InventoryTransactionType::RECEIVE->value)
            ->with(['item.category', 'serial', 'toPop', 'createdBy'])
            ->get();

        abort_if($transactions->isEmpty(), 404);

        return view('warehouse.receive.show', ['reference' => $reference, 'transactions' => $transactions]);
    }

    /**
     * Baris form (item_id + qty/lot_no ATAU serial_numbers teks multi-baris,
     * + unit_price) → bentuk array yang diharapkan `InventoryReceiveService`.
     * Cabang diputuskan dari `tracking_type` BARANG-nya — lihat docblock
     * `WarehouseTransferController::normalizeLines()` buat alasan lengkap.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<array{item_id:int, qty?:float, lot_no?:?string, serial_numbers?:list<string>, unit_price:float}>
     */
    private function normalizeLines(array $rows): array
    {
        $itemIds = collect($rows)->pluck('item_id')->map(fn ($id) => (int) $id)->unique();
        $trackingTypes = Item::whereIn('id', $itemIds)->pluck('tracking_type', 'id');

        return collect($rows)->map(function (array $row) use ($trackingTypes) {
            $itemId = (int) $row['item_id'];
            $unitPrice = (float) $row['unit_price'];
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

                return ['item_id' => $itemId, 'serial_numbers' => $serials, 'unit_price' => $unitPrice];
            }

            if (filled($row['serial_numbers'] ?? null)) {
                throw new InvalidArgumentException("Barang #{$itemId} bukan tipe Serial Number — kosongkan kolom Serial Number, isi Qty.");
            }

            return [
                'item_id' => $itemId,
                'qty' => (float) ($row['qty'] ?? 0),
                'lot_no' => $row['lot_no'] ?? null,
                'unit_price' => $unitPrice,
            ];
        })->all();
    }
}
