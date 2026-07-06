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

</div>

@push('scripts')
<script>
function fopDashboardHandler() {
    return {
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

                ['stat-cards-container', 'antrian-survey-container', 'tim-gabungan-container', 'status-teknisi-container'].forEach(id => {
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
