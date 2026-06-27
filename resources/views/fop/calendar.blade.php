@extends('layouts.app')

@section('title', 'FOP — Task Scheduler Calendar')

@section('content')
<div x-data="fopCalendarHandler()" class="flex flex-col gap-6 px-6 py-5">

    {{-- ══ Page Header ══════════════════════════════════════════════ --}}
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <svg class="h-5 w-5 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h18M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <div>
                <h1 class="text-base font-semibold text-text-main leading-tight">FOP — Task Scheduler</h1>
                <p class="text-xs text-text-muted">{{ $startDate->translatedFormat('d M Y') }} — {{ $endDate->translatedFormat('d M Y') }}</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('fop.calendar', ['start_date' => now()->startOfWeek(Carbon::MONDAY)->subWeek()->toDateString()]) }}"
               class="p-1.5 hover:bg-surface rounded transition-colors" title="Minggu lalu">
                <svg class="h-4 w-4 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <span class="text-xs font-mono text-text-muted px-2">{{ $startDate->format('d M') }} — {{ $endDate->format('d M') }}</span>
            <a href="{{ route('fop.calendar', ['start_date' => now()->startOfWeek(Carbon::MONDAY)->addWeek()->toDateString()]) }}"
               class="p-1.5 hover:bg-surface rounded transition-colors" title="Minggu depan">
                <svg class="h-4 w-4 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
            </a>
        </div>
    </div>

    {{-- ══ Summary Stats ════════════════════════════════════════════ --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-surface border border-border rounded-lg px-4 py-3">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-text-muted">Total Task</p>
            <p class="text-2xl font-bold font-mono text-text-main mt-1">{{ $stats['total'] }}</p>
        </div>
        <div class="bg-surface border border-border rounded-lg px-4 py-3">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-text-muted">Selesai</p>
            <p class="text-2xl font-bold font-mono mt-1" style="color:var(--color-success)">{{ $stats['completed'] }}</p>
        </div>
        <div class="bg-surface border border-border rounded-lg px-4 py-3">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-text-muted">Pending</p>
            <p class="text-2xl font-bold font-mono mt-1" style="color:var(--color-warning)">{{ $stats['pending'] }}</p>
        </div>
        <div class="bg-surface border border-border rounded-lg px-4 py-3">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-text-muted">Dibatalkan</p>
            <p class="text-2xl font-bold font-mono mt-1" style="color:var(--color-error)">{{ $stats['cancelled'] }}</p>
        </div>
    </div>

    {{-- ══ Main Layout: Sidebar + Calendar + Detail Panel ══════════ --}}
    <div class="grid grid-cols-12 gap-4 min-h-96">

        {{-- Sidebar: Tim Aktif ────────────────────────────────────── --}}
        <div class="col-span-12 md:col-span-2 bg-surface border border-border rounded-lg overflow-hidden">
            <div class="px-4 py-2.5 border-b border-border bg-surface-muted">
                <p class="text-xs font-semibold text-text-secondary">TIM AKTIF</p>
            </div>
            <div class="p-3 space-y-2 max-h-96 overflow-y-auto">
                @forelse($activeTeams as $team)
                    <button @click="selectTeam({{ $team['id'] }})"
                            :class="selectedTeamId === {{ $team['id'] }} ? 'ring-2 ring-primary' : ''"
                            class="w-full bg-background border border-border rounded-md px-3 py-2.5 hover:shadow-sm transition-all text-left">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded-full bg-primary/20 flex items-center justify-center text-[10px] font-bold text-primary">
                                {{ $team['initials'] }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold text-text-main truncate">{{ $team['name'] }}</p>
                                <div class="flex items-center gap-1 mt-0.5">
                                    <span class="w-1.5 h-1.5 rounded-full"
                                          :style="'background:' + ('{{ $team['status'] }}' === 'active' ? 'var(--color-success)' : '#9CA3AF')"></span>
                                    <span class="text-[10px] text-text-muted">{{ $team['taskCount'] }} task</span>
                                </div>
                            </div>
                        </div>
                    </button>
                @empty
                    <p class="text-xs text-text-muted text-center py-4">Tidak ada tim aktif</p>
                @endforelse
            </div>
        </div>

        {{-- Calendar Grid: 7 Hari (Sen-Min) ────────────────────────── --}}
        <div class="col-span-12 md:col-span-7 bg-surface border border-border rounded-lg overflow-hidden">
            <div class="grid grid-cols-7 border-b border-border">
                @foreach($days as $dayKey => $dayData)
                    <div class="px-3 py-2.5 bg-surface-muted border-r border-border last:border-r-0 text-center">
                        <p class="text-xs font-semibold text-text-secondary uppercase">{{ $dayData['dayName'] }}</p>
                        <p class="text-lg font-bold font-mono text-text-main">{{ $dayData['dayNum'] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="grid grid-cols-7 divide-x divide-border">
                @foreach($days as $dayKey => $dayData)
                    <div class="p-2 min-h-80 space-y-1.5 overflow-y-auto">
                        @forelse($dayData['tasks'] as $task)
                            <button @click="openTaskDetail({{ $task->id }})"
                                    class="w-full text-left p-2 rounded-md border border-border bg-background hover:shadow-sm transition-all group">
                                <div class="text-[10px] font-mono font-semibold text-text-muted group-hover:text-primary">
                                    {{ $task->task_number }}
                                </div>
                                <p class="text-[11px] font-semibold text-text-main truncate mt-0.5">{{ $task->title }}</p>
                                <p class="text-[10px] text-text-muted truncate">{{ $task->customer?->full_name ?? '—' }}</p>
                                <div class="flex items-center gap-1 mt-1 pt-1 border-t border-border/50">
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold uppercase"
                                          :style="'background:' + getTaskTypeColor('{{ $task->task_type->value }}') + '20; color:' + getTaskTypeColor('{{ $task->task_type->value }}')">
                                        {{ $task->task_type->value }}
                                    </span>
                                </div>
                            </button>
                        @empty
                            <p class="text-xs text-text-muted text-center py-2">—</p>
                        @endforelse
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Detail Panel (Slide-Over) ────────────────────────────────── --}}
        <div class="col-span-12 md:col-span-3 bg-surface border border-border rounded-lg p-4 overflow-y-auto max-h-96">
            <div x-show="!selectedTaskId" class="text-center text-text-muted py-8">
                <p class="text-sm">Pilih task untuk melihat detail</p>
            </div>

            <div x-show="selectedTaskId" x-cloak>
                <div class="space-y-4">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-text-muted">Progress Checklist ({{ selectedTask?.checklist_completed ?? 0 }}/{{ selectedTask?.checklist_total ?? 0 }})</p>
                        <div class="mt-2 bg-background rounded-full h-2 overflow-hidden">
                            <div class="bg-primary h-full transition-all" :style="`width: ${(selectedTask?.checklist_completed || 0) / (selectedTask?.checklist_total || 1) * 100}%`"></div>
                        </div>
                    </div>

                    <div class="space-y-2 border-t border-border pt-3">
                        <div class="flex justify-between">
                            <span class="text-xs text-text-muted">Tim</span>
                            <span class="text-xs font-semibold text-text-main">{{ selectedTask?.team_names ?? '—' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-text-muted">Jadwal</span>
                            <span class="text-xs font-mono text-text-main">{{ selectedTask?.scheduled_at ?? '—' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-text-muted">POP</span>
                            <span class="text-xs font-semibold text-text-main">{{ selectedTask?.pop_name ?? '—' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-text-muted">Pelanggan</span>
                            <span class="text-xs font-semibold text-text-main">{{ selectedTask?.customer_name ?? '—' }}</span>
                        </div>
                    </div>

                    <button @click="selectedTaskId = null" class="w-full mt-4 py-1.5 border border-border rounded text-xs font-semibold text-text-main hover:bg-surface-muted transition-colors">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ Legend ═════════════════════════════════════════════════════ --}}
    <div class="flex items-center gap-6 text-xs">
        <div class="flex items-center gap-2">
            <div class="w-3 h-3 rounded" style="background:var(--color-info)"></div>
            <span class="text-text-muted">Pemasangan</span>
        </div>
        <div class="flex items-center gap-2">
            <div class="w-3 h-3 rounded" style="background:var(--color-warning)"></div>
            <span class="text-text-muted">Maintenance</span>
        </div>
        <div class="flex items-center gap-2">
            <div class="w-3 h-3 rounded" style="background:var(--color-success)"></div>
            <span class="text-text-muted">Ambil modem</span>
        </div>
    </div>
</div>

<script>
function fopCalendarHandler() {
    return {
        selectedTeamId: null,
        selectedTaskId: null,
        selectedTask: null,

        selectTeam(teamId) {
            this.selectedTeamId = teamId;
        },

        openTaskDetail(taskId) {
            this.selectedTaskId = taskId;
            // Fetch task detail via AJAX (optional)
            // fetch(`/api/tasks/${taskId}`).then(r => r.json()).then(data => { this.selectedTask = data; });
        },

        getTaskTypeColor(type) {
            const colors = {
                'survey': 'var(--color-info)',
                'pemasangan': 'var(--color-warning)',
                'maintenance': 'var(--color-success)',
            };
            return colors[type] || 'var(--color-primary)';
        }
    };
}
</script>
@endsection
