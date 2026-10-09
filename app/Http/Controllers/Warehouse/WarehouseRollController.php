<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Warehouse\Concerns\AuthorizesWarehousePop;
use App\Models\InventoryRoll;
use App\Services\EffectiveAccessService;
use App\Services\Warehouse\RollLabelBarcodeRenderer;
use Illuminate\View\View;

/**
 * Cetak label roll kabel (App\Enums\TrackingType::ROLL) — permission REUSE
 * `warehouse_transfer.view` (label roll nempel ke dokumen Receive, sejalan
 * `warehouse.receive.show`, bukan feature baru). Browser print/"Save as PDF",
 * BUKAN dompdf (itu reserved buat kwitansi A4 — pola sama
 * `customers/qr/print.blade.php` yang juga browser-print).
 *
 * Barcode 1D (koreksi eksplisit user 2026-09-16), BUKAN QR — sejalan
 * `barcode-scan.js`/Scan Barang existing.
 */
class WarehouseRollController extends Controller
{
    use AuthorizesWarehousePop;

    public function print(InventoryRoll $roll, RollLabelBarcodeRenderer $renderer, EffectiveAccessService $access): View
    {
        $roll->load('item');
        $this->assertPopIdInScope($roll->current_pop_id ?? $roll->issued_from_pop_id, auth()->user(), $access);

        return view('warehouse.rolls.print', [
            'rolls' => collect([$roll]),
            'barcodeDataUris' => [$roll->id => $renderer->dataUri($roll->roll_code)],
        ]);
    }

    public function printBatch(string $reference, RollLabelBarcodeRenderer $renderer, EffectiveAccessService $access): View
    {
        $rolls = InventoryRoll::query()
            ->with('item')
            ->whereIn('id', function ($query) use ($reference) {
                $query->select('roll_id')
                    ->from('inventory_transactions')
                    ->where('reference_number', $reference)
                    ->where('type', 'receive')
                    ->whereNotNull('roll_id');
            })
            ->orderBy('roll_code')
            ->get();

        abort_if($rolls->isEmpty(), 404, "Gak ada roll kabel buat referensi {$reference}.");

        $user = auth()->user();
        foreach ($rolls as $roll) {
            $this->assertPopIdInScope($roll->current_pop_id ?? $roll->issued_from_pop_id, $user, $access);
        }

        $barcodeDataUris = $rolls->mapWithKeys(fn ($roll) => [$roll->id => $renderer->dataUri($roll->roll_code)])->all();

        return view('warehouse.rolls.print', ['rolls' => $rolls, 'barcodeDataUris' => $barcodeDataUris, 'reference' => $reference]);
    }
}
