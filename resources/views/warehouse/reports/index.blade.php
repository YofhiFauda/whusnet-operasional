@extends('layouts.app')

@section('title', 'Laporan Gudang - Whusnet Operasional')
@section('page_title', 'Laporan Gudang')

@section('content')

<x-warehouse.header
    active="reports"
    title="Laporan Gudang"
    subtitle="Agregat pergerakan barang & kerugian per periode — data mentahnya tercatat di ledger, disusun ringkas untuk kemudahan audit & evaluasi stok."
/>

@php
    $hasActiveFilters = (bool) ($popFilter || ($period !== now()->format('Y-m')));
    $selectedPopModel = $pops->firstWhere('id', $popFilter);
    $totalMovementItems = collect($movementRows)->sum(fn($r) => count($r['items']));
    $totalAdjustmentCount = count($adjustmentRows);
@endphp

<div x-data="{
    activeTab: 'movement',
    showMobileChartHint: true,
    monthPickerOpen: false,
    goToPrevMonth() {
        const parts = '{{ $period }}'.split('-');
        let year = parseInt(parts[0], 10);
        let month = parseInt(parts[1], 10) - 1;
        if (month < 1) { month = 12; year--; }
        const formatted = year + '-' + String(month).padStart(2, '0');
        document.getElementById('period_input').value = formatted;
        document.getElementById('reportFilterForm').submit();
    },
    goToNextMonth() {
        const parts = '{{ $period }}'.split('-');
        let year = parseInt(parts[0], 10);
        let month = parseInt(parts[1], 10) + 1;
        if (month > 12) { month = 1; year++; }
        const formatted = year + '-' + String(month).padStart(2, '0');
        document.getElementById('period_input').value = formatted;
        document.getElementById('reportFilterForm').submit();
    }
}" class="space-y-4 sm:space-y-5">

    <!-- ================= 1. NAKED CONTROL & FILTER BAR (Mobile & Tablet First) ================= -->
    <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-xl p-3.5 sm:p-4 shadow-xs">
        <form id="reportFilterForm" action="{{ route('warehouse.reports.index') }}" method="GET" class="space-y-3 sm:space-y-0 sm:flex sm:flex-wrap sm:items-end sm:gap-3">
            
            <!-- Periode Picker with Quick Prev/Next Navigation -->
            <div class="w-full sm:w-auto">
                <label for="period_input" class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                    Periode (Bulan)
                </label>
                <div class="flex items-center gap-1">
                    <button type="button" @click="goToPrevMonth()"
                            class="h-9 w-9 shrink-0 flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/60 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                            title="Bulan Sebelumnya">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    
                    <div class="relative flex-1 sm:w-44">
                        <input type="month" name="period" id="period_input" value="{{ $period }}"
                               class="w-full h-9 px-3 py-1.5 text-xs font-semibold border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/80 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                    </div>

                    <button type="button" @click="goToNextMonth()"
                            class="h-9 w-9 shrink-0 flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/60 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                            title="Bulan Berikutnya">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>

            <!-- Gudang POP Selector -->
            <div class="w-full sm:flex-1 sm:min-w-[200px] lg:max-w-xs">
                <label for="pop_id" class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                    Gudang / Cabang
                </label>
                <div class="relative">
                    <select name="pop_id" id="pop_id"
                            class="w-full h-9 px-3 py-1.5 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/80 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                        <option value="">— Semua Gudang Terjangkau —</option>
                        @foreach($pops as $pop)
                        <option value="{{ $pop->id }}" {{ (string) $popFilter === (string) $pop->id ? 'selected' : '' }}>
                            {{ $pop->name }} ({{ strtoupper($pop->type?->value ?? $pop->type) }})
                        </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Action Buttons Group (Responsive Grid on Mobile) -->
            <div class="grid grid-cols-2 sm:flex sm:items-center gap-2 pt-1 sm:pt-0 w-full sm:w-auto">
                <button type="submit"
                        class="h-9 inline-flex items-center justify-center gap-1.5 px-4 bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 text-white text-xs font-semibold rounded-lg shadow-xs transition-colors cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 12.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-8.586L2.293 6.707A1 1 0 012 6V4z"/></svg>
                    <span>Terapkan</span>
                </button>

                <a href="{{ route('warehouse.reports.export', ['period' => $period, 'pop_id' => $popFilter]) }}"
                   class="h-9 inline-flex items-center justify-center gap-1.5 px-3.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg shadow-xs transition-colors cursor-pointer truncate">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span class="truncate">Export Excel</span>
                </a>
            </div>

            @if($hasActiveFilters)
            <div class="sm:ml-auto flex items-center gap-1.5 pt-1 sm:pt-0">
                <a href="{{ route('warehouse.reports.index') }}"
                   class="text-xs text-rose-500 hover:text-rose-600 dark:text-rose-400 font-medium hover:underline inline-flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    <span>Reset Filter</span>
                </a>
            </div>
            @endif
        </form>

        <!-- Segmented Tab Switcher (Full Width Pill Design) -->
        <div class="mt-3.5 pt-3 border-t border-slate-100 dark:border-slate-700/60">
            <div class="grid grid-cols-2 sm:w-fit sm:flex items-center gap-1.5 bg-slate-100/80 dark:bg-slate-900/80 p-1 rounded-xl">
                <button @click="activeTab = 'movement'" type="button"
                        :class="activeTab === 'movement' ? 'bg-white dark:bg-slate-800 text-sky-600 dark:text-sky-400 shadow-xs font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 font-medium'"
                        class="inline-flex items-center justify-center gap-2 px-3.5 py-2 rounded-lg text-xs transition-all cursor-pointer">
                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                    <span class="truncate">Pergerakan Barang</span>
                    <span class="hidden xs:inline-flex px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold"
                          :class="activeTab === 'movement' ? 'bg-sky-100 dark:bg-sky-950/80 text-sky-700 dark:text-sky-300' : 'bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400'">
                        {{ count($movementRows) }} POP
                    </span>
                </button>

                <button @click="activeTab = 'adjustment'" type="button"
                        :class="activeTab === 'adjustment' ? 'bg-white dark:bg-slate-800 text-rose-600 dark:text-rose-400 shadow-xs font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 font-medium'"
                        class="inline-flex items-center justify-center gap-2 px-3.5 py-2 rounded-lg text-xs transition-all cursor-pointer">
                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <span class="truncate">Kerugian & Selisih</span>
                    @if($kpi['adjustment_count'] > 0)
                    <span class="inline-flex px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold bg-rose-100 dark:bg-rose-950/80 text-rose-700 dark:text-rose-300">
                        {{ $kpi['adjustment_count'] }}
                    </span>
                    @endif
                </button>
            </div>
        </div>
    </div>

    <!-- ================= 2. RESPONSIVE KPI METRIC STRIP (Tablet & Mobile Optimized) ================= -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2.5 sm:gap-3.5">
        
        <!-- Card 1: Barang Masuk -->
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-xl p-3 sm:p-3.5 shadow-xs flex flex-col justify-between">
            <div class="flex items-center justify-between gap-1 mb-1">
                <span class="text-[10px] sm:text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider truncate">
                    Barang Masuk
                </span>
                <div class="w-5 h-5 rounded-md bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                </div>
            </div>
            <div class="mt-1 flex items-baseline gap-1.5">
                <span class="text-xl sm:text-2xl font-extrabold font-mono text-emerald-600 dark:text-emerald-400 tabular-nums">
                    {{ $kpi['receive_count'] }}
                </span>
                <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">trx</span>
            </div>
        </div>

        <!-- Card 2: Transfer Keluar -->
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-xl p-3 sm:p-3.5 shadow-xs flex flex-col justify-between">
            <div class="flex items-center justify-between gap-1 mb-1">
                <span class="text-[10px] sm:text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider truncate">
                    Transfer Keluar
                </span>
                <div class="w-5 h-5 rounded-md bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                </div>
            </div>
            <div class="mt-1 flex items-baseline gap-1.5">
                <span class="text-xl sm:text-2xl font-extrabold font-mono text-sky-600 dark:text-sky-400 tabular-nums">
                    {{ $kpi['transfer_out_count'] }}
                </span>
                <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">trx</span>
            </div>
        </div>

        <!-- Card 3: Keluar ke Teknisi -->
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-xl p-3 sm:p-3.5 shadow-xs flex flex-col justify-between">
            <div class="flex items-center justify-between gap-1 mb-1">
                <span class="text-[10px] sm:text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider truncate">
                    Keluar Teknisi
                </span>
                <div class="w-5 h-5 rounded-md bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </div>
            </div>
            <div class="mt-1 flex items-baseline gap-1.5">
                <span class="text-xl sm:text-2xl font-extrabold font-mono text-indigo-600 dark:text-indigo-400 tabular-nums">
                    {{ $kpi['issue_count'] }}
                </span>
                <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">trx</span>
            </div>
        </div>

        <!-- Card 4: Total Kerugian -->
        <div class="bg-white dark:bg-slate-800/90 border {{ $kpi['adjustment_count'] > 0 ? 'border-amber-300/80 dark:border-amber-700/80 bg-amber-50/10' : 'border-slate-200/80 dark:border-slate-700/80' }} rounded-xl p-3 sm:p-3.5 shadow-xs flex flex-col justify-between">
            <div class="flex items-center justify-between gap-1 mb-1">
                <span class="text-[10px] sm:text-[11px] font-bold {{ $kpi['adjustment_count'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400 dark:text-slate-500' }} uppercase tracking-wider truncate">
                    Total Kejadian Rugi
                </span>
                <div class="w-5 h-5 rounded-md {{ $kpi['adjustment_count'] > 0 ? 'bg-amber-100 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400' : 'bg-slate-100 dark:bg-slate-700 text-slate-500' }} flex items-center justify-center shrink-0">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
            </div>
            <div class="mt-1 flex items-baseline gap-1.5">
                <span class="text-xl sm:text-2xl font-extrabold font-mono {{ $kpi['adjustment_count'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-800 dark:text-slate-100' }} tabular-nums">
                    {{ $kpi['adjustment_count'] }}
                </span>
                <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">kejadian</span>
            </div>
        </div>

        <!-- Card 5: Nilai Kerugian Finansial -->
        <div class="col-span-2 sm:col-span-1 bg-white dark:bg-slate-800/90 border {{ $kpi['total_loss_value'] > 0 ? 'border-rose-300 dark:border-rose-800 bg-rose-50/20 dark:bg-rose-950/20' : 'border-slate-200/80 dark:border-slate-700/80' }} rounded-xl p-3 sm:p-3.5 shadow-xs flex flex-col justify-between">
            <div class="flex items-center justify-between gap-1 mb-1">
                <span class="text-[10px] sm:text-[11px] font-bold {{ $kpi['total_loss_value'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-400 dark:text-slate-500' }} uppercase tracking-wider truncate">
                    Estimasi Nilai Rugi
                </span>
                <div class="w-5 h-5 rounded-md {{ $kpi['total_loss_value'] > 0 ? 'bg-rose-100 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400' : 'bg-slate-100 dark:bg-slate-700 text-slate-500' }} flex items-center justify-center shrink-0">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <div class="mt-1">
                <span class="text-base sm:text-lg font-extrabold font-mono {{ $kpi['total_loss_value'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-800 dark:text-slate-100' }} tabular-nums">
                    Rp {{ number_format($kpi['total_loss_value'], 0, ',', '.') }}
                </span>
                <span class="block text-[9px] text-slate-400 dark:text-slate-500 mt-0.5">Rusak & Hilang</span>
            </div>
        </div>

    </div>

    <!-- =========================================================================
         TAB 1: PERGERAKAN BARANG
         ========================================================================= -->
    <div x-show="activeTab === 'movement'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" class="space-y-4">
        
        <!-- Chart Pergerakan Transaksi -->
        @if(!empty($movementCounts))
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-xl p-4 sm:p-5 shadow-xs">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1.5 mb-3">
                <div>
                    <h3 class="text-xs sm:text-sm font-bold text-slate-800 dark:text-slate-100 uppercase tracking-wider flex items-center gap-2">
                        <span>Jumlah Transaksi per Gudang</span>
                    </h3>
                    <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">
                        Batang mengukur frekuensi transaksi (satuan kuantitas barang bervariasi per baris).
                    </p>
                </div>
                <!-- Touch Hint for Mobile -->
                <div class="inline-flex items-center gap-1 text-[10px] font-semibold text-sky-600 dark:text-sky-400 sm:hidden">
                    <span>Geser grafik</span>
                    <svg class="w-3 h-3 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </div>
            </div>
            
            <div id="movementChartLegend" class="flex flex-wrap gap-2.5 sm:gap-4 mb-3 text-[11px] font-semibold"></div>
            <div class="overflow-x-auto scroll-smooth pb-2 custom-scrollbar">
                <svg id="movementChart" role="img" aria-label="Grafik jumlah transaksi pergerakan barang per gudang"></svg>
            </div>
        </div>
        @endif

        <!-- Data Pergerakan Barang: Desktop Table + Mobile/Tablet Cards -->
        @if(empty($movementRows))
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-xl p-12 text-center shadow-xs">
            <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-slate-100 dark:bg-slate-700/50 flex items-center justify-center text-slate-400">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
            </div>
            <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Tidak ada pergerakan barang pada periode ini</h4>
            <p class="text-xs text-slate-400 mt-1">Coba ganti filter bulan atau pilih gudang cabang lain di atas.</p>
        </div>
        @else
        
        <!-- DESKTOP TABLE VIEW (lg+) -->
        <div class="hidden lg:block bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-xl overflow-hidden shadow-xs">
            <div class="overflow-x-auto scroll-smooth">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/80 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider text-[10px]">
                        <tr>
                            <th class="px-4 py-3.5 text-left">Gudang / Cabang</th>
                            <th class="px-4 py-3.5 text-left">Nama Barang</th>
                            <th class="px-4 py-3.5 text-right text-amber-700 dark:text-amber-400 bg-amber-50/60 dark:bg-amber-950/20">Stok Awal</th>
                            <th class="px-4 py-3.5 text-right text-emerald-700 dark:text-emerald-400 bg-emerald-50/40 dark:bg-emerald-950/10">Masuk</th>
                            <th class="px-4 py-3.5 text-right text-sky-700 dark:text-sky-400 bg-sky-50/40 dark:bg-sky-950/10">Trf Masuk</th>
                            <th class="px-4 py-3.5 text-right text-slate-600 dark:text-slate-400">Trf Keluar</th>
                            <th class="px-4 py-3.5 text-right text-indigo-700 dark:text-indigo-400 bg-indigo-50/40 dark:bg-indigo-950/10">Teknisi</th>
                            <th class="px-4 py-3.5 text-right text-purple-700 dark:text-purple-400 bg-purple-50/60 dark:bg-purple-950/20">Stok Akhir</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700/50">
                        @foreach($movementRows as $row)
                        @php $itemCount = count($row['items']); @endphp
                        @foreach($row['items'] as $i => $item)
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/30 transition-colors">
                            @if($i === 0)
                            <td class="px-4 py-3 text-xs font-bold text-slate-800 dark:text-slate-100 align-top border-r border-slate-100 dark:border-slate-700/50 bg-slate-50/30 dark:bg-slate-900/20" rowspan="{{ $itemCount }}">
                                <div class="sticky top-4">
                                    <span class="block text-sm font-bold text-slate-900 dark:text-slate-100">{{ $row['pop']->name }}</span>
                                    <span class="inline-flex mt-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-200/80 dark:bg-slate-700 text-slate-600 dark:text-slate-300 uppercase">
                                        {{ $row['pop']->type?->value ?? $row['pop']->type }}
                                    </span>
                                </div>
                            </td>
                            @endif
                            <td class="px-4 py-3 text-xs text-slate-800 dark:text-slate-200 font-semibold">
                                {{ $item['item_name'] }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-xs bg-amber-50/30 dark:bg-amber-950/10">
                                @if(($item['stok_awal_qty'] ?? 0) > 0)
                                    <span class="font-bold text-amber-700 dark:text-amber-400 tabular-nums">{{ rtrim(rtrim(number_format($item['stok_awal_qty'], 2, ',', '.'), '0'), ',') }} {{ $item['unit'] }}</span>
                                    @if(($item['stok_awal_nilai'] ?? 0) > 0)
                                        <span class="block text-[10px] text-slate-400 font-normal">Rp {{ number_format($item['stok_awal_nilai'], 0, ',', '.') }}</span>
                                    @endif
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-xs text-emerald-600 dark:text-emerald-400 font-semibold bg-emerald-50/20 dark:bg-emerald-950/5">
                                {{ $item['receive'] > 0 ? rtrim(rtrim(number_format($item['receive'], 2, ',', '.'), '0'), ',').' '.$item['unit'] : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-xs text-sky-600 dark:text-sky-400 font-semibold bg-sky-50/20 dark:bg-sky-950/5">
                                {{ $item['transfer_in'] > 0 ? rtrim(rtrim(number_format($item['transfer_in'], 2, ',', '.'), '0'), ',').' '.$item['unit'] : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-xs text-slate-500 dark:text-slate-400 font-medium">
                                {{ $item['transfer_out'] > 0 ? rtrim(rtrim(number_format($item['transfer_out'], 2, ',', '.'), '0'), ',').' '.$item['unit'] : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-xs text-indigo-600 dark:text-indigo-400 font-semibold bg-indigo-50/20 dark:bg-indigo-950/5">
                                {{ $item['issue'] > 0 ? rtrim(rtrim(number_format($item['issue'], 2, ',', '.'), '0'), ',').' '.$item['unit'] : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-xs bg-purple-50/30 dark:bg-purple-950/10">
                                @if(($item['stok_akhir_qty'] ?? 0) > 0)
                                    <span class="font-bold text-purple-700 dark:text-purple-400 tabular-nums">{{ rtrim(rtrim(number_format($item['stok_akhir_qty'], 2, ',', '.'), '0'), ',') }} {{ $item['unit'] }}</span>
                                    @if(($item['stok_akhir_nilai'] ?? 0) > 0)
                                        <span class="block text-[10px] text-slate-400 font-normal">Rp {{ number_format($item['stok_akhir_nilai'], 0, ',', '.') }}</span>
                                    @endif
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- MOBILE & TABLET CARD DECK VIEW (<lg) -->
        <div class="block lg:hidden space-y-4">
            @foreach($movementRows as $row)
            <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-xl overflow-hidden shadow-xs">
                
                <!-- Warehouse Header Strip -->
                <div class="px-4 py-3 bg-slate-50 dark:bg-slate-900/60 border-b border-slate-200/80 dark:border-slate-700/80 flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg bg-sky-100 dark:bg-sky-950/80 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ $row['pop']->name }}</h3>
                            <span class="text-[10px] font-semibold text-slate-400 uppercase">{{ $row['pop']->type?->value ?? $row['pop']->type }}</span>
                        </div>
                    </div>
                    <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-slate-200/80 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                        {{ count($row['items']) }} Item
                    </span>
                </div>

                <!-- Items Grid per Warehouse (Tablet: 2-col, Mobile: 1-col) -->
                <div class="p-3 sm:p-4 grid grid-cols-1 md:grid-cols-2 gap-3">
                    @foreach($row['items'] as $item)
                    <div class="bg-slate-50/50 dark:bg-slate-900/40 border border-slate-200/70 dark:border-slate-700/60 rounded-lg p-3.5 space-y-3">
                        
                        <!-- Item Title -->
                        <div class="flex items-start justify-between gap-2">
                            <h4 class="text-xs font-bold text-slate-900 dark:text-slate-100">{{ $item['item_name'] }}</h4>
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-200/70 dark:bg-slate-700 text-slate-600 dark:text-slate-300 shrink-0 uppercase">
                                {{ $item['unit'] }}
                            </span>
                        </div>

                        <!-- 3-Stage Lifecycle Grid (Awal -> Mutasi -> Akhir) -->
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            
                            <!-- Stok Awal Box -->
                            <div class="p-2.5 rounded-lg bg-amber-50/60 dark:bg-amber-950/20 border border-amber-200/60 dark:border-amber-900/40">
                                <span class="block text-[9px] font-bold text-amber-700 dark:text-amber-400 uppercase tracking-wider">Stok Awal</span>
                                <div class="mt-0.5 font-mono font-bold text-amber-800 dark:text-amber-300 text-xs">
                                    @if(($item['stok_awal_qty'] ?? 0) > 0)
                                        {{ rtrim(rtrim(number_format($item['stok_awal_qty'], 2, ',', '.'), '0'), ',') }}
                                        <span class="text-[10px] font-normal">{{ $item['unit'] }}</span>
                                        @if(($item['stok_awal_nilai'] ?? 0) > 0)
                                            <span class="block text-[9px] text-amber-600/80 dark:text-amber-400/80 font-normal">Rp {{ number_format($item['stok_awal_nilai'], 0, ',', '.') }}</span>
                                        @endif
                                    @else
                                        <span class="text-slate-400 font-normal">—</span>
                                    @endif
                                </div>
                            </div>

                            <!-- Stok Akhir Box -->
                            <div class="p-2.5 rounded-lg bg-purple-50/60 dark:bg-purple-950/20 border border-purple-200/60 dark:border-purple-900/40">
                                <span class="block text-[9px] font-bold text-purple-700 dark:text-purple-400 uppercase tracking-wider">Stok Akhir</span>
                                <div class="mt-0.5 font-mono font-bold text-purple-800 dark:text-purple-300 text-xs">
                                    @if(($item['stok_akhir_qty'] ?? 0) > 0)
                                        {{ rtrim(rtrim(number_format($item['stok_akhir_qty'], 2, ',', '.'), '0'), ',') }}
                                        <span class="text-[10px] font-normal">{{ $item['unit'] }}</span>
                                        @if(($item['stok_akhir_nilai'] ?? 0) > 0)
                                            <span class="block text-[9px] text-purple-600/80 dark:text-purple-400/80 font-normal">Rp {{ number_format($item['stok_akhir_nilai'], 0, ',', '.') }}</span>
                                        @endif
                                    @else
                                        <span class="text-slate-400 font-normal">—</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Mutasi Periode Flow Chips (2x2 Grid) -->
                        <div class="pt-1">
                            <span class="block text-[9px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-1.5">Aktivitas Periode</span>
                            <div class="grid grid-cols-2 gap-1.5 text-[11px] font-mono">
                                
                                <!-- Masuk -->
                                <div class="flex items-center justify-between px-2 py-1 rounded bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700/60">
                                    <span class="text-slate-500 dark:text-slate-400 text-[10px] font-sans">📥 Masuk</span>
                                    <span class="{{ $item['receive'] > 0 ? 'font-bold text-emerald-600 dark:text-emerald-400' : 'text-slate-400' }}">
                                        {{ $item['receive'] > 0 ? '+'.rtrim(rtrim(number_format($item['receive'], 2, ',', '.'), '0'), ',') : '0' }}
                                    </span>
                                </div>

                                <!-- Transfer Masuk -->
                                <div class="flex items-center justify-between px-2 py-1 rounded bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700/60">
                                    <span class="text-slate-500 dark:text-slate-400 text-[10px] font-sans">↗️ Trf Masuk</span>
                                    <span class="{{ $item['transfer_in'] > 0 ? 'font-bold text-sky-600 dark:text-sky-400' : 'text-slate-400' }}">
                                        {{ $item['transfer_in'] > 0 ? '+'.rtrim(rtrim(number_format($item['transfer_in'], 2, ',', '.'), '0'), ',') : '0' }}
                                    </span>
                                </div>

                                <!-- Transfer Keluar -->
                                <div class="flex items-center justify-between px-2 py-1 rounded bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700/60">
                                    <span class="text-slate-500 dark:text-slate-400 text-[10px] font-sans">↘️ Trf Keluar</span>
                                    <span class="{{ $item['transfer_out'] > 0 ? 'font-bold text-slate-700 dark:text-slate-300' : 'text-slate-400' }}">
                                        {{ $item['transfer_out'] > 0 ? '-'.rtrim(rtrim(number_format($item['transfer_out'], 2, ',', '.'), '0'), ',') : '0' }}
                                    </span>
                                </div>

                                <!-- Teknisi -->
                                <div class="flex items-center justify-between px-2 py-1 rounded bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700/60">
                                    <span class="text-slate-500 dark:text-slate-400 text-[10px] font-sans">👷 Teknisi</span>
                                    <span class="{{ $item['issue'] > 0 ? 'font-bold text-indigo-600 dark:text-indigo-400' : 'text-slate-400' }}">
                                        {{ $item['issue'] > 0 ? '-'.rtrim(rtrim(number_format($item['issue'], 2, ',', '.'), '0'), ',') : '0' }}
                                    </span>
                                </div>

                            </div>
                        </div>

                    </div>
                    @endforeach
                </div>

            </div>
            @endforeach
        </div>
        @endif

    </div>

    <!-- =========================================================================
         TAB 2: KERUGIAN & PENYESUAIAN
         ========================================================================= -->
    <div x-show="activeTab === 'adjustment'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;" class="space-y-4">
        
        <!-- Chart Kerugian -->
        @if(!empty($lossChartData))
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-xl p-4 sm:p-5 shadow-xs">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1.5 mb-3">
                <div>
                    <h3 class="text-xs sm:text-sm font-bold text-slate-800 dark:text-slate-100 uppercase tracking-wider flex items-center gap-2">
                        <span>Jumlah Kejadian Kerugian per Lokasi</span>
                    </h3>
                    <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">
                        Batang mengukur frekuensi kejadian insiden kerugian.
                    </p>
                </div>
                <!-- Touch Hint for Mobile -->
                <div class="inline-flex items-center gap-1 text-[10px] font-semibold text-sky-600 dark:text-sky-400 sm:hidden">
                    <span>Geser grafik</span>
                    <svg class="w-3 h-3 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </div>
            </div>

            <div id="lossChartLegend" class="flex flex-wrap gap-2.5 sm:gap-4 mb-3 text-[11px] font-semibold"></div>
            <div class="overflow-x-auto scroll-smooth pb-2 custom-scrollbar">
                <svg id="lossChart" role="img" aria-label="Grafik jumlah kejadian kerugian per lokasi"></svg>
            </div>
        </div>
        @endif

        <!-- Data Kerugian: Desktop Table + Mobile/Tablet Cards -->
        @if(empty($adjustmentRows))
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-xl p-12 text-center shadow-xs">
            <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-slate-100 dark:bg-slate-700/50 flex items-center justify-center text-slate-400">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Tidak ada kerugian tercatat pada periode ini</h4>
            <p class="text-xs text-slate-400 mt-1">Stok aman & tidak terdapat insiden rusak atau hilang yang dilaporkan.</p>
        </div>
        @else

        <!-- DESKTOP TABLE VIEW (lg+) -->
        <div class="hidden lg:block bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-xl overflow-hidden shadow-xs">
            <div class="overflow-x-auto scroll-smooth">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/80 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider text-[10px]">
                        <tr>
                            <th class="px-5 py-3.5 text-left">Kategori Kerugian</th>
                            <th class="px-5 py-3.5 text-left">Gudang / Sumber</th>
                            <th class="px-5 py-3.5 text-left">Barang</th>
                            <th class="px-5 py-3.5 text-right">Jumlah Trx</th>
                            <th class="px-5 py-3.5 text-right">Total Qty</th>
                            <th class="px-5 py-3.5 text-right">Estimasi Nilai Rugi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700/50">
                        @foreach($adjustmentRows as $row)
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/30 transition-colors">
                            <td class="px-5 py-3.5 text-xs font-bold text-slate-900 dark:text-slate-100">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span>{{ $row['reason_label'] }}</span>
                                    @if($row['self_reported'] ?? false)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950/80 dark:text-amber-300" title="Setidaknya satu transaksi dilaporkan oleh pemegang barang itu sendiri">
                                        Dilaporkan sendiri
                                    </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-xs text-slate-600 dark:text-slate-300 font-medium">
                                {{ $row['pop_label'] }}
                            </td>
                            <td class="px-5 py-3.5 text-xs text-slate-800 dark:text-slate-200 font-semibold">
                                {{ $row['item_name'] }}
                            </td>
                            <td class="px-5 py-3.5 text-right font-mono text-xs text-slate-700 dark:text-slate-300 font-semibold tabular-nums">
                                {{ $row['count'] }}
                            </td>
                            <td class="px-5 py-3.5 text-right font-mono text-xs text-rose-600 dark:text-rose-400 font-bold tabular-nums">
                                {{ rtrim(rtrim(number_format($row['total_qty'], 2, ',', '.'), '0'), ',') }} {{ $row['unit'] }}
                            </td>
                            <td class="px-5 py-3.5 text-right font-mono text-xs text-rose-600 dark:text-rose-400 font-extrabold tabular-nums">
                                {{ $row['loss_value'] !== null ? 'Rp '.number_format($row['loss_value'], 0, ',', '.') : '—' }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            <div class="px-5 py-3 bg-slate-50/50 dark:bg-slate-900/30 border-t border-slate-100 dark:border-slate-700/60 text-[11px] text-slate-400 dark:text-slate-500 flex items-start gap-2">
                <svg class="w-4 h-4 text-slate-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span><strong>Catatan:</strong> "— (Custody Teknisi)" menandakan kerugian terjadi saat barang di tangan teknisi (bukan di gudang fisik cabang). Nilai Rugi dihitung khusus untuk kategori Rusak & Hilang berdasarkan harga unit RECEIVE terakhir.</span>
            </div>
        </div>

        <!-- MOBILE & TABLET CARD DECK VIEW (<lg) -->
        <div class="block lg:hidden space-y-3">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach($adjustmentRows as $row)
                <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-xl p-3.5 space-y-3 shadow-xs">
                    
                    <!-- Top Category & Location -->
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <span class="inline-flex px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider
                                {{ in_array($row['reason'], ['lost', 'damaged']) ? 'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-200/60 dark:border-rose-800/60' : 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200/60 dark:border-amber-800/60' }}">
                                {{ $row['reason_label'] }}
                            </span>
                            @if($row['self_reported'] ?? false)
                            <span class="block mt-1 text-[10px] font-semibold text-amber-600 dark:text-amber-400">
                                ⚠️ Dilaporkan sendiri
                            </span>
                            @endif
                        </div>
                        <span class="text-right text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                            {{ $row['pop_label'] }}
                        </span>
                    </div>

                    <!-- Item Name -->
                    <div>
                        <h4 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-slate-100">
                            {{ $item['item_name'] ?? $row['item_name'] }}
                        </h4>
                    </div>

                    <!-- Metric 3-Column Strip -->
                    <div class="grid grid-cols-3 gap-2 p-2.5 rounded-lg bg-slate-50 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-700/60 text-xs font-mono">
                        <div>
                            <span class="block text-[9px] font-sans text-slate-400 uppercase font-bold">Transaksi</span>
                            <span class="font-bold text-slate-700 dark:text-slate-300">{{ $row['count'] }}x</span>
                        </div>
                        <div>
                            <span class="block text-[9px] font-sans text-slate-400 uppercase font-bold">Qty Hilang/Rsk</span>
                            <span class="font-bold text-rose-600 dark:text-rose-400">
                                {{ rtrim(rtrim(number_format($row['total_qty'], 2, ',', '.'), '0'), ',') }} <span class="text-[10px] font-normal">{{ $row['unit'] }}</span>
                            </span>
                        </div>
                        <div>
                            <span class="block text-[9px] font-sans text-slate-400 uppercase font-bold">Nilai Rugi</span>
                            <span class="font-extrabold text-rose-600 dark:text-rose-400 truncate block">
                                {{ $row['loss_value'] !== null ? 'Rp '.number_format($row['loss_value'], 0, ',', '.') : '—' }}
                            </span>
                        </div>
                    </div>

                </div>
                @endforeach
            </div>

            <!-- Mobile Footer Note -->
            <div class="p-3 bg-white dark:bg-slate-800/80 border border-slate-200/70 dark:border-slate-700/60 rounded-xl text-[11px] text-slate-400 dark:text-slate-500 flex items-start gap-2">
                <svg class="w-4 h-4 text-slate-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span><strong>Keterangan:</strong> Nilai Kerugian dihitung berdasarkan harga beli terakhir dari transaksi RECEIVE untuk kategori Rusak & Hilang.</span>
            </div>
        </div>

        @endif

    </div>

</div>

@push('scripts')
<script>
(function(){
    "use strict";

    // Palet kategorikal tervalidasi
    var PALETTE_LIGHT = ["#2a78d6", "#eb6834", "#1baf7a", "#eda100", "#e87ba4"];
    var PALETTE_DARK = ["#3987e5", "#d95926", "#199e70", "#c98500", "#d55181"];
    function isDark(){
        var stamp = document.documentElement.getAttribute("data-theme");
        if(stamp === "dark") return true;
        if(stamp === "light") return false;
        return window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches;
    }
    var palette = isDark() ? PALETTE_DARK : PALETTE_LIGHT;

    var ink = isDark() ? "#cbd5e1" : "#475569";
    var grid = isDark() ? "#334155" : "#e2e8f0";
    var axis = isDark() ? "#475569" : "#cbd5e1";

    function drawGroupedBar(svgEl, legendEl, rows, catKey, series){
        if(!svgEl || rows.length === 0) return;

        var ns = "http://www.w3.org/2000/svg";
        function make(tag, attrs){
            var e = document.createElementNS(ns, tag);
            Object.keys(attrs).forEach(function(k){ e.setAttribute(k, attrs[k]); });
            return e;
        }

        legendEl.innerHTML = "";
        series.forEach(function(s, i){
            var item = document.createElement("span");
            item.style.display = "inline-flex"; item.style.alignItems = "center"; item.style.gap = "5px"; item.style.color = ink;
            var dot = document.createElement("span");
            dot.style.width = "8px"; dot.style.height = "8px"; dot.style.borderRadius = "2px"; dot.style.background = palette[i % palette.length];
            item.appendChild(dot);
            item.appendChild(document.createTextNode(s.label));
            legendEl.appendChild(item);
        });

        var W = Math.max(560, rows.length * 130 + 80);
        var H = 240, padL = 34, padR = 12, padT = 14, padB = 42;
        var plotW = W - padL - padR, plotH = H - padT - padB;

        var maxVal = 1;
        rows.forEach(function(r){ series.forEach(function(s){ maxVal = Math.max(maxVal, r[s.key] || 0); }); });
        var niceMax = Math.ceil(maxVal / 5) * 5 || 5;

        svgEl.setAttribute("viewBox", "0 0 " + W + " " + H);
        svgEl.setAttribute("width", W); svgEl.setAttribute("height", H);
        svgEl.innerHTML = "";

        var ticks = 4;
        for(var t=0; t<=ticks; t++){
            var val = Math.round(niceMax * t / ticks);
            var y = padT + plotH - (val / niceMax) * plotH;
            svgEl.appendChild(make("line", {x1:padL, x2:W-padR, y1:y, y2:y, stroke:grid, "stroke-width":1}));
            var lbl = make("text", {x:padL-6, y:y+4, "text-anchor":"end", "font-size":10, fill:ink});
            lbl.textContent = val;
            svgEl.appendChild(lbl);
        }
        svgEl.appendChild(make("line", {x1:padL, x2:W-padR, y1:padT+plotH, y2:padT+plotH, stroke:axis, "stroke-width":1.5}));

        var groupW = plotW / rows.length;
        var barGap = 3;
        var barW = Math.min(26, (groupW - 14) / series.length - barGap);

        rows.forEach(function(row, ri){
            var groupX = padL + ri * groupW;
            series.forEach(function(s, si){
                var val = row[s.key] || 0;
                var barH = (val / niceMax) * plotH;
                var x = groupX + 7 + si * (barW + barGap);
                var y = padT + plotH - barH;
                var rect = make("rect", {x:x, y:y, width:barW, height:Math.max(barH,0), rx:3, ry:3, fill:palette[si % palette.length]});
                rect.style.cursor = "pointer";
                var titleEl = make("title", {});
                titleEl.textContent = row[catKey] + " — " + s.label + ": " + val;
                rect.appendChild(titleEl);
                svgEl.appendChild(rect);
                if(val > 0){
                    var vlabel = make("text", {x:x + barW/2, y:y-4, "text-anchor":"middle", "font-size":10, "font-weight":700, fill:ink});
                    vlabel.textContent = val;
                    svgEl.appendChild(vlabel);
                }
            });
            var catLbl = make("text", {x:groupX + groupW/2, y:padT+plotH+18, "text-anchor":"middle", "font-size":11, "font-weight":600, fill:ink});
            var full = String(row[catKey]);
            catLbl.textContent = full.length > 14 ? full.slice(0,13) + "…" : full;
            var titleFull = make("title", {}); titleFull.textContent = full;
            catLbl.appendChild(titleFull);
            svgEl.appendChild(catLbl);
        });
    }

    var movementRows = @json($movementCounts);
    drawGroupedBar(
        document.getElementById("movementChart"),
        document.getElementById("movementChartLegend"),
        movementRows, "pop_name",
        [
            {key:"receive", label:"Barang Masuk"},
            {key:"transfer_in", label:"Transfer Masuk"},
            {key:"transfer_out", label:"Transfer Keluar"},
            {key:"issue", label:"Keluar ke Teknisi"}
        ]
    );

    var lossRows = @json($lossChartData);
    var lossSeries = [
        {key:"lost", label:"Hilang"},
        {key:"damaged", label:"Rusak"},
        {key:"quarantine", label:"Karantina"},
        {key:"shrinkage_on_return", label:"Selisih Retur"},
        {key:"stock_opname_diff", label:"Selisih Opname"}
    ].filter(function(s){ return lossRows.some(function(r){ return (r[s.key] || 0) > 0; }); });

    drawGroupedBar(
        document.getElementById("lossChart"),
        document.getElementById("lossChartLegend"),
        lossRows, "pop_label",
        lossSeries.length ? lossSeries : [{key:"lost", label:"Hilang"}]
    );
})();
</script>
@endpush

@endsection
