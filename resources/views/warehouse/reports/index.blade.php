@extends('layouts.app')

@section('title', 'Laporan Gudang - Whusnet Operasional')
@section('page_title', 'Laporan Gudang')

@section('content')

<x-warehouse.header active="reports" title="Laporan Gudang" subtitle="Agregat pergerakan barang & kerugian per periode — data mentahnya udah tercatat di ledger, ini cuma disusun ulang biar gampang dibaca. Bukan realtime, muat ulang buat data terbaru." />

<div x-data="{ activeTab: 'movement' }">
    <!-- Filter -->
    <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-5 mb-6 shadow-xs">
        <form action="{{ route('warehouse.reports.index') }}" method="GET" class="flex flex-wrap items-end gap-3">
            <div>
                <label for="period" class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Periode (Bulan)</label>
                <input type="month" name="period" id="period" value="{{ $period }}" class="px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
            </div>
            <div class="w-full sm:w-64">
                <label for="pop_id" class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Gudang</label>
                <select name="pop_id" id="pop_id" class="w-full px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                    <option value="">— Semua Gudang Terjangkau —</option>
                    @foreach($pops as $pop)
                    <option value="{{ $pop->id }}" {{ (string) $popFilter === (string) $pop->id ? 'selected' : '' }}>{{ $pop->name }} ({{ strtoupper($pop->type) }})</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 text-white text-xs font-semibold rounded-xl shadow-xs transition-colors cursor-pointer">
                <span>Terapkan</span>
            </button>
        </form>

        <!-- Segmented Tab Switcher -->
        <div class="mt-4 pt-3.5 border-t border-slate-100 dark:border-slate-700/60 flex items-center gap-2">
            <button @click="activeTab = 'movement'" type="button"
                    :class="activeTab === 'movement' ? 'bg-sky-500 text-white shadow-xs shadow-sky-500/25' : 'bg-slate-100 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                <span>Pergerakan Barang</span>
            </button>
            <button @click="activeTab = 'adjustment'" type="button"
                    :class="activeTab === 'adjustment' ? 'bg-sky-500 text-white shadow-xs shadow-sky-500/25' : 'bg-slate-100 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>Kerugian (Rusak/Hilang/dll)</span>
            </button>
        </div>
    </div>

    <!-- TAB 1: PERGERAKAN BARANG -->
    <div x-show="activeTab === 'movement'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl overflow-hidden shadow-xs">
            @if(empty($movementRows))
            <div class="p-16 text-center">
                <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Gak ada pergerakan barang di periode ini</h4>
                <p class="text-xs text-slate-400 mt-1">Coba ganti bulan atau gudang di filter.</p>
            </div>
            @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800/60">
                        <tr>
                            <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Gudang</th>
                            <th class="px-6 py-3.5 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Barang Masuk</th>
                            <th class="px-6 py-3.5 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Transfer Masuk</th>
                            <th class="px-6 py-3.5 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Transfer Keluar</th>
                            <th class="px-6 py-3.5 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Keluar ke Teknisi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700/50">
                        @foreach($movementRows as $row)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/30">
                            <td class="px-6 py-3.5 text-sm font-semibold text-slate-800 dark:text-slate-200">
                                {{ $row['pop']->name }} <span class="text-[10px] uppercase text-slate-400">({{ $row['pop']->type }})</span>
                            </td>
                            <td class="px-6 py-3.5 text-right font-mono text-sm text-emerald-600 dark:text-emerald-400">{{ rtrim(rtrim(number_format($row['receive'], 2, ',', '.'), '0'), ',') }}</td>
                            <td class="px-6 py-3.5 text-right font-mono text-sm text-sky-600 dark:text-sky-400">{{ rtrim(rtrim(number_format($row['transfer_in'], 2, ',', '.'), '0'), ',') }}</td>
                            <td class="px-6 py-3.5 text-right font-mono text-sm text-slate-500 dark:text-slate-400">{{ rtrim(rtrim(number_format($row['transfer_out'], 2, ',', '.'), '0'), ',') }}</td>
                            <td class="px-6 py-3.5 text-right font-mono text-sm text-indigo-600 dark:text-indigo-400">{{ rtrim(rtrim(number_format($row['issue'], 2, ',', '.'), '0'), ',') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>

    <!-- TAB 2: KERUGIAN -->
    <div x-show="activeTab === 'adjustment'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl overflow-hidden shadow-xs">
            @if(empty($adjustmentRows))
            <div class="p-16 text-center">
                <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Gak ada kerugian tercatat di periode ini</h4>
                <p class="text-xs text-slate-400 mt-1">Coba ganti bulan atau gudang di filter.</p>
            </div>
            @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800/60">
                        <tr>
                            <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Kategori</th>
                            <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Gudang / Sumber</th>
                            <th class="px-6 py-3.5 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Jumlah Transaksi</th>
                            <th class="px-6 py-3.5 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Qty</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700/50">
                        @foreach($adjustmentRows as $row)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/30">
                            <td class="px-6 py-3.5 text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $row['reason_label'] }}</td>
                            <td class="px-6 py-3.5 text-sm text-slate-500 dark:text-slate-400">{{ $row['pop_label'] }}</td>
                            <td class="px-6 py-3.5 text-right font-mono text-sm text-slate-700 dark:text-slate-300">{{ $row['count'] }}</td>
                            <td class="px-6 py-3.5 text-right font-mono text-sm text-rose-600 dark:text-rose-400">{{ rtrim(rtrim(number_format($row['total_qty'], 2, ',', '.'), '0'), ',') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="px-6 py-3 text-[11px] text-slate-400 border-t border-slate-100 dark:border-slate-700/60">"— (Custody Teknisi)" = kerugian dilaporkan saat barang di tangan teknisi, bukan di gudang manapun saat itu — gak bisa diatribusi ke cabang tertentu, tapi tetap tercatat & dipantau di sini.</p>
            @endif
        </div>
    </div>
</div>

@endsection
