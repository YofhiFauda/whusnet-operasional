@extends('layouts.app')

@section('title', $task->task_number . ' — Task Detail')

@section('content')
<div class="max-w-[1180px] mx-auto px-4 sm:px-6 py-4 space-y-4">

    {{-- ══ Breadcrumb ═══════════════════════════════════════════════ --}}
    <nav class="flex items-center gap-1.5 text-xs text-text-muted">
        @can('viewAll', \App\Models\Task::class)
        <a href="{{ auth()->user()->hasPermission('task.view.own') ? route('tasks.own') : route('fop.dashboard') }}" class="hover:text-primary transition-colors font-ui">Task</a>
        @else
        <a href="{{ route('tasks.own') }}" class="hover:text-primary transition-colors font-ui">Task Saya</a>
        @endcan
        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
        </svg>
        <span class="font-mono">{{ $task->task_number }}</span>
    </nav>

    {{-- ══ Page Header ══════════════════════════════════════════════ --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap mb-1">
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
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full border font-ui" style="{{ $statusStyle }}">
                    {{ $task->status->label() }}
                </span>
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full border font-ui {{ $task->task_type->cardClasses() }}">
                    {{ $task->task_type->label() }}
                </span>
                <span class="font-mono text-xs text-text-muted">{{ $task->task_number }}</span>
                @if($task->isOverSla())
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full border font-ui"
                      style="background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border)">
                    Melewati SLA
                </span>
                @endif
            </div>
            <h1 class="text-lg sm:text-xl font-bold text-text-main font-ui">{{ $task->title }}</h1>
        </div>
        @php
            $lat = $task->customer?->customerAddress?->latitude ?? $task->pop?->latitude;
            $lng = $task->customer?->customerAddress?->longitude ?? $task->pop?->longitude;
        @endphp
        <div class="flex items-center gap-1.5 shrink-0 flex-wrap">
            @if($lat && $lng)
            <a href="https://www.google.com/maps/search/?api=1&query={{ $lat }},{{ $lng }}" target="_blank"
               class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1.5 border border-border rounded bg-surface hover:bg-surface-muted text-primary transition-colors shadow-sm font-ui cursor-pointer">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                Lokasi Maps
            </a>
            @endif
            @can('edit', $task)
            <a href="{{ route('tasks.edit', $task) }}"
               class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1.5 border border-border rounded bg-surface hover:bg-surface-muted text-text-secondary transition-colors font-ui cursor-pointer">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Edit
            </a>
            @endcan
            @can('cancel', $task)
            <button x-data @click="$dispatch('open-modal', 'cancel-task')"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1.5 border rounded transition-colors font-ui cursor-pointer"
                    style="border-color:var(--color-error-border); color:var(--color-error); background:var(--color-error-bg)">
                Batalkan
            </button>
            @endcan
        </div>
    </div>

    {{-- ══ Metric Strip ═════════════════════════════════════════════ --}}
    <div class="bg-surface border border-border rounded-lg overflow-hidden shadow-sm">
        <div class="grid grid-cols-2 sm:grid-cols-5 divide-y sm:divide-y-0 sm:divide-x divide-border">
            {{-- Tipe --}}
            <div class="p-3 flex flex-col justify-between">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted mb-0.5 font-ui">Tipe Task</p>
                <p class="text-xs font-semibold text-text-main font-ui">{{ $task->task_type->label() }}</p>
            </div>
            {{-- Jadwal --}}
            <div class="p-3 flex flex-col justify-between">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted mb-0.5 font-ui">Jadwal</p>
                <div>
                    <p class="text-xs font-semibold font-mono text-text-main">{{ $task->scheduled_at?->format('H:i') ?? '—' }}</p>
                    <p class="text-[10px] text-text-muted font-ui">{{ $task->scheduled_at?->translatedFormat('d M Y') ?? 'Belum dijadwalkan' }}</p>
                </div>
            </div>
            {{-- Durasi vs SLA --}}
            <div class="p-3 flex flex-col justify-between">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted mb-0.5 font-ui">Target SLA</p>
                <div>
                    <p class="text-xs font-semibold font-mono {{ $task->isOverSla() ? '' : 'text-text-main' }}"
                       style="{{ $task->isOverSla() ? 'color:var(--color-error)' : '' }}">
                        {{ $task->sla_minutes }} Menit
                    </p>
                    @if($task->actualDurationMinutes() !== null)
                    <p class="text-[10px] text-text-muted font-mono">Aktual: {{ $task->actualDurationMinutes() }} Mnt</p>
                    @endif
                </div>
            </div>
            {{-- POP --}}
            <div class="p-3 flex flex-col justify-between">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted mb-0.5 font-ui">POP / Cabang</p>
                <p class="text-xs font-semibold text-text-main truncate font-ui">{{ $task->pop?->name ?? '—' }}</p>
            </div>
            {{-- Foto Bukti --}}
            <div class="p-3 flex flex-col justify-between col-span-2 sm:col-span-1">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted mb-0.5 font-ui">Foto Bukti</p>
                <div>
                    <p class="text-xs font-semibold font-mono text-text-main">{{ $task->evidences->count() }} Foto</p>
                    <p class="text-[10px] text-text-muted font-ui">Terupload</p>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ Unified Detail Panel Container ═══════════════════════════ --}}
    <div class="bg-surface sm:border border-border sm:rounded-lg sm:shadow-sm overflow-hidden">
        <div class="grid grid-cols-1 md:grid-cols-12 divide-y md:divide-y-0 md:divide-x divide-border">
            
            {{-- Left Column: Informasi, Briefing Teknis, Foto Bukti --}}
            <div class="md:col-span-7 p-4 sm:p-5 space-y-4">
                
                {{-- ══ Informasi Task ════════════════════════════════ --}}
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted mb-2 font-ui">Informasi Task</p>
                    <div class="space-y-0.5 text-xs">
                        <div class="flex flex-col sm:flex-row sm:items-start py-2 border-b border-border gap-1 sm:gap-4">
                            <span class="text-text-muted sm:w-36 shrink-0 font-ui">FOP / Koordinator</span>
                            <span class="text-text-main font-medium flex-1 font-ui">{{ $task->fop?->name ?? '—' }}</span>
                        </div>
                        
                        <div class="flex flex-col sm:flex-row sm:items-start py-2 border-b border-border gap-1 sm:gap-4">
                            <span class="text-text-muted sm:w-36 shrink-0 font-ui">Pelanggan & Kontak</span>
                            <div class="text-text-main font-medium flex-1 font-ui">
                                @if($task->customer)
                                <div>
                                    <a href="{{ route('customers.show', $task->customer) }}"
                                       class="hover:underline font-semibold" style="color:var(--color-primary)">
                                        {{ $task->customer->full_name }}
                                    </a>
                                    <span class="font-mono text-xs text-text-muted ml-1">{{ $task->customer->display_id }}</span>
                                </div>
                                @if($task->customer->primary_phone)
                                <div class="flex items-center gap-1.5 mt-1 text-[11px]">
                                    <span class="text-text-muted font-mono">{{ $task->customer->primary_phone }}</span>
                                    <a href="https://wa.me/{{ preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $task->customer->primary_phone)) }}" target="_blank"
                                       class="text-emerald-600 dark:text-emerald-400 hover:underline inline-flex items-center gap-0.5 font-semibold cursor-pointer">
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
                        <div class="flex flex-col sm:flex-row sm:items-start py-2 border-b border-border gap-1 sm:gap-4">
                            <span class="text-text-muted sm:w-36 shrink-0 font-bold text-amber-700 dark:text-amber-400 font-ui">Issue / Keluhan</span>
                            <span class="text-text-main font-semibold leading-relaxed bg-amber-50/60 dark:bg-amber-900/20 border border-amber-200/60 dark:border-amber-800/30 rounded px-2 py-1 flex-1 font-ui">{{ $task->description }}</span>
                        </div>
                        @endif

                        @if($task->customer || $task->pop)
                        <div class="flex flex-col sm:flex-row sm:items-start py-2 border-b border-border gap-1 sm:gap-4">
                            <span class="text-text-muted sm:w-36 shrink-0 font-ui">Alamat & Lokasi</span>
                            <div class="text-text-secondary leading-relaxed flex-1 font-ui">
                                <div>
                                    @if($task->customer)
                                        {{ $task->customer->clean_address }}
                                    @else
                                        {{ $task->pop?->address ?? '—' }} ({{ $task->pop?->name }})
                                    @endif
                                </div>
                                @if($lat && $lng)
                                <div class="flex items-center gap-2 mt-1.5 pt-1.5 border-t border-border/50 text-[11px] flex-wrap">
                                    <span class="font-mono text-text-main">Lat: <strong class="text-primary">{{ $lat }}</strong> | Lng: <strong class="text-primary">{{ $lng }}</strong></span>
                                    <a href="https://www.google.com/maps/search/?api=1&query={{ $lat }},{{ $lng }}" target="_blank"
                                       class="inline-flex items-center gap-1 font-bold text-primary hover:underline cursor-pointer">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        Maps →
                                    </a>
                                </div>
                                @endif
                            </div>
                        </div>
                        @endif

                        @if($task->pending_reason)
                        <div class="flex flex-col sm:flex-row sm:items-start py-2 border-b border-border gap-1 sm:gap-4">
                            <span class="text-text-muted sm:w-36 shrink-0 font-ui">Alasan Pending</span>
                            <span class="font-semibold flex-1 text-warning font-ui">{{ $task->pending_reason }}</span>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- ══ Briefing Detail Teknis ════════════════════════ --}}
                <div class="pt-4 border-t border-border">
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted mb-2 font-ui">Briefing Detail Teknis</p>
                    
                    @if($task->task_type === \App\Enums\TaskType::SURVEY)
                    {{-- Survey specific details --}}
                    <div class="space-y-0.5 text-xs">
                        <div class="flex flex-col sm:flex-row sm:items-start py-2 border-b border-border gap-1 sm:gap-4">
                            <span class="text-text-muted sm:w-36 shrink-0 font-ui">Status SLA Berjalan</span>
                            <div class="text-text-main text-xs font-medium flex-1">
                                @if($task->started_at && !$task->completed_at)
                                    @php
                                        $elapsed = (int) $task->started_at->diffInMinutes(now());
                                        $remaining = $task->sla_minutes - $elapsed;
                                    @endphp
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-bold font-mono {{ $remaining < 0 ? 'text-error' : 'text-primary' }}">
                                            {{ $elapsed }} menit berjalan
                                        </span>
                                        <span class="text-[10px] text-text-muted font-mono">({{ $remaining >= 0 ? "Sisa {$remaining} mnt" : "Over SLA " . abs($remaining) . " mnt" }})</span>
                                    </div>
                                @elseif($task->status->value === 'terjadwal')
                                    <span class="text-warning font-ui">Menunggu teknisi klik Mulai Survey (Target SLA: {{ $task->sla_minutes }} menit)</span>
                                @elseif($task->completed_at)
                                    <span class="text-success font-ui">Selesai dikerjakan dalam {{ $task->actualDurationMinutes() }} menit</span>
                                @else
                                    <span class="text-text-muted font-ui">{{ $task->status->label() }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex flex-col sm:flex-row sm:items-start py-2 border-b border-border gap-1 sm:gap-4">
                            <span class="text-text-muted sm:w-36 shrink-0 font-ui">Rencana Paket</span>
                            <span class="text-text-main font-semibold flex-1 font-ui">
                                {{ $task->customer?->customerService?->internetPackage?->name ?? $task->customer?->customerService?->package_name_snapshot ?? 'Belum dipilih saat pendaftaran' }}
                            </span>
                        </div>
                    </div>
                    
                    @elseif($task->task_type === \App\Enums\TaskType::PEMASANGAN)
                    @php
                        $survey = $task->customer?->latestSurvey;
                        $service = $task->customer?->customerService;
                        $device = $task->customer?->customerDevice;
                    @endphp
                    <div class="space-y-3">
                        <div>
                            <span class="block text-[9px] font-semibold text-text-muted uppercase mb-1.5 font-ui">Hasil Survey Sebelumnya</span>
                            @if($survey)
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs">
                                <div class="border-b sm:border-b-0 sm:border-r border-border pb-1.5 sm:pb-0 sm:pr-2">
                                    <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">ODP Tujuan & Port</span>
                                    <span class="font-bold font-mono text-text-main text-xs mt-0.5 block">{{ $survey->nearest_odp ?: '-' }}</span>
                                </div>
                                <div class="border-b sm:border-b-0 sm:border-r border-border py-1.5 sm:py-0 sm:px-2">
                                    <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">Estimasi Dropcore</span>
                                    <span class="font-bold font-mono text-text-main text-xs mt-0.5 block">{{ $survey->cable_estimation_meter ? $survey->cable_estimation_meter . ' Meter' : '-' }}</span>
                                </div>
                                <div class="py-1.5 sm:py-0 sm:pl-2">
                                    <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">Alat Khusus</span>
                                    <span class="font-semibold text-text-main text-xs mt-0.5 block font-ui">{{ $survey->required_tools ?: 'Standar' }}</span>
                                </div>
                            </div>
                            @if($survey->survey_note)
                            <p class="text-[11px] text-text-secondary mt-1.5 italic font-ui">"{{ $survey->survey_note }}"</p>
                            @endif
                            @else
                            <p class="text-[11px] text-warning font-ui">Data hasil survey sebelumnya belum tercatat di sistem.</p>
                            @endif
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-3 border-t border-border text-xs">
                            <div>
                                <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">Paket yang Diaktifkan</span>
                                <span class="text-xs font-bold text-primary block mt-0.5 font-ui">{{ $service?->internetPackage?->name ?? $service?->package_name_snapshot ?? '-' }}</span>
                                @if($service?->monthly_price)
                                <span class="text-[10px] text-text-muted font-mono block mt-0.5">Rp {{ number_format($service->monthly_price, 0, ',', '.') }} / bulan</span>
                                @endif
                            </div>
                            <div>
                                <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">Alokasi Perangkat / ONT</span>
                                @if($device)
                                    <span class="text-xs font-bold text-text-main block mt-0.5 font-ui">{{ $device->brand }} {{ $device->model }}</span>
                                    <span class="text-[10px] font-mono text-text-muted block mt-0.5 font-ui">SN: {{ $device->serial_number ?: 'Belum diinput' }}</span>
                                @else
                                    <span class="text-xs text-warning font-medium block mt-0.5 font-ui font-ui">Perangkat ONT akan dicatat saat laporan pemasangan selesai.</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    @elseif($task->task_type === \App\Enums\TaskType::MAINTENANCE)
                    @php
                        $tech = $task->customer?->customerTechnicalDetail;
                        $device = $task->customer?->customerDevice;
                    @endphp
                    <div class="space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs">
                            <div class="border-b sm:border-b-0 sm:border-r border-border pb-1.5 sm:pb-0 sm:pr-2">
                                <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">ODP & Port Terhubung</span>
                                <span class="font-bold font-mono text-text-main text-xs mt-0.5 block">
                                    {{ $device?->odp ?? $tech?->odp_number ?: '-' }} 
                                    @if($device?->odp_port || $tech?->odp_port)
                                        (Port {{ $device?->odp_port ?? $tech?->odp_port }})
                                    @endif
                                </span>
                            </div>
                            <div class="border-b sm:border-b-0 sm:border-r border-border py-1.5 sm:py-0 sm:px-2">
                                <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">OLT & Port OLT</span>
                                <span class="font-bold font-mono text-text-main text-xs mt-0.5 block">
                                    {{ $tech?->olt_number ?: '-' }}
                                    @if($tech?->olt_port) Port {{ $tech->olt_port }} @endif
                                </span>
                            </div>
                            <div class="py-1.5 sm:py-0 sm:pl-2">
                                <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">Redaman RX Power</span>
                                <span class="font-bold font-mono text-text-main text-xs mt-0.5 block">
                                    {{ $device?->signal_rx_power ?? $tech?->initial_attenuation ? ($device?->signal_rx_power ?? $tech?->initial_attenuation) . ' dBm' : '-' }}
                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-3 border-t border-border text-xs">
                            <div>
                                <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">Perangkat Terpasang</span>
                                <span class="font-bold text-text-main text-xs mt-0.5 block font-ui">
                                    {{ $device?->brand ?? 'Modem' }} {{ $device?->model }}
                                </span>
                                <span class="text-[10px] font-mono text-text-muted mt-0.5 block">
                                    SN: {{ $device?->serial_number ?? $tech?->router_or_ont_serial ?: '-' }}
                                </span>
                            </div>
                            <div>
                                <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">PPPoE User / IP Address</span>
                                <span class="font-bold font-mono text-text-main text-xs mt-0.5 block">{{ $device?->pppoe_username ?: '-' }}</span>
                                <span class="text-[10px] font-mono text-text-muted mt-0.5 block">IP: {{ $device?->ip_address ?? $tech?->ip_address ?: '-' }}</span>
                            </div>
                        </div>
                    </div>

                    @elseif($task->task_type === \App\Enums\TaskType::AMBIL_MODEM)
                    @php
                        $device = $task->customer?->customerDevice;
                    @endphp
                    <div class="space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                            <div>
                                <span class="block text-[9px] text-text-muted font-bold uppercase font-ui font-ui">Alasan Deaktivasi</span>
                                <span class="text-xs font-bold text-text-main mt-0.5 block font-ui">{{ $task->description ?: 'Pengambilan Modem' }}</span>
                            </div>
                            <div>
                                <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">Janji Temu</span>
                                <span class="text-xs font-bold text-text-main font-mono mt-0.5 block">{{ $task->scheduled_at?->translatedFormat('l, d M Y — H:i') ?: 'Segera' }} WIB</span>
                            </div>
                        </div>

                        <div class="pt-3 border-t border-border space-y-2">
                            <span class="block text-[9px] text-text-main font-bold uppercase font-ui">Aset ISP yang Wajib Ditarik</span>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                                <div class="bg-background p-2.5 rounded border border-border">
                                    <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">ONT / Modem</span>
                                    <span class="font-bold text-text-main text-xs mt-0.5 block font-ui">{{ $device?->brand ?: 'Modem ONT' }} {{ $device?->model }}</span>
                                    <p class="text-[11px] text-primary font-mono mt-1 font-bold">SN: {{ $device?->serial_number ?: 'PERIKSA FISIK' }}</p>
                                </div>
                                <div class="bg-background p-2.5 rounded border border-border">
                                    <span class="block text-[9px] text-text-muted font-bold uppercase mb-1 font-ui font-ui">Kelengkapan</span>
                                    <ul class="list-disc list-inside space-y-0.5 text-text-secondary text-[10px] font-ui">
                                        <li>Adaptor Power</li>
                                        <li>Kabel Patchcord / LAN</li>
                                        <li>Router / STB Tambahan</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    @elseif($task->task_type === \App\Enums\TaskType::CREQ)
                    @php
                        $device = $task->customer?->customerDevice;
                        $tech = $task->customer?->customerTechnicalDetail;
                    @endphp
                    <div class="space-y-3">
                        <div class="text-xs">
                            <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">Rincian Request</span>
                            <span class="text-xs font-bold text-text-main block mt-0.5 font-ui">{{ $task->description ?: $task->title }}</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-3 border-t border-border text-xs">
                            <div>
                                <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">SSID WiFi Eksisting</span>
                                <span class="text-xs font-bold font-mono text-text-main block mt-0.5">{{ $device?->wifi_ssid ?? $tech?->ssid ?: 'Standard / Default' }}</span>
                            </div>
                            <div>
                                <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">IP Gateway Akses</span>
                                <span class="text-xs font-bold font-mono text-text-main block mt-0.5">{{ $device?->ip_address ?? $tech?->ip_address ?: '192.168.1.1' }}</span>
                            </div>
                        </div>
                    </div>

                    @elseif(in_array($task->task_type, [\App\Enums\TaskType::OREQ, \App\Enums\TaskType::INFR]))
                    <div class="space-y-3 text-xs">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">POP Pembina</span>
                                <span class="text-xs font-bold text-text-main mt-0.5 block font-ui">{{ $task->pop?->name ?? 'Pusat' }}</span>
                            </div>
                            <div>
                                <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">Target / Lokasi</span>
                                <span class="text-xs text-text-main font-semibold mt-0.5 block font-ui">{{ $task->pop?->address ?: 'Infrastruktur POP' }}</span>
                            </div>
                        </div>
                        <div class="pt-3 border-t border-border">
                            <span class="block text-[9px] text-text-muted font-bold uppercase font-ui font-ui">Instruksi Pekerjaan</span>
                            <span class="text-xs font-semibold text-text-main leading-relaxed mt-0.5 block font-ui">{{ $task->description ?: $task->title }}</span>
                        </div>
                    </div>
                    @endif
                </div>

                {{-- ══ Foto Bukti (Non-Survey/PSB) ══════════════════ --}}
                @if(!in_array($task->task_type->value, [\App\Enums\TaskType::SURVEY->value, \App\Enums\TaskType::PEMASANGAN->value]))
                <div class="pt-4 border-t border-border"
                     x-data="evidenceSection({{ $task->id }}, {{ $task->canComplete() ? 'true' : 'false' }}, {{ $task->evidences->count() }})">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted font-ui">Foto Bukti</p>
                        <span class="text-[10px] font-semibold font-mono text-text-secondary bg-background px-1.5 py-0.5 rounded border border-border" x-text="`${evidenceCount} foto`"></span>
                    </div>
                    
                    {{-- Grid foto --}}
                    @if($task->evidences->count() > 0)
                    <div class="grid grid-cols-3 gap-2 mb-3">
                        @foreach($task->evidences as $evidence)
                        <div class="relative group rounded-md overflow-hidden aspect-square bg-surface-muted border border-border">
                            <img src="{{ asset('storage/' . $evidence->file_path) }}"
                                 alt="{{ $evidence->caption ?? 'Bukti' }}"
                                 class="h-full w-full object-cover">
                            @if($evidence->caption)
                            <div class="absolute bottom-0 left-0 right-0 px-1.5 py-0.5 text-[9px] text-white truncate"
                                 style="background: rgba(15,23,42,0.7)">
                                {{ $evidence->caption }}
                            </div>
                            @endif
                            @can('edit', $task)
                            <button @click="deleteEvidence({{ $evidence->id }})"
                                    class="absolute top-1 right-1 h-5 w-5 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity text-white cursor-pointer"
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
                    <p class="text-xs text-text-muted mb-3 font-ui">Belum ada foto bukti.</p>
                    @endif

                    {{-- Upload --}}
                    @can('uploadEvidence', $task)
                    @if(in_array($task->status->value, ['in_progress', 'pending']))
                    <label class="block cursor-pointer">
                        <input type="file" accept="image/*" capture="environment" class="hidden" @change="uploadEvidence($event.target)" />
                        <div class="flex items-center justify-center gap-1.5 text-xs font-medium py-2 border border-dashed rounded transition-colors"
                             style="border-color:var(--color-primary-border); color:var(--color-primary)"
                             onmouseover="this.style.background='var(--color-primary-soft)'"
                             onmouseout="this.style.background=''">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            Upload Foto Bukti
                        </div>
                    </label>
                    <p x-show="uploadError" x-text="uploadError" class="text-[11px] mt-1.5 font-ui" style="color:var(--color-error)"></p>
                    @endif
                    @endcan
                </div>
                @endif
                
            </div>{{-- /left-column --}}
            
            {{-- Right Column: Waktu Pengerjaan, Tim Teknisi, Audit Log --}}
            <div class="md:col-span-5 p-4 sm:p-5 space-y-4 bg-slate-50/50 dark:bg-slate-800/50">
                
                {{-- ══ Waktu Pengerjaan ══════════════════════════════ --}}
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
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted font-ui">
                            Waktu {{ $showTypeLabel }}
                        </p>
                        <span class="text-[9px] font-semibold px-2 py-0.5 rounded-full border font-mono"
                              style="{{ $showOverSla
                                  ? 'background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border)'
                                  : 'background:var(--color-success-bg); color:var(--color-success); border-color:var(--color-success-border)' }}">
                            {{ $showOverSla ? 'Over SLA' : 'Dalam SLA' }}
                        </span>
                    </div>
                    <div class="bg-surface border border-border rounded p-3 space-y-2 shadow-xs">
                        <div class="flex items-center justify-between text-[11px]">
                            <div>
                                <p class="text-[9px] font-semibold uppercase tracking-widest text-text-muted mb-0.5 font-ui font-ui">Mulai</p>
                                <p class="font-mono font-semibold text-text-main">{{ $showStartedAt->format('H:i') }}</p>
                                <p class="text-[9px] text-text-muted font-ui">{{ $showStartedAt->translatedFormat('d M Y') }}</p>
                            </div>
                            <svg class="h-3.5 w-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25L21 12m0 0l-3.75 3.75M21 12H3" />
                            </svg>
                            <div class="text-right">
                                <p class="text-[9px] font-semibold uppercase tracking-widest text-text-muted mb-0.5 font-ui">Selesai</p>
                                <p class="font-mono font-semibold text-text-main">{{ $showCompletedAt->format('H:i') }}</p>
                                <p class="text-[9px] text-text-muted font-ui">{{ $showCompletedAt->translatedFormat('d M Y') }}</p>
                            </div>
                        </div>
                        <div class="pt-2 border-t border-border flex items-center justify-between text-[11px]">
                            <div>
                                <p class="text-[9px] font-semibold uppercase tracking-widest text-text-muted font-ui">Durasi Aktual</p>
                                <p class="font-mono font-semibold" style="color:{{ $showOverSla ? 'var(--color-error)' : 'var(--color-success)' }}">
                                    {{ $showDuration }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-[9px] font-semibold uppercase tracking-widest text-text-muted font-ui font-ui">Target SLA</p>
                                <p class="font-mono font-semibold text-text-secondary">{{ $task->sla_minutes }} menit</p>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                {{-- ══ Tim Teknisi ══════════════════════════════════ --}}
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted mb-2 font-ui">Tim Teknisi</p>
                    @if($task->teamMembers->count() > 0)
                    <div class="space-y-1.5">
                        @foreach($task->teamMembers as $member)
                        <div class="flex items-center gap-2 bg-surface border border-border rounded px-2.5 py-1.5 w-full shadow-xs">
                            <div class="h-6.5 w-6.5 rounded-full bg-primary-soft flex items-center justify-center text-[10px] font-bold shrink-0"
                                 style="color:var(--color-primary)">
                                {{ strtoupper(substr($member->user?->name ?? '?', 0, 2)) }}
                            </div>
                            <div class="flex-1 min-w-0 pr-3">
                                <p class="text-xs font-semibold text-text-main truncate font-ui">{{ $member->user?->name ?? 'User dihapus' }}</p>
                                <p class="text-[9px] text-text-muted capitalize font-ui">{{ $member->role_in_task }}</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <p class="text-xs text-text-muted font-ui">Belum ada anggota tim.</p>
                    @endif
                </div>

                {{-- ══ Riwayat Status (Audit Log) ══════════════════ --}}
                @if(auth()->user()->hasRole(['owner', 'admin', 'fop']))
                <div class="pt-4 border-t border-border">
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted mb-2 font-ui font-ui">Riwayat Status (Audit Log)</p>
                    @if($task->auditLogs && $task->auditLogs->count() > 0)
                    <div class="relative border-l border-border ml-2 space-y-3">
                        @foreach($task->auditLogs as $log)
                        <div class="relative pl-3.5">
                            {{-- Timeline node --}}
                            <div class="absolute -left-1 top-1.5 h-2 w-2 rounded-full bg-border border border-surface"></div>
                            <div class="mb-0.5 flex items-center justify-between gap-2">
                                <p class="text-xs font-semibold capitalize text-text-main font-ui">
                                    {{ str_replace('_', ' ', $log->action) }}
                                </p>
                                <span class="text-[9px] text-text-muted font-mono shrink-0">{{ $log->created_at->format('d M Y, H:i') }}</span>
                            </div>
                            <p class="text-[10px] text-text-muted font-ui">Oleh: <span class="font-medium text-text-secondary">{{ $log->user?->name ?? 'System' }}</span></p>
                            
                            @if($log->action === 'cancelled' && isset($log->new_values['cancel_reason']))
                            <div class="mt-1 p-1.5 bg-error-bg/20 border border-error-border rounded">
                                <p class="text-[9px] text-error font-medium font-ui">Alasan: {{ $log->new_values['cancel_reason'] }}</p>
                            </div>
                            @elseif($log->action === 'rejected' && isset($log->new_values['reject_reason']))
                            <div class="mt-1 p-1.5 bg-error-bg/20 border border-error-border rounded">
                                <p class="text-[9px] text-error font-medium font-ui">Alasan: {{ $log->new_values['reject_reason'] }}</p>
                            </div>
                            @elseif($log->action === 'completed' && isset($log->new_values['status']))
                            <div class="mt-0.5 text-[9px] text-success font-medium font-ui">Task ditandai selesai oleh teknisi.</div>
                            @endif
                        </div>
                        @endforeach
                    </div>
                    @else
                    <p class="text-xs text-text-muted font-ui">Belum ada riwayat aktivitas.</p>
                    @endif
                </div>
                @endif
                
            </div>{{-- /right-column --}}
            
        </div>
    </div>

    {{-- ══ Action Buttons ════════════════════════════════════════════ --}}
    @if(in_array($task->status->value, ['terjadwal', 'in_progress', 'pending']))
    <div class="flex flex-wrap items-center justify-end gap-2.5 pt-1.5 font-ui">
        @can('statusReschedule', $task)
        <button type="button" x-data @click="$dispatch('open-modal', 'reschedule-task-{{ $task->id }}')"
                class="inline-flex items-center gap-1.5 text-xs font-semibold px-3.5 py-2 rounded border bg-white dark:bg-slate-800 transition-colors hover:bg-warning/5 cursor-pointer"
                style="border-color:var(--color-warning-border); color:var(--color-warning)">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Pending
        </button>
        @endcan
        @if($task->status->value === 'terjadwal')
            @if($task->scheduled_at && !$task->scheduled_at->startOfDay()->isFuture())
            @if($task->task_type->value === \App\Enums\TaskType::SURVEY->value)
                @if($task->customer_id && auth()->user()->hasPermission('customers.detail.survey.update') && $task->teamMembers->pluck('user_id')->contains(auth()->id()))
                <form action="{{ route('customers.survey.start', $task->customer_id) }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 text-xs font-semibold px-4 py-2 rounded text-white transition-colors cursor-pointer"
                            style="background:var(--color-warning)">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                        </svg>
                        Mulai Survey
                    </button>
                </form>
                @endif
            @elseif($task->task_type->value === \App\Enums\TaskType::PEMASANGAN->value)
                @if($task->customer_id && auth()->user()->hasPermission('customers.detail.installation.update') && $task->teamMembers->pluck('user_id')->contains(auth()->id()))
                <form action="{{ route('customers.installation.start', $task->customer_id) }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 text-xs font-semibold px-4 py-2 rounded text-white transition-colors cursor-pointer"
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
                            class="inline-flex items-center gap-1.5 text-xs font-semibold px-4 py-2 rounded text-white transition-colors cursor-pointer"
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
            <span class="text-xs text-text-muted px-3.5 py-2 border border-border rounded bg-surface">
                Dijadwalkan {{ $task->scheduled_at?->translatedFormat('l, d M Y') }}
            </span>
            @endif
        @endif

        @can('statusComplete', $task)
        @if(in_array($task->status->value, ['in_progress', 'pending']))
            @php
                $reportUrl = match(true) {
                    $task->task_type->value === \App\Enums\TaskType::SURVEY->value => route('customers.survey.report', $task->customer_id),
                    $task->task_type->value === \App\Enums\TaskType::PEMASANGAN->value => route('customers.installation.report', $task->customer_id),
                    default => route('tasks.maintenance.report', $task),
                };
                $reportLabel = match(true) {
                    $task->task_type->value === \App\Enums\TaskType::SURVEY->value => 'Laporan Survey',
                    $task->task_type->value === \App\Enums\TaskType::PEMASANGAN->value => 'Laporan Pemasangan',
                    default => 'Isi Laporan',
                };
            @endphp
            @if($task->status->value === 'in_progress')
                <x-task.report-choice-dialog :task="$task" :report-url="$reportUrl">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ $reportLabel }}
                </x-task.report-choice-dialog>
            @else
                <a href="{{ $reportUrl }}"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold px-4 py-2 rounded text-white transition-colors cursor-pointer"
                        style="background:var(--color-success)">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Lanjutkan Laporan
                </a>
            @endif
        @endif
        @endcan
    </div>
    @endif

    {{-- ══ Action Buttons (FOP Manage) ════════════════════════════════ --}}
    @if(in_array($task->status->value, ['pending', 'terjadwal']))
    @if(auth()->user()->can('fopReject', $task) || auth()->user()->can('fopPending', $task))
    <div class="bg-surface border border-border rounded-lg p-4 flex flex-wrap gap-3 items-center justify-between shadow-sm">
        <div>
            <h4 class="text-xs font-semibold text-text-main mb-0.5 font-ui">Manajemen Task (FOP)</h4>
            <p class="text-[11px] text-text-muted font-ui">Kelola task sebelum mulai dikerjakan oleh teknisi.</p>
        </div>
        <div class="flex items-center gap-2">
            @if($task->status->value === 'pending')
                @can('fopReject', $task)
                <button x-data @click="$dispatch('open-modal', 'fop-reject-task-pending')"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 border bg-white dark:bg-slate-800 transition-colors hover:bg-error/5 cursor-pointer font-ui"
                        style="border-color:var(--color-error-border); color:var(--color-error)">
                    Reject Task
                </button>
                @endcan
            @endif

            @if($task->status->value === 'terjadwal')
                @can('fopPending', $task)
                <button x-data @click="$dispatch('open-modal', 'fop-pending-task')"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 border bg-white dark:bg-slate-800 transition-colors hover:bg-warning/5 cursor-pointer font-ui"
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
    @if($task->task_type->value === 'PSB')
    <div class="bg-surface border border-border rounded-lg p-4 flex flex-wrap gap-3 items-center justify-between shadow-sm">
        <div>
            <h4 class="text-xs font-semibold text-text-main mb-0.5 font-ui font-ui">Approve Pemasangan Lewat Verifikasi Admin</h4>
            <p class="text-[11px] text-text-muted font-ui">Aktivasi pelanggan (CID + tagihan awal) hanya bisa diproses di halaman Verifikasi Admin, bukan dari sini.</p>
        </div>
        @if($task->customer_id)
        <a href="{{ route('customers.verification.admin', $task->customer_id) }}"
           class="inline-flex items-center gap-1.5 text-xs font-semibold px-4 py-2 rounded text-white transition-colors cursor-pointer font-ui"
           style="background:var(--color-primary)">
            Buka Verifikasi Admin
        </a>
        @endif
    </div>
    @else
    <div class="bg-surface border border-border rounded-lg p-4 flex flex-wrap gap-3 items-center justify-between shadow-sm">
        <div>
            <h4 class="text-xs font-semibold text-text-main mb-0.5 font-ui font-ui">Review Hasil & Tandai Selesai (Khusus FOP)</h4>
            <p class="text-[11px] text-text-muted font-ui">Task ini telah diselesaikan oleh teknisi dan menunggu persetujuan Anda.</p>
        </div>
        <div class="flex items-center gap-2">
            <button x-data @click="$dispatch('open-modal', 'reject-task')"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 border bg-white dark:bg-slate-800 transition-colors cursor-pointer font-ui"
                    style="border-color:var(--color-error-border); color:var(--color-error)">
                Reject
            </button>
            <form action="{{ route('tasks.review', $task) }}" method="POST">
                @csrf
                <input type="hidden" name="action" value="approve">
                <button type="submit"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold px-4 py-2 rounded text-white transition-colors cursor-pointer font-ui"
                        style="background:var(--color-primary)">
                    Approve Task
                </button>
            </form>
        </div>
    </div>
    @endif
    @endcan
    @endif
</div>

{{-- ══ FOP Reject Pending Task Modal ═════════════════════════════════ --}}
@can('fopReject', $task)
<x-ui.modal name="fop-reject-task-pending" title="Reject Pending Task" maxWidth="sm">
    <p class="text-xs text-text-secondary mb-3 font-ui">
        Task ini belum dijadwalkan dan akan tetap berstatus <span class="font-semibold text-text-main">Pending</span>, namun dengan keterangan reject.
    </p>
    <form id="form-fop-reject-pending" action="{{ route('tasks.fop-reject', $task) }}" method="POST">
        @csrf
        <div class="space-y-1.5 font-ui">
            <label class="block text-[11px] font-semibold uppercase tracking-wider text-text-muted">Alasan Reject <span class="text-error">*</span></label>
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
    <p class="text-xs text-text-secondary mb-3 font-ui">
        Task ini akan diubah statusnya dari <span class="font-semibold text-text-main">Terjadwal</span> menjadi <span class="font-semibold text-text-main">Pending</span>. Tim teknisi yang sudah di-assign tidak akan terhapus.
    </p>
    <form id="form-fop-pending" action="{{ route('tasks.fop-pending', $task) }}" method="POST">
        @csrf
        <div class="space-y-1.5 font-ui">
            <label class="block text-[11px] font-semibold uppercase tracking-wider text-text-muted">Alasan Pending <span class="text-error">*</span></label>
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


{{-- ══ Pending Top-Level (Reschedule Penuh) Modal — beda dari Set Pending FOP-side & dialog Laporan ═══ --}}
@can('statusReschedule', $task)
<x-ui.modal name="reschedule-task-{{ $task->id }}" title="Pending Task (Reschedule)" maxWidth="sm">
    <p class="text-xs text-text-secondary mb-3 font-ui">
        Task ini akan dilepas dari Anda dan dikembalikan ke antrian Task FOP untuk dijadwalkan ulang ke teknisi/hari lain.
        <span class="font-semibold text-text-main">Assignment Anda pada task ini akan dihapus.</span>
    </p>
    <form id="form-reschedule-{{ $task->id }}" action="{{ route('tasks.reschedule', $task) }}" method="POST">
        @csrf
        <div class="space-y-1.5 font-ui">
            <label class="block text-[11px] font-semibold uppercase tracking-wider text-text-muted">Alasan Pending <span class="text-error">*</span></label>
            <x-ui.textarea name="pending_reason" rows="3" placeholder="Alasan mengapa task ini di-pending/reschedule..." required />
        </div>
    </form>
    <x-slot name="footer">
        <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'reschedule-task-{{ $task->id }}')">
            Batal
        </x-ui.button>
        <x-ui.button type="submit" form="form-reschedule-{{ $task->id }}" variant="warning">
            Pending Task
        </x-ui.button>
    </x-slot>
</x-ui.modal>
@endcan

{{-- ══ FOP Reject Modal ══════════════════════════════════════════════ --}}
@can('review', $task)
<x-ui.modal name="reject-task" title="Reject Laporan Task" maxWidth="sm">
    <p class="text-xs text-text-secondary mb-3 font-ui font-ui">
        Task ini akan dikembalikan ke status <span class="font-semibold text-text-main">In Progress</span>. 
        Teknisi harus memperbaiki laporan berdasarkan alasan reject.
    </p>
    <form id="form-reject-task" action="{{ route('tasks.review', $task) }}" method="POST">
        @csrf
        <input type="hidden" name="action" value="reject">
        <div class="space-y-1.5 font-ui">
            <label class="block text-[11px] font-semibold uppercase tracking-wider text-text-muted font-ui">Alasan Reject <span class="text-error">*</span></label>
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
    <p class="text-xs text-text-secondary mb-3 font-ui font-ui">
        Task <span class="font-mono font-semibold">{{ $task->task_number }}</span> akan dibatalkan.
        Tindakan ini tidak dapat dibatalkan.
    </p>
    <form id="form-cancel-task" action="{{ route('tasks.cancel', $task) }}" method="POST">
        @csrf
        <div class="space-y-1.5 font-ui">
            <label class="block text-[11px] font-semibold uppercase tracking-wider text-text-muted font-ui">Alasan Pembatalan <span class="text-error">*</span></label>
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
</script>
@endpush
@endsection
