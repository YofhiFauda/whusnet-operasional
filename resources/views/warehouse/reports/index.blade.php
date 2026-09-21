@extends('layouts.app')

@section('title', 'Laporan Gudang - Whusnet Operasional')
@section('page_title', 'Laporan Gudang')

@section('content')

<x-warehouse.header active="reports" title="Laporan Gudang" subtitle="Agregat pergerakan barang & kerugian per periode — data mentahnya udah tercatat di ledger, ini cuma disusun ulang biar gampang dibaca. Bukan realtime, muat ulang buat data terbaru." />

<div x-data="{ activeTab: 'movement' }">
    <!-- Filter (Naked Filter Bar) -->
    <div class="mb-5">
        <form action="{{ route('warehouse.reports.index') }}" method="GET" class="flex flex-wrap items-end gap-3">
            <div>
                <label for="period" class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Periode (Bulan)</label>
                <input type="month" name="period" id="period" value="{{ $period }}" class="px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
            </div>
            <div class="w-full sm:w-64">
                <label for="pop_id" class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Gudang</label>
                <select name="pop_id" id="pop_id" class="w-full px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                    <option value="">— Semua Gudang Terjangkau —</option>
                    @foreach($pops as $pop)
                    <option value="{{ $pop->id }}" {{ (string) $popFilter === (string) $pop->id ? 'selected' : '' }}>{{ $pop->name }} ({{ strtoupper($pop->type) }})</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 text-white text-xs font-semibold rounded-lg shadow-xs transition-colors cursor-pointer">
                <span>Terapkan</span>
            </button>
            <a href="{{ route('warehouse.reports.export', ['period' => $period, 'pop_id' => $popFilter]) }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg shadow-xs transition-colors cursor-pointer">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Download Excel</span>
            </a>
        </form>

        <!-- Segmented Tab Switcher -->
        <div class="mt-4 pt-3.5 border-t border-slate-100 dark:border-slate-700/60 flex items-center gap-2">
            <button @click="activeTab = 'movement'" type="button"
                    :class="activeTab === 'movement' ? 'bg-sky-500 text-white shadow-xs shadow-sky-500/25' : 'bg-slate-100 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-bold transition-all cursor-pointer">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                <span>Pergerakan Barang</span>
            </button>
            <button @click="activeTab = 'adjustment'" type="button"
                    :class="activeTab === 'adjustment' ? 'bg-sky-500 text-white shadow-xs shadow-sky-500/25' : 'bg-slate-100 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-bold transition-all cursor-pointer">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>Kerugian (Rusak/Hilang/dll)</span>
            </button>
        </div>
    </div>

    <!-- KPI Ringkasan Periode — hitung JUMLAH TRANSAKSI, bukan qty (lihat
         komentar WarehouseReportController::index() soal kenapa). -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-4 shadow-xs">
            <span class="block text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Barang Masuk</span>
            <div class="mt-1 flex items-baseline gap-1.5">
                <span class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400">{{ $kpi['receive_count'] }}</span>
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">transaksi</span>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-4 shadow-xs">
            <span class="block text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Transfer Keluar</span>
            <div class="mt-1 flex items-baseline gap-1.5">
                <span class="text-2xl font-extrabold text-sky-600 dark:text-sky-400">{{ $kpi['transfer_out_count'] }}</span>
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">transaksi</span>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-4 shadow-xs">
            <span class="block text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Keluar ke Teknisi</span>
            <div class="mt-1 flex items-baseline gap-1.5">
                <span class="text-2xl font-extrabold text-indigo-600 dark:text-indigo-400">{{ $kpi['issue_count'] }}</span>
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">transaksi</span>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800/90 border {{ $kpi['adjustment_count'] > 0 ? 'border-rose-200 dark:border-rose-900/60' : 'border-slate-200/80 dark:border-slate-700/80' }} rounded-lg p-4 shadow-xs">
            <span class="block text-[11px] font-bold {{ $kpi['adjustment_count'] > 0 ? 'text-rose-500 dark:text-rose-400' : 'text-slate-400 dark:text-slate-500' }} uppercase tracking-wider">Total Kerugian</span>
            <div class="mt-1 flex items-baseline gap-1.5">
                <span class="text-2xl font-extrabold {{ $kpi['adjustment_count'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-800 dark:text-slate-100' }}">{{ $kpi['adjustment_count'] }}</span>
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">kejadian</span>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800/90 border {{ $kpi['total_loss_value'] > 0 ? 'border-rose-200 dark:border-rose-900/60' : 'border-slate-200/80 dark:border-slate-700/80' }} rounded-lg p-4 shadow-xs">
            <span class="block text-[11px] font-bold {{ $kpi['total_loss_value'] > 0 ? 'text-rose-500 dark:text-rose-400' : 'text-slate-400 dark:text-slate-500' }} uppercase tracking-wider">Nilai Rugi (Rusak+Hilang)</span>
            <div class="mt-1 flex items-baseline gap-1.5">
                <span class="text-lg font-extrabold {{ $kpi['total_loss_value'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-800 dark:text-slate-100' }}">Rp {{ number_format($kpi['total_loss_value'], 0, ',', '.') }}</span>
            </div>
        </div>
    </div>

    <!-- TAB 1: PERGERAKAN BARANG -->
    <div x-show="activeTab === 'movement'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
        @if(!empty($movementCounts))
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-5 shadow-xs mb-4">
            <div class="flex items-center justify-between mb-1">
                <h3 class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider">Jumlah Transaksi per Gudang</h3>
                <span class="text-[10px] text-slate-400">Batang = jumlah transaksi, bukan qty (satuan barang beda-beda per baris)</span>
            </div>
            <div id="movementChartLegend" class="flex flex-wrap gap-3 mb-2 text-[11px] font-semibold"></div>
            <div class="overflow-x-auto scroll-smooth"><svg id="movementChart" role="img" aria-label="Grafik jumlah transaksi pergerakan barang per gudang"></svg></div>
        </div>
        @endif

        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg overflow-hidden shadow-xs">
            @if(empty($movementRows))
            <div class="p-16 text-center">
                <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Gak ada pergerakan barang di periode ini</h4>
                <p class="text-xs text-slate-400 mt-1">Coba ganti bulan atau gudang di filter.</p>
            </div>
            @else
            <div class="overflow-x-auto scroll-smooth">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800/60">
                        <tr>
                            <th class="px-5 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Gudang</th>
                            <th class="px-5 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Barang</th>
                            <th class="px-5 py-3.5 text-right text-xs font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider bg-amber-50/50 dark:bg-amber-950/20">Stok Awal</th>
                            <th class="px-5 py-3.5 text-right text-xs font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">Masuk</th>
                            <th class="px-5 py-3.5 text-right text-xs font-bold text-sky-600 dark:text-sky-400 uppercase tracking-wider">Trf Masuk</th>
                            <th class="px-5 py-3.5 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Trf Keluar</th>
                            <th class="px-5 py-3.5 text-right text-xs font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">Teknisi</th>
                            <th class="px-5 py-3.5 text-right text-xs font-bold text-purple-600 dark:text-purple-400 uppercase tracking-wider bg-purple-50/50 dark:bg-purple-950/20">Stok Akhir</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700/50">
                        @foreach($movementRows as $row)
                        @php $itemCount = count($row['items']); @endphp
                        @foreach($row['items'] as $i => $item)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/30">
                            @if($i === 0)
                            <td class="px-5 py-3.5 text-sm font-semibold text-slate-800 dark:text-slate-200 align-top" rowspan="{{ $itemCount }}">
                                {{ $row['pop']->name }} <span class="block text-[10px] uppercase text-slate-400 font-normal">{{ $row['pop']->type }}</span>
                            </td>
                            @endif
                            <td class="px-5 py-3.5 text-sm text-slate-700 dark:text-slate-300 font-medium">
                                {{ $item['item_name'] }}
                            </td>
                            <td class="px-5 py-3.5 text-right font-mono text-sm bg-amber-50/30 dark:bg-amber-950/10">
                                @if(($item['stok_awal_qty'] ?? 0) > 0)
                                    <span class="font-bold text-amber-700 dark:text-amber-400">{{ rtrim(rtrim(number_format($item['stok_awal_qty'], 2, ',', '.'), '0'), ',') }} {{ $item['unit'] }}</span>
                                    @if(($item['stok_awal_nilai'] ?? 0) > 0)
                                        <span class="block text-[10px] text-slate-400 font-normal">Rp {{ number_format($item['stok_awal_nilai'], 0, ',', '.') }}</span>
                                    @endif
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right font-mono text-sm text-emerald-600 dark:text-emerald-400 font-semibold">
                                {{ $item['receive'] > 0 ? rtrim(rtrim(number_format($item['receive'], 2, ',', '.'), '0'), ',').' '.$item['unit'] : '—' }}
                            </td>
                            <td class="px-5 py-3.5 text-right font-mono text-sm text-sky-600 dark:text-sky-400">
                                {{ $item['transfer_in'] > 0 ? rtrim(rtrim(number_format($item['transfer_in'], 2, ',', '.'), '0'), ',').' '.$item['unit'] : '—' }}
                            </td>
                            <td class="px-5 py-3.5 text-right font-mono text-sm text-slate-500 dark:text-slate-400">
                                {{ $item['transfer_out'] > 0 ? rtrim(rtrim(number_format($item['transfer_out'], 2, ',', '.'), '0'), ',').' '.$item['unit'] : '—' }}
                            </td>
                            <td class="px-5 py-3.5 text-right font-mono text-sm text-indigo-600 dark:text-indigo-400">
                                {{ $item['issue'] > 0 ? rtrim(rtrim(number_format($item['issue'], 2, ',', '.'), '0'), ',').' '.$item['unit'] : '—' }}
                            </td>
                            <td class="px-5 py-3.5 text-right font-mono text-sm bg-purple-50/30 dark:bg-purple-950/10">
                                @if(($item['stok_akhir_qty'] ?? 0) > 0)
                                    <span class="font-bold text-purple-700 dark:text-purple-400">{{ rtrim(rtrim(number_format($item['stok_akhir_qty'], 2, ',', '.'), '0'), ',') }} {{ $item['unit'] }}</span>
                                    @if(($item['stok_akhir_nilai'] ?? 0) > 0)
                                        <span class="block text-[10px] text-slate-400 font-normal">Rp {{ number_format($item['stok_akhir_nilai'], 0, ',', '.') }}</span>
                                    @endif
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>

    <!-- TAB 2: KERUGIAN -->
    <div x-show="activeTab === 'adjustment'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
        @if(!empty($lossChartData))
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-5 shadow-xs mb-4">
            <div class="flex items-center justify-between mb-1">
                <h3 class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider">Jumlah Kejadian Kerugian per Lokasi</h3>
                <span class="text-[10px] text-slate-400">Batang = jumlah kejadian, bukan qty (satuan barang beda-beda per baris)</span>
            </div>
            <div id="lossChartLegend" class="flex flex-wrap gap-3 mb-2 text-[11px] font-semibold"></div>
            <div class="overflow-x-auto scroll-smooth"><svg id="lossChart" role="img" aria-label="Grafik jumlah kejadian kerugian per lokasi"></svg></div>
        </div>
        @endif

        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg overflow-hidden shadow-xs">
            @if(empty($adjustmentRows))
            <div class="p-16 text-center">
                <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Gak ada kerugian tercatat di periode ini</h4>
                <p class="text-xs text-slate-400 mt-1">Coba ganti bulan atau gudang di filter.</p>
            </div>
            @else
            <div class="overflow-x-auto scroll-smooth">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800/60">
                        <tr>
                            <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Kategori</th>
                            <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Gudang / Sumber</th>
                            <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Barang</th>
                            <th class="px-6 py-3.5 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Jumlah Transaksi</th>
                            <th class="px-6 py-3.5 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Qty</th>
                            <th class="px-6 py-3.5 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Nilai Rugi (Rp)</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700/50">
                        @foreach($adjustmentRows as $row)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/30">
                            <td class="px-6 py-3.5 text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $row['reason_label'] }}</td>
                            <td class="px-6 py-3.5 text-sm text-slate-500 dark:text-slate-400">{{ $row['pop_label'] }}</td>
                            <td class="px-6 py-3.5 text-sm text-slate-500 dark:text-slate-400">{{ $row['item_name'] }}</td>
                            <td class="px-6 py-3.5 text-right font-mono text-sm text-slate-700 dark:text-slate-300">{{ $row['count'] }}</td>
                            <td class="px-6 py-3.5 text-right font-mono text-sm text-rose-600 dark:text-rose-400">{{ rtrim(rtrim(number_format($row['total_qty'], 2, ',', '.'), '0'), ',') }} {{ $row['unit'] }}</td>
                            <td class="px-6 py-3.5 text-right font-mono text-sm text-rose-600 dark:text-rose-400">{{ $row['loss_value'] !== null ? 'Rp '.number_format($row['loss_value'], 0, ',', '.') : '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="px-6 py-3 text-[11px] text-slate-400 border-t border-slate-100 dark:border-slate-700/60">"— (Custody Teknisi)" = kerugian dilaporkan saat barang di tangan teknisi, bukan di gudang manapun saat itu — gak bisa diatribusi ke cabang tertentu, tapi tetap tercatat & dipantau di sini. Nilai Rugi cuma dihitung buat kategori Rusak & Hilang (harga dari input RECEIVE terakhir barang itu) — "—" berarti belum pernah ada RECEIVE berharga buat barang itu, atau kategorinya bukan Rusak/Hilang.</p>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
(function(){
    "use strict";

    // Palet kategorikal tervalidasi (node validate_palette.js — PASS light
    // #ffffff & dark #1e293b, kedua mode) — bukan diambil asal, lihat
    // dataviz skill § color-formula. Urutan FIXED, jangan diacak per baris.
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

        var W = Math.max(560, rows.length * 130 + 80);
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
            catLbl.textContent = full.length > 14 ? full.slice(0,13) + "…" : full;
            var titleFull = make("title", {}); titleFull.textContent = full;
            catLbl.appendChild(titleFull);
            svgEl.appendChild(catLbl);
        });
    }

    var movementRows = @json($movementCounts);
    drawGroupedBar(
        document.getElementById("movementChart"),
        document.getElementById("movementChartLegend"),
        movementRows, "pop_name",
        [
            {key:"receive", label:"Barang Masuk"},
            {key:"transfer_in", label:"Transfer Masuk"},
            {key:"transfer_out", label:"Transfer Keluar"},
            {key:"issue", label:"Keluar ke Teknisi"}
        ]
    );

    var lossRows = @json($lossChartData);
    var lossSeries = [
        {key:"lost", label:"Hilang"},
        {key:"damaged", label:"Rusak"},
        {key:"quarantine", label:"Karantina"},
        {key:"shrinkage_on_return", label:"Selisih Retur"},
        {key:"stock_opname_diff", label:"Selisih Opname"}
    ].filter(function(s){ return lossRows.some(function(r){ return (r[s.key] || 0) > 0; }); });

    drawGroupedBar(
        document.getElementById("lossChart"),
        document.getElementById("lossChartLegend"),
        lossRows, "pop_label",
        lossSeries.length ? lossSeries : [{key:"lost", label:"Hilang"}]
    );
})();
</script>
@endpush

@endsection
