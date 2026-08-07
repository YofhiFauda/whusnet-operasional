@extends('layouts.app')

@section('title', $task->task_number . ' — Task Detail')

@section('content')
<div class="max-w-[1180px] mx-auto px-4 sm:px-6 py-4 space-y-4">

    {{-- ══ Breadcrumb ═══════════════════════════════════════════════ --}}
    <nav class="flex items-center gap-1.5 text-xs text-text-muted">
        @can('viewAll', \App\Models\Task::class)
        <a href="{{ auth()->user()->hasPermission('task.view.own') ? route('tasks.own') : route('fop.dashboard') }}" class="hover:text-primary transition-colors font-ui">Task</a>
        @else
        <a href="{{ route('tasks.own') }}" class="hover:text-primary transition-colors font-ui">Task Saya</a>
        @endcan
        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
        </svg>
        <span class="font-mono">{{ $task->task_number }}</span>
    </nav>

    {{-- ══ Page Header ══════════════════════════════════════════════ --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap mb-1">
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
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full border font-ui" style="{{ $statusStyle }}">
                    {{ $task->status->label() }}
                </span>
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full border font-ui {{ $task->task_type->cardClasses() }}">
                    {{ $task->task_type->label() }}
                </span>
                <span class="font-mono text-xs text-text-muted">{{ $task->task_number }}</span>
                @if($task->isOverSla())
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full border font-ui"
                      style="background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border)">
                    Melewati SLA
                </span>
                @endif
            </div>
            <h1 class="text-lg sm:text-xl font-bold text-text-main font-ui">{{ $task->title }}</h1>
        </div>
        @php
            $lat = $task->customer?->customerAddress?->latitude ?? $task->pop?->latitude;
            $lng = $task->customer?->customerAddress?->longitude ?? $task->pop?->longitude;
        @endphp
        <div class="flex items-center gap-1.5 shrink-0 flex-wrap">
            @if($lat && $lng)
            <a href="https://www.google.com/maps/search/?api=1&query={{ $lat }},{{ $lng }}" target="_blank"
               class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1.5 border border-border rounded bg-surface hover:bg-surface-muted text-primary transition-colors shadow-sm font-ui cursor-pointer">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                Lokasi Maps
            </a>
            @endif
            @can('edit', $task)
            <a href="{{ route('tasks.edit', $task) }}"
               class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1.5 border border-border rounded bg-surface hover:bg-surface-muted text-text-secondary transition-colors font-ui cursor-pointer">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Edit
            </a>
            @endcan
            @can('cancel', $task)
            <button x-data @click="$dispatch('open-modal', 'cancel-task')"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1.5 border rounded transition-colors font-ui cursor-pointer"
                    style="border-color:var(--color-error-border); color:var(--color-error); background:var(--color-error-bg)">
                Batalkan
            </button>
            @endcan
        </div>
    </div>

    {{-- ══ Metric Strip ═════════════════════════════════════════════ --}}
    <div class="bg-surface border border-border rounded-lg overflow-hidden shadow-sm">
        <div class="grid grid-cols-2 sm:grid-cols-5 divide-y sm:divide-y-0 sm:divide-x divide-border">
            {{-- Tipe --}}
            <div class="p-3 flex flex-col justify-between">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted mb-0.5 font-ui">Tipe Task</p>
                <p class="text-xs font-semibold text-text-main font-ui">{{ $task->task_type->label() }}</p>
            </div>
            {{-- Jadwal --}}
            <div class="p-3 flex flex-col justify-between">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted mb-0.5 font-ui">Jadwal</p>
                <div>
                    <p class="text-xs font-semibold font-mono text-text-main">{{ $task->scheduled_at?->format('H:i') ?? '—' }}</p>
                    <p class="text-[10px] text-text-muted font-ui">{{ $task->scheduled_at?->translatedFormat('d M Y') ?? 'Belum dijadwalkan' }}</p>
                </div>
            </div>
            {{-- Durasi vs SLA --}}
            <div class="p-3 flex flex-col justify-between">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted mb-0.5 font-ui">Target SLA</p>
                <div>
                    <p class="text-xs font-semibold font-mono {{ $task->isOverSla() ? '' : 'text-text-main' }}"
                       style="{{ $task->isOverSla() ? 'color:var(--color-error)' : '' }}">
                        {{ $task->sla_minutes }} Menit
                    </p>
                    @if($task->actualDurationMinutes() !== null)
                    <p class="text-[10px] text-text-muted font-mono">Aktual: {{ $task->actualDurationMinutes() }} Mnt</p>
                    @endif
                </div>
            </div>
            {{-- POP --}}
            <div class="p-3 flex flex-col justify-between">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted mb-0.5 font-ui">POP / Cabang</p>
                <p class="text-xs font-semibold text-text-main truncate font-ui">{{ $task->pop?->name ?? '—' }}</p>
            </div>
            {{-- Durasi Aktual --}}
            @php
                $topActualMin = $task->actualDurationMinutes();
                $topDuration = $topActualMin !== null
                    ? (intdiv($topActualMin, 60) > 0
                        ? intdiv($topActualMin, 60).' jam '.($topActualMin % 60).' menit'
                        : $topActualMin.' menit')
                    : null;
            @endphp
            <div class="p-3 flex flex-col justify-between col-span-2 sm:col-span-1">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted mb-0.5 font-ui">Durasi Aktual</p>
                <div>
                    <p class="text-xs font-semibold font-mono {{ $task->isOverSla() ? '' : 'text-text-main' }}"
                       style="{{ $topActualMin !== null && $task->isOverSla() ? 'color:var(--color-error)' : '' }}">
                        {{ $topDuration ?? '—' }}
                    </p>
                    <p class="text-[10px] text-text-muted font-ui">{{ $task->started_at ? 'Berjalan' : 'Belum dimulai' }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ Card gabungan: ROW 1 (Informasi Task + Waktu/Tim) & ROW 2 (Briefing Teknis) — satu wrapper, satu radius, dipisah divide-y biar sambungan gak putus ════ --}}
    <div class="bg-surface sm:border border-border sm:rounded-xl sm:shadow-xs overflow-hidden divide-y divide-border">
        <div class="grid grid-cols-1 md:grid-cols-12 divide-y md:divide-y-0 md:divide-x divide-border">
            
            {{-- Left Column: Informasi Task --}}
            <div class="md:col-span-7 p-4 sm:p-5 space-y-4">
                {{-- ══ Informasi Task ════════════════════════════════ --}}
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <svg class="h-4 w-4 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-text-muted font-ui">Informasi Task</p>
                    </div>
                    <div class="space-y-0.5 text-xs">
                        <div class="flex flex-col sm:flex-row sm:items-start py-2.5 border-b border-border gap-1 sm:gap-4">
                            <span class="text-text-muted sm:w-36 shrink-0 font-ui font-medium">FOP / Koordinator</span>
                            <span class="text-text-main font-semibold flex-1 font-ui">{{ $task->fop?->name ?? '—' }}</span>
                        </div>
                        
                        <div class="flex flex-col sm:flex-row sm:items-start py-2.5 border-b border-border gap-1 sm:gap-4">
                            <span class="text-text-muted sm:w-36 shrink-0 font-ui font-medium">Pelanggan & Kontak</span>
                            <div class="text-text-main font-medium flex-1 font-ui">
                                @if($task->customer)
                                <div>
                                    <a href="{{ route('customers.show', $task->customer) }}"
                                       class="hover:underline font-bold text-sky-600 dark:text-sky-400">
                                        {{ $task->customer->full_name }}
                                    </a>
                                    <span class="font-mono text-xs text-text-muted ml-1 bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded border border-border">{{ $task->customer->display_id }}</span>
                                </div>
                                @if($task->customer->primary_phone)
                                <div class="flex items-center gap-1.5 mt-1 text-[11px]">
                                    <span class="text-text-muted font-mono font-medium">{{ $task->customer->primary_phone }}</span>
                                    <a href="https://wa.me/{{ preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $task->customer->primary_phone)) }}" target="_blank"
                                       class="text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 inline-flex items-center gap-1 font-semibold cursor-pointer bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/50 px-2 py-0.5 rounded transition-colors">
                                        <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.768-.001 1.298.409 2.522 1.189 3.518l-.756 2.766 2.831-.744a5.748 5.748 0 002.504.588h.002c3.18 0 5.767-2.586 5.768-5.766 0-1.541-.6-2.99-1.691-4.08-1.091-1.09-2.539-1.69-4.079-1.648zm0 10.153a4.398 4.398 0 01-2.241-.614l-.16-.095-1.666.438.444-1.624-.105-.167a4.394 4.394 0 01-.67-2.326c.001-2.426 1.975-4.4 4.402-4.4 1.177 0 2.283.458 3.115 1.29a4.382 4.382 0 011.29 3.117c-.001 2.426-1.975 4.4-4.409 4.4z"/></svg>
                                        WhatsApp
                                    </a>
                                </div>
                                @endif
                                @else
                                <span class="text-text-muted">—</span>
                                @endif
                            </div>
                        </div>

                        @if($task->description)
                        <div class="flex flex-col sm:flex-row sm:items-start py-2.5 border-b border-border gap-1 sm:gap-4">
                            <span class="text-amber-700 dark:text-amber-400 sm:w-36 shrink-0 font-bold font-ui">Issue / Keluhan</span>
                            <span class="text-text-main font-semibold leading-relaxed bg-amber-50/70 dark:bg-amber-900/20 border border-amber-200/80 dark:border-amber-800/40 rounded-lg p-2.5 flex-1 font-ui">{{ $task->description }}</span>
                        </div>
                        @endif

                        {{-- Catatan Teknis (asesmen NOC, ticket->catatan_teknis) & Catatan
                             FOP (fop_task->notes) SENGAJA gak digabung ke box Issue/Keluhan
                             di atas — dua sumber beda, gampang menyimpang kalau dicampur.
                             Ditaruh di box sendiri-sendiri di sini biar teknisi tetap bisa
                             baca keduanya, bukan hilang gara-gara dipisah dari description. --}}
                        @if($task->fopTask?->ticket?->catatan_teknis)
                        <div class="flex flex-col sm:flex-row sm:items-start py-2.5 border-b border-border gap-1 sm:gap-4">
                            <span class="text-sky-700 dark:text-sky-400 sm:w-36 shrink-0 font-bold font-ui">Catatan Teknis (NOC)</span>
                            <span class="text-text-main font-semibold leading-relaxed bg-sky-50/70 dark:bg-sky-900/20 border border-sky-200/80 dark:border-sky-800/40 rounded-lg p-2.5 flex-1 font-ui whitespace-pre-line">{{ $task->fopTask->ticket->catatan_teknis }}</span>
                        </div>
                        @endif

                        @if($task->fopTask?->notes)
                        <div class="flex flex-col sm:flex-row sm:items-start py-2.5 border-b border-border gap-1 sm:gap-4">
                            <span class="text-text-muted sm:w-36 shrink-0 font-ui font-medium">Catatan FOP</span>
                            <span class="text-text-main leading-relaxed bg-surface-muted border border-border rounded-lg p-2.5 flex-1 font-ui whitespace-pre-line">{{ $task->fopTask->notes }}</span>
                        </div>
                        @endif

                        @if($task->customer || $task->pop)
                        <div class="flex flex-col sm:flex-row sm:items-start py-2.5 gap-1 sm:gap-4">
                            <span class="text-text-muted sm:w-36 shrink-0 font-ui font-medium">Alamat & Lokasi</span>
                            <div class="text-text-secondary leading-relaxed flex-1 font-ui">
                                <div class="font-medium text-text-main">
                                    @if($task->customer)
                                        {{ $task->customer->clean_address }}
                                    @else
                                        {{ $task->pop?->address ?? '—' }} ({{ $task->pop?->name }})
                                    @endif
                                </div>
                                @if($lat && $lng)
                                <div class="flex items-center gap-2 mt-2 pt-2 border-t border-border/60 text-[11px] flex-wrap">
                                    <span class="font-mono text-text-main bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded border border-border">Lat: <strong class="text-sky-600 dark:text-sky-400">{{ $lat }}</strong> | Lng: <strong class="text-sky-600 dark:text-sky-400">{{ $lng }}</strong></span>
                                    <a href="https://www.google.com/maps/search/?api=1&query={{ $lat }},{{ $lng }}" target="_blank"
                                       class="inline-flex items-center gap-1 font-bold text-sky-600 dark:text-sky-400 hover:underline cursor-pointer">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        Maps →
                                    </a>
                                </div>
                                @endif
                            </div>
                        </div>
                        @endif

                        @if($task->pending_reason)
                        <div class="flex flex-col sm:flex-row sm:items-start py-2.5 border-t border-border gap-1 sm:gap-4">
                            <span class="text-text-muted sm:w-36 shrink-0 font-ui font-medium">Alasan Pending</span>
                            <span class="font-semibold flex-1 text-warning font-ui">{{ $task->pending_reason }}</span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>{{-- /left-column --}}
            
            {{-- Right Column: Waktu Pengerjaan, Tim Teknisi, Audit Log --}}
            <div class="md:col-span-5 p-4 sm:p-5 space-y-5 bg-slate-50/50 dark:bg-slate-800/40">
                
                {{-- ══ Waktu Pengerjaan ══════════════════════════════ --}}
                @if($task->status->value === 'selesai' && $task->started_at && $task->completed_at)
                @php
                    $showStartedAt   = $task->started_at;
                    $showCompletedAt = $task->completed_at;
                    $showActualMin   = (int) $showStartedAt->diffInMinutes($showCompletedAt);
                    $showHours       = intdiv($showActualMin, 60);
                    $showRemMins     = $showActualMin % 60;
                    $showDuration    = $showHours > 0
                        ? "{$showHours} jam {$showRemMins} menit"
                        : "{$showActualMin} menit";
                    $showOverSla     = $showActualMin > $task->sla_minutes;
                    $showTypeLabel   = $task->task_type->value === \App\Enums\TaskType::PEMASANGAN->value ? 'Pemasangan' : 'Survey';
                @endphp
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-1.5">
                            <svg class="h-4 w-4 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-text-muted font-ui">
                                Waktu {{ $showTypeLabel }}
                            </p>
                        </div>
                        <span class="text-[9px] font-bold px-2 py-0.5 rounded-full border font-mono"
                              style="{{ $showOverSla
                                  ? 'background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border)'
                                  : 'background:var(--color-success-bg); color:var(--color-success); border-color:var(--color-success-border)' }}">
                            {{ $showOverSla ? 'Over SLA' : 'Dalam SLA' }}
                        </span>
                    </div>
                    <div class="bg-surface border border-border rounded-xl p-3.5 space-y-2.5 shadow-xs">
                        <div class="flex items-center justify-between text-[11px]">
                            <div>
                                <p class="text-[9px] font-bold uppercase tracking-wider text-text-muted mb-0.5 font-ui">Mulai</p>
                                <p class="font-mono font-bold text-text-main text-sm">{{ $showStartedAt->format('H:i') }}</p>
                                <p class="text-[9px] text-text-muted font-ui">{{ $showStartedAt->translatedFormat('d M Y') }}</p>
                            </div>
                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25L21 12m0 0l-3.75 3.75M21 12H3" />
                            </svg>
                            <div class="text-right">
                                <p class="text-[9px] font-bold uppercase tracking-wider text-text-muted mb-0.5 font-ui">Selesai</p>
                                <p class="font-mono font-bold text-text-main text-sm">{{ $showCompletedAt->format('H:i') }}</p>
                                <p class="text-[9px] text-text-muted font-ui">{{ $showCompletedAt->translatedFormat('d M Y') }}</p>
                            </div>
                        </div>
                        <div class="pt-2.5 border-t border-border flex items-center justify-between text-[11px]">
                            <div>
                                <p class="text-[9px] font-bold uppercase tracking-wider text-text-muted font-ui">Durasi Aktual</p>
                                <p class="font-mono font-bold text-sm" style="color:{{ $showOverSla ? 'var(--color-error)' : 'var(--color-success)' }}">
                                    {{ $showDuration }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-[9px] font-bold uppercase tracking-wider text-text-muted font-ui">Target SLA</p>
                                <p class="font-mono font-bold text-text-secondary">{{ $task->sla_minutes }} menit</p>
                            </div>
                        </div>
                        @if($task->completedBy)
                        <div class="pt-2.5 border-t border-border flex items-center gap-2 text-[11px]">
                            <svg class="h-3.5 w-3.5 text-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <p class="text-[10px] text-text-muted font-ui">Diselesaikan &amp; dilaporkan oleh: <span class="font-bold text-text-main">{{ $task->completedBy->name }}</span></p>
                        </div>
                        @endif
                    </div>
                </div>
                @endif

                {{-- ══ Tim Teknisi ══════════════════════════════════ --}}
                <div>
                    <div class="flex items-center gap-1.5 mb-2">
                        <svg class="h-4 w-4 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-text-muted font-ui">Tim Teknisi</p>
                    </div>
                    @if($task->teamMembers->count() > 0)
                    <div class="space-y-2">
                        @foreach($task->teamMembers as $member)
                        <div class="flex items-center gap-2.5 bg-surface border border-border rounded-lg p-2.5 w-full shadow-xs hover:border-sky-300 dark:hover:border-sky-700 transition-colors">
                            <div class="h-7 w-7 rounded-full bg-sky-100 dark:bg-sky-950 text-sky-700 dark:text-sky-300 flex items-center justify-center text-xs font-bold shrink-0 border border-sky-200 dark:border-sky-800">
                                {{ strtoupper(substr($member->user?->name ?? '?', 0, 2)) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-bold text-text-main truncate font-ui">{{ $member->user?->name ?? 'User dihapus' }}</p>
                                <p class="text-[10px] text-text-muted capitalize font-ui">{{ $member->role_in_task }}</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <p class="text-xs text-text-muted font-ui">Belum ada anggota tim.</p>
                    @endif
                </div>

                {{-- ══ Riwayat Status (Audit Log) ══════════════════ --}}
                @if(auth()->user()->hasRole(['owner', 'admin', 'fop']) || $task->isMember(auth()->id()))
                <div class="pt-4 border-t border-border">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-text-muted mb-2 font-ui">Riwayat Status (Audit Log)</p>
                    @if($task->auditLogs && $task->auditLogs->count() > 0)
                    <div class="relative border-l border-border ml-2 space-y-3">
                        @foreach($task->auditLogs as $log)
                        <div class="relative pl-3.5">
                            {{-- Timeline node --}}
                            <div class="absolute -left-1 top-1.5 h-2 w-2 rounded-full bg-border border border-surface"></div>
                            <div class="mb-0.5 flex items-center justify-between gap-2">
                                <p class="text-xs font-semibold capitalize text-text-main font-ui">
                                    {{ str_replace('_', ' ', $log->action) }}
                                </p>
                                <span class="text-[9px] text-text-muted font-mono shrink-0">{{ $log->created_at->format('d M Y, H:i') }}</span>
                            </div>
                            <p class="text-[10px] text-text-muted font-ui">Oleh: <span class="font-medium text-text-secondary">{{ $log->user?->name ?? 'System' }}</span></p>
                            
                            @if($log->action === 'cancelled' && isset($log->new_values['cancel_reason']))
                            <div class="mt-1 p-1.5 bg-error-bg/20 border border-error-border rounded">
                                <p class="text-[9px] text-error font-medium font-ui">Alasan: {{ $log->new_values['cancel_reason'] }}</p>
                            </div>
                            @elseif($log->action === 'rejected' && isset($log->new_values['reject_reason']))
                            <div class="mt-1 p-1.5 bg-error-bg/20 border border-error-border rounded">
                                <p class="text-[9px] text-error font-medium font-ui">Alasan: {{ $log->new_values['reject_reason'] }}</p>
                            </div>
                            @elseif($log->action === 'completed' && isset($log->new_values['status']))
                            <div class="mt-0.5 text-[9px] text-success font-medium font-ui">Task ditandai selesai oleh teknisi.</div>
                            @endif
                        </div>
                        @endforeach
                    </div>
                    @else
                    <p class="text-xs text-text-muted font-ui">Belum ada riwayat aktivitas.</p>
                    @endif
                </div>
                @endif
                
            </div>{{-- /right-column --}}
            
        </div>

        {{-- ══ ROW 2: Briefing Teknis Sampai Selesai (Full Width 1 Row) ════ --}}
        <div class="p-4 sm:p-6 space-y-6">

        {{-- Briefing Detail Teknis --}}
        <div>
            <div class="flex items-center gap-2 mb-3.5 pb-2 border-b border-border">
                <svg class="h-4.5 w-4.5 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <h3 class="text-xs font-bold uppercase tracking-wider text-text-main font-ui">Briefing Detail Teknis</h3>
            </div>
            
            @if($task->task_type === \App\Enums\TaskType::SURVEY)
            {{-- Survey specific details --}}
            <div class="space-y-0.5 text-xs">
                <div class="flex flex-col sm:flex-row sm:items-start py-2 border-b border-border gap-1 sm:gap-4">
                    <span class="text-text-muted sm:w-36 shrink-0 font-ui">Status SLA Berjalan</span>
                    <div class="text-text-main text-xs font-medium flex-1">
                        @if($task->started_at && !$task->completed_at)
                            @php
                                $elapsed = (int) $task->started_at->diffInMinutes(now());
                                $remaining = $task->sla_minutes - $elapsed;
                            @endphp
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-bold font-mono {{ $remaining < 0 ? 'text-error' : 'text-primary' }}">
                                    {{ $elapsed }} menit berjalan
                                </span>
                                <span class="text-[10px] text-text-muted font-mono">({{ $remaining >= 0 ? "Sisa {$remaining} mnt" : "Over SLA " . abs($remaining) . " mnt" }})</span>
                            </div>
                        @elseif($task->status->value === 'terjadwal')
                            <span class="text-warning font-ui">Menunggu teknisi klik Mulai Survey (Target SLA: {{ $task->sla_minutes }} menit)</span>
                        @elseif($task->completed_at)
                            <span class="text-success font-ui">Selesai dikerjakan dalam {{ $task->actualDurationMinutes() }} menit</span>
                        @else
                            <span class="text-text-muted font-ui">{{ $task->status->label() }}</span>
                        @endif
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row sm:items-start py-2 border-b border-border gap-1 sm:gap-4">
                    <span class="text-text-muted sm:w-36 shrink-0 font-ui">Rencana Paket</span>
                    <span class="text-text-main font-semibold flex-1 font-ui">
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
            <div class="space-y-3">
                <div>
                    <span class="block text-[9px] font-semibold text-text-muted uppercase mb-1.5 font-ui">Hasil Survey Sebelumnya</span>
                    @if($survey)
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                        <div class="bg-slate-50/60 dark:bg-slate-800/40 border border-border rounded-lg p-3">
                            <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">ODP Tujuan & Port</span>
                            <span class="font-bold font-mono text-text-main text-xs mt-0.5 block">{{ $survey->nearest_odp ?: '-' }}</span>
                        </div>
                        <div class="bg-slate-50/60 dark:bg-slate-800/40 border border-border rounded-lg p-3">
                            <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">Estimasi Dropcore</span>
                            <span class="font-bold font-mono text-text-main text-xs mt-0.5 block">{{ $survey->cable_estimation_meter ? $survey->cable_estimation_meter . ' Meter' : '-' }}</span>
                        </div>
                        <div class="bg-slate-50/60 dark:bg-slate-800/40 border border-border rounded-lg p-3">
                            <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">Alat Khusus</span>
                            <span class="font-semibold text-text-main text-xs mt-0.5 block font-ui">{{ $survey->required_tools ?: 'Standar' }}</span>
                        </div>
                    </div>
                    @if($survey->requested_installation_date)
                    <p class="text-[11px] mt-2 font-ui font-semibold text-sky-600 dark:text-sky-400">
                        Pelanggan meminta dipasang: {{ \App\Support\IndonesianDate::date($survey->requested_installation_date) }}
                    </p>
                    @endif
                    @if($survey->survey_note)
                    <p class="text-[11px] text-text-secondary mt-1.5 italic font-ui">"{{ $survey->survey_note }}"</p>
                    @endif
                    @else
                    <p class="text-[11px] text-warning font-ui">Data hasil survey sebelumnya belum tercatat di sistem.</p>
                    @endif
                </div>

                @php
                    $estimasiMaterial = $task->customer
                        ? \App\Models\TaskMaterial::where('customer_id', $task->customer->id)->estimasi()->orderBy('id')->get()
                        : collect();
                @endphp
                @if($estimasiMaterial->isNotEmpty())
                <div class="pt-3 border-t border-border">
                    <span class="block text-[9px] font-semibold text-text-muted uppercase mb-1.5 font-ui">Estimasi Kebutuhan Alat</span>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        @foreach($estimasiMaterial as $material)
                        <div class="flex justify-between items-center bg-slate-50/60 dark:bg-slate-800/40 border border-border p-2.5 rounded-lg">
                            <span class="text-text-secondary font-ui">{{ $material->item_name }}</span>
                            <span class="font-mono font-bold text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/60 px-2 py-0.5 rounded border border-sky-200 dark:border-sky-800">{{ rtrim(rtrim(number_format($material->qty, 2, ',', '.'), '0'), ',') }} {{ $material->unit }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-3 border-t border-border text-xs">
                    <div class="bg-slate-50/60 dark:bg-slate-800/40 border border-border rounded-lg p-3">
                        <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">Paket yang Diaktifkan</span>
                        <span class="text-xs font-bold text-sky-600 dark:text-sky-400 block mt-0.5 font-ui">{{ $service?->internetPackage?->name ?? $service?->package_name_snapshot ?? '-' }}</span>
                        @if($service?->monthly_price)
                        <span class="text-[10px] text-text-muted font-mono block mt-0.5">Rp {{ number_format($service->monthly_price, 0, ',', '.') }} / bulan</span>
                        @endif
                    </div>
                    <div class="bg-slate-50/60 dark:bg-slate-800/40 border border-border rounded-lg p-3">
                        <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">Alokasi Perangkat / ONT</span>
                        @if($device)
                            <span class="text-xs font-bold text-text-main block mt-0.5 font-ui">{{ $device->brand }} {{ $device->model }}</span>
                            <span class="text-[10px] font-mono text-text-muted block mt-0.5 font-ui">SN: {{ $device->serial_number ?: 'Belum diinput' }}</span>
                        @else
                            <span class="text-xs text-warning font-medium block mt-0.5 font-ui">Perangkat ONT akan dicatat saat laporan pemasangan selesai.</span>
                        @endif
                    </div>
                </div>
            </div>

            @elseif($task->task_type === \App\Enums\TaskType::MAINTENANCE)
            @php
                $tech = $task->customer?->customerTechnicalDetail;
                $device = $task->customer?->customerDevice;
            @endphp
            <div class="space-y-3">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
                    <div class="bg-slate-50/60 dark:bg-slate-800/40 border border-border rounded-lg p-3">
                        <span class="block text-[9px] text-text-muted font-bold uppercase tracking-wider font-ui mb-1">ODP & Port Terhubung</span>
                        <span class="font-bold font-mono text-text-main text-xs block">
                            {{ $device?->odp ?? $tech?->odp_number ?: '-' }} 
                            @if($device?->odp_port || $tech?->odp_port)
                                <span class="text-sky-600 dark:text-sky-400">(Port {{ $device?->odp_port ?? $tech?->odp_port }})</span>
                            @endif
                        </span>
                    </div>
                    <div class="bg-slate-50/60 dark:bg-slate-800/40 border border-border rounded-lg p-3">
                        <span class="block text-[9px] text-text-muted font-bold uppercase tracking-wider font-ui mb-1">OLT & Port OLT</span>
                        <span class="font-bold font-mono text-text-main text-xs block">
                            {{ $tech?->olt_number ?: '-' }}
                            @if($tech?->olt_port) Port {{ $tech->olt_port }} @endif
                        </span>
                    </div>
                    <div class="bg-slate-50/60 dark:bg-slate-800/40 border border-border rounded-lg p-3">
                        <span class="block text-[9px] text-text-muted font-bold uppercase tracking-wider font-ui mb-1">Redaman RX Power</span>
                        <span class="font-bold font-mono text-text-main text-xs block">
                            {{ $device?->signal_rx_power ?? $tech?->initial_attenuation ? ($device?->signal_rx_power ?? $tech?->initial_attenuation) . ' dBm' : '-' }}
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3 text-xs">
                    <div class="bg-slate-50/60 dark:bg-slate-800/40 border border-border rounded-lg p-3">
                        <span class="block text-[9px] text-text-muted font-bold uppercase tracking-wider font-ui mb-1">Perangkat Terpasang</span>
                        <span class="font-bold text-text-main text-xs block font-ui">
                            {{ $device?->brand ?? 'Modem' }} {{ $device?->model }}
                        </span>
                        <span class="text-[10px] font-mono text-text-muted mt-0.5 block">
                            SN: {{ $device?->serial_number ?? $tech?->router_or_ont_serial ?: '-' }}
                        </span>
                    </div>
                    <div class="bg-slate-50/60 dark:bg-slate-800/40 border border-border rounded-lg p-3">
                        <span class="block text-[9px] text-text-muted font-bold uppercase tracking-wider font-ui mb-1">PPPoE User / IP Address</span>
                        <span class="font-bold font-mono text-text-main text-xs block">{{ $device?->pppoe_username ?: '-' }}</span>
                        <span class="text-[10px] font-mono text-sky-600 dark:text-sky-400 mt-0.5 block">IP: {{ $device?->ip_address ?? $tech?->ip_address ?: '-' }}</span>
                    </div>
                </div>
            </div>

            @elseif($task->task_type === \App\Enums\TaskType::AMBIL_MODEM)
            @php
                $device = $task->customer?->customerDevice;
            @endphp
            <div class="space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <div class="bg-slate-50/60 dark:bg-slate-800/40 border border-border rounded-lg p-3">
                        <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">Alasan Deaktivasi</span>
                        <span class="text-xs font-bold text-text-main mt-0.5 block font-ui">{{ $task->description ?: 'Pengambilan Modem' }}</span>
                    </div>
                    <div class="bg-slate-50/60 dark:bg-slate-800/40 border border-border rounded-lg p-3">
                        <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">Janji Temu</span>
                        <span class="text-xs font-bold text-text-main font-mono mt-0.5 block">{{ $task->scheduled_at?->translatedFormat('l, d M Y — H:i') ?: 'Segera' }} WIB</span>
                    </div>
                </div>

                <div class="pt-3 border-t border-border space-y-2">
                    <span class="block text-[9px] text-text-main font-bold uppercase font-ui">Aset ISP yang Wajib Ditarik</span>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div class="bg-background p-3 rounded-lg border border-border">
                            <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">ONT / Modem</span>
                            <span class="font-bold text-text-main text-xs mt-0.5 block font-ui">{{ $device?->brand ?: 'Modem ONT' }} {{ $device?->model }}</span>
                            <p class="text-[11px] text-sky-600 dark:text-sky-400 font-mono mt-1 font-bold">SN: {{ $device?->serial_number ?: 'PERIKSA FISIK' }}</p>
                        </div>
                        <div class="bg-background p-3 rounded-lg border border-border">
                            <span class="block text-[9px] text-text-muted font-bold uppercase mb-1 font-ui">Kelengkapan</span>
                            <ul class="list-disc list-inside space-y-0.5 text-text-secondary text-[11px] font-ui">
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
            <div class="space-y-3">
                <div class="bg-slate-50/60 dark:bg-slate-800/40 border border-border rounded-lg p-3 text-xs">
                    <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">Rincian Request</span>
                    <span class="text-xs font-bold text-text-main block mt-0.5 font-ui">{{ $task->description ?: $task->title }}</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3 text-xs">
                    <div class="bg-slate-50/60 dark:bg-slate-800/40 border border-border rounded-lg p-3">
                        <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">SSID WiFi Eksisting</span>
                        <span class="text-xs font-bold font-mono text-text-main block mt-0.5">{{ $device?->wifi_ssid ?? $tech?->ssid ?: 'Standard / Default' }}</span>
                    </div>
                    <div class="bg-slate-50/60 dark:bg-slate-800/40 border border-border rounded-lg p-3">
                        <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">IP Gateway Akses</span>
                        <span class="text-xs font-bold font-mono text-text-main block mt-0.5">{{ $device?->ip_address ?? $tech?->ip_address ?: '192.168.1.1' }}</span>
                    </div>
                </div>
            </div>

            @elseif(in_array($task->task_type, [\App\Enums\TaskType::OREQ, \App\Enums\TaskType::INFR]))
            <div class="space-y-3 text-xs">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="bg-slate-50/60 dark:bg-slate-800/40 border border-border rounded-lg p-3">
                        <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">POP Pembina</span>
                        <span class="text-xs font-bold text-text-main mt-0.5 block font-ui">{{ $task->pop?->name ?? 'Pusat' }}</span>
                    </div>
                    <div class="bg-slate-50/60 dark:bg-slate-800/40 border border-border rounded-lg p-3">
                        <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">Target / Lokasi</span>
                        <span class="text-xs text-text-main font-semibold mt-0.5 block font-ui">{{ $task->pop?->address ?: 'Infrastruktur POP' }}</span>
                    </div>
                </div>
                <div class="bg-slate-50/60 dark:bg-slate-800/40 border border-border rounded-lg p-3">
                    <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">Instruksi Pekerjaan</span>
                    <span class="text-xs font-semibold text-text-main leading-relaxed mt-0.5 block font-ui">{{ $task->description ?: $task->title }}</span>
                </div>
            </div>
            @endif
        </div>

        {{-- ══ Alat Kerja Yang Perlu Dibawa ═══════════════════ --}}
        @php
            $workToolRows = app(\App\Services\TaskWorkToolService::class)->displayRowsForTask($task);
        @endphp
        @if($workToolRows->isNotEmpty())
        <div class="pt-5 border-border">
            <div class="flex items-center gap-2 mb-3">
                <svg class="h-4 w-4 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z" />
                </svg>
                <h3 class="text-xs font-bold uppercase tracking-wider text-text-main font-ui">Alat Kerja Yang Perlu Dibawa</h3>
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach($workToolRows as $row)
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-lg border border-border bg-slate-50 dark:bg-slate-800 text-text-main font-ui shadow-xs hover:border-sky-400 transition-colors">
                    <svg class="h-3.5 w-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    {{ $row->tool_name }}@if($row->note)<span class="font-normal text-text-muted"> · {{ $row->note }}</span>@endif
                </span>
                @endforeach
            </div>
        </div>
        @endif

        {{-- ══ Laporan Pekerjaan Teknisi / Report Details ═══════ --}}
        @if($task->task_type->value === \App\Enums\TaskType::SURVEY->value)
        @php
            $surveyReport = $task->customer?->latestSurvey;
            $surveyFopTask = app(\App\Services\TaskMaterialService::class)->resolveTaskFor($task->customer, \App\Enums\TaskType::SURVEY);
            $surveyMaterials = $surveyFopTask
                ? $surveyFopTask->materials()->estimasi()->orderBy('id')->get()
                : collect();
        @endphp
        @if($surveyReport || $surveyMaterials->isNotEmpty())
        <div class="pt-5 border-t border-border space-y-4">
            <div class="flex items-center gap-2 mb-1">
                <svg class="h-4 w-4 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h3 class="text-xs font-bold uppercase tracking-wider text-text-main font-ui">Laporan Result Survey Lapangan</h3>
            </div>

            @if($surveyReport)
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                <div class="bg-slate-50/60 dark:bg-slate-800/40 border border-border rounded-lg p-3">
                    <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">Status Survey</span>
                    <span class="text-xs font-bold block mt-0.5 font-ui {{ $surveyReport->survey_status === 'completed' ? 'text-emerald-600 dark:text-emerald-400' : ($surveyReport->survey_status === 'failed' ? 'text-rose-600 dark:text-rose-400' : 'text-amber-600 dark:text-amber-400') }}">
                        {{ $surveyReport->survey_status === 'completed' ? 'LAYAK PASANG (Selesai)' : ($surveyReport->survey_status === 'failed' ? 'TIDAK LAYAK PASANG' : 'Menunggu / In Progress') }}
                    </span>
                </div>
                <div class="bg-slate-50/60 dark:bg-slate-800/40 border border-border rounded-lg p-3">
                    <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">Estimasi Kabel Dropcore</span>
                    <span class="text-xs font-bold font-mono text-sky-600 dark:text-sky-400 block mt-0.5">{{ $surveyReport->cable_estimation_meter ? $surveyReport->cable_estimation_meter.' Meter' : '-' }}</span>
                </div>
                <div class="bg-slate-50/60 dark:bg-slate-800/40 border border-border rounded-lg p-3">
                    <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">ODP Terdekat</span>
                    <span class="text-xs font-bold font-mono text-text-main block mt-0.5">{{ $surveyReport->nearest_odp ?: '-' }}</span>
                </div>
            </div>

            @if($surveyReport->survey_note)
            <div class="bg-slate-50/70 dark:bg-slate-800/50 border border-border rounded-xl p-4 shadow-xs min-w-0 max-w-full overflow-hidden">
                <div class="flex items-center gap-1.5 mb-2">
                    <svg class="h-3.5 w-3.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    <span class="text-[10px] text-text-muted font-bold uppercase tracking-wider font-ui">Catatan Lapangan &amp; Kendala</span>
                </div>
                <p class="text-xs text-text-main leading-relaxed font-ui whitespace-pre-line break-words [word-break:break-word] min-w-0">{{ $surveyReport->survey_note }}</p>
            </div>
            @endif
            @endif

            @if($surveyMaterials->isNotEmpty())
            <div>
                <span class="block text-[10px] text-text-muted font-bold uppercase tracking-wider font-ui mb-2">Estimasi Material Dibutuhkan</span>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    @foreach($surveyMaterials as $material)
                    <div class="flex justify-between items-center bg-slate-50/60 dark:bg-slate-800/40 border border-border p-2.5 rounded-lg">
                        <span class="text-text-secondary font-ui font-medium">{{ $material->item_name }}@if($material->note)<span class="text-text-muted text-[10px]"> · {{ $material->note }}</span>@endif</span>
                        <span class="font-mono font-bold text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/60 px-2 py-0.5 rounded border border-sky-200 dark:border-sky-800">{{ rtrim(rtrim(number_format($material->qty, 2, ',', '.'), '0'), ',') }} {{ $material->unit }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            @if($surveyReport?->survey_photo || $surveyReport?->house_photo)
            <div x-data class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs">
                <div class="flex items-center gap-1.5 px-3.5 py-2.5 border-b border-border bg-slate-50/60 dark:bg-slate-800/40">
                    <svg class="h-3.5 w-3.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span class="text-[10px] text-text-muted font-bold uppercase tracking-wider font-ui">Foto Hasil Survey (ODP &amp; Lokasi Rumah)</span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5">
                    @if($surveyReport->survey_photo)
                    <button type="button" @click="$dispatch('open-image-preview', { url: '{{ asset('storage/'.$surveyReport->survey_photo) }}', label: 'Foto ODP Survey' })" class="group relative block w-full rounded-lg overflow-hidden aspect-square bg-slate-100 dark:bg-slate-800 border border-border hover:border-sky-500 transition-all">
                        <img src="{{ asset('storage/'.$surveyReport->survey_photo) }}" alt="Foto ODP Survey" class="h-full w-full object-cover">
                        <div class="absolute inset-0 flex items-end justify-center p-2 bg-slate-900/0 group-hover:bg-slate-900/60 backdrop-blur-0 group-hover:backdrop-blur-[2px] opacity-0 group-hover:opacity-100 transition-all duration-300 cursor-zoom-in">
                            <span class="text-white text-[10px] font-bold text-center font-ui">Foto ODP Survey</span>
                        </div>
                    </button>
                    @endif
                    @if($surveyReport->house_photo)
                    <button type="button" @click="$dispatch('open-image-preview', { url: '{{ asset('storage/'.$surveyReport->house_photo) }}', label: 'Foto Rumah Pelanggan' })" class="group relative block w-full rounded-lg overflow-hidden aspect-square bg-slate-100 dark:bg-slate-800 border border-border hover:border-sky-500 transition-all">
                        <img src="{{ asset('storage/'.$surveyReport->house_photo) }}" alt="Foto Rumah Customer" class="h-full w-full object-cover">
                        <div class="absolute inset-0 flex items-end justify-center p-2 bg-slate-900/0 group-hover:bg-slate-900/60 backdrop-blur-0 group-hover:backdrop-blur-[2px] opacity-0 group-hover:opacity-100 transition-all duration-300 cursor-zoom-in">
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
        <div class="pt-5 border-t border-border space-y-4">
            <div class="flex items-center gap-2 mb-1">
                <svg class="h-4 w-4 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h3 class="text-xs font-bold uppercase tracking-wider text-text-main font-ui">Laporan Hasil Pemasangan (PSB)</h3>
            </div>

            @if($installReport)
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                <div class="bg-slate-50/60 dark:bg-slate-800/40 border border-border rounded-lg p-3">
                    <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">Status Pemasangan</span>
                    <span class="text-xs font-bold block mt-0.5 font-ui {{ $installReport->installation_status === 'completed' ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                        {{ $installReport->installation_status === 'completed' ? 'PEMASANGAN SELESAI' : strtoupper($installReport->installation_status) }}
                    </span>
                </div>
                <div class="bg-slate-50/60 dark:bg-slate-800/40 border border-border rounded-lg p-3">
                    <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">Waktu Selesai Pemasangan</span>
                    <span class="text-xs font-bold font-mono text-text-main block mt-0.5">{{ $installReport->completed_at?->translatedFormat('d M Y — H:i') ?: '-' }} WIB</span>
                </div>
            </div>

            @if($installReport->installation_note || $installReport->notes)
            <div class="bg-slate-50/70 dark:bg-slate-800/50 border border-border rounded-xl p-4 shadow-xs min-w-0 max-w-full overflow-hidden">
                <div class="flex items-center gap-1.5 mb-2">
                    <svg class="h-3.5 w-3.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    <span class="text-[10px] text-text-muted font-bold uppercase tracking-wider font-ui">Catatan Pemasangan Lapangan</span>
                </div>
                <p class="text-xs text-text-main leading-relaxed font-ui whitespace-pre-line break-words [word-break:break-word] min-w-0">{{ $installReport->installation_note ?: $installReport->notes }}</p>
            </div>
            @endif
            @endif

            @if($installMaterials->isNotEmpty())
            <div>
                <span class="block text-[10px] text-text-muted font-bold uppercase tracking-wider font-ui mb-2">Material Pemasangan Terpakai</span>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    @foreach($installMaterials as $material)
                    <div class="flex justify-between items-center bg-slate-50/60 dark:bg-slate-800/40 border border-border p-2.5 rounded-lg">
                        <span class="text-text-secondary font-ui font-medium">{{ $material->item_name }}@if($material->note)<span class="text-text-muted text-[10px]"> · {{ $material->note }}</span>@endif</span>
                        <span class="font-mono font-bold text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/60 px-2 py-0.5 rounded border border-sky-200 dark:border-sky-800">{{ rtrim(rtrim(number_format($material->qty, 2, ',', '.'), '0'), ',') }} {{ $material->unit }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            @if($installReport?->installation_photo || $installReport?->contract_photo || $installReport?->signature_photo)
            <div x-data class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs">
                <div class="flex items-center gap-1.5 px-3.5 py-2.5 border-b border-border bg-slate-50/60 dark:bg-slate-800/40">
                    <svg class="h-3.5 w-3.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span class="text-[10px] text-text-muted font-bold uppercase tracking-wider font-ui">Foto Hasil Pemasangan &amp; Berita Acara</span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5">
                    @if($installReport->installation_photo)
                    <button type="button" @click="$dispatch('open-image-preview', { url: '{{ asset('storage/'.$installReport->installation_photo) }}', label: 'Foto Bukti Pemasangan' })" class="group relative block w-full rounded-lg overflow-hidden aspect-square bg-slate-100 dark:bg-slate-800 border border-border hover:border-sky-500 transition-all">
                        <img src="{{ asset('storage/'.$installReport->installation_photo) }}" alt="Foto Pemasangan" class="h-full w-full object-cover">
                        <div class="absolute inset-0 flex items-end justify-center p-2 bg-slate-900/0 group-hover:bg-slate-900/60 backdrop-blur-0 group-hover:backdrop-blur-[2px] opacity-0 group-hover:opacity-100 transition-all duration-300 cursor-zoom-in">
                            <span class="text-white text-[10px] font-bold text-center font-ui">Foto Bukti Pemasangan</span>
                        </div>
                    </button>
                    @endif
                    @if($installReport->contract_photo)
                    <button type="button" @click="$dispatch('open-image-preview', { url: '{{ asset('storage/'.$installReport->contract_photo) }}', label: 'Foto Kontrak / BA' })" class="group relative block w-full rounded-lg overflow-hidden aspect-square bg-slate-100 dark:bg-slate-800 border border-border hover:border-sky-500 transition-all">
                        <img src="{{ asset('storage/'.$installReport->contract_photo) }}" alt="Foto Kontrak/BA" class="h-full w-full object-cover">
                        <div class="absolute inset-0 flex items-end justify-center p-2 bg-slate-900/0 group-hover:bg-slate-900/60 backdrop-blur-0 group-hover:backdrop-blur-[2px] opacity-0 group-hover:opacity-100 transition-all duration-300 cursor-zoom-in">
                            <span class="text-white text-[10px] font-bold text-center font-ui">Foto Kontrak / BA</span>
                        </div>
                    </button>
                    @endif
                    @if($installReport->signature_photo)
                    <button type="button" @click="$dispatch('open-image-preview', { url: '{{ asset('storage/'.$installReport->signature_photo) }}', label: 'Foto Tanda Tangan' })" class="group relative block w-full rounded-lg overflow-hidden aspect-square bg-slate-100 dark:bg-slate-800 border border-border hover:border-sky-500 transition-all">
                        <img src="{{ asset('storage/'.$installReport->signature_photo) }}" alt="Foto Tanda Tangan" class="h-full w-full object-cover">
                        <div class="absolute inset-0 flex items-end justify-center p-2 bg-slate-900/0 group-hover:bg-slate-900/60 backdrop-blur-0 group-hover:backdrop-blur-[2px] opacity-0 group-hover:opacity-100 transition-all duration-300 cursor-zoom-in">
                            <span class="text-white text-[10px] font-bold text-center font-ui">Foto Tanda Tangan</span>
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
        <div class="pt-5 border-t border-border space-y-4">
            <div class="flex items-center gap-2 mb-1">
                <svg class="h-4 w-4 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h3 class="text-xs font-bold uppercase tracking-wider text-text-main font-ui">Laporan Pekerjaan Teknisi</h3>
            </div>

            @if($maintenanceReport?->kendala_teknis)
            <div class="bg-slate-50/70 dark:bg-slate-800/50 border border-border rounded-xl p-4 shadow-xs min-w-0 max-w-full overflow-hidden">
                <div class="flex items-center gap-1.5 mb-2">
                    <svg class="h-3.5 w-3.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    <span class="text-[10px] text-text-muted font-bold uppercase tracking-wider font-ui">Kendala &amp; Solusi</span>
                </div>
                <p class="text-xs text-text-main leading-relaxed font-ui whitespace-pre-line break-words [word-break:break-word] min-w-0">{{ $maintenanceReport->kendala_teknis }}</p>
            </div>
            @endif

            @if($materialsTerpakai->isNotEmpty())
            <div>
                <span class="block text-[10px] text-text-muted font-bold uppercase tracking-wider font-ui mb-2">Material Terpakai</span>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    @foreach($materialsTerpakai as $material)
                    <div class="flex justify-between items-center bg-slate-50/60 dark:bg-slate-800/40 border border-border p-2.5 rounded-lg">
                        <span class="text-text-secondary font-ui font-medium">{{ $material->item_name }}@if($material->note)<span class="text-text-muted text-[10px]"> · {{ $material->note }}</span>@endif</span>
                        <span class="font-mono font-bold text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/60 px-2 py-0.5 rounded border border-sky-200 dark:border-sky-800">{{ rtrim(rtrim(number_format($material->qty, 2, ',', '.'), '0'), ',') }} {{ $material->unit }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            @if($maintenanceReport?->opm_photo || $maintenanceReport?->speedtest_photo)
            <div x-data class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs">
                <div class="flex items-center gap-1.5 px-3.5 py-2.5 border-b border-border bg-slate-50/60 dark:bg-slate-800/40">
                    <svg class="h-3.5 w-3.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span class="text-[10px] text-text-muted font-bold uppercase tracking-wider font-ui">Foto OPM &amp; Speedtest</span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5">
                    @if($maintenanceReport->opm_photo)
                    <button type="button" @click="$dispatch('open-image-preview', { url: '{{ asset('storage/'.$maintenanceReport->opm_photo) }}', label: 'Foto OPM' })" class="group relative block w-full rounded-lg overflow-hidden aspect-square bg-slate-100 dark:bg-slate-800 border border-border hover:border-sky-500 transition-all">
                        <img src="{{ asset('storage/'.$maintenanceReport->opm_photo) }}" alt="Foto OPM" class="h-full w-full object-cover">
                        <div class="absolute inset-0 flex items-end justify-center p-2 bg-slate-900/0 group-hover:bg-slate-900/60 backdrop-blur-0 group-hover:backdrop-blur-[2px] opacity-0 group-hover:opacity-100 transition-all duration-300 cursor-zoom-in">
                            <span class="text-white text-[10px] font-bold text-center font-ui">Foto OPM</span>
                        </div>
                    </button>
                    @endif
                    @if($maintenanceReport->speedtest_photo)
                    <button type="button" @click="$dispatch('open-image-preview', { url: '{{ asset('storage/'.$maintenanceReport->speedtest_photo) }}', label: 'Foto Speedtest' })" class="group relative block w-full rounded-lg overflow-hidden aspect-square bg-slate-100 dark:bg-slate-800 border border-border hover:border-sky-500 transition-all">
                        <img src="{{ asset('storage/'.$maintenanceReport->speedtest_photo) }}" alt="Foto Speedtest" class="h-full w-full object-cover">
                        <div class="absolute inset-0 flex items-end justify-center p-2 bg-slate-900/0 group-hover:bg-slate-900/60 backdrop-blur-0 group-hover:backdrop-blur-[2px] opacity-0 group-hover:opacity-100 transition-all duration-300 cursor-zoom-in">
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
    </div>

    {{-- ══ Action Buttons ════════════════════════════════════════════ --}}
    @if(in_array($task->status->value, ['terjadwal', 'in_progress', 'pending']))
    <div class="flex flex-wrap items-center justify-end gap-2.5 pt-1.5 font-ui">
        @can('statusReschedule', $task)
        <button type="button" x-data @click="$dispatch('open-modal', 'reschedule-task-{{ $task->id }}')"
                class="inline-flex items-center gap-1.5 text-xs font-semibold px-3.5 py-2 rounded border bg-white dark:bg-slate-800 transition-colors hover:bg-warning/5 cursor-pointer"
                style="border-color:var(--color-warning-border); color:var(--color-warning)">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Pending
        </button>
        @endcan
        @if($task->status->value === 'terjadwal')
            @if($task->scheduled_at && !$task->scheduled_at->startOfDay()->isFuture())
            @if($task->task_type->value === \App\Enums\TaskType::SURVEY->value)
                @if($task->customer_id && auth()->user()->hasPermission('customers.detail.survey.update') && $task->teamMembers->pluck('user_id')->contains(auth()->id()))
                <form action="{{ route('customers.survey.start', $task->customer_id) }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 text-xs font-semibold px-4 py-2 rounded text-white transition-colors cursor-pointer"
                            style="background:var(--color-warning)">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                        </svg>
                        Mulai Survey
                    </button>
                </form>
                @endif
            @elseif($task->task_type->value === \App\Enums\TaskType::PEMASANGAN->value)
                @if($task->customer_id && auth()->user()->hasPermission('customers.detail.installation.update') && $task->teamMembers->pluck('user_id')->contains(auth()->id()))
                <form action="{{ route('customers.installation.start', $task->customer_id) }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 text-xs font-semibold px-4 py-2 rounded text-white transition-colors cursor-pointer"
                            style="background:var(--color-warning)">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                        </svg>
                        Mulai Pemasangan
                    </button>
                </form>
                @endif
            @else
                @can('statusStart', $task)
                <form action="{{ route('tasks.start', $task) }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 text-xs font-semibold px-4 py-2 rounded text-white transition-colors cursor-pointer"
                            style="background:var(--color-warning)">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                        </svg>
                        {{ $task->task_type->value === \App\Enums\TaskType::MAINTENANCE->value ? 'Mulai Maintenance' : 'Mulai Task' }}
                    </button>
                </form>
                @endcan
            @endif
            @else
            <span class="text-xs text-text-muted px-3.5 py-2 border border-border rounded bg-surface">
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
                <x-task.report-choice-dialog :task="$task" :report-url="$reportUrl">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ $reportLabel }}
                </x-task.report-choice-dialog>
            @else
                <a href="{{ $reportUrl }}"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold px-4 py-2 rounded text-white transition-colors cursor-pointer"
                        style="background:var(--color-success)">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Lanjutkan Laporan
                </a>
            @endif
        @endif
        @endcan
    </div>
    @endif

    {{-- ══ Action Buttons (FOP Manage) ════════════════════════════════ --}}
    @if(in_array($task->status->value, ['pending', 'terjadwal']))
    @if(auth()->user()->can('fopReject', $task) || auth()->user()->can('fopPending', $task))
    <div class="bg-surface border border-border rounded-lg p-4 flex flex-wrap gap-3 items-center justify-between shadow-sm">
        <div>
            <h4 class="text-xs font-semibold text-text-main mb-0.5 font-ui">Manajemen Task (FOP)</h4>
            <p class="text-[11px] text-text-muted font-ui">Kelola task sebelum mulai dikerjakan oleh teknisi.</p>
        </div>
        <div class="flex items-center gap-2">
            @if($task->status->value === 'pending')
                @can('fopReject', $task)
                <button x-data @click="$dispatch('open-modal', 'fop-reject-task-pending')"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 border bg-white dark:bg-slate-800 transition-colors hover:bg-error/5 cursor-pointer font-ui"
                        style="border-color:var(--color-error-border); color:var(--color-error)">
                    Reject Task
                </button>
                @endcan
            @endif

            @if($task->status->value === 'terjadwal')
                @can('fopPending', $task)
                <button x-data @click="$dispatch('open-modal', 'fop-pending-task')"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 border bg-white dark:bg-slate-800 transition-colors hover:bg-warning/5 cursor-pointer font-ui"
                        style="border-color:var(--color-warning-border); color:var(--color-warning)">
                    Set Pending
                </button>
                @endcan
            @endif
        </div>
    </div>
    @endif
    @endif

    {{-- ══ Action Buttons (FOP Review) ════════════════════════════════ --}}
    @if($task->status->value === 'selesai' && $task->fop_review_status === 'pending')
    @can('review', $task)
    @if($task->task_type->value === 'PSB')
    <div class="bg-surface border border-border rounded-lg p-4 flex flex-wrap gap-3 items-center justify-between shadow-sm">
        <div>
            <h4 class="text-xs font-semibold text-text-main mb-0.5 font-ui font-ui">Approve Pemasangan Lewat Verifikasi Admin</h4>
            <p class="text-[11px] text-text-muted font-ui">Aktivasi pelanggan (CID + tagihan awal) hanya bisa diproses di halaman Verifikasi Admin, bukan dari sini.</p>
        </div>
        @if($task->customer_id)
            @if(auth()->user()->hasPermission('customers.detail.installation.validate') || auth()->user()->hasFullAccess())
            <a href="{{ route('customers.verification.admin', $task->customer_id) }}"
               class="inline-flex items-center gap-1.5 text-xs font-semibold px-4 py-2 rounded text-white transition-colors cursor-pointer font-ui"
               style="background:var(--color-primary)">
                Buka Verifikasi Admin
            </a>
            @else
            <a href="{{ route('customers.installation.report', ['customer' => $task->customer_id, 'return_to' => route('tasks.show', $task)]) }}"
               class="inline-flex items-center gap-1.5 text-xs font-semibold px-4 py-2 rounded text-white transition-colors cursor-pointer font-ui"
               style="background:var(--color-primary)">
                Lihat Laporan Pemasangan
            </a>
            @endif
        @endif
    </div>
    @else
    <div class="bg-surface border border-border rounded-lg p-4 flex flex-wrap gap-3 items-center justify-between shadow-sm">
        <div>
            <h4 class="text-xs font-semibold text-text-main mb-0.5 font-ui font-ui">Review Hasil & Tandai Selesai (Khusus FOP)</h4>
            <p class="text-[11px] text-text-muted font-ui">Task ini telah diselesaikan oleh teknisi dan menunggu persetujuan Anda.</p>
        </div>
        <div class="flex items-center gap-2">
            <button x-data @click="$dispatch('open-modal', 'reject-task')"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 border bg-white dark:bg-slate-800 transition-colors cursor-pointer font-ui"
                    style="border-color:var(--color-error-border); color:var(--color-error)">
                Reject
            </button>
            <form action="{{ route('tasks.review', $task) }}" method="POST">
                @csrf
                <input type="hidden" name="action" value="approve">
                <button type="submit"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold px-4 py-2 rounded text-white transition-colors cursor-pointer font-ui"
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
    <p class="text-xs text-text-secondary mb-3 font-ui font-ui">
        Task ini akan dikembalikan ke status <span class="font-semibold text-text-main">In Progress</span>. 
        Teknisi harus memperbaiki laporan berdasarkan alasan reject.
    </p>
    <form id="form-reject-task" action="{{ route('tasks.review', $task) }}" method="POST">
        @csrf
        <input type="hidden" name="action" value="reject">
        <div class="space-y-1.5 font-ui">
            <label class="block text-[11px] font-semibold uppercase tracking-wider text-text-muted font-ui">Alasan Reject <span class="text-error">*</span></label>
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
    <p class="text-xs text-text-secondary mb-3 font-ui font-ui">
        Task <span class="font-mono font-semibold">{{ $task->task_number }}</span> akan dibatalkan.
        Tindakan ini tidak dapat dibatalkan.
    </p>
    <form id="form-cancel-task" action="{{ route('tasks.cancel', $task) }}" method="POST">
        @csrf
        <div class="space-y-1.5 font-ui">
            <label class="block text-[11px] font-semibold uppercase tracking-wider text-text-muted font-ui">Alasan Pembatalan <span class="text-error">*</span></label>
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
