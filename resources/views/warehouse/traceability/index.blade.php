@extends('layouts.app')

@section('title', 'Lacak Barang / Nomor Seri - Whusnet Operasional')
@section('page_title', 'Lacak Barang / SN')

@section('content')

<x-warehouse.header active="traceability" />

{{--
    Redesign (2026-09-04): satu halaman, tiga tab — "Lacak SN" (existing,
    dipertahankan apa adanya) + dua tab BARU buat scan SN barang masuk
    (Single Assign & Batch Assign per kategori Perangkat Aktif — modem/ONT,
    router, OLT module, AP Wireless, SFP, dst).

    Submit "Simpan & Assign" DUA-DUANYA POST ke rute yang SAMA
    (`warehouse.receive.store-scanned`) — lihat docblock
    `WarehouseReceiveController::storeScanned()`: Single & Batch itu
    identik dari sisi data (satu Gudang Pusat + satu model barang + daftar
    SN + satu harga satuan), bedanya cuma cara daftar SN-nya kekumpul di
    klien. Redirect PRG ke halaman Bon Penerimaan (`warehouse.receive.show`)
    yang SAMA dipakai form manual — bukan halaman hasil terpisah.
--}}
<div x-data="{ tab: @js(request()->query('tab', 'lacak')) }" class="space-y-5">

    <!-- Tab Switcher (Mobile-Optimized Horizontal Scroll / Pills) -->
    <div class="w-full sm:w-fit flex items-center gap-1.5 bg-slate-100/90 dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700/70 rounded-2xl p-1.5 overflow-x-auto no-scrollbar scroll-smooth">
        <button type="button" @click="tab = 'lacak'"
                :class="tab === 'lacak' ? 'bg-white dark:bg-slate-700 text-sky-700 dark:text-sky-300 shadow-xs ring-1 ring-black/5 dark:ring-white/5 font-bold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 hover:bg-white/50 dark:hover:bg-slate-700/40 font-medium'"
                class="inline-flex items-center gap-2 px-3.5 py-2 sm:px-4 sm:py-2.5 rounded-xl text-xs transition-all whitespace-nowrap cursor-pointer shrink-0 min-h-[40px]">
            <svg class="w-4 h-4 text-sky-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
            </svg>
            <span>Lacak SN</span>
        </button>

        <button type="button" @click="tab = 'single'"
                :class="tab === 'single' ? 'bg-white dark:bg-slate-700 text-emerald-700 dark:text-emerald-300 shadow-xs ring-1 ring-black/5 dark:ring-white/5 font-bold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 hover:bg-white/50 dark:hover:bg-slate-700/40 font-medium'"
                class="inline-flex items-center gap-2 px-3.5 py-2 sm:px-4 sm:py-2.5 rounded-xl text-xs transition-all whitespace-nowrap cursor-pointer shrink-0 min-h-[40px]">
            <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            <span class="hidden sm:inline">Scan Masuk — Single Assign</span>
            <span class="sm:hidden">Single Assign</span>
        </button>

        <button type="button" @click="tab = 'batch'"
                :class="tab === 'batch' ? 'bg-white dark:bg-slate-700 text-indigo-700 dark:text-indigo-300 shadow-xs ring-1 ring-black/5 dark:ring-white/5 font-bold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 hover:bg-white/50 dark:hover:bg-slate-700/40 font-medium'"
                class="inline-flex items-center gap-2 px-3.5 py-2 sm:px-4 sm:py-2.5 rounded-xl text-xs transition-all whitespace-nowrap cursor-pointer shrink-0 min-h-[40px]">
            <svg class="w-4 h-4 text-indigo-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5zM13.5 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5z"/>
            </svg>
            <span class="hidden sm:inline">Scan Masuk — Batch Kategori</span>
            <span class="sm:hidden">Batch Assign</span>
        </button>
    </div>

    <!-- ================= TAB 1: LACAK SN ================= -->
    <div x-show="tab === 'lacak'" x-cloak class="space-y-6">

        <!-- Search Hero Panel -->
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-4 sm:p-6 shadow-xs">
            <div class="max-w-3xl">
                <div class="flex items-start gap-3">
                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0 border border-sky-100 dark:border-sky-800/60 mt-0.5">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm sm:text-base font-bold text-slate-900 dark:text-slate-100">
                            Lacak Riwayat Serial Number (SN) Perangkat
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                            Ketik atau scan barcode SN modem ONT, Router, AP Wireless, atau perangkat aktif ISP untuk melihat riwayat silsilah dari gudang hingga pelanggan.
                        </p>
                    </div>
                </div>

                <!-- Responsive Mobile Search Form -->
                <form action="{{ route('warehouse.traceability.index') }}" method="GET" class="mt-4 sm:mt-5">
                    <input type="hidden" name="tab" value="lacak">
                    <div class="flex flex-col sm:flex-row gap-2.5">
                        <div class="relative flex-1">
                            <input type="text"
                                   name="sn"
                                   value="{{ $serialNumber }}"
                                   placeholder="Ketik / scan barcode SN (mis. ZTE0001, HG8245H)..."
                                   autofocus
                                   class="w-full pl-10 pr-4 py-2.5 sm:py-3 text-xs sm:text-sm font-mono font-semibold border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all min-h-[44px]">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5z"/></svg>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <button type="submit"
                                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 sm:py-3 bg-sky-600 hover:bg-sky-700 active:bg-sky-800 text-white rounded-xl text-xs sm:text-sm font-semibold shadow-xs shadow-sky-600/20 transition-all hover:scale-[1.01] active:scale-[0.98] cursor-pointer min-h-[44px]">
                                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                                </svg>
                                <span>Lacak SN</span>
                            </button>

                            @if($serialNumber !== '')
                            <a href="{{ route('warehouse.traceability.index') }}"
                               class="inline-flex items-center justify-center p-2.5 sm:px-3 sm:py-3 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700/50 transition-colors min-h-[44px]"
                               title="Reset Pencarian">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </a>
                            @endif
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- State Not Found -->
        @if($notFound)
        <div class="bg-amber-50/90 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/70 rounded-2xl p-6 sm:p-8 text-center shadow-xs">
            <div class="w-12 h-12 mx-auto mb-3.5 rounded-2xl bg-amber-100 dark:bg-amber-900/50 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <h4 class="text-sm font-bold text-amber-900 dark:text-amber-200">
                Serial Number "<span class="font-mono">{{ $serialNumber }}</span>" tidak ditemukan
            </h4>
            <p class="text-xs text-amber-700/90 dark:text-amber-400 mt-1.5 max-w-md mx-auto leading-relaxed">
                Pastikan nomor seri diketik dengan benar atau periksa apakah perangkat ini berada dalam POP Scope akses Anda.
            </p>
        </div>
        @endif

        <!-- State Found: Serial Detail & Timeline -->
        @if($serial)
        @php
            $status = $serial->status->value ?? '';
            $statusConfig = match($status) {
                'available' => [
                    'label' => 'Tersedia di Gudang',
                    'badge' => 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
                    'icon_bg' => 'bg-emerald-500',
                ],
                'issued' => [
                    'label' => 'Dipegang Teknisi (Custody)',
                    'badge' => 'bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-300 border-sky-200 dark:border-sky-800',
                    'icon_bg' => 'bg-sky-500',
                ],
                'installed' => [
                    'label' => 'Terpasang di Pelanggan',
                    'badge' => 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800',
                    'icon_bg' => 'bg-indigo-500',
                ],
                'in_transit' => [
                    'label' => 'Dalam Pengiriman Transfer',
                    'badge' => 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800',
                    'icon_bg' => 'bg-amber-500',
                ],
                'damaged', 'lost', 'scrapped' => [
                    'label' => strtoupper($status),
                    'badge' => 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800',
                    'icon_bg' => 'bg-rose-500',
                ],
                default => [
                    'label' => $serial->status->label(),
                    'badge' => 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 border-slate-200',
                    'icon_bg' => 'bg-slate-500',
                ],
            };
        @endphp

        <!-- Current Location & Device Status Card -->
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-4 sm:p-6 shadow-xs space-y-4"
             x-data="{ copied: false }">

            <!-- Device Summary Banner -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-slate-100 dark:border-slate-700/60">
                <div class="flex items-start gap-3.5">
                    <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-xl {{ $statusConfig['icon_bg'] }} text-white flex items-center justify-center shadow-md shadow-slate-900/10 shrink-0">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="text-base sm:text-lg font-extrabold text-slate-900 dark:text-slate-100 font-mono tracking-tight break-all">
                                {{ $serial->serial_number }}
                            </h3>

                            <!-- One-Tap Salin SN Button (Touch-Friendly) -->
                            <button type="button"
                                    @click="navigator.clipboard.writeText('{{ $serial->serial_number }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[11px] font-semibold transition-colors bg-slate-100 dark:bg-slate-700/70 text-slate-600 dark:text-slate-300 hover:text-sky-600 dark:hover:text-sky-400 cursor-pointer"
                                    title="Salin Nomor Seri">
                                <svg x-show="!copied" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75"/>
                                </svg>
                                <svg x-show="copied" x-cloak class="w-3.5 h-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                </svg>
                                <span x-text="copied ? 'Tersalin!' : 'Salin'">Salin</span>
                            </button>

                            <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-bold border {{ $statusConfig['badge'] }}">
                                {{ $statusConfig['label'] }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Model: <strong class="text-slate-800 dark:text-slate-200">{{ $serial->item->name }}</strong>
                            <span class="font-mono text-[11px] text-slate-400">({{ $serial->item->code }})</span>
                        </p>
                    </div>
                </div>

                <!-- Location / Responsibility Context Box -->
                <div class="bg-slate-50/80 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-700/60 rounded-xl p-3 text-xs w-full md:w-auto">
                    <span class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                        Lokasi / Penanggung Jawab Terkini
                    </span>
                    <div class="font-bold text-slate-800 dark:text-slate-100 mt-1">
                        @if($serial->status->value === 'installed' && $serial->customer)
                        <a href="{{ route('customers.show', $serial->customer) }}" class="text-sky-600 dark:text-sky-400 hover:underline inline-flex items-center gap-1.5 flex-wrap">
                            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                            </svg>
                            <span>Pelanggan: {{ $serial->customer->full_name }}</span>
                            <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                            </svg>
                        </a>
                        <p class="text-[10px] text-slate-400 font-normal mt-0.5">
                            Terpasang sejak {{ $serial->installed_at?->translatedFormat('d M Y') ?? '-' }}
                        </p>
                        @elseif($serial->current_technician_id)
                        <div class="text-indigo-600 dark:text-indigo-400 inline-flex items-center gap-1.5">
                            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <span>Teknisi: {{ $serial->currentTechnician->name ?? '-' }}</span>
                        </div>
                        <p class="text-[10px] text-slate-400 font-normal mt-0.5">Dalam custody operasional</p>
                        @elseif($serial->current_pop_id)
                        <div class="text-emerald-600 dark:text-emerald-400 inline-flex items-center gap-1.5">
                            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z"/>
                            </svg>
                            <span>Gudang: {{ $serial->currentPop->name ?? '-' }}</span>
                        </div>
                        <p class="text-[10px] text-slate-400 font-normal mt-0.5">Stok fisik tersedia</p>
                        @else
                        <span class="text-slate-500">{{ $serial->status->label() }}</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Quick Info Specs Grid (Mobile-Friendly 2x2 cards) -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-2.5 sm:gap-3.5 pt-1 text-xs">
                <div class="bg-slate-50/70 dark:bg-slate-900/40 p-3 rounded-xl border border-slate-100 dark:border-slate-800">
                    <span class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Kategori Barang</span>
                    <span class="font-semibold text-slate-700 dark:text-slate-300 mt-0.5 block truncate">{{ $serial->item->category?->name ?? '-' }}</span>
                </div>
                <div class="bg-slate-50/70 dark:bg-slate-900/40 p-3 rounded-xl border border-slate-100 dark:border-slate-800">
                    <span class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Gudang Asal Masuk</span>
                    <span class="font-semibold text-slate-700 dark:text-slate-300 mt-0.5 block truncate">{{ $serial->issuedFromPop->name ?? '-' }}</span>
                </div>
                <div class="bg-slate-50/70 dark:bg-slate-900/40 p-3 rounded-xl border border-slate-100 dark:border-slate-800">
                    <span class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Didaftarkan</span>
                    <span class="font-semibold text-slate-700 dark:text-slate-300 mt-0.5 block">{{ $serial->created_at->translatedFormat('d M Y H:i') }}</span>
                </div>
                <div class="bg-slate-50/70 dark:bg-slate-900/40 p-3 rounded-xl border border-slate-100 dark:border-slate-800">
                    <span class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Total Mutasi Ledger</span>
                    <span class="font-bold text-sky-600 dark:text-sky-400 font-mono mt-0.5 block">{{ $ledger->count() }} Peristiwa</span>
                </div>
            </div>
        </div>

        <!-- Interactive Connected Timeline (Audit Trail) -->
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl overflow-hidden shadow-xs">
            <div class="px-4 sm:px-6 py-4 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400 flex items-center justify-center border border-sky-100 dark:border-sky-800/60 shrink-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">
                            Riwayat Siklus Hidup Perangkat (Audit Trail)
                        </h4>
                        <p class="text-[11px] text-slate-400">Jejak lengkap dari pertama kali diterima hingga saat ini</p>
                    </div>
                </div>
            </div>

            <div class="p-4 sm:p-8">
                <div class="relative pl-5 sm:pl-8 border-l-2 border-sky-200 dark:border-sky-900/60 space-y-6 sm:space-y-8 ml-2 sm:ml-4">
                    @foreach($ledger as $index => $event)
                    <div class="relative group">
                        <!-- Timeline Dot -->
                        <div class="absolute -left-[27px] sm:-left-[39px] top-1.5 w-3.5 h-3.5 sm:w-4 sm:h-4 rounded-full bg-white dark:bg-slate-800 border-2 border-sky-500 flex items-center justify-center shadow-xs group-hover:scale-125 transition-transform">
                            <div class="w-1.5 h-1.5 rounded-full bg-sky-500 @if($loop->last) animate-pulse @endif"></div>
                        </div>

                        <!-- Timeline Content Box -->
                        <div class="bg-slate-50/80 dark:bg-slate-900/40 border border-slate-100 dark:border-slate-700/60 rounded-xl p-3.5 sm:p-4 transition-all hover:bg-slate-100/70 dark:hover:bg-slate-900/70 hover:shadow-xs">
                            @php
                                $eventLabel = match(true) {
                                    $event->type->value === 'transfer' && $event->from_pop_id !== null => 'Transfer Dikirim (Pusat)',
                                    $event->type->value === 'transfer' && $event->to_pop_id !== null => 'Transfer Diterima (Cabang)',
                                    default => $event->type->label(),
                                };
                            @endphp

                            <!-- Event Header -->
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1.5 mb-2">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-sky-100 dark:bg-sky-900/50 text-sky-800 dark:text-sky-300">
                                        {{ $eventLabel }}
                                    </span>
                                    @if($event->reference_number)
                                    <span class="text-xs font-mono font-medium text-slate-500 dark:text-slate-400">#{{ $event->reference_number }}</span>
                                    @endif
                                </div>
                                <time class="text-[11px] sm:text-xs text-slate-400 font-medium inline-flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span>{{ $event->created_at->translatedFormat('d M Y • H:i') }} WIB</span>
                                </time>
                            </div>

                            <!-- Movement Flow Path -->
                            @php
                                $fromLabel = $event->fromPop->name
                                    ?? $event->fromTechnician->name
                                    ?? $event->transfer?->fromPop?->name
                                    ?? null;

                                $toLabel = $event->toPop->name
                                    ?? $event->toTechnician->name
                                    ?? ($event->fopTask?->customer ? 'Pelanggan: '.$event->fopTask->customer->full_name : null)
                                    ?? $event->transfer?->toPop?->name
                                    ?? null;
                            @endphp

                            @if($fromLabel || $toLabel)
                            <div class="text-xs text-slate-700 dark:text-slate-300 font-medium mt-1">
                                <div class="flex flex-col sm:flex-row sm:items-center gap-1.5 sm:gap-2">
                                    <span class="px-2 py-1 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 font-semibold break-all">
                                        {{ $fromLabel ?? 'Pengadaan (Baru)' }}
                                    </span>
                                    <svg class="w-3.5 h-3.5 text-sky-500 shrink-0 self-center sm:rotate-0 rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                                    </svg>
                                    <span class="px-2 py-1 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 font-semibold break-all">
                                        {{ $toLabel ?? '-' }}
                                    </span>
                                </div>
                            </div>
                            @endif

                            @if($event->reason)
                            <div class="mt-2 text-xs text-slate-600 dark:text-slate-400 bg-white/70 dark:bg-slate-800/70 p-2.5 rounded-lg border border-slate-100 dark:border-slate-700/50 flex items-start gap-2">
                                <svg class="w-3.5 h-3.5 text-slate-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/>
                                </svg>
                                <div>
                                    <span class="font-bold text-slate-700 dark:text-slate-200">Catatan:</span> {{ $event->reason }}
                                </div>
                            </div>
                            @endif

                            <div class="mt-2.5 pt-2 border-t border-slate-200/60 dark:border-slate-700/40 flex items-center justify-between text-[11px] text-slate-400">
                                <span>Diverifikasi oleh: <strong class="text-slate-600 dark:text-slate-300">{{ $event->createdBy->name ?? 'Sistem' }}</strong></span>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

    </div>
    {{-- /TAB 1: LACAK SN --}}

    <!-- ================= TAB 2: SCAN MASUK — SINGLE ASSIGN ================= -->
    <div x-show="tab === 'single'" x-cloak
         x-data="{
            popId: '',
            categoryId: '',
            itemId: '',
            itemNames: @js($items->pluck('name', 'id')),
            unitPrice: '',
            snInput: '',
            scanned: [],
            add(sn = null) {
                // `sn` diisi kalau dipanggil dari hasil scan kamera
                // (`barcode-detected`) — beda dari input manual, TIDAK
                // nyentuh `snInput` biar teksnya gak ke-timpa kalau staf
                // lagi ngetik SN lain barengan kamera masih nyala.
                const value = (sn ?? this.snInput).trim();
                if (value === '') return;
                if (!this.itemId) {
                    window.Toast?.warning('Pilih Barang Dulu', 'Pilih model barang sebelum scan/isi SN.');
                    return;
                }
                if (this.scanned.some(row => row.sn.toLowerCase() === value.toLowerCase())) {
                    window.Toast?.info('SN Sudah Ada', `'${value}' sudah ada di daftar.`, 2000);
                    if (sn === null) this.snInput = '';
                    return;
                }
                // Cek prefix vendor SN (heuristik ONT/GPON, lihat docblock
                // `detectSnVendorMismatch` di barcode-scan.js) — SOFT
                // WARNING doang, SN tetap masuk daftar biar gak nge-block
                // kasus yang heuristiknya emang gak berlaku.
                const vendorMismatch = window.detectSnVendorMismatch?.(value, this.itemNames[this.itemId] || '');
                if (vendorMismatch) {
                    window.Toast?.warning('Cek Lagi Barangnya', `SN '${value}' kelihatannya ${vendorMismatch} — Barang yang dipilih beda merek. Yakin ini barangnya?`, 5000);
                }
                this.scanned.unshift({ sn: value });
                window.Toast?.success('SN Terinput', `'${value}' berhasil masuk daftar.`, 2000);
                if (sn === null) this.snInput = '';
            },
            remove(index) { this.scanned.splice(index, 1); },
            clearAll() { this.scanned = []; }
         }"
         @barcode-detected.window="$event.detail.target === 'single' && add($event.detail.code)"
         class="space-y-6">

        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-4 sm:p-6 shadow-xs">
            <div class="max-w-3xl">
                <div class="flex items-start gap-3">
                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0 border border-emerald-100 dark:border-emerald-800/60 mt-0.5">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm sm:text-base font-bold text-slate-900 dark:text-slate-100">
                            Scan SN Barang Masuk — Satu per Satu (Single Assign)
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                            Pilih gudang penerima dan satu model barang, lalu scan/ketik SN satu-satu. Cocok untuk penerimaan campuran beberapa model sekaligus.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left: Setup + Scan Input -->
            <div class="lg:col-span-1 space-y-4">
                <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-4 sm:p-5 shadow-xs space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Gudang Penerima</label>
                        <select x-model="popId" class="w-full px-3 py-2.5 border border-slate-300 dark:border-slate-600 rounded-xl text-xs font-semibold text-slate-800 dark:text-slate-200 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 min-h-[44px]">
                            <option value="">-- Pilih Gudang --</option>
                            @foreach($pusatPops as $pop)
                            <option value="{{ $pop->id }}">{{ $pop->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-[10px] text-slate-400 mt-1">Barang masuk cuma tercatat di Gudang Pusat — distribusi ke Cabang lewat Transfer terpisah.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Kategori Perangkat Aktif</label>
                        <select x-model="categoryId" @change="itemId = ''" class="w-full px-3 py-2.5 border border-slate-300 dark:border-slate-600 rounded-xl text-xs font-semibold text-slate-800 dark:text-slate-200 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 min-h-[44px]">
                            <option value="">-- Pilih Kategori --</option>
                            @foreach($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Model Barang</label>
                        <select x-model="itemId" class="w-full px-3 py-2.5 border border-slate-300 dark:border-slate-600 rounded-xl text-xs font-semibold text-slate-800 dark:text-slate-200 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 min-h-[44px]">
                            <option value="">-- Pilih Barang --</option>
                            @foreach($items as $item)
                            <option value="{{ $item->id }}" x-show="categoryId === '' || categoryId == {{ $item->item_category_id }}" data-category="{{ $item->item_category_id }}">{{ $item->name }} ({{ $item->code }})</option>
                            @endforeach
                        </select>
                        <p class="text-[10px] text-slate-400 mt-1">Terfilter otomatis sesuai kategori yang dipilih.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Harga Beli Satuan (Rp)</label>
                        <input type="number" x-model.number="unitPrice" min="1" step="1" placeholder="Contoh: 350000"
                               class="w-full px-3 py-2.5 border border-slate-300 dark:border-slate-600 rounded-xl text-xs font-mono font-semibold text-slate-800 dark:text-slate-200 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 min-h-[44px]">
                        <p class="text-[10px] text-slate-400 mt-1">Berlaku sama utk semua SN di daftar ini — acuan harga custody teknisi nanti (last-cost).</p>
                    </div>

                    <div class="border-t border-slate-100 dark:border-slate-700/60 pt-4">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Scan / Ketik SN</label>
                        <div class="relative">
                            <input type="text" x-model="snInput" @keydown.enter.prevent="add()" :disabled="!itemId"
                                   placeholder="Arahkan scanner atau ketik barcode..."
                                   class="w-full pl-9 pr-3 py-2.5 sm:py-3 border border-slate-300 dark:border-slate-600 rounded-xl text-xs font-mono font-semibold text-slate-800 dark:text-slate-200 bg-slate-50/50 dark:bg-slate-900/60 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 disabled:opacity-50 disabled:cursor-not-allowed min-h-[44px]">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5z"/></svg>
                            </div>
                        </div>
                        <button type="button" @click="add()" :disabled="!itemId"
                                class="mt-2.5 w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 sm:py-3 bg-emerald-600 hover:bg-emerald-700 disabled:bg-slate-300 disabled:cursor-not-allowed dark:disabled:bg-slate-700 text-white rounded-xl text-xs font-semibold transition-colors cursor-pointer min-h-[44px]">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                            </svg>
                            <span>Tambah ke Daftar</span>
                        </button>
                        <p class="text-[10px] text-slate-400 mt-1.5" x-show="!itemId">Pilih model barang dulu sebelum scan.</p>

                        <x-warehouse.barcode-scanner target="single" />
                    </div>
                </div>
            </div>

            <!-- Right: Scanned List -->
            <div class="lg:col-span-2">
                <form action="{{ route('warehouse.receive.store-scanned') }}" method="POST"
                      class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl overflow-hidden shadow-xs">
                    @csrf
                    <input type="hidden" name="pop_id" :value="popId">
                    <input type="hidden" name="item_id" :value="itemId">
                    <input type="hidden" name="unit_price" :value="unitPrice">
                    <template x-for="row in scanned" :key="row.sn">
                        <input type="hidden" name="serial_numbers[]" :value="row.sn">
                    </template>

                    <div class="px-4 sm:px-6 py-4 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
                        <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">Daftar SN Siap Assign</h4>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800"
                                  x-text="scanned.length + ' unit'"></span>
                            <button type="button" @click="clearAll()" x-show="scanned.length > 0" class="text-[11px] font-semibold text-rose-500 hover:text-rose-600 cursor-pointer">Kosongkan</button>
                        </div>
                    </div>

                    <div x-show="scanned.length === 0" class="p-8 sm:p-12 text-center">
                        <div class="w-12 h-12 mx-auto mb-3 rounded-2xl bg-slate-100 dark:bg-slate-700/60 text-slate-400 flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5z"/></svg>
                        </div>
                        <p class="text-xs text-slate-400">Belum ada SN yang di-scan.</p>
                        <p class="text-[11px] text-slate-400/80 mt-0.5">Tembak scanner atau ketik nomor seri di form sebelah kiri.</p>
                    </div>

                    <div x-show="scanned.length > 0" class="divide-y divide-slate-100 dark:divide-slate-700/50 max-h-[28rem] overflow-y-auto">
                        <template x-for="(row, index) in scanned" :key="row.sn">
                            <div class="px-4 sm:px-6 py-3 flex items-center justify-between gap-3 hover:bg-slate-50/50 dark:hover:bg-slate-700/30">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <span class="w-6 h-6 rounded-full bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                        </svg>
                                    </span>
                                    <span class="text-xs font-mono font-bold text-slate-800 dark:text-slate-200 break-all" x-text="row.sn"></span>
                                </div>
                                <button type="button" @click="remove(index)" class="p-1.5 text-slate-400 hover:text-rose-500 transition-colors cursor-pointer shrink-0" title="Hapus dari daftar">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        </template>
                    </div>

                    <div class="px-4 sm:px-6 py-4 border-t border-slate-100 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-900/30 flex items-center justify-end gap-2">
                        <button type="submit" :disabled="!popId || !itemId || !unitPrice || scanned.length === 0"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 sm:py-3 bg-emerald-600 hover:bg-emerald-700 disabled:bg-slate-300 dark:disabled:bg-slate-700 text-white rounded-xl text-xs font-semibold shadow-xs transition-all disabled:cursor-not-allowed cursor-pointer min-h-[44px]">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span>Simpan &amp; Assign ke Gudang</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    {{-- /TAB 2: SCAN MASUK SINGLE --}}

    <!-- ================= TAB 3: SCAN MASUK — BATCH PER KATEGORI ================= -->
    <div x-show="tab === 'batch'" x-cloak
         x-data="{
            popId: '',
            categoryId: '',
            itemId: '',
            itemNames: @js($items->pluck('name', 'id')),
            unitPrice: '',
            bulkInput: '',
            scanned: [],
            // Dipakai `process()` (tempel/scanner-fisik banyak baris
            // sekaligus) MAUPUN listener `barcode-detected` dari kamera
            // (satu kode per event) — satu jalur dedupe, gak ada dua logic
            // yang bisa menyimpang.
            // Balikin status ('added'/'duplicate'/'empty') — dipakai
            // `process()` buat toast RINGKASAN (bukan per-baris, bisa
            // puluhan baris sekali tempel) dan `onBarcodeScan()` buat toast
            // PER-SCAN (satu-satu, gak spam).
            pushCode(sn) {
                const value = sn.trim();
                if (value === '') return 'empty';
                if (this.scanned.some(row => row.sn.toLowerCase() === value.toLowerCase())) return 'duplicate';
                this.scanned.push({ sn: value });
                return 'added';
            },
            process() {
                if (!this.itemId) {
                    window.Toast?.warning('Pilih Barang Dulu', 'Pilih model barang sebelum memproses daftar SN.');
                    return;
                }
                const lines = this.bulkInput.split('\n').map(s => s.trim()).filter(s => s !== '');
                let added = 0, duplicate = 0;
                lines.forEach(sn => {
                    const status = this.pushCode(sn);
                    if (status === 'added') added++;
                    else if (status === 'duplicate') duplicate++;
                });
                this.bulkInput = '';
                if (added > 0 || duplicate > 0) {
                    window.Toast?.success('Batch Diproses', `${added} SN baru ditambahkan` + (duplicate > 0 ? `, ${duplicate} duplikat dilewati.` : '.'));
                }
            },
            onBarcodeScan(code) {
                if (!this.itemId) {
                    window.Toast?.warning('Pilih Barang Dulu', 'Pilih model barang sebelum scan.');
                    return;
                }
                // Vendor mismatch cuma dicek di jalur SCAN KAMERA per-unit
                // ini (bukan `pushCode()` generik yang juga dipacked
                // `process()` tempel-banyak-baris) — batch tempel biasanya
                // dari daftar SN valid yang udah dicatat sebelumnya, bukan
                // rawan "kepegang unit fisik yang salah" kayak scan
                // langsung. Lihat docblock `detectSnVendorMismatch`.
                const vendorMismatch = window.detectSnVendorMismatch?.(code, this.itemNames[this.itemId] || '');
                if (vendorMismatch) {
                    window.Toast?.warning('Cek Lagi Barangnya', `SN '${code}' kelihatannya ${vendorMismatch} — Barang yang dipilih beda merek. Yakin ini barangnya?`, 5000);
                }
                const status = this.pushCode(code);
                if (status === 'added') {
                    window.Toast?.success('SN Terinput', `'${code}' berhasil masuk daftar batch.`, 2000);
                } else if (status === 'duplicate') {
                    window.Toast?.info('SN Sudah Ada', `'${code}' sudah ada di daftar.`, 2000);
                }
            },
            remove(index) { this.scanned.splice(index, 1); },
            clearAll() { this.scanned = []; }
         }"
         @barcode-detected.window="$event.detail.target === 'batch' && onBarcodeScan($event.detail.code)"
         class="space-y-6">

        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-4 sm:p-6 shadow-xs">
            <div class="max-w-3xl">
                <div class="flex items-start gap-3">
                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0 border border-indigo-100 dark:border-indigo-800/60 mt-0.5">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm sm:text-base font-bold text-slate-900 dark:text-slate-100">
                            Scan SN Barang Masuk — Batch per Kategori
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                            Buat penerimaan satu model perangkat aktif (modem/ONT, router, AP Wireless, SFP, dst) dalam jumlah besar sekaligus — tembak scanner beruntun atau tempel daftar SN.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 1: Setup -->
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-4 sm:p-6 shadow-xs">
            <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider mb-3.5">
                1. Tentukan Kategori &amp; Model
            </h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Gudang Penerima</label>
                    <select x-model="popId" class="w-full px-3 py-2.5 border border-slate-300 dark:border-slate-600 rounded-xl text-xs font-semibold text-slate-800 dark:text-slate-200 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 min-h-[44px]">
                        <option value="">-- Pilih Gudang --</option>
                        @foreach($pusatPops as $pop)
                        <option value="{{ $pop->id }}">{{ $pop->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-[10px] text-slate-400 mt-1">Cuma Gudang Pusat — Cabang lewat Transfer.</p>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Kategori Perangkat Aktif</label>
                    <select x-model="categoryId" @change="itemId = ''" class="w-full px-3 py-2.5 border border-slate-300 dark:border-slate-600 rounded-xl text-xs font-semibold text-slate-800 dark:text-slate-200 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 min-h-[44px]">
                        <option value="">-- Pilih Kategori --</option>
                        @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Model Barang (semua SN batch ini)</label>
                    <select x-model="itemId" class="w-full px-3 py-2.5 border border-slate-300 dark:border-slate-600 rounded-xl text-xs font-semibold text-slate-800 dark:text-slate-200 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 min-h-[44px]">
                        <option value="">-- Pilih Barang --</option>
                        @foreach($items as $item)
                        <option value="{{ $item->id }}" x-show="categoryId === '' || categoryId == {{ $item->item_category_id }}" data-category="{{ $item->item_category_id }}">{{ $item->name }} ({{ $item->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Harga Beli Satuan (Rp)</label>
                    <input type="number" x-model.number="unitPrice" min="1" step="1" placeholder="Contoh: 350000"
                           class="w-full px-3 py-2.5 border border-slate-300 dark:border-slate-600 rounded-xl text-xs font-mono font-semibold text-slate-800 dark:text-slate-200 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 min-h-[44px]">
                    <p class="text-[10px] text-slate-400 mt-1">Berlaku sama utk semua SN batch ini.</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Step 2: Bulk Scan Input -->
            <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-4 sm:p-6 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">
                            2. Scan / Tempel Daftar SN
                        </h4>
                        <span class="text-[11px] font-mono text-slate-400"
                              x-text="(bulkInput.split('\n').filter(s => s.trim() !== '').length) + ' baris terdeteksi'"></span>
                    </div>
                    <p class="text-[11px] text-slate-400 mb-3">
                        Satu SN per baris. Scanner otomatis Enter tiap tembakan — arahkan ke kotak ini lalu tembak beruntun.
                    </p>
                    <textarea x-model="bulkInput" :disabled="!itemId" rows="8" placeholder="ZTE00001&#10;ZTE00002&#10;ZTE00003&#10;..."
                              class="w-full px-3 py-2.5 border border-slate-300 dark:border-slate-600 rounded-xl text-xs font-mono font-semibold text-slate-800 dark:text-slate-200 bg-slate-50/50 dark:bg-slate-900/60 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed leading-relaxed"></textarea>
                </div>

                <div class="flex flex-col sm:flex-row items-center justify-between gap-2 mt-4 pt-3 border-t border-slate-100 dark:border-slate-700/60">
                    <p class="text-[10px] text-slate-400" x-show="!itemId">Pilih model barang dulu sebelum memproses.</p>
                    <button type="button" @click="process()" :disabled="!itemId"
                            class="w-full sm:w-auto sm:ml-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 sm:py-3 bg-indigo-600 hover:bg-indigo-700 disabled:bg-slate-300 disabled:cursor-not-allowed dark:disabled:bg-slate-700 text-white rounded-xl text-xs font-semibold transition-colors cursor-pointer min-h-[44px]">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        <span>Proses ke Daftar Batch</span>
                    </button>
                </div>

                <x-warehouse.barcode-scanner target="batch" />
            </div>

            <!-- Step 3: Batch Preview List -->
            <form action="{{ route('warehouse.receive.store-scanned') }}" method="POST"
                  class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl overflow-hidden shadow-xs flex flex-col justify-between">
                @csrf
                <input type="hidden" name="pop_id" :value="popId">
                <input type="hidden" name="item_id" :value="itemId">
                <input type="hidden" name="unit_price" :value="unitPrice">
                <template x-for="row in scanned" :key="row.sn">
                    <input type="hidden" name="serial_numbers[]" :value="row.sn">
                </template>

                <div>
                    <div class="px-4 sm:px-6 py-4 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
                        <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">3. Preview Batch</h4>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800"
                                  x-text="scanned.length + ' unit'"></span>
                            <button type="button" @click="clearAll()" x-show="scanned.length > 0" class="text-[11px] font-semibold text-rose-500 hover:text-rose-600 cursor-pointer">Kosongkan</button>
                        </div>
                    </div>

                    <div x-show="scanned.length === 0" class="p-8 sm:p-12 text-center">
                        <div class="w-12 h-12 mx-auto mb-3 rounded-2xl bg-slate-100 dark:bg-slate-700/60 text-slate-400 flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5z"/>
                            </svg>
                        </div>
                        <p class="text-xs text-slate-400">Belum ada SN diproses ke batch.</p>
                        <p class="text-[11px] text-slate-400/80 mt-0.5">Tempel daftar SN di kolom sebelah lalu klik 'Proses ke Daftar Batch'.</p>
                    </div>

                    <div x-show="scanned.length > 0" class="divide-y divide-slate-100 dark:divide-slate-700/50 max-h-[20rem] overflow-y-auto">
                        <template x-for="(row, index) in scanned" :key="row.sn">
                            <div class="px-4 sm:px-6 py-2.5 flex items-center justify-between gap-3 hover:bg-slate-50/50 dark:hover:bg-slate-700/30">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <span class="text-[10px] font-mono text-slate-400 w-5" x-text="index + 1"></span>
                                    <span class="text-xs font-mono font-bold text-slate-800 dark:text-slate-200 break-all" x-text="row.sn"></span>
                                </div>
                                <button type="button" @click="remove(index)" class="p-1 text-slate-400 hover:text-rose-500 transition-colors cursor-pointer shrink-0" title="Hapus">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="px-4 sm:px-6 py-4 border-t border-slate-100 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-900/30">
                    <button type="submit" :disabled="!popId || !itemId || !unitPrice || scanned.length === 0"
                            class="w-full inline-flex items-center justify-center gap-2 px-5 py-2.5 sm:py-3 bg-indigo-600 hover:bg-indigo-700 disabled:bg-slate-300 dark:disabled:bg-slate-700 text-white rounded-xl text-xs font-semibold shadow-xs transition-all disabled:cursor-not-allowed cursor-pointer min-h-[44px]">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>Simpan &amp; Assign Batch ke Gudang</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    {{-- /TAB 3: SCAN MASUK BATCH --}}

</div>

{{-- Entry Vite terpisah (getUserMedia + BarcodeDetector) — lihat
     resources/js/barcode-scan.js kenapa gak digabung app.js. --}}
@vite(['resources/js/barcode-scan.js'])

@endsection
