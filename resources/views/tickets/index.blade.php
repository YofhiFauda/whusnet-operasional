@extends('layouts.app')

@section('title', $activeBucket->label() . ' — Ticket Service Desk')
@section('page_title', 'Ticket Service Desk')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto pb-12">

    {{-- Top Header Bar --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-xl font-extrabold text-text-main tracking-tight flex items-center gap-2">
                    <svg class="h-6 w-6 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                    </svg>
                    {{ $activeBucket->label() }}
                </h1>
                <span class="text-xs font-bold font-mono px-2.5 py-0.5 rounded-full border {{ $activeBucket->badgeClasses() }}">
                    {{ $bucketCounts[$activeBucket->value] ?? 0 }} Tickets
                </span>
            </div>
            <p class="text-xs text-text-muted mt-1 font-medium">{{ $activeBucket->description() }}</p>
        </div>

        @if(auth()->user()->hasPermission('tickets.create'))
        <a href="{{ route('tickets.create') }}"
           class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg bg-sky-600 text-white text-xs font-bold uppercase tracking-wider shadow-md shadow-sky-600/20 hover:bg-sky-700 transition-all cursor-pointer">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            New Ticket
        </a>
        @endif
    </div>

    {{-- Bucket Navigation Tabs (NOC Inbox Buckets) --}}
    <div class="flex items-center gap-1 border-b border-border overflow-x-auto pb-px">
        @foreach(\App\Enums\TicketBucket::cases() as $bucket)
            <a href="{{ route('tickets.bucket', $bucket->value) }}"
               class="shrink-0 inline-flex items-center gap-2 px-4 py-2.5 text-xs font-bold uppercase tracking-wider border-b-2 transition-all
                      {{ $activeBucket === $bucket
                            ? 'border-sky-600 text-sky-600 bg-sky-50/50 dark:bg-sky-950/20 rounded-t-lg'
                            : 'border-transparent text-text-muted hover:text-text-main hover:bg-slate-50 dark:hover:bg-slate-900/40' }}">
                <span>{{ $bucket->label() }}</span>
                <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded-full 
                            {{ $activeBucket === $bucket ? 'bg-sky-600 text-white' : 'bg-slate-200 dark:bg-slate-800 text-text-muted' }}">
                    {{ $bucketCounts[$bucket->value] ?? 0 }}
                </span>
            </a>
        @endforeach
    </div>

    {{-- Toolbar: Fast Search + Filters --}}
    <form method="GET" action="{{ route('tickets.bucket', $activeBucket->value) }}"
          class="bg-surface border border-border rounded-xl p-4 shadow-xs flex flex-col md:flex-row md:items-center gap-3">
        
        {{-- Search Input --}}
        <div class="relative flex-1">
            <svg class="h-4 w-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-text-muted pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input type="text" name="q" value="{{ request('q') }}"
                   placeholder="Cari nomor ticket, CID, nama pelanggan, atau keluhan..."
                   class="w-full pl-10 pr-4 py-2 text-xs font-mono rounded-lg border border-border bg-background text-text-main placeholder:text-text-muted focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
        </div>

        {{-- Filter Tipe --}}
        <div class="relative">
            <select name="type" class="text-xs rounded-lg border border-border bg-background px-3 py-2 text-text-main font-mono focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                <option value="">Semua Tipe</option>
                @foreach($typeOptions as $opt)
                    <option value="{{ $opt['value'] }}" @selected(request('type') === $opt['value'])>
                        {{ $opt['value'] }} — {{ $opt['label'] }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Ticket Saya Checkbox --}}
        <label class="inline-flex items-center gap-2 text-xs font-semibold text-text-secondary cursor-pointer whitespace-nowrap bg-background border border-border px-3 py-2 rounded-lg">
            <input type="checkbox" name="mine" value="1" @checked(request()->boolean('mine'))
                   class="rounded border-border text-sky-600 focus:ring-sky-500/30">
            <span>Ticket Saya</span>
        </label>

        {{-- Submit & Reset Buttons --}}
        <div class="flex items-center gap-2">
            <button type="submit" 
                    class="px-4 py-2 rounded-lg bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900 text-xs font-bold uppercase tracking-wider hover:bg-slate-800 transition-all cursor-pointer">
                Terapkan Filter
            </button>

            @if(request()->hasAny(['q', 'type', 'mine']))
                <a href="{{ route('tickets.bucket', $activeBucket->value) }}" 
                   class="px-3 py-2 text-xs font-bold text-text-muted hover:text-rose-600 transition-colors">
                    Reset
                </a>
            @endif
        </div>
    </form>

    {{-- Daftar Ticket List View (NOC Service Desk Inbox Style) --}}
    <div class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs divide-y divide-border">
        @forelse($tickets as $ticket)
            @php
                $borderAccent = $ticket->bucket()->borderAccentClasses();
            @endphp

            <a href="{{ route('tickets.show', $ticket) }}"
               class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 hover:bg-sky-50/50 dark:hover:bg-slate-900/40 transition-colors border-l-4 {{ $borderAccent }} cursor-pointer group">

                {{-- Left Side: Creator Avatar & Ticket Overview --}}
                <div class="flex items-start gap-3.5 min-w-0 flex-1">
                    {{-- User Avatar --}}
                    <div class="h-9 w-9 shrink-0 rounded-lg bg-slate-100 dark:bg-slate-800 border border-border flex items-center justify-center text-xs font-bold font-mono text-slate-700 dark:text-slate-300 group-hover:border-sky-500 transition-colors">
                        {{ strtoupper(substr($ticket->creator->name ?? '?', 0, 2)) }}
                    </div>

                    <div class="min-w-0 flex-1">
                        {{-- Meta Row: Creator name, Classification, Status, Priority --}}
                        <div class="flex items-center gap-2 flex-wrap text-xs">
                            <span class="font-bold text-text-main group-hover:text-sky-600 transition-colors truncate">
                                {{ $ticket->creator->name ?? '—' }}
                            </span>
                            
                            <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded border {{ $ticket->type->badgeClasses() }}">
                                {{ $ticket->type->value }}
                            </span>

                            <span class="text-[10px] font-bold px-2 py-0.5 rounded border {{ $ticket->statusBadgeClasses() }}">
                                {{ $ticket->statusLabel() }}
                            </span>

                            @if($ticket->priority)
                                <span class="text-[10px] font-semibold text-text-muted">
                                    • {{ $ticket->priority->value }}
                                </span>
                            @endif

                            @if($ticket->attachments_count > 0)
                                <span class="inline-flex items-center gap-1 text-[10px] font-mono font-medium text-sky-600 bg-sky-50 dark:bg-sky-950/50 px-1.5 py-0.5 rounded border border-sky-200 dark:border-sky-900">
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                    </svg>
                                    {{ $ticket->attachments_count }}
                                </span>
                            @endif
                        </div>

                        {{-- Main Info: Ticket ID & Customer Name --}}
                        <div class="flex items-center gap-2 mt-1 truncate">
                            <span class="font-mono font-bold text-xs text-sky-600 dark:text-sky-400 data-text">{{ $ticket->ticket_number }}</span>
                            <span class="text-text-muted text-xs">—</span>
                            <span class="text-xs font-semibold text-text-main truncate">{{ $ticket->customer->full_name ?? $ticket->customer_name ?? '—' }}</span>
                            <span class="text-xs font-mono text-text-muted">({{ $ticket->customer->display_id ?? $ticket->customer?->cid ?? '—' }})</span>
                        </div>

                        {{-- Complaint snippet --}}
                        <p class="text-xs text-text-muted truncate mt-0.5 line-clamp-1">
                            {{ $ticket->detail_keluhan }}
                        </p>
                    </div>
                </div>

                {{-- Right Side: POP & Timestamp --}}
                <div class="shrink-0 flex sm:flex-col items-center sm:items-end justify-between text-xs text-text-muted pt-2 sm:pt-0 border-t sm:border-t-0 border-border">
                    <span class="font-mono text-[11px] text-text-secondary">
                        {{ \App\Support\IndonesianDate::dateTime($ticket->created_at) }}
                    </span>
                    
                    <div class="flex items-center gap-1.5 mt-1">
                        <svg class="h-3.5 w-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span class="font-bold text-[11px] text-text-main">{{ $ticket->pop->name ?? '—' }}</span>
                    </div>
                </div>
            </a>
        @empty
            <div class="p-16 text-center space-y-3">
                <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-text-muted">
                    <svg class="h-6 w-6 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-bold text-text-main">Tidak ada ticket di {{ $activeBucket->label() }}</p>
                    <p class="text-xs text-text-muted mt-0.5">{{ $activeBucket->description() }}</p>
                </div> 
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    <div class="pt-2">{{ $tickets->links() }}</div>
</div>
@endsection

