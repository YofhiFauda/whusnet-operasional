{{--
    Struk / kwitansi pembayaran
    DATA    : menggunakan struktur $kwitansi dari KODE 1
    TAMPILAN: menggunakan layout thermal dari KODE 2

    Standalone page, tanpa layouts.app.
    Dioptimalkan untuk printer thermal 80mm.

    `$isCustomerCopy` (2026-08-31, default false — dipertahankan buat
    PaymentController::receipt() staf apa adanya): salinan yang dilihat/
    diunduh PELANGGAN lewat Portal (PortalPaymentController::receiptPdf(),
    render sama persis lewat dompdf — BUKAN template React terpisah, biar
    kwitansi yang dilihat pelanggan gak pernah beda isi/tata letak dari yang
    dipegang staf). Saat true: toolbar disembunyikan (gak relevan buat PDF)
    dan baris "Diterima oleh"/"Catatan" ikut disembunyikan — itu data
    internal pegawai, sama alasannya `PaymentReceiptResource` membuang field
    itu dari JSON.
--}}
@php($isCustomerCopy = $isCustomerCopy ?? false)
{{--
    `$isPdf` (2026-09-01): HANYA true dari
    `PortalPaymentController::receiptPdf()` — BUKAN dari `receiptView()`
    (iframe HTML modal "Lihat Kwitansi", tetap pakai layout struk thermal
    di bawah) atau print thermal 80mm staf (`PaymentController::receipt()`).

    RIWAYAT (biar gak diulang lagi): tiga percobaan sebelumnya mekarin
    `.page` (struk thermal, 302px, didesain buat 80mm) di kertas foto 5x7in
    GAGAL smua — (1) `transform: scale()` bikin footer kepotong diam-diam
    krn dompdf hitung page-break dari ukuran PRE-transform; (2) reflow px
    asli + `.page{width:100%}` bikin kolom nilai tabel kepotong SATU
    KARAKTER TERAKHIR, konsisten di semua ukuran kertas (226pt–1000pt) —
    murni bug lebar-persentase dompdf, bukan soal sempit; (3) reflow px +
    lebar eksplisit AKHIRNYA kerja, tapi hasilnya tetap "struk kecil
    dibesarin paksa", bukan kwitansi yang enak dibaca.

    Solusi final: TEMPLATE TERPISAH `.a4` (bukan modifikasi `.page`) —
    layout invoice A4 biasa (mirip kwitansi Stripe/Anthropic: header kiri,
    identitas kanan, tabel item, ringkasan nominal rata kanan). Kertas A4
    (`setPaper('a4')` di controller) tanpa kalkulasi px manual kayak
    sebelumnya.
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
           PRINT (struk thermal / browser Ctrl+P)
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

        /* ========================================
           A4 (dompdf, kwitansi PDF pelanggan) —
           template TERPISAH dari struk thermal di
           atas, lihat docblock $isPdf. Layout invoice
           biasa: header, identitas dua kolom, tabel
           item, ringkasan nominal rata kanan, riwayat
           pembayaran. Sans-serif (bukan monospace
           struk) biar kebaca kayak invoice resmi.
           ======================================== */

        @if($isPdf)
            body {
                background: #ffffff;
                padding: 0;
            }
        @endif

        .a4 {
            width: 700px;
            margin: 0 auto;
            padding: 48px 0;

            font-family: Helvetica, Arial, sans-serif;
            font-size: 13px;
            line-height: 1.5;
            color: #18181b;
        }

        .a4-header {
            width: 100%;
            margin-bottom: 32px;
        }

        .a4-header td {
            vertical-align: top;
        }

        .a4-title {
            font-size: 26px;
            font-weight: 700;
        }

        .a4-brand {
            text-align: right;
        }

        .a4-brand-name {
            font-size: 14px;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .a4-brand-sub {
            font-size: 11px;
            color: #52525b;
            margin-top: 2px;
        }

        .a4-meta {
            width: 100%;
            margin-bottom: 28px;
        }

        .a4-meta td {
            padding: 1px 0;
            font-size: 13px;
            vertical-align: top;
        }

        .a4-meta td.a4-meta-label {
            font-weight: 700;
            padding-right: 16px;
            white-space: nowrap;
        }

        .a4-parties {
            width: 100%;
            margin-bottom: 28px;
        }

        .a4-parties td {
            width: 50%;
            vertical-align: top;
            font-size: 13px;
            padding-right: 24px;
            line-height: 1.45;
        }

        .a4-party-label {
            font-weight: 700;
            margin-bottom: 4px;
        }

        .a4-party-name {
            font-weight: 700;
        }

        .a4-amount-line {
            font-size: 17px;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .a4-items {
            width: 100%;
            border-collapse: collapse;
        }

        .a4-items th {
            text-align: left;

            font-size: 11px;
            font-weight: 700;
            color: #52525b;
            text-transform: uppercase;
            letter-spacing: .04em;

            padding: 0 0 8px;
            border-bottom: 1px solid #d4d4d8;
        }

        .a4-items td {
            font-size: 13px;
            padding: 12px 0;
            border-bottom: 1px solid #f4f4f5;
            vertical-align: top;
        }

        .a4-items th.num,
        .a4-items td.num {
            text-align: right;
        }

        .a4-item-sub {
            font-size: 11px;
            color: #52525b;
            margin-top: 2px;
        }

        .a4-totals {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0 32px;
        }

        .a4-totals td {
            padding: 4px 0;
            font-size: 13px;
            text-align: right;
        }

        .a4-totals td:first-child {
            text-align: left;
            color: #52525b;
        }

        .a4-totals tr.a4-total-strong td {
            font-weight: 700;
            color: #18181b;
            padding-top: 8px;
            border-top: 1px solid #d4d4d8;
        }

        .a4-section-title {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .a4-history {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 32px;
        }

        .a4-history th {
            text-align: left;

            font-size: 11px;
            font-weight: 700;
            color: #52525b;
            text-transform: uppercase;
            letter-spacing: .04em;

            padding: 0 0 8px;
            border-bottom: 1px solid #d4d4d8;
        }

        .a4-history td {
            font-size: 13px;
            padding: 10px 0;
        }

        .a4-history th.num,
        .a4-history td.num {
            text-align: right;
        }

        .a4-footer {
            font-size: 11px;
            color: #71717a;

            border-top: 1px solid #e4e4e7;
            padding-top: 16px;
        }
    </style>
</head>

<body>

    @if($isPdf)

        {{-- =========================================
             KWITANSI — LAYOUT A4
             ========================================= --}}

        <div class="a4">

            {{-- =====================================
                 HEADER
                 ===================================== --}}

            <table class="a4-header">
                <tr>
                    <td>
                        <div class="a4-title">Kwitansi</div>
                    </td>

                    <td class="a4-brand">
                        <div class="a4-brand-name">Whusnet</div>
                        <div class="a4-brand-sub">Internet Service Provider</div>
                    </td>
                </tr>
            </table>

            {{-- =====================================
                 META
                 ===================================== --}}

            <table class="a4-meta">
                <tr>
                    <td class="a4-meta-label">No. Kwitansi</td>
                    <td>{{ $kwitansi['nomor'] }}</td>
                </tr>
                <tr>
                    <td class="a4-meta-label">Tanggal Bayar</td>
                    <td>{{ $kwitansi['tanggal_bayar'] }}</td>
                </tr>
                <tr>
                    <td class="a4-meta-label">Metode</td>
                    <td>{{ $kwitansi['metode'] }}</td>
                </tr>
                <tr>
                    <td class="a4-meta-label">Status</td>
                    <td>{{ $kwitansi['status'] }}</td>
                </tr>
                @if($kwitansi['keterangan_cicilan'])
                    <tr>
                        <td class="a4-meta-label">Keterangan</td>
                        <td>{{ $kwitansi['keterangan_cicilan'] }}</td>
                    </tr>
                @endif
                <tr>
                    <td class="a4-meta-label">Area</td>
                    <td>{{ $kwitansi['pop'] }}</td>
                </tr>
            </table>

            {{-- =====================================
                 PIHAK (penerbit / pelanggan)
                 ===================================== --}}

            <table class="a4-parties">
                <tr>
                    <td>
                        <div class="a4-party-label">Diterbitkan oleh</div>
                        <div class="a4-party-name">Whusnet Internet Service Provider</div>
                        <div>Grand Viola Townhouse No.3 Purbosuman</div>
                        <div>Kab. Ponorogo</div>
                    </td>

                    <td>
                        <div class="a4-party-label">Ditagihkan kepada</div>
                        <div class="a4-party-name">{{ $kwitansi['pelanggan']['nama'] }}</div>
                        <div>CID {{ $kwitansi['pelanggan']['cid'] }}</div>
                        @foreach($kwitansi['pelanggan']['alamat_baris'] as $baris)
                            <div>{{ $baris }}</div>
                        @endforeach
                        <div>{{ $kwitansi['pelanggan']['hp'] }}</div>
                    </td>
                </tr>
            </table>

            {{-- =====================================
                 RINGKASAN
                 ===================================== --}}

            <div class="a4-amount-line">
                {{ $kwitansi['dibayar'] }} dibayar pada {{ $kwitansi['tanggal_bayar'] }}
            </div>

            <table class="a4-items">
                <thead>
                    <tr>
                        <th>Deskripsi</th>
                        <th class="num">Kuantitas</th>
                        <th class="num">Harga Satuan</th>
                        <th class="num">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            {{ $kwitansi['invoice']['ada'] ? $kwitansi['invoice']['paket'] : 'Pembayaran' }}
                            @if($kwitansi['invoice']['ada'])
                                <div class="a4-item-sub">
                                    No. Tagihan {{ $kwitansi['invoice']['nomor'] }} · Periode {{ $kwitansi['invoice']['periode'] }}
                                </div>
                            @endif
                        </td>
                        <td class="num">1</td>
                        <td class="num">{{ $kwitansi['invoice']['ada'] ? $kwitansi['invoice']['total'] : $kwitansi['dibayar'] }}</td>
                        <td class="num">{{ $kwitansi['dibayar'] }}</td>
                    </tr>
                </tbody>
            </table>

            <table class="a4-totals">
                @if($kwitansi['invoice']['ada'])
                    <tr>
                        <td>Total Tagihan</td>
                        <td>{{ $kwitansi['invoice']['total'] }}</td>
                    </tr>
                @endif

                <tr class="a4-total-strong">
                    <td>Dibayar</td>
                    <td>{{ $kwitansi['dibayar'] }}</td>
                </tr>

                @if($kwitansi['lebih_bayar'])
                    <tr>
                        <td>Lebih Bayar</td>
                        <td>{{ $kwitansi['lebih_bayar'] }}</td>
                    </tr>
                @endif

                @if($kwitansi['invoice']['ada'])
                    <tr>
                        <td>Sisa Tagihan</td>
                        <td>{{ $kwitansi['invoice']['sisa'] }}{{ $kwitansi['invoice']['lunas'] ? ' (Lunas)' : '' }}</td>
                    </tr>
                @endif
            </table>

            {{-- =====================================
                 RIWAYAT PEMBAYARAN
                 ===================================== --}}

            <div class="a4-section-title">Riwayat Pembayaran</div>

            <table class="a4-history">
                <thead>
                    <tr>
                        <th>Metode Pembayaran</th>
                        <th>Tanggal</th>
                        <th class="num">Jumlah Dibayar</th>
                        <th class="num">No. Kwitansi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>{{ $kwitansi['metode'] }}</td>
                        <td>{{ $kwitansi['tanggal_bayar'] }}</td>
                        <td class="num">{{ $kwitansi['dibayar'] }}</td>
                        <td class="num">{{ $kwitansi['nomor'] }}</td>
                    </tr>
                </tbody>
            </table>

            {{-- =====================================
                 FOOTER
                 ===================================== --}}

            <div class="a4-footer">
                Kwitansi sah tanpa tanda tangan. Simpan sebagai bukti pembayaran.<br>
                Dicetak: {{ $kwitansi['dicetak'] }}
            </div>

        </div>

    @else

        {{-- =========================================
             TOOLBAR
             ========================================= --}}

        @unless($isCustomerCopy)
        <div class="toolbar">

            <button type="button" onclick="window.print()">
                Cetak Kwitansi
            </button>

            <a href="{{ route('payments.show', $payment->id) }}">
                Detail Pembayaran
            </a>

        </div>
        @endunless


        {{-- =========================================
             KWITANSI — STRUK THERMAL
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

                @unless($isCustomerCopy)
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
                @endunless
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

    @endif

</body>

</html>
