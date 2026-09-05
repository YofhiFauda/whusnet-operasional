<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\InventoryTransactionType;
use App\Enums\TrackingType;
use App\Http\Controllers\Controller;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\Pop;
use App\Services\InventoryReceiveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Barang Masuk (RECEIVE) — titik masuk barang baru dari supplier ke Gudang
 * Pusat. Ketinggalan waktu Fase 8 UI pertama kali dibangun — `InventoryReceiveService`
 * udah ada sejak Fase 6, tapi gak pernah dipanggil controller manapun (ketauan
 * pas user tanya cara input 100 SN modem ZTE baru, 2026-09-02).
 *
 * Permission REUSE `warehouse_transfer.create` (bukan feature baru) — RECEIVE
 * dan Transfer-dispatch sama-sama pekerjaan staf Gudang Pusat, jangan pecah
 * jadi permission terpisah buat satu aksi yang aktor & lokasinya identik.
 */
class WarehouseReceiveController extends Controller
{
    public function create(): View
    {
        $pusatPops = Pop::query()->where('type', 'pusat')->orderBy('name')->get();
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

    /**
     * Barang Masuk via Scan SN — dipanggil tab "Scan Masuk" (Single Assign
     * & Batch Assign) di Lacak Barang/SN (`warehouse.traceability.index`).
     * SATU aksi buat DUA tab itu: keduanya, secara data, sama persis —
     * satu Gudang Pusat + satu model barang + daftar SN + satu harga
     * satuan (semua unit dalam satu submit dianggap satu batch pembelian,
     * harga sama). Bedanya cuma di sisi klien: Single scan-tambah satu-satu
     * ke daftar, Batch tempel/scan banyak baris sekaligus — begitu sampai
     * submit, bentuknya identik, jadi gak perlu dua endpoint terpisah.
     *
     * Reuse `InventoryReceiveService::receiveBatch()` dengan SATU baris
     * (bukan `receiveSerialized()` langsung) — biar dapet `reference_number`
     * (RCV-...) & bisa di-redirect PRG ke halaman Bon Penerimaan yang SAMA
     * dipakai form manual (`warehouse.receive.show`), bukan bikin halaman
     * hasil terpisah buat jalur scan.
     */
    public function storeScanned(Request $request, InventoryReceiveService $service): RedirectResponse
    {
        $validated = $request->validate([
            'pop_id' => 'required|integer|exists:pops,id',
            'item_id' => 'required|integer|exists:items,id',
            'unit_price' => 'required|numeric|min:1',
            'notes' => 'nullable|string|max:500',
            'serial_numbers' => 'required|array|min:1',
            'serial_numbers.*' => [
                'required', 'string', 'max:100', 'distinct:ignore_case',
                Rule::unique('inventory_serials', 'serial_number'),
            ],
        ]);

        $pusat = Pop::findOrFail($validated['pop_id']);
        $item = Item::findOrFail($validated['item_id']);
        $actor = auth()->user();

        if ($item->tracking_type !== TrackingType::SERIALIZED) {
            return back()->withInput()->with('error', "Barang {$item->name} bukan tipe Bernomor Seri — gak bisa di-assign lewat scan SN.");
        }

        try {
            $reference = $service->receiveBatch($pusat, [[
                'item_id' => $item->id,
                'serial_numbers' => $validated['serial_numbers'],
                'unit_price' => (float) $validated['unit_price'],
            ]], $actor, $validated['notes'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $unitCount = count($validated['serial_numbers']);

        return redirect()->route('warehouse.receive.show', $reference)
            ->with('success', "Barang masuk {$reference} tercatat di {$pusat->name} — {$item->name} ({$unitCount} unit).");
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
