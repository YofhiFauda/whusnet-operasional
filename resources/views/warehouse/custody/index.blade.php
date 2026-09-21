@extends('layouts.app')

@section('title', 'Barang di Tangan Teknisi - Whusnet Operasional')
@section('page_title', 'Barang di Tangan Teknisi')

@section('content')

<x-warehouse.header active="custody" />

<!-- KPI Ringkasan Lapangan (rancangan-layout.md §8.1) — meteran (kabel dkk)
     dan batch non-meter (aksesoris/pcs) SENGAJA dipisah, bukan dijumlah
     campur satuan (lihat komentar WarehouseCustodyController::index()). -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-4 shadow-xs">
        <span class="block text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Perangkat Aktif (SN)</span>
        <div class="mt-1 flex items-baseline gap-1.5">
            <span class="text-2xl font-extrabold text-cyan-600 dark:text-cyan-400">{{ $kpi['serial_count'] }}</span>
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Unit</span>
        </div>
    </div>
    <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-4 shadow-xs">
        <span class="block text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Kabel Tergelar di Lapangan</span>
        <div class="mt-1 flex items-baseline gap-1.5">
            <span class="text-2xl font-extrabold text-purple-600 dark:text-purple-400">{{ rtrim(rtrim(number_format($kpi['meter_total'], 2, ',', '.'), '0'), ',') }}</span>
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Meter</span>
        </div>
    </div>
    <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-4 shadow-xs">
        <span class="block text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Material Pasif</span>
        <div class="mt-1 flex items-baseline gap-1.5">
            <span class="text-2xl font-extrabold text-indigo-600 dark:text-indigo-400">{{ $kpi['material_batch_count'] }}</span>
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Batch</span>
        </div>
    </div>
    <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-4 shadow-xs">
        <span class="block text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Teknisi Bertugas</span>
        <div class="mt-1 flex items-baseline gap-1.5">
            <span class="text-2xl font-extrabold text-slate-800 dark:text-slate-100">{{ $kpi['technician_count'] }}</span>
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Orang</span>
        </div>
    </div>
</div>

<div x-data="{ activeTab: 'serials' }">
    <!-- Filter & Summary Bar (Naked Filter Bar) -->
    <div class="mb-5">
        <form action="{{ route('warehouse.custody.index') }}" method="GET" class="flex flex-wrap items-end gap-3">
            <div class="w-full sm:w-64">
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Filter Teknisi Lapangan</label>
                <select name="technician_id" class="w-full px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                    <option value="">— Semua Teknisi Aktif —</option>
                    @foreach($technicians as $technician)
                    <option value="{{ $technician->id }}" {{ (string) $technicianFilter === (string) $technician->id ? 'selected' : '' }}>
                        {{ $technician->name }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="w-full sm:w-56">
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Gudang Asal Penyerahan</label>
                <select name="pop_id" class="w-full px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                    <option value="">— Semua Gudang —</option>
                    @foreach($pops as $pop)
                    <option value="{{ $pop->id }}" {{ (string) $popFilter === (string) $pop->id ? 'selected' : '' }}>{{ $pop->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1 min-w-[12rem]">
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Cari Nama Barang / SN / Lot</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="Ketik untuk mencari..."
                       class="w-full px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
            </div>
            <div class="flex items-center gap-2">
                <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 text-white text-xs font-semibold rounded-lg shadow-xs transition-colors cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                    <span>Filter</span>
                </button>
                @if($technicianFilter || $popFilter || $search)
                <a href="{{ route('warehouse.custody.index') }}" class="px-3 py-2 text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700/50 transition-colors">
                    Reset
                </a>
                @endif
            </div>
        </form>

        <!-- Segmented Tab Switcher -->
        <div class="mt-4 pt-3.5 border-t border-slate-100 dark:border-slate-700/60 flex items-center gap-2">
            <button @click="activeTab = 'serials'"
                    type="button"
                    :class="activeTab === 'serials' ? 'bg-sky-500 text-white shadow-xs shadow-sky-500/25' : 'bg-slate-100 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-bold transition-all cursor-pointer">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
                <span>Perangkat Serial Number</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono" :class="activeTab === 'serials' ? 'bg-white/20 text-white' : 'bg-slate-200 dark:bg-slate-600 text-slate-700 dark:text-slate-300'">
                    {{ $serials->count() }}
                </span>
            </button>

            <button @click="activeTab = 'materials'"
                    type="button"
                    :class="activeTab === 'materials' ? 'bg-sky-500 text-white shadow-xs shadow-sky-500/25' : 'bg-slate-100 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-bold transition-all cursor-pointer">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                <span>Perangkat Pasif</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono" :class="activeTab === 'materials' ? 'bg-white/20 text-white' : 'bg-slate-200 dark:bg-slate-600 text-slate-700 dark:text-slate-300'">
                    {{ $custodies->count() }}
                </span>
            </button>

            <button @click="activeTab = 'rolls'"
                    type="button"
                    :class="activeTab === 'rolls' ? 'bg-amber-500 text-white shadow-xs shadow-amber-500/25' : 'bg-slate-100 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-bold transition-all cursor-pointer">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a1 1 0 001-1v-4a1 1 0 00-1-1H9a1 1 0 00-1 1v4a1 1 0 001 1zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                <span>Roll Kabel</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono" :class="activeTab === 'rolls' ? 'bg-white/20 text-white' : 'bg-slate-200 dark:bg-slate-600 text-slate-700 dark:text-slate-300'">
                    {{ $rolls->count() }}
                </span>
            </button>

            {{-- Return dari pelanggan (ADHOC-88): modem hasil pengambilan alat yang masih dipegang teknisi --}}
            <button @click="activeTab = 'returns'"
                    type="button"
                    :class="activeTab === 'returns' ? 'bg-teal-500 text-white shadow-xs shadow-teal-500/25' : 'bg-slate-100 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-bold transition-all cursor-pointer">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/></svg>
                <span>Return dari Pelanggan</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono" :class="activeTab === 'returns' ? 'bg-white/20 text-white' : 'bg-slate-200 dark:bg-slate-600 text-slate-700 dark:text-slate-300'">
                    {{ $returned->count() }}
                </span>
            </button>
        </div>
    </div>

    <!-- TAB 1: PERANGKAT SERIAL NUMBER -->
    <div x-show="activeTab === 'serials'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl shadow-xs overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-lg bg-cyan-50 dark:bg-cyan-950/50 text-cyan-600 dark:text-cyan-400 flex items-center justify-center border border-cyan-100 dark:border-cyan-800/60">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
                    </div>
                    <div>
                        <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">Perangkat Aktif (Serial Number) Di Tangan Teknisi</h3>
                        <p class="text-[11px] text-slate-400">Modem ONT, Router, dan Radio Wireless yang diserahkan untuk tugas pasang/ganti</p>
                    </div>
                </div>
            </div>

            @if($serials->isEmpty())
            <div class="p-16 text-center">
                <div class="w-14 h-14 mx-auto mb-3 rounded-lg bg-slate-100 dark:bg-slate-700/50 flex items-center justify-center text-slate-400">
                    <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Tidak ada perangkat aktif di tangan teknisi</h4>
                <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">Semua perangkat serial number berada di gudang atau sudah terpasang di pelanggan.</p>
            </div>
            @else
            <!-- Desktop Table (hidden lg:block) -->
            <div class="hidden lg:block overflow-x-auto scroll-smooth min-h-[280px]">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800/60">
                        <tr>
                            <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Teknisi Lapangan</th>
                            <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Barang / Model</th>
                            <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Serial Number</th>
                            <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Asal Gudang POP</th>
                            <th class="px-6 py-3.5 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Aksi Kelola</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700/50">
                        @foreach($serials as $serial)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/30 transition-colors">
                            <!-- Teknisi -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-sky-500 to-indigo-600 text-white font-bold text-xs flex items-center justify-center shrink-0 shadow-xs">
                                        {{ strtoupper(substr($serial->currentTechnician->name ?? 'T', 0, 2)) }}
                                    </div>
                                    <div>
                                        <div class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ $serial->currentTechnician->name ?? '-' }}</div>
                                        <div class="text-[11px] text-slate-400">Teknisi Whusnet</div>
                                    </div>
                                </div>
                            </td>

                            <!-- Barang -->
                            <td class="px-6 py-4">
                                <div class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ $serial->item->name }}</div>
                                <div class="text-[11px] font-mono text-slate-400">{{ $serial->item->code }}</div>
                                @if(($serial->condition?->value ?? 'new') !== 'new')
                                @php
                                    $custodyConditionBadge = $serial->condition?->value === 'used_damaged'
                                        ? ['label' => 'Bekas — Rusak', 'class' => 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400 border-rose-200 dark:border-rose-800']
                                        : ['label' => 'Bekas — Sudah Dicek', 'class' => 'bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-400 border-sky-200 dark:border-sky-800'];
                                @endphp
                                <span class="inline-flex mt-1 px-1.5 py-0.5 rounded text-[10px] font-bold border {{ $custodyConditionBadge['class'] }}">{{ $custodyConditionBadge['label'] }}</span>
                                @endif
                            </td>

                            <!-- Serial Number -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if(auth()->user()->hasPermission('warehouse_traceability.view'))
                                <a href="{{ route('warehouse.traceability.index', ['sn' => $serial->serial_number]) }}"
                                   class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-sky-50 hover:bg-sky-100 dark:bg-sky-950/40 dark:hover:bg-sky-900/50 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800 transition-colors"
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
                                <span class="px-2 py-1 rounded-md bg-slate-100 dark:bg-slate-700 font-medium">
                                    {{ $serial->issuedFromPop->name ?? '-' }}
                                </span>
                            </td>

                            <!-- Aksi Cepat (Teleported) -->
                            <td class="px-6 py-4 whitespace-nowrap text-right text-xs relative">
                                @if(auth()->user()->hasPermission('warehouse_adjustment.create') || auth()->user()->hasPermission('warehouse_reassign.create') || auth()->user()->hasPermission('warehouse_traceability.view'))
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
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700/60 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-colors cursor-pointer">
                                        <span>⋮ Aksi Cepat</span>
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
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
                                            class="w-56 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-2xl py-1.5 text-left divide-y divide-slate-100 dark:divide-slate-700/60">
                                            <div class="py-1">
                                                @if(auth()->user()->hasPermission('warehouse_reassign.create'))
                                                <a href="{{ route('warehouse.reassign.serial.create', $serial) }}"
                                                   class="flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-sky-700 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-sky-950/40 transition-colors">
                                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                                                    <span>Alihkan Custody</span>
                                                </a>
                                                @endif
                                                @if(auth()->user()->hasPermission('warehouse_adjustment.create'))
                                                <a href="{{ route('warehouse.adjustments.serial.create', $serial) }}"
                                                   class="flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-amber-700 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/40 transition-colors">
                                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                                    <span>Lapor BAP / Rusak</span>
                                                </a>
                                                @endif
                                            </div>
                                            @if(auth()->user()->hasPermission('warehouse_traceability.view'))
                                            <div class="py-1">
                                                <a href="{{ route('warehouse.traceability.index', ['sn' => $serial->serial_number]) }}"
                                                   class="flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-colors">
                                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                                                    <span>Lacak SN</span>
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

            <!-- Mobile & Tablet Card View (block lg:hidden) -->
            <div class="block lg:hidden p-3 sm:p-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    @foreach($serials as $serial)
                    <div class="p-4 rounded-2xl border border-slate-200/80 dark:border-slate-700/80 bg-white dark:bg-slate-800 shadow-xs flex flex-col justify-between space-y-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-sky-500 to-indigo-600 text-white font-bold text-xs flex items-center justify-center shrink-0 shadow-xs">
                                    {{ strtoupper(substr($serial->currentTechnician->name ?? 'T', 0, 2)) }}
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ $serial->currentTechnician->name ?? '-' }}</div>
                                    <div class="text-[10px] text-slate-400">Gudang: {{ $serial->issuedFromPop->name ?? '-' }}</div>
                                </div>
                            </div>

                            @if(auth()->user()->hasPermission('warehouse_adjustment.create') || auth()->user()->hasPermission('warehouse_reassign.create') || auth()->user()->hasPermission('warehouse_traceability.view'))
                            <button type="button"
                                    @click="$dispatch('open-custody-actions', {
                                        technicianName: @js($serial->currentTechnician->name ?? '-'),
                                        popName: @js($serial->issuedFromPop->name ?? '-'),
                                        itemName: @js($serial->item->name),
                                        itemCode: @js($serial->item->code),
                                        identifier: @js('SN: ' . $serial->serial_number),
                                        reassignUrl: @js(auth()->user()->hasPermission('warehouse_reassign.create') ? route('warehouse.reassign.serial.create', $serial) : null),
                                        reassignTitle: 'Alihkan Custody Perangkat',
                                        adjustUrl: @js(auth()->user()->hasPermission('warehouse_adjustment.create') ? route('warehouse.adjustments.serial.create', $serial) : null),
                                        adjustTitle: 'Lapor BAP / Rusak',
                                        traceUrl: @js(auth()->user()->hasPermission('warehouse_traceability.view') ? route('warehouse.traceability.index', ['sn' => $serial->serial_number]) : null),
                                        traceTitle: 'Lacak Riwayat SN',
                                    })"
                                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-bold cursor-pointer"
                                    title="Menu Aksi">
                                <span>⋮</span>
                            </button>
                            @endif
                        </div>

                        <div>
                            <h4 class="text-xs sm:text-sm font-bold text-slate-800 dark:text-slate-200">{{ $serial->item->name }}</h4>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="text-[11px] font-mono text-slate-400">{{ $serial->item->code }}</span>
                                @if(($serial->condition?->value ?? 'new') !== 'new')
                                @php
                                    $custodyConditionBadge = $serial->condition?->value === 'used_damaged'
                                        ? ['label' => 'Bekas — Rusak', 'class' => 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400 border-rose-200 dark:border-rose-800']
                                        : ['label' => 'Bekas — Sudah Dicek', 'class' => 'bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-400 border-sky-200 dark:border-sky-800'];
                                @endphp
                                <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold border {{ $custodyConditionBadge['class'] }}">{{ $custodyConditionBadge['label'] }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="pt-2 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Serial Number</span>
                            @if(auth()->user()->hasPermission('warehouse_traceability.view'))
                            <a href="{{ route('warehouse.traceability.index', ['sn' => $serial->serial_number]) }}"
                               class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-sky-50 hover:bg-sky-100 dark:bg-sky-950/40 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800">
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

    <!-- TAB 3: ROLL KABEL -->
    <div x-show="activeTab === 'rolls'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl shadow-xs overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-lg bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 flex items-center justify-center border border-amber-100 dark:border-amber-800/60">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a1 1 0 001-1v-4a1 1 0 00-1-1H9a1 1 0 00-1 1v4a1 1 0 001 1zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">Roll Kabel Di Tangan Teknisi</h3>
                        <p class="text-[11px] text-slate-400">Sisa meter per roll — dilacak per unit, bukan agregat</p>
                    </div>
                </div>
            </div>

            @if($rolls->isEmpty())
            <div class="p-16 text-center">
                <div class="w-14 h-14 mx-auto mb-3 rounded-lg bg-slate-100 dark:bg-slate-700/50 flex items-center justify-center text-slate-400">
                    <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Tidak ada roll kabel di tangan teknisi</h4>
                <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">Semua roll berada di gudang atau sudah habis dipakai.</p>
            </div>
            @else
            <!-- Desktop Table (hidden lg:block) -->
            <div class="hidden lg:block overflow-x-auto scroll-smooth min-h-[280px]">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800/60">
                        <tr>
                            <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Teknisi Lapangan</th>
                            <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Barang</th>
                            <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Roll ID</th>
                            <th class="px-6 py-3.5 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Sisa Meter</th>
                            <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Asal Gudang POP</th>
                            <th class="px-6 py-3.5 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Aksi Kelola</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700/50">
                        @foreach($rolls as $roll)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/30 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-amber-500 to-orange-600 text-white font-bold text-xs flex items-center justify-center shrink-0 shadow-xs">
                                        {{ strtoupper(substr($roll->currentTechnician->name ?? 'T', 0, 2)) }}
                                    </div>
                                    <div>
                                        <div class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ $roll->currentTechnician->name ?? '-' }}</div>
                                        <div class="text-[11px] text-slate-400">Teknisi Whusnet</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ $roll->item->name ?? '(barang dihapus)' }}</div>
                                <div class="text-[11px] font-mono text-slate-400">{{ $roll->item->code ?? '-' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if(auth()->user()->hasPermission('warehouse_traceability.view'))
                                <a href="{{ route('warehouse.traceability.index', ['roll' => $roll->roll_code]) }}"
                                   class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-amber-50 hover:bg-amber-100 dark:bg-amber-950/40 dark:hover:bg-amber-900/50 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800 transition-colors"
                                   title="Lacak Riwayat Roll">
                                    {{ $roll->roll_code }}
                                </a>
                                @else
                                <span class="font-mono text-xs font-bold text-slate-700 dark:text-slate-300">{{ $roll->roll_code }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right font-mono">
                                <span class="text-sm font-extrabold text-slate-900 dark:text-slate-100">{{ rtrim(rtrim(number_format((float) $roll->length_remaining, 2, ',', '.'), '0'), ',') }}</span>
                                <span class="text-xs font-semibold text-slate-400 ml-0.5">/ {{ rtrim(rtrim(number_format((float) $roll->length_total, 2, ',', '.'), '0'), ',') }} m</span>
                                @if($roll->isLowRemaining())
                                <span class="block mt-1 inline-flex px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800">Sisa Kecil</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-600 dark:text-slate-400">
                                <span class="px-2 py-1 rounded-md bg-slate-100 dark:bg-slate-700 font-medium">{{ $roll->issuedFromPop->name ?? '-' }}</span>
                            </td>

                            <!-- Aksi Cepat (Teleported) -->
                            <td class="px-6 py-4 whitespace-nowrap text-right text-xs relative">
                                @if(auth()->user()->hasPermission('warehouse_adjustment.create') || auth()->user()->hasPermission('warehouse_reassign.create') || auth()->user()->hasPermission('warehouse_traceability.view'))
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
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700/60 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-colors cursor-pointer">
                                        <span>⋮ Aksi Cepat</span>
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
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
                                            class="w-56 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-2xl py-1.5 text-left divide-y divide-slate-100 dark:divide-slate-700/60">
                                            <div class="py-1">
                                                @if(auth()->user()->hasPermission('warehouse_reassign.create'))
                                                <a href="{{ route('warehouse.reassign.roll.create', $roll) }}"
                                                   class="flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-sky-700 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-sky-950/40 transition-colors">
                                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                                                    <span>Alihkan Custody</span>
                                                </a>
                                                @endif
                                                @if(auth()->user()->hasPermission('warehouse_adjustment.create'))
                                                <a href="{{ route('warehouse.adjustments.roll.create', $roll) }}"
                                                   class="flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-amber-700 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/40 transition-colors">
                                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                                    <span>Lapor BAP / Rusak</span>
                                                </a>
                                                @endif
                                            </div>
                                            @if(auth()->user()->hasPermission('warehouse_traceability.view'))
                                            <div class="py-1">
                                                <a href="{{ route('warehouse.traceability.index', ['roll' => $roll->roll_code]) }}"
                                                   class="flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-colors">
                                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                                                    <span>Lacak Roll</span>
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

            <!-- Mobile & Tablet Card View (block lg:hidden) -->
            <div class="block lg:hidden p-3 sm:p-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    @foreach($rolls as $roll)
                    <div class="p-4 rounded-2xl border border-slate-200/80 dark:border-slate-700/80 bg-white dark:bg-slate-800 shadow-xs flex flex-col justify-between space-y-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-amber-500 to-orange-600 text-white font-bold text-xs flex items-center justify-center shrink-0 shadow-xs">
                                    {{ strtoupper(substr($roll->currentTechnician->name ?? 'T', 0, 2)) }}
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ $roll->currentTechnician->name ?? '-' }}</div>
                                    <div class="text-[10px] text-slate-400">Gudang: {{ $roll->issuedFromPop->name ?? '-' }}</div>
                                </div>
                            </div>

                            <div class="flex items-center gap-1.5">
                                @if($roll->isLowRemaining())
                                <span class="inline-flex px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800">Sisa Kecil</span>
                                @endif

                                @if(auth()->user()->hasPermission('warehouse_adjustment.create') || auth()->user()->hasPermission('warehouse_reassign.create') || auth()->user()->hasPermission('warehouse_traceability.view'))
                                <button type="button"
                                        @click="$dispatch('open-custody-actions', {
                                            technicianName: @js($roll->currentTechnician->name ?? '-'),
                                            popName: @js($roll->issuedFromPop->name ?? '-'),
                                            itemName: @js($roll->item->name ?? '(barang dihapus)'),
                                            itemCode: @js($roll->item->code ?? '-'),
                                            identifier: @js('Roll: ' . $roll->roll_code . ' • Sisa: ' . rtrim(rtrim(number_format((float) $roll->length_remaining, 2, ',', '.'), '0'), ',') . ' m'),
                                            reassignUrl: @js(auth()->user()->hasPermission('warehouse_reassign.create') ? route('warehouse.reassign.roll.create', $roll) : null),
                                            reassignTitle: 'Alihkan Custody Roll Kabel',
                                            adjustUrl: @js(auth()->user()->hasPermission('warehouse_adjustment.create') ? route('warehouse.adjustments.roll.create', $roll) : null),
                                            adjustTitle: 'Lapor BAP / Rusak',
                                            traceUrl: @js(auth()->user()->hasPermission('warehouse_traceability.view') ? route('warehouse.traceability.index', ['roll' => $roll->roll_code]) : null),
                                            traceTitle: 'Lacak Riwayat Roll',
                                        })"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-bold cursor-pointer"
                                        title="Menu Aksi">
                                    <span>⋮</span>
                                </button>
                                @endif
                            </div>
                        </div>

                        <div>
                            <h4 class="text-xs sm:text-sm font-bold text-slate-800 dark:text-slate-200">{{ $roll->item->name ?? '(barang dihapus)' }}</h4>
                            <div class="text-[11px] font-mono text-slate-400 mt-0.5">{{ $roll->item->code ?? '-' }}</div>
                        </div>

                        <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-700/40 border border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
                            <div>
                                <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400 block">Roll ID</span>
                                @if(auth()->user()->hasPermission('warehouse_traceability.view'))
                                <a href="{{ route('warehouse.traceability.index', ['roll' => $roll->roll_code]) }}"
                                   class="inline-flex items-center gap-1 text-xs font-mono font-bold text-amber-600 dark:text-amber-400">
                                    <span>{{ $roll->roll_code }}</span>
                                </a>
                                @else
                                <span class="text-xs font-mono font-bold text-slate-700 dark:text-slate-300">{{ $roll->roll_code }}</span>
                                @endif
                            </div>
                            <div class="text-right">
                                <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400 block">Sisa / Total</span>
                                <span class="text-xs font-mono font-extrabold text-slate-900 dark:text-slate-100">{{ rtrim(rtrim(number_format((float) $roll->length_remaining, 2, ',', '.'), '0'), ',') }}</span>
                                <span class="text-[10px] text-slate-400 font-mono">/ {{ rtrim(rtrim(number_format((float) $roll->length_total, 2, ',', '.'), '0'), ',') }} m</span>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>

    <!-- TAB 2: MATERIAL & BATCH KABEL -->
    <div x-show="activeTab === 'materials'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl shadow-xs overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-lg bg-purple-50 dark:bg-purple-950/50 text-purple-600 dark:text-purple-400 flex items-center justify-center border border-purple-100 dark:border-purple-800/60">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    </div>
                    <div>
                        <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">Perangkat Pasif Di Tangan Teknisi</h3>
                        <p class="text-[11px] text-slate-400">Kabel dropcore, patchcord, konektor, ODP/closure yang dibawa teknisi</p>
                    </div>
                </div>
            </div>

            @if($custodies->isEmpty())
            <div class="p-16 text-center">
                <div class="w-14 h-14 mx-auto mb-3 rounded-lg bg-slate-100 dark:bg-slate-700/50 flex items-center justify-center text-slate-400">
                    <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </div>
                <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Tidak ada custody material aktif</h4>
                <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">Semua material telah direkonsiliasi dalam laporan pemasangan atau dikembalikan ke gudang.</p>
            </div>
            @else
            <!-- Desktop Table (hidden lg:block) -->
            <div class="hidden lg:block overflow-x-auto scroll-smooth min-h-[280px]">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800/60">
                        <tr>
                            <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Teknisi Lapangan</th>
                            <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Material / Barang</th>
                            <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Lot / Drum</th>
                            <th class="px-6 py-3.5 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Sisa Qty</th>
                            <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Asal POP</th>
                            <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Lama Dipegang</th>
                            <th class="px-6 py-3.5 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Aksi Kelola</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700/50">
                        @foreach($custodies as $custody)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/30 transition-colors">
                            <!-- Teknisi -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-purple-500 to-indigo-600 text-white font-bold text-xs flex items-center justify-center shrink-0 shadow-xs">
                                        {{ strtoupper(substr($custody->technician->name ?? 'T', 0, 2)) }}
                                    </div>
                                    <div>
                                        <div class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ $custody->technician->name }}</div>
                                        <div class="text-[11px] text-slate-400">Teknisi Whusnet</div>
                                    </div>
                                </div>
                            </td>

                            <!-- Barang -->
                            <td class="px-6 py-4">
                                <div class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ $custody->item->name }}</div>
                                <div class="text-[11px] font-mono text-slate-400">{{ $custody->item->code }}</div>
                            </td>

                            <!-- Lot -->
                            <td class="px-6 py-4 whitespace-nowrap font-mono text-xs text-slate-500 dark:text-slate-400">
                                {{ $custody->lot_no ?: '-' }}
                            </td>

                            <!-- Sisa Qty -->
                            <td class="px-6 py-4 whitespace-nowrap text-right font-mono">
                                <span class="text-sm font-extrabold text-slate-900 dark:text-slate-100">
                                    {{ rtrim(rtrim(number_format((float) $custody->qty_remaining, 2, ',', '.'), '0'), ',') }}
                                </span>
                                <span class="text-xs font-semibold text-slate-400 ml-0.5">{{ $custody->item->unit }}</span>
                            </td>

                            <!-- Asal Gudang -->
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-600 dark:text-slate-400">
                                <span class="px-2 py-1 rounded-md bg-slate-100 dark:bg-slate-700 font-medium">
                                    {{ $custody->issuedFromPop->name ?? '-' }}
                                </span>
                            </td>

                            <!-- Lama Dipegang (Aging) -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 dark:bg-slate-700/60 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-600">
                                    {{ $custody->ageLabel() }}
                                </span>
                            </td>

                            <!-- Aksi Cepat (Teleported) -->
                            <td class="px-6 py-4 whitespace-nowrap text-right text-xs relative">
                                @if(auth()->user()->hasPermission('warehouse_adjustment.create') || auth()->user()->hasPermission('warehouse_reassign.create'))
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
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700/60 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-colors cursor-pointer">
                                        <span>⋮ Aksi Cepat</span>
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
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
                                            class="w-56 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-2xl py-1.5 text-left divide-y divide-slate-100 dark:divide-slate-700/60">
                                            <div class="py-1">
                                                @if(auth()->user()->hasPermission('warehouse_reassign.create'))
                                                <a href="{{ route('warehouse.reassign.custody.create', $custody) }}"
                                                   class="flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-sky-700 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-sky-950/40 transition-colors">
                                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                                                    <span>Kembalikan / Alihkan</span>
                                                </a>
                                                @endif
                                                @if(auth()->user()->hasPermission('warehouse_adjustment.create'))
                                                <a href="{{ route('warehouse.adjustments.custody.create', $custody) }}"
                                                   class="flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-amber-700 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/40 transition-colors">
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

            <!-- Mobile & Tablet Card View (block lg:hidden) -->
            <div class="block lg:hidden p-3 sm:p-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    @foreach($custodies as $custody)
                    <div class="p-4 rounded-2xl border border-slate-200/80 dark:border-slate-700/80 bg-white dark:bg-slate-800 shadow-xs flex flex-col justify-between space-y-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-purple-500 to-indigo-600 text-white font-bold text-xs flex items-center justify-center shrink-0 shadow-xs">
                                    {{ strtoupper(substr($custody->technician->name ?? 'T', 0, 2)) }}
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ $custody->technician->name }}</div>
                                    <div class="text-[10px] text-slate-400">Gudang: {{ $custody->issuedFromPop->name ?? '-' }}</div>
                                </div>
                            </div>

                            @if(auth()->user()->hasPermission('warehouse_adjustment.create') || auth()->user()->hasPermission('warehouse_reassign.create'))
                            <button type="button"
                                    @click="$dispatch('open-custody-actions', {
                                        technicianName: @js($custody->technician->name ?? '-'),
                                        popName: @js($custody->issuedFromPop->name ?? '-'),
                                        itemName: @js($custody->item->name),
                                        itemCode: @js($custody->item->code),
                                        identifier: @js('Sisa: ' . rtrim(rtrim(number_format((float) $custody->qty_remaining, 2, ',', '.'), '0'), ',') . ' ' . $custody->item->unit . ($custody->lot_no ? ' • Lot: ' . $custody->lot_no : '')),
                                        reassignUrl: @js(auth()->user()->hasPermission('warehouse_reassign.create') ? route('warehouse.reassign.custody.create', $custody) : null),
                                        reassignTitle: 'Kembalikan / Alihkan Material',
                                        adjustUrl: @js(auth()->user()->hasPermission('warehouse_adjustment.create') ? route('warehouse.adjustments.custody.create', $custody) : null),
                                        adjustTitle: 'Koreksi / Lapor BAP',
                                        traceUrl: null,
                                        traceTitle: null,
                                    })"
                                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-bold cursor-pointer"
                                    title="Menu Aksi">
                                <span>⋮</span>
                            </button>
                            @endif
                        </div>

                        <div>
                            <h4 class="text-xs sm:text-sm font-bold text-slate-800 dark:text-slate-200">{{ $custody->item->name }}</h4>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="text-[11px] font-mono text-slate-400">{{ $custody->item->code }}</span>
                                @if($custody->lot_no)
                                <span class="text-[10px] font-mono text-slate-500 dark:text-slate-400">Lot: {{ $custody->lot_no }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-700/40 border border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
                            <div>
                                <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400 block">Sisa Qty</span>
                                <span class="text-xs sm:text-sm font-mono font-extrabold text-slate-900 dark:text-slate-100">
                                    {{ rtrim(rtrim(number_format((float) $custody->qty_remaining, 2, ',', '.'), '0'), ',') }}
                                </span>
                                <span class="text-[10px] text-slate-400">{{ $custody->item->unit }}</span>
                            </div>
                            <div class="text-right">
                                <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400 block">Lama Dipegang</span>
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-200/80 dark:bg-slate-600 text-slate-700 dark:text-slate-300">
                                    {{ $custody->ageLabel() }}
                                </span>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>

    <!-- TAB 4: RETURN DARI PELANGGAN (ADHOC-88)
         Modem hasil pengambilan alat (task DEAC) yang MASIH dipegang teknisi:
         status `RETURNED` (transit). Hilang dari sini begitu gudang menerimanya
         di Terima Retur. Beda dari tab "Perangkat Serial Number" (barang yang
         DIBAWA ke lapangan) — ini barang yang dibawa PULANG. -->
    @php $canReceiveReturn = auth()->user()->hasPermission('warehouse_reassign.create'); @endphp
    <div x-show="activeTab === 'returns'" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl shadow-xs overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-lg bg-teal-50 dark:bg-teal-950/50 text-teal-600 dark:text-teal-400 flex items-center justify-center border border-teal-100 dark:border-teal-800/60">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/></svg>
                    </div>
                    <div>
                        <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">Return dari Pelanggan Di Tangan Teknisi</h3>
                        <p class="text-[11px] text-slate-400">Modem hasil pengambilan alat (putus langganan) yang belum diterima gudang</p>
                    </div>
                </div>
                @if($canReceiveReturn)
                <a href="{{ route('warehouse.returns.index') }}" class="text-xs font-bold text-teal-600 dark:text-teal-400 whitespace-nowrap">Buka Terima Retur →</a>
                @endif
            </div>

            @if($returned->isEmpty())
            <div class="p-16 text-center">
                <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Tidak ada barang return di tangan teknisi</h4>
                <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">Semua modem hasil pengambilan alat sudah diterima gudang.</p>
            </div>
            @else
            <!-- Desktop Table -->
            <div class="hidden lg:block overflow-x-auto scroll-smooth">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800/60">
                        <tr>
                            <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Teknisi</th>
                            <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Barang</th>
                            <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Serial Number</th>
                            <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Dari Pelanggan</th>
                            <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Diambil</th>
                            <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Gudang Tujuan</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        @foreach($returned as $serial)
                        @php $retrievedAt = $returnedRetrievedAt[$serial->id] ?? null; @endphp
                        <tr>
                            <td class="px-5 py-3.5 text-xs font-bold text-slate-800 dark:text-slate-200">{{ $serial->currentTechnician->name ?? '-' }}</td>
                            <td class="px-5 py-3.5">
                                <span class="block text-xs font-bold text-slate-800 dark:text-slate-200">{{ $serial->item->name }}</span>
                                <span class="text-[11px] font-mono text-slate-400">{{ $serial->item->code }}</span>
                            </td>
                            <td class="px-5 py-3.5">
                                @if(auth()->user()->hasPermission('warehouse_traceability.view'))
                                <a href="{{ route('warehouse.traceability.index', ['sn' => $serial->serial_number]) }}" class="font-mono text-xs font-bold text-sky-700 dark:text-sky-300">{{ $serial->serial_number }}</a>
                                @else
                                <span class="font-mono text-xs font-bold text-slate-700 dark:text-slate-300">{{ $serial->serial_number }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-xs text-slate-700 dark:text-slate-300">{{ $serial->customer?->full_name ?? '-' }}</td>
                            <td class="px-5 py-3.5 text-xs text-slate-700 dark:text-slate-300 whitespace-nowrap">
                                {{ $retrievedAt?->translatedFormat('d M Y') ?? '-' }}
                                @if($retrievedAt)<span class="block text-[10px] text-slate-400">{{ $retrievedAt->diffForHumans() }}</span>@endif
                            </td>
                            <td class="px-5 py-3.5 text-xs text-slate-700 dark:text-slate-300">{{ $serial->issuedFromPop->name ?? '-' }}</td>
                            <td class="px-5 py-3.5 text-right">
                                @if($canReceiveReturn)
                                <a href="{{ route('warehouse.returns.receive.create', $serial) }}" class="inline-flex items-center px-3 py-1.5 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-[11px] font-bold">Terima</a>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Mobile & Tablet Card View -->
            <div class="block lg:hidden p-3 sm:p-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    @foreach($returned as $serial)
                    @php $retrievedAt = $returnedRetrievedAt[$serial->id] ?? null; @endphp
                    <div class="p-4 rounded-2xl border border-slate-200/80 dark:border-slate-700/80 bg-white dark:bg-slate-800 shadow-xs space-y-3">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ $serial->currentTechnician->name ?? '-' }}</div>
                                <div class="text-[10px] text-slate-400">Gudang tujuan: {{ $serial->issuedFromPop->name ?? '-' }}</div>
                            </div>
                            @if($canReceiveReturn)
                            <a href="{{ route('warehouse.returns.receive.create', $serial) }}" class="inline-flex items-center px-3 py-1.5 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-[11px] font-bold">Terima</a>
                            @endif
                        </div>
                        <div>
                            <h4 class="text-xs sm:text-sm font-bold text-slate-800 dark:text-slate-200">{{ $serial->item->name }}</h4>
                            <span class="block mt-0.5 font-mono text-xs font-bold text-slate-700 dark:text-slate-300">SN: {{ $serial->serial_number }}</span>
                        </div>
                        <div class="pt-2 border-t border-slate-100 dark:border-slate-700/60 text-[11px] text-slate-500 dark:text-slate-400">
                            Dari <b class="text-slate-700 dark:text-slate-300">{{ $serial->customer?->full_name ?? '-' }}</b>
                            @if($retrievedAt) · {{ $retrievedAt->translatedFormat('d M Y') }} ({{ $retrievedAt->diffForHumans() }}) @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

<!-- Modal: Mobile Action Sheet (Barang di Tangan Teknisi) -->
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
                    <div class="space-y-1">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-sky-100 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300" x-text="'Teknisi: ' + item.technicianName"></span>
                            <span class="px-2 py-0.5 rounded text-[10px] bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-medium" x-text="'Gudang: ' + item.popName"></span>
                        </div>
                        <h3 class="text-sm sm:text-base font-bold text-slate-900 dark:text-slate-100 leading-snug" x-text="item.itemName"></h3>
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
                           class="flex items-center gap-3 p-3 rounded-xl bg-sky-50/60 hover:bg-sky-100/70 dark:bg-sky-950/30 dark:hover:bg-sky-900/40 border border-sky-100 dark:border-sky-900/50 text-sky-800 dark:text-sky-300 transition-colors">
                            <div class="w-9 h-9 rounded-lg bg-sky-500 text-white flex items-center justify-center shrink-0 shadow-xs">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                            </div>
                            <div class="flex-1">
                                <div class="text-xs font-bold" x-text="item.reassignTitle || 'Alihkan Custody'"></div>
                                <div class="text-[10px] text-sky-600/80 dark:text-sky-400/80">Pindahkan ke teknisi lain atau kembalikan ke gudang</div>
                            </div>
                            <svg class="w-4 h-4 text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </template>

                    <!-- Lapor BAP / Rusak -->
                    <template x-if="item.adjustUrl">
                        <a :href="item.adjustUrl"
                           class="flex items-center gap-3 p-3 rounded-xl bg-amber-50/60 hover:bg-amber-100/70 dark:bg-amber-950/30 dark:hover:bg-amber-900/40 border border-amber-100 dark:border-amber-900/50 text-amber-800 dark:text-amber-300 transition-colors">
                            <div class="w-9 h-9 rounded-lg bg-amber-500 text-white flex items-center justify-center shrink-0 shadow-xs">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            </div>
                            <div class="flex-1">
                                <div class="text-xs font-bold" x-text="item.adjustTitle || 'Lapor BAP / Rusak'"></div>
                                <div class="text-[10px] text-amber-600/80 dark:text-amber-400/80">Catat kerusakan, hilang, atau selisih sisa</div>
                            </div>
                            <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </template>

                    <!-- Lacak Riwayat / Traceability -->
                    <template x-if="item.traceUrl">
                        <a :href="item.traceUrl"
                           class="flex items-center gap-3 p-3 rounded-xl bg-slate-100/80 hover:bg-slate-200/80 dark:bg-slate-800/80 dark:hover:bg-slate-700/80 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 transition-colors">
                            <div class="w-9 h-9 rounded-lg bg-slate-600 text-white flex items-center justify-center shrink-0 shadow-xs">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                            </div>
                            <div class="flex-1">
                                <div class="text-xs font-bold" x-text="item.traceTitle || 'Lacak Riwayat Barang'"></div>
                                <div class="text-[10px] text-slate-500 dark:text-slate-400">Lihat timeline mutasi, penyerahan & status</div>
                            </div>
                            <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
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
