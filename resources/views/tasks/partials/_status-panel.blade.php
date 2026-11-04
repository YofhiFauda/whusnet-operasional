@php
    $statusStyle = match($task->status->value) {
        'terjadwal'  => 'background:var(--color-info-bg); color:var(--color-info); border-color:var(--color-info-border)',
        'in_progress'=> 'background:var(--color-warning-bg); color:var(--color-warning); border-color:var(--color-warning-border)',
        'lapor_nanti'=> 'background:#f5f3ff; color:#6d28d9; border-color:#c4b5fd',
        'selesai'    => 'background:var(--color-success-bg); color:var(--color-success); border-color:var(--color-success-border)',
        'dibatalkan' => 'background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border)',
        default      => 'background:var(--color-surface-muted); color:var(--color-text-muted); border-color:var(--color-border)',
    };
@endphp
<span id="task-status-panel" class="text-[10px] font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-md border font-ui" style="{{ $statusStyle }}">
    {{ $task->status->label() }}
</span>
