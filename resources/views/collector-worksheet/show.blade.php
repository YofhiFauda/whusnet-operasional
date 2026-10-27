@extends('layouts.app')

@section('title', 'Kolektor: ' . $collector->name . ' - Whusnet Operasional')
@section('page_title', $collector->name)

@section('content')
    @include('partials.collector-realtime', ['channels' => $activityChannels, 'audiens' => 'admin'])

<div class="space-y-4 max-w-full pb-16" id="live-content">
    {{-- LAYER 1: NAKED PAGE HEADER (Card Budget = 0) --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-1">
        <div class="space-y-1 min-w-0">
            <div class="flex items-center gap-2 text-xs text-text-muted font-medium">
                <span>Operasional</span>
                <svg class="w-3 h-3 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <a href="{{ route('collector-worksheet.index') }}" class="hover:text-primary transition-colors">Worksheet Admin</a>
                <svg class="w-3 h-3 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-primary font-semibold truncate">{{ $collector->name }}</span>
            </div>
            <h1 class="text-lg sm:text-xl font-bold text-text-main tracking-tight leading-tight">
                Kolektor — {{ $collector->name }}
            </h1>
            <p class="text-xs text-text-muted leading-normal">
                Cross check pembayaran, verifikasi setoran kas, dan atur rute penugasan pelanggan.
            </p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <a href="{{ route('collector-worksheet.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-border bg-surface hover:bg-surface-muted text-text-main rounded-lg transition-colors text-xs font-medium shadow-xs">
                <svg class="w-3.5 h-3.5 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span>Worksheet Admin</span>
            </a>
        </div>
    </div>

    {{-- Error & Success Alerts (Naked) --}}
    @if ($errors->any())
        <x-ui.alert variant="error" title="Aksi Ditolak" class="rounded-lg">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </x-ui.alert>
    @endif

    @if (session('success'))
        <x-ui.alert variant="success" class="rounded-lg">{{ session('success') }}</x-ui.alert>
    @endif

    <div id="batch-alert" class="hidden text-xs sm:text-sm rounded-lg p-3.5"></div>

    {{-- LAYER 2: DETAIL PANEL (Strict Single Panel Architecture — Card Budget = 1) --}}
    <div class="bg-surface border border-border rounded-lg overflow-hidden shadow-xs">
        
        {{-- TOP STRIP: Collector Identity Header --}}
        <div class="p-4 border-b border-border bg-surface flex flex-col md:flex-row items-start md:items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="h-10 w-10 rounded-lg bg-primary/10 text-primary border border-primary/20 font-bold text-base flex items-center justify-center shrink-0">
                    {{ strtoupper(substr($collector->name, 0, 1)) }}
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-[10px] font-semibold uppercase tracking-wider text-primary bg-primary/10 px-2 py-0.5 rounded border border-primary/20">Kolektor</span>
                        @if($collector->status === 'active')
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                Aktif
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-surface-muted text-text-muted border border-border">
                                <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                Nonaktif
                            </span>
                        @endif
                        <span class="text-xs text-text-muted font-mono">ID: #{{ $collector->id }}</span>
                    </div>
                    <h2 class="font-semibold text-sm sm:text-base text-text-main truncate mt-0.5">{{ $collector->name }}</h2>
                </div>
            </div>

            <div class="flex items-center gap-2 text-xs text-text-muted">
                <span>Pelanggan Ter-assign:</span>
                <span class="font-bold text-text-main font-mono">{{ $assignedCustomers->total() }} Pelanggan</span>
            </div>
        </div>

        {{-- MIDDLE STRIP: Hairline Metric Strip with ADHOC-191 Setor Action --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 divide-y sm:divide-y-0 sm:divide-x divide-border bg-surface-muted/30 border-b border-border">
            {{-- Metric 1: Pelanggan Ter-assign --}}
            <div class="p-4 flex flex-col justify-between">
                <span class="text-[11px] font-semibold text-text-muted uppercase tracking-wider">Penugasan Rute</span>
                <div class="mt-2">
                    <div class="font-mono text-xl sm:text-2xl font-bold text-text-main tabular-nums">
                        {{ $assignedCustomers->total() }}
                    </div>
                    <div class="text-[11px] text-text-muted mt-1">
                        Pelanggan aktif dalam rute tagihan
                    </div>
                </div>
            </div>

            {{-- Metric 2: Saldo Belum Disetor + Aksi Setor (ADHOC-191) --}}
            <div class="p-4 flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-semibold text-text-muted uppercase tracking-wider">Saldo Belum Disetor</span>
                    <div class="w-6 h-6 rounded-md bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0 border border-emerald-500/20">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                </div>
                <div class="mt-2">
                    <div class="font-mono text-xl sm:text-2xl font-bold text-emerald-600 dark:text-emerald-400 tabular-nums">
                        Rp {{ number_format($balance, 0, ',', '.') }}
                    </div>

                    {{-- Aksi Setor Seluruh Saldo (ADHOC-191) --}}
                    @if (auth()->user()->hasPermission('collector_worksheet.deposit'))
                        @if ($balance > 0)
                            <form action="{{ route('collector-worksheet.deposit', $collector->id) }}" method="POST" class="mt-2.5"
                                  data-confirm="Setorkan seluruh saldo {{ $collector->name }} sebesar Rp{{ number_format($balance, 0, ',', '.') }} atas nama dia ke Anda? Pakai ini HANYA kalau kolektornya tak bisa menyetor sendiri lewat Worklist. Saldonya jadi nol dan menunggu verifikasi.">
                                @csrf
                                <input type="hidden" name="idempotency_key" value="{{ 'deposit-'.$collector->id.'-by-'.auth()->id().'-'.now()->timestamp.'-'.\Illuminate\Support\Str::random(8) }}">
                                <button type="submit" class="inline-flex items-center justify-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-xs px-3 py-1.5 rounded-lg transition-colors shadow-xs active:scale-95 cursor-pointer">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                    <span>Setor Atas Nama Kolektor</span>
                                </button>
                            </form>
                            <div class="mt-1 text-[11px] text-text-muted">
                                Uang tagihan tunai yang masih di tangan kolektor.
                            </div>
                        @else
                            <div class="mt-2.5">
                                <button type="button" disabled class="inline-flex items-center justify-center gap-1.5 bg-surface-muted text-text-muted border border-border text-xs font-medium px-3 py-1.5 rounded-lg cursor-not-allowed">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                    <span>Setor Atas Nama Kolektor</span>
                                </button>
                            </div>
                            <div class="mt-1 text-[11px] text-text-muted">
                                Belum ada saldo yang dapat disetor.
                            </div>
                        @endif
                    @else
                        <div class="mt-2 text-[11px] text-text-muted">
                            Anda tidak memiliki izin menyetor saldo Kolektor.
                        </div>
                    @endif
                </div>
            </div>

            {{-- Metric 3: Kurang Setor --}}
            <div class="p-4 flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-semibold uppercase tracking-wider {{ $outstandingShortfall > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-text-muted' }}">
                        Kurang Setor
                    </span>
                    <div class="w-6 h-6 rounded-md {{ $outstandingShortfall > 0 ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20' : 'bg-surface-muted text-text-muted border border-border' }} flex items-center justify-center shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                </div>
                <div class="mt-2">
                    <div class="font-mono text-xl sm:text-2xl font-bold tabular-nums {{ $outstandingShortfall > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-text-main' }}">
                        Rp {{ number_format($outstandingShortfall, 0, ',', '.') }}
                    </div>
                    <div class="mt-1 text-[11px] {{ $outstandingShortfall > 0 ? 'text-rose-600/90 dark:text-rose-400/90 font-medium' : 'text-text-muted' }}">
                        {{ $outstandingShortfall > 0 ? 'Kewajiban selisih yang belum ditutup — tidak otomatis nol saat setor.' : 'Tidak ada kewajiban selisih kas.' }}
                    </div>
                </div>
            </div>
        </div>

        {{-- STRIP 3: Integrated Tab Navigation Bar --}}
        <div class="border-b border-border bg-surface-muted/30 px-4 flex gap-4 overflow-x-auto custom-scrollbar text-xs">
            <a href="{{ route('collector-worksheet.show', ['collector' => $collector->id, 'tab' => 'pembayaran']) }}"
               class="py-3 font-semibold border-b-2 -mb-px transition-colors inline-flex items-center gap-1.5 shrink-0 {{ $tab === 'pembayaran' ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-text-main' }}">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                <span>Pembayaran</span>
            </a>
            <a href="{{ route('collector-worksheet.show', ['collector' => $collector->id, 'tab' => 'sudah_bayar']) }}"
               class="py-3 font-semibold border-b-2 -mb-px transition-colors inline-flex items-center gap-1.5 shrink-0 {{ $tab === 'sudah_bayar' ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-text-main' }}">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>Sudah Bayar ({{ $unsettledCount }})</span>
            </a>
            <a href="{{ route('collector-worksheet.show', ['collector' => $collector->id, 'tab' => 'setoran']) }}"
               class="py-3 font-semibold border-b-2 -mb-px transition-colors inline-flex items-center gap-1.5 shrink-0 {{ $tab === 'setoran' ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-text-main' }}">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Setoran</span>
            </a>
            <a href="{{ route('collector-worksheet.show', ['collector' => $collector->id, 'tab' => 'kunjungan']) }}"
               class="py-3 font-semibold border-b-2 -mb-px transition-colors inline-flex items-center gap-1.5 shrink-0 {{ $tab === 'kunjungan' ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-text-main' }}">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span>Kunjungan</span>
            </a>
            <a href="{{ route('collector-worksheet.show', ['collector' => $collector->id, 'tab' => 'kwitansi']) }}"
               class="py-3 font-semibold border-b-2 -mb-px transition-colors inline-flex items-center gap-1.5 shrink-0 {{ $tab === 'kwitansi' ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-text-main' }}">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                <span>Kwitansi</span>
            </a>
            <a href="{{ route('collector-worksheet.show', ['collector' => $collector->id, 'tab' => 'assign']) }}"
               class="py-3 font-semibold border-b-2 -mb-px transition-colors inline-flex items-center gap-1.5 shrink-0 {{ $tab === 'assign' ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-text-main' }}">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                <span>Atur Pelanggan</span>
            </a>
        </div>

        {{-- TAB CONTENTS AREA --}}
        <div class="p-4 sm:p-5">
            @if ($tab === 'pembayaran')
                {{-- ============ TAB: PEMBAYARAN ============ --}}
                <div class="space-y-4">
                    <div class="p-3.5 bg-sky-500/10 border border-sky-500/20 rounded-lg text-xs text-sky-800 dark:text-sky-300">
                        Seluruh tagihan belum dibayar kolektor ini — tanpa filter jatuh tempo, supaya cross check melihat gambaran penuh. Jendela tagih hanya berlaku di Worklist kolektor.
                    </div>

                    <form action="{{ route('collector-worksheet.show', $collector->id) }}" method="GET" class="flex gap-2">
                        <input type="hidden" name="tab" value="pembayaran">
                        <div class="relative flex-1 max-w-md">
                            <input type="text" name="invoice_search" value="{{ $invoiceSearch }}" placeholder="Cari nama atau CID pelanggan..."
                                   class="w-full text-xs pl-8 pr-3 py-2 border border-border rounded-lg bg-surface text-text-main focus:outline-none focus:border-primary">
                            <svg class="w-3.5 h-3.5 text-text-muted absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                        <button type="submit" class="bg-primary hover:bg-primary-hover text-white text-xs font-medium py-2 px-3.5 rounded-lg transition-colors cursor-pointer shrink-0 shadow-xs">
                            Cari
                        </button>
                        @if ($invoiceSearch !== '')
                            <a href="{{ route('collector-worksheet.show', ['collector' => $collector->id, 'tab' => 'pembayaran']) }}"
                               class="inline-flex items-center px-3 text-xs text-rose-600 dark:text-rose-400 hover:underline font-medium shrink-0">
                                Reset
                            </a>
                        @endif
                    </form>

                    @include('partials.collector-pay-table', [
                        'invoices' => $invoices,
                        'emptyMessage' => 'Kolektor ini tidak memiliki pelanggan dengan tagihan belum dibayar.',
                    ])

                    @push('scripts')
                        @include('partials.collector-pay-script', [
                            'storeUrl' => route('payment-batches.store', $collector->id),
                            'keyPrefix' => 'admin-batch-' . $collector->id,
                            'colspan' => 9,
                            'emptyMessage' => 'Kolektor ini tidak memiliki pelanggan dengan tagihan belum dibayar.',
                        ])
                    @endpush
                </div>

            @elseif ($tab === 'sudah_bayar')
                {{-- ============ TAB: SUDAH BAYAR ============ --}}
                <div class="space-y-4">
                    <div class="p-3.5 bg-emerald-500/10 border border-emerald-500/20 rounded-lg text-xs text-emerald-800 dark:text-emerald-300">
                        Sudah ditagih kolektor ini tapi belum ikut setoran mana pun — persis yang akan tersetor kalau tombol "Setor Atas Nama Kolektor" di kartu profil ditekan sekarang.
                    </div>

                    <div class="border border-border rounded-lg overflow-hidden shadow-xs">
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse text-left text-sm text-text-main min-w-0 sm:min-w-full">
                                <thead class="hidden sm:table-header-group">
                                    <tr class="bg-surface-muted/50 border-b border-border text-text-muted font-semibold text-xs uppercase tracking-wider">
                                        <th class="px-5 py-3">Pelanggan</th>
                                        <th class="px-5 py-3">No. Pembayaran</th>
                                        <th class="px-5 py-3">Metode</th>
                                        <th class="px-5 py-3">Tgl Ditagih</th>
                                        <th class="px-5 py-3">Keterangan</th>
                                        <th class="px-5 py-3 text-right">Nominal</th>
                                    </tr>
                                </thead>
                                <tbody class="block sm:table-row-group divide-y-0 sm:divide-y p-3 sm:p-0 space-y-3 sm:space-y-0 divide-border font-medium">
                                    @forelse ($unsettledPayments as $payment)
                                        @php $periodType = $payment->periodType(); @endphp
                                        <tr class="block sm:table-row bg-surface sm:bg-transparent rounded-lg sm:rounded-none border border-border sm:border-x-0 sm:border-t-0 sm:border-b p-3.5 sm:p-0 shadow-xs sm:shadow-none hover:bg-surface-muted/40 transition-colors">
                                            <td class="block sm:table-cell px-0 sm:px-5 py-1 sm:py-3.5">
                                                <div class="font-semibold text-text-main text-xs sm:text-sm">{{ $payment->customer->full_name ?? '-' }}</div>
                                                <div class="text-[10px] text-text-muted font-mono">{{ $payment->customer->cid ?? $payment->customer->customer_code ?? '-' }}</div>
                                            </td>
                                            <td class="block sm:table-cell px-0 sm:px-5 py-1 sm:py-3.5 font-mono text-xs">
                                                <span class="sm:hidden text-[10px] font-semibold text-text-muted uppercase tracking-wider block">No. Pembayaran:</span>
                                                {{ $payment->payment_number }}
                                            </td>
                                            <td class="block sm:table-cell px-0 sm:px-5 py-1 sm:py-3.5 text-xs">
                                                <span class="sm:hidden text-[10px] font-semibold text-text-muted uppercase tracking-wider block">Metode:</span>
                                                <span class="capitalize">{{ $payment->payment_method }}</span>
                                            </td>
                                            <td class="block sm:table-cell px-0 sm:px-5 py-1 sm:py-3.5 text-xs font-mono tabular-nums">
                                                <span class="sm:hidden text-[10px] font-semibold text-text-muted uppercase tracking-wider block">Tgl Ditagih:</span>
                                                {{ $payment->collected_date?->format('d/m/Y') ?? '-' }}
                                            </td>
                                            <td class="block sm:table-cell px-0 sm:px-5 py-1 sm:py-3.5">
                                                <span class="sm:hidden text-[10px] font-semibold text-text-muted uppercase tracking-wider block">Keterangan:</span>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium {{ $periodType->badgeClass() }}">{{ $periodType->label() }}</span>
                                            </td>
                                            <td class="block sm:table-cell px-0 sm:px-5 py-1 sm:py-3.5 sm:text-right font-mono font-semibold text-emerald-600 dark:text-emerald-400 tabular-nums text-xs sm:text-sm">
                                                <span class="sm:hidden text-[10px] font-semibold text-text-muted uppercase tracking-wider block">Nominal:</span>
                                                Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="px-5 py-10 text-center text-xs text-text-muted">Belum ada pembayaran yang menunggu disetor.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="px-5 py-3 border-t border-border">
                            {{ $unsettledPayments->links() }}
                        </div>
                    </div>
                </div>

            @elseif ($tab === 'setoran')
                {{-- ============ TAB: SETORAN ============ --}}
                <div class="space-y-4">
                    @forelse ($deposits as $deposit)
                        @php
                            $computed = $deposit->computedAmount();
                            $isPending = $deposit->status === \App\Enums\DepositStatus::MENUNGGU_VERIFIKASI;
                            $badgeClass = match ($deposit->status) {
                                \App\Enums\DepositStatus::MENUNGGU_VERIFIKASI => 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20',
                                \App\Enums\DepositStatus::TERVERIFIKASI, \App\Enums\DepositStatus::SELISIH_LUNAS => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20',
                                \App\Enums\DepositStatus::SELISIH => 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20',
                                \App\Enums\DepositStatus::LEBIH_SETOR => 'bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20',
                                \App\Enums\DepositStatus::DIHAPUS_BUKU => 'bg-surface-muted text-text-muted border border-border',
                            };
                        @endphp

                        <div x-data="{ expandedPayments: false, paymentsPage: 1 }" class="border border-border rounded-lg overflow-hidden shadow-xs">
                            {{-- Header --}}
                            <div class="p-4 flex flex-wrap items-center justify-between gap-3 border-b border-border bg-surface-muted/30">
                                <div>
                                    <div class="font-mono font-bold text-sm text-text-main flex items-center gap-2">
                                        <span>{{ $deposit->deposit_number }}</span>
                                    </div>
                                    <div class="text-[11px] text-text-muted mt-0.5">
                                        Disetor {{ $deposit->submitted_at?->format('d/m/Y H:i') }} &bull; {{ $deposit->payments->count() }} transaksi pembayaran
                                        @if ($deposit->verified_at)
                                            &bull; Diperiksa {{ $deposit->verifier->name ?? '-' }} {{ $deposit->verified_at->format('d/m/Y H:i') }}
                                        @endif
                                    </div>
                                </div>
                                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-medium {{ $badgeClass }}">
                                    {{ $deposit->status->label() }}
                                </span>
                            </div>

                            {{-- Metrics Grid --}}
                            <div class="p-4 grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs bg-surface">
                                <div>
                                    <div class="text-text-muted text-[10px] uppercase font-semibold tracking-wider">Tercatat Sistem</div>
                                    <div class="font-mono font-semibold text-sm text-text-main tabular-nums mt-1">Rp {{ number_format($computed, 0, ',', '.') }}</div>
                                </div>
                                <div>
                                    <div class="text-text-muted text-[10px] uppercase font-semibold tracking-wider">Uang Fisik Dihitung</div>
                                    <div class="font-mono font-semibold text-sm text-text-main tabular-nums mt-1">
                                        {{ $deposit->declared_amount === null ? '—' : 'Rp '.number_format((float) $deposit->declared_amount, 0, ',', '.') }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-text-muted text-[10px] uppercase font-semibold tracking-wider">Pelunasan Selisih</div>
                                    <div class="font-mono font-semibold text-sm text-text-main tabular-nums mt-1">
                                        Rp {{ number_format((float) $deposit->settlement_amount, 0, ',', '.') }}
                                        @if ($deposit->settlesDeposit)
                                            <span class="block text-[10px] font-sans font-normal text-text-muted">untuk {{ $deposit->settlesDeposit->deposit_number }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div>
                                    <div class="text-text-muted text-[10px] uppercase font-semibold tracking-wider">Selisih Hitung</div>
                                    <div class="font-mono font-semibold text-sm tabular-nums mt-1 {{ $deposit->difference !== null && abs((float) $deposit->difference) > 0.001 ? 'text-rose-600 dark:text-rose-400' : 'text-text-main' }}">
                                        {{ $deposit->difference === null ? '—' : 'Rp '.number_format((float) $deposit->difference, 0, ',', '.') }}
                                    </div>
                                </div>
                            </div>

                            @if ($deposit->note)
                                <div class="px-4 pb-3 text-xs text-text-muted italic">
                                    Catatan: {{ $deposit->note }}
                                </div>
                            @endif

                            @if ($deposit->status === \App\Enums\DepositStatus::SELISIH)
                                <div class="px-4 pb-3 text-xs text-rose-600 dark:text-rose-400 font-medium">
                                    Sisa kewajiban kolektor: <span class="font-mono font-bold">Rp {{ number_format($deposit->outstandingShortfall(), 0, ',', '.') }}</span>
                                    — dapat ditutup melalui pelunasan pada setoran berikutnya atau penghapusan buku oleh Owner.
                                </div>
                            @endif

                            @if ($deposit->status === \App\Enums\DepositStatus::LEBIH_SETOR)
                                <div class="px-4 pb-3 text-xs text-sky-700 dark:text-sky-400 font-medium">
                                    Uang fisik melebihi catatan sebesar <span class="font-mono font-bold">Rp {{ number_format(abs((float) $deposit->difference), 0, ',', '.') }}</span>
                                    — dikembalikan fisik ke kolektor saat itu juga, jadi status ini sudah final dan tidak menyisakan kewajiban.
                                </div>
                            @endif

                            @if ($deposit->status === \App\Enums\DepositStatus::DIHAPUS_BUKU)
                                <div class="px-4 pb-3 text-xs text-text-muted">
                                    Alasan Hapus Buku: {{ $deposit->write_off_reason }}
                                </div>
                            @endif

                            @if ($deposit->status->isVerified() && auth()->user()->hasPermission('collector_worksheet.print'))
                                <div class="px-4 pb-3 pt-1 border-t border-border">
                                    <div class="flex items-center justify-between gap-2 pt-2.5">
                                        <button type="button" @click="expandedPayments = !expandedPayments"
                                                class="flex items-center gap-1.5 text-[11px] font-semibold text-text-muted uppercase tracking-wider hover:text-text-main cursor-pointer">
                                            <svg class="h-3.5 w-3.5 transition-transform" :class="expandedPayments ? 'rotate-90' : ''"
                                                 fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                            </svg>
                                            <span>Pelanggan yang Bayar ({{ $deposit->payments->count() }})</span>
                                        </button>
                                        @if ($deposit->payments->isNotEmpty())
                                            <form action="{{ route('payment-receipts.print', $collector->id) }}" method="POST" target="_blank" class="no-confirm">
                                                @csrf
                                                @foreach ($deposit->payments as $payment)
                                                    <input type="hidden" name="payment_ids[]" value="{{ $payment->id }}">
                                                @endforeach
                                                <button type="submit" class="text-xs font-medium text-primary hover:underline whitespace-nowrap cursor-pointer">
                                                    Cetak Kwitansi Massal
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                    @php
                                        $paymentChunks = $deposit->payments->chunk(100)->values();
                                    @endphp
                                    <div x-show="expandedPayments" x-collapse class="space-y-1 mt-2">
                                        @forelse ($paymentChunks as $chunkIndex => $chunk)
                                            <div x-show="paymentsPage === {{ $chunkIndex + 1 }}" class="space-y-1">
                                                @foreach ($chunk as $payment)
                                                    <div class="flex items-center justify-between gap-2 text-xs py-1.5 px-3 rounded-lg bg-surface-muted/50 border border-border">
                                                        <div class="min-w-0 truncate">
                                                            <span class="font-medium text-text-main">{{ $payment->customer->full_name ?? '-' }}</span>
                                                            <span class="font-mono text-text-muted ml-1">{{ $payment->payment_number }}</span>
                                                        </div>
                                                        <div class="flex items-center gap-3 shrink-0">
                                                            <span class="font-mono font-semibold text-text-main tabular-nums">Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}</span>
                                                            <a href="{{ route('payment-receipts.print', ['collector' => $collector->id, 'payment_ids' => [$payment->id]]) }}"
                                                               target="_blank"
                                                               class="text-primary hover:underline font-medium">Kwitansi</a>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @empty
                                            <p class="text-xs text-text-muted">Tidak ada pembayaran dalam setoran ini.</p>
                                        @endforelse

                                        @if ($paymentChunks->count() > 1)
                                            <div class="flex items-center justify-between gap-2 pt-2 text-[11px] text-text-muted">
                                                <button type="button" @click="paymentsPage = Math.max(1, paymentsPage - 1)"
                                                        :disabled="paymentsPage === 1"
                                                        :class="paymentsPage === 1 ? 'opacity-40 cursor-not-allowed' : 'hover:text-text-main'"
                                                        class="font-medium">&larr; Sebelumnya</button>
                                                <span>Halaman <span x-text="paymentsPage"></span> dari {{ $paymentChunks->count() }} &bull; {{ $deposit->payments->count() }} pembayaran</span>
                                                <button type="button" @click="paymentsPage = Math.min({{ $paymentChunks->count() }}, paymentsPage + 1)"
                                                        :disabled="paymentsPage === {{ $paymentChunks->count() }}"
                                                        :class="paymentsPage === {{ $paymentChunks->count() }} ? 'opacity-40 cursor-not-allowed' : 'hover:text-text-main'"
                                                        class="font-medium">Berikutnya &rarr;</button>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            {{-- Form Verifikasi --}}
                            @if ($isPending && auth()->user()->hasPermission('collector_worksheet.validate'))
                                <form action="{{ route('collector-deposits.verify', $deposit->id) }}" method="POST"
                                      class="p-4 border-t border-border bg-surface-muted/20 space-y-3"
                                      data-confirm="Tutup setoran {{ $deposit->deposit_number }} dengan uang fisik yang Anda hitung? Setelah terverifikasi, pembayaran di dalamnya tidak dapat dibatalkan secara parsial.">
                                    @csrf
                                    <h4 class="text-xs font-semibold text-text-main uppercase tracking-wider">Form Verifikasi Fisik Kas</h4>
                                    
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                        <div>
                                            <label class="block text-[10px] font-semibold uppercase tracking-wider text-text-muted mb-1">
                                                Uang Fisik Dihitung (Rp)
                                            </label>
                                            <input type="text" inputmode="decimal" data-rupiah name="declared_amount" value="{{ \App\Helpers\FormatHelper::rupiahInput($computed) }}" required
                                                   class="w-full text-xs px-2.5 py-1.5 border border-border rounded-lg bg-surface text-text-main focus:outline-none focus:border-primary font-mono tabular-nums">
                                        </div>

                                        @if ($openShortfallDeposits->isNotEmpty())
                                            <div>
                                                <label class="block text-[10px] font-semibold uppercase tracking-wider text-text-muted mb-1">
                                                    Melunasi Setoran
                                                </label>
                                                <select name="settles_deposit_id" class="w-full text-xs px-2.5 py-1.5 border border-border rounded-lg bg-surface text-text-main focus:outline-none focus:border-primary">
                                                    <option value="">— tidak ada —</option>
                                                    @foreach ($openShortfallDeposits as $open)
                                                        <option value="{{ $open->id }}">{{ $open->deposit_number }} (sisa Rp{{ number_format($open->outstandingShortfall(), 0, ',', '.') }})</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-[10px] font-semibold uppercase tracking-wider text-text-muted mb-1">
                                                    Nominal Pelunasan (Rp)
                                                </label>
                                                <input type="text" inputmode="decimal" data-rupiah name="settlement_amount" value="0"
                                                       class="w-full text-xs px-2.5 py-1.5 border border-border rounded-lg bg-surface text-text-main focus:outline-none focus:border-primary font-mono tabular-nums">
                                            </div>
                                        @endif
                                    </div>

                                    <div>
                                        <label class="block text-[10px] font-semibold uppercase tracking-wider text-text-muted mb-1">
                                            Catatan Verifikasi (Wajib jika ada selisih)
                                        </label>
                                        <input type="text" name="note" maxlength="1000" placeholder="misal: fisik tunai kurang Rp 20.000, kolektor janji mengganti besok"
                                               class="w-full text-xs px-2.5 py-1.5 border border-border rounded-lg bg-surface text-text-main focus:outline-none focus:border-primary">
                                    </div>

                                    <div class="flex justify-end pt-1">
                                        <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium py-1.5 px-4 rounded-lg transition-colors cursor-pointer shadow-xs">
                                            Verifikasi Setoran Kas
                                        </button>
                                    </div>
                                </form>
                            @endif

                            {{-- Form Hapus Buku --}}
                            @if ($deposit->status === \App\Enums\DepositStatus::SELISIH && auth()->user()->hasPermission('collector_worksheet.approve'))
                                <form action="{{ route('collector-deposits.write-off', $deposit->id) }}" method="POST"
                                      class="p-4 border-t border-border bg-rose-500/5 space-y-2"
                                      data-confirm="Hapus buku selisih {{ $deposit->deposit_number }} sebesar Rp{{ number_format($deposit->outstandingShortfall(), 0, ',', '.') }}? Kerugian diakui dan tindakan ini tidak dapat dibatalkan.">
                                    @csrf
                                    <h4 class="text-xs font-semibold text-rose-700 dark:text-rose-400 uppercase tracking-wider">Persetujuan Penghapusan Buku (Owner)</h4>
                                    <div class="flex flex-col sm:flex-row gap-2">
                                        <input type="text" name="write_off_reason" required maxlength="1000" placeholder="Alasan penghapusan buku (wajib diisi)..."
                                               class="flex-1 text-xs px-2.5 py-1.5 border border-border rounded-lg bg-surface text-text-main focus:outline-none focus:border-rose-500">
                                        <button type="submit" class="bg-rose-600 hover:bg-rose-700 text-white text-xs font-medium py-1.5 px-4 rounded-lg transition-colors cursor-pointer shrink-0 shadow-xs">
                                            Proses Hapus Buku
                                        </button>
                                    </div>
                                </form>
                            @endif
                        </div>
                    @empty
                        <div class="p-8 text-center border border-border rounded-lg bg-surface-muted/20">
                            <div class="w-10 h-10 rounded-lg bg-surface-muted text-text-muted flex items-center justify-center mx-auto mb-2 border border-border">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </div>
                            <p class="text-xs font-semibold text-text-main">Belum Ada Setoran Kas</p>
                            <p class="text-xs text-text-muted mt-0.5">Kolektor ini belum pernah menyetorkan hasil penagihan fisik.</p>
                        </div>
                    @endforelse

                    <div>{{ $deposits->links() }}</div>
                </div>

            @elseif ($tab === 'kunjungan')
                {{-- ============ TAB: KUNJUNGAN ============ --}}
                <div class="space-y-4">
                    <x-ui.alert variant="neutral" class="rounded-lg">
                        Laporan penagihan tanpa setoran dapat diidentifikasi melalui riwayat kunjungan. Pelanggan yang berulang kali didatangi tanpa hasil pembayaran memerlukan perhatian dan verifikasi lapangan.
                    </x-ui.alert>

                    {{-- Table Aging Pelanggan --}}
                    <div class="border border-border rounded-lg overflow-hidden shadow-xs">
                        <div class="p-3.5 border-b border-border bg-surface-muted/30 flex flex-col sm:flex-row sm:items-center justify-between gap-1 sm:gap-2">
                            <h3 class="text-xs font-semibold text-text-main uppercase tracking-wider">Aging Pelanggan Tertunggak</h3>
                            <span class="text-[10px] text-text-muted">Urut berdasarkan frekuensi kunjungan</span>
                        </div>
                        <div class="overflow-x-auto custom-scrollbar">
                            <table class="w-full border-collapse text-left text-xs text-text-main min-w-[640px]">
                                <thead>
                                    <tr class="bg-surface-muted/50 border-b border-border text-text-muted font-semibold text-[10px] uppercase">
                                        <th class="px-4 py-2.5">PELANGGAN</th>
                                        <th class="px-4 py-2.5 text-right">TOTAL BELUM DIBAYAR</th>
                                        <th class="px-4 py-2.5 text-center">KUNJUNGAN GAGAL</th>
                                        <th class="px-4 py-2.5 text-center">TOTAL KUNJUNGAN</th>
                                        <th class="px-4 py-2.5">TERAKHIR DIKUNJUNGI</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border font-medium">
                                    @forelse ($aging as $row)
                                        @php $mencurigakan = $row->kunjungan_gagal >= 3; @endphp
                                        <tr class="hover:bg-surface-muted/30 transition-colors {{ $mencurigakan ? 'bg-amber-500/5' : '' }}">
                                            <td class="px-4 py-2.5 whitespace-nowrap">
                                                <div class="font-semibold text-text-main">{{ $row->full_name }}</div>
                                                <div class="text-[10px] text-text-muted font-mono">{{ $row->cid ?? $row->customer_code }}</div>
                                            </td>
                                            <td class="px-4 py-2.5 text-right font-mono font-semibold text-amber-600 dark:text-amber-400 tabular-nums">
                                                Rp {{ number_format((float) $row->tunggakan, 0, ',', '.') }}
                                            </td>
                                            <td class="px-4 py-2.5 text-center">
                                                <span class="px-2 py-0.5 rounded-full font-semibold text-[10px] {{ $mencurigakan ? 'bg-amber-500/10 text-amber-700 dark:text-amber-300 border border-amber-500/20' : 'bg-surface-muted text-text-muted border border-border' }}">
                                                    {{ $row->kunjungan_gagal }}x
                                                </span>
                                            </td>
                                            <td class="px-4 py-2.5 text-center text-text-muted font-mono tabular-nums">{{ $row->kunjungan_total }}</td>
                                            <td class="px-4 py-2.5 text-text-muted tabular-nums">
                                                {{ $row->terakhir_dikunjungi ? \Illuminate\Support\Carbon::parse($row->terakhir_dikunjungi)->format('d/m/Y') : 'Belum pernah' }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-4 py-8 text-center text-text-muted">Tidak ada data pelanggan tertunggak.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="p-3 border-t border-border">{{ $aging->links() }}</div>
                    </div>

                    {{-- Timeline Riwayat Kunjungan --}}
                    <div class="border border-border rounded-lg overflow-hidden shadow-xs">
                        <div class="p-3.5 border-b border-border bg-surface-muted/30">
                            <h3 class="text-xs font-semibold text-text-main uppercase tracking-wider">Riwayat Kunjungan Lapangan</h3>
                        </div>
                        <div class="divide-y divide-border max-h-[32rem] overflow-y-auto custom-scrollbar">
                            @forelse ($visitHistory as $visit)
                                <div class="p-3.5 flex flex-wrap items-center justify-between gap-3 hover:bg-surface-muted/30 transition-colors">
                                    <div>
                                        <div class="text-xs font-semibold text-text-main">{{ $visit->customer->full_name ?? '-' }}</div>
                                        <div class="text-[10px] text-text-muted mt-0.5">
                                            {{ $visit->visited_at?->format('d/m/Y') }}
                                            @if ($visit->promised_date) &bull; Janji bayar: <span class="font-semibold text-primary">{{ $visit->promised_date->format('d/m/Y') }}</span> @endif
                                            @if ($visit->note) &bull; {{ $visit->note }} @endif
                                        </div>
                                    </div>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-medium {{ $visit->result === \App\Enums\VisitResult::BAYAR ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20' : 'bg-surface-muted text-text-muted border border-border' }}">
                                        {{ $visit->result->label() }}
                                        @if ($visit->payment) &bull; {{ $visit->payment->payment_number }} @endif
                                    </span>
                                </div>
                            @empty
                                <div class="p-8 text-center text-text-muted text-xs">Belum ada catatan kunjungan lokasi.</div>
                            @endforelse
                        </div>
                        <div class="p-3 border-t border-border">{{ $visitHistory->links() }}</div>
                    </div>
                </div>

            @elseif ($tab === 'kwitansi')
                {{-- ============ TAB: KWITANSI (SUMBU DOKUMEN) ============ --}}
                <div class="space-y-4">
                    <x-ui.alert variant="neutral" class="rounded-lg">
                        Kwitansi adalah <span class="font-semibold">bukti bagi pelanggan</span>, bukan bagian dari perhitungan kas.
                        Setoran tetap diverifikasi tanpa menunggu berkas ini diupload — <span class="font-semibold">mengunggah kwitansi
                        tidak pernah mengubah saldo kolektor</span>. Nomor pembayaran dibaca otomatis dari lapisan teks PDF, atau dari
                        QR kalau berkasnya berupa foto/scan; yang tak terbaca menunggu dicocokkan manual di bawah.
                    </x-ui.alert>

                    {{-- Panel progres pembacaan --}}
                    @php
                        $statusAwal = \App\Models\PaymentReceipt::query()
                            ->forWorksheet($collector, auth()->user())
                            ->selectRaw('status, COUNT(*) as jml')
                            ->groupBy('status')
                            ->pluck('jml', 'status');
                    @endphp

                    <div x-data="receiptProgress({
                            url: '{{ route('payment-receipts.progress', $collector->id) }}',
                            awal: @js([
                                'pending' => (int) $statusAwal->get('pending', 0),
                                'processing' => (int) $statusAwal->get('processing', 0),
                                'matched' => (int) $statusAwal->get('matched', 0),
                                'mismatch' => (int) $statusAwal->get('mismatch', 0),
                                'failed' => (int) $statusAwal->get('failed', 0),
                            ]),
                         })"
                         x-init="mulai()"
                         class="border border-border rounded-lg shadow-xs overflow-hidden">
                        <div class="px-4 py-3 border-b border-border bg-surface-muted/30">
                            <h4 class="text-xs font-semibold text-text-main uppercase tracking-wider">Status Pembacaan Kwitansi</h4>
                        </div>

                        <div class="p-4">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <template x-if="antre > 0">
                                        <svg class="w-4 h-4 animate-spin text-primary shrink-0" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                                        </svg>
                                    </template>
                                    <span class="text-xs font-medium text-text-main truncate" x-text="ringkasan"></span>
                                </div>

                                <div class="flex flex-wrap items-center gap-1.5 text-[11px] font-medium">
                                    <span class="px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                        Cocok <span x-text="status.matched"></span>
                                    </span>
                                    <span class="px-2 py-0.5 rounded bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                        Antre <span x-text="antre"></span>
                                    </span>
                                    <span class="px-2 py-0.5 rounded bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">
                                        Perlu cek <span x-text="status.mismatch + status.failed"></span>
                                    </span>
                                </div>
                            </div>

                            <div x-show="antre > 0" x-cloak class="mt-3 h-1.5 w-full rounded-full bg-surface-muted overflow-hidden">
                                <div class="h-full bg-primary transition-all duration-500" :style="`width: ${persen}%`"></div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-stretch">
                        {{-- Cetak --}}
                        <div class="border border-border rounded-lg shadow-xs overflow-hidden flex flex-col justify-between">
                            <div>
                                <div class="px-4 py-3 border-b border-border bg-surface-muted/30">
                                    <h4 class="text-xs font-semibold text-text-main uppercase tracking-wider">Cetak Kwitansi</h4>
                                </div>
                                <div class="p-4">
                                    @if (auth()->user()->hasPermission('collector_worksheet.print'))
                                        <p class="text-xs text-text-muted mb-2.5">
                                            Hanya pembayaran yang <span class="font-semibold text-text-main">setorannya sudah diperiksa kantor</span> yang muncul di sini — kwitansi adalah dokumen kantor.
                                        </p>

                                        <form action="{{ route('payment-receipts.print', $collector->id) }}" method="POST" target="_blank" class="no-confirm"
                                              x-data="{ 
                                                  selectAll: false, 
                                                  toggleAll() {
                                                      const checkboxes = $el.querySelectorAll('input[name=\'payment_ids[]\']');
                                                      checkboxes.forEach(cb => cb.checked = this.selectAll);
                                                  }
                                              }"
                                              onsubmit="
                                                  if (!this.querySelector('input[name=\'payment_ids[]\']:checked')) {
                                                      event.preventDefault();
                                                      if (window.Toast) {
                                                          window.Toast.warning('Validasi Formulir', 'Harap pilih / centang minimal satu item dalam daftar terlebih dahulu.');
                                                      }
                                                      return false;
                                                  }
                                              ">
                                            @csrf

                                            @if ($receiptCandidates->isNotEmpty())
                                                <div class="flex items-center justify-between px-3 py-2 bg-surface-muted/50 rounded-lg border border-border mb-2.5 text-xs">
                                                    <label class="flex items-center gap-2 cursor-pointer select-none font-medium text-text-main">
                                                        <input type="checkbox" x-model="selectAll" @change="toggleAll()" class="rounded border-border text-primary focus:ring-primary cursor-pointer w-3.5 h-3.5">
                                                        <span>Pilih Semua Siap Cetak</span>
                                                    </label>
                                                    <span class="text-[11px] text-text-muted font-mono font-medium">
                                                        {{ $receiptCandidates->count() }} Pembayaran
                                                    </span>
                                                </div>
                                            @endif

                                            <div class="divide-y divide-border max-h-72 overflow-y-auto border border-border rounded-lg mb-3">
                                                @forelse ($receiptCandidates as $candidate)
                                                    <label class="px-3.5 py-2 flex items-center gap-3 hover:bg-surface-muted/40 cursor-pointer">
                                                        <input type="checkbox" name="payment_ids[]" value="{{ $candidate->id }}" class="rounded border-border text-primary">
                                                        <div class="flex-1 min-w-0">
                                                            <div class="text-xs font-semibold text-text-main truncate">{{ $candidate->customer->full_name ?? '-' }}</div>
                                                            <div class="text-[10px] text-text-muted font-mono">{{ $candidate->payment_number }} &bull; Rp {{ number_format((float) $candidate->amount, 0, ',', '.') }}</div>
                                                        </div>
                                                    </label>
                                                @empty
                                                    <div class="px-4 py-8 text-center text-xs text-text-muted">
                                                        Belum ada yang bisa dicetak — semua pembayaran sudah punya kwitansi, atau setorannya masih menunggu verifikasi.
                                                    </div>
                                                @endforelse
                                            </div>

                                            @if ($receiptCandidates->isNotEmpty())
                                                <button type="submit" class="w-full bg-slate-700 hover:bg-slate-800 text-white text-xs font-medium py-2 rounded-lg transition-colors cursor-pointer shadow-xs">
                                                    Buka Halaman Cetak
                                                </button>
                                            @endif
                                        </form>
                                    @else
                                        <p class="text-xs text-text-muted text-center py-6">Anda tidak punya izin mencetak kwitansi.</p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Upload --}}
                        <div class="border border-border rounded-lg shadow-xs overflow-hidden flex flex-col justify-between">
                            <div>
                                <div class="px-4 py-3 border-b border-border bg-surface-muted/30">
                                    <h4 class="text-xs font-semibold text-text-main uppercase tracking-wider">Upload Kwitansi (bisa banyak sekaligus)</h4>
                                </div>
                                <div class="p-4">
                                    @if (auth()->user()->hasPermission('collector_worksheet.upload'))
                                        <p class="text-xs text-text-muted mb-2.5">
                                            Unggah berkas foto/scan atau PDF kwitansi. Sistem otomatis membaca QR atau nomor pembayaran di dalamnya.
                                        </p>
                                        <form action="{{ route('payment-receipts.store') }}" method="POST" enctype="multipart/form-data"
                                              x-data="receiptUpload()" @submit="mengirim = true" class="space-y-3">
                                            @csrf
                                            <input type="hidden" name="files_count" id="files_count" x-model="jumlah">

                                            {{-- Dropzone --}}
                                            <div class="relative border-2 border-dashed border-border hover:border-primary bg-surface-muted/20 rounded-lg p-4 text-center transition-colors cursor-pointer group"
                                                 @dragover.prevent="isDragging = true"
                                                 @dragleave.prevent="isDragging = false"
                                                 @drop.prevent="isDragging = false; handleDrop($event)"
                                                 :class="{ 'border-primary bg-primary/5': isDragging }">

                                                <input type="file" name="files[]" id="receipt_files" multiple required accept="image/*,application/pdf"
                                                       @change="pilih($event)"
                                                       class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">

                                                <div class="flex flex-col items-center justify-center gap-1.5">
                                                    <div class="w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                                        </svg>
                                                    </div>
                                                    <div>
                                                        <p class="text-xs font-medium text-text-main">
                                                            <span class="text-primary font-semibold">Klik untuk memilih</span> atau tarik & lepas
                                                        </p>
                                                        <p class="text-[10px] text-text-muted mt-0.5">
                                                            JPG, PNG, WEBP, atau PDF (Maks 8 MB/berkas)
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- Ringkasan Pilihan --}}
                                            <div x-show="jumlah > 0" x-cloak class="p-2.5 bg-primary/10 border border-primary/20 rounded-lg flex items-center justify-between text-xs">
                                                <div class="flex items-center gap-2">
                                                    <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                    </svg>
                                                    <span class="font-medium text-text-main">
                                                        <span x-text="jumlah"></span> berkas (<span x-text="ukuran"></span>)
                                                    </span>
                                                </div>
                                                <span class="text-[10px] text-primary font-semibold">Siap diunggah</span>
                                            </div>

                                            <button type="submit" x-bind:disabled="mengirim"
                                                    class="w-full inline-flex items-center justify-center gap-1.5 bg-primary hover:bg-primary-hover disabled:opacity-60 text-white text-xs font-medium py-2 px-4 rounded-lg transition-colors cursor-pointer shadow-xs">
                                                <svg x-show="mengirim" x-cloak class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                                                </svg>
                                                <span x-text="mengirim ? 'Mengunggah…' : 'Unggah Kwitansi'">Unggah Kwitansi</span>
                                            </button>
                                        </form>
                                    @else
                                        <p class="text-xs text-text-muted text-center py-6">Anda tidak punya izin mengunggah kwitansi.</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Daftar berkas --}}
                    <div class="border border-border rounded-lg shadow-xs overflow-hidden">
                        <div class="px-4 py-3 border-b border-border bg-surface-muted/30">
                            <h4 class="text-xs font-semibold text-text-main uppercase tracking-wider">Berkas Kwitansi</h4>
                        </div>
                        <div class="divide-y divide-border">
                            @forelse ($receipts as $receipt)
                                @php
                                    $badge = match ($receipt->status) {
                                        \App\Enums\ReceiptStatus::MATCHED => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20',
                                        \App\Enums\ReceiptStatus::PENDING, \App\Enums\ReceiptStatus::PROCESSING => 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20',
                                        \App\Enums\ReceiptStatus::MISMATCH, \App\Enums\ReceiptStatus::FAILED => 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20',
                                    };
                                @endphp

                                <div class="px-4 py-3">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="text-xs font-semibold text-text-main truncate">{{ $receipt->original_filename }}</div>
                                            <div class="text-[10px] text-text-muted mt-0.5">
                                                diunggah {{ $receipt->uploader->name ?? '-' }} {{ $receipt->created_at?->format('d/m/Y H:i') }}
                                                @if ($receipt->payment)
                                                    &bull; <span class="font-mono">{{ $receipt->payment->payment_number }}</span> — {{ $receipt->payment->customer->full_name ?? '-' }}
                                                @elseif ($receipt->detected_number)
                                                    &bull; terbaca <span class="font-mono">{{ $receipt->detected_number }}</span>
                                                @endif
                                                @if ($receipt->match_method)
                                                    &bull; via {{ $receipt->match_method->label() }}
                                                @endif
                                            </div>
                                            @if ($receipt->last_error)
                                                <div class="text-[11px] text-rose-600 dark:text-rose-400 mt-0.5">{{ $receipt->last_error }}</div>
                                            @endif
                                        </div>

                                        <div class="flex items-center gap-2 shrink-0">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-medium {{ $badge }}">{{ $receipt->status->label() }}</span>

                                            @if ($receipt->payment_id && auth()->user()->hasPermission('collector_worksheet.print'))
                                                <a href="{{ route('payment-receipts.print', ['collector' => $collector->id, 'payment_ids' => [$receipt->payment_id]]) }}"
                                                   target="_blank" rel="noopener"
                                                   class="text-xs font-medium text-emerald-600 dark:text-emerald-400 hover:underline">
                                                    Kwitansi
                                                </a>
                                            @endif

                                            <a href="{{ route('payment-receipts.download', $receipt->id) }}" class="text-xs font-medium text-primary hover:underline">Lembar asal</a>
                                        </div>
                                    </div>

                                    @if ($receipt->status->needsAttention() && auth()->user()->hasPermission('collector_worksheet.upload'))
                                        <form action="{{ route('payment-receipts.match', $receipt->id) }}" method="POST" class="mt-2.5 flex flex-col sm:flex-row gap-2">
                                            @csrf
                                            <select name="payment_id" required class="flex-1 text-xs px-2.5 py-1.5 border border-border rounded-lg bg-surface text-text-main focus:outline-none focus:border-primary">
                                                <option value="">Cocokkan manual ke pembayaran...</option>
                                                @foreach ($receiptCandidates as $candidate)
                                                    <option value="{{ $candidate->id }}">{{ $candidate->payment_number }} — {{ $candidate->customer->full_name ?? '-' }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="bg-slate-700 hover:bg-slate-800 text-white text-xs font-medium py-1.5 px-4 rounded-lg transition-colors cursor-pointer">Cocokkan</button>
                                        </form>
                                    @endif

                                    @if ($receipt->status === \App\Enums\ReceiptStatus::MATCHED && auth()->user()->hasPermission('collector_worksheet.upload'))
                                        <form action="{{ route('payment-receipts.detach', $receipt->id) }}" method="POST" class="mt-1.5"
                                              data-confirm="Lepas kwitansi ini dari {{ $receipt->payment->payment_number ?? 'pembayarannya' }}? Berkasnya kembali menunggu dicocokkan.">
                                            @csrf
                                            <button type="submit" class="text-xs font-medium text-rose-600 dark:text-rose-400 hover:underline cursor-pointer">Lepas kaitan</button>
                                        </form>
                                    @endif
                                </div>
                            @empty
                                <div class="px-4 py-8 text-center text-xs text-text-muted">Belum ada kwitansi diunggah untuk kolektor ini.</div>
                            @endforelse
                        </div>
                        <div class="p-3 border-t border-border">{{ $receipts->links() }}</div>
                    </div>
                </div>

            @else
                {{-- ============ TAB: ATUR PELANGGAN ============ --}}
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-start">
                    {{-- Column 1: Pelanggan Saat Ini --}}
                    <div class="lg:col-span-6 border border-border rounded-lg overflow-hidden shadow-xs">
                        <div class="p-3.5 border-b border-border bg-surface-muted/30 flex items-center justify-between">
                            <h3 class="text-xs font-semibold text-text-main uppercase tracking-wider">
                                Pelanggan Saat Ini ({{ $assignedCustomers->total() }})
                            </h3>
                            <span class="text-[10px] text-text-muted">Kolektor: {{ $collector->name }}</span>
                        </div>

                        <div class="divide-y divide-border max-h-[32rem] overflow-y-auto custom-scrollbar">
                            @forelse ($assignedCustomers as $customer)
                                <div class="p-3 sm:p-3.5 flex items-center justify-between gap-3 hover:bg-surface-muted/30 transition-colors">
                                    <div class="min-w-0">
                                        <div class="font-semibold text-xs text-text-main truncate">{{ $customer->full_name }}</div>
                                        <div class="text-[10px] text-text-muted font-mono mt-0.5">
                                            {{ $customer->cid ?? $customer->customer_code }} &bull; {{ $customer->pop->name ?? '-' }}
                                        </div>
                                    </div>
                                    <form action="{{ route('collector-worksheet.release', ['collector' => $collector->id, 'customer' => $customer->id]) }}" method="POST"
                                          data-confirm="Lepas pelanggan {{ $customer->full_name }} dari penugasan kolektor {{ $collector->name }}?">
                                        @csrf
                                        <button type="submit" class="px-2.5 py-1 text-xs font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-500/10 rounded-md transition-colors cursor-pointer">
                                            Lepas
                                        </button>
                                    </form>
                                </div>
                            @empty
                                <div class="p-8 text-center text-xs text-text-muted">Belum ada pelanggan ter-assign ke kolektor ini.</div>
                            @endforelse
                        </div>

                        <div class="p-3 border-t border-border">
                            {{ $assignedCustomers->links() }}
                        </div>
                    </div>

                    {{-- Column 2: Tambah / Pindahkan Pelanggan --}}
                    <div class="lg:col-span-6 border border-border rounded-lg overflow-hidden shadow-xs">
                        <div class="p-3.5 border-b border-border bg-surface-muted/30">
                            <h3 class="text-xs font-semibold text-text-main uppercase tracking-wider">Tambah / Pindahkan Pelanggan</h3>
                        </div>

                        <div class="p-4 space-y-3.5">
                            {{-- Form Cari Pelanggan --}}
                            <form action="{{ route('collector-worksheet.show', ['collector' => $collector->id, 'tab' => 'assign']) }}" method="GET" class="flex gap-2">
                                <input type="hidden" name="tab" value="assign">
                                <div class="relative flex-1">
                                    <input type="text" name="search" value="{{ $search }}" placeholder="Cari nama, kode, atau CID..."
                                           class="w-full text-xs pl-8 pr-3 py-2 border border-border rounded-lg bg-surface text-text-main focus:outline-none focus:border-primary font-sans">
                                    <svg class="w-3.5 h-3.5 text-text-muted absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                </div>
                                <button type="submit" class="bg-primary hover:bg-primary-hover text-white text-xs font-medium py-2 px-3.5 rounded-lg transition-colors cursor-pointer shrink-0 shadow-xs">
                                    Cari
                                </button>
                            </form>

                            @if ($searchResults !== null)
                                <form action="{{ route('collector-worksheet.assign', $collector->id) }}" method="POST" class="space-y-3">
                                    @csrf
                                    <div class="divide-y divide-border max-h-80 overflow-y-auto border border-border rounded-lg bg-surface custom-scrollbar">
                                        @forelse ($searchResults as $customer)
                                            <label class="px-3.5 py-2.5 flex items-center gap-3 hover:bg-surface-muted/40 cursor-pointer transition-colors">
                                                <input type="checkbox" name="customer_ids[]" value="{{ $customer->id }}"
                                                       class="rounded border-border text-primary focus:ring-primary">
                                                <div class="flex-1 min-w-0">
                                                    <div class="text-xs font-semibold text-text-main truncate">{{ $customer->full_name }}</div>
                                                    <div class="text-[10px] text-text-muted mt-0.5">
                                                        {{ $customer->pop->name ?? '-' }}
                                                        @if($customer->collector)
                                                            &bull; <span class="text-amber-600 dark:text-amber-400">Saat ini: {{ $customer->collector->name }}</span>
                                                        @else
                                                            &bull; <span class="text-text-muted">Belum ada kolektor</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </label>
                                        @empty
                                            <div class="px-4 py-8 text-center text-xs text-text-muted">Tidak ada pelanggan yang cocok dengan pencarian.</div>
                                        @endforelse
                                    </div>

                                    @if ($searchResults->isNotEmpty())
                                        <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium py-2 rounded-lg transition-colors cursor-pointer shadow-xs flex items-center justify-center gap-1.5">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                            <span>Assign Terpilih ke {{ $collector->name }}</span>
                                        </button>
                                    @endif

                                    <div class="mt-2">{{ $searchResults->links() }}</div>
                                </form>
                            @else
                                <div class="p-6 text-center text-xs text-text-muted bg-surface-muted/30 rounded-lg border border-dashed border-border">
                                    Ketik nama, kode, atau CID pelanggan di atas untuk mencari dan menambahkan/memindahkan penugasan ke kolektor ini.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
    /**
     * Ringkasan berkas terpilih + kunci tombol saat submit.
     */
    function receiptUpload() {
        return {
            jumlah: 0,
            ukuran: '',
            mengirim: false,
            isDragging: false,
            pilih(event) {
                const files = Array.from(event.target.files || []);
                this.prosesFiles(files);
            },
            handleDrop(event) {
                const dt = event.dataTransfer;
                if (!dt || !dt.files || !dt.files.length) return;
                const fileInput = document.getElementById('receipt_files');
                if (fileInput) {
                    fileInput.files = dt.files;
                    this.prosesFiles(Array.from(dt.files));
                }
            },
            prosesFiles(files) {
                this.jumlah = files.length;
                const bytes = files.reduce((total, f) => total + f.size, 0);
                this.ukuran = bytes > 1048576
                    ? (bytes / 1048576).toFixed(1) + ' MB'
                    : Math.max(1, Math.round(bytes / 1024)) + ' KB';
            },
        };
    }

    /**
     * Panel progres pembacaan kwitansi.
     */
    function receiptProgress(config) {
        return {
            url: config.url,
            antre: (config.awal.pending || 0) + (config.awal.processing || 0),
            awalAntre: (config.awal.pending || 0) + (config.awal.processing || 0),
            status: {
                pending: config.awal.pending || 0,
                processing: config.awal.processing || 0,
                matched: config.awal.matched || 0,
                mismatch: config.awal.mismatch || 0,
                failed: config.awal.failed || 0,
            },
            timer: null,

            get ringkasan() {
                if (this.antre > 0) {
                    return 'Membaca kwitansi… ' + this.antre + ' berkas tersisa';
                }

                const total = this.status.matched + this.status.mismatch + this.status.failed;

                if (total === 0) {
                    return 'Belum ada kwitansi diunggah untuk kolektor ini.';
                }

                const perluCek = this.status.mismatch + this.status.failed;

                return perluCek > 0
                    ? total + ' kwitansi terbaca — ' + this.status.matched + ' cocok, ' + perluCek + ' perlu dicek manual.'
                    : total + ' kwitansi tercocokkan otomatis. Tidak ada yang tertinggal.';
            },

            get persen() {
                if (this.awalAntre === 0) return 100;
                return Math.min(100, Math.round(((this.awalAntre - this.antre) / this.awalAntre) * 100));
            },

            mulai() {
                this.cek();
                if (this.antre > 0) this.jadwalkan();
            },

            jadwalkan() {
                clearTimeout(this.timer);
                this.timer = setTimeout(() => this.cek(), 2000);
            },

            async cek() {
                let data;
                try {
                    const res = await fetch(this.url, { headers: { 'Accept': 'application/json' } });
                    if (!res.ok) return;
                    data = await res.json();
                } catch (e) {
                    if (this.antre > 0) this.jadwalkan();
                    return;
                }

                const sebelumnya = this.antre;
                this.status = data.status;
                this.antre = data.antre;

                if (this.antre > this.awalAntre) this.awalAntre = this.antre;

                if (sebelumnya > 0 && this.antre === 0) {
                    const perluCek = this.status.mismatch + this.status.failed;
                    const pesan = perluCek > 0
                        ? this.status.matched + ' kwitansi tercocokkan, ' + perluCek + ' perlu dicek manual.'
                        : this.status.matched + ' kwitansi tercocokkan otomatis.';

                    if (window.Toast) {
                        window.Toast.show('success', 'Pembacaan kwitansi selesai', pesan, 6000);
                    }

                    setTimeout(() => window.location.reload(), 1200);
                    return;
                }

                if (this.antre > 0) this.jadwalkan();
            },
        };
    }
</script>
@endpush
@endsection
