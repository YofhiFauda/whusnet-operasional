@extends('layouts.app')

@section('title', 'Barang di Tangan Teknisi - Whusnet Operasional')
@section('page_title', 'Barang di Tangan Teknisi')

@section('content')

<x-warehouse.header active="custody" />

<div x-data="{ activeTab: 'serials' }">
    <!-- Filter & Summary Bar -->
    <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-5 mb-6 shadow-xs">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <!-- Filter Form -->
            <form action="{{ route('warehouse.custody.index') }}" method="GET" class="flex flex-wrap items-end gap-3 flex-1">
                <div class="w-full sm:w-72">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Filter Teknisi Lapangan</label>
                    <select name="technician_id" class="w-full px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                        <option value="">— Semua Teknisi Aktif —</option>
                        @foreach($technicians as $technician)
                        <option value="{{ $technician->id }}" {{ (string) $technicianFilter === (string) $technician->id ? 'selected' : '' }}>
                            {{ $technician->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 text-white text-xs font-semibold rounded-xl shadow-xs transition-colors cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                        <span>Filter</span>
                    </button>
                    @if($technicianFilter)
                    <a href="{{ route('warehouse.custody.index') }}" class="px-3 py-2 text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700/50 transition-colors">
                        Reset
                    </a>
                    @endif
                </div>
            </form>

            <!-- Quick Metrics -->
            <div class="flex items-center gap-3 pt-3 lg:pt-0 border-t lg:border-t-0 border-slate-100 dark:border-slate-700/60 shrink-0">
                <div class="px-3.5 py-2 rounded-xl bg-cyan-50/80 dark:bg-cyan-950/40 border border-cyan-100 dark:border-cyan-800/60 text-center">
                    <span class="block text-[10px] font-bold text-cyan-600 dark:text-cyan-400 uppercase tracking-wider">Perangkat SN</span>
                    <span class="text-base font-extrabold text-cyan-700 dark:text-cyan-300 font-mono">{{ $serials->count() }} unit</span>
                </div>
                <div class="px-3.5 py-2 rounded-xl bg-purple-50/80 dark:bg-purple-950/40 border border-purple-100 dark:border-purple-800/60 text-center">
                    <span class="block text-[10px] font-bold text-purple-600 dark:text-purple-400 uppercase tracking-wider">Material Pasif</span>
                    <span class="text-base font-extrabold text-purple-700 dark:text-purple-300 font-mono">{{ $custodies->count() }} batch</span>
                </div>
            </div>
        </div>

        <!-- Segmented Tab Switcher -->
        <div class="mt-4 pt-3.5 border-t border-slate-100 dark:border-slate-700/60 flex items-center gap-2">
            <button @click="activeTab = 'serials'"
                    type="button"
                    :class="activeTab === 'serials' ? 'bg-sky-500 text-white shadow-xs shadow-sky-500/25' : 'bg-slate-100 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
                <span>Perangkat Serial Number</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono" :class="activeTab === 'serials' ? 'bg-white/20 text-white' : 'bg-slate-200 dark:bg-slate-600 text-slate-700 dark:text-slate-300'">
                    {{ $serials->count() }}
                </span>
            </button>

            <button @click="activeTab = 'materials'"
                    type="button"
                    :class="activeTab === 'materials' ? 'bg-sky-500 text-white shadow-xs shadow-sky-500/25' : 'bg-slate-100 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                <span>Material / Batch Kabel</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono" :class="activeTab === 'materials' ? 'bg-white/20 text-white' : 'bg-slate-200 dark:bg-slate-600 text-slate-700 dark:text-slate-300'">
                    {{ $custodies->count() }}
                </span>
            </button>
        </div>
    </div>

    <!-- TAB 1: PERANGKAT SERIAL NUMBER -->
    <div x-show="activeTab === 'serials'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl overflow-hidden shadow-xs">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
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
                <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-slate-100 dark:bg-slate-700/50 flex items-center justify-center text-slate-400">
                    <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Tidak ada perangkat aktif di tangan teknisi</h4>
                <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">Semua perangkat serial number berada di gudang atau sudah terpasang di pelanggan.</p>
            </div>
            @else
            <div class="overflow-x-auto">
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
                                    <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-sky-500 to-indigo-600 text-white font-bold text-xs flex items-center justify-center shrink-0 shadow-xs">
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

                            <!-- Aksi -->
                            <td class="px-6 py-4 whitespace-nowrap text-right text-xs">
                                <div class="flex items-center justify-end gap-1.5">
                                    @if(auth()->user()->hasPermission('warehouse_adjustment.create'))
                                    <a href="{{ route('warehouse.adjustments.serial.create', $serial) }}"
                                       class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-amber-50 hover:bg-amber-100 dark:bg-amber-950/40 dark:hover:bg-amber-900/50 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800 transition-colors">
                                        Tandai Rusak/Hilang
                                    </a>
                                    @endif

                                    @if(auth()->user()->hasPermission('warehouse_reassign.create'))
                                    <a href="{{ route('warehouse.reassign.serial.create', $serial) }}"
                                       class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-sky-50 hover:bg-sky-100 dark:bg-sky-950/40 dark:hover:bg-sky-900/50 text-sky-700 dark:text-sky-400 border border-sky-200 dark:border-sky-800 transition-colors">
                                        Alihkan Barang
                                    </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>

    <!-- TAB 2: MATERIAL & BATCH KABEL -->
    <div x-show="activeTab === 'materials'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl overflow-hidden shadow-xs">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-lg bg-purple-50 dark:bg-purple-950/50 text-purple-600 dark:text-purple-400 flex items-center justify-center border border-purple-100 dark:border-purple-800/60">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    </div>
                    <div>
                        <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">Material &amp; Kabel Pasif Di Tangan Teknisi</h3>
                        <p class="text-[11px] text-slate-400">Kabel dropcore, patchcord, konektor, ODP/closure yang dibawa teknisi</p>
                    </div>
                </div>
            </div>

            @if($custodies->isEmpty())
            <div class="p-16 text-center">
                <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-slate-100 dark:bg-slate-700/50 flex items-center justify-center text-slate-400">
                    <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </div>
                <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Tidak ada custody material aktif</h4>
                <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">Semua material telah direkonsiliasi dalam laporan pemasangan atau dikembalikan ke gudang.</p>
            </div>
            @else
            <div class="overflow-x-auto">
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
                                    <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-purple-500 to-indigo-600 text-white font-bold text-xs flex items-center justify-center shrink-0 shadow-xs">
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

                            <!-- Aksi -->
                            <td class="px-6 py-4 whitespace-nowrap text-right text-xs">
                                <div class="flex items-center justify-end gap-1.5">
                                    @if(auth()->user()->hasPermission('warehouse_adjustment.create'))
                                    <a href="{{ route('warehouse.adjustments.custody.create', $custody) }}"
                                       class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-amber-50 hover:bg-amber-100 dark:bg-amber-950/40 dark:hover:bg-amber-900/50 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800 transition-colors">
                                        Sesuaikan
                                    </a>
                                    @endif

                                    @if(auth()->user()->hasPermission('warehouse_reassign.create'))
                                    <a href="{{ route('warehouse.reassign.custody.create', $custody) }}"
                                       class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-sky-50 hover:bg-sky-100 dark:bg-sky-950/40 dark:hover:bg-sky-900/50 text-sky-700 dark:text-sky-400 border border-sky-200 dark:border-sky-800 transition-colors">
                                        Alihkan Barang
                                    </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>
</div>

@endsection
