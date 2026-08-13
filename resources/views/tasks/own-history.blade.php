@extends('layouts.app')

@section('title', 'Riwayat Task Saya')

@section('content')
<div class="max-w-4xl mx-auto space-y-5">

    {{-- ══ Page Header (Naked, strict Design.md §1.7) ════════════════════ --}}
    <div class="page-header flex items-center justify-between gap-3 select-none">
        <div class="flex items-center gap-3">
            <a href="{{ route('tasks.own') }}"
               class="h-9 w-9 flex items-center justify-center rounded-xl border border-border bg-surface hover:bg-surface-muted text-text-secondary hover:text-text-main transition-colors shadow-sm cursor-pointer"
               title="Kembali ke Task Saya">
                <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <div>
                <h1 class="page-header-title text-base sm:text-lg font-bold text-text-main font-ui">Riwayat Task Saya</h1>
                <p class="page-header-subtitle text-[11px] text-text-muted font-ui">Daftar task yang telah Anda selesaikan</p>
            </div>
        </div>
        <div class="text-right">
            <span class="font-mono text-base sm:text-lg font-bold text-text-main leading-none">{{ $tasks->total() }}</span>
            <span class="text-[9px] text-text-muted font-ui block uppercase tracking-wider font-semibold">Total Selesai</span>
        </div>
    </div>

    {{-- ══ List Riwayat ═════════════════════════════════════════════ --}}
    @if($tasks->count() > 0)
    <div class="space-y-3.5">
        @foreach($tasks as $task)
        @php
            $lat = $task->customer?->customerAddress?->latitude ?? $task->pop?->latitude;
            $lng = $task->customer?->customerAddress?->longitude ?? $task->pop?->longitude;
            $phone = $task->customer?->primary_phone;
            $phoneWa = $phone ? preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $phone)) : null;
        @endphp
        <div class="relative bg-surface border border-border rounded-2xl overflow-hidden pl-5 hover:border-slate-300 dark:hover:border-slate-700 hover:shadow-sm transition-all duration-200 group">
            
            {{-- Left accent strip: emerald-500 because it's completed --}}
            <div class="absolute left-0 top-0 bottom-0 w-1.5 bg-emerald-500"></div>

            <div class="p-4 sm:p-5 flex flex-col justify-between h-full">
                <div>
                    {{-- Header row --}}
                    <div class="flex items-start justify-between gap-3 mb-2.5">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="text-[10px] font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-md border {{ $task->task_type->cardClasses() }}">
                                {{ $task->task_type->label() }}
                            </span>
                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-md border bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/30 dark:text-emerald-400 dark:border-emerald-900/50 font-ui">
                                Selesai
                            </span>
                        </div>
                        <span class="font-mono text-[10px] font-semibold text-text-muted shrink-0">{{ $task->task_number }}</span>
                    </div>

                    {{-- Customer / Task Title --}}
                    <h3 class="font-bold text-text-main text-base leading-snug group-hover:text-primary transition-colors font-ui">
                        {{ $task->customer?->full_name ?? $task->title }}
                    </h3>

                    {{-- Address --}}
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

                    {{-- Completion Time --}}
                    <div class="flex items-center gap-1.5 mt-3 text-xs text-text-secondary font-medium font-ui">
                        <svg class="h-4 w-4 shrink-0 text-text-muted/70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="font-mono font-semibold">
                            {{ $task->completed_at?->translatedFormat('d M Y, H:i') ?? '-' }}
                        </span>
                    </div>
                </div>

                {{-- Action Row --}}
                <div class="flex items-center justify-between gap-2 mt-4 pt-3.5 border-t border-border">
                    {{-- Left actions: WhatsApp and Maps --}}
                    <div class="flex items-center gap-1.5 shrink-0">
                        @if($phoneWa)
                        <a href="https://wa.me/{{ $phoneWa }}" 
                           target="_blank" 
                           title="Hubungi WhatsApp"
                           class="p-2.5 rounded-xl border border-border bg-surface text-text-secondary hover:bg-emerald-50 dark:hover:bg-emerald-950/30 hover:text-emerald-600 dark:hover:text-emerald-400 hover:border-emerald-200 dark:hover:border-emerald-900/50 active:scale-95 transition-all shadow-sm cursor-pointer">
                            <svg class="h-4.5 w-4.5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.768-.001 1.298.409 2.522 1.189 3.518l-.756 2.766 2.831-.744a5.748 5.748 0 002.504.588h.002c3.18 0 5.767-2.586 5.768-5.766 0-1.541-.6-2.99-1.691-4.08-1.091-1.09-2.539-1.69-4.079-1.648zm0 10.153a4.398 4.398 0 01-2.241-.614l-.16-.095-1.666.438.444-1.624-.105-.167a4.394 4.394 0 01-.67-2.326c.001-2.426 1.975-4.4 4.402-4.4 1.177 0 2.283.458 3.115 1.29a4.382 4.382 0 011.29 3.117c-.001 2.426-1.975-4.4-4.409 4.4z"/>
                            </svg>
                        </a>
                        @endif

                        @if($lat && $lng)
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

                    <a href="{{ route('tasks.show', $task) }}"
                       class="inline-flex items-center justify-center gap-1.5 text-xs font-bold py-2.5 px-4 border border-border rounded-xl bg-surface hover:bg-surface-muted text-primary hover:text-primary-hover transition-all duration-150 shadow-sm cursor-pointer font-ui">
                        <span>Lihat Laporan</span>
                        <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                </div>
            </div>

        </div>
        @endforeach
    </div>

    <div class="pt-2 select-none">
        {{ $tasks->links() }}
    </div>
    @else
    <div class="bg-surface border border-border rounded-2xl py-16 px-4 text-center select-none shadow-xs">
        <svg class="h-10 w-10 text-text-muted opacity-40 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2" />
        </svg>
        <p class="text-sm text-text-muted font-bold font-ui">Belum ada task yang diselesaikan</p>
    </div>
    @endif

</div>
@endsection
