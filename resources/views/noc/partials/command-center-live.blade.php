{{-- ══ Tab 1: Live Command Center (Realtime Shift Monitoring & Task Audit) ══ --}}
<div class="space-y-6">

    {{-- Cluster / Mass Outage Alert Banner (Pendeteksi Gangguan Massal Otomatis) --}}
    @if(!empty($liveTelemetry['cluster_alerts']))
        <div class="space-y-2">
            @foreach($liveTelemetry['cluster_alerts'] as $alert)
                <div class="p-4 rounded-xl border border-rose-500/40 bg-rose-500/10 dark:bg-rose-950/30 text-rose-700 dark:text-rose-300 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-3 animate-pulse">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-rose-500 text-white flex items-center justify-center shrink-0 shadow-sm">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-rose-600 text-white">
                                    INDIKASI GANGGUAN MASSAL
                                </span>
                                <span class="text-xs font-mono font-bold text-rose-800 dark:text-rose-200">
                                    {{ $alert['count'] }} Tiket Terbuka dalam 90 Menit Terakhir
                                </span>
                            </div>
                            <p class="text-xs font-semibold text-rose-900 dark:text-rose-100 mt-0.5">
                                Konsentrasi gangguan terdeteksi di area <span class="underline font-bold">{{ $alert['location'] }}</span>.
                                @if(!empty($alert['issues']))
                                    (Issue: {{ implode(', ', $alert['issues']) }})
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="shrink-0 flex items-center gap-2">
                        <span class="text-[11px] font-mono text-rose-700 dark:text-rose-300">Pembaruan: {{ $alert['latest_time'] }} WIB</span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Live Telemetry Strip: 5 Hero Metric Tiles --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3.5">
        {{-- Total Tiket Aktif NOC --}}
        <div class="bg-surface border border-border rounded-xl p-4 shadow-xs relative overflow-hidden group hover:border-amber-500/50 transition-all">
            <div class="flex items-center justify-between text-text-muted">
                <span class="text-[10px] font-extrabold uppercase tracking-wider">Antrean Aktif NOC</span>
                <div class="w-7 h-7 rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            <p class="text-2xl font-black mt-2 text-text-main font-mono">{{ number_format($liveTelemetry['open_noc_count']) }}</p>
            <div class="flex items-center gap-1.5 mt-2 flex-wrap text-[10px]">
                @if($liveTelemetry['priority_counts']['urgent'] > 0)
                    <span class="px-1.5 py-0.5 rounded font-bold bg-rose-500/15 text-rose-600 dark:text-rose-400 border border-rose-500/30">
                        {{ $liveTelemetry['priority_counts']['urgent'] }} Urgent
                    </span>
                @endif
                @if($liveTelemetry['priority_counts']['high'] > 0)
                    <span class="px-1.5 py-0.5 rounded font-bold bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30">
                        {{ $liveTelemetry['priority_counts']['high'] }} High
                    </span>
                @endif
                <span class="text-text-muted">{{ $liveTelemetry['priority_counts']['medium'] + $liveTelemetry['priority_counts']['low'] }} Normal</span>
            </div>
            <div class="h-1 w-full bg-amber-500/20 rounded-full mt-3 overflow-hidden">
                <div class="h-full bg-amber-500 rounded-full w-full"></div>
            </div>
        </div>

        {{-- SLA At-Risk & Overdue --}}
        <div class="bg-surface border border-border rounded-xl p-4 shadow-xs relative overflow-hidden group hover:border-rose-500/50 transition-all">
            <div class="flex items-center justify-between text-text-muted">
                <span class="text-[10px] font-extrabold uppercase tracking-wider">SLA At-Risk / Lewat</span>
                <div class="w-7 h-7 rounded-lg {{ $liveTelemetry['overdue_sla_count'] > 0 ? 'bg-rose-500/15 text-rose-600 dark:text-rose-400' : 'bg-slate-500/10 text-text-muted' }} flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
            </div>
            <div class="flex items-baseline gap-2 mt-2">
                <p class="text-2xl font-black font-mono {{ $liveTelemetry['overdue_sla_count'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-text-main' }}">
                    {{ $liveTelemetry['overdue_sla_count'] }}
                </p>
                <span class="text-xs font-bold text-amber-600 dark:text-amber-400 font-mono">+{{ $liveTelemetry['at_risk_sla_count'] }} < 1j</span>
            </div>
            <p class="text-[10px] text-text-muted mt-2">
                {{ $liveTelemetry['overdue_sla_count'] > 0 ? 'Perlu tindakan eskalasi segera' : 'Seluruh tiket dalam batas aman' }}
            </p>
            <div class="h-1 w-full bg-rose-500/20 rounded-full mt-3 overflow-hidden">
                <div class="h-full bg-rose-500 rounded-full" style="width: {{ $liveTelemetry['open_noc_count'] > 0 ? min(100, ($liveTelemetry['overdue_sla_count'] / $liveTelemetry['open_noc_count']) * 100) : 0 }}%"></div>
            </div>
        </div>

        {{-- Remote Solve Rate Hari Ini --}}
        <div class="bg-surface border border-border rounded-xl p-4 shadow-xs relative overflow-hidden group hover:border-emerald-500/50 transition-all">
            <div class="flex items-center justify-between text-text-muted">
                <span class="text-[10px] font-extrabold uppercase tracking-wider">Remote Solve (Hari Ini)</span>
                <div class="w-7 h-7 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            <p class="text-2xl font-black mt-2 text-emerald-600 dark:text-emerald-400 font-mono">{{ $liveTelemetry['remote_solve_rate_today'] }}%</p>
            <p class="text-[10px] text-text-muted mt-2">Tuntas di meja NOC tanpa FOP</p>
            <div class="h-1 w-full bg-emerald-500/20 rounded-full mt-3 overflow-hidden">
                <div class="h-full bg-emerald-500 rounded-full" style="width: {{ min(100, $liveTelemetry['remote_solve_rate_today']) }}%"></div>
            </div>
        </div>

        {{-- Selesai Hari Ini --}}
        <div class="bg-surface border border-border rounded-xl p-4 shadow-xs relative overflow-hidden group hover:border-sky-500/50 transition-all">
            <div class="flex items-center justify-between text-text-muted">
                <span class="text-[10px] font-extrabold uppercase tracking-wider">Selesai Hari Ini</span>
                <div class="w-7 h-7 rounded-lg bg-sky-500/10 text-sky-600 dark:text-sky-400 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
            </div>
            <p class="text-2xl font-black mt-2 text-sky-600 dark:text-sky-400 font-mono">{{ number_format($stats['selesai_hari_ini']) }}</p>
            <p class="text-[10px] text-text-muted mt-2">Total tiket ditutup hari ini</p>
            <div class="h-1 w-full bg-sky-500/20 rounded-full mt-3 overflow-hidden">
                <div class="h-full bg-sky-500 rounded-full w-full"></div>
            </div>
        </div>

        {{-- MTTA Rerata Live --}}
        <div class="bg-surface border border-border rounded-xl p-4 shadow-xs relative overflow-hidden group hover:border-violet-500/50 transition-all col-span-2 sm:col-span-1">
            <div class="flex items-center justify-between text-text-muted">
                <span class="text-[10px] font-extrabold uppercase tracking-wider">MTTA Rata-rata</span>
                <div class="w-7 h-7 rounded-lg bg-violet-500/10 text-violet-600 dark:text-violet-400 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
            </div>
            <p class="text-2xl font-black mt-2 text-violet-600 dark:text-violet-400 font-mono">
                {{ $liveTelemetry['mtta_minutes'] !== null ? $liveTelemetry['mtta_minutes'].' mnt' : '—' }}
            </p>
            <p class="text-[10px] text-text-muted mt-2">Waktu respons pertama tiket</p>
            <div class="h-1 w-full bg-violet-500/20 rounded-full mt-3 overflow-hidden">
                <div class="h-full bg-violet-500 rounded-full w-2/3"></div>
            </div>
        </div>
    </div>

    {{-- Baris Dua Kolom: Tiket Kritis At-Risk & Audit Beban Operator --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Tabel Tiket Kritis & At-Risk SLA --}}
        <div class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs lg:col-span-2 flex flex-col">
            <div class="px-5 py-3.5 border-b border-border bg-slate-50/50 dark:bg-slate-900/40 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-rose-500 animate-ping"></span>
                    <h2 class="text-xs font-black uppercase tracking-wider text-text-main">
                        Antrean Tiket Kritis &amp; At-Risk SLA
                    </h2>
                </div>
                <span class="text-[10px] font-bold text-rose-600 dark:text-rose-400 bg-rose-500/10 px-2 py-0.5 rounded border border-rose-500/20">
                    Prioritas Penanganan Cepat
                </span>
            </div>

            <div class="overflow-x-auto flex-1">
                <table class="w-full text-xs text-left">
                    <thead class="bg-surface-muted/50 border-b border-border text-[10px] font-extrabold uppercase text-text-muted">
                        <tr>
                            <th class="py-2.5 px-3">Tiket</th>
                            <th class="py-2.5 px-3">Pelanggan &amp; Issue</th>
                            <th class="py-2.5 px-3">Cabang/POP</th>
                            <th class="py-2.5 px-3 text-center">Status SLA</th>
                            <th class="py-2.5 px-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($liveTelemetry['urgent_tickets'] as $ticket)
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-900/30 transition-colors {{ $ticket['is_breached'] ? 'bg-rose-500/5' : '' }}">
                                <td class="py-3 px-3 align-top">
                                    <div class="flex flex-col gap-1">
                                        <span class="font-mono font-black text-sky-600 dark:text-sky-400">#{{ $ticket['ticket_number'] }}</span>
                                        <span class="text-[9px] font-black uppercase tracking-wider px-1.5 py-0.5 rounded w-fit
                                            {{ in_array(strtolower($ticket['priority']), ['urgent', 'high']) ? 'bg-rose-500/15 text-rose-600 dark:text-rose-400 border border-rose-500/30' : 'bg-slate-100 dark:bg-slate-800 text-text-muted' }}">
                                            {{ $ticket['priority'] }}
                                        </span>
                                    </div>
                                </td>
                                <td class="py-3 px-3 align-top max-w-[240px]">
                                    <p class="font-bold text-text-main truncate">{{ $ticket['customer_name'] }}</p>
                                    @if($ticket['customer_cid'])
                                        <span class="text-[10px] font-mono text-text-muted">CID: {{ $ticket['customer_cid'] }}</span>
                                    @endif
                                    <p class="text-[11px] text-text-secondary font-medium mt-0.5 truncate">
                                        <span class="text-amber-600 dark:text-amber-400 font-bold">[{{ $ticket['issue'] }}]</span>
                                        {{ \Illuminate\Support\Str::limit($ticket['detail'], 50) }}
                                    </p>
                                </td>
                                <td class="py-3 px-3 align-top">
                                    <span class="text-xs font-semibold text-text-main">{{ $ticket['pop_name'] }}</span>
                                    <p class="text-[10px] text-text-muted">Masuk {{ $ticket['waiting_human'] }} lalu</p>
                                </td>
                                <td class="py-3 px-3 align-top text-center">
                                    @if($ticket['is_breached'])
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-rose-500/15 text-rose-600 dark:text-rose-400 border border-rose-500/30 animate-pulse font-mono">
                                            ⚠️ {{ $ticket['remain_label'] }}
                                        </span>
                                    @elseif($ticket['is_at_risk'])
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30 font-mono">
                                            ⏳ {{ $ticket['remain_label'] }}
                                        </span>
                                    @else
                                        <span class="text-[10px] text-text-muted font-mono">
                                            {{ $ticket['remain_label'] }}
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 px-3 align-top text-right">
                                    <button type="button" 
                                            onclick="window.dispatchEvent(new CustomEvent('open-ticket-drawer', { detail: { id: {{ $ticket['id'] }} } }))"
                                            class="px-2.5 py-1 text-[11px] font-bold rounded-lg bg-sky-600 hover:bg-sky-700 text-white transition-colors cursor-pointer shadow-2xs">
                                        Proses
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-xs text-text-muted">
                                    <span class="font-medium text-emerald-600 dark:text-emerald-400">✓ Tidak ada tiket kritis yang mendekati atau melanggar SLA.</span>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Audit Beban Kerja Operator (Workload Matrix) --}}
        <div class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs flex flex-col">
            <div class="px-5 py-3.5 border-b border-border bg-slate-50/50 dark:bg-slate-900/40 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-sky-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <h2 class="text-xs font-black uppercase tracking-wider text-text-main">
                        Audit Beban Operator
                    </h2>
                </div>
                <span class="text-[10px] font-bold text-text-muted bg-surface-muted px-2 py-0.5 rounded">Live Shift</span>
            </div>

            <div class="p-4 space-y-3 flex-1 overflow-y-auto max-h-[380px]">
                @forelse($liveTelemetry['operator_workloads'] as $op)
                    <div class="p-3 rounded-lg border border-border/70 bg-slate-50/50 dark:bg-slate-900/20 hover:border-sky-500/40 transition-colors">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="w-7 h-7 rounded-full bg-sky-500/10 text-sky-600 dark:text-sky-400 flex items-center justify-center font-bold text-xs uppercase">
                                    {{ substr($op['name'], 0, 1) }}
                                </span>
                                <div>
                                    <p class="text-xs font-bold text-text-main">{{ $op['name'] }}</p>
                                    <span class="text-[9px] font-extrabold uppercase tracking-wider px-1.5 py-0.2 rounded bg-slate-200/60 dark:bg-slate-800 text-text-muted">
                                        {{ $op['role'] }}
                                    </span>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="text-sm font-black font-mono {{ $op['active_count'] >= 5 ? 'text-rose-600 dark:text-rose-400' : 'text-sky-600 dark:text-sky-400' }}">
                                    {{ $op['active_count'] }}
                                </span>
                                <span class="text-[10px] text-text-muted block">Tiket Dipegang</span>
                            </div>
                        </div>
                        @if($op['latest_ticket'])
                            <div class="mt-2 pt-2 border-t border-border/50 flex items-center justify-between text-[10px] text-text-muted">
                                <span>Terakhir: <strong class="text-text-main font-mono">#{{ $op['latest_ticket'] }}</strong></span>
                                <span>{{ $op['latest_activity'] }}</span>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="py-8 text-center text-xs text-text-muted">
                        Belum ada staf yang memegang tiket terbuka saat ini.
                    </div>
                @endforelse
            </div>
        </div>

    </div>

    {{-- Main Operational Section: Antrean Tiket Aktif NOC (Urut Aging) & Activity Feed --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- List Tiket Aktif NOC --}}
        <div id="active-tickets-container" class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs lg:col-span-2">
            <div class="px-5 py-3.5 border-b border-border bg-slate-50/50 dark:bg-slate-900/40 flex items-center justify-between">
                <div>
                    <h2 class="text-xs font-black uppercase tracking-wider text-text-main flex items-center gap-2">
                        <svg class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Tiket Aktif NOC (Paling Lama Menunggu di Atas)
                    </h2>
                    <p class="text-[11px] text-text-muted mt-0.5">Urutan berdasarkan prioritas aging (tiket yang paling lama diproses NOC di posisi teratas).</p>
                </div>
                <span class="text-[10px] font-bold text-amber-600 dark:text-amber-400 bg-amber-500/10 px-2.5 py-1 rounded-full border border-amber-500/20">
                    {{ count($activeTickets) }} Tiket Aktif
                </span>
            </div>

            <div class="divide-y divide-border max-h-[520px] overflow-y-auto">
                @forelse($activeTickets as $ticket)
                    @php
                        $waitingHours = $ticket->created_at->diffInHours(now());
                        $agingBadgeClass = 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20';
                        if ($waitingHours >= 24) {
                            $agingBadgeClass = 'bg-rose-500/15 text-rose-600 dark:text-rose-400 border-rose-500/30 animate-pulse font-extrabold';
                        } elseif ($waitingHours >= 8) {
                            $agingBadgeClass = 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border-amber-500/30 font-bold';
                        }
                    @endphp
                    <div class="p-4 flex items-center justify-between gap-3 hover:bg-slate-50/80 dark:hover:bg-slate-900/40 transition-colors cursor-pointer"
                         onclick="window.dispatchEvent(new CustomEvent('open-ticket-drawer', { detail: { id: {{ $ticket->id }} } }))">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 text-xs flex-wrap">
                                <span class="font-mono font-bold text-sky-600 dark:text-sky-400">#{{ $ticket->ticket_number }}</span>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded border {{ $ticket->statusBadgeClasses() }}">{{ $ticket->statusLabel() }}</span>
                                @if($ticket->priority)
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded border bg-slate-100 dark:bg-slate-800 text-text-muted">{{ $ticket->priority->value }}</span>
                                @endif
                                <span class="text-[10px] font-medium text-text-muted">POP: {{ $ticket->pop->name ?? '—' }}</span>
                            </div>
                            <p class="text-xs font-extrabold text-text-main truncate mt-1">
                                {{ $ticket->customer->full_name ?? $ticket->customer_name ?? '—' }}
                                @if($ticket->customer?->cid)
                                    <span class="font-mono text-text-muted text-[11px] font-normal">({{ $ticket->customer->cid }})</span>
                                @endif
                            </p>
                            <p class="text-[11px] text-text-muted mt-0.5 truncate">
                                <span class="font-semibold text-text-secondary">{{ $ticket->issueCategory?->name ?? 'Issue' }}:</span>
                                {{ \Illuminate\Support\Str::limit($ticket->detail_keluhan, 75) }}
                            </p>
                        </div>

                        <div class="shrink-0 text-right flex flex-col items-end gap-1">
                            <span class="text-[10px] px-2.5 py-1 rounded-md border font-mono {{ $agingBadgeClass }}">
                                Menunggu {{ $ticket->created_at->diffForHumans(null, true) }}
                            </span>
                            <span class="text-[10px] text-text-muted">Oleh: {{ $ticket->creator->name ?? '—' }}</span>
                        </div>
                    </div>
                @empty
                    <div class="p-12 text-center text-xs text-text-muted flex flex-col items-center justify-center gap-2">
                        <svg class="w-8 h-8 text-emerald-500/50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Tidak ada tiket aktif yang menggantung di NOC.</span>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Activity Feed --}}
        <div id="activity-feed-container" class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs flex flex-col">
            <div class="px-5 py-3.5 border-b border-border bg-slate-50/50 dark:bg-slate-900/40 flex items-center justify-between">
                <h2 class="text-xs font-black uppercase tracking-wider text-text-main flex items-center gap-2">
                    <svg class="w-4 h-4 text-sky-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    Aktivitas Terbaru
                </h2>
                <span class="text-[10px] font-bold text-text-muted bg-slate-200/60 dark:bg-slate-800 px-2 py-0.5 rounded">Log Live</span>
            </div>
            <ul class="divide-y divide-border max-h-[520px] overflow-y-auto p-2">
                @forelse($activityFeed as $history)
                    <li class="p-3 text-xs hover:bg-slate-50/60 dark:hover:bg-slate-900/30 rounded-lg transition-colors">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-mono font-extrabold text-sky-600 dark:text-sky-400">#{{ $history->ticket->ticket_number ?? '—' }}</span>
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded border {{ $history->action->badgeClasses() }}">{{ $history->action->label() }}</span>
                        </div>
                        <p class="text-text-muted mt-1 text-[11px]">
                            Oleh <span class="font-semibold text-text-main">{{ $history->actor->name ?? 'Sistem' }}</span>
                        </p>
                        <p class="text-[10px] text-text-muted/80 mt-0.5 font-mono">
                            {{ \App\Support\IndonesianDate::dateTime($history->happened_at) }}
                        </p>
                    </li>
                @empty
                    <li class="p-8 text-center text-xs text-text-muted">Belum ada aktivitas tercatat.</li>
                @endforelse
            </ul>
        </div>

    </div>

</div>
