@extends('layouts.app')

@section('title', 'Worksheet Admin - Kolektor - Whusnet Operasional')
@section('page_title', 'Worksheet Admin — Kolektor')

@section('content')
    @include('partials.collector-realtime', ['channels' => $activityChannels, 'audiens' => 'admin'])

<div class="space-y-6">
    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mb-1 font-medium">
                <span>Operasional</span>
                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span>Billing & Tagihan</span>
                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-sky-600 dark:text-sky-400 font-semibold">Worksheet Admin</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-slate-100 tracking-tight">Worksheet Admin — Kolektor</h1>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-0.5">Kelola penugasan kolektor, distribusikan pelanggan belum ber-kolektor, dan awasi tunggakan rute.</p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <a href="{{ route('invoices.index') }}" class="inline-flex items-center gap-2 px-3.5 py-2 border border-slate-200 dark:border-slate-700/80 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/60 text-slate-700 dark:text-slate-200 rounded-xl transition-all text-xs font-semibold shadow-xs focus:outline-none focus:ring-2 focus:ring-sky-500/20">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span>Kembali ke Tagihan</span>
            </a>
        </div>
    </div>

    {{-- Error & Success Alerts --}}
    @if ($errors->any())
        <x-ui.alert variant="error" title="Assign Dibatalkan" class="rounded-2xl">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </x-ui.alert>
    @endif

    @if (session('success'))
        <x-ui.alert variant="success" class="rounded-2xl">{{ session('success') }}</x-ui.alert>
    @endif

    {{-- Summary Stat Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        {{-- Total Kolektor --}}
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-4 shadow-xs relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Total Kolektor</span>
                <div class="w-8 h-8 rounded-xl bg-violet-50 dark:bg-violet-500/10 text-violet-600 dark:text-violet-400 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
            </div>
            <div class="mt-2 flex items-baseline gap-2 min-w-0">
                <span class="text-lg sm:text-2xl font-bold text-slate-900 dark:text-slate-100 font-mono truncate">{{ $collectors->count() }}</span>
                <span class="text-xs text-slate-500 dark:text-slate-400 font-medium shrink-0">Petugas</span>
            </div>
            <div class="mt-1 text-[11px] text-emerald-600 dark:text-emerald-400 font-medium flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                <span>{{ $collectors->where('status', 'active')->count() }} Aktif</span>
            </div>
        </div>

        {{-- Belum Assigned --}}
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-4 shadow-xs relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Belum Assigned</span>
                <div class="w-8 h-8 rounded-xl bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
            </div>
            <div class="mt-2 flex items-baseline gap-2 min-w-0">
                <span class="text-lg sm:text-2xl font-bold text-amber-600 dark:text-amber-400 font-mono truncate">{{ number_format($unassignedCustomers->total(), 0, ',', '.') }}</span>
                <span class="text-xs text-slate-500 dark:text-slate-400 font-medium shrink-0">Pelanggan</span>
            </div>
            <div class="mt-1 text-[11px] text-slate-400 dark:text-slate-500 font-medium">
                Perlu alokasi kolektor
            </div>
        </div>

        {{-- Total Tunggakan Kolektor --}}
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-4 shadow-xs relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Tunggakan Kolektor</span>
                <div class="w-8 h-8 rounded-xl bg-rose-50 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <div class="mt-2 min-w-0">
                <span class="text-base sm:text-xl font-bold text-rose-600 dark:text-rose-400 font-mono truncate block">Rp {{ number_format($collectors->sum('unpaid_total'), 0, ',', '.') }}</span>
            </div>
            <div class="mt-1 text-[11px] text-slate-400 dark:text-slate-500 font-medium">
                Total sisa tagihan ter-assign
            </div>
        </div>

        {{-- Status Rute --}}
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-4 shadow-xs relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Status Penugasan</span>
                <div class="w-8 h-8 rounded-xl bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <div class="mt-2 flex items-baseline gap-2 min-w-0">
                <span class="text-lg sm:text-2xl font-bold text-sky-600 dark:text-sky-400 font-mono truncate">{{ $collectors->sum('customer_count') }}</span>
                <span class="text-xs text-slate-500 dark:text-slate-400 font-medium shrink-0">Pelanggan Ter-assign</span>
            </div>
            <div class="mt-1 text-[11px] text-slate-400 dark:text-slate-500 font-medium">
                Sudah memiliki kolektor
            </div>
        </div>
    </div>

    {{-- Main Worksheet Layout (Mobile Tabs + Desktop 2 Columns) --}}
    <div x-data="{ activeMobileTab: 'collectors', searchFilter: '' }" class="space-y-4">
        {{-- Mobile Switcher Buttons (Hidden on LG screens) --}}
        <div class="lg:hidden flex bg-slate-100 dark:bg-slate-800/80 p-1 rounded-xl border border-slate-200/80 dark:border-slate-700/60">
            <button type="button" @click="activeMobileTab = 'collectors'"
                    :class="activeMobileTab === 'collectors' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 shadow-xs font-bold' : 'text-slate-500 dark:text-slate-400 font-medium hover:text-slate-700 dark:hover:text-slate-200'"
                    class="flex-1 py-2 text-xs rounded-lg transition-all text-center flex items-center justify-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                <span>Daftar Kolektor ({{ $collectors->count() }})</span>
            </button>
            <button type="button" @click="activeMobileTab = 'unassigned'"
                    :class="activeMobileTab === 'unassigned' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 shadow-xs font-bold' : 'text-slate-500 dark:text-slate-400 font-medium hover:text-slate-700 dark:hover:text-slate-200'"
                    class="flex-1 py-2 text-xs rounded-lg transition-all text-center flex items-center justify-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                <span>Belum Assigned ({{ $unassignedCustomers->total() }})</span>
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            {{-- ============ PANEL KIRI: DAFTAR KOLEKTOR ============ --}}
            <div :class="activeMobileTab === 'collectors' ? 'block' : 'hidden lg:block'"
                 class="lg:col-span-5 bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl shadow-xs overflow-hidden">
                
                {{-- Panel Header --}}
                <div class="p-4 border-b border-slate-100 dark:border-slate-700/80 bg-slate-50/60 dark:bg-slate-800/40 space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="w-2 h-2 rounded-full bg-violet-500"></div>
                            <h2 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">
                                Daftar Kolektor ({{ $collectors->count() }})
                            </h2>
                        </div>
                        <span class="text-[10px] text-slate-400 dark:text-slate-500">Pilih untuk detail</span>
                    </div>

                    {{-- Quick Search Input --}}
                    @if($collectors->isNotEmpty())
                        <div class="relative">
                            <input type="text" x-model="searchFilter" placeholder="Filter kolektor..."
                                   class="w-full text-xs pl-8 pr-3 py-1.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                            <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                    @endif
                </div>

                {{-- Collector List Body --}}
                <div class="divide-y divide-slate-100 dark:divide-slate-700/50 max-h-[36rem] overflow-y-auto custom-scrollbar">
                    @forelse ($collectors as $collector)
                        <a href="{{ route('collector-worksheet.show', $collector->id) }}"
                           x-show="searchFilter === '' || '{{ strtolower($collector->name) }}'.includes(searchFilter.toLowerCase())"
                           class="group block p-4 hover:bg-slate-50/80 dark:hover:bg-slate-700/30 transition-all border-l-2 border-transparent hover:border-violet-500">
                            <div class="flex items-center gap-3">
                                {{-- Avatar Initial --}}
                                <div class="h-10 w-10 rounded-xl bg-gradient-to-br from-violet-500 to-indigo-600 text-white font-bold text-sm flex items-center justify-center shrink-0 shadow-xs group-hover:scale-105 transition-transform">
                                    {{ strtoupper(substr($collector->name, 0, 1)) }}
                                </div>

                                {{-- Info --}}
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-bold text-sm text-slate-900 dark:text-slate-100 truncate group-hover:text-violet-600 dark:group-hover:text-violet-400 transition-colors">
                                            {{ $collector->name }}
                                        </span>
                                    </div>
                                    
                                    <div class="flex items-center gap-2 mt-1">
                                        @if($collector->status === 'active')
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
                                                <span class="w-1 h-1 rounded-full bg-emerald-500"></span>
                                                Aktif
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-400">
                                                <span class="w-1 h-1 rounded-full bg-slate-400"></span>
                                                Nonaktif
                                            </span>
                                        @endif

                                        <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">
                                            {{ $collector->customer_count }} pelanggan
                                        </span>
                                    </div>
                                </div>

                                {{-- Tunggakan --}}
                                <div class="text-right shrink-0">
                                    <div class="text-[10px] uppercase font-bold tracking-wider text-amber-600 dark:text-amber-500">Tunggakan</div>
                                    <div class="font-bold text-sm text-amber-700 dark:text-amber-400 font-mono">
                                        Rp {{ number_format($collector->unpaid_total, 0, ',', '.') }}
                                    </div>
                                </div>

                                {{-- Arrow Chevron --}}
                                <div class="text-slate-300 dark:text-slate-600 group-hover:text-violet-500 dark:group-hover:text-violet-400 group-hover:translate-x-0.5 transition-all shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="p-8 text-center">
                            <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-700/50 text-slate-400 flex items-center justify-center mx-auto mb-3">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            </div>
                            <p class="text-xs font-semibold text-slate-700 dark:text-slate-300">Belum Ada Kolektor</p>
                            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Tambahkan user ber-role Kolektor melalui modul User Management.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- ============ PANEL KANAN: PELANGGAN BELUM DI-ASSIGN ============ --}}
            <div :class="activeMobileTab === 'unassigned' ? 'block' : 'hidden lg:block'"
                 class="lg:col-span-7 bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl shadow-xs overflow-hidden"
                 x-data="{ selectedCustomers: [] }">
                
                {{-- Panel Header --}}
                <div class="p-4 border-b border-slate-100 dark:border-slate-700/80 bg-slate-50/60 dark:bg-slate-800/40">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="w-2 h-2 rounded-full bg-amber-500"></div>
                            <h2 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">
                                Belum Punya Kolektor ({{ $unassignedCustomers->total() }})
                            </h2>
                        </div>
                        <span class="text-[10px] text-slate-400 dark:text-slate-500">Pilih & Assign</span>
                    </div>
                </div>

                <div class="p-4 sm:p-5 space-y-4">
                    {{-- Filter Form --}}
                    <form action="{{ route('collector-worksheet.index') }}" method="GET" id="unassignedFilterForm" class="space-y-3">
                        <div class="flex gap-2">
                            <div class="relative flex-1">
                                <input type="text" name="search" value="{{ $search }}" placeholder="Cari nama, kode, atau CID pelanggan..."
                                       class="w-full text-xs pl-8 pr-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </div>
                            <button type="submit" class="bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold py-2 px-4 rounded-xl transition-all cursor-pointer shrink-0 shadow-xs">
                                Cari
                            </button>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <x-ui.pop-filter :selected-cabang="$selectedCabang" :selected-mini="$selectedMini" form-id="unassignedFilterForm" />
                            <x-ui.wilayah-filter :selected-districts="$selectedDistricts" :selected-villages="$selectedVillages" form-id="unassignedFilterForm" />
                        </div>
                        @if($search !== '' || !empty($popIds) || !empty($miniPopIds) || !empty($districtIds) || !empty($villageIds))
                            <div class="flex justify-end">
                                <a href="{{ route('collector-worksheet.index') }}" class="text-xs text-rose-600 dark:text-rose-400 hover:underline font-semibold inline-flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    <span>Reset Filter</span>
                                </a>
                            </div>
                        @endif
                    </form>

                    @if ($collectors->isEmpty())
                        <div class="p-6 text-center text-xs text-slate-400 dark:text-slate-500 bg-slate-50 dark:bg-slate-900/50 rounded-xl border border-dashed border-slate-200 dark:border-slate-700">
                            Belum ada kolektor aktif — assign pelanggan belum bisa dilakukan.
                        </div>
                    @else
                        <form method="POST" action="{{ route('collector-worksheet.assign-selected') }}"
                              @submit="
                                  if (selectedCustomers.length === 0) {
                                      $event.preventDefault();
                                      $event.stopPropagation();
                                      if (window.Toast) {
                                          window.Toast.warning('Validasi Formulir', 'Harap pilih / centang minimal 1 pelanggan dalam daftar terlebih dahulu.');
                                      }
                                      return false;
                                  }
                              "
                              data-confirm="Assign pelanggan terpilih ke kolektor yang dipilih?" class="space-y-4">
                            @csrf
                            <input type="hidden" name="redirect_to" value="index">

                            {{-- Multi Select Header Bar --}}
                            @if($unassignedCustomers->isNotEmpty())
                                <div class="flex items-center justify-between px-3 py-2 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-100 dark:border-slate-700/80 text-xs">
                                    <label class="flex items-center gap-2 cursor-pointer select-none">
                                        <input type="checkbox"
                                               @change="
                                                   if ($el.checked) {
                                                       selectedCustomers = [{{ $unassignedCustomers->pluck('id')->implode(',') }}];
                                                   } else {
                                                       selectedCustomers = [];
                                                   }
                                               "
                                               class="rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900 text-sky-600 focus:ring-sky-500/20">
                                        <span class="font-semibold text-slate-700 dark:text-slate-300">Pilih Semua di Halaman Ini</span>
                                    </label>
                                    <span class="text-[11px] text-slate-500 dark:text-slate-400 font-mono">
                                        <span x-text="selectedCustomers.length" class="font-bold text-sky-600 dark:text-sky-400">0</span> terpilih
                                    </span>
                                </div>
                            @endif

                            {{-- Customer Checkbox List --}}
                            <div class="divide-y divide-slate-100 dark:divide-slate-700/50 max-h-80 overflow-y-auto border border-slate-100 dark:border-slate-700/80 rounded-xl bg-white dark:bg-slate-900/40 custom-scrollbar">
                                @forelse ($unassignedCustomers as $customer)
                                    <label class="px-4 py-3 flex items-center gap-3 hover:bg-slate-50/80 dark:hover:bg-slate-700/30 cursor-pointer transition-colors">
                                        <input type="checkbox" name="customer_ids[]" value="{{ $customer->id }}"
                                               x-model="selectedCustomers"
                                               class="rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900 text-sky-600 focus:ring-sky-500/20">
                                        <div class="flex-1 min-w-0">
                                            <div class="text-xs font-semibold text-slate-800 dark:text-slate-200 truncate">{{ $customer->full_name }}</div>
                                            <div class="flex items-center gap-2 mt-0.5 text-[10px] text-slate-400 dark:text-slate-500 font-mono">
                                                <span class="bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded text-slate-600 dark:text-slate-400">
                                                    {{ $customer->cid ?? $customer->customer_code }}
                                                </span>
                                                <span>&bull;</span>
                                                <span>{{ $customer->pop->name ?? '-' }}</span>
                                            </div>
                                        </div>
                                    </label>
                                @empty
                                    <div class="px-4 py-8 text-center text-xs text-slate-400 dark:text-slate-500">
                                        Semua pelanggan dalam scope Anda sudah memiliki kolektor.
                                    </div>
                                @endforelse
                            </div>

                            {{-- Bottom Assign Controls --}}
                            @if ($unassignedCustomers->isNotEmpty())
                                <div class="p-3 bg-slate-50 dark:bg-slate-900/80 rounded-xl border border-slate-200/80 dark:border-slate-700/80 space-y-2 sm:space-y-0 sm:flex sm:items-center sm:gap-2">
                                    <select name="collector_id" required
                                            class="w-full sm:flex-1 text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-200 focus:outline-none focus:border-sky-500">
                                        <option value="">Pilih kolektor tujuan...</option>
                                        @foreach ($collectors as $collector)
                                            <option value="{{ $collector->id }}">{{ $collector->name }} ({{ $collector->customer_count }} pelanggan)</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold py-2 px-5 rounded-xl transition-all cursor-pointer shrink-0 shadow-xs flex items-center justify-center gap-1.5">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                        <span>Assign Terpilih</span>
                                    </button>
                                </div>
                            @endif

                            <div class="mt-3">
                                {{ $unassignedCustomers->links() }}
                            </div>
                        </form>
                    @endif
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
