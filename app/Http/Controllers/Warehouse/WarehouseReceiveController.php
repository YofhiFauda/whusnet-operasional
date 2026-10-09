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
use App\Support\RupiahInput;
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
        if ($request->has('lines') && is_array($request->input('lines'))) {
            $request->merge([
                'lines' => array_map(
                    fn ($row) => is_array($row) ? RupiahInput::parseKeys($row, 'unit_price') : $row,
                    $request->input('lines')
                ),
            ]);
        }

        $validated = $request->validate([
            'pop_id' => 'required|integer|exists:pops,id',
            'notes' => 'nullable|string|max:500',
            'lines' => 'required|array|min:1',
            'lines.*.item_id' => 'required|integer|exists:items,id',
            'lines.*.qty' => 'nullable|numeric|min:0.01',
            'lines.*.serial_numbers' => 'nullable|string',
            'lines.*.serial_count' => 'nullable|integer|min:1',
            'lines.*.roll_count' => 'nullable|integer|min:1',
            'lines.*.vendor' => 'nullable|string|max:150',
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
            ->with(['item.category', 'serial', 'roll', 'toPop', 'createdBy'])
            ->get();

        abort_if($transactions->isEmpty(), 404);

        $grandTotal = $transactions->sum(fn ($line) => (float) $line->qty * (float) $line->unit_price_snapshot);

        // Ringkasan per barang & harga — sejalan dengan Invoice TRF (WarehouseTransferController::invoice)
        // Barang ROLL dikonversi ke satuan ROLL (dengan harga beli per roll),
        // sedangkan daftar per-unit SN / Roll ID tetap tersedia di $transactions
        $summaryLines = $transactions
            ->groupBy(fn ($line) => $line->item_id.'|'.$line->unit_price_snapshot)
            ->map(function ($group) {
                $item = $group->first()->item;

                if ($item->tracking_type === TrackingType::ROLL) {
                    $meterPerRoll = (float) $item->meter_per_roll;
                    $unitPricePerRoll = (float) $group->first()->unit_price_snapshot * $meterPerRoll;
                    $rollCount = $group->count();
                    $meterTotal = (float) $group->sum('qty');

                    return (object) [
                        'item' => $item,
                        'qty' => $rollCount,
                        'unit' => 'roll',
                        'meter_total' => $meterTotal,
                        'meter_per_roll' => $meterPerRoll,
                        'unit_price_snapshot' => $unitPricePerRoll,
                        'price_per_meter' => (float) $group->first()->unit_price_snapshot,
                        'subtotal' => (float) $rollCount * $unitPricePerRoll,
                    ];
                }

                $qty = (float) $group->sum('qty');
                $unitPrice = (float) $group->first()->unit_price_snapshot;

                return (object) [
                    'item' => $item,
                    'qty' => $qty,
                    'unit' => $item->unit,
                    'meter_total' => null,
                    'meter_per_roll' => null,
                    'unit_price_snapshot' => $unitPrice,
                    'price_per_meter' => null,
                    'subtotal' => $qty * $unitPrice,
                ];
            })
            ->values();

        return view('warehouse.receive.show', [
            'reference' => $reference,
            'transactions' => $transactions,
            'summaryLines' => $summaryLines,
            'grandTotal' => $grandTotal,
        ]);
    }

    /**
     * Baris form (item_id + qty ATAU serial_numbers teks multi-baris,
     * + unit_price) → bentuk array yang diharapkan `InventoryReceiveService`.
     * Cabang diputuskan dari `tracking_type` BARANG-nya — lihat docblock
     * `WarehouseTransferController::normalizeLines()` buat alasan lengkap.
     * Gak ada `lot_no` di sini lagi (ADHOC-75) — `InventoryReceiveService`
     * yang nentuin lot QUANTITY otomatis.
     *
     * SERIALIZED pecah dua sub-cabang lagi berdasar `auto_generate_serial`
     * item-nya: manual (daftar SN diketik) vs auto (jumlah unit doang, SN
     * digenerate sistem) — lihat
     * docs/plan/warehouse/analisa-generate-id-barang-non-serial.md.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<array{item_id:int, qty?:float, serial_numbers?:list<string>, serial_count?:int, roll_count?:int, vendor?:?string, unit_price:float}>
     */
    private function normalizeLines(array $rows): array
    {
        $itemIds = collect($rows)->pluck('item_id')->map(fn ($id) => (int) $id)->unique();
        $items = Item::whereIn('id', $itemIds)->get(['id', 'tracking_type', 'auto_generate_serial'])->keyBy('id');

        return collect($rows)->map(function (array $row) use ($items) {
            $itemId = (int) $row['item_id'];
            $unitPrice = (float) $row['unit_price'];
            $item = $items[$itemId] ?? null;
            $trackingType = $item?->tracking_type;

            if ($trackingType === TrackingType::SERIALIZED && $item->auto_generate_serial) {
                $serialCount = (int) ($row['serial_count'] ?? 0);

                if ($serialCount < 1) {
                    throw new InvalidArgumentException("Barang #{$itemId} SN-nya digenerate sistem — jumlah unit wajib diisi, minimal 1.");
                }

                return ['item_id' => $itemId, 'serial_count' => $serialCount, 'unit_price' => $unitPrice];
            }

            if ($trackingType === TrackingType::SERIALIZED) {
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

            if ($trackingType === TrackingType::ROLL) {
                $rollCount = (int) ($row['roll_count'] ?? 0);

                if ($rollCount < 1) {
                    throw new InvalidArgumentException("Barang #{$itemId} bertipe Roll Kabel — jumlah roll wajib diisi, minimal 1.");
                }

                return ['item_id' => $itemId, 'roll_count' => $rollCount, 'vendor' => $row['vendor'] ?? null, 'unit_price' => $unitPrice];
            }

            if (filled($row['serial_numbers'] ?? null)) {
                throw new InvalidArgumentException("Barang #{$itemId} bukan tipe Serial Number — kosongkan kolom Serial Number, isi Qty.");
            }

            return [
                'item_id' => $itemId,
                'qty' => (float) ($row['qty'] ?? 0),
                'unit_price' => $unitPrice,
            ];
        })->all();
    }
}
