@extends('layouts.app')

@section('title', 'Pemakaian Material Lapangan - Whusnet Operasional')
@section('page_title', 'Pemakaian Material')

@section('content')

<x-warehouse.header
    active="usage"
    title="Pemakaian Material Lapangan"
    subtitle="Pusat kendali konsumsi material, rekonsiliasi perangkat terpasang, dan log pelacakan barang oleh teknisi."
/>

@php
    $hasActiveFilters = (bool) ($popFilter || $categoryFilter || $search || ($preset === 'custom' && ($dateFrom || $dateTo)));
@endphp

<div x-data="materialUsageApp()" class="space-y-4">

    <!-- ================= LAYER 1: FLAT SUMMARY METRIC STRIP (Card Budget = 1 compliant) ================= -->
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-xs overflow-hidden">
        <div class="grid grid-cols-2 lg:grid-cols-4 divide-x divide-y lg:divide-y-0 divide-slate-200 dark:divide-slate-700 bg-slate-50/50 dark:bg-slate-900/30">
            
            <!-- Col 1: Modem ONT Terpasang -->
            <div class="p-3.5 sm:p-4 flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                        MODEM ONT TERPASANG
                    </span>
                    <div class="w-6 h-6 rounded-md bg-sky-100 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-2">
                    <div class="flex items-baseline gap-1.5">
                        <span class="text-xl sm:text-2xl font-bold font-mono text-slate-900 dark:text-slate-100 tabular-nums">
                            {{ $kpi['modem_count'] }}
                        </span>
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Unit</span>
                    </div>
                    <span class="text-[10px] text-slate-400 dark:text-slate-500 block truncate mt-0.5">PSB & Replacement Aktif</span>
                </div>
            </div>

            <!-- Col 2: Kabel Dropcore Terpakai -->
            <div class="p-3.5 sm:p-4 flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                        DROPCORE TERPAKAI
                    </span>
                    <div class="w-6 h-6 rounded-md bg-amber-100 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-2">
                    <div class="flex items-baseline gap-1.5">
                        <span class="text-xl sm:text-2xl font-bold font-mono text-amber-600 dark:text-amber-400 tabular-nums">
                            {{ rtrim(rtrim(number_format((float) $kpi['cable_meters'], 2, ',', '.'), '0'), ',') }}
                        </span>
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Meter</span>
                    </div>
                    <span class="text-[10px] text-slate-400 dark:text-slate-500 block truncate mt-0.5">Potongan Roll Lapangan</span>
                </div>
            </div>

            <!-- Col 3: Material Pasif -->
            <div class="p-3.5 sm:p-4 flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                        MATERIAL PASIF
                    </span>
                    <div class="w-6 h-6 rounded-md bg-indigo-100 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-2">
                    <div class="flex items-baseline gap-1.5">
                        <span class="text-xl sm:text-2xl font-bold font-mono text-indigo-600 dark:text-indigo-400 tabular-nums">
                            {{ rtrim(rtrim(number_format((float) $kpi['passive_count'], 2, ',', '.'), '0'), ',') }}
                        </span>
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Pcs</span>
                    </div>
                    <span class="text-[10px] text-slate-400 dark:text-slate-500 block truncate mt-0.5">Patchcord & Aksesori</span>
                </div>
            </div>

            <!-- Col 4: Tiket Lapangan -->
            <div class="p-3.5 sm:p-4 flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                        TIKET LAPANGAN
                    </span>
                    <div class="w-6 h-6 rounded-md bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-2">
                    <div class="flex items-baseline gap-1.5">
                        <span class="text-xl sm:text-2xl font-bold font-mono text-emerald-600 dark:text-emerald-400 tabular-nums">
                            {{ $kpi['task_count'] }}
                        </span>
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Tugas</span>
                    </div>
                    <span class="text-[10px] text-slate-400 dark:text-slate-500 block truncate mt-0.5">PSB & Maintenance Selesai</span>
                </div>
            </div>

        </div>
    </div>

    <!-- ================= LAYER 2: NAKED FILTER & PERIOD BAR ================= -->
    <div class="space-y-3">
        <form action="{{ route('warehouse.usage.index') }}" method="GET" x-ref="filterForm" class="space-y-3">
            <input type="hidden" name="preset" x-model="activePreset">

            <!-- Baris 1: Presets, Quick Toggle, Reset & Download Excel -->
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                
                <!-- Quick Period Presets -->
                <div class="flex items-center gap-1.5 flex-wrap">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mr-1 hidden sm:inline">
                        Periode:
                    </span>
                    <div class="grid grid-cols-2 sm:flex items-center gap-1.5 w-full sm:w-auto">
                        <button type="button" @click="selectPreset('today')"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors cursor-pointer text-center {{ $preset === 'today' ? 'bg-sky-600 text-white shadow-xs' : 'bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700' }}">
                            Hari Ini
                        </button>
                        <button type="button" @click="selectPreset('yesterday')"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors cursor-pointer text-center {{ $preset === 'yesterday' ? 'bg-sky-600 text-white shadow-xs' : 'bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700' }}">
                            Kemarin
                        </button>
                        <button type="button" @click="selectPreset('last_7_days')"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors cursor-pointer text-center {{ $preset === 'last_7_days' ? 'bg-sky-600 text-white shadow-xs' : 'bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700' }}">
                            7 Hari Terakhir
                        </button>
                        <button type="button" @click="selectPreset('this_month')"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors cursor-pointer text-center {{ $preset === 'this_month' ? 'bg-sky-600 text-white shadow-xs' : 'bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700' }}">
                            Bulan Ini
                        </button>
                        <button type="button" @click="selectPreset('all')"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors cursor-pointer text-center {{ $preset === 'all' ? 'bg-sky-600 text-white shadow-xs' : 'bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700' }}">
                            Semua Waktu
                        </button>
                    </div>
                </div>

                <!-- Right Action Bar -->
                <div class="flex items-center justify-between lg:justify-end gap-2 shrink-0">
                    <button type="button" @click="filterOpen = !filterOpen"
                            class="inline-flex items-center gap-1.5 text-xs text-sky-600 dark:text-sky-400 font-semibold hover:underline cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 12.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-8.586L2.293 6.707A1 1 0 012 6V4z"/>
                        </svg>
                        <span x-text="filterOpen ? 'Sembunyikan Filter' : 'Filter Lanjutan'"></span>
                        @if($hasActiveFilters)
                        <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
                        @endif
                    </button>

                    @if($hasActiveFilters || $preset !== 'today')
                    <a href="{{ route('warehouse.usage.index') }}"
                       class="text-xs text-rose-500 hover:text-rose-600 dark:text-rose-400 font-medium hover:underline">
                        Reset
                    </a>
                    @endif

                    <div class="h-4 w-px bg-slate-200 dark:bg-slate-700 hidden sm:block"></div>

                    <a href="{{ route('warehouse.usage.export', request()->query()) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg text-white bg-emerald-600 hover:bg-emerald-700 shadow-xs transition-colors cursor-pointer shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        <span>Download Excel</span>
                    </a>
                </div>
            </div>

            <!-- Baris 2: Collapsible Advanced Filters Grid -->
            <div x-show="filterOpen" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                 class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-2.5 pt-2">
                
                <!-- Tanggal Mulai -->
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Tanggal Mulai</label>
                    <input type="date" name="date_from" value="{{ $dateFrom }}" x-ref="dateFrom" @change="onDateChange()"
                           class="w-full px-3 py-1.5 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                </div>

                <!-- Tanggal Akhir -->
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Tanggal Akhir</label>
                    <input type="date" name="date_to" value="{{ $dateTo }}" x-ref="dateTo" @change="onDateChange()"
                           class="w-full px-3 py-1.5 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                </div>

                <!-- Gudang POP / Cabang -->
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Gudang POP / Cabang</label>
                    <select name="pop_id" @change="$refs.filterForm.submit()"
                            class="w-full px-3 py-1.5 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                        <option value="">— Semua Cabang POP —</option>
                        @foreach($pops as $pop)
                        <option value="{{ $pop->id }}" {{ (string) $popFilter === (string) $pop->id ? 'selected' : '' }}>
                            {{ $pop->name }} ({{ strtoupper($pop->type?->value ?? 'Cabang') }})
                        </option>
                        @endforeach
                    </select>
                </div>

                <!-- Kategori Barang -->
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Kategori Barang</label>
                    <select name="category_id" @change="$refs.filterForm.submit()"
                            class="w-full px-3 py-1.5 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                        <option value="">— Semua Kategori —</option>
                        @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ (string) $categoryFilter === (string) $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <!-- Kata Kunci Search -->
                <div class="flex items-end gap-1.5">
                    <div class="relative flex-1">
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Cari SN / Tiket / Teknisi</label>
                        <input type="text" name="search" value="{{ $search }}" placeholder="Ketik kata kunci..."
                               class="w-full px-3 py-1.5 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                    </div>
                    <button type="submit"
                            class="px-3 py-1.5 bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 text-white rounded-lg text-xs font-semibold transition-colors cursor-pointer shrink-0"
                            title="Cari">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- ================= LAYER 3: SINGLE UNIFIED DATA PANEL (Card Budget = 1) ================= -->
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-xs overflow-hidden">
        
        <!-- Panel Header Strip with Tabs & Context Info -->
        <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/30 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
            
            <!-- Segmented Pill Buttons -->
            <div class="w-full sm:w-fit grid grid-cols-2 sm:flex items-center gap-1 bg-slate-200/70 dark:bg-slate-900/80 p-1 rounded-lg">
                <button type="button" @click="currentTab = 'rekap'"
                        :class="currentTab === 'rekap' ? 'bg-white dark:bg-slate-800 text-sky-600 dark:text-sky-400 shadow-xs font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 font-medium'"
                        class="px-3 py-1.5 rounded-md text-xs transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    <span>Rekap per Barang</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold"
                          :class="currentTab === 'rekap' ? 'bg-sky-100 dark:bg-sky-950/80 text-sky-700 dark:text-sky-300' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'">
                        {{ $itemSummaries->count() }}
                    </span>
                </button>

                <button type="button" @click="currentTab = 'log'"
                        :class="currentTab === 'log' ? 'bg-white dark:bg-slate-800 text-sky-600 dark:text-sky-400 shadow-xs font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 font-medium'"
                        class="px-3 py-1.5 rounded-md text-xs transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                    </svg>
                    <span>Log Rincian Lapangan</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold"
                          :class="currentTab === 'log' ? 'bg-sky-100 dark:bg-sky-950/80 text-sky-700 dark:text-sky-300' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'">
                        {{ $logs->total() }}
                    </span>
                </button>
            </div>

            <!-- Active Period Badge -->
            <div class="flex items-center gap-2 text-xs">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-300 border border-sky-200/80 dark:border-sky-800/60">
                    <svg class="w-3 h-3 text-sky-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span>Periode: <strong>{{ $periodLabel }}</strong></span>
                </span>
                @if($popFilter)
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                    {{ $pops->firstWhere('id', $popFilter)?->name ?? 'Cabang Terpilih' }}
                </span>
                @endif
            </div>
        </div>

        <!-- ================= TAB 1: REKAPITULASI PER BARANG ================= -->
        <div x-show="currentTab === 'rekap'">
            @if($itemSummaries->isEmpty())
            <div class="p-12 text-center">
                <div class="w-12 h-12 mx-auto mb-3 rounded-lg bg-slate-100 dark:bg-slate-700/50 flex items-center justify-center text-slate-400">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                    </svg>
                </div>
                <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Tidak ada pemakaian material pada periode ini</h4>
                <p class="text-xs text-slate-400 mt-1 max-w-md mx-auto">
                    Coba ganti rentang tanggal atau ubah filter cabang POP / kategori pada tombol filter di atas.
                </p>
            </div>
            @else
            <!-- Desktop Table View -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50/80 dark:bg-slate-800/60 text-slate-400 dark:text-slate-500 text-[10px] font-bold uppercase tracking-wider border-b border-slate-100 dark:border-slate-700/60">
                        <tr>
                            <th class="px-4 py-3">Nama Barang & SKU</th>
                            <th class="px-4 py-3">Kategori</th>
                            <th class="px-4 py-3 text-right">Total Terpakai</th>
                            <th class="px-4 py-3 text-center">Jumlah Tugas</th>
                            <th class="px-4 py-3">Identitas Roll ID / SN Terpakai</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 text-xs">
                        @foreach($itemSummaries as $summary)
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/30 transition-colors">
                            <!-- Nama Barang & SKU -->
                            <td class="px-4 py-3">
                                <div class="font-bold text-slate-900 dark:text-slate-100">{{ $summary['item_name'] }}</div>
                                <span class="font-mono text-[11px] text-slate-400">{{ $summary['item_code'] }}</span>
                            </td>

                            <!-- Kategori -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 dark:bg-slate-700/80 text-slate-600 dark:text-slate-300 border border-slate-200/60 dark:border-slate-600/60">
                                    {{ $summary['category_name'] }}
                                </span>
                            </td>

                            <!-- Total Terpakai -->
                            <td class="px-4 py-3 whitespace-nowrap text-right font-mono">
                                <span class="text-sm font-bold text-slate-900 dark:text-slate-100 tabular-nums">
                                    {{ rtrim(rtrim(number_format((float) $summary['total_qty'], 2, ',', '.'), '0'), ',') }}
                                </span>
                                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 ml-0.5">{{ $summary['unit'] }}</span>
                            </td>

                            <!-- Jumlah Tugas -->
                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200/80 dark:border-emerald-800">
                                    {{ $summary['task_count'] }} Tugas
                                </span>
                            </td>

                            <!-- Identitas Roll ID / SN -->
                            <td class="px-4 py-3">
                                @if(!empty($summary['identifiers']))
                                <div class="flex items-center gap-1.5 flex-wrap max-w-md">
                                    @foreach(array_slice($summary['identifiers'], 0, 3) as $ident)
                                    <button type="button"
                                            @click="openSnModal('{{ $ident }}', '{{ addslashes($summary['item_name']) }}')"
                                            class="font-mono text-[11px] px-2 py-0.5 rounded bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 border border-amber-200/80 dark:border-amber-800/60 font-semibold cursor-pointer hover:bg-amber-100 dark:hover:bg-amber-900/60 transition-colors"
                                            title="Klik untuk melihat detail & salin">
                                        {{ $ident }}
                                    </button>
                                    @endforeach

                                    @if(count($summary['identifiers']) > 3)
                                    <button type="button"
                                            @click="openAllSnModal('{{ addslashes($summary['item_name']) }}', {{ json_encode($summary['identifiers']) }})"
                                            class="text-[10px] font-bold text-sky-600 dark:text-sky-400 cursor-pointer hover:underline">
                                        +{{ count($summary['identifiers']) - 3 }} Lainnya
                                    </button>
                                    @endif
                                </div>
                                @else
                                <span class="text-slate-400 text-xs italic">
                                    Material Pasif Non-Serial
                                </span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Mobile Row View for Rekapitulasi -->
            <div class="md:hidden divide-y divide-slate-100 dark:divide-slate-700/50">
                @foreach($itemSummaries as $summary)
                <div class="p-3.5 space-y-2.5">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <h3 class="font-bold text-xs text-slate-900 dark:text-slate-100">{{ $summary['item_name'] }}</h3>
                            <span class="font-mono text-[11px] text-slate-400">{{ $summary['item_code'] }}</span>
                        </div>
                        <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 shrink-0">
                            {{ $summary['category_name'] }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-2 py-2 border-y border-slate-100 dark:border-slate-700/60 text-xs">
                        <div>
                            <span class="text-slate-400 block text-[10px] font-bold uppercase tracking-wider">Total Terpakai</span>
                            <span class="font-bold font-mono text-slate-900 dark:text-slate-100 text-xs tabular-nums">
                                {{ rtrim(rtrim(number_format((float) $summary['total_qty'], 2, ',', '.'), '0'), ',') }}
                                <span class="text-[10px] font-normal text-slate-400">{{ $summary['unit'] }}</span>
                            </span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[10px] font-bold uppercase tracking-wider">Total Pekerjaan</span>
                            <span class="font-bold text-emerald-600 dark:text-emerald-400 text-xs">
                                {{ $summary['task_count'] }} Tugas
                            </span>
                        </div>
                    </div>

                    <div>
                        <span class="text-slate-400 block text-[10px] font-bold uppercase tracking-wider mb-1">Identitas SN / Roll ID</span>
                        <div class="flex flex-wrap gap-1">
                            @if(!empty($summary['identifiers']))
                                @foreach(array_slice($summary['identifiers'], 0, 5) as $ident)
                                <button type="button"
                                        @click="openSnModal('{{ $ident }}', '{{ addslashes($summary['item_name']) }}')"
                                        class="font-mono text-[10px] px-2 py-0.5 rounded bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 border border-amber-200/80 dark:border-amber-800/60 font-semibold cursor-pointer">
                                    {{ $ident }}
                                </button>
                                @endforeach
                                @if(count($summary['identifiers']) > 5)
                                <button type="button"
                                        @click="openAllSnModal('{{ addslashes($summary['item_name']) }}', {{ json_encode($summary['identifiers']) }})"
                                        class="text-[10px] font-bold text-sky-600 dark:text-sky-400">
                                    +{{ count($summary['identifiers']) - 5 }} lainnya
                                </button>
                                @endif
                            @else
                                <span class="text-slate-400 text-xs italic">Material Pasif Non-Serial</span>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        <!-- ================= TAB 2: RINCIAN LOG LAPANGAN ================= -->
        <div x-show="currentTab === 'log'" style="display: none;">
            @if($logs->isEmpty())
            <div class="p-12 text-center">
                <div class="w-12 h-12 mx-auto mb-3 rounded-lg bg-slate-100 dark:bg-slate-700/50 flex items-center justify-center text-slate-400">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Tidak ada log rincian pemakaian material</h4>
                <p class="text-xs text-slate-400 mt-1 max-w-md mx-auto">
                    Belum ada data pemakaian yang tercatat untuk filter dan periode waktu ini.
                </p>
            </div>
            @else
            <!-- Desktop Log Table View -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[750px]">
                    <thead class="bg-slate-50/80 dark:bg-slate-800/60 text-slate-400 dark:text-slate-500 text-[10px] font-bold uppercase tracking-wider border-b border-slate-100 dark:border-slate-700/60">
                        <tr>
                            <th class="px-4 py-3">Waktu & Gudang</th>
                            <th class="px-4 py-3">Barang</th>
                            <th class="px-4 py-3 text-right">Qty</th>
                            <th class="px-4 py-3">Identitas (SN / Roll)</th>
                            <th class="px-4 py-3">Teknisi Pelaksana</th>
                            <th class="px-4 py-3">Pelanggan & Tiket Tugas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 text-xs">
                        @foreach($logs as $log)
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/30 transition-colors">
                            <!-- Waktu & Gudang -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="font-bold text-slate-800 dark:text-slate-200">
                                    {{ $log['created_at']->translatedFormat('d M Y') }}
                                </div>
                                <div class="text-[11px] text-slate-400 font-mono">
                                    {{ $log['created_at']->format('H:i') }} WIB
                                </div>
                                <span class="inline-block mt-0.5 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                                    {{ $log['pop_name'] }}
                                </span>
                            </td>

                            <!-- Barang -->
                            <td class="px-4 py-3">
                                <div class="font-bold text-slate-900 dark:text-slate-100">{{ $log['item_name'] }}</div>
                                <div class="text-[11px] text-slate-400 font-mono">{{ $log['item_code'] }}</div>
                            </td>

                            <!-- Qty -->
                            <td class="px-4 py-3 whitespace-nowrap text-right font-mono">
                                <span class="text-sm font-bold text-slate-900 dark:text-slate-100 tabular-nums">
                                    {{ rtrim(rtrim(number_format((float) $log['qty'], 2, ',', '.'), '0'), ',') }}
                                </span>
                                <span class="text-xs font-semibold text-slate-400 ml-0.5">{{ $log['unit'] }}</span>
                            </td>

                            <!-- Identitas SN / Roll -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($log['identifier'] !== '-')
                                <button type="button"
                                        @click="openSnModal('{{ $log['identifier'] }}', '{{ addslashes($log['item_name']) }}', '{{ addslashes($log['customer_name']) }}', '{{ addslashes($log['task_number']) }}')"
                                        class="font-mono text-xs px-2 py-0.5 rounded font-bold bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 border border-amber-200/80 dark:border-amber-800/60 cursor-pointer hover:bg-amber-100 dark:hover:bg-amber-900/60 transition-colors"
                                        title="Klik untuk melihat detail & salin">
                                    {{ $log['identifier'] }}
                                </button>
                                @else
                                <span class="text-slate-400 text-xs">—</span>
                                @endif
                            </td>

                            <!-- Teknisi Pelaksana -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="font-bold text-slate-800 dark:text-slate-200">{{ $log['technician_name'] }}</div>
                                <div class="text-[10px] text-slate-400">Teknisi Lapangan</div>
                            </td>

                            <!-- Pelanggan & Tiket Tugas -->
                            <td class="px-4 py-3">
                                <div class="font-bold text-slate-800 dark:text-slate-200">
                                    @if($log['customer_id'])
                                    <a href="{{ route('customers.show', $log['customer_id']) }}" class="text-sky-600 dark:text-sky-400 hover:underline">
                                        {{ $log['customer_name'] }}
                                    </a>
                                    @else
                                    {{ $log['customer_name'] }}
                                    @endif
                                </div>
                                <div class="flex items-center gap-1.5 mt-0.5 flex-wrap">
                                    <span class="px-1.5 py-0.2 rounded text-[10px] font-semibold bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-300 border border-sky-200/80 dark:border-sky-800">
                                        {{ $log['task_category'] }}
                                    </span>
                                    @if($log['task_number'] !== '-')
                                    <span class="font-mono text-[11px] text-slate-400">#{{ $log['task_number'] }}</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Mobile Row View for Log Lapangan -->
            <div class="md:hidden divide-y divide-slate-100 dark:divide-slate-700/50">
                @foreach($logs as $log)
                <div class="p-3.5 space-y-2">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 block font-mono">
                                {{ $log['created_at']->translatedFormat('d M Y') }} • {{ $log['created_at']->format('H:i') }} WIB
                            </span>
                            <h4 class="font-bold text-xs text-slate-900 dark:text-slate-100 mt-0.5">{{ $log['item_name'] }}</h4>
                            <span class="font-mono text-[11px] text-slate-400">{{ $log['item_code'] }}</span>
                        </div>
                        <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border border-sky-200/80 dark:border-sky-800 shrink-0">
                            {{ $log['pop_name'] }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-2 py-1.5 border-y border-slate-100 dark:border-slate-700/60 text-xs">
                        <div>
                            <span class="text-slate-400 block text-[10px] font-bold uppercase">JUMLAH TERPAKAI</span>
                            <span class="font-bold font-mono text-slate-900 dark:text-slate-100 text-xs tabular-nums">
                                {{ rtrim(rtrim(number_format((float) $log['qty'], 2, ',', '.'), '0'), ',') }}
                                <span class="text-[10px] font-normal text-slate-400">{{ $log['unit'] }}</span>
                            </span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[10px] font-bold uppercase">IDENTITAS SN / ROLL</span>
                            @if($log['identifier'] !== '-')
                            <button type="button"
                                    @click="openSnModal('{{ $log['identifier'] }}', '{{ addslashes($log['item_name']) }}', '{{ addslashes($log['customer_name']) }}', '{{ addslashes($log['task_number']) }}')"
                                    class="font-mono text-[11px] font-bold text-amber-700 dark:text-amber-300 underline">
                                {{ $log['identifier'] }}
                            </button>
                            @else
                            <span class="text-slate-400 text-xs">—</span>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-xs pt-0.5">
                        <div>
                            <span class="text-slate-400 block text-[10px]">PELANGGAN</span>
                            <span class="font-bold text-sky-600 dark:text-sky-400">
                                @if($log['customer_id'])
                                <a href="{{ route('customers.show', $log['customer_id']) }}" class="hover:underline">
                                    {{ $log['customer_name'] }}
                                </a>
                                @else
                                {{ $log['customer_name'] }}
                                @endif
                            </span>
                            <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                                {{ $log['task_category'] }} @if($log['task_number'] !== '-') (#{{ $log['task_number'] }}) @endif
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-slate-400 block text-[10px]">TEKNISI</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200">{{ $log['technician_name'] }}</span>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            <!-- Pagination Footer -->
            <div class="px-4 py-3 border-t border-slate-100 dark:border-slate-700/60">
                {{ $logs->links() }}
            </div>
            @endif
        </div>

    </div>

    <!-- ================= MODALS & OVERLAYS ================= -->

    <!-- Single Serial Number / Roll ID Detail Modal -->
    <div x-show="snModal.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div @click.outside="snModal.open = false"
             class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg max-w-md w-full p-4 shadow-xl space-y-3.5">
            
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700/60 pb-2.5">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-md bg-amber-100 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold text-xs">
                        #
                    </div>
                    <h3 class="font-bold text-xs text-slate-900 dark:text-slate-100">Detail Serial Number / Roll ID</h3>
                </div>
                <button type="button" @click="snModal.open = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            
            <div class="space-y-3 text-xs">
                <div>
                    <span class="text-slate-400 block text-[10px] font-bold uppercase tracking-wider">NAMA BARANG</span>
                    <span class="font-bold text-slate-800 dark:text-slate-200 text-xs" x-text="snModal.itemName"></span>
                </div>

                <div>
                    <span class="text-slate-400 block text-[10px] font-bold uppercase tracking-wider">SERIAL NUMBER / ROLL ID</span>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="font-mono text-xs font-bold text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/50 px-2.5 py-1 rounded border border-sky-200 dark:border-sky-800 flex-1 truncate"
                              x-text="snModal.sn"></span>
                        <button type="button" @click="copyToClipboard(snModal.sn)"
                                class="inline-flex items-center gap-1 px-2.5 py-1 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold rounded text-xs hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors cursor-pointer"
                                title="Salin Serial Number">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            <span>Salin</span>
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-100 dark:border-slate-700/60">
                    <div>
                        <span class="text-slate-400 block text-[10px] font-bold uppercase">STATUS</span>
                        <span class="inline-flex items-center gap-1 font-bold text-emerald-600 dark:text-emerald-400 mt-0.5 text-xs">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            <span>Terpasang di Lapangan</span>
                        </span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[10px] font-bold uppercase">PELANGGAN / TIKET</span>
                        <span class="font-bold text-slate-800 dark:text-slate-200 block truncate mt-0.5 text-xs" x-text="snModal.customer || '-'"></span>
                        <span class="font-mono text-[10px] text-slate-400" x-text="snModal.ticket ? '#' + snModal.ticket : ''"></span>
                    </div>
                </div>
            </div>

            <div class="pt-1">
                <button type="button" @click="snModal.open = false"
                        class="w-full py-2 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 font-bold text-xs rounded-lg transition-colors cursor-pointer">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- All Serials Modal (For Multiple SN Items) -->
    <div x-show="allSnModal.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div @click.outside="allSnModal.open = false"
             class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg max-w-lg w-full p-4 shadow-xl space-y-3.5">
            
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700/60 pb-2.5">
                <div>
                    <h3 class="font-bold text-xs text-slate-900 dark:text-slate-100">Daftar Serial Number / Roll ID Terpakai</h3>
                    <p class="text-[11px] text-slate-400 mt-0.5" x-text="allSnModal.itemName"></p>
                </div>
                <button type="button" @click="allSnModal.open = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="flex items-center justify-between gap-2">
                <input type="text" x-model="allSnModal.search" placeholder="Filter nomor SN / Roll..."
                       class="flex-1 px-3 py-1.5 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                
                <button type="button" @click="copyAllSerials()"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-sky-600 hover:bg-sky-700 text-white rounded-lg text-xs font-semibold shadow-xs transition-colors cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                    <span>Salin Semua</span>
                </button>
            </div>

            <!-- List of Serials -->
            <div class="max-h-64 overflow-y-auto custom-scrollbar p-1">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <template x-for="sn in filteredModalSerials" :key="sn">
                        <div class="flex items-center justify-between p-2 rounded-md bg-slate-50 dark:bg-slate-900/60 border border-slate-200/80 dark:border-slate-700/80 text-xs">
                            <span class="font-mono font-bold text-slate-800 dark:text-slate-200 truncate" x-text="sn"></span>
                            <button type="button" @click="copyToClipboard(sn)"
                                    class="p-1 text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 cursor-pointer" title="Salin">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                            </button>
                        </div>
                    </template>
                </div>
                <div x-show="filteredModalSerials.length === 0" class="py-6 text-center text-xs text-slate-400">
                    Tidak ditemukan serial number yang cocok.
                </div>
            </div>

            <div class="pt-2 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between text-xs text-slate-400">
                <span>Total: <strong class="text-slate-800 dark:text-slate-200 font-mono" x-text="allSnModal.serials.length"></strong> identitas</span>
                <button type="button" @click="allSnModal.open = false"
                        class="px-3 py-1 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 font-semibold rounded-lg transition-colors cursor-pointer text-xs">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- Floating Toast Notification -->
    <div x-show="toast.show" x-cloak
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-3" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-3"
         class="fixed bottom-5 right-5 z-50 bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900 px-4 py-2.5 rounded-lg shadow-xl flex items-center gap-2.5 text-xs font-semibold">
        <svg class="w-4 h-4 text-emerald-400 dark:text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        <span x-text="toast.message"></span>
    </div>

</div>

@push('scripts')
<script>
    function materialUsageApp() {
        return {
            currentTab: 'rekap', // 'rekap' or 'log'
            activePreset: '{{ $preset }}',
            filterOpen: {{ $hasActiveFilters ? 'true' : 'false' }},
            
            snModal: {
                open: false,
                sn: '',
                itemName: '',
                customer: '',
                ticket: ''
            },

            allSnModal: {
                open: false,
                itemName: '',
                serials: [],
                search: ''
            },

            get filteredModalSerials() {
                if (!this.allSnModal.search) return this.allSnModal.serials;
                const kw = this.allSnModal.search.toLowerCase();
                return this.allSnModal.serials.filter(s => s.toLowerCase().includes(kw));
            },

            toast: {
                show: false,
                message: ''
            },

            selectPreset(preset) {
                this.activePreset = preset;
                const form = this.$refs.filterForm;
                if (form) {
                    if (this.$refs.dateFrom) this.$refs.dateFrom.value = '';
                    if (this.$refs.dateTo) this.$refs.dateTo.value = '';
                    if (form.preset) form.preset.value = preset;
                    form.submit();
                }
            },

            onDateChange() {
                this.activePreset = 'custom';
                const form = this.$refs.filterForm;
                if (form) {
                    if (form.preset) form.preset.value = 'custom';
                    form.submit();
                }
            },

            openSnModal(sn, itemName, customer = '', ticket = '') {
                this.snModal.sn = sn;
                this.snModal.itemName = itemName || 'Perangkat Logistik';
                this.snModal.customer = customer;
                this.snModal.ticket = ticket;
                this.snModal.open = true;
            },

            openAllSnModal(itemName, serialsList) {
                this.allSnModal.itemName = itemName;
                this.allSnModal.serials = Array.isArray(serialsList) ? serialsList : [];
                this.allSnModal.search = '';
                this.allSnModal.open = true;
            },

            copyToClipboard(text) {
                if (!text || text === '-') return;
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(() => {
                        this.showToast('Serial / Roll ID disalin: ' + text);
                    }).catch(() => {
                        this.fallbackCopy(text);
                    });
                } else {
                    this.fallbackCopy(text);
                }
            },

            fallbackCopy(text) {
                const temp = document.createElement('input');
                temp.value = text;
                document.body.appendChild(temp);
                temp.select();
                document.execCommand('copy');
                document.body.removeChild(temp);
                this.showToast('Serial / Roll ID disalin: ' + text);
            },

            copyAllSerials() {
                const list = this.filteredModalSerials.join('\n');
                if (!list) return;
                this.copyToClipboard(list);
                this.showToast('Seluruh (' + this.filteredModalSerials.length + ') SN berhasil disalin!');
            },

            showToast(msg) {
                this.toast.message = msg;
                this.toast.show = true;
                setTimeout(() => {
                    this.toast.show = false;
                }, 3000);
            }
        };
    }
</script>
@endpush

@endsection
