{{--
    History Ticketing — arsip SELURUH tiket yang sudah lepas dari meja Ticketing
    (Selesai Helpdesk/NOC, Assign FOP, atau Dibatalkan).
    Dirender oleh TicketHistoryController.
    Desain responsif multi-device: Desktop, Laptop, Tablet, dan Mobile dengan
    pilihan Table Mode & Card Mode + Quick Drawer Preview.
    Mengacu pada Design System WHUSNET Operasional (Design.md — Type A Data List Page).
--}}
@extends('layouts.app')

@section('title', 'History Ticketing — Service Desk Archive')
@section('page_title', 'History Ticketing')

@php
    use App\Http\Controllers\TicketHistoryController;
    use App\Support\IndonesianDate;
    use App\Enums\TicketHandler;

    $activeSecondaryFilters = array_filter(array_diff_key($filters, ['q' => '']));
    $hasActiveSecondary = count($activeSecondaryFilters) > 0;
    $totalActiveFilters = count(array_filter($filters));
    $totalTicketsCount = $summary['total'] ?? 0;
@endphp

@section('content')
<div x-data="ticketHistoryView()" class="space-y-5 pb-16">

    {{-- =========================================================================
         1. NAKED PAGE HEADER (Design System §1.7)
         ========================================================================= --}}
    <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
        <div class="space-y-1.5 min-w-0">
            <div class="flex items-center gap-2.5 flex-wrap">
                <div class="w-9 h-9 rounded-lg bg-sky-50 dark:bg-sky-950/60 border border-sky-200 dark:border-sky-800/60 flex items-center justify-center text-sky-600 dark:text-sky-400 shrink-0">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-xl sm:text-2xl font-black text-text-main tracking-tight">History Ticketing</h1>
                    <span class="text-[11px] font-mono font-bold px-2.5 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-text-muted border border-border shrink-0">
                        Arsip Service Desk
                    </span>
                </div>
            </div>
            <p class="text-xs text-text-muted font-medium max-w-3xl leading-relaxed">
                Rekap arsip tiket selesai, dibatalkan, atau diserahkan ke FOP. Tiket aktif berjalan dipantau di Worksheet Helpdesk / NOC.
            </p>
        </div>

        <div class="flex items-center gap-2.5 shrink-0 flex-wrap self-start xl:self-auto">
            {{-- Toggle View (Table vs Card) on Desktop/Tablet/Mobile --}}
            <div class="inline-flex items-center p-1 rounded-lg bg-surface-muted border border-border shadow-2xs">
                <button type="button" @click="setViewMode('table')"
                        :class="viewMode === 'table' ? 'bg-surface text-sky-600 dark:text-sky-400 shadow-xs' : 'text-text-muted hover:text-text-main'"
                        class="px-2.5 py-1.5 rounded-md text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer"
                        title="Tampilan Tabel Spreadsheet">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span class="hidden md:inline">Tabel</span>
                </button>
                <button type="button" @click="setViewMode('cards')"
                        :class="viewMode === 'cards' ? 'bg-surface text-sky-600 dark:text-sky-400 shadow-xs' : 'text-text-muted hover:text-text-main'"
                        class="px-2.5 py-1.5 rounded-md text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer"
                        title="Tampilan Kartu Operasional">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                    </svg>
                    <span class="hidden md:inline">Kartu</span>
                </button>
            </div>

            {{-- Refresh Button --}}
            <button type="button" onclick="window.location.reload()"
                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-border bg-surface hover:bg-surface-muted text-text-secondary hover:text-text-main text-xs font-semibold shadow-2xs transition-colors cursor-pointer"
                    title="Muat Ulang Halaman">
                <svg class="h-3.5 w-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span class="hidden sm:inline">Refresh</span>
            </button>

            {{-- Export Excel --}}
            @if($canExport)
            <a href="{{ route('tickets.history.export', request()->query()) }}"
               class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-lg bg-emerald-600 text-white text-xs font-bold uppercase tracking-wider shadow-xs hover:bg-emerald-700 active:scale-95 transition-all cursor-pointer">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                </svg>
                <span>Export Excel</span>
            </a>
            @endif
        </div>
    </div>

    {{-- =========================================================================
         2. STAT KPI METRICS STRIP (Design System §1.3 & §1.6)
         ========================================================================= --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        {{-- Card 1: Total Tiket --}}
        <div class="rounded-lg border border-border bg-surface p-3.5 shadow-2xs hover:border-border-strong transition-colors">
            <div class="flex items-center justify-between">
                <p class="text-[10px] sm:text-[11px] font-bold uppercase tracking-wider text-text-muted">Total Tiket</p>
                <span class="w-6 h-6 rounded-md bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-500">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </span>
            </div>
            <p class="text-xl sm:text-2xl font-black text-text-main font-mono mt-1.5">{{ number_format($summary['total']) }}</p>
            <p class="text-[10px] text-text-muted mt-0.5 truncate">Total tiket ditutup / dilepas</p>
        </div>

        {{-- Card 2: Selesai Helpdesk/NOC --}}
        <div class="rounded-lg border border-emerald-200 dark:border-emerald-900/40 bg-surface p-3.5 shadow-2xs">
            <div class="flex items-center justify-between">
                <p class="text-[10px] sm:text-[11px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Selesai Desk</p>
                <span class="w-6 h-6 rounded-md bg-emerald-100 dark:bg-emerald-900/50 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                </span>
            </div>
            <div class="flex items-baseline gap-2 mt-1.5">
                <p class="text-xl sm:text-2xl font-black text-emerald-600 dark:text-emerald-400 font-mono">{{ number_format($summary['selesai']) }}</p>
                @if($totalTicketsCount > 0)
                    <span class="text-[10px] font-bold font-mono text-emerald-600 dark:text-emerald-400">
                        {{ round(($summary['selesai'] / $totalTicketsCount) * 100) }}%
                    </span>
                @endif
            </div>
            <p class="text-[10px] text-emerald-700/80 dark:text-emerald-400/80 mt-0.5 truncate">Selesai Helpdesk / NOC</p>
        </div>

        {{-- Card 3: Assign FOP --}}
        <div class="rounded-lg border border-sky-200 dark:border-sky-900/40 bg-surface p-3.5 shadow-2xs">
            <div class="flex items-center justify-between">
                <p class="text-[10px] sm:text-[11px] font-bold uppercase tracking-wider text-sky-700 dark:text-sky-400">Assign FOP</p>
                <span class="w-6 h-6 rounded-md bg-sky-100 dark:bg-sky-900/50 flex items-center justify-center text-sky-600 dark:text-sky-400">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                </span>
            </div>
            <div class="flex items-baseline gap-2 mt-1.5">
                <p class="text-xl sm:text-2xl font-black text-sky-600 dark:text-sky-400 font-mono">{{ number_format($summary['assign_fop']) }}</p>
                @if($totalTicketsCount > 0)
                    <span class="text-[10px] font-bold font-mono text-sky-600 dark:text-sky-400">
                        {{ round(($summary['assign_fop'] / $totalTicketsCount) * 100) }}%
                    </span>
                @endif
            </div>
            <p class="text-[10px] text-sky-700/80 dark:text-sky-400/80 mt-0.5 truncate">Dieskalasi ke Teknisi FOP</p>
        </div>

        {{-- Card 4: Dibatalkan --}}
        <div class="rounded-lg border border-border bg-surface p-3.5 shadow-2xs">
            <div class="flex items-center justify-between">
                <p class="text-[10px] sm:text-[11px] font-bold uppercase tracking-wider text-text-muted">Dibatalkan</p>
                <span class="w-6 h-6 rounded-md bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-500">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </span>
            </div>
            <div class="flex items-baseline gap-2 mt-1.5">
                <p class="text-xl sm:text-2xl font-black text-slate-500 dark:text-slate-400 font-mono">{{ number_format($summary['dibatalkan']) }}</p>
                @if($totalTicketsCount > 0)
                    <span class="text-[10px] font-bold font-mono text-text-muted">
                        {{ round(($summary['dibatalkan'] / $totalTicketsCount) * 100) }}%
                    </span>
                @endif
            </div>
            <p class="text-[10px] text-text-muted mt-0.5 truncate">Dibatalkan sebelum FOP</p>
        </div>

        {{-- Card 5: Rata² Durasi di Meja Ticketing --}}
        <div class="col-span-2 sm:col-span-2 lg:col-span-1 rounded-lg border border-indigo-200 dark:border-indigo-900/40 bg-surface p-3.5 shadow-2xs">
            <div class="flex items-center justify-between">
                <p class="text-[10px] sm:text-[11px] font-bold uppercase tracking-wider text-indigo-700 dark:text-indigo-400">Rata² Durasi Desk</p>
                <span class="w-6 h-6 rounded-md bg-indigo-100 dark:bg-indigo-900/50 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <p class="text-xl sm:text-2xl font-black text-indigo-600 dark:text-indigo-400 font-mono mt-1.5">{{ $summary['avg_label'] }}</p>
            <p class="text-[10px] text-indigo-700/80 dark:text-indigo-400/80 mt-0.5 truncate" title="Kecepatan Helpdesk/NOC menyelesaikan atau menyerahkan ke FOP">
                Waktu respon meja Ticketing
            </p>
        </div>
    </div>

    {{-- =========================================================================
         3. SEARCH & ADVANCED FILTER HUB (Design System §1.8)
         ========================================================================= --}}
    <form id="history-filter-form" method="GET" action="{{ route('tickets.history') }}"
          x-data="{
              showFilters: {{ $hasActiveSecondary ? 'true' : 'false' }},
              setDatePreset(days) {
                  const to = new Date();
                  const from = new Date();
                  if (days > 0) {
                      from.setDate(to.getDate() - days);
                  }
                  const format = (d) => {
                      const y = d.getFullYear();
                      const m = String(d.getMonth() + 1).padStart(2, '0');
                      const day = String(d.getDate()).padStart(2, '0');
                      return `${y}-${m}-${day}`;
                  };
                  document.querySelector('input[name=date_from]').value = format(from);
                  document.querySelector('input[name=date_to]').value = format(to);
                  document.getElementById('history-filter-form').submit();
              },
              setMonthPreset(current = true) {
                  const d = new Date();
                  if (!current) d.setMonth(d.getMonth() - 1);
                  const year = d.getFullYear();
                  const month = d.getMonth();
                  const firstDay = new Date(year, month, 1);
                  const lastDay = new Date(year, month + 1, 0);
                  const format = (dt) => {
                      const y = dt.getFullYear();
                      const m = String(dt.getMonth() + 1).padStart(2, '0');
                      const day = String(dt.getDate()).padStart(2, '0');
                      return `${y}-${m}-${day}`;
                  };
                  document.querySelector('input[name=date_from]').value = format(firstDay);
                  document.querySelector('input[name=date_to]').value = format(lastDay);
                  document.getElementById('history-filter-form').submit();
              }
          }"
          class="rounded-lg border border-border bg-surface shadow-2xs transition-all overflow-hidden">

        {{-- Main Search Bar --}}
        <div class="p-3 sm:p-4 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
            {{-- Input Cari Utama --}}
            <div class="relative flex-1">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-text-muted">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input type="text" name="q" value="{{ $filters['q'] }}"
                       placeholder="Cari nomor tiket, nama pelanggan, CID, nomor HP, desa, keluhan..."
                       class="w-full pl-10 pr-9 text-xs sm:text-sm rounded-lg border border-border bg-background py-2.5 text-text-main placeholder:text-text-muted focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all font-medium">
                @if($filters['q'])
                    <a href="{{ route('tickets.history', request()->except(['q', 'page'])) }}"
                       class="absolute inset-y-0 right-0 pr-3 flex items-center text-text-muted hover:text-rose-500 transition-colors"
                       title="Hapus pencarian">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </a>
                @endif
            </div>

            {{-- Action Controls --}}
            <div class="flex items-center gap-2 shrink-0">
                <button type="button" @click="showFilters = !showFilters"
                        class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-lg border border-border bg-background hover:bg-surface-muted text-xs font-semibold text-text-secondary hover:text-text-main transition-colors cursor-pointer flex-1 sm:flex-initial"
                        :class="{ 'border-sky-500 text-sky-600 dark:text-sky-400 bg-sky-50/50 dark:bg-sky-950/30': showFilters || {{ $hasActiveSecondary ? 'true' : 'false' }} }">
                    <svg class="w-4 h-4 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                    <span>Filter</span>
                    @if(count($activeSecondaryFilters) > 0)
                        <span class="px-1.5 py-0.5 text-[10px] font-bold font-mono rounded-full bg-sky-600 text-white">
                            {{ count($activeSecondaryFilters) }}
                        </span>
                    @endif
                    <svg class="w-3.5 h-3.5 text-text-muted transition-transform duration-200" :class="{ 'rotate-180': showFilters }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <button type="submit" class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-lg bg-sky-600 text-white text-xs font-bold uppercase tracking-wider hover:bg-sky-700 active:scale-95 transition-all cursor-pointer shadow-xs flex-1 sm:flex-initial">
                    Terapkan
                </button>

                @if($totalActiveFilters > 0)
                    <a href="{{ route('tickets.history') }}"
                       class="px-3 py-2.5 rounded-lg text-xs font-semibold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors inline-flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                        <span>Reset</span>
                    </a>
                @endif
            </div>
        </div>

        {{-- Active Filters Badges / Pills --}}
        @if($totalActiveFilters > 0)
            <div class="px-3.5 sm:px-4 pb-3 flex items-center gap-1.5 flex-wrap border-t border-border/60 pt-2.5 bg-surface-muted/30">
                <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted mr-1">Filter Aktif:</span>

                @if($filters['q'])
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-surface border border-border text-text-main shadow-2xs">
                        <span class="text-text-muted">Cari:</span> "{{ $filters['q'] }}"
                        <a href="{{ route('tickets.history', request()->except(['q', 'page'])) }}" class="hover:text-rose-500 font-bold ml-0.5">×</a>
                    </span>
                @endif

                @if($filters['pop_id'])
                    @php $popName = collect($popOptions)->firstWhere('id', (int) $filters['pop_id'])?->name; @endphp
                    @if($popName)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-surface border border-border text-text-main shadow-2xs">
                            <span class="text-text-muted">POP:</span> {{ $popName }}
                            <a href="{{ route('tickets.history', request()->except(['pop_id', 'page'])) }}" class="hover:text-rose-500 font-bold ml-0.5">×</a>
                        </span>
                    @endif
                @endif

                @if($filters['issue_category_id'])
                    @php $catName = collect($categoryOptions)->firstWhere('id', (int) $filters['issue_category_id'])?->name; @endphp
                    @if($catName)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-surface border border-border text-text-main shadow-2xs">
                            <span class="text-text-muted">Kategori:</span> {{ $catName }}
                            <a href="{{ route('tickets.history', request()->except(['issue_category_id', 'page'])) }}" class="hover:text-rose-500 font-bold ml-0.5">×</a>
                        </span>
                    @endif
                @endif

                @if($filters['status'])
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-surface border border-border text-text-main shadow-2xs">
                        <span class="text-text-muted">Status:</span> {{ $statusOptions[$filters['status']] ?? $filters['status'] }}
                        <a href="{{ route('tickets.history', request()->except(['status', 'page'])) }}" class="hover:text-rose-500 font-bold ml-0.5">×</a>
                    </span>
                @endif

                @if($filters['handler'])
                    @php $handlerObj = TicketHandler::tryFrom($filters['handler']); @endphp
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-surface border border-border text-text-main shadow-2xs">
                        <span class="text-text-muted">Di Tangan:</span> {{ $handlerObj?->label() ?? $filters['handler'] }}
                        <a href="{{ route('tickets.history', request()->except(['handler', 'page'])) }}" class="hover:text-rose-500 font-bold ml-0.5">×</a>
                    </span>
                @endif

                @if($filters['type'])
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-surface border border-border text-text-main shadow-2xs">
                        <span class="text-text-muted">Tipe:</span> {{ $filters['type'] }}
                        <a href="{{ route('tickets.history', request()->except(['type', 'page'])) }}" class="hover:text-rose-500 font-bold ml-0.5">×</a>
                    </span>
                @endif

                @if($filters['created_by'])
                    @php $creatorName = collect($creatorOptions)->firstWhere('id', (int) $filters['created_by'])?->name; @endphp
                    @if($creatorName)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-surface border border-border text-text-main shadow-2xs">
                            <span class="text-text-muted">Input By:</span> {{ $creatorName }}
                            <a href="{{ route('tickets.history', request()->except(['created_by', 'page'])) }}" class="hover:text-rose-500 font-bold ml-0.5">×</a>
                        </span>
                    @endif
                @endif

                @if($filters['date_from'] || $filters['date_to'])
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-surface border border-border text-text-main shadow-2xs">
                        <span class="text-text-muted">Rentang:</span> {{ $filters['date_from'] ?: '...' }} s/d {{ $filters['date_to'] ?: '...' }}
                        <a href="{{ route('tickets.history', request()->except(['date_from', 'date_to', 'page'])) }}" class="hover:text-rose-500 font-bold ml-0.5">×</a>
                    </span>
                @endif

                <a href="{{ route('tickets.history') }}" class="text-[11px] text-rose-600 dark:text-rose-400 font-bold hover:underline ml-1">
                    Hapus Semua
                </a>
            </div>
        @endif

        {{-- Expandable Secondary Filter Panel --}}
        <div x-show="showFilters" x-collapse x-cloak class="p-4 border-t border-border bg-surface-muted/50 space-y-4">
            {{-- Quick Date Range Presets --}}
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted mr-1">Preset Cepat:</span>
                <button type="button" @click="setDatePreset(0)" class="px-2.5 py-1 rounded-md border border-border bg-surface hover:bg-surface-muted text-xs font-medium text-text-secondary hover:text-text-main transition-colors cursor-pointer">
                    Hari Ini
                </button>
                <button type="button" @click="setDatePreset(7)" class="px-2.5 py-1 rounded-md border border-border bg-surface hover:bg-surface-muted text-xs font-medium text-text-secondary hover:text-text-main transition-colors cursor-pointer">
                    7 Hari Terakhir
                </button>
                <button type="button" @click="setDatePreset(30)" class="px-2.5 py-1 rounded-md border border-border bg-surface hover:bg-surface-muted text-xs font-medium text-text-secondary hover:text-text-main transition-colors cursor-pointer">
                    30 Hari Terakhir
                </button>
                <button type="button" @click="setMonthPreset(true)" class="px-2.5 py-1 rounded-md border border-border bg-surface hover:bg-surface-muted text-xs font-medium text-text-secondary hover:text-text-main transition-colors cursor-pointer">
                    Bulan Ini
                </button>
                <button type="button" @click="setMonthPreset(false)" class="px-2.5 py-1 rounded-md border border-border bg-surface hover:bg-surface-muted text-xs font-medium text-text-secondary hover:text-text-main transition-colors cursor-pointer">
                    Bulan Lalu
                </button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-text-muted">POP / Cabang</label>
                    <select name="pop_id" class="w-full text-xs rounded-lg border border-border bg-background px-3 py-2 text-text-main focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all font-medium">
                        <option value="">Semua POP</option>
                        @foreach($popOptions as $pop)
                            <option value="{{ $pop->id }}" @selected((string) $filters['pop_id'] === (string) $pop->id)>{{ $pop->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-text-muted">Kategori Issue</label>
                    <select name="issue_category_id" class="w-full text-xs rounded-lg border border-border bg-background px-3 py-2 text-text-main focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all font-medium">
                        <option value="">Semua Kategori</option>
                        @foreach($categoryOptions as $category)
                            <option value="{{ $category->id }}" @selected((string) $filters['issue_category_id'] === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-text-muted">Status Penanganan</label>
                    <select name="status" class="w-full text-xs rounded-lg border border-border bg-background px-3 py-2 text-text-main focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all font-medium">
                        <option value="">Semua Status</option>
                        @foreach($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-text-muted">Di Tangan (Handler)</label>
                    <select name="handler" class="w-full text-xs rounded-lg border border-border bg-background px-3 py-2 text-text-main focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all font-medium">
                        <option value="">Semua Handler</option>
                        @foreach(TicketHandler::cases() as $handler)
                            <option value="{{ $handler->value }}" @selected($filters['handler'] === $handler->value)>{{ $handler->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-text-muted">Dibuat Oleh (Input By)</label>
                    <select name="created_by" class="w-full text-xs rounded-lg border border-border bg-background px-3 py-2 text-text-main focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all font-medium">
                        <option value="">Semua User</option>
                        @foreach($creatorOptions as $creator)
                            <option value="{{ $creator->id }}" @selected((string) $filters['created_by'] === (string) $creator->id)>{{ $creator->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-text-muted">Tipe Tiket</label>
                    <select name="type" class="w-full text-xs rounded-lg border border-border bg-background px-3 py-2 text-text-main focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all font-medium">
                        <option value="">Semua Tipe</option>
                        @foreach($typeOptions as $opt)
                            <option value="{{ $opt['value'] }}" @selected($filters['type'] === $opt['value'])>{{ $opt['value'] }} — {{ $opt['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-text-muted">Dari Tanggal</label>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] }}"
                           class="w-full text-xs rounded-lg border border-border bg-background px-3 py-2 text-text-main focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all font-medium">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-text-muted">Sampai Tanggal</label>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] }}"
                           class="w-full text-xs rounded-lg border border-border bg-background px-3 py-2 text-text-main focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all font-medium">
                </div>
            </div>
        </div>
    </form>

    {{-- =========================================================================
         4. DATA PRESENTATION (TABLE SPREADSHEET & CARD STREAM)
         ========================================================================= --}}
    @if($tickets->isEmpty())
        {{-- Polished Empty State --}}
        <div class="rounded-lg border border-border bg-surface p-12 text-center shadow-2xs">
            <div class="w-14 h-14 rounded-lg bg-surface-muted border border-border flex items-center justify-center text-text-muted mx-auto mb-3 shadow-2xs">
                <svg class="h-7 w-7 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h3 class="text-sm sm:text-base font-bold text-text-main">Tidak Ada Riwayat Tiket</h3>
            <p class="text-xs text-text-muted mt-1 max-w-sm mx-auto">
                Belum ada arsip tiket yang sesuai dengan kata kunci pencarian atau filter yang Anda pilih.
            </p>
            @if($totalActiveFilters > 0)
                <div class="mt-4">
                    <a href="{{ route('tickets.history') }}"
                       class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-sky-600 text-white text-xs font-bold hover:bg-sky-700 transition-all shadow-xs cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span>Reset Semua Filter</span>
                    </a>
                </div>
            @endif
        </div>
    @else

        {{-- 4A. Table Mode (High-Density Spreadsheet View for Desktop & Laptop) --}}
        <div x-show="viewMode === 'table'" class="rounded-lg border border-slate-200/70 dark:border-slate-800 bg-surface overflow-hidden shadow-2xs">
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-xs whitespace-nowrap text-left border-collapse">
                    <thead class="bg-surface-muted/90 text-text-muted sticky top-0 z-10 border-b border-slate-200/60 dark:border-slate-800/80">
                        <tr>
                            <th class="px-3.5 py-2.5 font-bold uppercase tracking-wider text-[10px] sm:text-[11px]">Waktu &amp; Tiket</th>
                            <th class="px-3.5 py-2.5 font-bold uppercase tracking-wider text-[10px] sm:text-[11px]">Pelanggan &amp; Kontak</th>
                            <th class="px-3.5 py-2.5 font-bold uppercase tracking-wider text-[10px] sm:text-[11px]">Lokasi</th>
                            <th class="px-3.5 py-2.5 font-bold uppercase tracking-wider text-[10px] sm:text-[11px]">Issue &amp; Keluhan</th>
                            <th class="px-3.5 py-2.5 font-bold uppercase tracking-wider text-[10px] sm:text-[11px]">Status &amp; Penanganan</th>
                            <th class="px-3.5 py-2.5 font-bold uppercase tracking-wider text-[10px] sm:text-[11px]">Selesai / Lepas</th>
                            <th class="px-3.5 py-2.5 font-bold uppercase tracking-wider text-[10px] sm:text-[11px] text-right">Durasi Desk</th>
                            <th class="px-3.5 py-2.5 font-bold uppercase tracking-wider text-[10px] sm:text-[11px]">SLA</th>
                            <th class="px-3.5 py-2.5 font-bold uppercase tracking-wider text-[10px] sm:text-[11px] text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50">
                        @foreach($tickets as $ticket)
                            @php
                                $isBatch = $ticket->isBatch();
                            @endphp
                            <tr @click="openDrawer({{ $ticket->id }})"
                                class="hover:bg-sky-50/50 dark:hover:bg-slate-800/40 transition-colors group cursor-pointer {{ $isBatch ? 'bg-violet-50/20 dark:bg-violet-950/10' : '' }}">
                                
                                {{-- 1. Waktu & Tiket --}}
                                <td class="px-3.5 py-2.5">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <a href="{{ route('tickets.show', $ticket->id) }}" @click.stop
                                           class="font-mono font-bold text-sky-600 dark:text-sky-400 group-hover:underline text-xs">
                                            {{ $ticket->ticket_number }}
                                        </a>
                                        @if($isBatch)
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-extrabold bg-violet-100 dark:bg-violet-950 text-violet-700 dark:text-violet-300 border border-violet-200 dark:border-violet-800 shrink-0 inline-flex items-center gap-1">
                                                <span class="w-1.5 h-1.5 rounded-full bg-violet-500 animate-pulse"></span>
                                                BATCH ({{ $ticket->batchMembers->count() }})
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        <span class="font-mono text-[10px] text-text-muted">
                                            {{ IndonesianDate::dateTime($ticket->created_at) }}
                                        </span>
                                        <span class="px-1 py-0.2 rounded text-[9px] font-mono font-medium bg-surface-muted text-text-muted border border-border">
                                            {{ $ticket->type->value }}
                                        </span>
                                    </div>
                                </td>

                                {{-- 2. Pelanggan & Kontak --}}
                                <td class="px-3.5 py-2.5">
                                    @if($isBatch)
                                        <div class="font-bold text-violet-700 dark:text-violet-300 text-xs">
                                            ⚡ {{ $ticket->customer_name ?: 'Insiden Massal' }}
                                        </div>
                                        <span class="block font-mono text-[10px] text-violet-600 dark:text-violet-400 font-semibold">
                                            {{ $ticket->batchMembers->count() }} Pelanggan Terdampak
                                        </span>
                                    @else
                                        <div class="font-semibold text-text-main group-hover:text-sky-600 dark:group-hover:text-sky-400 transition-colors text-xs">
                                            {{ $ticket->customer->full_name ?? $ticket->customer_name ?? '—' }}
                                        </div>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <span class="font-mono text-[10px] font-semibold text-text-muted bg-surface-muted px-1.5 py-0.2 rounded border border-border">
                                                {{ $ticket->customer?->display_id ?? '—' }}
                                            </span>
                                            @if($ticket->customer_phone)
                                                <a href="https://wa.me/{{ preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $ticket->customer_phone)) }}"
                                                   target="_blank" @click.stop
                                                   class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400 font-mono text-[10px] hover:underline font-medium"
                                                   title="Chat via WhatsApp">
                                                    <svg class="h-3 w-3 fill-current shrink-0" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                                                    <span>{{ $ticket->customer_phone }}</span>
                                                </a>
                                            @endif
                                        </div>
                                    @endif
                                </td>

                                {{-- 3. Lokasi --}}
                                <td class="px-3.5 py-2.5">
                                    <div class="flex items-center gap-1 font-medium text-text-secondary text-xs">
                                        <svg class="w-3 h-3 text-sky-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                        <span>{{ $ticket->pop?->name ?? '—' }}</span>
                                    </div>
                                    <span class="block text-[10px] text-text-muted mt-0.5 truncate max-w-[130px]" title="{{ $ticket->customer_village }}">
                                        📍 {{ $ticket->customer_village ?? '—' }}
                                    </span>
                                </td>

                                {{-- 4. Issue & Keluhan --}}
                                <td class="px-3.5 py-2.5 max-w-xs">
                                    <p class="truncate text-text-secondary text-xs" title="{{ $ticket->detail_keluhan }}">
                                        {{ $ticket->detail_keluhan }}
                                    </p>
                                    <div class="flex items-center gap-1.5 mt-0.5 flex-wrap">
                                        <span class="px-1.5 py-0.2 rounded text-[9px] font-medium bg-surface-muted border border-border text-text-muted">
                                            {{ $ticket->issueCategory?->name ?? '—' }}
                                        </span>
                                        @if($ticket->customer_package)
                                            <span class="text-[9px] font-mono text-text-muted truncate max-w-[100px]" title="Paket: {{ $ticket->customer_package }}">
                                                📦 {{ $ticket->customer_package }}
                                            </span>
                                        @endif
                                    </div>
                                </td>

                                {{-- 5. Status & Penanganan --}}
                                <td class="px-3.5 py-2.5">
                                    <span class="inline-block px-2.5 py-0.5 rounded-md border text-[10px] font-bold {{ TicketHistoryController::statusBadgeFor($ticket) }}">
                                        {{ TicketHistoryController::statusLabelFor($ticket) }}
                                    </span>
                                </td>

                                {{-- 6. Selesai / Lepas & Aktor --}}
                                <td class="px-3.5 py-2.5">
                                    <div class="font-mono text-[11px] text-text-muted">
                                        {{ $ticket->resolved_at ? IndonesianDate::dateTime($ticket->resolved_at) : '—' }}
                                    </div>
                                    <div class="text-[10px] text-text-secondary font-medium mt-0.5 flex items-center gap-1">
                                        <span class="text-text-muted">Oleh:</span>
                                        <span class="font-semibold text-text-main">{{ TicketHistoryController::actorLabelFor($ticket) ?? '—' }}</span>
                                    </div>
                                </td>

                                {{-- 7. Durasi Desk --}}
                                <td class="px-3.5 py-2.5 font-mono text-right {{ $ticket->resolved_at ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-text-muted' }}">
                                    {{ $ticket->solvingTimeLabel() ?? '—' }}
                                </td>

                                {{-- 8. Target SLA --}}
                                <td class="px-3.5 py-2.5">
                                    @if($ticket->slaBadgeLabel())
                                        <span class="inline-block px-2 py-0.5 rounded border text-[10px] font-bold {{ $ticket->slaBadgeClasses() }}">
                                            {{ $ticket->slaBadgeLabel() }}
                                        </span>
                                    @else
                                        <span class="text-text-muted text-xs">—</span>
                                    @endif
                                </td>

                                {{-- 9. Aksi Cepat --}}
                                <td class="px-3.5 py-2.5 text-center shrink-0" @click.stop>
                                    <a href="{{ route('tickets.show', $ticket->id) }}"
                                       class="inline-flex items-center justify-center gap-1 px-3 py-1 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold transition-all shadow-xs active:scale-95 cursor-pointer"
                                       title="Lihat Detail Tiket">
                                        <span>Detail</span>
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </a>
                                </td>
                            </tr>

                            {{-- Batch Members Sub-Rows --}}
                            @if($ticket->isBatch() && $ticket->batchMembers->isNotEmpty())
                                @foreach($ticket->batchMembers as $member)
                                    <tr class="bg-violet-50/40 dark:bg-violet-950/20 text-[11px] border-b border-violet-100/60 dark:border-violet-900/20">
                                        <td class="px-3.5 py-2 text-center text-violet-500 dark:text-violet-400 font-bold">↳</td>
                                        <td class="px-3.5 py-2" colspan="2">
                                            <div class="flex items-center gap-1.5">
                                                <span class="font-bold text-text-main">{{ $member->customer_name }}</span>
                                                <span class="font-mono text-[10px] text-sky-600 dark:text-sky-400">({{ $member->cid ?: '—' }})</span>
                                                @if($member->phone)
                                                    <a href="https://wa.me/{{ preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $member->phone)) }}"
                                                       target="_blank" @click.stop
                                                       class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400 font-mono text-[10px] hover:underline"
                                                       title="WhatsApp Member">
                                                        <svg class="h-3 w-3 fill-current shrink-0" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                                                        <span>{{ $member->phone }}</span>
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-3.5 py-2" colspan="6">
                                            <span class="text-[10px] text-text-muted italic">Tergabung dalam Tiket Batch {{ $ticket->ticket_number }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- 4B. Card Mode (Operational Card Stream for Mobile & Tablet) --}}
        <div x-show="viewMode === 'cards'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($tickets as $ticket)
                @php
                    $isBatch = $ticket->isBatch();
                @endphp
                <div @click="openDrawer({{ $ticket->id }})"
                     class="rounded-lg border border-border bg-surface p-4 shadow-2xs hover:border-sky-300 dark:hover:border-sky-800 transition-all cursor-pointer flex flex-col justify-between space-y-3.5 relative overflow-hidden group {{ $isBatch ? 'border-violet-300/70 dark:border-violet-800/80 bg-violet-50/15 dark:bg-violet-950/10' : '' }}">
                    
                    {{-- Card Header: Ticket Number & Status --}}
                    <div class="space-y-1.5">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <a href="{{ route('tickets.show', $ticket->id) }}" @click.stop
                                   class="font-mono font-black text-sm text-sky-600 dark:text-sky-400 group-hover:underline">
                                    {{ $ticket->ticket_number }}
                                </a>
                                @if($isBatch)
                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-extrabold bg-violet-100 dark:bg-violet-950 text-violet-700 dark:text-violet-300 border border-violet-200 dark:border-violet-800 shrink-0 inline-flex items-center gap-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-violet-500 animate-pulse"></span>
                                        BATCH ({{ $ticket->batchMembers->count() }})
                                    </span>
                                @endif
                                <span class="px-1.5 py-0.2 rounded text-[9px] font-mono font-medium bg-surface-muted text-text-muted border border-border">
                                    {{ $ticket->type->value }}
                                </span>
                            </div>

                            <span class="inline-block px-2 py-0.5 rounded-md border text-[10px] font-bold shrink-0 {{ TicketHistoryController::statusBadgeFor($ticket) }}">
                                {{ TicketHistoryController::statusLabelFor($ticket) }}
                            </span>
                        </div>

                        {{-- Customer info --}}
                        <div>
                            @if($isBatch)
                                <h4 class="font-bold text-violet-700 dark:text-violet-300 text-sm">
                                    ⚡ {{ $ticket->customer_name ?: 'Insiden Massal' }}
                                </h4>
                                <p class="text-[11px] font-mono text-violet-600 dark:text-violet-400 mt-0.5">
                                    {{ $ticket->batchMembers->count() }} Pelanggan Terdampak
                                </p>
                            @else
                                <div class="flex items-center justify-between gap-2">
                                    <h4 class="font-bold text-text-main group-hover:text-sky-600 dark:group-hover:text-sky-400 transition-colors text-sm">
                                        {{ $ticket->customer->full_name ?? $ticket->customer_name ?? '—' }}
                                    </h4>
                                    @if($ticket->customer?->display_id)
                                        <span class="font-mono text-[10px] font-bold text-text-muted bg-surface-muted px-1.5 py-0.2 rounded border border-border shrink-0">
                                            {{ $ticket->customer->display_id }}
                                        </span>
                                    @endif
                                </div>

                                @if($ticket->customer_phone)
                                    <div class="mt-1">
                                        <a href="https://wa.me/{{ preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $ticket->customer_phone)) }}"
                                           target="_blank" @click.stop
                                           class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400 font-mono text-xs hover:underline font-semibold"
                                           title="Chat via WhatsApp">
                                            <svg class="h-3.5 w-3.5 fill-current shrink-0" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                                            <span>{{ $ticket->customer_phone }}</span>
                                        </a>
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>

                    {{-- Issue Body --}}
                    <div class="rounded-lg bg-surface-muted/60 p-3 space-y-2 text-xs">
                        <p class="text-text-secondary leading-relaxed line-clamp-2" title="{{ $ticket->detail_keluhan }}">
                            {{ $ticket->detail_keluhan }}
                        </p>
                        <div class="flex items-center justify-between gap-2 flex-wrap pt-1 border-t border-border/50 text-[11px]">
                            <span class="font-medium text-text-muted">
                                📍 {{ $ticket->pop?->name ?? '—' }} &bull; {{ $ticket->customer_village ?? '—' }}
                            </span>
                            <span class="px-1.5 py-0.2 rounded text-[10px] font-medium bg-surface border border-border text-text-muted">
                                {{ $ticket->issueCategory?->name ?? '—' }}
                            </span>
                        </div>
                    </div>

                    {{-- Batch Members Expansion preview if batch --}}
                    @if($isBatch && $ticket->batchMembers->isNotEmpty())
                        <div class="rounded-lg border border-violet-200 dark:border-violet-900/50 bg-violet-50/40 dark:bg-violet-950/20 p-2.5 space-y-1.5">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-violet-700 dark:text-violet-300">
                                Anggota Batch ({{ $ticket->batchMembers->count() }}):
                            </span>
                            <div class="space-y-1 max-h-24 overflow-y-auto custom-scrollbar pr-1">
                                @foreach($ticket->batchMembers as $member)
                                    <div class="flex items-center justify-between text-[11px] py-0.5 border-b border-violet-100 dark:border-violet-900/30 last:border-0">
                                        <span class="font-semibold text-text-main truncate max-w-[140px]">{{ $member->customer_name }}</span>
                                        <span class="font-mono text-[10px] text-sky-600 dark:text-sky-400">{{ $member->cid ?: '—' }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Card Footer: Metadata & Actions --}}
                    <div class="pt-2 border-t border-border flex items-center justify-between gap-2 text-xs flex-wrap">
                        <div class="space-y-0.5">
                            <div class="flex items-center gap-2 text-[10px] text-text-muted font-mono">
                                <span>Durasi: <strong class="{{ $ticket->resolved_at ? 'text-emerald-600 dark:text-emerald-400' : 'text-text-main' }}">{{ $ticket->solvingTimeLabel() ?? '—' }}</strong></span>
                                @if($ticket->slaBadgeLabel())
                                    &bull;
                                    <span class="font-bold {{ $ticket->slaBadgeClasses() }} px-1 rounded">
                                        {{ $ticket->slaBadgeLabel() }}
                                    </span>
                                @endif
                            </div>
                            <div class="text-[10px] text-text-muted truncate max-w-[200px]">
                                Oleh: <strong class="text-text-secondary">{{ TicketHistoryController::actorLabelFor($ticket) ?? '—' }}</strong>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5 shrink-0" @click.stop>
                            <button type="button" @click="openDrawer({{ $ticket->id }})"
                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-border bg-surface hover:bg-surface-muted text-text-secondary hover:text-text-main text-xs font-semibold shadow-2xs transition-colors cursor-pointer">
                                <svg class="w-3.5 h-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <span>Preview</span>
                            </button>
                            <a href="{{ route('tickets.show', $ticket->id) }}"
                               class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold transition-all shadow-xs cursor-pointer">
                                <span>Detail</span>
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- =========================================================================
             5. PAGINATION & RESULT COUNTER
             ========================================================================= --}}
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-2">
            <p class="text-xs text-text-muted font-medium">
                Menampilkan <span class="font-bold text-text-main font-mono">{{ $tickets->firstItem() ?? 0 }}</span> - <span class="font-bold text-text-main font-mono">{{ $tickets->lastItem() ?? 0 }}</span> dari <span class="font-bold text-text-main font-mono">{{ $tickets->total() }}</span> riwayat tiket
            </p>
            <div>
                {{ $tickets->links() }}
            </div>
        </div>
    @endif

    {{-- =========================================================================
         6. QUICK DETAIL DRAWER SLIDEOVER (Design System Interactive Drawer)
         ========================================================================= --}}
    @include('tickets.partials.detail-drawer')

</div>
@endsection

@push('scripts')
<script>
    /**
     * Alpine Component untuk History Ticketing View
     */
    function ticketHistoryView() {
        return {
            viewMode: localStorage.getItem('whusnet_ticket_history_view') || (window.innerWidth >= 768 ? 'table' : 'cards'),

            init() {
                // Responsiveness listener jika resize layar
                window.addEventListener('resize', () => {
                    if (!localStorage.getItem('whusnet_ticket_history_view')) {
                        this.viewMode = window.innerWidth >= 768 ? 'table' : 'cards';
                    }
                });
            },

            setViewMode(mode) {
                this.viewMode = mode;
                localStorage.setItem('whusnet_ticket_history_view', mode);
            },

            openDrawer(ticketId) {
                window.dispatchEvent(new CustomEvent('open-ticket-drawer', {
                    detail: { id: ticketId }
                }));
            }
        };
    }
</script>
@endpush
