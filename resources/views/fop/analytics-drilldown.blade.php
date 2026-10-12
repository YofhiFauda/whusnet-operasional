@extends('layouts.app')

@section('title', 'Rincian Analitik FOP')
@section('page_title', 'Rincian Analitik FOP')

@section('content')
<div class="mx-auto max-w-5xl space-y-4">
    <a href="{{ $backUrl }}" class="text-xs font-semibold text-primary hover:underline">← Kembali ke Analitik FOP</a>
    <div class="rounded-xl border border-border bg-surface p-5">
        <h1 class="text-lg font-bold text-text-main">{{ $title }}</h1>
        <p class="mt-1 text-xs text-text-muted">Daftar sumber angka pada periode dan POP yang dipilih.</p>
    </div>
    <div class="overflow-x-auto rounded-xl border border-border bg-surface">
        <table class="w-full text-left text-xs">
            <thead class="border-b border-border bg-surface-muted text-text-muted">
                <tr>
                    <th class="px-4 py-3">Task</th>
                    <th class="px-4 py-3">Tanggal</th>
                    <th class="px-4 py-3">POP</th>
                    <th class="px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @if($fopTasks)
                    @forelse($fopTasks as $fopTask)
                    <tr>
                        <td class="px-4 py-3">
                            @can('fop_tasks.view')
                            <a href="{{ route('fop-tasks.history.show', $fopTask) }}" class="font-semibold text-primary hover:underline">{{ $fopTask->task_number }}</a>
                            @else
                            <span class="font-semibold text-text-main">{{ $fopTask->task_number }}</span>
                            @endcan
                        </td>
                        <td class="px-4 py-3 text-text-secondary">{{ $fopTask->task_date?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-text-secondary">{{ $fopTask->pop?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-text-secondary">{{ $fopTask->status?->value ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-text-muted">Tidak ada task pada rincian ini.</td></tr>
                    @endforelse
                @else
                    @forelse($tasks as $task)
                    <tr>
                        <td class="px-4 py-3">
                            @if(auth()->user()->hasPermission('task.view.all'))
                            <a href="{{ route('tasks.show', $task) }}" class="font-semibold text-primary hover:underline">{{ $task->task_number }}</a>
                            @elseif(auth()->user()->hasPermission('fop_tasks.view') && $task->fopTask)
                            <a href="{{ route('fop-tasks.history.show', $task->fopTask) }}" class="font-semibold text-primary hover:underline">{{ $task->task_number }}</a>
                            @else
                            <span class="font-semibold text-text-main">{{ $task->task_number }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-text-secondary">{{ $task->scheduled_at?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-text-secondary">{{ $task->pop?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-text-secondary">{{ $task->status?->value ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-text-muted">Tidak ada task pada rincian ini.</td></tr>
                    @endforelse
                @endif
            </tbody>
        </table>
    </div>
    {{ ($fopTasks ?? $tasks)->links() }}
</div>
@endsection
