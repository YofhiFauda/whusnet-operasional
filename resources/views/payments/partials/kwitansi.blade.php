{{--
    Isi lembar kwitansi — SATU markup dipakai oleh `payments/receipt.blade.php`
    (halaman cetak berdiri sendiri) dan `payments/show.blade.php` (blok
    print-only di Detail Pembayaran). ADHOC-94: sebelumnya kedua halaman
    menyalin markup nyaris identik secara manual — begitu satu diubah, yang
    lain diam-diam menyimpang. Sekarang cuma satu sumber; ubah di sini,
    keduanya ikut.

    Butuh `$kwitansi` (ReceiptPresenter::for()) di scope pemanggil. CSS-nya
    sengaja SELF-CONTAINED (class di-prefix `kw-`) supaya aman ditempel di
    halaman apa pun — baik yang bergaya utility Tailwind (`show.blade.php`)
    maupun halaman polos (`receipt.blade.php`) — tanpa bentrok/tertimpa gaya
    tuan rumah.
--}}
<style>
    .kw-sheet {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 13px;
        line-height: 1.25;
        color: #000000;
    }

    .kw-sheet .kw-header-row {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 8px;
    }

    .kw-sheet .kw-header-row > tbody > tr > td {
        vertical-align: top;
        padding: 0;
    }

    .kw-sheet .kw-header-row .kw-header-data {
        width: 55%;
    }

    .kw-sheet .kw-header-row .kw-header-date {
        width: 45%;
        text-align: right;
    }

    .kw-sheet .kw-date-table {
        margin-left: auto;
        border-collapse: collapse;
        /* Kertas fisik cuma 5,5in lebar (NCR staf & PDF Portal disamakan
           2026-09-26) — 13px bikin "Tgl. Jatuh tempo :" patah di tengah
           label. Diperkecil + nowrap biar selalu satu baris. */
        font-size: 11px;
    }

    .kw-sheet .kw-date-table td {
        padding: 2px 0 2px 8px;
        text-align: left;
        white-space: nowrap;
    }

    .kw-sheet .kw-company-name {
        font-weight: bold;
        font-size: 15px;
        margin-bottom: 2px;
    }

    .kw-sheet .kw-company-info {
        font-size: 12px;
        margin-bottom: 8px;
    }

    .kw-sheet .kw-doc-number {
        font-size: 12px;
        margin-bottom: 6px;
    }

    .kw-sheet .kw-customer-name {
        font-size: 16px;
        font-weight: bold;
        margin-bottom: 2px;
    }

    .kw-sheet .kw-customer-address {
        font-size: 12px;
        margin-bottom: 8px;
    }

    .kw-sheet .kw-invoice-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 0;
        font-size: 12px;
    }

    .kw-sheet .kw-invoice-table th,
    .kw-sheet .kw-invoice-table td {
        border: 1px solid #000000;
        padding: 3px 5px;
        text-align: left;
    }

    .kw-sheet .kw-invoice-table th {
        font-weight: bold;
        text-align: center;
    }

    .kw-sheet .kw-align-center {
        text-align: center;
    }

    .kw-sheet .kw-align-right {
        text-align: right;
    }

    /* Label & nominal di kolom sempit (Harga/Total/label ringkasan) —
       tanpa ini, "Rp 4.900.000"/"Lebih Bayar"/"Titip Saldo" patah jadi 2
       baris di kertas 5,5in (feedback 2026-09-26). */
    .kw-sheet .kw-nowrap {
        white-space: nowrap;
    }

    /* Baris Sub Total/DP/Total — cuma 2 kolom (sejajar Harga & Total),
       kolom No/Keterangan/Paket kosong TANPA border sama sekali (bukan
       cuma tanpa isi). */
    .kw-sheet .kw-summary-blank {
        border: none !important;
    }

    .kw-sheet .kw-total-row {
        font-weight: bold;
        font-size: 14px;
    }

    .kw-sheet .kw-thank-you {
        margin-top: 6px;
        text-align: right;
        font-size: 12px;
        padding-right: 8px;
    }

    .kw-sheet .kw-notes-section {
        margin-top: 4px;
        font-size: 10px;
        line-height: 1.25;
    }

    .kw-sheet .kw-notes-title {
        margin-bottom: 5px;
    }

    .kw-sheet .kw-notes-list {
        margin: 0;
        padding-left: 18px;
    }
</style>

<div class="kw-sheet">

    {{-- ===================================== HEADER ===================================== --}}

    <div class="kw-company-name">WHUSNET by CONNEXA DIGITAL NETWORK</div>
    <div class="kw-company-info">
        Jl. Waseso Aji RT.01 RW.01 Doyong, Ngampel, Balong, Ponorogo 63461<br>
        Tlp. 081809130009
    </div>

    {{-- Data diri (kiri) sejajar dengan Tanggal/Jatuh Tempo (kanan) — pakai
         baris tabel, bukan float, biar dua kolom mulai di baris yang sama. --}}
    <table class="kw-header-row">
        <tr>
            <td class="kw-header-data">
                <div class="kw-doc-number">{{ $kwitansi['pelanggan']['cid'] }}</div>
                <div class="kw-customer-name">{{ $kwitansi['pelanggan']['nama'] }}</div>
                <div class="kw-customer-address">{{ $kwitansi['pelanggan']['alamat'] }}</div>
            </td>
            <td class="kw-header-date">
                <table class="kw-date-table">
                    <tr>
                        <td>Tanggal :</td>
                        <td>{{ $kwitansi['tanggal_bayar'] }}</td>
                    </tr>
                    <tr>
                        <td>Tgl. Jatuh tempo :</td>
                        <td>{{ $kwitansi['jatuh_tempo'] }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- ===================================== RINCIAN PEMBAYARAN ===================================== --}}

    <table class="kw-invoice-table">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 35%;">Keterangan</th>
                <th style="width: 20%;">Paket</th>
                <th style="width: 20%;">Harga</th>
                <th style="width: 20%;">Total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="kw-align-center">1</td>
                <td>{{ $kwitansi['keterangan_item'] }}</td>
                <td class="kw-align-center">{{ $kwitansi['invoice']['paket'] }}</td>
                <td class="kw-align-right kw-nowrap">{{ $kwitansi['dibayar'] }}</td>
                <td class="kw-align-right kw-nowrap">{{ $kwitansi['dibayar'] }}</td>
            </tr>

            {{-- ================= RINCIAN TOTAL =================
                 Menyatu di tabel yang sama (bukan tabel terpisah) supaya
                 border-nya konsisten 1px, bukan dua border numpuk jadi
                 kelihatan tebal. Cuma 2 kolom yang sejajar Harga & Total;
                 kolom No/Keterangan/Paket kosong tanpa border (feedback
                 2026-09-23). --}}
            <tr>
                <td class="kw-summary-blank" colspan="3"></td>
                <td class="kw-nowrap">Sub Total</td>
                <td class="kw-align-right kw-nowrap">{{ $kwitansi['dibayar'] }}</td>
            </tr>
            <tr>
                <td class="kw-summary-blank" colspan="3"></td>
                <td class="kw-nowrap">DP</td>
                <td class="kw-align-right kw-nowrap">{{ $kwitansi['invoice']['ada'] ? $kwitansi['invoice']['sisa'] : '-' }}</td>
            </tr>
            @if($kwitansi['lebih_bayar'])
                <tr>
                    <td class="kw-summary-blank" colspan="3"></td>
                    <td class="kw-nowrap">Lebih Bayar</td>
                    <td class="kw-align-right kw-nowrap">{{ $kwitansi['lebih_bayar'] }}</td>
                </tr>
            @endif
            {{-- Titip Saldo (ADHOC-92) — cuma muncul untuk kredit sumber
                 bayar_di_muka (overpay invoice AWAL), beda dari "Lebih Bayar"
                 biasa di atas. --}}
            @if($kwitansi['titip_saldo'])
                <tr>
                    <td class="kw-summary-blank" colspan="3"></td>
                    <td class="kw-nowrap">Titip Saldo</td>
                    <td class="kw-align-right kw-nowrap">{{ $kwitansi['titip_saldo'] }}</td>
                </tr>
            @endif
            <tr class="kw-total-row">
                <td class="kw-summary-blank" colspan="3"></td>
                <td class="kw-nowrap">Total</td>
                <td class="kw-align-right kw-nowrap">{{ $kwitansi['dibayar'] }}</td>
            </tr>
        </tbody>
    </table>

    <div class="kw-thank-you">Terima kasih</div>

    {{-- ===================================== FOOTER ===================================== --}}

    <div class="kw-notes-section">
        <div class="kw-notes-title">Perhatian :</div>
        <ol class="kw-notes-list">
            <li>Lakukan pembayaran sebelum jatuh tempo agar koneksi tetap aktif.</li>
            <li>Konfirmasi Pembayaran Hub 0817585853</li>
            <li>Layanan Teknisi Gangguan Hub 0818415131 (24jam).</li>
            <li>Nilai tagihan sudah termasuk PPN 11%</li>
        </ol>
    </div>

</div>
