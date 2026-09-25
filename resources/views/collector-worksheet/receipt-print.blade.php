{{--
    Halaman cetak kwitansi kolektor (setoran diperiksa) — satu lembar per
    payment, kertas biasa (bukan roll thermal), `page-break-after` di antara
    lembar. Markup isi lembar SAMA dengan `payments/receipt.blade.php`
    (partial `payments.partials.kwitansi`, ADHOC-94) + QR pencocokan
    otomatis yang tetap dipertahankan di sini (`docs/plan/kolektor/
    analisa-kwitansi-otomatis-portal.md`, di luar cakupan perubahan desain
    kwitansi).
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kwitansi — {{ $collector->name }}</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
            padding: 16px;
            background: #f4f4f4;
            color: #000000;
        }

        .toolbar {
            max-width: 700px;
            margin: 0 auto 16px;
            text-align: center;
        }

        .toolbar button {
            padding: 8px 18px;
            font: inherit;
            font-size: 13px;
            font-weight: 700;
            border: 1px solid #0284c7;
            border-radius: 6px;
            background: #0284c7;
            color: #ffffff;
            cursor: pointer;
        }

        .sheet-frame {
            background: #ffffff;
            width: 100%;
            max-width: 700px;
            margin: 0 auto 24px;
            padding: 24px;
        }

        .sheet-frame:not(:last-child) {
            page-break-after: always;
        }

        .kw-qr {
            text-align: center;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px dashed #000000;
        }

        .kw-qr img {
            width: 72px;
            height: 72px;
            display: block;
            margin: 0 auto;
        }

        .kw-qr .kw-qr-num {
            margin-top: 4px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .03em;
            word-break: break-all;
        }

        @media print {
            {{-- Header/footer bawaan browser (tanggal, judul, URL) dicetak
                 di kotak margin halaman — cuma lenyap kalau margin-nya nol.
                 Jarak ke tepi kertas ditanggung .sheet-frame, bukan @page. --}}
            @page {
                size: auto;
                margin: 0;
            }

            .toolbar {
                display: none !important;
            }

            body {
                background: #ffffff !important;
                padding: 0 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .sheet-frame {
                max-width: 100%;
                margin: 0 auto;
                padding: 8mm 6mm !important;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Cetak {{ $payments->count() }} Kwitansi</button>
    </div>

    @foreach ($payments as $payment)
        @php($kwitansi = $kwitansiByPayment[$payment->id])
        <div class="sheet-frame">
            @include('payments.partials.kwitansi')

            <div class="kw-qr">
                <img src="{{ $qrByPayment[$payment->id] }}" alt="QR {{ $payment->payment_number }}">
                <div class="kw-qr-num">{{ $payment->payment_number }}</div>
            </div>
        </div>
    @endforeach
</body>
</html>
