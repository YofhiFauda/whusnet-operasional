<?php /** @var \Illuminate\Support\Collection<int, \App\Models\InventoryRoll> $rolls */ ?>
{{--
    Cetak label roll kabel (App\Enums\TrackingType::ROLL) — satu view dipakai
    dua rute: single ($rolls berisi 1 roll, `warehouse.rolls.print`) dan
    batch/massal (`warehouse.rolls.print-batch`, semua roll hasil 1 Receive).
    Sticker 104mm x 84mm (100mm + 2mm bleed tiap sisi), @media print + @page
    size, browser print/"Save as PDF" (BUKAN dompdf) — pola ukuran sama
    resources/views/customers/qr/print.blade.php.

    Barcode 1D (Code128, koreksi eksplisit user 2026-09-16 — BUKAN QR),
    sejalan `barcode-scan.js`/Scan Barang existing yang emang scope-nya
    barcode 1D dari awal. Layout VERTIKAL (barcode wide di atas, bukan
    kotak QR di samping) — barcode 1D butuh lebar, bukan persegi:
    [barcode] → roll_code (teks manusia) → nama barang.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Label Roll Kabel{{ isset($reference) ? " — {$reference}" : ' — '.$rolls->first()->roll_code }}</title>
    <style>
        * { box-sizing: border-box; }

        body {
            font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
            margin: 0;
            padding: 0;
            background: #f1f5f9;
            color: #000;
        }

        .toolbar {
            max-width: 220px;
            margin: 16px auto;
            text-align: center;
        }

        .toolbar button {
            padding: 8px 18px;
            font: inherit;
            font-weight: 700;
            border: 1px solid #d97706;
            border-radius: 6px;
            background: #d97706;
            color: #fff;
            cursor: pointer;
        }

        /* Sticker 100mm x 80mm + bleed 2mm tiap sisi = 104mm x 84mm, safe
           area 6mm dari tepi canvas luar — sama ukuran stiker QR pelanggan
           biar staf gudang bisa pakai stok label yang sama. */
        .sticker {
            width: 104mm;
            height: 84mm;
            margin: 0 auto 6mm;
            background: #fff;
            border: 1px solid #cbd5e1;
            padding: 6mm;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .sticker-barcode {
            width: 100%;
            display: flex;
            justify-content: center;
        }

        .sticker-barcode img {
            width: 88mm;
            height: auto;
            display: block;
        }

        .sticker .roll-code {
            font-weight: 800;
            font-size: 13px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            line-height: 1.2;
            color: #000;
            text-align: center;
            margin-top: 1.5mm;
            word-break: break-all;
        }

        .sticker .item-name {
            font-size: 11px;
            font-weight: 700;
            color: #1e293b;
            text-align: center;
            margin-top: 1mm;
        }

        @media screen {
            body {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                padding: 24px;
            }
            .sticker {
                border-radius: 8px;
                box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            }
        }

        @media print {
            .toolbar { display: none; }
            body {
                background: #fff;
                padding: 0;
                margin: 0;
            }
            .sticker {
                border: none;
                margin: 0;
                box-shadow: none;
                page-break-inside: avoid;
            }
            @page {
                size: 104mm 84mm;
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()">Cetak {{ $rolls->count() > 1 ? "Semua ({$rolls->count()})" : '' }}</button>
    </div>

    @foreach ($rolls as $roll)
        <div class="sticker">
            <div class="sticker-barcode">
                <img src="{{ $barcodeDataUris[$roll->id] }}" alt="Barcode Roll {{ $roll->roll_code }}">
            </div>

            <div class="roll-code">{{ $roll->roll_code }}</div>
            <div class="item-name">{{ $roll->item?->name ?? '(barang dihapus)' }}</div>
        </div>
    @endforeach
</body>
</html>
