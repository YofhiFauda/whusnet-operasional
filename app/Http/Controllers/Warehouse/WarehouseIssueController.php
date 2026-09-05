<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\InventoryTransactionType;
use App\Enums\SerialStatus;
use App\Enums\TrackingType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Warehouse\Concerns\AuthorizesWarehousePop;
use App\Models\InventoryBalance;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\Pop;
use App\Models\User;
use App\Services\EffectiveAccessService;
use App\Services\InventoryIssueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Issue Gudang Cabang → Teknisi (ADHOC-54, rancangan-ui.md §2.4). Beda dari
 * Transfer: SATU aksi langsung selesai (stok cabang berkurang + custody
 * teknisi terbentuk sekaligus) — gak ada fase confirm terpisah.
 *
 * Gak py model/tabel header sendiri (beda dari `InventoryTransfer`) — satu
 * Issue itu sekumpulan baris `inventory_transactions` yang berbagi
 * `reference_number` (ISS-...), makanya `show()` nerima STRING, bukan route
 * model binding.
 */
class WarehouseIssueController extends Controller
{
    use AuthorizesWarehousePop;

    public function create(EffectiveAccessService $access): View
    {
        $user = auth()->user();

        $cabangPops = Pop::query()
            ->where('type', 'cabang')
            ->when(! $access->hasAllPopAccess($user), fn ($q) => $q->whereIn('id', $access->getAllowedPopIds($user)))
            ->orderBy('name')
            ->get();

        // Teknisi/FOP doang di dropdown — admin/owner gak logis jadi "penerima"
        // barang lapangan. Belum discope per-cabang (butuh data penempatan staf
        // yang gak ada strukturnya sekarang) — SEMUA teknisi/fop lintas cabang
        // muncul, admin gudang yang menilai kewajaran pas milih.
        $technicians = User::query()
            ->whereHas('role', fn ($q) => $q->whereIn('code', ['teknisi', 'fop']))
            ->orderBy('name')
            ->get();

        $items = Item::active()->with('category')->orderBy('name')->get();

        return view('warehouse.issues.create', compact('cabangPops', 'technicians', 'items'));
    }

    public function store(Request $request, InventoryIssueService $service, EffectiveAccessService $access): RedirectResponse
    {
        $validated = $request->validate([
            'cabang_pop_id' => 'required|integer|exists:pops,id',
            'technician_id' => 'required|integer|exists:users,id',
            'lines' => 'required|array|min:1',
            'lines.*.item_id' => 'required|integer|exists:items,id',
            'lines.*.qty' => 'nullable|numeric|min:0.01',
            'lines.*.lot_no' => 'nullable|string|max:50',
            'lines.*.serial_numbers' => 'nullable|string',
        ]);

        $cabang = Pop::findOrFail($validated['cabang_pop_id']);
        // Dropdown Cabang di create() cuma nyaring TAMPILAN — tanpa cek ini,
        // pop_admin scoped bisa POST cabang_pop_id cabang lain langsung.
        $this->assertPopInScope($cabang, auth()->user(), $access);

        $technician = User::findOrFail($validated['technician_id']);

        try {
            $lines = $this->normalizeLines($validated['lines']);
            $transactions = $service->issue($cabang, $technician, $lines, auth()->user());
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $reference = $transactions[0]->reference_number;

        return redirect()->route('warehouse.issues.show', $reference)
            ->with('success', "Serah terima {$reference} berhasil dicatat untuk {$technician->name}.");
    }

    /**
     * Referensi read-only "apa aja yang beneran ada di Cabang ini sekarang"
     * (ADHOC-54, gap "Issue ribet" — admin sebelumnya harus hafal/nebak SN
     * yang tersedia sebelum ngetik manual, baru ketauan salah pas submit).
     * Dipanggil via fetch() dari `warehouse.issues.create` begitu Cabang
     * dipilih — BUKAN endpoint mutasi, jadi cukup gerbang permission yang
     * sama kayak halaman create-nya.
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

    public function show(string $reference, EffectiveAccessService $access): View
    {
        $transactions = InventoryTransaction::query()
            ->where('reference_number', $reference)
            ->where('type', InventoryTransactionType::ISSUE->value)
            ->with(['item.category', 'serial', 'fromPop', 'toTechnician', 'createdBy'])
            ->get();

        abort_if($transactions->isEmpty(), 404);

        $this->assertPopIdInScope($transactions->first()->from_pop_id, auth()->user(), $access);

        return view('warehouse.issues.show', ['reference' => $reference, 'transactions' => $transactions]);
    }

    /**
     * Cabang diputuskan dari `tracking_type` BARANG-nya, bukan ada/enggaknya
     * key `serial_numbers` di request — lihat docblock
     * `WarehouseTransferController::normalizeLines()` buat alasan lengkap.
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
