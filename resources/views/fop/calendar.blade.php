@extends('layouts.app')

@section('title', 'FOP — Task Scheduler Calendar')

@section('content')
@php
    $totalCompletedChecklists = collect($tasksMap)->sum('checklist_completed');
    $totalAllChecklists = collect($tasksMap)->sum('checklist_total');
@endphp

<div x-data="fopCalendarHandler()" class="flex flex-col gap-6 px-6 py-5 min-h-screen text-slate-200 relative">

    {{-- ══ Page Header ══════════════════════════════════════════════ --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-800">
        <div class="flex items-center gap-3">
            <div class="p-2 rounded-lg bg-sky-500/10 border border-sky-500/20 text-sky-400">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h18M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>
            <div>
                <h1 class="text-xl font-bold tracking-tight text-white">FOP — Task Scheduler</h1>
                <p class="text-xs text-slate-400">{{ $startDate->translatedFormat('d F Y') }} — {{ $endDate->translatedFormat('d F Y') }}</p>
            </div>
        </div>

        {{-- Toggle Mode & Checklist Pill & Action Button --}}
        <div class="flex flex-wrap items-center gap-3">
            @if(auth()->user()->hasPermission('task.create') || auth()->user()->hasPermission('tasks.create') || auth()->user()->hasPermission('task.schedule') || auth()->user()->hasPermission('tasks.schedule') || auth()->user()->hasPermission('task.assign.team') || auth()->user()->hasPermission('tasks.assign'))
                <button @click="openScheduleModal('{{ Carbon\Carbon::now()->format('Y-m-d') }}')" 
                        class="px-3.5 py-1.5 rounded-lg bg-gradient-to-r from-sky-500 to-indigo-600 hover:from-sky-400 hover:to-indigo-500 text-white font-semibold text-xs shadow-lg shadow-sky-500/20 flex items-center gap-1.5 transition-all cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>+ Jadwalkan Task</span>
                </button>
            @endif

            <div class="inline-flex rounded-lg p-1 bg-slate-800/80 border border-slate-700/60">
                <button @click="viewMode = 'weekly'" 
                        :class="viewMode === 'weekly' ? 'bg-sky-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:text-slate-200'" 
                        class="px-3 py-1 rounded-md text-xs transition-all cursor-pointer">
                    Mingguan
                </button>
                <button @click="viewMode = 'monthly'" 
                        :class="viewMode === 'monthly' ? 'bg-sky-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:text-slate-200'" 
                        class="px-3 py-1 rounded-md text-xs transition-all cursor-pointer">
                    Bulanan
                </button>
            </div>

            <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-800/90 border border-slate-700 text-xs font-medium text-slate-300 shadow-inner">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span>Progress checklist <strong class="text-white font-mono ml-1">{{ $totalCompletedChecklists }}/{{ $totalAllChecklists }}</strong></span>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 flex items-center justify-between shadow-lg backdrop-blur-sm">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 flex-shrink-0 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                <span class="text-xs font-semibold">{!! session('success') !!}</span>
            </div>
            <button @click="$el.parentElement.remove()" class="text-emerald-400 hover:text-white cursor-pointer">&times;</button>
        </div>
    @endif

    @if($errors->any())
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 flex items-start justify-between shadow-lg backdrop-blur-sm">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 flex-shrink-0 mt-0.5 text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <div class="text-xs">
                    <strong class="font-bold block mb-1">Peringatan Penjadwalan:</strong>
                    <ul class="list-disc list-inside space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{!! $error !!}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <button @click="$el.parentElement.remove()" class="text-rose-400 hover:text-white cursor-pointer">&times;</button>
        </div>
    @endif

    {{-- ══ Main Layout: 2 Columns (Sidebar Kiri + Kalender Kanan) ══ --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        {{-- ══ SIDEBAR KIRI (3 Kolom/Seksi) ══════════════════════════ --}}
        <div class="lg:col-span-3 space-y-6">

            {{-- Seksi 1: RINGKASAN HARI INI --}}
            <div class="bg-slate-900/90 border border-slate-800 rounded-xl p-4 shadow-lg backdrop-blur-sm">
                <h3 class="text-xs font-bold text-slate-400 tracking-wider uppercase mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    RINGKASAN PERIODE INI
                </h3>
                <div class="grid grid-cols-2 gap-2.5">
                    <div class="p-3 rounded-lg bg-indigo-950/40 border border-indigo-800/50 text-indigo-200">
                        <p class="text-[10px] font-semibold uppercase tracking-wider opacity-75">Total Task</p>
                        <p class="text-2xl font-bold font-mono mt-0.5 text-white">{{ $stats['total'] }}</p>
                    </div>
                    <div class="p-3 rounded-lg bg-emerald-950/40 border border-emerald-800/50 text-emerald-200">
                        <p class="text-[10px] font-semibold uppercase tracking-wider opacity-75">Selesai</p>
                        <p class="text-2xl font-bold font-mono mt-0.5 text-emerald-400">{{ $stats['completed'] }}</p>
                    </div>
                    <div class="p-3 rounded-lg bg-amber-950/40 border border-amber-800/50 text-amber-200">
                        <p class="text-[10px] font-semibold uppercase tracking-wider opacity-75">Pending</p>
                        <p class="text-2xl font-bold font-mono mt-0.5 text-amber-400">{{ $stats['pending'] }}</p>
                    </div>
                    <div class="p-3 rounded-lg bg-rose-950/40 border border-rose-800/50 text-rose-200">
                        <p class="text-[10px] font-semibold uppercase tracking-wider opacity-75">Dibatalkan</p>
                        <p class="text-2xl font-bold font-mono mt-0.5 text-rose-400">{{ $stats['cancelled'] }}</p>
                    </div>
                </div>
            </div>

            {{-- Seksi 2: TIM AKTIF --}}
            <div class="bg-slate-900/90 border border-slate-800 rounded-xl p-4 shadow-lg backdrop-blur-sm">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-xs font-bold text-slate-400 tracking-wider uppercase flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        TIM AKTIF
                    </h3>
                    <span class="text-[10px] bg-slate-800 px-2 py-0.5 rounded-full text-slate-400 font-mono">{{ $activeTeams->count() }} Tim</span>
                </div>

                <div class="space-y-2 max-h-72 overflow-y-auto pr-1">
                    @forelse($activeTeams as $team)
                        <div @click="selectTeam({{ $team['id'] }})"
                             :class="selectedTeamId === {{ $team['id'] }} ? 'ring-2 ring-sky-500 bg-slate-800/90' : 'bg-slate-800/40 hover:bg-slate-800/70'"
                             class="p-3 rounded-lg border border-slate-700/60 transition-all cursor-pointer flex items-center justify-between gap-3 group">
                            <div class="flex items-center gap-3 min-w-0">
                                {{-- Avatars --}}
                                <div class="flex -space-x-2 overflow-hidden shrink-0">
                                    @foreach($team['avatars'] as $avatar)
                                        <div class="inline-block h-7 w-7 rounded-full ring-2 ring-slate-900 bg-sky-600 flex items-center justify-center text-[10px] font-bold text-white shadow" title="{{ $avatar['name'] }}">
                                            {{ $avatar['initials'] }}
                                        </div>
                                    @endforeach
                                    @if($team['extraCount'] > 0)
                                        <div class="inline-block h-7 w-7 rounded-full ring-2 ring-slate-900 bg-slate-700 flex items-center justify-center text-[9px] font-bold text-slate-300">
                                            +{{ $team['extraCount'] }}
                                        </div>
                                    @endif
                                </div>

                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-white truncate group-hover:text-sky-400 transition-colors">{{ $team['name'] }}</p>
                                    <p class="text-[11px] text-slate-400 mt-0.5 truncate">
                                        <span class="text-slate-300 font-semibold">{{ $team['taskCount'] }}</span> task • <span class="text-emerald-400 font-semibold">{{ $team['completedCount'] }}</span> selesai
                                    </p>
                                </div>
                            </div>

                            {{-- Status Dot --}}
                            <span class="w-2.5 h-2.5 rounded-full shrink-0 {{ $team['status'] === 'active' ? 'bg-emerald-500 shadow-sm shadow-emerald-500/50 animate-pulse' : 'bg-slate-600' }}" title="{{ $team['status'] === 'active' ? 'Sedang bertugas' : 'Standby' }}"></span>
                        </div>
                    @empty
                        <div class="text-center py-6 text-slate-500 text-xs">
                            Tidak ada aktivitas tim pada periode ini
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Seksi 3: KETERANGAN (Legenda Warna Pastel) --}}
            <div class="bg-slate-900/90 border border-slate-800 rounded-xl p-4 shadow-lg backdrop-blur-sm">
                <h3 class="text-xs font-bold text-slate-400 tracking-wider uppercase mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                    </svg>
                    KETERANGAN TIPE TASK
                </h3>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div class="flex items-center gap-2.5 p-2 rounded bg-sky-500/10 border border-sky-500/20 text-sky-300">
                        <span class="w-3 h-3 rounded bg-sky-500 shrink-0"></span>
                        <span class="font-medium truncate">Survey</span>
                    </div>
                    <div class="flex items-center gap-2.5 p-2 rounded bg-emerald-500/10 border border-emerald-500/20 text-emerald-300">
                        <span class="w-3 h-3 rounded bg-emerald-500 shrink-0"></span>
                        <span class="font-medium truncate">Pemasangan</span>
                    </div>
                    <div class="flex items-center gap-2.5 p-2 rounded bg-amber-500/10 border border-amber-500/20 text-amber-300">
                        <span class="w-3 h-3 rounded bg-amber-500 shrink-0"></span>
                        <span class="font-medium truncate">Maintenance</span>
                    </div>
                    <div class="flex items-center gap-2.5 p-2 rounded bg-pink-500/10 border border-pink-500/20 text-pink-300">
                        <span class="w-3 h-3 rounded bg-pink-500 shrink-0"></span>
                        <span class="font-medium truncate">Ambil Modem</span>
                    </div>
                </div>
            </div>

        </div>

        {{-- ══ AREA UTAMA KALENDER (Grid 7 Hari) ════════════════════ --}}
        <div class="lg:col-span-9 flex flex-col gap-4">

            {{-- Navigation Bar Atas Kalender --}}
            <div class="flex items-center justify-between bg-slate-900/90 border border-slate-800 rounded-xl px-4 py-3 shadow">
                <a href="{{ route('fop.calendar', ['start_date' => $startDate->copy()->subWeek()->toDateString()]) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-300 transition-colors border border-slate-700">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                    Prev
                </a>

                <div class="text-sm font-bold text-white tracking-wide font-mono flex items-center gap-2">
                    <span>{{ $startDate->translatedFormat('d M') }}</span>
                    <span class="text-slate-500">—</span>
                    <span>{{ $endDate->translatedFormat('d M Y') }}</span>
                </div>

                <a href="{{ route('fop.calendar', ['start_date' => $startDate->copy()->addWeek()->toDateString()]) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-300 transition-colors border border-slate-700">
                    Next
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            </div>

            {{-- 7-Day Columns Grid --}}
            <div class="grid grid-cols-1 md:grid-cols-7 gap-3">
                @foreach($days as $dayKey => $dayData)
                    @php
                        $isToday = $dayData['date']->isToday();
                    @endphp
                    <div class="flex flex-col bg-slate-900/90 border {{ $isToday ? 'border-sky-500/80 ring-1 ring-sky-500/40' : 'border-slate-800' }} rounded-xl overflow-hidden shadow-md">
                        
                        {{-- Day Header --}}
                        <div class="p-3 text-center border-b {{ $isToday ? 'bg-sky-950/40 border-sky-800/50' : 'bg-slate-800/50 border-slate-800' }}">
                            <p class="text-[11px] font-bold uppercase tracking-wider {{ $isToday ? 'text-sky-400' : 'text-slate-400' }}">
                                {{ $dayData['dayName'] }}
                            </p>
                            <div class="mt-1 flex justify-center">
                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-sm font-mono font-bold {{ $isToday ? 'bg-sky-500 text-white shadow-md shadow-sky-500/50' : 'text-slate-200' }}">
                                    {{ $dayData['dayNum'] }}
                                </span>
                            </div>
                        </div>

                        {{-- Tasks Container --}}
                        <div class="p-2 flex-1 space-y-2.5 min-h-[320px] max-h-[500px] overflow-y-auto">
                            @forelse($dayData['tasks'] as $task)
                                @php
                                    // Tentukan style pastel berdasarkan task_type
                                    $typeVal = $task->task_type->value;
                                    $cardStyle = match($typeVal) {
                                        'survey'      => 'bg-sky-500/15 border-sky-500/30 text-sky-200 hover:border-sky-400',
                                        'pemasangan'  => 'bg-emerald-500/15 border-emerald-500/30 text-emerald-200 hover:border-emerald-400',
                                        'maintenance' => 'bg-amber-500/15 border-amber-500/30 text-amber-200 hover:border-amber-400',
                                        'ambil_modem' => 'bg-pink-500/15 border-pink-500/30 text-pink-200 hover:border-pink-400',
                                        default       => 'bg-purple-500/15 border-purple-500/30 text-purple-200 hover:border-purple-400',
                                    };
                                    $badgeStyle = match($typeVal) {
                                        'survey'      => 'bg-sky-500/20 text-sky-300',
                                        'pemasangan'  => 'bg-emerald-500/20 text-emerald-300',
                                        'maintenance' => 'bg-amber-500/20 text-amber-300',
                                        'ambil_modem' => 'bg-pink-500/20 text-pink-300',
                                        default       => 'bg-purple-500/20 text-purple-300',
                                    };
                                    $teamNames = $task->teamMembers->map(fn($m) => explode(' ', $m->user?->name ?? '?')[0])->join(', ');
                                @endphp

                                <div @click="openTaskDetail({{ $task->id }})"
                                     :class="selectedTaskId === {{ $task->id }} ? 'ring-2 ring-white scale-[1.02]' : ''"
                                     class="p-2.5 rounded-lg border {{ $cardStyle }} transition-all cursor-pointer shadow-sm flex flex-col gap-1.5 group">
                                    
                                    <div class="flex items-center justify-between gap-1">
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider {{ $badgeStyle }}">
                                            {{ $task->task_type->label() }}
                                        </span>
                                        @if($task->scheduled_at)
                                            <span class="text-[10px] font-mono font-semibold opacity-90">
                                                {{ $task->scheduled_at->format('H:i') }}
                                            </span>
                                        @endif
                                    </div>

                                    <p class="text-xs font-bold leading-snug group-hover:underline line-clamp-2">
                                        {{ $task->title }}
                                    </p>

                                    @if($teamNames)
                                        <p class="text-[11px] opacity-80 truncate flex items-center gap-1">
                                            <svg class="w-3 h-3 shrink-0 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                            {{ $teamNames }}
                                        </p>
                                    @endif
                                </div>
                            @empty
                                <div class="h-full flex items-center justify-center py-8">
                                    <span class="text-[11px] text-slate-600 font-mono">—</span>
                                </div>
                            @endforelse
                        </div>

                        {{-- Quick Add Button (+ Task) --}}
                        <div class="p-2 border-t border-slate-800/80 bg-slate-900/50">
                            @if(auth()->user()->hasPermission('task.create') || auth()->user()->hasPermission('tasks.create') || auth()->user()->hasPermission('task.schedule') || auth()->user()->hasPermission('tasks.schedule') || auth()->user()->hasPermission('task.assign.team') || auth()->user()->hasPermission('tasks.assign'))
                                <button type="button" @click="openScheduleModal('{{ $dayKey }}')"
                                        class="w-full py-1.5 rounded border border-dashed border-slate-700 hover:border-sky-500 text-slate-400 hover:text-sky-400 text-xs font-medium flex items-center justify-center gap-1 transition-colors cursor-pointer">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                    </svg>
                                    <span>+ Task</span>
                                </button>
                            @else
                                <div class="py-1.5 text-center text-[10px] text-slate-600 font-mono">No Access</div>
                            @endif
                        </div>

                    </div>
                @endforeach
            </div>

            {{-- ══ PANEL DETAIL TASK INTERAKTIF (Di Bawah Kalender) ══ --}}
            <div x-show="selectedTaskId" x-cloak
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="mt-2 bg-slate-900 border border-slate-700/80 rounded-xl p-5 shadow-2xl relative">
                
                <button @click="selectedTaskId = null" class="absolute top-4 right-4 p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    
                    {{-- Kolom Info Utama --}}
                    <div class="md:col-span-1 space-y-3">
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-sky-500/20 text-sky-300" x-text="selectedTask?.task_type_label"></span>
                            <span class="text-xs font-mono text-slate-400" x-text="selectedTask?.task_number"></span>
                        </div>
                        <h3 class="text-base font-bold text-white leading-snug" x-text="selectedTask?.title"></h3>
                        
                        <div class="pt-2 border-t border-slate-800 space-y-2 text-xs">
                            <div class="flex justify-between">
                                <span class="text-slate-400">Status</span>
                                <span class="font-semibold text-emerald-400 uppercase font-mono text-[11px]" x-text="selectedTask?.status_label"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Tim / Teknisi</span>
                                <span class="font-semibold text-white text-right" x-text="selectedTask?.team_names"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Jadwal</span>
                                <span class="font-mono text-white text-right" x-text="selectedTask?.scheduled_at"></span>
                            </div>
                        </div>
                    </div>

                    {{-- Kolom Info Lokasi & Pelanggan --}}
                    <div class="md:col-span-1 space-y-3 md:border-l md:border-slate-800 md:pl-6">
                        <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">INFO PELANGGAN & LOKASI</h4>
                        <div class="space-y-2.5 text-xs">
                            <div>
                                <p class="text-slate-400 text-[11px]">Nama Pelanggan / ID</p>
                                <p class="font-semibold text-white mt-0.5" x-text="`${selectedTask?.customer_name} (${selectedTask?.customer_number})`"></p>
                            </div>
                            <div>
                                <p class="text-slate-400 text-[11px]">POP / Cabang</p>
                                <p class="font-semibold text-white mt-0.5" x-text="selectedTask?.pop_name"></p>
                            </div>
                            <div>
                                <p class="text-slate-400 text-[11px]">Alamat Pemasangan</p>
                                <p class="text-slate-300 mt-0.5 leading-relaxed" x-text="selectedTask?.location"></p>
                            </div>
                        </div>
                        <div class="pt-2">
                            <a :href="`/tasks/${selectedTask?.id}`" class="inline-flex items-center gap-1.5 text-xs font-semibold text-sky-400 hover:text-sky-300 underline">
                                <span>Buka Halaman Task Lengkap</span>
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                            </a>
                        </div>
                    </div>

                    {{-- Kolom Checklist --}}
                    <div class="md:col-span-1 space-y-3 md:border-l md:border-slate-800 md:pl-6">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">PROGRESS CHECKLIST</h4>
                            <span class="text-xs font-mono font-bold text-emerald-400" x-text="`${selectedTask?.checklist_completed || 0} / ${selectedTask?.checklist_total || 0}`"></span>
                        </div>

                        {{-- Progress Bar --}}
                        <div class="w-full bg-slate-800 rounded-full h-2 overflow-hidden">
                            <div class="bg-emerald-500 h-full transition-all duration-500 rounded-full" 
                                 :style="`width: ${(selectedTask?.checklist_completed || 0) / (selectedTask?.checklist_total || 1) * 100}%`"></div>
                        </div>

                        {{-- Checklist Items --}}
                        <div class="space-y-1.5 max-h-40 overflow-y-auto pt-1 pr-1">
                            <template x-if="selectedTask && selectedTask.checklists && selectedTask.checklists.length > 0">
                                <template x-for="item in selectedTask.checklists" :key="item.id">
                                    <div class="flex items-start gap-2 p-1.5 rounded bg-slate-800/50 text-xs hover:bg-slate-800 transition-colors">
                                        <input type="checkbox" :checked="item.is_checked" @change="toggleChecklist(selectedTask.id, item)" class="mt-0.5 rounded border-slate-700 bg-slate-900 text-emerald-500 focus:ring-sky-500 cursor-pointer">
                                        <span :class="item.is_checked ? 'line-through text-slate-400' : 'text-slate-200 font-medium'" x-text="item.item"></span>
                                    </div>
                                </template>
                            </template>
                            <template x-if="!selectedTask || !selectedTask.checklists || selectedTask.checklists.length === 0">
                                <p class="text-xs text-slate-500 italic py-2">Tidak ada item checklist pada task ini.</p>
                            </template>
                        </div>
                    </div>

                </div>
            </div>

        </div>

    </div>

    {{-- ══ MODAL FORM PENJADWALAN TIM TEKNISI (Slide-Over & Glassmorphism) ══ --}}
    <div x-show="showScheduleModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        
        <div class="bg-slate-900 border border-slate-700/80 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden text-slate-200"
             @click.outside="closeScheduleModal()">
             
            {{-- Modal Header --}}
            <div class="p-5 border-b border-slate-800 flex items-center justify-between bg-slate-900/90">
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-lg bg-indigo-500/10 border border-indigo-500/20 text-indigo-400">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h18M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white">Form Penjadwalan Tim Teknisi</h3>
                        <p class="text-xs text-slate-400">Pilih task antrean (waiting_survey / waiting_installation) dan tentukan tim</p>
                    </div>
                </div>
                <button @click="closeScheduleModal()" type="button" class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800 transition-colors cursor-pointer">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Modal Body Form --}}
            <form :action="scheduleForm.task_id ? ('/tasks/' + scheduleForm.task_id + '/schedule') : '#'" method="POST" class="flex-1 overflow-y-auto p-6 space-y-5">
                @csrf

                {{-- 1. Pilih Task Antrean --}}
                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">1. Pilih Task Antrean FOP <span class="text-rose-400">*</span></label>
                    <select x-model="scheduleForm.task_id" @change="onSelectPendingTask()" required
                            class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-xs text-slate-200 focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                        <option value="">-- Pilih Tiket Antrean --</option>
                        <template x-for="t in pendingQueue" :key="t.id">
                            <option :value="t.id" x-text="`[${t.task_number || '-'}] ${String(t.task_type || 'TASK').toUpperCase()} — ${t.customer ? t.customer.full_name : (t.title || '-')} (${t.pop ? t.pop.name : '-'})`"></option>
                        </template>
                    </select>
                    <template x-if="pendingQueue.length === 0">
                        <p class="text-[11px] text-amber-400 mt-1 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <span>Tidak ada tiket antrean pending saat ini.</span>
                        </p>
                    </template>
                </div>

                {{-- Grid Parameter Waktu, SLA, Nama Tim --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    {{-- Waktu Penjadwalan --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Waktu Jadwal <span class="text-rose-400">*</span></label>
                        <input type="datetime-local" x-model="scheduleForm.scheduled_at" name="scheduled_at" required
                               class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-xs text-slate-200 focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                    </div>
                    {{-- Nama Tim --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Nama Tim</label>
                        <input type="text" x-model="scheduleForm.team_name" name="team_name" placeholder="e.g. Tim Alpha / Barat"
                               class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-xs text-slate-200 placeholder-slate-600 focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                    </div>
                    {{-- Target SLA --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1.5">Target SLA <span class="text-rose-400">*</span></label>
                        <select x-model="scheduleForm.sla_minutes" name="sla_minutes" required
                                class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-xs text-slate-200 focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                            <option value="120">2 Jam (120 Menit - Survey)</option>
                            <option value="180">3 Jam (180 Menit - Maint)</option>
                            <option value="240">4 Jam (240 Menit - Instalasi)</option>
                            <option value="360">6 Jam (360 Menit)</option>
                            <option value="480">8 Jam (1 Hari Kerja)</option>
                        </select>
                    </div>
                </div>

                {{-- Pilih Teknisi --}}
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider">Pilih Teknisi (Maks 3 Orang) <span class="text-rose-400">*</span></label>
                        <span class="text-[11px] text-slate-400 font-mono" x-text="`${scheduleForm.team_member_ids.length}/3 dipilih`"></span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 max-h-36 overflow-y-auto p-2 rounded-lg bg-slate-950 border border-slate-800">
                        <template x-for="tech in teknisiList" :key="tech.id">
                            <label class="flex items-center gap-2 p-2 rounded border border-slate-800 hover:bg-slate-900 cursor-pointer transition-colors"
                                   :class="scheduleForm.team_member_ids.includes(tech.id) ? 'bg-sky-500/10 border-sky-500/40 text-sky-300 font-medium' : 'text-slate-300'">
                                <input type="checkbox" name="team_member_ids[]" :value="tech.id" x-model="scheduleForm.team_member_ids"
                                       :disabled="!scheduleForm.team_member_ids.includes(tech.id) && scheduleForm.team_member_ids.length >= 3"
                                       class="rounded border-slate-700 bg-slate-900 text-sky-500 focus:ring-0">
                                <span class="text-xs truncate" x-text="tech.name"></span>
                            </label>
                        </template>
                    </div>
                </div>

                {{-- List Pengerjaan (Checklist Dinamis) --}}
                <div class="pt-2 border-t border-slate-800/80">
                    <div class="flex items-center justify-between mb-2">
                        <div>
                            <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider">List Pengerjaan (Checklist Dinamis)</label>
                            <p class="text-[11px] text-slate-400">Teknisi dapat melakukan validasi mandiri pada item pengerjaan ini</p>
                        </div>
                        <button type="button" @click="addChecklistItem()" class="px-2.5 py-1 rounded bg-slate-800 hover:bg-slate-700 text-sky-400 hover:text-sky-300 text-xs font-medium flex items-center gap-1 transition-colors cursor-pointer">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                            <span>+ Item</span>
                        </button>
                    </div>

                    {{-- Hidden inputs untuk diparsing backend --}}
                    <template x-for="(chk, idx) in scheduleForm.checklist_items" :key="'hidden-' + idx">
                        <input type="hidden" name="checklist_items[]" :value="chk">
                    </template>

                    {{-- Dynamic visual editor --}}
                    <div class="space-y-2 max-h-48 overflow-y-auto pr-1">
                        <template x-for="(chk, idx) in scheduleForm.checklist_items" :key="idx">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-mono text-slate-500 w-5 text-right" x-text="`${idx + 1}.`"></span>
                                <input type="text" x-model="scheduleForm.checklist_items[idx]" placeholder="Masukkan deskripsi pengerjaan..." required
                                       class="flex-1 rounded-md bg-slate-950 border border-slate-700 px-2.5 py-1.5 text-xs text-slate-200 focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                                <button type="button" @click="removeChecklistItem(idx)" class="p-1.5 text-slate-500 hover:text-rose-400 rounded hover:bg-slate-800 transition-colors cursor-pointer" title="Hapus item">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </div>
                        </template>
                        <template x-if="scheduleForm.checklist_items.length === 0">
                            <p class="text-xs text-slate-500 italic py-3 text-center border border-dashed border-slate-800 rounded-lg">Belum ada list pengerjaan. Klik "+ Item" atau pilih tiket antrean.</p>
                        </template>
                    </div>
                </div>

                {{-- Override Konflik --}}
                <div class="pt-2">
                    <label class="flex items-center gap-2 cursor-pointer p-2.5 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-300 text-xs">
                        <input type="checkbox" name="conflict_override" value="1" x-model="scheduleForm.conflict_override" class="rounded border-amber-600/50 bg-slate-900 text-amber-500 focus:ring-0">
                        <span>Paksa jadwalkan abaikan konflik waktu (jika teknisi bentrok jadwal)</span>
                    </label>
                </div>

                {{-- Footer Modal --}}
                <div class="pt-4 border-t border-slate-800 flex items-center justify-end gap-3">
                    <button type="button" @click="closeScheduleModal()" class="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 font-medium text-xs transition-colors cursor-pointer">
                        Batal
                    </button>
                    <button type="submit" :disabled="!scheduleForm.task_id || scheduleForm.team_member_ids.length === 0 || scheduleForm.checklist_items.length === 0"
                            class="px-5 py-2 rounded-lg bg-gradient-to-r from-sky-500 to-indigo-600 hover:from-sky-400 hover:to-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed text-white font-semibold text-xs shadow-lg shadow-sky-500/20 transition-all cursor-pointer">
                        Simpan Penjadwalan
                    </button>
                </div>
            </form>

        </div>
    </div>

</div>

@push('scripts')
<script>
function fopCalendarHandler() {
    const fallbackTemplates = {
        'survey': ['Cek koordinat lokasi & ODP terdekat', 'Pengukuran jarak tarik kabel dropcore', 'Cek redaman & kapasitas port ODP', 'Foto dokumentasi lokasi pelanggan'],
        'pemasangan': ['Penarikan kabel dropcore dari ODP ke rumah', 'Pemasangan roset & konektor fiber (Fast Connector)', 'Instalasi & aktivasi Modem ONT/Router', 'Edukasi penggunaan internet ke pelanggan', 'Foto bukti pengetesan speedtest & modem'],
        'maintenance': ['Pengecekan redaman optik & status LOS modem', 'Pemeriksaan kabel dropcore eksternal & internal', 'Konfigurasi ulang perangkat ONT/Router', 'Konfirmasi perbaikan layanan dengan pelanggan'],
        'ambil_modem': ['Pengecekan kelengkapan modem & adaptor', 'Pencabutan perangkat ONT/Router', 'Serah terima perangkat & tanda tangan pelanggan']
    };

    return {
        tasksMap: @json($tasksMap ?? []),
        pendingQueue: @json($pendingQueue ?? []),
        teknisiList: @json($teknisiList ?? []),
        defaultChecklists: @json($defaultChecklistsMap ?? []),
        
        selectedTeamId: null,
        selectedTaskId: null,
        selectedTask: null,
        viewMode: 'weekly',

        showScheduleModal: false,
        scheduleForm: {
            task_id: '',
            task_type: '',
            scheduled_at: '',
            team_name: '',
            sla_minutes: 120,
            team_member_ids: [],
            conflict_override: false,
            checklist_items: []
        },

        selectTeam(teamId) {
            this.selectedTeamId = (this.selectedTeamId === teamId) ? null : teamId;
        },

        openTaskDetail(taskId) {
            if (this.selectedTaskId === taskId) {
                this.selectedTaskId = null;
                this.selectedTask = null;
            } else {
                this.selectedTaskId = taskId;
                this.selectedTask = this.tasksMap[taskId] || null;
            }
        },

        openScheduleModal(dayKey) {
            this.scheduleForm = {
                task_id: '',
                task_type: '',
                scheduled_at: (dayKey || new Date().toISOString().split('T')[0]) + 'T09:00',
                team_name: '',
                sla_minutes: 120,
                team_member_ids: [],
                conflict_override: false,
                checklist_items: []
            };
            this.showScheduleModal = true;
        },

        closeScheduleModal() {
            this.showScheduleModal = false;
            this.scheduleForm = {
                task_id: '',
                task_type: '',
                scheduled_at: '',
                team_name: '',
                sla_minutes: 120,
                team_member_ids: [],
                conflict_override: false,
                checklist_items: []
            };
        },

        onSelectPendingTask() {
            const task = this.pendingQueue.find(t => String(t.id) === String(this.scheduleForm.task_id));
            if (!task) return;

            const type = task.task_type || 'survey';
            this.scheduleForm.task_type = type;

            if (type === 'survey') this.scheduleForm.sla_minutes = 120;
            else if (type === 'pemasangan') this.scheduleForm.sla_minutes = 240;
            else if (type === 'maintenance') this.scheduleForm.sla_minutes = 180;
            else this.scheduleForm.sla_minutes = 120;

            const templates = this.defaultChecklists[type] || fallbackTemplates[type] || ['Pengecekan lokasi teknis', 'Penyelesaian instalasi/konfigurasi', 'Verifikasi fungsi dengan pelanggan'];
            this.scheduleForm.checklist_items = [...templates];
        },

        addChecklistItem() {
            this.scheduleForm.checklist_items.push('');
        },

        removeChecklistItem(idx) {
            this.scheduleForm.checklist_items.splice(idx, 1);
        },

        toggleChecklist(taskId, item) {
            fetch('/tasks/' + taskId + '/checklists/' + item.id, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({ is_checked: !item.is_checked })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    item.is_checked = data.is_checked;
                    if (this.tasksMap[taskId]) {
                        const count = this.tasksMap[taskId].checklists.filter(c => c.is_checked).length;
                        this.tasksMap[taskId].checklist_completed = count;
                    }
                } else {
                    alert('Gagal mengupdate checklist.');
                    item.is_checked = !item.is_checked;
                }
            })
            .catch(err => {
                console.error(err);
                alert('Terjadi kesalahan koneksi.');
                item.is_checked = !item.is_checked;
            });
        }
    };
}
window.fopCalendarHandler = fopCalendarHandler;
</script>
@endpush
@endsection
