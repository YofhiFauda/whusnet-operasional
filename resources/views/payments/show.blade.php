@extends('layouts.app')

@section('title', 'Detail Pembayaran ' . $payment->payment_number . ' - Whusnet Operasional')
@section('page_title', 'Detail Pembayaran')
@section('breadcrumb_parent', 'Pembayaran')
@section('breadcrumb_parent_url', route('payments.index'))

@section('content')
@php
    $badgeClass = match($payment->payment_status->value) {
        'valid' => 'bg-emerald-50 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800',
        'ditolak' => 'bg-red-50 dark:bg-red-950/80 text-red-700 dark:text-red-400 border-red-200 dark:border-red-800',
        default => 'bg-amber-50 dark:bg-amber-950/80 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-800',
    };
@endphp

<style>
    @media print {
        .no-print, header, sidebar, footer, nav, #toast, .modal { display: none !important; }
        .print-only { display: block !important; }
        .screen-only { display: none !important; }
        body { background: white !important; color: black !important; padding: 0 !important; }
    }
    @media screen {
        .print-only { display: none !important; }
    }
</style>

<!-- PRINT ONLY A4 KWITANSI PEMBAYARAN SHEET -->
<div class="print-only p-8 bg-white text-slate-900 font-sans text-xs leading-normal">
    <!-- Header -->
    <div class="flex justify-between items-start border-b pb-4 mb-4 border-slate-300">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900">WHUSNET</h1>
            <p class="text-xs text-slate-600 font-medium">{{ $payment->pop->name ?? 'Kantor Pusat' }} &bull; ISP Service</p>
            <p class="text-[10px] text-slate-500 mt-0.5">Website Billing ISP Internal</p>
        </div>
        <div class="text-right">
            <h2 class="text-base font-bold text-slate-900 uppercase tracking-wide">STRUK / KWITANSI PEMBAYARAN</h2>
            <p class="font-mono text-xs font-semibold text-slate-700">No. Transaksi: {{ $payment->payment_number }}</p>
            <p class="text-xs text-slate-600 mt-1">Status: <span class="font-bold uppercase text-emerald-700">● {{ $payment->payment_status->label() }}</span></p>
            @if($installmentContext)
                <p class="text-xs text-slate-600">{{ $installmentContext['settles'] ? 'Melunasi Tagihan' : 'Cicilan Ke-'.$installmentContext['number'] }}</p>
            @endif
        </div>
    </div>

    <!-- Info Grid -->
    <div class="grid grid-cols-2 gap-6 mb-6 text-xs">
        <div>
            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">DITERIMA DARI PELANGGAN</p>
            <p class="font-bold text-sm text-slate-900">{{ $payment->customer->full_name ?? '-' }}</p>
            <p class="font-mono text-xs text-slate-700">CID: {{ $payment->customer->cid ?? $payment->customer->customer_code ?? '-' }}</p>
            <p class="text-slate-600 font-mono">No. HP: {{ $payment->customer->primary_phone ?? $payment->customer->phone ?? '-' }}</p>
            <p class="text-slate-600 mt-0.5">Alamat: {{ $payment->customer->address ?? '-' }}</p>
        </div>
        <div class="text-right space-y-1">
            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">DETAIL TRANSAKSI</p>
            <p><span class="text-slate-500">Tanggal Bayar:</span> <span class="font-semibold">{{ optional($payment->payment_date)->format('d/m/Y') }}</span></p>
            <p><span class="text-slate-500">Metode Bayar:</span> <span class="font-semibold uppercase font-mono">{{ strtoupper($payment->payment_method) }}</span></p>
            <p><span class="text-slate-500">Kolektor:</span> <span class="font-semibold">{{ $payment->collector ? $payment->collector->name : 'Langsung (Kasir POP)' }}</span></p>
            <p><span class="text-slate-500">No. Tagihan:</span> <span class="font-mono font-semibold">{{ $payment->invoice->invoice_number ?? '-' }}</span></p>
            <p><span class="text-slate-500">Periode Tagihan:</span> <span class="font-mono font-semibold">{{ $payment->invoice->billing_period ?? '-' }}</span></p>
            <p><span class="text-slate-500">POP / Cabang:</span> <span class="font-semibold">{{ $payment->pop->name ?? '-' }}</span></p>
        </div>
    </div>

    <!-- Table Rincian Transaksi -->
    <table class="w-full text-left border-collapse text-xs mb-6">
        <thead>
            <tr class="border-y border-slate-300 bg-slate-100 text-slate-700 uppercase font-semibold text-[10px]">
                <th class="py-2.5 px-3">Deskripsi Pembayaran</th>
                <th class="py-2.5 px-3 text-center">Metode</th>
                <th class="py-2.5 px-3 text-right">Nominal Diterima (Rp)</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
            <tr>
                <td class="py-3 px-3">
                    <p class="font-bold text-slate-900">Pembayaran Internet {{ $payment->invoice->internetPackage->name ?? 'Layanan ISP' }}</p>
                    <p class="text-[11px] text-slate-500">Invoice: {{ $payment->invoice->invoice_number ?? '-' }} &bull; Periode {{ $payment->invoice->billing_period ?? '-' }}</p>
                </td>
                <td class="py-3 px-3 text-center font-mono uppercase">{{ $payment->payment_method }}</td>
                <td class="py-3 px-3 text-right font-mono font-bold">Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}</td>
            </tr>
            @if((float) $payment->overpay_amount > 0)
            <tr>
                <td class="py-3 px-3" colspan="2">
                    <p class="text-slate-600">Lebih Bayar (catatan, di luar pembayaran tagihan)</p>
                </td>
                <td class="py-3 px-3 text-right font-mono font-bold text-sky-700">Rp {{ number_format((float) $payment->overpay_amount, 0, ',', '.') }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    <!-- Calculation Breakdown & Signatures -->
    <div class="flex justify-between items-start gap-6 text-xs">
        <div class="space-y-2 max-w-xs">
            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">KETERANGAN & CATATAN</p>
            <p class="text-[11px] text-slate-600">Catatan: {{ $payment->note ?: 'Tidak ada catatan.' }}. Struk pembayaran ini diterbitkan secara resmi oleh WHUSNET Operasional.</p>
            <p class="text-[10px] text-slate-400 italic mt-4">Struk sah tanpa tanda tangan &bull; Dicetak {{ now()->format('d/m/Y H:i') }}</p>
        </div>

        <div class="w-64 space-y-1.5 text-xs">
            @if($payment->invoice)
            <div class="flex justify-between text-slate-600">
                <span>Total Tagihan Invoice</span>
                <span class="font-mono font-semibold">Rp {{ number_format((float) $payment->invoice->total_amount, 0, ',', '.') }}</span>
            </div>
            @endif
            <div class="flex justify-between text-emerald-600 font-bold text-sm pt-1 border-t border-slate-300">
                <span>JUMLAH DIBAYAR</span>
                <span class="font-mono">Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}</span>
            </div>
            @if($payment->invoice)
            <div class="flex justify-between text-slate-600">
                <span>Sisa Tagihan</span>
                <span class="font-mono font-semibold {{ (float)$payment->invoice->remaining_amount > 0 ? 'text-red-600' : 'text-emerald-600' }}">
                    Rp {{ number_format((float) $payment->invoice->remaining_amount, 0, ',', '.') }}
                </span>
            </div>
            @endif
            <div class="pt-3 text-right">
                <span class="text-[10px] text-slate-500 block">Diterima oleh:</span>
                <span class="font-semibold text-slate-900 block">{{ $payment->receiver->name ?? '-' }}</span>
            </div>
        </div>
    </div>
</div>

<div class="space-y-5 screen-only">
    @include('payments.partials.riwayat-banner')

    <!-- LAYER 1: NAKED PAGE HEADER (§1.5 & §1.7 — STRICTLY NO CARD WRAPPER) -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        
        <!-- Left Title Block -->
        <div class="space-y-1">
            <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                <a href="{{ route('payments.index') }}" class="hover:text-slate-900 dark:hover:text-slate-200 transition-colors">Pembayaran</a>
                <svg class="h-3 w-3 text-slate-400 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="font-mono font-semibold text-slate-900 dark:text-slate-100">{{ $payment->payment_number }}</span>
            </nav>

            <div class="flex items-center gap-2.5 flex-wrap">
                <a href="{{ route('payments.index') }}" class="p-1.5 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 rounded-lg hover:bg-slate-200/60 dark:hover:bg-slate-800 transition-colors" title="Kembali">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-slate-100 tracking-tight">Detail Pembayaran</h1>
                
                <!-- Payment Technical ID Badge -->
                <span class="font-mono text-xs px-2.5 py-1 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 font-semibold inline-flex items-center gap-1.5">
                    {{ $payment->payment_number }}
                    <button onclick="copyToClipboard('{{ $payment->payment_number }}', 'No. Pembayaran')" title="Salin No. Pembayaran" class="text-slate-400 hover:text-sky-600 transition-colors">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    </button>
                </span>

                <!-- Status Badge -->
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold border {{ $badgeClass }}">
                    <span class="w-2 h-2 rounded-full bg-current"></span>
                    {{ $payment->payment_status->label() }}
                </span>

                @if($installmentContext)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold border {{ $installmentContext['settles'] ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800' : 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-800' }}">
                        {{ $installmentContext['settles'] ? 'Melunasi Tagihan' : 'Cicilan Ke-'.$installmentContext['number'] }}
                    </span>
                @endif

                @if((float) $payment->overpay_amount > 0)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold border bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-400 border-sky-200 dark:border-sky-800" title="Uang lebih yang diserahkan pelanggan — catatan saja, tidak menambah pembayaran tagihan">
                        Lebih Bayar Rp {{ number_format((float) $payment->overpay_amount, 0, ',', '.') }}
                    </span>
                @endif
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400">Tercatat pada {{ optional($payment->payment_date)->format('d/m/Y') }} &bull; Diterima oleh {{ $payment->receiver->name ?? 'System' }} &bull; POP {{ $payment->pop->name ?? '-' }}</p>
        </div>

        <!-- Right Action Toolbar (Naked Buttons) -->
        <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap no-print">
            @if($payment->invoice)
                <a href="{{ route('invoices.show', $payment->invoice_id) }}" class="inline-flex items-center justify-center gap-2 px-3.5 py-2 bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>Detail Tagihan</span>
                </a>
            @endif

            <!-- Print Options Dropdown -->
            <div class="relative flex-1 sm:flex-none">
                <button onclick="togglePrintDropdown(event)" id="printDropdownBtn" class="w-full inline-flex items-center justify-center gap-2 px-3.5 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-lg shadow-sm transition-colors">
                    <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    <span>Cetak</span>
                    <svg class="h-3 w-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>

                <div id="printDropdownMenu" class="hidden absolute right-0 mt-2 w-56 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg shadow-xl py-1 z-40 text-xs">
                    <button type="button" onclick="window.print(); closePrintDropdown();" class="w-full px-3.5 py-2.5 flex items-center gap-2.5 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors text-left">
                        <svg class="h-4 w-4 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <div>
                            <p class="font-semibold text-slate-900 dark:text-slate-100 leading-tight">Cetak Kwitansi A4 (PDF)</p>
                            <p class="text-[10px] text-slate-400">Format kuitansi pembayaran resmi</p>
                        </div>
                    </button>
                    <div class="border-t border-slate-100 dark:border-slate-800"></div>
                    <a href="{{ route('payments.receipt', $payment->id) }}" target="_blank" onclick="closePrintDropdown();" class="w-full px-3.5 py-2.5 flex items-center gap-2.5 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors text-left">
                        <svg class="h-4 w-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        <div>
                            <p class="font-semibold text-slate-900 dark:text-slate-100 leading-tight">Cetak Struk Thermal (80mm)</p>
                            <p class="text-[10px] text-slate-400">Struk bukti bayar kasir POP</p>
                        </div>
                    </a>
                </div>
            </div>

            @can('payments.reject')
                @if($payment->payment_status->value === 'valid')
                    <button type="button" onclick="openRejectDialog()" class="inline-flex items-center justify-center gap-2 px-3.5 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        <span>Tolak Pembayaran</span>
                    </button>
                @endif
            @endcan

            <a href="{{ route('payments.index') }}" class="inline-flex items-center justify-center gap-2 px-3.5 py-2 border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-lg shadow-sm transition-colors">
                <span>Kembali</span>
            </a>
        </div>
    </div>

    @if($payment->payment_status->value === 'ditolak')
        <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 rounded-lg p-4 text-xs text-red-700 dark:text-red-400">
            <p class="font-semibold">Pembayaran ini sudah ditolak/dibatalkan.</p>
            @if($payment->reject_reason)
                <p class="mt-1">Alasan: {{ $payment->reject_reason }}</p>
            @endif
            <p class="mt-1 text-red-600/80 dark:text-red-400/70">
                Oleh {{ $payment->rejecter->name ?? '-' }} pada {{ optional($payment->rejected_at)->format('d/m/Y H:i') }}
            </p>
        </div>
    @endif

    <!-- LAYER 3: SINGLE PRIMARY DETAIL PANEL (CARD BUDGET = 1 STRICT COMPLIANCE) -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg overflow-hidden shadow-sm">
        
        <!-- SECTION 1: FINANCIAL METRIC STRIP (Flat Row with Vertical Dividers) -->
        <div class="p-5 sm:p-6 grid grid-cols-2 md:grid-cols-4 gap-6 bg-slate-50/50 dark:bg-slate-800/20 border-b border-slate-200 dark:border-slate-800">
            <div>
                <span class="text-[10px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider block">NOMINAL BAYAR</span>
                <span class="font-mono text-2xl font-bold text-emerald-600 dark:text-emerald-400 block mt-0.5">Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}</span>
                @if((float) $payment->overpay_amount > 0)
                    <span class="text-[11px] font-semibold text-sky-600 dark:text-sky-400 block">+ Rp {{ number_format((float) $payment->overpay_amount, 0, ',', '.') }} lebih bayar</span>
                @else
                    <span class="text-[11px] text-slate-500 dark:text-slate-400">Pembayaran Terverifikasi</span>
                @endif
            </div>

            <div>
                <span class="text-[10px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider block">TANGGAL & METODE</span>
                <span class="font-bold text-slate-800 dark:text-slate-200 block text-base mt-1">{{ optional($payment->payment_date)->format('d/m/Y') }}</span>
                <span class="inline-flex items-center gap-1 mt-0.5 px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800 uppercase">
                    {{ strtoupper($payment->payment_method) }}
                </span>
            </div>

            <div>
                <span class="text-[10px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider block">DITERIMA OLEH</span>
                <span class="font-semibold text-slate-800 dark:text-slate-200 block text-base mt-1 truncate">{{ $payment->receiver->name ?? '-' }}</span>
                <span class="text-[11px] text-slate-500 dark:text-slate-400 truncate block">POP: {{ $payment->pop->name ?? '-' }}</span>
            </div>

            <div>
                <span class="text-[10px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider block">KOLEKTOR LAPANGAN</span>
                <span class="font-semibold text-slate-800 dark:text-slate-200 block text-base mt-1 truncate">
                    {{ $payment->collector ? $payment->collector->name : 'Langsung (Kasir POP)' }}
                </span>
                <span class="inline-flex items-center gap-1 mt-0.5 px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                    {{ $payment->collector ? 'Kolektor Lapangan' : 'Bukan via kolektor' }}
                </span>
            </div>
        </div>

        <!-- SECTION 2: UNIFIED CUSTOMER & INVOICE STRIP (Flat Row — No Cards inside) -->
        <div class="p-5 sm:p-6 grid grid-cols-1 md:grid-cols-2 gap-6 border-b border-slate-200 dark:border-slate-800">
            
            <!-- Customer Information Block -->
            <div class="space-y-3">
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">INFORMASI PELANGGAN</span>
                </div>

                <div class="flex items-start gap-3.5">
                    <div class="w-10 h-10 rounded-lg bg-sky-100 dark:bg-sky-950/80 text-sky-700 dark:text-sky-400 font-bold text-sm flex items-center justify-center shrink-0">
                        {{ strtoupper(substr($payment->customer->full_name ?? 'P', 0, 2)) }}
                    </div>
                    <div class="space-y-1 text-xs">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('customers.show', $payment->customer_id) }}" class="font-bold text-slate-900 dark:text-slate-100 text-sm hover:text-sky-600 dark:hover:text-sky-400 transition-colors">
                                {{ $payment->customer->full_name ?? '-' }}
                            </a>
                        </div>
                        <div class="flex items-center gap-3 text-slate-500 dark:text-slate-400 flex-wrap">
                            <span class="font-mono font-medium flex items-center gap-1">
                                CID: {{ $payment->customer->cid ?? $payment->customer->customer_code ?? '-' }}
                                @if($payment->customer->cid || $payment->customer->customer_code)
                                    <button onclick="copyToClipboard('{{ $payment->customer->cid ?? $payment->customer->customer_code }}', 'CID')" class="text-slate-400 hover:text-sky-600"><svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg></button>
                                @endif
                            </span>
                            <span>&bull;</span>
                            <span class="flex items-center gap-1">
                                HP: <span class="font-mono">{{ $payment->customer->primary_phone ?? $payment->customer->phone ?? '-' }}</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Invoice Context Block -->
            <div class="space-y-3 md:border-l md:border-slate-200 md:dark:border-slate-800 md:pl-6">
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">TAGIHAN / INVOICE TERKAIT</span>
                </div>

                <div class="flex items-start gap-3.5">
                    <div class="w-10 h-10 rounded-lg bg-sky-100 dark:bg-sky-950/80 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    </div>
                    <div class="space-y-0.5 text-xs">
                        @if($payment->invoice)
                            <a href="{{ route('invoices.show', $payment->invoice_id) }}" class="font-mono font-bold text-sky-600 dark:text-sky-400 hover:underline text-sm block">
                                {{ $payment->invoice->invoice_number }}
                            </a>
                            <p class="text-slate-500 dark:text-slate-400 font-mono text-[11px]">
                                Periode: {{ $payment->invoice->billing_period ?? '-' }} &bull; Total Invoice: Rp {{ number_format((float) ($payment->invoice->total_amount ?? 0), 0, ',', '.') }}
                            </p>
                            <p class="text-slate-600 dark:text-slate-400 text-[11px] font-semibold">
                                Sisa Tagihan Saat Ini: Rp {{ number_format((float) ($payment->invoice->remaining_amount ?? 0), 0, ',', '.') }}
                            </p>
                        @else
                            <p class="text-slate-400 italic">Tidak terhubung ke invoice tertentu</p>
                        @endif
                    </div>
                </div>
            </div>

        </div>

        <!-- SECTION 3: TAB NAVIGATION BAR (Internal Border Bottom) -->
        <div class="border-b border-slate-200 dark:border-slate-800 px-4 sm:px-6 flex items-center justify-between gap-4 overflow-x-auto custom-scrollbar no-print bg-slate-50/30 dark:bg-slate-800/10">
            <div class="flex items-center gap-2 sm:gap-6 text-xs shrink-0">
                <button onclick="switchTab('info')" id="tab-info" class="py-3.5 border-b-2 border-sky-600 text-sky-600 dark:border-sky-400 dark:text-sky-400 font-semibold flex items-center gap-2 transition-all">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Informasi & Catatan</span>
                </button>
                <button onclick="switchTab('proof')" id="tab-proof" class="py-3.5 border-b-2 border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 font-medium flex items-center gap-2 transition-all">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span>Bukti Pembayaran</span>
                </button>
                @if(auth()->user()->hasPermission('audit_logs.view'))
                <button onclick="switchTab('audit')" id="tab-audit" class="py-3.5 border-b-2 border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 font-medium flex items-center gap-2 transition-all">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Timeline & Audit Log</span>
                </button>
                @endif
            </div>
        </div>

        <!-- SECTION 4: TAB CONTENT PANES -->

        <!-- TAB PANE 1: Informasi & Catatan -->
        <div id="pane-info" class="p-5 sm:p-6 space-y-6">
            <div class="space-y-3">
                <h3 class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">Catatan Pembayaran</h3>
                <div class="p-4 bg-slate-50/80 dark:bg-slate-800/40 rounded-lg border border-slate-200 dark:border-slate-800 text-xs text-slate-700 dark:text-slate-300">
                    {{ $payment->note ?: 'Tidak ada catatan khusus untuk transaksi ini.' }}
                </div>
            </div>

            @if($payment->old_payment_id || $payment->old_transaction_id || $payment->old_request_id)
            <div class="space-y-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Audit Visibilitas Data Migrasi Legacy</h3>
                <div class="p-4 bg-slate-50/80 dark:bg-slate-800/40 rounded-lg border border-slate-200 dark:border-slate-800 space-y-2 text-xs max-w-lg">
                    @if($payment->old_payment_id)
                    <div class="flex justify-between items-center pb-2 border-b border-slate-200/60 dark:border-slate-800/60">
                        <span class="text-slate-500 dark:text-slate-400">ID Bayar Lama:</span>
                        <span class="font-mono font-bold text-slate-800 dark:text-slate-200">{{ $payment->old_payment_id }}</span>
                    </div>
                    @endif
                    @if($payment->old_transaction_id)
                    <div class="flex justify-between items-center pb-2 border-b border-slate-200/60 dark:border-slate-800/60">
                        <span class="text-slate-500 dark:text-slate-400">ID Transaksi Lama:</span>
                        <span class="font-mono font-bold text-slate-800 dark:text-slate-200">{{ $payment->old_transaction_id }}</span>
                    </div>
                    @endif
                    @if($payment->old_request_id)
                    <div class="flex justify-between items-center">
                        <span class="text-slate-500 dark:text-slate-400">ID Permintaan Lama:</span>
                        <span class="font-mono font-bold text-slate-800 dark:text-slate-200">{{ $payment->old_request_id }}</span>
                    </div>
                    @endif
                </div>
            </div>
            @endif
        </div>

        <!-- TAB PANE 2: Bukti Pembayaran -->
        <div id="pane-proof" class="hidden p-5 sm:p-6 space-y-4">
            <h3 class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">Bukti Pembayaran</h3>
            
            @if($payment->proof_file)
                <div class="p-6 bg-slate-50/80 dark:bg-slate-800/40 rounded-lg border border-dashed border-slate-300 dark:border-slate-700 text-center space-y-3 max-w-md">
                    <svg class="h-10 w-10 text-sky-600 dark:text-sky-400 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <div>
                        <p class="text-xs font-semibold text-slate-800 dark:text-slate-200">Lampiran Ter-upload</p>
                        <p class="text-[10px] text-slate-400 font-mono mt-0.5 truncate">{{ $payment->proof_file }}</p>
                    </div>
                    <a href="{{ asset('storage/' . $payment->proof_file) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-lg text-xs font-semibold shadow-sm transition-colors">
                        Lihat Bukti Pembayaran
                    </a>
                </div>
            @else
                <div class="p-6 bg-slate-50/80 dark:bg-slate-800/40 rounded-lg border border-slate-200 dark:border-slate-800 text-center text-xs text-slate-500 dark:text-slate-400">
                    Bukti pembayaran belum diupload.
                </div>
            @endif
        </div>

        <!-- TAB PANE 3: Timeline & Audit Log -->
        @if(auth()->user()->hasPermission('audit_logs.view'))
        <div id="pane-audit" class="hidden p-5 sm:p-6 space-y-6">
            <div class="space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Riwayat Audit Pembayaran</h3>
                
                @if($payment->relationLoaded('auditLogs') && $payment->auditLogs->count() > 0)
                    <div class="overflow-x-auto custom-scrollbar">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-50/80 dark:bg-slate-800/40 border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 uppercase tracking-wider text-[10px]">
                                    <th class="px-4 py-3 font-semibold">Waktu</th>
                                    <th class="px-4 py-3 font-semibold">Aksi</th>
                                    <th class="px-4 py-3 font-semibold">User</th>
                                    <th class="px-4 py-3 font-semibold">Data Sebelum</th>
                                    <th class="px-4 py-3 font-semibold">Data Sesudah</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                                @foreach($payment->auditLogs as $auditLog)
                                    <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/30 transition-colors">
                                        <td class="px-4 py-3 font-mono text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                            {{ optional($auditLog->created_at)->format('d/m/Y H:i') }}
                                        </td>
                                        <td class="px-4 py-3 font-semibold text-slate-900 dark:text-slate-100 whitespace-nowrap">
                                            {{ ucwords(str_replace('_', ' ', $auditLog->action)) }}
                                        </td>
                                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-200 whitespace-nowrap">
                                            {{ $auditLog->user->name ?? '-' }}
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <pre class="max-w-xs whitespace-pre-wrap break-words text-[10px] text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 rounded p-2">{{ $auditLog->old_values ? json_encode($auditLog->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '-' }}</pre>
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <pre class="max-w-xs whitespace-pre-wrap break-words text-[10px] text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 rounded p-2">{{ $auditLog->new_values ? json_encode($auditLog->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '-' }}</pre>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-xs text-slate-500 dark:text-slate-400">Belum ada riwayat audit pembayaran.</p>
                @endif
            </div>
        </div>
        @endif

    </div>
</div>

@can('payments.reject')
    @if($payment->payment_status->value === 'valid')
        {{-- Form asli tetap POST+redirect biasa (bukan AJAX) — dialognya cuma
             ambil alasan lalu men-submit form tersembunyi ini, numpang
             window.Dialog (components/dialog.blade.php) seperti pola
             window.confirmTicketAction di tickets/partials/action-dialog.blade.php,
             bukan modal bikinan sendiri per halaman. --}}
        <form id="rejectForm" method="POST" action="{{ route('payments.reject', $payment->id) }}" class="hidden no-print">
            @csrf
            <input type="hidden" name="reject_reason" id="reject_reason_hidden" value="{{ old('reject_reason') }}">
        </form>
    @endif
@endcan

<script>
    function openRejectDialog() {
        window.Dialog.show({
            title: 'Tolak Pembayaran {{ $payment->payment_number }}',
            icon: 'error',
            contentHtml: `
                <p class="mb-3 text-xs text-text-secondary">Tagihan akan dihitung ulang setelah pembayaran ini ditolak. Tindakan ini butuh alasan.</p>
                <label for="reject-reason-input" class="block text-xs font-semibold text-text-secondary mb-1.5">Alasan Penolakan *</label>
                <textarea id="reject-reason-input" rows="4" maxlength="1000" class="w-full text-sm rounded-lg border border-border bg-background p-2.5 text-text-main focus:outline-none focus:ring-2 focus:ring-red-500/30 focus:border-red-500" placeholder="Contoh: bukti transfer tidak valid / duplikat input.">{{ old('reject_reason') }}</textarea>
                <p id="reject-reason-error" class="hidden text-xs text-red-600 mt-1.5">Alasan wajib diisi.</p>
            `,
            buttons: [
                { text: 'Batal', type: 'secondary', onClick: () => window.Dialog.close() },
                {
                    text: 'Tolak Pembayaran', type: 'danger', onClick: (e) => {
                        const input = document.getElementById('reject-reason-input');
                        const reason = (input?.value || '').trim();

                        if (reason === '') {
                            document.getElementById('reject-reason-error')?.classList.remove('hidden');
                            const btn = e.currentTarget;
                            btn.disabled = false;
                            btn.classList.remove('opacity-50', 'cursor-not-allowed');
                            input?.focus();
                            return;
                        }

                        document.getElementById('reject_reason_hidden').value = reason;
                        document.getElementById('rejectForm').submit();
                    },
                },
            ],
        });

        setTimeout(() => document.getElementById('reject-reason-input')?.focus(), 350);
    }

    @if ($errors->has('reject_reason'))
        document.addEventListener('DOMContentLoaded', openRejectDialog);
    @endif

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

    function switchTab(tabKey) {
        const tabs = ['info', 'proof', 'audit'];
        tabs.forEach(key => {
            const btn = document.getElementById(`tab-${key}`);
            const pane = document.getElementById(`pane-${key}`);
            if (!btn || !pane) return;
            
            if (key === tabKey) {
                btn.className = 'py-3.5 border-b-2 border-sky-600 text-sky-600 dark:border-sky-400 dark:text-sky-400 font-semibold flex items-center gap-2 transition-all';
                pane.classList.remove('hidden');
            } else {
                btn.className = 'py-3.5 border-b-2 border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 font-medium flex items-center gap-2 transition-all';
                pane.classList.add('hidden');
            }
        });
    }

    function copyToClipboard(text, label) {
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
        window.Toast.success('Disalin', `${label} (${text}) berhasil disalin`);
    }
</script>
@endsection
