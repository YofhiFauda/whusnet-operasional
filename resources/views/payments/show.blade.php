@extends('layouts.app')

@section('title', 'Detail Pembayaran ' . $payment->payment_number . ' - Whusnet Operasional')
@section('page_title', 'Detail Pembayaran')
@section('breadcrumb_parent', 'Riwayat Transaksi Pembayaran')
@section('breadcrumb_parent_url', route('payments.index'))

@section('content')
@php
    $badgeClass = match($payment->payment_status->value ?? $payment->payment_status) {
        'valid' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
        'ditolak' => 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20',
        default => 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
    };
    $totalMoneyReceived = (float) $payment->amount + (float) $payment->overpay_amount;

    // Label alokasi dana harus ikut JENIS tagihan yang sebenarnya dibayar —
    // dulu di-hardcode "Pelunasan Tagihan Internet" walau invoice-nya MTN/
    // OTH/REL (bukan langganan internet sama sekali). Urutan sama dengan
    // badge jenis tagihan di invoices/show.blade.php: sub-nama manual >
    // kategori manual > jenis tagihan.
    $invoiceLabel = $payment->invoice?->manual_subtype_name
        ?? $payment->invoice?->manual_category?->label()
        ?? $payment->invoice?->invoice_type?->label()
        ?? 'Tagihan';
    $invoiceSubtitle = $payment->invoice?->description
        ?? $payment->invoice?->internetPackage?->name
        ?? 'Layanan ISP';
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

<!-- PRINT ONLY KWITANSI PEMBAYARAN SHEET -->
{{-- Markup isi kwitansi ada di partial bersama — SATU sumber yang sama
     dipakai payments/receipt.blade.php (ADHOC-94). Dulu dua halaman ini
     menyalin markup nyaris identik secara manual dan pelan-pelan
     menyimpang; sekarang cukup ubah partial-nya. --}}
<div class="print-only px-16 py-12 bg-white">
    @include('payments.partials.kwitansi')
</div>

<!-- SCREEN ONLY ENTERPRISE VIEW (Type B: Record Detail Page — Single Panel Architecture) -->
<div class="screen-only space-y-4 max-w-full pb-16">

    @if($payment->payment_status->value === 'ditolak')
        <div class="bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/60 rounded-lg p-3.5 text-xs text-rose-700 dark:text-rose-300">
            <div class="flex items-start gap-3">
                <div class="p-1.5 rounded-md bg-rose-600 text-white shrink-0 mt-0.5">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                <div class="space-y-0.5 min-w-0">
                    <h4 class="font-semibold text-rose-900 dark:text-rose-200 text-sm">Pembayaran ini telah dikembalikan</h4>
                    @if($payment->reject_reason)
                        <p class="text-xs text-rose-800 dark:text-rose-300">Alasan: <strong class="font-medium">{{ $payment->reject_reason }}</strong></p>
                    @endif
                    <p class="text-[11px] text-rose-600 dark:text-rose-400">
                        Dikembalikan oleh {{ $payment->rejecter->name ?? '-' }} pada {{ optional($payment->rejected_at)->format('d/m/Y H:i') }} WIB
                    </p>
                </div>
            </div>
        </div>
    @endif

    <!-- LAYER 1: NAKED PAGE HEADER (Card Budget = 0, no card background, no card border) -->
    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4 mb-1">
        <div class="space-y-1.5 flex-1 min-w-0">
            <!-- Row: Title, Technical ID Badge & Status -->
            <div class="flex items-center gap-2 sm:gap-2.5 flex-wrap">
                <a href="{{ route('payments.index') }}" class="p-1.5 text-text-muted hover:text-text-main rounded-lg hover:bg-surface-muted transition-colors" title="Kembali ke Daftar">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                
                <h1 class="text-lg sm:text-xl font-bold text-text-main tracking-tight leading-tight">
                    Detail Pembayaran
                </h1>

                <!-- Payment Technical ID Badge + Copy -->
                <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-surface-muted border border-border font-mono text-xs font-semibold text-text-main">
                    <span>{{ $payment->payment_number }}</span>
                    <button type="button" onclick="copyText('{{ $payment->payment_number }}', 'No. Pembayaran')" title="Salin Kode Pembayaran" class="text-text-muted hover:text-primary transition-colors cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                        </svg>
                    </button>
                </div>

                <!-- Status Badge -->
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $badgeClass }}">
                    <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                    <span>{{ $payment->payment_status->label() }}</span>
                </span>

                @if($installmentContext)
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $installmentContext['settles'] ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20' : 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20' }}">
                    {{ $installmentContext['settles'] ? 'Melunasi Tagihan' : 'Cicilan Ke-'.$installmentContext['number'] }}
                </span>
                @endif

                @if((float) $payment->overpay_amount > 0)
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20" title="Deposit lebih bayar dari pelanggan">
                    Lebih Bayar Rp {{ number_format((float) $payment->overpay_amount, 0, ',', '.') }}
                </span>
                @endif
            </div>

            <!-- Metadata Subtitle -->
            <p class="text-xs text-text-muted mt-1 leading-normal">
                Tercatat pada <strong class="text-text-main font-medium">{{ optional($payment->payment_date)->format('d/m/Y H:i') }} WIB</strong> • Diterima oleh <strong class="text-text-main font-medium">{{ $payment->receiver->name ?? 'System' }}</strong> • Lokasi Kas <strong class="text-text-main font-medium">POP {{ $payment->pop->name ?? '-' }}</strong>
            </p>
        </div>

        <!-- Right Action Toolbar -->
        <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap shrink-0">
            @if($payment->invoice_id)
            <a href="{{ route('invoices.show', $payment->invoice_id) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary hover:bg-primary-hover text-white rounded-lg text-xs font-medium transition-colors shadow-xs">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span>Detail Tagihan</span>
            </a>
            @endif

            <!-- Print Kwitansi Button -->
            <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-surface hover:bg-surface-muted text-text-main rounded-lg text-xs font-medium transition-colors border border-border shadow-xs cursor-pointer">
                <svg class="h-4 w-4 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                </svg>
                <span>Cetak Kwitansi</span>
            </button>

            {{-- Edit Pembayaran (ADHOC-108) --}}
            @can('payments.update')
                @if($payment->isEditable())
                <a href="{{ route('payments.edit', $payment->id) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-xs font-medium transition-colors shadow-xs">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"></path>
                    </svg>
                    <span>Edit Pembayaran</span>
                </a>
                @endif
            @endcan

            <!-- Kembalikan Button -->
            @can('payments.reject')
                @if($payment->payment_status->value === 'valid')
                <button type="button" onclick="openRejectModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-medium rounded-lg shadow-xs transition-colors cursor-pointer">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    <span>Kembalikan Pembayaran</span>
                </button>
                @endif
            @endcan
        </div>
    </div>

    <!-- LAYER 2: DETAIL PANEL (Strict Single Card Architecture — Card Budget = 1) -->
    <div class="bg-surface border border-border rounded-lg overflow-hidden shadow-xs">
        
        <!-- TOP SEGMENT: Customer Identity Strip (Flat, divided) -->
        <div class="p-4 border-b border-border bg-surface flex flex-col md:flex-row items-start md:items-center justify-between gap-3">
            <div class="flex items-start sm:items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-lg bg-primary/10 text-primary font-bold text-sm flex items-center justify-center shrink-0 border border-primary/20">
                    {{ strtoupper(substr($payment->customer->full_name ?? 'P', 0, 2)) }}
                </div>
                <div class="space-y-0.5 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-[10px] font-semibold uppercase tracking-wider text-primary bg-primary/10 px-2 py-0.5 rounded border border-primary/20">Identitas Pelanggan</span>
                        @if($payment->customer && ($payment->customer->cid || $payment->customer->customer_code))
                        <div class="inline-flex items-center gap-1 font-mono text-xs text-text-muted bg-surface-muted px-2 py-0.5 rounded border border-border">
                            <span>CID: {{ $payment->customer->cid ?? $payment->customer->customer_code }}</span>
                            <button type="button" onclick="copyText('{{ $payment->customer->cid ?? $payment->customer->customer_code }}', 'CID')" title="Salin CID" class="text-text-muted hover:text-primary cursor-pointer">
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                            </button>
                        </div>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        @if($payment->customer_id)
                        <a href="{{ route('customers.show', $payment->customer_id) }}" class="font-semibold text-text-main text-sm hover:text-primary transition-colors">
                            {{ $payment->customer->full_name ?? '-' }}
                        </a>
                        @else
                        <span class="font-semibold text-text-main text-sm">{{ $payment->customer->full_name ?? '-' }}</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-2.5 text-xs text-text-muted flex-wrap">
                        <div class="flex items-center gap-1 font-mono">
                            <svg class="w-3.5 h-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                            <span>{{ $payment->customer->primary_phone ?? $payment->customer->phone ?? '-' }}</span>
                        </div>
                        <span>•</span>
                        <div class="flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                            <span>{{ $payment->pop->name ?? '-' }}</span>
                        </div>
                        @if($payment->customer && $payment->customer->address)
                        <span>•</span>
                        <div class="flex items-center gap-1 max-w-sm truncate" title="{{ $payment->customer->address }}">
                            <svg class="w-3.5 h-3.5 text-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            <span class="truncate">{{ $payment->customer->address }}</span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Action: Full Profile Link -->
            @if($payment->customer_id)
            <div class="shrink-0 self-end md:self-center">
                <a href="{{ route('customers.show', $payment->customer_id) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-surface hover:bg-surface-muted text-text-main border border-border rounded-lg text-xs font-medium transition-colors shadow-xs">
                    <span>Profil Pelanggan</span>
                    <svg class="w-3.5 h-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                </a>
            </div>
            @endif
        </div>

        <!-- MIDDLE SEGMENT: Hairline Metric Strip (Flat bar with dividers, Card Budget preserved) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 divide-y sm:divide-y-0 sm:divide-x divide-border bg-surface-muted/30 border-b border-border">
            <!-- Metric 1: Nominal Diterima -->
            <div class="p-4 flex flex-col justify-between">
                <span class="text-[11px] font-semibold text-text-muted uppercase tracking-wider">Nominal Diterima</span>
                <div class="mt-2">
                    <div class="font-mono text-xl sm:text-2xl font-bold text-emerald-600 dark:text-emerald-400 tracking-tight tabular-nums">
                        Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}
                    </div>
                    @if((float) $payment->overpay_amount > 0)
                    <div class="text-[11px] font-medium text-sky-600 dark:text-sky-400 mt-0.5 flex items-center gap-1 font-mono">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                        <span>Rp {{ number_format((float) $payment->overpay_amount, 0, ',', '.') }} Overpay</span>
                    </div>
                    @else
                    <div class="text-[11px] text-text-muted mt-0.5">
                        Terverifikasi Sistem
                    </div>
                    @endif
                </div>
            </div>

            <!-- Metric 2: Tanggal & Metode -->
            <div class="p-4 flex flex-col justify-between">
                <span class="text-[11px] font-semibold text-text-muted uppercase tracking-wider">Tanggal & Metode</span>
                <div class="mt-2">
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-base font-bold text-text-main tabular-nums">
                            {{ optional($payment->payment_date)->format('d/m/Y') }}
                        </span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-mono font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 uppercase">
                            {{ strtoupper($payment->payment_method) }}
                        </span>
                    </div>
                    {{-- Rekening snapshot --}}
                    @if($payment->bank_name || $payment->account_number)
                        <div class="text-xs text-text-muted mt-1">
                            Rekening: <strong class="text-text-main font-medium">{{ $payment->bank_name }}</strong>
                            <span class="font-mono">{{ $payment->account_number }}</span>
                        </div>
                    @endif
                    {{-- Nama Pengirim (ADHOC-95) --}}
                    @if($payment->sender_name)
                        <div class="text-xs text-text-muted mt-0.5">
                            Pengirim: <strong class="text-text-main font-medium">{{ $payment->sender_name }}</strong>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Metric 3: Operator Kasir -->
            <div class="p-4 flex flex-col justify-between">
                <span class="text-[11px] font-semibold text-text-muted uppercase tracking-wider">Operator Kasir</span>
                <div class="mt-2">
                    <div class="font-semibold text-text-main text-sm truncate">
                        {{ $payment->receiver->name ?? '-' }}
                    </div>
                    <div class="text-xs text-text-muted mt-0.5">
                        Cabang / POP: <strong class="text-text-main font-medium">{{ $payment->pop->name ?? '-' }}</strong>
                    </div>
                </div>
            </div>

            <!-- Metric 4: Kolektor Lapangan -->
            <div class="p-4 flex flex-col justify-between">
                <span class="text-[11px] font-semibold text-text-muted uppercase tracking-wider">Kolektor</span>
                <div class="mt-2">
                    <div class="font-semibold text-text-main text-sm truncate">
                        {{ $payment->collector ? $payment->collector->name : 'Bayar Langsung' }}
                    </div>
                    <div class="mt-1">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-surface-muted text-text-muted border border-border">
                            {{ $payment->collector ? ($payment->collected_by_role === \App\Enums\CollectorRole::TEKNISI->value ? 'Teknisi Lapangan' : 'Kolektor Lapangan') : 'Tanpa Kolektor Lapangan' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- BOTTOM SEGMENT: Main 2-Column Joined Area (Divided with hairline) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 divide-y lg:divide-y-0 lg:divide-x divide-border items-stretch">
            
            <!-- LEFT MAIN TABBED CONTENT (8 cols) -->
            <div class="lg:col-span-8 flex flex-col min-w-0">
                
                <!-- Tab Bar Header -->
                <div class="border-b border-border px-4 flex items-center gap-4 text-xs bg-surface-muted/30 overflow-x-auto">
                    <button type="button" onclick="switchTab('info')" id="tab-info" class="py-3 border-b-2 border-primary text-primary font-semibold flex items-center gap-1.5 transition-colors shrink-0 cursor-pointer">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>Informasi & Catatan</span>
                    </button>
                    <button type="button" onclick="switchTab('proof')" id="tab-proof" class="py-3 border-b-2 border-transparent text-text-muted hover:text-text-main font-medium flex items-center gap-1.5 transition-colors shrink-0 cursor-pointer">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <span>Bukti Pembayaran</span>
                    </button>
                    @if(auth()->user()->hasPermission('audit_logs.view') || auth()->user()->hasRole('admin'))
                    <button type="button" onclick="switchTab('audit')" id="tab-audit" class="py-3 border-b-2 border-transparent text-text-muted hover:text-text-main font-medium flex items-center gap-1.5 transition-colors shrink-0 cursor-pointer">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>Timeline & Audit Log</span>
                    </button>
                    @endif
                </div>

                <!-- TAB PANE 1: Informasi & Catatan -->
                <div id="pane-info" class="p-5 space-y-5 flex-1 flex flex-col justify-between">
                    
                    <div class="space-y-5">
                        <!-- Financial Breakdown Itemization -->
                        <div>
                            <h3 class="text-xs font-semibold text-text-muted uppercase tracking-wider mb-2.5">Rincian Pembagian Alokasi Dana</h3>
                            <div class="border border-border rounded-lg overflow-hidden">
                                <table class="w-full text-left border-collapse text-xs">
                                    <thead class="bg-surface-muted/50 border-b border-border text-text-muted uppercase tracking-wider font-semibold text-[10px]">
                                        <tr>
                                            <th class="p-3">Deskripsi Alokasi</th>
                                            <th class="p-3">Referensi / Keterangan</th>
                                            <th class="p-3 text-right">Nominal</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border font-medium">
                                        <tr>
                                            <td class="p-3">
                                                <span class="font-semibold text-text-main block">Pelunasan {{ $invoiceLabel }}</span>
                                                <span class="text-[10px] text-text-muted">{{ $invoiceSubtitle }}</span>
                                            </td>
                                            <td class="p-3 font-mono text-text-muted">
                                                {{ $payment->invoice->invoice_number ?? '-' }}
                                            </td>
                                            <td class="p-3 text-right font-mono font-semibold text-text-main tabular-nums">
                                                Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}
                                            </td>
                                        </tr>
                                        @if((float) $payment->overpay_amount > 0)
                                        <tr class="bg-sky-500/5">
                                            <td class="p-3">
                                                <span class="font-semibold text-sky-700 dark:text-sky-300 block">Alokasi Lebih Bayar (Deposit Pelanggan)</span>
                                                <span class="text-[10px] text-sky-600 dark:text-sky-400">Disimpan untuk pemotongan tagihan berikutnya</span>
                                            </td>
                                            <td class="p-3 font-mono text-sky-600 dark:text-sky-400">
                                                Overpay / Saldo
                                            </td>
                                            <td class="p-3 text-right font-mono font-semibold text-sky-600 dark:text-sky-400 tabular-nums">
                                                Rp {{ number_format((float) $payment->overpay_amount, 0, ',', '.') }}
                                            </td>
                                        </tr>
                                        @endif
                                    </tbody>
                                    <tfoot class="bg-surface-muted/40 border-t border-border font-semibold">
                                        <tr>
                                            <td colspan="2" class="p-3 text-text-main text-xs">TOTAL UANG DITERIMA DARI PELANGGAN</td>
                                            <td class="p-3 text-right font-mono text-sm text-emerald-600 dark:text-emerald-400 tabular-nums">
                                                Rp {{ number_format($totalMoneyReceived, 0, ',', '.') }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <!-- Remarks / Notes Box -->
                        <div class="space-y-1.5">
                            <h3 class="text-xs font-semibold text-text-muted uppercase tracking-wider">Catatan Petugas</h3>
                            <div class="p-3.5 bg-surface-muted/40 rounded-lg border border-border text-xs text-text-main leading-relaxed font-sans">
                                {{ $payment->note ?: 'Tidak ada catatan khusus untuk transaksi ini.' }}
                            </div>
                        </div>

                        @if($payment->old_payment_id || $payment->old_transaction_id || $payment->old_request_id)
                        <div class="space-y-1.5">
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-text-muted">Audit Visibilitas Data Migrasi Legacy</h3>
                            <div class="p-3.5 bg-surface-muted/40 rounded-lg border border-border space-y-2 text-xs">
                                @if($payment->old_payment_id)
                                <div class="flex justify-between items-center pb-2 border-b border-border">
                                    <span class="text-text-muted">ID Bayar Lama:</span>
                                    <span class="font-mono font-semibold text-text-main">{{ $payment->old_payment_id }}</span>
                                </div>
                                @endif
                                @if($payment->old_transaction_id)
                                <div class="flex justify-between items-center pb-2 border-b border-border">
                                    <span class="text-text-muted">ID Transaksi Lama:</span>
                                    <span class="font-mono font-semibold text-text-main">{{ $payment->old_transaction_id }}</span>
                                </div>
                                @endif
                                @if($payment->old_request_id)
                                <div class="flex justify-between items-center">
                                    <span class="text-text-muted">ID Permintaan Lama:</span>
                                    <span class="font-mono font-semibold text-text-main">{{ $payment->old_request_id }}</span>
                                </div>
                                @endif
                            </div>
                        </div>
                        @endif
                    </div>

                </div>

                <!-- TAB PANE 2: Bukti Pembayaran -->
                <div id="pane-proof" class="hidden p-5 space-y-4 flex-1">
                    <h3 class="text-xs font-semibold text-text-muted uppercase tracking-wider">Lampiran Bukti Pembayaran / Struk Transfer</h3>
                    
                    @if($payment->proof_file)
                        <div class="p-6 bg-surface-muted/30 border border-dashed border-border rounded-lg text-center space-y-3">
                            <div class="w-10 h-10 rounded-lg bg-primary/10 text-primary flex items-center justify-center mx-auto border border-primary/20">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="font-semibold text-sm text-text-main">Lampiran Bukti Terdaftar</p>
                                <p class="text-xs font-mono text-text-muted max-w-sm mx-auto mt-0.5 truncate">{{ $payment->proof_file }}</p>
                            </div>
                            <a href="{{ asset('storage/' . $payment->proof_file) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary hover:bg-primary-hover text-white rounded-lg text-xs font-medium transition-colors shadow-xs">
                                <span>Lihat Bukti Pembayaran</span>
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                            </a>
                        </div>
                    @else
                        <!-- Proof Placeholder Box -->
                        <div class="p-6 border border-dashed border-border rounded-lg text-center bg-surface-muted/30 space-y-2">
                            <div class="w-10 h-10 rounded-lg bg-surface-muted text-text-muted flex items-center justify-center mx-auto border border-border">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="font-semibold text-xs text-text-main">
                                    {{ strtolower($payment->payment_method) === 'cash' ? 'Pembayaran Tunai Langsung Kasir' : 'Belum Ada Lampiran File Bukti' }}
                                </p>
                                <p class="text-[11px] text-text-muted max-w-sm mx-auto mt-0.5">
                                    Transaksi tunai kasir POP tidak mewajibkan upload foto fisik, namun kwitansi resmi dapat dicetak kapan saja.
                                </p>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- TAB PANE 3: Timeline & Audit Log -->
                @if(auth()->user()->hasPermission('audit_logs.view') || auth()->user()->hasRole('admin'))
                <div id="pane-audit" class="hidden p-5 space-y-3.5 flex-1">
                    <h3 class="text-xs font-semibold text-text-muted uppercase tracking-wider">Riwayat Audit Pembayaran</h3>
                    
                    @if($payment->relationLoaded('auditLogs') && $payment->auditLogs->count() > 0)
                        <div class="border border-border rounded-lg overflow-hidden">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="bg-surface-muted/50 border-b border-border text-text-muted uppercase tracking-wider text-[10px] font-semibold">
                                        <th class="p-2.5">Waktu</th>
                                        <th class="p-2.5">Aksi</th>
                                        <th class="p-2.5">User Operator</th>
                                        <th class="p-2.5">Detail Perubahan Data</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border font-medium">
                                    @foreach($payment->auditLogs as $index => $auditLog)
                                    <tr>
                                        <td class="p-2.5 font-mono text-text-muted whitespace-nowrap tabular-nums">
                                            {{ optional($auditLog->created_at)->format('d/m/Y H:i') }}
                                        </td>
                                        <td class="p-2.5">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                                {{ ucwords(str_replace('_', ' ', $auditLog->action)) }}
                                            </span>
                                        </td>
                                        <td class="p-2.5 font-medium text-text-main">
                                            {{ $auditLog->user->name ?? '-' }}
                                        </td>
                                        <td class="p-2.5">
                                            <button type="button" onclick="toggleJsonView('json-{{ $index }}')" class="text-primary hover:underline font-mono text-[11px] cursor-pointer">
                                                [+] Lihat JSON Payload
                                            </button>
                                            <pre id="json-{{ $index }}" class="hidden mt-2 p-3 bg-slate-900 text-emerald-400 font-mono text-[10px] rounded-lg overflow-x-auto custom-scrollbar">{{ json_encode([
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
                        <div class="p-4 bg-surface-muted/30 rounded-lg border border-border text-xs text-text-muted text-center">
                            Belum ada entri audit log untuk transaksi pembayaran ini.
                        </div>
                    @endif
                </div>
                @endif

            </div>

            <!-- RIGHT DETAILS PANEL (4 cols) -->
            <div class="lg:col-span-4 flex flex-col p-5 flex-1 justify-between bg-surface-muted/10">
                <div>
                    <div class="flex items-center justify-between pb-3 border-b border-border">
                        <span class="text-xs font-semibold text-text-muted uppercase tracking-wider">Tagihan Terkait</span>
                        @if($payment->invoice_id)
                        <a href="{{ route('invoices.show', $payment->invoice_id) }}" class="text-xs font-medium text-primary hover:underline flex items-center gap-1">
                            <span>Lihat Tagihan</span>
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </a>
                        @endif
                    </div>

                    @if($payment->invoice)
                    <div class="space-y-3 text-xs pt-3.5">
                        <div class="flex items-center justify-between">
                            <span class="text-text-muted">No. Invoice:</span>
                            <a href="{{ route('invoices.show', $payment->invoice_id) }}" class="font-mono font-semibold text-primary hover:underline">
                                {{ $payment->invoice->invoice_number }}
                            </a>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-text-muted">Periode Tagihan:</span>
                            <span class="font-mono font-medium text-text-main">{{ $payment->invoice->billing_period ?? '-' }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-text-muted">Total Invoice:</span>
                            <span class="font-mono font-medium text-text-main tabular-nums">Rp {{ number_format((float) ($payment->invoice->total_amount ?? 0), 0, ',', '.') }}</span>
                        </div>
                    </div>
                    @else
                    <div class="pt-3.5">
                        <p class="text-xs text-text-muted italic">Tidak terhubung ke invoice tertentu.</p>
                    </div>
                    @endif
                </div>

                @if($payment->invoice)
                <div class="pt-3.5 mt-5 border-t border-border">
                    <div class="p-3 rounded-lg bg-surface-muted/50 border border-border flex items-center justify-between">
                        <span class="text-xs text-text-muted font-medium">Sisa Tagihan:</span>
                        <span class="font-mono font-bold text-sm {{ (float)($payment->invoice->remaining_amount ?? 0) > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }} tabular-nums">
                            Rp {{ number_format((float) ($payment->invoice->remaining_amount ?? 0), 0, ',', '.') }} {{ (float)($payment->invoice->remaining_amount ?? 0) == 0 ? '(Lunas)' : '' }}
                        </span>
                    </div>
                </div>
                @endif
            </div>
        </div>
        
    </div>

</div>

<!-- MODAL: TOLAK PEMBAYARAN -->
@can('payments.reject')
    @if($payment->payment_status->value === 'valid')
    <div id="rejectModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs hidden flex items-center justify-center p-4">
        <div class="bg-surface rounded-lg max-w-md w-full overflow-hidden shadow-lg border border-border">
            <div class="px-5 py-3.5 border-b border-border flex items-center justify-between bg-rose-500/10">
                <div class="flex items-center gap-2.5">
                    <div class="p-1.5 rounded-md bg-rose-600 text-white shrink-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-text-main text-sm">Kembalikan Pembayaran {{ $payment->payment_number }}</h3>
                        <p class="text-[10px] text-text-muted">Tagihan akan dihitung ulang & kembali belum lunas</p>
                    </div>
                </div>
                <button type="button" onclick="closeRejectModal()" class="p-1 text-text-muted hover:text-text-main rounded-md cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form id="rejectForm" method="POST" action="{{ route('payments.reject', $payment->id) }}" class="p-5 space-y-3.5 text-xs">
                @csrf
                <p class="text-text-muted leading-relaxed">
                    Mengembalikan transaksi ini membatalkan status pelunasan invoice terkait dan membalikkan alokasi deposit. Kalau pembayarannya dari bulan yang sudah tutup buku, laporan bulan itu tidak berubah — pengembaliannya tercatat di bulan ini.
                </p>

                <div>
                    <label class="block font-medium text-text-main mb-1.5">Alasan Pengembalian *</label>
                    <textarea name="reject_reason" id="rejectReasonInput" rows="3" required class="w-full p-2.5 rounded-lg border border-border bg-surface text-xs text-text-main focus:outline-none focus:border-rose-500 focus:ring-1 focus:ring-rose-500" placeholder="Contoh: Bukti transfer tidak sah / Duplikasi input kasir / Rekonsiliasi kas tidak sesuai">{{ old('reject_reason') }}</textarea>
                    @error('reject_reason')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Modal Action Buttons -->
                <div class="pt-3 border-t border-border flex items-center justify-end gap-2">
                    <button type="button" onclick="closeRejectModal()" class="px-3 py-1.5 rounded-lg border border-border font-medium text-text-muted hover:bg-surface-muted cursor-pointer transition-colors">
                        Batal
                    </button>
                    <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white font-medium shadow-xs transition-colors cursor-pointer">
                        Ya, Kembalikan Pembayaran
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
@endcan

<script>
    function switchTab(tabKey) {
        const tabs = ['info', 'proof', 'audit'];
        tabs.forEach(key => {
            const btn = document.getElementById(`tab-${key}`);
            const pane = document.getElementById(`pane-${key}`);
            if (!btn || !pane) return;

            if (key === tabKey) {
                btn.className = 'py-3 border-b-2 border-primary text-primary font-semibold flex items-center gap-1.5 transition-colors shrink-0 cursor-pointer';
                pane.classList.remove('hidden');
            } else {
                btn.className = 'py-3 border-b-2 border-transparent text-text-muted hover:text-text-main font-medium flex items-center gap-1.5 transition-colors shrink-0 cursor-pointer';
                pane.classList.add('hidden');
            }
        });
    }

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
