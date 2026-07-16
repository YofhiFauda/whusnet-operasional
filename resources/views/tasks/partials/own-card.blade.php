{{--
    ══ Partial: Satu Task Card untuk Dashboard Teknisi ══════════════════
    Digunakan oleh TaskController::cardPartial() → GET /tasks-saya/partial/{task}
    Diambil via fetch() dari Echo listener saat TaskScheduled event diterima.
    $task : App\Models\Task (loaded dengan customer, pop, evidences)
--}}
@php
    $barColor = match(true) {
        $task->status->value === 'terjadwal'   => 'var(--color-info)',
        $task->status->value === 'in_progress' => 'var(--color-warning)',
        $task->status->value === 'selesai'     => 'var(--color-success)',
        $task->status->value === 'dibatalkan'  => 'var(--color-error)',
        $task->status->value === 'pending' && $task->report_deferred => '#7c3aed',
        $task->status->value === 'pending'     => '#a16207',
        default       => 'var(--color-border)',
    };
    $statusStyle = match(true) {
        $task->status->value === 'terjadwal'   => 'background:var(--color-info-bg); color:var(--color-info); border-color:var(--color-info-border)',
        $task->status->value === 'in_progress' => 'background:var(--color-warning-bg); color:var(--color-warning); border-color:var(--color-warning-border)',
        $task->status->value === 'selesai'     => 'background:var(--color-success-bg); color:var(--color-success); border-color:var(--color-success-border)',
        $task->status->value === 'dibatalkan'  => 'background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border)',
        $task->status->value === 'pending' && $task->report_deferred => 'background:#f5f3ff; color:#6d28d9; border-color:#c4b5fd',
        $task->status->value === 'pending'     => 'background:#fefce8; color:#a16207; border-color:#fde68a',
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
                    {{ $task->status->displayLabel($task->report_deferred) }}
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

        {{-- Nama pelanggan + alamat — selalu ditampilkan --}}
        <p class="font-semibold text-text-main">{{ $task->customer?->full_name ?? $task->title }}</p>
        @if($task->customer)
        <p class="text-xs text-text-muted mt-0.5">
            {{ $task->customer->clean_address ?? '' }}
            @if($task->pop)&mdash; {{ $task->pop->name }}@endif
        </p>
        @endif

        {{-- Koordinat Lokasi + Maps — digate sampai task mulai dikerjakan (S8.4-T011) --}}
        @if($task->status->value !== 'terjadwal')
        @php
            $lat = $task->customer?->customerAddress?->latitude ?? $task->pop?->latitude;
            $lng = $task->customer?->customerAddress?->longitude ?? $task->pop?->longitude;
        @endphp
        @if($lat && $lng)
        <div class="mt-2.5 p-2 bg-surface-muted border border-border rounded-md flex items-center justify-between gap-3" data-coordinate-card>
            <div class="flex flex-col gap-0.5 min-w-0">
                <span class="text-[9px] font-semibold uppercase tracking-wider text-text-muted">Koordinat Lokasi</span>
                <span class="font-mono text-[10px] text-text-secondary truncate">
                    {{ $lat }}, {{ $lng }}
                </span>
            </div>
            <a href="https://www.google.com/maps/search/?api=1&query={{ $lat }},{{ $lng }}" 
               target="_blank"
               class="shrink-0 inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-1 border border-border rounded bg-surface hover:bg-surface-muted text-primary transition-colors cursor-pointer"
               data-map-button>
                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                Maps
            </a>
        </div>
        @endif
        @endif

        {{-- Jadwal --}}
        <div class="flex items-center gap-1.5 mt-2 text-xs text-text-secondary">
            <svg class="h-3.5 w-3.5 shrink-0 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="font-mono font-semibold">{{ $task->scheduled_at?->format('H:i') }}</span>
            <span class="text-text-muted">· SLA {{ $task->sla_minutes }} menit</span>
        </div>

        {{-- Tombol aksi --}}
        <div class="flex items-center gap-2 mt-4 pt-3 border-t border-border">
            {{-- "Buka Detail" digate sampai task mulai dikerjakan (S8.4-T011) --}}
            @if($task->status->value !== 'terjadwal')
            <a href="{{ route('tasks.show', $task) }}"
               class="flex-1 text-center text-xs font-semibold py-2 px-3 border border-border rounded-md bg-background hover:bg-surface-muted text-text-secondary transition-colors">
                Buka Detail
            </a>
            @endif

            @if(in_array($task->status->value, ['in_progress', 'pending']))
                @php
                    $reportUrl = match(true) {
                        $task->task_type->value === 'SURVEY' => route('customers.survey.report', $task->customer_id),
                        $task->task_type->value === 'PSB' => route('customers.installation.report', $task->customer_id),
                        default => route('tasks.maintenance.report', $task),
                    };
                @endphp
                @if($task->status->value === 'in_progress')
                    <x-task.report-choice-dialog :task="$task" :report-url="$reportUrl" class="flex-1 justify-center">
                        Isi Laporan
                    </x-task.report-choice-dialog>
                @else
                    <a href="{{ $reportUrl }}"
                       class="flex-1 text-center text-xs font-semibold py-2 px-3 rounded-md text-white transition-colors"
                       style="background:var(--color-success)">
                        Lanjutkan Laporan
                    </a>
                @endif
            @endif

            @if($task->status->value === 'terjadwal')
                @if($task->task_type->value === 'SURVEY')
                    @if($task->customer_id && auth()->user()->hasPermission('customers.detail.survey.update') && $task->teamMembers->pluck('user_id')->contains(auth()->id()))
                    <form action="{{ route('customers.survey.start', $task->customer_id) }}" method="POST" class="flex-1">
                        @csrf
                        <button type="submit"
                                class="w-full text-xs font-semibold py-2 px-3 rounded-md text-white transition-colors"
                                style="background:var(--color-warning)">
                            Mulai Survey
                        </button>
                    </form>
                    @endif
                @elseif($task->task_type->value === 'PSB')
                    @if($task->customer_id && auth()->user()->hasPermission('customers.detail.installation.update') && $task->teamMembers->pluck('user_id')->contains(auth()->id()))
                    <form action="{{ route('customers.installation.start', $task->customer_id) }}" method="POST" class="flex-1">
                        @csrf
                        <button type="submit"
                                class="w-full text-xs font-semibold py-2 px-3 rounded-md text-white transition-colors"
                                style="background:var(--color-warning)">
                            Mulai Pemasangan
                        </button>
                    </form>
                    @endif
                @else
                    @can('statusStart', $task)
                    <form action="{{ route('tasks.start', $task) }}" method="POST" class="flex-1">
                        @csrf
                        <button type="submit"
                                class="w-full text-xs font-semibold py-2 px-3 rounded-md text-white transition-colors"
                                style="background:var(--color-warning)">
                            Mulai Task
                        </button>
                    </form>
                    @endcan
                @endif
            @endif
        </div>

    </div>
</div>
