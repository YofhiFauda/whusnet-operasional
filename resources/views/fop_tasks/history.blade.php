@extends('layouts.app')

@section('title', 'Riwayat Task FOP')

@section('content')
<div class="px-4 py-6 max-w-12xl mx-auto space-y-5">

    {{-- ══ Page Header ══ --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 py-2">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 tracking-tight font-ui">Riwayat Task FOP</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 font-ui">Daftar task FOP yang telah selesai atau dibatalkan.</p>
        </div>
    </div>

    {{-- ══ Filters (Naked) ══ --}}
    <form method="GET" action="{{ route('fop-tasks.history') }}" class="flex flex-col gap-3 pb-3">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-6 gap-3">
            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5 font-ui">Pencarian</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari Task..." class="w-full text-sm border border-slate-300 dark:border-slate-600 rounded px-3 py-1.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none placeholder:text-slate-400 dark:text-slate-500 font-ui bg-white dark:bg-slate-800">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5 font-ui">Kategori</label>
                <select name="category" class="w-full text-sm border border-slate-300 dark:border-slate-600 rounded px-3 py-1.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none bg-white dark:bg-slate-800 font-ui">
                    <option value="">Semua</option>
                    @foreach($categories as $key => $val)
                        <option value="{{ $key }}" {{ request('category') === $key ? 'selected' : '' }}>{{ $key }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5 font-ui">Status</label>
                <select name="status" class="w-full text-sm border border-slate-300 dark:border-slate-600 rounded px-3 py-1.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none bg-white dark:bg-slate-800 font-ui">
                    <option value="">Semua</option>
                    <option value="selesai" {{ request('status') === 'selesai' ? 'selected' : '' }}>Selesai</option>
                    <option value="dibatalkan" {{ request('status') === 'dibatalkan' ? 'selected' : '' }}>Dibatalkan</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5 font-ui">Prioritas</label>
                <select name="priority" class="w-full text-sm border border-slate-300 dark:border-slate-600 rounded px-3 py-1.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none bg-white dark:bg-slate-800 font-ui">
                    <option value="">Semua</option>
                    <option value="low" {{ request('priority') === 'low' ? 'selected' : '' }}>Low</option>
                    <option value="Medium" {{ request('priority') === 'Medium' ? 'selected' : '' }}>Medium</option>
                    <option value="High" {{ request('priority') === 'High' ? 'selected' : '' }}>High</option>
                    <option value="Urgent" {{ request('priority') === 'Urgent' ? 'selected' : '' }}>Urgent</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5 font-ui">Area</label>
                <select name="village_id" class="w-full text-sm border border-slate-300 dark:border-slate-600 rounded px-3 py-1.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none bg-white dark:bg-slate-800 font-ui">
                    <option value="">Semua</option>
                    @foreach($villages as $v)
                        <option value="{{ $v->id }}" {{ request('village_id') == $v->id ? 'selected' : '' }}>{{ $v->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5 font-ui">Team</label>
                <select name="team_id" class="w-full text-sm border border-slate-300 dark:border-slate-600 rounded px-3 py-1.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none bg-white dark:bg-slate-800 font-ui">
                    <option value="">Semua</option>
                    @foreach($teams as $t)
                        <option value="{{ $t['id'] }}" {{ request('team_id') == $t['id'] ? 'selected' : '' }}>{{ $t['name'] }} ({{ $t['work_date'] }})</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="flex items-center justify-between mt-1">
            <span class="text-sm text-slate-500 dark:text-slate-400 font-ui">Menampilkan <span class="font-semibold text-slate-700 dark:text-slate-300 font-data">{{ $fopTasks->count() }}</span> dari <span class="font-semibold text-slate-700 dark:text-slate-300 font-data">{{ $fopTasks->total() }}</span> data</span>
            <div class="flex items-center gap-3">
                @if(request()->anyFilled(['search', 'category', 'status', 'priority', 'village_id', 'team_id']))
                    <a href="{{ route('fop-tasks.history') }}" class="text-sm font-medium text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 transition-colors font-ui">Reset</a>
                @endif
                <button type="submit" class="bg-slate-100 dark:bg-slate-700/50 hover:bg-slate-200 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-600 text-sm font-medium px-4 py-1.5 rounded transition-colors font-ui">Filter</button>
            </div>
        </div>
    </form>

    {{-- ══ Table Panel (Single Card Container) ══ --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700 text-[11px] font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider font-ui">
                        <th class="px-3 py-2">Kategori</th>
                        <th class="px-3 py-2">Tanggal</th>
                        <th class="px-3 py-2">Tugas</th>
                        <th class="px-3 py-2">Area</th>
                        <th class="px-3 py-2">Issue</th>
                        <th class="px-3 py-2">Teknisi</th>
                        <th class="px-3 py-2">Team</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Prioritas</th>
                        <th class="px-3 py-2 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 text-[11px] text-slate-700 dark:text-slate-300 font-ui">
                    @forelse($fopTasks as $task)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 dark:hover:bg-slate-800/50 dark:hover:bg-slate-700/50 dark:hover:bg-slate-800/50 transition-colors align-top">
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
                                    $statusLabel = $task->task
                                        ? $task->task->status->displayLabel($task->task->report_deferred)
                                        : $task->status->displayLabel();
                                    $statusClasses = $task->task
                                        ? $task->task->status->displayBadgeClasses($task->task->report_deferred)
                                        : $task->status->displayBadgeClasses();
                                @endphp
                                <span class="inline-flex items-center px-2 py-1 rounded text-[11px] font-medium border w-fit {{ $statusClasses }}"
                                    title="Status Riwayat sudah final, tidak bisa diubah">
                                    {{ $statusLabel }}
                                </span>
                                @if($task->status->value === 'dibatalkan' && $task->cancel_reason)
                                    <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-0.5 max-w-[160px] truncate" title="{{ $task->cancel_reason }}">{{ $task->cancel_reason }}</p>
                                @endif
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap font-ui">
                                <span @class([
                                        'inline-flex items-center px-2 py-1 rounded text-[11px] font-medium border w-fit',
                                        'border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 bg-slate-50 dark:bg-slate-800/50' => $task->priority->value === 'low',
                                        'border-yellow-300 text-yellow-800 bg-yellow-50' => $task->priority->value === 'Medium',
                                        'border-orange-300 text-orange-800 bg-orange-50' => $task->priority->value === 'High',
                                        'border-red-300 text-red-700 dark:text-red-400 bg-red-50 dark:bg-red-900/20 font-bold' => $task->priority->value === 'Urgent',
                                    ])
                                    title="Prioritas Riwayat sudah final, tidak bisa diubah">
                                    {{ $task->priority->value }}
                                </span>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-right">
                                <a href="{{ route('fop-tasks.history.show', $task->id) }}"
                                   class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-800 transition-colors bg-blue-50 hover:bg-blue-100 px-2 py-1.5 rounded text-[11px] font-medium font-ui"
                                   title="Detail">
                                    Detail
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
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
                                <p class="text-[11px] font-medium font-ui">Tidak ada data riwayat task FOP.</p>
                                <p class="text-[10px] mt-1 text-slate-400 dark:text-slate-500 font-ui">Silakan buat task baru atau ubah filter pencarian.</p>
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
