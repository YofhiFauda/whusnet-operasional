{{--
    ══ Partial: Satu Task Card untuk Dashboard Teknisi ══════════════════
    Digunakan oleh TaskController::cardPartial() → GET /tasks-saya/partial/{task}
    Diambil via fetch() dari Echo listener saat TaskScheduled event diterima.
    $task : App\Models\Task (loaded dengan customer, pop, checklists, evidences)
--}}
@php
    $barColor = match($task->status->value) {
        'terjadwal'   => 'var(--color-info)',
        'in_progress' => 'var(--color-warning)',
        'selesai'     => 'var(--color-success)',
        'dibatalkan'  => 'var(--color-error)',
        default       => 'var(--color-border)',
    };
    $statusStyle = match($task->status->value) {
        'terjadwal'   => 'background:var(--color-info-bg); color:var(--color-info); border-color:var(--color-info-border)',
        'in_progress' => 'background:var(--color-warning-bg); color:var(--color-warning); border-color:var(--color-warning-border)',
        'selesai'     => 'background:var(--color-success-bg); color:var(--color-success); border-color:var(--color-success-border)',
        'dibatalkan'  => 'background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border)',
        default       => 'background:var(--color-surface-muted); color:var(--color-text-muted); border-color:var(--color-border)',
    };
@endphp

<div id="task-card-{{ $task->id }}"
     class="bg-surface border border-border rounded-lg overflow-hidden
            {{ $task->status->value === 'in_progress' ? 'ring-2 ring-amber-400' : '' }}"
     data-task-id="{{ $task->id }}">

    {{-- Status bar atas --}}
    <div class="h-1 w-full" style="background: {{ $barColor }}"></div>

    <div class="px-4 py-4">

        {{-- Header task --}}
        <div class="flex items-start justify-between gap-3 mb-3">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full border {{ $task->task_type->cardClasses() }}">
                    {{ $task->task_type->label() }}
                </span>
                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full border" style="{{ $statusStyle }}">
                    {{ $task->status->label() }}
                </span>
                @if($task->isOverSla())
                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full border"
                      style="background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border)">
                    Melewati SLA
                </span>
                @endif
            </div>
            <span class="font-mono text-[11px] text-text-muted shrink-0">{{ $task->task_number }}</span>
        </div>

        {{-- Nama pelanggan + alamat --}}
        <p class="font-semibold text-text-main">{{ $task->customer?->full_name ?? $task->title }}</p>
        @if($task->customer)
        <p class="text-xs text-text-muted mt-0.5">
            {{ $task->customer->address ?? '' }}
            @if($task->pop)&mdash; {{ $task->pop->name }}@endif
        </p>
        @endif

        {{-- Jadwal --}}
        <div class="flex items-center gap-1.5 mt-2 text-xs text-text-secondary">
            <svg class="h-3.5 w-3.5 shrink-0 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="font-mono font-semibold">{{ $task->scheduled_at?->format('H:i') }}</span>
            <span class="text-text-muted">· SLA {{ $task->sla_minutes }} menit</span>
        </div>

        {{-- Checklist progress --}}
        @if($task->checklists->count() > 0)
        @php
            $done  = $task->checklists->where('is_checked', true)->count();
            $total = $task->checklists->count();
            $pct   = $total > 0 ? round($done / $total * 100) : 0;
        @endphp
        <div class="mt-3">
            <div class="flex items-center justify-between text-[11px] text-text-muted mb-1">
                <span>Checklist</span>
                <span class="font-mono">{{ $done }}/{{ $total }}</span>
            </div>
            <div class="h-1.5 bg-border rounded-full overflow-hidden">
                <div class="h-full rounded-full transition-all"
                     style="background:var(--color-success); width:{{ $pct }}%"></div>
            </div>
        </div>
        @endif

        {{-- Tombol aksi --}}
        <div class="flex items-center gap-2 mt-4 pt-3 border-t border-border">
            <a href="{{ route('tasks.show', $task) }}"
               class="flex-1 text-center text-xs font-semibold py-2 px-3 border border-border rounded-md bg-background hover:bg-surface-muted text-text-secondary transition-colors">
                Buka Detail
            </a>

            @can('statusStart', $task)
            @if($task->status->value === 'terjadwal')
            <form action="{{ route('tasks.start', $task) }}" method="POST" class="flex-1">
                @csrf
                <button type="submit"
                        class="w-full text-xs font-semibold py-2 px-3 rounded-md text-white transition-colors"
                        style="background:var(--color-warning)">
                    Mulai Task
                </button>
            </form>
            @endif
            @endcan
        </div>

    </div>
</div>
