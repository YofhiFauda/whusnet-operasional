{{--
    Halaman cetak kwitansi kolektor - Ditingkatkan khusus Cetak Thermal.
    Layout 1-kolom presisi dengan tinggi dinamis roll paper thermal.
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
            font-family: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, Consolas, 'Courier New', monospace; 
            margin: 0; 
            padding: 16px; 
            background: #f1f5f9; 
            color: #000000; 
            font-size: 11px; 
            line-height: 1.35;
            -webkit-font-smoothing: antialiased;
        }

        .toolbar { 
            max-width: 302px; 
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

        .page:not(:last-child) { 
            page-break-after: always; 
        }

        /* HEADER */
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

        .muted { 
            color: #000000; 
            font-weight: 500;
        }

        .desc { 
            font-size: 10px; 
            font-weight: 500;
            color: #000000; 
            line-height: 1.25;
        }

        .qr { 
            text-align: center; 
            flex-shrink: 0; 
            margin-top: 4px;
        }

        .qr img { 
            width: 64px; 
            height: 64px; 
            display: block; 
            margin: 0 auto; 
        }

        .qr .num { 
            margin-top: 3px; 
            font-size: 9px; 
            font-weight: 700; 
            letter-spacing: .03em; 
            word-break: break-all; 
            max-width: 80px; 
        }

        .sep { 
            border-top: 1px dashed #000000; 
            margin: 8px 0; 
        }

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
            font-weight: 500;
            color: #000000;
            width: 38%;
        }

        td.val { 
            text-align: right; 
            font-weight: 700; 
            color: #000000;
            word-break: break-word;
        }

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

        /* PRINT MEDIA rules optimized for thermal paper */
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
                max-width: 80mm !important; /* standar kertas thermal 80mm */
                box-shadow: none !important;
            }

            .sep {
                border-top: 1px dashed #000000 !important;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Cetak {{ $payments->count() }} Kwitansi</button>
    </div>

    @foreach ($payments as $payment)
        @php($k = $kwitansiByPayment[$payment->id])
        <div class="page">
            <div class="head">
                <div>
                    <div class="brand">KWITANSI PEMBAYARAN</div>
                    <div class="sub-brand">Whusnet Internet Service Provider</div>
                    <div class="desc">Grand Viola Townhouse No.3 Purbosuman Kab.Ponorogo</div>
                </div>
            </div>

            <div class="sep"></div>

            <table>
                <tr>
                    <td class="lbl">No. Kwitansi</td>
                    <td class="val">{{ $k['nomor'] }}</td>
                </tr>
                <tr>
                    <td class="lbl">Tgl Ditagih</td>
                    <td class="val">{{ $k['tanggal_ditagih'] }}</td>
                </tr>
                <tr>
                    <td class="lbl">Metode</td>
                    <td class="val">{{ $k['metode'] }}</td>
                </tr>
                <tr>
                    <td class="lbl">Status</td>
                    <td class="val">{{ $k['status'] }}</td>
                </tr>
                @if ($k['keterangan_cicilan'])
                    <tr>
                        <td class="lbl">Keterangan</td>
                        <td class="val">{{ $k['keterangan_cicilan'] }}</td>
                    </tr>
                @endif
            </table>

            <div class="sep"></div>

            <table>
                <tr>
                    <td class="lbl">Pelanggan</td>
                    <td class="val">{{ $k['pelanggan']['nama'] }}</td>
                </tr>
                <tr>
                    <td class="lbl">CID</td>
                    <td class="val">{{ $k['pelanggan']['cid'] }}</td>
                </tr>
                <tr>
                    <td class="lbl">No. HP</td>
                    <td class="val">{{ $k['pelanggan']['hp'] }}</td>
                </tr>
                <tr>
                    <td class="lbl">Alamat</td>
                    <td class="val">{!! implode('<br>', array_map('e', $k['pelanggan']['alamat_baris'])) !!}</td>
                </tr>
                <tr>
                    <td class="lbl">No. Tagihan</td>
                    <td class="val">{{ $k['invoice']['nomor'] }}</td>
                </tr>
                @if ($k['invoice']['ada'])
                    <tr>
                        <td class="lbl">Periode</td>
                        <td class="val">{{ $k['invoice']['periode'] }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Paket</td>
                        <td class="val">{{ $k['invoice']['paket'] }}</td>
                    </tr>
                @endif
            </table>

            <div class="sep"></div>

            <table>
                @if ($k['invoice']['ada'])
                    <tr>
                        <td class="lbl">Total Tagihan</td>
                        <td class="val">{{ $k['invoice']['total'] }}</td>
                    </tr>
                @endif
                <tr class="total">
                    <td class="lbl">DIBAYAR</td>
                    <td class="val">{{ $k['dibayar'] }}</td>
                </tr>
                @if ($k['lebih_bayar'])
                    <tr>
                        <td class="lbl">Lebih Bayar</td>
                        <td class="val">{{ $k['lebih_bayar'] }}</td>
                    </tr>
                @endif
                @if ($k['invoice']['ada'])
                    <tr>
                        <td class="lbl">Sisa Tagihan</td>
                        <td class="val">{{ $k['invoice']['sisa'] }}{{ $k['invoice']['lunas'] ? ' (Lunas)' : '' }}</td>
                    </tr>
                @endif
            </table>

            <div class="sep"></div>

            <table>
                <tr>
                    <td class="lbl">Ditagih oleh</td>
                    <td class="val">{{ $collector->name }}</td>
                </tr>
                @if (! empty($k['catatan']))
                    <tr>
                        <td class="lbl">Catatan</td>
                        <td class="val">{{ $k['catatan'] }}</td>
                    </tr>
                @endif
                <tr>
                    <td class="lbl">Area</td>
                    <td class="val">{{ $k['pop'] }}</td>
                </tr>
            </table>

            <div class="sep"></div>

            <div class="foot">
                Kwitansi sah tanpa tanda tangan.<br>
                Simpan sebagai bukti pembayaran.<br>
                <div class="timestamp">Dicetak: {{ $k['dicetak'] }}</div>
            </div>
        </div>
    @endforeach
</body>
</html>