<?php

namespace App\Services\Warehouse;

use Picqer\Barcode\BarcodeGeneratorSVG;

/**
 * Render barcode 1D label roll kabel — BUKAN QR (koreksi eksplisit user
 * 2026-09-16, gantiin `RollLabelQrRenderer` sebelumnya). Sejalan
 * `barcode-scan.js` (Scan Barang existing) yang emang scope-nya barcode 1D
 * dari awal, bukan QR — jadi roll_code yang dicetak di sini bisa langsung
 * discan pakai alur scan yang sama, gak perlu mode scan terpisah.
 *
 * Code128 (auto subtype B/C) — cukup buat alfanumerik + tanda hubung
 * `roll_code` (mis. `DC-4C-20260916-000002`), format barcode paling umum
 * didukung scanner genggam/webcam murah.
 */
class RollLabelBarcodeRenderer
{
    public function dataUri(string $rollCode): string
    {
        $generator = new BarcodeGeneratorSVG;
        $svg = $generator->getBarcode($rollCode, BarcodeGeneratorSVG::TYPE_CODE_128, 2, 50);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
