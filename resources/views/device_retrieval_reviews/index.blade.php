@extends('layouts.app')

@section('title', 'Review Penarikan Alat - Whusnet Operasional')
@section('page_title', 'Review Penarikan Alat')

@php
    $tabLabels = [
        \App\Services\DeviceRetrievalReviewService::TAB_MENUNGGU => 'Menunggu Review',
        \App\Services\DeviceRetrievalReviewService::TAB_DITOLAK => 'Ditolak (Sedang Diulang)',
        \App\Services\DeviceRetrievalReviewService::TAB_DIBEBASKAN => 'Dibebaskan',
    ];
    $tolerance = \App\Services\DeviceRetrievalReviewService::TOLERANCE_ATTEMPTS;
    // Baris yang form-nya gagal validasi dibuka lagi otomatis (lihat _attempt).
    $oldRetrievalId = (int) old('retrieval_id');
    // Kolom tabel desktop; baris & header memakai template yang sama supaya sejajar.
    $gridCols = 'md:grid md:grid-cols-[minmax(0,1fr)_110px_190px_220px_96px] md:items-center md:gap-4';
@endphp

@section('content')
<div class="max-w-[1400px] mx-auto pb-16 md:pb-8">

    {{-- ══ Page Header — naked (Design.md §1.7) ══ --}}
    <div class="mb-5">
        <h1 class="text-[22px] font-bold leading-tight text-slate-900 dark:text-slate-50 tracking-tight">Review Penarikan Alat</h1>
        <p class="mt-1 max-w-3xl text-[13px] text-slate-500 dark:text-slate-400">
            Laporan deact yang modemnya gagal diambil. FOP memutuskan <span class="font-semibold text-slate-700 dark:text-slate-300">Approve</span> (pelanggan dibebaskan)
            atau <span class="font-semibold text-slate-700 dark:text-slate-300">Tolak</span> (deact diulang lewat task baru). Mulai percobaan ke-{{ $tolerance }} alasan boleh ditoleransi; approve sebelum itu wajib disertai catatan.
        </p>
    </div>

    @if($errors->any())
    <div class="alert-banner alert-banner-error" role="alert">
        <div class="alert-banner-icon">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
        </div>
        <div class="alert-banner-body">
            <p class="alert-banner-title">Aksi belum tersimpan</p>
            @foreach($errors->all() as $error)<p class="alert-banner-message">{{ $error }}</p>@endforeach
        </div>
    </div>
    @endif

    {{-- ══ Filter Bar — naked (Design.md §1.8) ══ --}}
    <form method="GET" action="{{ route('device-retrieval-reviews.index') }}" class="mb-4 flex flex-wrap items-center gap-2.5" role="search">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <label class="flex h-9 w-full sm:max-w-[360px] flex-1 items-center gap-2 rounded-full border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 focus-within:border-sky-600 focus-within:ring-[3px] focus-within:ring-sky-600/15">
            <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z"/></svg>
            <span class="sr-only">Cari pelanggan</span>
            <input type="search" name="q" value="{{ $search }}" placeholder="Cari nama / CID / no. HP"
                   class="min-w-0 flex-1 border-0 bg-transparent p-0 text-[13px] text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-0">
        </label>
        <button type="submit" class="btn-secondary !min-h-9 !py-1.5">Cari</button>
        @if($search !== '')
        <a href="{{ route('device-retrieval-reviews.index', ['tab' => $tab]) }}"
           class="inline-flex items-center gap-1 rounded-full bg-sky-50 dark:bg-sky-950/60 px-2.5 py-1 text-xs font-medium text-sky-700 dark:text-sky-300 hover:bg-sky-100 dark:hover:bg-sky-900/60"
           title="Hapus pencarian">
            "{{ $search }}"
            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </a>
        @endif
    </form>

    {{-- ══ Table Panel — satu-satunya card di halaman (card budget = 1) ══ --}}
    <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">

        {{-- Tab status: garis bawah internal panel, bukan tombol berkotak (§1.5) --}}
        <nav class="flex overflow-x-auto border-b border-slate-200 dark:border-slate-700 px-2 scrollbar-none" aria-label="Status review">
            @foreach($tabLabels as $key => $label)
            @php $isActive = $tab === $key; @endphp
            <a href="{{ route('device-retrieval-reviews.index', array_filter(['tab' => $key, 'q' => $search])) }}"
               @if($isActive) aria-current="page" @endif
               class="-mb-px inline-flex shrink-0 items-center gap-2 border-b-2 px-3 py-3 text-[13px] font-semibold whitespace-nowrap transition-colors
                      {{ $isActive ? 'border-sky-600 text-sky-700 dark:border-sky-400 dark:text-sky-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200' }}">
                {{ $label }}
                <span class="rounded-full px-1.5 py-px font-mono text-[11px] tabular-nums {{ $isActive ? 'bg-sky-50 text-sky-700 dark:bg-sky-950 dark:text-sky-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300' }}">{{ $tabCounts[$key] ?? 0 }}</span>
            </a>
            @endforeach
        </nav>

        @if($customers->isNotEmpty())
        {{-- Header kolom (desktop) --}}
        <div class="hidden {{ $gridCols }} border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 px-4 py-2.5 text-[10px] font-semibold uppercase tracking-[0.07em] text-slate-500 dark:text-slate-400">
            <span class="pl-7">Pelanggan</span>
            <span>Percobaan</span>
            <span>Laporan terakhir</span>
            <span>Status</span>
            <span class="text-right">Aksi</span>
        </div>
        @endif

        {{-- Satu baris per pelanggan; percobaan di-collapse supaya tidak memakan tempat. --}}
        <div class="divide-y divide-slate-200 dark:divide-slate-700">
            @forelse($customers as $customer)
            @php
                $attempts = $customer->caseAttempts;
                $attemptCount = $attempts->count();
                $latest = $attempts->last();
                $withinTolerance = \App\Services\DeviceRetrievalReviewService::isWithinTolerance($attemptCount);
                $showToleranceFlag = $withinTolerance && $tab === \App\Services\DeviceRetrievalReviewService::TAB_MENUNGGU;
                $hasOldInput = $attempts->contains(fn ($a) => $a->id === $oldRetrievalId);
                $latestBadge = match ($latest?->review_status) {
                    \App\Enums\DeviceRetrievalReviewStatus::MENUNGGU => 'badge badge-warning',
                    \App\Enums\DeviceRetrievalReviewStatus::DITOLAK => 'badge badge-error',
                    \App\Enums\DeviceRetrievalReviewStatus::DITUTUP => 'badge badge-neutral',
                    default => 'badge text-violet-700 bg-violet-50 border-violet-200 dark:text-violet-300 dark:bg-violet-950/50 dark:border-violet-800/60',
                };
            @endphp
            <section id="pelanggan-{{ $customer->id }}" x-data="{ open: @js($hasOldInput) }" class="scroll-mt-20">
                <div class="relative px-4 py-3 transition-colors hover:bg-slate-50/70 dark:hover:bg-slate-700/20 {{ $gridCols }}"
                     :class="open && 'bg-slate-50/70 dark:bg-slate-700/20'">
                    {{-- Penanda ≥ ambang toleransi: garis tipis di tepi kiri, bukan latar baris --}}
                    @if($showToleranceFlag)
                    <span class="absolute inset-y-0 left-0 w-[3px] bg-amber-400 dark:bg-amber-500" aria-hidden="true"></span>
                    @endif

                    <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-controls="percobaan-{{ $customer->id }}"
                            class="flex w-full min-w-0 items-center gap-3 rounded-lg text-left cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/40">
                        <svg class="h-4 w-4 shrink-0 text-slate-400 transition-transform duration-200" :class="open && 'rotate-90'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7"/></svg>
                        <span class="min-w-0">
                            <span class="block truncate text-[13px] font-semibold text-slate-900 dark:text-slate-100">{{ $customer->full_name }}</span>
                            <span class="mt-0.5 flex flex-wrap items-center gap-x-1.5 text-xs text-slate-500 dark:text-slate-400">
                                <span class="font-mono text-sky-700 dark:text-sky-400">{{ $customer->display_id }}</span>
                                <span class="text-slate-300 dark:text-slate-600">·</span>
                                <span>{{ $customer->pop->name ?? '-' }}</span>
                            </span>
                        </span>
                    </button>

                    {{-- Kolom data — di mobile jadi baris meta di bawah nama --}}
                    <div class="mt-2 flex flex-wrap items-center gap-2 pl-7 md:mt-0 md:block md:pl-0">
                        <span class="font-mono text-[13px] font-semibold tabular-nums text-slate-900 dark:text-slate-100">
                            Percobaan {{ $attemptCount }}<span class="md:hidden">,</span>
                        </span>
                        @if($showToleranceFlag)
                        <span class="block text-[11px] font-medium text-amber-700 dark:text-amber-400 md:mt-0.5">≥ {{ $tolerance }}×</span>
                        @endif
                    </div>
                    <div class="mt-1 pl-7 text-xs md:mt-0 md:pl-0">
                        @if($latest)
                        <span class="block font-mono tabular-nums text-slate-700 dark:text-slate-300">{{ $latest->created_at->translatedFormat('d M Y, H:i') }}</span>
                        <span class="block truncate text-slate-500 dark:text-slate-400">{{ $latest->outcome->label() }}</span>
                        @endif
                    </div>
                    <div class="mt-2 flex flex-wrap items-center gap-1.5 pl-7 md:mt-0 md:pl-0">
                        @if($latest)
                        <span class="{{ $latestBadge }}">{{ $latest->review_status->label() }}</span>
                        @endif
                        @if($showToleranceFlag)
                        <span class="badge badge-warning">Sudah {{ $tolerance }}× percobaan — boleh ditoleransi</span>
                        @endif
                    </div>
                    <div class="mt-3 flex items-center gap-2 pl-7 md:mt-0 md:justify-end md:pl-0">
                        <a href="{{ route('device-retrieval-reviews.show', $customer) }}"
                           class="inline-flex h-8 items-center gap-1 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                            Detail
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>

                {{-- Riwayat percobaan (terbaru di atas) — area datar di dalam panel, bukan card baru --}}
                <div id="percobaan-{{ $customer->id }}" x-show="open" x-collapse x-cloak
                     class="border-t border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-900/30">
                    <ol class="px-4 py-2 sm:pl-11 sm:pr-6">
                        @foreach($attempts->reverse() as $index => $attempt)
                            @include('device_retrieval_reviews._attempt', [
                                'attempt' => $attempt,
                                'number' => $index + 1,
                                'isLast' => $loop->last,
                                'returnTo' => 'list',
                            ])
                        @endforeach
                    </ol>
                </div>
            </section>
            @empty
            {{-- Empty state (Design.md §14.2) --}}
            <div class="flex flex-col items-center justify-center gap-3 px-6 py-14 text-center">
                <svg class="h-12 w-12 text-slate-400 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H6.911a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661Z"/></svg>
                @if($search !== '')
                <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Tidak ada yang cocok</h3>
                <p class="max-w-sm text-[13px] leading-relaxed text-slate-500 dark:text-slate-400">Tidak ada pelanggan di tab ini yang cocok dengan "{{ $search }}".</p>
                <a href="{{ route('device-retrieval-reviews.index', ['tab' => $tab]) }}" class="btn-secondary">Reset Pencarian</a>
                @else
                <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">Tidak ada pelanggan di tab ini</h3>
                <p class="max-w-sm text-[13px] leading-relaxed text-slate-500 dark:text-slate-400">
                    @if($tab === \App\Services\DeviceRetrievalReviewService::TAB_MENUNGGU)
                    Semua laporan penarikan alat sudah diputuskan.
                    @else
                    Belum ada kasus dengan status ini.
                    @endif
                </p>
                @endif
            </div>
            @endforelse
        </div>

        @if($customers->hasPages())
        <div class="border-t border-slate-200 dark:border-slate-700 px-4 py-3">
            {{ $customers->links() }}
        </div>
        @endif
    </div>
</div>

<x-ui.image-preview-modal />
@endsection
