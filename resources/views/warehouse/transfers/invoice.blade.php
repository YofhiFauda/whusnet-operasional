{{--
    Invoice Transfer Pusat→Cabang — mengikuti struktur template resmi
    docs/plan/warehouse/laporan/INVOICE.{docx,md,png} (header brand kiri
    atas, blok "Tagihan ke"/"Kirim ke", tabel Rincian Biaya Qty/Nama Barang/
    Harga Satuan/Diskon/Jumlah, Ringkasan Total Diskon/Subtotal/Pajak/Total,
    TTD kanan bawah) — field "Pelanggan"/"ID Pelanggan"/"Penjual" template
    aslinya buat penjualan ke pelanggan, di sini diadaptasi ke konteks
    internal Pusat→Cabang (Cabang tujuan yang "ditagih" nilai barangnya).
    Brand & alamat pengirim diambil dari POP Pusat (`fromPop`), BUKAN
    dihardcode — konsisten dengan Surat Jalan.

    `$lines` di sini SUDAH DIGABUNG per item+harga di controller
    (`WarehouseTransferController::invoice()`) — SENGAJA TANPA kolom kode
    barang/SN/lot (keputusan user 2026-09-17: daftar SN per-unit udah ada di
    Surat Jalan, di Invoice cuma perlu "hasil akhir" per barang biar gak
    redundan). Kalau butuh SN spesifik, itu urusan Surat Jalan.

    Diskon & Pajak SELALU Rp 0 (warehouse gak py konsep itu) — kolom/baris
    tetap ditampilkan demi kesetiaan ke template resmi, bukan dihapus.

    TTD Kepala Gudang MURNI nama+jabatan (hardcode config('warehouse.kepala_gudang'))
    + garis kosong — SAMA seperti Surat Jalan, TIDAK ADA gambar/upload TTD
    di dokumen manapun (koreksi user 2026-09-17).
--}}
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Invoice — {{ $transfer->reference_number }}</title>

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
            padding: 40px 0 48px;
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

        .invoice-title {
            font-size: 15px;
            font-weight: 700;
            color: #1e3a5f;
            border-bottom: 2px solid #1e3a5f;
            padding-bottom: 4px;
            margin-bottom: 16px;
        }

        .info {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .info th {
            background: #dbe6f3;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            padding: 6px 10px;
            border: 1px solid #b6c7dc;
            width: 50%;
        }

        .info td {
            border: 1px solid #b6c7dc;
            padding: 10px;
            width: 50%;
            vertical-align: top;
            font-size: 11.5px;
        }

        .info td .row {
            padding: 1px 0;
        }

        .info td .label {
            font-weight: 700;
            display: inline-block;
            width: 95px;
        }

        .items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
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

        .items th.num,
        .items td.num {
            text-align: right;
        }

        .items td.center {
            text-align: center;
        }

        .totals {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 40px;
        }

        .totals td {
            padding: 5px 8px;
            font-size: 11.5px;
            text-align: right;
            border: 1px solid #d4d4d8;
        }

        .totals td:first-child {
            text-align: left;
            color: #52525b;
            width: 78%;
        }

        .totals tr.total-strong td {
            font-weight: 700;
            font-size: 13px;
            color: #18181b;
            background: #dbe6f3;
        }

        .signoff {
            width: 100%;
        }

        .signoff td {
            width: 50%;
        }

        .signoff-block {
            width: 240px;
            margin-left: auto;
            text-align: center;
        }

        .signoff-place {
            text-align: right;
            font-size: 11.5px;
            margin-bottom: 8px;
        }

        .signoff-label {
            font-size: 11.5px;
        }

        .signoff-space {
            height: 60px;
        }

        .signoff-name {
            font-weight: 700;
            border-top: 1px solid #18181b;
            padding-top: 6px;
        }

        .signoff-title {
            font-size: 10.5px;
            color: #52525b;
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

        <div class="invoice-title">INVOICE #{{ $transfer->reference_number }}</div>

        <table class="info">
            <tr>
                <th>Tagihan ke</th>
                <th>Kirim ke</th>
            </tr>
            <tr>
                <td>
                    <div class="row"><span class="label">Cabang</span>{{ $transfer->toPop->name }}</div>
                    <div class="row"><span class="label">Kode Cabang</span>{{ $transfer->toPop->code }}</div>
                    <div class="row"><span class="label">Tanggal</span>{{ \App\Support\IndonesianDate::date($transfer->created_at) }}</div>
                </td>
                <td>
                    <div class="row"><span class="label">Penerima</span>{{ $transfer->toPop->pic_name ?? '-' }}</div>
                    <div class="row"><span class="label">Pengiriman</span>Internal Antar Gudang</div>
                    <div class="row"><span class="label">No. Surat Jalan</span>{{ $suratJalanNumber }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="row"><span class="label">Jatuh Tempo</span>-</div>
                    <div class="row"><span class="label">Penjual</span>PT CONNEXA DIGITAL NETWORK</div>
                    <div class="row"><span class="label">Pembayaran</span>Internal (Non-Kas)</div>
                </td>
                <td>
                    <div class="row"><span class="label">Metode</span>Transfer Gudang Pusat → Cabang</div>
                </td>
            </tr>
        </table>

        <table class="items">
            <thead>
                <tr>
                    <th style="width:10%">Qty</th>
                    <th style="width:40%">Nama Barang</th>
                    <th style="width:17%">Harga Satuan</th>
                    <th style="width:13%">Diskon</th>
                    <th style="width:20%">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lines as $line)
                    @php
                        $qtyDecimals = fmod((float) $line->qty, 1) === 0.0 ? 0 : 2;
                        $subtotal = (float) $line->qty * (float) $line->unit_price_snapshot;
                    @endphp
                    <tr>
                        <td class="center">{{ number_format((float) $line->qty, $qtyDecimals, ',', '.') }} {{ $line->unit }}</td>
                        <td>{{ $line->item->name }}</td>
                        <td class="num">{{ \App\Helpers\FormatHelper::rupiah($line->unit_price_snapshot) }}</td>
                        <td class="num">{{ \App\Helpers\FormatHelper::rupiah(0) }}</td>
                        <td class="num">{{ \App\Helpers\FormatHelper::rupiah($subtotal) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totals">
            <tr>
                <td>Total Diskon</td>
                <td>{{ \App\Helpers\FormatHelper::rupiah(0) }}</td>
            </tr>
            <tr>
                <td>Subtotal</td>
                <td>{{ \App\Helpers\FormatHelper::rupiah($total) }}</td>
            </tr>
            <tr>
                <td>Pajak</td>
                <td>{{ \App\Helpers\FormatHelper::rupiah(0) }}</td>
            </tr>
            <tr class="total-strong">
                <td>Total</td>
                <td>{{ \App\Helpers\FormatHelper::rupiah($total) }}</td>
            </tr>
        </table>

        <table class="signoff">
            <tr>
                <td></td>
                <td>
                    <div class="signoff-block">
                        <div class="signoff-place">{{ $transfer->fromPop->city ?? 'Ponorogo' }}, {{ \App\Support\IndonesianDate::date($transfer->created_at) }}</div>
                        <div class="signoff-label">Mengetahui,</div>
                        <div class="signoff-space"></div>
                        <div class="signoff-name">{{ $kepalaGudang['name'] }}</div>
                        <div class="signoff-title">{{ $kepalaGudang['title'] }}</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>
</body>

</html>
