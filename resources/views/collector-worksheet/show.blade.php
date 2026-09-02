@extends('layouts.app')

@section('title', 'Kolektor: ' . $collector->name . ' - Whusnet Operasional')
@section('page_title', $collector->name)

@section('content')
    @include('partials.collector-realtime', ['channels' => $activityChannels, 'audiens' => 'admin'])

<div class="space-y-6" id="live-content">
    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mb-1 font-medium">
                <span>Operasional</span>
                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <a href="{{ route('collector-worksheet.index') }}" class="hover:text-sky-600 dark:hover:text-sky-400">Worksheet Admin</a>
                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-sky-600 dark:text-sky-400 font-semibold">{{ $collector->name }}</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-slate-100 tracking-tight">
                Kolektor — {{ $collector->name }}
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-0.5">Cross check pembayaran, verifikasi setoran kas, dan atur rute penugasan pelanggan.</p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <a href="{{ route('collector-worksheet.index') }}" class="inline-flex items-center gap-2 px-3.5 py-2 border border-slate-200 dark:border-slate-700/80 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/60 text-slate-700 dark:text-slate-200 rounded-xl transition-all text-xs font-semibold shadow-xs focus:outline-none focus:ring-2 focus:ring-sky-500/20">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span>Worksheet Admin</span>
            </a>
        </div>
    </div>

    {{-- Error & Success Alerts --}}
    @if ($errors->any())
        <x-ui.alert variant="error" title="Aksi Ditolak" class="rounded-2xl">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </x-ui.alert>
    @endif

    @if (session('success'))
        <x-ui.alert variant="success" class="rounded-2xl">{{ session('success') }}</x-ui.alert>
    @endif

    <div id="batch-alert" class="hidden text-xs sm:text-sm rounded-2xl p-4"></div>

    {{-- Collector Profile Header Card & Financial Summary --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-12 gap-4 items-stretch">
        {{-- Profile Card --}}
        <div class="sm:col-span-1 lg:col-span-4 bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-4 sm:p-5 shadow-xs flex flex-col justify-between">
            <div class="flex items-center gap-3.5">
                <div class="h-11 w-11 sm:h-12 sm:w-12 rounded-2xl bg-gradient-to-br from-violet-500 to-indigo-600 text-white font-bold text-base sm:text-lg flex items-center justify-center shrink-0 shadow-md">
                    {{ strtoupper(substr($collector->name, 0, 1)) }}
                </div>
                <div class="min-w-0 flex-1">
                    <h2 class="font-bold text-sm sm:text-base text-slate-900 dark:text-slate-100 truncate">{{ $collector->name }}</h2>
                    <div class="flex flex-wrap items-center gap-2 mt-1">
                        @if($collector->status === 'active')
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
                                <span class="w-1 h-1 rounded-full bg-emerald-500"></span>
                                Aktif
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-400">
                                <span class="w-1 h-1 rounded-full bg-slate-400"></span>
                                Nonaktif
                            </span>
                        @endif
                        <span class="text-[11px] text-slate-500 dark:text-slate-400 font-mono truncate">ID: #{{ $collector->id }}</span>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                <span>Pelanggan Ter-assign:</span>
                <span class="font-bold text-slate-900 dark:text-slate-100 font-mono">{{ $assignedCustomers->total() }} Pelanggan</span>
            </div>
        </div>

        {{-- Saldo Belum Disetor --}}
        <div class="sm:col-span-1 lg:col-span-4 bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-4 sm:p-5 shadow-xs flex flex-col justify-between">
            <div class="min-w-0">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] uppercase font-bold tracking-wider text-slate-400 dark:text-slate-500 truncate">Saldo Belum Disetor</span>
                    <div class="w-7 h-7 rounded-lg bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                </div>
                <div class="font-bold text-lg sm:text-2xl text-slate-900 dark:text-slate-100 font-mono mt-2 truncate">
                    Rp {{ number_format($balance, 0, ',', '.') }}
                </div>
            </div>
            <div class="mt-3 text-[11px] text-slate-400 dark:text-slate-500">
                Uang tagihan tunai yang masih di tangan kolektor.
            </div>
        </div>

        {{-- Kurang Setor --}}
        <div class="sm:col-span-1 lg:col-span-4 rounded-2xl p-4 sm:p-5 shadow-xs border flex flex-col justify-between transition-all {{ $outstandingShortfall > 0 ? 'bg-red-50/70 dark:bg-red-500/10 border-red-200 dark:border-red-500/30' : 'bg-white dark:bg-slate-800/90 border-slate-200/80 dark:border-slate-700/80' }}">
            <div class="min-w-0">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] uppercase font-bold tracking-wider {{ $outstandingShortfall > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-400 dark:text-slate-500' }} truncate">
                        Kurang Setor
                    </span>
                    <div class="w-7 h-7 rounded-lg {{ $outstandingShortfall > 0 ? 'bg-red-100 dark:bg-red-500/20 text-red-600 dark:text-red-400' : 'bg-slate-100 dark:bg-slate-700/50 text-slate-400' }} flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                </div>
                <div class="font-bold text-lg sm:text-2xl font-mono mt-2 truncate {{ $outstandingShortfall > 0 ? 'text-red-700 dark:text-red-400' : 'text-slate-900 dark:text-slate-100' }}">
                    Rp {{ number_format($outstandingShortfall, 0, ',', '.') }}
                </div>
            </div>
            <div class="mt-3 text-[11px] {{ $outstandingShortfall > 0 ? 'text-red-600/90 dark:text-red-400/90 font-medium' : 'text-slate-400 dark:text-slate-500' }}">
                Kewajiban selisih yang belum ditutup — tidak otomatis nol saat setor.
            </div>
        </div>
    </div>

    {{-- Navigation Tabs --}}
    <div class="border-b border-slate-200 dark:border-slate-700/80 overflow-x-auto custom-scrollbar pb-1">
        <div class="flex gap-1.5 sm:gap-2 min-w-max">
            <a href="{{ route('collector-worksheet.show', ['collector' => $collector->id, 'tab' => 'pembayaran']) }}"
               class="px-3 sm:px-4 py-2.5 sm:py-3 text-xs font-semibold border-b-2 -mb-px transition-all inline-flex items-center gap-2 {{ $tab === 'pembayaran' ? 'border-sky-600 text-sky-600 dark:text-sky-400 dark:border-sky-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200' }}">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                <span>Pembayaran</span>
            </a>
            <a href="{{ route('collector-worksheet.show', ['collector' => $collector->id, 'tab' => 'setoran']) }}"
               class="px-3 sm:px-4 py-2.5 sm:py-3 text-xs font-semibold border-b-2 -mb-px transition-all inline-flex items-center gap-2 {{ $tab === 'setoran' ? 'border-sky-600 text-sky-600 dark:text-sky-400 dark:border-sky-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200' }}">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Setoran</span>
            </a>
            <a href="{{ route('collector-worksheet.show', ['collector' => $collector->id, 'tab' => 'kunjungan']) }}"
               class="px-3 sm:px-4 py-2.5 sm:py-3 text-xs font-semibold border-b-2 -mb-px transition-all inline-flex items-center gap-2 {{ $tab === 'kunjungan' ? 'border-sky-600 text-sky-600 dark:text-sky-400 dark:border-sky-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200' }}">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span>Kunjungan</span>
            </a>
            <a href="{{ route('collector-worksheet.show', ['collector' => $collector->id, 'tab' => 'kwitansi']) }}"
               class="px-3 sm:px-4 py-2.5 sm:py-3 text-xs font-semibold border-b-2 -mb-px transition-all inline-flex items-center gap-2 {{ $tab === 'kwitansi' ? 'border-sky-600 text-sky-600 dark:text-sky-400 dark:border-sky-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200' }}">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                <span>Kwitansi</span>
            </a>
            <a href="{{ route('collector-worksheet.show', ['collector' => $collector->id, 'tab' => 'assign']) }}"
               class="px-3 sm:px-4 py-2.5 sm:py-3 text-xs font-semibold border-b-2 -mb-px transition-all inline-flex items-center gap-2 {{ $tab === 'assign' ? 'border-sky-600 text-sky-600 dark:text-sky-400 dark:border-sky-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200' }}">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                <span>Atur Pelanggan</span>
            </a>
        </div>
    </div>

    {{-- TAB CONTENTS --}}
    @if ($tab === 'pembayaran')
        {{-- ============ TAB: PEMBAYARAN ============ --}}
        <div class="space-y-4">
            <div class="p-4 bg-sky-50/60 dark:bg-sky-500/10 border border-sky-200/60 dark:border-sky-500/20 rounded-2xl text-xs text-sky-800 dark:text-sky-300">
                Seluruh tunggakan kolektor ini — tanpa filter jatuh tempo, supaya cross check melihat gambaran penuh. Jendela tagih hanya berlaku di Worklist kolektor.
            </div>

            @include('partials.collector-pay-table', [
                'invoices' => $invoices,
                'emptyMessage' => 'Kolektor ini tidak memiliki pelanggan dengan tunggakan aktif.',
            ])

            @push('scripts')
                @include('partials.collector-pay-script', [
                    'storeUrl' => route('payment-batches.store', $collector->id),
                    'keyPrefix' => 'admin-batch-' . $collector->id,
                    'colspan' => 9,
                    'emptyMessage' => 'Kolektor ini tidak memiliki pelanggan dengan tunggakan aktif.',
                ])
            @endpush
        </div>

    @elseif ($tab === 'setoran')
        {{-- ============ TAB: SETORAN ============ --}}
        <div class="space-y-4">
            @forelse ($deposits as $deposit)
                @php
                    $computed = $deposit->computedAmount();
                    $isPending = $deposit->status === \App\Enums\DepositStatus::MENUNGGU_VERIFIKASI;
                    $badgeClass = match ($deposit->status) {
                        \App\Enums\DepositStatus::MENUNGGU_VERIFIKASI => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400 border border-amber-200 dark:border-amber-500/20',
                        \App\Enums\DepositStatus::TERVERIFIKASI, \App\Enums\DepositStatus::SELISIH_LUNAS => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/20',
                        \App\Enums\DepositStatus::SELISIH => 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400 border border-red-200 dark:border-red-500/20',
                        \App\Enums\DepositStatus::LEBIH_SETOR => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-400 border border-sky-200 dark:border-sky-500/20',
                        \App\Enums\DepositStatus::DIHAPUS_BUKU => 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300 border border-slate-200 dark:border-slate-600',
                    };
                @endphp

                <div x-data="{ expandedPayments: false, paymentsPage: 1 }" class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl overflow-hidden shadow-xs">
                    {{-- Header --}}
                    <div class="p-4 sm:p-5 flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-700/80 bg-slate-50/50 dark:bg-slate-800/40">
                        <div>
                            <div class="font-mono font-bold text-sm text-slate-900 dark:text-slate-100 flex items-center gap-2">
                                <span>{{ $deposit->deposit_number }}</span>
                            </div>
                            <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">
                                Disetor {{ $deposit->submitted_at?->format('d/m/Y H:i') }} &bull; {{ $deposit->payments->count() }} transaksi pembayaran
                                @if ($deposit->verified_at)
                                    &bull; Diperiksa {{ $deposit->verifier->name ?? '-' }} {{ $deposit->verified_at->format('d/m/Y H:i') }}
                                @endif
                            </div>
                        </div>
                        <span class="px-3 py-1 rounded-full text-[11px] font-semibold {{ $badgeClass }}">
                            {{ $deposit->status->label() }}
                        </span>
                    </div>

                    {{-- Metrics Grid --}}
                    <div class="p-4 sm:p-5 grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs bg-white dark:bg-slate-800/90">
                        <div>
                            <div class="text-slate-400 dark:text-slate-500 text-[10px] uppercase font-bold tracking-wider">Tercatat Sistem</div>
                            <div class="font-mono font-bold text-sm text-slate-800 dark:text-slate-200 mt-1">Rp {{ number_format($computed, 0, ',', '.') }}</div>
                        </div>
                        <div>
                            <div class="text-slate-400 dark:text-slate-500 text-[10px] uppercase font-bold tracking-wider">Uang Fisik Dihitung</div>
                            <div class="font-mono font-bold text-sm text-slate-800 dark:text-slate-200 mt-1">
                                {{ $deposit->declared_amount === null ? '—' : 'Rp '.number_format((float) $deposit->declared_amount, 0, ',', '.') }}
                            </div>
                        </div>
                        <div>
                            <div class="text-slate-400 dark:text-slate-500 text-[10px] uppercase font-bold tracking-wider">Pelunasan Selisih</div>
                            <div class="font-mono font-bold text-sm text-slate-800 dark:text-slate-200 mt-1">
                                Rp {{ number_format((float) $deposit->settlement_amount, 0, ',', '.') }}
                                @if ($deposit->settlesDeposit)
                                    <span class="block text-[10px] font-sans font-normal text-slate-400">untuk {{ $deposit->settlesDeposit->deposit_number }}</span>
                                @endif
                            </div>
                        </div>
                        <div>
                            <div class="text-slate-400 dark:text-slate-500 text-[10px] uppercase font-bold tracking-wider">Selisih Hitung</div>
                            <div class="font-mono font-bold text-sm mt-1 {{ $deposit->difference !== null && abs((float) $deposit->difference) > 0.001 ? 'text-red-600 dark:text-red-400' : 'text-slate-800 dark:text-slate-200' }}">
                                {{ $deposit->difference === null ? '—' : 'Rp '.number_format((float) $deposit->difference, 0, ',', '.') }}
                            </div>
                        </div>
                    </div>

                    @if ($deposit->note)
                        <div class="px-5 pb-3 text-xs text-slate-500 dark:text-slate-400 italic">
                            Catatan: {{ $deposit->note }}
                        </div>
                    @endif

                    @if ($deposit->status === \App\Enums\DepositStatus::SELISIH)
                        <div class="px-5 pb-4 text-xs text-red-600 dark:text-red-400 font-medium">
                            Sisa kewajiban kolektor: <span class="font-mono font-bold">Rp {{ number_format($deposit->outstandingShortfall(), 0, ',', '.') }}</span>
                            — dapat ditutup melalui pelunasan pada setoran berikutnya atau penghapusan buku oleh Owner.
                        </div>
                    @endif

                    @if ($deposit->status === \App\Enums\DepositStatus::LEBIH_SETOR)
                        <div class="px-5 pb-4 text-xs text-sky-700 dark:text-sky-400 font-medium">
                            Uang fisik melebihi catatan sebesar <span class="font-mono font-bold">Rp {{ number_format(abs((float) $deposit->difference), 0, ',', '.') }}</span>
                            — dikembalikan fisik ke kolektor saat itu juga, jadi status ini sudah final dan tidak menyisakan kewajiban.
                        </div>
                    @endif

                    @if ($deposit->status === \App\Enums\DepositStatus::DIHAPUS_BUKU)
                        <div class="px-5 pb-4 text-xs text-slate-500 dark:text-slate-400">
                            Alasan Hapus Buku: {{ $deposit->write_off_reason }}
                        </div>
                    @endif

                    {{-- Pelanggan yang bayar + cetak kwitansi — cuma muncul setelah setoran
                         DIPERIKSA kantor (`isVerified()`, bukan "harus terverifikasi": setoran
                         yang berakhir Kurang Setor pun sudah selesai diperiksa, lihat §12
                         docs/kolektor/business-logic.md). Sebelum itu uangnya masih di tas
                         kolektor — kantor belum punya dasar menerbitkan bukti apa pun. Cetak di
                         sini menumpang route & guard yang sama dengan tab Kwitansi
                         (payment-receipts.print → applyUserScope + payment_status=valid +
                         status setoran != menunggu_verifikasi), cuma di-scope ke SATU setoran. --}}
                    @if ($deposit->status->isVerified() && auth()->user()->hasPermission('collector_worksheet.print'))
                        <div class="px-5 pb-4 pt-1 border-t border-slate-100 dark:border-slate-700/80">
                            <div class="flex items-center justify-between gap-2 pt-3">
                                {{-- Accordion, bukan drawer: bisa dibuka bareng beberapa setoran
                                     sekaligus buat cross check, dan konteks metrik di atas
                                     (Tercatat Sistem/Selisih) tetap kelihatan tanpa pindah panel. --}}
                                <button type="button" @click="expandedPayments = !expandedPayments"
                                        class="flex items-center gap-1.5 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider hover:text-slate-800 dark:hover:text-slate-200">
                                    <svg class="h-3.5 w-3.5 transition-transform" :class="expandedPayments ? 'rotate-90' : ''"
                                         fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                    </svg>
                                    <span>Pelanggan yang Bayar ({{ $deposit->payments->count() }})</span>
                                </button>
                                @if ($deposit->payments->isNotEmpty())
                                    {{-- POST, bukan link GET: setoran harian bisa memuat puluhan/
                                         ratusan pembayaran, dan payment_ids sebanyak itu di query
                                         string GET melewati batas panjang URL server (414
                                         Request-URI Too Large, kejadian nyata 2026-08-14). --}}
                                    <form action="{{ route('payment-receipts.print', $collector->id) }}" method="POST" target="_blank" class="no-confirm">
                                        @csrf
                                        @foreach ($deposit->payments as $payment)
                                            <input type="hidden" name="payment_ids[]" value="{{ $payment->id }}">
                                        @endforeach
                                        <button type="submit" class="text-xs font-semibold text-sky-600 dark:text-sky-400 hover:underline whitespace-nowrap">
                                            Cetak Kwitansi Massal
                                        </button>
                                    </form>
                                @endif
                            </div>
                            @php
                                // Dipotong 100/halaman DI SISI TAMPILAN saja — bukan query
                                // ulang ke server. Datanya sudah dieager-load penuh lewat
                                // `payments.customer` (CollectorWorksheetController::show()),
                                // jadi pager di sini murni Alpine x-show, gak nambah query.
                                // Form "Cetak Kwitansi Massal" di atas TETAP baca SELURUH
                                // $deposit->payments, bukan cuma halaman yang lagi kelihatan —
                                // paging tampilan tidak boleh diam-diam ikut memotong yang
                                // dicetak.
                                $paymentChunks = $deposit->payments->chunk(100)->values();
                            @endphp
                            <div x-show="expandedPayments" x-collapse class="space-y-1 mt-2">
                                @forelse ($paymentChunks as $chunkIndex => $chunk)
                                    <div x-show="paymentsPage === {{ $chunkIndex + 1 }}" class="space-y-1">
                                        @foreach ($chunk as $payment)
                                            <div class="flex items-center justify-between gap-2 text-xs py-1.5 px-3 rounded-lg bg-slate-50 dark:bg-slate-900/40">
                                                <div class="min-w-0 truncate">
                                                    <span class="font-medium text-slate-700 dark:text-slate-300">{{ $payment->customer->full_name ?? '-' }}</span>
                                                    <span class="font-mono text-slate-400 dark:text-slate-500 ml-1">{{ $payment->payment_number }}</span>
                                                </div>
                                                <div class="flex items-center gap-3 shrink-0">
                                                    <span class="font-mono font-semibold text-slate-700 dark:text-slate-300">Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}</span>
                                                    <a href="{{ route('payment-receipts.print', ['collector' => $collector->id, 'payment_ids' => [$payment->id]]) }}"
                                                       target="_blank"
                                                       class="text-sky-600 dark:text-sky-400 hover:underline font-semibold">Kwitansi</a>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @empty
                                    <p class="text-xs text-slate-400 dark:text-slate-500">Tidak ada pembayaran dalam setoran ini.</p>
                                @endforelse

                                @if ($paymentChunks->count() > 1)
                                    <div class="flex items-center justify-between gap-2 pt-2 text-[11px] text-slate-500 dark:text-slate-400">
                                        <button type="button" @click="paymentsPage = Math.max(1, paymentsPage - 1)"
                                                :disabled="paymentsPage === 1"
                                                :class="paymentsPage === 1 ? 'opacity-40 cursor-not-allowed' : 'hover:text-slate-800 dark:hover:text-slate-200'"
                                                class="font-semibold">&larr; Sebelumnya</button>
                                        <span>Halaman <span x-text="paymentsPage"></span> dari {{ $paymentChunks->count() }} &bull; {{ $deposit->payments->count() }} pembayaran</span>
                                        <button type="button" @click="paymentsPage = Math.min({{ $paymentChunks->count() }}, paymentsPage + 1)"
                                                :disabled="paymentsPage === {{ $paymentChunks->count() }}"
                                                :class="paymentsPage === {{ $paymentChunks->count() }} ? 'opacity-40 cursor-not-allowed' : 'hover:text-slate-800 dark:hover:text-slate-200'"
                                                class="font-semibold">Berikutnya &rarr;</button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- Form Verifikasi --}}
                    @if ($isPending && auth()->user()->hasPermission('collector_worksheet.validate'))
                        <form action="{{ route('collector-deposits.verify', $deposit->id) }}" method="POST"
                              class="p-4 sm:p-5 border-t border-slate-100 dark:border-slate-700/80 bg-slate-50/60 dark:bg-slate-900/40 space-y-3"
                              data-confirm="Tutup setoran {{ $deposit->deposit_number }} dengan uang fisik yang Anda hitung? Setelah terverifikasi, pembayaran di dalamnya tidak dapat dibatalkan secara parsial.">
                            @csrf
                            <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">Form Verifikasi Fisik Kas</h4>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                                        Uang Fisik Dihitung (Rp)
                                    </label>
                                    <input type="text" inputmode="decimal" data-rupiah name="declared_amount" value="{{ \App\Helpers\FormatHelper::rupiahInput($computed) }}" required
                                           class="w-full text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-200 focus:outline-none focus:border-sky-500 font-mono">
                                </div>

                                @if ($openShortfallDeposits->isNotEmpty())
                                    <div>
                                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                                            Melunasi Setoran
                                        </label>
                                        <select name="settles_deposit_id" class="w-full text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-200 focus:outline-none focus:border-sky-500">
                                            <option value="">— tidak ada —</option>
                                            @foreach ($openShortfallDeposits as $open)
                                                <option value="{{ $open->id }}">{{ $open->deposit_number }} (sisa Rp{{ number_format($open->outstandingShortfall(), 0, ',', '.') }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                                            Nominal Pelunasan (Rp)
                                        </label>
                                        <input type="text" inputmode="decimal" data-rupiah name="settlement_amount" value="0"
                                               class="w-full text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-200 focus:outline-none focus:border-sky-500 font-mono">
                                    </div>
                                @endif
                            </div>

                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                                    Catatan Verifikasi (Wajib jika ada selisih)
                                </label>
                                <input type="text" name="note" maxlength="1000" placeholder="misal: fisik tunai kurang Rp 20.000, kolektor janji mengganti besok"
                                       class="w-full text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-200 focus:outline-none focus:border-sky-500">
                            </div>

                            <div class="flex justify-end pt-1">
                                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold py-2 px-5 rounded-xl transition-all cursor-pointer shadow-xs">
                                    Verifikasi Setoran Kas
                                </button>
                            </div>
                        </form>
                    @endif

                    {{-- Form Hapus Buku --}}
                    @if ($deposit->status === \App\Enums\DepositStatus::SELISIH && auth()->user()->hasPermission('collector_worksheet.approve'))
                        <form action="{{ route('collector-deposits.write-off', $deposit->id) }}" method="POST"
                              class="p-4 sm:p-5 border-t border-slate-100 dark:border-slate-700/80 bg-red-50/30 dark:bg-red-500/5 space-y-2"
                              data-confirm="Hapus buku selisih {{ $deposit->deposit_number }} sebesar Rp{{ number_format($deposit->outstandingShortfall(), 0, ',', '.') }}? Kerugian diakui dan tindakan ini tidak dapat dibatalkan.">
                            @csrf
                            <h4 class="text-xs font-bold text-red-700 dark:text-red-400 uppercase tracking-wider">Persetujuan Penghapusan Buku (Owner)</h4>
                            <div class="flex flex-col sm:flex-row gap-2">
                                <input type="text" name="write_off_reason" required maxlength="1000" placeholder="Alasan penghapusan buku (wajib diisi)..."
                                       class="flex-1 text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-200 focus:outline-none focus:border-red-500">
                                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-xs font-semibold py-2 px-5 rounded-xl transition-all cursor-pointer shrink-0 shadow-xs">
                                    Proses Hapus Buku
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            @empty
                <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-10 text-center">
                    <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-700/50 text-slate-400 flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <p class="text-xs font-semibold text-slate-700 dark:text-slate-300">Belum Ada Setoran Kas</p>
                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Kolektor ini belum pernah menyetorkan hasil penagihan fisik.</p>
                </div>
            @endforelse

            <div>{{ $deposits->links() }}</div>
        </div>

    @elseif ($tab === 'kunjungan')
        {{-- ============ TAB: KUNJUNGAN ============ --}}
        <div class="space-y-6">
            <x-ui.alert variant="neutral" class="rounded-2xl">
                Laporan penagihan tanpa setoran dapat diidentifikasi melalui riwayat kunjungan. Pelanggan yang berulang kali didatangi tanpa hasil pembayaran memerlukan perhatian dan verifikasi lapangan.
            </x-ui.alert>

            {{-- Table Aging Pelanggan --}}
            <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl overflow-hidden shadow-xs">
                <div class="p-4 border-b border-slate-100 dark:border-slate-700/80 bg-slate-50/60 dark:bg-slate-800/40 flex flex-col sm:flex-row sm:items-center justify-between gap-1 sm:gap-2">
                    <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">Aging Pelanggan Tertunggak</h3>
                    <span class="text-[10px] text-slate-400 dark:text-slate-500">Urut berdasarkan frekuensi kunjungan</span>
                </div>
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full border-collapse text-left text-xs text-slate-700 dark:text-slate-200 min-w-[640px]">
                        <thead>
                            <tr class="bg-slate-50/50 dark:bg-slate-700/50 border-b border-slate-200/80 dark:border-slate-700 text-slate-500 dark:text-slate-400 font-semibold text-[11px]">
                                <th class="px-5 py-3">PELANGGAN</th>
                                <th class="px-5 py-3 text-right">TOTAL TUNGGAKAN</th>
                                <th class="px-5 py-3 text-center">KUNJUNGAN GAGAL</th>
                                <th class="px-5 py-3 text-center">TOTAL KUNJUNGAN</th>
                                <th class="px-5 py-3">TERAKHIR DIKUNJUNGI</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                            @forelse ($aging as $row)
                                @php $mencurigakan = $row->kunjungan_gagal >= 3; @endphp
                                <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/25 transition-colors {{ $mencurigakan ? 'bg-amber-50/40 dark:bg-amber-500/5' : '' }}">
                                    <td class="px-5 py-3 whitespace-nowrap">
                                        <div class="font-semibold text-slate-900 dark:text-slate-100">{{ $row->full_name }}</div>
                                        <div class="text-[10px] text-slate-400 dark:text-slate-500 font-mono">{{ $row->cid ?? $row->customer_code }}</div>
                                    </td>
                                    <td class="px-5 py-3 text-right font-mono font-bold text-amber-700 dark:text-amber-400">
                                        Rp {{ number_format((float) $row->tunggakan, 0, ',', '.') }}
                                    </td>
                                    <td class="px-5 py-3 text-center">
                                        <span class="px-2.5 py-0.5 rounded-full font-bold text-[11px] {{ $mencurigakan ? 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' }}">
                                            {{ $row->kunjungan_gagal }}x
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-center text-slate-600 dark:text-slate-300 font-mono">{{ $row->kunjungan_total }}</td>
                                    <td class="px-5 py-3 text-slate-500 dark:text-slate-400">
                                        {{ $row->terakhir_dikunjungi ? \Illuminate\Support\Carbon::parse($row->terakhir_dikunjungi)->format('d/m/Y') : 'Belum pernah' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-8 text-center text-slate-400 dark:text-slate-500">Tidak ada data pelanggan tertunggak.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-4 border-t border-slate-100 dark:border-slate-700/80">{{ $aging->links() }}</div>
            </div>

            {{-- Timeline Riwayat Kunjungan --}}
            <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl overflow-hidden shadow-xs">
                <div class="p-4 border-b border-slate-100 dark:border-slate-700/80 bg-slate-50/60 dark:bg-slate-800/40">
                    <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">Riwayat Kunjungan Lapangan</h3>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-700/50 max-h-[32rem] overflow-y-auto custom-scrollbar">
                    @forelse ($visitHistory as $visit)
                        <div class="p-4 flex flex-wrap items-center justify-between gap-3 hover:bg-slate-50/60 dark:hover:bg-slate-700/25 transition-colors">
                            <div>
                                <div class="text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $visit->customer->full_name ?? '-' }}</div>
                                <div class="text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">
                                    {{ $visit->visited_at?->format('d/m/Y') }}
                                    @if ($visit->promised_date) &bull; Janji bayar: <span class="font-semibold text-sky-600 dark:text-sky-400">{{ $visit->promised_date->format('d/m/Y') }}</span> @endif
                                    @if ($visit->note) &bull; {{ $visit->note }} @endif
                                </div>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold {{ $visit->result === \App\Enums\VisitResult::BAYAR ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/20' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' }}">
                                {{ $visit->result->label() }}
                                @if ($visit->payment) &bull; {{ $visit->payment->payment_number }} @endif
                            </span>
                        </div>
                    @empty
                        <div class="p-8 text-center text-slate-400 dark:text-slate-500">Belum ada catatan kunjungan lokasi.</div>
                    @endforelse
                </div>
                <div class="p-4 border-t border-slate-100 dark:border-slate-700/80">{{ $visitHistory->links() }}</div>
            </div>
        </div>

    @elseif ($tab === 'kwitansi')
        {{-- ============ TAB: KWITANSI (SUMBU DOKUMEN) ============ --}}
        <x-ui.alert variant="neutral" class="rounded-2xl">
            Kwitansi adalah <span class="font-semibold">bukti bagi pelanggan</span>, bukan bagian dari perhitungan kas.
            Setoran tetap diverifikasi tanpa menunggu berkas ini diupload — <span class="font-semibold">mengunggah kwitansi
            tidak pernah mengubah saldo kolektor</span>. Nomor pembayaran dibaca otomatis dari lapisan teks PDF, atau dari
            QR kalau berkasnya berupa foto/scan; yang tak terbaca menunggu dicocokkan manual di bawah.
        </x-ui.alert>

        {{-- ══ Panel progres pembacaan ═══════════════════════════════════════
             Pembacaan berjalan di queue, jadi setelah Unggah halaman berisi
             baris `pending` yang berubah sendiri beberapa detik kemudian tanpa
             ada yang memberi tahu. Sebelum panel ini, layarnya diam total:
             admin menekan Unggah lalu tidak melihat apa pun terjadi.

             Polling, bukan broadcast — tidak perlu otorisasi channel, dan
             berhenti sendiri begitu antreannya nol. --}}
        @php
            // Nilai awal diambil dari SELURUH kwitansi kolektor ini, bukan dari
            // halaman paginasi yang sedang tampil — penghitung yang cuma
            // menjumlah 25 baris pertama akan berbohong begitu berkasnya lebih
            // dari satu halaman.
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
             class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl shadow-xs overflow-hidden">
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700/80 bg-slate-50/60 dark:bg-slate-900/30">
                <h4 class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider">Status Pembacaan Kwitansi</h4>
            </div>

            <div class="px-5 py-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <template x-if="antre > 0">
                            <svg class="w-4 h-4 animate-spin text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                            </svg>
                        </template>
                        {{-- Saat antrean kosong, panel menyebut HASIL — bukan
                             "tidak ada pembacaan yang berjalan". Pembacaan lewat
                             lapisan teks selesai dalam ~1 detik, jadi begitu
                             halaman selesai dimuat ulang antreannya memang sudah
                             nol: kalimat "tidak ada yang berjalan" muncul tepat
                             pada saat admin paling ingin tahu hasilnya, dan
                             bertabrakan dengan pesan sukses di atasnya. --}}
                        <span class="text-xs font-semibold text-slate-700 dark:text-slate-200 truncate" x-text="ringkasan"></span>
                    </div>

                    <div class="flex flex-wrap items-center gap-1.5 text-[11px] font-semibold">
                        <span class="px-2 py-1 rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
                            Cocok <span x-text="status.matched"></span>
                        </span>
                        <span class="px-2 py-1 rounded-lg bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">
                            Antre <span x-text="antre"></span>
                        </span>
                        <span class="px-2 py-1 rounded-lg bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400">
                            Perlu cek <span x-text="status.mismatch + status.failed"></span>
                        </span>
                    </div>
                </div>

                <div x-show="antre > 0" x-cloak class="mt-3 h-1.5 w-full rounded-full bg-slate-100 dark:bg-slate-700/60 overflow-hidden">
                    <div class="h-full bg-sky-500 transition-all duration-500" :style="`width: ${persen}%`"></div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-stretch">
            {{-- Cetak --}}
            <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl shadow-xs overflow-hidden flex flex-col justify-between">
                <div>
                    <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700/80 bg-slate-50/60 dark:bg-slate-900/30">
                        <h4 class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider">Cetak Kwitansi</h4>
                    </div>
                    <div class="p-5">
                        @if (auth()->user()->hasPermission('collector_worksheet.print'))
                            <p class="text-xs text-slate-500 dark:text-slate-400 mb-3">
                                Hanya pembayaran yang <span class="font-semibold">setorannya sudah diperiksa kantor</span> yang muncul di sini —
                                kwitansi adalah dokumen kantor, bukan sesuatu yang terbit di lapangan. Selama uangnya masih di tas
                                kolektor, belum ada dasar menerbitkan bukti.
                            </p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mb-3">
                                Tiap kwitansi memuat QR berisi nomor pembayaran <span class="font-semibold">dan</span> nomor itu sebagai
                                teks — teksnya yang menyelamatkan berkas ketika QR-nya rusak.
                            </p>

                            {{-- POST, bukan GET: daftar kandidat bisa sampai 200 baris (batas
                                 query di controller), dan 200 payment_id di query string GET
                                 gampang lewat batas panjang URL server (414 Request-URI Too
                                 Large). Rute menerima GET & POST sekaligus, jadi cukup ganti
                                 method di sini tanpa menyentuh controller. --}}
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
                                    <div class="flex items-center justify-between px-3 py-2 bg-slate-50 dark:bg-slate-800/80 rounded-xl border border-slate-200/80 dark:border-slate-700/80 mb-2.5 text-xs">
                                        <label class="flex items-center gap-2 cursor-pointer select-none font-semibold text-slate-700 dark:text-slate-200">
                                            <input type="checkbox" x-model="selectAll" @change="toggleAll()" class="rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900 text-sky-600 focus:ring-sky-500 cursor-pointer w-4 h-4">
                                            <span>Pilih Semua Kwitansi Siap Cetak</span>
                                        </label>
                                        <span class="text-[11px] text-slate-500 dark:text-slate-400 font-mono font-medium">
                                            {{ $receiptCandidates->count() }} Pembayaran
                                        </span>
                                    </div>
                                @endif

                                <div class="divide-y divide-slate-100 dark:divide-slate-700/50 max-h-72 overflow-y-auto border border-slate-100 dark:border-slate-700 rounded-xl mb-3">
                                    @forelse ($receiptCandidates as $candidate)
                                        <label class="px-4 py-2.5 flex items-center gap-3 hover:bg-slate-50 dark:hover:bg-slate-700/30 cursor-pointer">
                                            <input type="checkbox" name="payment_ids[]" value="{{ $candidate->id }}" class="rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900">
                                            <div class="flex-1 min-w-0">
                                                <div class="text-sm font-medium text-slate-800 dark:text-slate-200 truncate">{{ $candidate->customer->full_name ?? '-' }}</div>
                                                <div class="text-[10px] text-slate-400 dark:text-slate-500 font-mono">{{ $candidate->payment_number }} &bull; Rp {{ number_format((float) $candidate->amount, 0, ',', '.') }}</div>
                                            </div>
                                        </label>
                                    @empty
                                        <div class="px-4 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                                            Belum ada yang bisa dicetak — semua pembayaran sudah punya kwitansi, atau setorannya masih menunggu verifikasi.
                                        </div>
                                    @endforelse
                                </div>

                                @if ($receiptCandidates->isNotEmpty())
                                    <button type="submit" class="w-full bg-slate-700 hover:bg-slate-800 text-white text-sm font-semibold py-2.5 rounded-xl transition-colors cursor-pointer">
                                        Buka Halaman Cetak
                                    </button>
                                @endif
                            </form>
                        @else
                            <p class="text-xs text-slate-400 dark:text-slate-500 text-center py-8">Anda tidak punya izin mencetak kwitansi.</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Upload --}}
            <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl shadow-xs overflow-hidden flex flex-col justify-between">
                <div>
                    <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700/80 bg-slate-50/60 dark:bg-slate-900/30">
                        <h4 class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider">Upload Kwitansi (bisa banyak sekaligus)</h4>
                    </div>
                    <div class="p-5">
                        @if (auth()->user()->hasPermission('collector_worksheet.upload'))
                            <p class="text-xs text-slate-500 dark:text-slate-400 mb-3">
                                Unggah berkas foto/scan atau PDF kwitansi penagihan. Sistem akan secara otomatis membaca QR atau nomor pembayaran di dalamnya.
                            </p>
                            <form action="{{ route('payment-receipts.store') }}" method="POST" enctype="multipart/form-data"
                                  x-data="receiptUpload()" @submit="mengirim = true" class="space-y-3">
                                @csrf
                                <input type="hidden" name="files_count" id="files_count" x-model="jumlah">

                                {{-- Drag & Drop Dropzone --}}
                                <div class="relative border-2 border-dashed border-slate-200 dark:border-slate-700 hover:border-sky-500 dark:hover:border-sky-400 bg-slate-50/50 dark:bg-slate-900/40 rounded-xl p-5 text-center transition-all cursor-pointer group"
                                     @dragover.prevent="isDragging = true"
                                     @dragleave.prevent="isDragging = false"
                                     @drop.prevent="isDragging = false; handleDrop($event)"
                                     :class="{ 'border-sky-500 bg-sky-50/50 dark:bg-sky-500/10': isDragging }">

                                    <input type="file" name="files[]" id="receipt_files" multiple required accept=".jpg,.jpeg,.png,.webp,.pdf"
                                           @change="pilih($event)"
                                           class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">

                                    <div class="flex flex-col items-center justify-center gap-2">
                                        <div class="w-10 h-10 rounded-full bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400 flex items-center justify-center group-hover:scale-110 transition-transform">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-xs font-semibold text-slate-700 dark:text-slate-200">
                                                <span class="text-sky-600 dark:text-sky-400">Klik untuk memilih berkas</span> atau tarik & lepas ke sini
                                            </p>
                                            <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">
                                                JPG, PNG, WEBP, atau PDF (Maks 8 MB/berkas, maks 100 berkas)
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                {{-- Ringkasan Pilihan --}}
                                <div x-show="jumlah > 0" x-cloak class="p-3 bg-sky-50/60 dark:bg-sky-500/10 border border-sky-200/60 dark:border-sky-500/20 rounded-xl flex items-center justify-between text-xs">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-sky-600 dark:text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        <span class="font-semibold text-sky-900 dark:text-sky-200">
                                            <span x-text="jumlah"></span> berkas terpilih (<span x-text="ukuran"></span>)
                                        </span>
                                    </div>
                                    <span class="text-[10px] text-sky-700 dark:text-sky-300 font-medium">Siap diunggah</span>
                                </div>

                                <p class="text-[11px] text-slate-400 dark:text-slate-500">
                                    Berkas identik yang terunggah dua kali tidak diproses ulang.
                                </p>

                                <button type="submit" x-bind:disabled="mengirim"
                                        class="w-full inline-flex items-center justify-center gap-2 bg-sky-600 hover:bg-sky-700 disabled:opacity-60 disabled:cursor-wait text-white text-sm font-semibold py-2.5 px-5 rounded-xl transition-colors cursor-pointer shadow-xs">
                                    <svg x-show="mengirim" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                                    </svg>
                                    <span x-text="mengirim ? 'Mengunggah…' : 'Unggah Kwitansi'">Unggah Kwitansi</span>
                                </button>
                            </form>
                        @else
                            <p class="text-xs text-slate-400 dark:text-slate-500 text-center py-8">Anda tidak punya izin mengunggah kwitansi.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Daftar berkas --}}
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl shadow-xs overflow-hidden">
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700/80 bg-slate-50/60 dark:bg-slate-900/30">
                <h4 class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider">Berkas Kwitansi</h4>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-700/50">
                @forelse ($receipts as $receipt)
                    @php
                        $badge = match ($receipt->status) {
                            \App\Enums\ReceiptStatus::MATCHED => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
                            \App\Enums\ReceiptStatus::PENDING, \App\Enums\ReceiptStatus::PROCESSING => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
                            \App\Enums\ReceiptStatus::MISMATCH, \App\Enums\ReceiptStatus::FAILED => 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',
                        };
                    @endphp

                    <div class="px-5 py-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-slate-800 dark:text-slate-200 truncate">{{ $receipt->original_filename }}</div>
                                <div class="text-[10px] text-slate-400 dark:text-slate-500">
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
                                    <div class="text-[11px] text-red-600 dark:text-red-400 mt-1">{{ $receipt->last_error }}</div>
                                @endif
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <span class="px-2.5 py-1 rounded-lg text-[11px] font-semibold {{ $badge }}">{{ $receipt->status->label() }}</span>

                                {{-- Kwitansi SATUAN pelanggan ini — dirender ulang dari data,
                                     bukan potongan gambar dari lembar borongan. Presisi
                                     sempurna, dan statusnya selalu yang terkini: pembayaran
                                     yang kelak ditolak akan tampil "Ditolak", sementara
                                     gambar beku akan terus menyatakan "Lunas". --}}
                                @if ($receipt->payment_id && auth()->user()->hasPermission('collector_worksheet.print'))
                                    <a href="{{ route('payment-receipts.print', ['collector' => $collector->id, 'payment_ids' => [$receipt->payment_id]]) }}"
                                       target="_blank" rel="noopener"
                                       class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">
                                        Kwitansi
                                    </a>
                                @endif

                                {{-- Lembar unggahan apa adanya — arsip bahwa kertasnya
                                     benar tercetak & diserahkan. Satu berkas unggahan bisa
                                     memuat banyak kwitansi (satu payment_id per baris hasil
                                     cocok), jadi labelnya bukan "kwitansi". --}}
                                <a href="{{ route('payment-receipts.download', $receipt->id) }}" class="text-xs font-semibold text-sky-600 dark:text-sky-400 hover:underline">Lembar asal</a>
                            </div>
                        </div>

                        @if ($receipt->status->needsAttention() && auth()->user()->hasPermission('collector_worksheet.upload'))
                            {{-- Override manual: status dokumen tidak boleh disandera
                                 keberhasilan mesin. QR sobek & OCR mati adalah kejadian
                                 normal, kwitansinya tetap harus sampai ke pelanggan yang benar. --}}
                            <form action="{{ route('payment-receipts.match', $receipt->id) }}" method="POST" class="mt-3 flex flex-col sm:flex-row gap-2">
                                @csrf
                                <select name="payment_id" required class="flex-1 text-sm px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-200 focus:outline-none focus:border-sky-500">
                                    <option value="">Cocokkan manual ke pembayaran...</option>
                                    @foreach ($receiptCandidates as $candidate)
                                        <option value="{{ $candidate->id }}">{{ $candidate->payment_number }} — {{ $candidate->customer->full_name ?? '-' }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="bg-slate-700 hover:bg-slate-800 text-white text-sm font-semibold py-2 px-5 rounded-lg transition-colors cursor-pointer">Cocokkan</button>
                            </form>
                        @endif

                        @if ($receipt->status === \App\Enums\ReceiptStatus::MATCHED && auth()->user()->hasPermission('collector_worksheet.upload'))
                            <form action="{{ route('payment-receipts.detach', $receipt->id) }}" method="POST" class="mt-2"
                                  data-confirm="Lepas kwitansi ini dari {{ $receipt->payment->payment_number ?? 'pembayarannya' }}? Berkasnya kembali menunggu dicocokkan.">
                                @csrf
                                <button type="submit" class="text-xs font-semibold text-red-600 dark:text-red-400 hover:underline cursor-pointer">Lepas kaitan</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <div class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400">Belum ada kwitansi diunggah untuk kolektor ini.</div>
                @endforelse
            </div>
            <div class="p-4 border-t border-slate-100 dark:border-slate-700/80">{{ $receipts->links() }}</div>
        </div>

    @else
        {{-- ============ TAB: ATUR PELANGGAN ============ --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            {{-- Column 1: Pelanggan Saat Ini --}}
            <div class="lg:col-span-6 bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl overflow-hidden shadow-xs">
                <div class="p-4 border-b border-slate-100 dark:border-slate-700/80 bg-slate-50/60 dark:bg-slate-800/40 flex items-center justify-between">
                    <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">
                        Pelanggan Saat Ini ({{ $assignedCustomers->total() }})
                    </h3>
                    <span class="text-[10px] text-slate-400 dark:text-slate-500">Kolektor: {{ $collector->name }}</span>
                </div>

                <div class="divide-y divide-slate-100 dark:divide-slate-700/50 max-h-[32rem] overflow-y-auto custom-scrollbar">
                    @forelse ($assignedCustomers as $customer)
                        <div class="p-3.5 sm:p-4 flex items-center justify-between gap-3 hover:bg-slate-50/60 dark:hover:bg-slate-700/25 transition-colors">
                            <div class="min-w-0">
                                <div class="font-semibold text-xs text-slate-900 dark:text-slate-100 truncate">{{ $customer->full_name }}</div>
                                <div class="text-[10px] text-slate-400 dark:text-slate-500 font-mono mt-0.5">
                                    {{ $customer->cid ?? $customer->customer_code }} &bull; {{ $customer->pop->name ?? '-' }}
                                </div>
                            </div>
                            <form action="{{ route('collector-worksheet.release', ['collector' => $collector->id, 'customer' => $customer->id]) }}" method="POST"
                                  data-confirm="Lepas pelanggan {{ $customer->full_name }} dari penugasan kolektor {{ $collector->name }}?">
                                @csrf
                                <button type="submit" class="px-2.5 py-1 text-xs font-semibold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 rounded-lg transition-all cursor-pointer">
                                    Lepas
                                </button>
                            </form>
                        </div>
                    @empty
                        <div class="p-8 text-center text-xs text-slate-400 dark:text-slate-500">Belum ada pelanggan ter-assign ke kolektor ini.</div>
                    @endforelse
                </div>

                <div class="p-4 border-t border-slate-100 dark:border-slate-700/80">
                    {{ $assignedCustomers->links() }}
                </div>
            </div>

            {{-- Column 2: Tambah / Pindahkan Pelanggan --}}
            <div class="lg:col-span-6 bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl overflow-hidden shadow-xs">
                <div class="p-4 border-b border-slate-100 dark:border-slate-700/80 bg-slate-50/60 dark:bg-slate-800/40">
                    <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">Tambah / Pindahkan Pelanggan</h3>
                </div>

                <div class="p-4 sm:p-5 space-y-4">
                    {{-- Form Cari Pelanggan --}}
                    <form action="{{ route('collector-worksheet.show', ['collector' => $collector->id, 'tab' => 'assign']) }}" method="GET" class="flex gap-2">
                        <input type="hidden" name="tab" value="assign">
                        <div class="relative flex-1">
                            <input type="text" name="search" value="{{ $search }}" placeholder="Cari nama, kode, atau CID..."
                                   class="w-full text-xs pl-8 pr-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-200 focus:outline-none focus:border-sky-500 font-sans">
                            <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                        <button type="submit" class="bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold py-2 px-4 rounded-xl transition-all cursor-pointer shrink-0 shadow-xs">
                            Cari
                        </button>
                    </form>

                    @if ($searchResults !== null)
                        <form action="{{ route('collector-worksheet.assign', $collector->id) }}" method="POST" class="space-y-3">
                            @csrf
                            <div class="divide-y divide-slate-100 dark:divide-slate-700/50 max-h-80 overflow-y-auto border border-slate-100 dark:border-slate-700/80 rounded-xl bg-white dark:bg-slate-900/40 custom-scrollbar">
                                @forelse ($searchResults as $customer)
                                    <label class="px-4 py-3 flex items-center gap-3 hover:bg-slate-50/80 dark:hover:bg-slate-700/30 cursor-pointer transition-colors">
                                        <input type="checkbox" name="customer_ids[]" value="{{ $customer->id }}"
                                               class="rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900 text-sky-600 focus:ring-sky-500/20">
                                        <div class="flex-1 min-w-0">
                                            <div class="text-xs font-semibold text-slate-800 dark:text-slate-200 truncate">{{ $customer->full_name }}</div>
                                            <div class="text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">
                                                {{ $customer->pop->name ?? '-' }}
                                                @if($customer->collector)
                                                    &bull; <span class="text-amber-600 dark:text-amber-400">Saat ini: {{ $customer->collector->name }}</span>
                                                @else
                                                    &bull; <span class="text-slate-400">Belum ada kolektor</span>
                                                @endif
                                            </div>
                                        </div>
                                    </label>
                                @empty
                                    <div class="px-4 py-8 text-center text-xs text-slate-400 dark:text-slate-500">Tidak ada pelanggan yang cocok dengan pencarian.</div>
                                @endforelse
                            </div>

                            @if ($searchResults->isNotEmpty())
                                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold py-2.5 rounded-xl transition-all cursor-pointer shadow-xs flex items-center justify-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                    <span>Assign Terpilih ke {{ $collector->name }}</span>
                                </button>
                            @endif

                            <div class="mt-2">{{ $searchResults->links() }}</div>
                        </form>
                    @else
                        <div class="p-8 text-center text-xs text-slate-400 dark:text-slate-500 bg-slate-50 dark:bg-slate-900/50 rounded-xl border border-dashed border-slate-200 dark:border-slate-700">
                            Ketik nama, kode, atau CID pelanggan di atas untuk mencari dan menambahkan/memindahkan penugasan ke kolektor ini.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
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
     *
     * Polling sederhana, bukan broadcast: tak butuh otorisasi channel, dan
     * berhenti sendiri begitu antrean nol. Interval 2 detik — pembacaan satu
     * lembar 200 kwitansi memakan ~1 detik, jadi lebih rapat cuma membebani
     * server tanpa terlihat bedanya.
     */
    function receiptProgress(config) {
        return {
            url: config.url,
            antre: (config.awal.pending || 0) + (config.awal.processing || 0),
            awalAntre: (config.awal.pending || 0) + (config.awal.processing || 0),
            // Diisi dari server, BUKAN nol. Kalau dimulai dari nol, penghitung
            // berkedip "0 0 0" dulu sebelum panggilan pertama selesai — dan
            // pada berkas yang pembacaannya sudah kelar, kedipan itulah
            // satu-satunya yang sempat dilihat admin.
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
                    // Jaringan putus bukan alasan menghentikan panel — coba lagi
                    // selama masih ada yang antre.
                    if (this.antre > 0) this.jadwalkan();
                    return;
                }

                const sebelumnya = this.antre;
                this.status = data.status;
                this.antre = data.antre;

                if (this.antre > this.awalAntre) this.awalAntre = this.antre;

                // Baru selesai pada siklus ini: beri tahu, lalu segarkan sekali
                // supaya daftar berkas menampilkan hasil akhirnya.
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
