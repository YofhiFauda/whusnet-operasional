@extends('layouts.app')

@section('title', 'Catat Pembayaran Teknisi - Whusnet Operasional')
@section('page_title', 'Catat Pembayaran')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto pb-16">toDateString()),
        bankAccounts: @js($bankAccounts),
     })">

    {{-- Header & Breadcrumbs --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mb-1.5 font-medium">
                <span>Operasional</span>
                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span>Billing &amp; Kasir</span>
                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-amber-600 dark:text-amber-400 font-semibold">Jalur Lapangan</span>
            </div>
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-slate-100 tracking-tight">Catat Pembayaran Lapangan</h1>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                    Mode Teknisi
                </span>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                Pencatatan langsung tagihan pelanggan dalam POP Anda (tanpa antrean penugasan rute).
            </p>
        </div>

        {{-- Quick Guide Chip --}}
        <div class="hidden sm:flex items-center gap-2 px-3.5 py-2 rounded-2xl bg-white dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700/80 shadow-2xs text-xs text-slate-600 dark:text-slate-300">
            <div class="w-7 h-7 rounded-xl bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <span>Batas setor harian pukul <strong>{{ $closeTime }} WIB</strong></span>
        </div>
    </div>

    @if ($errors->any())
        <x-ui.alert variant="error" title="Setoran Gagal" class="rounded-2xl">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </x-ui.alert>
    @endif

    @if (session('success'))
        <x-ui.alert variant="success" class="rounded-2xl">{{ session('success') }}</x-ui.alert>
    @endif

    {{-- Bento Overview: Saldo & Status Setoran --}}
    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
        {{-- Card Saldo Kas di Tangan --}}
        <div class="md:col-span-7 bg-gradient-to-br from-slate-900 via-slate-850 to-slate-900 text-white rounded-3xl p-5 sm:p-6 shadow-sm border border-slate-800 relative overflow-hidden flex flex-col justify-between">
            <div class="absolute -right-8 -bottom-8 w-40 h-40 bg-amber-500/10 rounded-full blur-2xl pointer-events-none"></div>
            
            <div>
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></div>
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Kas di Tangan (Belum Disetor)</span>
                    </div>
                    <span class="px-2.5 py-0.5 rounded-full bg-slate-800 text-slate-300 border border-slate-700 text-[11px] font-medium font-mono">
                        {{ auth()->user()->name }}
                    </span>
                </div>

                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-4xl font-bold font-mono tracking-tight text-white">
                        Rp {{ number_format($saldo, 0, ',', '.') }}
                    </span>
                </div>

                <p class="text-xs text-slate-400 mt-1">{{ $unsettledCount }} transaksi belum disetor</p>
                <p class="text-xs text-slate-400 mt-1">
                    @if($saldo > 0)
                        Uang tunai pelanggan yang wajib diserahkan ke admin kasir.
                    @else
                        Tidak ada saldo tertahan di tangan Anda saat ini.
                    @endif
                </p>
            </div>

            <div class="mt-5 pt-4 border-t border-slate-800 flex flex-wrap items-center justify-between gap-3">
                <div class="text-[11px] text-slate-400 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Cut-off setor: {{ $closeTime }} WIB</span>
                </div>

                @if($saldo > 0)
                    <form method="POST" action="{{ $depositUrl }}"
                          data-confirm="Setorkan seluruh saldo Rp {{ number_format($saldo, 0, ',', '.') }} ({{ $unsettledCount }} pembayaran) ke admin? Setoran akan menunggu verifikasi.">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <button type="submit"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 text-slate-950 font-bold text-xs sm:text-sm shadow-md transition-all active:scale-95 cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                            <span>Setor Seluruh Saldo</span>
                        </button>
                    </form>
                @endif
            </div>
            @if ($pendingDeposits->isNotEmpty())
                <div class="mt-4 space-y-2">
                    <div class="text-[11px] font-bold uppercase text-slate-400">Setoran Menunggu / Kurang</div>
                    @foreach ($pendingDeposits as $deposit)
                        <div class="flex flex-wrap justify-between gap-2 rounded-xl bg-slate-800 p-2 text-xs">
                            <span class="font-mono">{{ $deposit->deposit_number }}</span>
                            <span>{{ $deposit->status->label() }}</span>
                        </div>
                    @endforeach
                    <a href="{{ route('technician-payments.index', ['tab' => 'setoran']) }}" class="inline-block text-xs font-semibold text-amber-300">Lihat seluruh riwayat setoran</a>
                </div>
            @endif
        </div>

        {{-- Card Status Kepatuhan Setoran --}}
        <div class="md:col-span-5 rounded-3xl p-5 sm:p-6 border shadow-2xs flex flex-col justify-between transition-all {{ $belumSetor ? 'bg-amber-50/90 dark:bg-amber-500/10 border-amber-300 dark:border-amber-500/30' : 'bg-white dark:bg-slate-800/90 border-slate-200/80 dark:border-slate-700/80' }}">
            <div>
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider {{ $belumSetor ? 'text-amber-700 dark:text-amber-400' : 'text-slate-400 dark:text-slate-500' }}">
                        Status Rekonsiliasi
                    </span>
                    <div class="w-8 h-8 rounded-xl {{ $belumSetor ? 'bg-amber-100 dark:bg-amber-500/20 text-amber-600 dark:text-amber-400' : 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' }} flex items-center justify-center shrink-0">
                        @if($belumSetor)
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        @else
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        @endif
                    </div>
                </div>

                <div class="mt-2.5">
                    @if($belumSetor)
                        <div class="text-lg font-bold text-amber-800 dark:text-amber-300 flex items-center gap-2">
                            <span>Perlu Setor Segera</span>
                        </div>
                        <p class="text-xs text-amber-700/90 dark:text-amber-300/90 mt-1 leading-relaxed">
                            Terdapat penerimaan uang sebelum pukul {{ $closeTime }} yang belum disetor. Ini adalah pengingat operasional, pencatatan baru tetap dapat dilakukan.
                        </p>
                    @else
                        <div class="text-lg font-bold text-emerald-700 dark:text-emerald-400 flex items-center gap-1.5">
                            <span>Status Tertib</span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                            Tidak ada penerimaan yang melewati batas setor harian. Status setoran menunggu verifikasi dapat dilihat di Riwayat Setoran.
                        </p>
                    @endif
                </div>
            </div>

            <div class="mt-4 pt-3 border-t {{ $belumSetor ? 'border-amber-200 dark:border-amber-500/20' : 'border-slate-100 dark:border-slate-700/60' }} text-xs">
                <div class="text-slate-500 dark:text-slate-400">Kurang Setor</div>
                <div class="font-mono font-bold {{ $outstandingShortfall > 0 ? 'text-rose-700 dark:text-rose-300' : 'text-slate-900 dark:text-slate-100' }}">Rp {{ number_format($outstandingShortfall, 0, ',', '.') }}</div>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">Selisih tetap tercatat sampai dilunasi atau diselesaikan admin.</p>
            </div>
        </div>
    </div>

    <nav class="flex gap-2 border-b border-slate-200 dark:border-slate-700" aria-label="Pembayaran Teknisi">
        <a href="{{ route('technician-payments.index', ['tab' => 'catat']) }}" class="px-4 py-3 text-sm font-semibold border-b-2 {{ $tab === 'catat' ? 'border-amber-500 text-amber-700 dark:text-amber-300' : 'border-transparent text-slate-500 dark:text-slate-400' }}">Catat Pembayaran</a>
        <a href="{{ route('technician-payments.index', ['tab' => 'bayar']) }}" class="px-4 py-3 text-sm font-semibold border-b-2 {{ $tab === 'bayar' ? 'border-amber-500 text-amber-700 dark:text-amber-300' : 'border-transparent text-slate-500 dark:text-slate-400' }}">Sudah Bayar ({{ $unsettledCount }})</a>
        <a href="{{ route('technician-payments.index', ['tab' => 'setoran']) }}" class="px-4 py-3 text-sm font-semibold border-b-2 {{ $tab === 'setoran' ? 'border-amber-500 text-amber-700 dark:text-amber-300' : 'border-transparent text-slate-500 dark:text-slate-400' }}">Riwayat Setoran</a>
    </nav>

    @if ($tab === 'catat')
        <div id="batch-alert" class="hidden rounded-xl border p-4 text-sm"></div>
        <form method="GET" action="{{ route('technician-payments.index') }}" class="flex flex-wrap gap-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
            <input type="hidden" name="tab" value="catat">
            <label for="technician-search" class="w-full text-sm font-semibold text-slate-700 dark:text-slate-200">Cari pelanggan atau tagihan dalam POP Anda</label>
            <input id="technician-search" name="search" type="search" minlength="3" value="{{ $search }}" placeholder="Nama, CID, kode pelanggan, atau nomor tagihan" class="min-w-0 flex-1 rounded-xl border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            <button type="submit" class="rounded-xl bg-sky-600 px-5 py-2 text-sm font-semibold text-white">Cari</button>
        </form>
        @if (mb_strlen($search) >= 3)
            @include('partials.collector-pay-table', [
                'invoices' => $invoices,
                'canLogVisit' => false,
                'emptyMessage' => 'Tidak ada tagihan pelanggan dalam POP Anda yang cocok.',
            ])
            @push('scripts')
                @include('partials.collector-pay-script', [
                    'storeUrl' => route('technician-payments.store'),
                    'keyPrefix' => 'teknisi-' . auth()->id(),
                    'colspan' => 9,
                    'reloadOnSuccess' => true,
                    'emptyMessage' => 'Tidak ada tagihan pelanggan dalam POP Anda yang cocok.',
                ])
            @endpush
        @else
            <p class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500 dark:border-slate-700">Ketik minimal 3 karakter untuk melihat tagihan dan mencatat pembayaran.</p>
        @endif
    @elseif ($tab === 'bayar')
        @include('technician-payments._unsettled')
    @else
        @include('technician-payments._deposits')
    @endif
</div>

@endsection

