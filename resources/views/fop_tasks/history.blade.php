@extends('layouts.app')

@section('title', 'Riwayat Task FOP')

@section('content')
<div x-data="{ filterDrawerOpen: false }" class="px-3 sm:px-4 py-4 sm:py-6 max-w-12xl mx-auto space-y-4 sm:space-y-5 pb-16 md:pb-6">

    {{-- ══ Page Header ══ --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4 py-1">
        <div>
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                    <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-slate-100 tracking-tight font-ui">Riwayat Task FOP</h1>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1 font-ui">Daftar task FOP yang telah selesai atau dibatalkan.</p>
        </div>
        <div>
            <a href="{{ route('fop-tasks.index') }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 transition-colors shadow-2xs font-ui">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                <span>Task FOP Aktif</span>
            </a>
        </div>
    </div>

    @php
        $activeFiltersCount = count(array_filter(request()->only(['category', 'priority', 'village_id', 'team_id'])));
        $currentStatus = request('status', '');
    @endphp

    {{-- ══ Quick Segmented Status Tabs ══ --}}
    <div class="flex items-center gap-2 overflow-x-auto pb-1 custom-scrollbar -mx-1 px-1">
        <a href="{{ route('fop-tasks.history', array_merge(request()->except(['status', 'page']), ['status' => ''])) }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-all shadow-2xs {{ empty($currentStatus) ? 'bg-sky-600 text-white shadow-sky-600/20' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:bg-slate-50' }}">
            <span>Semua Riwayat</span>
            <span class="text-[10px] px-1.5 py-0.2 rounded-full {{ empty($currentStatus) ? 'bg-white/20 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-500' }} font-mono">{{ $fopTasks->total() }}</span>
        </a>

        <a href="{{ route('fop-tasks.history', array_merge(request()->except(['status', 'page']), ['status' => 'selesai'])) }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-all shadow-2xs {{ $currentStatus === 'selesai' ? 'bg-green-600 text-white shadow-green-600/20' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:bg-slate-50' }}">
            <span class="w-2 h-2 rounded-full bg-green-400"></span>
            <span>Selesai</span>
        </a>

        <a href="{{ route('fop-tasks.history', array_merge(request()->except(['status', 'page']), ['status' => 'dibatalkan'])) }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-all shadow-2xs {{ $currentStatus === 'dibatalkan' ? 'bg-red-600 text-white shadow-red-600/20' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:bg-slate-50' }}">
            <span class="w-2 h-2 rounded-full bg-red-400"></span>
            <span>Dibatalkan</span>
        </a>
    </div>

    {{-- ══ Mobile Search & Filter Bar ══ --}}
    <div class="block md:hidden">
        <form method="GET" action="{{ route('fop-tasks.history') }}" class="flex items-center gap-2">
            @if(request('status'))
                <input type="hidden" name="status" value="{{ request('status') }}">
            @endif
            @if(request('category'))
                <input type="hidden" name="category" value="{{ request('category') }}">
            @endif
            @if(request('priority'))
                <input type="hidden" name="priority" value="{{ request('priority') }}">
            @endif
            @if(request('village_id'))
                <input type="hidden" name="village_id" value="{{ request('village_id') }}">
            @endif
            @if(request('team_id'))
                <input type="hidden" name="team_id" value="{{ request('team_id') }}">
            @endif

            <div class="relative flex-1">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari task riwayat..."
                       class="w-full text-xs pl-8 pr-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 placeholder:text-slate-400 focus:border-sky-500 focus:ring-1 focus:ring-sky-500 outline-none shadow-2xs font-ui">
                <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>

            <button type="button" @click="filterDrawerOpen = true"
                    class="inline-flex items-center gap-1.5 px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 transition-colors shadow-2xs shrink-0 cursor-pointer">
                <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                </svg>
                <span>Filter</span>
                @if($activeFiltersCount > 0)
                    <span class="w-4 h-4 rounded-full bg-sky-600 text-white text-[10px] font-bold flex items-center justify-center">{{ $activeFiltersCount }}</span>
                @endif
            </button>
        </form>
    </div>

    {{-- ══ Desktop Filters Form ══ --}}
    <form method="GET" action="{{ route('fop-tasks.history') }}" class="hidden md:flex flex-col gap-3 pb-2">
        <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5 font-ui">Pencarian</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari Task..." class="w-full text-xs border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none placeholder:text-slate-400 text-slate-800 dark:text-slate-100 font-ui bg-white dark:bg-slate-800 shadow-2xs">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5 font-ui">Kategori</label>
                <select name="category" class="w-full text-xs border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 font-ui shadow-2xs">
                    <option value="">Semua</option>
                    @foreach($categories as $key => $val)
                        <option value="{{ $key }}" {{ request('category') === $key ? 'selected' : '' }}>{{ $key }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5 font-ui">Status</label>
                <select name="status" class="w-full text-xs border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 font-ui shadow-2xs">
                    <option value="">Semua</option>
                    <option value="selesai" {{ request('status') === 'selesai' ? 'selected' : '' }}>Selesai</option>
                    <option value="dibatalkan" {{ request('status') === 'dibatalkan' ? 'selected' : '' }}>Dibatalkan</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5 font-ui">Prioritas</label>
                <select name="priority" class="w-full text-xs border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 font-ui shadow-2xs">
                    <option value="">Semua</option>
                    <option value="low" {{ request('priority') === 'low' ? 'selected' : '' }}>Low</option>
                    <option value="Medium" {{ request('priority') === 'Medium' ? 'selected' : '' }}>Medium</option>
                    <option value="High" {{ request('priority') === 'High' ? 'selected' : '' }}>High</option>
                    <option value="Urgent" {{ request('priority') === 'Urgent' ? 'selected' : '' }}>Urgent</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5 font-ui">Area</label>
                <select name="village_id" class="w-full text-xs border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 font-ui shadow-2xs">
                    <option value="">Semua</option>
                    @foreach($villages as $v)
                        <option value="{{ $v->id }}" {{ request('village_id') == $v->id ? 'selected' : '' }}>{{ $v->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5 font-ui">Team</label>
                <select name="team_id" class="w-full text-xs border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 font-ui shadow-2xs">
                    <option value="">Semua</option>
                    @foreach($teams as $t)
                        <option value="{{ $t['id'] }}" {{ request('team_id') == $t['id'] ? 'selected' : '' }}>{{ $t['name'] }} ({{ $t['work_date'] }})</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="flex items-center justify-between mt-1">
            <span class="text-xs text-slate-500 dark:text-slate-400 font-ui">Menampilkan <span class="font-semibold text-slate-700 dark:text-slate-300 font-data">{{ $fopTasks->count() }}</span> dari <span class="font-semibold text-slate-700 dark:text-slate-300 font-data">{{ $fopTasks->total() }}</span> data</span>
            <div class="flex items-center gap-3">
                @if(request()->anyFilled(['search', 'category', 'status', 'priority', 'village_id', 'team_id']))
                    <a href="{{ route('fop-tasks.history') }}" class="text-xs font-semibold text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 transition-colors font-ui">Reset</a>
                @endif
                <button type="submit" class="bg-slate-800 hover:bg-slate-900 text-white dark:bg-slate-700 dark:hover:bg-slate-600 text-xs font-semibold px-4 py-2 rounded-lg transition-colors font-ui shadow-2xs cursor-pointer">Filter</button>
            </div>
        </div>
    </form>

    {{-- ══ Mobile Filter Drawer ══ --}}
    <div x-show="filterDrawerOpen"
         class="fixed inset-0 z-50 overflow-y-auto flex items-end md:hidden"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         style="display: none;">
        
        <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs" @click="filterDrawerOpen = false"></div>

        <div class="bg-surface border-t border-border w-full rounded-t-2xl shadow-2xl relative z-10 max-h-[85vh] flex flex-col overflow-hidden"
             @click.away="filterDrawerOpen = false"
             x-transition:enter="transition ease-out duration-250"
             x-transition:enter-start="translate-y-full"
             x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-y-0"
             x-transition:leave-end="translate-y-full">
            
            <div class="w-12 h-1.5 bg-slate-300 dark:bg-slate-600 rounded-full mx-auto my-2 shrink-0"></div>

            <div class="px-5 py-3 border-b border-border flex items-center justify-between bg-surface-muted shrink-0">
                <h3 class="text-sm font-bold text-text-main font-ui">Filter Riwayat Task</h3>
                <button type="button" @click="filterDrawerOpen = false" class="text-text-muted hover:text-text-main p-1">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form method="GET" action="{{ route('fop-tasks.history') }}" class="flex flex-col flex-1 overflow-hidden font-ui">
                <div class="p-5 overflow-y-auto space-y-4 flex-1 custom-scrollbar">
                    @if(request('status'))
                        <input type="hidden" name="status" value="{{ request('status') }}">
                    @endif

                    <div>
                        <label class="block text-xs font-semibold text-text-secondary uppercase tracking-wider mb-1.5">Pencarian</label>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari task riwayat..." class="w-full text-sm border border-border rounded-lg px-3 py-2 bg-surface text-text-main focus:ring-1 focus:ring-primary outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-text-secondary uppercase tracking-wider mb-1.5">Kategori</label>
                        <select name="category" class="w-full text-sm border border-border rounded-lg px-3 py-2 bg-surface text-text-main focus:ring-1 focus:ring-primary outline-none">
                            <option value="">Semua Kategori</option>
                            @foreach($categories as $key => $val)
                                <option value="{{ $key }}" {{ request('category') === $key ? 'selected' : '' }}>{{ $key }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-text-secondary uppercase tracking-wider mb-1.5">Status</label>
                        <select name="status" class="w-full text-sm border border-border rounded-lg px-3 py-2 bg-surface text-text-main focus:ring-1 focus:ring-primary outline-none">
                            <option value="">Semua Status</option>
                            <option value="selesai" {{ request('status') === 'selesai' ? 'selected' : '' }}>Selesai</option>
                            <option value="dibatalkan" {{ request('status') === 'dibatalkan' ? 'selected' : '' }}>Dibatalkan</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-text-secondary uppercase tracking-wider mb-1.5">Prioritas</label>
                        <select name="priority" class="w-full text-sm border border-border rounded-lg px-3 py-2 bg-surface text-text-main focus:ring-1 focus:ring-primary outline-none">
                            <option value="">Semua Prioritas</option>
                            <option value="low" {{ request('priority') === 'low' ? 'selected' : '' }}>Low</option>
                            <option value="Medium" {{ request('priority') === 'Medium' ? 'selected' : '' }}>Medium</option>
                            <option value="High" {{ request('priority') === 'High' ? 'selected' : '' }}>High</option>
                            <option value="Urgent" {{ request('priority') === 'Urgent' ? 'selected' : '' }}>Urgent</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-text-secondary uppercase tracking-wider mb-1.5">Area (Desa)</label>
                        <select name="village_id" class="w-full text-sm border border-border rounded-lg px-3 py-2 bg-surface text-text-main focus:ring-1 focus:ring-primary outline-none">
                            <option value="">Semua Area</option>
                            @foreach($villages as $v)
                                <option value="{{ $v->id }}" {{ request('village_id') == $v->id ? 'selected' : '' }}>{{ $v->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-text-secondary uppercase tracking-wider mb-1.5">Team</label>
                        <select name="team_id" class="w-full text-sm border border-border rounded-lg px-3 py-2 bg-surface text-text-main focus:ring-1 focus:ring-primary outline-none">
                            <option value="">Semua Team</option>
                            @foreach($teams as $t)
                                <option value="{{ $t['id'] }}" {{ request('team_id') == $t['id'] ? 'selected' : '' }}>{{ $t['name'] }} ({{ $t['work_date'] }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="px-5 py-3.5 border-t border-border bg-surface-muted flex items-center justify-between gap-3 shrink-0">
                    <a href="{{ route('fop-tasks.history') }}" class="btn-secondary text-xs">Reset</a>
                    <button type="submit" class="btn-primary text-xs">Terapkan Filter</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ══ MOBILE TASK FEED (< 768px) ══ --}}
    <div class="block md:hidden space-y-3.5">
        @forelse($fopTasks as $task)
            @include('fop_tasks.partials.mobile-card', ['task' => $task, 'isHistory' => true])
        @empty
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-8 text-center text-slate-500 dark:text-slate-400">
                <svg class="w-10 h-10 mx-auto mb-3 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <p class="text-xs font-bold text-slate-700 dark:text-slate-300 font-ui">Tidak ada riwayat task FOP.</p>
                <p class="text-[11px] mt-1 text-slate-400 dark:text-slate-500 font-ui">Silakan sesuaikan filter pencarian.</p>
            </div>
        @endforelse
    </div>

    {{-- ══ DESKTOP TABLE PANEL (>= 768px) ══ --}}
    <div class="hidden md:block bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-2xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700 text-[11px] font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider font-ui">
                        <th class="px-3 py-2.5">Kategori</th>
                        <th class="px-3 py-2.5">Tanggal</th>
                        <th class="px-3 py-2.5">Tugas</th>
                        <th class="px-3 py-2.5">Area</th>
                        <th class="px-3 py-2.5">Issue</th>
                        <th class="px-3 py-2.5">Teknisi</th>
                        <th class="px-3 py-2.5">Team</th>
                        <th class="px-3 py-2.5">Status</th>
                        <th class="px-3 py-2.5">Prioritas</th>
                        <th class="px-3 py-2.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 text-[11px] text-slate-700 dark:text-slate-300 font-ui">
                    @forelse($fopTasks as $task)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors align-top">
                            <td class="px-3 py-2 whitespace-nowrap">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-medium border font-ui {{ $task->category instanceof \App\Enums\TaskType ? $task->category->badgeClasses() : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">
                                    {{ $task->category instanceof \App\Enums\TaskType ? $task->category->value : $task->category }}
                                </span>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-slate-600 dark:text-slate-400 font-data">
                                {{ $task->task_date ? $task->task_date->format('d/m/Y H:i') : '—' }}
                            </td>
                            <td class="px-3 py-2 min-w-[200px] whitespace-normal leading-tight font-ui">
                                <span class="font-medium text-slate-800 dark:text-slate-200">{{ $task->tugas }}</span>
                                @if($task->status->value === 'dibatalkan' && $task->cancel_reason)
                                    <p class="text-[10px] text-red-600 dark:text-red-400 mt-0.5">
                                        <span class="font-semibold">Alasan Batal:</span> {{ $task->cancel_reason }}
                                    </p>
                                @endif
                            </td>
                            <td class="px-3 py-2 whitespace-normal leading-tight text-slate-600 dark:text-slate-400 min-w-[120px] font-ui">
                                {{ $task->village?->name ?? '—' }}
                            </td>
                            <td class="px-3 py-2 min-w-[150px] whitespace-normal leading-tight text-red-600 dark:text-red-400 font-ui">
                                {{ $task->issue ?? '—' }}
                            </td>
                            <td class="px-3 py-2 font-ui">
                                <div class="flex flex-wrap gap-1 items-start min-w-[150px]">
                                    @php 
                                        $visibleTechs = $task->technicians->take(2); 
                                        $hiddenTechsCount = $task->technicians->count() - 2; 
                                    @endphp
                                    @forelse($visibleTechs as $tech)
                                        @php 
                                            $firstName = explode(' ', trim($tech->name))[0]; 
                                        @endphp
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-blue-50 text-blue-700 border border-blue-100 cursor-help" title="{{ $tech->name }}">
                                            {{ \Illuminate\Support\Str::limit($firstName, 12) }}
                                        </span>
                                    @empty
                                        <span class="text-slate-400 dark:text-slate-500 text-[10px] italic">Unassigned</span>
                                    @endforelse
                                    
                                    @if($hiddenTechsCount > 0)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-slate-100 dark:bg-slate-700/50 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700 cursor-help font-ui" title="{{ $task->technicians->skip(2)->pluck('name')->implode(', ') }}">
                                            +{{ $hiddenTechsCount }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap font-ui">
                                @if($task->team)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-sky-50 dark:bg-sky-900/20 text-sky-700 dark:text-sky-400 border border-sky-100 dark:border-sky-800/30">
                                        {{ $task->team->name }}
                                    </span>
                                @else
                                    <span class="text-slate-300 text-[10px]">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap font-ui">
                                @php
                                    $statusValue = $task->status->value;
                                    $statusLabel = $task->task
                                        ? $task->task->status->displayLabel($task->task->report_deferred)
                                        : ($statusValue === 'draft' ? 'Belum Ditugaskan' : $task->status->displayLabel());
                                    $statusClasses = $task->task
                                        ? $task->task->status->displayBadgeClasses($task->task->report_deferred)
                                        : ($statusValue === 'draft' ? 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/50' : $task->status->displayBadgeClasses());
                                @endphp
                                <span class="inline-flex items-center px-2 py-1 rounded text-[11px] font-medium border {{ $statusClasses }}">
                                    {{ $statusLabel }}
                                </span>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap font-ui">
                                <span class="font-medium text-[11px]">{{ $task->priority->value }}</span>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-right font-ui">
                                <a href="{{ route('fop-tasks.history.show', $task->id) }}"
                                   class="text-slate-400 dark:text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 transition-colors bg-slate-100 dark:bg-slate-700/50 hover:bg-slate-200 p-1.5 rounded inline-block"
                                   title="Detail Riwayat">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-3 py-10 text-center text-slate-500 dark:text-slate-400">
                                <svg class="w-8 h-8 mx-auto mb-3 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                <p class="text-[11px] font-medium">Tidak ada data riwayat task FOP.</p>
                                <p class="text-[10px] mt-1 text-slate-400 dark:text-slate-500">Silakan sesuaikan filter pencarian.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Pagination Links --}}
    @if($fopTasks->hasPages())
        <div class="mt-4">
            {{ $fopTasks->links() }}
        </div>
    @endif
</div>
@endsection
