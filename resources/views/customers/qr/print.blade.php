{{--
    Cetak Stiker QR Pelanggan (docs/plan/qr-code/rancangan-qr-pelanggan-final.md
    §7.6, digabung 2026-08-26 atas keputusan eksplisit user) — QR + nama +
    REQ ID + POP + Login ID Portal + PIN, dalam SATU cetakan.

    PIN sekarang IKUT tampil di sini (perintah eksplisit user 2026-08-26,
    membalik keputusan sebelumnya) — `pin_hash` direvisi jadi reversible
    (Crypt::encryptString, bukan bcrypt) supaya halaman reprintable ini bisa
    nunjukin PIN kapan pun dibuka ulang, lihat docblock
    `CustomerQrTokenService::issuePin()`/`revealPin()`. Trade-off SADAR:
    siapa pun akses DB + APP_KEY bisa baca PIN semua pelanggan — beda dari
    hash lama yang buta permanen. Baris yang dibuat SEBELUM revisi ini masih
    format bcrypt lama → `revealPin()` balikin null → PIN gak tampil (bukan
    bug; satu-satunya jalan keluar reset PIN dari halaman QR Pelanggan).

    Label manusia pakai REQ ID (bukan CID) — REQ ID permanen, CID baru
    terisi setelah pelanggan aktif (§7.6).
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Stiker QR — {{ $customer->full_name }}</title>
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
            border: 1px solid #0284c7;
            border-radius: 6px;
            background: #0284c7;
            color: #fff;
            cursor: pointer;
        }

        /* 
           Canvas stiker: 100mm x 80mm
           Ditambah bleed 2mm tiap sisi: 104mm x 84mm
           Safe Area: minimal 4mm dari garis potong (total padding 6mm dari tepi canvas luar)
        */
        .sticker {
            width: 104mm;
            height: 84mm;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #cbd5e1;
            padding: 6mm;
            position: relative;
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
            gap: 5mm;
            overflow: hidden;
        }

        /* Kolom Kiri: QR Code (25mm x 25mm ideal) */
        .sticker-qr-container {
            width: 28mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            flex-shrink: 0;
        }

        .sticker-qr-container img {
            width: 25mm;
            height: 25mm;
            display: block;
            margin-bottom: 2mm;
        }

        .sticker-qr-container .hint {
            font-size: 7.5px;
            color: #64748b;
            line-height: 1.25;
        }

        /* Kolom Kanan: Detail & PIN */
        .sticker-content {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            height: 100%;
        }

        .sticker-content .name {
            font-weight: 800;
            font-size: 12px;
            text-transform: uppercase;
            line-height: 1.2;
            color: #000;
            word-break: break-word;
        }

        .sticker-content .meta {
            font-size: 9.5px;
            color: #475569;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            margin-top: 1mm;
        }

        .sticker-content .divider {
            border-top: 1px dashed #cbd5e1;
            margin: 2mm 0;
        }

        .sticker-content .login-id {
            font-size: 10px;
            color: #0369a1;
            font-weight: 700;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        }

        .sticker-content .login-hint {
            font-size: 7.5px;
            color: #64748b;
            line-height: 1.25;
            margin-top: 0.5mm;
        }

        .sticker-content .pin-section {
            margin-top: 2mm;
        }

        .sticker-content .pin-label {
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #64748b;
        }

        .sticker-content .pin {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: .25em;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            color: #000;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 4px;
            padding: 2px 0;
            text-align: center;
            margin-top: 0.5mm;
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
            /* Visual guide for 2mm cut line (crop marks indicator) */
            .sticker::after {
                content: '';
                position: absolute;
                top: 2mm;
                left: 2mm;
                right: 2mm;
                bottom: 2mm;
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
        <button onclick="window.print()">Cetak</button>
    </div>

    <div class="sticker">
        <!-- Kolom Kiri: QR Code & Hint Scan -->
        <div class="sticker-qr-container">
            <img src="{{ $qrDataUri }}" alt="QR Pelanggan {{ $customer->full_name }}">
            <div class="hint">Scan QR ini pakai HP untuk cek &amp; bayar tagihan.</div>
        </div>

        <!-- Kolom Kanan: Detail Pelanggan & PIN -->
        <div class="sticker-content">
            <div class="name">{{ $customer->full_name }}</div>
            <div class="meta">{{ $customer->customer_code }} · {{ $customer->pop?->name ?? '—' }}</div>

            @if ($customer->portal_login_id)
                <div class="divider"></div>
                <div class="login-id">Login ID: {{ $customer->portal_login_id }}</div>
                <div class="login-hint">Gunakan Login ID &amp; PIN di bawah untuk aktivasi Portal Pelanggan.</div>
            @endif

            @if ($plainPin)
                <div class="pin-section">
                    <div class="pin-label">PIN Aktivasi</div>
                    <div class="pin">{{ $plainPin }}</div>
                </div>
            @elseif ($customer->portal_login_id)
                <div class="divider"></div>
                <div class="login-hint">PIN belum tersedia untuk dicetak ulang — reset PIN dari halaman QR Pelanggan.</div>
            @endif
        </div>
    </div>
</body>
</html>
