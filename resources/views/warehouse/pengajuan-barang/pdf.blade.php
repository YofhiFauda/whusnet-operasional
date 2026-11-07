{{--
    Surat Pengajuan Barang — mengikuti LAYOUT contoh dokumen lama
    docs/plan/warehouse/Surat_Pengajuan_Barang_WHUSNET(POP Bandar).docx
    SEDEKAT MUNGKIN (header titik dua rata, tabel barang garis hitam polos
    tanpa shading, blok tanda tangan polos tanpa kotak — bukan cuma "struktur
    isi" kayak revisi sebelumnya; dikoreksi 2026-11-07 setelah user kirim
    screenshot dokumen asli dan bilang "masih jauh dari design") — TAPI KOP
    CONNEXA (reuse partial existing sama Surat Jalan/Invoice Transfer),
    BUKAN "CV WHUSNET BERTIGA KREATIF" dari contoh dokumen lama (instruksi
    user eksplisit 2026-11-06: "KOP CONNEXA").

    Tanda tangan "Disetujui" MURNI nama FOP yang BENERAN approve
    (`$pengajuan->decidedBy`) — kosong (garis doang) sampai diputuskan,
    BUKAN nama statis "Ausya Rosyid" kayak contoh dokumen (itu contoh
    instance lama, bukan hardcode permanen — lihat CLAUDE.md RBAC).

    Baris tanggal+kota di atas blok tanda tangan pakai "Pacitan" statis
    (kota kantor pusat di KOP, bukan kota POP pengajuan) — sama konvensi
    dokumen asli, surat ditandatangani di kantor pusat terlepas dari cabang
    mana yang mengajukan.
--}}
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Surat Pengajuan Barang — {{ $pengajuan->nomor_request }}</title>

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
            table-layout: fixed;
        }

        .kop-logo-td {
            width: 220px;
            vertical-align: middle;
            padding: 0;
            text-align: left;
        }

        .kop-logo {
            height: 84px;
            width: auto;
            display: block;
        }

        .kop-text-td {
            vertical-align: middle;
            text-align: center;
            padding: 0 4px;
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

        .kop-spacer-td {
            width: 220px;
            padding: 0;
        }

        .kop-divider-line {
            width: 100%;
            height: 3px;
            background-color: #18181b;
            margin-bottom: 16px;
        }

        .doc-title-container {
            text-align: center;
            margin-bottom: 22px;
        }

        .doc-title {
            font-size: 14px;
            font-weight: 700;
            color: #18181b;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .meta td {
            padding: 2.5px 0;
            font-size: 12px;
            vertical-align: top;
        }

        .meta td.meta-label {
            width: 150px;
            white-space: nowrap;
        }

        .meta td.meta-colon {
            width: 14px;
            text-align: center;
        }

        .items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .items th {
            border: 1px solid #18181b;
            font-size: 10.5px;
            font-weight: 700;
            padding: 6px 8px;
            text-align: center;
        }

        .items td {
            border: 1px solid #18181b;
            padding: 7px 8px;
            font-size: 11.5px;
            vertical-align: top;
        }

        .items td.center {
            text-align: center;
        }

        .signoff-date-line {
            text-align: right;
            margin-top: 30px;
            margin-bottom: 20px;
            font-size: 12px;
        }

        .signoff {
            width: 100%;
            border-collapse: collapse;
        }

        .signoff td {
            width: 50%;
            text-align: center;
            vertical-align: top;
            padding: 0;
        }

        .signoff-space {
            height: 60px;
        }

        .signoff-name {
            font-weight: 400;
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
                <td class="kop-spacer-td"></td>
            </tr>
        </table>
        <div class="kop-divider-line"></div>

        <div class="doc-title-container">
            <div class="doc-title">SURAT PENGAJUAN BARANG</div>
        </div>

        <table class="meta">
            <tr>
                <td class="meta-label">No. Request</td>
                <td class="meta-colon">:</td>
                <td>{{ $pengajuan->nomor_request }}</td>
            </tr>
            <tr>
                <td class="meta-label">Tanggal</td>
                <td class="meta-colon">:</td>
                <td>{{ \App\Support\IndonesianDate::date($pengajuan->created_at) }}</td>
            </tr>
            <tr>
                <td class="meta-label">Nama Pengaju</td>
                <td class="meta-colon">:</td>
                <td>{{ $pengajuan->pengaju->name }}</td>
            </tr>
            <tr>
                <td class="meta-label">Nama Kegiatan</td>
                <td class="meta-colon">:</td>
                <td>{{ $pengajuan->nama_kegiatan }}</td>
            </tr>
            <tr>
                <td class="meta-label">OLT/Cabang/POP</td>
                <td class="meta-colon">:</td>
                <td>{{ $pengajuan->pop->name }}</td>
            </tr>
        </table>

        <table class="items">
            <thead>
                <tr>
                    <th style="width:6%">No.</th>
                    <th style="width:24%">Nama Barang</th>
                    <th style="width:20%">Spesifikasi</th>
                    <th style="width:10%">Jumlah</th>
                    <th style="width:10%">Satuan</th>
                    <th style="width:30%">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pengajuan->items as $item)
                <tr>
                    <td class="center">{{ $loop->iteration }}</td>
                    <td>{{ $item->nama_barang }}</td>
                    <td>{{ $item->spesifikasi ?? '-' }}</td>
                    <td class="center">{{ rtrim(rtrim(number_format((float) $item->jumlah, 2, ',', '.'), '0'), ',') }}</td>
                    <td class="center">{{ $item->satuan }}</td>
                    <td>
                        @if($item->keterangan_link)
                        <a href="{{ $item->keterangan_link }}">{{ $item->keterangan_link }}</a>
                        @else
                        -
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="signoff-date-line">Pacitan, {{ \App\Support\IndonesianDate::date($pengajuan->created_at) }}</div>

        <table class="signoff">
            <tr>
                <td>Yang Mengajukan,</td>
                <td>Disetujui,</td>
            </tr>
            <tr>
                <td>
                    <div class="signoff-space"></div>
                    <div class="signoff-name">{{ $pengajuan->pengaju->name }}</div>
                </td>
                <td>
                    <div class="signoff-space"></div>
                    <div class="signoff-name">{{ $pengajuan->decidedBy->name ?? '' }}</div>
                </td>
            </tr>
        </table>
    </div>
</body>

</html>
