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

    {{-- ══ Team FOP Aktif ══════════════════════════════════════════ --}}
    <div>
        <div class="flex items-center justify-between mb-3">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-text-muted">Team FOP Aktif</p>
            <a href="{{ route('fop-tasks.index') }}" class="text-xs text-primary hover:text-primary-hover transition-colors">Kelola Team →</a>
        </div>
        @if($activeFopTeams->count() > 0)
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach($activeFopTeams as $team)
            <div @dragover.prevent="onTeamDragOver({{ $team['id'] }})"
                 @dragleave="onTeamDragLeave({{ $team['id'] }})"
                 @drop.prevent="onTeamDrop({{ $team['id'] }})"
                 :class="dragOverTeamId === {{ $team['id'] }} ? 'ring-2 ring-primary ring-offset-1' : ''"
                 class="flex flex-col bg-white border border-slate-200 rounded shadow-sm overflow-hidden hover:shadow-md hover:border-primary/40 transition-all">
                {{-- Header: nama team + tanggal --}}
                <button type="button" @click="openTeamDetail({{ $team['id'] }})"
                        class="text-left px-4 py-2.5 border-b border-border bg-surface-muted flex items-center justify-between shrink-0 cursor-pointer">
                    <span class="text-xs font-semibold text-text-main">{{ $team['name'] }}</span>
                    <span class="text-[10px] text-text-muted">{{ $team['work_date'] }}</span>
                </button>

                {{-- Body: list task (draggable ke Team lain) --}}
                <div class="p-3 flex-1 space-y-1.5 max-h-44 overflow-y-auto">
                    @forelse($team['tasks'] as $t)
                    <div draggable="{{ $t['draggable'] ? 'true' : 'false' }}"
                         @dragstart="{{ $t['draggable'] ? 'true' : 'false' }} ? startTaskDrag($event, {{ $t['fop_task_id'] }}, {{ $team['id'] }}, @js($t['tugas'])) : $event.preventDefault()"
                         @dragend="endTaskDrag()"
                         @click="openTeamDetail({{ $team['id'] }})"
                         class="flex items-center justify-between text-[11px] bg-surface-muted rounded px-2 py-1.5
                            {{ $t['draggable'] ? 'cursor-grab active:cursor-grabbing' : 'cursor-not-allowed opacity-60' }}">
                        <span class="text-text-main truncate">{{ $t['tugas'] }}</span>
                        <span class="text-[10px] font-medium px-1.5 py-0.5 rounded shrink-0 ml-2"
                            style="{{ $t['status_style'] }}">
                            {{ $t['status'] }}
                        </span>
                    </div>
                    @empty
                    <p class="text-[11px] text-text-muted italic py-2 text-center">Belum ada task</p>
                    @endforelse
                </div>

                {{-- Footer: avatar teknisi + total --}}
                <button type="button" @click="openTeamDetail({{ $team['id'] }})"
                        class="text-left px-3 py-2.5 border-t border-border bg-white flex items-center justify-between shrink-0 cursor-pointer">
                    <div class="flex items-center -space-x-1.5">
                        @foreach($team['members']->take(4) as $member)
                        <span class="h-6 w-6 rounded-full bg-primary border-2 border-white flex items-center justify-center text-[9px] font-bold text-white" title="{{ $member['name'] }}">
                            {{ $member['initials'] }}
                        </span>
                        @endforeach
                        @if($team['members']->count() > 4)
                        <span class="h-6 w-6 rounded-full bg-slate-300 border-2 border-white flex items-center justify-center text-[9px] font-bold text-slate-700">
                            +{{ $team['members']->count() - 4 }}
                        </span>
                        @endif
                    </div>
                    <span class="text-[10px] text-text-muted">{{ $team['members']->count() }} teknisi · {{ $team['total_tasks'] }} task</span>
                </button>
            </div>
            @endforeach
        </div>
        @else
        <div class="bg-white border border-slate-200 rounded shadow-sm flex items-center justify-center py-8 text-text-muted">
            <p class="text-sm">Belum ada team aktif hari ini.</p>
        </div>
        @endif
    </div>

    {{-- ══ TEAM DETAIL MODAL ══ --}}
    <div x-show="teamDetail.open"
         x-effect="document.body.classList.toggle('overflow-hidden', teamDetail.open)"
         class="fixed inset-0 z-50 overflow-y-auto"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         style="display: none;">

        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" @click="teamDetail.open = false"></div>

        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white border border-slate-200 w-full max-w-lg rounded shadow-xl relative z-10"
                 x-show="teamDetail.open"
                 @click.away="teamDetail.open = false"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0">

                <template x-if="teamDetail.data">
                    <div>
                        <div class="px-5 py-3.5 border-b border-slate-200 flex items-center justify-between bg-slate-50 rounded-t">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-800" x-text="teamDetail.data.name"></h3>
                                <p class="text-[11px] text-slate-500" x-text="teamDetail.data.work_date"></p>
                            </div>
                            <button type="button" @click="teamDetail.open = false" class="text-slate-400 hover:text-slate-600 transition-colors">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="p-5 max-h-[70vh] overflow-y-auto space-y-4">
                            {{-- Progress ringkasan --}}
                            <div class="bg-slate-50 border border-slate-200 rounded p-3">
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="text-xs font-semibold text-slate-700">Progress Team</span>
                                    <span class="text-xs font-semibold text-slate-700" x-text="teamDetail.data.completed_tasks + '/' + teamDetail.data.total_tasks + ' selesai'"></span>
                                </div>
                                <div class="h-2 bg-slate-200 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full transition-all"
                                         :style="`width: ${teamDetail.data.progress_percent}%; background: ${teamDetail.data.progress_percent === 100 ? '#16a34a' : '#2563eb'}`">
                                    </div>
                                </div>
                            </div>

                            {{-- Anggota (avatar row) --}}
                            {{-- <div class="flex items-center gap-2 flex-wrap">
                                <template x-for="member in teamDetail.data.members" :key="member.name">
                                    <div class="flex items-center gap-1.5 bg-slate-50 border border-slate-200 rounded-full pl-1 pr-2.5 py-1">
                                        <span class="h-5 w-5 rounded-full bg-blue-600 flex items-center justify-center text-[9px] font-bold text-white" x-text="member.initials"></span>
                                        <span class="text-[11px] font-medium text-slate-700" x-text="member.name"></span>
                                    </div>
                                </template>
                            </div> --}}

                            {{-- List Task: tugas, siapa ngerjain, progress/status --}}
                            <div>
                                <h4 class="text-xs font-semibold text-slate-600 uppercase tracking-wide mb-2">Task dalam Team</h4>
                                <ul class="space-y-3">
                                    <template x-for="t in teamDetail.data.tasks" :key="t.task_number">
                                        <li>
                                            <a :href="t.task_id ? `{{ url('/tasks') }}/${t.task_id}` : '#'"
                                               class="block bg-white border border-slate-200 rounded-lg p-4 hover:border-sky-500 transition-colors shadow-sm cursor-pointer"
                                               :class="!t.task_id ? 'pointer-events-none opacity-60' : ''">
                                                
                                                <!-- Top Row -->
                                                <div class="flex items-start justify-between gap-2 mb-3">
                                                    <!-- Top Left: Badge Kategori -->
                                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wider border font-ui"
                                                          :class="t.badge_classes"
                                                          x-text="t.category_label">
                                                    </span>
                                                    <!-- Top Right: Task ID & Status -->
                                                    <div class="flex items-center gap-1.5 shrink-0">
                                                        <span class="text-[10px] font-mono text-slate-500 font-medium" x-text="t.task_number"></span>
                                                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full border font-ui"
                                                              :style="t.status_style"
                                                              x-text="t.status">
                                                        </span>
                                                    </div>
                                                </div>

                                                <!-- Body: Customer Name -->
                                                <div class="mb-1">
                                                    <h5 class="text-sm font-semibold text-slate-900 font-ui" x-text="t.customer_name"></h5>
                                                </div>

                                                <!-- Body: Customer Address -->
                                                <div class="mb-3">
                                                    <p class="text-xs text-slate-500 font-ui leading-relaxed" x-text="t.customer_address"></p>
                                                </div>

                                                <!-- Footer: Technicians / PIC -->
                                                <div class="pt-2.5 border-t border-slate-100 flex items-center justify-between">
                                                    <div class="flex items-center gap-1.5 text-[10px] text-slate-500 font-ui">
                                                        <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                        </svg>
                                                        <span class="font-medium" x-text="t.technicians.join(', ') || 'Belum ada PIC'"></span>
                                                    </div>
                                                    <span class="text-[10px] font-semibold text-sky-600 font-ui inline-flex items-center gap-0.5">
                                                        Detail Task
                                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                        </svg>
                                                    </span>
                                                </div>

                                            </a>
                                        </li>
                                    </template>
                                    <li x-show="teamDetail.data.tasks.length === 0" class="text-[11px] text-slate-400 italic text-center py-3">Belum ada task</li>
                                </ul>
                            </div>
                        </div>

                        <div class="px-5 py-3.5 border-t border-slate-200 bg-slate-50 flex justify-end rounded-b">
                            <a href="{{ route('fop-tasks.index') }}" class="text-xs font-medium text-primary hover:text-primary-hover transition-colors">Kelola di Task FOP →</a>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- ══ SWITCH TEAM MODAL (drag-drop confirm) ══ --}}
    <div x-show="switchTeamModal.open"
         x-effect="document.body.classList.toggle('overflow-hidden', switchTeamModal.open)"
         class="fixed inset-0 z-50 overflow-y-auto"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         style="display: none;">

        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" @click="switchTeamModal.open = false"></div>

        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white border border-slate-200 w-full max-w-sm rounded shadow-xl relative z-10"
                 @click.away="switchTeamModal.open = false">

                <div class="px-5 py-3.5 border-b border-slate-200 flex items-center justify-between bg-slate-50 rounded-t">
                    <h3 class="text-sm font-semibold text-slate-800">Pindahkan Task ke Team Lain</h3>
                    <button type="button" @click="switchTeamModal.open = false" class="text-slate-400 hover:text-slate-600 transition-colors">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-5 space-y-4">
                    <p class="text-xs text-slate-600">
                        Task <span class="font-semibold" x-text="switchTeamModal.tugas"></span>
                        dipindah dari <span class="font-semibold" x-text="switchTeamModal.fromTeamName"></span>
                        ke <span class="font-semibold" x-text="switchTeamModal.toTeamName"></span>.
                    </p>

                    <div class="relative" x-data="{ openTechDropdown: false }">
                        <label class="block text-[11px] font-semibold text-slate-600 uppercase tracking-wide mb-1.5">
                            Teknisi Pengerjaan di Team Tujuan
                        </label>
                        <div @click="openTechDropdown = true" @click.away="openTechDropdown = false"
                             class="min-h-[38px] w-full border border-slate-300 rounded px-2 py-1.5 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/40 cursor-text flex items-center gap-1.5 flex-wrap">
                            <template x-for="techId in switchTeamModal.technicianIds" :key="techId">
                                <span class="inline-flex items-center gap-1 bg-slate-100 border border-slate-200 text-slate-700 text-xs font-medium px-2 py-0.5 rounded">
                                    <span x-text="switchTeamModal.toTeamMembers.find(m => m.id === techId)?.name"></span>
                                    <button type="button" @click.stop="toggleSwitchTeamTech(techId)" class="hover:text-error transition-colors">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </span>
                            </template>
                            <input type="text" x-model="switchTeamModal.searchTech" @focus="openTechDropdown = true"
                                   placeholder="Cari teknisi..." class="flex-1 min-w-[80px] outline-none text-sm bg-transparent border-none p-0 focus:ring-0">
                        </div>

                        <div x-show="openTechDropdown" class="absolute z-50 w-full bg-white border border-slate-200 rounded shadow-lg mt-1 max-h-40 overflow-y-auto" style="display: none;">
                            <template x-for="member in switchTeamModal.toTeamMembers" :key="member.id">
                                <label class="flex items-center gap-2 px-3 py-2 bg-white hover:bg-slate-50 cursor-pointer border-b border-slate-100 last:border-0"
                                       x-show="switchTeamModal.searchTech === '' || member.name.toLowerCase().includes(switchTeamModal.searchTech.toLowerCase())">
                                    <input type="checkbox"
                                           :checked="switchTeamModal.technicianIds.includes(member.id)"
                                           @change="toggleSwitchTeamTech(member.id)"
                                           class="w-4 h-4 rounded border-slate-300 text-primary focus:ring-primary">
                                    <span class="text-sm text-slate-700" x-text="member.name"></span>
                                </label>
                            </template>
                            <p x-show="switchTeamModal.toTeamMembers.length === 0" class="px-3 py-2 text-xs text-slate-400 italic">Team tujuan belum punya anggota.</p>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1">Hanya menampilkan anggota Team tujuan — hindari konflik teknisi dari team lain. Teknisi lama pada task ini otomatis dilepas, digantikan pilihan di atas.</p>
                    </div>
                </div>

                <div class="px-5 py-3.5 border-t border-slate-200 bg-slate-50 flex justify-end gap-2 rounded-b">
                    <button type="button" @click="switchTeamModal.open = false"
                            class="text-xs font-medium px-3 py-1.5 rounded border border-slate-300 text-slate-600 hover:bg-slate-100 transition-colors">
                        Batal
                    </button>
                    <button type="button" @click="submitSwitchTeam()"
                            :disabled="switchTeamModal.technicianIds.length === 0 || switchTeamModal.isSubmitting"
                            class="text-xs font-medium px-3 py-1.5 rounded bg-primary text-white hover:bg-primary-hover transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        <span x-show="!switchTeamModal.isSubmitting">Konfirmasi Pindah</span>
                        <span x-show="switchTeamModal.isSubmitting">Memproses...</span>
                    </button>
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

</div>

@push('scripts')
<script>
function fopDashboardHandler() {
    return {
        teamsData: @json($activeFopTeams),
        teamDetail: { open: false, data: null },
        dragOverTeamId: null,
        dragging: null,
        switchTeamModal: {
            open: false,
            fopTaskId: null,
            fromTeamId: null,
            fromTeamName: '',
            toTeamId: null,
            toTeamName: '',
            toTeamMembers: [],
            tugas: '',
            technicianIds: [],
            searchTech: '',
            isSubmitting: false,
        },

        openTeamDetail(id) {
            this.teamDetail.data = this.teamsData.find(t => t.id === id) || null;
            this.teamDetail.open = true;
        },

        // ── Drag & Drop: Switch Task antar Team ─────────────────────
        startTaskDrag(event, fopTaskId, fromTeamId, tugas) {
            this.dragging = { fopTaskId, fromTeamId, tugas };
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', String(fopTaskId));
        },

        endTaskDrag() {
            this.dragging = null;
            this.dragOverTeamId = null;
        },

        onTeamDragOver(teamId) {
            if (!this.dragging || this.dragging.fromTeamId === teamId) return;
            this.dragOverTeamId = teamId;
        },

        onTeamDragLeave(teamId) {
            if (this.dragOverTeamId === teamId) this.dragOverTeamId = null;
        },

        onTeamDrop(toTeamId) {
            this.dragOverTeamId = null;
            if (!this.dragging || this.dragging.fromTeamId === toTeamId) {
                this.dragging = null;
                return;
            }

            const fromTeam = this.teamsData.find(t => t.id === this.dragging.fromTeamId);
            const toTeam = this.teamsData.find(t => t.id === toTeamId);
            if (!toTeam) {
                this.dragging = null;
                return;
            }

            this.switchTeamModal = {
                open: true,
                fopTaskId: this.dragging.fopTaskId,
                fromTeamId: this.dragging.fromTeamId,
                fromTeamName: fromTeam ? fromTeam.name : '',
                toTeamId: toTeam.id,
                toTeamName: toTeam.name,
                toTeamMembers: toTeam.members,
                tugas: this.dragging.tugas,
                technicianIds: [],
                searchTech: '',
                isSubmitting: false,
            };
            this.dragging = null;
        },

        toggleSwitchTeamTech(id) {
            const index = this.switchTeamModal.technicianIds.indexOf(id);
            if (index > -1) {
                this.switchTeamModal.technicianIds.splice(index, 1);
            } else {
                this.switchTeamModal.technicianIds.push(id);
                this.switchTeamModal.searchTech = '';
            }
        },

        submitSwitchTeam() {
            if (this.switchTeamModal.technicianIds.length === 0) return;
            this.switchTeamModal.isSubmitting = true;
            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            fetch(`{{ url('/fop-tasks') }}/${this.switchTeamModal.fopTaskId}/switch-team`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token
                },
                body: JSON.stringify({
                    to_team_id: this.switchTeamModal.toTeamId,
                    technician_ids: this.switchTeamModal.technicianIds,
                })
            })
            .then(res => res.json())
            .then(data => {
                this.switchTeamModal.isSubmitting = false;
                if (data.success) {
                    this.showToast('success', data.message);
                    this.switchTeamModal.open = false;
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    this.showToast('error', data.message || 'Gagal memindahkan task.');
                }
            })
            .catch(() => {
                this.switchTeamModal.isSubmitting = false;
                this.showToast('error', 'Terjadi kesalahan jaringan.');
            });
        },

        showToast(type, message) {
            if (window.Toast) {
                if (type === 'success') window.Toast.success('Berhasil', message);
                else if (type === 'error') window.Toast.error('Gagal', message);
                else if (type === 'warning') window.Toast.warning('Peringatan', message);
                else window.Toast.info('Informasi', message);
            } else {
                alert(message);
            }
        },

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
                            this.refreshTaskStats();
                        })
                        .listen('TaskCompleted', (e) => {
                            this.refreshTaskStats();
                        })
                        .listen('SurveyStarted',          () => this.refreshDashboardContainers())
                        .listen('SurveyCompleted',        () => this.refreshDashboardContainers())
                        .listen('InstallationStarted',    () => this.refreshDashboardContainers())
                        .listen('InstallationCompleted',  () => this.refreshDashboardContainers());
                });
            };
            setup();
        },

        async refreshTaskStats() {
            try {
                const res = await fetch(window.location.href);
                const html = await res.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                
                ['stat-cards-container', 'status-teknisi-container'].forEach(id => {
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

                ['stat-cards-container', 'antrian-survey-container', 'status-teknisi-container'].forEach(id => {
                    const el = document.getElementById(id);
                    const newEl = doc.getElementById(id);
                    if (el && newEl) el.innerHTML = newEl.innerHTML;
                });
            } catch (e) {
                console.error('Auto-refresh error:', e);
            }
        },

    };
}
</script>
@endpush
@endsection
