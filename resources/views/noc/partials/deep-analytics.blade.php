{{-- ══ Tab 2: Deep Analytics & Audit Solving ══ --}}
<div class="space-y-6">

    {{-- Seksi 1: Telemetri Kecepatan & Kualitas Solving (MTTR, FCR & MTTA) --}}
    <div class="bg-surface border border-border rounded-xl p-5 shadow-xs space-y-4">
        <div class="flex items-center justify-between flex-wrap gap-2 border-b border-border/60 pb-3">
            <div>
                <h2 class="text-xs font-black uppercase tracking-wider text-text-main flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                    Telemetri Kecepatan &amp; Kualitas Solving (MTTR &amp; FCR)
                </h2>
                <p class="text-[11px] text-text-muted mt-0.5">Pengukuran efisiensi penyelesaian gangguan via remote NOC vs eskalasi teknisi lapangan (FOP).</p>
            </div>
            <span class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 px-2.5 py-1 rounded border border-emerald-500/20">
                SLA &amp; RESOLUTION QUALITY
            </span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- MTTR Remote --}}
            <div class="p-3.5 rounded-xl border border-border/80 bg-slate-50/50 dark:bg-slate-900/30">
                <span class="text-[10px] font-extrabold uppercase text-text-muted">MTTR Remote (Meja NOC)</span>
                <p class="text-2xl font-black mt-1 text-emerald-600 dark:text-emerald-400 font-mono">
                    {{ $deepAnalytics['mttr_remote_minutes'] !== null ? $deepAnalytics['mttr_remote_minutes'].' mnt' : '—' }}
                </p>
                <p class="text-[10px] text-text-muted mt-1">
                    {{ $deepAnalytics['remote_solve_count'] }} tiket tuntas tanpa kunjungan
                </p>
            </div>

            {{-- MTTR Lapangan (FOP) --}}
            <div class="p-3.5 rounded-xl border border-border/80 bg-slate-50/50 dark:bg-slate-900/30">
                <span class="text-[10px] font-extrabold uppercase text-text-muted">MTTR Lapangan (Eskalasi FOP)</span>
                <p class="text-2xl font-black mt-1 text-indigo-600 dark:text-indigo-400 font-mono">
                    @if($deepAnalytics['mttr_field_minutes'] !== null)
                        {{ intdiv($deepAnalytics['mttr_field_minutes'], 60) }}j {{ $deepAnalytics['mttr_field_minutes'] % 60 }}m
                    @else
                        —
                    @endif
                </p>
                <p class="text-[10px] text-text-muted mt-1">
                    {{ $deepAnalytics['field_escalation_count'] }} tiket perlu teknisi fisik
                </p>
            </div>

            {{-- Remote Solve Rate (FCR) --}}
            <div class="p-3.5 rounded-xl border border-border/80 bg-slate-50/50 dark:bg-slate-900/30">
                <span class="text-[10px] font-extrabold uppercase text-text-muted">First Contact Resolution (FCR)</span>
                <p class="text-2xl font-black mt-1 text-sky-600 dark:text-sky-400 font-mono">
                    {{ $deepAnalytics['fcr_rate'] }}%
                </p>
                <div class="h-1.5 w-full bg-slate-100 dark:bg-slate-800 rounded-full mt-2 overflow-hidden">
                    <div class="h-full bg-sky-500 rounded-full" style="width: {{ min(100, $deepAnalytics['fcr_rate']) }}%"></div>
                </div>
            </div>

            {{-- MTTA Acknowledge --}}
            <div class="p-3.5 rounded-xl border border-border/80 bg-slate-50/50 dark:bg-slate-900/30">
                <span class="text-[10px] font-extrabold uppercase text-text-muted">MTTA (Respons Pertama)</span>
                <p class="text-2xl font-black mt-1 text-violet-600 dark:text-violet-400 font-mono">
                    {{ $deepAnalytics['mtta_minutes'] !== null ? $deepAnalytics['mtta_minutes'].' mnt' : '—' }}
                </p>
                <p class="text-[10px] text-text-muted mt-1">Tiket dibuat sampai direspon</p>
            </div>
        </div>

        {{-- Breakdown Kecepatan Solving per Kategori Keluhan --}}
        @if(!empty($deepAnalytics['mttr_by_category']))
            <div class="mt-4 pt-4 border-t border-border/60">
                <h3 class="text-[11px] font-bold uppercase tracking-wider text-text-main mb-2">
                    Kecepatan Solving per Kategori Keluhan (MTTR Breakdown)
                </h3>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                    @foreach($deepAnalytics['mttr_by_category'] as $cat)
                        <div class="p-2.5 rounded-lg border border-border/60 bg-surface text-xs flex items-center justify-between">
                            <div class="truncate max-w-[140px]">
                                <span class="font-bold text-text-main block truncate">{{ $cat['name'] }}</span>
                                <span class="text-[10px] text-text-muted font-mono">{{ $cat['count'] }} Tiket</span>
                            </div>
                            <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded text-[11px]">
                                {{ $cat['avg_label'] }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Seksi 2: Pelanggan Kronis (*Chronic Repeat Customers*) & Rasio Komplain POP --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Radar Pelanggan Kronis --}}
        <div class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs flex flex-col">
            <div class="px-5 py-3.5 border-b border-border bg-slate-50/50 dark:bg-slate-900/40 flex items-center justify-between">
                <div>
                    <h2 class="text-xs font-black uppercase tracking-wider text-text-main flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                        Radar Pelanggan Kronis (Komplain Berulang)
                    </h2>
                    <p class="text-[11px] text-text-muted mt-0.5">Pelanggan dengan keluhan &ge; 2 kali dalam 30 hari terakhir (indikasi redaman/kabel drop aus).</p>
                </div>
                <span class="text-[10px] font-bold text-rose-600 dark:text-rose-400 bg-rose-500/10 px-2 py-0.5 rounded border border-rose-500/20">
                    {{ count($deepAnalytics['chronic_customers']) }} Terdeteksi
                </span>
            </div>

            <div class="divide-y divide-border flex-1 max-h-[380px] overflow-y-auto">
                @forelse($deepAnalytics['chronic_customers'] as $chronic)
                    <div class="p-3.5 hover:bg-slate-50/60 dark:hover:bg-slate-900/30 transition-colors flex items-center justify-between gap-3 text-xs">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-text-main truncate">{{ $chronic['name'] }}</span>
                                @if($chronic['cid'])
                                    <span class="text-[10px] font-mono text-text-muted font-normal">({{ $chronic['cid'] }})</span>
                                @endif
                                <span class="text-[10px] font-semibold text-text-muted bg-surface-muted px-1.5 py-0.2 rounded">{{ $chronic['pop_name'] }}</span>
                            </div>
                            <p class="text-[11px] text-text-muted mt-0.5 truncate">
                                Dominan: <strong class="text-amber-600 dark:text-amber-400">{{ $chronic['top_issue'] }}</strong>
                                @if($chronic['address'])
                                    — {{ $chronic['address'] }}
                                @endif
                            </p>
                        </div>
                        <div class="shrink-0 text-right">
                            <span class="px-2 py-0.5 rounded text-[11px] font-mono font-bold bg-rose-500/15 text-rose-600 dark:text-rose-400 border border-rose-500/30">
                                {{ $chronic['count'] }}x Tiket
                            </span>
                            <span class="text-[10px] text-text-muted block mt-0.5 font-mono">#{{ $chronic['last_ticket'] }}</span>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-xs text-text-muted">
                        <span class="text-emerald-600 dark:text-emerald-400 font-medium">✓ Bagus! Tidak ada pelanggan dengan komplain berulang dalam 30 hari.</span>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Rasio Komplain terhadap Basis Pelanggan POP --}}
        <div class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs flex flex-col">
            <div class="px-5 py-3.5 border-b border-border bg-slate-50/50 dark:bg-slate-900/40 flex items-center justify-between">
                <div>
                    <h2 class="text-xs font-black uppercase tracking-wider text-text-main flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                        Rasio Komplain per Basis Pelanggan POP
                    </h2>
                    <p class="text-[11px] text-text-muted mt-0.5">% Insiden keluhan terhadap total pelanggan aktif di tiap cabang/POP.</p>
                </div>
                <span class="text-[10px] font-bold text-text-muted bg-surface-muted px-2 py-0.5 rounded">Tingkat Insiden</span>
            </div>

            <div class="overflow-x-auto flex-1 max-h-[380px]">
                <table class="w-full text-xs text-left">
                    <thead class="bg-surface-muted/50 border-b border-border text-[10px] font-extrabold uppercase text-text-muted">
                        <tr>
                            <th class="py-2.5 px-3">Cabang / POP</th>
                            <th class="py-2.5 px-3 text-center">Komplain</th>
                            <th class="py-2.5 px-3 text-center">Pelanggan Aktif</th>
                            <th class="py-2.5 px-3 text-right">Rasio Insiden</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($deepAnalytics['regional_complaint_rates'] as $rate)
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-900/30 transition-colors">
                                <td class="py-2.5 px-3 font-bold text-text-main">{{ $rate['pop_name'] }}</td>
                                <td class="py-2.5 px-3 text-center font-mono font-bold text-text-main">{{ $rate['complaints'] }}</td>
                                <td class="py-2.5 px-3 text-center font-mono text-text-muted">{{ number_format($rate['customers']) }}</td>
                                <td class="py-2.5 px-3 text-right font-mono">
                                    <span class="px-2 py-0.5 rounded text-[11px] font-bold
                                        {{ $rate['rate_pct'] >= 5.0 ? 'bg-rose-500/15 text-rose-600 dark:text-rose-400 border border-rose-500/30' : ($rate['rate_pct'] >= 2.0 ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30' : 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30') }}">
                                        {{ $rate['rate_pct'] }}%
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-6 text-center text-text-muted">Belum ada data untuk dihitung.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    {{-- Seksi 3: Statistik per Daerah, Issue & Tren Matriks (Kompatibilitas Penuh & Chart.js) --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Grafik / Meter Statistik per Daerah --}}
        <div id="region-stats-container" class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs flex flex-col">
            <div class="px-5 py-3.5 border-b border-border bg-slate-50/50 dark:bg-slate-900/40 flex items-center justify-between">
                <h2 class="text-xs font-black uppercase tracking-wider text-text-main flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-sky-500"></span>
                    Statistik Tiket per Daerah (POP / Kec)
                </h2>
                <span class="text-[10px] font-bold text-text-muted bg-slate-200/60 dark:bg-slate-800 px-2 py-0.5 rounded">Top {{ count($regionStats) }}</span>
            </div>
            <div class="p-5 space-y-3.5 flex-1">
                @forelse($regionStats as $regionName => $data)
                    <div>
                        <div class="flex items-center justify-between text-xs mb-1 font-semibold">
                            <span class="text-text-main truncate max-w-[200px]">{{ $regionName }}</span>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="text-text-muted text-[11px] font-mono">{{ $data['percentage'] }}%</span>
                                <span class="font-mono font-bold text-sky-600 dark:text-sky-400 bg-sky-500/10 px-2 py-0.5 rounded text-[11px]">{{ $data['count'] }} Tiket</span>
                            </div>
                        </div>
                        <div class="h-2 w-full bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-sky-500 to-indigo-500 rounded-full transition-all duration-500" style="width: {{ $data['percentage'] }}%"></div>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-xs text-text-muted">Tidak ada data tiket untuk periode terpilih.</div>
                @endforelse
            </div>
        </div>

        {{-- Grafik / Meter Statistik per Issue --}}
        <div id="issue-stats-container" class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs flex flex-col">
            <div class="px-5 py-3.5 border-b border-border bg-slate-50/50 dark:bg-slate-900/40 flex items-center justify-between">
                <h2 class="text-xs font-black uppercase tracking-wider text-text-main flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                    Statistik Tiket per Kategori Issue
                </h2>
                <span class="text-[10px] font-bold text-text-muted bg-slate-200/60 dark:bg-slate-800 px-2 py-0.5 rounded">Top {{ count($issueStats) }}</span>
            </div>
            <div class="p-5 space-y-3.5 flex-1">
                @forelse($issueStats as $issueName => $data)
                    <div>
                        <div class="flex items-center justify-between text-xs mb-1 font-semibold">
                            <span class="text-text-main truncate max-w-[200px]">{{ $issueName }}</span>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="text-text-muted text-[11px] font-mono">{{ $data['percentage'] }}%</span>
                                <span class="font-mono font-bold text-amber-600 dark:text-amber-400 bg-amber-500/10 px-2 py-0.5 rounded text-[11px]">{{ $data['count'] }} Tiket</span>
                            </div>
                        </div>
                        <div class="h-2 w-full bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-amber-500 to-rose-500 rounded-full transition-all duration-500" style="width: {{ $data['percentage'] }}%"></div>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-xs text-text-muted">Tidak ada data issue untuk periode terpilih.</div>
                @endforelse
            </div>
        </div>

        {{-- Trend Matrix: Daerah dengan Issue (Analisa Hotspot) --}}
        <div id="trend-matrix-container" class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs lg:col-span-2">
            <div class="px-5 py-3.5 border-b border-border bg-slate-50/50 dark:bg-slate-900/40 flex items-center justify-between">
                <div>
                    <h2 class="text-xs font-black uppercase tracking-wider text-text-main flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-rose-500 animate-ping"></span>
                        Tren Matriks: Daerah dengan Issue Terbanyak (Hotspot Trend)
                    </h2>
                    <p class="text-[11px] text-text-muted mt-0.5">Pemetaan konsentrasi keluhan untuk deteksi dini daerah bermasalah.</p>
                </div>
                <span class="text-[10px] font-bold text-rose-600 dark:text-rose-400 bg-rose-500/10 px-2 py-1 rounded border border-rose-500/20">
                    STACKED BAR
                </span>
            </div>

            <div class="p-4">
                @if(count($trendMatrix['regions']) > 0 && count($trendMatrix['issues']) > 0)
                    <div class="h-72">
                        <canvas id="chart-trend-matrix"></canvas>
                    </div>
                    <script type="application/json" id="data-trend-matrix">@json($trendMatrix)</script>
                @else
                    <div class="py-8 text-center text-xs text-text-muted">Tidak cukup data untuk menampilkan matriks tren issue daerah.</div>
                @endif
            </div>
        </div>

    </div>

    {{-- Tren Harian, SLA Compliance & Distribusi Aging --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Tren Harian Volume Tiket (Line Chart) --}}
        <div id="daily-trend-container" class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs lg:col-span-2">
            <div class="px-5 py-3.5 border-b border-border bg-slate-50/50 dark:bg-slate-900/40">
                <h2 class="text-xs font-black uppercase tracking-wider text-text-main flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-sky-500"></span>
                    Tren Harian Volume Tiket
                </h2>
                <p class="text-[11px] text-text-muted mt-0.5">Masuk vs selesai vs dibatalkan, maks. 60 hari terakhir dari rentang terpilih.</p>
            </div>
            <div class="p-4">
                <div class="h-64">
                    <canvas id="chart-daily-trend"></canvas>
                </div>
                <script type="application/json" id="data-daily-trend">@json($dailyTrend)</script>
            </div>
        </div>

        {{-- SLA Compliance (Donut) --}}
        <div id="sla-compliance-container" class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs flex flex-col">
            <div class="px-5 py-3.5 border-b border-border bg-slate-50/50 dark:bg-slate-900/40">
                <h2 class="text-xs font-black uppercase tracking-wider text-text-main flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    SLA Compliance
                </h2>
                <p class="text-[11px] text-text-muted mt-0.5">Dari {{ $slaCompliance['total'] }} tiket resolved berSLA.</p>
            </div>
            <div class="p-4 flex-1 flex flex-col items-center justify-center">
                @if($slaCompliance['total'] > 0)
                    <canvas id="chart-sla-compliance" width="180" height="180"></canvas>
                    <script type="application/json" id="data-sla-compliance">@json($slaCompliance)</script>
                    <p class="text-2xl font-black mt-3 {{ $slaCompliance['ontime_pct'] >= 80 ? 'text-emerald-600 dark:text-emerald-400' : ($slaCompliance['ontime_pct'] >= 50 ? 'text-amber-600 dark:text-amber-400' : 'text-rose-600 dark:text-rose-400') }} font-mono">
                        {{ $slaCompliance['ontime_pct'] }}%
                    </p>
                    <p class="text-[10px] text-text-muted">On-time ({{ $slaCompliance['ontime'] }} dari {{ $slaCompliance['total'] }})</p>
                @else
                    <div class="py-8 text-center text-xs text-text-muted">Belum ada tiket resolved ber-SLA pada periode ini.</div>
                @endif
            </div>
        </div>

        {{-- Distribusi Aging Tiket Aktif (Bar Chart) --}}
        <div id="aging-buckets-container" class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs lg:col-span-3">
            <div class="px-5 py-3.5 border-b border-border bg-slate-50/50 dark:bg-slate-900/40">
                <h2 class="text-xs font-black uppercase tracking-wider text-text-main flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                    Distribusi Aging Tiket Aktif NOC
                </h2>
                <p class="text-[11px] text-text-muted mt-0.5">Seluruh antrean handler=NOC berstatus terbuka, bukan cuma yang tampil di list di bawah.</p>
            </div>
            <div class="p-4">
                <div class="h-40">
                    <canvas id="chart-aging-buckets"></canvas>
                </div>
                <script type="application/json" id="data-aging-buckets">@json($agingBuckets)</script>
            </div>
        </div>

    </div>

    {{-- Tren Bulanan Komplain Pelanggan — Semua POP --}}
    <div class="space-y-6">

        {{-- Tren Bulanan per Daerah --}}
        <div id="monthly-complaint-region-container" class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs">
            <div class="px-5 py-3.5 border-b border-border bg-slate-50/50 dark:bg-slate-900/40">
                <h2 class="text-xs font-black uppercase tracking-wider text-text-main flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                    Tren Bulanan Komplain per Daerah (Semua POP)
                </h2>
                <p class="text-[11px] text-text-muted mt-0.5">
                    12 bulan terakhir, lintas semua POP dalam scope Anda — dipakai buat cari daerah paling sering komplain.
                    "Komplain" = Ticket Maintenance ({{ $monthlyComplaintTrend['total'] }} tiket).
                </p>
            </div>
            <div class="p-4">
                <div class="h-80">
                    <canvas id="chart-monthly-complaint-region"></canvas>
                </div>
                <script type="application/json" id="data-monthly-complaint">@json($monthlyComplaintTrend)</script>
            </div>
        </div>

        {{-- Tren Bulanan per Kategori Issue --}}
        <div id="monthly-complaint-issue-container" class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs">
            <div class="px-5 py-3.5 border-b border-border bg-slate-50/50 dark:bg-slate-900/40">
                <h2 class="text-xs font-black uppercase tracking-wider text-text-main flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                    Tren Bulanan Komplain per Kategori Issue (Semua POP)
                </h2>
                <p class="text-[11px] text-text-muted mt-0.5">
                    Komplainnya soal apa saja tiap bulan — top 5 kategori + Lainnya, sumber sama dengan chart daerah di atas.
                </p>
            </div>
            <div class="p-4">
                <div class="h-80">
                    <canvas id="chart-monthly-complaint-issue"></canvas>
                </div>
            </div>
        </div>

    </div>

    {{-- Performa & Analisa per POP --}}
    <div id="per-pop-analytics-container">
        @if(count($perPopAnalytics) > 0)
            <div class="flex items-center gap-2 mb-3">
                <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                <h2 class="text-xs font-black uppercase tracking-wider text-text-main">Performa &amp; Analisa per POP</h2>
            </div>
            <script type="application/json" id="data-per-pop-analytics">@json($perPopAnalytics)</script>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                @foreach($perPopAnalytics as $pop)
                    <div class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs">
                        <div class="px-5 py-3.5 border-b border-border bg-slate-50/50 dark:bg-slate-900/40">
                            <h3 class="text-xs font-black uppercase tracking-wider text-text-main">{{ $pop['pop_name'] }}</h3>
                            <p class="text-[11px] text-text-muted mt-0.5">Jumlah Komplain (bar) vs Total Pelanggan (garis) — 12 bulan terakhir.</p>
                        </div>
                        <div class="p-4">
                            <div class="h-64">
                                <canvas id="chart-pop-{{ $pop['pop_id'] }}"></canvas>
                            </div>
                        </div>
                        <div class="px-5 py-3.5 border-t border-border bg-indigo-500/5">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400 mb-1">Analisa</p>
                            <p class="text-xs text-text-main leading-relaxed">{{ $pop['analysis'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Leaderboard Performa Individu Helpdesk & NOC --}}
    @if($canViewPerformance)
        <div id="leaderboard-container" class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs">
            <div class="px-5 py-3.5 border-b border-border bg-slate-50/50 dark:bg-slate-900/40">
                <h2 class="text-xs font-black uppercase tracking-wider text-text-main flex items-center gap-2">
                    <svg class="w-4 h-4 text-violet-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    Leaderboard Performa Individu
                </h2>
                <p class="text-[11px] text-text-muted mt-0.5">Diukur dari riwayat tiket (ticket_histories) per aktor — satu tiket bisa dipegang lebih dari satu orang, jadi atribusinya per aksi, bukan per tiket.</p>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 divide-y lg:divide-y-0 lg:divide-x divide-border">
                {{-- Helpdesk --}}
                <div class="p-4">
                    <h3 class="text-[11px] font-extrabold uppercase text-sky-600 dark:text-sky-400 mb-2 flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span> Helpdesk
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left">
                            <thead>
                                <tr class="border-b border-border text-[10px] uppercase text-text-muted font-extrabold">
                                    <th class="py-2 pr-2">Nama</th>
                                    <th class="py-2 px-2 text-center">Selesai</th>
                                    <th class="py-2 px-2 text-center">Eskalasi NOC</th>
                                    <th class="py-2 px-2 text-center">Avg. Durasi</th>
                                    <th class="py-2 pl-2 text-center">SLA Breach</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/60">
                                @forelse($leaderboard['helpdesk'] as $row)
                                    <tr>
                                        <td class="py-2 pr-2 font-bold text-text-main truncate max-w-[140px]">{{ $row['name'] }}</td>
                                        <td class="py-2 px-2 text-center font-mono font-bold text-emerald-600 dark:text-emerald-400">{{ $row['jumlah_selesai'] }}</td>
                                        <td class="py-2 px-2 text-center font-mono">{{ $row['jumlah_eskalasi_noc'] }}</td>
                                        <td class="py-2 px-2 text-center font-mono text-text-muted">
                                            {{ $row['avg_durasi_menit'] !== null ? ($row['avg_durasi_menit'] < 60 ? $row['avg_durasi_menit'].'m' : intdiv($row['avg_durasi_menit'], 60).'j '.($row['avg_durasi_menit'] % 60).'m') : '-' }}
                                        </td>
                                        <td class="py-2 pl-2 text-center font-mono {{ $row['sla_breach_count'] > 0 ? 'text-rose-600 dark:text-rose-400 font-bold' : 'text-text-muted' }}">{{ $row['sla_breach_count'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="py-6 text-center text-text-muted">Belum ada data pada periode ini.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- NOC --}}
                <div class="p-4">
                    <h3 class="text-[11px] font-extrabold uppercase text-amber-600 dark:text-amber-400 mb-2 flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> NOC
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left">
                            <thead>
                                <tr class="border-b border-border text-[10px] uppercase text-text-muted font-extrabold">
                                    <th class="py-2 pr-2">Nama</th>
                                    <th class="py-2 px-2 text-center">Selesai</th>
                                    <th class="py-2 px-2 text-center">Eskalasi FOP</th>
                                    <th class="py-2 px-2 text-center">Avg. Durasi</th>
                                    <th class="py-2 pl-2 text-center">SLA Breach</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/60">
                                @forelse($leaderboard['noc'] as $row)
                                    <tr>
                                        <td class="py-2 pr-2 font-bold text-text-main truncate max-w-[140px]">{{ $row['name'] }}</td>
                                        <td class="py-2 px-2 text-center font-mono font-bold text-emerald-600 dark:text-emerald-400">{{ $row['jumlah_selesai'] }}</td>
                                        <td class="py-2 px-2 text-center font-mono">{{ $row['jumlah_eskalasi_fop'] }}</td>
                                        <td class="py-2 px-2 text-center font-mono text-text-muted">
                                            {{ $row['avg_durasi_menit'] !== null ? ($row['avg_durasi_menit'] < 60 ? $row['avg_durasi_menit'].'m' : intdiv($row['avg_durasi_menit'], 60).'j '.($row['avg_durasi_menit'] % 60).'m') : '-' }}
                                        </td>
                                        <td class="py-2 pl-2 text-center font-mono {{ $row['sla_breach_count'] > 0 ? 'text-rose-600 dark:text-rose-400 font-bold' : 'text-text-muted' }}">{{ $row['sla_breach_count'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="py-6 text-center text-text-muted">Belum ada data pada periode ini.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Seksi 4: Rekapitulasi Handover Shift --}}
    <div class="bg-surface border border-border rounded-xl p-5 shadow-xs">
        <div class="flex items-center justify-between flex-wrap gap-2 mb-3">
            <div>
                <h3 class="text-xs font-bold text-text-main uppercase tracking-wider">
                    Rekapitulasi Serah-Terima Shift (Handover)
                </h3>
                <p class="text-[11px] text-text-muted mt-0.5">Snapshot ringkasan operasional tiket hari ini untuk pergantian tugas tim NOC.</p>
            </div>
            <span class="text-[10px] font-bold text-text-muted bg-surface-muted px-2 py-0.5 rounded font-mono">
                {{ today()->format('d M Y') }}
            </span>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 text-center">
            <div class="p-3 rounded-lg bg-surface-muted/50 border border-border/50">
                <span class="text-[10px] font-bold uppercase text-text-muted block">Masuk Hari Ini</span>
                <span class="text-lg font-black font-mono text-sky-600 dark:text-sky-400">{{ $deepAnalytics['shift_summary']['masuk_hari_ini'] }}</span>
            </div>
            <div class="p-3 rounded-lg bg-surface-muted/50 border border-border/50">
                <span class="text-[10px] font-bold uppercase text-text-muted block">Selesai Hari Ini</span>
                <span class="text-lg font-black font-mono text-emerald-600 dark:text-emerald-400">{{ $deepAnalytics['shift_summary']['selesai_hari_ini'] }}</span>
            </div>
            <div class="p-3 rounded-lg bg-surface-muted/50 border border-border/50">
                <span class="text-[10px] font-bold uppercase text-text-muted block">Eskalasi FOP</span>
                <span class="text-lg font-black font-mono text-indigo-600 dark:text-indigo-400">{{ $deepAnalytics['shift_summary']['eskalasi_fop_hari_ini'] }}</span>
            </div>
            <div class="p-3 rounded-lg bg-surface-muted/50 border border-border/50">
                <span class="text-[10px] font-bold uppercase text-text-muted block">Sisa Antrean NOC</span>
                <span class="text-lg font-black font-mono text-amber-600 dark:text-amber-400">{{ $deepAnalytics['shift_summary']['sisa_antrean_noc'] }}</span>
            </div>
            <div class="p-3 rounded-lg bg-surface-muted/50 border border-border/50">
                <span class="text-[10px] font-bold uppercase text-text-muted block">Breach Hari Ini</span>
                <span class="text-lg font-black font-mono {{ $deepAnalytics['shift_summary']['breach_hari_ini'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-text-muted' }}">{{ $deepAnalytics['shift_summary']['breach_hari_ini'] }}</span>
            </div>
            <div class="p-3 rounded-lg bg-surface-muted/50 border border-border/50">
                <span class="text-[10px] font-bold uppercase text-text-muted block">Top Issue Hari Ini</span>
                <span class="text-xs font-bold text-text-main truncate block mt-1" title="{{ $deepAnalytics['shift_summary']['top_issue_today'] }}">
                    {{ $deepAnalytics['shift_summary']['top_issue_today'] }}
                </span>
            </div>
        </div>
    </div>

</div>
