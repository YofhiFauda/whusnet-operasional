@extends('layouts.app')

@section('title', 'Kelola Stok Gudang - Whusnet Operasional')
@section('page_title', 'Kelola Stok')

@section('content')

<x-warehouse.header active="stock" />

<div class="mb-6 space-y-4">

@php
    // Kolom Aksi & Quick Action Bar gabungan 4 permission beda — quick-shortcut
    // Transfer/Serah/Minta Stok ditambahkan 2026-09-07 biar staf gak perlu
    // balik ke menu Header buat prefill form manual. `$showActionColumn`
    // dipakai header <th> & body <td> tabel WAJIB kondisi yang sama, kalau
    // enggak kolom bisa geser (th ada, td kosong atau sebaliknya).
    $canAdjust = auth()->user()->hasPermission('warehouse_adjustment.create');
    $canTransfer = auth()->user()->hasPermission('warehouse_transfer.create');
    $canIssue = auth()->user()->hasPermission('warehouse_issue.create');
    $canRequestStock = auth()->user()->hasPermission('warehouse_stock_request.create');
    $showActionColumn = $canAdjust || $canTransfer || $canIssue || $canRequestStock;
@endphp

<!-- Indikator Mode (rancangan-layout.md §3.3-A) — MURNI informasi, gak
     mengubah scope. Quick-action button dicabut dari sini 2026-09-07
     (duplikat sama dropdown "+ Aksi" di header terpadu) — row-level
     shortcut per barang (⋮ Aksi Cepat di tabel bawah) TETAP ada, itu
     kontekstual bukan navigasi umum. -->

<!-- Filter & Search Toolbar (Naked Filter Bar) -->
<div class="mb-5">
    <form method="GET" action="{{ route('warehouse.stock.index') }}" class="space-y-3">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
            <!-- Search Input -->
            <div class="md:col-span-4">
                <label for="search" class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Cari Barang / Kode</label>
                <div class="relative">
                    <input type="text" name="search" id="search" value="{{ $search }}" placeholder="Ketik nama atau kode barang (mis. ONT, ZTE, Feeder)..."
                           class="w-full pl-9 pr-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all shadow-2xs">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- POP Dropdown -->
            <div class="md:col-span-3">
                <label for="pop_id" class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Gudang POP</label>
                <select name="pop_id" id="pop_id" class="w-full px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all shadow-2xs">
                    <option value="">— Semua Gudang Terjangkau —</option>
                    @foreach($pops as $pop)
                    <option value="{{ $pop->id }}" {{ (string) $popFilter === (string) $pop->id ? 'selected' : '' }}>
                        {{ $pop->name }} ({{ strtoupper($pop->type) }})
                    </option>
                    @endforeach
                </select>
            </div>

            <!-- Jenis Tracking Dropdown -->
            <div class="md:col-span-3">
                <label for="tracking_type" class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Jenis Tracking</label>
                <select name="tracking_type" id="tracking_type" class="w-full px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all shadow-2xs">
                    <option value="">— Semua Jenis —</option>
                    @foreach(\App\Enums\TrackingType::cases() as $type)
                    <option value="{{ $type->value }}" {{ $trackingFilter === $type->value ? 'selected' : '' }}>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Filter Buttons & Quick Toggles -->
            <div class="md:col-span-2 flex items-center gap-2 justify-end">
                <button type="submit" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 text-white text-xs font-semibold rounded-lg shadow-2xs transition-colors cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                    <span>Terapkan</span>
                </button>

                @if($search || $popFilter || $lowStockOnly || $trackingFilter)
                <a href="{{ route('warehouse.stock.index') }}" class="inline-flex items-center justify-center p-2 text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700/50 transition-colors shadow-2xs" title="Reset Filter">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                </a>
                @endif
            </div>
        </div>

        <!-- Quick Status Pills -->
        <div class="pt-1.5 flex items-center gap-2 flex-wrap text-xs">
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mr-1">Filter Cepat:</span>
            <label class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold cursor-pointer transition-colors {{ !$lowStockOnly ? 'bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700' }}">
                <input type="radio" name="low_stock_only" value="0" {{ !$lowStockOnly ? 'checked' : '' }} onchange="this.form.submit()" class="hidden">
                <span>Semua Stok Barang</span>
            </label>
            <label class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold cursor-pointer transition-colors {{ $lowStockOnly ? 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700' }}">
                <input type="radio" name="low_stock_only" value="1" {{ $lowStockOnly ? 'checked' : '' }} onchange="this.form.submit()" class="hidden">
                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                <span>Stok Menipis / Rendah Saja</span>
            </label>
        </div>
    </form>
</div>

<!-- Tabel Stok Barang (Card Budget = 1) -->
<div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden shadow-xs" x-data="{}">
    @if($balances->isEmpty())
    <div class="p-16 text-center">
        <div class="w-14 h-14 mx-auto mb-3 rounded-lg bg-slate-100 dark:bg-slate-700/50 flex items-center justify-center text-slate-400">
            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </svg>
        </div>
        <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Tidak ada data stok yang cocok</h4>
        <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">Coba ubah kata kunci pencarian atau reset filter gudang.</p>
        @if($search || $popFilter || $lowStockOnly || $trackingFilter)
        <a href="{{ route('warehouse.stock.index') }}" class="mt-4 inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 text-xs font-semibold rounded-lg text-slate-700 dark:text-slate-200 transition-colors">
            <span>Reset Semua Filter</span>
        </a>
        @endif
    </div>
    @else
    <div class="overflow-x-auto scroll-smooth">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-800/60">
                <tr>
                    <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Gudang POP</th>
                    <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Detail Barang</th>
                    <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Jenis &amp; Lot</th>
                    <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Kesehatan Stok</th>
                    <th class="px-6 py-3.5 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Jumlah Tersedia</th>
                    @if($showActionColumn)
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

                    <!-- Jenis & Lot (digabung, 2026-09-07 — sebelumnya 2 kolom terpisah) -->
                    <td class="px-6 py-4 whitespace-nowrap">
                        @if($trackingType === 'serialized')
                        <button type="button"
                            @click="$dispatch('open-serial-modal', { popId: {{ $balance->pop_id }}, itemId: {{ $balance->item_id }}, itemName: @js($balance->item->name), popName: @js($balance->pop->name) })"
                            title="Lihat daftar Serial Number yang tersedia di gudang ini"
                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-cyan-50 hover:bg-cyan-100 dark:bg-cyan-950/40 dark:hover:bg-cyan-900/50 text-cyan-700 dark:text-cyan-300 border border-cyan-200 dark:border-cyan-800 cursor-pointer transition-colors">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
                            <span>SERIAL NUMBER</span>
                        </button>
                        <div class="text-[10px] text-slate-400 mt-1 font-mono">{{ $qty !== null ? rtrim(rtrim(number_format($qty, 0, ',', '.'), '0'), ',') : 0 }} SN siap</div>
                        @elseif($trackingType === 'batch')
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-purple-50 dark:bg-purple-950/40 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                            <span>BATCH / LOT</span>
                        </span>
                        <div class="text-[10px] text-slate-400 mt-1 font-mono">Lot: {{ $balance->lot_no ?: '-' }}</div>
                        @else
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 dark:bg-slate-700/60 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-600">
                            <span>QUANTITY</span>
                        </span>
                        <div class="text-[10px] text-slate-400 mt-1">Non-serial</div>
                        @endif
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

                    <!-- Aksi: 1 dropdown "⋮" (rancangan-layout.md §3.4), bukan
                         chip terpisah numpuk — isinya sama, kondisinya sama
                         persis kayak sebelum konsolidasi (per pop type +
                         permission masing-masing). -->
                    @if($showActionColumn)
                    <td class="px-6 py-4 whitespace-nowrap text-right text-xs relative">
                        <div x-data="{ menuOpen: false }" @click.outside="menuOpen = false" class="inline-block text-left">
                            <button type="button" @click="menuOpen = !menuOpen"
                                class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700/60 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-colors cursor-pointer">
                                <span>⋮ Aksi</span>
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            </button>

                            <div x-show="menuOpen" x-cloak x-transition
                                class="absolute right-0 mt-1 w-52 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-lg z-10 py-1.5 text-left">
                                {{--
                                    Prefill pop_id/item_id lewat query string, form tujuan baca
                                    sendiri (Alpine x-init / URLSearchParams) — BUKAN nambah param
                                    baru ke controller create() manapun (tetap tipis). Kirim
                                    Transfer cuma masuk akal dari baris Gudang PUSAT (Transfer
                                    cuma satu arah Pusat→Cabang); Minta Stok & Serah Teknisi cuma
                                    masuk akal dari baris CABANG.
                                --}}
                                @if($canTransfer && $balance->pop->type === 'pusat')
                                <a href="{{ route('warehouse.transfers.create', ['pop_id' => $balance->pop_id, 'item_id' => $balance->item_id, 'lot_no' => $balance->lot_no]) }}"
                                   class="flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-sky-700 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-sky-950/40 transition-colors">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                    <span>Kirim Transfer</span>
                                </a>
                                @endif

                                @if($canRequestStock && $balance->pop->type === 'cabang')
                                <a href="{{ route('warehouse.stock-requests.create', ['pop_id' => $balance->pop_id, 'item_id' => $balance->item_id, 'lot_no' => $balance->lot_no]) }}"
                                   class="flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-indigo-700 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 transition-colors">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                    <span>Minta Stok</span>
                                </a>
                                @endif

                                @if($canIssue && $balance->pop->type === 'cabang')
                                <a href="{{ route('warehouse.issues.create', ['pop_id' => $balance->pop_id, 'item_id' => $balance->item_id, 'lot_no' => $balance->lot_no]) }}"
                                   class="flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-emerald-700 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 transition-colors">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a4 4 0 10-4-4"/></svg>
                                    <span>Serah Teknisi</span>
                                </a>
                                @endif

                                @if($canAdjust)
                                @if(($canTransfer && $balance->pop->type === 'pusat') || (($canRequestStock || $canIssue) && $balance->pop->type === 'cabang'))
                                <div class="my-1 border-t border-slate-100 dark:border-slate-700/60"></div>
                                @endif
                                <a href="{{ route('warehouse.adjustments.balance.create', ['pop_id' => $balance->pop_id, 'item_id' => $balance->item_id, 'lot_no' => $balance->lot_no]) }}"
                                   class="flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-amber-700 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/40 transition-colors">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/></svg>
                                    <span>Opname / Sesuaikan</span>
                                </a>
                                <a href="{{ route('warehouse.stock.threshold.create', ['pop_id' => $balance->pop_id, 'item_id' => $balance->item_id, 'lot_no' => $balance->lot_no]) }}"
                                   class="flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-rose-700 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span>Atur Ambang (Threshold)</span>
                                </a>
                                @endif
                            </div>
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

<!-- Modal: Daftar Serial Number (dipicu badge "SERIAL NUMBER" di tabel) -->
<div x-data="serialListModal(@js(route('warehouse.stock.serials')))" @open-serial-modal.window="open($event.detail)"
     x-show="visible" x-cloak
     class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4"
     @click.self="close()" @keydown.escape.window="close()">
    <div class="bg-white dark:bg-slate-800 rounded-lg max-w-md w-full max-h-[80vh] overflow-hidden shadow-2xl border border-slate-200 dark:border-slate-700 flex flex-col">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between bg-cyan-50/60 dark:bg-cyan-950/30">
            <div>
                <h3 class="font-bold text-slate-900 dark:text-white text-sm" x-text="itemName"></h3>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">Serial Number tersedia di <span class="font-semibold" x-text="popName"></span></p>
            </div>
            <button type="button" @click="close()" class="p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 rounded-lg cursor-pointer">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="p-4 overflow-y-auto scroll-smooth">
            <template x-if="loading">
                <div class="py-8 text-center text-slate-400 flex items-center justify-center gap-2">
                    <svg class="w-4 h-4 animate-spin text-cyan-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <span class="text-xs">Memuat daftar SN...</span>
                </div>
            </template>

            <template x-if="!loading && serials.length === 0">
                <p class="py-8 text-center text-xs text-slate-400 italic">Tidak ada SN berstatus tersedia di gudang ini saat ini.</p>
            </template>

            <div x-show="!loading && serials.length > 0" class="flex flex-wrap gap-1.5">
                <template x-for="sn in serials" :key="sn">
                    <span class="text-[11px] font-mono font-bold px-2.5 py-1 rounded-lg bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300" x-text="sn"></span>
                </template>
            </div>
            <p class="mt-3 text-[10px] text-slate-400" x-show="!loading && serials.length >= 200">Menampilkan 200 SN pertama — buka Lacak Barang buat pencarian 1 SN spesifik.</p>
        </div>
    </div>
</div>
</div>

@push('scripts')
<script>
function serialListModal(endpoint) {
    return {
        endpoint: endpoint,
        visible: false,
        loading: false,
        itemName: '',
        popName: '',
        serials: [],

        open(detail) {
            this.visible = true;
            this.loading = true;
            this.itemName = detail.itemName;
            this.popName = detail.popName;
            this.serials = [];

            fetch(`${this.endpoint}?pop_id=${detail.popId}&item_id=${detail.itemId}`, { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(data => { this.serials = data.serials || []; })
                .catch(() => { this.serials = []; })
                .finally(() => { this.loading = false; });
        },

        close() {
            this.visible = false;
        },
    };
}
</script>
@endpush

@endsection
