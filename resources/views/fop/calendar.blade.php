@extends('layouts.app')

@section('title', 'FOP — Task Scheduler')
@section('page_title', 'FOP — Task Scheduler')

@section('content')
@php
    $totalCompletedChecklists = collect($tasksMap)->sum('checklist_completed');
    $totalAllChecklists       = collect($tasksMap)->sum('checklist_total');
@endphp

<div x-data="fopCalendarHandler()" class="flex flex-col gap-0">

    {{-- ══ PAGE HEADER (naked — no card per Design.md §1.7) ═════════ --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
        <div class="flex items-center gap-3">
            <svg class="h-6 w-6 shrink-0" style="color:var(--color-primary)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h18M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <div>
                <h1 class="text-xl font-bold text-text-main leading-tight">FOP — Task Scheduler</h1>
                <p class="text-xs text-text-muted mt-0.5">{{ $startDate->translatedFormat('d F Y') }} — {{ $endDate->translatedFormat('d F Y') }}</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            {{-- Progress checklist pill --}}
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border text-xs font-medium" style="background:var(--color-surface); border-color:var(--color-border); color:var(--color-text-secondary)">
                <span class="w-2 h-2 rounded-full" style="background:var(--color-success)"></span>
                Progress checklist <strong class="ml-1 font-mono" style="color:var(--color-text-main)">{{ $totalCompletedChecklists }}/{{ $totalAllChecklists }}</strong>
            </div>

            {{-- Mode toggle --}}
            <div class="inline-flex rounded-lg p-0.5 border" style="background:var(--color-background); border-color:var(--color-border)">
                <button @click="viewMode = 'weekly'"
                        :class="viewMode === 'weekly' ? 'text-white shadow-sm font-semibold' : 'font-medium'"
                        :style="viewMode === 'weekly' ? 'background:var(--color-primary); color:#fff' : 'color:var(--color-text-muted)'"
                        class="px-3 py-1 rounded-md text-xs transition-all cursor-pointer">
                    Mingguan
                </button>
                <button @click="viewMode = 'monthly'"
                        :class="viewMode === 'monthly' ? 'font-semibold' : 'font-medium'"
                        :style="viewMode === 'monthly' ? 'background:var(--color-primary); color:#fff' : 'color:var(--color-text-muted)'"
                        class="px-3 py-1 rounded-md text-xs transition-all cursor-pointer">
                    Bulanan
                </button>
            </div>

            {{-- Action button --}}
            @if(auth()->user()->hasPermission('task.create') || auth()->user()->hasPermission('task.schedule') || auth()->user()->hasPermission('task.assign.team') || auth()->user()->hasPermission('tasks.create') || auth()->user()->hasPermission('tasks.assign'))
                <button @click="openScheduleModal('{{ Carbon\Carbon::now()->format('Y-m-d') }}')"
                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-white font-semibold text-xs shadow-sm cursor-pointer transition-all"
                        style="background:var(--color-primary)">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    + Jadwalkan Task
                </button>
            @endif
        </div>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
        <div class="mb-4 p-3 rounded-lg flex items-center justify-between text-sm" style="background:#F0FDF4; border:1px solid #BBF7D0; color:var(--color-success)">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                <span class="text-xs font-medium">{!! session('success') !!}</span>
            </div>
            <button @click="$el.parentElement.remove()" class="ml-4 cursor-pointer" style="color:var(--color-success)">&times;</button>
        </div>
    @endif
    @if($errors->any())
        <div class="mb-4 p-3 rounded-lg flex items-start justify-between" style="background:#FEF2F2; border:1px solid #FECACA; color:var(--color-error)">
            <ul class="text-xs space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            <button @click="$el.parentElement.remove()" class="ml-4 cursor-pointer" style="color:var(--color-error)">&times;</button>
        </div>
    @endif

    {{-- ══ MAIN 2-COLUMN LAYOUT ══════════════════════════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">

        {{-- ══ SIDEBAR KIRI (3 cols) ═════════════════════════════════ --}}
        <div class="lg:col-span-3 space-y-4">

            {{-- RINGKASAN HARI INI --}}
            <div class="rounded-lg border" style="background:var(--color-surface); border-color:var(--color-border)">
                <div class="px-4 pt-3 pb-2 border-b" style="border-color:var(--color-border)">
                    <p class="text-[11px] font-bold uppercase tracking-widest" style="color:var(--color-text-muted)">RINGKASAN HARI INI</p>
                </div>
                <div class="grid grid-cols-2 divide-x divide-y" style="--tw-divide-opacity:1">
                    <div class="p-3">
                        <p class="text-[10px] font-semibold uppercase tracking-wider" style="color:var(--color-text-muted)">Total task</p>
                        <p class="text-2xl font-bold font-mono mt-0.5" style="color:var(--color-text-main)">{{ $stats['total'] }}</p>
                    </div>
                    <div class="p-3">
                        <p class="text-[10px] font-semibold uppercase tracking-wider" style="color:var(--color-text-muted)">Selesai</p>
                        <p class="text-2xl font-bold font-mono mt-0.5" style="color:var(--color-success)">{{ $stats['completed'] }}</p>
                    </div>
                    <div class="p-3 border-t" style="border-color:var(--color-border)">
                        <p class="text-[10px] font-semibold uppercase tracking-wider" style="color:var(--color-text-muted)">Pending</p>
                        <p class="text-2xl font-bold font-mono mt-0.5" style="color:var(--color-warning)">{{ $stats['pending'] }}</p>
                    </div>
                    <div class="p-3 border-t" style="border-color:var(--color-border)">
                        <p class="text-[10px] font-semibold uppercase tracking-wider" style="color:var(--color-text-muted)">Dibatalkan</p>
                        <p class="text-2xl font-bold font-mono mt-0.5" style="color:var(--color-error)">{{ $stats['cancelled'] }}</p>
                    </div>
                </div>
            </div>

            {{-- TIM AKTIF --}}
            <div class="rounded-lg border" style="background:var(--color-surface); border-color:var(--color-border)">
                <div class="px-4 pt-3 pb-2 border-b flex items-center justify-between" style="border-color:var(--color-border)">
                    <p class="text-[11px] font-bold uppercase tracking-widest" style="color:var(--color-text-muted)">TIM AKTIF</p>
                    <span class="text-[10px] font-mono px-2 py-0.5 rounded-full" style="background:var(--color-background); color:var(--color-text-muted); border:1px solid var(--color-border)">{{ $activeTeams->count() }} Tim</span>
                </div>
                <div class="p-3 space-y-2 max-h-80 overflow-y-auto">
                    @forelse($activeTeams as $team)
                        <div @click="selectTeam({{ $team['id'] }})"
                             :class="selectedTeamId === {{ $team['id'] }} ? 'ring-2' : ''"
                             :style="selectedTeamId === {{ $team['id'] }} ? 'ring-color:var(--color-primary); background:var(--color-primary-soft)' : ''"
                             class="p-3 rounded-lg border cursor-pointer transition-all group"
                             style="border-color:var(--color-border)">
                            <div class="flex items-center justify-between gap-2">
                                {{-- Avatars --}}
                                <div class="flex -space-x-1.5 overflow-hidden shrink-0">
                                    @foreach($team['avatars'] as $avatar)
                                        <div class="inline-flex h-7 w-7 rounded-full items-center justify-center text-[10px] font-bold text-white ring-2 ring-white"
                                             style="background:var(--color-primary)" title="{{ $avatar['name'] }}">
                                            {{ $avatar['initials'] }}
                                        </div>
                                    @endforeach
                                    @if($team['extraCount'] > 0)
                                        <div class="inline-flex h-7 w-7 rounded-full items-center justify-center text-[9px] font-bold ring-2 ring-white"
                                             style="background:var(--color-surface-muted); color:var(--color-text-muted)">
                                            +{{ $team['extraCount'] }}
                                        </div>
                                    @endif
                                </div>

                                {{-- Info --}}
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-bold truncate" style="color:var(--color-text-main)">{{ $team['name'] }}</p>
                                    <p class="text-[11px] mt-0.5" style="color:var(--color-text-muted)">
                                        <span style="color:var(--color-text-secondary); font-weight:600">{{ $team['taskCount'] }}</span> task hari ini ·
                                        <span style="color:var(--color-success); font-weight:600">{{ $team['completedCount'] }}</span> selesai
                                    </p>
                                </div>

                                {{-- Status dot --}}
                                <span class="w-2.5 h-2.5 rounded-full shrink-0"
                                      style="{{ $team['status'] === 'active' ? 'background:var(--color-success)' : 'background:var(--color-border)' }}"
                                      title="{{ $team['status'] === 'active' ? 'Sedang bertugas' : 'Standby' }}"></span>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-center py-4" style="color:var(--color-text-muted)">Tidak ada tim aktif periode ini</p>
                    @endforelse
                </div>
            </div>

            {{-- KETERANGAN --}}
            <div class="rounded-lg border" style="background:var(--color-surface); border-color:var(--color-border)">
                <div class="px-4 pt-3 pb-2 border-b" style="border-color:var(--color-border)">
                    <p class="text-[11px] font-bold uppercase tracking-widest" style="color:var(--color-text-muted)">KETERANGAN</p>
                </div>
                <div class="p-3 grid grid-cols-2 gap-2">
                    <div class="flex items-center gap-2 text-xs" style="color:var(--color-text-secondary)">
                        <span class="w-3 h-3 rounded shrink-0" style="background:#BAE6FD"></span> Survey
                    </div>
                    <div class="flex items-center gap-2 text-xs" style="color:var(--color-text-secondary)">
                        <span class="w-3 h-3 rounded shrink-0" style="background:#BBF7D0"></span> Pemasangan
                    </div>
                    <div class="flex items-center gap-2 text-xs" style="color:var(--color-text-secondary)">
                        <span class="w-3 h-3 rounded shrink-0" style="background:#FDE68A"></span> Maintenance
                    </div>
                    <div class="flex items-center gap-2 text-xs" style="color:var(--color-text-secondary)">
                        <span class="w-3 h-3 rounded shrink-0" style="background:#FBCFE8"></span> Ambil modem
                    </div>
                </div>
            </div>

        </div>{{-- /sidebar --}}

        {{-- ══ AREA KALENDER UTAMA (9 cols) ═════════════════════════ --}}
        <div class="lg:col-span-9 flex flex-col gap-4">

            {{-- Navigation bar --}}
            <div class="flex items-center justify-between rounded-lg px-4 py-3 border" style="background:var(--color-surface); border-color:var(--color-border)">
                <a href="{{ route('fop.calendar', ['start_date' => $startDate->copy()->subWeek()->toDateString()]) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors border cursor-pointer"
                   style="background:var(--color-background); border-color:var(--color-border); color:var(--color-text-secondary)">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                    Prev
                </a>

                <span class="text-sm font-bold font-mono" style="color:var(--color-text-main)">
                    {{ $startDate->translatedFormat('d M') }} – {{ $endDate->translatedFormat('d M Y') }}
                </span>

                <a href="{{ route('fop.calendar', ['start_date' => $startDate->copy()->addWeek()->toDateString()]) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors border cursor-pointer"
                   style="background:var(--color-background); border-color:var(--color-border); color:var(--color-text-secondary)">
                    Next
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            </div>

            {{-- 7-Day Grid --}}
            <div class="grid grid-cols-1 md:grid-cols-7 gap-0 rounded-lg border overflow-hidden" style="border-color:var(--color-border)">

                {{-- Column headers --}}
                @foreach($days as $dayKey => $dayData)
                    @php $isToday = $dayData['date']->isToday(); @endphp
                    <div class="border-b border-r last:border-r-0 text-center py-2.5 px-1"
                         style="border-color:var(--color-border); {{ $isToday ? 'background:var(--color-primary-soft)' : 'background:var(--color-background)' }}">
                        <p class="text-[10px] font-bold uppercase tracking-wider {{ $isToday ? '' : '' }}"
                           style="{{ $isToday ? 'color:var(--color-primary)' : 'color:var(--color-text-muted)' }}">
                            {{ $dayData['dayName'] }}
                        </p>
                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-sm font-mono font-bold mt-0.5"
                              style="{{ $isToday ? 'background:var(--color-primary); color:#fff' : 'color:var(--color-text-main)' }}">
                            {{ $dayData['dayNum'] }}
                        </span>
                    </div>
                @endforeach

                {{-- Task cells per day --}}
                @foreach($days as $dayKey => $dayData)
                    @php $isToday = $dayData['date']->isToday(); @endphp
                    <div class="border-r last:border-r-0 flex flex-col min-h-[300px]" style="border-color:var(--color-border); background:var(--color-surface)">

                        {{-- Tasks list --}}
                        <div class="p-1.5 flex-1 space-y-1.5 overflow-y-auto max-h-[420px]">
                            @forelse($dayData['tasks'] as $task)
                                @php
                                    $typeVal = $task->task_type->value;
                                    [$bg, $border, $text, $badgeBg, $badgeText] = match($typeVal) {
                                        'survey'      => ['#F0F9FF', '#BAE6FD', '#0369A1', '#DBEAFE', '#1D4ED8'],
                                        'pemasangan'  => ['#F0FDF4', '#BBF7D0', '#15803D', '#DCFCE7', '#166534'],
                                        'maintenance' => ['#FFFBEB', '#FDE68A', '#B45309', '#FEF3C7', '#92400E'],
                                        'ambil_modem' => ['#FDF4FF', '#F5D0FE', '#9333EA', '#FAE8FF', '#7E22CE'],
                                        default       => ['#F8FAFC', '#E2E8F0', '#475569', '#F1F5F9', '#334155'],
                                    };
                                    $teamNames = $task->teamMembers->map(fn($m) => explode(' ', $m->user?->name ?? '?')[0])->join(', ');
                                @endphp

                                <div @click="openTaskDetail({{ $task->id }})"
                                     :class="selectedTaskId === {{ $task->id }} ? 'ring-2' : ''"
                                     :style="selectedTaskId === {{ $task->id }} ? 'ring-color:var(--color-primary)' : ''"
                                     class="p-2 rounded border cursor-pointer transition-all text-left group"
                                     style="background:{{ $bg }}; border-color:{{ $border }}">

                                    <div class="flex items-start justify-between gap-1 mb-1">
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase"
                                              style="background:{{ $badgeBg }}; color:{{ $badgeText }}">
                                            {{ $task->task_type->label() }}
                                        </span>
                                        @if($task->scheduled_at)
                                            <span class="text-[10px] font-mono shrink-0" style="color:{{ $text }}; opacity:.8">
                                                {{ $task->scheduled_at->format('H:i') }}
                                            </span>
                                        @endif
                                    </div>

                                    <p class="text-xs font-bold leading-snug line-clamp-2" style="color:{{ $text }}">
                                        {{ $task->title }}
                                    </p>

                                    @if($teamNames)
                                        <p class="text-[10px] mt-1 truncate" style="color:{{ $text }}; opacity:.75">{{ $teamNames }}</p>
                                    @endif
                                </div>
                            @empty
                                <div class="h-full flex items-start justify-center pt-6">
                                    <span class="text-[11px] font-mono" style="color:var(--color-border)">—</span>
                                </div>
                            @endforelse
                        </div>

                        {{-- + Task quick add --}}
                        <div class="p-1.5 border-t" style="border-color:var(--color-border)">
                            @if(auth()->user()->hasPermission('task.create') || auth()->user()->hasPermission('task.schedule') || auth()->user()->hasPermission('task.assign.team') || auth()->user()->hasPermission('tasks.create') || auth()->user()->hasPermission('tasks.assign'))
                                <button type="button" @click="openScheduleModal('{{ $dayKey }}')"
                                        class="w-full py-1.5 rounded border border-dashed text-[11px] font-medium flex items-center justify-center gap-1 transition-colors cursor-pointer"
                                        style="border-color:var(--color-border); color:var(--color-text-muted)"
                                        onmouseover="this.style.borderColor='var(--color-primary)';this.style.color='var(--color-primary)'"
                                        onmouseout="this.style.borderColor='var(--color-border)';this.style.color='var(--color-text-muted)'">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                    </svg>
                                    + Task
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- ══ DETAIL PANEL (di bawah kalender, muncul saat task diklik) ══ --}}
            <div x-show="selectedTaskId" x-cloak
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="rounded-lg border relative"
                 style="background:var(--color-surface); border-color:var(--color-border)">

                {{-- Header detail --}}
                <div class="px-5 py-3 border-b flex items-center justify-between" style="border-color:var(--color-border)">
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-bold" style="color:var(--color-text-main)" x-text="selectedTask?.title"></span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase"
                              style="background:var(--color-primary-soft); color:var(--color-primary)"
                              x-text="selectedTask?.status_label"></span>
                    </div>
                    <button @click="selectedTaskId = null; selectedTask = null"
                            class="p-1.5 rounded cursor-pointer transition-colors"
                            style="color:var(--color-text-muted)"
                            onmouseover="this.style.background='var(--color-background)'"
                            onmouseout="this.style.background='transparent'">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {{-- Detail body: 3 columns --}}
                <div class="grid grid-cols-1 md:grid-cols-3 divide-y md:divide-y-0 md:divide-x" style="--tw-divide-opacity:1; border-color:var(--color-border)">

                    {{-- Kolom 1: Info utama --}}
                    <div class="p-5 space-y-3">
                        <p class="text-[10px] font-bold uppercase tracking-wider" style="color:var(--color-text-muted)">INFO TASK</p>
                        <div class="space-y-2">
                            <div class="flex justify-between text-xs">
                                <span style="color:var(--color-text-muted)">Tim</span>
                                <span class="font-semibold text-right" style="color:var(--color-text-main)" x-text="selectedTask?.team_names || '—'"></span>
                            </div>
                            <div class="flex justify-between text-xs border-t pt-2" style="border-color:var(--color-border)">
                                <span style="color:var(--color-text-muted)">Teknisi</span>
                                <span class="font-semibold text-right" style="color:var(--color-text-main)" x-text="selectedTask?.team_names || '—'"></span>
                            </div>
                            <div class="flex justify-between text-xs border-t pt-2" style="border-color:var(--color-border)">
                                <span style="color:var(--color-text-muted)">Jadwal</span>
                                <span class="font-mono text-right" style="color:var(--color-text-main)" x-text="selectedTask?.scheduled_at || '—'"></span>
                            </div>
                            <div class="flex justify-between text-xs border-t pt-2" style="border-color:var(--color-border)">
                                <span style="color:var(--color-text-muted)">Tipe</span>
                                <span class="font-semibold uppercase text-right" style="color:var(--color-text-main)" x-text="selectedTask?.task_type_label || '—'"></span>
                            </div>
                        </div>
                        <div class="pt-2">
                            <a :href="`/tasks/${selectedTask?.id}`"
                               class="inline-flex items-center gap-1 text-xs font-semibold underline"
                               style="color:var(--color-primary)">
                                Buka Halaman Task
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                            </a>
                        </div>
                    </div>

                    {{-- Kolom 2: Lokasi & pelanggan --}}
                    <div class="p-5 space-y-3">
                        <p class="text-[10px] font-bold uppercase tracking-wider" style="color:var(--color-text-muted)">INFO PELANGGAN & LOKASI</p>
                        <div class="space-y-2">
                            <div class="text-xs border-b pb-2" style="border-color:var(--color-border)">
                                <span style="color:var(--color-text-muted)">Alamat Lengkap</span>
                                <p class="font-medium mt-0.5 leading-relaxed" style="color:var(--color-text-main)" x-text="selectedTask?.customer_address || '—'"></p>
                            </div>
                            <div class="text-xs border-b pb-2" style="border-color:var(--color-border)">
                                <span style="color:var(--color-text-muted)">POP / Cabang</span>
                                <p class="font-medium mt-0.5" style="color:var(--color-text-main)" x-text="selectedTask?.pop_name || '—'"></p>
                            </div>
                            <div class="text-xs">
                                <span style="color:var(--color-text-muted)">No. Pelanggan</span>
                                <p class="font-mono font-medium mt-0.5" style="color:var(--color-text-main)" x-text="selectedTask?.customer_number || '—'"></p>
                            </div>
                        </div>
                    </div>

                    {{-- Kolom 3: Checklist progress --}}
                    <div class="p-5 space-y-3">
                        <div class="flex items-center justify-between">
                            <p class="text-[10px] font-bold uppercase tracking-wider" style="color:var(--color-text-muted)">PROGRESS CHECKLIST</p>
                            <span class="text-xs font-mono font-bold" style="color:var(--color-success)"
                                  x-text="`${selectedTask?.checklist_completed || 0}/${selectedTask?.checklist_total || 0}`"></span>
                        </div>

                        <div class="w-full rounded-full h-1.5 overflow-hidden" style="background:var(--color-background)">
                            <div class="h-full rounded-full transition-all duration-500"
                                 style="background:var(--color-success)"
                                 :style="`width: ${(selectedTask?.checklist_completed || 0) / (selectedTask?.checklist_total || 1) * 100}%`"></div>
                        </div>

                        <div class="space-y-1.5 max-h-36 overflow-y-auto">
                            <template x-if="selectedTask && selectedTask.checklists && selectedTask.checklists.length > 0">
                                <template x-for="item in selectedTask.checklists" :key="item.id">
                                    <div class="flex items-start gap-2 py-1.5 border-b text-xs"
                                         style="border-color:var(--color-border)">
                                        <input type="checkbox"
                                               :checked="item.is_checked"
                                               @change="toggleChecklist(selectedTask.id, item)"
                                               class="mt-0.5 cursor-pointer rounded"
                                               style="accent-color:var(--color-success)">
                                        <span :class="item.is_checked ? 'line-through' : ''"
                                              :style="item.is_checked ? 'color:var(--color-text-muted)' : 'color:var(--color-text-main)'"
                                              x-text="item.item"></span>
                                    </div>
                                </template>
                            </template>
                            <template x-if="!selectedTask || !selectedTask.checklists || selectedTask.checklists.length === 0">
                                <p class="text-xs italic py-2" style="color:var(--color-text-muted)">Tidak ada checklist.</p>
                            </template>
                        </div>
                    </div>

                </div>
            </div>

        </div>{{-- /calendar area --}}

    </div>{{-- /main grid --}}

    {{-- ══ MODAL FORM PENJADWALAN ════════════════════════════════════ --}}
    <div x-show="showScheduleModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="background:rgba(15,23,42,.5)"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">

        <div class="w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden rounded-xl shadow-2xl"
             style="background:var(--color-surface); border:1px solid var(--color-border)"
             @click.outside="closeScheduleModal()">

            {{-- Modal Header --}}
            <div class="px-5 py-4 border-b flex items-center justify-between" style="border-color:var(--color-border)">
                <div>
                    <h3 class="text-base font-bold" style="color:var(--color-text-main)">Form Penjadwalan Tim Teknisi</h3>
                    <p class="text-xs mt-0.5" style="color:var(--color-text-muted)">Pilih task antrean dan tentukan tim & jadwal</p>
                </div>
                <button @click="closeScheduleModal()" type="button"
                        class="p-1.5 rounded-lg cursor-pointer transition-colors"
                        style="color:var(--color-text-muted)"
                        onmouseover="this.style.background='var(--color-background)'"
                        onmouseout="this.style.background='transparent'">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Modal Body --}}
            <form :action="scheduleForm.task_id ? ('/tasks/' + scheduleForm.task_id + '/schedule') : '#'" method="POST"
                  class="flex-1 overflow-y-auto p-5 space-y-5">
                @csrf

                {{-- 1. Pilih Task Antrean --}}
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider mb-1.5" style="color:var(--color-text-secondary)">
                        1. Pilih Task Antrean FOP <span style="color:var(--color-error)">*</span>
                    </label>
                    <select x-model="scheduleForm.task_id" @change="onSelectPendingTask()" required
                            class="w-full rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2"
                            style="background:var(--color-background); border:1px solid var(--color-border); color:var(--color-text-main); focus-ring-color:var(--color-primary)">
                        <option value="">-- Pilih Tiket Antrean --</option>
                        <template x-for="t in pendingQueue" :key="t.id">
                            <option :value="t.id" x-text="`[${t.task_number || '-'}] ${String(t.task_type || 'TASK').toUpperCase()} — ${t.customer ? t.customer.full_name : (t.title || '-')} (${t.pop ? t.pop.name : '-'})`"></option>
                        </template>
                    </select>
                    <template x-if="pendingQueue.length === 0">
                        <p class="text-[11px] mt-1 flex items-center gap-1" style="color:var(--color-warning)">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            Tidak ada tiket antrean pending saat ini.
                        </p>
                    </template>
                </div>

                {{-- Grid: Waktu, Nama Tim, SLA --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider mb-1.5" style="color:var(--color-text-secondary)">Waktu Jadwal <span style="color:var(--color-error)">*</span></label>
                        <input type="datetime-local" x-model="scheduleForm.scheduled_at" name="scheduled_at" required
                               class="w-full rounded-lg px-3 py-2 text-xs focus:outline-none"
                               style="background:var(--color-background); border:1px solid var(--color-border); color:var(--color-text-main)">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider mb-1.5" style="color:var(--color-text-secondary)">Nama Tim</label>
                        <input type="text" x-model="scheduleForm.team_name" name="team_name" placeholder="e.g. Tim Alpha"
                               class="w-full rounded-lg px-3 py-2 text-xs focus:outline-none"
                               style="background:var(--color-background); border:1px solid var(--color-border); color:var(--color-text-main)">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider mb-1.5" style="color:var(--color-text-secondary)">Target SLA <span style="color:var(--color-error)">*</span></label>
                        <select x-model="scheduleForm.sla_minutes" name="sla_minutes" required
                                class="w-full rounded-lg px-3 py-2 text-xs focus:outline-none"
                                style="background:var(--color-background); border:1px solid var(--color-border); color:var(--color-text-main)">
                            <option value="120">2 Jam — Survey</option>
                            <option value="180">3 Jam — Maintenance</option>
                            <option value="240">4 Jam — Pemasangan</option>
                            <option value="360">6 Jam</option>
                            <option value="480">8 Jam (1 Hari Kerja)</option>
                        </select>
                    </div>
                </div>

                {{-- Pilih Teknisi --}}
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-xs font-bold uppercase tracking-wider" style="color:var(--color-text-secondary)">Pilih Teknisi (Maks 3) <span style="color:var(--color-error)">*</span></label>
                        <span class="text-[11px] font-mono" style="color:var(--color-text-muted)" x-text="`${scheduleForm.team_member_ids.length}/3 dipilih`"></span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 max-h-36 overflow-y-auto p-2 rounded-lg"
                         style="background:var(--color-background); border:1px solid var(--color-border)">
                        <template x-for="tech in teknisiList" :key="tech.id">
                            <label class="flex items-center gap-2 p-2 rounded border cursor-pointer transition-colors text-xs"
                                   :style="scheduleForm.team_member_ids.includes(tech.id)
                                       ? 'background:var(--color-primary-soft); border-color:var(--color-primary); color:var(--color-primary); font-weight:600'
                                       : 'border-color:var(--color-border); color:var(--color-text-secondary)'">
                                <input type="checkbox" name="team_member_ids[]" :value="tech.id"
                                       x-model="scheduleForm.team_member_ids"
                                       :disabled="!scheduleForm.team_member_ids.includes(tech.id) && scheduleForm.team_member_ids.length >= 3"
                                       class="rounded cursor-pointer">
                                <span class="truncate" x-text="tech.name"></span>
                            </label>
                        </template>
                    </div>
                </div>

                {{-- Checklist Dinamis --}}
                <div class="pt-3 border-t" style="border-color:var(--color-border)">
                    <div class="flex items-center justify-between mb-2">
                        <div>
                            <label class="text-xs font-bold uppercase tracking-wider" style="color:var(--color-text-secondary)">List Pengerjaan (Checklist)</label>
                            <p class="text-[11px] mt-0.5" style="color:var(--color-text-muted)">Teknisi validasi mandiri per item</p>
                        </div>
                        <button type="button" @click="addChecklistItem()"
                                class="px-2.5 py-1 rounded text-xs font-medium flex items-center gap-1 cursor-pointer transition-colors"
                                style="background:var(--color-background); border:1px solid var(--color-border); color:var(--color-primary)">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                            + Item
                        </button>
                    </div>

                    <template x-for="(chk, idx) in scheduleForm.checklist_items" :key="'hidden-' + idx">
                        <input type="hidden" name="checklist_items[]" :value="chk">
                    </template>

                    <div class="space-y-1.5 max-h-44 overflow-y-auto pr-0.5">
                        <template x-for="(chk, idx) in scheduleForm.checklist_items" :key="idx">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-mono w-5 text-right shrink-0" style="color:var(--color-text-muted)" x-text="`${idx + 1}.`"></span>
                                <input type="text" x-model="scheduleForm.checklist_items[idx]" placeholder="Deskripsi pengerjaan..." required
                                       class="flex-1 rounded px-2.5 py-1.5 text-xs focus:outline-none"
                                       style="background:var(--color-background); border:1px solid var(--color-border); color:var(--color-text-main)">
                                <button type="button" @click="removeChecklistItem(idx)"
                                        class="p-1.5 rounded cursor-pointer transition-colors"
                                        style="color:var(--color-text-muted)"
                                        onmouseover="this.style.color='var(--color-error)'"
                                        onmouseout="this.style.color='var(--color-text-muted)'">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </div>
                        </template>
                        <template x-if="scheduleForm.checklist_items.length === 0">
                            <p class="text-xs italic py-3 text-center rounded border border-dashed" style="color:var(--color-text-muted); border-color:var(--color-border)">
                                Belum ada list. Klik "+ Item" atau pilih tiket antrean.
                            </p>
                        </template>
                    </div>
                </div>

                {{-- Override konflik --}}
                <div>
                    <label class="flex items-center gap-2 cursor-pointer p-2.5 rounded-lg text-xs"
                           style="background:#FFFBEB; border:1px solid #FDE68A; color:#92400E">
                        <input type="checkbox" name="conflict_override" value="1" x-model="scheduleForm.conflict_override"
                               class="rounded cursor-pointer">
                        <span>Paksa jadwalkan meski ada konflik waktu teknisi</span>
                    </label>
                </div>

                {{-- Footer --}}
                <div class="pt-4 border-t flex items-center justify-end gap-3" style="border-color:var(--color-border)">
                    <button type="button" @click="closeScheduleModal()"
                            class="px-4 py-2 rounded-lg text-xs font-medium cursor-pointer transition-colors"
                            style="background:var(--color-background); border:1px solid var(--color-border); color:var(--color-text-secondary)">
                        Batal
                    </button>
                    <button type="submit"
                            :disabled="!scheduleForm.task_id || scheduleForm.team_member_ids.length === 0 || scheduleForm.checklist_items.length === 0"
                            class="px-5 py-2 rounded-lg text-white text-xs font-semibold shadow-sm cursor-pointer transition-all disabled:opacity-40 disabled:cursor-not-allowed"
                            style="background:var(--color-primary)">
                        Simpan Penjadwalan
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>{{-- /x-data --}}

@push('scripts')
<script>
function fopCalendarHandler() {
    const fallbackTemplates = {
        'survey':      ['Cek koordinat lokasi & ODP terdekat', 'Pengukuran jarak tarik kabel dropcore', 'Cek redaman & kapasitas port ODP', 'Foto dokumentasi lokasi pelanggan'],
        'pemasangan':  ['Penarikan kabel dropcore dari ODP ke rumah', 'Pemasangan roset & konektor fiber', 'Instalasi & aktivasi Modem ONT/Router', 'Edukasi penggunaan internet ke pelanggan', 'Foto bukti speedtest & modem'],
        'maintenance': ['Pengecekan redaman optik & status LOS modem', 'Pemeriksaan kabel dropcore', 'Konfigurasi ulang perangkat ONT/Router', 'Konfirmasi perbaikan layanan dengan pelanggan'],
        'ambil_modem': ['Pengecekan kelengkapan modem & adaptor', 'Pencabutan perangkat ONT/Router', 'Serah terima perangkat & tanda tangan pelanggan']
    };

    return {
        tasksMap:         @json($tasksMap ?? []),
        pendingQueue:     @json($pendingQueue ?? []),
        teknisiList:      @json($teknisiList ?? []),
        defaultChecklists:@json($defaultChecklistsMap ?? []),

        selectedTeamId:   null,
        selectedTaskId:   null,
        selectedTask:     null,
        viewMode:         'weekly',
        showScheduleModal: false,

        scheduleForm: {
            task_id: '', task_type: '', scheduled_at: '',
            team_name: '', sla_minutes: 120,
            team_member_ids: [], conflict_override: false,
            checklist_items: []
        },

        selectTeam(teamId) {
            this.selectedTeamId = (this.selectedTeamId === teamId) ? null : teamId;
        },

        openTaskDetail(taskId) {
            if (this.selectedTaskId === taskId) {
                this.selectedTaskId = null;
                this.selectedTask   = null;
            } else {
                this.selectedTaskId = taskId;
                this.selectedTask   = this.tasksMap[taskId] || null;
            }
        },

        openScheduleModal(dayKey) {
            this.scheduleForm = {
                task_id: '', task_type: '',
                scheduled_at: (dayKey || new Date().toISOString().split('T')[0]) + 'T09:00',
                team_name: '', sla_minutes: 120,
                team_member_ids: [], conflict_override: false,
                checklist_items: []
            };
            this.showScheduleModal = true;
        },

        closeScheduleModal() {
            this.showScheduleModal = false;
            this.scheduleForm = {
                task_id: '', task_type: '', scheduled_at: '',
                team_name: '', sla_minutes: 120,
                team_member_ids: [], conflict_override: false,
                checklist_items: []
            };
        },

        onSelectPendingTask() {
            const task = this.pendingQueue.find(t => String(t.id) === String(this.scheduleForm.task_id));
            if (!task) return;
            const type = task.task_type || 'survey';
            this.scheduleForm.task_type   = type;
            this.scheduleForm.sla_minutes = { survey: 120, pemasangan: 240, maintenance: 180 }[type] || 120;
            const templates = this.defaultChecklists[type] || fallbackTemplates[type] || ['Pengecekan teknis', 'Penyelesaian pekerjaan', 'Verifikasi dengan pelanggan'];
            this.scheduleForm.checklist_items = [...templates];
        },

        addChecklistItem()        { this.scheduleForm.checklist_items.push(''); },
        removeChecklistItem(idx)  { this.scheduleForm.checklist_items.splice(idx, 1); },

        toggleChecklist(taskId, item) {
            fetch('/tasks/' + taskId + '/checklists/' + item.id, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                body: JSON.stringify({ is_checked: !item.is_checked })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    item.is_checked = data.is_checked;
                    if (this.tasksMap[taskId]) {
                        this.tasksMap[taskId].checklist_completed = this.tasksMap[taskId].checklists.filter(c => c.is_checked).length;
                    }
                }
            })
            .catch(() => { item.is_checked = !item.is_checked; });
        }
    };
}
window.fopCalendarHandler = fopCalendarHandler;
</script>
@endpush
@endsection
