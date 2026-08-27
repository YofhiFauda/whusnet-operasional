{{--
    Wajib ganti PIN cetak — login pertama (docs/plan/qr-code/
    rancangan-qr-pelanggan-final.md §6.5.5b). Cuma ke-render kalau session
    flag pending_change sudah dipasang QrBillingController::verify() —
    gak bisa dilompati langsung.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Ganti PIN — Whusnet</title>
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

        .card { width: 100%; max-width: 360px; background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 28px 24px; }
        h1 { font-size: 16px; margin: 0 0 6px; }
        p.desc { font-size: 12px; color: #94a3b8; margin: 0 0 18px; line-height: 1.5; }

        label { display: block; font-size: 12px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.04em; margin: 14px 0 6px; }
        input {
            width: 100%; font-size: 18px; letter-spacing: 0.3em; text-align: center;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            padding: 12px; border-radius: 8px; border: 1px solid #334155; background: #0f172a; color: #f1f5f9;
        }
        input:focus { outline: none; border-color: #0284c7; }

        button { width: 100%; margin-top: 18px; padding: 12px; font: inherit; font-weight: 700; border: none; border-radius: 8px; background: #0284c7; color: #fff; cursor: pointer; }
        button:hover { background: #0369a1; }

        .error { margin-top: 14px; padding: 10px 12px; border-radius: 8px; background: rgba(244, 63, 94, 0.12); border: 1px solid rgba(244, 63, 94, 0.4); color: #fda4af; font-size: 13px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Ganti PIN Anda</h1>
        <p class="desc">Untuk keamanan, PIN yang tercetak di kartu wajib diganti sekali di pemakaian pertama.</p>

        <form method="POST" action="{{ route('qr.billing.pin.change-submit', ['code' => $code]) }}">
            @csrf

            <label for="new_pin">PIN Baru (6 digit)</label>
            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6" name="new_pin" id="new_pin" autocomplete="off" required autofocus>

            <label for="new_pin_confirmation">Ulangi PIN Baru</label>
            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6" name="new_pin_confirmation" id="new_pin_confirmation" autocomplete="off" required>

            <button type="submit">Simpan PIN Baru</button>
        </form>

        @if ($error)
            <div class="error">{{ $error }}</div>
        @endif

        @error('new_pin')
            <div class="error">{{ $message }}</div>
        @enderror
        @error('new_pin_confirmation')
            <div class="error">PIN baru dan konfirmasi tidak sama.</div>
        @enderror
    </div>
</body>
</html>
