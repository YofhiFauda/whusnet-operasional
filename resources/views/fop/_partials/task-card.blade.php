{{--
    Partial: FOP Task Card
    Props:
      $task          — array dari FopDashboardController::taskToCard()
                       atau Eloquent Task object
      $showCountdown — bool, dipakai di S8.2-T001 untuk toggle countdown SLA
--}}
@php
    $taskArr = is_array($task) ? $task : [
        'id'              => $task->id,
        'task_number'     => $task->task_number,
        'title'           => $task->title,
        'task_type'       => $task->task_type->value,          // string: 'survey', 'pemasangan', dll
        'task_type_label' => $task->task_type->label(),
        'card_classes'    => $task->task_type->cardClasses(),
        'status_label'    => $task->status->label(),
        'scheduled_at'    => $task->scheduled_at?->toIso8601String(),
        'started_at'      => $task->started_at?->toIso8601String(),
        'completed_at'    => $task->completed_at?->toIso8601String(),  // diperlukan untuk ringkasan waktu
        'sla_minutes'     => $task->sla_minutes,
        'customer_name'   => $task->customer?->full_name ?? '—',
        'pop_name'        => $task->pop?->name ?? '—',
        'team'            => $task->teamMembers->map(fn($m) => ['name' => $m->user?->name ?? '?'])->toArray(),
        'checklist_done'  => $task->checklists->where('is_checked', true)->count(),
        'checklist_total' => $task->checklists->count(),
        'is_over_sla'     => $task->isOverSla(),
    ];

    // Normalise task_type ke string jika masuk dari array tapi masih berupa Enum
    // (misal dari taskToCard() yang sudah ->value, tapi jaga-jaga)
    if ($taskArr['task_type'] instanceof \App\Enums\TaskType) {
        $taskArr['task_type'] = $taskArr['task_type']->value;
    }
@endphp

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

    {{-- Countdown SLA Eksekusi — aktif saat $showCountdown = true dan task in_progress --}}
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

    {{-- Ringkasan Waktu Survey/Pemasangan — tampil saat task selesai --}}
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
