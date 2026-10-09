<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Warehouse\Concerns\AuthorizesWarehousePop;
use App\Models\InventorySerial;
use App\Services\EffectiveAccessService;
use App\Services\Warehouse\RollLabelBarcodeRenderer;
use Illuminate\View\View;

/**
 * Cetak label SN barang SERIALIZED yang `auto_generate_serial=true` (ODP,
 * Splitter — gak punya SN vendor). Pola PERSIS `WarehouseRollController`:
 * permission REUSE `warehouse_transfer.view`, barcode 1D Code128 (reuse
 * `RollLabelBarcodeRenderer` — namanya spesifik roll tapi isinya generik,
 * terima string apa aja), browser print bukan dompdf. Lihat
 * docs/plan/warehouse/analisa-generate-id-barang-non-serial.md.
 */
class WarehouseSerialController extends Controller
{
    use AuthorizesWarehousePop;

    public function print(InventorySerial $serial, RollLabelBarcodeRenderer $renderer, EffectiveAccessService $access): View
    {
        $serial->load('item');
        $this->assertPopIdInScope($serial->current_pop_id ?? $serial->issued_from_pop_id, auth()->user(), $access);

        return view('warehouse.serials.print', [
            'serials' => collect([$serial]),
            'barcodeDataUris' => [$serial->id => $renderer->dataUri($serial->serial_number)],
        ]);
    }

    public function printBatch(string $reference, RollLabelBarcodeRenderer $renderer, EffectiveAccessService $access): View
    {
        $serials = InventorySerial::query()
            ->with('item')
            ->whereIn('id', function ($query) use ($reference) {
                $query->select('serial_id')
                    ->from('inventory_transactions')
                    ->where('reference_number', $reference)
                    ->where('type', 'receive')
                    ->whereNotNull('serial_id');
            })
            ->orderBy('serial_number')
            ->get();

        abort_if($serials->isEmpty(), 404, "Gak ada SN buat referensi {$reference}.");

        $user = auth()->user();
        foreach ($serials as $serial) {
            $this->assertPopIdInScope($serial->current_pop_id ?? $serial->issued_from_pop_id, $user, $access);
        }

        $barcodeDataUris = $serials->mapWithKeys(fn ($serial) => [$serial->id => $renderer->dataUri($serial->serial_number)])->all();

        return view('warehouse.serials.print', ['serials' => $serials, 'barcodeDataUris' => $barcodeDataUris, 'reference' => $reference]);
    }
}
