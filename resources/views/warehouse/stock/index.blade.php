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

<!-- Filter & Search Toolbar Card (Structured & Responsive) -->
<div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-4 sm:p-5 shadow-xs space-y-4 mb-5">
    <form method="GET" action="{{ route('warehouse.stock.index') }}" id="stockFilterForm" class="space-y-4">
        <!-- BARIS 1: Search Bar, Quick Status Pills, & Reset Button -->
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
            <!-- Cari Cepat (Live Search) -->
            <div class="relative flex-1">
                <input type="text" name="search" id="search" value="{{ $search }}"
                       placeholder="Cari Cepat (Nama Barang, Kode SKU, dll)..."
                       class="w-full h-10 pl-10 pr-4 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-800/60 text-slate-800 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:bg-white dark:focus:bg-slate-800 focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all shadow-2xs">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 dark:text-slate-500">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                    </svg>
                </div>
            </div>

            <!-- Filter Cepat Status Pills & Reset Button -->
            <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap shrink-0">
                <!-- Status Pills -->
                <div class="flex items-center p-1 bg-slate-100 dark:bg-slate-800/80 rounded-xl text-xs font-semibold w-full sm:w-auto">
                    <label class="flex-1 sm:flex-none text-center px-3.5 py-1.5 rounded-lg transition-all cursor-pointer {{ !$lowStockOnly ? 'bg-white dark:bg-slate-900 text-sky-600 dark:text-sky-400 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100' }}">
                        <input type="radio" name="low_stock_only" value="0" {{ !$lowStockOnly ? 'checked' : '' }} onchange="this.form.submit()" class="hidden">
                        <span>Semua Stok</span>
                    </label>
                    <label class="flex-1 sm:flex-none text-center px-3.5 py-1.5 rounded-lg transition-all cursor-pointer flex items-center justify-center gap-1.5 {{ $lowStockOnly ? 'bg-white dark:bg-slate-900 text-rose-600 dark:text-rose-400 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100' }}">
                        <input type="radio" name="low_stock_only" value="1" {{ $lowStockOnly ? 'checked' : '' }} onchange="this.form.submit()" class="hidden">
                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                        <span>Stok Menipis</span>
                    </label>
                </div>

                @if($search || $popFilter || $lowStockOnly || $trackingFilter || $categoryFilter || $itemFilter)
                <a href="{{ route('warehouse.stock.index') }}"
                   class="h-10 px-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-semibold inline-flex items-center justify-center gap-1.5 transition-colors shadow-2xs shrink-0"
                   title="Reset Semua Filter">
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/>
                    </svg>
                    <span class="hidden sm:inline">Reset</span>
                </a>
                @endif
            </div>
        </div>

        <!-- BARIS 2: Dropdown Filters Grid (Responsive: Smartphone 1-col, Tablet 2-col, Desktop 4-col) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
            <!-- Gudang POP Dropdown -->
            <div>
                <label for="pop_id" class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 flex items-center gap-1">
                    <svg class="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    <span>Gudang POP</span>
                </label>
                <select name="pop_id" id="pop_id" onchange="this.form.submit()" class="w-full h-9 px-3 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all shadow-2xs">
                    <option value="">— Semua Gudang Terjangkau —</option>
                    @foreach($pops as $pop)
                    <option value="{{ $pop->id }}" {{ (string) $popFilter === (string) $pop->id ? 'selected' : '' }}>
                        {{ $pop->name }} ({{ strtoupper($pop->type) }})
                    </option>
                    @endforeach
                </select>
            </div>

            <!-- Kategori Dropdown -->
            <div>
                <label for="category_id" class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 flex items-center gap-1">
                    <svg class="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                    <span>Kategori Barang</span>
                </label>
                <select name="category_id" id="category_id" onchange="handleStockCategoryChange(this)" class="w-full h-9 px-3 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all shadow-2xs">
                    <option value="">— Semua Kategori —</option>
                    @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ (string) $categoryFilter === (string) $cat->id ? 'selected' : '' }}>
                        {{ $cat->name }}
                    </option>
                    @endforeach
                </select>
            </div>

            <!-- Nama Barang Dropdown -->
            <div>
                <label for="item_id" class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 flex items-center gap-1">
                    <svg class="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    <span>Nama Barang</span>
                </label>
                <select name="item_id" id="item_id" onchange="this.form.submit()" class="w-full h-9 px-3 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all shadow-2xs">
                    <option value="">— Semua Barang —</option>
                    @foreach($items as $item)
                    <option value="{{ $item->id }}" data-category="{{ $item->item_category_id }}" {{ (string) $itemFilter === (string) $item->id ? 'selected' : '' }}>
                        {{ $item->name }} ({{ $item->code }})
                    </option>
                    @endforeach
                </select>
            </div>

            <!-- Jenis Tracking Dropdown -->
            <div>
                <label for="tracking_type" class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 flex items-center gap-1">
                    <svg class="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    <span>Jenis Tracking</span>
                </label>
                <select name="tracking_type" id="tracking_type" onchange="this.form.submit()" class="w-full h-9 px-3 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all shadow-2xs">
                    <option value="">— Semua Jenis —</option>
                    @foreach(\App\Enums\TrackingType::cases() as $type)
                    <option value="{{ $type->value }}" {{ $trackingFilter === $type->value ? 'selected' : '' }}>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </form>
</div>

<!-- Kontainer Utama List Stok Barang -->
<div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl shadow-xs overflow-hidden" x-data="{}">
    @if($balances->isEmpty())
    <div class="p-12 sm:p-16 text-center">
        <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400">
            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </svg>
        </div>
        <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Tidak ada data stok yang cocok</h4>
        <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">Coba ubah kata kunci pencarian atau reset filter gudang.</p>
        @if($search || $popFilter || $lowStockOnly || $trackingFilter || $categoryFilter || $itemFilter)
        <a href="{{ route('warehouse.stock.index') }}" class="mt-4 inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-xs font-semibold rounded-xl text-slate-700 dark:text-slate-200 transition-colors">
            <span>Reset Semua Filter</span>
        </a>
        @endif
    </div>
    @else

    <!-- TAMPILAN DESKTOP & LAPTOP: Table View (hidden lg:block) -->
    <div class="hidden lg:block overflow-x-auto scroll-smooth min-h-[300px]">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50/80 dark:bg-slate-800/60">
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
            <tbody class="bg-white dark:bg-slate-900 divide-y divide-slate-100 dark:divide-slate-800">
                @foreach($balances as $balance)
                @php
                    $isLow = $balance->isLowStock();
                    $qty = (float) $balance->qty;
                    $min = (float) ($balance->minimum_stock ?? 0);
                    $trackingType = $balance->item->tracking_type->value ?? 'quantity';
                    $ratio = $min > 0 ? min(100, round(($qty / $min) * 100)) : 100;

                    // Opname terakhir per item per gudang
                    $opnameKey = $balance->pop_id.'-'.$balance->item_id.'-'.($balance->lot_no ?: '');
                    $lastOpnameAt = isset($lastOpnameByKey[$opnameKey]) ? \Illuminate\Support\Carbon::parse($lastOpnameByKey[$opnameKey]) : null;
                @endphp
                <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors {{ $isLow ? 'bg-rose-50/25 dark:bg-rose-950/10' : '' }}">
                    <!-- Gudang POP -->
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300">
                        <div class="flex items-center gap-1.5">
                            <span class="font-bold text-slate-800 dark:text-slate-200">{{ $balance->pop->name }}</span>
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold {{ $balance->pop->type === 'pusat' ? 'bg-sky-100 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300' }}">
                                {{ strtoupper($balance->pop->type) }}
                            </span>
                        </div>
                    </td>

                    <!-- Detail Barang -->
                    <td class="px-6 py-4">
                        <div class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ $balance->item->name }}</div>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="text-xs font-mono text-slate-400 dark:text-slate-500">{{ $balance->item->code }}</span>
                            @if($balance->item->category)
                            <span class="px-1.5 py-0.5 rounded text-[10px] bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-medium">
                                {{ $balance->item->category->name }}
                            </span>
                            @endif
                        </div>
                    </td>

                    <!-- Jenis & Lot -->
                    <td class="px-6 py-4 whitespace-nowrap">
                        @if($trackingType === 'serialized')
                        <button type="button"
                            @click="$dispatch('open-serial-modal', { popId: {{ $balance->pop_id }}, itemId: {{ $balance->item_id }}, itemName: @js($balance->item->name), popName: @js($balance->pop->name) })"
                            title="Lihat daftar Serial Number yang tersedia di gudang ini"
                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-cyan-50 hover:bg-cyan-100 dark:bg-cyan-950/40 dark:hover:bg-cyan-900/50 text-cyan-700 dark:text-cyan-300 border border-cyan-200 dark:border-cyan-800 cursor-pointer transition-colors">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
                            <span>SERIAL NUMBER</span>
                        </button>
                        <div class="text-[10px] text-slate-400 dark:text-slate-500 mt-1 font-mono">{{ $qty !== null ? rtrim(rtrim(number_format($qty, 0, ',', '.'), '0'), ',') : 0 }} SN siap</div>
                        @elseif($trackingType === 'roll')
                        <button type="button"
                            @click="$dispatch('open-roll-modal', { popId: {{ $balance->pop_id }}, itemId: {{ $balance->item_id }}, itemName: @js($balance->item->name), popName: @js($balance->pop->name) })"
                            title="Lihat daftar roll yang tersedia di gudang ini"
                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 hover:bg-amber-100 dark:bg-amber-950/40 dark:hover:bg-amber-900/50 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800 cursor-pointer transition-colors">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a1 1 0 001-1v-4a1 1 0 00-1-1H9a1 1 0 00-1 1v4a1 1 0 001 1zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            <span>ROLL KABEL</span>
                        </button>
                        <div class="text-[10px] text-slate-400 dark:text-slate-500 mt-1 font-mono">Sisa total meter</div>
                        @else
                        @php
                            $priceKey = $balance->item_id.'-'.($balance->lot_no ?: '');
                            $lotPrice = $lastPriceByKey[$priceKey] ?? null;
                            $isMultiLot = isset($multiLotPopItemKeys[$balance->pop_id.'-'.$balance->item_id]);
                        @endphp
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                            <span>QUANTITY</span>
                        </span>
                        @if($isMultiLot)
                        <div class="text-[10px] {{ $balance->lot_no ? 'text-purple-600 dark:text-purple-400 font-bold' : 'text-slate-400' }} mt-1 font-mono">
                            {{ $balance->lot_no ? 'Harga Baru' : 'Harga Lama' }}
                        </div>
                        @elseif($lotPrice === null)
                        <div class="text-[10px] text-slate-400 dark:text-slate-500 mt-1">Non-serial</div>
                        @endif
                        @if($lotPrice !== null)
                        <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5 font-mono">Rp {{ number_format($lotPrice, 0, ',', '.') }}/{{ $balance->item->unit }}</div>
                        @endif
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
                        <div class="text-[10px] text-slate-400 dark:text-slate-500 mt-1 font-mono">Min: {{ rtrim(rtrim(number_format($min, 2, ',', '.'), '0'), ',') }} {{ $trackingType === 'roll' ? 'meter' : $balance->item->unit }}</div>
                        @endif
                        <div class="text-[10px] {{ $lastOpnameAt ? 'text-slate-400 dark:text-slate-500' : 'text-amber-500 dark:text-amber-400 font-semibold' }} mt-0.5">
                            Opname: {{ $lastOpnameAt ? $lastOpnameAt->diffForHumans() : 'Belum pernah' }}
                        </div>
                    </td>

                    <!-- Qty Tersedia -->
                    <td class="px-6 py-4 whitespace-nowrap text-right font-mono">
                        <span class="text-base font-extrabold {{ $isLow ? 'text-rose-600 dark:text-rose-400' : 'text-slate-900 dark:text-slate-100' }}">
                            {{ rtrim(rtrim(number_format($qty, 2, ',', '.'), '0'), ',') }}
                        </span>
                        <span class="text-xs font-semibold text-slate-400 ml-0.5">{{ $trackingType === 'roll' ? 'meter' : $balance->item->unit }}</span>
                    </td>

                    <!-- Aksi -->
                    @if($showActionColumn)
                    <td class="px-6 py-4 whitespace-nowrap text-right text-xs relative">
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
                                class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-colors cursor-pointer">
                                <span>⋮ Aksi</span>
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
                                    </div>

                                    @if($canAdjust)
                                    <div class="py-1">
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
                                    </div>
                                    @endif
                                </div>
                            </template>
                        </div>
                    </td>
                    @endif
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- TAMPILAN SMARTPHONE & TABLET: Responsive Card View (block lg:hidden) -->
    <div class="block lg:hidden p-3 sm:p-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            @foreach($balances as $balance)
            @php
                $isLow = $balance->isLowStock();
                $qty = (float) $balance->qty;
                $min = (float) ($balance->minimum_stock ?? 0);
                $trackingType = $balance->item->tracking_type->value ?? 'quantity';
                $opnameKey = $balance->pop_id.'-'.$balance->item_id.'-'.($balance->lot_no ?: '');
                $lastOpnameAt = isset($lastOpnameByKey[$opnameKey]) ? \Illuminate\Support\Carbon::parse($lastOpnameByKey[$opnameKey]) : null;
                $priceKey = $balance->item_id.'-'.($balance->lot_no ?: '');
                $lotPrice = $lastPriceByKey[$priceKey] ?? null;
                $isMultiLot = isset($multiLotPopItemKeys[$balance->pop_id.'-'.$balance->item_id]);
            @endphp
            <div class="p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-800/90 shadow-xs hover:border-sky-300 dark:hover:border-sky-700 transition-all flex flex-col justify-between space-y-3 {{ $isLow ? 'bg-rose-50/20 dark:bg-rose-950/15 border-rose-200 dark:border-rose-900/60' : '' }}">
                <!-- Header Card: POP + Status Stok + Action Dropdown -->
                <div class="flex items-start justify-between gap-2">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="font-bold text-xs text-slate-800 dark:text-slate-200">{{ $balance->pop->name }}</span>
                        <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold {{ $balance->pop->type === 'pusat' ? 'bg-sky-100 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300' }}">
                            {{ strtoupper($balance->pop->type) }}
                        </span>
                        @if($balance->item->category)
                        <span class="px-1.5 py-0.5 rounded text-[10px] bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-400 font-medium">
                            {{ $balance->item->category->name }}
                        </span>
                        @endif
                    </div>

                    <div class="flex items-center gap-1.5 shrink-0">
                        @if($isLow)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 dark:bg-rose-900/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                            Menipis
                        </span>
                        @else
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            Aman
                        </span>
                        @endif

                        @if($showActionColumn)
                        <button type="button"
                                @click="$dispatch('open-stock-actions', {
                                    popName: @js($balance->pop->name),
                                    popType: @js($balance->pop->type),
                                    itemName: @js($balance->item->name),
                                    itemCode: @js($balance->item->code),
                                    categoryName: @js($balance->item->category?->name),
                                    qtyFormatted: @js(rtrim(rtrim(number_format($qty, 2, ',', '.'), '0'), ',')),
                                    unit: @js($trackingType === 'roll' ? 'meter' : $balance->item->unit),
                                    isLow: {{ $isLow ? 'true' : 'false' }},
                                    trackingType: @js($trackingType),
                                    popId: {{ $balance->pop_id }},
                                    itemId: {{ $balance->item_id }},
                                    transferUrl: @js($canTransfer && $balance->pop->type === 'pusat' ? route('warehouse.transfers.create', ['pop_id' => $balance->pop_id, 'item_id' => $balance->item_id, 'lot_no' => $balance->lot_no]) : null),
                                    requestStockUrl: @js($canRequestStock && $balance->pop->type === 'cabang' ? route('warehouse.stock-requests.create', ['pop_id' => $balance->pop_id, 'item_id' => $balance->item_id, 'lot_no' => $balance->lot_no]) : null),
                                    issueUrl: @js($canIssue && $balance->pop->type === 'cabang' ? route('warehouse.issues.create', ['pop_id' => $balance->pop_id, 'item_id' => $balance->item_id, 'lot_no' => $balance->lot_no]) : null),
                                    adjustUrl: @js($canAdjust ? route('warehouse.adjustments.balance.create', ['pop_id' => $balance->pop_id, 'item_id' => $balance->item_id, 'lot_no' => $balance->lot_no]) : null),
                                    thresholdUrl: @js($canAdjust ? route('warehouse.stock.threshold.create', ['pop_id' => $balance->pop_id, 'item_id' => $balance->item_id, 'lot_no' => $balance->lot_no]) : null),
                                })"
                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-700/60 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-colors cursor-pointer text-xs font-bold"
                                title="Menu Aksi">
                            <span>⋮</span>
                        </button>
                        @endif
                    </div>
                </div>

                <!-- Body Card: Item Name & Tracking Badge -->
                <div>
                    <h4 class="font-bold text-slate-900 dark:text-slate-100 text-sm sm:text-base leading-snug">{{ $balance->item->name }}</h4>
                    <div class="flex items-center gap-2 mt-1 flex-wrap">
                        <span class="text-xs font-mono text-slate-400 dark:text-slate-500">{{ $balance->item->code }}</span>
                        
                        @if($trackingType === 'serialized')
                        <button type="button"
                            @click="$dispatch('open-serial-modal', { popId: {{ $balance->pop_id }}, itemId: {{ $balance->item_id }}, itemName: @js($balance->item->name), popName: @js($balance->pop->name) })"
                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-cyan-50 hover:bg-cyan-100 dark:bg-cyan-950/40 text-cyan-700 dark:text-cyan-300 border border-cyan-200 dark:border-cyan-800 cursor-pointer">
                            <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
                            <span>{{ $qty !== null ? rtrim(rtrim(number_format($qty, 0, ',', '.'), '0'), ',') : 0 }} SN Siap</span>
                        </button>
                        @elseif($trackingType === 'roll')
                        <button type="button"
                            @click="$dispatch('open-roll-modal', { popId: {{ $balance->pop_id }}, itemId: {{ $balance->item_id }}, itemName: @js($balance->item->name), popName: @js($balance->pop->name) })"
                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 hover:bg-amber-100 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800 cursor-pointer">
                            <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a1 1 0 001-1v-4a1 1 0 00-1-1H9a1 1 0 00-1 1v4a1 1 0 001 1zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            <span>Lihat Roll</span>
                        </button>
                        @else
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                            <span>QUANTITY</span>
                        </span>
                        @if($isMultiLot)
                        <span class="text-[10px] {{ $balance->lot_no ? 'text-purple-600 dark:text-purple-400 font-bold' : 'text-slate-400' }} font-mono">
                            {{ $balance->lot_no ? '• Harga Baru' : '• Harga Lama' }}
                        </span>
                        @endif
                        @if($lotPrice !== null)
                        <span class="text-[10px] text-slate-500 dark:text-slate-400 font-mono">Rp {{ number_format($lotPrice, 0, ',', '.') }}/{{ $balance->item->unit }}</span>
                        @endif
                        @endif
                    </div>
                </div>

                <!-- Bottom Stats Grid: Qty Tersedia vs Ambang & Opname -->
                <div class="grid grid-cols-2 gap-2 text-xs bg-slate-50 dark:bg-slate-800/50 p-2.5 rounded-xl border border-slate-100 dark:border-slate-800">
                    <div>
                        <span class="text-slate-400 dark:text-slate-500 text-[10px] uppercase font-bold tracking-wider block">Stok Tersedia</span>
                        <div class="flex items-baseline gap-1 mt-0.5">
                            <span class="text-base font-extrabold font-mono {{ $isLow ? 'text-rose-600 dark:text-rose-400' : 'text-slate-900 dark:text-slate-100' }}">
                                {{ rtrim(rtrim(number_format($qty, 2, ',', '.'), '0'), ',') }}
                            </span>
                            <span class="text-xs font-semibold text-slate-400">{{ $trackingType === 'roll' ? 'meter' : $balance->item->unit }}</span>
                        </div>
                    </div>
                    <div>
                        <span class="text-slate-400 dark:text-slate-500 text-[10px] uppercase font-bold tracking-wider block">Batas &amp; Opname</span>
                        <div class="text-[11px] text-slate-600 dark:text-slate-300 font-medium mt-0.5">
                            Min: <span class="font-mono">{{ $min > 0 ? rtrim(rtrim(number_format($min, 2, ',', '.'), '0'), ',') : '-' }}</span>
                        </div>
                        <div class="text-[10px] {{ $lastOpnameAt ? 'text-slate-400 dark:text-slate-500' : 'text-amber-500 dark:text-amber-400 font-semibold' }}">
                            {{ $lastOpnameAt ? 'Opname: '.$lastOpnameAt->diffForHumans() : 'Belum pernah opname' }}
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    @if($balances->hasPages())
    <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/40">
        {{ $balances->links() }}
    </div>
    @endif
    @endif
</div>

<!-- Modal: Daftar Serial Number (dipicu badge "SERIAL NUMBER" di tabel & card) -->
<div x-data="serialListModal(@js(route('warehouse.stock.serials')))" @open-serial-modal.window="open($event.detail)">
    <template x-teleport="body">
        <div x-show="visible" x-cloak
             x-effect="document.body.classList.toggle('overflow-hidden', visible)"
             class="fixed inset-0 z-[9999] bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 overflow-y-auto"
             @click.self="close()" @keydown.escape.window="close()">
            <div class="bg-white dark:bg-slate-800 rounded-2xl max-w-2xl w-full max-h-[85vh] sm:max-h-[80vh] overflow-hidden shadow-2xl border border-slate-200 dark:border-slate-700 flex flex-col my-auto"
                 @click.stop>
                <div class="px-4 sm:px-5 py-3.5 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between bg-cyan-50/70 dark:bg-cyan-950/40 shrink-0">
                    <div class="min-w-0 pr-2">
                        <h3 class="font-bold text-slate-900 dark:text-white text-xs sm:text-sm truncate" x-text="itemName"></h3>
                        <p class="text-[10px] sm:text-[11px] text-slate-500 dark:text-slate-400 truncate">Rincian Serial Number tersedia di <span class="font-semibold" x-text="popName"></span></p>
                    </div>
                    <button type="button" @click="close()" class="p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 rounded-lg cursor-pointer shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="overflow-y-auto scroll-smooth p-1 sm:p-2">
                    <template x-if="loading">
                        <div class="py-10 text-center text-slate-400 flex items-center justify-center gap-2">
                            <svg class="w-4 h-4 animate-spin text-cyan-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span class="text-xs">Memuat daftar SN...</span>
                        </div>
                    </template>

                    <template x-if="!loading && serials.length === 0">
                        <p class="py-10 text-center text-xs text-slate-400 italic">Tidak ada SN berstatus tersedia di gudang ini saat ini.</p>
                    </template>

                    <div x-show="!loading && serials.length > 0" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-xs">
                            <thead class="bg-slate-50 dark:bg-slate-800/60">
                                <tr>
                                    <th class="px-3.5 py-2.5 text-left font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Serial Number</th>
                                    <th class="px-3.5 py-2.5 text-left font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Kondisi</th>
                                    <th class="px-3.5 py-2.5 text-right font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Harga Beli</th>
                                    <th class="px-3.5 py-2.5 text-right font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Diterima</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700/50">
                                <template x-for="serial in serials" :key="serial.serial_number">
                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30 transition-colors">
                                        <td class="px-3.5 py-2.5 font-mono font-bold text-slate-700 dark:text-slate-300" x-text="serial.serial_number"></td>
                                        <td class="px-3.5 py-2.5">
                                            <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold border"
                                                :class="{
                                                    'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800': serial.condition === 'new',
                                                    'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400 border-rose-200 dark:border-rose-800': serial.condition === 'used_damaged',
                                                    'bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-400 border-sky-200 dark:border-sky-800': serial.condition === 'used_good' && serial.condition_checked,
                                                    'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-800': serial.condition === 'used_good' && !serial.condition_checked,
                                                }"
                                                x-text="serial.condition === 'new' ? 'Baru' : (serial.condition === 'used_damaged' ? 'Bekas — Rusak' : (serial.condition_checked ? 'Bekas — Sudah Dicek' : 'Bekas — Belum Dicek'))"></span>
                                        </td>
                                        <td class="px-3.5 py-2.5 text-right font-mono font-semibold text-emerald-600 dark:text-emerald-400" x-text="serial.unit_price_snapshot ? 'Rp ' + Number(serial.unit_price_snapshot).toLocaleString('id-ID') : '-'"></td>
                                        <td class="px-3.5 py-2.5 text-right text-slate-400" x-text="serial.received_at || '-'"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 mb-2 px-3.5 text-[10px] text-slate-400" x-show="!loading && serials.length >= 200">Menampilkan 200 SN pertama — buka Lacak Barang buat pencarian 1 SN spesifik.</p>
                </div>
            </div>
        </div>
    </template>
</div>

<!-- Modal: Daftar Roll Kabel (dipicu badge "ROLL KABEL" di tabel & card) -->
<div x-data="rollListModal(@js(route('warehouse.stock.rolls')))" @open-roll-modal.window="open($event.detail)">
    <template x-teleport="body">
        <div x-show="visible" x-cloak
             x-effect="document.body.classList.toggle('overflow-hidden', visible)"
             class="fixed inset-0 z-[9999] bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 overflow-y-auto"
             @click.self="close()" @keydown.escape.window="close()">
            <div class="bg-white dark:bg-slate-800 rounded-2xl max-w-2xl w-full max-h-[85vh] sm:max-h-[80vh] overflow-hidden shadow-2xl border border-slate-200 dark:border-slate-700 flex flex-col my-auto"
                 @click.stop>
                <div class="px-4 sm:px-5 py-3.5 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between bg-amber-50/70 dark:bg-amber-950/40 shrink-0">
                    <div class="min-w-0 pr-2">
                        <h3 class="font-bold text-slate-900 dark:text-white text-xs sm:text-sm truncate" x-text="itemName"></h3>
                        <p class="text-[10px] sm:text-[11px] text-slate-500 dark:text-slate-400 truncate">Rincian Roll tersedia di <span class="font-semibold" x-text="popName"></span></p>
                    </div>
                    <button type="button" @click="close()" class="p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 rounded-lg cursor-pointer shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="overflow-y-auto scroll-smooth p-1 sm:p-2">
                    <template x-if="loading">
                        <div class="py-10 text-center text-slate-400 flex items-center justify-center gap-2">
                            <svg class="w-4 h-4 animate-spin text-amber-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span class="text-xs">Memuat daftar roll...</span>
                        </div>
                    </template>

                    <template x-if="!loading && rolls.length === 0">
                        <p class="py-10 text-center text-xs text-slate-400 italic">Tidak ada roll berstatus tersedia di gudang ini saat ini.</p>
                    </template>

                    <div x-show="!loading && rolls.length > 0" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-xs">
                            <thead class="bg-slate-50 dark:bg-slate-800/60">
                                <tr>
                                    <th class="px-3.5 py-2.5 text-left font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Roll ID</th>
                                    <th class="px-3.5 py-2.5 text-right font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Sisa / Total</th>
                                    <th class="px-3.5 py-2.5 text-right font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Harga Beli</th>
                                    <th class="px-3.5 py-2.5 text-right font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Nilai Sisa</th>
                                    <th class="px-3.5 py-2.5 text-left font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Vendor &amp; Diterima</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700/50">
                                <template x-for="roll in rolls" :key="roll.roll_code">
                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30 transition-colors">
                                        <td class="px-3.5 py-2.5 font-mono font-bold text-slate-700 dark:text-slate-300" x-text="roll.roll_code"></td>
                                        <td class="px-3.5 py-2.5 text-right font-mono font-semibold text-amber-600 dark:text-amber-400" x-text="Number(roll.length_remaining).toLocaleString('id-ID') + ' / ' + Number(roll.length_total).toLocaleString('id-ID') + ' m'"></td>
                                        <td class="px-3.5 py-2.5 text-right font-mono font-semibold text-slate-700 dark:text-slate-200">
                                            <div x-text="roll.price_per_roll ? 'Rp ' + Number(roll.price_per_roll).toLocaleString('id-ID') + ' / roll' : '-'"></div>
                                            <div class="text-[10px] text-slate-400 font-normal" x-text="roll.price_per_meter ? '(Rp ' + Number(roll.price_per_meter).toLocaleString('id-ID') + ' / m)' : ''"></div>
                                        </td>
                                        <td class="px-3.5 py-2.5 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400" x-text="roll.total_value ? 'Rp ' + Number(roll.total_value).toLocaleString('id-ID') : '-'"></td>
                                        <td class="px-3.5 py-2.5 text-slate-500 dark:text-slate-400">
                                            <div class="font-medium text-slate-700 dark:text-slate-300" x-text="roll.vendor || '-'"></div>
                                            <div class="text-[10px] text-slate-400" x-text="roll.received_at || '-'"></div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 mb-2 px-3.5 text-[10px] text-slate-400" x-show="!loading && rolls.length >= 200">Menampilkan 200 roll pertama — buka Lacak Barang buat pencarian 1 roll spesifik.</p>
                </div>
            </div>
        </div>
    </template>
</div>

<!-- Modal: Mobile Action Sheet (Kelola Stok) -->
<div x-data="stockActionSheet()" @open-stock-actions.window="open($event.detail)">
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
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold"
                                  :class="item.popType === 'pusat' ? 'bg-sky-100 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300'"
                                  x-text="item.popName + ' (' + (item.popType ? item.popType.toUpperCase() : '') + ')'"></span>
                            <template x-if="item.categoryName">
                                <span class="px-2 py-0.5 rounded text-[10px] bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-medium" x-text="item.categoryName"></span>
                            </template>
                            <template x-if="item.isLow">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 dark:bg-rose-900/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                                    Menipis
                                </span>
                            </template>
                        </div>
                        <h3 class="text-sm sm:text-base font-bold text-slate-900 dark:text-slate-100 leading-snug" x-text="item.itemName"></h3>
                        <div class="flex items-center gap-2 text-xs font-mono text-slate-400 dark:text-slate-500">
                            <span x-text="item.itemCode"></span>
                            <span>•</span>
                            <span>Tersedia: <strong class="text-slate-800 dark:text-slate-200" x-text="item.qtyFormatted + ' ' + item.unit"></strong></span>
                        </div>
                    </div>
                    <button type="button" @click="close()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-500 dark:text-slate-400 flex items-center justify-center shrink-0 cursor-pointer">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Action Items List -->
                <div class="space-y-2">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 px-1">Pilih Aksi Barang</span>

                    <!-- Kirim Transfer -->
                    <template x-if="item.transferUrl">
                        <a :href="item.transferUrl"
                           class="flex items-center gap-3 p-3 rounded-xl bg-sky-50/60 hover:bg-sky-100/70 dark:bg-sky-950/30 dark:hover:bg-sky-900/40 border border-sky-100 dark:border-sky-900/50 text-sky-800 dark:text-sky-300 transition-colors">
                            <div class="w-9 h-9 rounded-lg bg-sky-500 text-white flex items-center justify-center shrink-0 shadow-xs">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                            </div>
                            <div class="flex-1">
                                <div class="text-xs font-bold">Kirim Transfer Antar Gudang</div>
                                <div class="text-[10px] text-sky-600/80 dark:text-sky-400/80">Kirim mutasi stok ke POP Cabang</div>
                            </div>
                            <svg class="w-4 h-4 text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </template>

                    <!-- Minta Stok -->
                    <template x-if="item.requestStockUrl">
                        <a :href="item.requestStockUrl"
                           class="flex items-center gap-3 p-3 rounded-xl bg-indigo-50/60 hover:bg-indigo-100/70 dark:bg-indigo-950/30 dark:hover:bg-indigo-900/40 border border-indigo-100 dark:border-indigo-900/50 text-indigo-800 dark:text-indigo-300 transition-colors">
                            <div class="w-9 h-9 rounded-lg bg-indigo-500 text-white flex items-center justify-center shrink-0 shadow-xs">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            </div>
                            <div class="flex-1">
                                <div class="text-xs font-bold">Minta Stok dari Pusat</div>
                                <div class="text-[10px] text-indigo-600/80 dark:text-indigo-400/80">Buat permohonan permintaan barang</div>
                            </div>
                            <svg class="w-4 h-4 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </template>

                    <!-- Serah Teknisi -->
                    <template x-if="item.issueUrl">
                        <a :href="item.issueUrl"
                           class="flex items-center gap-3 p-3 rounded-xl bg-emerald-50/60 hover:bg-emerald-100/70 dark:bg-emerald-950/30 dark:hover:bg-emerald-900/40 border border-emerald-100 dark:border-emerald-900/50 text-emerald-800 dark:text-emerald-300 transition-colors">
                            <div class="w-9 h-9 rounded-lg bg-emerald-500 text-white flex items-center justify-center shrink-0 shadow-xs">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a4 4 0 10-4-4"/></svg>
                            </div>
                            <div class="flex-1">
                                <div class="text-xs font-bold">Serahkan ke Teknisi Lapangan</div>
                                <div class="text-[10px] text-emerald-600/80 dark:text-emerald-400/80">Keluarkan barang untuk instalasi/maintenance</div>
                            </div>
                            <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </template>

                    <!-- Opname / Sesuaikan -->
                    <template x-if="item.adjustUrl">
                        <a :href="item.adjustUrl"
                           class="flex items-center gap-3 p-3 rounded-xl bg-amber-50/60 hover:bg-amber-100/70 dark:bg-amber-950/30 dark:hover:bg-amber-900/40 border border-amber-100 dark:border-amber-900/50 text-amber-800 dark:text-amber-300 transition-colors">
                            <div class="w-9 h-9 rounded-lg bg-amber-500 text-white flex items-center justify-center shrink-0 shadow-xs">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/></svg>
                            </div>
                            <div class="flex-1">
                                <div class="text-xs font-bold">Opname / Penyesuaian Stok</div>
                                <div class="text-[10px] text-amber-600/80 dark:text-amber-400/80">Koreksi selisih fisik gudang & catat BAP</div>
                            </div>
                            <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </template>

                    <!-- Atur Ambang -->
                    <template x-if="item.thresholdUrl">
                        <a :href="item.thresholdUrl"
                           class="flex items-center gap-3 p-3 rounded-xl bg-rose-50/60 hover:bg-rose-100/70 dark:bg-rose-950/30 dark:hover:bg-rose-900/40 border border-rose-100 dark:border-rose-900/50 text-rose-800 dark:text-rose-300 transition-colors">
                            <div class="w-9 h-9 rounded-lg bg-rose-500 text-white flex items-center justify-center shrink-0 shadow-xs">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div class="flex-1">
                                <div class="text-xs font-bold">Atur Ambang Batas Minimum</div>
                                <div class="text-[10px] text-rose-600/80 dark:text-rose-400/80">Konfigurasi peringatan stok menipis</div>
                            </div>
                            <svg class="w-4 h-4 text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </template>

                    <!-- Shortcut Serial Number list if tracking_type === serialized -->
                    <template x-if="item.trackingType === 'serialized'">
                        <button type="button"
                                @click="close(); $dispatch('open-serial-modal', { popId: item.popId, itemId: item.itemId, itemName: item.itemName, popName: item.popName })"
                                class="w-full flex items-center gap-3 p-3 rounded-xl bg-cyan-50/60 hover:bg-cyan-100/70 dark:bg-cyan-950/30 dark:hover:bg-cyan-900/40 border border-cyan-100 dark:border-cyan-900/50 text-cyan-800 dark:text-cyan-300 transition-colors cursor-pointer text-left">
                            <div class="w-9 h-9 rounded-lg bg-cyan-500 text-white flex items-center justify-center shrink-0 shadow-xs">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
                            </div>
                            <div class="flex-1">
                                <div class="text-xs font-bold">Lihat Daftar Serial Number</div>
                                <div class="text-[10px] text-cyan-600/80 dark:text-cyan-400/80">Daftar SN yang tersedia di gudang ini</div>
                            </div>
                            <svg class="w-4 h-4 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </template>

                    <!-- Shortcut Roll Kabel list if tracking_type === roll -->
                    <template x-if="item.trackingType === 'roll'">
                        <button type="button"
                                @click="close(); $dispatch('open-roll-modal', { popId: item.popId, itemId: item.itemId, itemName: item.itemName, popName: item.popName })"
                                class="w-full flex items-center gap-3 p-3 rounded-xl bg-amber-50/60 hover:bg-amber-100/70 dark:bg-amber-950/30 dark:hover:bg-amber-900/40 border border-amber-100 dark:border-amber-900/50 text-amber-800 dark:text-amber-300 transition-colors cursor-pointer text-left">
                            <div class="w-9 h-9 rounded-lg bg-amber-500 text-white flex items-center justify-center shrink-0 shadow-xs">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a1 1 0 001-1v-4a1 1 0 00-1-1H9a1 1 0 00-1 1v4a1 1 0 001 1zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            </div>
                            <div class="flex-1">
                                <div class="text-xs font-bold">Lihat Daftar Roll Kabel</div>
                                <div class="text-[10px] text-amber-600/80 dark:text-amber-400/80">Rincian sisa meter per roll di gudang</div>
                            </div>
                            <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </button>
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

function stockActionSheet() {
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

function rollListModal(endpoint) {
    return {
        endpoint: endpoint,
        visible: false,
        loading: false,
        itemName: '',
        popName: '',
        rolls: [],

        open(detail) {
            this.visible = true;
            this.loading = true;
            this.itemName = detail.itemName;
            this.popName = detail.popName;
            this.rolls = [];

            fetch(`${this.endpoint}?pop_id=${detail.popId}&item_id=${detail.itemId}`, { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(data => { this.rolls = data.rolls || []; })
                .catch(() => { this.rolls = []; })
                .finally(() => { this.loading = false; });
        },

        close() {
            this.visible = false;
        },
    };
}

function filterStockItemsByCategory(catId) {
    const itemSelect = document.getElementById('item_id');
    if (!itemSelect) return;
    const options = itemSelect.querySelectorAll('option[data-category]');
    options.forEach(opt => {
        if (!catId || opt.dataset.category === catId) {
            opt.hidden = false;
            opt.disabled = false;
        } else {
            opt.hidden = true;
            opt.disabled = true;
            if (opt.selected) {
                itemSelect.value = '';
            }
        }
    });
}

function handleStockCategoryChange(selectElement) {
    const itemSelect = document.getElementById('item_id');
    if (itemSelect) {
        itemSelect.value = '';
    }
    selectElement.form.submit();
}

document.addEventListener('DOMContentLoaded', function() {
    const catSelect = document.getElementById('category_id');
    if (catSelect && catSelect.value) {
        filterStockItemsByCategory(catSelect.value);
    }

    const searchInput = document.getElementById('search');
    if (searchInput) {
        let searchTimeout = null;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 400);
        });

        // Pertahankan fokus di search input jika user sedang aktif mencari
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('search') && urlParams.get('search') !== '') {
            searchInput.focus();
            const val = searchInput.value;
            searchInput.value = '';
            searchInput.value = val;
        }
    }
});
</script>
@endpush

@endsection
