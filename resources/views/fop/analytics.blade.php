@extends('layouts.app')

@section('title', 'Dashboard Analitik FOP')
@section('page_title', 'Dashboard Analitik FOP')
@section('breadcrumb_parent', 'Dashboard')
@section('breadcrumb_parent_url', '/')

@section('content')
<div class="flex flex-col gap-6 max-w-screen-2xl mx-auto font-sans text-text-main">

    {{-- ══ Page Header ══════════════════════════════════════════════ --}}
    <div class="page-header flex flex-col gap-1.5 md:flex-row md:items-center md:justify-between mb-2">
        <div class="page-header-left">
            <div class="flex items-center gap-2">
                <svg class="h-5 w-5 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <h1 class="text-xl font-bold text-text-main leading-tight tracking-tight font-sans">Dashboard Analitik FOP</h1>
            </div>
            <p class="text-xs text-text-muted mt-0.5 font-sans">Pola & performa lintas periode — alat kerja, wilayah, teknisi, backlog, durasi pengerjaan. Beda dari FOP Dashboard (operasional harian): halaman ini buat evaluasi mingguan/bulanan, bukan realtime shift.</p>
        </div>
    </div>

    {{-- ══ Filter ═══════════════════════════════════════════════════ --}}
    <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-4 shadow-xs">
        <form action="{{ route('fop.analytics') }}" method="GET" class="flex flex-wrap items-end gap-3">
            <div>
                <label for="granularity" class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Granularitas</label>
                <select name="granularity" id="granularity" class="px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                    <option value="harian" {{ $granularity === 'harian' ? 'selected' : '' }}>Harian (30 hari)</option>
                    <option value="mingguan" {{ $granularity === 'mingguan' ? 'selected' : '' }}>Mingguan (12 minggu)</option>
                    <option value="bulanan" {{ $granularity === 'bulanan' ? 'selected' : '' }}>Bulanan (12 bulan)</option>
                    <option value="tahunan" {{ $granularity === 'tahunan' ? 'selected' : '' }}>Tahunan (5 tahun)</option>
                </select>
            </div>
            <div>
                <label for="from" class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Dari</label>
                <input type="date" name="from" id="from" value="{{ $periodFrom }}" class="px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
            </div>
            <div>
                <label for="to" class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Sampai</label>
                <input type="date" name="to" id="to" value="{{ $periodTo }}" class="px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
            </div>
            <div class="w-full sm:w-64">
                <label for="pop_id" class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">POP</label>
                <select name="pop_id" id="pop_id" class="w-full px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                    <option value="">— Semua POP Terjangkau —</option>
                    @foreach($pops as $pop)
                    <option value="{{ $pop->id }}" {{ (string) $popFilter === (string) $pop->id ? 'selected' : '' }}>{{ $pop->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 text-white text-xs font-semibold rounded-lg shadow-xs transition-colors cursor-pointer">
                <span>Terapkan</span>
            </button>
        </form>
        <p class="mt-2 text-[10px] text-slate-400">Isi "Dari"/"Sampai" buat rentang custom (mengalahkan granularitas), atau kosongkan lalu pilih granularitas buat rentang default otomatis.</p>
    </div>

    {{-- ══ KPI Strip ════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="metric-card status-info">
            <div class="metric-card-label"><span>Pemakaian Alat Kerja</span></div>
            <div class="metric-card-value-container">
                <p class="metric-card-value">{{ $toolTotal }}</p>
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold font-sans uppercase tracking-wider bg-slate-100 dark:bg-slate-700/50 text-slate-500 dark:text-slate-400">{{ $toolTypesUsed }}/{{ $toolTypesActive }} jenis</span>
            </div>
            <div class="mt-1">@include('fop.partials.analytics-delta-badge', ['delta' => $periodComparison['tool_total']['delta_pct'], 'invert' => false])</div>
        </div>
        <div class="metric-card {{ $backlogStats['over_sla'] > 0 ? 'status-error' : 'status-info' }}">
            <div class="metric-card-label"><span>Backlog Belum Dikerjakan</span></div>
            <div class="metric-card-value-container">
                <p class="metric-card-value">{{ $backlogStats['total'] }}</p>
                @if($backlogStats['over_sla'] > 0)
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold font-sans uppercase tracking-wider bg-error-bg text-error border border-error-border">{{ $backlogStats['over_sla'] }} Lewat SLA</span>
                @endif
            </div>
            <p class="mt-1 text-[9px] text-slate-300 dark:text-slate-600">Snapshot live, gak dibandingkan periode</p>
        </div>
        <div class="metric-card status-info">
            <div class="metric-card-label"><span>Rata-Rata Durasi Solving</span></div>
            <p class="metric-card-value">{{ $durationAverage !== null ? number_format($durationAverage, 0, ',', '.').' mnt' : '—' }}</p>
            <div class="mt-1">@include('fop.partials.analytics-delta-badge', ['delta' => $periodComparison['duration_average']['delta_pct'], 'invert' => true])</div>
        </div>
        <div class="metric-card status-info">
            <div class="metric-card-label"><span>Task Dibatalkan (Proksi Gagal)</span></div>
            <p class="metric-card-value">{{ $cancelledTotal }}</p>
            <div class="mt-1">@include('fop.partials.analytics-delta-badge', ['delta' => $periodComparison['cancelled_total']['delta_pct'], 'invert' => true])</div>
        </div>
    </div>

    {{-- ══ Section 1 — Barang Gudang ═════════════════════════════════ --}}
    <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-5 shadow-xs">
        <h3 class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-3">Ranking Barang Gudang Terpakai</h3>
        @if(empty($materialRanking))
        <p class="text-xs text-slate-400 py-6 text-center">Gak ada pemakaian barang gudang tercatat di periode ini.</p>
        @else
        <div id="materialRankingChartLegend" class="flex flex-wrap gap-3 mb-2 text-[11px] font-semibold"></div>
        <div class="overflow-x-auto scroll-smooth"><svg id="materialRankingChart" role="img" aria-label="Grafik ranking barang gudang terpakai"></svg></div>
        @endif
    </div>

    <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-5 shadow-xs">
        <div class="flex items-center justify-between mb-1">
            <h3 class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider">Tren Pemakaian Barang Gudang (Top 5)</h3>
            <span class="text-[10px] text-slate-400">Granularitas ikut filter di atas</span>
        </div>
        @if(empty($materialTrendChart['rows']))
        <p class="text-xs text-slate-400 py-6 text-center">Gak ada tren pemakaian barang gudang tercatat di periode ini.</p>
        @else
        <div id="materialTrendChartLegend" class="flex flex-wrap gap-3 mb-2 text-[11px] font-semibold"></div>
        <div class="overflow-x-auto scroll-smooth"><svg id="materialTrendChart" role="img" aria-label="Grafik tren pemakaian barang gudang per periode"></svg></div>
        @endif
    </div>

    <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-5 shadow-xs">
        <div class="flex items-center justify-between mb-1 flex-wrap gap-2">
            <h3 class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider">Modem/ONT Terpasang — Instalasi vs Maintenance</h3>
            <div class="flex items-center gap-3 text-[11px] font-semibold">
                <span class="text-slate-500 dark:text-slate-400">Instalasi: <span class="text-slate-800 dark:text-slate-100">{{ $modemInstalasiTotal }}</span></span>
                <span class="text-slate-500 dark:text-slate-400">Maintenance: <span class="text-slate-800 dark:text-slate-100">{{ $modemMaintenanceTotal }}</span></span>
            </div>
        </div>
        @if(empty($modemRanking))
        <p class="text-xs text-slate-400 py-6 text-center">Gak ada modem/ONT terpasang tercatat di periode ini.</p>
        @else
        <div id="modemChartLegend" class="flex flex-wrap gap-3 mb-2 text-[11px] font-semibold"></div>
        <div class="overflow-x-auto scroll-smooth"><svg id="modemChart" role="img" aria-label="Grafik modem terpasang per model, instalasi vs maintenance"></svg></div>
        @if($modemMaintenanceTotal === 0)
        <p class="text-[10px] text-slate-400 mt-2">Maintenance masih 0 — jalur ganti modem saat maintenance belum mencatat SN di sistem, bukan berarti gak pernah terjadi di lapangan.</p>
        @endif
        @endif
    </div>

    {{-- ══ Section 2 — Wilayah ══════════════════════════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg overflow-hidden shadow-xs">
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700/60">
                <h3 class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider">Pemasangan per Kecamatan</h3>
            </div>
            @include('fop.partials.analytics-region-table', ['rows' => $regionInstallation, 'total' => $regionInstallationTotal, 'emptyText' => 'Gak ada pemasangan selesai di periode ini.'])
        </div>
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg overflow-hidden shadow-xs">
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700/60">
                <h3 class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider">Task Maintenance per Kecamatan</h3>
                <p class="text-[10px] text-slate-400 mt-0.5">Proksi "komplain" — belum ada tabel komplain terpisah.</p>
            </div>
            @include('fop.partials.analytics-region-table', ['rows' => $regionMaintenance, 'total' => $regionMaintenanceTotal, 'emptyText' => 'Gak ada task maintenance di periode ini.'])
        </div>
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg overflow-hidden shadow-xs">
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700/60">
                <h3 class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider">Task Dibatalkan per Kecamatan</h3>
                <p class="text-[10px] text-slate-400 mt-0.5">Proksi "task gagal" — belum ada status "gagal" tersendiri.</p>
            </div>
            @include('fop.partials.analytics-region-table', ['rows' => $regionCancelled, 'total' => $regionCancelledTotal, 'emptyText' => 'Gak ada task dibatalkan di periode ini.'])
        </div>
    </div>

    {{-- ══ Section 3 — Teknisi ══════════════════════════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg overflow-hidden shadow-xs">
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700/60">
                <h3 class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider">Beban Tugas Teknisi</h3>
                <p class="text-[10px] text-slate-400 mt-0.5">Jumlah assignment (semua status), tim &gt;1 orang dihitung per orang.</p>
            </div>
            @include('fop.partials.analytics-leaderboard-table', ['rows' => $workloadLeaderboard, 'total' => $workloadLeaderboardTotal, 'emptyText' => 'Gak ada assignment tercatat di periode ini.'])
        </div>
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg overflow-hidden shadow-xs">
            <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700/60">
                <h3 class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider">Solving Terbanyak</h3>
                @if($completedByWarning)
                <p class="text-[10px] text-amber-600 dark:text-amber-400 mt-0.5">⚠ Rentang mencakup data sebelum {{ \Illuminate\Support\Carbon::parse('2026-08-07')->translatedFormat('d M Y') }} — kolom pencatat penyelesai baru mulai dilacak sejak tanggal itu, angka historis sebelumnya mungkin belum lengkap.</p>
                @endif
            </div>
            @include('fop.partials.analytics-leaderboard-table', ['rows' => $solvingLeaderboard, 'total' => $solvingLeaderboardTotal, 'emptyText' => 'Gak ada task selesai tercatat di periode ini.'])
        </div>
    </div>

    {{-- ══ Section 4 — Backlog ══════════════════════════════════════ --}}
    <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg overflow-hidden shadow-xs">
        <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700/60">
            <h3 class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider">Backlog Task Belum Dikerjakan</h3>
            <p class="text-[10px] text-slate-400 mt-0.5">Draft/Terjadwal/Pending, terlama di atas · {{ $backlogStats['total'] }} total · <strong>gak ikut filter periode di atas</strong>, selalu tampilkan seluruh antrean aktif.</p>
        </div>
        @if($backlog->isEmpty())
        <div class="p-16 text-center">
            <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Gak ada backlog</h4>
            <p class="text-xs text-slate-400 mt-1">Semua task sudah tertangani.</p>
        </div>
        @else
        <div class="overflow-x-auto scroll-smooth">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-800/60">
                    <tr>
                        <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Task</th>
                        <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Pelanggan</th>
                        <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Tipe</th>
                        <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3.5 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Umur</th>
                        <th class="px-6 py-3.5 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">SLA</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700/50">
                    @foreach($backlog as $row)
                    <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/30">
                        <td class="px-6 py-3.5 text-sm font-mono text-slate-700 dark:text-slate-300">{{ $row['task_number'] ?? '—' }}</td>
                        <td class="px-6 py-3.5 text-sm text-slate-700 dark:text-slate-300">{{ $row['customer_name'] }}</td>
                        <td class="px-6 py-3.5 text-sm text-slate-500 dark:text-slate-400">{{ $row['task_type_label'] }}</td>
                        <td class="px-6 py-3.5 text-sm text-slate-500 dark:text-slate-400">{{ $row['status_label'] }}</td>
                        <td class="px-6 py-3.5 text-right font-mono text-sm text-slate-600 dark:text-slate-300">{{ $row['umur_jam'] }} jam</td>
                        <td class="px-6 py-3.5 text-right">
                            @if($row['over_sla'])
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-error-bg text-error border border-error-border">Lewat SLA</span>
                            @elseif($row['sla_deadline'])
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-slate-100 dark:bg-slate-700/50 text-slate-500 dark:text-slate-400">Dalam SLA</span>
                            @else
                            <span class="text-[10px] text-slate-300">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3 border-t border-slate-100 dark:border-slate-700/60">
            {{ $backlog->links() }}
        </div>
        @endif
    </div>

    {{-- ══ Section 5 — Durasi Terlama ═══════════════════════════════ --}}
    <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-5 shadow-xs">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider">Top 20 Task Durasi Pengerjaan Terlama</h3>
            @if($durationAverage !== null)
            <span class="text-[10px] text-slate-400">Baseline rata-rata periode: {{ number_format($durationAverage, 0, ',', '.') }} menit</span>
            @endif
        </div>
        @if(empty($durationRanking))
        <p class="text-xs text-slate-400 py-6 text-center">Gak ada task selesai dengan durasi tercatat di periode ini.</p>
        @else
        <div id="durationChartLegend" class="flex flex-wrap gap-3 mb-2 text-[11px] font-semibold"></div>
        <div class="overflow-x-auto scroll-smooth"><svg id="durationChart" role="img" aria-label="Grafik ranking durasi pengerjaan terlama"></svg></div>
        @endif
    </div>
</div>

@push('scripts')
<script>
(function(){
    "use strict";

    // Palet kategorikal tervalidasi — sama sumber dengan warehouse/reports/index.blade.php
    // (node validate_palette.js — PASS light #ffffff & dark #1e293b).
    var PALETTE_LIGHT = ["#2a78d6", "#eb6834", "#1baf7a", "#eda100", "#e87ba4"];
    var PALETTE_DARK = ["#3987e5", "#d95926", "#199e70", "#c98500", "#d55181"];
    function isDark(){
        var stamp = document.documentElement.getAttribute("data-theme");
        if(stamp === "dark") return true;
        if(stamp === "light") return false;
        return window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches;
    }
    var palette = isDark() ? PALETTE_DARK : PALETTE_LIGHT;
    var ink = isDark() ? "#cbd5e1" : "#475569";
    var grid = isDark() ? "#334155" : "#e2e8f0";
    var axis = isDark() ? "#475569" : "#cbd5e1";

    function drawGroupedBar(svgEl, legendEl, rows, catKey, series){
        if(!svgEl || rows.length === 0) return;

        var ns = "http://www.w3.org/2000/svg";
        function make(tag, attrs){
            var e = document.createElementNS(ns, tag);
            Object.keys(attrs).forEach(function(k){ e.setAttribute(k, attrs[k]); });
            return e;
        }

        if(legendEl){
            legendEl.innerHTML = "";
            series.forEach(function(s, i){
                var item = document.createElement("span");
                item.style.display = "inline-flex"; item.style.alignItems = "center"; item.style.gap = "5px"; item.style.color = ink;
                var dot = document.createElement("span");
                dot.style.width = "8px"; dot.style.height = "8px"; dot.style.borderRadius = "2px"; dot.style.background = palette[i % palette.length];
                item.appendChild(dot);
                item.appendChild(document.createTextNode(s.label));
                legendEl.appendChild(item);
            });
        }

        // Lebar chart ngikutin lebar card (fit-width) — bukan floor tetap
        // 560px. Card ini sekarang full-width (bukan grid 2-kolom lagi),
        // jadi kalau barnya dikit, chart WAJIB ngisi seluruh lebar card,
        // bukan nyisa ruang kosong di kanan. Scroll horizontal (wrapper
        // `overflow-x-auto`) baru muncul kalau barnya beneran kebanyakan
        // sampai lebih lebar dari card.
        var containerWidth = (svgEl.parentElement && svgEl.parentElement.clientWidth) || 560;
        var W = Math.max(containerWidth, rows.length * 90 + 80);
        var H = 240, padL = 34, padR = 12, padT = 10, padB = 40;
        var plotW = W - padL - padR, plotH = H - padT - padB;

        var maxVal = 1;
        rows.forEach(function(r){ series.forEach(function(s){ maxVal = Math.max(maxVal, r[s.key] || 0); }); });
        var niceMax = Math.ceil(maxVal / 5) * 5 || 5;

        svgEl.setAttribute("viewBox", "0 0 " + W + " " + H);
        svgEl.setAttribute("width", W); svgEl.setAttribute("height", H);
        svgEl.innerHTML = "";

        var ticks = 4;
        for(var t=0; t<=ticks; t++){
            var val = Math.round(niceMax * t / ticks);
            var y = padT + plotH - (val / niceMax) * plotH;
            svgEl.appendChild(make("line", {x1:padL, x2:W-padR, y1:y, y2:y, stroke:grid, "stroke-width":1}));
            var lbl = make("text", {x:padL-6, y:y+4, "text-anchor":"end", "font-size":10, fill:ink});
            lbl.textContent = val;
            svgEl.appendChild(lbl);
        }
        svgEl.appendChild(make("line", {x1:padL, x2:W-padR, y1:padT+plotH, y2:padT+plotH, stroke:axis, "stroke-width":1.5}));

        var groupW = plotW / rows.length;
        var barGap = 3;
        var barW = Math.min(26, (groupW - 14) / series.length - barGap);

        rows.forEach(function(row, ri){
            var groupX = padL + ri * groupW;
            series.forEach(function(s, si){
                var val = row[s.key] || 0;
                var barH = (val / niceMax) * plotH;
                var x = groupX + 7 + si * (barW + barGap);
                var y = padT + plotH - barH;
                var rect = make("rect", {x:x, y:y, width:barW, height:Math.max(barH,0), rx:3, ry:3, fill:palette[si % palette.length]});
                rect.style.cursor = "pointer";
                var titleEl = make("title", {});
                titleEl.textContent = row[catKey] + " — " + s.label + ": " + val;
                rect.appendChild(titleEl);
                svgEl.appendChild(rect);
                if(val > 0){
                    var vlabel = make("text", {x:x + barW/2, y:y-4, "text-anchor":"middle", "font-size":10, "font-weight":700, fill:ink});
                    vlabel.textContent = val;
                    svgEl.appendChild(vlabel);
                }
            });
            var catLbl = make("text", {x:groupX + groupW/2, y:padT+plotH+18, "text-anchor":"middle", "font-size":11, "font-weight":600, fill:ink});
            var full = String(row[catKey]);
            catLbl.textContent = full.length > 12 ? full.slice(0,11) + "…" : full;
            var titleFull = make("title", {}); titleFull.textContent = full;
            catLbl.appendChild(titleFull);
            svgEl.appendChild(catLbl);
        });
    }

    var materialRankingRows = @json($materialRanking);
    drawGroupedBar(
        document.getElementById("materialRankingChart"),
        document.getElementById("materialRankingChartLegend"),
        materialRankingRows, "item_name",
        [{key:"total", label:"Jumlah Pemakaian"}]
    );

    var materialTrendRows = @json($materialTrendChart['rows']);
    var materialTrendSeries = @json($materialTrendChart['series']);
    drawGroupedBar(
        document.getElementById("materialTrendChart"),
        document.getElementById("materialTrendChartLegend"),
        materialTrendRows, "bucket",
        materialTrendSeries
    );

    var modemRows = @json($modemRanking);
    drawGroupedBar(
        document.getElementById("modemChart"),
        document.getElementById("modemChartLegend"),
        modemRows, "item_name",
        [{key:"instalasi", label:"Instalasi"}, {key:"maintenance", label:"Maintenance"}]
    );

    var durationRows = @json($durationRanking);
    drawGroupedBar(
        document.getElementById("durationChart"),
        document.getElementById("durationChartLegend"),
        durationRows.map(function(r){ return {task_number: r.task_number || "—", durasi_menit: r.durasi_menit}; }),
        "task_number",
        [{key:"durasi_menit", label:"Durasi (menit)"}]
    );
})();
</script>
@endpush

@endsection
