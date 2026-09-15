@extends('layouts.app')

@section('title', 'Detail Ticket ' . $ticket->ticket_number . ' — ISP NOC')
@section('page_title', 'Detail Ticket ' . $ticket->ticket_number)

@section('content')
@php
    $isBatch = $ticket->isBatch();
    $customer = $ticket->customer;
    $koordinat = ($ticket->customer_latitude && $ticket->customer_longitude)
        ? "{$ticket->customer_latitude}, {$ticket->customer_longitude}"
        : null;
    $batchCount = $ticket->batchMembers->count();
    $ticketActions = $ticket->actionFlagsFor(auth()->user());
@endphp

<div class="space-y-6 max-w-6xl mx-auto pb-16">

    {{-- Top Navigation & Breadcrumb --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-2 text-xs text-text-muted flex-wrap">
            <a href="{{ route('tickets.create') }}" class="hover:text-sky-600 dark:hover:text-sky-400 transition-colors flex items-center gap-1 font-medium">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
                Ticketing
            </a>
            <span>/</span>
            <span class="text-text-main font-semibold">Detail Ticket</span>
            <span>/</span>
            <span class="font-mono font-bold {{ $isBatch ? 'text-violet-600 dark:text-violet-400' : 'text-sky-600 dark:text-sky-400' }}">{{ $ticket->ticket_number }}</span>
            @if($isBatch)
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-violet-100 dark:bg-violet-950/70 text-violet-700 dark:text-violet-300 border border-violet-200 dark:border-violet-800">
                    <span class="w-1.5 h-1.5 rounded-full bg-violet-500 animate-pulse"></span>
                    BATCH INCIDENT
                </span>
            @endif
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('tickets.create') }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-border bg-surface text-xs font-semibold text-text-secondary hover:text-text-main hover:bg-surface-muted transition-all shadow-2xs">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Kembali ke Inbox
            </a>
        </div>
    </div>

    @if($isBatch)
        {{-- ========================================================================= --}}
        {{-- ⚡ BATCH INCIDENT COMMAND HUB (TAMPILAN TIKET MASSAL SEPERTI TKT-2026-0328) --}}
        {{-- ========================================================================= --}}
        
        {{-- Hero Banner Insiden Massal --}}
        <div class="relative overflow-hidden rounded-2xl border border-violet-300/80 dark:border-violet-800/60 bg-gradient-to-br from-violet-50/90 via-surface to-surface dark:from-violet-950/30 dark:via-surface dark:to-surface p-6 shadow-sm">
            {{-- Background decorative ambient lights --}}
            <div class="absolute -top-16 -right-16 w-64 h-64 bg-violet-400/10 dark:bg-violet-600/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-16 -left-16 w-64 h-64 bg-indigo-400/10 dark:bg-indigo-600/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="relative z-10 flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6">
                <div class="space-y-3 min-w-0">
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-black uppercase tracking-wider bg-violet-600 text-white shadow-xs">
                            <svg class="h-3.5 w-3.5 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            INSIDEN MASSAL (BATCH)
                        </span>

                        <h1 class="text-2xl font-black font-mono tracking-tight text-violet-700 dark:text-violet-300">
                            {{ $ticket->ticket_number }}
                        </h1>

                        <span class="text-xs font-mono font-bold px-2.5 py-1 rounded-lg border {{ $ticket->type->badgeClasses() }}">
                            {{ $ticket->type->value }} — {{ $ticket->type->label() }}
                        </span>

                        @if($ticket->issueCategory)
                            <span class="text-xs font-bold px-2.5 py-1 rounded-lg border border-violet-200 dark:border-violet-800 text-violet-700 dark:text-violet-300 bg-violet-100/70 dark:bg-violet-950/60 shadow-2xs">
                                ⚡ {{ $ticket->issueCategory->name }}
                            </span>
                        @endif

                        <span class="text-xs font-bold px-2.5 py-1 rounded-lg border {{ $ticket->statusBadgeClasses() }}">
                            {{ $ticket->statusLabel() }}
                        </span>

                        @if($ticket->priority)
                            <span class="text-xs font-bold px-2.5 py-1 rounded-lg bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200 border border-border">
                                Prioritas: {{ $ticket->priority->value }}
                            </span>
                        @endif
                    </div>

                    <div class="flex items-center gap-3 text-xs text-text-muted flex-wrap">
                        <div class="flex items-center gap-1.5 font-semibold text-text-main">
                            <svg class="h-4 w-4 text-violet-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            <span>Wilayah POP: <strong class="text-text-main">{{ $ticket->pop->name ?? '—' }}</strong></span>
                        </div>
                        <span>•</span>
                        <div class="flex items-center gap-1.5 font-semibold text-text-main">
                            <svg class="h-4 w-4 text-violet-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <span>Dampak: <strong class="text-violet-600 dark:text-violet-400">{{ $batchCount }} Pelanggan Terdaftar</strong></span>
                        </div>
                        @if($ticket->customer_odp)
                            <span>•</span>
                            <div class="flex items-center gap-1.5 text-text-secondary font-mono">
                                <span>ODP: <strong class="text-text-main">{{ $ticket->customer_odp }}</strong></span>
                            </div>
                        @endif
                    </div>

                    {{-- SLA Countdown --}}
                    @if($ticket->slaDeadline())
                        <div class="flex items-center gap-2 text-xs pt-1">
                            <span class="font-bold text-text-secondary">Handling SLA:</span>
                            @if(! $ticket->resolved_at && $ticket->handler !== \App\Enums\TicketHandler::FOP)
                                <x-countdown-timer
                                    :deadline="$ticket->slaDeadline()->toIso8601String()"
                                    :total-seconds="$ticket->slaTotalSeconds()"
                                    label="Sisa Waktu Handling SLA" />
                            @else
                                <span class="text-xs font-bold px-2.5 py-0.5 rounded-md border {{ $ticket->slaBadgeClasses() }}">
                                    {{ $ticket->slaBadgeLabel() }}
                                </span>
                                @if($ticket->handler === \App\Enums\TicketHandler::FOP)
                                    <span class="text-[11px] text-text-muted">(Diteruskan ke FOP — SLA dilanjutkan di Task FOP Lapangan)</span>
                                @endif
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Creator & Meta Atribusi --}}
                <div class="shrink-0 p-3.5 rounded-xl bg-surface/80 dark:bg-slate-900/60 border border-border backdrop-blur-xs space-y-1.5 text-xs">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted">Assigned by:</span>
                        <span class="font-bold text-text-main">{{ $ticket->creator->name ?? '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted">Dibuat:</span>
                        <span class="font-mono text-text-main">{{ \App\Support\IndonesianDate::dateTime($ticket->created_at) }}</span>
                    </div>
                    @if($ticket->resolved_at)
                        <div class="flex items-center justify-between gap-3 pt-1 border-t border-border">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted">Selesai/Lepas:</span>
                            <span class="font-mono text-emerald-600 dark:text-emerald-400 font-bold">{{ \App\Support\IndonesianDate::dateTime($ticket->resolved_at) }}</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Linked FOP Task Bar --}}
            @if($ticket->fopTask)
                <div class="mt-5 pt-4 border-t border-violet-200/60 dark:border-violet-900/40 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-violet-100/50 dark:bg-violet-950/40 rounded-xl p-3.5 border">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 rounded-xl bg-violet-600 text-white shadow-xs shrink-0">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </div>
                        <div class="text-xs">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-text-main">Task FOP Lapangan Terkait:</span>
                                <span class="font-mono font-black text-violet-700 dark:text-violet-300 text-sm">{{ $ticket->fopTask->task_number }}</span>
                            </div>
                            <p class="text-text-muted mt-0.5">
                                @if($ticket->fopTask->technicians->isNotEmpty())
                                    Teknisi: <span class="font-bold text-text-main">{{ $ticket->fopTask->technicians->pluck('name')->join(', ') }}</span>
                                @else
                                    <span class="italic">Menunggu penugasan teknisi FOP di lapangan</span>
                                @endif
                            </p>
                        </div>
                    </div>

                    @if(auth()->user()->hasPermission('fop_tasks.view'))
                        <a href="{{ route('fop-tasks.index') }}"
                           class="shrink-0 inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-violet-600 text-white text-xs font-bold hover:bg-violet-700 transition-colors shadow-xs">
                            Buka Task FOP →
                        </a>
                    @endif
                </div>
            @endif
        </div>

        {{-- KPI Overview Grid --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Card 1: Total Pelanggan --}}
            <div class="p-4 rounded-xl border border-border bg-surface shadow-2xs space-y-1">
                <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted flex items-center justify-between">
                    <span>Pelanggan Terdampak</span>
                    <span class="w-2 h-2 rounded-full bg-violet-500"></span>
                </span>
                <div class="text-2xl font-black font-mono text-violet-600 dark:text-violet-400">
                    {{ $batchCount }} <span class="text-xs font-normal text-text-muted">User</span>
                </div>
                <p class="text-[11px] text-text-muted">Tergabung dalam insiden ini</p>
            </div>

            {{-- Card 2: POP & Lokasi --}}
            <div class="p-4 rounded-xl border border-border bg-surface shadow-2xs space-y-1">
                <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted flex items-center justify-between">
                    <span>Wilayah & POP</span>
                    <span class="w-2 h-2 rounded-full bg-sky-500"></span>
                </span>
                <div class="text-base font-bold text-text-main truncate">
                    {{ $ticket->pop->name ?? '—' }}
                </div>
                <p class="text-[11px] text-text-muted truncate">
                    {{ $ticket->customer_odp ? 'ODP: ' . $ticket->customer_odp : ($ticket->batchMembers->first()?->customer?->odp_code ? 'ODP: ' . $ticket->batchMembers->first()->customer->odp_code : 'Distribusi POP') }}
                </p>
            </div>

            {{-- Card 3: Handling Duration --}}
            <div class="p-4 rounded-xl border border-border bg-surface shadow-2xs space-y-1">
                <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted flex items-center justify-between">
                    <span>Durasi Penanganan</span>
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                </span>
                <div class="text-base font-mono font-bold text-text-main">
                    {{ $ticket->solvingTimeLabel() ?: $ticket->created_at->diffForHumans(null, true) }}
                </div>
                <p class="text-[11px] text-text-muted">
                    {{ $ticket->resolved_at ? 'Waktu selesai/lepas' : 'Sedang berlangsung' }}
                </p>
            </div>

            {{-- Card 4: Handler Status --}}
            <div class="p-4 rounded-xl border border-border bg-surface shadow-2xs space-y-1">
                <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted flex items-center justify-between">
                    <span>Meja Penanggungjawab</span>
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                </span>
                <div class="text-base font-bold text-text-main">
                    {{ $ticket->handler->label() }}
                </div>
                <p class="text-[11px] text-text-muted">
                    Status: <strong class="text-text-main">{{ $ticket->statusLabel() }}</strong>
                </p>
            </div>
        </div>

    @else
        {{-- ========================================================================= --}}
        {{-- 👤 NON-BATCH TICKET HEADER BANNER (SINGLE CUSTOMER TICKET) --}}
        {{-- ========================================================================= --}}
        <div class="bg-surface border border-border rounded-xl p-6 shadow-sm space-y-4">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                <div class="min-w-0 space-y-2">
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h1 class="text-xl font-extrabold font-mono text-sky-600 dark:text-sky-400 tracking-tight data-text">
                            {{ $ticket->ticket_number }}
                        </h1>

                        <span class="text-xs font-mono font-bold px-2.5 py-0.5 rounded border {{ $ticket->type->badgeClasses() }}">
                            {{ $ticket->type->value }} — {{ $ticket->type->label() }}
                        </span>

                        @if($ticket->issueCategory)
                            <span class="text-xs font-bold px-2.5 py-0.5 rounded border border-sky-200 dark:border-sky-900 text-sky-700 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/50">
                                {{ $ticket->issueCategory->name }}
                            </span>
                        @endif

                        <span class="text-xs font-bold px-2.5 py-0.5 rounded border {{ $ticket->statusBadgeClasses() }}">
                            {{ $ticket->statusLabel() }}
                        </span>

                        @if($ticket->priority)
                            <span class="text-xs font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200 border border-border">
                                Prioritas: {{ $ticket->priority->value }}
                            </span>
                        @endif
                    </div>

                    <div class="flex items-center gap-2 text-xs text-text-muted">
                        <span class="font-bold text-text-main">{{ $ticket->customer_name ?? '—' }}</span>
                        <span>•</span>
                        <span class="font-medium text-text-secondary">{{ $ticket->pop->name ?? '—' }}</span>
                    </div>

                    @if($ticket->slaDeadline())
                        <div class="flex items-center gap-2 text-xs">
                            <span class="font-semibold text-text-secondary">Target SLA:</span>
                            @if(! $ticket->resolved_at && $ticket->handler !== \App\Enums\TicketHandler::FOP)
                                <x-countdown-timer
                                    :deadline="$ticket->slaDeadline()->toIso8601String()"
                                    :total-seconds="$ticket->slaTotalSeconds()"
                                    label="Sisa Handling SLA" />
                            @else
                                <span class="text-xs font-bold px-2.5 py-0.5 rounded border {{ $ticket->slaBadgeClasses() }}">
                                    {{ $ticket->slaBadgeLabel() }}
                                </span>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="shrink-0 text-left md:text-right space-y-1 text-xs text-text-muted border-t md:border-t-0 pt-3 md:pt-0 border-border">
                    <div class="flex items-center md:justify-end gap-1.5">
                        <span class="font-semibold text-text-secondary">Assigned by:</span>
                        <span class="font-bold text-text-main">{{ $ticket->creator->name ?? '—' }}</span>
                    </div>
                    <div class="flex items-center md:justify-end gap-1.5">
                        <span class="font-semibold text-text-secondary">Created:</span>
                        <span class="font-mono text-text-main">{{ \App\Support\IndonesianDate::dateTime($ticket->created_at) }}</span>
                    </div>
                </div>
            </div>

            @if($ticket->fopTask)
                <div class="pt-4 border-t border-border flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-sky-50/50 dark:bg-slate-900/40 border border-sky-200 dark:border-sky-900/50 rounded-lg p-3.5">
                    <div class="flex items-center gap-3">
                        <div class="p-2 rounded-lg bg-sky-100 dark:bg-sky-950 text-sky-600 dark:text-sky-400 shrink-0">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </div>
                        <div class="text-xs">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-text-main">Task FOP Lapangan Terkait:</span>
                                <span class="font-mono font-bold text-sky-600 dark:text-sky-400">{{ $ticket->fopTask->task_number }}</span>
                            </div>
                            <p class="text-text-muted mt-0.5">
                                @if($ticket->fopTask->technicians->isNotEmpty())
                                    Teknisi: <span class="font-medium text-text-main">{{ $ticket->fopTask->technicians->pluck('name')->join(', ') }}</span>
                                @else
                                    Belum ada teknisi FOP ditugaskan
                                @endif
                            </p>
                        </div>
                    </div>
                    @if(auth()->user()->hasPermission('fop_tasks.view'))
                        <a href="{{ route('fop-tasks.index') }}"
                           class="shrink-0 inline-flex items-center gap-1 px-3 py-1.5 rounded bg-sky-600 text-white text-xs font-bold hover:bg-sky-700 transition-colors shadow-xs">
                            Buka Task FOP →
                        </a>
                    @endif
                </div>
            @endif
        </div>
    @endif

    {{-- Aksi Tiket — Close/Escalate (docs/plan/RANCANGAN_WORKSHEET_TICKETING.MD) --}}
    @if($ticketActions['can_close'] || $ticketActions['can_escalate_noc'] || $ticketActions['can_escalate_fop'] || $ticketActions['can_return_to_helpdesk'] || $ticketActions['can_cancel'])
    <div class="bg-surface border border-amber-200 dark:border-amber-900/50 rounded-xl p-5 shadow-xs">
        <div class="flex items-center justify-between gap-2 mb-3">
            <div class="flex items-center gap-2">
                <span class="w-1.5 h-4 bg-amber-500 rounded-full"></span>
                <h2 class="text-xs font-bold uppercase tracking-wider text-text-main">Aksi Tiket — Ditangani {{ $ticket->handler->label() }}</h2>
            </div>
            <span class="text-[10px] text-text-muted">Pilih jalur eskalasi atau penutupan tiket</span>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            @if($ticketActions['can_close'])
            <button type="button"
                onclick="confirmTicketDetailAction('{{ route('tickets.close', $ticket) }}', null, 'Selesaikan Tiket', 'Apa yang sudah dikerjakan? (opsional)', false, 'Tandai tiket ini selesai?')"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-700 active:scale-95 transition-all cursor-pointer shadow-xs">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                Selesaikan Sendiri
            </button>
            @endif

            @if($ticketActions['can_escalate_noc'])
            <button type="button"
                onclick="confirmTicketDetailAction('{{ route('tickets.escalate', $ticket) }}', 'noc', 'Kirim Tiket ke NOC', 'Catatan buat NOC (opsional)', false, 'Kirim tiket ini ke NOC?')"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-amber-600 text-white text-xs font-bold hover:bg-amber-700 active:scale-95 transition-all cursor-pointer shadow-xs">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                Kirim ke NOC
            </button>
            @endif

            @if($ticketActions['can_escalate_fop'])
            <button type="button"
                onclick="confirmTicketDetailAction('{{ route('tickets.escalate', $ticket) }}', 'fop', 'Kirim Tiket ke FOP', 'Catatan buat FOP (opsional)', false, 'Kirim tiket ini ke FOP? Task FOP baru akan dibuat.')"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-sky-600 text-white text-xs font-bold hover:bg-sky-700 active:scale-95 transition-all cursor-pointer shadow-xs">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                Kirim ke FOP
            </button>
            @endif

            @if($ticketActions['can_return_to_helpdesk'])
            <button type="button"
                onclick="confirmTicketDetailAction('{{ route('tickets.return-to-helpdesk', $ticket) }}', null, 'Kembalikan ke Helpdesk', 'Alasan dikembalikan (opsional)', false, 'Kembalikan tiket ini ke Helpdesk?')"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-slate-600 text-white text-xs font-bold hover:bg-slate-700 active:scale-95 transition-all cursor-pointer shadow-xs">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
                Kembalikan ke Helpdesk
            </button>
            @endif

            @if($ticketActions['can_cancel'])
            <button type="button"
                onclick="confirmTicketDetailAction('{{ route('tickets.cancel', $ticket) }}', null, 'Batalkan Tiket', 'Alasan pembatalan (wajib diisi)', true, 'Batalkan tiket ini? Tindakan ini tidak bisa dibatalkan kembali.')"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-red-600 text-white text-xs font-bold hover:bg-red-700 active:scale-95 transition-all cursor-pointer shadow-xs">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                Batalkan
            </button>
            @endif
        </div>
    </div>
    @endif

    {{-- ========================================================================= --}}
    {{-- 👥 DAFTAR PELANGGAN TERDAMPAK (KHUSUS TIKET BATCH) --}}
    {{-- ========================================================================= --}}
    @if($isBatch)
        <div x-data="batchRosterManager({
            ticketId: {{ $ticket->id }},
            storeUrl: '{{ route('tickets.batch-members.store', $ticket) }}',
            lookupUrl: '{{ route('tickets.lookup-customer') }}',
            members: {{ Js::from($ticket->batchMembers->map(fn($m) => [
                'id' => $m->id,
                'cid' => $m->cid ?: ($m->customer?->display_id ?: ($m->customer?->cid ?: ($m->customer?->customer_code ?: '—'))),
                'name' => $m->customer_name ?: ($m->customer?->full_name ?? '—'),
                'phone' => $m->phone ?: ($m->customer?->primary_phone ?? ''),
                'package' => $m->customer?->internetPackage?->name ?? '—',
                'address' => $m->customer?->address ?? ($m->customer?->village?->name ?? '—'),
                'odp' => $m->customer?->odp_code ?? '—',
                'added_by' => $m->addedBy?->name ?? 'Sistem',
                'created_at' => \App\Support\IndonesianDate::dateTime($m->created_at),
            ])) }}
        })" class="bg-surface border border-violet-200 dark:border-violet-900/50 rounded-2xl overflow-hidden shadow-sm space-y-4">
            
            {{-- Roster Header & Quick Tools Bar --}}
            <div class="px-6 py-4 border-b border-border bg-violet-50/50 dark:bg-violet-950/20 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-xl bg-violet-600 text-white shadow-xs">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-sm font-black uppercase tracking-wider text-text-main">DAFTAR PELANGGAN TERDAMPAK</h2>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold font-mono bg-violet-100 dark:bg-violet-950 text-violet-700 dark:text-violet-300 border border-violet-300 dark:border-violet-800"
                                  x-text="members.length + ' Pelanggan'"></span>
                        </div>
                        <p class="text-[11px] text-text-muted mt-0.5">Roster pelanggan yang mengalami kendala akibat insiden jaringan massal ini.</p>
                    </div>
                </div>

                {{-- Action Buttons (Copy CID, Copy Phone, Add Member) --}}
                <div class="flex items-center gap-2 flex-wrap">
                    <button type="button" @click="copyAllCids()"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-border bg-surface text-xs font-bold text-text-secondary hover:text-text-main hover:bg-surface-muted transition-colors shadow-2xs cursor-pointer"
                            title="Salin seluruh CID pelanggan terdampak">
                        <svg class="h-3.5 w-3.5 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                        </svg>
                        <span>Salin CID</span>
                    </button>

                    <button type="button" @click="copyAllPhones()"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-border bg-surface text-xs font-bold text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/30 transition-colors shadow-2xs cursor-pointer"
                            title="Salin semua no. HP untuk pesan broadcast WA">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                        <span>Salin No. HP</span>
                    </button>

                    <button type="button" @click="openAddModal()"
                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-violet-600 hover:bg-violet-700 text-white text-xs font-bold active:scale-95 transition-all shadow-xs cursor-pointer">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Tambah Pelanggan</span>
                    </button>
                </div>
            </div>

            {{-- Filter & Search inside Roster --}}
            <div class="px-6 py-2">
                <div class="relative">
                    <svg class="w-4 h-4 text-text-muted absolute left-3 top-3 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input type="text" x-model="searchQuery"
                           placeholder="Filter pelanggan terdampak (ketik CID, nama, nomor HP, atau alamat)..."
                           class="w-full text-xs rounded-xl border border-border bg-background pl-9 pr-4 py-2.5 text-text-main placeholder:text-text-muted focus:outline-none focus:ring-2 focus:ring-violet-500/30 focus:border-violet-500 transition-all">
                    <button type="button" x-show="searchQuery" @click="searchQuery = ''" class="absolute right-3 top-2.5 text-text-muted hover:text-text-main text-xs font-bold">×</button>
                </div>
            </div>

            {{-- Impacted Customers 1-Row Table Roster --}}
            <div class="px-6 pb-6">
                <template x-if="filteredMembers.length > 0">
                    <div class="overflow-x-auto rounded-xl border border-border bg-surface shadow-2xs">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="border-b border-border bg-slate-50/80 dark:bg-slate-900/60 text-[10px] font-black uppercase tracking-wider text-text-muted">
                                    <th class="py-3 px-3 w-10 text-center">#</th>
                                    <th class="py-3 px-4 w-44">CID Pelanggan</th>
                                    <th class="py-3 px-4 min-w-[200px]">Nama Pelanggan</th>
                                    <th class="py-3 px-4 w-48">No. HP / WhatsApp</th>
                                    <th class="py-3 px-4">Paket &amp; Lokasi</th>
                                    <th class="py-3 px-4 w-36 text-right">Dicatat Oleh</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <template x-for="(m, index) in filteredMembers" :key="m.id">
                                    <tr class="hover:bg-violet-50/40 dark:hover:bg-violet-950/20 transition-colors group">
                                        {{-- # --}}
                                        <td class="py-3 px-3 text-center text-[10px] font-mono text-text-muted" x-text="index + 1"></td>

                                        {{-- CID --}}
                                        <td class="py-3 px-4 font-mono">
                                            <span class="inline-flex items-center gap-1.5 font-bold text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/60 px-2.5 py-1 rounded-md border border-sky-200 dark:border-sky-900 hover:bg-sky-100 dark:hover:bg-sky-900/80 transition-colors cursor-pointer"
                                                  @click="copyText(m.cid, 'CID ' + m.cid + ' disalin!')"
                                                  :title="'Klik untuk salin CID: ' + m.cid">
                                                <svg class="w-3 h-3 text-sky-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                                </svg>
                                                <span x-text="m.cid"></span>
                                            </span>
                                        </td>

                                        {{-- Nama Pelanggan --}}
                                        <td class="py-3 px-4">
                                            <div class="flex items-center gap-2.5">
                                                <div class="w-7 h-7 rounded-full bg-violet-100 dark:bg-violet-950 text-violet-700 dark:text-violet-300 font-black text-[11px] flex items-center justify-center shrink-0 border border-violet-200 dark:border-violet-800"
                                                     x-text="getInitials(m.name)"></div>
                                                <div class="min-w-0">
                                                    <span class="font-bold text-text-main text-xs block truncate" :title="m.name" x-text="m.name"></span>
                                                </div>
                                            </div>
                                        </td>

                                        {{-- No. HP / WhatsApp --}}
                                        <td class="py-3 px-4 font-mono">
                                            <template x-if="m.phone">
                                                <a :href="'https://wa.me/' + m.phone" target="_blank" rel="noopener"
                                                   class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-emerald-50 dark:bg-emerald-950/40 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 text-emerald-700 dark:text-emerald-400 text-xs font-bold border border-emerald-200 dark:border-emerald-800/60 transition-all shadow-2xs group/wa">
                                                    <svg class="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="currentColor" viewBox="0 0 24 24">
                                                        <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/>
                                                    </svg>
                                                    <span x-text="m.phone"></span>
                                                </a>
                                            </template>
                                            <template x-if="!m.phone">
                                                <span class="text-text-muted italic text-[11px]">—</span>
                                            </template>
                                        </td>

                                        {{-- Paket & Lokasi --}}
                                        <td class="py-3 px-4 text-[11px]">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span class="font-semibold text-text-main" x-text="m.package"></span>
                                                <span x-show="m.odp && m.odp !== '—'" class="text-text-muted font-mono" x-text="'• ODP: ' + m.odp"></span>
                                                <span x-show="m.address && m.address !== '—'" class="text-text-secondary truncate max-w-[200px]" :title="m.address" x-text="'• ' + m.address"></span>
                                            </div>
                                        </td>

                                        {{-- Dicatat Oleh --}}
                                        <td class="py-3 px-4 text-right text-[10px] text-text-muted">
                                            <span class="font-medium text-text-secondary block truncate" x-text="m.added_by"></span>
                                            <span class="font-mono text-[9px]" x-text="m.created_at"></span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </template>

                {{-- Empty State Roster --}}
                <template x-if="filteredMembers.length === 0">
                    <div class="text-center py-10 px-4 rounded-xl border border-dashed border-border space-y-3">
                        <div class="w-12 h-12 rounded-full bg-violet-100 dark:bg-violet-950 text-violet-600 dark:text-violet-400 mx-auto flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <div class="space-y-1">
                            <p class="text-xs font-bold text-text-main" x-text="searchQuery ? 'Tidak ada pelanggan cocok dengan filter' : 'Belum ada data pelanggan terdampak'"></p>
                            <p class="text-[11px] text-text-muted">Klik tombol di bawah untuk menambahkan data pelanggan yang mengalami gangguan.</p>
                        </div>
                        <button type="button" @click="openAddModal()"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-violet-600 text-white text-xs font-bold hover:bg-violet-700 transition-colors shadow-xs cursor-pointer">
                            + Tambah Pelanggan Pertama
                        </button>
                    </div>
                </template>
            </div>

            {{-- Modal Tambah Pelanggan Terdampak --}}
            <div x-show="addModal.open" x-cloak
                 class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs"
                 @keydown.escape.window="closeAddModal()">
                <div @click.outside="closeAddModal()" class="w-full max-w-lg rounded-2xl bg-surface border border-border shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-150">
                    <div class="px-5 py-4 border-b border-border bg-violet-50/50 dark:bg-violet-950/30 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-violet-500"></span>
                            <h3 class="text-sm font-bold text-text-main">Tambah Pelanggan Terdampak</h3>
                        </div>
                        <button type="button" @click="closeAddModal()" class="text-text-muted hover:text-text-main cursor-pointer p-1">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="p-5 space-y-4">
                        {{-- Search Autocomplete Customer --}}
                        <div class="space-y-1.5 relative">
                            <label class="block text-[11px] font-bold text-text-secondary uppercase tracking-wider">Cari Data Pelanggan (CID / Nama / HP)</label>
                            <div class="relative">
                                <input type="text" x-model="addModal.cidQuery" @input.debounce.300ms="searchCustomer()"
                                       :disabled="addModal.selected !== null"
                                       placeholder="Ketik CID, nama, atau no. HP..."
                                       class="w-full text-xs rounded-xl border border-border bg-background px-3.5 py-2.5 text-text-main placeholder:text-text-muted focus:outline-none focus:ring-2 focus:ring-violet-500/30 focus:border-violet-500 disabled:bg-surface-muted transition-all">
                                <button type="button" x-show="addModal.selected" x-cloak
                                        @click="resetSelectedCustomer()"
                                        class="absolute right-3 top-2.5 text-xs font-bold text-violet-600 dark:text-violet-400 hover:underline cursor-pointer">
                                    Ganti
                                </button>
                            </div>

                            {{-- Autocomplete Results Dropdown --}}
                            <div x-show="addModal.results.length > 0 && !addModal.selected" x-cloak
                                 class="absolute z-20 mt-1 w-full bg-surface border border-border rounded-xl shadow-xl max-h-56 overflow-y-auto divide-y divide-border">
                                <template x-for="r in addModal.results" :key="r.id">
                                    <button type="button" @click="pickCustomer(r)"
                                            class="w-full text-left px-3.5 py-2.5 text-xs hover:bg-violet-50 dark:hover:bg-violet-950/40 transition-colors cursor-pointer flex items-center justify-between gap-2">
                                        <div class="min-w-0">
                                            <div class="font-bold text-text-main truncate" x-text="r.nama"></div>
                                            <div class="text-[10px] text-text-muted font-mono" x-text="r.cid + ' • ' + (r.telepon || '—')"></div>
                                        </div>
                                        <span class="text-[10px] font-semibold text-violet-600 dark:text-violet-400 shrink-0">Pilih →</span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <p class="text-[11px] text-text-muted">Pilih dari hasil pencarian untuk auto-isi Nama &amp; No. HP, atau masukkan data secara manual jika pelanggan belum terdaftar.</p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="space-y-1.5">
                                <label class="block text-[11px] font-bold text-text-secondary uppercase tracking-wider">Nama Pelanggan <span class="text-rose-500">*</span></label>
                                <input type="text" x-model="addModal.customerName" placeholder="Nama lengkap..."
                                       class="w-full text-xs rounded-xl border border-border bg-background px-3 py-2 text-text-main focus:outline-none focus:ring-2 focus:ring-violet-500/30 focus:border-violet-500 transition-all">
                            </div>
                            <div class="space-y-1.5">
                                <label class="block text-[11px] font-bold text-text-secondary uppercase tracking-wider">Nomor HP</label>
                                <input type="text" x-model="addModal.phone" placeholder="Contoh: 08123456789..."
                                       class="w-full text-xs rounded-xl border border-border bg-background px-3 py-2 text-text-main font-mono focus:outline-none focus:ring-2 focus:ring-violet-500/30 focus:border-violet-500 transition-all">
                            </div>
                        </div>

                        <p x-show="addModal.error" x-cloak class="text-xs font-semibold text-rose-600 dark:text-rose-400" x-text="addModal.error"></p>
                    </div>

                    <div class="px-5 py-3.5 border-t border-border flex items-center justify-end gap-2 bg-surface-muted/60 dark:bg-slate-900/40">
                        <button type="button" @click="closeAddModal()" class="px-3.5 py-2 rounded-xl text-xs font-bold text-text-muted hover:bg-surface border border-transparent hover:border-border cursor-pointer transition-colors">
                            Batal
                        </button>
                        <button type="button" @click="submitBatchMember()" :disabled="addModal.submitting || !addModal.customerName"
                                class="px-4 py-2 rounded-xl bg-violet-600 text-white text-xs font-bold hover:bg-violet-700 disabled:opacity-50 cursor-pointer shadow-xs transition-all">
                            <span x-show="!addModal.submitting">Simpan Pelanggan</span>
                            <span x-show="addModal.submitting" x-cloak>Menyimpan...</span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Toast Notification Feedback --}}
            <div x-show="toast.show" x-cloak
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-2"
                 class="fixed top-4 left-4 right-4 sm:left-auto sm:top-6 sm:right-6 z-50 max-w-sm rounded-xl bg-slate-900 text-white px-4 py-3 shadow-xl flex items-center gap-2.5 text-xs font-semibold border border-slate-700">
                <svg class="h-4 w-4 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <span x-text="toast.message"></span>
            </div>
        </div>
    @else
        {{-- ========================================================================= --}}
        {{-- 👤 CUSTOMER TECHNICAL SNAPSHOT (KHUSUS TIKET NON-BATCH) --}}
        {{-- ========================================================================= --}}
        <div class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs">
            <div class="px-6 py-3.5 border-b border-border bg-slate-50/50 dark:bg-slate-900/40 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-1.5 h-4 bg-sky-600 rounded-full"></span>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-text-main">CUSTOMER TECHNICAL SNAPSHOT</h2>
                </div>
                <span class="text-[10px] font-mono text-text-muted uppercase tracking-wider">Historical Snapshot</span>
            </div>

            <div class="p-6">
                <div class="border border-border rounded-lg overflow-hidden bg-border shadow-xs">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-px bg-border">

                        {{-- Cell 1: Customer Name --}}
                        <div class="bg-surface p-3 space-y-1">
                            <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">Customer Name</span>
                            <div class="text-xs font-semibold text-text-main truncate">{{ $ticket->customer_name ?: '—' }}</div>
                        </div>

                        {{-- Cell 2: CID Number --}}
                        <div class="bg-surface p-3 space-y-1">
                            <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">CID Number</span>
                            <div class="text-xs font-bold font-mono text-sky-600 dark:text-sky-400 truncate">
                                {{ $customer?->display_id ?: ($customer?->cid ?: ($customer?->customer_code ?: '—')) }}
                            </div>
                        </div>

                        {{-- Cell 3: Phone / HP --}}
                        <div class="bg-surface p-3 space-y-1">
                            <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">Phone / HP</span>
                            <div class="text-xs font-mono text-text-main truncate">
                                @if($ticket->customer_phone)
                                    <a href="https://wa.me/{{ $ticket->customer_phone }}" target="_blank" rel="noopener" class="text-emerald-600 dark:text-emerald-400 hover:underline">
                                        {{ $ticket->customer_phone }}
                                    </a>
                                @else
                                    <span>—</span>
                                @endif
                            </div>
                        </div>

                        {{-- Cell 4: Active Package --}}
                        <div class="bg-surface p-3 space-y-1">
                            <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">Active Package</span>
                            <div class="text-xs font-medium text-text-main truncate">{{ $ticket->customer_package ?: '—' }}</div>
                        </div>

                        {{-- Cell 5: Site Address (2 cols) --}}
                        <div class="bg-surface p-3 space-y-1 md:col-span-2">
                            <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">Site Address</span>
                            <div class="text-xs text-text-main line-clamp-2">{{ $ticket->customer_address ?: '—' }}</div>
                        </div>

                        {{-- Cell 6: POP / Cabang --}}
                        <div class="bg-surface p-3 space-y-1">
                            <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">POP / Cabang</span>
                            <div class="text-xs font-semibold text-text-main">{{ $ticket->pop->name ?? '—' }}</div>
                        </div>

                        {{-- Cell 7: ODP Port --}}
                        <div class="bg-surface p-3 space-y-1">
                            <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">ODP Port</span>
                            <div class="text-xs font-mono font-medium text-text-main">{{ $ticket->customer_odp ?: '—' }}</div>
                        </div>

                        {{-- Cell 8: Perangkat Pelanggan --}}
                        <div class="bg-surface p-3 space-y-1 md:col-span-2">
                            <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">Perangkat Pelanggan (ONT/Router)</span>
                            <div class="text-xs font-mono text-text-main">{{ $ticket->customer_device ?: '—' }}</div>
                        </div>

                        {{-- Cell 9: GPS Coordinates --}}
                        <div class="bg-surface p-3 space-y-1 md:col-span-2">
                            <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">GPS Coordinates</span>
                            <div class="text-xs font-mono text-sky-600 dark:text-sky-400 flex items-center gap-1">
                                <svg class="h-3.5 w-3.5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                @if($koordinat)
                                    <a href="{{ $ticket->customerMapsUrl() }}" target="_blank" rel="noopener"
                                       class="hover:underline font-bold">{{ $koordinat }}</a>
                                @else
                                    <span>—</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Detail Keluhan & Catatan Teknis --}}
    <div class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs space-y-4">
        <div class="px-6 py-3.5 border-b border-border bg-slate-50/50 dark:bg-slate-900/40 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="w-1.5 h-4 {{ $isBatch ? 'bg-violet-600' : 'bg-sky-600' }} rounded-full"></span>
                <h2 class="text-xs font-bold uppercase tracking-wider text-text-main">{{ $isBatch ? 'ISSUE & NOC MASS INCIDENT ASSESSMENT' : 'ISSUE & NOC TECHNICAL ASSESSMENT' }}</h2>
            </div>
            <span class="text-[10px] font-mono text-text-muted uppercase tracking-wider">Deskripsi &amp; Analisa Teknis</span>
        </div>

        <div class="p-6 space-y-5">
            {{-- Detail Keluhan --}}
            <div class="space-y-1.5">
                <span class="block text-[11px] font-bold text-text-muted uppercase tracking-wider">{{ $isBatch ? 'Detail Dampak Gangguan (Mass Outage Description)' : 'Detail Keluhan (Customer Complaint)' }}</span>
                <div class="p-4 bg-background border border-border rounded-xl text-xs text-text-main leading-relaxed whitespace-pre-line">
                    {{ $ticket->detail_keluhan }}
                </div>
            </div>

            {{-- Catatan Teknis (NOC Monospace Box) --}}
            <div class="space-y-1.5">
                <span class="block text-[11px] font-bold text-text-muted uppercase tracking-wider">Catatan Teknis &amp; Analisa Jaringan (NOC Engineering Notes)</span>
                <div class="p-4 bg-slate-900/5 dark:bg-slate-900/40 border border-border rounded-xl text-xs font-mono text-text-main italic leading-relaxed whitespace-pre-line">
                    {{ $ticket->catatan_teknis ?: 'Tidak ada catatan teknis awal.' }}
                </div>
            </div>
        </div>
    </div>

    {{-- Dual-Column History Timelines --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Riwayat Ticketing --}}
        <div class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs">
            <div class="px-6 py-3.5 border-b border-border bg-slate-50/50 dark:bg-slate-900/40 flex items-center justify-between">
                <div>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-text-main">Riwayat Ticketing (Service Desk)</h2>
                    <p class="text-[10px] text-text-muted mt-0.5">Jejak aktivitas penanganan tiket</p>
                </div>
                <span class="text-[10px] font-mono text-text-muted">{{ $ticket->histories->count() }} log</span>
            </div>

            <ul class="divide-y divide-border">
                @forelse($ticket->histories as $history)
                    <li class="p-4 hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-colors">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[10px] font-bold font-mono px-2 py-0.5 rounded border {{ $history->action->badgeClasses() }}">
                                {{ $history->action->label() }}
                            </span>
                            <span class="text-[10px] font-mono text-text-muted">
                                {{ \App\Support\IndonesianDate::dateTime($history->happened_at) }}
                            </span>
                        </div>

                        <p class="text-xs font-semibold text-text-secondary mt-2">
                            oleh <span class="text-text-main font-bold">{{ $history->actor->name ?? 'Sistem' }}</span>
                        </p>

                        @if($history->reason)
                            <p class="text-xs text-text-muted mt-1.5 bg-background border border-border rounded-lg p-2.5 font-mono">
                                {{ $history->reason }}
                            </p>
                        @endif
                    </li>
                @empty
                    <li class="p-6 text-center text-xs text-text-muted font-mono">Belum ada riwayat ticket.</li>
                @endforelse
            </ul>
        </div>

        {{-- Riwayat Task FOP --}}
        <div class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs">
            <div class="px-6 py-3.5 border-b border-border bg-slate-50/50 dark:bg-slate-900/40 flex items-center justify-between">
                <div>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-text-main">Riwayat Task FOP (Teknisi Lapangan)</h2>
                    <p class="text-[10px] text-text-muted mt-0.5">Jejak operasional pengerjaan di lapangan</p>
                </div>
                <span class="text-[10px] font-mono text-text-muted">{{ count(optional($ticket->fopTask)->statusHistories ?? []) }} log</span>
            </div>

            <ul class="divide-y divide-border">
                @forelse(optional($ticket->fopTask)->statusHistories ?? [] as $history)
                    <li class="p-4 hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-colors">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[10px] font-bold font-mono px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 border border-border text-text-main">
                                {{ $history->label() }}
                            </span>
                            <span class="text-[10px] font-mono text-text-muted">
                                {{ \App\Support\IndonesianDate::dateTime($history->changed_at) }}
                            </span>
                        </div>

                        <p class="text-xs font-semibold text-text-secondary mt-2">
                            oleh <span class="text-text-main font-bold">{{ $history->changedByUser->name ?? 'Sistem' }}</span>
                        </p>
                    </li>
                @empty
                    <li class="p-6 text-center text-xs text-text-muted font-mono">
                        {{ $ticket->fopTask ? 'Belum ada perubahan status FOP.' : 'Task FOP tidak aktif.' }}
                    </li>
                @endforelse
            </ul>
        </div>
    </div>

    {{-- Lampiran Panel --}}
    @if($ticket->attachments->isNotEmpty())
    <div class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs">
        <div class="px-6 py-3.5 border-b border-border bg-slate-50/50 dark:bg-slate-900/40 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="w-1.5 h-4 bg-sky-600 rounded-full"></span>
                <h2 class="text-xs font-bold uppercase tracking-wider text-text-main">EVIDENCE &amp; ATTACHMENTS ({{ $ticket->attachments->count() }})</h2>
            </div>
        </div>

        <ul class="divide-y divide-border">
            @foreach($ticket->attachments as $attachment)
                <li class="flex items-center justify-between p-4 hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-colors">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="h-9 w-9 shrink-0 rounded-lg bg-slate-100 dark:bg-slate-800 border border-border flex items-center justify-center text-text-muted">
                            @if($attachment->isImage())
                                <svg class="h-4 w-4 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            @else
                                <svg class="h-4 w-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                            @endif
                        </div>

                        <div class="min-w-0">
                            <p class="text-xs font-bold text-text-main truncate">{{ $attachment->original_name }}</p>
                            <p class="text-[10px] font-mono text-text-muted mt-0.5">
                                {{ $attachment->humanSize() }} • {{ $attachment->uploader->name ?? 'System' }}
                            </p>
                        </div>
                    </div>

                    <a href="{{ route('tickets.attachments.download', $attachment) }}"
                       class="shrink-0 inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-sky-100 dark:hover:bg-slate-800 dark:hover:bg-slate-700 text-sky-600 dark:text-sky-400 text-xs font-bold transition-colors">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Unduh
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- Action Confirmation Dialog Modal --}}
    @include('tickets.partials.action-dialog')
</div>
@endsection

@push('scripts')
<script>
    /**
     * Alpine Component for Impacted Batch Members Roster Management
     */
    function batchRosterManager(config) {
        return {
            ticketId: config.ticketId,
            storeUrl: config.storeUrl,
            lookupUrl: config.lookupUrl,
            members: config.members || [],
            searchQuery: '',
            
            // Add Member Modal State
            addModal: {
                open: false,
                submitting: false,
                cidQuery: '',
                customerName: '',
                phone: '',
                selected: null,
                results: [],
                error: null,
            },

            // Toast Feedback State
            toast: {
                show: false,
                message: '',
                timer: null,
            },

            get filteredMembers() {
                const q = this.searchQuery.trim().toLowerCase();
                if (!q) return this.members;

                return this.members.filter(m => {
                    return (m.name && m.name.toLowerCase().includes(q)) ||
                           (m.cid && m.cid.toLowerCase().includes(q)) ||
                           (m.phone && m.phone.includes(q)) ||
                           (m.address && m.address.toLowerCase().includes(q)) ||
                           (m.package && m.package.toLowerCase().includes(q)) ||
                           (m.odp && m.odp.toLowerCase().includes(q));
                });
            },

            getInitials(name) {
                if (!name) return '??';
                const parts = name.trim().split(/\s+/);
                if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
                return (parts[0][0] + parts[1][0]).toUpperCase();
            },

            showToast(msg) {
                this.toast.message = msg;
                this.toast.show = true;
                if (this.toast.timer) clearTimeout(this.toast.timer);
                this.toast.timer = setTimeout(() => {
                    this.toast.show = false;
                }, 3000);
            },

            copyText(text, successMsg) {
                if (!text || text === '—') return;
                navigator.clipboard.writeText(text).then(() => {
                    this.showToast(successMsg || 'Disalin ke clipboard!');
                }).catch(() => {
                    this.showToast('Gagal menyalin');
                });
            },

            copyAllCids() {
                const cids = this.members.map(m => m.cid).filter(c => c && c !== '—');
                if (!cids.length) {
                    this.showToast('Tidak ada CID yang tersedia');
                    return;
                }
                this.copyText(cids.join(', '), `${cids.length} CID disalin ke clipboard!`);
            },

            copyAllPhones() {
                const phones = this.members.map(m => m.phone).filter(p => p && p.trim() !== '');
                if (!phones.length) {
                    this.showToast('Tidak ada nomor HP yang tersedia');
                    return;
                }
                this.copyText(phones.join(', '), `${phones.length} Nomor HP disalin untuk broadcast!`);
            },

            openAddModal() {
                this.addModal = {
                    open: true,
                    submitting: false,
                    cidQuery: '',
                    customerName: '',
                    phone: '',
                    selected: null,
                    results: [],
                    error: null,
                };
            },

            closeAddModal() {
                this.addModal.open = false;
            },

            resetSelectedCustomer() {
                this.addModal.selected = null;
                this.addModal.cidQuery = '';
                this.addModal.customerName = '';
                this.addModal.phone = '';
            },

            async searchCustomer() {
                const q = this.addModal.cidQuery.trim();
                if (q.length < 2) {
                    this.addModal.results = [];
                    return;
                }

                try {
                    const res = await fetch(`${this.lookupUrl}?query=${encodeURIComponent(q)}`, {
                        headers: { 'Accept': 'application/json' },
                    });
                    if (res.ok) {
                        this.addModal.results = await res.json();
                    }
                } catch (e) {
                    console.error(e);
                }
            },

            pickCustomer(cust) {
                this.addModal.selected = cust;
                this.addModal.cidQuery = `${cust.cid} — ${cust.nama}`;
                this.addModal.customerName = cust.nama || '';
                this.addModal.phone = cust.telepon || '';
                this.addModal.results = [];
            },

            async submitBatchMember() {
                if (!this.addModal.customerName.trim()) {
                    this.addModal.error = 'Nama pelanggan wajib diisi.';
                    return;
                }

                this.addModal.submitting = true;
                this.addModal.error = null;

                const payload = {
                    customer_id: this.addModal.selected ? this.addModal.selected.id : null,
                    customer_name: this.addModal.customerName.trim(),
                    phone: this.addModal.phone.trim() || null,
                };

                try {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
                    const res = await fetch(this.storeUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify(payload),
                    });

                    const data = await res.json();

                    if (!res.ok) {
                        this.addModal.error = data.message || (data.errors ? Object.values(data.errors).flat().join(', ') : 'Gagal menambahkan anggota.');
                        this.addModal.submitting = false;
                        return;
                    }

                    // Append new member to list
                    const newMember = {
                        id: data.id,
                        cid: data.cid || (this.addModal.selected ? this.addModal.selected.cid : '—'),
                        name: data.customer_name || this.addModal.customerName,
                        phone: data.phone || this.addModal.phone || '',
                        package: this.addModal.selected?.paket || '—',
                        address: this.addModal.selected?.alamat || '—',
                        odp: this.addModal.selected?.odp || '—',
                        added_by: '{{ auth()->user()->name }}',
                        created_at: 'Baru saja',
                    };

                    this.members.unshift(newMember);
                    this.closeAddModal();
                    this.showToast('Pelanggan berhasil ditambahkan ke insiden massal!');
                } catch (e) {
                    this.addModal.error = 'Terjadi kesalahan jaringan saat menyimpan.';
                } finally {
                    this.addModal.submitting = false;
                }
            },
        };
    }

    /**
     * Action Confirmation Handler
     */
    function confirmTicketDetailAction(url, target, title, label, required, confirmText) {
        window.confirmTicketAction({
            title,
            message: confirmText,
            label,
            required,
            confirmText: required ? 'Ya, Batalkan' : 'Ya, Lanjutkan',
            confirmType: required ? 'danger' : 'primary',
            icon: required ? 'error' : 'warning',
            onConfirm: (reason) => {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = url;
                form.classList.add('no-confirm');

                const field = (name, value) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = value;
                    form.appendChild(input);
                };

                field('_token', document.querySelector('meta[name="csrf-token"]').content);
                field('reason', reason);
                if (target) {
                    field('target', target);
                }

                document.body.appendChild(form);
                form.submit();
            },
        });
    }
</script>
@endpush
