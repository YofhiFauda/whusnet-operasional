@extends('layouts.app')
@php
    // Kumpulkan task survey in_progress agar data-nya bisa dipakai Alpine.js
    $surveyTasksData = $tasks->filter(fn($t) =>
        in_array($t->status->value, ['in_progress', 'pending']) &&
        $t->task_type->value === 'SURVEY'
    )->map(fn($t) => [
        'id'            => $t->id,
        'customer_name' => $t->customer?->full_name ?? $t->title,
        'customer_address' => $t->customer?->address ?? '—',
        'pop_name'      => $t->pop?->name ?? '—',
        'task_number'   => $t->task_number,
        'evidence_count'=> $t->evidences->count(),
        'can_complete'  => $t->canComplete(),
        'evidence_url'  => route('tasks.evidences.store', $t),
        'submit_url'    => route('customers.survey.store', $t->customer_id),
    ])->values()->toArray();

    // Kumpulkan task pemasangan in_progress agar data-nya bisa dipakai Alpine.js
    $installTasksData = $tasks->filter(fn($t) =>
        in_array($t->status->value, ['in_progress', 'pending']) &&
        $t->task_type->value === 'PSB'
    )->map(fn($t) => [
        'id'            => $t->id,
        'customer_name' => $t->customer?->full_name ?? $t->title,
        'customer_address' => $t->customer?->address ?? '—',
        'pop_name'      => $t->pop?->name ?? '—',
        'task_number'   => $t->task_number,
        'current_package_id' => $t->customer?->customerService?->internet_package_id,
        'evidence_count'=> $t->evidences->count(),
        'can_complete'  => $t->canComplete(),
        'evidence_url'  => route('tasks.evidences.store', $t),
        'submit_url'    => route('customers.installation.store', $t->customer_id),
    ])->values()->toArray();

    $packages = \App\Models\InternetPackage::active()->orderBy('name')->get();
@endphp

@section('title', 'Task Saya')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-6 space-y-5">

    {{-- ══ Page Header ══════════════════════════════════════════════ --}}
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <svg class="h-5 w-5 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
            </svg>
            <div>
                <h1 class="text-base font-semibold text-text-main leading-tight">Task Saya Hari Ini</h1>
                <p class="text-xs text-text-muted">
                    Halo, {{ auth()->user()->name }} 👋 &mdash; {{ now()->translatedFormat('l, d F Y') }}
                </p>
            </div>
        </div>
        <div class="text-right">
            <p class="text-2xl font-bold font-mono text-text-main leading-none">{{ $tasks->count() }}</p>
            <p class="text-[11px] text-text-muted">task hari ini</p>
        </div>
    </div>

    {{-- ══ Banner Notifikasi Real-Time (S8.2-T010) ═══════════════════ --}}
    {{-- Banner muncul saat FOP assign task baru via echo channel private-teknisi.{userId} --}}
    <div x-data="technicianNotifier()"
         x-show="banner.visible"
         x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 -translate-y-3"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-3"
         class="flex items-start gap-3 px-4 py-3 rounded-lg border shadow-md cursor-pointer"
         style="background:var(--color-primary-soft,#eff6ff); border-color:var(--color-primary-border,#93c5fd); color:var(--color-primary,#2563eb)"
         @click="scrollToCard()"
         id="task-notification-banner"
         role="alert"
         aria-live="polite">

        {{-- Bell Icon --}}
        <svg class="h-5 w-5 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>

        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold leading-tight" x-text="banner.title"></p>
            <p class="text-xs mt-0.5 opacity-80" x-text="banner.subtitle"></p>
            <p class="text-[11px] mt-1 opacity-60">Klik banner ini untuk melihat task baru &darr;</p>
        </div>

        {{-- Close button --}}
        <button @click.stop="dismissBanner()"
                class="shrink-0 p-1 rounded hover:opacity-70 transition-opacity"
                title="Tutup notifikasi"
                aria-label="Tutup notifikasi">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    {{-- Flash messages ditangani otomatis oleh global Component Toast (<x-toast />) --}}

    {{-- ══ Task Hari Ini ════════════════════════════════════════════ --}}
    @if($tasks->count() > 0)
    <div class="space-y-3" id="today-task-list">
        @foreach($tasks as $task)
        <div class="bg-surface border border-border rounded-lg overflow-hidden
            @if($task->status->value === 'in_progress') ring-2 ring-amber-400 @endif">

            {{-- Status bar atas --}}
            @php
                $barColor = match($task->status->value) {
                    'terjadwal'   => 'var(--color-info)',
                    'in_progress' => 'var(--color-warning)',
                    'selesai'     => 'var(--color-success)',
                    'dibatalkan'  => 'var(--color-error)',
                    default       => 'var(--color-border)',
                };
            @endphp
            <div class="h-1 w-full" style="background: {{ $barColor }}"></div>

            <div class="px-4 py-4">

                {{-- Header task --}}
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div class="flex items-center gap-2 flex-wrap">
                        {{-- Tipe badge --}}
                        <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full border {{ $task->task_type->cardClasses() }}">
                            {{ $task->task_type->label() }}
                        </span>
                        {{-- Status badge --}}
                        @php
                            $statusStyle = match($task->status->value) {
                                'terjadwal'  => 'background:var(--color-info-bg); color:var(--color-info); border-color:var(--color-info-border)',
                                'in_progress'=> 'background:var(--color-warning-bg); color:var(--color-warning); border-color:var(--color-warning-border)',
                                'selesai'    => 'background:var(--color-success-bg); color:var(--color-success); border-color:var(--color-success-border)',
                                'dibatalkan' => 'background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border)',
                                default      => 'background:var(--color-surface-muted); color:var(--color-text-muted); border-color:var(--color-border)',
                            };
                        @endphp
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
                    @if($task->pop)
                        &mdash; {{ $task->pop->name }}
                    @endif
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

                {{-- Countdown SLA Eksekusi — aktif saat in_progress --}}
                @if($task->status->value === 'in_progress' && $task->started_at)
                @php
                    $slaDeadlineIso = $task->started_at
                        ->addMinutes($task->sla_minutes)
                        ->toIso8601String();
                @endphp
                <div class="mt-2">
                    <x-countdown-timer
                        deadline="{{ $slaDeadlineIso }}"
                        :total-seconds="$task->sla_minutes * 60"
                        label="Sisa SLA"
                    />
                </div>
                @endif

                {{-- Ringkasan Waktu Survey/Pemasangan — tampil setelah task selesai --}}
                @if($task->status->value === 'selesai' && $task->started_at && $task->completed_at)
                @php
                    $taskStartedAt   = $task->started_at;
                    $taskCompletedAt = $task->completed_at;
                    $actualMinutes   = (int) $taskStartedAt->diffInMinutes($taskCompletedAt);
                    $actualHours     = intdiv($actualMinutes, 60);
                    $actualRemMins   = $actualMinutes % 60;
                    $durationLabel   = $actualHours > 0
                        ? "{$actualHours} jam {$actualRemMins} menit"
                        : "{$actualRemMins} menit";
                    $isOverSla       = $actualMinutes > $task->sla_minutes;
                    $typeLabel       = $task->task_type->value === 'PSB' ? 'Pemasangan' : 'Survey';
                @endphp
                <div class="mt-2 flex items-center gap-1.5">
                    <svg class="h-3 w-3 shrink-0 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-[11px] font-medium text-text-secondary">
                        Waktu {{ $typeLabel }}:
                    </span>
                    <span class="text-[11px] font-mono font-semibold text-text-main">
                        {{ $taskStartedAt->format('H:i') }} – {{ $taskCompletedAt->format('H:i') }}
                    </span>
                    <span class="text-[11px] font-semibold px-1.5 py-0.5 rounded"
                          style="{{ $isOverSla
                              ? 'background:var(--color-error-bg); color:var(--color-error)'
                              : 'background:var(--color-success-bg); color:var(--color-success)' }}">
                        {{ $durationLabel }}
                    </span>
                </div>
                @endif

                {{-- Tombol aksi --}}
                <div class="flex items-center gap-2 mt-4 pt-3 border-t border-border">
                    <a href="{{ route('tasks.show', $task) }}"
                       class="flex-1 text-center text-xs font-semibold py-2 px-3 border border-border rounded-md bg-background hover:bg-surface-muted text-text-secondary transition-colors">
                        Buka Detail
                    </a>

                    @if($task->status->value === 'terjadwal')
                        @if($task->task_type->value === 'SURVEY')
                            @if(auth()->user()->hasPermission('customers.detail.survey.update') && $task->teamMembers->pluck('user_id')->contains(auth()->id()))
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
                            @if(auth()->user()->hasPermission('customers.detail.installation.update') && $task->teamMembers->pluck('user_id')->contains(auth()->id()))
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
                                    {{ $task->task_type->value === \App\Enums\TaskType::MAINTENANCE->value ? 'Mulai Maintenance' : 'Mulai Task' }}
                                </button>
                            </form>
                            @endcan
                        @endif
                    @endif

                    @can('statusComplete', $task)
                    @if(in_array($task->status->value, ['in_progress', 'pending']))
                    @if($task->task_type->value === 'SURVEY')
                    <button type="button" @click="$dispatch('open-survey-report', { taskId: {{ $task->id }} })"
                            class="flex-1 text-center text-xs font-semibold py-2 px-3 rounded-md text-white transition-colors"
                            style="background:var(--color-success)">
                        Isi Laporan
                    </button>
                    @elseif($task->task_type->value === 'PSB')
                    <button type="button" @click="$dispatch('open-install-report', { taskId: {{ $task->id }} })"
                            class="flex-1 text-center text-xs font-semibold py-2 px-3 rounded-md text-white transition-colors"
                            style="background:var(--color-success)">
                        Isi Laporan
                    </button>
                    @else
                    <a href="{{ route('tasks.maintenance.report', $task) }}"
                       class="flex-1 text-center text-xs font-semibold py-2 px-3 rounded-md text-white transition-colors"
                       style="background:var(--color-success)">
                        Isi Laporan
                    </a>
                    @endif
                    @endif
                    @endcan
                </div>

            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="bg-surface border border-border rounded-lg flex flex-col items-center justify-center py-16 gap-3"
         data-empty-tasks>
        <svg class="h-10 w-10 text-text-muted opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
        </svg>
        <p class="text-sm text-text-muted">Tidak ada task untuk hari ini.</p>
        <p class="text-xs text-text-muted">Hubungi FOP jika ada penugasan yang belum muncul.</p>
    </div>
    @endif

    {{-- ══ Task Mendatang ════════════════════════════════════════════ --}}
    @if($upcomingTasks->count() > 0)
    <div data-section="mendatang">
        <p class="text-[11px] font-semibold uppercase tracking-widest text-text-muted mb-3">Jadwal Mendatang</p>
        <div class="space-y-2">
            @foreach($upcomingTasks as $task)
            <a href="{{ route('tasks.show', $task) }}"
               class="flex items-center justify-between bg-surface border border-border rounded-lg px-4 py-3 hover:bg-surface-muted transition-colors">
                <div class="flex items-center gap-3">
                    <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full border {{ $task->task_type->cardClasses() }}">
                        {{ $task->task_type->label() }}
                    </span>
                    <div>
                        <p class="text-sm font-medium text-text-main">{{ $task->customer?->full_name ?? $task->title }}</p>
                        <p class="text-[11px] text-text-muted">
                            {{ $task->scheduled_at?->translatedFormat('l, d M') }} &middot;
                            {{ $task->scheduled_at?->format('H:i') }}
                        </p>
                    </div>
                </div>
                <svg class="h-4 w-4 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
            </a>
            @endforeach
        </div>
    </div>
    @endif

</div>

{{-- ══ Slide-Over: Laporan Survey Multi-Step ════════════════════════════ --}}
<div x-data="surveyReportWizard({{ json_encode($surveyTasksData) }})"
     @open-survey-report.window="openSurveyReport($event.detail.taskId)"
     x-show="open"
     x-cloak
     class="fixed inset-0 z-50 flex"
     style="display:none">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/50" @click="closeSlideOver()"></div>

    {{-- Drawer --}}
    <div class="relative ml-auto w-full max-w-lg h-full bg-surface shadow-2xl flex flex-col overflow-hidden"
         @click.stop>

        {{-- Header --}}
        <div class="flex items-center justify-between px-5 py-4 border-b border-border shrink-0">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-text-muted">Laporan Survey</p>
                <p class="text-sm font-semibold text-text-main mt-0.5" x-text="activeTask?.customer_name"></p>
            </div>
            <button @click="closeSlideOver()" class="p-1.5 rounded-md hover:bg-surface-muted transition-colors">
                <svg class="h-4 w-4 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        {{-- Step Pills --}}
        <div class="flex items-center gap-1 px-5 py-3 border-b border-border shrink-0 overflow-x-auto">
            <template x-for="(label, idx) in steps" :key="idx">
                <button @click="goToStep(idx)" class="flex items-center gap-1 shrink-0">
                    <span class="h-5 w-5 rounded-full flex items-center justify-center text-[10px] font-bold transition-colors"
                          :style="idx === currentStep ? 'background:var(--color-primary); color:white'
                                  : (idx < currentStep ? 'background:var(--color-success); color:white'
                                  : 'background:var(--color-surface-muted); color:var(--color-text-muted)')">
                        <template x-if="idx < currentStep">✓</template>
                        <template x-if="idx >= currentStep" ><span x-text="idx+1"></span></template>
                    </span>
                    <span class="text-[11px] font-medium whitespace-nowrap"
                          :style="idx === currentStep ? 'color:var(--color-primary)' : 'color:var(--color-text-muted)'"
                          x-text="label">
                    </span>
                    <template x-if="idx < steps.length - 1">
                        <svg class="h-3 w-3 text-border mx-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </template>
                </button>
            </template>
        </div>

        {{-- Body —scrollable --}}
        <div class="flex-1 overflow-y-auto p-5 space-y-4">

            {{-- Step 1: Data Diri --}}
            <div x-show="currentStep === 0">
                <h3 class="text-sm font-semibold text-text-main mb-3">Verifikasi Data</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex gap-3">
                        <span class="text-text-muted w-28 shrink-0 text-xs pt-0.5">Pelanggan</span>
                        <span class="font-medium text-text-main" x-text="activeTask?.customer_name"></span>
                    </div>
                    <div class="flex gap-3">
                        <span class="text-text-muted w-28 shrink-0 text-xs pt-0.5">Alamat</span>
                        <span class="text-text-secondary" x-text="activeTask?.customer_address"></span>
                    </div>
                    <div class="flex gap-3">
                        <span class="text-text-muted w-28 shrink-0 text-xs pt-0.5">POP</span>
                        <span class="text-text-secondary" x-text="activeTask?.pop_name"></span>
                    </div>
                    <div class="flex gap-3">
                        <span class="text-text-muted w-28 shrink-0 text-xs pt-0.5">No. Task</span>
                        <span class="font-mono text-text-secondary" x-text="activeTask?.task_number"></span>
                    </div>
                    <div class="flex gap-3">
                        <span class="text-text-muted w-28 shrink-0 text-xs pt-0.5">Teknisi</span>
                        <span class="font-medium text-text-main">{{ auth()->user()->name }}</span>
                    </div>
                </div>
                <p class="mt-4 text-xs text-text-muted p-3 rounded-md" style="background:var(--color-info-bg); border:1px solid var(--color-info-border); color:var(--color-info)">
                    Pastikan data di atas sesuai. Jika tidak sesuai, hubungi FOP untuk koreksi.
                </p>
            </div>

            {{-- Step 2: Foto Lokasi --}}
            <div x-show="currentStep === 1">
                <h3 class="text-sm font-semibold text-text-main mb-1">Foto Lokasi</h3>
                <p class="text-xs text-text-muted mb-3">Upload minimal 1 foto lokasi/rumah pelanggan.</p>

                {{-- Preview foto --}}
                <div class="grid grid-cols-3 gap-2 mb-3" x-show="uploadedPhotos.length > 0">
                    <template x-for="(photo, idx) in uploadedPhotos" :key="idx">
                        <div class="relative aspect-square rounded-md overflow-hidden border border-border bg-surface-muted">
                            <img :src="photo.url" class="h-full w-full object-cover" alt="Bukti">
                            <span class="absolute bottom-0 left-0 right-0 text-[9px] text-white px-1 py-0.5 truncate"
                                  style="background:rgba(0,0,0,0.6)" x-text="photo.caption"></span>
                        </div>
                    </template>
                </div>

                {{-- Upload zone --}}
                <label class="block cursor-pointer" x-show="!uploading">
                    <input type="file" accept="image/*" capture="environment" class="hidden"
                           @change="uploadPhoto($event.target, 'Foto Lokasi')">
                    <div class="flex items-center justify-center gap-2 py-4 border-2 border-dashed rounded-md text-xs font-medium transition-colors"
                         style="border-color:var(--color-primary-border); color:var(--color-primary)">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Ambil / Pilih Foto
                    </div>
                </label>
                <div x-show="uploading" class="flex items-center gap-2 text-xs text-text-muted py-3">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Mengupload...
                </div>
                <p x-show="uploadError" x-text="uploadError" class="text-xs mt-1" style="color:var(--color-error)"></p>

                {{-- Jumlah foto --}}
                <p class="text-xs mt-2" :style="uploadedPhotos.length >= 1 ? 'color:var(--color-success)' : 'color:var(--color-text-muted)'">
                    <span x-text="uploadedPhotos.length"></span> foto diupload
                    <span x-show="uploadedPhotos.length < 1"> (minimal 1)</span>
                </p>
            </div>

            {{-- Step 3: Cek Sinyal --}}
            <div x-show="currentStep === 2">
                <h3 class="text-sm font-semibold text-text-main mb-3">Cek Sinyal</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">Kekuatan Sinyal (dBm)</label>
                        <input type="number" x-model="form.signal_strength_dbm" min="-120" max="0"
                               placeholder="Contoh: -65"
                               class="w-full text-sm px-3 py-2 border border-border rounded-md bg-background text-text-main focus:outline-none focus:ring-2"
                               style="--tw-ring-color: var(--color-primary)">
                        <p class="text-[11px] text-text-muted mt-0.5">Opsional. Isi jika ada alat ukur sinyal.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">Catatan Kondisi Sinyal</label>
                        <textarea x-model="form.signal_note" rows="3"
                                  placeholder="Kondisi sinyal, hambatan, dll..."
                                  class="w-full text-sm px-3 py-2 border border-border rounded-md bg-background text-text-main focus:outline-none resize-none"></textarea>
                    </div>
                </div>
            </div>

            {{-- Step 4: Teknis Jaringan --}}
            <div x-show="currentStep === 3">
                <h3 class="text-sm font-semibold text-text-main mb-3">Teknis Jaringan</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">
                            Jarak dari POP (meter) <span style="color:var(--color-error)">*</span>
                        </label>
                        <input type="number" x-model="form.cable_estimation_meter" min="0"
                               placeholder="Contoh: 320"
                               class="w-full text-sm px-3 py-2 border border-border rounded-md bg-background text-text-main focus:outline-none focus:ring-2"
                               :style="stepErrors.cable ? 'border-color:var(--color-error)' : ''">
                        <p x-show="stepErrors.cable" class="text-[11px] mt-0.5" style="color:var(--color-error)">Wajib diisi.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">
                            ODP/Node Terdekat <span style="color:var(--color-error)">*</span>
                        </label>
                        <input type="text" x-model="form.nearest_odp"
                               placeholder="Contoh: ODP-SMN-001"
                               class="w-full text-sm px-3 py-2 border border-border rounded-md bg-background text-text-main focus:outline-none focus:ring-2"
                               :style="stepErrors.odp ? 'border-color:var(--color-error)' : ''">
                        <p x-show="stepErrors.odp" class="text-[11px] mt-0.5" style="color:var(--color-error)">Wajib diisi.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">
                            Tipe Media Rekomendasi <span style="color:var(--color-error)">*</span>
                        </label>
                        <div class="flex gap-2">
                            <template x-for="opt in ['Fiber','Wireless','UTP']" :key="opt">
                                <button type="button"
                                        @click="form.media_type = opt"
                                        class="flex-1 py-2 text-xs font-semibold rounded-md border transition-colors"
                                        :style="form.media_type === opt
                                            ? 'background:var(--color-primary); color:white; border-color:var(--color-primary)'
                                            : 'background:var(--color-surface); color:var(--color-text-secondary); border-color:var(--color-border)'">
                                    <span x-text="opt"></span>
                                </button>
                            </template>
                        </div>
                        <p x-show="stepErrors.media" class="text-[11px] mt-0.5" style="color:var(--color-error)">Pilih tipe media.</p>
                    </div>
                </div>
            </div>

            {{-- Step 5: Kesimpulan + TTD --}}
            <div x-show="currentStep === 4">
                <h3 class="text-sm font-semibold text-text-main mb-3">Kesimpulan Survey</h3>
                <div class="space-y-4">
                    {{-- Hasil survey --}}
                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-2">
                            Hasil Survey <span style="color:var(--color-error)">*</span>
                        </label>
                        <div class="space-y-2">
                            <template x-for="opt in surveyResultOptions" :key="opt.value">
                                <label class="flex items-center gap-3 p-3 border rounded-md cursor-pointer transition-colors"
                                       :style="form.survey_result === opt.value
                                           ? 'border-color:var(--color-primary); background:var(--color-primary-soft)'
                                           : 'border-color:var(--color-border); background:var(--color-surface)'">
                                    <input type="radio" :value="opt.value" x-model="form.survey_result" class="sr-only">
                                    <span class="h-4 w-4 rounded-full border-2 flex items-center justify-center shrink-0"
                                          :style="form.survey_result === opt.value
                                              ? 'border-color:var(--color-primary)'
                                              : 'border-color:var(--color-border)'">
                                        <span class="h-2 w-2 rounded-full" x-show="form.survey_result === opt.value"
                                              style="background:var(--color-primary)"></span>
                                    </span>
                                    <div>
                                        <p class="text-sm font-medium" x-text="opt.label"></p>
                                        <p class="text-[11px] text-text-muted" x-text="opt.desc"></p>
                                    </div>
                                </label>
                            </template>
                        </div>
                        <p x-show="stepErrors.result" class="text-[11px] mt-1" style="color:var(--color-error)">Pilih hasil survey.</p>
                    </div>

                    {{-- Alasan jika tidak layak --}}
                    <div x-show="form.survey_result === 'tidak_layak'">
                        <label class="block text-xs font-medium text-text-secondary mb-1">
                            Alasan Tidak Layak <span style="color:var(--color-error)">*</span>
                        </label>
                        <textarea x-model="form.reject_reason" rows="3"
                                  placeholder="Jelaskan alasan tidak layak..."
                                  class="w-full text-sm px-3 py-2 border border-border rounded-md bg-background text-text-main focus:outline-none resize-none"></textarea>
                    </div>

                    {{-- Tingkat kesulitan --}}
                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-2">
                            Tingkat Kesulitan Pemasangan <span style="color:var(--color-error)">*</span>
                        </label>
                        <div class="flex gap-2">
                            <template x-for="opt in ['MUDAH','SEDANG','SULIT']" :key="opt">
                                <button type="button"
                                        @click="form.difficulty_level = opt"
                                        class="flex-1 py-2 text-xs font-semibold rounded-md border transition-colors"
                                        :style="form.difficulty_level === opt
                                            ? 'background:var(--color-primary); color:white; border-color:var(--color-primary)'
                                            : 'background:var(--color-surface); color:var(--color-text-secondary); border-color:var(--color-border)'">
                                    <span x-text="opt"></span>
                                </button>
                            </template>
                        </div>
                        <p x-show="stepErrors.difficulty" class="text-[11px] mt-0.5" style="color:var(--color-error)">Pilih tingkat kesulitan.</p>
                    </div>

                    {{-- Tanda tangan digital --}}
                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">
                            Tanda Tangan Teknisi <span style="color:var(--color-error)">*</span>
                        </label>
                        <div class="border border-border rounded-md overflow-hidden bg-white">
                            <canvas id="survey-signature-canvas"
                                    class="w-full touch-none"
                                    style="height:140px; cursor:crosshair"></canvas>
                        </div>
                        <div class="flex items-center justify-between mt-1">
                            <p class="text-[11px] text-text-muted">Tanda tangan di kotak di atas menggunakan jari/stylus.</p>
                            <button type="button" @click="clearSignature()" class="text-[11px] font-medium" style="color:var(--color-primary)">Ulangi</button>
                        </div>
                        <p x-show="stepErrors.signature" class="text-[11px] mt-0.5" style="color:var(--color-error)">Tanda tangan wajib diisi.</p>
                    </div>
                </div>
            </div>

            {{-- Error umum --}}
            <div x-show="submitError" class="p-3 rounded-md text-sm" style="background:var(--color-error-bg); border:1px solid var(--color-error-border); color:var(--color-error)">
                <p x-text="submitError"></p>
            </div>

        </div>

        {{-- Footer --}}
        <div class="px-5 py-4 border-t border-border flex items-center justify-between gap-3 shrink-0">
            <button type="button" @click="prevStep()"
                    x-show="currentStep > 0"
                    class="text-xs font-semibold px-4 py-2 border border-border rounded-md bg-background text-text-secondary hover:bg-surface-muted transition-colors">
                Sebelumnya
            </button>
            <div class="flex-1"></div>
            <button type="button" @click="nextStep()"
                    x-show="currentStep < steps.length - 1"
                    class="text-xs font-semibold px-5 py-2 rounded-md text-white transition-colors"
                    style="background:var(--color-primary)">
                Berikutnya
            </button>
            <button type="button" @click="submitReport()"
                    x-show="currentStep === steps.length - 1"
                    :disabled="submitting"
                    class="text-xs font-semibold px-5 py-2 rounded-md text-white transition-colors"
                    :style="submitting ? 'background:var(--color-surface-muted); color:var(--color-text-disabled); cursor:not-allowed' : 'background:var(--color-success)'">
                <span x-show="!submitting">Submit Laporan</span>
                <span x-show="submitting">Menyimpan...</span>
            </button>
        </div>
    </div>
</div>

{{-- ══ Slide-Over: Laporan Pemasangan Multi-Step ════════════════════════ --}}
<div x-data="installReportWizard({{ json_encode($installTasksData) }})"
     @open-install-report.window="openInstallReport($event.detail.taskId)"
     x-show="open"
     x-cloak
     class="fixed inset-0 z-50 flex"
     style="display:none">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/50" @click="closeSlideOver()"></div>

    {{-- Drawer --}}
    <div class="relative ml-auto w-full max-w-lg h-full bg-surface shadow-2xl flex flex-col overflow-hidden"
         @click.stop>

        {{-- Header --}}
        <div class="flex items-center justify-between px-5 py-4 border-b border-border shrink-0">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-text-muted">Laporan Pemasangan</p>
                <p class="text-sm font-semibold text-text-main mt-0.5" x-text="activeTask?.customer_name"></p>
            </div>
            <button @click="closeSlideOver()" class="p-1.5 rounded-md hover:bg-surface-muted transition-colors">
                <svg class="h-4 w-4 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        {{-- Step Pills --}}
        <div class="flex items-center gap-1 px-5 py-3 border-b border-border shrink-0 overflow-x-auto">
            <template x-for="(label, idx) in steps" :key="idx">
                <div class="flex items-center gap-1 shrink-0">
                    <span class="h-5 w-5 rounded-full flex items-center justify-center text-[10px] font-bold transition-colors"
                          :style="idx === currentStep ? 'background:var(--color-primary); color:white'
                                  : (idx < currentStep ? 'background:var(--color-success); color:white'
                                  : 'background:var(--color-surface-muted); color:var(--color-text-muted)')">
                        <template x-if="idx < currentStep">✓</template>
                        <template x-if="idx >= currentStep"><span x-text="idx+1"></span></template>
                    </span>
                    <span class="text-[11px] font-medium whitespace-nowrap"
                          :style="idx === currentStep ? 'color:var(--color-primary)' : 'color:var(--color-text-muted)'"
                          x-text="label">
                    </span>
                    <template x-if="idx < steps.length - 1">
                        <svg class="h-3 w-3 text-border mx-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </template>
                </div>
            </template>
        </div>

        {{-- Body --}}
        <div class="flex-1 overflow-y-auto p-5 space-y-4">

            {{-- Step 1: Foto Pemasangan --}}
            <div x-show="currentStep === 0">
                <h3 class="text-sm font-semibold text-text-main mb-1">Foto Hasil Pemasangan</h3>
                <p class="text-xs text-text-muted mb-3">Upload minimal 1 foto ONT terpasang dan 1 foto kabel routing.</p>

                {{-- Preview foto --}}
                <div class="grid grid-cols-3 gap-2 mb-3" x-show="uploadedPhotos.length > 0">
                    <template x-for="(photo, idx) in uploadedPhotos" :key="idx">
                        <div class="relative aspect-square rounded-md overflow-hidden border border-border bg-surface-muted">
                            <img :src="photo.url" class="h-full w-full object-cover" alt="Bukti">
                            <span class="absolute bottom-0 left-0 right-0 text-[9px] text-white px-1 py-0.5 truncate"
                                  style="background:rgba(0,0,0,0.6)" x-text="photo.caption"></span>
                        </div>
                    </template>
                </div>

                {{-- Upload buttons --}}
                <div class="space-y-2">
                    <label class="block cursor-pointer">
                        <input type="file" accept="image/*" capture="environment" class="hidden"
                               @change="uploadPhoto($event.target, 'Foto ONT Terpasang')">
                        <div class="flex items-center justify-center gap-2 py-3 border-2 border-dashed rounded-md text-xs font-medium text-primary hover:bg-surface-muted transition-colors">
                            📷 Foto ONT / ONU Terpasang (Wajib)
                        </div>
                    </label>

                    <label class="block cursor-pointer">
                        <input type="file" accept="image/*" capture="environment" class="hidden"
                               @change="uploadPhoto($event.target, 'Foto Kabel Routing')">
                        <div class="flex items-center justify-center gap-2 py-3 border-2 border-dashed rounded-md text-xs font-medium text-primary hover:bg-surface-muted transition-colors">
                            📷 Foto Routing Kabel (Wajib)
                        </div>
                    </label>

                    <label class="block cursor-pointer">
                        <input type="file" accept="image/*" capture="environment" class="hidden"
                               @change="uploadPhoto($event.target, 'Foto Titik Sambungan')">
                        <div class="flex items-center justify-center gap-2 py-3 border-2 border-dashed rounded-md text-xs font-medium text-text-secondary hover:bg-surface-muted transition-colors">
                            📷 Foto Titik Sambungan / Joint Box (Opsional)
                        </div>
                    </label>
                </div>

                <div x-show="uploading" class="flex items-center gap-2 text-xs text-text-muted py-3">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Mengupload...
                </div>
                <p x-show="uploadError" x-text="uploadError" class="text-xs mt-1 text-rose-500"></p>
                <p class="text-xs mt-2" :style="uploadedPhotos.length >= 2 ? 'color:var(--color-success)' : 'color:var(--color-text-muted)'">
                    <span x-text="uploadedPhotos.length"></span> foto diupload (minimal 2)
                </p>
            </div>

            {{-- Step 2: Data Teknis --}}
            <div x-show="currentStep === 1">
                <h3 class="text-sm font-semibold text-text-main mb-3">Data Teknis & Paket</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">Paket Internet Pelanggan</label>
                        <select x-model="form.internet_package_id"
                                class="w-full text-sm px-3 py-2 border border-border rounded-md bg-background text-text-main focus:outline-none focus:ring-2"
                                style="--tw-ring-color: var(--color-primary)"
                                :style="stepErrors.package ? 'border-color:var(--color-error)' : ''">
                            <option value="">-- Pilih Paket Internet --</option>
                            @foreach($packages as $pkg)
                                <option value="{{ $pkg->id }}">{{ $pkg->name }} ({{ $pkg->bandwidth_label }})</option>
                            @endforeach
                        </select>
                        <p x-show="stepErrors.package" class="text-[11px] mt-0.5 text-rose-500">Wajib diisi.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">ONT / ONU Serial Number</label>
                        <input type="text" x-model="form.router_or_ont_serial" placeholder="Contoh: ZNTEH8GXXXXX"
                               class="w-full text-sm px-3 py-2 border border-border rounded-md bg-background text-text-main focus:outline-none focus:ring-2"
                               :style="stepErrors.serial ? 'border-color:var(--color-error)' : ''">
                        <p x-show="stepErrors.serial" class="text-[11px] mt-0.5 text-rose-500">Wajib diisi.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">MAC Address Router / ONT</label>
                        <input type="text" x-model="form.router_mac" placeholder="Contoh: AA:BB:CC:DD:EE:FF"
                               class="w-full text-sm px-3 py-2 border border-border rounded-md bg-background text-text-main focus:outline-none focus:ring-2"
                               :style="stepErrors.mac ? 'border-color:var(--color-error)' : ''">
                        <p x-show="stepErrors.mac" class="text-[11px] mt-0.5 text-rose-500">Wajib diisi.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">VLAN ID</label>
                        <input type="number" x-model="form.vlan" placeholder="Contoh: 100"
                               class="w-full text-sm px-3 py-2 border border-border rounded-md bg-background text-text-main focus:outline-none focus:ring-2"
                               :style="stepErrors.vlan ? 'border-color:var(--color-error)' : ''">
                        <p x-show="stepErrors.vlan" class="text-[11px] mt-0.5 text-rose-500">Wajib diisi.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">IP Address</label>
                        <input type="text" x-model="form.ip_address" placeholder="Contoh: 10.10.20.5"
                               class="w-full text-sm px-3 py-2 border border-border rounded-md bg-background text-text-main focus:outline-none focus:ring-2"
                               :style="stepErrors.ip ? 'border-color:var(--color-error)' : ''">
                        <p x-show="stepErrors.ip" class="text-[11px] mt-0.5 text-rose-500">Wajib diisi.</p>
                    </div>
                </div>
            </div>

            {{-- Step 3: Kontrak & TTD --}}
            <div x-show="currentStep === 2">
                <h3 class="text-sm font-semibold text-text-main mb-3">Kontrak & Tanda Tangan</h3>
                <div class="space-y-4">
                    {{-- Upload file kontrak --}}
                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">Foto / Scan Kontrak Fisik <span class="text-rose-500">*</span></label>
                        <input type="file" id="install-contract-file" accept="image/*" capture="environment"
                               class="w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-primary-soft file:text-primary hover:file:bg-primary-soft/80"
                               :style="stepErrors.contract ? 'border-color:var(--color-error)' : ''">
                        <p x-show="stepErrors.contract" class="text-[11px] mt-0.5 text-rose-500">Wajib mengunggah file kontrak.</p>
                    </div>

                    {{-- TTD Pelanggan --}}
                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">Tanda Tangan Pelanggan <span class="text-rose-500">*</span></label>
                        <div class="border border-border rounded-md overflow-hidden bg-white">
                            <canvas id="install-customer-signature-canvas" class="w-full touch-none" style="height:120px; cursor:crosshair"></canvas>
                        </div>
                        <div class="flex items-center justify-between mt-1">
                            <p class="text-[11px] text-text-muted">Gunakan jari / stylus untuk tanda tangan.</p>
                            <button type="button" @click="clearCanvas('customer')" class="text-[11px] font-medium text-primary">Ulangi</button>
                        </div>
                        <p x-show="stepErrors.sig_customer" class="text-[11px] mt-0.5 text-rose-500">Tanda tangan pelanggan wajib diisi.</p>
                    </div>

                    {{-- TTD Teknisi --}}
                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">Tanda Tangan Teknisi <span class="text-rose-500">*</span></label>
                        <div class="border border-border rounded-md overflow-hidden bg-white">
                            <canvas id="install-tech-signature-canvas" class="w-full touch-none" style="height:120px; cursor:crosshair"></canvas>
                        </div>
                        <div class="flex items-center justify-between mt-1">
                            <p class="text-[11px] text-text-muted">Tanda tangan Anda sebagai bukti penyelesaian pekerjaan.</p>
                            <button type="button" @click="clearCanvas('tech')" class="text-[11px] font-medium text-primary">Ulangi</button>
                        </div>
                        <p x-show="stepErrors.sig_tech" class="text-[11px] mt-0.5 text-rose-500">Tanda tangan teknisi wajib diisi.</p>
                    </div>
                </div>
            </div>

            {{-- Step 4: Aktivasi --}}
            <div x-show="currentStep === 3">
                <h3 class="text-sm font-semibold text-text-main mb-2">Konfirmasi Aktivasi</h3>
                <p class="text-xs text-text-muted mb-4">Layanan pemasangan selesai secara fisik. Konfirmasi data di bawah untuk mengirim ke Verifikasi Admin.</p>

                <div class="bg-surface-muted rounded-md p-4 space-y-2 text-xs">
                    <div class="flex justify-between">
                        <span class="text-text-muted">Pelanggan:</span>
                        <span class="font-medium text-text-main" x-text="activeTask?.customer_name"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-text-muted">Alamat:</span>
                        <span class="text-text-secondary text-right" x-text="activeTask?.customer_address"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-text-muted">Paket Internet:</span>
                        <span class="font-medium text-text-main" x-text="document.querySelector(`select option[value='${form.internet_package_id}']`)?.text || '—'"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-text-muted">Serial Number ONT:</span>
                        <span class="font-mono text-text-secondary" x-text="form.router_or_ont_serial"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-text-muted">MAC Address:</span>
                        <span class="font-mono text-text-secondary" x-text="form.router_mac"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-text-muted">VLAN ID:</span>
                        <span class="font-mono text-text-secondary" x-text="form.vlan"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-text-muted">IP Address:</span>
                        <span class="font-mono text-text-secondary" x-text="form.ip_address"></span>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="block text-xs font-medium text-text-secondary mb-1">Catatan Pemasangan (Opsional)</label>
                    <textarea x-model="form.installation_note" rows="3"
                              placeholder="Catatan tambahan hasil pemasangan..."
                              class="w-full text-sm px-3 py-2 border border-border rounded-md bg-background text-text-main focus:outline-none resize-none"></textarea>
                </div>
            </div>

            {{-- Error submit --}}
            <div x-show="submitError" class="p-3 rounded-md text-sm text-rose-500 bg-rose-50 border border-rose-100">
                <p x-text="submitError"></p>
            </div>

        </div>

        {{-- Footer --}}
        <div class="px-5 py-4 border-t border-border flex items-center justify-between gap-3 shrink-0">
            <button type="button" @click="prevStep()"
                    x-show="currentStep > 0"
                    class="text-xs font-semibold px-4 py-2 border border-border rounded-md bg-background text-text-secondary hover:bg-surface-muted transition-colors">
                Sebelumnya
            </button>
            <div class="flex-1"></div>
            <button type="button" @click="nextStep()"
                    x-show="currentStep < steps.length - 1"
                    class="text-xs font-semibold px-5 py-2 rounded-md text-white transition-colors"
                    style="background:var(--color-primary)">
                Berikutnya
            </button>
            <button type="button" @click="submitReport()"
                    x-show="currentStep === steps.length - 1"
                    :disabled="submitting"
                    class="text-xs font-semibold px-5 py-2 rounded-md text-white transition-colors"
                    :style="submitting ? 'background:var(--color-surface-muted); color:var(--color-text-disabled); cursor:not-allowed' : 'background:var(--color-success)'">
                <span x-show="!submitting">Aktifkan Pelanggan</span>
                <span x-show="submitting">Memproses...</span>
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
function surveyReportWizard(surveyTasks) {
    return {
        open: false,
        currentStep: 0,
        steps: ['Data Diri', 'Foto Lokasi', 'Cek Sinyal', 'Teknis Jaringan', 'Kesimpulan'],
        activeTask: null,
        uploadedPhotos: [],
        uploading: false,
        uploadError: null,
        submitting: false,
        submitError: null,
        stepErrors: {},
        signatureCanvas: null,
        signatureCtx: null,
        isDrawing: false,
        lastX: 0,
        lastY: 0,
        signatureIsEmpty: true,

        form: {
            signal_strength_dbm: '',
            signal_note: '',
            cable_estimation_meter: '',
            nearest_odp: '',
            media_type: 'Fiber',
            survey_result: '',
            reject_reason: '',
            difficulty_level: 'SEDANG',
            signature_data: '',
        },

        surveyResultOptions: [
            { value: 'layak',           label: 'Layak Dipasang',        desc: 'Lokasi memenuhi syarat teknis pemasangan' },
            { value: 'tidak_layak',     label: 'Tidak Layak',           desc: 'Lokasi tidak memenuhi syarat, berikan alasan' },
            { value: 'kunjungan_ulang', label: 'Perlu Kunjungan Ulang', desc: 'Perlu survei tambahan di waktu lain' },
        ],

        openSurveyReport(taskId) {
            this.activeTask = surveyTasks.find(t => t.id === taskId) || null;
            if (!this.activeTask) return;
            // Reset state
            this.currentStep = 0;
            this.uploadedPhotos = [];
            this.uploading = false;
            this.uploadError = null;
            this.submitting = false;
            this.submitError = null;
            this.stepErrors = {};
            this.signatureIsEmpty = true;
            this.form = {
                signal_strength_dbm: '',
                signal_note: '',
                cable_estimation_meter: '',
                nearest_odp: '',
                media_type: 'Fiber',
                survey_result: '',
                reject_reason: '',
                difficulty_level: 'SEDANG',
                signature_data: '',
            };
            this.open = true;
            document.body.style.overflow = 'hidden';
            // Init canvas setelah render
            this.$nextTick(() => this.initCanvas());
        },

        closeSlideOver() {
            this.open = false;
            document.body.style.overflow = '';
        },

        // ── Canvas TTD ────────────────────────────────────────────────
        initCanvas() {
            const el = document.getElementById('survey-signature-canvas');
            if (!el) return;
            this.signatureCanvas = el;
            this.signatureCtx = el.getContext('2d');
            el.width  = el.offsetWidth;
            el.height = 140;
            this.signatureCtx.lineWidth   = 2.5;
            this.signatureCtx.strokeStyle = '#1e293b';
            this.signatureCtx.lineCap     = 'round';
            this.signatureCtx.lineJoin    = 'round';
            // Mouse
            el.addEventListener('mousedown',  e => this.startDraw(e));
            el.addEventListener('mousemove',  e => this.draw(e));
            el.addEventListener('mouseup',    () => this.stopDraw());
            el.addEventListener('mouseleave', () => this.stopDraw());
            // Touch
            el.addEventListener('touchstart', e => { e.preventDefault(); this.startDraw(e.touches[0]); }, { passive: false });
            el.addEventListener('touchmove',  e => { e.preventDefault(); this.draw(e.touches[0]); },      { passive: false });
            el.addEventListener('touchend',   () => this.stopDraw());
        },

        getPos(e) {
            const r = this.signatureCanvas.getBoundingClientRect();
            return { x: (e.clientX ?? e.pageX) - r.left, y: (e.clientY ?? e.pageY) - r.top };
        },

        startDraw(e) {
            this.isDrawing = true;
            const p = this.getPos(e);
            this.lastX = p.x; this.lastY = p.y;
            this.signatureIsEmpty = false;
        },

        draw(e) {
            if (!this.isDrawing) return;
            const p = this.getPos(e);
            this.signatureCtx.beginPath();
            this.signatureCtx.moveTo(this.lastX, this.lastY);
            this.signatureCtx.lineTo(p.x, p.y);
            this.signatureCtx.stroke();
            this.lastX = p.x; this.lastY = p.y;
        },

        stopDraw() { this.isDrawing = false; },

        clearSignature() {
            if (!this.signatureCtx) return;
            this.signatureCtx.clearRect(0, 0, this.signatureCanvas.width, this.signatureCanvas.height);
            this.signatureIsEmpty = true;
            this.form.signature_data = '';
        },

        // ── Navigasi Step ─────────────────────────────────────────────
        goToStep(idx) {
            // Hanya boleh mundur atau ke step yang sudah dikunjungi
            if (idx < this.currentStep) this.currentStep = idx;
        },

        prevStep() {
            if (this.currentStep > 0) this.currentStep--;
        },

        nextStep() {
            if (!this.validateCurrentStep()) return;
            if (this.currentStep < this.steps.length - 1) {
                this.currentStep++;
                if (this.currentStep === 4) {
                    this.$nextTick(() => this.initCanvas());
                }
            }
        },

        validateCurrentStep() {
            this.stepErrors = {};
            if (this.currentStep === 1) {
                // Step 2: Foto wajib min 1
                if (this.uploadedPhotos.length < 1) {
                    this.stepErrors.photo = true;
                    this.uploadError = 'Upload minimal 1 foto lokasi sebelum lanjut.';
                    return false;
                }
            }
            if (this.currentStep === 3) {
                // Step 4: validasi teknis
                if (!this.form.cable_estimation_meter && this.form.cable_estimation_meter !== 0) this.stepErrors.cable = true;
                if (!this.form.nearest_odp) this.stepErrors.odp = true;
                if (!this.form.media_type)  this.stepErrors.media = true;
                if (Object.keys(this.stepErrors).length > 0) return false;
            }
            this.uploadError = null;
            return true;
        },

        // ── Upload Foto ───────────────────────────────────────────────
        async uploadPhoto(input, caption) {
            const file = input.files[0];
            if (!file || !this.activeTask) return;
            this.uploading = true;
            this.uploadError = null;
            const form = new FormData();
            form.append('photo', file);
            form.append('caption', caption);
            form.append('_token', document.querySelector('meta[name="csrf-token"]').content);
            try {
                const res  = await fetch(this.activeTask.evidence_url, {
                    method: 'POST', body: form, headers: { 'Accept': 'application/json' },
                });
                const data = await res.json();
                if (data.success) {
                    this.uploadedPhotos.push({ url: data.evidence.url, caption: data.evidence.caption || caption });
                } else {
                    this.uploadError = 'Gagal upload. Coba lagi.';
                }
            } catch (err) {
                this.uploadError = 'Koneksi bermasalah. Coba lagi.';
            } finally {
                this.uploading = false;
                input.value = '';
            }
        },

        // ── Submit Laporan ────────────────────────────────────────────
        async submitReport() {
            // Validasi step terakhir
            this.stepErrors = {};
            this.submitError = null;

            if (!this.form.survey_result) { this.stepErrors.result = true; }
            if (!this.form.difficulty_level) { this.stepErrors.difficulty = true; }
            if (this.signatureIsEmpty) { this.stepErrors.signature = true; }
            if (this.form.survey_result === 'tidak_layak' && !this.form.reject_reason) {
                this.submitError = 'Alasan tidak layak wajib diisi.';
                return;
            }
            if (Object.keys(this.stepErrors).length > 0) return;

            // Ambil signature data URI dari canvas
            this.form.signature_data = this.signatureCanvas
                ? this.signatureCanvas.toDataURL('image/png')
                : '';

            if (!this.form.signature_data || this.signatureIsEmpty) {
                this.stepErrors.signature = true;
                return;
            }

            this.submitting = true;
            try {
                const body = new FormData();
                body.append('_token', document.querySelector('meta[name="csrf-token"]').content);
                Object.entries(this.form).forEach(([k, v]) => { if (v !== '') body.append(k, v); });

                const res  = await fetch(this.activeTask.submit_url, {
                    method: 'POST', body, headers: { 'Accept': 'application/json' },
                });
                const data = await res.json();

                if (data.success) {
                    this.closeSlideOver();
                    window.location.reload();
                } else {
                    this.submitError = data.message || 'Terjadi kesalahan. Coba lagi.';
                }
            } catch (err) {
                this.submitError = 'Koneksi bermasalah. Pastikan internet aktif dan coba lagi.';
            } finally {
                this.submitting = false;
            }
        },
    };
}

function installReportWizard(installTasks) {
    return {
        open: false,
        currentStep: 0,
        steps: ['Foto Pemasangan', 'Data Teknis', 'Kontrak & TTD', 'Aktivasi'],
        activeTask: null,
        uploadedPhotos: [],
        uploading: false,
        uploadError: null,
        submitting: false,
        submitError: null,
        stepErrors: {},

        customerCanvas: null,
        customerCtx: null,
        customerIsEmpty: true,

        techCanvas: null,
        techCtx: null,
        techIsEmpty: true,

        form: {
            internet_package_id: '',
            router_or_ont_serial: '',
            router_mac: '',
            vlan: '',
            ip_address: '',
            signature_customer: '',
            signature_technician: '',
            installation_note: '',
        },

        openInstallReport(taskId) {
            this.activeTask = installTasks.find(t => t.id === taskId) || null;
            if (!this.activeTask) return;
            this.currentStep = 0;
            this.uploadedPhotos = [];
            this.uploading = false;
            this.uploadError = null;
            this.submitting = false;
            this.submitError = null;
            this.stepErrors = {};
            this.customerIsEmpty = true;
            this.techIsEmpty = true;
            this.form = {
                internet_package_id: this.activeTask.current_package_id || '',
                router_or_ont_serial: '',
                router_mac: '',
                vlan: '',
                ip_address: '',
                signature_customer: '',
                signature_technician: '',
                installation_note: '',
            };
            this.open = true;
            document.body.style.overflow = 'hidden';
            this.$nextTick(() => {
                this.initCanvases();
            });
        },

        closeSlideOver() {
            this.open = false;
            document.body.style.overflow = '';
        },

        initCanvases() {
            this.initCanvas('install-customer-signature-canvas', 'customer');
            this.initCanvas('install-tech-signature-canvas', 'tech');
        },

        initCanvas(id, type) {
            const el = document.getElementById(id);
            if (!el) return;
            const ctx = el.getContext('2d');
            el.width = el.offsetWidth;
            el.height = 120;
            ctx.lineWidth = 2.5;
            ctx.strokeStyle = '#1e293b';
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';

            let drawing = false;
            let lastX = 0, lastY = 0;

            const getPos = (e) => {
                const r = el.getBoundingClientRect();
                return { x: (e.clientX ?? e.pageX) - r.left, y: (e.clientY ?? e.pageY) - r.top };
            };

            el.addEventListener('mousedown', (e) => {
                drawing = true;
                const p = getPos(e);
                lastX = p.x; lastY = p.y;
                if (type === 'customer') this.customerIsEmpty = false;
                if (type === 'tech') this.techIsEmpty = false;
            });

            el.addEventListener('mousemove', (e) => {
                if (!drawing) return;
                const p = getPos(e);
                ctx.beginPath();
                ctx.moveTo(lastX, lastY);
                ctx.lineTo(p.x, p.y);
                ctx.stroke();
                lastX = p.x; lastY = p.y;
            });

            const stop = () => { drawing = false; };
            el.addEventListener('mouseup', stop);
            el.addEventListener('mouseleave', stop);

            el.addEventListener('touchstart', (e) => {
                e.preventDefault();
                drawing = true;
                const p = getPos(e.touches[0]);
                lastX = p.x; lastY = p.y;
                if (type === 'customer') this.customerIsEmpty = false;
                if (type === 'tech') this.techIsEmpty = false;
            }, { passive: false });

            el.addEventListener('touchmove', (e) => {
                e.preventDefault();
                if (!drawing) return;
                const p = getPos(e.touches[0]);
                ctx.beginPath();
                ctx.moveTo(lastX, lastY);
                ctx.lineTo(p.x, p.y);
                ctx.stroke();
                lastX = p.x; lastY = p.y;
            }, { passive: false });

            el.addEventListener('touchend', stop);

            if (type === 'customer') {
                this.customerCanvas = el;
                this.customerCtx = ctx;
            } else {
                this.techCanvas = el;
                this.techCtx = ctx;
            }
        },

        clearCanvas(type) {
            if (type === 'customer') {
                this.customerCtx.clearRect(0, 0, this.customerCanvas.width, this.customerCanvas.height);
                this.customerIsEmpty = true;
            } else {
                this.techCtx.clearRect(0, 0, this.techCanvas.width, this.techCanvas.height);
                this.techIsEmpty = true;
            }
        },

        validateCurrentStep() {
            this.stepErrors = {};
            this.uploadError = null;
            if (this.currentStep === 0) {
                if (this.uploadedPhotos.length < 2) {
                    this.stepErrors.photo = true;
                    this.uploadError = 'Upload minimal 2 foto bukti (ONT terpasang & kabel routing).';
                    return false;
                }
            }
            if (this.currentStep === 1) {
                if (!this.form.internet_package_id) this.stepErrors.package = true;
                if (!this.form.router_or_ont_serial) this.stepErrors.serial = true;
                if (!this.form.router_mac) this.stepErrors.mac = true;
                if (!this.form.vlan) this.stepErrors.vlan = true;
                if (!this.form.ip_address) this.stepErrors.ip = true;

                if (Object.keys(this.stepErrors).length > 0) return false;
            }
            if (this.currentStep === 2) {
                const fileInput = document.getElementById('install-contract-file');
                if (!fileInput || !fileInput.files[0]) {
                    this.stepErrors.contract = true;
                }
                if (this.customerIsEmpty) this.stepErrors.sig_customer = true;
                if (this.techIsEmpty) this.stepErrors.sig_tech = true;

                if (Object.keys(this.stepErrors).length > 0) return false;
            }
            return true;
        },

        prevStep() {
            if (this.currentStep > 0) this.currentStep--;
        },

        nextStep() {
            if (!this.validateCurrentStep()) return;
            if (this.currentStep < this.steps.length - 1) {
                this.currentStep++;
                if (this.currentStep === 2) {
                    this.$nextTick(() => this.initCanvases());
                }
            }
        },

        async uploadPhoto(input, caption) {
            const file = input.files[0];
            if (!file || !this.activeTask) return;
            this.uploading = true;
            this.uploadError = null;
            const form = new FormData();
            form.append('photo', file);
            form.append('caption', caption);
            form.append('_token', document.querySelector('meta[name="csrf-token"]').content);
            try {
                const res = await fetch(this.activeTask.evidence_url, {
                    method: 'POST', body: form, headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    this.uploadedPhotos.push({ url: data.evidence.url, caption: data.evidence.caption || caption });
                } else {
                    this.uploadError = 'Gagal upload foto bukti. Coba lagi.';
                }
            } catch (err) {
                this.uploadError = 'Koneksi bermasalah saat mengunggah foto.';
            } finally {
                this.uploading = false;
                input.value = '';
            }
        },

        async submitReport() {
            this.submitError = null;
            if (!this.validateCurrentStep()) return;

            this.form.signature_customer = this.customerCanvas.toDataURL('image/png');
            this.form.signature_technician = this.techCanvas.toDataURL('image/png');

            this.submitting = true;
            try {
                const body = new FormData();
                body.append('_token', document.querySelector('meta[name="csrf-token"]').content);
                Object.entries(this.form).forEach(([k, v]) => {
                    if (v !== '' && v !== null) {
                        body.append(k, v);
                    }
                });

                const fileInput = document.getElementById('install-contract-file');
                if (fileInput && fileInput.files[0]) {
                    body.append('contract_file', fileInput.files[0]);
                }

                const res = await fetch(this.activeTask.submit_url, {
                    method: 'POST', body, headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    this.closeSlideOver();
                    window.location.reload();
                } else {
                    this.submitError = data.message || 'Gagal menyimpan laporan pemasangan.';
                }
            } catch (err) {
                this.submitError = 'Koneksi bermasalah. Coba lagi.';
            } finally {
                this.submitting = false;
            }
        }
    };
}
</script>

<script>
/**
 * technicianNotifier — Alpine.js component (S8.2-T010)
 * ─────────────────────────────────────────────────────
 * Listen ke Laravel Reverb channel private-teknisi.{userId} untuk event TaskScheduled.
 * Saat event diterima:
 *   1. Tampilkan banner notifikasi di atas list task.
 *   2. Fetch HTML card parsial dari /tasks-saya/partial/{taskId} lalu inject ke DOM.
 *   3. Klik banner → smooth-scroll ke card yang baru diinject.
 *   4. Auto-dismiss banner setelah 10 detik.
 */
function technicianNotifier() {
    return {
        banner: {
            visible: false,
            title: '',
            subtitle: '',
            taskId: null,
        },
        dismissTimer: null,

        init() {
            const userId = {{ auth()->id() }};

            // Retry loop: tunggu window.Echo tersedia (race condition antara
            // Alpine mount dan Vite bundle selesai load echo.js).
            // Retry maksimal 10x dengan interval 300ms (total ~3 detik).
            let attempts = 0;
            const maxAttempts = 10;

            const attach = () => {
                if (typeof window.Echo !== 'undefined') {
                    window.Echo.private(`teknisi.${userId}`)
                        .listen('TaskScheduled', (event) => {
                            this.handleTaskScheduled(event);
                        });
                    return;
                }

                attempts++;
                if (attempts < maxAttempts) {
                    setTimeout(attach, 300);
                } else {
                    console.warn('[technicianNotifier] window.Echo tidak tersedia setelah 3 detik. Notifikasi real-time tidak aktif.');
                }
            };

            attach();
        },

        handleTaskScheduled(event) {
            // Hitung jadwal yang tampil di banner
            let jadwalLabel = '';
            if (event.scheduled_at) {
                const dt = new Date(event.scheduled_at);
                // Format: YYYY-MM-DD HH:mm (sederhana, tanpa dependency locale)
                const pad = (n) => String(n).padStart(2, '0');
                jadwalLabel = `${dt.getFullYear()}-${pad(dt.getMonth()+1)}-${pad(dt.getDate())} ${pad(dt.getHours())}:${pad(dt.getMinutes())}`;
            }

            // Teks banner berbeda berdasarkan konteks event
            const isRescheduled = event.event_type === 'rescheduled';
            this.banner.title    = isRescheduled
                ? `Jadwal diperbarui: ${event.title}`
                : `Task baru ditugaskan: ${event.title}`;
            this.banner.subtitle = jadwalLabel ? `Jadwal: ${jadwalLabel}` : '';
            this.banner.taskId   = event.id;
            this.banner.visible  = true;

            // Auto-dismiss setelah 10 detik
            clearTimeout(this.dismissTimer);
            this.dismissTimer = setTimeout(() => this.dismissBanner(), 10000);

            // Inject card task baru ke DOM — hanya untuk event 'created'
            // Untuk reschedule, card sudah ada di DOM (perlu reload untuk update jadwal)
            if (!isRescheduled) {
                this.injectTaskCard(event.id);
            }
        },

        dismissBanner() {
            this.banner.visible = false;
            clearTimeout(this.dismissTimer);
        },

        scrollToCard() {
            if (!this.banner.taskId) return;
            const card = document.getElementById(`task-card-${this.banner.taskId}`);
            if (card) {
                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                // Highlight card sebentar
                card.style.transition = 'box-shadow 0.3s';
                card.style.boxShadow = '0 0 0 3px var(--color-primary, #2563eb)';
                setTimeout(() => { card.style.boxShadow = ''; }, 2000);
            }
            this.dismissBanner();
        },

        async injectTaskCard(taskId) {
            // Cek apakah card sudah ada (mencegah duplikasi jika event diterima dua kali)
            if (document.getElementById(`task-card-${taskId}`)) return;

            try {
                const res = await fetch(`/tasks-saya/partial/${taskId}`, {
                    headers: {
                        'Accept': 'text/html',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                if (!res.ok) {
                    console.warn(`[technicianNotifier] Gagal fetch card task #${taskId}: HTTP ${res.status}`);
                    return;
                }

                const html = await res.text();

                // Dapatkan container task list hari ini
                let container = document.getElementById('today-task-list');

                if (!container) {
                    // Jika container belum ada (halaman kosong / tidak ada task hari ini),
                    // cari parent wrapper dan buat container baru
                    const emptyState = document.querySelector('[data-empty-tasks]');
                    if (emptyState) {
                        // Sembunyikan empty state
                        emptyState.style.display = 'none';
                    }
                    // Buat container baru dan inject sebelum section Mendatang atau di akhir content
                    container = document.createElement('div');
                    container.id = 'today-task-list';
                    container.className = 'space-y-3';
                    // Cari titik sisip di atas section Mendatang
                    const mendatangSection = document.querySelector('[data-section="mendatang"]');
                    const contentWrapper = document.querySelector('.max-w-2xl');
                    if (mendatangSection && contentWrapper) {
                        contentWrapper.insertBefore(container, mendatangSection);
                    } else if (contentWrapper) {
                        contentWrapper.appendChild(container);
                    }
                }

                // Inject card di awal list (task baru tampil di atas)
                const wrapper = document.createElement('div');
                wrapper.innerHTML = html.trim();
                const newCard = wrapper.firstElementChild;
                if (newCard) {
                    container.insertBefore(newCard, container.firstChild);
                }

            } catch (err) {
                console.error('[technicianNotifier] Error saat inject task card:', err);
            }
        },
    };
}
</script>
@endpush
@endsection

