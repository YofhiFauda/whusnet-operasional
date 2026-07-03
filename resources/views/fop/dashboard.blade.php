@extends('layouts.app')

@section('title', 'FOP Dashboard')

@section('content')
<div x-data="fopDashboardHandler()" class="flex flex-col gap-5 px-4 py-6 max-w-screen-2xl mx-auto">

    {{-- ══ Page Header ══════════════════════════════════════════════ --}}
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <svg class="h-5 w-5 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
            </svg>
            <div>
                <h1 class="text-base font-semibold text-text-main leading-tight">FOP Dashboard</h1>
                <p class="text-xs text-text-muted">{{ now()->translatedFormat('l, d F Y') }}</p>
            </div>
        </div>
        @can('create', \App\Models\Task::class)
        <a href="{{ route('tasks.create') }}"
           class="inline-flex items-center gap-1.5 bg-primary hover:bg-primary-hover text-white text-xs font-semibold px-3 py-1.5 rounded-md transition-colors">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            + Jadwal Task
        </a>
        @endcan
    </div>

    {{-- ══ Stat Cards ═══════════════════════════════════════════════ --}}
    <div id="stat-cards-container" class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white border border-slate-200 rounded px-4 py-3 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-text-muted">Antrean Survey</p>
            <div class="flex items-center gap-2 mt-1">
                <p class="text-2xl font-bold font-mono text-text-main">{{ $stats['antrian_survey'] }}</p>
                @if(($stats['overdue_survey'] ?? 0) > 0)
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold" style="background:var(--color-error-bg); color:var(--color-error); border:1px solid var(--color-error-border)">
                        {{ $stats['overdue_survey'] }} Terlambat
                    </span>
                @endif
            </div>
            <p class="text-[11px] text-text-muted mt-0.5">Belum disurvey</p>
        </div>
        <div class="bg-white border border-slate-200 rounded px-4 py-3 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-text-muted">Perlu Aksi FOP</p>
            <div class="flex items-center gap-2 mt-1">
                <p class="text-2xl font-bold font-mono" style="color:var(--color-warning)">{{ $stats['perlu_aksi_fop'] }}</p>
                @if(($stats['overdue_installation'] ?? 0) > 0)
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold" style="background:var(--color-error-bg); color:var(--color-error); border:1px solid var(--color-error-border)">
                        {{ $stats['overdue_installation'] }} Terlambat
                    </span>
                @endif
            </div>
            <p class="text-[11px] text-text-muted mt-0.5">Menunggu verifikasi</p>
        </div>
        <div class="bg-white border border-slate-200 rounded px-4 py-3 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-text-muted">Sedang Berjalan</p>
            <p class="text-2xl font-bold font-mono mt-1" style="color:var(--color-info)">{{ $stats['berjalan'] }}</p>
            <p class="text-[11px] text-text-muted mt-0.5">Task aktif hari ini</p>
        </div>
        <div class="bg-white border border-slate-200 rounded px-4 py-3 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-text-muted">Selesai Hari Ini</p>
            <p class="text-2xl font-bold font-mono mt-1" style="color:var(--color-success)">{{ $stats['selesai_hari_ini'] }}</p>
            <p class="text-[11px] text-text-muted mt-0.5">Task selesai</p>
        </div>
    </div>

    {{-- ══ Kanban Pipeline ══════════════════════════════════════════ --}}
    <div>
        <p class="text-[11px] font-semibold uppercase tracking-widest text-text-muted mb-3">Pipeline Task Hari Ini</p>
        <div id="kanban-pipeline-container" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">

            {{-- Kolom 0: Antrean Tiket (Belum Dijadwalkan) --}}
            <div class="bg-white border border-slate-200 rounded shadow-sm overflow-hidden">
                <div class="px-4 py-2.5 border-b border-border flex items-center justify-between bg-surface-muted">
                    <span class="text-xs font-semibold text-text-secondary">Antrean Tiket</span>
                    <span class="text-xs font-mono font-semibold text-text-secondary">
                        {{ $kanban['antrean']->count() }}
                    </span>
                </div>
                <div class="p-3 space-y-2 min-h-32">
                    @forelse($kanban['antrean'] as $task)
                        <div class="bg-background border border-border rounded-md px-3 py-2.5 hover:shadow-sm transition-all">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-mono font-semibold px-1.5 py-0.5 rounded bg-surface-muted text-text-secondary">{{ $task->task_number }}</span>
                                <span class="text-[10px] font-semibold text-primary uppercase">{{ $task->task_type }}</span>
                            </div>
                            <p class="text-xs font-semibold text-text-main mt-1.5 truncate">{{ $task->title }}</p>
                            @if($task->customer)
                                <p class="text-[11px] text-text-muted mt-0.5 truncate">{{ $task->customer->full_name }}</p>
                            @endif
                            <p class="text-[11px] text-text-muted">{{ $task->pop?->name ?? '—' }}</p>
                            
                            <div class="mt-3 pt-2 border-t border-border flex justify-end">
                                <button type="button"
                                        @click="openScheduleTicket({{ $task->id }}, '{{ addslashes($task->title) }}', '{{ $task->task_number }}', '{{ $task->task_type }}')"
                                        class="inline-flex items-center gap-1 bg-primary hover:bg-primary-hover text-white text-[11px] font-semibold px-2.5 py-1 rounded transition-colors">
                                    Jadwalkan & Tugaskan
                                </button>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-text-muted text-center py-4">Tidak ada antrean tiket</p>
                    @endforelse
                </div>
            </div>

            {{-- Kolom 1: Terjadwal --}}
            <div class="bg-white border border-slate-200 rounded shadow-sm overflow-hidden">
                <div class="px-4 py-2.5 border-b border-border flex items-center justify-between"
                     style="background:var(--color-info-bg)">
                    <span class="text-xs font-semibold" style="color:var(--color-info)">Terjadwal</span>
                    <span class="text-xs font-mono font-semibold" style="color:var(--color-info)">
                        {{ $kanban['terjadwal']->count() }}
                    </span>
                </div>
                <div class="p-3 space-y-2 min-h-32">
                    @forelse($kanban['terjadwal'] as $task)
                        <x-task-card :task="$task" displayMode="kanban" :showCountdown="false" />
                    @empty
                        <p class="text-xs text-text-muted text-center py-4">Tidak ada task terjadwal</p>
                    @endforelse
                </div>
            </div>

            {{-- Kolom 2: Sedang Berjalan — aktifkan countdown SLA Eksekusi --}}
            <div class="bg-white border border-slate-200 rounded shadow-sm overflow-hidden">
                <div class="px-4 py-2.5 border-b border-border flex items-center justify-between"
                     style="background:var(--color-warning-bg)">
                    <span class="text-xs font-semibold" style="color:var(--color-warning)">Sedang Berjalan</span>
                    <span class="text-xs font-mono font-semibold" style="color:var(--color-warning)">
                        {{ $kanban['berjalan']->count() }}
                    </span>
                </div>
                <div class="p-3 space-y-2 min-h-32">
                    @forelse($kanban['berjalan'] as $task)
                        <x-task-card :task="$task" displayMode="kanban" :showCountdown="true" />
                    @empty
                        <p class="text-xs text-text-muted text-center py-4">Tidak ada task berjalan</p>
                    @endforelse
                </div>
            </div>

            {{-- Kolom 3: Perlu Aksi FOP — countdown Verifikasi 3×24 jam --}}
            <div class="bg-white border border-slate-200 rounded shadow-sm overflow-hidden">
                <div class="px-4 py-2.5 border-b border-border flex items-center justify-between"
                     style="background:#FFF7ED; border-bottom-color:#FDBA74">
                    <span class="text-xs font-semibold" style="color:#C2410C">Perlu Aksi FOP</span>
                    <span class="text-xs font-mono font-semibold" style="color:#C2410C">
                        {{ $kanban['perlu_aksi_fop']->count() }}
                    </span>
                </div>
                <div class="p-3 space-y-2 min-h-32">
                    @forelse($kanban['perlu_aksi_fop'] as $item)
                    <div class="bg-background border border-border rounded-md px-3 py-2.5 hover:shadow-sm transition-all">
                        {{-- Nama pelanggan --}}
                        <p class="text-xs font-semibold text-text-main truncate">{{ $item['name'] }}</p>
                        <p class="text-[11px] font-mono text-text-muted">{{ $item['cid'] }}</p>
                        <p class="text-[11px] text-text-muted mt-0.5">{{ $item['pop_name'] }}</p>
                        {{-- Survey selesai --}}
                        @if($item['survey_completed_at'])
                        <p class="text-[11px] text-text-muted mt-1">
                            Survey selesai: {{ $item['survey_completed_at'] }}
                        </p>
                        @endif
                        {{-- Countdown Verifikasi 3×24 jam --}}
                        <div class="mt-2">
                            @if($item['verif_deadline_iso'])
                                <x-countdown-timer
                                    deadline="{{ $item['verif_deadline_iso'] }}"
                                    :total-seconds="$item['verif_total_seconds']"
                                    label="Sisa Verifikasi"
                                />
                            @else
                                <span class="text-[11px] text-text-muted">Tanggal survey tidak tersedia</span>
                            @endif
                        </div>
                        {{-- Tombol aksi --}}
                        <div class="flex gap-1.5 mt-2.5">
                            <a href="{{ route('customers.show', $item['id']) }}"
                               class="w-full text-center text-[11px] font-medium py-1.5 px-2 border border-border rounded-md bg-surface hover:bg-surface-muted text-text-secondary transition-colors">
                                Detail Pelanggan
                            </a>
                        </div>
                    </div>
                    @empty
                        <p class="text-xs text-text-muted text-center py-4">Tidak ada item perlu aksi</p>
                    @endforelse
                </div>
            </div>

            {{-- Kolom 4: Selesai --}}
            <div class="bg-white border border-slate-200 rounded shadow-sm overflow-hidden">
                <div class="px-4 py-2.5 border-b border-border flex items-center justify-between"
                     style="background:var(--color-success-bg)">
                    <span class="text-xs font-semibold" style="color:var(--color-success)">Selesai</span>
                    <span class="text-xs font-mono font-semibold" style="color:var(--color-success)">
                        {{ $kanban['selesai']->count() }}
                    </span>
                </div>
                <div class="p-3 space-y-2 min-h-32">
                    @forelse($kanban['selesai'] as $task)
                        <x-task-card :task="$task" displayMode="kanban" :showCountdown="false" />
                    @empty
                        <p class="text-xs text-text-muted text-center py-4">Tidak ada task selesai</p>
                    @endforelse
                </div>
            </div>

        </div>
    </div>

    {{-- ══ Antrean Survey ═══════════════════════════════════════════ --}}
    <div>
        <div class="flex items-center justify-between mb-3">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-text-muted">Antrean Survey</p>
            <span class="text-xs text-text-muted">Hitung mundur 1×24 jam sejak registrasi</span>
        </div>
        <div id="antrian-survey-container" class="bg-white border border-slate-200 rounded shadow-sm overflow-hidden">
            @if($surveyQueue->count() > 0)
            <table class="w-full text-[11px]">
                <thead class="bg-surface-muted">
                    <tr>
                        <th class="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-widest text-text-muted">Pelanggan</th>
                        <th class="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-widest text-text-muted">POP</th>
                        <th class="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-widest text-text-muted">Terdaftar</th>
                        <th class="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-widest text-text-muted">
                            Sisa Waktu Survey
                        </th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($surveyQueue as $item)
                    <tr class="hover:bg-surface-muted transition-colors">
                        <td class="px-3 py-2">
                            <p class="font-medium text-text-main">{{ $item['name'] }}</p>
                            <p class="text-[10px] font-mono text-text-muted">{{ $item['cid'] }}</p>
                        </td>
                        <td class="px-3 py-2 text-text-secondary text-[11px]">{{ $item['pop_name'] }}</td>
                        <td class="px-3 py-2 text-text-muted text-[11px]">{{ $item['registered_at'] }}</td>
                        <td class="px-3 py-2">
                            {{-- Countdown Survey 1×24 jam — aktif --}}
                            <x-countdown-timer
                                deadline="{{ $item['deadline_iso'] }}"
                                :total-seconds="$item['total_seconds']"
                                label="Sisa Survey"
                            />
                        </td>
                        <td class="px-3 py-2 text-right">
                            <a href="{{ route('customers.show', $item['id']) }}"
                               class="text-xs font-medium px-2.5 py-1 border border-border rounded-md bg-surface hover:bg-surface-muted text-text-secondary transition-colors">
                                Detail
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @else
            <div class="flex items-center justify-center py-10 text-text-muted">
                <p class="text-sm">Tidak ada pelanggan dalam antrean survey.</p>
            </div>
            @endif
        </div>
    </div>

    {{-- ══ Daftar Tim Gabungan / Otomatis ════════════════════════════ --}}
    <div>
        <div class="flex items-center justify-between mb-3">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-text-muted">Daftar Tim Gabungan (Hari Ini)</p>
            <span class="text-xs text-text-muted">Menampilkan task aktif dengan >1 teknisi</span>
        </div>
        <div id="tim-gabungan-container" class="bg-white border border-slate-200 rounded shadow-sm overflow-hidden mb-6">
            @if(isset($activeTeams) && $activeTeams->count() > 0)
            <table class="w-full text-[11px]">
                <thead class="bg-surface-muted">
                    <tr>
                        <th class="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-widest text-text-muted">Nama Tim</th>
                        <th class="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-widest text-text-muted">Anggota Tim</th>
                        <th class="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-widest text-text-muted">Tugas (Task)</th>
                        <th class="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-widest text-text-muted">Alamat Penugasan</th>
                        <th class="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-widest text-text-muted">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($activeTeams as $team)
                    <tr class="hover:bg-surface-muted transition-colors">
                        <td class="px-3 py-2">
                            <span class="font-bold text-primary bg-primary-soft px-2 py-1 rounded-md">{{ $team['team_name'] }}</span>
                        </td>
                        <td class="px-3 py-2">
                            <ul class="list-disc list-inside text-text-main">
                                @foreach($team['members'] as $member)
                                    <li>{{ $member }}</li>
                                @endforeach
                            </ul>
                        </td>
                        <td class="px-3 py-2">
                            <p class="font-medium text-text-main">{{ $team['task_title'] }}</p>
                            <span class="text-[10px] uppercase font-bold text-text-muted">{{ $team['task_type'] }}</span>
                        </td>
                        <td class="px-3 py-2 text-text-secondary truncate max-w-[200px]" title="{{ $team['address'] }}">
                            {{ Str::limit($team['address'], 40) }}
                        </td>
                        <td class="px-3 py-2">
                            <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-1 rounded-full"
                                  style="background:var(--color-{{ $team['status_color'] }}-bg); color:var(--color-{{ $team['status_color'] }}); border:1px solid var(--color-{{ $team['status_color'] }}-border)">
                                {{ $team['status'] }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @else
            <div class="flex items-center justify-center py-6 text-text-muted">
                <p class="text-sm">Tidak ada Tim Gabungan yang aktif/terjadwal hari ini.</p>
            </div>
            @endif
        </div>
    </div>

    {{-- ══ Status Teknisi ═══════════════════════════════════════════ --}}
    {{-- Real-time via Reverb akan ditambahkan di S8.2-T009 --}}
    <div>
        <div class="flex items-center justify-between mb-3">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-text-muted">Status Teknisi</p>
            <button onclick="window.location.reload();" 
                    class="inline-flex items-center gap-1 text-[10px] font-semibold uppercase tracking-widest text-primary hover:text-primary-hover transition-colors">
                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H17" />
                </svg>
                Refresh
            </button>
        </div>
        <div id="status-teknisi-container" class="bg-white border border-slate-200 rounded shadow-sm overflow-hidden">
            @if($teknisiList->count() > 0)
            <table class="w-full text-[11px]">
                <thead class="bg-surface-muted">
                    <tr>
                        <th class="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-widest text-text-muted">Teknisi</th>
                        <th class="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-widest text-text-muted">Status</th>
                        <th class="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-widest text-text-muted">Task Aktif Hari Ini</th>
                        <th class="px-3 py-2 text-left text-[10px] font-semibold uppercase tracking-widest text-text-muted">Lokasi Terakhir</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($teknisiList as $tek)
                    <tr class="hover:bg-surface-muted transition-colors">
                        <td class="px-3 py-2">
                            <div class="flex items-center gap-2">
                                <div class="h-6 w-6 rounded-full bg-primary-soft flex items-center justify-center text-[10px] font-bold shrink-0"
                                     style="color:var(--color-primary)">
                                    {{ $tek['initials'] }}
                                </div>
                                <span class="font-medium text-text-main">{{ $tek['name'] }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-2">
                            @if($tek['status'] === 'aktif')
                                <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-1.5 py-0.5 rounded-full"
                                      style="background:var(--color-success-bg); color:var(--color-success); border:1px solid var(--color-success-border)">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse"></span>
                                    Aktif
                                </span>
                            @elseif($tek['status'] === 'terjadwal')
                                <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-1.5 py-0.5 rounded-full"
                                      style="background:var(--color-info-bg); color:var(--color-info); border:1px solid var(--color-info-border)">
                                    Terjadwal
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded-full border border-border text-text-muted">
                                    Standby
                                </span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-[11px] font-mono text-text-secondary">
                            {{ $tek['task_count'] }} task
                        </td>
                        <td class="px-3 py-2 text-[11px] text-text-secondary">
                            {{ $tek['location'] }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @else
            <div class="flex items-center justify-center py-10 text-text-muted">
                <p class="text-sm">Tidak ada teknisi di wilayah Anda.</p>
            </div>
            @endif
        </div>
    </div>

    {{-- Slide-Over Drawer Penjadwalan Tiket Antrean --}}
    <x-ui.drawer name="schedule-ticket" title="Jadwalkan & Tugaskan Tiket" maxWidth="md">
        <form x-ref="scheduleForm" :action="'/tasks/' + scheduleTicket.id + '/schedule'" method="POST" @submit.prevent="submitScheduleForm($el)">
            @csrf
            
            {{-- Ticket Info --}}
            <div class="mb-5 bg-surface-muted border border-border rounded-lg p-4">
                <h3 class="text-xs font-semibold text-text-main uppercase tracking-widest mb-1.5">Informasi Tiket</h3>
                <p class="text-sm font-bold text-text-main" x-text="scheduleTicket.title"></p>
                <div class="flex gap-2 items-center mt-1 text-xs text-text-muted">
                    <span class="font-mono bg-background border border-border px-1.5 py-0.5 rounded" x-text="scheduleTicket.number"></span>
                    <span class="uppercase font-semibold text-primary" x-text="scheduleTicket.type"></span>
                </div>
            </div>

            {{-- Assign technicians (1-3) --}}
            <div class="mb-5">
                <label class="block text-xs font-semibold text-text-secondary uppercase tracking-widest mb-2">
                    Pilih Anggota Tim Teknisi (1–3 Orang) <span style="color:var(--color-error)">*</span>
                </label>
                <div class="space-y-2 max-h-40 overflow-y-auto border border-border rounded-md p-3 bg-background">
                    @foreach($teknisiList as $tek)
                    <label class="flex items-center gap-2.5 cursor-pointer hover:bg-surface p-1 rounded transition-colors">
                        <input type="checkbox" name="team_member_ids[]" value="{{ $tek['id'] }}" 
                               x-model="scheduleTicket.selectedTeknisi"
                               @change="checkScheduleConflicts()"
                               class="h-4 w-4 rounded border-border text-primary focus:ring-primary">
                        <span class="text-xs font-medium text-text-main">{{ $tek['name'] }}</span>
                    </label>
                    @endforeach
                </div>
                <p class="text-[11px] text-text-muted mt-1">Centang 1 sampai 3 teknisi yang akan bertugas bersama.</p>
            </div>

            {{-- Scheduled date & time --}}
            <div class="mb-5">
                <label for="schedule_time" class="block text-xs font-semibold text-text-secondary uppercase tracking-widest mb-2">
                    Jadwal Pelaksanaan <span style="color:var(--color-error)">*</span>
                </label>
                <input type="datetime-local" name="scheduled_at" id="schedule_time" required
                       x-model="scheduleTicket.scheduledAt"
                       @change="checkScheduleConflicts()"
                       class="w-full border border-border rounded-md px-3 py-2 text-sm text-text-main bg-background focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition">
            </div>

            {{-- Conflict warning --}}
            <div x-show="scheduleConflicts.length > 0"
                 class="mb-4 px-3 py-2.5 rounded-md border text-xs"
                 style="background:var(--color-warning-bg); border-color:var(--color-warning-border); color:var(--color-warning)">
                <p class="font-semibold mb-1">⚠ Konflik jadwal terdeteksi</p>
                <p class="text-text-secondary">Teknisi berikut sudah punya task aktif pada waktu yang sama:</p>
                <ul class="mt-1 space-y-0.5">
                    <template x-for="u in scheduleConflicts" :key="u.id">
                        <li class="font-medium" x-text="'• ' + u.name"></li>
                    </template>
                </ul>
                @can('conflictOverride', \App\Models\Task::class)
                <label class="flex items-center gap-2 mt-2.5 cursor-pointer">
                    <input type="checkbox" name="conflict_override" value="1" x-model="scheduleOverride" class="h-3.5 w-3.5 rounded" style="accent-color:var(--color-warning)">
                    <span class="font-semibold">Override konflik jadwal</span>
                </label>
                @else
                <p class="mt-1.5 font-medium" style="color:var(--color-error)">Anda tidak punya izin override konflik.</p>
                @endcan
            </div>

            <div class="border-t border-border pt-4 bg-surface flex gap-2 justify-end">
                <button type="button" @click="$dispatch('close-drawer', 'schedule-ticket')"
                        class="px-4 py-2 border border-border rounded-md text-xs font-semibold text-text-secondary hover:bg-surface-muted transition-colors">
                    Batal
                </button>
                <button type="submit" :disabled="scheduling || scheduleTicket.selectedTeknisi.length < 1 || scheduleTicket.selectedTeknisi.length > 3 || !scheduleTicket.scheduledAt || (scheduleConflicts.length > 0 && !scheduleOverride)"
                        :class="scheduling || scheduleTicket.selectedTeknisi.length < 1 || scheduleTicket.selectedTeknisi.length > 3 || !scheduleTicket.scheduledAt || (scheduleConflicts.length > 0 && !scheduleOverride) ? 'opacity-50 cursor-not-allowed' : ''"
                        class="px-4 py-2 bg-primary hover:bg-primary-hover text-white text-xs font-semibold rounded-md transition-colors leading-none flex items-center justify-center">
                    <span x-text="scheduling ? 'Menjadwalkan...' : 'Konfirmasi Jadwal'"></span>
                </button>
            </div>
        </form>
    </x-ui.drawer>

</div>

@push('scripts')
<script>
function fopDashboardHandler() {
    return {
        scheduleTicket: {
            id: null,
            title: '',
            number: '',
            type: '',
            selectedTeknisi: [],
            scheduledAt: '',
        },
        scheduleConflicts: [],
        scheduleOverride: false,
        scheduling: false,

        init() {
            this.initEchoListeners();
        },

        initEchoListeners() {
            const popIds = @json($pops->pluck('id'));
            let attempts = 0;
            const setup = () => {
                if (typeof window.Echo === 'undefined' || !window.Echo) {
                    attempts++;
                    if (attempts < 20) setTimeout(setup, 100);
                    return;
                }
                popIds.forEach(popId => {
                    window.Echo.private(`fop.${popId}`)
                        .listen('TaskStarted', (e) => {
                            this.refreshKanbanPipeline();
                        })
                        .listen('TaskCompleted', (e) => {
                            this.refreshKanbanPipeline();
                        })
                        .listen('SurveyStarted',          () => this.refreshDashboardContainers())
                        .listen('SurveyCompleted',        () => this.refreshDashboardContainers())
                        .listen('InstallationStarted',    () => this.refreshDashboardContainers())
                        .listen('InstallationCompleted',  () => this.refreshDashboardContainers());
                });
            };
            setup();
        },

        async refreshKanbanPipeline() {
            try {
                const res = await fetch(window.location.href);
                const html = await res.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                
                ['kanban-pipeline-container', 'stat-cards-container', 'status-teknisi-container'].forEach(id => {
                    const el = document.getElementById(id);
                    const newEl = doc.getElementById(id);
                    if (el && newEl) el.innerHTML = newEl.innerHTML;
                });
            } catch (e) {
                console.error('Auto-refresh error:', e);
            }
        },

        async refreshDashboardContainers() {
            try {
                const res = await fetch(window.location.href);
                const html = await res.text();
                const doc = new DOMParser().parseFromString(html, 'text/html');

                ['stat-cards-container', 'kanban-pipeline-container', 'antrian-survey-container', 'tim-gabungan-container', 'status-teknisi-container'].forEach(id => {
                    const el = document.getElementById(id);
                    const newEl = doc.getElementById(id);
                    if (el && newEl) el.innerHTML = newEl.innerHTML;
                });
            } catch (e) {
                console.error('Auto-refresh error:', e);
            }
        },

        openScheduleTicket(id, title, number, type) {
            this.scheduleTicket = {
                id: id,
                title: title,
                number: number,
                type: type,
                selectedTeknisi: [],
                scheduledAt: '',
            };
            this.scheduleConflicts = [];
            this.scheduleOverride = false;
            this.scheduling = false;
            this.$dispatch('open-drawer', 'schedule-ticket');
        },

        async checkScheduleConflicts() {
            if (!this.scheduleTicket.scheduledAt || this.scheduleTicket.selectedTeknisi.length === 0) {
                this.scheduleConflicts = [];
                return;
            }
            try {
                const res = await fetch('/api/tasks/check-conflict', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        user_ids:     this.scheduleTicket.selectedTeknisi,
                        scheduled_at: this.scheduleTicket.scheduledAt,
                        task_type:    this.scheduleTicket.type,
                    }),
                });
                const data = await res.json();
                this.scheduleConflicts = data.conflict_users || [];
                if (!data.has_conflict) this.scheduleOverride = false;
            } catch (e) { /* silent */ }
        },

        submitScheduleForm(form) {
            if (this.scheduleConflicts.length > 0 && !this.scheduleOverride) return;
            if (this.scheduleTicket.selectedTeknisi.length < 1 || this.scheduleTicket.selectedTeknisi.length > 3) {
                window.Alert?.('Error', 'Pilih minimal 1 dan maksimal 3 teknisi.', 'error');
                return;
            }
            this.scheduling = true;
            form.submit();
        }
    };
}
</script>
@endpush
@endsection
