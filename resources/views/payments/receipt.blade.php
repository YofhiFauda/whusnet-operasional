{{--
    Kwitansi pembayaran — SATU template dipakai identik di semua titik cetak
    (ADHOC-94, docs/plan/billing/analisa-rancangan-perubahan-kwitansi.md).

    Struk thermal 80mm dan invoice A4-Stripe yang dulu ada di sini DIHAPUS
    total — bukan disembunyikan. Staf & pelanggan sekarang melihat bentuk
    yang identik: tidak ada lagi info internal (Diterima oleh/Catatan/status
    badge berwarna/riwayat pembayaran) di lembar kwitansi.

    Markup isi kwitansi ada di `payments/partials/kwitansi.blade.php` — SATU
    sumber yang sama juga dipakai `payments/show.blade.php` (blok print-only
    Detail Pembayaran), biar tidak ada lagi dua salinan markup yang pelan-
    pelan menyimpang.

    Dipakai oleh:
    - PaymentController::receipt()                → cetak fisik staf (kertas NCR)
    - PortalPaymentController::receiptPdf()        → dompdf, unduhan pelanggan
    - PortalPaymentController::receiptView()       → iframe modal "Lihat Kwitansi"
--}}
{{--
    `$isPdf` (2026-09-01, diperluas 2026-09-23 — ADHOC-94): true dari
    `PortalPaymentController::receiptPdf()` DAN `receiptView()` — keduanya
    konteks Portal, bukan halaman cetak fisik staf. `$isCustomerCopy` yang
    dulu memisahkan ketiganya sudah tidak relevan (isi sekarang identik di
    semua titik), jadi satu flag ini cukup untuk dua hal PURELY TEKNIS:
    1. Toolbar "Cetak Kwitansi"/"Detail Pembayaran" disembunyikan — link
       "Detail Pembayaran" menuju rute staf internal, tak boleh muncul di
       konteks Portal (pelanggan tak login ke panel staf).
    2. `@page` ukuran kertas fisik NCR (9,5×5,5in) cuma berlaku untuk jalur
       cetak fisik staf. PDF & iframe Portal tidak pernah dicetak ke kertas
       NCR kantor — biarkan default (dompdf pakai `setPaper('a4')`, browser
       pakai default halaman kalau iframe-nya dicetak).
--}}
@php($isPdf = $isPdf ?? false)

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Kwitansi — {{ $kwitansi['nomor'] }}</title>

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

        .toolbar button,
        .toolbar a {
            display: inline-block;

            padding: 8px 18px;

            font: inherit;
            font-size: 13px;
            font-weight: 700;

            border: 1px solid #0284c7;
            border-radius: 6px;

            background: #0284c7;
            color: #ffffff;

            cursor: pointer;
            text-decoration: none;
        }

        .toolbar a {
            background: #ffffff;
            color: #000000;
            border-color: #cbd5e1;
            margin-left: 6px;
        }

        .sheet-frame {
            background: #ffffff;
            width: 100%;
            max-width: 700px;
            margin: 0 auto;
            padding: 24px;
        }

        /* Kertas NCR 2-ply — cuma jalur cetak fisik staf (bukan PDF/iframe
           Portal). Lihat docblock $isPdf di atas. */
        @media print {
            @if(! $isPdf)
                @page {
                    size: 9.5in 5.5in;
                    margin: 0;
                }
            @endif

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
                padding: 6mm 5mm;
            }
        }
    </style>
</head>

<body>

    @unless($isPdf)
        <div class="toolbar">
            <button type="button" onclick="window.print()">
                Cetak Kwitansi
            </button>

            <a href="{{ route('payments.show', $payment->id) }}">
                Detail Pembayaran
            </a>
        </div>
    @endunless

    <div class="sheet-frame">
        @include('payments.partials.kwitansi')
    </div>

</body>

</html>
