<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\TrackingType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Warehouse\Concerns\AuthorizesWarehousePop;
use App\Models\InventoryBalance;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\Pop;
use App\Services\EffectiveAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Satu kolom pencarian di halaman Koreksi Stok — server yang menentukan
 * barang ini curah, SN, atau roll, supaya user gak perlu tahu istilah
 * "opname qty vs opname unit" (spec 2026-10-31-koreksi-stok-gudang §4.4).
 *
 * Read-only, tapi TETAP lewat POP scope: tanpa itu user cabang bisa menebak
 * SN gudang lain dan melihat statusnya. SN/roll hanya cocok kalau
 * `current_pop_id` = gudang terpilih — unit yang lagi di custody teknisi
 * (current_pop_id null) sengaja gak terselesaikan, koreksinya lewat Custody.
 */
class WarehouseAdjustmentResolveController extends Controller
{
    use AuthorizesWarehousePop;

    private const MAX_OPTIONS = 10;

    /**
     * Cermin cek scope `storeSerial()`/`storeRoll()` (issued_from_pop_id
     * didahulukan). Unit bisa ketemu di gudang user tapi tercatat keluar dari
     * gudang lain — tanpa cermin ini form menawarkan tombol simpan yang pasti
     * 403 dan isian user hilang. Sengaja TIDAK mengubah aturan endpoint simpan.
     */
    private function canAdjustUnit(?int $scopePopId, EffectiveAccessService $access): bool
    {
        $user = auth()->user();

        return $scopePopId === null
            || $access->hasAllPopAccess($user)
            || in_array($scopePopId, $access->getAllowedPopIds($user), true);
    }

    public function __invoke(Request $request, EffectiveAccessService $access): JsonResponse
    {
        $validated = $request->validate([
            'pop_id' => ['required', 'integer', 'exists:pops,id'],
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $pop = Pop::findOrFail($validated['pop_id']);
        $this->assertPopInScope($pop, auth()->user(), $access);
        $q = trim($validated['q']);

        $serial = InventorySerial::query()->with('item')
            ->where('serial_number', $q)->where('current_pop_id', $pop->id)->first();
        if ($serial && ! $serial->item?->isNonOperational()) {
            return response()->json([
                'type' => 'serial',
                'id' => $serial->id,
                'label' => $serial->item->name,
                'serial_number' => $serial->serial_number,
                'status_label' => $serial->status->label(),
                // Target "Barang bermasalah" per-unit — dirender di sini supaya
                // klien gak merakit `/serial/{id}` sendiri (CLAUDE.md, ADHOC-20).
                'adjust_url' => $this->canAdjustUnit($serial->issued_from_pop_id ?? $serial->current_pop_id, $access)
                    ? route('warehouse.adjustments.serial.store', $serial)
                    : null,
            ]);
        }

        $roll = InventoryRoll::query()->with('item')
            ->where('roll_code', $q)->where('current_pop_id', $pop->id)->first();
        if ($roll && ! $roll->item?->isNonOperational()) {
            return response()->json([
                'type' => 'roll',
                'id' => $roll->id,
                'label' => $roll->item->name,
                'roll_code' => $roll->roll_code,
                'length_remaining' => (float) $roll->length_remaining,
                'adjust_url' => $this->canAdjustUnit($roll->issued_from_pop_id ?? $roll->current_pop_id, $access)
                    ? route('warehouse.adjustments.roll.store', $roll)
                    : null,
            ]);
        }

        // '%'/'_' di $q WAJIB di-escape — tanpa ini ketikan seperti "%%" atau
        // "A_B" jadi wildcard LIKE dan mencocokkan hampir semua nama barang.
        $likeQuery = addcslashes($q, '%_\\');

        $items = Item::active()
            ->whereDoesntHave('category', fn ($query) => $query->where('is_operational', false))
            ->where(fn ($query) => $query->where('code', $q)->orWhere('name', 'like', "%{$likeQuery}%"))
            ->orderBy('name')
            ->limit(self::MAX_OPTIONS + 1)
            ->get();

        // Kode persis menang atas kecocokan nama sebagian — "KON-01" jangan
        // sampai jadi daftar pilihan cuma karena ada "KON-010".
        $exact = $items->first(fn (Item $item) => strcasecmp($item->code, $q) === 0);
        if ($exact) {
            $items = collect([$exact]);
        }

        if ($items->isEmpty()) {
            return response()->json(['type' => 'none']);
        }

        if ($items->count() > 1) {
            return response()->json([
                'type' => 'choose',
                // Dicek SEBELUM take() — take() memotong koleksi jadi count()
                // sesudahnya selalu <= MAX_OPTIONS, flag "lebih" gak kebaca.
                'more' => $items->count() > self::MAX_OPTIONS,
                'options' => $items->take(self::MAX_OPTIONS)
                    ->map(fn (Item $item) => ['item_id' => $item->id, 'code' => $item->code, 'label' => $item->name])
                    ->values(),
            ]);
        }

        $item = $items->first();

        // Barang ber-SN/roll gak bisa dihitung sebagai qty — opname-nya per
        // unit. Kasih petunjuk, jangan diam-diam buka form qty yang salah.
        if ($item->tracking_type !== TrackingType::QUANTITY) {
            return response()->json(['type' => 'need_unit', 'label' => $item->name, 'tracking' => $item->tracking_type->value]);
        }

        $lots = InventoryBalance::query()
            ->where('pop_id', $pop->id)->where('item_id', $item->id)
            ->orderBy('lot_no')
            ->get(['lot_no', 'qty'])
            ->map(fn (InventoryBalance $balance) => ['lot_no' => (string) $balance->lot_no, 'qty' => (float) $balance->qty])
            ->values();

        return response()->json([
            'type' => 'qty',
            'item_id' => $item->id,
            'label' => $item->name,
            'unit' => $item->unit,
            'lots' => $lots,
        ]);
    }
}
