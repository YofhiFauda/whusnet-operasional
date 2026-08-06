{{--
    History Ticketing — arsip SELURUH tiket, dirender TicketHistoryController.
    Pengganti sheet Google Sheets "Helpdesk Task Manager" (lihat
    docs/plan/analisa-halaman-history-ticketing.md), makanya tampilannya sengaja
    tabel padat gaya spreadsheet, bukan kartu seperti halaman arsip lain.
--}}
@extends('layouts.app')

@section('title', 'History Ticketing — Ticket Service Desk')
@section('page_title', 'History Ticketing')

@php
    use App\Http\Controllers\TicketHistoryController;
@endphp

@section('content')
<div class="space-y-5 pb-12">

    {{-- Header + ringkasan --}}
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <h1 class="text-xl font-extrabold text-text-main tracking-tight flex items-center gap-2">
                <svg class="h-6 w-6 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                History Ticketing
            </h1>
            <p class="text-xs text-text-muted mt-1 font-medium">
                Tiket yang sudah lepas dari meja Ticketing — Selesai, Dibatalkan, atau diserahkan ke FOP.
                Tiket yang masih dikerjakan ada di Worksheet Helpdesk / Worksheet NOC.
            </p>
        </div>

        @if($canExport)
        <a href="{{ route('tickets.history.export', request()->query()) }}"
           class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-emerald-600 text-white text-xs font-bold uppercase tracking-wider shadow-md shadow-emerald-600/20 hover:bg-emerald-700 active:scale-95 transition-all cursor-pointer shrink-0">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
            </svg>
            Export Excel
        </a>
        @endif
    </div>

    {{-- Stat ringkas — meniru "RATA² SOLVING TIME" di header sheet lama --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="rounded-xl border border-border bg-surface p-3.5">
            <p class="text-[10px] font-bold uppercase tracking-wider text-text-muted">Total Tiket</p>
            <p class="text-2xl font-extrabold text-text-main font-mono mt-1">{{ number_format($summary['total']) }}</p>
        </div>
        <div class="rounded-xl border border-border bg-surface p-3.5">
            <p class="text-[10px] font-bold uppercase tracking-wider text-text-muted">Selesai Helpdesk/NOC</p>
            <p class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 font-mono mt-1">{{ number_format($summary['selesai']) }}</p>
        </div>
        <div class="rounded-xl border border-border bg-surface p-3.5">
            <p class="text-[10px] font-bold uppercase tracking-wider text-text-muted">Assign FOP</p>
            <p class="text-2xl font-extrabold text-sky-600 dark:text-sky-400 font-mono mt-1">{{ number_format($summary['assign_fop']) }}</p>
        </div>
        <div class="rounded-xl border border-border bg-surface p-3.5">
            <p class="text-[10px] font-bold uppercase tracking-wider text-text-muted">Dibatalkan</p>
            <p class="text-2xl font-extrabold text-slate-500 dark:text-slate-400 font-mono mt-1">{{ number_format($summary['dibatalkan']) }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-3">
        <div class="rounded-xl border border-border bg-surface p-3.5">
            {{--
                Rata-rata durasi di meja Ticketing: created_at → resolved_at.
                Buat tiket jalur FOP, resolved_at = waktu diserahkan ke FOP —
                jadi angka ini mengukur kecepatan Ticketing, BUKAN kecepatan
                penyelesaian di lapangan (itu SLA pengerjaan di modul FOP).
            --}}
            <p class="text-[10px] font-bold uppercase tracking-wider text-text-muted">Rata-rata Durasi di Ticketing</p>
            <p class="text-2xl font-extrabold text-sky-600 dark:text-sky-400 font-mono mt-1">{{ $summary['avg_label'] }}</p>
            <p class="text-[10px] text-text-muted mt-1">Sampai diselesaikan atau diserahkan ke FOP — bukan sampai teknisi selesai.</p>
        </div>
    </div>

    {{-- Filter — GET biasa (bukan fetch): halaman ini sering di-bookmark & dibagikan per rentang tanggal --}}
    @php
        $activeSecondaryFilters = array_filter(array_diff_key($filters, ['q' => '']));
        $hasActiveSecondary = count($activeSecondaryFilters) > 0;
        $totalActiveFilters = count(array_filter($filters));
    @endphp

    <form method="GET" action="{{ route('tickets.history') }}"
          x-data="{ showFilters: {{ $hasActiveSecondary ? 'true' : 'false' }} }"
          class="rounded-xl border border-border bg-surface shadow-xs transition-all">

        {{-- Baris Utama (Search Bar + Action Hub) --}}
        <div class="p-3 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
            {{-- Input Cari Utama --}}
            <div class="relative flex-1">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-text-muted">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input type="text" name="q" value="{{ $filters['q'] }}"
                       placeholder="Cari tiket, nama pelanggan, CID, desa, keluhan..."
                       class="w-full pl-9 pr-8 text-xs rounded-lg border border-border bg-background py-2 text-text-main placeholder:text-text-muted focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all font-medium">
                @if($filters['q'])
                    <a href="{{ route('tickets.history', request()->except(['q', 'page'])) }}"
                       class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-text-muted hover:text-text-main"
                       title="Hapus pencarian">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </a>
                @endif
            </div>

            {{-- Tombol Filter & Aksi --}}
            <div class="flex items-center gap-2 shrink-0">
                <button type="button" @click="showFilters = !showFilters"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-border bg-background hover:bg-surface-muted text-xs font-semibold text-text-secondary hover:text-text-main transition-colors cursor-pointer"
                        :class="{ 'border-sky-500 text-sky-600 dark:text-sky-400 bg-sky-50/50 dark:bg-sky-950/30': showFilters || {{ $hasActiveSecondary ? 'true' : 'false' }} }">
                    <svg class="w-3.5 h-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                    <span>Filter</span>
                    @if(count($activeSecondaryFilters) > 0)
                        <span class="px-1.5 py-0.5 text-[10px] font-bold font-mono rounded-full bg-sky-600 text-white">
                            {{ count($activeSecondaryFilters) }}
                        </span>
                    @endif
                    <svg class="w-3 h-3 text-text-muted transition-transform duration-200" :class="{ 'rotate-180': showFilters }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-sky-600 text-white text-xs font-bold uppercase tracking-wider hover:bg-sky-700 active:scale-95 transition-all cursor-pointer shadow-xs">
                    Terapkan
                </button>

                @if($totalActiveFilters > 0)
                    <a href="{{ route('tickets.history') }}"
                       class="px-3 py-2 rounded-lg text-xs font-semibold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors">
                        Reset
                    </a>
                @endif
            </div>
        </div>

        {{-- Ringkasan Filter Aktif (Pills) --}}
        @if($totalActiveFilters > 0)
            <div class="px-3 pb-3 flex items-center gap-1.5 flex-wrap border-t border-border/50 pt-2.5">
                <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted mr-1">Filter Aktif:</span>

                @if($filters['q'])
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-surface-muted border border-border text-text-main">
                        <span class="text-text-muted">Cari:</span> "{{ $filters['q'] }}"
                        <a href="{{ route('tickets.history', request()->except(['q', 'page'])) }}" class="hover:text-rose-500 ml-0.5 font-bold">×</a>
                    </span>
                @endif

                @if($filters['pop_id'])
                    @php $popName = collect($popOptions)->firstWhere('id', (int) $filters['pop_id'])?->name; @endphp
                    @if($popName)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-surface-muted border border-border text-text-main">
                            <span class="text-text-muted">POP:</span> {{ $popName }}
                            <a href="{{ route('tickets.history', request()->except(['pop_id', 'page'])) }}" class="hover:text-rose-500 ml-0.5 font-bold">×</a>
                        </span>
                    @endif
                @endif

                @if($filters['issue_category_id'])
                    @php $catName = collect($categoryOptions)->firstWhere('id', (int) $filters['issue_category_id'])?->name; @endphp
                    @if($catName)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-surface-muted border border-border text-text-main">
                            <span class="text-text-muted">Kategori:</span> {{ $catName }}
                            <a href="{{ route('tickets.history', request()->except(['issue_category_id', 'page'])) }}" class="hover:text-rose-500 ml-0.5 font-bold">×</a>
                        </span>
                    @endif
                @endif

                @if($filters['status'])
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-surface-muted border border-border text-text-main">
                        <span class="text-text-muted">Status:</span> {{ $statusOptions[$filters['status']] ?? $filters['status'] }}
                        <a href="{{ route('tickets.history', request()->except(['status', 'page'])) }}" class="hover:text-rose-500 ml-0.5 font-bold">×</a>
                    </span>
                @endif

                @if($filters['handler'])
                    @php $handlerObj = \App\Enums\TicketHandler::tryFrom($filters['handler']); @endphp
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-surface-muted border border-border text-text-main">
                        <span class="text-text-muted">Di Tangan:</span> {{ $handlerObj?->label() ?? $filters['handler'] }}
                        <a href="{{ route('tickets.history', request()->except(['handler', 'page'])) }}" class="hover:text-rose-500 ml-0.5 font-bold">×</a>
                    </span>
                @endif

                @if($filters['type'])
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-surface-muted border border-border text-text-main">
                        <span class="text-text-muted">Tipe:</span> {{ $filters['type'] }}
                        <a href="{{ route('tickets.history', request()->except(['type', 'page'])) }}" class="hover:text-rose-500 ml-0.5 font-bold">×</a>
                    </span>
                @endif

                @if($filters['created_by'])
                    @php $creatorName = collect($creatorOptions)->firstWhere('id', (int) $filters['created_by'])?->name; @endphp
                    @if($creatorName)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-surface-muted border border-border text-text-main">
                            <span class="text-text-muted">Input By:</span> {{ $creatorName }}
                            <a href="{{ route('tickets.history', request()->except(['created_by', 'page'])) }}" class="hover:text-rose-500 ml-0.5 font-bold">×</a>
                        </span>
                    @endif
                @endif

                @if($filters['date_from'] || $filters['date_to'])
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-surface-muted border border-border text-text-main">
                        <span class="text-text-muted">Tanggal:</span> {{ $filters['date_from'] ?: '—' }} s/d {{ $filters['date_to'] ?: '—' }}
                        <a href="{{ route('tickets.history', request()->except(['date_from', 'date_to', 'page'])) }}" class="hover:text-rose-500 ml-0.5 font-bold">×</a>
                    </span>
                @endif
            </div>
        @endif

        {{-- Grid Filter Sekunder (Expandable) --}}
        <div x-show="showFilters" x-collapse x-cloak class="p-3 border-t border-border bg-surface-muted/40 space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="space-y-1">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-text-muted">POP / Cabang</label>
                    <select name="pop_id" class="w-full text-xs rounded-lg border border-border bg-background px-2.5 py-1.5 text-text-main focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                        <option value="">Semua POP</option>
                        @foreach($popOptions as $pop)
                            <option value="{{ $pop->id }}" @selected((string) $filters['pop_id'] === (string) $pop->id)>{{ $pop->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-text-muted">Kategori Issue</label>
                    <select name="issue_category_id" class="w-full text-xs rounded-lg border border-border bg-background px-2.5 py-1.5 text-text-main focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                        <option value="">Semua Kategori</option>
                        @foreach($categoryOptions as $category)
                            <option value="{{ $category->id }}" @selected((string) $filters['issue_category_id'] === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-text-muted">Status</label>
                    <select name="status" class="w-full text-xs rounded-lg border border-border bg-background px-2.5 py-1.5 text-text-main focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                        <option value="">Semua Status</option>
                        @foreach($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-text-muted">Di Tangan</label>
                    <select name="handler" class="w-full text-xs rounded-lg border border-border bg-background px-2.5 py-1.5 text-text-main focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                        <option value="">Semua</option>
                        @foreach(\App\Enums\TicketHandler::cases() as $handler)
                            <option value="{{ $handler->value }}" @selected($filters['handler'] === $handler->value)>{{ $handler->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-text-muted">Input By</label>
                    <select name="created_by" class="w-full text-xs rounded-lg border border-border bg-background px-2.5 py-1.5 text-text-main focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                        <option value="">Semua User</option>
                        @foreach($creatorOptions as $creator)
                            <option value="{{ $creator->id }}" @selected((string) $filters['created_by'] === (string) $creator->id)>{{ $creator->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-text-muted">Tipe Tiket</label>
                    <select name="type" class="w-full text-xs rounded-lg border border-border bg-background px-2.5 py-1.5 text-text-main focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                        <option value="">Semua Tipe</option>
                        @foreach($typeOptions as $opt)
                            <option value="{{ $opt['value'] }}" @selected($filters['type'] === $opt['value'])>{{ $opt['value'] }} — {{ $opt['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-text-muted">Dari Tanggal</label>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] }}"
                           class="w-full text-xs rounded-lg border border-border bg-background px-2.5 py-1.5 text-text-main focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                </div>

                <div class="space-y-1">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-text-muted">Sampai Tanggal</label>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] }}"
                           class="w-full text-xs rounded-lg border border-border bg-background px-2.5 py-1.5 text-text-main focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                </div>
            </div>
        </div>
    </form>

    {{-- Tabel — scroll horizontal sendiri, halaman gak ikut melebar --}}
    <div class="rounded-xl border border-border bg-surface overflow-hidden">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-xs whitespace-nowrap">
                <thead class="bg-surface-muted dark:bg-slate-900/60 text-text-muted">
                    <tr class="text-left">
                        <th class="px-3 py-2.5 font-bold uppercase tracking-wider">Date</th>
                        <th class="px-3 py-2.5 font-bold uppercase tracking-wider">Tiket</th>
                        <th class="px-3 py-2.5 font-bold uppercase tracking-wider">Input By</th>
                        <th class="px-3 py-2.5 font-bold uppercase tracking-wider">Nama / CID</th>
                        <th class="px-3 py-2.5 font-bold uppercase tracking-wider">HP</th>
                        <th class="px-3 py-2.5 font-bold uppercase tracking-wider">Desa</th>
                        <th class="px-3 py-2.5 font-bold uppercase tracking-wider">POP</th>
                        <th class="px-3 py-2.5 font-bold uppercase tracking-wider">Issue / Aduan</th>
                        <th class="px-3 py-2.5 font-bold uppercase tracking-wider">Kategori</th>
                        <th class="px-3 py-2.5 font-bold uppercase tracking-wider">Paket</th>
                        <th class="px-3 py-2.5 font-bold uppercase tracking-wider">Status</th>
                        {{--
                            Artinya menyesuaikan hasil akhir: yang menyelesaikan,
                            yang membatalkan, atau yang MENGIRIM ke FOP.
                        --}}
                        <th class="px-3 py-2.5 font-bold uppercase tracking-wider" title="Yang menyelesaikan / membatalkan / mengirim ke FOP">Oleh</th>
                        <th class="px-3 py-2.5 font-bold uppercase tracking-wider" title="Tiket selesai, atau diserahkan ke FOP">Selesai / Diserahkan</th>
                        <th class="px-3 py-2.5 font-bold uppercase tracking-wider text-right" title="Lama tiket berada di meja Ticketing">Durasi Ticketing</th>
                        <th class="px-3 py-2.5 font-bold uppercase tracking-wider" title="Target Handling SLA — tepat waktu atau lewat, dihitung dari created_at s.d. resolved_at">SLA</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($tickets as $ticket)
                        <tr class="hover:bg-sky-50/50 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="px-3 py-2.5 font-mono text-text-muted">{{ \App\Support\IndonesianDate::dateTime($ticket->created_at) }}</td>
                            <td class="px-3 py-2.5">
                                <a href="{{ route('tickets.show', $ticket->id) }}" class="font-mono font-bold text-sky-600 dark:text-sky-400 hover:underline">
                                    {{ $ticket->ticket_number }}
                                </a>
                            </td>
                            <td class="px-3 py-2.5 text-text-secondary">{{ $ticket->creator?->name ?? '—' }}</td>
                            <td class="px-3 py-2.5">
                                <span class="font-semibold text-text-main">{{ $ticket->customer_name ?? '—' }}</span>
                                <span class="block font-mono text-[10px] text-text-muted">{{ $ticket->customer?->display_id ?? '—' }}</span>
                            </td>
                            <td class="px-3 py-2.5 font-mono text-text-secondary">{{ $ticket->customer_phone ?? '—' }}</td>
                            {{-- Snapshot saat tiket dibuat — sengaja bukan relasi desa terkini --}}
                            <td class="px-3 py-2.5 text-text-secondary">{{ $ticket->customer_village ?? '—' }}</td>
                            <td class="px-3 py-2.5 text-text-secondary">{{ $ticket->pop?->name ?? '—' }}</td>
                            <td class="px-3 py-2.5 max-w-xs truncate text-text-secondary" title="{{ $ticket->detail_keluhan }}">{{ $ticket->detail_keluhan }}</td>
                            <td class="px-3 py-2.5 text-text-secondary">{{ $ticket->issueCategory?->name ?? '—' }}</td>
                            <td class="px-3 py-2.5 text-text-secondary">{{ $ticket->customer_package ?? '—' }}</td>
                            <td class="px-3 py-2.5">
                                <span class="inline-block px-2 py-0.5 rounded border text-[10px] font-bold {{ TicketHistoryController::statusBadgeFor($ticket) }}">
                                    {{ TicketHistoryController::statusLabelFor($ticket) }}
                                </span>
                            </td>
                            <td class="px-3 py-2.5 text-text-secondary">{{ TicketHistoryController::actorLabelFor($ticket) ?? '—' }}</td>
                            <td class="px-3 py-2.5 font-mono text-text-muted">
                                {{ $ticket->resolved_at ? \App\Support\IndonesianDate::dateTime($ticket->resolved_at) : '—' }}
                            </td>
                            <td class="px-3 py-2.5 font-mono text-right {{ $ticket->resolved_at ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-text-muted' }}">
                                {{ $ticket->solvingTimeLabel() ?? '—' }}
                            </td>
                            <td class="px-3 py-2.5">
                                @if($ticket->slaBadgeLabel())
                                    <span class="inline-block px-2 py-0.5 rounded border text-[10px] font-bold {{ $ticket->slaBadgeClasses() }}">
                                        {{ $ticket->slaBadgeLabel() }}
                                    </span>
                                @else
                                    <span class="text-text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="15" class="px-3 py-10 text-center text-text-muted">
                                Tidak ada tiket yang cocok dengan filter ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>
        {{ $tickets->links() }}
    </div>
</div>
@endsection
