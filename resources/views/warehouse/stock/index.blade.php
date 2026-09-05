@extends('layouts.app')

@section('title', 'Kelola Stok Gudang - Whusnet Operasional')
@section('page_title', 'Kelola Stok')

@section('content')

<x-warehouse.header active="stock" />

<!-- Filter & Search Toolbar -->
<div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-4 mb-6 shadow-xs">
    <form method="GET" action="{{ route('warehouse.stock.index') }}" class="space-y-3">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
            <!-- Search Input -->
            <div class="md:col-span-5">
                <label for="search" class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Cari Barang / Kode</label>
                <div class="relative">
                    <input type="text" name="search" id="search" value="{{ $search }}" placeholder="Ketik nama atau kode barang (mis. ONT, ZTE, Feeder)..."
                           class="w-full pl-9 pr-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- POP Dropdown -->
            <div class="md:col-span-4">
                <label for="pop_id" class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Gudang POP</label>
                <select name="pop_id" id="pop_id" class="w-full px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                    <option value="">— Semua Gudang Terjangkau —</option>
                    @foreach($pops as $pop)
                    <option value="{{ $pop->id }}" {{ (string) $popFilter === (string) $pop->id ? 'selected' : '' }}>
                        {{ $pop->name }} ({{ strtoupper($pop->type) }})
                    </option>
                    @endforeach
                </select>
            </div>

            <!-- Filter Buttons & Quick Toggles -->
            <div class="md:col-span-3 flex items-center gap-2 justify-end">
                <button type="submit" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 text-white text-xs font-semibold rounded-xl shadow-xs transition-colors cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                    <span>Terapkan</span>
                </button>

                @if($search || $popFilter || $lowStockOnly)
                <a href="{{ route('warehouse.stock.index') }}" class="inline-flex items-center justify-center p-2 text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700/50 transition-colors" title="Reset Filter">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                </a>
                @endif
            </div>
        </div>

        <!-- Quick Status Pills -->
        <div class="pt-2.5 border-t border-slate-100 dark:border-slate-700/60 flex items-center gap-2 flex-wrap text-xs">
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mr-1">Filter Cepat:</span>
            <label class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold cursor-pointer transition-colors {{ !$lowStockOnly ? 'bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800' : 'bg-slate-100 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300' }}">
                <input type="radio" name="low_stock_only" value="0" {{ !$lowStockOnly ? 'checked' : '' }} onchange="this.form.submit()" class="hidden">
                <span>Semua Stok Barang</span>
            </label>
            <label class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold cursor-pointer transition-colors {{ $lowStockOnly ? 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800' : 'bg-slate-100 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300' }}">
                <input type="radio" name="low_stock_only" value="1" {{ $lowStockOnly ? 'checked' : '' }} onchange="this.form.submit()" class="hidden">
                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                <span>Stok Menipis / Rendah Saja</span>
            </label>
        </div>
    </form>
</div>

<!-- Tabel Stok Barang -->
<div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl overflow-hidden shadow-xs">
    @if($balances->isEmpty())
    <div class="p-16 text-center">
        <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-slate-100 dark:bg-slate-700/50 flex items-center justify-center text-slate-400">
            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </svg>
        </div>
        <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Tidak ada data stok yang cocok</h4>
        <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">Coba ubah kata kunci pencarian atau reset filter gudang.</p>
        @if($search || $popFilter || $lowStockOnly)
        <a href="{{ route('warehouse.stock.index') }}" class="mt-4 inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 text-xs font-semibold rounded-xl text-slate-700 dark:text-slate-200 transition-colors">
            <span>Reset Semua Filter</span>
        </a>
        @endif
    </div>
    @else
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-800/60">
                <tr>
                    <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Gudang POP</th>
                    <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Detail Barang</th>
                    <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Jenis Tracking</th>
                    <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Lot / Batch</th>
                    <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Kesehatan Stok</th>
                    <th class="px-6 py-3.5 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Jumlah Tersedia</th>
                    @if(auth()->user()->hasPermission('warehouse_adjustment.create'))
                    <th class="px-6 py-3.5 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Aksi</th>
                    @endif
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700/50">
                @foreach($balances as $balance)
                @php
                    $isLow = $balance->isLowStock();
                    $qty = (float) $balance->qty;
                    $min = (float) ($balance->minimum_stock ?? 0);
                    $trackingType = $balance->item->tracking_type->value ?? 'quantity';
                    $ratio = $min > 0 ? min(100, round(($qty / $min) * 100)) : 100;

                    // "Opname terakhir per item per gudang" (Fase 2 P1, gap #3,
                    // kontrol-anti-manipulasi.md §5) — info doang, BUKAN status
                    // lulus/gagal/overdue (sengaja gak ada jadwal kalender tetap).
                    $opnameKey = $balance->pop_id.'-'.$balance->item_id.'-'.($balance->lot_no ?: '');
                    $lastOpnameAt = isset($lastOpnameByKey[$opnameKey]) ? \Illuminate\Support\Carbon::parse($lastOpnameByKey[$opnameKey]) : null;
                @endphp
                <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/30 transition-colors {{ $isLow ? 'bg-rose-50/25 dark:bg-rose-950/10' : '' }}">
                    <!-- Gudang POP -->
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300">
                        <div class="flex items-center gap-1.5">
                            <span class="font-bold text-slate-800 dark:text-slate-200">{{ $balance->pop->name }}</span>
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold {{ $balance->pop->type === 'pusat' ? 'bg-sky-100 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300' }}">
                                {{ strtoupper($balance->pop->type) }}
                            </span>
                        </div>
                    </td>

                    <!-- Detail Barang -->
                    <td class="px-6 py-4">
                        <div class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ $balance->item->name }}</div>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="text-xs font-mono text-slate-400">{{ $balance->item->code }}</span>
                            @if($balance->item->category)
                            <span class="px-1.5 py-0.5 rounded text-[10px] bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-400 font-medium">
                                {{ $balance->item->category->name }}
                            </span>
                            @endif
                        </div>
                    </td>

                    <!-- Jenis Tracking -->
                    <td class="px-6 py-4 whitespace-nowrap">
                        @if($trackingType === 'serialized')
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-cyan-50 dark:bg-cyan-950/40 text-cyan-700 dark:text-cyan-300 border border-cyan-200 dark:border-cyan-800">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
                            <span>SERIAL NUMBER</span>
                        </span>
                        @elseif($trackingType === 'batch')
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-purple-50 dark:bg-purple-950/40 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                            <span>BATCH / LOT</span>
                        </span>
                        @else
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 dark:bg-slate-700/60 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-600">
                            <span>QUANTITY</span>
                        </span>
                        @endif
                    </td>

                    <!-- Lot / Drum -->
                    <td class="px-6 py-4 whitespace-nowrap text-xs font-mono text-slate-500 dark:text-slate-400">
                        {{ $balance->lot_no ?: '-' }}
                    </td>

                    <!-- Kesehatan Stok -->
                    <td class="px-6 py-4 whitespace-nowrap">
                        @if($isLow)
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-rose-100 dark:bg-rose-900/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                                Kritis / Menipis
                            </span>
                        </div>
                        @else
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                Stok Aman
                            </span>
                        </div>
                        @endif
                        @if($min > 0)
                        <div class="text-[10px] text-slate-400 mt-1 font-mono">Min: {{ rtrim(rtrim(number_format($min, 2, ',', '.'), '0'), ',') }} {{ $balance->item->unit }}</div>
                        @endif
                        <div class="text-[10px] {{ $lastOpnameAt ? 'text-slate-400' : 'text-amber-500 dark:text-amber-400 font-semibold' }} mt-0.5">
                            Opname: {{ $lastOpnameAt ? $lastOpnameAt->diffForHumans() : 'Belum pernah' }}
                        </div>
                    </td>

                    <!-- Qty Tersedia -->
                    <td class="px-6 py-4 whitespace-nowrap text-right font-mono">
                        <span class="text-base font-extrabold {{ $isLow ? 'text-rose-600 dark:text-rose-400' : 'text-slate-900 dark:text-slate-100' }}">
                            {{ rtrim(rtrim(number_format($qty, 2, ',', '.'), '0'), ',') }}
                        </span>
                        <span class="text-xs font-semibold text-slate-400 ml-0.5">{{ $balance->item->unit }}</span>
                    </td>

                    <!-- Aksi -->
                    @if(auth()->user()->hasPermission('warehouse_adjustment.create'))
                    <td class="px-6 py-4 whitespace-nowrap text-right text-xs">
                        <div class="inline-flex items-center gap-1.5">
                            <a href="{{ route('warehouse.adjustments.balance.create', ['pop_id' => $balance->pop_id, 'item_id' => $balance->item_id, 'lot_no' => $balance->lot_no]) }}"
                               class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold rounded-xl bg-amber-50 hover:bg-amber-100 dark:bg-amber-950/40 dark:hover:bg-amber-900/50 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/></svg>
                                <span>Sesuaikan</span>
                            </a>
                            <a href="{{ route('warehouse.stock.threshold.create', ['pop_id' => $balance->pop_id, 'item_id' => $balance->item_id, 'lot_no' => $balance->lot_no]) }}"
                               title="Atur ambang Stok Rendah"
                               class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold rounded-xl bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/40 dark:hover:bg-rose-900/50 text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-rose-800 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>Ambang</span>
                            </a>
                        </div>
                    </td>
                    @endif
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if($balances->hasPages())
    <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/40">
        {{ $balances->links() }}
    </div>
    @endif
    @endif
</div>

@endsection
