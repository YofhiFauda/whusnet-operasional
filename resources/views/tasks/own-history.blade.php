@extends('layouts.app')

@section('title', 'Riwayat Task Saya')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-6 space-y-5">

    {{-- ══ Page Header ══════════════════════════════════════════════ --}}
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('tasks.own') }}"
               class="h-8 w-8 flex items-center justify-center rounded-md border border-border bg-surface hover:bg-surface-muted text-text-secondary shrink-0">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <div>
                <h1 class="text-base font-semibold text-text-main leading-tight">Riwayat Task Saya</h1>
                <p class="text-xs text-text-muted">Task yang sudah Anda selesaikan</p>
            </div>
        </div>
        <div class="text-right">
            <p class="text-2xl font-bold font-mono text-text-main leading-none">{{ $tasks->total() }}</p>
            <p class="text-[11px] text-text-muted">total selesai</p>
        </div>
    </div>

    {{-- ══ List Riwayat ═════════════════════════════════════════════ --}}
    @if($tasks->count() > 0)
    <div class="space-y-3">
        @foreach($tasks as $task)
        <a href="{{ route('tasks.show', $task) }}"
           class="block bg-surface border border-border rounded-lg overflow-hidden hover:border-primary/40 transition-colors">
            <div class="h-1 w-full" style="background: var(--color-success)"></div>
            <div class="px-4 py-3.5">
                <div class="flex items-start justify-between gap-3 mb-2">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full border {{ $task->task_type->cardClasses() }}">
                            {{ $task->task_type->label() }}
                        </span>
                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full border"
                              style="background:var(--color-success-bg); color:var(--color-success); border-color:var(--color-success-border)">
                            Selesai
                        </span>
                    </div>
                    <span class="font-mono text-[11px] text-text-muted shrink-0">{{ $task->task_number }}</span>
                </div>

                <p class="font-semibold text-text-main">{{ $task->customer?->full_name ?? $task->title }}</p>
                @if($task->customer)
                <p class="text-xs text-text-muted mt-0.5">
                    {{ $task->customer->clean_address ?? '' }}
                    @if($task->pop)&mdash; {{ $task->pop->name }}@endif
                </p>
                @endif

                <div class="flex items-center justify-between mt-2.5 pt-2.5 border-t border-border text-xs">
                    <div class="flex items-center gap-1.5 text-text-secondary">
                        <svg class="h-3.5 w-3.5 shrink-0 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="font-mono font-semibold">
                            {{ $task->completed_at?->translatedFormat('d M Y, H:i') ?? '-' }}
                        </span>
                    </div>
                    <span class="text-[10px] font-semibold text-primary flex items-center gap-1">
                        Lihat Laporan
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </span>
                </div>
            </div>
        </a>
        @endforeach
    </div>

    <div class="pt-2">
        {{ $tasks->links() }}
    </div>
    @else
    <div class="bg-surface border border-border rounded-lg py-10 text-center">
        <p class="text-sm text-text-muted">Belum ada task yang Anda selesaikan.</p>
    </div>
    @endif

</div>
@endsection
