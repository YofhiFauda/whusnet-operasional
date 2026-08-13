{{--
    Struk / kwitansi pembayaran — halaman berdiri sendiri (TANPA layouts.app).
    Sengaja tidak memakai layout aplikasi: struk dibuka di tab baru lalu langsung
    dicetak, jadi sidebar/topbar cuma jadi sampah di kertas. Lebar dikunci 80mm
    supaya cocok untuk printer thermal kasir POP, tapi tetap terbaca di A4.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Struk Pembayaran {{ $payment->payment_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 16px;
            background: #f1f5f9;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 12px;
            color: #0f172a;
        }
        .struk {
            width: 80mm;
            max-width: 100%;
            margin: 0 auto;
            background: #fff;
            padding: 14px 16px 18px;
            border: 1px solid #e2e8f0;
        }
        .center { text-align: center; }
        .brand { font-size: 15px; font-weight: 700; letter-spacing: .05em; }
        .muted { color: #64748b; }
        .sep { border-top: 1px dashed #94a3b8; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 2px 0; vertical-align: top; }
        td.val { text-align: right; font-weight: 600; }
        .total td { padding-top: 6px; font-size: 14px; font-weight: 700; }
        .actions { width: 80mm; max-width: 100%; margin: 12px auto 0; text-align: center; }
        .actions button, .actions a {
            display: inline-block;
            padding: 8px 14px;
            font: inherit;
            font-weight: 700;
            border-radius: 6px;
            border: 1px solid #0284c7;
            background: #0284c7;
            color: #fff;
            cursor: pointer;
            text-decoration: none;
        }
        .actions a { background: #fff; color: #0f172a; border-color: #cbd5e1; margin-left: 6px; }
        @media print {
            /*
             * margin:0 pada @page = satu-satunya cara mematikan header/footer
             * bawaan browser (tanggal, judul dokumen, URL localhost:8000/...).
             * Teks itu hidup di KOTAK MARGIN halaman, bukan di dokumen kita —
             * tidak ada selector yang bisa menyembunyikannya. Nol-kan marginnya,
             * kotaknya ikut hilang. Jarak ke tepi kertas dipindah ke padding
             * elemen di bawah ini; menghapusnya membuat struk mepet tepi dan
             * terpotong printer.
             */
            @page { margin: 0; }
            body { background: #fff; padding: 0; }
            .struk { border: 0; padding: 6mm 5mm; width: auto; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    {{-- Isi struk datang dari ReceiptPresenter — sama persis dengan lembar A4
         di detail pembayaran dan kartu kolektor. Yang berbeda cuma bentuknya
         (80mm, monospace). Menambah field langsung di sini bikin ketiga
         cetakan menyimpang lagi; tambahkan di presenter. --}}
    <div class="struk">
        <div class="center">
            <div class="brand">WHUSNET</div>
            <div class="muted">{{ $kwitansi['pop'] }}</div>
            <div class="muted">STRUK PEMBAYARAN</div>
        </div>

        <div class="sep"></div>

        <table>
            <tr>
                <td class="muted">No. Struk</td>
                <td class="val">{{ $kwitansi['nomor'] }}</td>
            </tr>
            <tr>
                <td class="muted">Tanggal</td>
                <td class="val">{{ $kwitansi['tanggal_bayar'] }}</td>
            </tr>
            <tr>
                <td class="muted">Metode</td>
                <td class="val">{{ $kwitansi['metode'] }}</td>
            </tr>
            <tr>
                <td class="muted">Status</td>
                <td class="val">{{ $kwitansi['status'] }}</td>
            </tr>
            @if($kwitansi['keterangan_cicilan'])
            <tr>
                <td class="muted">Keterangan</td>
                <td class="val">{{ $kwitansi['keterangan_cicilan'] }}</td>
            </tr>
            @endif
        </table>

        <div class="sep"></div>

        <table>
            <tr>
                <td class="muted">Pelanggan</td>
                <td class="val">{{ $kwitansi['pelanggan']['nama'] }}</td>
            </tr>
            <tr>
                <td class="muted">CID</td>
                <td class="val">{{ $kwitansi['pelanggan']['cid'] }}</td>
            </tr>
            <tr>
                <td class="muted">No. HP</td>
                <td class="val">{{ $kwitansi['pelanggan']['hp'] }}</td>
            </tr>
            <tr>
                <td class="muted">Alamat</td>
                {{-- Penggalan yang sama dengan kartu kolektor & lembar A4 —
                     80mm sempit, lipatan otomatis jatuh di tempat acak. --}}
                <td class="val">{!! implode('<br>', array_map('e', $kwitansi['pelanggan']['alamat_baris'])) !!}</td>
            </tr>
            <tr>
                <td class="muted">No. Tagihan</td>
                <td class="val">{{ $kwitansi['invoice']['nomor'] }}</td>
            </tr>
            @if($kwitansi['invoice']['ada'])
            <tr>
                <td class="muted">Periode</td>
                <td class="val">{{ $kwitansi['invoice']['periode'] }}</td>
            </tr>
            <tr>
                <td class="muted">Paket</td>
                <td class="val">{{ $kwitansi['invoice']['paket'] }}</td>
            </tr>
            @endif
        </table>

        <div class="sep"></div>

        <table>
            @if($kwitansi['invoice']['ada'])
            <tr>
                <td class="muted">Total Tagihan</td>
                <td class="val">{{ $kwitansi['invoice']['total'] }}</td>
            </tr>
            @endif
            <tr class="total">
                <td>DIBAYAR</td>
                <td class="val">{{ $kwitansi['dibayar'] }}</td>
            </tr>
            @if($kwitansi['lebih_bayar'])
            <tr>
                <td class="muted">Lebih Bayar</td>
                <td class="val">{{ $kwitansi['lebih_bayar'] }}</td>
            </tr>
            @endif
            @if($kwitansi['invoice']['ada'])
            <tr>
                <td class="muted">Sisa Tagihan</td>
                <td class="val">{{ $kwitansi['invoice']['sisa'] }}{{ $kwitansi['invoice']['lunas'] ? ' (Lunas)' : '' }}</td>
            </tr>
            @endif
        </table>

        <div class="sep"></div>

        <table>
            <tr>
                <td class="muted">Diterima oleh</td>
                <td class="val">{{ $kwitansi['penerima'] }}</td>
            </tr>
            <tr>
                <td class="muted">Ditagih oleh</td>
                <td class="val">{{ $kwitansi['penagih'] }}</td>
            </tr>
            @if($kwitansi['catatan'])
            <tr>
                <td class="muted">Catatan</td>
                <td class="val">{{ $kwitansi['catatan'] }}</td>
            </tr>
            @endif
        </table>

        <div class="sep"></div>

        <div class="center muted">
            Struk sah tanpa tanda tangan.<br>
            Dicetak {{ $kwitansi['dicetak'] }}
        </div>
    </div>

    <div class="actions">
        <button type="button" onclick="window.print()">Cetak Struk</button>
        <a href="{{ route('payments.show', $payment->id) }}">Detail Pembayaran</a>
    </div>
</body>
</html>
