@extends('layouts.app')

@section('title', $task->task_number . ' — Task Detail')

@section('content')
<div class="max-w-[1180px] mx-auto space-y-4 select-none sm:select-text">
<!-- <div class="max-w-[1180px] mx-auto px-4 sm:px-6 py-4 space-y-4 select-none sm:select-text"> -->

    {{-- ══ Breadcrumb ═══════════════════════════════════════════════ --}}
    <nav class="flex items-center gap-1.5 text-xs text-text-muted">
        @can('viewAll', \App\Models\Task::class)
        <a href="{{ auth()->user()->hasPermission('task.view.own') ? route('tasks.own') : route('fop.dashboard') }}" class="hover:text-primary transition-colors font-ui">Task</a>
        @else
        <a href="{{ route('tasks.own') }}" class="hover:text-primary transition-colors font-ui">Task Saya</a>
        @endcan
        <svg class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
        </svg>
        <span class="font-mono text-text-main">{{ $task->task_number }}</span>
    </nav>

    {{-- ══ Page Header ══════════════════════════════════════════════ --}}
    <div class="flex flex-col gap-3.5 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap mb-1.5">
                <a href="{{ route('tasks.own') }}"
                   class="h-10 w-10 flex items-center justify-center rounded-md border border-border bg-surface hover:bg-surface-muted text-text-secondary hover:text-text-main transition-all active:scale-95 shadow-xs cursor-pointer"
                   title="Kembali ke Task Saya">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
                {{-- Status badge --}}
                @php
                    $statusStyle = match($task->status->value) {
                        'terjadwal'  => 'background:var(--color-info-bg); color:var(--color-info); border-color:var(--color-info-border)',
                        'in_progress'=> 'background:var(--color-warning-bg); color:var(--color-warning); border-color:var(--color-warning-border)',
                        'selesai'    => 'background:var(--color-success-bg); color:var(--color-success); border-color:var(--color-success-border)',
                        'dibatalkan' => 'background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border)',
                        default      => 'background:var(--color-surface-muted); color:var(--color-text-muted); border-color:var(--color-border)',
                    };
                @endphp
                <span class="text-[10px] font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-md border font-ui" style="{{ $statusStyle }}">
                    {{ $task->status->label() }}
                </span>
                <span class="text-[10px] font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-md border font-ui {{ $task->task_type->cardClasses() }}">
                    {{ $task->task_type->label() }}
                </span>
                <span class="font-mono text-xs text-text-muted font-semibold shrink-0">{{ $task->task_number }}</span>
                @if($task->isOverSla())
                <span class="text-[10px] font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-md border font-ui"
                      style="background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border)">
                    Melewati SLA
                </span>
                @endif
            </div>
            <h1 class="text-base sm:text-xl font-bold text-text-main font-ui tracking-tight leading-snug">{{ $task->title }}</h1>
        </div>

        @php
            $lat = $task->customer?->customerAddress?->latitude ?? $task->pop?->latitude;
            $lng = $task->customer?->customerAddress?->longitude ?? $task->pop?->longitude;
        @endphp
        {{-- Header action buttons - grid on mobile, flex on desktop --}}
        <div class="grid grid-cols-2 sm:flex sm:items-center gap-2 shrink-0 w-full sm:w-auto">
            @if($lat && $lng)
            <a href="https://www.google.com/maps/search/?api=1&query={{ $lat }},{{ $lng }}" target="_blank"
               class="inline-flex items-center justify-center gap-1.5 text-xs font-bold px-3 py-2.5 border border-border rounded-xl bg-surface hover:bg-surface-muted text-primary hover:text-primary-hover transition-all shadow-sm font-ui cursor-pointer active:scale-[0.98]">
                <svg class="h-4.5 w-4.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span>Lokasi Maps</span>
            </a>
            @endif
            @can('edit', $task)
            <a href="{{ route('tasks.edit', $task) }}"
               class="inline-flex items-center justify-center gap-1.5 text-xs font-bold px-3 py-2.5 border border-border rounded-xl bg-surface hover:bg-surface-muted text-text-secondary hover:text-text-main transition-all shadow-sm font-ui cursor-pointer active:scale-[0.98]">
                <svg class="h-4.5 w-4.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                <span>Edit</span>
            </a>
            @endcan
            @can('cancel', $task)
            <button x-data @click="$dispatch('open-modal', 'cancel-task')"
                    class="inline-flex items-center justify-center gap-1.5 text-xs font-bold px-3 py-2.5 border rounded-xl transition-all shadow-sm font-ui cursor-pointer active:scale-[0.98] col-span-2 sm:col-span-1"
                    style="border-color:var(--color-error-border); color:var(--color-error); background:var(--color-error-bg)">
                <span>Batalkan Task</span>
            </button>
            @endcan
        </div>
    </div>

    {{-- ══ Primary Detail Panel (Single Unified Card Budget: 1) ═══════════════ --}}
    @php
        $topActualMin = $task->actualDurationMinutes();
        $topDuration = $topActualMin !== null
            ? (intdiv($topActualMin, 60) > 0
                ? intdiv($topActualMin, 60).'j '.($topActualMin % 60).'m'
                : $topActualMin.'m')
            : null;
    @endphp
    <div class="bg-surface border border-border rounded-2xl overflow-hidden shadow-xs">
        
        {{-- Section A: Metric Strip (Separated by Dividers) --}}
        <div class="grid grid-cols-2 sm:grid-cols-5 divide-y sm:divide-y-0 sm:divide-x divide-border border-b border-border bg-slate-50/50 dark:bg-slate-800/10">
            {{-- Metric 1: Tipe Task --}}
            <div class="p-4 flex flex-col justify-between min-w-0">
                <span class="text-[9px] font-bold uppercase tracking-wider text-text-muted font-ui select-none">Tipe Task</span>
                <span class="text-xs font-bold text-text-main mt-1.5 font-ui truncate">{{ $task->task_type->label() }}</span>
            </div>
            {{-- Metric 2: Jadwal --}}
            <div class="p-4 flex flex-col justify-between min-w-0">
                <span class="text-[9px] font-bold uppercase tracking-wider text-text-muted font-ui select-none">Jadwal</span>
                <div class="mt-1.5">
                    <span class="text-xs font-bold font-mono text-text-main block leading-tight">{{ $task->scheduled_at?->format('H:i') ?? '—' }}</span>
                    <span class="text-[9px] text-text-muted font-ui block mt-0.5 leading-none">{{ $task->scheduled_at?->translatedFormat('d M Y') ?? 'Belum dijadwalkan' }}</span>
                </div>
            </div>
            {{-- Metric 3: Target SLA --}}
            <div class="p-4 flex flex-col justify-between min-w-0">
                <span class="text-[9px] font-bold uppercase tracking-wider text-text-muted font-ui select-none">Target SLA</span>
                <div class="mt-1.5">
                    <span class="text-xs font-bold font-mono block leading-tight {{ $task->isOverSla() ? 'text-rose-500' : 'text-text-main' }}">
                        {{ $task->sla_minutes }} Menit
                    </span>
                    @if($topActualMin !== null)
                    <span class="text-[9px] text-text-muted font-mono block mt-0.5 leading-none">Aktual: {{ $topActualMin }} Mnt</span>
                    @endif
                </div>
            </div>
            {{-- Metric 4: POP / Cabang --}}
            <div class="p-4 flex flex-col justify-between min-w-0">
                <span class="text-[9px] font-bold uppercase tracking-wider text-text-muted font-ui select-none">POP / Cabang</span>
                <span class="text-xs font-bold text-text-main mt-1.5 font-ui truncate" title="{{ $task->pop?->name ?? '—' }}">{{ $task->pop?->name ?? '—' }}</span>
            </div>
            {{-- Metric 5: Durasi Aktual --}}
            <div class="p-4 flex flex-col justify-between col-span-2 sm:col-span-1 min-w-0">
                <span class="text-[9px] font-bold uppercase tracking-wider text-text-muted font-ui select-none">Durasi Aktual</span>
                <div class="mt-1.5">
                    <span class="text-xs font-bold font-mono block leading-tight {{ $task->isOverSla() ? 'text-rose-500' : 'text-text-main' }}">
                        {{ $topDuration ?? '—' }}
                    </span>
                    <span class="text-[9px] text-text-muted font-ui block mt-0.5 leading-none">{{ $task->started_at ? 'Berjalan' : 'Belum dimulai' }}</span>
                </div>
            </div>
        </div>

        {{-- Section B: Columns Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-12 divide-y md:divide-y-0 md:divide-x divide-border">
            
            {{-- Col Left: Informasi Task --}}
            <div class="md:col-span-7 p-4 sm:p-5 space-y-4">
                <div class="flex items-center gap-2 mb-1 select-none">
                    <svg class="h-4.5 w-4.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-text-muted font-ui">Informasi Pekerjaan</h3>
                </div>
                
                <div class="space-y-0.5 text-xs">
                    {{-- FOP --}}
                    <div class="flex flex-col sm:flex-row sm:items-start py-2.5 border-b border-border gap-1 sm:gap-4">
                        <span class="text-text-muted sm:w-36 shrink-0 font-ui font-medium flex items-center gap-1.5 select-none">
                            <svg class="h-3.5 w-3.5 text-text-disabled shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            FOP Koordinator
                        </span>
                        <span class="text-text-main font-semibold flex-1 font-ui">{{ $task->fop?->name ?? '—' }}</span>
                    </div>

                    {{-- Pelanggan --}}
                    <div class="flex flex-col sm:flex-row sm:items-start py-2.5 border-b border-border gap-1 sm:gap-4">
                        <span class="text-text-muted sm:w-36 shrink-0 font-ui font-medium flex items-center gap-1.5 select-none">
                            <svg class="h-3.5 w-3.5 text-text-disabled shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Pelanggan
                        </span>
                        <div class="text-text-main flex-1 font-ui">
                            @if($task->customer)
                            <div>
                                <a href="{{ route('customers.show', $task->customer) }}" class="hover:underline font-bold text-sky-600 dark:text-sky-400">
                                    {{ $task->customer->full_name }}
                                </a>
                                <span class="font-mono text-xs text-text-muted ml-1 bg-surface-muted dark:bg-slate-800 px-1.5 py-0.5 rounded border border-border font-semibold">{{ $task->customer->display_id }}</span>
                            </div>
                            @if($task->customer->primary_phone)
                            <div class="flex items-center gap-1.5 mt-1 text-[11px] select-text">
                                <span class="text-text-muted font-mono font-medium">{{ $task->customer->primary_phone }}</span>
                                <a href="https://wa.me/{{ preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $task->customer->primary_phone)) }}" target="_blank"
                                   class="text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 inline-flex items-center gap-1 font-bold cursor-pointer bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/50 px-2 py-0.5 rounded-lg transition-colors select-none">
                                    <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.768-.001 1.298.409 2.522 1.189 3.518l-.756 2.766 2.831-.744a5.748 5.748 0 002.504.588h.002c3.18 0 5.767-2.586 5.768-5.766 0-1.541-.6-2.99-1.691-4.08-1.091-1.09-2.539-1.69-4.079-1.648zm0 10.153a4.398 4.398 0 01-2.241-.614l-.16-.095-1.666.438.444-1.624-.105-.167a4.394 4.394 0 01-.67-2.326c.001-2.426 1.975-4.4 4.402-4.4 1.177 0 2.283.458 3.115 1.29a4.382 4.382 0 011.29 3.117c-.001 2.426-1.975-4.4-4.409 4.4z"/></svg>
                                    WhatsApp
                                </a>
                            </div>
                            @endif
                            @else
                            <span class="text-text-muted">—</span>
                            @endif
                        </div>
                    </div>

                    {{-- Issue / Keluhan --}}
                    @if($task->description)
                    <div class="flex flex-col sm:flex-row sm:items-start py-3 border-b border-border gap-1.5 sm:gap-4 select-text">
                        <span class="text-amber-700 dark:text-amber-400 sm:w-36 shrink-0 font-bold font-ui flex items-center gap-1.5 select-none">
                            <svg class="h-3.5 w-3.5 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            Issue / Keluhan
                        </span>
                        <span class="text-text-main font-semibold leading-relaxed bg-amber-50/70 dark:bg-amber-900/10 border border-amber-200/80 dark:border-amber-800/40 rounded-xl p-3 flex-1 font-ui shadow-xs">{{ $task->description }}</span>
                    </div>
                    @endif

                    {{-- NOC Notes --}}
                    @if($task->fopTask?->ticket?->catatan_teknis)
                    <div class="flex flex-col sm:flex-row sm:items-start py-3 border-b border-border gap-1.5 sm:gap-4 select-text">
                        <span class="text-sky-700 dark:text-sky-400 sm:w-36 shrink-0 font-bold font-ui flex items-center gap-1.5 select-none">
                            <svg class="h-3.5 w-3.5 text-sky-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            Catatan Teknis (NOC)
                        </span>
                        <span class="text-text-main font-semibold leading-relaxed bg-sky-50/70 dark:bg-sky-900/10 border border-sky-200/80 dark:border-sky-800/40 rounded-xl p-3 flex-1 font-ui whitespace-pre-line shadow-xs">{{ $task->fopTask->ticket->catatan_teknis }}</span>
                    </div>
                    @endif

                    {{-- FOP Notes --}}
                    @if($task->fopTask?->notes)
                    <div class="flex flex-col sm:flex-row sm:items-start py-3 border-b border-border gap-1.5 sm:gap-4 select-text">
                        <span class="text-text-secondary sm:w-36 shrink-0 font-semibold font-ui flex items-center gap-1.5 select-none">
                            <svg class="h-3.5 w-3.5 text-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2" />
                            </svg>
                            Catatan FOP
                        </span>
                        <span class="text-text-main leading-relaxed bg-surface-muted border border-border rounded-xl p-3 flex-1 font-ui whitespace-pre-line shadow-xs">{{ $task->fopTask->notes }}</span>
                    </div>
                    @endif

                    {{-- Address Location --}}
                    @if($task->customer || $task->pop)
                    <div class="flex flex-col sm:flex-row sm:items-start py-2.5 gap-1 sm:gap-4 select-text">
                        <span class="text-text-muted sm:w-36 shrink-0 font-ui font-medium flex items-center gap-1.5 select-none">
                            <svg class="h-3.5 w-3.5 text-text-disabled shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            </svg>
                            Lokasi &amp; Peta
                        </span>
                        <div class="text-text-secondary leading-relaxed flex-1 font-ui">
                            <div class="font-semibold text-text-main">
                                @if($task->customer)
                                    {{ $task->customer->clean_address }}
                                @else
                                    {{ $task->pop?->address ?? '—' }} ({{ $task->pop?->name }})
                                @endif
                            </div>
                            @if($lat && $lng)
                            <div class="flex items-center gap-2 mt-2 pt-2 border-t border-border/60 text-[11px] flex-wrap select-none">
                                <span class="font-mono text-text-main bg-surface-muted px-2 py-0.5 rounded border border-border">Lat: <strong class="text-sky-600 dark:text-sky-400">{{ $lat }}</strong> | Lng: <strong class="text-sky-600 dark:text-sky-400">{{ $lng }}</strong></span>
                                <a href="https://www.google.com/maps/search/?api=1&query={{ $lat }},{{ $lng }}" target="_blank"
                                   class="inline-flex items-center gap-1 font-bold text-sky-600 dark:text-sky-400 hover:underline cursor-pointer">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    </svg>
                                    Maps →
                                </a>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif

                    {{-- Pending reason --}}
                    @if($task->pending_reason)
                    <div class="flex flex-col sm:flex-row sm:items-start py-2.5 border-t border-border gap-1 sm:gap-4">
                        <span class="text-text-muted sm:w-36 shrink-0 font-ui font-medium flex items-center gap-1.5 select-none">
                            <svg class="h-3.5 w-3.5 text-text-disabled shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            Alasan Pending
                        </span>
                        <span class="font-bold flex-1 text-warning font-ui">{{ $task->pending_reason }}</span>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Col Right: Waktu, Tim, & Logs --}}
            <div class="md:col-span-5 p-4 sm:p-5 space-y-5 bg-slate-50/15 dark:bg-slate-800/5">
                
                {{-- Waktu pengerjaan --}}
                @if($task->status->value === 'selesai' && $task->started_at && $task->completed_at)
                @php
                    $showStartedAt   = $task->started_at;
                    $showCompletedAt = $task->completed_at;
                    $showActualMin   = (int) $showStartedAt->diffInMinutes($showCompletedAt);
                    $showHours       = intdiv($showActualMin, 60);
                    $showRemMins     = $showActualMin % 60;
                    $showDuration    = $showHours > 0 ? "{$showHours}j {$showRemMins}m" : "{$showActualMin}m";
                    $showOverSla     = $showActualMin > $task->sla_minutes;
                    $showTypeLabel   = $task->task_type->value === \App\Enums\TaskType::PEMASANGAN->value ? 'Pemasangan' : 'Survey';
                @endphp
                <div class="space-y-2">
                    <div class="flex items-center justify-between mb-1 select-none">
                        <div class="flex items-center gap-1.5">
                            <svg class="h-4.5 w-4.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-text-muted font-ui">Durasi Kerja {{ $showTypeLabel }}</p>
                        </div>
                        <span class="text-[9px] font-bold px-2 py-0.5 rounded-full border font-mono"
                              style="{{ $showOverSla
                                  ? 'background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border)'
                                  : 'background:var(--color-success-bg); color:var(--color-success); border-color:var(--color-success-border)' }}">
                            {{ $showOverSla ? 'Over SLA' : 'Dalam SLA' }}
                        </span>
                    </div>
                    <div class="bg-surface border border-border rounded-xl p-3 space-y-2 text-xs">
                        <div class="flex items-center justify-between font-ui">
                            <div>
                                <p class="text-[9px] font-bold uppercase tracking-wider text-text-muted mb-0.5 select-none">Mulai</p>
                                <p class="font-mono font-bold text-text-main text-xs">{{ $showStartedAt->format('H:i') }}</p>
                                <p class="text-[9px] text-text-muted select-none">{{ $showStartedAt->translatedFormat('d M Y') }}</p>
                            </div>
                            <svg class="h-4 w-4 text-slate-400 shrink-0 select-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25L21 12m0 0l-3.75 3.75M21 12H3" />
                            </svg>
                            <div class="text-right">
                                <p class="text-[9px] font-bold uppercase tracking-wider text-text-muted mb-0.5 select-none">Selesai</p>
                                <p class="font-mono font-bold text-text-main text-xs">{{ $showCompletedAt->format('H:i') }}</p>
                                <p class="text-[9px] text-text-muted select-none">{{ $showCompletedAt->translatedFormat('d M Y') }}</p>
                            </div>
                        </div>
                        <div class="pt-2 border-t border-border flex items-center justify-between text-xs select-none">
                            <div>
                                <p class="text-[9px] font-bold uppercase tracking-wider text-text-muted font-ui">Durasi Aktual</p>
                                <p class="font-mono font-bold" style="color:{{ $showOverSla ? 'var(--color-error)' : 'var(--color-success)' }}">
                                    {{ $showDuration }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-[9px] font-bold uppercase tracking-wider text-text-muted font-ui">Target SLA</p>
                                <p class="font-mono font-bold text-text-secondary">{{ $task->sla_minutes }} menit</p>
                            </div>
                        </div>
                        @if($task->completedBy)
                        <div class="pt-2 border-t border-border flex items-center gap-1.5 select-none">
                            <svg class="h-3.5 w-3.5 text-text-disabled shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <p class="text-[10px] text-text-muted font-ui">Dilaporkan oleh: <span class="font-bold text-text-main">{{ $task->completedBy->name }}</span></p>
                        </div>
                        @endif
                    </div>
                </div>
                @endif

                {{-- Tim teknisi --}}
                <div class="space-y-2">
                    <div class="flex items-center gap-1.5 mb-1 select-none">
                        <svg class="h-4.5 w-4.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-text-muted font-ui">Tim Anggota Lapangan</p>
                    </div>
                    @if($task->teamMembers->count() > 0)
                    <div class="grid grid-cols-1 gap-2">
                        @foreach($task->teamMembers as $member)
                        <div class="flex items-center gap-2.5 bg-surface-muted border border-border rounded-xl p-2.5 shadow-xs transition-colors hover:border-sky-300 dark:hover:border-sky-800">
                            <div class="h-7 w-7 rounded-full bg-sky-100 dark:bg-sky-950 text-sky-700 dark:text-sky-300 flex items-center justify-center text-xs font-bold shrink-0 border border-sky-200 dark:border-sky-900/60 select-none">
                                {{ strtoupper(substr($member->user?->name ?? '?', 0, 2)) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-bold text-text-main truncate font-ui leading-tight">{{ $member->user?->name ?? 'User dihapus' }}</p>
                                <p class="text-[10px] text-text-muted capitalize font-ui mt-0.5 leading-none select-none">{{ $member->role_in_task }}</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <p class="text-xs text-text-muted font-ui select-none">Belum ada anggota tim.</p>
                    @endif
                </div>

                {{-- Audit log (Riwayat status) --}}
                @if(auth()->user()->hasRole(['owner', 'admin', 'fop']) || $task->isMember(auth()->id()))
                <div class="pt-4 border-t border-border">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-text-muted mb-3 font-ui select-none">Riwayat Perubahan Status</p>
                    {{-- $statusTimeline = audit_logs yang SUDAH disaring
                         (TaskController::show → App\Support\TaskAuditTimeline).
                         Jangan dikembalikan ke $task->auditLogs mentah: Task punya dua
                         lapis pencatat (trait model + log bisnis bernama), jadi satu klik
                         user tampil sebagai dua baris. Barisnya tetap utuh di DB dan tetap
                         tampil penuh di halaman Audit Log. --}}
                    @if($statusTimeline->isNotEmpty())
                    <div class="relative border-l border-border ml-2.5 space-y-3.5">
                        @foreach($statusTimeline as $log)
                        <div class="relative pl-4 select-text">
                            {{-- Timeline indicator dot --}}
                            <div class="absolute -left-1 top-1.5 h-2.5 w-2.5 rounded-full bg-border border-2 border-surface shrink-0 select-none"></div>
                            <div class="mb-0.5 flex items-center justify-between gap-2">
                                <p class="text-xs font-bold text-text-main font-ui leading-tight">
                                    {{ \App\Support\TaskAuditTimeline::label($log) }}
                                </p>
                                <span class="text-[9px] text-text-muted font-mono shrink-0 font-semibold select-none">{{ $log->created_at->format('d M, H:i') }}</span>
                            </div>
                            <p class="text-[10px] text-text-muted font-ui select-none">Oleh: <span class="font-bold text-text-secondary">{{ $log->user?->name ?? 'System' }}</span></p>
                            
                            @if($log->action === 'cancelled' && isset($log->new_values['cancel_reason']))
                            <div class="mt-1 p-1.5 bg-error-bg/30 border border-error-border rounded-lg max-w-full overflow-hidden">
                                <p class="text-[10px] text-error font-semibold font-ui break-words">Alasan: {{ $log->new_values['cancel_reason'] }}</p>
                            </div>
                            @elseif($log->action === 'rejected' && isset($log->new_values['reject_reason']))
                            <div class="mt-1 p-1.5 bg-error-bg/30 border border-error-border rounded-lg max-w-full overflow-hidden">
                                <p class="text-[10px] text-error font-semibold font-ui break-words">Alasan: {{ $log->new_values['reject_reason'] }}</p>
                            </div>
                            {{-- Alasan pending/lapor-nanti ikut ditampilkan: tanpa ini baris
                                 "Ditunda (Pending)" tidak menjelaskan APA yang menghambat,
                                 padahal itu satu-satunya keterangan yang ditinggalkan teknisi. --}}
                            @elseif(in_array($log->action, ['pending', 'report_deferred'], true) && isset($log->new_values['pending_reason']))
                            <div class="mt-1 p-1.5 bg-warning-bg/30 border border-warning-border rounded-lg max-w-full overflow-hidden">
                                <p class="text-[10px] text-warning font-semibold font-ui break-words">Alasan: {{ $log->new_values['pending_reason'] }}</p>
                            </div>
                            @endif
                        </div>
                        @endforeach
                    </div>
                    @else
                    <p class="text-xs text-text-muted font-ui select-none">Belum ada riwayat aktivitas.</p>
                    @endif
                </div>
                @endif

            </div>
        </div>

        {{-- Section C: Briefing & Laporan (Full-Width Segment) --}}
        <div class="p-4 sm:p-5 border-t border-border space-y-6">
            
            {{-- Briefing Detail Teknis --}}
            <div>
                <div class="flex items-center gap-2 mb-3.5 pb-2 border-b border-border select-none">
                    <svg class="h-4.5 w-4.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    </svg>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-text-main font-ui">Briefing &amp; Detail Teknis</h3>
                </div>
                
                @if($task->task_type === \App\Enums\TaskType::SURVEY)
                <div class="space-y-0.5 text-xs select-text">
                    <div class="flex flex-col sm:flex-row sm:items-start py-2 border-b border-border gap-1 sm:gap-4">
                        <span class="text-text-muted sm:w-36 shrink-0 font-ui font-medium select-none">Status SLA Berjalan</span>
                        <div class="text-text-main font-semibold flex-1 font-ui">
                            @if($task->started_at && !$task->completed_at)
                                @php
                                    $elapsed = (int) $task->started_at->diffInMinutes(now());
                                    $remaining = $task->sla_minutes - $elapsed;
                                @endphp
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold font-mono {{ $remaining < 0 ? 'text-error' : 'text-primary' }}">
                                        {{ $elapsed }} menit berjalan
                                    </span>
                                    <span class="text-[10px] text-text-muted font-mono select-none">({{ $remaining >= 0 ? "Sisa {$remaining} mnt" : "Over SLA " . abs($remaining) . " mnt" }})</span>
                                </div>
                            @elseif($task->status->value === 'terjadwal')
                                <span class="text-warning font-semibold">Menunggu teknisi klik Mulai Survey (Target SLA: {{ $task->sla_minutes }} menit)</span>
                            @elseif($task->completed_at)
                                <span class="text-success font-semibold">Selesai dikerjakan dalam {{ $task->actualDurationMinutes() }} menit</span>
                            @else
                                <span class="text-text-muted">{{ $task->status->label() }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="flex flex-col sm:flex-row sm:items-start py-2 border-b border-border gap-1 sm:gap-4">
                        <span class="text-text-muted sm:w-36 shrink-0 font-ui font-medium select-none">Rencana Paket</span>
                        <span class="text-text-main font-bold flex-1 font-ui">
                            {{ $task->customer?->customerService?->internetPackage?->name ?? $task->customer?->customerService?->package_name_snapshot ?? 'Belum dipilih saat pendaftaran' }}
                        </span>
                    </div>
                </div>
                
                @elseif($task->task_type === \App\Enums\TaskType::PEMASANGAN)
                @php
                    $survey = $task->customer?->latestSurvey;
                    $service = $task->customer?->customerService;
                    $device = $task->customer?->customerDevice;
                @endphp
                <div class="space-y-4 select-text">
                    <div>
                        <span class="block text-[9px] font-bold text-text-muted uppercase mb-2 font-ui tracking-wider select-none">Hasil Survey Lapangan</span>
                        @if($survey)
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                            <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs">
                                <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">ODP Tujuan &amp; Port</span>
                                <span class="font-bold font-mono text-text-main text-xs mt-1 block">{{ $survey->nearest_odp ?: '-' }}</span>
                            </div>
                            <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs">
                                <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">Estimasi Dropcore</span>
                                <span class="font-bold font-mono text-text-main text-xs mt-1 block">{{ $survey->cable_estimation_meter ? $survey->cable_estimation_meter . ' Meter' : '-' }}</span>
                            </div>
                            <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs">
                                <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">Kebutuhan Alat</span>
                                <span class="font-bold text-text-main text-xs mt-1 block font-ui">{{ $survey->required_tools ?: 'Standar' }}</span>
                            </div>
                        </div>
                        @if($survey->requested_installation_date)
                        <p class="text-[11px] mt-2.5 font-ui font-bold text-sky-600 dark:text-sky-400">
                            Permintaan instalasi pelanggan: {{ \App\Support\IndonesianDate::date($survey->requested_installation_date) }}
                        </p>
                        @endif
                        @if($survey->survey_note)
                        <p class="text-[11px] text-text-secondary mt-1.5 italic font-ui">"{{ $survey->survey_note }}"</p>
                        @endif
                        @else
                        <p class="text-[11px] text-warning font-semibold font-ui select-none">Data hasil survey sebelumnya belum tercatat di sistem.</p>
                        @endif
                    </div>

                    @php
                        $estimasiMaterial = $task->customer
                            ? \App\Models\TaskMaterial::where('customer_id', $task->customer->id)->estimasi()->orderBy('id')->get()
                            : collect();
                    @endphp
                    @if($estimasiMaterial->isNotEmpty())
                    <div class="pt-3.5 border-t border-border">
                        <span class="block text-[9px] font-bold text-text-muted uppercase mb-2 font-ui tracking-wider select-none">Estimasi Kebutuhan Alat &amp; Material</span>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 text-xs">
                            @foreach($estimasiMaterial as $material)
                            <div class="flex justify-between items-center bg-surface-muted border border-border p-3 rounded-xl shadow-xs">
                                <span class="text-text-secondary font-ui font-semibold">{{ $material->item_name }}</span>
                                <span class="font-mono font-bold text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/60 px-2.5 py-0.5 rounded-lg border border-sky-200 dark:border-sky-900/50">{{ rtrim(rtrim(number_format($material->qty, 2, ',', '.'), '0'), ',') }} {{ $material->unit }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 pt-3.5 border-t border-border text-xs">
                        <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs">
                            <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">Paket Layanan</span>
                            <span class="text-xs font-bold text-sky-600 dark:text-sky-400 block mt-1 font-ui">{{ $service?->internetPackage?->name ?? $service?->package_name_snapshot ?? '-' }}</span>
                            @if($service?->monthly_price)
                            <span class="text-[10px] text-text-muted font-mono block mt-0.5 select-none">Rp {{ number_format($service->monthly_price, 0, ',', '.') }} / bulan</span>
                            @endif
                        </div>
                        <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs">
                            <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">Alokasi ONT / Modem</span>
                            @if($device)
                                <span class="text-xs font-bold text-text-main block mt-1 font-ui">{{ $device->brand }} {{ $device->model }}</span>
                                <span class="text-[10px] font-mono text-text-muted block mt-0.5 font-ui font-semibold">SN: {{ $device->serial_number ?: 'Belum diinput' }}</span>
                            @else
                                <span class="text-xs text-warning font-semibold block mt-1 font-ui">Perangkat ONT akan dicatat saat laporan pemasangan diselesaikan.</span>
                            @endif
                        </div>
                    </div>
                </div>

                @elseif($task->task_type === \App\Enums\TaskType::MAINTENANCE)
                @php
                    $tech = $task->customer?->customerTechnicalDetail;
                    $device = $task->customer?->customerDevice;
                @endphp
                <div class="space-y-4 select-text">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                        <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs font-ui">
                            <span class="block text-[9px] text-text-muted font-bold uppercase tracking-wider mb-1 select-none">ODP &amp; Port Terhubung</span>
                            <span class="font-bold font-mono text-text-main text-xs block">
                                {{ $device?->odp ?? $tech?->odp_number ?: '-' }} 
                                @if($device?->odp_port || $tech?->odp_port)
                                    <span class="text-sky-600 dark:text-sky-400 font-bold">(Port {{ $device?->odp_port ?? $tech?->odp_port }})</span>
                                @endif
                            </span>
                        </div>
                        <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs font-ui">
                            <span class="block text-[9px] text-text-muted font-bold uppercase tracking-wider mb-1 select-none">OLT &amp; Port OLT</span>
                            <span class="font-bold font-mono text-text-main text-xs block">
                                {{ $tech?->olt_number ?: '-' }}
                                @if($tech?->olt_port) <span class="text-sky-600 dark:text-sky-400 font-bold">(Port {{ $tech->olt_port }})</span> @endif
                            </span>
                        </div>
                        <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs font-ui font-mono">
                            <span class="block text-[9px] text-text-muted font-bold uppercase tracking-wider mb-1 select-none">Redaman RX Power</span>
                            <span class="font-bold text-text-main text-xs block">
                                {{ $device?->signal_rx_power ?? $tech?->initial_attenuation ? ($device?->signal_rx_power ?? $tech?->initial_attenuation) . ' dBm' : '-' }}
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs">
                            <span class="block text-[9px] text-text-muted font-bold uppercase tracking-wider font-ui mb-1 select-none">Perangkat Terpasang</span>
                            <span class="font-bold text-text-main text-xs block font-ui">
                                {{ $device?->brand ?? 'Modem' }} {{ $device?->model }}
                            </span>
                            <span class="text-[10px] font-mono text-text-muted mt-0.5 block font-semibold">
                                SN: {{ $device?->serial_number ?? $tech?->router_or_ont_serial ?: '-' }}
                            </span>
                        </div>
                        <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs">
                            <span class="block text-[9px] text-text-muted font-bold uppercase tracking-wider font-ui mb-1 select-none">PPPoE User / IP Address</span>
                            <span class="font-bold font-mono text-text-main text-xs block">{{ $device?->pppoe_username ?: '-' }}</span>
                            <span class="text-[10px] font-mono text-sky-600 dark:text-sky-400 mt-0.5 block font-semibold">IP: {{ $device?->ip_address ?? $tech?->ip_address ?: '-' }}</span>
                        </div>
                    </div>
                </div>

                @elseif($task->task_type === \App\Enums\TaskType::AMBIL_MODEM)
                @php
                    $device = $task->customer?->customerDevice;
                @endphp
                <div class="space-y-4 select-text">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs">
                            <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">Alasan Deaktivasi</span>
                            <span class="text-xs font-bold text-text-main mt-1 block font-ui">{{ $task->description ?: 'Pengambilan Modem' }}</span>
                        </div>
                        <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs">
                            <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">Janji Temu</span>
                            <span class="text-xs font-bold text-text-main font-mono mt-1 block">{{ $task->scheduled_at?->translatedFormat('l, d M Y — H:i') ?: 'Segera' }} WIB</span>
                        </div>
                    </div>

                    <div class="pt-3.5 border-t border-border space-y-2">
                        <span class="block text-[10px] text-text-main font-bold uppercase font-ui tracking-wider select-none">Aset ISP yang Wajib Ditarik</span>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                            <div class="bg-surface-muted border border-border p-3.5 rounded-xl shadow-xs">
                                <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">ONT / Modem</span>
                                <span class="font-bold text-text-main text-xs mt-1 block font-ui">{{ $device?->brand ?: 'Modem ONT' }} {{ $device?->model }}</span>
                                <p class="text-[10px] text-sky-600 dark:text-sky-400 font-mono mt-1 font-bold select-all">SN: {{ $device?->serial_number ?: 'PERIKSA FISIK' }}</p>
                            </div>
                            <div class="bg-surface-muted border border-border p-3.5 rounded-xl shadow-xs">
                                <span class="block text-[9px] text-text-muted font-bold uppercase mb-1.5 font-ui select-none">Kelengkapan Standar</span>
                                <ul class="list-disc list-inside space-y-0.5 text-text-secondary text-[11px] font-ui font-semibold">
                                    <li>Adaptor Power</li>
                                    <li>Kabel Patchcord / LAN</li>
                                    <li>Router / STB Tambahan</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                @elseif($task->task_type === \App\Enums\TaskType::CREQ)
                @php
                    $device = $task->customer?->customerDevice;
                    $tech = $task->customer?->customerTechnicalDetail;
                @endphp
                <div class="space-y-3.5 select-text">
                    <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs text-xs">
                        <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">Rincian Request</span>
                        <span class="text-xs font-bold text-text-main block mt-1 font-ui">{{ $task->description ?: $task->title }}</span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs">
                            <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">SSID WiFi Eksisting</span>
                            <span class="text-xs font-bold font-mono text-text-main block mt-1">{{ $device?->wifi_ssid ?? $tech?->ssid ?: 'Standard / Default' }}</span>
                        </div>
                        <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs">
                            <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">IP Gateway Akses</span>
                            <span class="text-xs font-bold font-mono text-text-main block mt-1">{{ $device?->ip_address ?? $tech?->ip_address ?: '192.168.1.1' }}</span>
                        </div>
                    </div>
                </div>

                @elseif(in_array($task->task_type, [\App\Enums\TaskType::OREQ, \App\Enums\TaskType::INFR]))
                <div class="space-y-3 text-xs select-text">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs">
                            <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">POP Pembina</span>
                            <span class="text-xs font-bold text-text-main mt-1 block font-ui">{{ $task->pop?->name ?? 'Pusat' }}</span>
                        </div>
                        <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs">
                            <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">Target / Lokasi</span>
                            <span class="text-xs text-text-main font-semibold mt-1 block font-ui">{{ $task->pop?->address ?: 'Infrastruktur POP' }}</span>
                        </div>
                    </div>
                    <div class="bg-surface-muted border border-border rounded-xl p-3.5 shadow-xs">
                        <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">Instruksi Pekerjaan</span>
                        <span class="text-xs font-semibold text-text-main leading-relaxed mt-1 block font-ui">{{ $task->description ?: $task->title }}</span>
                    </div>
                </div>
                @endif
            </div>

            {{-- Alat Kerja Yang Perlu Dibawa --}}
            @php
                $workToolRows = app(\App\Services\TaskWorkToolService::class)->displayRowsForTask($task);
            @endphp
            @if($workToolRows->isNotEmpty())
            <div class="pt-5 border-t border-border">
                <div class="flex items-center gap-2 mb-3.5 select-none">
                    <svg class="h-4.5 w-4.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z" />
                    </svg>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-text-main font-ui">Alat Kerja Wajib</h3>
                </div>
                <div class="flex flex-wrap gap-2 select-text">
                    @foreach($workToolRows as $row)
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-3.5 py-1.5 rounded-xl border border-border bg-surface-muted text-text-main font-ui shadow-xs hover:border-sky-400 transition-colors">
                        <svg class="h-3.5 w-3.5 text-emerald-500 shrink-0 select-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>{{ $row->tool_name }}</span>@if($row->note)<span class="font-normal text-text-muted text-[10px]"> · {{ $row->note }}</span>@endif
                    </span>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Laporan Hasil / Result Details --}}
            @if($task->task_type->value === \App\Enums\TaskType::SURVEY->value)
            @php
                $surveyReport = $task->customer?->latestSurvey;
                $surveyFopTask = app(\App\Services\TaskMaterialService::class)->resolveTaskFor($task->customer, \App\Enums\TaskType::SURVEY);
                $surveyMaterials = $surveyFopTask
                    ? $surveyFopTask->materials()->estimasi()->orderBy('id')->get()
                    : collect();
            @endphp
            @if($surveyReport || $surveyMaterials->isNotEmpty())
            <div class="pt-5 border-t border-border space-y-4 select-text">
                <div class="flex items-center gap-2 mb-1 select-none">
                    <svg class="h-4.5 w-4.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-text-main font-ui">Laporan Result Survey Lapangan</h3>
                </div>

                @if($surveyReport)
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                    <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs">
                        <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">Status Survey</span>
                        <span class="text-xs font-bold block mt-1 font-ui {{ $surveyReport->survey_status === 'completed' ? 'text-emerald-600 dark:text-emerald-400' : ($surveyReport->survey_status === 'failed' ? 'text-rose-600 dark:text-rose-400' : 'text-amber-600 dark:text-amber-400') }}">
                            {{ $surveyReport->survey_status === 'completed' ? 'LAYAK PASANG (Selesai)' : ($surveyReport->survey_status === 'failed' ? 'TIDAK LAYAK PASANG (Gagal)' : 'Menunggu / In Progress') }}
                        </span>
                    </div>
                    <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs">
                        <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">Estimasi Kabel Dropcore</span>
                        <span class="text-xs font-bold font-mono text-sky-600 dark:text-sky-400 block mt-1">{{ $surveyReport->cable_estimation_meter ? $surveyReport->cable_estimation_meter.' Meter' : '-' }}</span>
                    </div>
                    <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs">
                        <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">ODP Terdekat</span>
                        <span class="text-xs font-bold font-mono text-text-main block mt-1">{{ $surveyReport->nearest_odp ?: '-' }}</span>
                    </div>
                </div>

                @if($surveyReport->survey_note)
                <div class="bg-surface-muted border border-border rounded-xl p-4 shadow-xs min-w-0 max-w-full overflow-hidden">
                    <div class="flex items-center gap-1.5 mb-2 select-none">
                        <svg class="h-3.5 w-3.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        <span class="text-[10px] text-text-muted font-bold uppercase tracking-wider font-ui">Catatan Lapangan &amp; Kendala</span>
                    </div>
                    <p class="text-xs text-text-main leading-relaxed font-ui whitespace-pre-line break-words [word-break:break-word] min-w-0 font-medium">{{ $surveyReport->survey_note }}</p>
                </div>
                @endif
                @endif

                @if($surveyMaterials->isNotEmpty())
                <div>
                    <span class="block text-[10px] text-text-muted font-bold uppercase tracking-wider font-ui mb-2 select-none">Estimasi Material Dibutuhkan</span>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        @foreach($surveyMaterials as $material)
                        <div class="flex justify-between items-center bg-surface-muted border border-border p-3 rounded-xl shadow-xs">
                            <span class="text-text-secondary font-ui font-semibold">{{ $material->item_name }}@if($material->note)<span class="text-text-muted text-[10px]"> · {{ $material->note }}</span>@endif</span>
                            <span class="font-mono font-bold text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/60 px-2.5 py-0.5 rounded border border-sky-200 dark:border-sky-900/50">{{ rtrim(rtrim(number_format($material->qty, 2, ',', '.'), '0'), ',') }} {{ $material->unit }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                @if($surveyReport?->survey_photo || $surveyReport?->house_photo)
                <div x-data class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs select-none">
                    <div class="flex items-center gap-1.5 px-3.5 py-2.5 border-b border-border bg-surface-muted">
                        <svg class="h-3.5 w-3.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                        </svg>
                        <span class="text-[10px] text-text-muted font-bold uppercase tracking-wider font-ui">Foto Hasil Survey (ODP &amp; Rumah)</span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5">
                        @if($surveyReport->survey_photo)
                        <button type="button" @click="$dispatch('open-image-preview', { url: '{{ asset('storage/'.$surveyReport->survey_photo) }}', label: 'Foto ODP Survey' })" class="group relative block w-full rounded-lg overflow-hidden aspect-square bg-slate-100 dark:bg-slate-800 border border-border hover:border-sky-500 transition-all cursor-pointer">
                            <img src="{{ asset('storage/'.$surveyReport->survey_photo) }}" alt="Foto ODP Survey" class="h-full w-full object-cover">
                            <div class="absolute inset-0 flex items-end justify-center p-2 bg-slate-900/0 group-hover:bg-slate-900/60 backdrop-blur-0 group-hover:backdrop-blur-[2px] opacity-0 group-hover:opacity-100 transition-all duration-300">
                                <span class="text-white text-[10px] font-bold text-center font-ui">Foto ODP Survey</span>
                            </div>
                        </button>
                        @endif
                        @if($surveyReport->house_photo)
                        <button type="button" @click="$dispatch('open-image-preview', { url: '{{ asset('storage/'.$surveyReport->house_photo) }}', label: 'Foto Rumah Pelanggan' })" class="group relative block w-full rounded-lg overflow-hidden aspect-square bg-slate-100 dark:bg-slate-800 border border-border hover:border-sky-500 transition-all cursor-pointer">
                            <img src="{{ asset('storage/'.$surveyReport->house_photo) }}" alt="Foto Rumah Customer" class="h-full w-full object-cover">
                            <div class="absolute inset-0 flex items-end justify-center p-2 bg-slate-900/0 group-hover:bg-slate-900/60 backdrop-blur-0 group-hover:backdrop-blur-[2px] opacity-0 group-hover:opacity-100 transition-all duration-300">
                                <span class="text-white text-[10px] font-bold text-center font-ui">Foto Rumah Pelanggan</span>
                            </div>
                        </button>
                        @endif
                    </div>
                </div>
                @endif
            </div>
            @endif

            @elseif($task->task_type->value === \App\Enums\TaskType::PEMASANGAN->value)
            @php
                $installReport = $task->customer?->installations()->latest()->first();
                $installFopTask = app(\App\Services\TaskMaterialService::class)->resolveTaskFor($task->customer, \App\Enums\TaskType::PEMASANGAN);
                $installMaterials = $installFopTask
                    ? $installFopTask->materials()->terpakai()->orderBy('id')->get()
                    : collect();
            @endphp
            @if($installReport || $installMaterials->isNotEmpty())
            <div class="pt-5 border-t border-border space-y-4 select-text">
                <div class="flex items-center gap-2 mb-1 select-none">
                    <svg class="h-4.5 w-4.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-text-main font-ui">Laporan Hasil Pemasangan (PSB)</h3>
                </div>

                @if($installReport)
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs">
                        <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">Status Pemasangan</span>
                        <span class="text-xs font-bold block mt-1 font-ui {{ $installReport->installation_status === 'completed' ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                            {{ $installReport->installation_status === 'completed' ? 'PEMASANGAN SELESAI' : strtoupper($installReport->installation_status) }}
                        </span>
                    </div>
                    <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs">
                        <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">Waktu Selesai Pemasangan</span>
                        <span class="text-xs font-bold font-mono text-text-main block mt-1">{{ $installReport->completed_at?->translatedFormat('d M Y — H:i') ?: '-' }} WIB</span>
                    </div>
                </div>

                @if($installReport->installation_note || $installReport->notes)
                <div class="bg-surface-muted border border-border rounded-xl p-4 shadow-xs min-w-0 max-w-full overflow-hidden">
                    <div class="flex items-center gap-1.5 mb-2 select-none">
                        <svg class="h-3.5 w-3.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        <span class="text-[10px] text-text-muted font-bold uppercase tracking-wider font-ui">Catatan Pemasangan Lapangan</span>
                    </div>
                    <p class="text-xs text-text-main leading-relaxed font-ui whitespace-pre-line break-words [word-break:break-word] min-w-0 font-medium">{{ $installReport->installation_note ?: $installReport->notes }}</p>
                </div>
                @endif
                @endif

                @if($installMaterials->isNotEmpty())
                <div>
                    <span class="block text-[10px] text-text-muted font-bold uppercase tracking-wider font-ui mb-2 select-none">Material Pemasangan Terpakai</span>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        @foreach($installMaterials as $material)
                        <div class="flex justify-between items-center bg-surface-muted border border-border p-3 rounded-xl shadow-xs">
                            <span class="text-text-secondary font-ui font-semibold">{{ $material->item_name }}@if($material->note)<span class="text-text-muted text-[10px]"> · {{ $material->note }}</span>@endif</span>
                            <span class="font-mono font-bold text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/60 px-2.5 py-0.5 rounded border border-sky-200 dark:border-sky-900/50">{{ rtrim(rtrim(number_format($material->qty, 2, ',', '.'), '0'), ',') }} {{ $material->unit }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                @if($installReport?->installation_photo || $installReport?->contract_photo || $installReport?->signature_photo)
                <div x-data class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs select-none">
                    <div class="flex items-center gap-1.5 px-3.5 py-2.5 border-b border-border bg-surface-muted">
                        <svg class="h-3.5 w-3.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                        </svg>
                        <span class="text-[10px] text-text-muted font-bold uppercase tracking-wider font-ui">Foto Hasil Pemasangan &amp; Berita Acara</span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5">
                        @if($installReport->installation_photo)
                        <button type="button" @click="$dispatch('open-image-preview', { url: '{{ asset('storage/'.$installReport->installation_photo) }}', label: 'Foto Bukti Pemasangan' })" class="group relative block w-full rounded-lg overflow-hidden aspect-square bg-slate-100 dark:bg-slate-800 border border-border hover:border-sky-500 transition-all cursor-pointer">
                            <img src="{{ asset('storage/'.$installReport->installation_photo) }}" alt="Foto Pemasangan" class="h-full w-full object-cover">
                            <div class="absolute inset-0 flex items-end justify-center p-2 bg-slate-900/0 group-hover:bg-slate-900/60 backdrop-blur-0 group-hover:backdrop-blur-[2px] opacity-0 group-hover:opacity-100 transition-all duration-300">
                                <span class="text-white text-[10px] font-bold text-center font-ui">Foto Pemasangan</span>
                            </div>
                        </button>
                        @endif
                        @if($installReport->contract_photo)
                        <button type="button" @click="$dispatch('open-image-preview', { url: '{{ asset('storage/'.$installReport->contract_photo) }}', label: 'Foto Kontrak / BA' })" class="group relative block w-full rounded-lg overflow-hidden aspect-square bg-slate-100 dark:bg-slate-800 border border-border hover:border-sky-500 transition-all cursor-pointer">
                            <img src="{{ asset('storage/'.$installReport->contract_photo) }}" alt="Foto Kontrak/BA" class="h-full w-full object-cover">
                            <div class="absolute inset-0 flex items-end justify-center p-2 bg-slate-900/0 group-hover:bg-slate-900/60 backdrop-blur-0 group-hover:backdrop-blur-[2px] opacity-0 group-hover:opacity-100 transition-all duration-300">
                                <span class="text-white text-[10px] font-bold text-center font-ui">Foto Kontrak / BA</span>
                            </div>
                        </button>
                        @endif
                        @if($installReport->signature_photo)
                        <button type="button" @click="$dispatch('open-image-preview', { url: '{{ asset('storage/'.$installReport->signature_photo) }}', label: 'Foto Tanda Tangan' })" class="group relative block w-full rounded-lg overflow-hidden aspect-square bg-slate-100 dark:bg-slate-800 border border-border hover:border-sky-500 transition-all cursor-pointer">
                            <img src="{{ asset('storage/'.$installReport->signature_photo) }}" alt="Foto Tanda Tangan" class="h-full w-full object-cover">
                            <div class="absolute inset-0 flex items-end justify-center p-2 bg-slate-900/0 group-hover:bg-slate-900/60 backdrop-blur-0 group-hover:backdrop-blur-[2px] opacity-0 group-hover:opacity-100 transition-all duration-300">
                                <span class="text-white text-[10px] font-bold text-center font-ui">Tanda Tangan</span>
                            </div>
                        </button>
                        @endif
                    </div>
                </div>
                @endif
            </div>
            @endif

            @else
            @php
                $maintenanceFopTask = app(\App\Services\TaskWorkToolService::class)->resolveTaskFor($task);
                $materialsTerpakai = $maintenanceFopTask
                    ? $maintenanceFopTask->materials()->terpakai()->orderBy('id')->get()
                    : collect();
                $maintenanceReport = $task->maintenanceReport;
            @endphp
            @if($maintenanceReport || $materialsTerpakai->isNotEmpty())
            <div class="pt-5 border-t border-border space-y-4 select-text">
                <div class="flex items-center gap-2 mb-1 select-none">
                    <svg class="h-4.5 w-4.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-text-main font-ui">Laporan Pekerjaan Teknisi</h3>
                </div>

                @if($maintenanceReport?->kendala_teknis)
                <div class="bg-surface-muted border border-border rounded-xl p-4 shadow-xs min-w-0 max-w-full overflow-hidden">
                    <div class="flex items-center gap-1.5 mb-2 select-none">
                        <svg class="h-3.5 w-3.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        <span class="text-[10px] text-text-muted font-bold uppercase tracking-wider font-ui">Kendala &amp; Solusi</span>
                    </div>
                    <p class="text-xs text-text-main leading-relaxed font-ui whitespace-pre-line break-words [word-break:break-word] min-w-0 font-medium">{{ $maintenanceReport->kendala_teknis }}</p>
                </div>
                @endif

                @if($materialsTerpakai->isNotEmpty())
                <div>
                    <span class="block text-[10px] text-text-muted font-bold uppercase tracking-wider font-ui mb-2 select-none">Material Terpakai</span>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        @foreach($materialsTerpakai as $material)
                        <div class="flex justify-between items-center bg-surface-muted border border-border p-3 rounded-xl shadow-xs">
                            <span class="text-text-secondary font-ui font-semibold">{{ $material->item_name }}@if($material->note)<span class="text-text-muted text-[10px]"> · {{ $material->note }}</span>@endif</span>
                            <span class="font-mono font-bold text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/60 px-2.5 py-0.5 rounded border border-sky-200 dark:border-sky-900/50">{{ rtrim(rtrim(number_format($material->qty, 2, ',', '.'), '0'), ',') }} {{ $material->unit }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                @if($maintenanceReport?->opm_photo || $maintenanceReport?->speedtest_photo)
                <div x-data class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs select-none">
                    <div class="flex items-center gap-1.5 px-3.5 py-2.5 border-b border-border bg-surface-muted">
                        <svg class="h-3.5 w-3.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                        </svg>
                        <span class="text-[10px] text-text-muted font-bold uppercase tracking-wider font-ui">Foto OPM &amp; Speedtest</span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5">
                        @if($maintenanceReport->opm_photo)
                        <button type="button" @click="$dispatch('open-image-preview', { url: '{{ asset('storage/'.$maintenanceReport->opm_photo) }}', label: 'Foto OPM' })" class="group relative block w-full rounded-lg overflow-hidden aspect-square bg-slate-100 dark:bg-slate-800 border border-border hover:border-sky-500 transition-all cursor-pointer">
                            <img src="{{ asset('storage/'.$maintenanceReport->opm_photo) }}" alt="Foto OPM" class="h-full w-full object-cover">
                            <div class="absolute inset-0 flex items-end justify-center p-2 bg-slate-900/0 group-hover:bg-slate-900/60 backdrop-blur-0 group-hover:backdrop-blur-[2px] opacity-0 group-hover:opacity-100 transition-all duration-300">
                                <span class="text-white text-[10px] font-bold text-center font-ui">Foto OPM</span>
                            </div>
                        </button>
                        @endif
                        @if($maintenanceReport->speedtest_photo)
                        <button type="button" @click="$dispatch('open-image-preview', { url: '{{ asset('storage/'.$maintenanceReport->speedtest_photo) }}', label: 'Foto Speedtest' })" class="group relative block w-full rounded-lg overflow-hidden aspect-square bg-slate-100 dark:bg-slate-800 border border-border hover:border-sky-500 transition-all cursor-pointer">
                            <img src="{{ asset('storage/'.$maintenanceReport->speedtest_photo) }}" alt="Foto Speedtest" class="h-full w-full object-cover">
                            <div class="absolute inset-0 flex items-end justify-center p-2 bg-slate-900/0 group-hover:bg-slate-900/60 backdrop-blur-0 group-hover:backdrop-blur-[2px] opacity-0 group-hover:opacity-100 transition-all duration-300">
                                <span class="text-white text-[10px] font-bold text-center font-ui">Foto Speedtest</span>
                            </div>
                        </button>
                        @endif
                    </div>
                </div>
                @endif
            </div>
            @endif
            @endif

        </div>

        {{-- Section D: Action/Status Overlay panels (FOP Manage / FOP Review) --}}
        @if(in_array($task->status->value, ['pending', 'terjadwal']))
        @if(auth()->user()->can('fopReject', $task) || auth()->user()->can('fopPending', $task))
        <div class="p-4 sm:p-5 border-t border-border flex flex-col sm:flex-row gap-3 items-center justify-between bg-slate-50/20 dark:bg-slate-800/5 select-none">
            <div>
                <h4 class="text-xs font-bold text-text-main mb-0.5 font-ui">Manajemen Task (FOP Koordinator)</h4>
                <p class="text-[11px] text-text-muted font-ui">Kelola task penugasan sebelum dikerjakan oleh tim teknisi.</p>
            </div>
            <div class="flex items-center gap-2 w-full sm:w-auto">
                @if($task->status->value === 'pending')
                    @can('fopReject', $task)
                    <button x-data @click="$dispatch('open-modal', 'fop-reject-task-pending')"
                            class="flex-1 sm:flex-initial inline-flex items-center justify-center gap-1.5 text-xs font-bold px-3.5 py-2.5 border bg-surface hover:bg-surface-muted transition-all rounded-xl cursor-pointer font-ui active:scale-95 shadow-sm"
                            style="border-color:var(--color-error-border); color:var(--color-error)">
                        Reject Task
                    </button>
                    @endcan
                @endif

                @if($task->status->value === 'terjadwal')
                    @can('fopPending', $task)
                    <button x-data @click="$dispatch('open-modal', 'fop-pending-task')"
                            class="flex-1 sm:flex-initial inline-flex items-center justify-center gap-1.5 text-xs font-bold px-3.5 py-2.5 border bg-surface hover:bg-surface-muted transition-all rounded-xl cursor-pointer font-ui active:scale-95 shadow-sm"
                            style="border-color:var(--color-warning-border); color:var(--color-warning)">
                        Set Pending
                    </button>
                    @endcan
                @endif
            </div>
        </div>
        @endif
        @endif

        {{-- Review FOP --}}
        @if($task->status->value === 'selesai' && $task->fop_review_status === 'pending')
        @can('review', $task)
        @if($task->task_type->value === 'PSB')
        <div class="p-4 sm:p-5 border-t border-border flex flex-col sm:flex-row gap-3 items-center justify-between bg-slate-50/20 dark:bg-slate-800/5 select-none">
            <div class="min-w-0 flex-1">
                <h4 class="text-xs font-bold text-text-main mb-0.5 font-ui">Approve Pemasangan (Verifikasi Admin)</h4>
                <p class="text-[11px] text-text-muted font-ui leading-relaxed">Aktivasi layanan (CID + tagihan awal) hanya boleh diproses melalui halaman Verifikasi Admin.</p>
            </div>
            @if($task->customer_id)
                @if(auth()->user()->hasPermission('customers.detail.installation.validate') || auth()->user()->hasFullAccess())
                <a href="{{ route('customers.verification.admin', $task->customer_id) }}"
                   class="w-full sm:w-auto text-center inline-flex items-center justify-center gap-1.5 text-xs font-bold px-4 py-2.5 rounded-xl text-white transition-all shadow-md shadow-sky-500/10 cursor-pointer font-ui active:scale-95"
                   style="background:var(--color-primary)">
                    Buka Verifikasi Admin
                </a>
                @else
                <a href="{{ route('customers.installation.report', ['customer' => $task->customer_id, 'return_to' => route('tasks.show', $task)]) }}"
                   class="w-full sm:w-auto text-center inline-flex items-center justify-center gap-1.5 text-xs font-bold px-4 py-2.5 rounded-xl text-white transition-all shadow-md shadow-sky-500/10 cursor-pointer font-ui active:scale-95"
                   style="background:var(--color-primary)">
                    Lihat Laporan Pemasangan
                </a>
                @endif
            @endif
        </div>
        @else
        <div class="p-4 sm:p-5 border-t border-border flex flex-col sm:flex-row gap-3 items-center justify-between bg-slate-50/20 dark:bg-slate-800/5 select-none">
            <div>
                <h4 class="text-xs font-bold text-text-main mb-0.5 font-ui">Review Hasil Pekerjaan (Khusus FOP)</h4>
                <p class="text-[11px] text-text-muted font-ui">Task ini telah diselesaikan oleh teknisi dan sedang menunggu persetujuan Anda.</p>
            </div>
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <button x-data @click="$dispatch('open-modal', 'reject-task')"
                        class="flex-1 sm:flex-initial inline-flex items-center justify-center gap-1.5 text-xs font-bold px-3.5 py-2.5 border bg-surface hover:bg-surface-muted transition-all rounded-xl cursor-pointer font-ui active:scale-95 shadow-sm"
                        style="border-color:var(--color-error-border); color:var(--color-error)">
                    Reject Laporan
                </button>
                <form action="{{ route('tasks.review', $task) }}" method="POST" class="flex-1 sm:flex-initial">
                    @csrf
                    <input type="hidden" name="action" value="approve">
                    <button type="submit"
                            class="w-full inline-flex items-center justify-center gap-1.5 text-xs font-bold px-4 py-2.5 rounded-xl text-white transition-all shadow-md shadow-sky-600/10 cursor-pointer font-ui active:scale-95"
                            style="background:var(--color-primary)">
                        Approve Task
                    </button>
                </form>
            </div>
        </div>
        @endif
        @endcan
        @endif
    </div>

    {{-- ══ Action Buttons (Teknisi / Lapangan) ════════════════════════ --}}
    @if(in_array($task->status->value, ['terjadwal', 'in_progress', 'pending']))
    <div class="flex flex-wrap items-center justify-end gap-2.5 pt-1.5 font-ui select-none">
        @can('statusReschedule', $task)
        <button type="button" x-data @click="$dispatch('open-modal', 'reschedule-task-{{ $task->id }}')"
                class="inline-flex items-center justify-center gap-1.5 text-xs font-bold px-4 py-2.5 rounded-xl border bg-surface hover:bg-warning/5 cursor-pointer active:scale-95 transition-all shadow-sm"
                style="border-color:var(--color-warning-border); color:var(--color-warning)">
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Set Pending</span>
        </button>
        @endcan
        @if($task->status->value === 'terjadwal')
            @if($task->scheduled_at && !$task->scheduled_at->startOfDay()->isFuture())
            @if($task->task_type->value === \App\Enums\TaskType::SURVEY->value)
                @if($task->customer_id && auth()->user()->hasPermission('customers.detail.survey.update') && $task->teamMembers->pluck('user_id')->contains(auth()->id()))
                <form action="{{ route('customers.survey.start', $task->customer_id) }}" method="POST" class="w-full sm:w-auto">
                    @csrf
                    <button type="submit"
                            class="w-full inline-flex items-center justify-center gap-1.5 text-xs font-bold px-4 py-2.5 rounded-xl text-white transition-all shadow-md shadow-amber-500/10 cursor-pointer active:scale-95"
                            style="background:var(--color-warning)">
                        <svg class="h-4.5 w-4.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                        </svg>
                        Mulai Survey
                    </button>
                </form>
                @endif
            @elseif($task->task_type->value === \App\Enums\TaskType::PEMASANGAN->value)
                @if($task->customer_id && auth()->user()->hasPermission('customers.detail.installation.update') && $task->teamMembers->pluck('user_id')->contains(auth()->id()))
                <form action="{{ route('customers.installation.start', $task->customer_id) }}" method="POST" class="w-full sm:w-auto">
                    @csrf
                    <button type="submit"
                            class="w-full inline-flex items-center justify-center gap-1.5 text-xs font-bold px-4 py-2.5 rounded-xl text-white transition-all shadow-md shadow-amber-500/10 cursor-pointer active:scale-95"
                            style="background:var(--color-warning)">
                        <svg class="h-4.5 w-4.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                        </svg>
                        Mulai Pemasangan
                    </button>
                </form>
                @endif
            @else
                @can('statusStart', $task)
                <form action="{{ route('tasks.start', $task) }}" method="POST" class="w-full sm:w-auto">
                    @csrf
                    <button type="submit"
                            class="w-full inline-flex items-center justify-center gap-1.5 text-xs font-bold px-4 py-2.5 rounded-xl text-white transition-all shadow-md shadow-amber-500/10 cursor-pointer active:scale-95"
                            style="background:var(--color-warning)">
                        <svg class="h-4.5 w-4.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                        </svg>
                        {{ $task->task_type->value === \App\Enums\TaskType::MAINTENANCE->value ? 'Mulai Maintenance' : 'Mulai Task' }}
                    </button>
                </form>
                @endcan
            @endif
            @else
            <span class="w-full sm:w-auto text-center text-xs text-text-muted px-4 py-2.5 border border-border rounded-xl bg-surface font-semibold">
                Dijadwalkan {{ $task->scheduled_at?->translatedFormat('l, d M Y') }}
            </span>
            @endif
        @endif

        @can('statusComplete', $task)
        @if(in_array($task->status->value, ['in_progress', 'pending']))
            @php
                $reportUrl = match(true) {
                    $task->task_type->value === \App\Enums\TaskType::SURVEY->value => route('customers.survey.report', ['customer' => $task->customer_id, 'return_to' => route('tasks.show', $task)]),
                    $task->task_type->value === \App\Enums\TaskType::PEMASANGAN->value => route('customers.installation.report', ['customer' => $task->customer_id, 'return_to' => route('tasks.show', $task)]),
                    default => route('tasks.maintenance.report', $task),
                };
                $reportLabel = match(true) {
                    $task->task_type->value === \App\Enums\TaskType::SURVEY->value => 'Laporan Survey',
                    $task->task_type->value === \App\Enums\TaskType::PEMASANGAN->value => 'Laporan Pemasangan',
                    default => 'Isi Laporan',
                };
            @endphp
            @if($task->status->value === 'in_progress')
                <x-task.report-choice-dialog :task="$task" :report-url="$reportUrl" class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 text-xs font-bold py-2.5 px-4 rounded-xl text-white bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 active:scale-[0.98] transition-all shadow-md shadow-emerald-500/10 cursor-pointer whitespace-nowrap">
                    <svg class="h-4.5 w-4.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ $reportLabel }}</span>
                </x-task.report-choice-dialog>
            @else
                <a href="{{ $reportUrl }}"
                   class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 text-xs font-bold px-4 py-2.5 rounded-xl text-white transition-all shadow-md shadow-emerald-500/10 cursor-pointer active:scale-95 whitespace-nowrap"
                   style="background:var(--color-success)">
                    <svg class="h-4.5 w-4.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Lanjutkan Laporan</span>
                </a>
            @endif
        @endif
        @endcan
    </div>
    @endif

</div>

{{-- ══ FOP Reject Pending Task Modal ═════════════════════════════════ --}}
@can('fopReject', $task)
<x-ui.modal name="fop-reject-task-pending" title="Reject Pending Task" maxWidth="sm">
    <p class="text-xs text-text-secondary mb-3 font-ui">
        Task ini belum dijadwalkan dan akan tetap berstatus <span class="font-semibold text-text-main">Pending</span>, namun dengan keterangan reject.
    </p>
    <form id="form-fop-reject-pending" action="{{ route('tasks.fop-reject', $task) }}" method="POST">
        @csrf
        <div class="space-y-1.5 font-ui">
            <label class="block text-[11px] font-semibold uppercase tracking-wider text-text-muted">Alasan Reject <span class="text-error">*</span></label>
            <x-ui.textarea name="reject_reason" rows="3" placeholder="Alasan reject task..." required />
        </div>
    </form>
    <x-slot name="footer">
        <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'fop-reject-task-pending')">
            Batal
        </x-ui.button>
        <x-ui.button type="submit" form="form-fop-reject-pending" variant="danger">
            Reject Task
        </x-ui.button>
    </x-slot>
</x-ui.modal>
@endcan

{{-- ══ FOP Set Pending Scheduled Task Modal ═══════════════════════════ --}}
@can('fopPending', $task)
<x-ui.modal name="fop-pending-task" title="Set Task Menjadi Pending" maxWidth="sm">
    <p class="text-xs text-text-secondary mb-3 font-ui">
        Task ini akan diubah statusnya dari <span class="font-semibold text-text-main">Terjadwal</span> menjadi <span class="font-semibold text-text-main">Pending</span>. Tim teknisi yang sudah di-assign tidak akan terhapus.
    </p>
    <form id="form-fop-pending" action="{{ route('tasks.fop-pending', $task) }}" method="POST">
        @csrf
        <div class="space-y-1.5 font-ui">
            <label class="block text-[11px] font-semibold uppercase tracking-wider text-text-muted">Alasan Pending <span class="text-error">*</span></label>
            <x-ui.textarea name="pending_reason" rows="3" placeholder="Alasan mengapa di-pending..." required />
        </div>
    </form>
    <x-slot name="footer">
        <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'fop-pending-task')">
            Batal
        </x-ui.button>
        <x-ui.button type="submit" form="form-fop-pending" variant="warning">
            Set Pending
        </x-ui.button>
    </x-slot>
</x-ui.modal>
@endcan


{{-- ══ Pending Top-Level (Reschedule Penuh) Modal — beda dari Set Pending FOP-side & dialog Laporan ═══ --}}
@can('statusReschedule', $task)
<x-ui.modal name="reschedule-task-{{ $task->id }}" title="Pending Task (Reschedule)" maxWidth="sm">
    <p class="text-xs text-text-secondary mb-3 font-ui">
        Task ini akan dilepas dari Anda dan dikembalikan ke antrian Task FOP untuk dijadwalkan ulang ke teknisi/hari lain.
        <span class="font-semibold text-text-main">Assignment Anda pada task ini akan dihapus.</span>
    </p>
    <form id="form-reschedule-{{ $task->id }}" action="{{ route('tasks.reschedule', $task) }}" method="POST">
        @csrf
        <div class="space-y-1.5 font-ui">
            <label class="block text-[11px] font-semibold uppercase tracking-wider text-text-muted">Alasan Pending <span class="text-error">*</span></label>
            <x-ui.textarea name="pending_reason" rows="3" placeholder="Alasan mengapa task ini di-pending/reschedule..." required />
        </div>
    </form>
    <x-slot name="footer">
        <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'reschedule-task-{{ $task->id }}')">
            Batal
        </x-ui.button>
        <x-ui.button type="submit" form="form-reschedule-{{ $task->id }}" variant="warning">
            Pending Task
        </x-ui.button>
    </x-slot>
</x-ui.modal>
@endcan

{{-- ══ FOP Reject Modal ══════════════════════════════════════════════ --}}
@can('review', $task)
<x-ui.modal name="reject-task" title="Reject Laporan Task" maxWidth="sm">
    <p class="text-xs text-text-secondary mb-3 font-ui">
        Task ini akan dikembalikan ke status <span class="font-semibold text-text-main">In Progress</span>. 
        Teknisi harus memperbaiki laporan berdasarkan alasan reject.
    </p>
    <form id="form-reject-task" action="{{ route('tasks.review', $task) }}" method="POST">
        @csrf
        <input type="hidden" name="action" value="reject">
        <div class="space-y-1.5 font-ui">
            <label class="block text-[11px] font-semibold uppercase tracking-wider text-text-muted">Alasan Reject <span class="text-error">*</span></label>
            <x-ui.textarea name="reason" rows="3" placeholder="Alasan reject (misal: Foto bukti kurang jelas)..." required />
        </div>
    </form>
    <x-slot name="footer">
        <x-ui.button type="button" variant="secondary"
                     x-on:click="$dispatch('close-modal', 'reject-task')">
            Batal
        </x-ui.button>
        <x-ui.button type="submit" form="form-reject-task" variant="danger">
            Konfirmasi Reject
        </x-ui.button>
    </x-slot>
</x-ui.modal>
@endcan

{{-- ══ Cancel Modal ══════════════════════════════════════════════════ --}}
@can('cancel', $task)
<x-ui.modal name="cancel-task" title="Batalkan Task" maxWidth="sm">
    <p class="text-xs text-text-secondary mb-3 font-ui">
        Task <span class="font-mono font-semibold">{{ $task->task_number }}</span> akan dibatalkan.
        Tindakan ini tidak dapat dibatalkan.
    </p>
    <form id="form-cancel-task" action="{{ route('tasks.cancel', $task) }}" method="POST">
        @csrf
        <div class="space-y-1.5 font-ui">
            <label class="block text-[11px] font-semibold uppercase tracking-wider text-text-muted">Alasan Pembatalan <span class="text-error">*</span></label>
            <x-ui.textarea name="cancel_reason" rows="3" placeholder="Alasan pembatalan..." required />
        </div>
    </form>
    <x-slot name="footer">
        <x-ui.button type="button" variant="secondary"
                     x-on:click="$dispatch('close-modal', 'cancel-task')">
            Batal
        </x-ui.button>
        <x-ui.button type="submit" form="form-cancel-task" variant="danger">
            Ya, Batalkan Task
        </x-ui.button>
    </x-slot>
</x-ui.modal>
@endcan


{{-- ══ Task Data untuk Alpine.js ═════════════════════════════════════ --}}
@php
$taskData = [
    'id' => $task->id,
    'task_number' => $task->task_number,
    'customer_name' => $task->customer?->full_name ?? '—',
    'customer_address' => $task->customer?->address ?? '—',
    'pop_name' => $task->pop?->name ?? '—',
    'task_type' => $task->task_type->value,
    'submit_url_survey' => route('customers.survey.store', $task),
    'submit_url_install' => route('customers.installation.store', $task),
    'current_package_id' => $task->customer?->customerService?->internet_package_id,
];
@endphp

<x-ui.image-preview-modal />

@endsection
