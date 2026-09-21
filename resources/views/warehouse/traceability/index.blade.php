@extends('layouts.app')

@section('title', 'Lacak Barang / Nomor Seri - Whusnet Operasional')
@section('page_title', 'Lacak Barang / SN')

@section('content')

<x-warehouse.header active="traceability" />

{{--
    2026-09-07: dua tab scan RECEIVE ("Single Assign" & "Batch Assign") yang
    dulu numpang di sini DIPINDAH ke Barang Masuk (`warehouse.receive.create`,
    mode "Scan Cepat") — secara bisnis emang RECEIVE, bukan bagian Lacak
    Barang/SN. 2026-09-08: mode "Scan Cepat" itu DIHAPUS TOTAL dari Receive
    (UI + route `warehouse.receive.store-scanned` + controller). Halaman ini
    balik jadi murni pencarian riwayat SN, gak perlu tab switcher lagi.
--}}
<div class="space-y-6">

        <!-- Search Hero Panel -->
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-4 sm:p-6 shadow-xs">
            <div class="max-w-3xl">
                <div class="flex items-start gap-3">
                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-lg bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0 border border-sky-100 dark:border-sky-800/60 mt-0.5">
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
                                   class="w-full pl-10 pr-4 py-2.5 sm:py-3 text-xs sm:text-sm font-mono font-semibold border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all min-h-[44px]">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5z"/></svg>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <button type="submit"
                                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 sm:py-3 bg-sky-600 hover:bg-sky-700 active:bg-sky-800 text-white rounded-lg text-xs sm:text-sm font-semibold shadow-xs shadow-sky-600/20 transition-all hover:scale-[1.01] active:scale-[0.98] cursor-pointer min-h-[44px]">
                                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                                </svg>
                                <span>Lacak SN</span>
                            </button>

                            @if($serialNumber !== '')
                            <a href="{{ route('warehouse.traceability.index') }}"
                               class="inline-flex items-center justify-center p-2.5 sm:px-3 sm:py-3 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700/50 transition-colors min-h-[44px]"
                               title="Reset Pencarian">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </a>
                            @endif
                        </div>
                    </div>
                </form>

                {{-- Pencarian Roll Kabel (App\Enums\TrackingType::ROLL) — form
                     terpisah biar 2 identitas beda namespace (SN modem vs
                     roll_code kabel) gak nyampur di 1 input. --}}
                <form action="{{ route('warehouse.traceability.index') }}" method="GET" class="mt-3">
                    <div class="flex flex-col sm:flex-row gap-2.5">
                        <div class="relative flex-1">
                            <input type="text"
                                   name="roll"
                                   value="{{ $rollCode }}"
                                   placeholder="Atau ketik / scan Roll ID kabel (mis. WR-ROLL-FO-20260915-000001)..."
                                   class="w-full pl-4 pr-4 py-2.5 sm:py-3 text-xs sm:text-sm font-mono font-semibold border border-amber-200 dark:border-amber-900/60 rounded-lg bg-amber-50/40 dark:bg-amber-950/20 text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all min-h-[44px]">
                        </div>
                        <button type="submit"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 sm:py-3 bg-amber-600 hover:bg-amber-700 active:bg-amber-800 text-white rounded-lg text-xs sm:text-sm font-semibold shadow-xs transition-all min-h-[44px] cursor-pointer">
                            <span>Lacak Roll</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- State Not Found -->
        @if($notFound)
        <div class="bg-amber-50/90 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/70 rounded-lg p-6 sm:p-8 text-center shadow-xs">
            <div class="w-12 h-12 mx-auto mb-3.5 rounded-lg bg-amber-100 dark:bg-amber-900/50 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <h4 class="text-sm font-bold text-amber-900 dark:text-amber-200">
                @if($rollCode !== '')
                    Roll ID "<span class="font-mono">{{ $rollCode }}</span>" tidak ditemukan
                @else
                    Serial Number "<span class="font-mono">{{ $serialNumber }}</span>" tidak ditemukan
                @endif
            </h4>
            <p class="text-xs text-amber-700/90 dark:text-amber-400 mt-1.5 max-w-md mx-auto leading-relaxed">
                Pastikan {{ $rollCode !== '' ? 'roll ID' : 'nomor seri' }} diketik dengan benar atau periksa apakah barang ini berada dalam POP Scope akses Anda.
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
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-4 sm:p-6 shadow-xs space-y-4"
             x-data="{ copied: false }">

            <!-- Device Summary Banner -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-slate-100 dark:border-slate-700/60">
                <div class="flex items-start gap-3.5">
                    <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-lg {{ $statusConfig['icon_bg'] }} text-white flex items-center justify-center shadow-md shadow-slate-900/10 shrink-0">
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

                            {{-- Badge Kondisi Fisik (analisa-gap-kondisi-barang.md
                                 poin 8) — axis independen dari status di atas. --}}
                            @php
                                $conditionVal = $serial->condition?->value ?? 'new';
                                $conditionBadge = match(true) {
                                    $conditionVal === 'new' => ['label' => 'Baru', 'class' => 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800'],
                                    $conditionVal === 'used_damaged' => ['label' => 'Bekas — Rusak', 'class' => 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800'],
                                    $serial->condition_checked_at !== null => ['label' => 'Bekas — Sudah Dicek', 'class' => 'bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-300 border-sky-200 dark:border-sky-800'],
                                    default => ['label' => 'Bekas — Belum Dicek', 'class' => 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800'],
                                };
                            @endphp
                            <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-bold border {{ $conditionBadge['class'] }}">
                                {{ $conditionBadge['label'] }}
                            </span>
                        </div>

                        {{-- Aksi "Sudah Dicek" — cuma muncul buat SN bekas yang
                             belum dicek (poin 5 rancangan), inline toggle di
                             halaman Detail SN ini sendiri (pola-3 CLAUDE.md). --}}
                        @if(($serial->condition?->value ?? 'new') !== 'new' && $serial->condition_checked_at === null && auth()->user()->hasPermission('warehouse_reassign.create'))
                        <div class="mt-2" x-data="{ open: false }">
                            <button type="button" @click="open = !open"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-amber-600 hover:bg-amber-700 text-white transition-colors cursor-pointer">
                                Tandai Sudah Dicek
                            </button>
                            <div x-show="open" x-cloak class="mt-2 p-3 bg-amber-50/70 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800/60 rounded-lg max-w-sm">
                                <p class="text-[11px] text-amber-800 dark:text-amber-300 mb-2">Hasil cek fisik SN ini:</p>
                                <form action="{{ route('warehouse.traceability.serial.condition-check', $serial) }}" method="POST" class="flex flex-col sm:flex-row gap-2">
                                    @csrf
                                    <select name="condition" required class="flex-1 px-2 py-1.5 text-xs border border-amber-200 dark:border-amber-800 rounded-lg bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200">
                                        <option value="used_good">Kondisi Baik</option>
                                        <option value="used_damaged">Rusak</option>
                                    </select>
                                    <button type="submit" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold rounded-lg cursor-pointer">Simpan</button>
                                </form>
                            </div>
                        </div>
                        @endif
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            Model: <strong class="text-slate-800 dark:text-slate-200">{{ $serial->item->name }}</strong>
                            <span class="font-mono text-[11px] text-slate-400">({{ $serial->item->code }})</span>
                        </p>
                    </div>
                </div>

                <!-- Location / Responsibility Context Box -->
                <div class="bg-slate-50/80 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-700/60 rounded-lg p-3 text-xs w-full md:w-auto">
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
                <div class="bg-slate-50/70 dark:bg-slate-900/40 p-3 rounded-lg border border-slate-100 dark:border-slate-800">
                    <span class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Kategori Barang</span>
                    <span class="font-semibold text-slate-700 dark:text-slate-300 mt-0.5 block truncate">{{ $serial->item->category?->name ?? '-' }}</span>
                </div>
                <div class="bg-slate-50/70 dark:bg-slate-900/40 p-3 rounded-lg border border-slate-100 dark:border-slate-800">
                    <span class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Gudang Asal Masuk</span>
                    <span class="font-semibold text-slate-700 dark:text-slate-300 mt-0.5 block truncate">{{ $serial->issuedFromPop->name ?? '-' }}</span>
                </div>
                <div class="bg-slate-50/70 dark:bg-slate-900/40 p-3 rounded-lg border border-slate-100 dark:border-slate-800">
                    <span class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Didaftarkan</span>
                    <span class="font-semibold text-slate-700 dark:text-slate-300 mt-0.5 block">{{ $serial->created_at->translatedFormat('d M Y H:i') }}</span>
                </div>
                <div class="bg-slate-50/70 dark:bg-slate-900/40 p-3 rounded-lg border border-slate-100 dark:border-slate-800">
                    <span class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Total Mutasi Ledger</span>
                    <span class="font-bold text-sky-600 dark:text-sky-400 font-mono mt-0.5 block">{{ $ledger->count() }} Peristiwa</span>
                </div>
            </div>
        </div>

        <!-- Interactive Connected Timeline (Audit Trail) -->
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg overflow-hidden shadow-xs">
            <div class="px-4 sm:px-6 py-4 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400 flex items-center justify-center border border-sky-100 dark:border-sky-800/60 shrink-0">
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
                        <div class="bg-slate-50/80 dark:bg-slate-900/40 border border-slate-100 dark:border-slate-700/60 rounded-lg p-3.5 sm:p-4 transition-all hover:bg-slate-100/70 dark:hover:bg-slate-900/70 hover:shadow-xs">
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

        <!-- State Found: Roll Kabel Detail & Timeline -->
        @if($roll)
        @php
            $rollStatus = $roll->status->value ?? '';
            $rollStatusConfig = match($rollStatus) {
                'available' => ['label' => 'Tersedia di Gudang', 'badge' => 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800', 'icon_bg' => 'bg-emerald-500'],
                'issued' => ['label' => 'Dipegang Teknisi (Custody)', 'badge' => 'bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-300 border-sky-200 dark:border-sky-800', 'icon_bg' => 'bg-sky-500'],
                'in_use' => ['label' => 'Sedang Dipakai (Sisa Sebagian)', 'badge' => 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800', 'icon_bg' => 'bg-indigo-500'],
                'depleted' => ['label' => 'Habis', 'badge' => 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 border-slate-200', 'icon_bg' => 'bg-slate-500'],
                'transferred' => ['label' => 'Dalam Transfer', 'badge' => 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800', 'icon_bg' => 'bg-amber-500'],
                'damaged', 'lost', 'scrapped' => ['label' => strtoupper($rollStatus), 'badge' => 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800', 'icon_bg' => 'bg-rose-500'],
                default => ['label' => $roll->status->label(), 'badge' => 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 border-slate-200', 'icon_bg' => 'bg-slate-500'],
            };
        @endphp

        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-4 sm:p-6 shadow-xs space-y-4">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-slate-100 dark:border-slate-700/60">
                <div class="flex items-start gap-3.5">
                    <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-lg {{ $rollStatusConfig['icon_bg'] }} text-white flex items-center justify-center shadow-md shadow-slate-900/10 shrink-0">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a1 1 0 001-1v-4a1 1 0 00-1-1H9a1 1 0 00-1 1v4a1 1 0 001 1zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="text-base sm:text-lg font-extrabold text-slate-900 dark:text-slate-100 font-mono tracking-tight break-all">
                                {{ $roll->roll_code }}
                            </h3>
                            <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-bold border {{ $rollStatusConfig['badge'] }}">
                                {{ $rollStatusConfig['label'] }}
                            </span>
                            @if($roll->isLowRemaining())
                            <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800">Sisa Kecil</span>
                            @endif
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            {{ $roll->item->name ?? '(barang dihapus)' }}
                            <span class="font-mono text-[11px] text-slate-400">Vendor: {{ $roll->vendor ?? '—' }}</span>
                        </p>
                    </div>
                </div>

                <div class="bg-slate-50/80 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-700/60 rounded-lg p-3 text-xs w-full md:w-auto">
                    <span class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Sisa Panjang</span>
                    <div class="font-bold text-slate-800 dark:text-slate-100 mt-1 font-mono">
                        {{ rtrim(rtrim(number_format((float) $roll->length_remaining, 2, ',', '.'), '0'), ',') }} / {{ rtrim(rtrim(number_format((float) $roll->length_total, 2, ',', '.'), '0'), ',') }} meter
                    </div>
                    <p class="text-[10px] text-slate-400 font-normal mt-0.5">
                        @if($roll->current_technician_id)
                            Teknisi: {{ $roll->currentTechnician->name ?? '-' }}
                        @elseif($roll->current_pop_id)
                            Gudang: {{ $roll->currentPop->name ?? '-' }}
                        @else
                            {{ $roll->status->label() }}
                        @endif
                    </p>
                </div>
            </div>

            <!-- Quick Info Specs Grid for Roll -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-2.5 sm:gap-3.5 pt-1 text-xs">
                <div class="bg-slate-50/70 dark:bg-slate-900/40 p-3 rounded-lg border border-slate-100 dark:border-slate-800">
                    <span class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Kategori Barang</span>
                    <span class="font-semibold text-slate-700 dark:text-slate-300 mt-0.5 block truncate">{{ $roll->item->category?->name ?? '-' }}</span>
                </div>
                <div class="bg-slate-50/70 dark:bg-slate-900/40 p-3 rounded-lg border border-slate-100 dark:border-slate-800">
                    <span class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Harga per Roll</span>
                    <span class="font-semibold text-slate-700 dark:text-slate-300 mt-0.5 block">
                        @if($roll->unit_price_snapshot && (float) $roll->item?->meter_per_roll > 0)
                            Rp {{ number_format((float) $roll->unit_price_snapshot * (float) $roll->item->meter_per_roll, 0, ',', '.') }}
                            <span class="text-[10px] font-normal text-slate-400">(Rp {{ number_format((float) $roll->unit_price_snapshot, 0, ',', '.') }}/m)</span>
                        @else
                            -
                        @endif
                    </span>
                </div>
                <div class="bg-slate-50/70 dark:bg-slate-900/40 p-3 rounded-lg border border-slate-100 dark:border-slate-800">
                    <span class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Nilai Sisa Fisik</span>
                    <span class="font-bold text-emerald-600 dark:text-emerald-400 mt-0.5 block font-mono">
                        @if($roll->unit_price_snapshot)
                            Rp {{ number_format((float) $roll->unit_price_snapshot * (float) $roll->length_remaining, 0, ',', '.') }}
                        @else
                            -
                        @endif
                    </span>
                </div>
                <div class="bg-slate-50/70 dark:bg-slate-900/40 p-3 rounded-lg border border-slate-100 dark:border-slate-800">
                    <span class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Total Mutasi Ledger</span>
                    <span class="font-bold text-amber-600 dark:text-amber-400 font-mono mt-0.5 block">{{ $ledger->count() }} Peristiwa</span>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg overflow-hidden shadow-xs">
            <div class="px-4 sm:px-6 py-4 border-b border-slate-100 dark:border-slate-700/60">
                <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">Riwayat Ledger Roll (RECEIVE/TRANSFER/ISSUE/RETURN/ADJUSTMENT)</h4>
                <p class="text-[11px] text-slate-400 mt-0.5">Pemakaian harian (potong meter) TIDAK masuk ledger ini — cukup tercatat di Laporan Task teknisi.</p>
            </div>
            <div class="p-4 sm:p-8">
                <div class="relative pl-5 sm:pl-8 border-l-2 border-amber-200 dark:border-amber-900/60 space-y-6 sm:space-y-8 ml-2 sm:ml-4">
                    @foreach($ledger as $event)
                    <div class="relative group">
                        <div class="absolute -left-[27px] sm:-left-[39px] top-1.5 w-3.5 h-3.5 sm:w-4 sm:h-4 rounded-full bg-white dark:bg-slate-800 border-2 border-amber-500 flex items-center justify-center shadow-xs group-hover:scale-125 transition-transform">
                            <div class="w-1.5 h-1.5 rounded-full bg-amber-500 @if($loop->last) animate-pulse @endif"></div>
                        </div>
                        <div class="bg-slate-50/80 dark:bg-slate-900/40 border border-slate-100 dark:border-slate-700/60 rounded-lg p-3.5 sm:p-4">
                            @php
                                $rollFromLabel = $event->fromPop->name ?? $event->fromTechnician->name ?? $event->transfer?->fromPop?->name ?? null;
                                $rollToLabel = $event->toPop->name ?? $event->toTechnician->name ?? $event->transfer?->toPop?->name ?? null;
                            @endphp
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1.5 mb-2">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 dark:bg-amber-900/50 text-amber-800 dark:text-amber-300">{{ $event->type->label() }}</span>
                                    @if($event->reference_number)
                                    <span class="text-xs font-mono font-medium text-slate-500 dark:text-slate-400">#{{ $event->reference_number }}</span>
                                    @endif
                                </div>
                                <time class="text-[11px] sm:text-xs text-slate-400 font-medium">{{ $event->created_at->translatedFormat('d M Y • H:i') }} WIB</time>
                            </div>

                            @if($rollFromLabel || $rollToLabel)
                            <div class="text-xs text-slate-700 dark:text-slate-300 font-medium mt-1 flex flex-col sm:flex-row sm:items-center gap-1.5 sm:gap-2">
                                <span class="px-2 py-1 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 font-semibold break-all">{{ $rollFromLabel ?? 'Pengadaan (Baru)' }}</span>
                                <svg class="w-3.5 h-3.5 text-amber-500 shrink-0 self-center sm:rotate-0 rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                                <span class="px-2 py-1 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 font-semibold break-all">{{ $rollToLabel ?? '-' }}</span>
                            </div>
                            @endif

                            <div class="mt-2 text-xs font-mono text-slate-500 dark:text-slate-400">Qty: {{ rtrim(rtrim(number_format((float) $event->qty, 2, ',', '.'), '0'), ',') }} meter</div>

                            <div class="mt-2.5 pt-2 border-t border-slate-200/60 dark:border-slate-700/40 text-[11px] text-slate-400">
                                Diverifikasi oleh: <strong class="text-slate-600 dark:text-slate-300">{{ $event->createdBy->name ?? 'Sistem' }}</strong>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

    </div>

@endsection
