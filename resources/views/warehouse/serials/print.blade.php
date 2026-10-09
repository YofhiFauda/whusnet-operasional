<?php /** @var \Illuminate\Support\Collection<int, \App\Models\InventorySerial> $serials */ ?>
{{--
    Cetak label SN barang SERIALIZED `auto_generate_serial=true` (ODP,
    Splitter — gak punya SN vendor). Satu view dipakai dua rute: single
    ($serials berisi 1 SN, `warehouse.serials.print`) dan batch/massal
    (`warehouse.receive.serials.print`, semua SN hasil 1 Receive). Layout &
    ukuran sticker COPY PERSIS `warehouse/rolls/print.blade.php` biar staf
    gudang pakai stok label yang sama — lihat
    docs/plan/warehouse/analisa-generate-id-barang-non-serial.md.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Label SN{{ isset($reference) ? " — {$reference}" : ' — '.$serials->first()->serial_number }}</title>
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

        .sticker {
            width: 104mm;
            height: 40mm;
            margin: 0 auto 6mm;
            background: #fff;
            border: 1px solid #cbd5e1;
            padding: 3mm 4mm;
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
            max-height: 22mm;
            display: block;
        }

        .sticker .serial-code {
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
                size: 104mm 40mm;
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()">Cetak {{ $serials->count() > 1 ? "Semua ({$serials->count()})" : '' }}</button>
    </div>

    @foreach ($serials as $serial)
        <div class="sticker">
            <div class="sticker-barcode">
                <img src="{{ $barcodeDataUris[$serial->id] }}" alt="Barcode SN {{ $serial->serial_number }}">
            </div>

            <div class="serial-code">{{ $serial->serial_number }}</div>
            <div class="item-name">{{ $serial->item?->name ?? '(barang dihapus)' }}</div>
        </div>
    @endforeach
</body>
</html>
