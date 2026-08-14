{{-- 
    Struk / kwitansi pembayaran
    DATA    : menggunakan struktur $kwitansi dari KODE 1
    TAMPILAN: menggunakan layout thermal dari KODE 2

    Standalone page, tanpa layouts.app.
    Dioptimalkan untuk printer thermal 80mm.
--}}

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
            font-family:
                'JetBrains Mono',
                ui-monospace,
                SFMono-Regular,
                Menlo,
                Consolas,
                'Courier New',
                monospace;

            margin: 0;
            padding: 16px;

            background: #f1f5f9;
            color: #000000;

            font-size: 11px;
            line-height: 1.35;

            -webkit-font-smoothing: antialiased;
        }

        /* ========================================
           TOOLBAR
           ======================================== */

        .toolbar {
            max-width: 302px;
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

        /* ========================================
           PAGE / KWITANSI
           ======================================== */

        .page {
            width: 302px;
            max-width: 100%;

            margin: 0 auto 24px;

            background: #ffffff;

            border: 1px solid #cbd5e1;

            padding: 16px 14px;

            position: relative;

            color: #000000;
        }

        /* ========================================
           HEADER
           ======================================== */

        .head {
            display: flex;
            flex-direction: column;

            align-items: center;
            text-align: center;

            gap: 2px;
        }

        .brand {
            font-size: 15px;
            font-weight: 800;

            letter-spacing: .03em;

            text-transform: uppercase;

            color: #000000;
        }

        .sub-brand {
            font-size: 12px;
            font-weight: 700;

            color: #000000;
        }

        .desc {
            font-size: 10px;
            font-weight: 500;

            color: #000000;

            line-height: 1.25;
        }

        /* ========================================
           TEXT
           ======================================== */

        .muted {
            color: #000000;
            font-weight: 500;
        }

        /* ========================================
           SEPARATOR
           ======================================== */

        .sep {
            border-top: 1px dashed #000000;
            margin: 8px 0;
        }

        /* ========================================
           TABLE
           ======================================== */

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td {
            padding: 2px 0;
            vertical-align: top;

            font-size: 11px;
        }

        td.lbl {
            width: 38%;

            font-weight: 500;
            color: #000000;
        }

        td.val {
            width: 62%;

            text-align: right;

            font-weight: 700;
            color: #000000;

            word-break: break-word;
        }

        /* ========================================
           TOTAL PEMBAYARAN
           ======================================== */

        .total td {
            padding-top: 4px;
            padding-bottom: 4px;

            font-size: 13px;
            font-weight: 800;
        }

        .total td.val {
            font-size: 15px;
            font-weight: 800;
        }

        /* ========================================
           FOOTER
           ======================================== */

        .foot {
            text-align: center;

            color: #000000;

            font-size: 10px;
            font-weight: 500;

            line-height: 1.3;
        }

        .foot .timestamp {
            font-weight: 700;
            font-size: 9px;

            margin-top: 2px;
        }

        /* ========================================
           PRINT
           ======================================== */

        @media print {

            @page {
                size: auto;
                margin: 0;
            }

            .toolbar {
                display: none !important;
            }

            body {
                background: #ffffff !important;
                color: #000000 !important;

                padding: 0 !important;

                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .page {
                border: 0 !important;

                margin: 0 auto !important;

                padding: 4mm 4mm !important;

                width: 100% !important;
                max-width: 80mm !important;

                box-shadow: none !important;
            }

            .sep {
                border-top: 1px dashed #000000 !important;
            }
        }
    </style>
</head>

<body>

    {{-- =========================================
         TOOLBAR
         ========================================= --}}

    <div class="toolbar">

        <button type="button" onclick="window.print()">
            Cetak Kwitansi
        </button>

        <a href="{{ route('payments.show', $payment->id) }}">
            Detail Pembayaran
        </a>

    </div>


    {{-- =========================================
         KWITANSI
         ========================================= --}}

    <div class="page">

        {{-- =====================================
             HEADER
             ===================================== --}}

        <div class="head">

            <div>

                <div class="brand">
                    KWITANSI PEMBAYARAN
                </div>

                <div class="sub-brand">
                    Whusnet Internet Service Provider
                </div>

                <div class="desc">
                    Grand Viola Townhouse No.3 Purbosuman Kab.Ponorogo
                </div>

            </div>

        </div>


        <div class="sep"></div>


        {{-- =====================================
             INFORMASI PEMBAYARAN
             ===================================== --}}

        <table>

            <tr>
                <td class="lbl">
                    No. Kwitansi
                </td>

                <td class="val">
                    {{ $kwitansi['nomor'] }}
                </td>
            </tr>

            <tr>
                <td class="lbl">
                    Tanggal
                </td>

                <td class="val">
                    {{ $kwitansi['tanggal_bayar'] }}
                </td>
            </tr>

            <tr>
                <td class="lbl">
                    Metode
                </td>

                <td class="val">
                    {{ $kwitansi['metode'] }}
                </td>
            </tr>

            <tr>
                <td class="lbl">
                    Status
                </td>

                <td class="val">
                    {{ $kwitansi['status'] }}
                </td>
            </tr>
            @if($kwitansi['keterangan_cicilan'])

                <tr>
                    <td class="lbl">
                        Keterangan
                    </td>

                    <td class="val">
                        {{ $kwitansi['keterangan_cicilan'] }}
                    </td>
                </tr>
            @endif
        </table>

        <div class="sep"></div>
        {{-- =====================================
             INFORMASI PELANGGAN
             ===================================== --}}
        <table>
            <tr>
                <td class="lbl">
                    Pelanggan
                </td>
                <td class="val">
                    {{ $kwitansi['pelanggan']['nama'] }}
                </td>
            </tr>

            <tr>
                <td class="lbl">
                    CID
                </td>

                <td class="val">
                    {{ $kwitansi['pelanggan']['cid'] }}
                </td>
            </tr>

            <tr>
                <td class="lbl">
                    No. HP
                </td>

                <td class="val">
                    {{ $kwitansi['pelanggan']['hp'] }}
                </td>
            </tr>

            <tr>
                <td class="lbl">
                    Alamat
                </td>

                <td class="val">
                    {!! implode(
                        '<br>',
                        array_map('e', $kwitansi['pelanggan']['alamat_baris'])
                    ) !!}
                </td>
            </tr>

            <tr>
                <td class="lbl">
                    No. Tagihan
                </td>

                <td class="val">
                    {{ $kwitansi['invoice']['nomor'] }}
                </td>
            </tr>

            @if($kwitansi['invoice']['ada'])

                <tr>
                    <td class="lbl">
                        Periode
                    </td>

                    <td class="val">
                        {{ $kwitansi['invoice']['periode'] }}
                    </td>
                </tr>

                <tr>
                    <td class="lbl">
                        Paket
                    </td>

                    <td class="val">
                        {{ $kwitansi['invoice']['paket'] }}
                    </td>
                </tr>

            @endif

        </table>


        <div class="sep"></div>


        {{-- =====================================
             INFORMASI NOMINAL
             ===================================== --}}

        <table>

            @if($kwitansi['invoice']['ada'])

                <tr>
                    <td class="lbl">
                        Total Tagihan
                    </td>

                    <td class="val">
                        {{ $kwitansi['invoice']['total'] }}
                    </td>
                </tr>

            @endif


            <tr class="total">

                <td class="lbl">
                    DIBAYAR
                </td>

                <td class="val">
                    {{ $kwitansi['dibayar'] }}
                </td>

            </tr>


            @if($kwitansi['lebih_bayar'])

                <tr>

                    <td class="lbl">
                        Lebih Bayar
                    </td>

                    <td class="val">
                        {{ $kwitansi['lebih_bayar'] }}
                    </td>

                </tr>

            @endif


            @if($kwitansi['invoice']['ada'])

                <tr>

                    <td class="lbl">
                        Sisa Tagihan
                    </td>

                    <td class="val">
                        {{ $kwitansi['invoice']['sisa'] }}

                        {{ $kwitansi['invoice']['lunas'] ? ' (Lunas)' : '' }}
                    </td>

                </tr>

            @endif

        </table>


        <div class="sep"></div>


        {{-- =====================================
             INFORMASI PENAGIHAN
             ===================================== --}}

        <table>

            <tr>

                <td class="lbl">
                    Diterima oleh
                </td>

                <td class="val">
                    {{ $kwitansi['penerima'] }}
                </td>

            </tr>
            @if($kwitansi['catatan'])
                <tr>
                    <td class="lbl">
                        Catatan
                    </td>
                    <td class="val">
                        {{ $kwitansi['catatan'] }}
                    </td>
                </tr>
            @endif
            <tr>

                <td class="lbl">
                    Area
                </td>

                <td class="val">
                    {{ $kwitansi['pop'] }}
                </td>

            </tr>

        </table>


        <div class="sep"></div>


        {{-- =====================================
             FOOTER
             ===================================== --}}

        <div class="foot">

            Kwitansi sah tanpa tanda tangan.<br>

            Simpan sebagai bukti pembayaran.

            <div class="timestamp">
                Dicetak: {{ $kwitansi['dicetak'] }}
            </div>

        </div>

    </div>

</body>

</html>