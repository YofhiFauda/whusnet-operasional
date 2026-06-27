@props(['task', 'displayMode' => 'list', 'showCountdown' => false])

@php
    $taskArr = is_array($task) ? $task : [
        'id'              => $task->id,
        'task_number'     => $task->task_number,
        'title'           => $task->title,
        'task_type'       => $task->task_type->value ?? $task->task_type,
        'task_type_label' => $task->task_type instanceof \App\Enums\TaskType ? $task->task_type->label() : ($task->task_type_label ?? ''),
        'card_classes'    => $task->task_type instanceof \App\Enums\TaskType ? $task->task_type->cardClasses() : ($task->card_classes ?? ''),
        'status_label'    => $task->status instanceof \App\Enums\TaskStatus ? $task->status->label() : ($task->status_label ?? ''),
        'status'          => $task->status->value ?? $task->status,
        'scheduled_at'    => $task->scheduled_at?->toIso8601String() ?? (is_string($task->scheduled_at ?? null) ? $task->scheduled_at : null),
        'started_at'      => $task->started_at?->toIso8601String() ?? (is_string($task->started_at ?? null) ? $task->started_at : null),
        'completed_at'    => $task->completed_at?->toIso8601String() ?? (is_string($task->completed_at ?? null) ? $task->completed_at : null),
        'sla_minutes'     => $task->sla_minutes,
        'customer_name'   => $task->customer?->full_name ?? '—',
        'pop_name'        => $task->pop?->name ?? '—',
        'team'            => $task->teamMembers ? $task->teamMembers->map(fn($m) => ['name' => $m->user?->name ?? '?'])->toArray() : [],
        'checklist_done'  => $task->checklists ? $task->checklists->where('is_checked', true)->count() : 0,
        'checklist_total' => $task->checklists ? $task->checklists->count() : 0,
        'is_over_sla'     => method_exists($task, 'isOverSla') ? $task->isOverSla() : ($task->is_over_sla ?? false),
    ];

    if (isset($taskArr['task_type']) && $taskArr['task_type'] instanceof \App\Enums\TaskType) {
        $taskArr['task_type'] = $taskArr['task_type']->value;
    }
@endphp

@if($displayMode === 'kanban')
    {{-- Grid card (from FOP) --}}
    <a href="{{ route('tasks.show', $taskArr['id']) }}"
       class="block {{ $taskArr['card_classes'] }} rounded-md border px-3 py-2.5 hover:shadow-sm transition-all">

        {{-- Tipe + SLA badge --}}
        <div class="flex items-center justify-between mb-1.5">
            <span class="text-[10px] font-bold uppercase tracking-wide leading-none">
                {{ $taskArr['task_type_label'] }}
            </span>
            @if($taskArr['is_over_sla'])
            <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded"
                  style="background:var(--color-error); color:white">
                SLA Lewat
            </span>
            @endif
        </div>

        {{-- Pelanggan --}}
        <p class="text-xs font-semibold text-text-main truncate">{{ $taskArr['customer_name'] }}</p>

        {{-- Jadwal --}}
        @if($taskArr['scheduled_at'])
        <p class="text-[11px] font-mono text-text-muted mt-0.5">
            {{ \Carbon\Carbon::parse($taskArr['scheduled_at'])->format('H:i') }}
        </p>
        @endif

        {{-- Countdown SLA Eksekusi --}}
        @if($showCountdown && !empty($taskArr['started_at']))
        @php
            $slaDeadlineIso = \Carbon\Carbon::parse($taskArr['started_at'])
                ->addMinutes((int) $taskArr['sla_minutes'])
                ->toIso8601String();
        @endphp
        <div class="mt-2">
            <x-countdown-timer
                deadline="{{ $slaDeadlineIso }}"
                :total-seconds="(int) $taskArr['sla_minutes'] * 60"
                label="Sisa SLA"
                :compact="true"
            />
        </div>
        @endif

        {{-- Ringkasan Waktu Survey/Pemasangan --}}
        @if(!empty($taskArr['started_at']) && !empty($taskArr['completed_at']))
        @php
            $cardStarted   = \Carbon\Carbon::parse($taskArr['started_at']);
            $cardCompleted = \Carbon\Carbon::parse($taskArr['completed_at']);
            $cardActualMin = (int) $cardStarted->diffInMinutes($cardCompleted);
            $cardHours     = intdiv($cardActualMin, 60);
            $cardRemMins   = $cardActualMin % 60;
            $cardDuration  = $cardHours > 0 ? "{$cardHours}j {$cardRemMins}m" : "{$cardActualMin}m";
            $cardOverSla   = $cardActualMin > (int) $taskArr['sla_minutes'];
            $cardTypeLabel = ($taskArr['task_type'] ?? '') === 'pemasangan' ? 'Pemasangan' : 'Survey';
        @endphp
        <div class="mt-1.5 flex items-center gap-1">
            <span class="text-[10px] text-text-muted">Waktu {{ $cardTypeLabel }}:</span>
            <span class="text-[10px] font-mono font-semibold text-text-main">
                {{ $cardStarted->format('H:i') }}–{{ $cardCompleted->format('H:i') }}
            </span>
            <span class="text-[10px] font-semibold px-1 py-0.5 rounded"
                  style="{{ $cardOverSla
                      ? 'background:var(--color-error-bg); color:var(--color-error)'
                      : 'background:var(--color-success-bg); color:var(--color-success)' }}">
                {{ $cardDuration }}
            </span>
        </div>
        @endif

        {{-- Checklist progress --}}
        @if($taskArr['checklist_total'] > 0)
        <div class="mt-2">
            <div class="h-1 bg-white/40 rounded-full overflow-hidden">
                <div class="h-full rounded-full" style="background:var(--color-success);
                     width: {{ $taskArr['checklist_total'] > 0 ? round($taskArr['checklist_done'] / $taskArr['checklist_total'] * 100) : 0 }}%">
                </div>
            </div>
            <p class="text-[10px] text-text-muted mt-0.5">
                {{ $taskArr['checklist_done'] }}/{{ $taskArr['checklist_total'] }} checklist
            </p>
        </div>
        @endif

        {{-- Tim (initials) --}}
        @if(!empty($taskArr['team']))
        <div class="flex items-center gap-0.5 mt-1.5">
            @foreach(array_slice($taskArr['team'], 0, 3) as $member)
            <span class="inline-flex h-4 w-4 rounded-full bg-white/70 items-center justify-center text-[9px] font-bold text-slate-700"
                  title="{{ $member['name'] }}">
                {{ strtoupper(substr($member['name'], 0, 1)) }}
            </span>
            @endforeach
        </div>
        @endif

    </a>
@else
    {{-- List row card (new, minimal) --}}
    <div class="bg-surface border border-border rounded-lg p-3 hover:shadow-sm transition-all flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex-1">
            <div class="flex items-center gap-2 mb-1">
                <span class="text-xs font-mono font-semibold px-2 py-0.5 rounded bg-surface-muted text-text-secondary border border-border">
                    {{ $taskArr['task_number'] }}
                </span>
                <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded {{ $taskArr['card_classes'] }}">
                    {{ $taskArr['task_type_label'] }}
                </span>
                <span class="text-[10px] font-semibold text-text-muted">
                    &bull; {{ $taskArr['status_label'] }}
                </span>
            </div>
            
            <p class="text-sm font-semibold text-text-main">{{ $taskArr['customer_name'] }}</p>
            
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1.5 text-xs text-text-muted">
                <div class="flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>
                        {{ $taskArr['scheduled_at'] ? \Carbon\Carbon::parse($taskArr['scheduled_at'])->format('d M Y H:i') : 'Belum dijadwalkan' }}
                    </span>
                </div>
                
                @if(!empty($taskArr['team']))
                <div class="flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <span>{{ implode(', ', array_column($taskArr['team'], 'name')) }}</span>
                </div>
                @endif
                
                <div class="flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span>{{ $taskArr['pop_name'] }}</span>
                </div>
            </div>
        </div>

        <div class="flex flex-col md:items-end gap-2 shrink-0">
            @if($taskArr['is_over_sla'] && !in_array($taskArr['status'], ['selesai', 'dibatalkan']))
                <span class="text-[10px] font-semibold px-2 py-1 rounded"
                      style="background:var(--color-error-bg); color:var(--color-error)">
                    SLA Lewat
                </span>
            @endif

            @if($showCountdown && !empty($taskArr['started_at']) && $taskArr['status'] == 'in_progress')
                @php
                    $slaDeadlineIso = \Carbon\Carbon::parse($taskArr['started_at'])
                        ->addMinutes((int) $taskArr['sla_minutes'])
                        ->toIso8601String();
                @endphp
                <x-countdown-timer
                    deadline="{{ $slaDeadlineIso }}"
                    :total-seconds="(int) $taskArr['sla_minutes'] * 60"
                    label="Sisa SLA"
                    :compact="true"
                />
            @endif

            <a href="{{ route('tasks.show', $taskArr['id']) }}" class="inline-flex items-center gap-1 px-3 py-1.5 border border-border rounded text-xs font-semibold text-text-secondary hover:bg-surface-muted transition-colors">
                Detail Task
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
            </a>
        </div>
    </div>
@endif
