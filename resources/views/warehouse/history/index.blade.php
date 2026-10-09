@extends('layouts.app')

@section('title', 'Riwayat Mutasi & Ledger Material - Whusnet Operasional')
@section('page_title', 'Riwayat Mutasi Gudang')

@section('content')

<x-warehouse.header active="history" title="Riwayat Mutasi & Ledger Material" subtitle="Jejak digital seluruh pergerakan barang, transfer antar cabang, pengeluaran teknisi, retur, hingga koreksi stok terverifikasi." />

@php
    // Design System Type Colors & Icons (sesuai template redesign_riwayat_mutasi_gudang.html)
    $typeColor = [
        'receive' => [
            'bg' => 'bg-emerald-50 dark:bg-emerald-950/40',
            'text' => 'text-emerald-700 dark:text-emerald-400',
            'border' => 'border-emerald-200 dark:border-emerald-800',
            'dot' => 'bg-emerald-500',
            'label' => 'Barang Masuk',
        ],
        'transfer' => [
            'bg' => 'bg-sky-50 dark:bg-sky-950/40',
            'text' => 'text-sky-700 dark:text-sky-400',
            'border' => 'border-sky-200 dark:border-sky-800',
            'dot' => 'bg-sky-500',
            'label' => 'Transfer Cabang',
        ],
        'issue' => [
            'bg' => 'bg-indigo-50 dark:bg-indigo-950/40',
            'text' => 'text-indigo-700 dark:text-indigo-400',
            'border' => 'border-indigo-200 dark:border-indigo-800',
            'dot' => 'bg-indigo-500',
            'label' => 'Serah Teknisi',
        ],
        'return' => [
            'bg' => 'bg-teal-50 dark:bg-teal-950/40',
            'text' => 'text-teal-700 dark:text-teal-400',
            'border' => 'border-teal-200 dark:border-teal-800',
            'dot' => 'bg-teal-500',
            'label' => 'Retur Alat',
        ],
        'adjustment' => [
            'bg' => 'bg-amber-50 dark:bg-amber-950/40',
            'text' => 'text-amber-700 dark:text-amber-400',
            'border' => 'border-amber-200 dark:border-amber-800',
            'dot' => 'bg-amber-500',
            'label' => 'Penyesuaian Saldo',
        ],
        'stock_opname' => [
            'bg' => 'bg-amber-50 dark:bg-amber-950/40',
            'text' => 'text-amber-700 dark:text-amber-400',
            'border' => 'border-amber-200 dark:border-amber-800',
            'dot' => 'bg-amber-500',
            'label' => 'Stock Opname',
        ],
        'transfer_custody' => [
            'bg' => 'bg-fuchsia-50 dark:bg-fuchsia-950/40',
            'text' => 'text-fuchsia-700 dark:text-fuchsia-400',
            'border' => 'border-fuchsia-200 dark:border-fuchsia-800',
            'dot' => 'bg-fuchsia-500',
            'label' => 'Custody Teknisi',
        ],
        'install' => [
            'bg' => 'bg-violet-50 dark:bg-violet-950/40',
            'text' => 'text-violet-700 dark:text-violet-400',
            'border' => 'border-violet-200 dark:border-violet-800',
            'dot' => 'bg-violet-500',
            'label' => 'Pasang Pelanggan',
        ],
    ];
    $defaultColor = [
        'bg' => 'bg-slate-100 dark:bg-slate-700',
        'text' => 'text-slate-700 dark:text-slate-300',
        'border' => 'border-slate-200 dark:border-slate-600',
        'dot' => 'bg-slate-400',
        'label' => 'Mutasi Gudang',
    ];

    $hasActiveFilters = (bool) ($typeFilter || $popFilter || $search || $dateFrom || $dateTo || $conditionFilter || $adjustmentReasonFilter);
@endphp

<div x-data="{
         currentType: '{{ $typeFilter }}',
         showAdvancedFilters: {{ ($popFilter || $dateFrom || $dateTo || $conditionFilter || $adjustmentReasonFilter) ? 'true' : 'false' }},
         detailDrawerOpen: false,
         activeDetail: null,
         openDetail(item) {
             this.activeDetail = item;
             this.detailDrawerOpen = true;
         },
         closeDetail() {
             this.detailDrawerOpen = false;
         },
         filterByType(t) {
             this.currentType = t;
             $refs.typeInput.value = t;
             $refs.filterForm.submit();
         },
         clearSearch() {
             document.getElementById('search').value = '';
             $refs.filterForm.submit();
         },
         setPeriodPreset(preset) {
             const now = new Date();
             const dateToInput = document.getElementById('date_to');
             const dateFromInput = document.getElementById('date_from');
             
             const formatDate = (d) => d.toISOString().split('T')[0];
             
             if (preset === 'today') {
                 dateFromInput.value = formatDate(now);
                 dateToInput.value = formatDate(now);
             } else if (preset === 'this_week') {
                 const firstDay = new Date(now.setDate(now.getDate() - now.getDay() + 1));
                 dateFromInput.value = formatDate(firstDay);
                 dateToInput.value = formatDate(new Date());
             } else if (preset === 'this_month') {
                 const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
                 dateFromInput.value = formatDate(firstDay);
                 dateToInput.value = formatDate(new Date());
             }
             $refs.filterForm.submit();
         }
     }"
     class="space-y-6">

    {{-- =========================================================================
         1. 5 KPI METRIC CARDS (Sesuai Template redesign_riwayat_mutasi_gudang.html)
         ========================================================================= --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-4">
        
        <!-- Card 1: Total Transaksi -->
        <div @click="filterByType('')"
             class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-4 shadow-2xs relative overflow-hidden cursor-pointer hover:border-sky-300 dark:hover:border-sky-700 transition-all group"
             :class="currentType === '' ? 'ring-2 ring-sky-500 border-sky-500/50 bg-sky-50/20 dark:bg-sky-950/20' : ''">
            <div class="flex items-center justify-between text-slate-400 dark:text-slate-500 mb-2">
                <span class="text-[11px] font-bold uppercase tracking-wider">Total Transaksi</span>
                <div class="p-1.5 rounded-lg bg-slate-100 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300 group-hover:bg-sky-100 dark:group-hover:bg-sky-900/60 group-hover:text-sky-600 transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="font-mono text-2xl font-black text-slate-900 dark:text-slate-50">{{ number_format($summary['total'] ?? $ledger->total(), 0, ',', '.') }}</span>
                <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">transaksi</span>
            </div>
            <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1">Sesuai filter aktif</p>
        </div>

        <!-- Card 2: Inbound (Barang Masuk) -->
        <div @click="filterByType('receive')"
             class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-4 shadow-2xs cursor-pointer hover:border-emerald-300 dark:hover:border-emerald-700 transition-all group"
             :class="currentType === 'receive' ? 'ring-2 ring-emerald-500 border-emerald-500/50 bg-emerald-50/20 dark:bg-emerald-950/20' : ''">
            <div class="flex items-center justify-between text-emerald-600 dark:text-emerald-400 mb-2">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Barang Masuk (Inbound)</span>
                <div class="p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 group-hover:scale-105 transition-transform">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                </div>
            </div>
            <div class="flex items-baseline gap-1.5">
                <span class="font-mono text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ number_format($summary['inbound'] ?? 0, 0, ',', '.') }}</span>
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">trx</span>
            </div>
            <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1">Pengadaan &amp; Suplier</p>
        </div>

        <!-- Card 3: Transfer Cabang -->
        <div @click="filterByType('transfer')"
             class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-4 shadow-2xs cursor-pointer hover:border-sky-300 dark:hover:border-sky-700 transition-all group"
             :class="currentType === 'transfer' ? 'ring-2 ring-sky-500 border-sky-500/50 bg-sky-50/20 dark:bg-sky-950/20' : ''">
            <div class="flex items-center justify-between text-sky-600 dark:text-sky-400 mb-2">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Transfer Cabang</span>
                <div class="p-1.5 rounded-lg bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400 group-hover:scale-105 transition-transform">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                </div>
            </div>
            <div class="flex items-baseline gap-1.5">
                <span class="font-mono text-2xl font-black text-sky-600 dark:text-sky-400">{{ number_format($summary['transfer'] ?? 0, 0, ',', '.') }}</span>
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">trx</span>
            </div>
            <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1">Barang Keluar Inter-POP</p>
        </div>

        <!-- Card 4: Serah Teknisi -->
        <div @click="filterByType('issue')"
             class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-4 shadow-2xs cursor-pointer hover:border-indigo-300 dark:hover:border-indigo-700 transition-all group"
             :class="['issue', 'install'].includes(currentType) ? 'ring-2 ring-indigo-500 border-indigo-500/50 bg-indigo-50/20 dark:bg-indigo-950/20' : ''">
            <div class="flex items-center justify-between text-indigo-600 dark:text-indigo-400 mb-2">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Serah Teknisi</span>
                <div class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 group-hover:scale-105 transition-transform">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
            </div>
            <div class="flex items-baseline gap-1.5">
                <span class="font-mono text-2xl font-black text-indigo-600 dark:text-indigo-400">{{ number_format($summary['outbound_tech'] ?? 0, 0, ',', '.') }}</span>
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">trx</span>
            </div>
            <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1">Barang Keluar Lapangan</p>
        </div>

        <!-- Card 5: Penyesuaian & Retur -->
        <div @click="filterByType('adjustment')"
             class="col-span-2 lg:col-span-1 bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-4 shadow-2xs cursor-pointer hover:border-amber-300 dark:hover:border-amber-700 transition-all group"
             :class="['return', 'adjustment', 'stock_opname', 'transfer_custody'].includes(currentType) ? 'ring-2 ring-amber-500 border-amber-500/50 bg-amber-50/20 dark:bg-amber-950/20' : ''">
            <div class="flex items-center justify-between text-amber-600 dark:text-amber-400 mb-2">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Penyesuaian &amp; Retur</span>
                <div class="p-1.5 rounded-lg bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 group-hover:scale-105 transition-transform">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
            </div>
            <div class="flex items-baseline gap-1.5">
                <span class="font-mono text-2xl font-black text-amber-600 dark:text-amber-400">{{ number_format($summary['adjust_return'] ?? 0, 0, ',', '.') }}</span>
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">trx</span>
            </div>
            <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1">Opname &amp; Koreksi</p>
        </div>

    </div>

    {{-- =========================================================================
         2. COMPREHENSIVE FILTER BAR PANEL (Sesuai Template)
         ========================================================================= --}}
    <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-4 shadow-xs space-y-4">
        
        <!-- Filter Header & Quick Range Chips -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-700/60">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-sky-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                <span class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-200">Filter Data Ledger</span>
            </div>

            <!-- Quick Preset Chips -->
            <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar">
                <button type="button" @click="setPeriodPreset('today')"
                        class="px-2.5 py-1 rounded-lg text-[11px] bg-slate-100 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-all cursor-pointer">
                    Hari Ini
                </button>
                <button type="button" @click="setPeriodPreset('this_week')"
                        class="px-2.5 py-1 rounded-lg text-[11px] bg-slate-100 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-all cursor-pointer">
                    Minggu Ini
                </button>
                <button type="button" @click="setPeriodPreset('this_month')"
                        class="px-2.5 py-1 rounded-lg text-[11px] bg-slate-100 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-all cursor-pointer">
                    Bulan Ini
                </button>
            </div>
        </div>

        <form x-ref="filterForm" action="{{ route('warehouse.history.index') }}" method="GET" class="space-y-4">
            {{-- Urutan ikut terbawa saat filter diganti (analisa-ui-ux §A5) --}}
            @if($sort !== 'date' || $sortDirection !== 'desc')
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="dir" value="{{ $sortDirection }}">
            @endif
            <input type="hidden" name="type" x-ref="typeInput" value="{{ $typeFilter }}">

            <!-- Input Controls Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
                
                <!-- 1. Search Query -->
                <div>
                    <label for="search" class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Pencarian Teks</label>
                    <div class="relative">
                        <input type="text" name="search" id="search" value="{{ $search }}"
                               placeholder="Ref, SN, Barang, PJP..." 
                               @input.debounce.500ms="$refs.filterForm.submit()"
                               onkeydown="if(event.key === 'Enter'){ event.preventDefault(); this.form.submit(); }"
                               class="w-full pl-8 pr-7 py-2 text-xs font-medium rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 focus:outline-none transition-all">
                        <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                        </div>
                        @if($search)
                        <button type="button" @click="clearSearch()" class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                            ✕
                        </button>
                        @endif
                    </div>
                </div>

                <!-- 2. Jenis Mutasi Filter -->
                <div>
                    <label for="type_select" class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Jenis Mutasi</label>
                    <select id="type_select" onchange="document.querySelector('[x-ref=typeInput]').value = this.value; this.form.submit()"
                            class="w-full px-3 py-2 text-xs font-medium rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 focus:outline-none transition-all">
                        <option value="">— Semua Jenis Mutasi —</option>
                        @foreach($types as $t)
                        <option value="{{ $t->value }}" {{ $typeFilter === $t->value ? 'selected' : '' }}>
                            {{ $t->label() }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <!-- 3. Gudang / POP Selector -->
                <div>
                    <label for="pop_id" class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Lokasi Gudang / POP</label>
                    <select name="pop_id" id="pop_id" onchange="this.form.submit()"
                            class="w-full px-3 py-2 text-xs font-medium rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 focus:outline-none transition-all">
                        <option value="">— Semua Gudang Terjangkau —</option>
                        @foreach($pops as $pop)
                        <option value="{{ $pop->id }}" {{ (string) $popFilter === (string) $pop->id ? 'selected' : '' }}>
                            {{ $pop->name }} ({{ strtoupper($pop->type) }})
                        </option>
                        @endforeach
                    </select>
                </div>

                <!-- 4. Date Range Inputs -->
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Rentang Tanggal</label>
                    <div class="grid grid-cols-2 gap-1.5">
                        <input type="date" name="date_from" id="date_from" value="{{ $dateFrom }}" onchange="this.form.submit()"
                               class="w-full px-2 py-1.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 focus:outline-none">
                        <input type="date" name="date_to" id="date_to" value="{{ $dateTo }}" onchange="this.form.submit()"
                               class="w-full px-2 py-1.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 focus:outline-none">
                    </div>
                </div>

            </div>

            <!-- Action Buttons & Advanced Filter Toggle -->
            <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
                <div class="flex items-center gap-2">
                    <button type="button" @click="showAdvancedFilters = !showAdvancedFilters"
                            class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                            :class="showAdvancedFilters ? 'text-sky-600 dark:text-sky-400 border-sky-300 dark:border-sky-700 bg-sky-50/50 dark:bg-sky-950/30' : ''">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75"/></svg>
                        <span>Opsi Lanjutan</span>
                        <svg class="w-3 h-3 transition-transform duration-200" :class="showAdvancedFilters ? 'rotate-180 text-sky-500' : 'text-slate-400'" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    @if($hasActiveFilters)
                    <a href="{{ route('warehouse.history.index') }}" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg border border-slate-200 dark:border-slate-700 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        <span>Reset Filter</span>
                    </a>
                    @endif
                </div>

                @if($hasActiveFilters)
                <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-sky-50 dark:bg-sky-950/50 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800 text-[11px] font-medium">
                        <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
                        Filter Aktif
                    </span>
                </div>
                @endif
            </div>

            <!-- Collapsible Advanced Filters -->
            <div x-show="showAdvancedFilters" x-cloak
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 -translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-2"
                 class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-3 border-t border-slate-100 dark:border-slate-700/60 text-xs">
                
                <!-- Filter Kondisi Fisik -->
                <div>
                    <label for="condition" class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Kondisi Fisik Barang (SN)</label>
                    <select name="condition" id="condition" onchange="this.form.submit()" class="w-full px-3 py-2 text-xs font-medium rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 focus:outline-none">
                        <option value="">— Semua Kondisi —</option>
                        <option value="new" {{ $conditionFilter === 'new' ? 'selected' : '' }}>Baru</option>
                        <option value="unchecked" {{ $conditionFilter === 'unchecked' ? 'selected' : '' }}>Bekas — Belum Dicek</option>
                        <option value="checked_good" {{ $conditionFilter === 'checked_good' ? 'selected' : '' }}>Bekas — Sudah Dicek</option>
                        <option value="damaged" {{ $conditionFilter === 'damaged' ? 'selected' : '' }}>Bekas — Rusak</option>
                    </select>
                </div>

                <!-- Filter Alasan Penyesuaian -->
                <div>
                    <label for="adjustment_reason" class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Alasan Penyesuaian Saldo</label>
                    <select name="adjustment_reason" id="adjustment_reason" onchange="this.form.submit()" class="w-full px-3 py-2 text-xs font-medium rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 focus:outline-none">
                        <option value="">— Semua Alasan —</option>
                        <option value="lost" {{ $adjustmentReasonFilter === 'lost' ? 'selected' : '' }}>Hilang</option>
                        <option value="damaged" {{ $adjustmentReasonFilter === 'damaged' ? 'selected' : '' }}>Rusak</option>
                        <option value="scrapped" {{ $adjustmentReasonFilter === 'scrapped' ? 'selected' : '' }}>Scrap</option>
                        <option value="quarantine" {{ $adjustmentReasonFilter === 'quarantine' ? 'selected' : '' }}>Karantina</option>
                        <option value="shrinkage_on_return" {{ $adjustmentReasonFilter === 'shrinkage_on_return' ? 'selected' : '' }}>Selisih Saat Return</option>
                        <option value="other" {{ $adjustmentReasonFilter === 'other' ? 'selected' : '' }}>Lainnya</option>
                    </select>
                </div>
            </div>
        </form>
    </div>

    {{-- =========================================================================
         3. MUTATION DATA DISPLAY CONTAINER (TABLE + MOBILE CARDS)
         ========================================================================= --}}
    <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl overflow-hidden shadow-xs">
        
        <!-- Table Action / Active Filter Info Bar -->
        <div class="px-4 py-3 bg-slate-50/80 dark:bg-slate-900/60 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between gap-3 text-xs">
            <div class="flex items-center gap-2 text-slate-500 dark:text-slate-400">
                <span>Menampilkan <strong class="text-slate-800 dark:text-slate-200 font-mono">{{ $ledger->count() }}</strong> dari <strong class="text-slate-800 dark:text-slate-200 font-mono">{{ $ledger->total() }}</strong> catatan ledger</span>
                @if($hasActiveFilters)
                <span class="text-[10px] px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 font-bold">
                    Filtered
                </span>
                @endif
            </div>

            <div class="flex items-center gap-2">
                <span class="text-slate-400 hidden sm:inline">Tipe Aktif:</span>
                <span class="font-bold text-sky-600 dark:text-sky-400">
                    {{ $typeFilter ? ($types[array_search($typeFilter, array_column($types, 'value'))]->label() ?? strtoupper($typeFilter)) : 'Semua Mutasi' }}
                </span>
            </div>
        </div>

        @if($ledger->isEmpty())
        <div class="py-16 px-6 text-center">
            <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 dark:bg-slate-700/60 flex items-center justify-center text-slate-400 dark:text-slate-500 mb-3">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                </svg>
            </div>
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200">Tidak ada mutasi yang cocok dengan filter</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-sm mx-auto">Coba ubah kata kunci pencarian, rentang tanggal periode, atau reset filter Anda.</p>
            @if($hasActiveFilters)
            <a href="{{ route('warehouse.history.index') }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 mt-4 rounded-lg text-xs font-semibold bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <span>Reset Semua Filter</span>
            </a>
            @endif
        </div>
        @else

        @php
            // Satu sumber hitung dipakai 2 kali (tabel desktop & card mobile) — analisa §B6.
            // Sebelumnya tiap blok render punya @php sendiri yang menyusun ulang
            // $detailItemData (rute, label, resolusi $detailRoute via route()) dari
            // $group yang sama — logic bisnis dobel, rawan drift kalau salah satu
            // diubah dan yang lain lupa. Dihitung sekali di sini per $group->key.
            $ledgerMeta = $ledger->mapWithKeys(function ($group) use ($typeColor, $defaultColor) {
                $txn = $group->representative;
                $rowColor = $typeColor[$txn->type->value ?? ''] ?? $defaultColor;

                $detailRoute = match ($txn->type->value ?? '') {
                    'receive' => auth()->user()->hasPermission('warehouse_transfer.view') && $txn->reference_number
                        ? route('warehouse.receive.show', $txn->reference_number) : null,
                    'transfer' => auth()->user()->hasPermission('warehouse_transfer.view') && $txn->inventory_transfer_id
                        ? route('warehouse.transfers.show', $txn->inventory_transfer_id) : null,
                    'issue' => auth()->user()->hasPermission('warehouse_issue.view') && $txn->reference_number
                        ? route('warehouse.issues.show', $txn->reference_number) : null,
                    default => null,
                };

                $detailItemData = [
                    'id' => $txn->id,
                    'ref_code' => $txn->reference_number ?? ('TRX-'.$txn->id),
                    'timestamp' => $group->createdAt->translatedFormat('d M Y, H:i').' WIB',
                    'type' => $txn->type->value ?? 'general',
                    'type_label' => $group->typeLabel,
                    'origin' => $txn->fromPop->name ?? ($txn->fromTechnician->name ?? 'Pengadaan (Baru)'),
                    'destination' => ($txn->type->value === 'install')
                        ? (($txn->fopTask?->customer ?? $txn->serial?->customer)?->full_name ?? 'Pelanggan')
                        : ($txn->toPop->name ?? ($txn->toTechnician->name ?? ($txn->transfer?->toPop ? $txn->transfer->toPop->name.' (menunggu konfirmasi)' : 'Pelanggan / Luar'))),
                    'item_name' => ($group->lineCount > 1) ? "{$group->itemCount} Jenis Barang" : $txn->item->name,
                    'category' => ($group->lineCount > 1) ? $group->lines->pluck('item.name')->unique()->take(3)->implode(', ') : ($txn->item->category?->name ?? 'Material Logistik'),
                    'qty' => ($group->lineCount > 1) ? $group->lineCount : ($txn->serial ? 1 : rtrim(rtrim(number_format((float) $txn->qty, 2, ',', '.'), '0'), ',')),
                    'unit' => ($group->lineCount > 1) ? 'baris' : ($txn->serial ? 'unit' : $txn->item->unit),
                    'sn_list' => $group->lines->filter(fn ($l) => $l->serial)->pluck('serial.serial_number')->values()->all(),
                    'operator' => $txn->createdBy?->name ?? 'Sistem',
                    'role' => $txn->createdBy?->role?->name ?? 'Petugas Logistik',
                    'status' => ($txn->type->value === 'transfer' && $txn->to_pop_id === null && $txn->to_technician_id === null) ? 'In-Transit' : 'Selesai',
                    'source_doc' => $txn->reference_number ?? ('ID-'.$txn->id),
                    'notes' => $txn->notes ?? ($txn->reason ?? 'Tidak ada catatan tambahan.'),
                    'evidence_url' => $txn->evidence_file_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($txn->evidence_file_path) : null,
                    'url' => $detailRoute,
                ];

                return [$group->key => [
                    'rowColor' => $rowColor,
                    'detailRoute' => $detailRoute,
                    'detailItemData' => $detailItemData,
                ]];
            });
        @endphp

        {{-- Bar urut (analisa-ui-ux §A5) — dipakai desktop & mobile. Tanggal
             (default) atau Tipe dokumen; klik ulang membalik arah. --}}
        @php
            $histSortLink = fn (string $key) => request()->fullUrlWithQuery([
                'sort' => $key,
                'dir' => ($sort === $key && $sortDirection === 'desc') ? 'asc' : 'desc',
                'page' => null,
            ]);
            $histSortIcon = fn (string $key) => $sort !== $key ? '↕' : ($sortDirection === 'desc' ? '↓' : '↑');
        @endphp
        <div class="flex items-center gap-2 px-4 py-2.5 border-b border-slate-100 dark:border-slate-700/60 text-[11px]" aria-label="Urutkan riwayat">
            <span class="font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Urutkan</span>
            <a href="{{ $histSortLink('date') }}" aria-sort="{{ $sort === 'date' ? ($sortDirection === 'desc' ? 'descending' : 'ascending') : 'none' }}"
               class="inline-flex items-center gap-1 px-2 py-1 rounded-lg font-semibold {{ $sort === 'date' ? 'bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-300' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700' }}">Waktu <span class="font-mono" aria-hidden="true">{{ $histSortIcon('date') }}</span></a>
            <a href="{{ $histSortLink('type') }}" aria-sort="{{ $sort === 'type' ? ($sortDirection === 'desc' ? 'descending' : 'ascending') : 'none' }}"
               class="inline-flex items-center gap-1 px-2 py-1 rounded-lg font-semibold {{ $sort === 'type' ? 'bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-300' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700' }}">Tipe Dokumen <span class="font-mono" aria-hidden="true">{{ $histSortIcon('type') }}</span></a>
        </div>

        <!-- A. DESKTOP / LAPTOP HIGH DENSITY TABLE (md+) -->
        <div class="hidden md:block overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-100/70 dark:bg-slate-800/80 border-b border-slate-200 dark:border-slate-700 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                        <th class="py-3 px-4 whitespace-nowrap">Waktu &amp; Ref TRX</th>
                        <th class="py-3 px-4">Rute (Asal → Tujuan)</th>
                        <th class="py-3 px-4">Barang &amp; Volume</th>
                        <th class="py-3 px-4">Nomor Seri (SN)</th>
                        <th class="py-3 px-4">Operator / PJP</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 bg-white dark:bg-slate-800">
                    @foreach($ledger as $group)
                    @php
                        $txn = $group->representative;
                        ['rowColor' => $rowColor, 'detailRoute' => $detailRoute, 'detailItemData' => $detailItemData] = $ledgerMeta[$group->key];

                        $serialConditionVal = $txn->serial?->condition?->value ?? 'new';
                        $conditionBadge = match(true) {
                            $serialConditionVal === 'new' => ['label' => 'Baru', 'class' => 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800'],
                            $serialConditionVal === 'used_damaged' => ['label' => 'Bekas — Rusak', 'class' => 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400 border-rose-200 dark:border-rose-800'],
                            $txn->serial?->condition_checked_at !== null => ['label' => 'Bekas — Sudah Dicek', 'class' => 'bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-400 border-sky-200 dark:border-sky-800'],
                            default => ['label' => 'Bekas — Belum Dicek', 'class' => 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-800'],
                        };
                    @endphp
                    <tr class="hover:bg-sky-50/40 dark:hover:bg-slate-700/40 transition-colors group cursor-pointer"
                        @if($detailRoute) onclick="window.location='{{ $detailRoute }}'" @endif>
                        
                        <!-- 1. Waktu & Ref TRX -->
                        <td class="py-3.5 px-4 align-top">
                            <div class="flex flex-col">
                                <span class="font-mono font-bold text-sky-600 dark:text-sky-400 group-hover:underline flex items-center gap-1">
                                    <span>#{{ $txn->reference_number ?? ('TRX-'.$txn->id) }}</span>
                                    <svg class="w-3 h-3 text-slate-400 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                </span>
                                <span class="text-[11px] text-slate-700 dark:text-slate-300 font-medium mt-0.5">{{ $group->createdAt->translatedFormat('d M Y') }}</span>
                                <span class="text-[10px] text-slate-400 font-mono">{{ $group->createdAt->format('H:i') }} WIB ({{ $group->createdAt->diffForHumans() }})</span>
                            </div>
                        </td>

                        <!-- 2. Rute Asal -> Tujuan -->
                        <td class="py-3.5 px-4 align-top">
                            <div class="flex items-center gap-1.5 font-medium text-slate-700 dark:text-slate-200 flex-wrap">
                                <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-700/60 text-slate-700 dark:text-slate-300">
                                    {{ $txn->fromPop->name ?? ($txn->fromTechnician->name ?? 'Pengadaan (Baru)') }}
                                </span>
                                <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                                @if($txn->to_pop_id === null && $txn->to_technician_id === null && $txn->type->value === 'transfer' && $txn->transfer?->toPop)
                                <span class="px-2 py-0.5 rounded bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800 font-semibold">
                                    {{ $txn->transfer->toPop->name }} <span class="text-[10px] font-normal opacity-80">(menunggu konfirmasi)</span>
                                </span>
                                @elseif($txn->type->value === 'install')
                                @php $cust = $txn->fopTask?->customer ?? $txn->serial?->customer; @endphp
                                <span class="px-2 py-0.5 rounded bg-violet-50 dark:bg-violet-950/40 text-violet-700 dark:text-violet-300 border border-violet-200 dark:border-violet-800/50 font-semibold">
                                    {{ $cust ? 'Pelanggan: '.$cust->full_name : 'Pelanggan' }}
                                </span>
                                @else
                                <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-700/60 text-slate-700 dark:text-slate-300 font-semibold text-sky-600 dark:text-sky-400">
                                    {{ $txn->toPop->name ?? ($txn->toTechnician->name ?? 'Pelanggan / Luar') }}
                                </span>
                                @endif
                            </div>
                        </td>

                        <!-- 4. Barang & Volume -->
                        <td class="py-3.5 px-4 align-top">
                            <div>
                                @if($group->lineCount > 1)
                                <div class="font-bold text-slate-900 dark:text-slate-100">
                                    @if($detailRoute)
                                    <a href="{{ $detailRoute }}" class="hover:underline text-sky-600 dark:text-sky-400">{{ $group->itemCount }} Jenis Barang</a>
                                    @else
                                    <span>{{ $group->itemCount }} Jenis Barang</span>
                                    @endif
                                </div>
                                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 line-clamp-2">
                                    {{ $group->lines->pluck('item.name')->unique()->take(2)->implode(', ') }}{{ $group->itemCount > 2 ? ', +'.($group->itemCount - 2).' lainnya' : '' }}
                                </div>
                                @else
                                <div class="font-bold text-slate-900 dark:text-slate-100">
                                    @if($detailRoute)
                                    <a href="{{ $detailRoute }}" class="hover:underline text-slate-900 dark:text-slate-100 group-hover:text-sky-600 dark:group-hover:text-sky-400">{{ $txn->item->name }}</a>
                                    @else
                                    <span>{{ $txn->item->name }}</span>
                                    @endif
                                </div>
                                @if($txn->item->category)
                                <div class="text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">
                                    {{ $txn->item->category->name }}
                                </div>
                                @endif
                                @endif

                                <div class="mt-1">
                                    <span class="font-mono font-extrabold text-sky-700 dark:text-sky-300 bg-sky-100 dark:bg-sky-950/80 px-2 py-0.5 rounded text-[11px]">
                                        @if($group->lineCount > 1)
                                        {{ $group->lineCount }} baris
                                        @else
                                        {{ $txn->serial ? '1 unit' : rtrim(rtrim(number_format((float) $txn->qty, 2, ',', '.'), '0'), ',').' '.$txn->item->unit }}
                                        @endif
                                    </span>
                                </div>
                            </div>
                        </td>

                        <!-- 5. Nomor Seri (SN) -->
                        <td class="py-3.5 px-4 align-top">
                            @if($txn->serial)
                            <div class="space-y-1">
                                <div>
                                    @if(auth()->user()->hasPermission('warehouse_traceability.view'))
                                    <a href="{{ route('warehouse.traceability.index', ['sn' => $txn->serial->serial_number]) }}"
                                       onclick="event.stopPropagation()"
                                       class="font-mono text-[11px] font-bold text-sky-600 dark:text-sky-400 hover:underline bg-sky-50 dark:bg-sky-950/40 px-1.5 py-0.5 rounded border border-sky-200 dark:border-sky-800"
                                       title="Klik untuk melacak jejak serial number">
                                        SN: {{ $txn->serial->serial_number }}
                                    </a>
                                    @else
                                    <span class="font-mono text-[11px] font-semibold text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 rounded">
                                        SN: {{ $txn->serial->serial_number }}
                                    </span>
                                    @endif
                                </div>
                                <div>
                                    <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold border {{ $conditionBadge['class'] }}">
                                        {{ $conditionBadge['label'] }}
                                    </span>
                                </div>
                            </div>
                            @elseif($txn->lot_no)
                            <div class="font-mono text-[11px] text-slate-500 dark:text-slate-400">
                                <span class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700/60">Lot: {{ $txn->lot_no }}</span>
                            </div>
                            @else
                            <span class="text-[11px] text-slate-400 italic">Non-SN / Curah</span>
                            @endif
                        </td>

                        <!-- 6. Operator / PJP -->
                        <td class="py-3.5 px-4 align-top">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-[10px] flex items-center justify-center shrink-0">
                                    {{ strtoupper(substr($txn->createdBy?->name ?? 'SU', 0, 2)) }}
                                </div>
                                <div class="truncate max-w-[120px]">
                                    <p class="font-semibold text-slate-800 dark:text-slate-200 truncate">{{ $txn->createdBy?->name ?? 'Sistem' }}</p>
                                    <p class="text-[10px] text-slate-400 truncate">{{ $txn->createdBy?->role?->name ?? 'Operator' }}</p>
                                </div>
                            </div>
                        </td>

                        <!-- 7. Status -->
                        <td class="py-3.5 px-4 text-center whitespace-nowrap align-top">
                            @if($txn->type->value === 'transfer' && $txn->to_pop_id === null && $txn->to_technician_id === null)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                <span>Transit</span>
                            </span>
                            @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                <span>Selesai</span>
                            </span>
                            @endif
                        </td>

                        <!-- 8. Actions Button -->
                        <td class="py-3.5 px-4 text-right whitespace-nowrap align-top" onclick="event.stopPropagation()">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" @click="openDetail({{ json_encode($detailItemData) }})"
                                        class="p-1.5 rounded-lg text-slate-400 hover:text-sky-600 hover:bg-sky-50 dark:hover:bg-slate-700 transition-colors cursor-pointer"
                                        title="Lihat Detail Mutasi">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                                @if($txn->evidence_file_path)
                                <button type="button" @click="openDetail({{ json_encode($detailItemData) }})"
                                        class="p-1.5 rounded-lg text-emerald-500 hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-slate-700 transition-colors cursor-pointer"
                                        title="Lihat Bukti Foto BAP">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </button>
                                @endif
                            </div>
                        </td>

                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- B. MOBILE & TABLET CARD STACK VIEW (< md Screens) -->
        <div class="md:hidden divide-y divide-slate-100 dark:divide-slate-700/60">
            @foreach($ledger as $group)
            @php
                $txn = $group->representative;
                ['rowColor' => $rowColor, 'detailRoute' => $detailRoute, 'detailItemData' => $detailItemData] = $ledgerMeta[$group->key];
            @endphp
            <div class="p-4 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors space-y-3 cursor-pointer"
                 @click="openDetail({{ json_encode($detailItemData) }})">
                
                <!-- Card Header: Type, Status, Ref Code -->
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $rowColor['bg'] }} {{ $rowColor['text'] }} {{ $rowColor['border'] }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $rowColor['dot'] }}"></span>
                            <span>{{ $group->typeLabel }}</span>
                        </span>
                        <p class="font-mono font-bold text-sky-600 dark:text-sky-400 text-xs mt-1">#{{ $txn->reference_number ?? ('TRX-'.$txn->id) }}</p>
                    </div>
                    <div class="text-right">
                        @if($txn->type->value === 'transfer' && $txn->to_pop_id === null && $txn->to_technician_id === null)
                        <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">Transit</span>
                        @else
                        <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">Selesai</span>
                        @endif
                        <p class="text-[10px] text-slate-400 mt-1">{{ $group->createdAt->translatedFormat('d M Y, H:i') }}</p>
                    </div>
                </div>

                <!-- Item Info & Quantity -->
                <div class="bg-slate-50 dark:bg-slate-900/50 p-2.5 rounded-lg border border-slate-100 dark:border-slate-800 flex items-center justify-between">
                    <div>
                        <p class="font-bold text-xs text-slate-800 dark:text-slate-100">
                            {{ ($group->lineCount > 1) ? "{$group->itemCount} Jenis Barang" : $txn->item->name }}
                        </p>
                        <p class="text-[10px] text-slate-400">{{ $txn->item->category?->name ?? 'Material' }}</p>
                    </div>
                    <span class="font-mono font-extrabold text-sky-700 dark:text-sky-300 bg-sky-100 dark:bg-sky-950 px-2 py-1 rounded text-xs">
                        @if($group->lineCount > 1)
                        {{ $group->lineCount }} baris
                        @else
                        {{ $txn->serial ? '1 unit' : rtrim(rtrim(number_format((float) $txn->qty, 2, ',', '.'), '0'), ',').' '.$txn->item->unit }}
                        @endif
                    </span>
                </div>

                <!-- Route & Operator -->
                <div class="flex items-center justify-between text-xs text-slate-600 dark:text-slate-300 pt-1">
                    <div class="flex items-center gap-1 font-medium">
                        <span>{{ $txn->fromPop->name ?? ($txn->fromTechnician->name ?? 'Pengadaan') }}</span>
                        <svg class="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        <span class="font-bold text-sky-600 dark:text-sky-400">
                            {{ $txn->toPop->name ?? ($txn->toTechnician->name ?? ($txn->type->value === 'install' ? 'Pelanggan' : 'Gudang')) }}
                        </span>
                    </div>
                    <span class="text-[11px] text-slate-400">{{ $txn->createdBy?->name ?? 'Sistem' }}</span>
                </div>

                <!-- SN Preview if available -->
                @if($txn->serial)
                <div class="flex items-center gap-1 pt-1">
                    <span class="text-[10px] text-slate-400">SN:</span>
                    <span class="font-mono text-[10px] bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 rounded font-bold text-slate-700 dark:text-slate-300">
                        {{ $txn->serial->serial_number }}
                    </span>
                </div>
                @endif

            </div>
            @endforeach
        </div>

        <!-- Pagination Controls Footer -->
        <div class="px-5 py-4 border-t border-slate-100 dark:border-slate-700/60 bg-slate-50/40 dark:bg-slate-900/20 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
            <div class="text-slate-500 dark:text-slate-400">
                Menampilkan <span class="font-semibold font-mono text-slate-700 dark:text-slate-200">{{ $ledger->firstItem() ?? 0 }}</span>–<span class="font-semibold font-mono text-slate-700 dark:text-slate-200">{{ $ledger->lastItem() ?? 0 }}</span> dari <span class="font-semibold font-mono text-slate-700 dark:text-slate-200">{{ $ledger->total() }}</span> mutasi
            </div>
            <div>
                {{ $ledger->links() }}
            </div>
        </div>
        @endif
    </div>

    {{-- =========================================================================
         4. MUTATION DETAIL SLIDE-OVER DRAWER (Sesuai Template)
         ========================================================================= --}}
    <div x-show="detailDrawerOpen" 
         x-cloak
         @keydown.escape.window="closeDetail()"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-hidden bg-slate-900/60 dark:bg-slate-950/70 backdrop-blur-xs flex justify-end"
         style="display: none;">
        
        <div @click.outside="closeDetail()" 
             x-show="detailDrawerOpen"
             x-transition:enter="transition ease-out duration-300 transform"
             x-transition:enter-start="translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in duration-200 transform"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="translate-x-full"
             class="w-full max-w-xl bg-white dark:bg-slate-900 h-full shadow-2xl border-l border-slate-200 dark:border-slate-800 flex flex-col justify-between overflow-hidden">
            
            <!-- Drawer Header -->
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50/80 dark:bg-slate-900/80 shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-sky-100 dark:bg-sky-950 text-sky-600 dark:text-sky-400 flex items-center justify-center font-bold">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div>
                        <h3 class="font-extrabold text-slate-900 dark:text-slate-100 text-sm">Detail Mutasi Material</h3>
                        <p class="font-mono text-xs text-sky-600 dark:text-sky-400 font-bold" x-text="activeDetail?.ref_code"></p>
                    </div>
                </div>

                <button @click="closeDetail()" class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Drawer Body Scrollable Content -->
            <div class="flex-1 overflow-y-auto p-6 space-y-5 custom-scrollbar text-xs">
                
                <!-- Status & Type Header Info -->
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase text-slate-400 tracking-wider">Jenis Transaksi</span>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800">
                            <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
                            <span x-text="activeDetail?.type_label"></span>
                        </span>
                    </div>

                    <div class="flex items-center justify-between pt-2 border-t border-slate-200/60 dark:border-slate-700/60">
                        <span class="text-[10px] font-bold uppercase text-slate-400 tracking-wider">Status Ledger</span>
                        <span class="px-2.5 py-0.5 rounded text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300" x-text="activeDetail?.status"></span>
                    </div>

                    <div class="flex items-center justify-between pt-2 border-t border-slate-200/60 dark:border-slate-700/60">
                        <span class="text-[10px] font-bold uppercase text-slate-400 tracking-wider">Waktu Eksekusi</span>
                        <span class="font-semibold text-slate-800 dark:text-slate-200 font-mono" x-text="activeDetail?.timestamp"></span>
                    </div>
                </div>

                <!-- Route Flow Graph (Origin -> Destination) -->
                <div>
                    <h4 class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2">Alur Perpindahan Stok</h4>
                    <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-700/80 bg-white dark:bg-slate-800 grid grid-cols-2 gap-4 text-center relative">
                        <div class="space-y-1">
                            <span class="text-[10px] font-bold text-slate-400 uppercase">Asal</span>
                            <p class="font-extrabold text-sm text-slate-800 dark:text-slate-100" x-text="activeDetail?.origin"></p>
                        </div>
                        <div class="space-y-1">
                            <span class="text-[10px] font-bold text-sky-500 uppercase">Tujuan</span>
                            <p class="font-extrabold text-sm text-sky-600 dark:text-sky-400" x-text="activeDetail?.destination"></p>
                        </div>
                    </div>
                </div>

                <!-- Item & Volume Summary -->
                <div>
                    <h4 class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2">Rincian Barang</h4>
                    <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-700/80 bg-white dark:bg-slate-800 space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-black text-sm text-slate-900 dark:text-slate-50" x-text="activeDetail?.item_name"></p>
                                <p class="text-xs text-slate-400" x-text="activeDetail?.category"></p>
                            </div>
                            <span class="font-mono text-base font-black text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950 px-3 py-1 rounded-lg border border-sky-200 dark:border-sky-800" x-text="`${activeDetail?.qty} ${activeDetail?.unit}`"></span>
                        </div>
                    </div>
                </div>

                <!-- Serial Numbers (SN) Breakdown List -->
                <div x-show="activeDetail?.sn_list && activeDetail?.sn_list.length > 0">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Daftar Serial Number (SN) Terdaftar</h4>
                        <span class="text-[10px] font-bold text-sky-600 dark:text-sky-400 font-mono" x-text="`${activeDetail?.sn_list.length} unit SN`"></span>
                    </div>

                    <div class="p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 max-h-40 overflow-y-auto custom-scrollbar">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5 font-mono text-[11px]">
                            <template x-for="(sn, idx) in activeDetail?.sn_list" :key="idx">
                                <div class="px-2.5 py-1.5 rounded bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-between">
                                    <span class="font-bold text-slate-800 dark:text-slate-200" x-text="sn"></span>
                                    <span class="text-[9px] text-emerald-600 font-bold">OK</span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Physical Evidence / BAP Photo Preview if exists -->
                <div x-show="activeDetail?.evidence_url" class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-700 space-y-2 bg-slate-50/50 dark:bg-slate-800/30">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Bukti Fisik &amp; BAP</p>
                    <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-900/5">
                        <img :src="activeDetail?.evidence_url"
                             alt="Bukti fisik BAP"
                             class="w-full max-h-64 object-contain mx-auto">
                    </div>
                </div>

                <!-- Responsible Person & Source Reference -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                        <span class="text-[10px] font-bold uppercase text-slate-400">Operator / PJP</span>
                        <p class="font-bold text-slate-800 dark:text-slate-100 text-xs mt-1" x-text="activeDetail?.operator"></p>
                        <p class="text-[10px] text-slate-400" x-text="activeDetail?.role"></p>
                    </div>

                    <div class="p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                        <span class="text-[10px] font-bold uppercase text-slate-400">Referensi Tiket/Doc</span>
                        <p class="font-mono font-bold text-sky-600 dark:text-sky-400 text-xs mt-1" x-text="activeDetail?.source_doc || 'N/A'"></p>
                        <p class="text-[10px] text-slate-400">Dokumen Acuan</p>
                    </div>
                </div>

                <!-- Notes / Catatan -->
                <div>
                    <h4 class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Catatan Keterangan</h4>
                    <p class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 italic text-xs leading-relaxed" x-text="activeDetail?.notes || 'Tidak ada catatan tambahan.'"></p>
                </div>

            </div>

            <!-- Drawer Footer Action -->
            <div class="p-4 border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 flex items-center justify-between gap-3 shrink-0">
                <button type="button" @click="closeDetail()" class="px-4 py-2 text-xs font-semibold rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                    Tutup
                </button>

                <template x-if="activeDetail?.url">
                    <a :href="activeDetail?.url" class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold rounded-lg bg-sky-600 hover:bg-sky-700 text-white shadow-xs transition-colors">
                        <span>Lihat Dokumen Lengkap</span>
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                    </a>
                </template>
            </div>

        </div>
    </div>

</div>

@endsection
