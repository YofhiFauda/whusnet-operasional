{{--
    Surat Jalan Transfer Pusat→Cabang — mengikuti template resmi
    docs/plan/warehouse/laporan/Surat_Jalan_Transfer_Gudang_WHUSNET.{docx,md,png}
    apa adanya (banner WHUSNET, meta 2x2, blok Pengirim/Penerima, tabel
    barang, catatan, lembar pengesahan).

    TANPA HARGA — `$lines` dikirim controller TANPA kolom
    `unit_price_snapshot` sama sekali (lihat
    WarehouseTransferController::suratJalan()), bukan cuma disembunyikan di
    sini (docs/plan/warehouse/rancangan-invoice-surat-jalan-transfer.md §4.2).

    TTD Pengirim & Penerima MURNI nama + garis kosong — diisi tangan di
    kertas, TIDAK PERNAH masuk sistem (§5, §7 keputusan #2). Jangan tambah
    field upload TTD di sini, itu sudah diputuskan eksplisit ditolak.
--}}
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Surat Jalan — {{ $transfer->reference_number }}</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #18181b;
            margin: 0;
            padding: 0;
            background: #ffffff;
        }

        .a4 {
            width: 700px;
            margin: 0 auto;
            padding: 32px 0 48px;
        }

        .kop-header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }

        .kop-logo-td {
            width: 160px;
            vertical-align: middle;
            padding: 0;
        }

        .kop-logo {
            height: 56px;
            width: auto;
            display: block;
        }

        .kop-text-td {
            vertical-align: middle;
            padding-left: 12px;
        }

        .kop-company-name {
            font-size: 16px;
            font-weight: 700;
            color: #18181b;
            letter-spacing: 0.02em;
            line-height: 1.2;
            margin-bottom: 2px;
        }

        .kop-company-address {
            font-size: 10.5px;
            color: #3f3f46;
            line-height: 1.3;
            margin-bottom: 2px;
        }

        .kop-company-contact {
            font-size: 10.5px;
            color: #3f3f46;
            line-height: 1.3;
        }

        .kop-pipe {
            color: #71717a;
            padding: 0 4px;
        }

        .kop-divider-line {
            width: 100%;
            height: 3px;
            background-color: #18181b;
            margin-bottom: 16px;
        }

        .doc-title-container {
            text-align: center;
            margin-bottom: 18px;
        }

        .doc-title {
            font-size: 14px;
            font-weight: 700;
            color: #1e3a5f;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            border-bottom: 1.5px solid #1e3a5f;
            display: inline-block;
            padding-bottom: 2px;
        }

        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .meta td {
            padding: 3px 0;
            font-size: 12px;
            vertical-align: top;
            width: 25%;
        }

        .meta td.meta-label {
            font-weight: 700;
            width: 25%;
        }

        .meta td.meta-value {
            width: 25%;
        }

        .parties {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .parties th {
            background: #dbe6f3;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            padding: 6px 10px;
            border: 1px solid #b6c7dc;
        }

        .parties td {
            border: 1px solid #b6c7dc;
            padding: 10px;
            width: 50%;
            vertical-align: top;
            font-size: 12px;
            line-height: 1.5;
        }

        .party-name {
            font-weight: 700;
            margin-bottom: 2px;
        }

        .items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .items th {
            background: #dbe6f3;
            border: 1px solid #b6c7dc;
            font-size: 10.5px;
            font-weight: 700;
            padding: 6px 8px;
            text-align: center;
        }

        .items td {
            border: 1px solid #d4d4d8;
            padding: 7px 8px;
            font-size: 11.5px;
            vertical-align: top;
        }

        .items td.center {
            text-align: center;
        }

        .item-sub {
            font-size: 10px;
            color: #52525b;
            margin-top: 2px;
        }

        .notes {
            border: 1px solid #d4d4d8;
            padding: 12px 14px;
            font-size: 11px;
            color: #3f3f46;
            margin-bottom: 24px;
        }

        .notes-title {
            font-weight: 700;
            margin-bottom: 6px;
            color: #18181b;
        }

        .notes ol {
            margin: 0;
            padding-left: 16px;
        }

        .notes li {
            margin-bottom: 3px;
        }

        .signoff {
            width: 100%;
            border-collapse: collapse;
        }

        .signoff th {
            background: #dbe6f3;
            border: 1px solid #b6c7dc;
            padding: 6px;
            font-size: 11px;
            font-weight: 700;
            width: 50%;
        }

        .signoff td {
            border: 1px solid #b6c7dc;
            width: 50%;
            text-align: center;
            padding: 10px;
            vertical-align: bottom;
        }

        .signoff-space {
            height: 70px;
        }

        .signoff-name {
            font-weight: 700;
        }

        .signoff-date {
            font-size: 11px;
            color: #52525b;
            margin-top: 4px;
        }
    </style>
</head>

<body>
    @php
        $logoPath = public_path('images/kop-surat-logo.png');
        $logoData = file_exists($logoPath) ? base64_encode(file_get_contents($logoPath)) : null;
    @endphp

    <div class="a4">
        <table class="kop-header">
            <tr>
                <td class="kop-logo-td">
                    @if($logoData)
                        <img src="data:image/png;base64,{{ $logoData }}" class="kop-logo" alt="PT CONNEXA DIGITAL NETWORK">
                    @endif
                </td>
                <td class="kop-text-td">
                    <div class="kop-company-name">PT CONNEXA DIGITAL NETWORK</div>
                    <div class="kop-company-address">Pucangombo, Tegalombo, Pacitan, Jawa Timur</div>
                    <div class="kop-company-contact">info@connexa.net.id <span class="kop-pipe">│</span> 082240003434</div>
                </td>
            </tr>
        </table>
        <div class="kop-divider-line"></div>

        <div class="doc-title-container">
            <div class="doc-title">SURAT JALAN TRANSFER PERALATAN GUDANG</div>
        </div>

        <table class="meta">
            <tr>
                <td class="meta-label">No. Surat Jalan</td>
                <td class="meta-value">{{ $suratJalanNumber }}</td>
                <td class="meta-label">Tanggal</td>
                <td class="meta-value">{{ \App\Support\IndonesianDate::date($transfer->created_at) }}</td>
            </tr>
            <tr>
                <td class="meta-label">Referensi WO / Tiket</td>
                <td class="meta-value">{{ $transfer->reference_number }}</td>
                <td class="meta-label">Jenis Transfer</td>
                <td class="meta-value">Antar Gudang</td>
            </tr>
        </table>

        <table class="parties">
            <tr>
                <th>PENGIRIM (Gudang Pusat)</th>
                <th>PENERIMA (Gudang Cabang)</th>
            </tr>
            <tr>
                <td>
                    <div class="party-name">{{ $transfer->fromPop->name }}</div>
                    {{ $fromPopAddress }}<br>
                    Telp: {{ $transfer->fromPop->pic_phone ?? '-' }}<br>
                    PJ: {{ $transfer->fromPop->pic_name ?? '-' }}
                </td>
                <td>
                    <div class="party-name">{{ $transfer->toPop->name }}</div>
                    {{ $toPopAddress }}<br>
                    Telp: {{ $transfer->toPop->pic_phone ?? '-' }}<br>
                    PJ: {{ $transfer->toPop->pic_name ?? '-' }}
                </td>
            </tr>
        </table>

        <table class="items">
            <thead>
                <tr>
                    <th style="width:6%">No.</th>
                    <th style="width:14%">Kode Barang</th>
                    <th style="width:34%">Nama / Deskripsi Barang</th>
                    <th style="width:10%">Satuan</th>
                    <th style="width:10%">Qty Kirim</th>
                    <th style="width:10%">Qty Terima</th>
                    <th style="width:16%">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lines as $line)
                    @php
                        $qtyDecimals = fmod((float) $line->qty, 1) === 0.0 ? 0 : 2;
                        $snOrRoll = $line->serial->serial_number ?? $line->roll->roll_code ?? null;
                    @endphp
                    <tr>
                        <td class="center">{{ $loop->iteration }}</td>
                        <td>{{ $line->item->code }}</td>
                        <td>
                            {{ $line->item->name }}
                            @if($snOrRoll)
                                <div class="item-sub">SN/Kode: {{ $snOrRoll }}</div>
                            @endif
                            @if($line->lot_no)
                                <div class="item-sub">Lot: {{ $line->lot_no }}</div>
                            @endif
                        </td>
                        <td class="center">{{ $line->item->unit }}</td>
                        <td class="center">{{ number_format((float) $line->qty, $qtyDecimals, ',', '.') }}</td>
                        <td class="center"></td>
                        <td></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="notes">
            <div class="notes-title">Catatan / Instruksi Pengiriman:</div>
            <ol>
                <li>Surat jalan ini merupakan dokumen resmi transfer peralatan jaringan ISP antar gudang WHUSNET.</li>
                <li>Pihak Gudang Cabang wajib melakukan verifikasi, pemeriksaan fisik, dan penghitungan barang saat tiba.</li>
                <li>Apabila terdapat selisih atau kerusakan, harap dicatat pada kolom 'Keterangan' dan dikonfirmasi ke Gudang Pusat.</li>
                <li>Update status penerimaan barang wajib diinput ke sistem WHUSNET Operasional setelah serah terima selesai.</li>
            </ol>
        </div>

        <table class="signoff">
            <tr>
                <th>Pengirim (Gudang Pusat)</th>
                <th>Penerima (Gudang Cabang)</th>
            </tr>
            <tr>
                <td>
                    <div class="signoff-space"></div>
                    <div class="signoff-name">( {{ $transfer->createdBy?->name ?? '-' }} )</div>
                    <div class="signoff-date">Tgl: ____/____/{{ $transfer->created_at->format('Y') }}</div>
                </td>
                <td>
                    <div class="signoff-space"></div>
                    <div class="signoff-name">( {{ $transfer->toPop->pic_name ?? '-' }} )</div>
                    <div class="signoff-date">Tgl: ____/____/{{ $transfer->created_at->format('Y') }}</div>
                </td>
            </tr>
        </table>
    </div>
</body>

</html>
