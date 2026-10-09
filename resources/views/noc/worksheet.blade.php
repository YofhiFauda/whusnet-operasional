@extends('layouts.app')

@section('title', 'Worksheet NOC — Operations & Service Desk')
@section('page_title', 'Worksheet NOC')

@php
    use App\Http\Controllers\TicketHistoryController;
    use App\Support\IndonesianDate;

    // Hitung ringkasan metrik dari koleksi aktif
    $collection = $tickets->getCollection();
    $urgentCount = $collection->filter(fn($t) => in_array($t->priority?->value, ['Urgent', 'High']))->count();
    $batchCount = $collection->filter(fn($t) => $t->isBatch())->count();
    $agingAlertCount = $collection->filter(fn($t) => $t->created_at->diffInMinutes(now()) >= 480)->count();
@endphp

@section('content')
<div class="space-y-5 pb-16" x-data="nocWorksheet()" @click="closeActionMenu()" @keydown.escape.window="closeActionMenu()">

    {{-- =========================================================================
         1. HERO HEADER & QUICK KPI METRICS DECK
         ========================================================================= --}}
    <div class="bg-gradient-to-br from-white via-slate-50/40 to-slate-100/30 dark:from-slate-900/90 dark:via-slate-900/70 dark:to-slate-800/50 border border-slate-200/60 dark:border-slate-800 rounded-2xl p-4 sm:p-6 shadow-xs relative overflow-hidden">
        {{-- Background decorative ambient glow --}}
        <div class="absolute -right-16 -top-16 w-64 h-64 bg-sky-500/10 dark:bg-sky-500/5 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -left-16 -bottom-16 w-64 h-64 bg-amber-500/10 dark:bg-amber-500/5 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-1 flex flex-col xl:flex-row xl:items-center xl:justify-between gap-5">
            {{-- Title & Info --}}
            <div class="space-y-1.5 min-w-0">
                <div class="flex items-center gap-2.5 flex-wrap">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-widest bg-sky-500/10 text-sky-700 dark:text-sky-300 border border-sky-300/30 dark:border-sky-800/60">
                        <span class="w-1.5 h-1.5 rounded-full bg-sky-500 animate-pulse"></span>
                        NOC Operations &amp; Escalation
                    </span>
                    @if($batchCount > 0)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-violet-500/10 text-violet-700 dark:text-violet-300 border border-violet-300/30 dark:border-violet-800/60">
                            <span class="w-1.5 h-1.5 rounded-full bg-violet-500 animate-ping"></span>
                            {{ $batchCount }} Insiden Massal
                        </span>
                    @endif
                </div>

                <div class="flex items-center gap-3">
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-slate-100 tracking-tight flex items-center gap-2">
                        Worksheet NOC
                    </h1>
                    <span class="inline-flex items-center justify-center px-2.5 py-0.5 text-xs font-mono font-black rounded-lg bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900 shadow-2xs">
                        {{ $tickets->total() }} Tiket
                    </span>
                </div>

                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 max-w-3xl leading-relaxed">
                    @if($tab === 'assign_fop')
                        Daftar tiket yang telah diteruskan dari NOC ke tim teknisi lapangan (FOP). Pemantauan progres real-time berlangsung di modul Task FOP.
                    @else
                        Antrean tiket teknis yang sedang ditangani meja NOC. Klik baris / kartu untuk investigasi mendalam, koordinasi, atau aksi eskalasi cepat.
                    @endif
                </p>
            </div>

            {{-- Quick KPI Metric Cards --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 shrink-0">
                {{-- Card 1: Total Open --}}
                <div class="p-3 rounded-xl border border-slate-100 dark:border-slate-800 bg-white/90 dark:bg-slate-800/60 backdrop-blur-xs flex flex-col justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 text-sky-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                        Antrean
                    </span>
                    <span class="text-lg sm:text-xl font-black font-mono text-slate-900 dark:text-slate-100 mt-1">
                        {{ $tabCounts['masuk'] }}
                    </span>
                </div>

                {{-- Card 2: Forwarded FOP --}}
                <div class="p-3 rounded-xl border border-slate-100 dark:border-slate-800 bg-white/90 dark:bg-slate-800/60 backdrop-blur-xs flex flex-col justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                        Ke FOP
                    </span>
                    <span class="text-lg sm:text-xl font-black font-mono text-indigo-600 dark:text-indigo-400 mt-1">
                        {{ $tabCounts['assign_fop'] }}
                    </span>
                </div>

                {{-- Card 3: Urgent / High --}}
                <div class="p-3 rounded-xl border border-slate-100 dark:border-slate-800 bg-white/90 dark:bg-slate-800/60 backdrop-blur-xs flex flex-col justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        Urgent/High
                    </span>
                    <span class="text-lg sm:text-xl font-black font-mono text-rose-600 dark:text-rose-400 mt-1">
                        {{ $urgentCount }}
                    </span>
                </div>

                {{-- Card 4: Aging Alert --}}
                <div class="p-3 rounded-xl border border-slate-100 dark:border-slate-800 bg-white/90 dark:bg-slate-800/60 backdrop-blur-xs flex flex-col justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        &gt; 8 Jam
                    </span>
                    <span class="text-lg sm:text-xl font-black font-mono text-amber-600 dark:text-amber-400 mt-1">
                        {{ $agingAlertCount }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- =========================================================================
         2. TABS & VIEW CONTROLS (SIMETRIS & HARMONIS)
         ========================================================================= --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        {{-- Navigation Tabs (Masuk vs Assign FOP) --}}
        <div class="inline-flex items-center p-1 rounded-xl bg-slate-100/70 dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 text-xs shadow-2xs">
            @foreach([
                'masuk' => [
                    'label' => 'Tiket Masuk',
                    'icon' => 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4',
                    'badge' => 'bg-amber-600 text-white'
                ],
                'assign_fop' => [
                    'label' => 'Assign FOP',
                    'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
                    'badge' => 'bg-sky-600 text-white'
                ],
            ] as $tabValue => $meta)
                <a href="{{ route('noc.worksheet', array_merge(request()->except(['tab', 'page']), ['tab' => $tabValue])) }}"
                   class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg font-semibold transition-all {{ $tab === $tabValue ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 shadow-xs font-bold border border-slate-200/50 dark:border-slate-700/60' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 hover:bg-white/50 dark:hover:bg-slate-800/40' }}">
                    <svg class="w-4 h-4 shrink-0 {{ $tab === $tabValue ? 'text-sky-600 dark:text-sky-400' : 'text-slate-400 dark:text-slate-500' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $meta['icon'] }}" />
                    </svg>
                    <span>{{ $meta['label'] }}</span>
                    <span class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-bold font-mono {{ $meta['badge'] }}">
                        {{ $tabCounts[$tabValue] }}
                    </span>
                </a>
            @endforeach
        </div>

        {{-- View Layout Mode Switcher (Symmetric Container Styling) --}}
        <div class="inline-flex items-center p-1 rounded-xl bg-slate-100/70 dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 text-xs shadow-2xs self-start sm:self-auto">
            <button type="button" @click="setViewMode('table')"
                    :class="viewMode === 'table' ? 'bg-white dark:bg-slate-800 text-sky-600 dark:text-sky-400 font-bold shadow-xs border border-slate-200/50 dark:border-slate-700/60' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 hover:bg-white/50 dark:hover:bg-slate-800/40'"
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg font-semibold transition-all cursor-pointer"
                    title="Tampilan Tabel Lengkap">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                </svg>
                <span>Tabel</span>
            </button>
            <button type="button" @click="setViewMode('cards')"
                    :class="viewMode === 'cards' ? 'bg-white dark:bg-slate-800 text-sky-600 dark:text-sky-400 font-bold shadow-xs border border-slate-200/50 dark:border-slate-700/60' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 hover:bg-white/50 dark:hover:bg-slate-800/40'"
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg font-semibold transition-all cursor-pointer"
                    title="Tampilan Kartu / Feed">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                </svg>
                <span>Kartu</span>
            </button>
        </div>
    </div>

    {{-- =========================================================================
         3. SEARCH & ADVANCED FILTER PANEL
         ========================================================================= --}}
    @php
        $activeSecondaryFilters = array_filter(array_diff_key($filters, ['q' => '']));
        $hasActiveSecondary = count($activeSecondaryFilters) > 0;
        $totalActiveFilters = count(array_filter($filters));
    @endphp

    <form method="GET" action="{{ route('noc.worksheet') }}"
          x-data="{ showFilters: {{ $hasActiveSecondary ? 'true' : 'false' }} }"
          class="rounded-2xl border border-slate-200/60 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xs transition-all overflow-hidden">
        <input type="hidden" name="tab" value="{{ $tab }}">

        {{-- Baris Utama (Search Bar + Action Hub) --}}
        <div class="p-3.5 sm:p-4 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
            {{-- Input Cari Utama --}}
            <div class="relative flex-1">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 dark:text-slate-500">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input type="text" name="q" value="{{ $filters['q'] }}"
                       placeholder="Cari nomor tiket, nama pelanggan, CID, nomor HP, desa, keluhan..."
                       class="w-full pl-10 pr-9 text-xs sm:text-sm rounded-xl border border-slate-200/80 dark:border-slate-700/80 bg-slate-50/40 dark:bg-slate-800/60 py-2.5 text-slate-900 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all font-medium">
                @if($filters['q'])
                    <a href="{{ route('noc.worksheet', array_merge(request()->except(['q', 'page']), ['tab' => $tab])) }}"
                       class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-rose-500 transition-colors"
                       title="Hapus pencarian">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </a>
                @endif
            </div>

            {{-- Tombol Filter & Aksi --}}
            <div class="flex items-center gap-2 shrink-0">
                <button type="button" @click="showFilters = !showFilters"
                        class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-slate-200/80 dark:border-slate-700/80 bg-slate-50/40 dark:bg-slate-800/60 hover:bg-slate-100/70 dark:hover:bg-slate-700/60 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-colors cursor-pointer flex-1 sm:flex-initial"
                        :class="{ 'border-sky-500 text-sky-600 dark:text-sky-400 bg-sky-50/60 dark:bg-sky-950/40': showFilters || {{ $hasActiveSecondary ? 'true' : 'false' }} }">
                    <svg class="w-4 h-4 text-slate-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                    <span>Filter</span>
                    @if(count($activeSecondaryFilters) > 0)
                        <span class="px-1.5 py-0.5 text-[10px] font-bold font-mono rounded-full bg-sky-600 text-white">
                            {{ count($activeSecondaryFilters) }}
                        </span>
                    @endif
                    <svg class="w-3.5 h-3.5 text-slate-400 dark:text-slate-500 transition-transform duration-200" :class="{ 'rotate-180': showFilters }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <button type="submit" class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl bg-sky-600 text-white text-xs font-bold uppercase tracking-wider hover:bg-sky-700 active:scale-95 transition-all cursor-pointer shadow-xs flex-1 sm:flex-initial">
                    Terapkan
                </button>

                @if($totalActiveFilters > 0)
                    <a href="{{ route('noc.worksheet', ['tab' => $tab]) }}"
                       class="px-3 py-2.5 rounded-xl text-xs font-semibold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors inline-flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.038 8.038 0 01-15.357-2m15.357 2H15"/></svg>
                        <span>Reset</span>
                    </a>
                @endif
            </div>
        </div>

        {{-- Active Filters Summary Pills --}}
        @if($totalActiveFilters > 0)
            <div class="px-3.5 sm:px-4 pb-3 flex items-center gap-1.5 flex-wrap border-t border-slate-100 dark:border-slate-800/80 pt-2.5 bg-slate-50/40 dark:bg-slate-900/40">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mr-1">Filter Aktif:</span>

                @if($filters['q'])
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700/80 text-slate-900 dark:text-slate-100 shadow-2xs">
                        <span class="text-slate-400 dark:text-slate-500">Cari:</span> "{{ $filters['q'] }}"
                        <a href="{{ route('noc.worksheet', array_merge(request()->except(['q', 'page']), ['tab' => $tab])) }}" class="text-slate-400 hover:text-rose-500 font-bold ml-0.5">×</a>
                    </span>
                @endif

                @if($filters['pop_id'])
                    @php $popName = collect($popOptions)->firstWhere('id', (int) $filters['pop_id'])?->name; @endphp
                    @if($popName)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700/80 text-slate-900 dark:text-slate-100 shadow-2xs">
                            <span class="text-slate-400 dark:text-slate-500">POP:</span> {{ $popName }}
                            <a href="{{ route('noc.worksheet', array_merge(request()->except(['pop_id', 'page']), ['tab' => $tab])) }}" class="text-slate-400 hover:text-rose-500 font-bold ml-0.5">×</a>
                        </span>
                    @endif
                @endif

                @if($filters['issue_category_id'])
                    @php $catName = collect($categoryOptions)->firstWhere('id', (int) $filters['issue_category_id'])?->name; @endphp
                    @if($catName)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700/80 text-slate-900 dark:text-slate-100 shadow-2xs">
                            <span class="text-slate-400 dark:text-slate-500">Kategori:</span> {{ $catName }}
                            <a href="{{ route('noc.worksheet', array_merge(request()->except(['issue_category_id', 'page']), ['tab' => $tab])) }}" class="text-slate-400 hover:text-rose-500 font-bold ml-0.5">×</a>
                        </span>
                    @endif
                @endif

                @if($filters['priority'])
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700/80 text-slate-900 dark:text-slate-100 shadow-2xs">
                        <span class="text-slate-400 dark:text-slate-500">Prioritas:</span> {{ $filters['priority'] }}
                        <a href="{{ route('noc.worksheet', array_merge(request()->except(['priority', 'page']), ['tab' => $tab])) }}" class="text-slate-400 hover:text-rose-500 font-bold ml-0.5">×</a>
                    </span>
                @endif

                @if($filters['type'])
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700/80 text-slate-900 dark:text-slate-100 shadow-2xs">
                        <span class="text-slate-400 dark:text-slate-500">Tipe:</span> {{ $filters['type'] }}
                        <a href="{{ route('noc.worksheet', array_merge(request()->except(['type', 'page']), ['tab' => $tab])) }}" class="text-slate-400 hover:text-rose-500 font-bold ml-0.5">×</a>
                    </span>
                @endif

                @if($filters['created_by'])
                    @php $creatorName = collect($creatorOptions)->firstWhere('id', (int) $filters['created_by'])?->name; @endphp
                    @if($creatorName)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700/80 text-slate-900 dark:text-slate-100 shadow-2xs">
                            <span class="text-slate-400 dark:text-slate-500">Pengirim:</span> {{ $creatorName }}
                            <a href="{{ route('noc.worksheet', array_merge(request()->except(['created_by', 'page']), ['tab' => $tab])) }}" class="text-slate-400 hover:text-rose-500 font-bold ml-0.5">×</a>
                        </span>
                    @endif
                @endif

                @if($filters['date_from'] || $filters['date_to'])
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700/80 text-slate-900 dark:text-slate-100 shadow-2xs">
                        <span class="text-slate-400 dark:text-slate-500">Tanggal:</span> {{ $filters['date_from'] ?: '—' }} s/d {{ $filters['date_to'] ?: '—' }}
                        <a href="{{ route('noc.worksheet', array_merge(request()->except(['date_from', 'date_to', 'page']), ['tab' => $tab])) }}" class="text-slate-400 hover:text-rose-500 font-bold ml-0.5">×</a>
                    </span>
                @endif
            </div>
        @endif

        {{-- Expandable Secondary Filter Fields --}}
        <div x-show="showFilters" x-collapse x-cloak class="p-4 border-t border-slate-100 dark:border-slate-800/80 bg-slate-50/40 dark:bg-slate-900/60 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">POP / Cabang</label>
                    <select name="pop_id" class="w-full text-xs rounded-xl border border-slate-200/80 dark:border-slate-700/80 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                        <option value="">Semua POP</option>
                        @foreach($popOptions as $pop)
                            <option value="{{ $pop->id }}" @selected((string) $filters['pop_id'] === (string) $pop->id)>{{ $pop->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Kategori Issue</label>
                    <select name="issue_category_id" class="w-full text-xs rounded-xl border border-slate-200/80 dark:border-slate-700/80 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                        <option value="">Semua Kategori</option>
                        @foreach($categoryOptions as $category)
                            <option value="{{ $category->id }}" @selected((string) $filters['issue_category_id'] === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Prioritas</label>
                    <select name="priority" class="w-full text-xs rounded-xl border border-slate-200/80 dark:border-slate-700/80 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                        <option value="">Semua Prioritas</option>
                        @foreach($priorityOptions as $priority)
                            <option value="{{ $priority->value }}" @selected($filters['priority'] === $priority->value)>{{ $priority->value }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Tipe Tiket</label>
                    <select name="type" class="w-full text-xs rounded-xl border border-slate-200/80 dark:border-slate-700/80 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                        <option value="">Semua Tipe</option>
                        @foreach($typeOptions as $opt)
                            <option value="{{ $opt['value'] }}" @selected($filters['type'] === $opt['value'])>{{ $opt['value'] }} — {{ $opt['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Dikirim Oleh (Helpdesk)</label>
                    <select name="created_by" class="w-full text-xs rounded-xl border border-slate-200/80 dark:border-slate-700/80 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                        <option value="">Semua User</option>
                        @foreach($creatorOptions as $creator)
                            <option value="{{ $creator->id }}" @selected((string) $filters['created_by'] === (string) $creator->id)>{{ $creator->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Dari Tanggal</label>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] }}"
                           class="w-full text-xs rounded-xl border border-slate-200/80 dark:border-slate-700/80 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Sampai Tanggal</label>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] }}"
                           class="w-full text-xs rounded-xl border border-slate-200/80 dark:border-slate-700/80 bg-white dark:bg-slate-800 px-3 py-2 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                </div>
            </div>
        </div>
    </form>

    {{-- =========================================================================
         4. DATA PRESENTATION (RESPONSIVE TABLE VIEW & TOUCH-FRIENDLY CARD VIEW)
         ========================================================================= --}}
    @if($tickets->count() > 0)

        {{-- 4A. DESKTOP & LAPTOP HIGH-DENSITY DATA TABLE --}}
        <div x-show="viewMode === 'table'" class="rounded-2xl border border-slate-200/60 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xs overflow-hidden">
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left text-xs border-collapse whitespace-nowrap">
                    <thead class="bg-slate-50/60 dark:bg-slate-800/40 text-slate-400 dark:text-slate-500 border-b border-slate-100 dark:border-slate-800/80">
                        <tr class="text-left font-bold uppercase tracking-wider text-[10px]">
                            <th class="px-3.5 py-3">Waktu Masuk</th>
                            <th class="px-3.5 py-3">Tiket</th>
                            <th class="px-3.5 py-3">Pelanggan / CID</th>
                            <th class="px-3.5 py-3">Kontak &amp; Lokasi</th>
                            <th class="px-3.5 py-3">POP</th>
                            <th class="px-3.5 py-3 min-w-[200px]">Aduan Keluhan</th>
                            <th class="px-3.5 py-3">Kategori</th>
                            <th class="px-3.5 py-3">Prioritas</th>
                            @if($tab === 'assign_fop')
                                <th class="px-3.5 py-3">Status</th>
                                <th class="px-3.5 py-3" title="Kapan tiket diserahkan ke FOP">Diserahkan</th>
                                <th class="px-3.5 py-3" title="Yang mengirim tiket ke FOP">Dikirim Oleh</th>
                            @else
                                <th class="px-3.5 py-3 text-right" title="Lama tiket menunggu di meja NOC">Umur Antrean</th>
                            @endif
                            <th class="px-3.5 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                        @foreach($tickets as $ticket)
                            @php
                                $actions = $ticket->actionFlagsFor(auth()->user());
                                $ageMinutes = (int) $ticket->created_at->diffInMinutes(now());
                                $ageLabel = sprintf('%dj %02dm', intdiv($ageMinutes, 60), $ageMinutes % 60);
                                $ageTextColor = match (true) {
                                    $ageMinutes >= 1440 => 'text-rose-600 dark:text-rose-400 font-bold',
                                    $ageMinutes >= 480 => 'text-amber-600 dark:text-amber-400 font-bold',
                                    default => 'text-slate-400 dark:text-slate-500 font-medium',
                                };
                                $isBatch = $ticket->isBatch();
                                $hasActions = $actions['can_close'] || $actions['can_escalate_fop'] || $actions['can_return_to_helpdesk'] || $actions['can_cancel'];
                            @endphp

                            <tr data-ticket-row="{{ $ticket->id }}"
                                data-ticket-code="{{ $ticket->ticket_number }}"
                                @if($actions['can_close']) data-url-close="{{ route('tickets.close', $ticket) }}" @endif
                                @if($actions['can_escalate_fop']) data-url-escalate="{{ route('tickets.escalate', $ticket) }}" @endif
                                @if($actions['can_return_to_helpdesk']) data-url-return="{{ route('tickets.return-to-helpdesk', $ticket) }}" @endif
                                @if($actions['can_cancel']) data-url-cancel="{{ route('tickets.cancel', $ticket) }}" @endif
                                @click="openDetail({{ $ticket->id }})"
                                class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors cursor-pointer group {{ $isBatch ? 'bg-violet-50/20 dark:bg-violet-950/10' : '' }}">
                                
                                {{-- Waktu Masuk --}}
                                <td class="px-3.5 py-3 font-mono text-[11px] text-slate-400 dark:text-slate-500">
                                    {{ IndonesianDate::dateTime($ticket->created_at) }}
                                </td>

                                {{-- Tiket & Tipe --}}
                                <td class="px-3.5 py-3">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="font-mono font-bold text-sky-600 dark:text-sky-400 group-hover:underline text-xs">
                                            {{ $ticket->ticket_number }}
                                        </span>
                                        @if($isBatch)
                                            <span class="px-1.5 py-0.5 rounded-md text-[9px] font-extrabold bg-violet-100 dark:bg-violet-950 text-violet-700 dark:text-violet-300 border border-violet-200 dark:border-violet-800 shrink-0 inline-flex items-center gap-1 shadow-2xs">
                                                <span class="w-1.5 h-1.5 rounded-full bg-violet-500 animate-pulse"></span>
                                                BATCH ({{ $ticket->batchMembers->count() }})
                                            </span>
                                        @endif
                                    </div>
                                    <span class="inline-block mt-0.5 px-1.5 py-0.2 rounded text-[9px] font-mono font-semibold bg-slate-50 dark:bg-slate-800 text-slate-400 dark:text-slate-500 border border-slate-200/50 dark:border-slate-700/60">
                                        {{ $ticket->type->value }}
                                    </span>
                                </td>

                                {{-- Pelanggan & CID --}}
                                <td class="px-3.5 py-3">
                                    @if($isBatch)
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-bold text-violet-700 dark:text-violet-300 text-xs">⚡ {{ $ticket->customer_name ?: 'Insiden Massal' }}</span>
                                        </div>
                                        <span class="block font-mono text-[10px] text-violet-600 dark:text-violet-400 font-semibold">
                                            {{ $ticket->batchMembers->count() }} Pelanggan Terdampak
                                        </span>
                                    @else
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-semibold text-slate-800 dark:text-slate-200 group-hover:text-sky-600 dark:group-hover:text-sky-400 transition-colors text-xs">
                                                {{ $ticket->customer->full_name ?? $ticket->customer_name ?? '—' }}
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-1.5 mt-0.5">
                                            <span class="font-mono text-[10px] font-semibold text-slate-400 dark:text-slate-500 bg-slate-50 dark:bg-slate-800/80 px-1.5 py-0.2 rounded border border-slate-200/50 dark:border-slate-700/60">
                                                {{ $ticket->customer?->display_id ?? '—' }}
                                            </span>
                                            @if($ticket->customer?->display_id)
                                                <button type="button" @click.stop="copyCid('{{ $ticket->customer->display_id }}', $event)"
                                                        class="text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 transition-colors p-0.5 cursor-pointer"
                                                        title="Salin CID">
                                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                                </button>
                                            @endif
                                        </div>
                                    @endif
                                </td>

                                {{-- Kontak & Lokasi --}}
                                <td class="px-3.5 py-3">
                                    <div class="flex items-center gap-1.5">
                                        @if($ticket->customer_phone)
                                            <span class="inline-flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-mono font-semibold text-xs"
                                                  title="Nomor WhatsApp">
                                                <svg class="w-3.5 h-3.5 fill-current shrink-0" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                                                <span>{{ $ticket->customer_phone }}</span>
                                            </span>
                                        @else
                                            <span class="text-slate-400 dark:text-slate-500">—</span>
                                        @endif
                                    </div>
                                    <span class="block text-[11px] text-slate-400 dark:text-slate-500 mt-0.5 truncate max-w-[140px]" title="{{ $ticket->customer_village }}">
                                        📍 {{ $ticket->customer_village ?? '—' }}
                                    </span>
                                </td>

                                {{-- POP --}}
                                <td class="px-3.5 py-3">
                                    <span class="inline-flex items-center gap-1 font-medium text-slate-600 dark:text-slate-300 text-xs">
                                        <svg class="w-3 h-3 text-sky-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                        {{ $ticket->pop?->name ?? '—' }}
                                    </span>
                                </td>

                                {{-- Aduan Keluhan --}}
                                <td class="px-3.5 py-3 max-w-xs truncate text-slate-500 dark:text-slate-400 text-xs font-normal" title="{{ $ticket->detail_keluhan }}">
                                    {{ $ticket->detail_keluhan }}
                                </td>

                                {{-- Kategori --}}
                                <td class="px-3.5 py-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-slate-50 dark:bg-slate-800/80 border border-slate-200/50 dark:border-slate-700/60 text-slate-500 dark:text-slate-400">
                                        {{ $ticket->issueCategory?->name ?? '—' }}
                                    </span>
                                </td>

                                {{-- Prioritas --}}
                                <td class="px-3.5 py-3">
                                    @if($ticket->priority)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full border text-[10px] font-bold uppercase tracking-wider
                                            @switch($ticket->priority->value)
                                                @case('Urgent') bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400 border-rose-200 dark:border-rose-900/60 @break
                                                @case('High') bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400 border-amber-200 dark:border-amber-900/60 @break
                                                @default bg-slate-50 dark:bg-slate-800/80 border-slate-200/50 dark:border-slate-700/60 text-slate-500 dark:text-slate-400
                                            @endswitch">
                                            @if(in_array($ticket->priority->value, ['Urgent', 'High']))
                                                <span class="w-1.5 h-1.5 rounded-full bg-current animate-ping"></span>
                                            @endif
                                            {{ $ticket->priority->value }}
                                        </span>
                                    @else
                                        <span class="text-slate-400 dark:text-slate-500">—</span>
                                    @endif
                                </td>

                                {{-- Kolom Tab Khusus --}}
                                @if($tab === 'assign_fop')
                                    <td class="px-3.5 py-3">
                                        <span class="inline-block px-2.5 py-0.5 rounded-md border text-[10px] font-bold {{ TicketHistoryController::statusBadgeFor($ticket) }}">
                                            {{ TicketHistoryController::statusLabelFor($ticket) }}
                                        </span>
                                    </td>
                                    <td class="px-3.5 py-3 font-mono text-slate-400 dark:text-slate-500 text-[11px]">
                                        {{ $ticket->resolved_at ? IndonesianDate::dateTime($ticket->resolved_at) : '—' }}
                                    </td>
                                    <td class="px-3.5 py-3 text-slate-600 dark:text-slate-300 font-medium text-xs">
                                        {{ $ticket->escalatedToFopBy()?->name ?? '—' }}
                                    </td>
                                @else
                                    <td class="px-3.5 py-3 text-right">
                                        <span class="inline-flex items-center gap-1 font-mono text-[11px] {{ $ageTextColor }}">
                                            <svg class="w-3 h-3 text-current opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            {{ $ageLabel }}
                                        </span>
                                    </td>
                                @endif

                                {{-- Aksi Langsung per Baris --}}
                                <td class="px-3.5 py-3 text-center shrink-0" @click.stop>
                                    @if($hasActions)
                                        <div class="relative inline-block text-left">
                                            <button type="button"
                                                    @click.stop="toggleActionMenu('table-{{ $ticket->id }}', $event)"
                                                    class="px-2.5 py-1 rounded-lg border border-slate-200/60 dark:border-slate-700/60 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/60 text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white text-xs font-semibold inline-flex items-center gap-1 transition-all shadow-2xs cursor-pointer">
                                                <span>Aksi</span>
                                                <svg class="w-3.5 h-3.5 text-slate-400 dark:text-slate-500 transition-transform duration-200" :class="{ 'rotate-180': activeActionMenu === 'table-{{ $ticket->id }}' }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                </svg>
                                            </button>

                                            <div x-show="activeActionMenu === 'table-{{ $ticket->id }}'"
                                                 x-cloak
                                                 @click.outside="if (activeActionMenu === 'table-{{ $ticket->id }}') closeActionMenu()"
                                                 x-transition:enter="transition ease-out duration-100"
                                                 x-transition:enter-start="transform opacity-0 scale-95"
                                                 x-transition:enter-end="transform opacity-100 scale-100"
                                                 x-transition:leave="transition ease-in duration-75"
                                                 x-transition:leave-start="transform opacity-100 scale-100"
                                                 x-transition:leave-end="transform opacity-0 scale-95"
                                                 class="absolute right-0 z-50 mt-1.5 w-48 rounded-xl border border-slate-200/70 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-xl py-1.5 divide-y divide-slate-100 dark:divide-slate-700/80 text-left">
                                                <div class="py-1">
                                                    @if($actions['can_close'])
                                                        <button type="button"
                                                                @click.stop="closeActionMenu(); $dispatch('ticket-drawer-action', { id: {{ $ticket->id }}, action: 'close' })"
                                                                class="w-full text-left px-3.5 py-2 text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 flex items-center gap-2.5 transition-colors cursor-pointer">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                            <span>Selesai</span>
                                                        </button>
                                                    @endif
                                                    @if($actions['can_escalate_fop'])
                                                        <button type="button"
                                                                @click.stop="closeActionMenu(); $dispatch('ticket-drawer-action', { id: {{ $ticket->id }}, action: 'fop' })"
                                                                class="w-full text-left px-3.5 py-2 text-xs font-semibold text-sky-600 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-sky-950/40 flex items-center gap-2.5 transition-colors cursor-pointer">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                                            <span>Assign FOP</span>
                                                        </button>
                                                    @endif
                                                </div>
                                                @if($actions['can_return_to_helpdesk'] || $actions['can_cancel'])
                                                    <div class="py-1">
                                                        @if($actions['can_return_to_helpdesk'])
                                                            <button type="button"
                                                                    @click.stop="closeActionMenu(); $dispatch('ticket-drawer-action', { id: {{ $ticket->id }}, action: 'return' })"
                                                                    class="w-full text-left px-3.5 py-2 text-xs font-semibold text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/40 flex items-center gap-2.5 transition-colors cursor-pointer">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
                                                                <span>Kembalikan</span>
                                                            </button>
                                                        @endif
                                                        @if($actions['can_cancel'])
                                                            <button type="button"
                                                                    @click.stop="closeActionMenu(); $dispatch('ticket-drawer-action', { id: {{ $ticket->id }}, action: 'cancel' })"
                                                                    class="w-full text-left px-3.5 py-2 text-xs font-semibold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 flex items-center gap-2.5 transition-colors cursor-pointer">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                                                <span>Batalkan</span>
                                                            </button>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-slate-400 dark:text-slate-500 text-xs">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- 4B. MOBILE & TABLET TOUCH-FRIENDLY CARD STREAM --}}
        <div x-show="viewMode === 'cards'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($tickets as $ticket)
                @php
                    $actions = $ticket->actionFlagsFor(auth()->user());
                    $ageMinutes = (int) $ticket->created_at->diffInMinutes(now());
                    $ageLabel = sprintf('%dj %02dm', intdiv($ageMinutes, 60), $ageMinutes % 60);
                    $isBatch = $ticket->isBatch();
                    $hasActions = $actions['can_close'] || $actions['can_escalate_fop'] || $actions['can_return_to_helpdesk'] || $actions['can_cancel'];
                @endphp

                <div data-ticket-row="{{ $ticket->id }}"
                     data-ticket-code="{{ $ticket->ticket_number }}"
                     @if($actions['can_close']) data-url-close="{{ route('tickets.close', $ticket) }}" @endif
                     @if($actions['can_escalate_fop']) data-url-escalate="{{ route('tickets.escalate', $ticket) }}" @endif
                     @if($actions['can_return_to_helpdesk']) data-url-return="{{ route('tickets.return-to-helpdesk', $ticket) }}" @endif
                     @if($actions['can_cancel']) data-url-cancel="{{ route('tickets.cancel', $ticket) }}" @endif
                     @click="openDetail({{ $ticket->id }})"
                     class="rounded-2xl border border-slate-200/60 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 sm:p-5 shadow-xs hover:shadow-md transition-all cursor-pointer flex flex-col justify-between space-y-4 relative overflow-hidden group {{ $isBatch ? 'border-violet-300/60 dark:border-violet-800 bg-violet-50/20 dark:bg-violet-950/10' : '' }}">
                    
                    {{-- Top Card Bar: Number, Priority, Age --}}
                    <div class="space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-mono font-black text-sm text-sky-600 dark:text-sky-400 group-hover:underline">
                                    {{ $ticket->ticket_number }}
                                </span>
                                @if($isBatch)
                                    <span class="px-2 py-0.5 rounded-full text-[9px] font-extrabold bg-violet-100 dark:bg-violet-950 text-violet-700 dark:text-violet-300 border border-violet-300/60 dark:border-violet-800 inline-flex items-center gap-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-violet-500 animate-pulse"></span>
                                        BATCH ({{ $ticket->batchMembers->count() }})
                                    </span>
                                @endif
                                <span class="px-2 py-0.5 rounded-md text-[9px] font-mono font-semibold bg-slate-100/70 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 text-slate-500 dark:text-slate-400">
                                    {{ $ticket->type->value }}
                                </span>
                            </div>

                            {{-- Priority & Age Badges --}}
                            <div class="flex items-center gap-1.5 shrink-0">
                                @if($ticket->priority)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider border
                                        @switch($ticket->priority->value)
                                            @case('Urgent') bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400 border-rose-300 dark:border-rose-800 @break
                                            @case('High') bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400 border-amber-300 dark:border-amber-800 @break
                                            @default bg-slate-100/70 dark:bg-slate-800 border-slate-100 dark:border-slate-700 text-slate-600 dark:text-slate-300
                                        @endswitch">
                                        {{ $ticket->priority->value }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- Customer Info Box --}}
                        <div class="p-3 rounded-xl bg-slate-50/50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800 space-y-1.5">
                            <div class="flex items-center justify-between gap-2">
                                @if($isBatch)
                                    <span class="font-bold text-violet-700 dark:text-violet-300 text-xs sm:text-sm">⚡ {{ $ticket->customer_name ?: 'Insiden Massal' }}</span>
                                    <span class="text-[10px] font-bold font-mono text-violet-600 dark:text-violet-400">{{ $ticket->batchMembers->count() }} Pelanggan</span>
                                @else
                                    <span class="font-bold text-slate-900 dark:text-slate-100 text-xs sm:text-sm truncate">{{ $ticket->customer->full_name ?? $ticket->customer_name ?? '—' }}</span>
                                    <div class="flex items-center gap-1">
                                        <span class="font-mono text-[10px] font-bold text-slate-500 dark:text-slate-400 bg-white dark:bg-slate-800 px-1.5 py-0.5 rounded border border-slate-100 dark:border-slate-700">
                                            {{ $ticket->customer?->display_id ?? '—' }}
                                        </span>
                                        @if($ticket->customer?->display_id)
                                            <button type="button" @click.stop="copyCid('{{ $ticket->customer->display_id }}', $event)"
                                                    class="text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 transition-colors p-1 cursor-pointer" title="Salin CID">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                            </button>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            {{-- Inner divider (subtle border in light & dark theme) --}}
                            <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400 pt-1.5 border-t border-slate-100 dark:border-slate-700/40 flex-wrap gap-2">
                                <span class="flex items-center gap-1">
                                    <svg class="w-3 h-3 text-sky-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                                    {{ $ticket->customer_village ?? '—' }} • {{ $ticket->pop?->name ?? '—' }}
                                </span>

                                @if($ticket->customer_phone)
                                    <span class="inline-flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-mono font-bold text-[11px]"
                                          title="Nomor WhatsApp">
                                        <svg class="w-3.5 h-3.5 fill-current shrink-0" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                                        {{ $ticket->customer_phone }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- Issue Category & Detail Aduan --}}
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Aduan:</span>
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-100/70 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 text-slate-600 dark:text-slate-300">
                                    {{ $ticket->issueCategory?->name ?? 'Kategori Umum' }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-600 dark:text-slate-300 line-clamp-2 leading-relaxed bg-slate-50/40 dark:bg-slate-800/30 p-2.5 rounded-xl border border-slate-100 dark:border-slate-800/60">
                                {{ $ticket->detail_keluhan }}
                            </p>
                        </div>
                    </div>

                    {{-- Bottom Action & Footer Section --}}
                    <div class="pt-3 border-t border-slate-100/50 dark:border-slate-800/70 flex items-center justify-between gap-2" @click.stop>
                        <div class="text-[10px] text-slate-400 dark:text-slate-500 font-mono flex items-center gap-1">
                            <svg class="w-3 h-3 text-slate-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @if($tab === 'assign_fop')
                                <span>{{ $ticket->resolved_at ? IndonesianDate::dateTime($ticket->resolved_at) : 'Diserahkan' }}</span>
                            @else
                                <span>Umur: <strong class="text-slate-900 dark:text-slate-100 font-bold">{{ $ageLabel }}</strong></span>
                            @endif
                        </div>

                        {{-- Action Button Group --}}
                        <div class="flex items-center gap-1.5">
                            @if($hasActions)
                                <div class="relative inline-block text-left">
                                    <button type="button"
                                            @click.stop="toggleActionMenu('card-{{ $ticket->id }}', $event)"
                                            class="px-3.5 py-2 rounded-xl bg-sky-600 text-white hover:bg-sky-700 text-xs font-bold inline-flex items-center gap-1.5 transition-all shadow-xs cursor-pointer">
                                        <span>Tindakan</span>
                                        <svg class="w-3.5 h-3.5 transition-transform duration-200" :class="{ 'rotate-180': activeActionMenu === 'card-{{ $ticket->id }}' }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </button>

                                    <div x-show="activeActionMenu === 'card-{{ $ticket->id }}'"
                                         x-cloak
                                         @click.outside="if (activeActionMenu === 'card-{{ $ticket->id }}') closeActionMenu()"
                                         x-transition:enter="transition ease-out duration-100"
                                         x-transition:enter-start="transform opacity-0 scale-95"
                                         x-transition:enter-end="transform opacity-100 scale-100"
                                         x-transition:leave="transition ease-in duration-75"
                                         x-transition:leave-start="transform opacity-100 scale-100"
                                         x-transition:leave-end="transform opacity-0 scale-95"
                                         class="absolute right-0 bottom-full mb-1.5 z-50 w-48 rounded-xl border border-slate-200/70 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-2xl py-1.5 divide-y divide-slate-100 dark:divide-slate-700/80 text-left">
                                        <div class="py-1">
                                            @if($actions['can_close'])
                                                <button type="button"
                                                        @click.stop="closeActionMenu(); $dispatch('ticket-drawer-action', { id: {{ $ticket->id }}, action: 'close' })"
                                                        class="w-full text-left px-3.5 py-2 text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 flex items-center gap-2.5 transition-colors cursor-pointer">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                    <span>Selesai</span>
                                                </button>
                                            @endif
                                            @if($actions['can_escalate_fop'])
                                                <button type="button"
                                                        @click.stop="closeActionMenu(); $dispatch('ticket-drawer-action', { id: {{ $ticket->id }}, action: 'fop' })"
                                                        class="w-full text-left px-3.5 py-2 text-xs font-semibold text-sky-600 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-sky-950/40 flex items-center gap-2.5 transition-colors cursor-pointer">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                                    <span>Assign FOP</span>
                                                </button>
                                            @endif
                                        </div>
                                        @if($actions['can_return_to_helpdesk'] || $actions['can_cancel'])
                                            <div class="py-1">
                                                @if($actions['can_return_to_helpdesk'])
                                                    <button type="button"
                                                            @click.stop="closeActionMenu(); $dispatch('ticket-drawer-action', { id: {{ $ticket->id }}, action: 'return' })"
                                                            class="w-full text-left px-3.5 py-2 text-xs font-semibold text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/40 flex items-center gap-2.5 transition-colors cursor-pointer">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
                                                                <span>Kembalikan</span>
                                                    </button>
                                                @endif
                                                @if($actions['can_cancel'])
                                                    <button type="button"
                                                            @click.stop="closeActionMenu(); $dispatch('ticket-drawer-action', { id: {{ $ticket->id }}, action: 'cancel' })"
                                                            class="w-full text-left px-3.5 py-2 text-xs font-semibold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 flex items-center gap-2.5 transition-colors cursor-pointer">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                                                <span>Batalkan</span>
                                                    </button>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            <button type="button" @click="openDetail({{ $ticket->id }})"
                                    class="p-2 rounded-xl border border-slate-200/70 dark:border-slate-700/80 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/60 text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-colors cursor-pointer"
                                    title="Lihat Detail Lengkap">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

    @else
        {{-- =========================================================================
             5. RICH EMPTY STATE
             ========================================================================= --}}
        <div class="rounded-2xl border border-slate-200/60 dark:border-slate-800 bg-white dark:bg-slate-900 p-10 sm:p-14 text-center space-y-4 shadow-xs">
            <div class="w-16 h-16 rounded-2xl bg-sky-50 dark:bg-sky-950/50 border border-sky-200/60 dark:border-sky-900/60 text-sky-600 dark:text-sky-400 flex items-center justify-center mx-auto shadow-xs">
                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
            </div>
            
            <div class="max-w-md mx-auto space-y-1.5">
                <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">
                    @if($tab === 'assign_fop')
                        Tidak Ada Tiket di Meja FOP
                    @else
                        Antrean Tiket NOC Bersih
                    @endif
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                    @if($tab === 'assign_fop')
                        Belum ada tiket yang diteruskan NOC ke FOP{{ $totalActiveFilters > 0 ? ' yang sesuai dengan kriteria filter saat ini' : '' }}.
                    @else
                        Semua tiket teknis telah tertangani dengan baik{{ $totalActiveFilters > 0 ? ' atau tidak ada hasil sesuai kriteria filter' : '' }}.
                    @endif
                </p>
            </div>

            @if($totalActiveFilters > 0)
                <div class="pt-2">
                    <a href="{{ route('noc.worksheet', ['tab' => $tab]) }}"
                       class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-100/80 hover:bg-slate-200/80 dark:bg-slate-800 dark:hover:bg-slate-700/80 border border-slate-200/60 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 hover:text-slate-900 dark:hover:text-white transition-colors shadow-2xs">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.038 8.038 0 01-15.357-2m15.357 2H15"/></svg>
                        <span>Reset Semua Filter</span>
                    </a>
                </div>
            @endif
        </div>
    @endif

    {{-- =========================================================================
         6. PAGINATION
         ========================================================================= --}}
    <div class="pt-2">
        {{ $tickets->links() }}
    </div>

    {{-- =========================================================================
         7. DETAIL DRAWER & ACTION DIALOG
         ========================================================================= --}}
    @include('tickets.partials.detail-drawer')
    @include('tickets.partials.action-dialog')

</div>
@endsection

@push('scripts')
<script>
    function nocWorksheet() {
        return {
            viewMode: window.innerWidth < 1024 ? 'cards' : 'table',
            activeActionMenu: null,

            init() {
                const saved = localStorage.getItem('whusnet_noc_view_mode');
                if (saved) {
                    this.viewMode = saved;
                }
            },

            setViewMode(mode) {
                this.viewMode = mode;
                localStorage.setItem('whusnet_noc_view_mode', mode);
            },

            toggleActionMenu(id, event) {
                if (event) event.stopPropagation();
                this.activeActionMenu = (this.activeActionMenu === id ? null : id);
            },

            closeActionMenu() {
                this.activeActionMenu = null;
            },

            openDetail(id) {
                this.closeActionMenu();
                window.dispatchEvent(new CustomEvent('open-ticket-drawer', { detail: { id } }));
            },

            copyCid(cid, event) {
                if (event) event.stopPropagation();
                if (!cid) return;
                navigator.clipboard.writeText(cid).then(() => {
                    if (window.Toast) {
                        window.Toast.success('Tersalin', `CID ${cid} berhasil disalin ke clipboard.`);
                    }
                }).catch(() => {});
            },
        };
    }

    /**
     * Listener Aksi Drawer / Baris:
     * Menghubungkan dispatch event aksi ke Modal Konfirmasi & API request.
     */
    window.addEventListener('ticket-drawer-action', (event) => {
        const { id, action } = event.detail;
        const row = document.querySelector(`[data-ticket-row="${id}"]`);

        if (!row) {
            return;
        }

        const code = row.dataset.ticketCode;

        const map = {
            close: {
                url: row.dataset.urlClose,
                payload: {},
                title: 'Selesaikan Tiket',
                label: 'Catatan penyelesaian teknis NOC (opsional)',
                message: `Tandai tiket ${code} telah selesai ditangani?`,
                required: false,
            },
            fop: {
                url: row.dataset.urlEscalate,
                payload: { target: 'fop' },
                title: 'Kirim Tiket ke FOP',
                label: 'Instruksi / Catatan untuk teknisi lapangan FOP (opsional)',
                message: `Eskalasi tiket ${code} ke tim FOP lapangan?`,
                required: false,
            },
            return: {
                url: row.dataset.urlReturn,
                payload: {},
                title: 'Kembalikan ke Helpdesk',
                label: 'Alasan pengembalian ke Helpdesk (opsional)',
                message: `Kembalikan tiket ${code} ke Helpdesk?`,
                required: false,
            },
            cancel: {
                url: row.dataset.urlCancel,
                payload: {},
                title: 'Batalkan Tiket',
                label: 'Alasan pembatalan tiket (wajib diisi)',
                message: `Batalkan tiket ${code}?`,
                required: true,
            },
        }[action];

        if (!map || !map.url) {
            return;
        }

        window.confirmTicketAction({
            title: map.title,
            message: map.message,
            label: map.label,
            required: map.required,
            confirmText: map.required ? 'Ya, Batalkan' : 'Ya, Lanjutkan',
            confirmType: map.required ? 'danger' : 'primary',
            icon: map.required ? 'error' : 'warning',
            onConfirm: (reason) => performTicketAction(id, map.url, { ...map.payload, reason }),
        });
    });

    /**
     * Eksekusi AJAX Request Mutasi Tiket
     */
    async function performTicketAction(ticketId, url, payload) {
        const rows = document.querySelectorAll(`[data-ticket-row="${ticketId}"]`);
        const buttons = document.querySelectorAll('[data-drawer-action]');
        buttons.forEach(b => { b.disabled = true; });

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(payload),
            });
            const body = await res.json();

            if (!res.ok) {
                window.Toast?.error('Gagal', body.message || 'Aksi gagal, silakan coba lagi.');
                buttons.forEach(b => { b.disabled = false; });
                return;
            }

            window.Toast?.success('Berhasil', body.message);
            window.dispatchEvent(new CustomEvent('close-ticket-drawer'));

            if (rows.length > 0) {
                rows.forEach(r => {
                    r.style.transition = 'all 0.3s ease-out';
                    r.style.opacity = '0';
                    r.style.transform = 'scale(0.95)';
                    setTimeout(() => { r.remove(); }, 300);
                });
            }
        } catch (e) {
            window.Toast?.error('Gagal', 'Terjadi gangguan jaringan saat memproses aksi.');
            buttons.forEach(b => { b.disabled = false; });
        }
    }
</script>
@endpush
