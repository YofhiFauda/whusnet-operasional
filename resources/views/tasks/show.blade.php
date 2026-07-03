@extends('layouts.app')

@section('title', $task->task_number . ' — Task Detail')

@section('content')
<div class="max-w-[1180px] mx-auto px-4 sm:px-6 py-6 space-y-6">

    {{-- ══ Breadcrumb ═══════════════════════════════════════════════ --}}
    <nav class="flex items-center gap-1.5 text-xs text-text-muted">
        @can('viewAll', \App\Models\Task::class)
        <a href="{{ route('tasks.index') }}" class="hover:text-primary transition-colors">Task</a>
        @else
        <a href="{{ route('tasks.own') }}" class="hover:text-primary transition-colors">Task Saya</a>
        @endcan
        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
        </svg>
        <span class="font-mono">{{ $task->task_number }}</span>
    </nav>

    {{-- ══ Page Header ══════════════════════════════════════════════ --}}
    <div class="flex items-start justify-between gap-4">
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap mb-1.5">
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
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full border" style="{{ $statusStyle }}">
                    {{ $task->status->label() }}
                </span>
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full border {{ $task->task_type->cardClasses() }}">
                    {{ $task->task_type->label() }}
                </span>
                <span class="font-mono text-xs text-text-muted">{{ $task->task_number }}</span>
                @if($task->isOverSla())
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full border"
                      style="background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border)">
                    Melewati SLA
                </span>
                @endif
            </div>
            <h1 class="text-xl sm:text-2xl font-bold text-text-main">{{ $task->title }}</h1>
        </div>
        @php
            $lat = $task->customer?->customerAddress?->latitude ?? $task->pop?->latitude;
            $lng = $task->customer?->customerAddress?->longitude ?? $task->pop?->longitude;
        @endphp
        <div class="flex items-center gap-2 shrink-0">
            @if($lat && $lng)
            <a href="https://www.google.com/maps/search/?api=1&query={{ $lat }},{{ $lng }}" target="_blank"
               class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 border border-border rounded-md bg-surface hover:bg-surface-muted text-primary transition-colors shadow-sm">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                Lokasi Maps
            </a>
            @endif
            @can('edit', $task)
            <a href="{{ route('tasks.edit', $task) }}"
               class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 border border-border rounded-md bg-surface hover:bg-surface-muted text-text-secondary transition-colors">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Edit
            </a>
            @endcan
            @can('cancel', $task)
            <button x-data @click="$dispatch('open-modal', 'cancel-task')"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 border rounded-md transition-colors"
                    style="border-color:var(--color-error-border); color:var(--color-error); background:var(--color-error-bg)">
                Batalkan
            </button>
            @endcan
        </div>
    </div>

    {{-- Flash messages ditangani otomatis oleh global Component Toast (<x-toast />) --}}

    {{-- ══ Metric Strip ═════════════════════════════════════════════ --}}
    <div class="bg-surface border border-border rounded-lg overflow-hidden shadow-sm">
        <div class="grid grid-cols-2 sm:grid-cols-5 divide-y sm:divide-y-0 sm:divide-x divide-border">
            {{-- Tipe --}}
            <div class="p-4 flex flex-col justify-between">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted mb-1">Tipe Task</p>
                <p class="text-sm font-semibold text-text-main">{{ $task->task_type->label() }}</p>
            </div>
            {{-- Jadwal --}}
            <div class="p-4 flex flex-col justify-between">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted mb-1">Jadwal</p>
                <div>
                    <p class="text-sm font-semibold font-mono text-text-main">{{ $task->scheduled_at?->format('H:i') ?? '—' }}</p>
                    <p class="text-[11px] text-text-muted">{{ $task->scheduled_at?->translatedFormat('d M Y') ?? 'Belum dijadwalkan' }}</p>
                </div>
            </div>
            {{-- Durasi vs SLA --}}
            <div class="p-4 flex flex-col justify-between">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted mb-1">Target SLA</p>
                <div>
                    <p class="text-sm font-semibold font-mono {{ $task->isOverSla() ? '' : 'text-text-main' }}"
                       style="{{ $task->isOverSla() ? 'color:var(--color-error)' : '' }}">
                        {{ $task->sla_minutes }} Menit
                    </p>
                    @if($task->actualDurationMinutes() !== null)
                    <p class="text-[11px] text-text-muted font-mono">Aktual: {{ $task->actualDurationMinutes() }} Mnt</p>
                    @endif
                </div>
            </div>
            {{-- POP --}}
            <div class="p-4 flex flex-col justify-between">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted mb-1">POP / Cabang</p>
                <p class="text-sm font-semibold text-text-main truncate">{{ $task->pop?->name ?? '—' }}</p>
            </div>
            {{-- Foto Bukti --}}
            <div class="p-4 flex flex-col justify-between col-span-2 sm:col-span-1">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted mb-1">Foto Bukti</p>
                <div>
                    <p class="text-sm font-semibold font-mono text-text-main">{{ $task->evidences->count() }} Foto</p>
                    <p class="text-[11px] text-text-muted">Terupload di sistem</p>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ Waktu Pengerjaan — tampil setelah task selesai ═══════════════ --}}
    @if($task->status->value === 'selesai' && $task->started_at && $task->completed_at)
    @php
        $showStartedAt   = $task->started_at;
        $showCompletedAt = $task->completed_at;
        $showActualMin   = (int) $showStartedAt->diffInMinutes($showCompletedAt);
        $showHours       = intdiv($showActualMin, 60);
        $showRemMins     = $showActualMin % 60;
        $showDuration    = $showHours > 0
            ? "{$showHours} jam {$showRemMins} menit"
            : "{$showActualMin} menit";
        $showOverSla     = $showActualMin > $task->sla_minutes;
        $showTypeLabel   = $task->task_type->value === \App\Enums\TaskType::PEMASANGAN->value ? 'Pemasangan' : 'Survey';
    @endphp
    <div class="bg-surface border border-border rounded-lg overflow-hidden shadow-sm">
        <div class="px-5 py-3.5 border-b border-border bg-surface-muted/50 flex items-center justify-between">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-text-muted">
                Waktu {{ $showTypeLabel }}
            </p>
            <span class="text-xs font-semibold px-2.5 py-1 rounded-full border font-mono"
                  style="{{ $showOverSla
                      ? 'background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border)'
                      : 'background:var(--color-success-bg); color:var(--color-success); border-color:var(--color-success-border)' }}">
                {{ $showOverSla ? 'Over SLA' : 'Dalam SLA' }}
            </span>
        </div>
        <div class="px-5 py-4">
            <div class="flex flex-wrap items-center gap-4 text-sm">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted mb-1">Mulai</p>
                    <p class="font-mono font-semibold text-text-main">{{ $showStartedAt->format('H:i') }}</p>
                    <p class="text-[11px] text-text-muted">{{ $showStartedAt->translatedFormat('d M Y') }}</p>
                </div>
                <svg class="h-4 w-4 text-text-muted mt-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                </svg>
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted mb-1">Selesai</p>
                    <p class="font-mono font-semibold text-text-main">{{ $showCompletedAt->format('H:i') }}</p>
                    <p class="text-[11px] text-text-muted">{{ $showCompletedAt->translatedFormat('d M Y') }}</p>
                </div>
                <div class="ml-auto text-right">
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted mb-1">Durasi Aktual</p>
                    <p class="font-mono font-semibold text-lg"
                       style="color:{{ $showOverSla ? 'var(--color-error)' : 'var(--color-success)' }}">
                        {{ $showDuration }}
                    </p>
                    <p class="text-[11px] text-text-muted">SLA: {{ $task->sla_minutes }} menit</p>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ══ Info Utama ════════════════════════════════════════════════ --}}
    <div class="bg-surface border border-border rounded-lg overflow-hidden shadow-sm">
        <div class="px-5 py-3.5 border-b border-border bg-surface-muted/50">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-text-muted">Informasi Task</p>
        </div>
        <div class="px-5 py-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-3 gap-x-8 text-sm">
                <div class="flex gap-3 border-b border-border pb-3">
                    <span class="text-text-muted w-32 shrink-0 text-xs pt-0.5">FOP / Koordinator</span>
                    <span class="text-text-main font-medium">{{ $task->fop?->name ?? '—' }}</span>
                </div>
                <div class="flex gap-3 border-b border-border pb-3">
                    <span class="text-text-muted w-32 shrink-0 text-xs pt-0.5">Pelanggan & Kontak</span>
                    <div class="text-text-main font-medium">
                        @if($task->customer)
                        <div>
                            <a href="{{ route('customers.show', $task->customer) }}"
                               class="hover:underline font-semibold" style="color:var(--color-primary)">
                                {{ $task->customer->full_name }}
                            </a>
                            <span class="font-mono text-xs text-text-muted ml-1">{{ $task->customer->display_id }}</span>
                        </div>
                        @if($task->customer->phone)
                        <div class="flex items-center gap-2 mt-1 text-xs">
                            <span class="text-text-muted">{{ $task->customer->phone }}</span>
                            <a href="https://wa.me/{{ preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $task->customer->phone)) }}" target="_blank"
                               class="text-emerald-600 hover:underline inline-flex items-center gap-1 font-semibold">
                                <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.768-.001 1.298.409 2.522 1.189 3.518l-.756 2.766 2.831-.744a5.748 5.748 0 002.504.588h.002c3.18 0 5.767-2.586 5.768-5.766 0-1.541-.6-2.99-1.691-4.08-1.091-1.09-2.539-1.69-4.079-1.648zm0 10.153a4.398 4.398 0 01-2.241-.614l-.16-.095-1.666.438.444-1.624-.105-.167a4.394 4.394 0 01-.67-2.326c.001-2.426 1.975-4.4 4.402-4.4 1.177 0 2.283.458 3.115 1.29a4.382 4.382 0 011.29 3.117c-.001 2.426-1.975 4.4-4.409 4.4z"/></svg>
                                WhatsApp
                            </a>
                        </div>
                        @endif
                        @else
                        <span class="text-text-muted">—</span>
                        @endif
                    </div>
                </div>
                @if($task->description)
                <div class="flex gap-3 sm:col-span-2 border-b border-border pb-3">
                    <span class="text-text-muted w-32 shrink-0 text-xs pt-0.5 font-bold text-amber-700">ISSUE / KELUHAN</span>
                    <span class="text-text-main font-semibold leading-relaxed bg-amber-50/60 border border-amber-200/60 rounded px-2.5 py-1.5 flex-1">{{ $task->description }}</span>
                </div>
                @endif
                @if($task->customer || $task->pop)
                <div class="flex gap-3 sm:col-span-2 border-b border-border pb-3">
                    <span class="text-text-muted w-32 shrink-0 text-xs pt-0.5">Alamat & Lokasi</span>
                    <div class="text-text-secondary leading-relaxed flex-1">
                        <div>
                            @if($task->customer)
                                {{ implode(', ', array_filter([
                                    $task->customer->address,
                                    $task->customer->village?->name,
                                    $task->customer->district?->name,
                                    $task->customer->city?->name,
                                ])) ?: '—' }}
                            @else
                                {{ $task->pop?->address ?? '—' }} ({{ $task->pop?->name }})
                            @endif
                        </div>
                        @if($lat && $lng)
                        <div class="flex items-center gap-3 mt-1.5 pt-1.5 border-t border-border/50 text-xs flex-wrap">
                            <span class="font-mono text-text-main">Latitude: <strong class="text-primary">{{ $lat }}</strong> | Longitude: <strong class="text-primary">{{ $lng }}</strong></span>
                            <a href="https://www.google.com/maps/search/?api=1&query={{ $lat }},{{ $lng }}" target="_blank"
                               class="inline-flex items-center gap-1 font-bold text-primary hover:underline">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                Buka di Google Maps →
                            </a>
                        </div>
                        @endif
                    </div>
                </div>
                @endif
                @if($task->pending_reason)
                <div class="flex gap-3 sm:col-span-2">
                    <span class="text-text-muted w-32 shrink-0 text-xs pt-0.5">Alasan Pending</span>
                    <span class="font-medium" style="color:var(--color-warning)">{{ $task->pending_reason }}</span>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ══ Briefing Teknis Khusus per Kategori Task (Seragam Sesuai Design System) ══ --}}
    @if($task->task_type === \App\Enums\TaskType::SURVEY)
    <div class="bg-surface border border-border rounded-lg overflow-hidden shadow-sm">
        <div class="px-5 py-3.5 border-b border-border bg-surface-muted/50 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="h-2 w-2 rounded-full bg-primary"></span>
                <p class="text-[11px] font-semibold uppercase tracking-widest text-text-muted">Briefing Detail Teknis — Survey Pelanggan</p>
            </div>
            <span class="text-xs font-mono font-semibold text-text-secondary bg-background px-2.5 py-1 rounded border border-border">SLA Survey: {{ $task->sla_minutes }} Menit</span>
        </div>
        <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div class="border border-border rounded-md p-4 bg-background">
                <p class="text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Status SLA Berjalan</p>
                @if($task->started_at && !$task->completed_at)
                    @php
                        $elapsed = (int) $task->started_at->diffInMinutes(now());
                        $remaining = $task->sla_minutes - $elapsed;
                    @endphp
                    <div class="flex items-center gap-2">
                        <span class="text-base font-bold font-mono {{ $remaining < 0 ? 'text-error' : 'text-primary' }}">
                            {{ $elapsed }} menit berjalan
                        </span>
                        <span class="text-xs text-text-muted font-mono">({{ $remaining >= 0 ? "Sisa {$remaining} mnt" : "Over SLA " . abs($remaining) . " mnt" }})</span>
                    </div>
                @elseif($task->status->value === 'terjadwal')
                    <p class="text-sm font-medium text-warning">Menunggu teknisi klik Mulai Survey (Target SLA: {{ $task->sla_minutes }} menit)</p>
                @elseif($task->completed_at)
                    <p class="text-sm font-medium text-success">Selesai dikerjakan dalam {{ $task->actualDurationMinutes() }} menit</p>
                @else
                    <p class="text-sm text-text-muted">{{ $task->status->label() }}</p>
                @endif
            </div>

            <div class="border border-border rounded-md p-4 bg-background">
                <p class="text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Rencana Paket Layanan</p>
                <p class="text-sm font-bold text-text-main">
                    {{ $task->customer?->customerService?->internetPackage?->name ?? $task->customer?->customerService?->package_name_snapshot ?? 'Belum dipilih saat pendaftaran' }}
                </p>
            </div>

            <div class="border border-border rounded-md p-4 bg-background md:col-span-2 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Koordinat Lokasi Survey</p>
                    @if($lat && $lng)
                    <p class="text-xs font-mono font-semibold text-text-main">Latitude: <span class="text-primary">{{ $lat }}</span> | Longitude: <span class="text-primary">{{ $lng }}</span></p>
                    @else
                    <p class="text-xs text-text-muted italic">Koordinat belum tercatat pada alamat pelanggan.</p>
                    @endif
                </div>
                @if($lat && $lng)
                <a href="https://www.google.com/maps/search/?api=1&query={{ $lat }},{{ $lng }}" target="_blank"
                   class="inline-flex items-center gap-1.5 text-xs font-bold px-3.5 py-2 rounded-md bg-primary text-white hover:bg-primary-hover transition shrink-0 shadow-sm">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Buka Rute Maps
                </a>
                @endif
            </div>
        </div>
    </div>
    @endif

    @if($task->task_type === \App\Enums\TaskType::PEMASANGAN)
    @php
        $survey = $task->customer?->latestSurvey;
        $service = $task->customer?->customerService;
        $device = $task->customer?->customerDevice;
    @endphp
    <div class="bg-surface border border-border rounded-lg overflow-hidden shadow-sm">
        <div class="px-5 py-3.5 border-b border-border bg-surface-muted/50 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="h-2 w-2 rounded-full bg-primary"></span>
                <p class="text-[11px] font-semibold uppercase tracking-widest text-text-muted">Briefing Detail Teknis — Pemasangan Baru (PSB)</p>
            </div>
            <span class="text-xs font-mono font-semibold text-text-secondary bg-background px-2.5 py-1 rounded border border-border">SLA PSB: {{ $task->sla_minutes }} Menit</span>
        </div>
        <div class="p-5 space-y-4 text-sm">
            {{-- Ringkasan Hasil Survey Sebelumnya --}}
            <div class="border border-border rounded-md p-4 bg-background">
                <h5 class="text-xs font-bold uppercase tracking-wider text-text-muted mb-3 flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    Ringkasan Hasil Survey Sebelumnya
                </h5>
                @if($survey)
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                    <div class="bg-surface p-3 rounded border border-border">
                        <span class="block text-[10px] text-text-muted font-bold uppercase">ODP Tujuan & Port</span>
                        <span class="font-bold font-mono text-text-main text-sm mt-0.5 block">{{ $survey->nearest_odp ?: '-' }}</span>
                    </div>
                    <div class="bg-surface p-3 rounded border border-border">
                        <span class="block text-[10px] text-text-muted font-bold uppercase">Estimasi Kabel Dropcore</span>
                        <span class="font-bold font-mono text-text-main text-sm mt-0.5 block">{{ $survey->cable_estimation_meter ? $survey->cable_estimation_meter . ' Meter' : '-' }}</span>
                    </div>
                    <div class="bg-surface p-3 rounded border border-border">
                        <span class="block text-[10px] text-text-muted font-bold uppercase">Kebutuhan Alat Khusus</span>
                        <span class="font-semibold text-text-main text-xs mt-0.5 block">{{ $survey->required_tools ?: 'Standar' }}</span>
                    </div>
                </div>
                @if($survey->survey_note)
                <p class="text-xs text-text-secondary mt-3 pt-2.5 border-t border-border italic">"{{ $survey->survey_note }}"</p>
                @endif
                @else
                <p class="text-xs text-warning bg-surface p-3 rounded border border-border">Data hasil survey sebelumnya belum tercatat di sistem untuk pelanggan ini.</p>
                @endif
            </div>

            {{-- Spesifikasi Perangkat & Paket --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="border border-border rounded-md p-4 bg-background">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Paket Internet yang Diaktifkan</p>
                    <p class="text-sm font-bold text-primary">{{ $service?->internetPackage?->name ?? $service?->package_name_snapshot ?? '-' }}</p>
                    @if($service?->monthly_price)
                    <p class="text-xs text-text-muted font-mono mt-1">Rp {{ number_format($service->monthly_price, 0, ',', '.') }} / bulan</p>
                    @endif
                </div>
                <div class="border border-border rounded-md p-4 bg-background">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Alokasi Perangkat / ONT</p>
                    @if($device)
                        <p class="text-sm font-bold text-text-main">{{ $device->brand }} {{ $device->model }}</p>
                        <p class="text-xs font-mono text-text-muted mt-1">SN: {{ $device->serial_number ?: 'Belum diinput' }}</p>
                    @else
                        <p class="text-xs text-warning font-medium">Perangkat ONT akan dicatat oleh teknisi pada saat laporan pemasangan selesai.</p>
                    @endif
                </div>
            </div>

            {{-- Komposisi Tim Teknisi --}}
            <div class="border border-border rounded-md p-4 bg-background">
                <p class="text-[10px] font-bold uppercase tracking-wider text-text-muted mb-2.5">Komposisi Tim Teknisi Ditugaskan ({{ $task->teamMembers->count() }} Orang)</p>
                @if($task->teamMembers->isNotEmpty())
                <div class="flex flex-wrap gap-2">
                    @foreach($task->teamMembers as $tm)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded bg-surface border border-border text-xs font-semibold text-text-main">
                        <span class="h-1.5 w-1.5 rounded-full bg-success"></span>
                        {{ $tm->user?->name ?? 'Teknisi' }} ({{ $tm->role_in_task }})
                    </span>
                    @endforeach
                </div>
                @else
                <p class="text-xs text-text-muted italic">Belum ada tim teknisi dialokasikan.</p>
                @endif
            </div>
        </div>
    </div>
    @endif

    @if($task->task_type === \App\Enums\TaskType::MAINTENANCE)
    @php
        $tech = $task->customer?->customerTechnicalDetail;
        $device = $task->customer?->customerDevice;
    @endphp
    <div class="bg-surface border border-border rounded-lg overflow-hidden shadow-sm">
        <div class="px-5 py-3.5 border-b border-border bg-surface-muted/50 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="h-2 w-2 rounded-full bg-primary"></span>
                <p class="text-[11px] font-semibold uppercase tracking-widest text-text-muted">Briefing Detail Teknis — Maintenance / Gangguan</p>
            </div>
            <span class="text-xs font-mono font-semibold text-text-secondary bg-background px-2.5 py-1 rounded border border-border">SLA Maintenance: {{ $task->sla_minutes }} Menit</span>
        </div>
        <div class="p-5 space-y-4 text-sm">
            {{-- Data Teknis Eksisting --}}
            <div class="border border-border rounded-md p-4 bg-background">
                <h5 class="text-xs font-bold uppercase tracking-wider text-text-muted mb-3 flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    Data Teknis Eksisting Pelanggan
                </h5>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 text-xs">
                    <div class="bg-surface p-3 rounded border border-border">
                        <span class="block text-[10px] text-text-muted font-bold uppercase">ODP & Port Terhubung</span>
                        <span class="font-bold font-mono text-text-main text-sm mt-0.5 block">
                            {{ $device?->odp ?? $tech?->odp_number ?: '-' }} 
                            @if($device?->odp_port || $tech?->odp_port)
                                (Port {{ $device?->odp_port ?? $tech?->odp_port }})
                            @endif
                        </span>
                    </div>
                    <div class="bg-surface p-3 rounded border border-border">
                        <span class="block text-[10px] text-text-muted font-bold uppercase">OLT & Port OLT</span>
                        <span class="font-bold font-mono text-text-main text-sm mt-0.5 block">
                            {{ $tech?->olt_number ?: '-' }}
                            @if($tech?->olt_port) Port {{ $tech->olt_port }} @endif
                            @if($tech?->olt_slot) Slot {{ $tech->olt_slot }} @endif
                        </span>
                    </div>
                    <div class="bg-surface p-3 rounded border border-border">
                        <span class="block text-[10px] text-text-muted font-bold uppercase">Redaman Normal / Awal</span>
                        <span class="font-bold font-mono text-text-main text-sm mt-0.5 block">
                            {{ $device?->signal_rx_power ?? $tech?->initial_attenuation ? ($device?->signal_rx_power ?? $tech?->initial_attenuation) . ' dBm' : '-' }}
                        </span>
                    </div>
                    <div class="bg-surface p-3 rounded border border-border sm:col-span-2">
                        <span class="block text-[10px] text-text-muted font-bold uppercase">Perangkat Terpasang (ONT/Modem)</span>
                        <span class="font-bold text-text-main text-sm mt-0.5 block">
                            {{ $device?->brand ?? 'Modem' }} {{ $device?->model }}
                        </span>
                        <span class="text-[11px] font-mono text-text-muted mt-1 block">
                            SN: <strong class="text-text-main">{{ $device?->serial_number ?? $tech?->router_or_ont_serial ?: '-' }}</strong> | 
                            MAC: {{ $device?->mac_address ?? $tech?->router_mac ?: '-' }}
                        </span>
                    </div>
                    <div class="bg-surface p-3 rounded border border-border">
                        <span class="block text-[10px] text-text-muted font-bold uppercase">PPPoE User / IP Address</span>
                        <span class="font-bold font-mono text-text-main text-xs mt-0.5 block">{{ $device?->pppoe_username ?: '-' }}</span>
                        <span class="text-[11px] font-mono text-text-muted mt-0.5 block">IP: {{ $device?->ip_address ?? $tech?->ip_address ?: '-' }}</span>
                    </div>
                </div>
            </div>

            {{-- Riwayat Gangguan Terakhir --}}
            <div class="border border-border rounded-md p-4 bg-background">
                <h5 class="text-xs font-bold uppercase tracking-wider text-text-muted mb-3">Riwayat Gangguan / Maintenance Terakhir</h5>
                @if(isset($recentMaintenanceTasks) && $recentMaintenanceTasks->isNotEmpty())
                <div class="space-y-2">
                    @foreach($recentMaintenanceTasks as $rm)
                    <div class="flex items-center justify-between p-2.5 rounded bg-surface text-xs border border-border">
                        <div>
                            <a href="{{ route('tasks.show', $rm) }}" class="font-bold text-primary hover:underline font-mono">{{ $rm->task_number }}</a>
                            <span class="text-text-main ml-1.5 font-medium">{{ $rm->title }}</span>
                            <span class="text-text-muted ml-1 font-mono">({{ $rm->scheduled_at?->format('d/m/Y') }})</span>
                        </div>
                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-background border border-border text-text-secondary">
                            {{ $rm->status->label() }}
                        </span>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-xs text-text-muted italic">Pelanggan ini belum memiliki riwayat tiket maintenance/gangguan sebelumnya.</p>
                @endif
            </div>
        </div>
    </div>
    @endif

    @if($task->task_type === \App\Enums\TaskType::RELOKASI)
    @php
        $device = $task->customer?->customerDevice;
        $isEksternal = stripos($task->title . ' ' . $task->description, 'pindah rumah') !== false || stripos($task->title . ' ' . $task->description, 'eksternal') !== false || stripos($task->title . ' ' . $task->description, 'alamat') !== false;
    @endphp
    <div class="bg-surface border border-border rounded-lg overflow-hidden shadow-sm">
        <div class="px-5 py-3.5 border-b border-border bg-surface-muted/50 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="h-2 w-2 rounded-full bg-primary"></span>
                <p class="text-[11px] font-semibold uppercase tracking-widest text-text-muted">Briefing Detail Teknis — Relokasi / Pemindahan</p>
            </div>
            <span class="text-xs font-mono font-semibold text-text-secondary bg-background px-2.5 py-1 rounded border border-border">SLA Relokasi: {{ $task->sla_minutes }} Menit</span>
        </div>
        <div class="p-5 space-y-4 text-sm">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="border border-border rounded-md p-4 bg-background">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Tipe Relokasi</p>
                    <p class="text-sm font-bold text-text-main">{{ $isEksternal ? 'Relokasi Eksternal (Pindah Alamat / Rumah)' : 'Relokasi Internal (Geser Ruangan / Lantai)' }}</p>
                </div>
                <div class="border border-border rounded-md p-4 bg-background">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Status ODP & Kebutuhan Kabel</p>
                    <p class="text-xs text-text-main font-medium leading-relaxed">
                        {{ $isEksternal ? 'Wajib survei ulang ODP terdekat di alamat baru dan instalasi kabel dropcore baru.' : 'Melakukan penggeseran modem ONT / perpanjangan kabel dropcore atau kabel LAN indoor.' }}
                    </p>
                </div>
            </div>

            <div class="border border-border rounded-md p-4 bg-background space-y-3">
                <h5 class="text-xs font-bold uppercase tracking-wider text-text-muted">Alamat Asal vs Alamat Tujuan</h5>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <div class="p-3 bg-surface rounded border border-border">
                        <span class="block text-[10px] font-bold text-text-muted uppercase mb-1">Alamat Asal (Eksisting)</span>
                        <p class="text-text-main font-medium leading-relaxed">{{ $task->customer?->address ?: '-' }}</p>
                    </div>
                    <div class="p-3 bg-surface rounded border border-border">
                        <span class="block text-[10px] font-bold text-primary uppercase mb-1">Alamat Tujuan (Baru / Detail Relokasi)</span>
                        <p class="text-text-main font-bold leading-relaxed">{{ $task->description ?: 'Lihat instruksi pada judul task' }}</p>
                    </div>
                </div>
            </div>

            <div class="border border-border rounded-md p-4 bg-background">
                <p class="text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Perangkat Eksisting yang Akan Dipindahkan</p>
                @if($device)
                <p class="text-sm font-bold text-text-main">{{ $device->brand }} {{ $device->model }}</p>
                <p class="text-xs font-mono text-text-muted mt-1">SN: <strong class="text-text-main">{{ $device->serial_number ?: '-' }}</strong> | MAC: {{ $device->mac_address ?: '-' }}</p>
                @else
                <p class="text-xs text-text-muted">Data modem ONT belum tercatat di detail perangkat pelanggan.</p>
                @endif
            </div>
        </div>
    </div>
    @endif

    @if($task->task_type === \App\Enums\TaskType::AMBIL_MODEM)
    @php
        $device = $task->customer?->customerDevice;
    @endphp
    <div class="bg-surface border border-border rounded-lg overflow-hidden shadow-sm">
        <div class="px-5 py-3.5 border-b border-border bg-surface-muted/50 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="h-2 w-2 rounded-full bg-primary"></span>
                <p class="text-[11px] font-semibold uppercase tracking-widest text-text-muted">Briefing Detail Teknis — Pengambilan Modem / Deaktivasi</p>
            </div>
            <span class="text-xs font-mono font-semibold text-text-secondary bg-background px-2.5 py-1 rounded border border-border">SLA Penarikan: {{ $task->sla_minutes }} Menit</span>
        </div>
        <div class="p-5 space-y-4 text-sm">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="border border-border rounded-md p-4 bg-background">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Alasan Pengambilan / Deaktivasi</p>
                    <p class="text-sm font-bold text-text-main">{{ $task->description ?: 'Deaktivasi / Berakhir Langganan' }}</p>
                </div>
                <div class="border border-border rounded-md p-4 bg-background">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Instruksi & Janji Temu Penjemputan</p>
                    <p class="text-sm font-bold text-text-main font-mono">{{ $task->scheduled_at?->translatedFormat('l, d M Y — H:i') ?: 'Segera / Sesuai kesepakatan' }} WIB</p>
                </div>
            </div>

            {{-- Daftar Aset ISP yang Wajib Ditarik --}}
            <div class="border border-border rounded-md p-4 bg-background">
                <h5 class="text-xs font-bold uppercase tracking-wider text-text-main mb-1.5 flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    Daftar Aset ISP yang Wajib Ditarik & Diverifikasi
                </h5>
                <p class="text-[11px] text-text-muted mb-3.5">Teknisi wajib mencocokkan Serial Number (SN) di fisik perangkat dengan data di bawah sebelum membawa modem pulang.</p>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <div class="bg-surface p-3.5 rounded border border-border">
                        <span class="block text-[10px] text-text-muted font-bold uppercase">Perangkat Utama (ONT / Modem)</span>
                        <span class="font-bold text-text-main text-sm mt-0.5 block">{{ $device?->brand ?: 'Modem ONT' }} {{ $device?->model }}</span>
                        <div class="mt-2 pt-2 border-t border-border font-mono text-xs space-y-0.5">
                            <span class="block text-primary font-bold">SN: {{ $device?->serial_number ?: 'PERIKSA FISIK' }}</span>
                            <span class="block text-text-muted text-[11px]">MAC: {{ $device?->mac_address ?: '-' }}</span>
                        </div>
                    </div>
                    <div class="bg-surface p-3.5 rounded border border-border flex flex-col justify-between">
                        <div>
                            <span class="block text-[10px] text-text-muted font-bold uppercase mb-2">Kelengkapan Pendamping Wajib Dibawa</span>
                            <ul class="list-disc list-inside space-y-1.5 text-text-secondary">
                                <li><strong class="text-text-main">Adaptor Power</strong> original (Sesuai voltase)</li>
                                <li><strong class="text-text-main">Kabel Patchcord / LAN</strong> (Jika tersedia)</li>
                                <li><strong class="text-text-main">Router / STB Tambahan</strong> (Jika aset sewa ISP)</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    @if($task->task_type === \App\Enums\TaskType::CREQ)
    @php
        $device = $task->customer?->customerDevice;
        $tech = $task->customer?->customerTechnicalDetail;
    @endphp
    <div class="bg-surface border border-border rounded-lg overflow-hidden shadow-sm">
        <div class="px-5 py-3.5 border-b border-border bg-surface-muted/50 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="h-2 w-2 rounded-full bg-primary"></span>
                <p class="text-[11px] font-semibold uppercase tracking-widest text-text-muted">Briefing Detail Teknis — Customer Request (C-REQ)</p>
            </div>
            <span class="text-xs font-mono font-semibold text-text-secondary bg-background px-2.5 py-1 rounded border border-border">SLA C-REQ: {{ $task->sla_minutes }} Menit</span>
        </div>
        <div class="p-5 space-y-4 text-sm">
            <div class="border border-border rounded-md p-4 bg-background">
                <p class="text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Rincian Request Pelanggan</p>
                <p class="text-sm font-bold text-text-main">{{ $task->description ?: $task->title }}</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <div class="border border-border rounded-md p-4 bg-background">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">SSID WiFi Eksisting</p>
                    <p class="text-sm font-bold font-mono text-text-main">{{ $device?->wifi_ssid ?? $tech?->ssid ?: 'Standard / Default' }}</p>
                </div>
                <div class="border border-border rounded-md p-4 bg-background">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">IP Gateway / Akses Router</p>
                    <p class="text-sm font-bold font-mono text-text-main">{{ $device?->ip_address ?? $tech?->ip_address ?: '192.168.1.1' }}</p>
                </div>
            </div>
        </div>
    </div>
    @endif

    @if(in_array($task->task_type, [\App\Enums\TaskType::OREQ, \App\Enums\TaskType::INFR]))
    <div class="bg-surface border border-border rounded-lg overflow-hidden shadow-sm">
        <div class="px-5 py-3.5 border-b border-border bg-surface-muted/50 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="h-2 w-2 rounded-full bg-primary"></span>
                <p class="text-[11px] font-semibold uppercase tracking-widest text-text-muted">Briefing Detail Teknis — Internal / Office Request</p>
            </div>
            <span class="text-xs font-mono font-semibold text-text-secondary bg-background px-2.5 py-1 rounded border border-border">SLA: {{ $task->sla_minutes }} Menit</span>
        </div>
        <div class="p-5 space-y-4 text-sm">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="border border-border rounded-md p-4 bg-background">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">POP / Cabang Pembina</p>
                    <p class="text-sm font-bold text-text-main">{{ $task->pop?->name ?? 'Pusat' }}</p>
                </div>
                <div class="border border-border rounded-md p-4 bg-background flex flex-col justify-between">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Lokasi / Target Fasilitas</p>
                        <p class="text-xs font-semibold text-text-main">{{ $task->pop?->address ?: 'Area Infrastruktur POP' }}</p>
                    </div>
                    @if($lat && $lng)
                    <div class="mt-2 pt-2 border-t border-border flex items-center justify-between text-xs">
                        <span class="font-mono text-text-muted">{{ $lat }}, {{ $lng }}</span>
                        <a href="https://www.google.com/maps/search/?api=1&query={{ $lat }},{{ $lng }}" target="_blank" class="text-primary hover:underline font-bold">Buka Maps →</a>
                    </div>
                    @endif
                </div>
            </div>

            <div class="border border-border rounded-md p-4 bg-background">
                <p class="text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Detail Instruksi Pekerjaan Internal</p>
                <p class="text-sm font-semibold text-text-main leading-relaxed">{{ $task->description ?: $task->title }}</p>
            </div>

            <div class="border border-border rounded-md p-4 bg-background">
                <p class="text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Persiapan Kebutuhan Material / Sparepart</p>
                <p class="text-xs text-text-secondary leading-relaxed">Siapkan peralatan fisik kerja lapangan, kabel patchcord/splice, atau sparepart pengganti sesuai kebutuhan tugas di atas dari gudang POP sebelum berangkat.</p>
            </div>
        </div>
    </div>
    @endif

    {{-- ══ Tim Teknisi ══════════════════════════════════════════════ --}}
    <div class="bg-surface border border-border rounded-lg overflow-hidden shadow-sm">
        <div class="px-5 py-3.5 border-b border-border bg-surface-muted/50">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-text-muted">Tim Teknisi</p>
        </div>
        <div class="px-5 py-4">
            @if($task->teamMembers->count() > 0)
            <div class="flex flex-wrap gap-2">
                @foreach($task->teamMembers as $member)
                <div class="flex items-center gap-2.5 bg-background border border-border rounded-md px-3 py-2 w-full sm:w-auto">
                    <div class="h-7 w-7 rounded-full bg-primary-soft flex items-center justify-center text-xs font-bold shrink-0"
                         style="color:var(--color-primary)">
                        {{ strtoupper(substr($member->user?->name ?? '?', 0, 2)) }}
                    </div>
                    <div class="flex-1 min-w-0 pr-4">
                        <p class="text-xs font-semibold text-text-main truncate">{{ $member->user?->name ?? 'User dihapus' }}</p>
                        <p class="text-[10px] text-text-muted capitalize">{{ $member->role_in_task }}</p>
                    </div>
                    @can('task.assign.team')
                        @if(in_array($task->status->value, ['terjadwal', 'in_progress']))
                            <button type="button" 
                                x-data="" 
                                x-on:click="$dispatch('open-modal', 'swap-technician-{{ $member->user_id }}')" 
                                class="text-xs text-primary hover:underline whitespace-nowrap">
                                Ganti
                            </button>
                            
                            <x-ui.modal name="swap-technician-{{ $member->user_id }}" title="Ganti Teknisi" maxWidth="sm">
                                <form action="{{ route('tasks.team.update', $task) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="old_user_id" value="{{ $member->user_id }}">
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium mb-1">Pilih Teknisi Pengganti</label>
                                        <select name="new_user_id" class="w-full rounded-md border-border text-sm focus:border-primary focus:ring-primary" required>
                                            <option value="">-- Pilih Teknisi --</option>
                                            @foreach(\App\Models\User::whereHas('role', fn($q) => $q->where('code', 'teknisi'))->where('id', '!=', $member->user_id)->orderBy('name')->get() as $tek)
                                                <option value="{{ $tek->id }}">{{ $tek->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium mb-1">Jadwal Pelaksanaan (Opsional)</label>
                                        <input type="datetime-local" name="scheduled_at" 
                                               value="{{ $task->scheduled_at ? $task->scheduled_at->format('Y-m-d\TH:i') : '' }}" 
                                               class="w-full border border-border rounded-md px-3 py-2 text-sm text-text-main bg-background focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition">
                                        <p class="text-[11px] text-text-muted mt-1">Biarkan default atau kosongkan jika tidak ingin mengubah jadwal.</p>
                                    </div>
                                    <div class="flex justify-end gap-2">
                                        <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'swap-technician-{{ $member->user_id }}')">Batal</x-ui.button>
                                        <x-ui.button type="submit" variant="primary">Simpan</x-ui.button>
                                    </div>
                                </form>
                            </x-ui.modal>
                        @endif
                    @endcan
                </div>
                @endforeach
            </div>
            @else
            <p class="text-sm text-text-muted">Belum ada anggota tim.</p>
            @endif
        </div>

    </div>

    {{-- ══ Audit Log (History) ════════════════════════════════════════ --}}
    @if(auth()->user()->hasRole(['owner', 'admin', 'fop']))
    <div class="bg-surface border border-border rounded-lg overflow-hidden shadow-sm mt-4">
        <div class="px-5 py-3.5 border-b border-border bg-surface-muted/50">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-text-muted">Riwayat Status (Audit Log)</p>
        </div>
        <div class="px-5 py-4">
            @if($task->auditLogs && $task->auditLogs->count() > 0)
            <div class="relative border-l border-border ml-3 space-y-6">
                @foreach($task->auditLogs as $log)
                <div class="relative pl-5">
                    {{-- Timeline node --}}
                    <div class="absolute -left-1.5 top-1.5 h-3 w-3 rounded-full bg-border border-2 border-surface"></div>
                    <div class="mb-1 flex items-center justify-between">
                        <p class="text-xs font-semibold capitalize" style="color:var(--color-text-main)">
                            {{ str_replace('_', ' ', $log->action) }}
                        </p>
                        <span class="text-[10px] text-text-muted font-mono">{{ $log->created_at->format('d M Y, H:i') }}</span>
                    </div>
                    <p class="text-[11px] text-text-secondary">Oleh: <span class="font-medium text-text-main">{{ $log->user?->name ?? 'System' }}</span></p>
                    
                    @if($log->action === 'cancelled' && isset($log->new_values['cancel_reason']))
                    <div class="mt-1 p-2 bg-error-bg/20 border border-error-border rounded-md">
                        <p class="text-[10px] text-error font-medium">Alasan: {{ $log->new_values['cancel_reason'] }}</p>
                    </div>
                    @elseif($log->action === 'rejected' && isset($log->new_values['reject_reason']))
                    <div class="mt-1 p-2 bg-error-bg/20 border border-error-border rounded-md">
                        <p class="text-[10px] text-error font-medium">Alasan: {{ $log->new_values['reject_reason'] }}</p>
                    </div>
                    @elseif($log->action === 'completed' && isset($log->new_values['status']))
                    <div class="mt-1 text-[10px] text-success font-medium">Task ditandai selesai oleh teknisi.</div>
                    @endif
                </div>
                @endforeach
            </div>
            @else
            <p class="text-sm text-text-muted">Belum ada riwayat aktivitas.</p>
            @endif
        </div>
    </div>
    @endif




    {{-- ══ Bukti Foto ════════════════════════════════════════════════ --}}
    @if(!in_array($task->task_type->value, [\App\Enums\TaskType::SURVEY->value, \App\Enums\TaskType::PEMASANGAN->value]))
    <div class="bg-surface border border-border rounded-lg overflow-hidden shadow-sm"
         x-data="evidenceSection({{ $task->id }}, {{ $task->canComplete() ? 'true' : 'false' }}, {{ $task->evidences->count() }})">
        <div class="px-5 py-3.5 border-b border-border bg-surface-muted/50 flex items-center justify-between">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-text-muted">Foto Bukti</p>
            <span class="text-xs font-semibold font-mono text-text-secondary bg-background px-2.5 py-0.5 rounded border border-border" x-text="`${evidenceCount} foto`"></span>
        </div>
        <div class="p-4">
            {{-- Grid foto --}}
            @if($task->evidences->count() > 0)
            <div class="grid grid-cols-3 gap-2 mb-4">
                @foreach($task->evidences as $evidence)
                <div class="relative group rounded-md overflow-hidden aspect-square bg-surface-muted border border-border">
                    <img src="{{ asset('storage/' . $evidence->file_path) }}"
                         alt="{{ $evidence->caption ?? 'Bukti' }}"
                         class="h-full w-full object-cover">
                    @if($evidence->caption)
                    <div class="absolute bottom-0 left-0 right-0 px-2 py-1 text-[10px] text-white truncate"
                         style="background: rgba(15,23,42,0.7)">
                        {{ $evidence->caption }}
                    </div>
                    @endif
                    @can('edit', $task)
                    <button @click="deleteEvidence({{ $evidence->id }})"
                            class="absolute top-1 right-1 h-6 w-6 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity text-white"
                            style="background:var(--color-error)">
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                    @endcan
                </div>
                @endforeach
            </div>
            @else
            <p class="text-sm text-text-muted mb-4">Belum ada foto bukti.</p>
            @endif

            {{-- Upload --}}
            @can('uploadEvidence', $task)
            @if(in_array($task->status->value, ['in_progress', 'pending']))
            <label class="block cursor-pointer">
                <input type="file" accept="image/*" capture="environment" class="hidden" @change="uploadEvidence($event.target)" />
                <div class="flex items-center justify-center gap-2 text-xs font-medium py-3 border border-dashed rounded-md transition-colors"
                     style="border-color:var(--color-primary-border); color:var(--color-primary)"
                     onmouseover="this.style.background='var(--color-primary-soft)'"
                     onmouseout="this.style.background=''">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    Upload Foto Bukti
                </div>
            </label>
            <p x-show="uploadError" x-text="uploadError" class="text-xs mt-2" style="color:var(--color-error)"></p>
            @endif
            @endcan
        </div>
    </div>
    @endif

    {{-- ══ Action Buttons (Teknisi) ══════════════════════════════════ --}}
    @if(in_array($task->status->value, ['terjadwal', 'in_progress', 'pending']))
    <div class="flex items-center justify-end gap-3">
        @if($task->status->value === 'terjadwal')
            @if($task->scheduled_at && !$task->scheduled_at->startOfDay()->isFuture())
            @if($task->task_type->value === \App\Enums\TaskType::SURVEY->value)
                @if(auth()->user()->hasPermission('customers.detail.survey.update') && $task->teamMembers->pluck('user_id')->contains(auth()->id()))
                <form action="{{ route('customers.survey.start', $task->customer_id) }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-2 text-sm font-semibold px-5 py-2.5 rounded-md text-white transition-colors"
                            style="background:var(--color-warning)">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                        </svg>
                        Mulai Survey
                    </button>
                </form>
                @endif
            @elseif($task->task_type->value === \App\Enums\TaskType::PEMASANGAN->value)
                @if(auth()->user()->hasPermission('customers.detail.installation.update') && $task->teamMembers->pluck('user_id')->contains(auth()->id()))
                <form action="{{ route('customers.installation.start', $task->customer_id) }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-2 text-sm font-semibold px-5 py-2.5 rounded-md text-white transition-colors"
                            style="background:var(--color-warning)">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                        </svg>
                        Mulai Pemasangan
                    </button>
                </form>
                @endif
            @else
                @can('statusStart', $task)
                <form action="{{ route('tasks.start', $task) }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-2 text-sm font-semibold px-5 py-2.5 rounded-md text-white transition-colors"
                            style="background:var(--color-warning)">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                        </svg>
                        {{ $task->task_type->value === \App\Enums\TaskType::MAINTENANCE->value ? 'Mulai Maintenance' : 'Mulai Task' }}
                    </button>
                </form>
                @endcan
            @endif
            @else
            <span class="text-sm text-text-muted px-4 py-2.5 rounded-md border border-border">
                Dijadwalkan {{ $task->scheduled_at?->translatedFormat('l, d M Y') }}
            </span>
            @endif
        @endif

        @can('statusPending', $task)
        @if($task->status->value === 'in_progress')
        <button type="button" x-data @click="$dispatch('open-modal', 'pending-task')"
                class="inline-flex items-center gap-2 text-sm font-semibold px-4 py-2.5 rounded-md border transition-colors"
                style="border-color:var(--color-warning-border); color:var(--color-warning)">
            {{ in_array($task->task_type->value, [\App\Enums\TaskType::SURVEY->value, \App\Enums\TaskType::PEMASANGAN->value, \App\Enums\TaskType::MAINTENANCE->value]) ? 'Laporan Nanti' : 'Pending' }}
        </button>
        @endif
        @endcan

        @can('statusComplete', $task)
        @if(in_array($task->status->value, ['in_progress', 'pending']))
            @if($task->task_type->value === \App\Enums\TaskType::SURVEY->value)
                <a href="{{ route('customers.survey.report', $task->customer_id) }}"
                        class="inline-flex items-center gap-2 text-sm font-semibold px-5 py-2.5 rounded-md text-white transition-colors"
                        style="background:var(--color-success)">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Laporan Survey
                </a>
            @elseif($task->task_type->value === \App\Enums\TaskType::PEMASANGAN->value)
                <a href="{{ route('customers.installation.report', $task->customer_id) }}"
                        class="inline-flex items-center gap-2 text-sm font-semibold px-5 py-2.5 rounded-md text-white transition-colors"
                        style="background:var(--color-success)">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Laporan Pemasangan
                </a>
            @else
                <a href="{{ route('tasks.maintenance.report', $task) }}"
                        class="inline-flex items-center gap-2 text-sm font-semibold px-5 py-2.5 rounded-md text-white transition-colors"
                        style="background:var(--color-success)">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Isi Laporan
                </a>
            @endif
        @endif
        @endcan
    </div>
    @endif

    {{-- ══ Action Buttons (FOP Manage) ════════════════════════════════ --}}
    @if(in_array($task->status->value, ['pending', 'terjadwal']))
    @if(auth()->user()->can('fopReject', $task) || auth()->user()->can('fopPending', $task) || auth()->user()->can('schedule', $task))
    <div class="bg-surface border border-border rounded-lg p-5 mt-4 flex items-center justify-between shadow-sm">
        <div>
            <h4 class="text-sm font-semibold text-text-main mb-1">Manajemen Task (FOP)</h4>
            <p class="text-xs text-text-secondary">Kelola task sebelum mulai dikerjakan oleh teknisi.</p>
        </div>
        <div class="flex items-center gap-3">
            @if($task->status->value === 'pending')
                @can('schedule', $task)
                <button x-data @click="$dispatch('open-modal', 'schedule-task')"
                        class="inline-flex items-center gap-2 text-sm font-semibold px-4 py-2 rounded-md text-white transition-colors hover:brightness-110"
                        style="background:var(--color-primary)">
                    Jadwalkan Task
                </button>
                @endcan
                @can('fopReject', $task)
                <button x-data @click="$dispatch('open-modal', 'fop-reject-task-pending')"
                        class="inline-flex items-center gap-2 text-sm font-semibold px-4 py-2 rounded-md border bg-white transition-colors hover:bg-error/5"
                        style="border-color:var(--color-error-border); color:var(--color-error)">
                    Reject Task
                </button>
                @endcan
            @endif

            @if($task->status->value === 'terjadwal')
                @can('fopPending', $task)
                <button x-data @click="$dispatch('open-modal', 'fop-pending-task')"
                        class="inline-flex items-center gap-2 text-sm font-semibold px-4 py-2 rounded-md border bg-white transition-colors hover:bg-warning/5"
                        style="border-color:var(--color-warning-border); color:var(--color-warning)">
                    Set Pending
                </button>
                @endcan
            @endif
        </div>
    </div>
    @endif
    @endif

    {{-- ══ Action Buttons (FOP Review) ════════════════════════════════ --}}
    @if($task->status->value === 'selesai' && $task->fop_review_status === 'pending')
    @can('review', $task)
    <div class="bg-surface border border-border rounded-lg p-5 mt-4 flex items-center justify-between shadow-sm">
        <div>
            <h4 class="text-sm font-semibold text-text-main mb-1">Review Hasil & Tandai Selesai (Khusus FOP)</h4>
            <p class="text-xs text-text-secondary">Task ini telah diselesaikan oleh teknisi dan menunggu persetujuan Anda.</p>
        </div>
        <div class="flex items-center gap-3">
            <button x-data @click="$dispatch('open-modal', 'reject-task')"
                    class="inline-flex items-center gap-2 text-sm font-semibold px-4 py-2 rounded-md border bg-white transition-colors"
                    style="border-color:var(--color-error-border); color:var(--color-error)">
                Reject (Kembalikan ke Teknisi)
            </button>
            <form action="{{ route('tasks.review', $task) }}" method="POST">
                @csrf
                <input type="hidden" name="action" value="approve">
                <button type="submit"
                        class="inline-flex items-center gap-2 text-sm font-semibold px-5 py-2.5 rounded-md text-white transition-colors"
                        style="background:var(--color-primary)">
                    Approve Task
                </button>
            </form>
        </div>
    </div>
    @endcan
    @endif
</div>

{{-- ══ Schedule Task Modal ═════════════════════════════════════════ --}}
@can('schedule', $task)
@if($task->status->value === 'pending')
<x-ui.modal name="schedule-task" title="Jadwalkan Task" maxWidth="md">
    <form action="{{ route('tasks.schedule', $task) }}" method="POST">
        @csrf
        <div class="space-y-4 p-4">
            <div>
                <label class="block text-sm font-medium mb-1">Tanggal & Waktu</label>
                <input type="datetime-local" name="scheduled_at" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary" required>
            </div>

            @php
                $availableTeknisi = \App\Models\User::whereHas('role', fn($q) => $q->where('code', 'teknisi'))->orderBy('name')->get();
            @endphp
            <div>
                <label class="block text-sm font-medium mb-1">Tim Teknisi (1-3)</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-2">
                    @foreach($availableTeknisi as $tek)
                    <label class="flex items-center gap-2 p-2 border rounded-md cursor-pointer hover:bg-surface transition-colors border-border">
                        <input type="checkbox" name="team_member_ids[]" value="{{ $tek->id }}" class="h-4 w-4 rounded border-border text-primary focus:ring-primary">
                        <span class="text-sm font-medium text-text-main">{{ $tek->name }}</span>
                    </label>
                    @endforeach
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Checklist Poin (per baris / koma)</label>
                <textarea name="checklist_items" rows="4"
                          class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm"
                          placeholder="Verifikasi KTP&#10;Cek Sinyal&#10;Foto Lokasi"
                          required></textarea>
                <p class="text-xs text-gray-500 mt-1">Pisahkan dengan baris baru atau koma</p>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 px-4 py-3 bg-gray-50 border-t border-gray-200">
            <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'schedule-task')">
                Batal
            </x-ui.button>
            <x-ui.button type="submit" variant="primary">
                Jadwalkan
            </x-ui.button>
        </div>
    </form>
</x-ui.modal>
@endif
@endcan

{{-- ══ FOP Reject Pending Task Modal ═════════════════════════════════ --}}
@can('fopReject', $task)
<x-ui.modal name="fop-reject-task-pending" title="Reject Pending Task" maxWidth="sm">
    <p class="text-sm text-text-secondary mb-4">
        Task ini belum dijadwalkan dan akan tetap berstatus <span class="font-semibold text-text-main">Pending</span>, namun dengan keterangan reject.
    </p>
    <form id="form-fop-reject-pending" action="{{ route('tasks.fop-reject', $task) }}" method="POST">
        @csrf
        <div class="space-y-1.5">
            <label class="block text-xs font-semibold uppercase tracking-wider text-text-muted">Alasan Reject <span class="text-error">*</span></label>
            <x-ui.textarea name="reject_reason" rows="3" placeholder="Alasan reject task..." required />
        </div>
    </form>
    <x-slot name="footer">
        <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'fop-reject-task-pending')">
            Batal
        </x-ui.button>
        <x-ui.button type="submit" form="form-fop-reject-pending" variant="danger">
            Reject Task
        </x-ui.button>
    </x-slot>
</x-ui.modal>
@endcan

{{-- ══ FOP Set Pending Scheduled Task Modal ═══════════════════════════ --}}
@can('fopPending', $task)
<x-ui.modal name="fop-pending-task" title="Set Task Menjadi Pending" maxWidth="sm">
    <p class="text-sm text-text-secondary mb-4">
        Task ini akan diubah statusnya dari <span class="font-semibold text-text-main">Terjadwal</span> menjadi <span class="font-semibold text-text-main">Pending</span>. Tim teknisi yang sudah di-assign tidak akan terhapus.
    </p>
    <form id="form-fop-pending" action="{{ route('tasks.fop-pending', $task) }}" method="POST">
        @csrf
        <div class="space-y-1.5">
            <label class="block text-xs font-semibold uppercase tracking-wider text-text-muted">Alasan Pending <span class="text-error">*</span></label>
            <x-ui.textarea name="pending_reason" rows="3" placeholder="Alasan mengapa di-pending..." required />
        </div>
    </form>
    <x-slot name="footer">
        <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'fop-pending-task')">
            Batal
        </x-ui.button>
        <x-ui.button type="submit" form="form-fop-pending" variant="warning">
            Set Pending
        </x-ui.button>
    </x-slot>
</x-ui.modal>
@endcan


{{-- ══ FOP Reject Modal ══════════════════════════════════════════════ --}}
@can('review', $task)
<x-ui.modal name="reject-task" title="Reject Laporan Task" maxWidth="sm">
    <p class="text-sm text-text-secondary mb-4">
        Task ini akan dikembalikan ke status <span class="font-semibold text-text-main">In Progress</span>. 
        Teknisi harus memperbaiki laporan berdasarkan alasan reject.
    </p>
    <form id="form-reject-task" action="{{ route('tasks.review', $task) }}" method="POST">
        @csrf
        <input type="hidden" name="action" value="reject">
        <div class="space-y-1.5">
            <label class="block text-xs font-semibold uppercase tracking-wider text-text-muted">Alasan Reject <span class="text-error">*</span></label>
            <x-ui.textarea name="reason" rows="3" placeholder="Alasan reject (misal: Foto bukti kurang jelas)..." required />
        </div>
    </form>
    <x-slot name="footer">
        <x-ui.button type="button" variant="secondary"
                     x-on:click="$dispatch('close-modal', 'reject-task')">
            Batal
        </x-ui.button>
        <x-ui.button type="submit" form="form-reject-task" variant="danger">
            Konfirmasi Reject
        </x-ui.button>
    </x-slot>
</x-ui.modal>
@endcan

{{-- ══ Cancel Modal ══════════════════════════════════════════════════ --}}
@can('cancel', $task)
<x-ui.modal name="cancel-task" title="Batalkan Task" maxWidth="sm">
    <p class="text-sm text-text-secondary mb-4">
        Task <span class="font-mono font-semibold">{{ $task->task_number }}</span> akan dibatalkan.
        Tindakan ini tidak dapat dibatalkan.
    </p>
    <form id="form-cancel-task" action="{{ route('tasks.cancel', $task) }}" method="POST">
        @csrf
        <div class="space-y-1.5">
            <label class="block text-xs font-semibold uppercase tracking-wider text-text-muted">Alasan Pembatalan <span class="text-error">*</span></label>
            <x-ui.textarea name="cancel_reason" rows="3" placeholder="Alasan pembatalan..." required />
        </div>
    </form>
    <x-slot name="footer">
        <x-ui.button type="button" variant="secondary"
                     x-on:click="$dispatch('close-modal', 'cancel-task')">
            Batal
        </x-ui.button>
        <x-ui.button type="submit" form="form-cancel-task" variant="danger">
            Ya, Batalkan Task
        </x-ui.button>
    </x-slot>
</x-ui.modal>
@endcan

{{-- ══ Pending Modal ("Laporan Nanti") ══════════════════════════════ --}}
@can('statusPending', $task)
<x-ui.modal name="pending-task" title="{{ in_array($task->task_type->value, [\App\Enums\TaskType::SURVEY->value, \App\Enums\TaskType::PEMASANGAN->value, \App\Enums\TaskType::MAINTENANCE->value]) ? 'Laporan Nanti' : 'Set Pending' }}" maxWidth="sm">
    <p class="text-sm text-text-secondary mb-4 leading-relaxed">
        @if(in_array($task->task_type->value, [\App\Enums\TaskType::SURVEY->value, \App\Enums\TaskType::PEMASANGAN->value, \App\Enums\TaskType::MAINTENANCE->value]))
            Task akan disimpan sementara dengan status <span class="font-semibold text-text-main">Pending</span>. Anda dapat melanjutkan pengisian laporan hasil survei/pemasangan/maintenance sewaktu-waktu (misalnya setelah kembali ke kantor atau kondisi sinyal membaik).
        @else
            Task ini akan diubah statusnya menjadi <span class="font-semibold text-text-main">Pending</span> dan menunggu penanganan atau penjadwalan ulang dari FOP.
        @endif
    </p>
    <form id="form-pending-task" action="{{ route('tasks.pending', $task) }}" method="POST">
        @csrf
        <div class="space-y-1.5">
            <label class="block text-xs font-semibold uppercase tracking-wider text-text-muted">
                {{ in_array($task->task_type->value, [\App\Enums\TaskType::SURVEY->value, \App\Enums\TaskType::PEMASANGAN->value]) ? 'Alasan Menunda Laporan' : 'Alasan Pending' }} <span class="text-error">*</span>
            </label>
            <x-ui.textarea name="pending_reason" rows="3" placeholder="{{ in_array($task->task_type->value, [\App\Enums\TaskType::SURVEY->value, \App\Enums\TaskType::PEMASANGAN->value]) ? 'Contoh: Kendala sinyal di lokasi, laporan akan dilanjutkan di kantor...' : 'Alasan task di-pending...' }}" required />
        </div>
    </form>
    <x-slot name="footer">
        <x-ui.button type="button" variant="secondary"
                     x-on:click="$dispatch('close-modal', 'pending-task')">
            Batal
        </x-ui.button>
        <x-ui.button type="submit" form="form-pending-task" variant="warning">
            {{ in_array($task->task_type->value, [\App\Enums\TaskType::SURVEY->value, \App\Enums\TaskType::PEMASANGAN->value]) ? 'Konfirmasi Laporan Nanti' : 'Konfirmasi Pending' }}
        </x-ui.button>
    </x-slot>
</x-ui.modal>
@endcan

{{-- ══ Task Data untuk Alpine.js ═════════════════════════════════════ --}}
@php
$taskData = [
    'id' => $task->id,
    'task_number' => $task->task_number,
    'customer_name' => $task->customer?->full_name ?? '—',
    'customer_address' => $task->customer?->address ?? '—',
    'pop_name' => $task->pop?->name ?? '—',
    'task_type' => $task->task_type->value,
    'evidence_count' => $task->evidences->count(),
    'can_complete' => $task->canComplete(),
    'evidence_url' => route('tasks.evidences.store', $task),
    'submit_url_survey' => route('customers.survey.store', $task),
    'submit_url_install' => route('customers.installation.store', $task),
    // 'submit_url_survey' => route('tasks.survey-report.store', $task),
    // 'submit_url_install' => route('tasks.install-report.store', $task),
    'current_package_id' => $task->customer?->customerService?->internet_package_id,
];
@endphp

@push('scripts')
<script>
function evidenceSection(taskId, initialCanComplete, initialCount) {
    return {
        taskId,
        canComplete:   initialCanComplete,
        evidenceCount: initialCount,
        uploadError:   null,

        async uploadEvidence(input) {
            const file = input.files[0];
            if (!file) return;
            this.uploadError = null;
            const form = new FormData();
            form.append('photo', file);
            form.append('_token', document.querySelector('meta[name="csrf-token"]').content);
            const res  = await fetch(`/tasks/${this.taskId}/evidences`, {
                method: 'POST', body: form, headers: { 'Accept': 'application/json' },
            });
            const data = await res.json();
            if (data.success) {
                this.evidenceCount = data.evidence_count;
                this.canComplete   = data.can_complete;
                window.location.reload();
            } else {
                this.uploadError = 'Gagal upload. Coba lagi.';
            }
            input.value = '';
        },

        deleteEvidence(id) {
            window.Confirm(
                'Hapus Foto Bukti',
                'Apakah Anda yakin ingin menghapus foto bukti ini?',
                'error',
                async () => {
                    const res  = await fetch(`/tasks/${this.taskId}/evidences/${id}`, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                    });
                    const data = await res.json();
                    if (data.success) { this.evidenceCount = data.evidence_count; window.location.reload(); }
                }
            );
        },
    };
}

@endpush
@endsection

