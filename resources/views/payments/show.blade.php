@extends('layouts.app')

@section('title', 'Detail Pembayaran ' . $payment->payment_number . ' - Whusnet Operasional')
@section('page_title', 'Detail Pembayaran')
@section('breadcrumb_parent', 'Riwayat Transaksi Pembayaran')
@section('breadcrumb_parent_url', route('payments.index'))

@section('content')
@php
    $badgeClass = match($payment->payment_status->value ?? $payment->payment_status) {
        'valid' => 'bg-emerald-50 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60',
        'ditolak' => 'bg-rose-50 dark:bg-rose-950/80 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800/60',
        default => 'bg-amber-50 dark:bg-amber-950/80 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60',
    };
    $totalMoneyReceived = (float) $payment->amount + (float) $payment->overpay_amount;
@endphp

<style>
    @media print {
        /*
         * Header/footer bawaan browser (tanggal, judul dokumen, URL
         * localhost:8000/...) dicetak di KOTAK MARGIN halaman, di luar
         * jangkauan selector mana pun. margin:0 menghapus kotaknya sekalian.
         * Jarak ke tepi kertas ditanggung `.print-only.p-8` — jangan ikut
         * dinolkan.
         */
        @page { margin: 0; }
        .no-print, header, aside, #sidebar-backdrop, #toastContainer, .modal-backdrop, nav, footer { display: none !important; }
        .print-only { display: block !important; }
        .screen-only { display: none !important; }
        body { background: white !important; color: black !important; padding: 0 !important; }
        .print-card { border: 1px solid #cbd5e1 !important; box-shadow: none !important; }
    }
    @media screen {
        .print-only { display: none !important; }
    }
</style>

<!-- PRINT ONLY A4 KWITANSI PEMBAYARAN SHEET -->
{{-- Isi lembar ini berasal dari ReceiptPresenter, sumber yang sama dengan
     struk thermal (payments/receipt) dan kartu kolektor. Dulu tiap view
     membaca $payment sendiri-sendiri sehingga satu pembayaran tercetak beda
     isi tergantung dari halaman mana tombolnya ditekan.

     2026-09-01: layout disamain sama kwitansi PDF pelanggan (`.a4` di
     `payments/receipt.blade.php`, dompdf) — gaya invoice bersih (Stripe/
     Anthropic-style), bukan lagi kartu enterprise berwarna-warni. Beda dari
     versi pelanggan: lembar ini INTERNAL (dilihat staf), jadi baris
     "Diterima oleh"/"Kolektor"/"Catatan" tetap ada — versi pelanggan
     sengaja membuangnya (lihat `$isCustomerCopy` di receipt.blade.php). --}}
<div class="print-only px-16 py-12 bg-white text-zinc-900 font-sans text-[13px] leading-relaxed">

    <!-- Header -->
    <div class="flex justify-between items-start mb-8">
        <h1 class="text-[26px] font-bold text-zinc-900">Kwitansi</h1>
        <div class="text-right">
            <p class="text-sm font-bold uppercase tracking-wide text-zinc-900">Whusnet</p>
            <p class="text-[11px] text-zinc-500">Internet Service Provider</p>
        </div>
    </div>

    <!-- Meta -->
    <table class="mb-7">
        <tr>
            <td class="font-bold pr-4 whitespace-nowrap align-top py-0.5">No. Kwitansi</td>
            <td class="py-0.5">{{ $kwitansi['nomor'] }}</td>
        </tr>
        <tr>
            <td class="font-bold pr-4 whitespace-nowrap align-top py-0.5">Tanggal Bayar</td>
            <td class="py-0.5">{{ $kwitansi['tanggal_bayar'] }}</td>
        </tr>
        <tr>
            <td class="font-bold pr-4 whitespace-nowrap align-top py-0.5">Tanggal Ditagih</td>
            <td class="py-0.5">{{ $kwitansi['tanggal_ditagih'] }}</td>
        </tr>
        <tr>
            <td class="font-bold pr-4 whitespace-nowrap align-top py-0.5">Metode</td>
            <td class="py-0.5">{{ $kwitansi['metode'] }}</td>
        </tr>
        <tr>
            <td class="font-bold pr-4 whitespace-nowrap align-top py-0.5">Status</td>
            {{-- Warna status IKUT statusnya. Sebelumnya emerald tanpa syarat:
                 pembayaran DITOLAK pun tercetak hijau di kwitansi resmi. --}}
            <td class="py-0.5 font-semibold {{ $kwitansi['status_valid'] ? 'text-emerald-700' : 'text-rose-700' }}">● {{ $kwitansi['status'] }}</td>
        </tr>
        @if($kwitansi['keterangan_cicilan'])
            <tr>
                <td class="font-bold pr-4 whitespace-nowrap align-top py-0.5">Keterangan</td>
                <td class="py-0.5">{{ $kwitansi['keterangan_cicilan'] }}</td>
            </tr>
        @endif
        <tr>
            <td class="font-bold pr-4 whitespace-nowrap align-top py-0.5">Area</td>
            <td class="py-0.5">{{ $kwitansi['pop'] }}</td>
        </tr>
    </table>

    <!-- Pihak -->
    <table class="w-full mb-7">
        <tr>
            <td class="w-1/2 align-top pr-6">
                <p class="font-bold mb-1">Diterbitkan oleh</p>
                <p class="font-bold">Whusnet Internet Service Provider</p>
                <p>Grand Viola Townhouse No.3 Purbosuman</p>
                <p>Kab. Ponorogo</p>
            </td>
            <td class="w-1/2 align-top">
                <p class="font-bold mb-1">Ditagihkan kepada</p>
                <p class="font-bold">{{ $kwitansi['pelanggan']['nama'] }}</p>
                <p>CID {{ $kwitansi['pelanggan']['cid'] }}</p>
                @foreach($kwitansi['pelanggan']['alamat_baris'] as $baris)
                    <p>{{ $baris }}</p>
                @endforeach
                <p>{{ $kwitansi['pelanggan']['hp'] }}</p>
            </td>
        </tr>
    </table>

    <!-- Ringkasan -->
    <p class="text-[17px] font-bold mb-5">{{ $kwitansi['dibayar'] }} dibayar pada {{ $kwitansi['tanggal_bayar'] }}</p>

    <table class="w-full border-collapse">
        <thead>
            <tr class="border-b border-zinc-300 text-[11px] font-bold uppercase tracking-wide text-zinc-500">
                <th class="text-left pb-2">Deskripsi</th>
                <th class="text-right pb-2">Kuantitas</th>
                <th class="text-right pb-2">Harga Satuan</th>
                <th class="text-right pb-2">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            <tr class="border-b border-zinc-100">
                <td class="py-3 align-top">
                    {{-- Judul baris ikut keterangan cicilan. Dulu selalu
                         "Pelunasan Invoice" — cicilan sebagian pun tercetak
                         seolah tagihannya sudah lunas. --}}
                    <p>{{ $kwitansi['keterangan_cicilan'] ?: 'Pembayaran' }} — Internet {{ $kwitansi['invoice']['paket'] }}</p>
                    @if($kwitansi['invoice']['ada'])
                        <p class="text-[11px] text-zinc-500 mt-0.5">No. Tagihan {{ $kwitansi['invoice']['nomor'] }} · Periode {{ $kwitansi['invoice']['periode'] }}</p>
                    @endif
                </td>
                <td class="py-3 text-right align-top">1</td>
                <td class="py-3 text-right align-top">{{ $kwitansi['invoice']['ada'] ? $kwitansi['invoice']['total'] : $kwitansi['dibayar'] }}</td>
                <td class="py-3 text-right align-top">{{ $kwitansi['dibayar'] }}</td>
            </tr>
            @if($kwitansi['lebih_bayar'])
            <tr class="border-b border-zinc-100">
                <td class="py-3 align-top text-zinc-500" colspan="3">Lebih Bayar (Deposit / Overpay Pelanggan)</td>
                <td class="py-3 text-right align-top text-sky-700 font-semibold">{{ $kwitansi['lebih_bayar'] }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    <table class="w-full mb-8">
        @if($kwitansi['invoice']['ada'])
            <tr>
                <td class="py-1 text-zinc-500">Total Tagihan</td>
                <td class="py-1 text-right">{{ $kwitansi['invoice']['total'] }}</td>
            </tr>
        @endif
        <tr class="font-bold border-t border-zinc-300 {{ $kwitansi['status_valid'] ? 'text-emerald-700' : 'text-rose-700' }}">
            <td class="py-1 pt-2">Dibayar</td>
            <td class="py-1 pt-2 text-right">{{ $kwitansi['dibayar'] }}</td>
        </tr>
        @if($kwitansi['invoice']['ada'])
            <tr>
                <td class="py-1 text-zinc-500">Sisa Tagihan</td>
                <td class="py-1 text-right font-semibold {{ $kwitansi['invoice']['lunas'] ? 'text-emerald-700' : 'text-rose-700' }}">
                    {{ $kwitansi['invoice']['sisa'] }} {{ $kwitansi['invoice']['lunas'] ? '(Lunas)' : '' }}
                </td>
            </tr>
        @endif
    </table>

    <!-- Informasi Penagihan (internal, staf saja) -->
    <p class="text-[15px] font-bold mb-3">Informasi Penagihan</p>

    <table class="w-full mb-8">
        <tr>
            <td class="font-bold pr-4 whitespace-nowrap align-top py-0.5">Diterima oleh</td>
            <td class="py-0.5">{{ $kwitansi['penerima'] }}</td>
        </tr>
        @if($kwitansi['penagih'])
            <tr>
                <td class="font-bold pr-4 whitespace-nowrap align-top py-0.5">Kolektor/Penagih</td>
                <td class="py-0.5">{{ $kwitansi['penagih'] }}</td>
            </tr>
        @endif
        <tr>
            <td class="font-bold pr-4 whitespace-nowrap align-top py-0.5">Catatan</td>
            {{-- Catatan kosong tetap kosong. Kalimat "Tagihan Bulanan…" yang
                 dulu muncul sebagai fallback terbaca seperti catatan petugas,
                 padahal tak pernah ada yang menulisnya. --}}
            @if($kwitansi['catatan'])
                <td class="py-0.5 italic">"{{ $kwitansi['catatan'] }}"</td>
            @else
                <td class="py-0.5 italic text-zinc-400">Tanpa catatan.</td>
            @endif
        </tr>
    </table>

    <!-- Riwayat Pembayaran -->
    <p class="text-[15px] font-bold mb-3">Riwayat Pembayaran</p>

    <table class="w-full border-collapse mb-8">
        <thead>
            <tr class="border-b border-zinc-300 text-[11px] font-bold uppercase tracking-wide text-zinc-500">
                <th class="text-left pb-2">Metode Pembayaran</th>
                <th class="text-left pb-2">Tanggal</th>
                <th class="text-right pb-2">Jumlah Dibayar</th>
                <th class="text-right pb-2">No. Kwitansi</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="py-2.5">{{ $kwitansi['metode'] }}</td>
                <td class="py-2.5">{{ $kwitansi['tanggal_bayar'] }}</td>
                <td class="py-2.5 text-right">{{ $kwitansi['dibayar'] }}</td>
                <td class="py-2.5 text-right">{{ $kwitansi['nomor'] }}</td>
            </tr>
        </tbody>
    </table>

    <!-- Footer -->
    <p class="text-[11px] text-zinc-500 border-t border-zinc-200 pt-4">
        Kwitansi sah tanpa tanda tangan. Simpan sebagai bukti pembayaran.<br>
        Dicetak: {{ $kwitansi['dicetak'] }}
    </p>

</div>

<!-- SCREEN ONLY ENTERPRISE VIEW -->
<div class="screen-only space-y-6">

    <!-- TOP NOTICE BANNER -->
    @include('payments.partials.riwayat-banner')

    @if($payment->payment_status->value === 'ditolak')
        <div class="bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/60 rounded-2xl p-4 text-xs text-rose-700 dark:text-rose-300 shadow-2xs">
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-xl bg-rose-600 text-white shrink-0">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                <div>
                    <h4 class="font-bold text-sm text-rose-900 dark:text-rose-200">Pembayaran ini telah Ditolak / Dibatalkan</h4>
                    @if($payment->reject_reason)
                        <p class="mt-0.5">Alasan: <strong>{{ $payment->reject_reason }}</strong></p>
                    @endif
                    <p class="text-[10px] text-rose-600 dark:text-rose-400 mt-1">
                        Ditolak oleh {{ $payment->rejecter->name ?? '-' }} pada {{ optional($payment->rejected_at)->format('d/m/Y H:i') }} WIB
                    </p>
                </div>
            </div>
        </div>
    @endif

    <!-- PAGE TITLE BAR & ACTION BUTTONS -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-700/80 shadow-2xs">
        <div>
            <!-- Title & ID Badge -->
            <div class="flex items-center gap-2.5 flex-wrap">
                <a href="{{ route('payments.index') }}" class="p-2 rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 transition-colors" title="Kembali ke Daftar">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                
                <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                    Detail Pembayaran
                </h1>

                <!-- Payment Code Badge -->
                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 font-mono text-xs font-bold text-slate-800 dark:text-slate-200 shadow-2xs">
                    <span>{{ $payment->payment_number }}</span>
                    <button onclick="copyText('{{ $payment->payment_number }}', 'No. Pembayaran')" title="Salin Kode Pembayaran" class="text-slate-400 hover:text-sky-600 transition-colors cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                        </svg>
                    </button>
                </div>

                <!-- Status Badges -->
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border {{ $badgeClass }}">
                    <span class="w-2 h-2 rounded-full bg-current"></span>
                    <span>{{ $payment->payment_status->label() }}</span>
                </span>

                @if($installmentContext)
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold {{ $installmentContext['settles'] ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800' : 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800' }}">
                    {{ $installmentContext['settles'] ? 'Melunasi Tagihan' : 'Cicilan Ke-'.$installmentContext['number'] }}
                </span>
                @endif

                @if((float) $payment->overpay_amount > 0)
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800" title="Deposit lebih bayar dari pelanggan">
                    Lebih Bayar Rp {{ number_format((float) $payment->overpay_amount, 0, ',', '.') }}
                </span>
                @endif
            </div>

            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5">
                Tercatat pada <strong>{{ optional($payment->payment_date)->format('d/m/Y H:i') }} WIB</strong> • Diterima oleh <strong>{{ $payment->receiver->name ?? 'System' }}</strong> • Lokasi Kas <strong>POP {{ $payment->pop->name ?? '-' }}</strong>
            </p>
        </div>

        <!-- Right Action Bar -->
        <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap">
            @if($payment->invoice_id)
            <a href="{{ route('invoices.show', $payment->invoice_id) }}" class="inline-flex items-center gap-2 px-3.5 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-xl text-xs font-bold transition-all shadow-xs active:scale-95">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span>Detail Tagihan</span>
            </a>
            @endif

            <!-- Print Struk Button & Dropdown Menu -->
            <div class="relative inline-block text-left">
                <button onclick="togglePrintDropdown(event)" id="printDropdownBtn" class="inline-flex items-center gap-2 px-3.5 py-2 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-bold transition-all border border-slate-200 dark:border-slate-600 active:scale-95 cursor-pointer">
                    <svg class="h-4 w-4 text-slate-500 dark:text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                    </svg>
                    <span>Cetak Struk</span>
                    <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>

                <div id="printDropdownMenu" class="hidden absolute right-0 mt-2 w-60 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-xl py-1.5 z-30 text-xs">
                    <button type="button" onclick="window.print(); closePrintDropdown();" class="w-full px-4 py-2.5 flex items-center gap-3 text-slate-800 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors text-left cursor-pointer">
                        <div class="p-1.5 rounded-lg bg-sky-50 dark:bg-sky-950 text-sky-600 dark:text-sky-400">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        </div>
                        <div>
                            <p class="font-bold text-slate-900 dark:text-white">Cetak Kwitansi A4 (PDF)</p>
                            <p class="text-[10px] text-slate-400">Format kuitansi resmi WHUSNET</p>
                        </div>
                    </button>
                    <div class="border-t border-slate-100 dark:border-slate-700/60 my-1"></div>
                    <button type="button" onclick="openThermalPreview(); closePrintDropdown();" class="w-full px-4 py-2.5 flex items-center gap-3 text-slate-800 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors text-left cursor-pointer">
                        <div class="p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        </div>
                        <div>
                            <p class="font-bold text-slate-900 dark:text-white">Struk Thermal (80mm)</p>
                            <p class="text-[10px] text-slate-400">Pratinjau Struk Cetak Kasir</p>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Reject Button -->
            @can('payments.reject')
                @if($payment->payment_status->value === 'valid')
                <button type="button" onclick="openRejectModal()" class="inline-flex items-center gap-2 px-3.5 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl shadow-2xs transition-all active:scale-95 cursor-pointer">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    <span>Tolak Pembayaran</span>
                </button>
                @endif
            @endcan
        </div>
    </div>

    <!-- HERO METRIC SUMMARY CARDS GRID -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Card 1: Nominal Bayar -->
        <div class="bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-5 shadow-2xs relative overflow-hidden group hover:border-emerald-500/50 transition-all duration-300">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Nominal Diterima</span>
                <div class="p-2.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-900/50">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-3">
                <div class="font-mono text-2xl lg:text-3xl font-black text-emerald-600 dark:text-emerald-400 tracking-tight">
                    Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}
                </div>
                @if((float) $payment->overpay_amount > 0)
                <div class="text-[11px] font-bold text-sky-600 dark:text-sky-400 mt-1 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                    <span>Rp {{ number_format((float) $payment->overpay_amount, 0, ',', '.') }} Tercatat Overpay</span>
                </div>
                @else
                <div class="text-[11px] font-medium text-slate-400 dark:text-slate-500 mt-1">
                    Terverifikasi Sistem
                </div>
                @endif
            </div>
        </div>

        <!-- Card 2: Tanggal & Metode -->
        <div class="bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-5 shadow-2xs relative overflow-hidden group hover:border-sky-500/50 transition-all duration-300">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Tanggal & Metode</span>
                <div class="p-2.5 rounded-xl bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 border border-sky-100 dark:border-sky-900/50">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-3">
                <div class="font-mono text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                    {{ optional($payment->payment_date)->format('d/m/Y') }}
                </div>
                <div class="mt-1">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-lg text-[10px] font-mono font-bold bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 uppercase">
                        {{ strtoupper($payment->payment_method) }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Card 3: Operator Kasir -->
        <div class="bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-5 shadow-2xs relative overflow-hidden group hover:border-indigo-500/50 transition-all duration-300">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Operator Kasir</span>
                <div class="p-2.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-900/50">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-3">
                <div class="font-bold text-slate-900 dark:text-white text-base truncate">
                    {{ $payment->receiver->name ?? '-' }}
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Cabang / POP: <strong>{{ $payment->pop->name ?? '-' }}</strong>
                </div>
            </div>
        </div>

        <!-- Card 4: Kolektor Lapangan -->
        <div class="bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-5 shadow-2xs relative overflow-hidden group hover:border-amber-500/50 transition-all duration-300">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Kolektor</span>
                <div class="p-2.5 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 border border-amber-100 dark:border-amber-900/50">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-3">
                <div class="font-bold text-slate-900 dark:text-white text-base truncate">
                    {{ $payment->collector ? $payment->collector->name : 'Direct / Kasir POP' }}
                </div>
                <div class="mt-1">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                        {{ $payment->collector ? 'Kolektor Lapangan' : 'Tanpa Kolektor Lapangan' }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN ENTERPRISE 2-COLUMN GRID -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        
        <!-- LEFT MAIN TABBED CONTENT (8 cols on lg/xl) -->
        <div class="lg:col-span-8 space-y-6">
            
            <div class="bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl overflow-hidden shadow-2xs">
                
                <!-- Tab Bar Header -->
                <div class="border-b border-slate-100 dark:border-slate-700/60 px-6 flex items-center gap-6 text-xs bg-slate-50/50 dark:bg-slate-900/40 custom-scrollbar overflow-x-auto">
                    <button onclick="switchTab('info')" id="tab-info" class="py-4 border-b-2 border-sky-600 text-sky-600 dark:text-sky-400 font-bold flex items-center gap-2 transition-all shrink-0 cursor-pointer">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>Informasi & Catatan</span>
                    </button>
                    <button onclick="switchTab('proof')" id="tab-proof" class="py-4 border-b-2 border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 font-semibold flex items-center gap-2 transition-all shrink-0 cursor-pointer">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <span>Bukti Pembayaran</span>
                    </button>
                    @if(auth()->user()->hasPermission('audit_logs.view'))
                    <button onclick="switchTab('audit')" id="tab-audit" class="py-4 border-b-2 border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 font-semibold flex items-center gap-2 transition-all shrink-0 cursor-pointer">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>Timeline & Audit Log</span>
                    </button>
                    @endif
                </div>

                <!-- TAB PANE 1: Informasi & Catatan -->
                <div id="pane-info" class="p-6 space-y-6">
                    
                    <!-- Financial Breakdown Itemization -->
                    <div>
                        <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider mb-3">Rincian Pembagian Alokasi Dana</h3>
                        <div class="border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead class="bg-slate-50 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-700 text-slate-500 uppercase tracking-wider font-bold text-[10px]">
                                    <tr>
                                        <th class="p-3.5">Deskripsi Alokasi</th>
                                        <th class="p-3.5">Referensi / Keterangan</th>
                                        <th class="p-3.5 text-right">Nominal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 font-medium">
                                    <tr>
                                        <td class="p-3.5">
                                            <span class="font-bold text-slate-900 dark:text-white block">Pelunasan Tagihan Internet</span>
                                            <span class="text-[10px] text-slate-400">{{ $payment->invoice->internetPackage->name ?? 'Layanan ISP' }}</span>
                                        </td>
                                        <td class="p-3.5 font-mono text-slate-600 dark:text-slate-400">
                                            {{ $payment->invoice->invoice_number ?? '-' }}
                                        </td>
                                        <td class="p-3.5 text-right font-mono font-bold text-slate-900 dark:text-white">
                                            Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                    @if((float) $payment->overpay_amount > 0)
                                    <tr class="bg-sky-50/30 dark:bg-sky-950/20">
                                        <td class="p-3.5">
                                            <span class="font-bold text-sky-700 dark:text-sky-300 block">Alokasi Lebih Bayar (Deposit Pelanggan)</span>
                                            <span class="text-[10px] text-sky-600 dark:text-sky-400">Disimpan untuk pemotongan tagihan berikutnya</span>
                                        </td>
                                        <td class="p-3.5 font-mono text-sky-600 dark:text-sky-400">
                                            Overpay / Saldo
                                        </td>
                                        <td class="p-3.5 text-right font-mono font-bold text-sky-600 dark:text-sky-400">
                                            Rp {{ number_format((float) $payment->overpay_amount, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                    @endif
                                </tbody>
                                <tfoot class="bg-slate-50 dark:bg-slate-900/80 border-t border-slate-200 dark:border-slate-700 font-bold">
                                    <tr>
                                        <td colspan="2" class="p-3.5 text-slate-900 dark:text-white">TOTAL UANG DITERIMA DARI PELANGGAN</td>
                                        <td class="p-3.5 text-right font-mono text-sm text-emerald-600 dark:text-emerald-400">
                                            Rp {{ number_format($totalMoneyReceived, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Remarks / Notes Box -->
                    <div class="space-y-2">
                        <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">Catatan Petugas</h3>
                        <div class="p-4 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200 dark:border-slate-700/80 text-xs text-slate-700 dark:text-slate-300 leading-relaxed font-sans">
                            {{ $payment->note ?: 'Tidak ada catatan khusus untuk transaksi ini.' }}
                        </div>
                    </div>

                    @if($payment->old_payment_id || $payment->old_transaction_id || $payment->old_request_id)
                    <div class="space-y-2">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Audit Visibilitas Data Migrasi Legacy</h3>
                        <div class="p-4 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200 dark:border-slate-700/80 space-y-2 text-xs">
                            @if($payment->old_payment_id)
                            <div class="flex justify-between items-center pb-2 border-b border-slate-100 dark:border-slate-700/60">
                                <span class="text-slate-500">ID Bayar Lama:</span>
                                <span class="font-mono font-bold text-slate-800 dark:text-slate-200">{{ $payment->old_payment_id }}</span>
                            </div>
                            @endif
                            @if($payment->old_transaction_id)
                            <div class="flex justify-between items-center pb-2 border-b border-slate-100 dark:border-slate-700/60">
                                <span class="text-slate-500">ID Transaksi Lama:</span>
                                <span class="font-mono font-bold text-slate-800 dark:text-slate-200">{{ $payment->old_transaction_id }}</span>
                            </div>
                            @endif
                            @if($payment->old_request_id)
                            <div class="flex justify-between items-center">
                                <span class="text-slate-500">ID Permintaan Lama:</span>
                                <span class="font-mono font-bold text-slate-800 dark:text-slate-200">{{ $payment->old_request_id }}</span>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif

                </div>

                <!-- TAB PANE 2: Bukti Pembayaran -->
                <div id="pane-proof" class="hidden p-6 space-y-4">
                    <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">Lampiran Bukti Pembayaran / Struk Transfer</h3>
                    
                    @if($payment->proof_file)
                        <div class="p-6 bg-slate-50 dark:bg-slate-900/40 border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-2xl text-center space-y-3">
                            <div class="w-12 h-12 rounded-full bg-sky-100 dark:bg-sky-950 text-sky-600 dark:text-sky-400 flex items-center justify-center mx-auto">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="font-bold text-sm text-slate-800 dark:text-slate-200">Lampiran Bukti Terdaftar</p>
                                <p class="text-xs font-mono text-slate-400 max-w-sm mx-auto mt-0.5 truncate">{{ $payment->proof_file }}</p>
                            </div>
                            <a href="{{ asset('storage/' . $payment->proof_file) }}" target="_blank" class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-xl text-xs font-bold transition-all shadow-xs">
                                <span>Lihat Bukti Pembayaran</span>
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                            </a>
                        </div>
                    @else
                        <!-- Proof Placeholder Box -->
                        <div class="p-8 border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-2xl text-center bg-slate-50/50 dark:bg-slate-900/30 space-y-3">
                            <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mx-auto">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="font-bold text-sm text-slate-800 dark:text-slate-200">
                                    {{ strtolower($payment->payment_method) === 'cash' ? 'Pembayaran Tunai Langsung Kasir' : 'Belum Ada Lampiran File Bukti' }}
                                </p>
                                <p class="text-xs text-slate-400 max-w-sm mx-auto mt-0.5">
                                    Transaksi tunai kasir POP tidak mewajibkan upload foto fisik, namun struk resmi A4 & Thermal dapat dicetak kapan saja.
                                </p>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- TAB PANE 3: Timeline & Audit Log -->
                @if(auth()->user()->hasPermission('audit_logs.view'))
                <div id="pane-audit" class="hidden p-6 space-y-4">
                    <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">Riwayat Audit Pembayaran</h3>
                    
                    @if($payment->relationLoaded('auditLogs') && $payment->auditLogs->count() > 0)
                        <div class="border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="bg-slate-50 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-700 text-slate-400 uppercase tracking-wider text-[10px]">
                                        <th class="p-3">Waktu</th>
                                        <th class="p-3">Aksi</th>
                                        <th class="p-3">User Operator</th>
                                        <th class="p-3">Detail Perubahan Data</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 font-medium">
                                    @foreach($payment->auditLogs as $index => $auditLog)
                                    <tr>
                                        <td class="p-3 font-mono text-slate-500 whitespace-nowrap">
                                            {{ optional($auditLog->created_at)->format('d/m/Y H:i') }}
                                        </td>
                                        <td class="p-3">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                                {{ ucwords(str_replace('_', ' ', $auditLog->action)) }}
                                            </span>
                                        </td>
                                        <td class="p-3 font-bold text-slate-800 dark:text-slate-200">
                                            {{ $auditLog->user->name ?? '-' }}
                                        </td>
                                        <td class="p-3">
                                            <button onclick="toggleJsonView('json-{{ $index }}')" class="text-sky-600 dark:text-sky-400 hover:underline font-mono text-[11px] cursor-pointer">
                                                [+] Lihat JSON Payload
                                            </button>
                                            <pre id="json-{{ $index }}" class="hidden mt-2 p-3 bg-slate-900 text-emerald-400 font-mono text-[10px] rounded-xl overflow-x-auto custom-scrollbar">{{ json_encode([
                                                'old_values' => $auditLog->old_values,
                                                'new_values' => $auditLog->new_values,
                                            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="p-4 bg-slate-50 dark:bg-slate-900/40 rounded-xl border border-slate-200 dark:border-slate-700 text-xs text-slate-500 text-center">
                            Belum ada entri audit log untuk transaksi pembayaran ini.
                        </div>
                    @endif
                </div>
                @endif

            </div>
        </div>

        <!-- RIGHT STICKY SIDEBAR DETAILS (4 cols on lg/xl) -->
        <div class="lg:col-span-4 space-y-6 lg:sticky lg:top-20">
            
            <!-- INVOICE CONTEXT CARD -->
            <div class="bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-5 shadow-2xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/60">
                    <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Tagihan Terkait</span>
                    @if($payment->invoice_id)
                    <a href="{{ route('invoices.show', $payment->invoice_id) }}" class="text-xs font-bold text-sky-600 dark:text-sky-400 hover:underline flex items-center gap-1">
                        <span>Lihat Tagihan</span>
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </a>
                    @endif
                </div>

                @if($payment->invoice)
                <div class="space-y-2 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 dark:text-slate-400">No. Invoice:</span>
                        <a href="{{ route('invoices.show', $payment->invoice_id) }}" class="font-mono font-bold text-sky-600 dark:text-sky-400 hover:underline">
                            {{ $payment->invoice->invoice_number }}
                        </a>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 dark:text-slate-400">Periode Tagihan:</span>
                        <span class="font-mono font-semibold text-slate-800 dark:text-slate-200">{{ $payment->invoice->billing_period ?? '-' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 dark:text-slate-400">Total Invoice:</span>
                        <span class="font-mono font-semibold text-slate-800 dark:text-slate-200">Rp {{ number_format((float) ($payment->invoice->total_amount ?? 0), 0, ',', '.') }}</span>
                    </div>
                    <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-700/60">
                        <span class="text-slate-500 dark:text-slate-400">Sisa Tagihan Saat Ini:</span>
                        <span class="font-mono font-bold {{ (float)($payment->invoice->remaining_amount ?? 0) > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                            Rp {{ number_format((float) ($payment->invoice->remaining_amount ?? 0), 0, ',', '.') }} {{ (float)($payment->invoice->remaining_amount ?? 0) == 0 ? '(Lunas)' : '' }}
                        </span>
                    </div>
                </div>
                @else
                <p class="text-xs text-slate-400 italic">Tidak terhubung ke invoice tertentu.</p>
                @endif
            </div>

            <!-- CUSTOMER PROFILE CARD -->
            <div class="bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-5 shadow-2xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/60">
                    <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Identitas Pelanggan</span>
                    @if($payment->customer_id)
                    <a href="{{ route('customers.show', $payment->customer_id) }}" class="text-xs font-bold text-sky-600 dark:text-sky-400 hover:underline flex items-center gap-1">
                        <span>Profil Full</span>
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </a>
                    @endif
                </div>

                <div class="flex items-start gap-3">
                    <div class="w-11 h-11 rounded-2xl bg-sky-100 dark:bg-sky-950 text-sky-700 dark:text-sky-300 font-extrabold text-base flex items-center justify-center shrink-0 border border-sky-200 dark:border-sky-800">
                        {{ strtoupper(substr($payment->customer->full_name ?? 'P', 0, 2)) }}
                    </div>
                    <div class="space-y-1 text-xs">
                        @if($payment->customer_id)
                        <a href="{{ route('customers.show', $payment->customer_id) }}" class="font-bold text-slate-900 dark:text-white text-sm hover:text-sky-600 transition-colors block">
                            {{ $payment->customer->full_name ?? '-' }}
                        </a>
                        @else
                        <span class="font-bold text-slate-900 dark:text-white text-sm block">{{ $payment->customer->full_name ?? '-' }}</span>
                        @endif
                        <div class="font-mono text-[11px] text-slate-400 flex items-center gap-1.5">
                            <span>CID: {{ $payment->customer->cid ?? $payment->customer->customer_code ?? '-' }}</span>
                            @if($payment->customer && ($payment->customer->cid || $payment->customer->customer_code))
                            <button onclick="copyText('{{ $payment->customer->cid ?? $payment->customer->customer_code }}', 'CID')" title="Salin CID" class="text-slate-400 hover:text-sky-600 cursor-pointer">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                            </button>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="space-y-2.5 text-xs pt-3 border-t border-slate-100 dark:border-slate-700/60">
                    <div class="flex justify-between items-center">
                        <span class="text-slate-500 dark:text-slate-400">No. HP / WA:</span>
                        <div class="flex items-center gap-1.5 font-mono font-semibold text-slate-800 dark:text-slate-200">
                            <span>{{ $payment->customer->primary_phone ?? $payment->customer->phone ?? '-' }}</span>
                            @if($payment->customer && ($payment->customer->primary_phone || $payment->customer->phone))
                            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $payment->customer->primary_phone ?? $payment->customer->phone) }}" target="_blank" class="p-1 text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 rounded" title="Chat WA">
                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                            </a>
                            @endif
                        </div>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-slate-500 dark:text-slate-400">POP / Cabang:</span>
                        <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $payment->pop->name ?? '-' }}</span>
                    </div>
                    <div class="pt-1">
                        <span class="text-slate-500 dark:text-slate-400 block mb-1">Alamat Pemasangan:</span>
                        <p class="text-slate-700 dark:text-slate-300 text-[11px] leading-relaxed bg-slate-50 dark:bg-slate-900/60 p-2.5 rounded-xl border border-slate-200/60 dark:border-slate-700/60">
                            {{ $payment->customer->address ?? '-' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: TOLAK PEMBAYARAN -->
@can('payments.reject')
    @if($payment->payment_status->value === 'valid')
    <div id="rejectModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs hidden flex items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-800 rounded-2xl max-w-md w-full overflow-hidden shadow-2xl border border-slate-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between bg-rose-50/60 dark:bg-rose-950/40">
                <div class="flex items-center gap-2.5">
                    <div class="p-2 rounded-xl bg-rose-600 text-white shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 dark:text-white text-base">Tolak Pembayaran {{ $payment->payment_number }}</h3>
                        <p class="text-[10px] text-slate-500">Tagihan akan dihitung ulang & status dikembalikan</p>
                    </div>
                </div>
                <button type="button" onclick="closeRejectModal()" class="p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 rounded-lg cursor-pointer">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form id="rejectForm" method="POST" action="{{ route('payments.reject', $payment->id) }}" class="p-6 space-y-4 text-xs">
                @csrf
                <p class="text-slate-600 dark:text-slate-300 leading-relaxed">
                    Menolak transaksi ini akan membatalkan status pelunasan invoice terkait dan membalikkan alokasi deposit. Tindakan ini membutuhkan alasan penolakan.
                </p>

                <div>
                    <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1.5">Alasan Penolakan *</label>
                    <textarea name="reject_reason" id="rejectReasonInput" rows="4" required class="w-full p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:border-rose-500 focus:ring-2 focus:ring-rose-500/20" placeholder="Contoh: Bukti transfer tidak sah / Duplikasi input kasir / Rekonsiliasi kas tidak sesuai">{{ old('reject_reason') }}</textarea>
                    @error('reject_reason')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Modal Action Buttons -->
                <div class="pt-3 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-end gap-2">
                    <button type="button" onclick="closeRejectModal()" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 cursor-pointer">
                        Batal
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold shadow-md shadow-rose-600/20 active:scale-95 cursor-pointer">
                        Ya, Tolak Pembayaran
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
@endcan

<!-- MODAL: PRATINJAU STRUK THERMAL 80MM -->
<div id="thermalModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-800 rounded-2xl max-w-sm w-full overflow-hidden shadow-2xl border border-slate-200 dark:border-slate-700">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between bg-slate-50 dark:bg-slate-900">
            <h3 class="font-bold text-slate-900 dark:text-white text-xs">Simulasi Struk Thermal 80mm</h3>
            <button type="button" onclick="closeThermalModal()" class="p-1 text-slate-400 hover:text-slate-600 text-lg leading-none cursor-pointer">&times;</button>
        </div>

        <!-- 80mm Receipt Paper Card View -->
        <div class="p-4 bg-amber-50/30 dark:bg-slate-900">
            <div class="bg-white p-4 font-mono text-[11px] text-slate-900 shadow-md rounded border border-slate-200 space-y-2 leading-tight">
                <div class="text-center pb-2 border-b border-dashed border-slate-300">
                    <p class="font-black text-sm">WHUSNET OPERASIONAL</p>
                    <p class="text-[9px]">ISP Internet Service Provider</p>
                    <p class="text-[9px]">POP {{ $payment->pop->name ?? 'Kantor Pusat' }}</p>
                </div>

                <div class="space-y-0.5 text-[10px] py-1 border-b border-dashed border-slate-300">
                    <p>No : {{ $payment->payment_number }}</p>
                    <p>Tgl: {{ optional($payment->payment_date)->format('d/m/Y H:i') }}</p>
                    <p>Kas: {{ $payment->receiver->name ?? '-' }}</p>
                    <p>Cst: {{ $payment->customer->full_name ?? '-' }}</p>
                    <p>CID: {{ $payment->customer->cid ?? $payment->customer->customer_code ?? '-' }}</p>
                </div>

                <div class="py-1 border-b border-dashed border-slate-300 space-y-1">
                    <div class="flex justify-between font-bold">
                        <span>Inv: {{ $payment->invoice->invoice_number ?? '-' }}</span>
                        <span>Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}</span>
                    </div>
                    <p class="text-[9px] text-slate-500">{{ $payment->invoice->internetPackage->name ?? 'Layanan ISP' }}</p>
                    
                    @if((float) $payment->overpay_amount > 0)
                    <div class="flex justify-between pt-1 font-bold text-sky-700">
                        <span>Deposit Overpay</span>
                        <span>Rp {{ number_format((float) $payment->overpay_amount, 0, ',', '.') }}</span>
                    </div>
                    @endif
                </div>

                <div class="pt-1 space-y-0.5 text-right font-bold text-xs">
                    <div class="flex justify-between">
                        <span>TOTAL CASH:</span>
                        <span>Rp {{ number_format($totalMoneyReceived, 0, ',', '.') }}</span>
                    </div>
                </div>

                <div class="text-center pt-3 border-t border-dashed border-slate-300 text-[9px] text-slate-500">
                    <p>Terima kasih atas pembayaran Anda</p>
                    <p class="mt-0.5">Layanan CS: {{ $payment->customer->primary_phone ?? '083838506993' }}</p>
                </div>
            </div>
        </div>

        <div class="p-4 bg-slate-50 dark:bg-slate-900 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
            <button type="button" onclick="closeThermalModal()" class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-300 cursor-pointer">Tutup</button>
            <a href="{{ route('payments.receipt', $payment->id) }}" target="_blank" onclick="closeThermalModal()" class="px-4 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold shadow-xs transition-all">Cetak Thermal</a>
        </div>
    </div>
</div>

<script>
    function switchTab(tabKey) {
        const tabs = ['info', 'proof', 'audit'];
        tabs.forEach(key => {
            const btn = document.getElementById(`tab-${key}`);
            const pane = document.getElementById(`pane-${key}`);
            if (!btn || !pane) return;

            if (key === tabKey) {
                btn.className = 'py-4 border-b-2 border-sky-600 text-sky-600 dark:text-sky-400 font-bold flex items-center gap-2 transition-all shrink-0 cursor-pointer';
                pane.classList.remove('hidden');
            } else {
                btn.className = 'py-4 border-b-2 border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 font-semibold flex items-center gap-2 transition-all shrink-0 cursor-pointer';
                pane.classList.add('hidden');
            }
        });
    }

    function togglePrintDropdown(e) {
        if (e) e.stopPropagation();
        const menu = document.getElementById('printDropdownMenu');
        if (menu) menu.classList.toggle('hidden');
    }

    function closePrintDropdown() {
        const menu = document.getElementById('printDropdownMenu');
        if (menu) menu.classList.add('hidden');
    }

    document.addEventListener('click', function(e) {
        const btn = document.getElementById('printDropdownBtn');
        const menu = document.getElementById('printDropdownMenu');
        if (menu && !menu.classList.contains('hidden') && !menu.contains(e.target) && !btn?.contains(e.target)) {
            closePrintDropdown();
        }
    });

    function openRejectModal() {
        const modal = document.getElementById('rejectModal');
        if (modal) modal.classList.remove('hidden');
    }

    function closeRejectModal() {
        const modal = document.getElementById('rejectModal');
        if (modal) modal.classList.add('hidden');
    }

    @if ($errors->has('reject_reason'))
        document.addEventListener('DOMContentLoaded', openRejectModal);
    @endif

    function openThermalPreview() {
        const modal = document.getElementById('thermalModal');
        if (modal) modal.classList.remove('hidden');
    }

    function closeThermalModal() {
        const modal = document.getElementById('thermalModal');
        if (modal) modal.classList.add('hidden');
    }

    function toggleJsonView(id) {
        const el = document.getElementById(id);
        if (el) el.classList.toggle('hidden');
    }

    function copyText(text, label) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text);
        } else {
            const input = document.createElement('input');
            input.value = text;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
        }
        if (window.Toast && window.Toast.success) {
            window.Toast.success('Disalin', `${label || 'Teks'} (${text}) berhasil disalin.`);
        } else {
            alert(`${label || 'Teks'} (${text}) berhasil disalin.`);
        }
    }
</script>
@endsection
