{{--
    "Cetak QR" — stiker tempel modem (revisi user 2026-11-15). Isinya CUMA
    QR + CID di bawahnya. SENGAJA tanpa nama, Login ID, dan PIN: stiker ini
    terpajang permanen di rumah pelanggan, siapa pun bisa memotretnya.
    Kartu lengkap ber-PIN ada di customers/qr/print.blade.php
    ("QR Kirim ke Pelanggan").

    Teks bawah = `display_id`: CID asli kalau sudah aktif, REQ ID/C00RQ…
    kalau belum (keputusan user — stiker umumnya dicetak saat pemasangan).
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Cetak QR — {{ $customer->display_id }}</title>
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
            margin: 16px auto;
            text-align: center;
        }

        .toolbar button {
            padding: 8px 18px;
            font: inherit;
            font-weight: 700;
            border: 1px solid #0284c7;
            border-radius: 6px;
            background: #0284c7;
            color: #fff;
            cursor: pointer;
        }

        /* Canvas 50mm x 60mm + bleed 2mm tiap sisi = 54mm x 64mm */
        .label {
            width: 54mm;
            height: 64mm;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #cbd5e1;
            padding: 5mm;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 2mm;
            overflow: hidden;
        }

        .label img {
            width: 42mm;
            height: 42mm;
            display: block;
        }

        .label .cid {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: .05em;
            text-align: center;
            word-break: break-all;
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
            .label {
                border-radius: 8px;
                box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            }
            /* Penanda garis potong 2mm */
            .label::after {
                content: '';
                position: absolute;
                inset: 2mm;
                border: 1px dashed rgba(239, 68, 68, 0.35);
                pointer-events: none;
                border-radius: 4px;
            }
        }

        @media print {
            .toolbar { display: none; }
            body {
                background: #fff;
                padding: 0;
                margin: 0;
            }
            .label {
                border: none;
                margin: 0;
                box-shadow: none;
                page-break-inside: avoid;
            }
            @page {
                size: 54mm 64mm;
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()">Cetak</button>
    </div>

    <div class="label">
        <img src="{{ $qrDataUri }}" alt="QR {{ $customer->display_id }}">
        <div class="cid">{{ $customer->display_id }}</div>
    </div>
</body>
</html>
