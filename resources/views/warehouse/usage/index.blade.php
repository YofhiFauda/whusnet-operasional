@extends('layouts.app')

@section('title', 'Pemakaian Material Lapangan - Whusnet Operasional')
@section('page_title', 'Pemakaian Material Lapangan')

@section('content')

<x-warehouse.header active="usage" />

<div class="space-y-6">

    <!-- Filter & Preset Control Card -->
    <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-xl p-4 sm:p-5 shadow-xs">
        <form action="{{ route('warehouse.usage.index') }}" method="GET" class="space-y-4">
            <!-- Row 1: Quick Preset Buttons & Export -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-700/60">
                <div>
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1.5">
                        Pilih Periode Pemakaian
                    </span>
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <button type="submit" name="preset" value="today"
                                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer {{ $preset === 'today' ? 'bg-sky-600 text-white shadow-xs shadow-sky-600/25' : 'bg-slate-100 dark:bg-slate-700/60 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700' }}">
                            Hari Ini
                        </button>
                        <button type="submit" name="preset" value="yesterday"
                                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer {{ $preset === 'yesterday' ? 'bg-sky-600 text-white shadow-xs shadow-sky-600/25' : 'bg-slate-100 dark:bg-slate-700/60 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700' }}">
                            Kemarin
                        </button>
                        <button type="submit" name="preset" value="last_7_days"
                                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer {{ $preset === 'last_7_days' ? 'bg-sky-600 text-white shadow-xs shadow-sky-600/25' : 'bg-slate-100 dark:bg-slate-700/60 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700' }}">
                            7 Hari Terakhir
                        </button>
                        <button type="submit" name="preset" value="this_month"
                                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer {{ $preset === 'this_month' ? 'bg-sky-600 text-white shadow-xs shadow-sky-600/25' : 'bg-slate-100 dark:bg-slate-700/60 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700' }}">
                            Bulan Ini
                        </button>
                    </div>
                </div>

                <!-- Export Excel Button -->
                <div class="flex items-center gap-2">
                    <a href="{{ route('warehouse.usage.export', request()->query()) }}"
                       class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg shadow-xs transition-colors cursor-pointer">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                        </svg>
                        <span>Download Excel (.xlsx)</span>
                    </a>
                </div>
            </div>

            <!-- Row 2: Custom Date Range & Dropdown Filters -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 pt-1">
                <!-- Custom Start Date -->
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Tanggal Mulai</label>
                    <input type="date" name="date_from" value="{{ $dateFrom }}"
                           class="w-full px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                </div>

                <!-- Custom End Date -->
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Tanggal Akhir</label>
                    <input type="date" name="date_to" value="{{ $dateTo }}"
                           class="w-full px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                </div>

                <!-- Filter Cabang POP -->
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Gudang POP / Cabang</label>
                    <select name="pop_id" class="w-full px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                        <option value="">— Semua Cabang POP —</option>
                        @foreach($pops as $pop)
                        <option value="{{ $pop->id }}" {{ (string) $popFilter === (string) $pop->id ? 'selected' : '' }}>
                            {{ $pop->name }} ({{ strtoupper($pop->type?->value ?? 'Cabang') }})
                        </option>
                        @endforeach
                    </select>
                </div>

                <!-- Filter Kategori -->
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Kategori Barang</label>
                    <select name="category_id" class="w-full px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                        <option value="">— Semua Kategori —</option>
                        @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ (string) $categoryFilter === (string) $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <!-- Filter Search & Submit -->
                <div class="flex items-end gap-2">
                    <div class="flex-1">
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Cari Barang / Teknisi / Roll ID</label>
                        <input type="text" name="search" value="{{ $search }}" placeholder="Ketik kata kunci..."
                               class="w-full px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                    </div>
                    <button type="submit" name="preset" value="custom"
                            class="inline-flex items-center justify-center p-2.5 bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 text-white rounded-lg text-xs font-semibold shadow-xs transition-colors cursor-pointer"
                            title="Terapkan Filter">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                        </svg>
                    </button>
                    @if($popFilter || $categoryFilter || $search || $preset !== 'today')
                    <a href="{{ route('warehouse.usage.index') }}"
                       class="inline-flex items-center justify-center p-2.5 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700/50 transition-colors"
                       title="Reset Filter">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </a>
                    @endif
                </div>
            </div>
        </form>
    </div>

    <!-- Active Period Banner -->
    <div class="flex items-center justify-between px-1 text-xs">
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800/60">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span>Periode: {{ $periodLabel }}</span>
            </span>
            @if($popFilter)
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-600">
                POP: {{ $pops->firstWhere('id', $popFilter)?->name ?? 'Cabang Terpilih' }}
            </span>
            @endif
        </div>
        <span class="text-slate-400 text-[11px]">
            Menampilkan {{ $itemSummaries->count() }} jenis barang terpakai
        </span>
    </div>

    <!-- KPI Ringkasan Konsumsi Periode Terpilih -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Modem Terpasang -->
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-xl p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Modem ONT Terpasang</span>
                <div class="w-7 h-7 rounded-lg bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400 flex items-center justify-center border border-sky-100 dark:border-sky-800/60">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                    </svg>
                </div>
            </div>
            <div class="mt-2 flex items-baseline gap-1.5">
                <span class="text-2xl font-extrabold text-sky-600 dark:text-sky-400">{{ $kpi['modem_count'] }}</span>
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Unit</span>
            </div>
            <p class="text-[10px] text-slate-400 mt-1">Terpasang di pelanggan (Instalasi / Ganti)</p>
        </div>

        <!-- Kabel Terpakai -->
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-xl p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Kabel Dropcore Terpakai</span>
                <div class="w-7 h-7 rounded-lg bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 flex items-center justify-center border border-amber-100 dark:border-amber-800/60">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a1 1 0 001-1v-4a1 1 0 00-1-1H9a1 1 0 00-1 1v4a1 1 0 001 1zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                </div>
            </div>
            <div class="mt-2 flex items-baseline gap-1.5">
                <span class="text-2xl font-extrabold text-amber-600 dark:text-amber-400">
                    {{ rtrim(rtrim(number_format((float) $kpi['cable_meters'], 2, ',', '.'), '0'), ',') }}
                </span>
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Meter</span>
            </div>
            <p class="text-[10px] text-slate-400 mt-1">Dipotong dari roll kabel di lapangan</p>
        </div>

        <!-- Material Pasif -->
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-xl p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Material Pasif / Aksesori</span>
                <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center border border-indigo-100 dark:border-indigo-800/60">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                </div>
            </div>
            <div class="mt-2 flex items-baseline gap-1.5">
                <span class="text-2xl font-extrabold text-indigo-600 dark:text-indigo-400">
                    {{ rtrim(rtrim(number_format((float) $kpi['passive_count'], 2, ',', '.'), '0'), ',') }}
                </span>
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Pcs / Item</span>
            </div>
            <p class="text-[10px] text-slate-400 mt-1">Patchcord, fast connector, protection sleeve, dll</p>
        </div>

        <!-- Total Tiket Tugas -->
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-xl p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Pekerjaan Lapangan</span>
                <div class="w-7 h-7 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center border border-emerald-100 dark:border-emerald-800/60">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <div class="mt-2 flex items-baseline gap-1.5">
                <span class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400">{{ $kpi['task_count'] }}</span>
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Tiket Tugas</span>
            </div>
            <p class="text-[10px] text-slate-400 mt-1">Instalasi PSB & Maintenance terselesaikan</p>
        </div>
    </div>

    <!-- Segmented Tab UI: Rekapitulasi vs Rincian Log -->
    <div x-data="{ viewTab: 'summary' }" class="space-y-4">
        <!-- Tab Buttons -->
        <div class="flex items-center justify-between pb-1 border-b border-slate-200/80 dark:border-slate-700/80">
            <div class="flex items-center gap-2">
                <button type="button" @click="viewTab = 'summary'"
                        :class="viewTab === 'summary' ? 'bg-sky-600 text-white shadow-xs shadow-sky-600/20' : 'bg-slate-100 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-bold transition-all cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    <span>Rekap per Barang</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono" :class="viewTab === 'summary' ? 'bg-white/20 text-white' : 'bg-slate-200 dark:bg-slate-600 text-slate-700 dark:text-slate-300'">
                        {{ $itemSummaries->count() }}
                    </span>
                </button>

                <button type="button" @click="viewTab = 'logs'"
                        :class="viewTab === 'logs' ? 'bg-sky-600 text-white shadow-xs shadow-sky-600/20' : 'bg-slate-100 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-bold transition-all cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z"/>
                    </svg>
                    <span>Rincian Log Lapangan</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono" :class="viewTab === 'logs' ? 'bg-white/20 text-white' : 'bg-slate-200 dark:bg-slate-600 text-slate-700 dark:text-slate-300'">
                        {{ $logs->total() }}
                    </span>
                </button>
            </div>
        </div>

        <!-- TAB 1: REKAP PER BARANG -->
        <div x-show="viewTab === 'summary'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
            <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-xl shadow-xs overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
                    <div>
                        <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">
                            Akumulasi Pemakaian Material ({{ $periodLabel }})
                        </h3>
                        <p class="text-[11px] text-slate-400 mt-0.5">Total jumlah barang yang terpakai dan terpasang oleh teknisi pada periode terpilih</p>
                    </div>
                </div>

                @if($itemSummaries->isEmpty())
                <div class="p-12 text-center">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-lg bg-slate-100 dark:bg-slate-700/50 flex items-center justify-center text-slate-400">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                        </svg>
                    </div>
                    <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Tidak ada pemakaian material pada periode ini</h4>
                    <p class="text-xs text-slate-400 mt-1">Coba ubah tanggal atau pilih cabang POP lain pada filter di atas.</p>
                </div>
                @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400 text-[11px] font-bold uppercase tracking-wider text-left">
                            <tr>
                                <th class="px-5 py-3.5">Nama Barang & SKU</th>
                                <th class="px-4 py-3.5">Kategori</th>
                                <th class="px-4 py-3.5 text-right">Total Terpakai</th>
                                <th class="px-4 py-3.5 text-center">Jumlah Tugas</th>
                                <th class="px-5 py-3.5">Roll ID / Serial Number Terpakai</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 text-xs">
                            @foreach($itemSummaries as $summary)
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-700/30 transition-colors">
                                <!-- Nama Barang -->
                                <td class="px-5 py-3.5 font-medium text-slate-800 dark:text-slate-200">
                                    <div class="font-bold text-slate-900 dark:text-slate-100">{{ $summary['item_name'] }}</div>
                                    <span class="font-mono text-[11px] text-slate-400">{{ $summary['item_code'] }}</span>
                                </td>

                                <!-- Kategori -->
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 dark:bg-slate-700/70 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-600">
                                        {{ $summary['category_name'] }}
                                    </span>
                                </td>

                                <!-- Total Terpakai -->
                                <td class="px-4 py-3.5 whitespace-nowrap text-right font-mono">
                                    <span class="text-sm font-extrabold text-slate-900 dark:text-slate-100">
                                        {{ rtrim(rtrim(number_format((float) $summary['total_qty'], 2, ',', '.'), '0'), ',') }}
                                    </span>
                                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 ml-0.5">{{ $summary['unit'] }}</span>
                                </td>

                                <!-- Jumlah Tugas -->
                                <td class="px-4 py-3.5 whitespace-nowrap text-center">
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                        {{ $summary['task_count'] }} Tugas
                                    </span>
                                </td>

                                <!-- Identitas Roll / SN -->
                                <td class="px-5 py-3.5">
                                    @if(!empty($summary['identifiers']))
                                    <div class="flex items-center gap-1.5 flex-wrap max-w-md">
                                        @foreach(array_slice($summary['identifiers'], 0, 4) as $ident)
                                        <span class="font-mono text-[11px] px-2 py-0.5 rounded bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60 font-semibold">
                                            {{ $ident }}
                                        </span>
                                        @endforeach
                                        @if(count($summary['identifiers']) > 4)
                                        <span class="text-[10px] font-bold text-slate-400">
                                            +{{ count($summary['identifiers']) - 4 }} lainnya
                                        </span>
                                        @endif
                                    </div>
                                    @else
                                    <span class="text-slate-400 text-[11px]">Material Pasif Non-Serial</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>

        <!-- TAB 2: RINCIAN LOG LAPANGAN -->
        <div x-show="viewTab === 'logs'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
            <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-xl shadow-xs overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
                    <div>
                        <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">
                            Rincian Pemakaian Lapangan per Transaksi / Tiket
                        </h3>
                        <p class="text-[11px] text-slate-400 mt-0.5">Daftar lengkap per peristiwa pemasangan atau maintenance dengan identitas teknisi & pelanggan</p>
                    </div>
                </div>

                @if($logs->isEmpty())
                <div class="p-12 text-center">
                    <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Tidak ada log rincian pemakaian</h4>
                </div>
                @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400 text-[11px] font-bold uppercase tracking-wider text-left">
                            <tr>
                                <th class="px-4 py-3.5">Waktu & Gudang</th>
                                <th class="px-4 py-3.5">Barang</th>
                                <th class="px-4 py-3.5 text-right">Qty</th>
                                <th class="px-4 py-3.5">Identitas (Roll / SN)</th>
                                <th class="px-4 py-3.5">Teknisi</th>
                                <th class="px-4 py-3.5">Pelanggan & Tiket Tugas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 text-xs">
                            @foreach($logs as $log)
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-700/30 transition-colors">
                                <!-- Waktu & Gudang -->
                                <td class="px-4 py-3.5 whitespace-nowrap">
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
                                <td class="px-4 py-3.5">
                                    <div class="font-bold text-slate-900 dark:text-slate-100">{{ $log['item_name'] }}</div>
                                    <div class="text-[11px] text-slate-400 font-mono">{{ $log['item_code'] }}</div>
                                </td>

                                <!-- Qty -->
                                <td class="px-4 py-3.5 whitespace-nowrap text-right font-mono">
                                    <span class="text-sm font-extrabold text-slate-900 dark:text-slate-100">
                                        {{ rtrim(rtrim(number_format((float) $log['qty'], 2, ',', '.'), '0'), ',') }}
                                    </span>
                                    <span class="text-xs font-semibold text-slate-400 ml-0.5">{{ $log['unit'] }}</span>
                                </td>

                                <!-- Identitas (Roll / SN) -->
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    @if($log['identifier'] !== '-')
                                    <span class="font-mono text-xs px-2 py-0.5 rounded font-bold bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60">
                                        {{ $log['identifier'] }}
                                    </span>
                                    @else
                                    <span class="text-slate-400 text-xs">—</span>
                                    @endif
                                </td>

                                <!-- Teknisi -->
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <div class="font-bold text-slate-800 dark:text-slate-200">{{ $log['technician_name'] }}</div>
                                    <div class="text-[10px] text-slate-400">Teknisi Pelaksana</div>
                                </td>

                                <!-- Pelanggan & Tiket -->
                                <td class="px-4 py-3.5">
                                    <div class="font-bold text-slate-800 dark:text-slate-200">
                                        @if($log['customer_id'])
                                        <a href="{{ route('customers.show', $log['customer_id']) }}" class="text-sky-600 dark:text-sky-400 hover:underline">
                                            {{ $log['customer_name'] }}
                                        </a>
                                        @else
                                        {{ $log['customer_name'] }}
                                        @endif
                                    </div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-1.5 mt-0.5 flex-wrap">
                                        <span class="px-1.5 py-0.2 rounded text-[10px] font-semibold bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800">
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

                <!-- Pagination -->
                <div class="px-5 py-4 border-t border-slate-100 dark:border-slate-700/60">
                    {{ $logs->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>

</div>

@endsection
