{{--
    Fungsi A — langkah 1 (docs/plan/qr-code/rancangan-qr-pelanggan-final.md
    §6.1). Halaman PUBLIK tanpa auth — SENGAJA cuma nama tersamar + ID + POP,
    TANPA nomor HP lengkap/NIK/foto KTP (§3.5). Data penuh baru muncul di
    langkah 2 setelah PIN/4-digit HP benar.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Tagihan Pelanggan — Whusnet</title>
    <style>
        * { box-sizing: border-box; }

        body {
            font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0f172a;
            color: #e2e8f0;
            padding: 24px;
        }

        .card {
            width: 100%;
            max-width: 360px;
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 28px 24px;
        }

        h1 { font-size: 15px; margin: 0 0 4px; color: #94a3b8; font-weight: 600; }
        .name { font-size: 18px; font-weight: 800; margin: 0 0 2px; letter-spacing: 0.02em; }
        .meta { font-size: 12px; color: #94a3b8; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; margin-bottom: 20px; }

        label { display: block; font-size: 12px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 6px; }
        .hint { font-size: 12px; color: #64748b; margin-top: 6px; }

        input[type=text], input[type=tel] {
            width: 100%;
            font-size: 20px;
            letter-spacing: 0.3em;
            text-align: center;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #334155;
            background: #0f172a;
            color: #f1f5f9;
        }
        input:focus { outline: none; border-color: #0284c7; }

        button {
            width: 100%;
            margin-top: 16px;
            padding: 12px;
            font: inherit;
            font-weight: 700;
            border: none;
            border-radius: 8px;
            background: #0284c7;
            color: #fff;
            cursor: pointer;
        }
        button:hover { background: #0369a1; }

        .error {
            margin-top: 14px;
            padding: 10px 12px;
            border-radius: 8px;
            background: rgba(244, 63, 94, 0.12);
            border: 1px solid rgba(244, 63, 94, 0.4);
            color: #fda4af;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Whusnet — Tagihan Pelanggan</h1>
        <p class="name">{{ $maskedName }}</p>
        <p class="meta">{{ $displayId }} · {{ $popName ?? '—' }}</p>

        <form method="POST" action="{{ route('qr.billing.verify', ['code' => $code]) }}">
            @csrf

            @if ($gateType === 'pin')
                <label for="pin">Masukkan PIN Anda</label>
                <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6" name="pin" id="pin" autocomplete="off" required autofocus>
                <p class="hint">PIN 6 digit tercetak di kartu pelanggan Anda.</p>
            @else
                <label for="hp_last4">4 Digit Terakhir Nomor HP Terdaftar</label>
                <input type="tel" inputmode="numeric" pattern="[0-9]*" maxlength="4" name="hp_last4" id="hp_last4" autocomplete="off" required autofocus>
                <p class="hint">Belum punya PIN? Gunakan 4 digit terakhir nomor HP yang terdaftar.</p>
            @endif

            <button type="submit">Lihat Tagihan</button>
        </form>

        @if ($error)
            <div class="error">{{ $error }}</div>
        @endif

        @error('pin')
            <div class="error">{{ $message }}</div>
        @enderror
        @error('hp_last4')
            <div class="error">{{ $message }}</div>
        @enderror
    </div>
</body>
</html>
