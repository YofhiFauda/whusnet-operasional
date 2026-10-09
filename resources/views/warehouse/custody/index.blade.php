@extends('layouts.app')

@section('title', 'Barang di Tangan Teknisi - Whusnet Operasional')
@section('page_title', 'Barang di Tangan Teknisi')

@section('content')

<x-warehouse.header
    active="custody"
    title="Barang di Tangan Teknisi"
    subtitle="Pusat pemantauan perangkat aktif, material pasif, roll kabel, dan modem retur di tangan teknisi lapangan."
/>

@php
    $hasActiveFilter = (bool) ($technicianFilter || $popFilter || $search);
    $activeFilterCount = ($technicianFilter ? 1 : 0) + ($popFilter ? 1 : 0) + ($search ? 1 : 0);
    $canReassign = auth()->user()->hasPermission('warehouse_reassign.create');
    $canAdjust = auth()->user()->hasPermission('warehouse_adjustment.create');
    $canTrace = auth()->user()->hasPermission('warehouse_traceability.view');

    // Nama teknisi terpilih untuk ringkasan chip
    $selectedTech = $technicianFilter ? $technicians->firstWhere('id', $technicianFilter) : null;
    $selectedPop = $popFilter ? $pops->firstWhere('id', $popFilter) : null;

    // Sort kolom per tab (analisa-ui-ux §A5 — sisa Fase 2, sebelumnya ditunda).
    // Param TERPISAH per tab (serial_sort, material_sort, roll_sort, return_sort)
    // biar 4 tab gak rebutan satu param sort — klik urut di tab A gak ganggu
    // urutan tab B. $tabSortLink dipakai di 4 header tabel, cuma beda prefix.
    $tabSortLink = fn (string $prefix, string $key, ?string $curSort, string $curDir) => request()->fullUrlWithQuery([
        $prefix.'_sort' => $key,
        $prefix.'_dir' => ($curSort === $key && $curDir === 'asc') ? 'desc' : 'asc',
        $prefix.'_page' => null,
    ]);
    $tabSortIcon = fn (?string $curSort, string $curDir, string $key) => $curSort !== $key ? '↕' : ($curDir === 'desc' ? '↓' : '↑');
    $tabSortAria = fn (?string $curSort, string $curDir, string $key) => $curSort !== $key ? 'none' : ($curDir === 'desc' ? 'descending' : 'ascending');
    $tabSortHead = fn (?string $curSort, string $key) => $curSort === $key ? 'text-sky-600 dark:text-sky-400' : 'text-slate-300 dark:text-slate-600';
@endphp

{{-- Toggle mode tampilan (analisa-ui-ux §U6): per barang (default) atau saldo per teknisi --}}
@php
    $custodyViewToggle = fn (string $mode) => request()->fullUrlWithQuery(['view' => $mode === 'barang' ? null : $mode]);
@endphp
<div class="inline-flex items-center p-1 bg-slate-100 dark:bg-slate-800/80 rounded-xl text-xs font-semibold mb-4" role="tablist" aria-label="Mode tampilan custody">
    <a href="{{ $custodyViewToggle('barang') }}" role="tab" aria-selected="{{ $viewMode === 'barang' ? 'true' : 'false' }}"
       class="px-3.5 py-1.5 rounded-lg transition-all {{ $viewMode === 'barang' ? 'bg-white dark:bg-slate-900 text-sky-600 dark:text-sky-400 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100' }}">Per Barang</a>
    <a href="{{ $custodyViewToggle('teknisi') }}" role="tab" aria-selected="{{ $viewMode === 'teknisi' ? 'true' : 'false' }}"
       class="px-3.5 py-1.5 rounded-lg transition-all {{ $viewMode === 'teknisi' ? 'bg-white dark:bg-slate-900 text-sky-600 dark:text-sky-400 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100' }}">Saldo per Teknisi</a>
</div>

<!-- ================= LAYER 1: FLAT SUMMARY METRIC STRIP (Card Budget = 1 compliant) ================= -->
<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-xs overflow-hidden mb-5">
    <div class="grid grid-cols-2 lg:grid-cols-4 divide-x divide-y sm:divide-y-0 divide-slate-200 dark:divide-slate-700 bg-slate-50/50 dark:bg-slate-900/40">
        
        <!-- KPI 1: Perangkat Aktif (SN) -->
        <div class="p-4 sm:p-5 flex flex-col justify-between hover:bg-white dark:hover:bg-slate-800/80 transition-colors">
            <div class="flex items-center justify-between gap-2">
                <span class="text-[10px] sm:text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                    Perangkat Aktif (SN)
                </span>
                <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-cyan-50 dark:bg-cyan-950/60 text-cyan-600 dark:text-cyan-400 flex items-center justify-center shrink-0 border border-cyan-200 dark:border-cyan-800">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                    </svg>
                </div>
            </div>
            <div class="mt-3">
                <div class="flex items-baseline gap-1.5">
                    <span class="text-2xl sm:text-3xl font-extrabold font-mono text-cyan-600 dark:text-cyan-400 tabular-nums">
                        {{ $kpi['serial_count'] }}
                    </span>
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Unit</span>
                </div>
                <span class="text-[11px] text-slate-400 dark:text-slate-400 block truncate mt-0.5">Modem ONT, Router & AP</span>
            </div>
        </div>

        <!-- KPI 2: Kabel di Lapangan -->
        <div class="p-4 sm:p-5 flex flex-col justify-between hover:bg-white dark:hover:bg-slate-800/80 transition-colors">
            <div class="flex items-center justify-between gap-2">
                <span class="text-[10px] sm:text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                    Kabel Lapangan
                </span>
                <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0 border border-amber-200 dark:border-amber-800">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a1 1 0 001-1v-4a1 1 0 00-1-1H9a1 1 0 00-1 1v4a1 1 0 001 1zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                </div>
            </div>
            <div class="mt-3">
                <div class="flex items-baseline gap-1.5">
                    <span class="text-2xl sm:text-3xl font-extrabold font-mono text-amber-600 dark:text-amber-400 tabular-nums">
                        {{ rtrim(rtrim(number_format((float) $kpi['meter_total'], 2, ',', '.'), '0'), ',') }}
                    </span>
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Meter</span>
                </div>
                <span class="text-[11px] text-slate-400 dark:text-slate-400 block truncate mt-0.5">Dropcore & {{ $kpi['roll_count'] }} Roll Sisa</span>
            </div>
        </div>

        <!-- KPI 3: Material Pasif -->
        <div class="p-4 sm:p-5 flex flex-col justify-between hover:bg-white dark:hover:bg-slate-800/80 transition-colors">
            <div class="flex items-center justify-between gap-2">
                <span class="text-[10px] sm:text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                    Material Pasif
                </span>
                <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0 border border-indigo-200 dark:border-indigo-800">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                </div>
            </div>
            <div class="mt-3">
                <div class="flex items-baseline gap-1.5">
                    <span class="text-2xl sm:text-3xl font-extrabold font-mono text-indigo-600 dark:text-indigo-400 tabular-nums">
                        {{ $kpi['material_batch_count'] }}
                    </span>
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Batch</span>
                </div>
                <span class="text-[11px] text-slate-400 dark:text-slate-400 block truncate mt-0.5">Patchcord, Closure & Aksesori</span>
            </div>
        </div>

        <!-- KPI 4: Teknisi Bertugas -->
        <div class="p-4 sm:p-5 flex flex-col justify-between hover:bg-white dark:hover:bg-slate-800/80 transition-colors">
            <div class="flex items-center justify-between gap-2">
                <span class="text-[10px] sm:text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                    Teknisi Bertugas
                </span>
                <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0 border border-emerald-200 dark:border-emerald-800">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </div>
            </div>
            <div class="mt-3">
                <div class="flex items-baseline gap-1.5">
                    <span class="text-2xl sm:text-3xl font-extrabold font-mono text-slate-800 dark:text-slate-100 tabular-nums">
                        {{ $kpi['technician_count'] }}
                    </span>
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Orang</span>
                </div>
                <span class="text-[11px] text-slate-400 dark:text-slate-400 block truncate mt-0.5">Membawa Barang Lapangan</span>
            </div>
        </div>

    </div>
</div>

@php
    // Tab aktif awal diambil dari ?tab= (whitelist) supaya setelah paginasi
    // reload, tab yang sama kebuka lagi — link paginasi membawa tab-nya sendiri.
    $activeTab = in_array(request('tab'), ['serials', 'materials', 'rolls', 'returns'], true) ? request('tab') : 'serials';
@endphp
<div x-data="{ activeTab: '{{ $activeTab }}', filterOpen: {{ $hasActiveFilter ? 'true' : 'false' }} }" class="space-y-4">
    
    <!-- ================= LAYER 2: EXPANDABLE / COLLAPSIBLE FILTER BAR ================= -->
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-xs overflow-hidden transition-all">
        
        <!-- Filter Bar Header Toggle -->
        <div class="px-4 sm:px-5 py-3.5 flex flex-wrap items-center justify-between gap-3 bg-slate-50/70 dark:bg-slate-800/80 border-b border-slate-200/80 dark:border-slate-700">
            <div class="flex items-center gap-2.5 flex-wrap">
                <div class="flex items-center gap-2 cursor-pointer select-none" @click="filterOpen = !filterOpen">
                    <div class="w-6 h-6 rounded-lg bg-sky-100 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0 border border-sky-200 dark:border-sky-800">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z"/>
                        </svg>
                    </div>
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-200">
                        Filter & Pencarian Custody
                    </span>
                </div>

                @if($hasActiveFilter)
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-sky-100 dark:bg-sky-900/50 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800">
                    <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
                    <span>{{ $activeFilterCount }} Filter Aktif</span>
                </span>
                @endif
            </div>

            <!-- Filter Controls & Toggle Button -->
            <div class="flex items-center gap-2">
                @if($hasActiveFilter)
                <a href="{{ route('warehouse.custody.index') }}"
                   class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg transition-colors border border-rose-200 dark:border-rose-900/60"
                   title="Reset Semua Filter">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    <span>Reset</span>
                </a>
                @endif

                <button type="button" @click="filterOpen = !filterOpen"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-white dark:bg-slate-700/80 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-600 transition-all cursor-pointer shadow-2xs">
                    <span x-text="filterOpen ? 'Tutup Filter' : 'Buka Filter'"></span>
                    <svg class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200" :class="filterOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Collapsible Filter Form Body -->
        <div x-show="filterOpen" x-collapse class="p-4 sm:p-5 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
            <form action="{{ route('warehouse.custody.index') }}" method="GET" class="space-y-4">
                {{-- Mode tampilan ikut terbawa saat filter diterapkan --}}
                @if($viewMode === 'teknisi')
                <input type="hidden" name="view" value="teknisi">
                @endif
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3.5">
                    
                    <!-- Filter Teknisi Lapangan -->
                    <div class="lg:col-span-4">
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-400 mb-1.5 flex items-center gap-1.5">
                            <svg class="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            <span>Filter Teknisi Lapangan</span>
                        </label>
                        <select name="technician_id" class="w-full px-3 py-2 text-xs font-semibold border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                            <option value="">— Semua Teknisi Aktif ({{ $technicians->count() }}) —</option>
                            @foreach($technicians as $technician)
                            <option value="{{ $technician->id }}" {{ (string) $technicianFilter === (string) $technician->id ? 'selected' : '' }}>
                                {{ $technician->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Gudang Asal Penyerahan -->
                    <div class="lg:col-span-3">
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-400 mb-1.5 flex items-center gap-1.5">
                            <svg class="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                            <span>Gudang Asal POP</span>
                        </label>
                        <select name="pop_id" class="w-full px-3 py-2 text-xs font-semibold border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                            <option value="">— Semua Gudang POP —</option>
                            @foreach($pops as $pop)
                            <option value="{{ $pop->id }}" {{ (string) $popFilter === (string) $pop->id ? 'selected' : '' }}>{{ $pop->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Input Pencarian -->
                    <div class="lg:col-span-3">
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-400 mb-1.5 flex items-center gap-1.5">
                            <svg class="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <span>Cari Barang / SN / Roll / Lot</span>
                        </label>
                        <div class="relative">
                            <input type="text" name="search" value="{{ $search }}" placeholder="Ketik kata kunci pencarian..."
                                   class="w-full pl-9 pr-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all placeholder:text-slate-400 dark:placeholder:text-slate-500">
                            <svg class="w-4 h-4 text-slate-400 dark:text-slate-500 absolute left-3 top-2.5 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                            </svg>
                        </div>
                    </div>

                    <!-- Tombol Action -->
                    <div class="lg:col-span-2 flex items-end gap-2">
                        <button type="submit" class="flex-1 inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 text-white text-xs font-bold rounded-xl shadow-xs transition-all active:scale-[0.98] cursor-pointer">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                            <span>Terapkan</span>
                        </button>
                    </div>

                </div>
            </form>
        </div>

        @if($viewMode === 'teknisi')
        @include('warehouse.custody.per-teknisi')
        @else
        <!-- ================= LAYER 3: SEGMENTED TAB SWITCHER (Responsive Scroll) ================= -->
        <div class="px-4 sm:px-5 py-3.5 bg-slate-50/40 dark:bg-slate-900/30 overflow-x-auto no-scrollbar scroll-smooth">
            <div class="flex items-center gap-2 min-w-max">
                
                <!-- Tab 1: Serial Numbers -->
                <button @click="activeTab = 'serials'"
                        type="button"
                        :class="activeTab === 'serials' ? 'bg-sky-500 text-white shadow-xs shadow-sky-500/25 ring-1 ring-sky-500' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700'"
                        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
                    <span>Perangkat Serial Number</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold" :class="activeTab === 'serials' ? 'bg-white/25 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-600'">
                        {{ $serials->total() }}
                    </span>
                </button>

                <!-- Tab 2: Material Pasif -->
                <button @click="activeTab = 'materials'"
                        type="button"
                        :class="activeTab === 'materials' ? 'bg-indigo-600 text-white shadow-xs shadow-indigo-600/25 ring-1 ring-indigo-600' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700'"
                        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    <span>Perangkat Pasif (Material)</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold" :class="activeTab === 'materials' ? 'bg-white/25 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-600'">
                        {{ $custodies->total() }}
                    </span>
                </button>

                <!-- Tab 3: Roll Kabel -->
                <button @click="activeTab = 'rolls'"
                        type="button"
                        :class="activeTab === 'rolls' ? 'bg-amber-500 text-white shadow-xs shadow-amber-500/25 ring-1 ring-amber-500' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700'"
                        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a1 1 0 001-1v-4a1 1 0 00-1-1H9a1 1 0 00-1 1v4a1 1 0 001 1zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    <span>Roll Kabel</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold" :class="activeTab === 'rolls' ? 'bg-white/25 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-600'">
                        {{ $rolls->total() }}
                    </span>
                </button>

                <!-- Tab 4: Return dari Pelanggan -->
                <button @click="activeTab = 'returns'"
                        type="button"
                        :class="activeTab === 'returns' ? 'bg-teal-600 text-white shadow-xs shadow-teal-600/25 ring-1 ring-teal-600' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700'"
                        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/></svg>
                    <span>Return dari Pelanggan</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold" :class="activeTab === 'returns' ? 'bg-white/25 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-600'">
                        {{ $returned->total() }}
                    </span>
                </button>

            </div>
        </div>
    </div>

    <!-- =========================================================================
         TAB 1: PERANGKAT SERIAL NUMBER
         ========================================================================= -->
    <div x-show="activeTab === 'serials'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-xs overflow-hidden">
            
            <!-- Tab Header Banner -->
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50/60 dark:bg-slate-800/80">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-cyan-50 dark:bg-cyan-950/60 text-cyan-600 dark:text-cyan-400 flex items-center justify-center border border-cyan-200 dark:border-cyan-800 shrink-0 shadow-2xs">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
                    </div>
                    <div>
                        <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">Perangkat Aktif (Serial Number)</h3>
                        <p class="text-[11px] text-slate-400 dark:text-slate-400">Modem ONT, Router, dan Radio Wireless yang diserahkan untuk tugas pasang/ganti</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 self-end sm:self-auto">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 text-xs font-mono font-bold">
                        <span>Total:</span>
                        <strong class="text-cyan-600 dark:text-cyan-400">{{ $serials->total() }}</strong>
                        <span class="text-[10px] text-slate-400 font-sans">Unit</span>
                    </span>
                </div>
            </div>

            @if($serials->isEmpty())
            <div class="p-12 sm:p-16 text-center">
                <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-cyan-50 dark:bg-cyan-950/40 border border-cyan-200 dark:border-cyan-800 flex items-center justify-center text-cyan-600 dark:text-cyan-400">
                    <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Tidak ada perangkat aktif di tangan teknisi</h4>
                <p class="text-xs text-slate-400 dark:text-slate-400 mt-1 max-w-sm mx-auto">Semua perangkat serial number berada di gudang atau sudah terpasang di lokasi pelanggan.</p>
            </div>
            @else
            <!-- Desktop Table View (hidden lg:block) -->
            <div class="hidden lg:block overflow-x-auto scroll-smooth">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800/90">
                        <tr>
                            <th scope="col" aria-sort="{{ $tabSortAria($serialSort, $serialDir, 'teknisi') }}" class="px-6 py-3.5 text-left text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                <a href="{{ $tabSortLink('serial', 'teknisi', $serialSort, $serialDir) }}" class="inline-flex items-center gap-1 hover:text-slate-800 dark:hover:text-slate-200">Teknisi Lapangan <span class="font-mono {{ $tabSortHead($serialSort, 'teknisi') }}" aria-hidden="true">{{ $tabSortIcon($serialSort, $serialDir, 'teknisi') }}</span></a>
                            </th>
                            <th scope="col" aria-sort="{{ $tabSortAria($serialSort, $serialDir, 'item') }}" class="px-6 py-3.5 text-left text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                <a href="{{ $tabSortLink('serial', 'item', $serialSort, $serialDir) }}" class="inline-flex items-center gap-1 hover:text-slate-800 dark:hover:text-slate-200">Barang / Model <span class="font-mono {{ $tabSortHead($serialSort, 'item') }}" aria-hidden="true">{{ $tabSortIcon($serialSort, $serialDir, 'item') }}</span></a>
                            </th>
                            <th class="px-6 py-3.5 text-left text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Serial Number</th>
                            <th class="px-6 py-3.5 text-left text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Asal Gudang POP</th>
                            <th class="px-6 py-3.5 text-left text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Diinput Oleh &amp; Sejak</th>
                            <th class="px-6 py-3.5 text-right text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Aksi Kelola</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700/60">
                        @foreach($serials as $serial)
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/40 transition-colors group">
                            <!-- Teknisi -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-sky-500 to-indigo-600 text-white font-bold text-xs flex items-center justify-center shrink-0 shadow-2xs">
                                        {{ strtoupper(substr($serial->currentTechnician->name ?? 'T', 0, 2)) }}
                                    </div>
                                    <div>
                                        <div class="text-xs font-bold text-slate-800 dark:text-slate-200 group-hover:text-sky-600 dark:group-hover:text-sky-400 transition-colors">
                                            {{ $serial->currentTechnician->name ?? '-' }}
                                        </div>
                                        <div class="text-[10px] text-slate-400 dark:text-slate-400 flex items-center gap-1">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            <span>Teknisi Lapangan</span>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Barang -->
                            <td class="px-6 py-4">
                                <div class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ $serial->item->name }}</div>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="text-[11px] font-mono text-slate-400 dark:text-slate-400">{{ $serial->item->code }}</span>
                                    @if(($serial->condition?->value ?? 'new') !== 'new')
                                    <x-warehouse.condition-badge
                                        :condition="$serial->condition"
                                        :checked="$serial->condition_checked_at !== null" />
                                    @endif
                                </div>
                            </td>

                            <!-- Serial Number -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($canTrace)
                                <a href="{{ route('warehouse.traceability.index', ['sn' => $serial->serial_number]) }}"
                                   class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-sky-50 hover:bg-sky-100 dark:bg-sky-950/50 dark:hover:bg-sky-900/60 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800 transition-colors"
                                   title="Lacak Riwayat SN">
                                    <span>{{ $serial->serial_number }}</span>
                                    <svg class="w-3.5 h-3.5 text-sky-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                                </a>
                                @else
                                <span class="font-mono text-xs font-bold text-slate-700 dark:text-slate-300">{{ $serial->serial_number }}</span>
                                @endif
                            </td>

                            <!-- Asal Gudang POP -->
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-600 dark:text-slate-400">
                                <span class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-700/80 font-medium text-[11px] border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200">
                                    {{ $serial->issuedFromPop->name ?? '-' }}
                                </span>
                            </td>

                            <!-- Diinput oleh & sejak (analisa-ui-ux §U3) -->
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-600 dark:text-slate-400">
                                @php $issue = $serialIssuers->get($serial->id); @endphp
                                @if($issue)
                                <div class="font-semibold text-slate-700 dark:text-slate-200">{{ $issue->createdBy->name ?? 'Sistem' }}</div>
                                <div class="text-[11px] text-slate-400" title="{{ $issue->created_at?->translatedFormat('d M Y H:i') }}">{{ $issue->created_at?->diffForHumans() ?? '-' }}</div>
                                @else
                                <span class="text-[11px] text-slate-400">—</span>
                                @endif
                            </td>

                            <!-- Aksi Cepat (Teleported Desktop) -->
                            <td class="px-6 py-4 whitespace-nowrap text-right text-xs relative">
                                @if($canAdjust || $canReassign || $canTrace)
                                <div x-data="{
                                    menuOpen: false,
                                    pos: { openUp: false, top: null, bottom: null, left: 0 },
                                    toggle(e) {
                                        if (!this.menuOpen) {
                                            this.pos = window.calcDropdownPos(e.currentTarget, 224);
                                        }
                                        this.menuOpen = !this.menuOpen;
                                    }
                                }" @scroll.window="menuOpen = false" @resize.window="menuOpen = false" class="inline-block text-left">
                                    <button type="button" @click.stop="toggle($event)"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 transition-colors cursor-pointer border border-slate-200 dark:border-slate-600">
                                        <span>Kelola</span>
                                        <svg class="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                    <template x-teleport="body">
                                        <div x-show="menuOpen" x-cloak @click.outside="menuOpen = false" @click.stop
                                            x-transition:enter="transition ease-out duration-100"
                                            x-transition:enter-start="opacity-0 scale-95"
                                            x-transition:enter-end="opacity-100 scale-100"
                                            x-transition:leave="transition ease-in duration-75"
                                            x-transition:leave-start="opacity-100 scale-100"
                                            x-transition:leave-end="opacity-0 scale-95"
                                            :style="pos.openUp ? `position: fixed; bottom: ${pos.bottom}px; left: ${pos.left}px; z-index: 9999;` : `position: fixed; top: ${pos.top}px; left: ${pos.left}px; z-index: 9999;`"
                                            class="w-56 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-2xl py-1.5 text-left divide-y divide-slate-100 dark:divide-slate-700/80">
                                            <div class="py-1">
                                                @if($canReassign)
                                                <a href="{{ route('warehouse.reassign.serial.create', $serial) }}"
                                                   class="flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-sky-700 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-sky-950/50 transition-colors">
                                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                                                    <span>Alihkan Custody</span>
                                                </a>
                                                @endif
                                                @if($canAdjust)
                                                <a href="{{ route('warehouse.adjustments.serial.create', $serial) }}"
                                                   class="flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-amber-700 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/50 transition-colors">
                                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                                    <span>Lapor BAP / Rusak</span>
                                                </a>
                                                @endif
                                            </div>
                                            @if($canTrace)
                                            <div class="py-1">
                                                <a href="{{ route('warehouse.traceability.index', ['sn' => $serial->serial_number]) }}"
                                                   class="flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                                                    <span>Lacak Timeline SN</span>
                                                </a>
                                            </div>
                                            @endif
                                        </div>
                                    </template>
                                </div>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Mobile & Tablet Card Grid View (block lg:hidden) -->
            <div class="block lg:hidden p-3.5 sm:p-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                    @foreach($serials as $serial)
                    <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-xs flex flex-col justify-between space-y-3.5 hover:border-slate-300 dark:hover:border-slate-600 transition-colors">
                        
                        <!-- Card Header: Technician + Actions -->
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-sky-500 to-indigo-600 text-white font-bold text-xs flex items-center justify-center shrink-0 shadow-2xs">
                                    {{ strtoupper(substr($serial->currentTechnician->name ?? 'T', 0, 2)) }}
                                </div>
                                <div class="min-w-0">
                                    <div class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate">{{ $serial->currentTechnician->name ?? '-' }}</div>
                                    <div class="text-[10px] text-slate-400 dark:text-slate-400">Gudang: {{ $serial->issuedFromPop->name ?? '-' }}</div>
                                </div>
                            </div>

                            @if($canAdjust || $canReassign || $canTrace)
                            <button type="button"
                                    @click="$dispatch('open-custody-actions', {
                                        technicianName: @js($serial->currentTechnician->name ?? '-'),
                                        popName: @js($serial->issuedFromPop->name ?? '-'),
                                        itemName: @js($serial->item->name),
                                        itemCode: @js($serial->item->code),
                                        identifier: @js('SN: ' . $serial->serial_number),
                                        reassignUrl: @js($canReassign ? route('warehouse.reassign.serial.create', $serial) : null),
                                        reassignTitle: 'Alihkan Custody Perangkat',
                                        adjustUrl: @js($canAdjust ? route('warehouse.adjustments.serial.create', $serial) : null),
                                        adjustTitle: 'Lapor BAP / Rusak',
                                        traceUrl: @js($canTrace ? route('warehouse.traceability.index', ['sn' => $serial->serial_number]) : null),
                                        traceTitle: 'Lacak Riwayat SN',
                                    })"
                                    class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-600 dark:text-slate-300 text-xs font-bold cursor-pointer transition-colors border border-slate-200 dark:border-slate-600"
                                    title="Menu Aksi">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 18.75a.75.75 0 110-1.5.75.75 0 010 1.5z"/></svg>
                            </button>
                            @endif
                        </div>

                        <!-- Card Body: Item Name & Info -->
                        <div>
                            <h4 class="text-xs sm:text-sm font-bold text-slate-800 dark:text-slate-200">{{ $serial->item->name }}</h4>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-[11px] font-mono text-slate-400 dark:text-slate-400">{{ $serial->item->code }}</span>
                                @if(($serial->condition?->value ?? 'new') !== 'new')
                                <x-warehouse.condition-badge
                                    :condition="$serial->condition"
                                    :checked="$serial->condition_checked_at !== null" />
                                @endif
                            </div>
                            @php $issue = $serialIssuers->get($serial->id); @endphp
                            @if($issue)
                            <div class="text-[10px] text-slate-400 dark:text-slate-400 mt-1">Diinput oleh <span class="font-semibold text-slate-600 dark:text-slate-300">{{ $issue->createdBy->name ?? 'Sistem' }}</span> · {{ $issue->created_at?->diffForHumans() }}</div>
                            @endif
                        </div>

                        <!-- Card Footer: SN Pill -->
                        <div class="pt-2.5 border-t border-slate-100 dark:border-slate-700/80 flex items-center justify-between">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-400">Serial Number</span>
                            @if($canTrace)
                            <a href="{{ route('warehouse.traceability.index', ['sn' => $serial->serial_number]) }}"
                               class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-sky-50 hover:bg-sky-100 dark:bg-sky-950/50 dark:hover:bg-sky-900/60 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800 transition-colors">
                                <span>{{ $serial->serial_number }}</span>
                                <svg class="w-3 h-3 text-sky-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                            </a>
                            @else
                            <span class="font-mono text-xs font-bold text-slate-700 dark:text-slate-300">{{ $serial->serial_number }}</span>
                            @endif
                        </div>

                    </div>
                    @endforeach
                </div>
            </div>
            @endif

        </div>
    </div>

    <!-- =========================================================================
         TAB 2: MATERIAL PASIF (BATCH / AKSESORI)
         ========================================================================= -->
    <div x-show="activeTab === 'materials'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-xs overflow-hidden">
            
            <!-- Tab Header Banner -->
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50/60 dark:bg-slate-800/80">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center border border-indigo-200 dark:border-indigo-800 shrink-0 shadow-2xs">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    </div>
                    <div>
                        <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">Perangkat Pasif Di Tangan Teknisi</h3>
                        <p class="text-[11px] text-slate-400 dark:text-slate-400">Kabel dropcore potong, patchcord, konektor, ODP/closure yang dibawa teknisi</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 self-end sm:self-auto">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 text-xs font-mono font-bold">
                        <span>Total:</span>
                        <strong class="text-indigo-600 dark:text-indigo-400">{{ $custodies->total() }}</strong>
                        <span class="text-[10px] text-slate-400 font-sans">Batch</span>
                    </span>
                </div>
            </div>

            @if($custodies->isEmpty())
            <div class="p-12 sm:p-16 text-center">
                <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                    <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </div>
                <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Tidak ada custody material aktif</h4>
                <p class="text-xs text-slate-400 dark:text-slate-400 mt-1 max-w-sm mx-auto">Semua material telah direkonsiliasi dalam laporan pemasangan atau dikembalikan ke gudang.</p>
            </div>
            @else
            <!-- Desktop Table (hidden lg:block) -->
            <div class="hidden lg:block overflow-x-auto scroll-smooth">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800/90">
                        <tr>
                            <th scope="col" aria-sort="{{ $tabSortAria($materialSort, $materialDir, 'teknisi') }}" class="px-6 py-3.5 text-left text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                <a href="{{ $tabSortLink('material', 'teknisi', $materialSort, $materialDir) }}" class="inline-flex items-center gap-1 hover:text-slate-800 dark:hover:text-slate-200">Teknisi Lapangan <span class="font-mono {{ $tabSortHead($materialSort, 'teknisi') }}" aria-hidden="true">{{ $tabSortIcon($materialSort, $materialDir, 'teknisi') }}</span></a>
                            </th>
                            <th scope="col" aria-sort="{{ $tabSortAria($materialSort, $materialDir, 'item') }}" class="px-6 py-3.5 text-left text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                <a href="{{ $tabSortLink('material', 'item', $materialSort, $materialDir) }}" class="inline-flex items-center gap-1 hover:text-slate-800 dark:hover:text-slate-200">Material / Barang <span class="font-mono {{ $tabSortHead($materialSort, 'item') }}" aria-hidden="true">{{ $tabSortIcon($materialSort, $materialDir, 'item') }}</span></a>
                            </th>
                            <th class="px-6 py-3.5 text-left text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Lot / Drum</th>
                            <th scope="col" aria-sort="{{ $tabSortAria($materialSort, $materialDir, 'qty') }}" class="px-6 py-3.5 text-right text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                <a href="{{ $tabSortLink('material', 'qty', $materialSort, $materialDir) }}" class="inline-flex items-center gap-1 justify-end w-full hover:text-slate-800 dark:hover:text-slate-200">Sisa Qty <span class="font-mono {{ $tabSortHead($materialSort, 'qty') }}" aria-hidden="true">{{ $tabSortIcon($materialSort, $materialDir, 'qty') }}</span></a>
                            </th>
                            <th class="px-6 py-3.5 text-left text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Asal POP</th>
                            <th class="px-6 py-3.5 text-left text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Lama Dipegang</th>
                            <th class="px-6 py-3.5 text-right text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Aksi Kelola</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700/60">
                        @foreach($custodies as $custody)
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/40 transition-colors group">
                            <!-- Teknisi -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-indigo-500 to-purple-600 text-white font-bold text-xs flex items-center justify-center shrink-0 shadow-2xs">
                                        {{ strtoupper(substr($custody->technician->name ?? 'T', 0, 2)) }}
                                    </div>
                                    <div>
                                        <div class="text-xs font-bold text-slate-800 dark:text-slate-200 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                                            {{ $custody->technician->name }}
                                        </div>
                                        <div class="text-[10px] text-slate-400 dark:text-slate-400 flex items-center gap-1">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            <span>Teknisi Lapangan</span>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Barang -->
                            <td class="px-6 py-4">
                                <div class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ $custody->item->name }}</div>
                                <div class="text-[11px] font-mono text-slate-400 dark:text-slate-400 mt-0.5">{{ $custody->item->code }}</div>
                            </td>

                            <!-- Lot -->
                            <td class="px-6 py-4 whitespace-nowrap font-mono text-xs text-slate-500 dark:text-slate-400">
                                {{ $custody->lot_no ?: '—' }}
                            </td>

                            <!-- Sisa Qty -->
                            <td class="px-6 py-4 whitespace-nowrap text-right font-mono">
                                <span class="text-sm font-extrabold text-slate-900 dark:text-slate-100 tabular-nums">
                                    {{ rtrim(rtrim(number_format((float) $custody->qty_remaining, 2, ',', '.'), '0'), ',') }}
                                </span>
                                <span class="text-xs font-semibold text-slate-400 ml-0.5">{{ $custody->item->unit }}</span>
                            </td>

                            <!-- Asal Gudang -->
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-600 dark:text-slate-400">
                                <span class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-700/80 font-medium text-[11px] border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200">
                                    {{ $custody->issuedFromPop->name ?? '-' }}
                                </span>
                            </td>

                            <!-- Lama Dipegang (Aging) + Diinput oleh (analisa-ui-ux §U3) -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-semibold bg-slate-100 dark:bg-slate-700/80 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600">
                                    {{ $custody->ageLabel() }}
                                </span>
                                @php $cIssue = $custodyIssuers->get($custodyKey($custody->technician_id, $custody->item_id, $custody->lot_no, $custody->issued_at)); @endphp
                                @if($cIssue)
                                <div class="text-[10px] text-slate-400 dark:text-slate-400 mt-1">oleh <span class="font-semibold text-slate-600 dark:text-slate-300">{{ $cIssue->createdBy->name ?? 'Sistem' }}</span></div>
                                @endif
                            </td>

                            <!-- Aksi Cepat (Teleported Desktop) -->
                            <td class="px-6 py-4 whitespace-nowrap text-right text-xs relative">
                                @if($canAdjust || $canReassign)
                                <div x-data="{
                                    menuOpen: false,
                                    pos: { openUp: false, top: null, bottom: null, left: 0 },
                                    toggle(e) {
                                        if (!this.menuOpen) {
                                            this.pos = window.calcDropdownPos(e.currentTarget, 224);
                                        }
                                        this.menuOpen = !this.menuOpen;
                                    }
                                }" @scroll.window="menuOpen = false" @resize.window="menuOpen = false" class="inline-block text-left">
                                    <button type="button" @click.stop="toggle($event)"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 transition-colors cursor-pointer border border-slate-200 dark:border-slate-600">
                                        <span>Kelola</span>
                                        <svg class="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                    <template x-teleport="body">
                                        <div x-show="menuOpen" x-cloak @click.outside="menuOpen = false" @click.stop
                                            x-transition:enter="transition ease-out duration-100"
                                            x-transition:enter-start="opacity-0 scale-95"
                                            x-transition:enter-end="opacity-100 scale-100"
                                            x-transition:leave="transition ease-in duration-75"
                                            x-transition:leave-start="opacity-100 scale-100"
                                            x-transition:leave-end="opacity-0 scale-95"
                                            :style="pos.openUp ? `position: fixed; bottom: ${pos.bottom}px; left: ${pos.left}px; z-index: 9999;` : `position: fixed; top: ${pos.top}px; left: ${pos.left}px; z-index: 9999;`"
                                            class="w-56 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-2xl py-1.5 text-left divide-y divide-slate-100 dark:divide-slate-700/80">
                                            <div class="py-1">
                                                @if($canReassign)
                                                <a href="{{ route('warehouse.reassign.custody.create', $custody) }}"
                                                   class="flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-sky-700 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-sky-950/50 transition-colors">
                                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                                                    <span>Kembalikan / Alihkan</span>
                                                </a>
                                                @endif
                                                @if($canAdjust)
                                                <a href="{{ route('warehouse.adjustments.custody.create', $custody) }}"
                                                   class="flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-amber-700 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/50 transition-colors">
                                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                                    <span>Koreksi / Lapor BAP</span>
                                                </a>
                                                @endif
                                            </div>
                                        </div>
                                    </template>
                                </div>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Mobile & Tablet Card Grid View (block lg:hidden) -->
            <div class="block lg:hidden p-3.5 sm:p-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                    @foreach($custodies as $custody)
                    <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-xs flex flex-col justify-between space-y-3.5 hover:border-slate-300 dark:hover:border-slate-600 transition-colors">
                        
                        <!-- Card Header -->
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-indigo-500 to-purple-600 text-white font-bold text-xs flex items-center justify-center shrink-0 shadow-2xs">
                                    {{ strtoupper(substr($custody->technician->name ?? 'T', 0, 2)) }}
                                </div>
                                <div class="min-w-0">
                                    <div class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate">{{ $custody->technician->name }}</div>
                                    <div class="text-[10px] text-slate-400 dark:text-slate-400">Gudang: {{ $custody->issuedFromPop->name ?? '-' }}</div>
                                </div>
                            </div>

                            @if($canAdjust || $canReassign)
                            <button type="button"
                                    @click="$dispatch('open-custody-actions', {
                                        technicianName: @js($custody->technician->name ?? '-'),
                                        popName: @js($custody->issuedFromPop->name ?? '-'),
                                        itemName: @js($custody->item->name),
                                        itemCode: @js($custody->item->code),
                                        identifier: @js('Sisa: ' . rtrim(rtrim(number_format((float) $custody->qty_remaining, 2, ',', '.'), '0'), ',') . ' ' . $custody->item->unit . ($custody->lot_no ? ' • Lot: ' . $custody->lot_no : '')),
                                        reassignUrl: @js($canReassign ? route('warehouse.reassign.custody.create', $custody) : null),
                                        reassignTitle: 'Kembalikan / Alihkan Material',
                                        adjustUrl: @js($canAdjust ? route('warehouse.adjustments.custody.create', $custody) : null),
                                        adjustTitle: 'Koreksi / Lapor BAP',
                                        traceUrl: null,
                                        traceTitle: null,
                                    })"
                                    class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-600 dark:text-slate-300 text-xs font-bold cursor-pointer transition-colors border border-slate-200 dark:border-slate-600"
                                    title="Menu Aksi">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 18.75a.75.75 0 110-1.5.75.75 0 010 1.5z"/></svg>
                            </button>
                            @endif
                        </div>

                        <!-- Card Body -->
                        <div>
                            <h4 class="text-xs sm:text-sm font-bold text-slate-800 dark:text-slate-200">{{ $custody->item->name }}</h4>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-[11px] font-mono text-slate-400 dark:text-slate-400">{{ $custody->item->code }}</span>
                                @if($custody->lot_no)
                                <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700/80 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-600">Lot: {{ $custody->lot_no }}</span>
                                @endif
                            </div>
                        </div>

                        <!-- Card Footer -->
                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-700/40 border border-slate-200/80 dark:border-slate-700/80 flex items-center justify-between">
                            <div>
                                <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400 dark:text-slate-400 block">Sisa Qty</span>
                                <div class="flex items-baseline gap-1 mt-0.5">
                                    <span class="text-sm sm:text-base font-mono font-extrabold text-slate-900 dark:text-slate-100">
                                        {{ rtrim(rtrim(number_format((float) $custody->qty_remaining, 2, ',', '.'), '0'), ',') }}
                                    </span>
                                    <span class="text-[11px] text-slate-500 dark:text-slate-400 font-semibold">{{ $custody->item->unit }}</span>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400 dark:text-slate-400 block">Lama Dipegang</span>
                                <span class="inline-flex mt-0.5 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-200/80 dark:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-300/60 dark:border-slate-600">
                                    {{ $custody->ageLabel() }}
                                </span>
                                @php $cIssue = $custodyIssuers->get($custodyKey($custody->technician_id, $custody->item_id, $custody->lot_no, $custody->issued_at)); @endphp
                                @if($cIssue)
                                <span class="block text-[10px] text-slate-400 dark:text-slate-400 mt-1">oleh {{ $cIssue->createdBy->name ?? 'Sistem' }}</span>
                                @endif
                            </div>
                        </div>

                    </div>
                    @endforeach
                </div>
            </div>
            @endif

        </div>
    </div>

    <!-- =========================================================================
         TAB 3: ROLL KABEL
         ========================================================================= -->
    <div x-show="activeTab === 'rolls'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-xs overflow-hidden">
            
            <!-- Tab Header Banner -->
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50/60 dark:bg-slate-800/80">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center border border-amber-200 dark:border-amber-800 shrink-0 shadow-2xs">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a1 1 0 001-1v-4a1 1 0 00-1-1H9a1 1 0 00-1 1v4a1 1 0 001 1zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">Roll Kabel Di Tangan Teknisi</h3>
                        <p class="text-[11px] text-slate-400 dark:text-slate-400">Sisa meter per roll — dilacak per haspel/drum individu, bukan agregat</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 self-end sm:self-auto">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 text-xs font-mono font-bold">
                        <span>Total:</span>
                        <strong class="text-amber-600 dark:text-amber-400">{{ $rolls->total() }}</strong>
                        <span class="text-[10px] text-slate-400 font-sans">Roll Drum</span>
                    </span>
                </div>
            </div>

            @if($rolls->isEmpty())
            <div class="p-12 sm:p-16 text-center">
                <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 flex items-center justify-center text-amber-600 dark:text-amber-400">
                    <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Tidak ada roll kabel di tangan teknisi</h4>
                <p class="text-xs text-slate-400 dark:text-slate-400 mt-1 max-w-sm mx-auto">Semua roll kabel berada di gudang atau sudah habis dipakai (depleted).</p>
            </div>
            @else
            <!-- Desktop Table (hidden lg:block) -->
            <div class="hidden lg:block overflow-x-auto scroll-smooth">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800/90">
                        <tr>
                            <th scope="col" aria-sort="{{ $tabSortAria($rollSort, $rollDir, 'teknisi') }}" class="px-6 py-3.5 text-left text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                <a href="{{ $tabSortLink('roll', 'teknisi', $rollSort, $rollDir) }}" class="inline-flex items-center gap-1 hover:text-slate-800 dark:hover:text-slate-200">Teknisi Lapangan <span class="font-mono {{ $tabSortHead($rollSort, 'teknisi') }}" aria-hidden="true">{{ $tabSortIcon($rollSort, $rollDir, 'teknisi') }}</span></a>
                            </th>
                            <th scope="col" aria-sort="{{ $tabSortAria($rollSort, $rollDir, 'item') }}" class="px-6 py-3.5 text-left text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                <a href="{{ $tabSortLink('roll', 'item', $rollSort, $rollDir) }}" class="inline-flex items-center gap-1 hover:text-slate-800 dark:hover:text-slate-200">Barang / Kabel <span class="font-mono {{ $tabSortHead($rollSort, 'item') }}" aria-hidden="true">{{ $tabSortIcon($rollSort, $rollDir, 'item') }}</span></a>
                            </th>
                            <th class="px-6 py-3.5 text-left text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Roll ID</th>
                            <th scope="col" aria-sort="{{ $tabSortAria($rollSort, $rollDir, 'remaining') }}" class="px-6 py-3.5 text-left text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider min-w-[200px]">
                                <a href="{{ $tabSortLink('roll', 'remaining', $rollSort, $rollDir) }}" class="inline-flex items-center gap-1 hover:text-slate-800 dark:hover:text-slate-200">Sisa Meter & Kapasitas <span class="font-mono {{ $tabSortHead($rollSort, 'remaining') }}" aria-hidden="true">{{ $tabSortIcon($rollSort, $rollDir, 'remaining') }}</span></a>
                            </th>
                            <th class="px-6 py-3.5 text-left text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Asal POP</th>
                            <th class="px-6 py-3.5 text-right text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Aksi Kelola</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700/60">
                        @foreach($rolls as $roll)
                        @php
                            $remaining = (float) $roll->length_remaining;
                            $total = max(1, (float) $roll->length_total);
                            $percentage = min(100, max(0, round(($remaining / $total) * 100)));
                            $isLow = $roll->isLowRemaining();
                        @endphp
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/40 transition-colors group">
                            <!-- Teknisi -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-amber-500 to-orange-600 text-white font-bold text-xs flex items-center justify-center shrink-0 shadow-2xs">
                                        {{ strtoupper(substr($roll->currentTechnician->name ?? 'T', 0, 2)) }}
                                    </div>
                                    <div>
                                        <div class="text-xs font-bold text-slate-800 dark:text-slate-200 group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors">
                                            {{ $roll->currentTechnician->name ?? '-' }}
                                        </div>
                                        <div class="text-[10px] text-slate-400 dark:text-slate-400 flex items-center gap-1">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            <span>Teknisi Lapangan</span>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Barang -->
                            <td class="px-6 py-4">
                                <div class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ $roll->item->name ?? '(barang dihapus)' }}</div>
                                <div class="text-[11px] font-mono text-slate-400 dark:text-slate-400 mt-0.5">{{ $roll->item->code ?? '-' }}</div>
                            </td>

                            <!-- Roll ID -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($canTrace)
                                <a href="{{ route('warehouse.traceability.index', ['roll' => $roll->roll_code]) }}"
                                   class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-amber-50 hover:bg-amber-100 dark:bg-amber-950/50 dark:hover:bg-amber-900/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800 transition-colors"
                                   title="Lacak Riwayat Roll">
                                    <span>{{ $roll->roll_code }}</span>
                                    <svg class="w-3.5 h-3.5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                                </a>
                                @else
                                <span class="font-mono text-xs font-bold text-slate-700 dark:text-slate-300">{{ $roll->roll_code }}</span>
                                @endif
                                @php $rIssue = $rollIssuers->get($roll->id); @endphp
                                @if($rIssue)
                                <div class="text-[10px] text-slate-400 dark:text-slate-400 mt-1">Diinput {{ $rIssue->createdBy->name ?? 'Sistem' }} · {{ $rIssue->created_at?->diffForHumans() }}</div>
                                @endif
                            </td>

                            <!-- Sisa Meter & Progress Bar -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="space-y-1.5">
                                    <div class="flex items-center justify-between text-xs font-mono">
                                        <div>
                                            <span class="font-extrabold text-slate-900 dark:text-slate-100 text-sm">
                                                {{ rtrim(rtrim(number_format($remaining, 2, ',', '.'), '0'), ',') }}
                                            </span>
                                            <span class="text-slate-400 dark:text-slate-400 font-semibold text-[11px]">/ {{ rtrim(rtrim(number_format((float) $roll->length_total, 2, ',', '.'), '0'), ',') }} m</span>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            @if($isLow)
                                            <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800">Sisa Kecil</span>
                                            @endif
                                            <span class="text-[11px] font-bold {{ $isLow ? 'text-rose-600 dark:text-rose-400' : 'text-slate-500 dark:text-slate-400' }}">{{ $percentage }}%</span>
                                        </div>
                                    </div>
                                    <div class="w-full bg-slate-100 dark:bg-slate-700/80 rounded-full h-1.5 overflow-hidden">
                                        <div class="h-full rounded-full transition-all duration-300 {{ $isLow ? 'bg-rose-500' : ($percentage < 40 ? 'bg-amber-500' : 'bg-emerald-500') }}"
                                             style="width: {{ $percentage }}%"></div>
                                    </div>
                                </div>
                            </td>

                            <!-- Asal POP -->
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-600 dark:text-slate-400">
                                <span class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-700/80 font-medium text-[11px] border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200">
                                    {{ $roll->issuedFromPop->name ?? '-' }}
                                </span>
                            </td>

                            <!-- Aksi Cepat (Teleported Desktop) -->
                            <td class="px-6 py-4 whitespace-nowrap text-right text-xs relative">
                                @if($canAdjust || $canReassign || $canTrace)
                                <div x-data="{
                                    menuOpen: false,
                                    pos: { openUp: false, top: null, bottom: null, left: 0 },
                                    toggle(e) {
                                        if (!this.menuOpen) {
                                            this.pos = window.calcDropdownPos(e.currentTarget, 224);
                                        }
                                        this.menuOpen = !this.menuOpen;
                                    }
                                }" @scroll.window="menuOpen = false" @resize.window="menuOpen = false" class="inline-block text-left">
                                    <button type="button" @click.stop="toggle($event)"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 transition-colors cursor-pointer border border-slate-200 dark:border-slate-600">
                                        <span>Kelola</span>
                                        <svg class="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                    <template x-teleport="body">
                                        <div x-show="menuOpen" x-cloak @click.outside="menuOpen = false" @click.stop
                                            x-transition:enter="transition ease-out duration-100"
                                            x-transition:enter-start="opacity-0 scale-95"
                                            x-transition:enter-end="opacity-100 scale-100"
                                            x-transition:leave="transition ease-in duration-75"
                                            x-transition:leave-start="opacity-100 scale-100"
                                            x-transition:leave-end="opacity-0 scale-95"
                                            :style="pos.openUp ? `position: fixed; bottom: ${pos.bottom}px; left: ${pos.left}px; z-index: 9999;` : `position: fixed; top: ${pos.top}px; left: ${pos.left}px; z-index: 9999;`"
                                            class="w-56 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-2xl py-1.5 text-left divide-y divide-slate-100 dark:divide-slate-700/80">
                                            <div class="py-1">
                                                @if($canReassign)
                                                <a href="{{ route('warehouse.reassign.roll.create', $roll) }}"
                                                   class="flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-sky-700 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-sky-950/50 transition-colors">
                                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                                                    <span>Alihkan Custody</span>
                                                </a>
                                                @endif
                                                @if($canAdjust)
                                                <a href="{{ route('warehouse.adjustments.roll.create', $roll) }}"
                                                   class="flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-amber-700 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/50 transition-colors">
                                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                                    <span>Lapor BAP / Rusak</span>
                                                </a>
                                                @endif
                                            </div>
                                            @if($canTrace)
                                            <div class="py-1">
                                                <a href="{{ route('warehouse.traceability.index', ['roll' => $roll->roll_code]) }}"
                                                   class="flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                                                    <span>Lacak Timeline Roll</span>
                                                </a>
                                            </div>
                                            @endif
                                        </div>
                                    </template>
                                </div>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Mobile & Tablet Card Grid View (block lg:hidden) -->
            <div class="block lg:hidden p-3.5 sm:p-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                    @foreach($rolls as $roll)
                    @php
                        $remaining = (float) $roll->length_remaining;
                        $total = max(1, (float) $roll->length_total);
                        $percentage = min(100, max(0, round(($remaining / $total) * 100)));
                        $isLow = $roll->isLowRemaining();
                    @endphp
                    <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-xs flex flex-col justify-between space-y-3.5 hover:border-slate-300 dark:hover:border-slate-600 transition-colors">
                        
                        <!-- Card Header -->
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-amber-500 to-orange-600 text-white font-bold text-xs flex items-center justify-center shrink-0 shadow-2xs">
                                    {{ strtoupper(substr($roll->currentTechnician->name ?? 'T', 0, 2)) }}
                                </div>
                                <div class="min-w-0">
                                    <div class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate">{{ $roll->currentTechnician->name ?? '-' }}</div>
                                    <div class="text-[10px] text-slate-400 dark:text-slate-400">Gudang: {{ $roll->issuedFromPop->name ?? '-' }}</div>
                                </div>
                            </div>

                            <div class="flex items-center gap-1.5">
                                @if($isLow)
                                <span class="inline-flex px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800">Sisa Kecil</span>
                                @endif

                                @if($canAdjust || $canReassign || $canTrace)
                                <button type="button"
                                        @click="$dispatch('open-custody-actions', {
                                            technicianName: @js($roll->currentTechnician->name ?? '-'),
                                            popName: @js($roll->issuedFromPop->name ?? '-'),
                                            itemName: @js($roll->item->name ?? '(barang dihapus)'),
                                            itemCode: @js($roll->item->code ?? '-'),
                                            identifier: @js('Roll: ' . $roll->roll_code . ' • Sisa: ' . rtrim(rtrim(number_format($remaining, 2, ',', '.'), '0'), ',') . ' m'),
                                            reassignUrl: @js($canReassign ? route('warehouse.reassign.roll.create', $roll) : null),
                                            reassignTitle: 'Alihkan Custody Roll Kabel',
                                            adjustUrl: @js($canAdjust ? route('warehouse.adjustments.roll.create', $roll) : null),
                                            adjustTitle: 'Lapor BAP / Rusak',
                                            traceUrl: @js($canTrace ? route('warehouse.traceability.index', ['roll' => $roll->roll_code]) : null),
                                            traceTitle: 'Lacak Riwayat Roll',
                                        })"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-600 dark:text-slate-300 text-xs font-bold cursor-pointer transition-colors border border-slate-200 dark:border-slate-600"
                                        title="Menu Aksi">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 18.75a.75.75 0 110-1.5.75.75 0 010 1.5z"/></svg>
                                </button>
                                @endif
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div>
                            <h4 class="text-xs sm:text-sm font-bold text-slate-800 dark:text-slate-200">{{ $roll->item->name ?? '(barang dihapus)' }}</h4>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-[11px] font-mono text-slate-400 dark:text-slate-400">{{ $roll->item->code ?? '-' }}</span>
                                @if($canTrace)
                                <a href="{{ route('warehouse.traceability.index', ['roll' => $roll->roll_code]) }}"
                                   class="inline-flex items-center gap-1 text-[11px] font-mono font-bold text-amber-600 dark:text-amber-400 hover:underline">
                                    <span>#{{ $roll->roll_code }}</span>
                                </a>
                                @else
                                <span class="text-[11px] font-mono font-bold text-slate-600 dark:text-slate-300">#{{ $roll->roll_code }}</span>
                                @endif
                            </div>
                            @php $rIssue = $rollIssuers->get($roll->id); @endphp
                            @if($rIssue)
                            <div class="text-[10px] text-slate-400 dark:text-slate-400 mt-1">Diinput oleh {{ $rIssue->createdBy->name ?? 'Sistem' }} · {{ $rIssue->created_at?->diffForHumans() }}</div>
                            @endif
                        </div>

                        <!-- Progress Strip -->
                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-700/40 border border-slate-200/80 dark:border-slate-700/80 space-y-2">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400 dark:text-slate-400 block">Sisa / Total</span>
                                    <div class="flex items-baseline gap-1 mt-0.5 font-mono">
                                        <span class="text-sm font-extrabold text-slate-900 dark:text-slate-100">
                                            {{ rtrim(rtrim(number_format($remaining, 2, ',', '.'), '0'), ',') }}
                                        </span>
                                        <span class="text-[11px] text-slate-400 dark:text-slate-400 font-semibold">/ {{ rtrim(rtrim(number_format((float) $roll->length_total, 2, ',', '.'), '0'), ',') }} m</span>
                                    </div>
                                </div>
                                <span class="text-xs font-mono font-bold {{ $isLow ? 'text-rose-600 dark:text-rose-400' : 'text-slate-600 dark:text-slate-300' }}">{{ $percentage }}%</span>
                            </div>
                            <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-1.5 overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-300 {{ $isLow ? 'bg-rose-500' : ($percentage < 40 ? 'bg-amber-500' : 'bg-emerald-500') }}"
                                     style="width: {{ $percentage }}%"></div>
                            </div>
                        </div>

                    </div>
                    @endforeach
                </div>
            </div>
            @endif

        </div>
    </div>

    <!-- =========================================================================
         TAB 4: RETURN DARI PELANGGAN (ADHOC-88)
         ========================================================================= -->
    @php $canReceiveReturn = auth()->user()->hasPermission('warehouse_reassign.create'); @endphp
    <div x-show="activeTab === 'returns'" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-xs overflow-hidden">
            
            <!-- Tab Header Banner -->
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50/60 dark:bg-slate-800/80">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-teal-50 dark:bg-teal-950/60 text-teal-600 dark:text-teal-400 flex items-center justify-center border border-teal-200 dark:border-teal-800 shrink-0 shadow-2xs">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/></svg>
                    </div>
                    <div>
                        <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">Return dari Pelanggan Di Tangan Teknisi</h3>
                        <p class="text-[11px] text-slate-400 dark:text-slate-400">Modem hasil pengambilan alat (putus langganan) yang sedang dibawa kembali ke gudang</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 self-end sm:self-auto">
                    @if($canReceiveReturn)
                    <a href="{{ route('warehouse.returns.index') }}" class="inline-flex items-center gap-1 text-xs font-bold text-teal-600 dark:text-teal-400 hover:text-teal-700 dark:hover:text-teal-300">
                        <span>Buka Terima Retur</span>
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                    </a>
                    @endif
                </div>
            </div>

            @if($returned->isEmpty())
            <div class="p-12 sm:p-16 text-center">
                <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-teal-50 dark:bg-teal-950/40 border border-teal-200 dark:border-teal-800 flex items-center justify-center text-teal-600 dark:text-teal-400">
                    <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Tidak ada barang return di tangan teknisi</h4>
                <p class="text-xs text-slate-400 dark:text-slate-400 mt-1 max-w-sm mx-auto">Semua modem hasil pengambilan alat dari pelanggan sudah diterima di gudang cabang.</p>
            </div>
            @else
            <!-- Desktop Table (hidden lg:block) -->
            <div class="hidden lg:block overflow-x-auto scroll-smooth">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800/90">
                        <tr>
                            <th scope="col" aria-sort="{{ $tabSortAria($returnSort, $returnDir, 'teknisi') }}" class="px-6 py-3.5 text-left text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                <a href="{{ $tabSortLink('return', 'teknisi', $returnSort, $returnDir) }}" class="inline-flex items-center gap-1 hover:text-slate-800 dark:hover:text-slate-200">Teknisi Pengambil <span class="font-mono {{ $tabSortHead($returnSort, 'teknisi') }}" aria-hidden="true">{{ $tabSortIcon($returnSort, $returnDir, 'teknisi') }}</span></a>
                            </th>
                            <th scope="col" aria-sort="{{ $tabSortAria($returnSort, $returnDir, 'item') }}" class="px-6 py-3.5 text-left text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                <a href="{{ $tabSortLink('return', 'item', $returnSort, $returnDir) }}" class="inline-flex items-center gap-1 hover:text-slate-800 dark:hover:text-slate-200">Perangkat Modem <span class="font-mono {{ $tabSortHead($returnSort, 'item') }}" aria-hidden="true">{{ $tabSortIcon($returnSort, $returnDir, 'item') }}</span></a>
                            </th>
                            <th class="px-6 py-3.5 text-left text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Serial Number</th>
                            <th scope="col" aria-sort="{{ $tabSortAria($returnSort, $returnDir, 'pelanggan') }}" class="px-6 py-3.5 text-left text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                <a href="{{ $tabSortLink('return', 'pelanggan', $returnSort, $returnDir) }}" class="inline-flex items-center gap-1 hover:text-slate-800 dark:hover:text-slate-200">Dari Pelanggan <span class="font-mono {{ $tabSortHead($returnSort, 'pelanggan') }}" aria-hidden="true">{{ $tabSortIcon($returnSort, $returnDir, 'pelanggan') }}</span></a>
                            </th>
                            <th class="px-6 py-3.5 text-left text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Waktu Ambil</th>
                            <th class="px-6 py-3.5 text-left text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Gudang Tujuan</th>
                            <th class="px-6 py-3.5 text-right text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700/60">
                        @foreach($returned as $serial)
                        @php $retrievedAt = $returnedRetrievedAt[$serial->id] ?? null; @endphp
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/40 transition-colors group">
                            <!-- Teknisi -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-teal-500 to-emerald-600 text-white font-bold text-xs flex items-center justify-center shrink-0 shadow-2xs">
                                        {{ strtoupper(substr($serial->currentTechnician->name ?? 'T', 0, 2)) }}
                                    </div>
                                    <div>
                                        <div class="text-xs font-bold text-slate-800 dark:text-slate-200 group-hover:text-teal-600 dark:group-hover:text-teal-400 transition-colors">
                                            {{ $serial->currentTechnician->name ?? '-' }}
                                        </div>
                                        <div class="text-[10px] text-slate-400 dark:text-slate-400">Teknisi Pengambil</div>
                                    </div>
                                </div>
                            </td>

                            <!-- Barang -->
                            <td class="px-6 py-4">
                                <div class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ $serial->item->name }}</div>
                                <div class="text-[11px] font-mono text-slate-400 dark:text-slate-400 mt-0.5">{{ $serial->item->code }}</div>
                            </td>

                            <!-- Serial Number -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($canTrace)
                                <a href="{{ route('warehouse.traceability.index', ['sn' => $serial->serial_number]) }}"
                                   class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-teal-50 hover:bg-teal-100 dark:bg-teal-950/50 dark:hover:bg-teal-900/60 text-teal-700 dark:text-teal-300 border border-teal-200 dark:border-teal-800 transition-colors">
                                    <span>{{ $serial->serial_number }}</span>
                                    <svg class="w-3.5 h-3.5 text-teal-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                                </a>
                                @else
                                <span class="font-mono text-xs font-bold text-slate-700 dark:text-slate-300">{{ $serial->serial_number }}</span>
                                @endif
                            </td>

                            <!-- Dari Pelanggan -->
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-700 dark:text-slate-300">
                                <div class="font-bold">{{ $serial->customer?->full_name ?? '—' }}</div>
                                @if($serial->customer?->customer_number)
                                <div class="text-[10px] font-mono text-slate-400 dark:text-slate-400">{{ $serial->customer->customer_number }}</div>
                                @endif
                            </td>

                            <!-- Tanggal Diambil -->
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-700 dark:text-slate-300">
                                <div>{{ $retrievedAt?->translatedFormat('d M Y') ?? '—' }}</div>
                                @if($retrievedAt)
                                <span class="text-[10px] text-slate-400 dark:text-slate-400 block mt-0.5">{{ $retrievedAt->diffForHumans() }}</span>
                                @endif
                            </td>

                            <!-- Gudang Tujuan -->
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-600 dark:text-slate-400">
                                <span class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-700/80 font-medium text-[11px] border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200">
                                    {{ $serial->issuedFromPop->name ?? '-' }}
                                </span>
                            </td>

                            <!-- Tombol Terima -->
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                @if($canReceiveReturn)
                                <a href="{{ route('warehouse.returns.receive.create', $serial) }}"
                                   class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-teal-600 hover:bg-teal-700 text-white rounded-xl text-xs font-bold shadow-xs transition-all active:scale-95">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                    <span>Terima Retur</span>
                                </a>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Mobile & Tablet Card Grid View (block lg:hidden) -->
            <div class="block lg:hidden p-3.5 sm:p-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                    @foreach($returned as $serial)
                    @php $retrievedAt = $returnedRetrievedAt[$serial->id] ?? null; @endphp
                    <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-xs flex flex-col justify-between space-y-3.5 hover:border-slate-300 dark:hover:border-slate-600 transition-colors">
                        
                        <!-- Card Header -->
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-teal-500 to-emerald-600 text-white font-bold text-xs flex items-center justify-center shrink-0 shadow-2xs">
                                    {{ strtoupper(substr($serial->currentTechnician->name ?? 'T', 0, 2)) }}
                                </div>
                                <div class="min-w-0">
                                    <div class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate">{{ $serial->currentTechnician->name ?? '-' }}</div>
                                    <div class="text-[10px] text-slate-400 dark:text-slate-400">Gudang tujuan: {{ $serial->issuedFromPop->name ?? '-' }}</div>
                                </div>
                            </div>

                            @if($canReceiveReturn)
                            <a href="{{ route('warehouse.returns.receive.create', $serial) }}"
                               class="inline-flex items-center gap-1 px-3 py-1.5 bg-teal-600 hover:bg-teal-700 text-white rounded-xl text-xs font-bold shadow-xs transition-all shrink-0 active:scale-95">
                                <span>Terima</span>
                            </a>
                            @endif
                        </div>

                        <!-- Card Body -->
                        <div>
                            <h4 class="text-xs sm:text-sm font-bold text-slate-800 dark:text-slate-200">{{ $serial->item->name }}</h4>
                            <div class="mt-1 font-mono text-xs font-bold text-slate-700 dark:text-slate-300">
                                SN: <span class="text-teal-600 dark:text-teal-400">{{ $serial->serial_number }}</span>
                            </div>
                        </div>

                        <!-- Customer & Retrieval Info -->
                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-700/40 border border-slate-200/80 dark:border-slate-700/80 text-xs space-y-1">
                            <div class="flex items-center justify-between text-slate-700 dark:text-slate-300">
                                <span class="text-[10px] uppercase font-bold text-slate-400 dark:text-slate-400">Dari Pelanggan:</span>
                                <span class="font-bold truncate max-w-[160px]">{{ $serial->customer?->full_name ?? '-' }}</span>
                            </div>
                            @if($retrievedAt)
                            <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 text-[11px]">
                                <span>Diambil:</span>
                                <span>{{ $retrievedAt->translatedFormat('d M Y') }} ({{ $retrievedAt->diffForHumans() }})</span>
                            </div>
                            @endif
                        </div>

                    </div>
                    @endforeach
                </div>
            </div>
            @endif

        </div>
    </div>

    {{-- Paginasi per tab (analisa-ui-ux §A1). Satu area, ikut tab aktif.
         Tiap link membawa ?tab= sendiri biar setelah reload tab yang sama
         kebuka lagi. KPI & dropdown teknisi dihitung dari agregat penuh di
         controller, jadi angka total tidak terpengaruh halaman yang dibuka. --}}
    @if($serials->hasPages() || $custodies->hasPages() || $rolls->hasPages() || $returned->hasPages())
    <div class="mt-2">
        @if($serials->hasPages())
        <div x-show="activeTab === 'serials'">{{ $serials->appends(['tab' => 'serials'])->links() }}</div>
        @endif
        @if($custodies->hasPages())
        <div x-show="activeTab === 'materials'" style="display:none;">{{ $custodies->appends(['tab' => 'materials'])->links() }}</div>
        @endif
        @if($rolls->hasPages())
        <div x-show="activeTab === 'rolls'" style="display:none;">{{ $rolls->appends(['tab' => 'rolls'])->links() }}</div>
        @endif
        @if($returned->hasPages())
        <div x-show="activeTab === 'returns'" style="display:none;">{{ $returned->appends(['tab' => 'returns'])->links() }}</div>
        @endif
    </div>
    @endif
</div>

<!-- =========================================================================
     MODAL: MOBILE & TABLET BOTTOM ACTION SHEET
     ========================================================================= -->
<div x-data="custodyActionSheet()" @open-custody-actions.window="open($event.detail)">
    <template x-teleport="body">
        <div x-show="visible" x-cloak
             x-effect="document.body.classList.toggle('overflow-hidden', visible)"
             class="fixed inset-0 z-[9999] bg-slate-900/60 backdrop-blur-xs flex items-end sm:items-center justify-center p-0 sm:p-4"
             @click.self="close()" @keydown.escape.window="close()">
            
            <div x-show="visible"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-full sm:translate-y-4 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-full sm:translate-y-4 sm:scale-95"
                 class="bg-white dark:bg-slate-900 rounded-t-3xl sm:rounded-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto shadow-2xl border border-slate-200 dark:border-slate-800 p-5 space-y-4 text-left">
                
                <!-- Drag Handle for Mobile -->
                <div class="w-12 h-1.5 bg-slate-300 dark:bg-slate-700 rounded-full mx-auto -mt-1 sm:hidden"></div>

                <!-- Header Info -->
                <div class="flex items-start justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div class="space-y-1 min-w-0">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-sky-100 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300" x-text="'Teknisi: ' + item.technicianName"></span>
                            <span class="px-2 py-0.5 rounded-md text-[10px] bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-medium" x-text="'Gudang: ' + item.popName"></span>
                        </div>
                        <h3 class="text-sm sm:text-base font-bold text-slate-900 dark:text-slate-100 leading-snug truncate" x-text="item.itemName"></h3>
                        <div class="flex items-center gap-2 text-xs font-mono text-slate-400 dark:text-slate-500">
                            <span x-text="item.itemCode"></span>
                            <template x-if="item.identifier">
                                <span>• <strong class="text-slate-800 dark:text-slate-200" x-text="item.identifier"></strong></span>
                            </template>
                        </div>
                    </div>
                    <button type="button" @click="close()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-500 dark:text-slate-400 flex items-center justify-center shrink-0 cursor-pointer">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Action Items List -->
                <div class="space-y-2">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 px-1">Pilih Aksi Kelola</span>

                    <!-- Alihkan / Kembalikan Custody -->
                    <template x-if="item.reassignUrl">
                        <a :href="item.reassignUrl"
                           class="flex items-center gap-3 p-3 rounded-xl bg-sky-50/60 hover:bg-sky-100/70 dark:bg-sky-950/30 dark:hover:bg-sky-900/40 border border-sky-100 dark:border-sky-900/50 text-sky-800 dark:text-sky-300 transition-colors group">
                            <div class="w-9 h-9 rounded-xl bg-sky-500 text-white flex items-center justify-center shrink-0 shadow-2xs group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-bold truncate" x-text="item.reassignTitle || 'Alihkan Custody'"></div>
                                <div class="text-[10px] text-sky-600/80 dark:text-sky-400/80 truncate">Pindahkan ke teknisi lain atau kembalikan ke gudang</div>
                            </div>
                            <svg class="w-4 h-4 text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </template>

                    <!-- Lapor BAP / Rusak -->
                    <template x-if="item.adjustUrl">
                        <a :href="item.adjustUrl"
                           class="flex items-center gap-3 p-3 rounded-xl bg-amber-50/60 hover:bg-amber-100/70 dark:bg-amber-950/30 dark:hover:bg-amber-900/40 border border-amber-100 dark:border-amber-900/50 text-amber-800 dark:text-amber-300 transition-colors group">
                            <div class="w-9 h-9 rounded-xl bg-amber-500 text-white flex items-center justify-center shrink-0 shadow-2xs group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-bold truncate" x-text="item.adjustTitle || 'Lapor BAP / Rusak'"></div>
                                <div class="text-[10px] text-amber-600/80 dark:text-amber-400/80 truncate">Catat kerusakan, hilang, atau selisih sisa fisik</div>
                            </div>
                            <svg class="w-4 h-4 text-amber-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </template>

                    <!-- Lacak Riwayat / Traceability -->
                    <template x-if="item.traceUrl">
                        <a :href="item.traceUrl"
                           class="flex items-center gap-3 p-3 rounded-xl bg-slate-100/80 hover:bg-slate-200/80 dark:bg-slate-800/80 dark:hover:bg-slate-700/80 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 transition-colors group">
                            <div class="w-9 h-9 rounded-xl bg-slate-700 text-white flex items-center justify-center shrink-0 shadow-2xs group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-bold truncate" x-text="item.traceTitle || 'Lacak Riwayat Barang'"></div>
                                <div class="text-[10px] text-slate-500 dark:text-slate-400 truncate">Lihat timeline mutasi, penyerahan & status</div>
                            </div>
                            <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </template>
                </div>

                <!-- Close Button -->
                <div class="pt-2">
                    <button type="button" @click="close()"
                            class="w-full py-3 px-4 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl transition-colors text-center cursor-pointer">
                        Tutup Menu
                    </button>
                </div>
            </div>
        </div>
    </template>
    @endif
</div>

@push('scripts')
<script>
if (typeof window.calcDropdownPos !== 'function') {
    window.calcDropdownPos = function(el, menuWidth = 224) {
        const r = el.getBoundingClientRect();
        const spaceBelow = window.innerHeight - r.bottom;
        const spaceAbove = r.top;
        const openUp = spaceBelow < 180 && spaceAbove > spaceBelow;
        
        let left = r.right - menuWidth;
        if (left < 8) left = 8;
        if (left + menuWidth > window.innerWidth - 8) {
            left = Math.max(8, window.innerWidth - menuWidth - 8);
        }
        
        return {
            openUp: openUp,
            top: openUp ? null : Math.round(r.bottom + 4),
            bottom: openUp ? Math.round(window.innerHeight - r.top + 4) : null,
            left: Math.round(left)
        };
    };
}

function custodyActionSheet() {
    return {
        visible: false,
        item: {},
        open(detail) {
            this.item = detail;
            this.visible = true;
        },
        close() {
            this.visible = false;
        }
    };
}
</script>
@endpush

@endsection
