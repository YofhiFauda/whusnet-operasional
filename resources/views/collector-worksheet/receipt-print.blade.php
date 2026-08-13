{{--
    Halaman cetak kwitansi. SENGAJA tidak memakai layout aplikasi — ini
    dokumen kertas, bukan halaman aplikasi: tak ada sidebar, tak ada topbar,
    tak ada skrip yang perlu dimuat dari mana pun.

    Dua penanda nomor dicetak bersamaan dan itu disengaja:
      · QR  — dibaca mesin saat kwitansi diupload kembali (jalur utama)
      · teks polos — dibaca OCR kalau QR sobek, dan dibaca MANUSIA kalau
        OCR pun gagal. Tanpa teks polos, kwitansi yang QR-nya rusak jadi
        kertas yang tak bisa dikenali siapa pun.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kwitansi — {{ $collector->name }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: ui-sans-serif, system-ui, sans-serif; margin: 0; padding: 16px; color: #0f172a; background: #fff; }
        .toolbar { margin-bottom: 16px; }
        .toolbar button { padding: 8px 18px; font-size: 13px; font-weight: 600; border: 1px solid #cbd5e1; border-radius: 8px; background: #0284c7; color: #fff; cursor: pointer; }
        .sheet { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
        .receipt { border: 1px dashed #94a3b8; border-radius: 8px; padding: 14px; page-break-inside: avoid; display: flex; gap: 14px; }
        .receipt .body { flex: 1; min-width: 0; }
        .brand { font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #64748b; }
        .title { font-size: 15px; font-weight: 700; margin: 2px 0 8px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        td { padding: 2px 0; vertical-align: top; }
        td.k { color: #64748b; width: 42%; }
        .amount { font-size: 16px; font-weight: 700; margin-top: 6px; }
        .num { font-family: ui-monospace, monospace; font-size: 12px; font-weight: 700; letter-spacing: .04em; }
        .qr { width: 96px; text-align: center; }
        .qr img { width: 96px; height: 96px; display: block; }
        .qr .num { margin-top: 4px; font-size: 9px; word-break: break-all; }
        .foot { margin-top: 8px; font-size: 9px; color: #94a3b8; line-height: 1.35; }
        @media print {
            /*
             * Lihat catatan sama di payments/receipt.blade.php: header/footer
             * bawaan browser (tanggal, judul, URL) hidup di kotak margin
             * halaman dan cuma bisa dimatikan dengan margin:0. Jarak ke tepi
             * kertas pindah ke padding body — bukan dihapus.
             */
            @page { margin: 0; }
            .toolbar { display: none; }
            body { padding: 10mm 8mm; }
            .receipt { border-color: #cbd5e1; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Cetak {{ $payments->count() }} Kwitansi</button>
    </div>

    <div class="sheet">
        {{-- Isi tiap kartu datang dari ReceiptPresenter — sama dengan struk
             thermal & lembar A4. Bentuknya saja yang berbeda (kartu + QR). --}}
        @foreach ($payments as $payment)
            @php($k = $kwitansiByPayment[$payment->id])
            <div class="receipt">
                <div class="body">
                    <div class="brand">Whusnet — Kwitansi Pembayaran</div>
                    <div class="title">{{ $k['pelanggan']['nama'] }}</div>

                    <table>
                        <tr>
                            <td class="k">Pelanggan</td>
                            <td>{{ $k['pelanggan']['cid'] }}</td>
                        </tr>
                        <tr>
                            <td class="k">Alamat</td>
                            {{-- Dipenggal di kecamatan supaya alamat panjang
                                 tidak melipat sembarangan di kolom sempit. --}}
                            <td>{!! implode('<br>', array_map('e', $k['pelanggan']['alamat_baris'])) !!}</td>
                        </tr>
                        <tr>
                            <td class="k">No. Tagihan</td>
                            <td>{{ $k['invoice']['nomor'] }}</td>
                        </tr>
                        <tr>
                            <td class="k">Periode</td>
                            <td>{{ $k['invoice']['periode'] }}</td>
                        </tr>
                        <tr>
                            <td class="k">Paket</td>
                            <td>{{ $k['invoice']['paket'] }}</td>
                        </tr>
                        <tr>
                            <td class="k">Tanggal Ditagih</td>
                            <td>{{ $k['tanggal_ditagih'] }}</td>
                        </tr>
                        <tr>
                            <td class="k">Metode</td>
                            <td>{{ $k['metode'] }}</td>
                        </tr>
                        @if($k['keterangan_cicilan'])
                        <tr>
                            <td class="k">Keterangan</td>
                            <td>{{ $k['keterangan_cicilan'] }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td class="k">Ditagih oleh</td>
                            <td>{{ $collector->name }}</td>
                        </tr>
                    </table>

                    <div class="amount">{{ $k['dibayar'] }}</div>
                    <div class="foot">
                        Simpan kwitansi ini sebagai bukti pembayaran.<br>
                        Sisa tagihan pada nota ini: {{ $k['invoice']['sisa'] }}{{ $k['invoice']['lunas'] ? ' (Lunas)' : '' }}
                        @if($k['lebih_bayar'])
                            <br>Lebih bayar: {{ $k['lebih_bayar'] }}
                        @endif
                    </div>
                </div>

                <div class="qr">
                    <img src="{{ $qrByPayment[$payment->id] }}" alt="QR {{ $payment->payment_number }}">
                    {{-- Nomor yang sama dicetak sebagai teks: ini yang dibaca
                         OCR saat QR gagal, dan yang dibaca manusia saat OCR
                         juga gagal. --}}
                    <div class="num">{{ $payment->payment_number }}</div>
                </div>
            </div>
        @endforeach
    </div>
</body>
</html>
