@php
    $accentColor = match(true) {
        $task->isOverSla() || ($task->status->value === 'terjadwal' && $task->scheduled_at && $task->scheduled_at->isPast() && !$task->scheduled_at->isToday()) => 'bg-rose-500',
        $task->status->value === 'in_progress' => 'bg-amber-500',
        $task->status->value === 'selesai' => 'bg-emerald-500',
        $task->status->value === 'dibatalkan' => 'bg-slate-400 dark:bg-slate-600',
        $task->task_type->value === 'SURVEY' => 'bg-sky-500',
        $task->task_type->value === 'PSB' => 'bg-emerald-500',
        default => 'bg-slate-300 dark:bg-slate-700',
    };

    $statusClasses = match($task->status->value) {
        'terjadwal' => 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/30 dark:text-blue-400 dark:border-blue-900/50',
        'in_progress' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/30 dark:text-amber-400 dark:border-amber-900/50',
        'selesai' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/30 dark:text-emerald-400 dark:border-emerald-900/50',
        'dibatalkan' => 'bg-slate-50 text-slate-600 border-slate-200 dark:bg-slate-800/30 dark:text-slate-400 dark:border-slate-700/50',
        'pending' => $task->report_deferred
            ? 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-950/30 dark:text-purple-400 dark:border-purple-900/50'
            : 'bg-yellow-50 text-yellow-700 border-yellow-200 dark:bg-yellow-950/30 dark:text-yellow-400 dark:border-yellow-900/50',
        default => 'bg-slate-50 text-slate-500 border-slate-200 dark:bg-slate-800/30 dark:text-slate-500 dark:border-slate-700/50',
    };

    $lat = $task->customer?->customerAddress?->latitude ?? $task->pop?->latitude;
    $lng = $task->customer?->customerAddress?->longitude ?? $task->pop?->longitude;
    $phone = $task->customer?->primary_phone;
    $phoneWa = $phone ? preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $phone)) : null;
@endphp

<div id="task-card-{{ $task->id }}"
     class="relative bg-surface border border-border rounded-2xl overflow-hidden pl-5 hover:border-slate-300 dark:hover:border-slate-700 hover:shadow-sm transition-all duration-200 {{ $task->status->value === 'in_progress' ? 'ring-1 ring-amber-400 shadow-md shadow-amber-400/5' : '' }}"
     data-task-id="{{ $task->id }}"
     data-task-type="{{ $task->task_type->value }}"
     data-task-status="{{ $task->status->value }}"
     data-customer-name="{{ strtolower($task->customer?->full_name ?? $task->title) }}"
     data-customer-address="{{ strtolower($task->customer?->clean_address ?? '') }}"
     data-task-number="{{ strtolower($task->task_number) }}"
     data-is-overdue="{{ ($task->status->value === 'terjadwal' && $task->scheduled_at && $task->scheduled_at->isPast() && !$task->scheduled_at->isToday()) || $task->isOverSla() ? 'true' : 'false' }}"
     data-priority-weight="{{ $task->fopTask?->priority?->sortOrder() ?? 5 }}"
     data-scheduled-timestamp="{{ $task->scheduled_at?->timestamp ?? 0 }}">

    {{-- Left priority/status bar --}}
    <div class="absolute left-0 top-0 bottom-0 w-1.5 {{ $accentColor }}"></div>

    <div class="p-4 sm:p-5 flex flex-col justify-between h-full">
        <div>
            {{-- Header row --}}
            <div class="flex items-start justify-between gap-3 mb-3">
                <div class="flex items-center gap-1.5 flex-wrap">
                    <span class="text-[10px] font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-md border {{ $task->task_type->cardClasses() }}">
                        {{ $task->task_type->label() }}
                    </span>
                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-md border {{ $statusClasses }}">
                        {{ $task->status->displayLabel($task->report_deferred) }}
                    </span>
                    @if($task->isOverSla())
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-md border bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/30 dark:text-rose-400 dark:border-rose-900/50 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                        Melewati SLA
                    </span>
                    @endif
                    @if($task->status->value === 'terjadwal' && $task->scheduled_at && $task->scheduled_at->isPast() && !$task->scheduled_at->isToday())
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-md border bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/30 dark:text-rose-400 dark:border-rose-900/50 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                        Jadwal Terlewat
                    </span>
                    @endif
                </div>
                <span class="font-mono text-[10px] font-semibold text-text-muted shrink-0">{{ $task->task_number }}</span>
            </div>

            {{-- Customer name --}}
            <h3 class="font-bold text-text-main text-base leading-snug group-hover:text-primary transition-colors">
                {{ $task->customer?->full_name ?? $task->title }}
            </h3>

            {{-- Customer address --}}
            @if($task->customer)
            <p class="text-xs text-text-muted mt-1.5 flex items-start gap-1.5 leading-relaxed font-ui">
                <svg class="h-3.5 w-3.5 text-text-muted/70 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span class="line-clamp-2">
                    {{ $task->customer->clean_address ?? '' }}
                    @if($task->pop)
                        <span class="text-text-muted/65">&mdash; {{ $task->pop->name }}</span>
                    @endif
                </span>
            </p>
            @endif

            {{-- Coordinates display inside card when in progress --}}
            @if($task->status->value !== 'terjadwal' && $lat && $lng)
            <div class="mt-3 p-2.5 bg-surface-muted border border-border rounded-xl flex items-center justify-between gap-3" data-coordinate-card>
                <div class="flex flex-col gap-0.5 min-w-0">
                    <span class="text-[9px] font-semibold uppercase tracking-wider text-text-muted">Koordinat Lokasi</span>
                    <span class="font-mono text-[10px] text-text-secondary truncate">
                        {{ $lat }}, {{ $lng }}
                    </span>
                </div>
            </div>
            @endif

            {{-- Schedule & SLA --}}
            <div class="flex items-center gap-1.5 mt-3 text-xs text-text-secondary font-medium">
                <svg class="h-4 w-4 shrink-0 text-text-muted/70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="font-mono font-semibold">
                    {{ $task->scheduled_at?->isToday() ? $task->scheduled_at->format('H:i') : $task->scheduled_at?->translatedFormat('d M, H:i') }}
                </span>
                <span class="text-text-muted/80">&middot; SLA {{ $task->sla_minutes }} menit</span>
            </div>

            {{-- Countdown timer if in progress --}}
            @if($task->status->value === 'in_progress' && $task->started_at)
                @php
                    $slaDeadlineIso = $task->started_at->addMinutes($task->sla_minutes)->toIso8601String();
                @endphp
                <div class="mt-3">
                    <x-countdown-timer
                        deadline="{{ $slaDeadlineIso }}"
                        :total-seconds="$task->sla_minutes * 60"
                        label="Sisa SLA"
                    />
                </div>
            @endif

            {{-- Completion summary if finished --}}
            @if($task->status->value === 'selesai' && $task->started_at && $task->completed_at)
                @php
                    $actualMinutes = (int) $task->started_at->diffInMinutes($task->completed_at);
                    $actualHours = intdiv($actualMinutes, 60);
                    $actualRemMins = $actualMinutes % 60;
                    $durationLabel = $actualHours > 0 ? "{$actualHours} jam {$actualRemMins} menit" : "{$actualRemMins} menit";
                    $isOverSla = $actualMinutes > $task->sla_minutes;
                    $typeLabel = $task->task_type->value === 'PSB' ? 'Pemasangan' : 'Survey';
                @endphp
                <div class="mt-3 flex items-center gap-1.5 flex-wrap">
                    <svg class="h-3.5 w-3.5 shrink-0 text-text-muted/70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-[11px] font-medium text-text-secondary font-ui">Waktu {{ $typeLabel }}:</span>
                    <span class="text-[11px] font-mono font-semibold text-text-main">
                        {{ $task->started_at->format('H:i') }} &ndash; {{ $task->completed_at->format('H:i') }}
                    </span>
                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-lg border font-ui {{ $isOverSla ? 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/30 dark:text-rose-400 dark:border-rose-900/50' : 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/30 dark:text-emerald-400 dark:border-emerald-900/50' }}">
                        {{ $durationLabel }}
                    </span>
                </div>
            @endif
        </div>

        {{-- Action Buttons --}}
        <div class="flex items-center justify-between gap-2 mt-4 pt-3.5 border-t border-border">
            {{-- Left actions: Navigation and Phone --}}
            <div class="flex items-center gap-1.5 shrink-0">
                @if($task->status->value !== 'terjadwal' && $lat && $lng)
                <a href="https://www.google.com/maps/search/?api=1&query={{ $lat }},{{ $lng }}" 
                   target="_blank" 
                   title="Petunjuk Arah Maps"
                   class="inline-flex items-center justify-center gap-1.5 p-2.5 min-[426px]:px-3.5 rounded-xl border border-border bg-surface text-text-secondary hover:bg-sky-50 dark:hover:bg-sky-950/30 hover:text-sky-600 dark:hover:text-sky-400 hover:border-sky-200 dark:hover:border-sky-900/50 active:scale-95 transition-all shadow-sm cursor-pointer font-ui">
                    <svg class="h-4.5 w-4.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span class="hidden min-[426px]:inline text-xs font-semibold">Maps</span>
                </a>
                @endif
            </div>

            {{-- Right actions: Start/Complete/Detail --}}
            <div class="flex items-center gap-2 flex-1 justify-end min-w-0">
                @if($task->status->value !== 'terjadwal')
                <a href="{{ route('tasks.show', $task) }}"
                   class="inline-flex items-center justify-center text-xs font-bold py-2.5 px-3.5 border border-border rounded-xl bg-surface hover:bg-surface-muted text-text-secondary hover:text-text-main transition-all duration-150 shadow-sm cursor-pointer whitespace-nowrap active:scale-95 font-ui">
                    Buka Detail
                </a>
                @endif

                @if($task->status->value === 'terjadwal')
                    @if($task->task_type->value === 'SURVEY')
                        @if($task->customer_id && auth()->user()->hasPermission('customers.detail.survey.update') && $task->teamMembers->pluck('user_id')->contains(auth()->id()))
                        <form action="{{ route('customers.survey.start', $task->customer_id) }}" method="POST" class="flex-1 max-w-[200px]">
                            @csrf
                            <button type="submit"
                                    class="w-full text-center text-xs font-bold py-2.5 px-4 rounded-xl text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 active:scale-[0.98] transition-all shadow-md shadow-amber-500/10 cursor-pointer whitespace-nowrap font-ui">
                                Mulai Survey
                            </button>
                        </form>
                        @endif
                    @elseif($task->task_type->value === 'PSB')
                        @if($task->customer_id && auth()->user()->hasPermission('customers.detail.installation.update') && $task->teamMembers->pluck('user_id')->contains(auth()->id()))
                        <form action="{{ route('customers.installation.start', $task->customer_id) }}" method="POST" class="flex-1 max-w-[200px]">
                            @csrf
                            <button type="submit"
                                    class="w-full text-center text-xs font-bold py-2.5 px-4 rounded-xl text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 active:scale-[0.98] transition-all shadow-md shadow-amber-500/10 cursor-pointer whitespace-nowrap font-ui">
                                Mulai Pemasangan
                            </button>
                        </form>
                        @endif
                    @else
                        @can('statusStart', $task)
                        <form action="{{ route('tasks.start', $task) }}" method="POST" class="flex-1 max-w-[200px]">
                            @csrf
                            <button type="submit"
                                    class="w-full text-center text-xs font-bold py-2.5 px-4 rounded-xl text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 active:scale-[0.98] transition-all shadow-md shadow-amber-500/10 cursor-pointer whitespace-nowrap font-ui">
                                {{ $task->task_type->value === \App\Enums\TaskType::MAINTENANCE->value && !request()->routeIs('tasks.own.card-partial') ? 'Mulai Maintenance' : 'Mulai Task' }}
                            </button>
                        </form>
                        @endcan
                    @endif
                @endif

                @can('statusComplete', $task)
                @if(in_array($task->status->value, ['in_progress', 'pending']))
                    @php
                        $reportUrl = match(true) {
                            $task->task_type->value === 'SURVEY' => route('customers.survey.report', ['customer' => $task->customer_id, 'return_to' => route('tasks.own')]),
                            $task->task_type->value === 'PSB' => route('customers.installation.report', ['customer' => $task->customer_id, 'return_to' => route('tasks.own')]),
                            default => route('tasks.maintenance.report', $task),
                        };
                    @endphp
                    @if($task->status->value === 'in_progress')
                        <x-task.report-choice-dialog :task="$task" :report-url="$reportUrl" class="inline-flex items-center justify-center text-center text-xs font-bold py-2.5 px-4 rounded-xl text-white bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 active:scale-[0.98] transition-all shadow-md shadow-emerald-500/10 cursor-pointer whitespace-nowrap font-ui">
                            Isi Laporan
                        </x-task.report-choice-dialog>
                    @else
                        <a href="{{ $reportUrl }}"
                           class="inline-flex items-center justify-center text-center text-xs font-bold py-2.5 px-4 rounded-xl text-white bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 active:scale-[0.98] transition-all shadow-md shadow-emerald-500/10 cursor-pointer whitespace-nowrap font-ui">
                            Lanjutkan Laporan
                        </a>
                    @endif
                @endif
                @endcan
            </div>
        </div>
    </div>
</div>
