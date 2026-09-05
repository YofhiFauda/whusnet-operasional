@extends('layouts.app')

@section('title', 'Dashboard Gudang & Logistik - Whusnet Operasional')
@section('page_title', 'Dashboard Gudang')

@section('content')

<x-warehouse.header active="dashboard" />

<!-- 4 Elevated Metric KPI Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <!-- Total Gudang -->
    <div class="relative overflow-hidden bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-5 shadow-xs transition-all hover:shadow-md hover:border-slate-300 dark:hover:border-slate-600">
        <div class="flex items-center justify-between">
            <div>
                <span class="block text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Gudang Terhubung</span>
                <div class="mt-1 flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-slate-800 dark:text-slate-100 tracking-tight data-text">{{ $stats['total_gudang'] }}</span>
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Titik POP</span>
                </div>
            </div>
            <div class="w-12 h-12 rounded-xl bg-sky-50 dark:bg-sky-950/50 border border-sky-100 dark:border-sky-800/60 flex items-center justify-center text-sky-600 dark:text-sky-400 shrink-0">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </div>
        </div>
        <div class="mt-3.5 pt-3 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
            <span>Pusat &amp; Cabang</span>
            <span class="inline-flex items-center gap-1 font-medium text-sky-600 dark:text-sky-400">
                <span>POP Scope Aktif</span>
            </span>
        </div>
    </div>

    <!-- Barang Ke-track -->
    <div class="relative overflow-hidden bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-5 shadow-xs transition-all hover:shadow-md hover:border-slate-300 dark:hover:border-slate-600">
        <div class="flex items-center justify-between">
            <div>
                <span class="block text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">SKU Barang Aktif</span>
                <div class="mt-1 flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-slate-800 dark:text-slate-100 tracking-tight data-text">{{ $stats['total_barang_ketrack'] }}</span>
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Item Terdaftar</span>
                </div>
            </div>
            <div class="w-12 h-12 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 border border-indigo-100 dark:border-indigo-800/60 flex items-center justify-center text-indigo-600 dark:text-indigo-400 shrink-0">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
            </div>
        </div>
        <div class="mt-3.5 pt-3 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
            <span>Stok fisik tersedia</span>
            <a href="{{ route('warehouse.stock.index') }}" class="font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">Kelola Stok →</a>
        </div>
    </div>

    <!-- SN Tersedia -->
    <div class="relative overflow-hidden bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-5 shadow-xs transition-all hover:shadow-md hover:border-slate-300 dark:hover:border-slate-600">
        <div class="flex items-center justify-between">
            <div>
                <span class="block text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Perangkat SN Siap</span>
                <div class="mt-1 flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-emerald-600 dark:text-emerald-400 tracking-tight data-text">{{ $stats['serial_tersedia'] }}</span>
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Unit Siap Pasang</span>
                </div>
            </div>
            <div class="w-12 h-12 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-100 dark:border-emerald-800/60 flex items-center justify-center text-emerald-600 dark:text-emerald-400 shrink-0">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
            </div>
        </div>
        <div class="mt-3.5 pt-3 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
            <span>ONT, Router, AP Wireless</span>
            <a href="{{ route('warehouse.traceability.index') }}" class="font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">Pelacakan SN →</a>
        </div>
    </div>

    <!-- Stok Rendah -->
    <div class="relative overflow-hidden bg-white dark:bg-slate-800/90 border {{ $stats['low_stock_count'] > 0 ? 'border-rose-200 dark:border-rose-800/70 bg-gradient-to-br from-rose-50/40 to-white dark:from-rose-950/20 dark:to-slate-800' : 'border-slate-200/80 dark:border-slate-700/80' }} rounded-2xl p-5 shadow-xs transition-all hover:shadow-md">
        <div class="flex items-center justify-between">
            <div>
                <span class="block text-[11px] font-bold {{ $stats['low_stock_count'] > 0 ? 'text-rose-500 dark:text-rose-400' : 'text-slate-400 dark:text-slate-500' }} uppercase tracking-wider">Perlu Restock</span>
                <div class="mt-1 flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold {{ $stats['low_stock_count'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-800 dark:text-slate-100' }} tracking-tight data-text">{{ $stats['low_stock_count'] }}</span>
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Item Menipis</span>
                </div>
            </div>
            <div class="w-12 h-12 rounded-xl {{ $stats['low_stock_count'] > 0 ? 'bg-rose-100 dark:bg-rose-900/40 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800' : 'bg-slate-100 dark:bg-slate-700/50 text-slate-400' }} flex items-center justify-center shrink-0">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
        </div>
        <div class="mt-3.5 pt-3 border-t {{ $stats['low_stock_count'] > 0 ? 'border-rose-100 dark:border-rose-900/40' : 'border-slate-100 dark:border-slate-700/60' }} flex items-center justify-between text-xs">
            <span class="{{ $stats['low_stock_count'] > 0 ? 'text-rose-600 dark:text-rose-400 font-medium' : 'text-slate-500 dark:text-slate-400' }}">
                {{ $stats['low_stock_count'] > 0 ? 'Segera Restock' : 'Semua stok aman' }}
            </span>
            @if($stats['low_stock_count'] > 0)
            <a href="{{ route('warehouse.stock.index', ['low_stock_only' => 1]) }}" class="font-bold text-rose-600 dark:text-rose-400 hover:underline">Lihat List →</a>
            @endif
        </div>
    </div>
</div>

<!-- Operational Flow Stepper Banner (Logistik ISP) -->
<div class="bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 text-white rounded-2xl p-5 mb-6 shadow-sm border border-slate-700/80">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4 pb-3.5 border-b border-slate-700/80">
        <div class="flex items-center gap-2.5">
            <span class="w-2 h-2 rounded-full bg-sky-400 animate-ping"></span>
            <h3 class="text-xs font-bold uppercase tracking-widest text-slate-300">Siklus Distribusi Logistik Whusnet</h3>
        </div>
        <span class="text-[11px] text-slate-400 font-medium">Alur pergerakan material terstandarisasi untuk pencegahan fraud &amp; selisih stok</span>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <!-- Step 1: Inbound -->
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-3.5 hover:bg-slate-800 transition-colors">
            <div class="flex items-center gap-2.5 mb-2">
                <span class="w-6 h-6 rounded-lg bg-emerald-500/20 text-emerald-400 font-bold text-xs flex items-center justify-center border border-emerald-500/30">1</span>
                <h4 class="text-xs font-bold text-slate-100">Barang Masuk (Inbound)</h4>
            </div>
            <p class="text-[11px] text-slate-400 leading-relaxed">Penerimaan barang &amp; catat last-cost di Gudang Pusat, batch serial number / roll kabel.</p>
        </div>

        <!-- Step 2: Transfer -->
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-3.5 hover:bg-slate-800 transition-colors">
            <div class="flex items-center gap-2.5 mb-2">
                <span class="w-6 h-6 rounded-lg bg-sky-500/20 text-sky-400 font-bold text-xs flex items-center justify-center border border-sky-500/30">2</span>
                <h4 class="text-xs font-bold text-slate-100">Transfer Cabang</h4>
            </div>
            <p class="text-[11px] text-slate-400 leading-relaxed">Distribusi stok dari Pusat ke Gudang Cabang (POP) dengan konfirmasi terima bertahap.</p>
        </div>

        <!-- Step 3: Issue Custody -->
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-3.5 hover:bg-slate-800 transition-colors">
            <div class="flex items-center gap-2.5 mb-2">
                <span class="w-6 h-6 rounded-lg bg-indigo-500/20 text-indigo-400 font-bold text-xs flex items-center justify-center border border-indigo-500/30">3</span>
                <h4 class="text-xs font-bold text-slate-100">Serah ke Teknisi</h4>
            </div>
            <p class="text-[11px] text-slate-400 leading-relaxed">Penyerahan material / ONT ke teknisi lapangan dengan pencatatan custody terperinci.</p>
        </div>

        <!-- Step 4: Installation & Active -->
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-3.5 hover:bg-slate-800 transition-colors">
            <div class="flex items-center gap-2.5 mb-2">
                <span class="w-6 h-6 rounded-lg bg-amber-500/20 text-amber-400 font-bold text-xs flex items-center justify-center border border-amber-500/30">4</span>
                <h4 class="text-xs font-bold text-slate-100">Instalasi &amp; Pelacakan</h4>
            </div>
            <p class="text-[11px] text-slate-400 leading-relaxed">Rekonsiliasi material pasang di pelanggan, lacak nomor seri modem aktif hingga nonaktif.</p>
        </div>
    </div>
</div>

<!-- Stok Rendah Alert Section -->
@if($lowStock->isNotEmpty())
<div class="bg-white dark:bg-slate-800/90 border border-rose-200 dark:border-rose-900/60 rounded-2xl overflow-hidden shadow-xs mb-6">
    <div class="px-6 py-4 bg-rose-50/70 dark:bg-rose-950/30 border-b border-rose-200 dark:border-rose-900/60 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div class="flex items-center gap-2.5">
            <div class="w-7 h-7 rounded-lg bg-rose-500 text-white flex items-center justify-center shadow-xs">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <div>
                <h3 class="text-xs font-bold text-rose-800 dark:text-rose-300 uppercase tracking-wider">Perhatian Stok Kritis (Perlu Pengadaan / Transfer)</h3>
                <p class="text-[11px] text-rose-600 dark:text-rose-400">Item berikut telah berada di bawah batas minimum stok operasional.</p>
            </div>
        </div>
        <a href="{{ route('warehouse.stock.index', ['low_stock_only' => 1]) }}" class="inline-flex items-center gap-1 text-xs font-bold text-rose-700 dark:text-rose-300 hover:underline">
            <span>Buka di Kelola Stok</span>
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
        </a>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-800/60">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Gudang POP</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Barang / Item</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Status Stok Fisik</th>
                    <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Sisa / Min</th>
                    @if(auth()->user()->hasPermission('warehouse_adjustment.create'))
                    <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Aksi</th>
                    @endif
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700/50">
                @foreach($lowStock as $balance)
                @php
                    $qty = (float) $balance->qty;
                    $min = (float) ($balance->minimum_stock ?? 1);
                    $ratio = $min > 0 ? min(100, round(($qty / $min) * 100)) : 0;
                @endphp
                <tr class="hover:bg-rose-50/30 dark:hover:bg-rose-950/10 transition-colors">
                    <td class="px-6 py-3.5 text-sm text-slate-700 dark:text-slate-300">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                            {{ $balance->pop->name }}
                        </span>
                    </td>
                    <td class="px-6 py-3.5">
                        <div class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $balance->item->name }}</div>
                        <div class="text-[11px] font-mono text-slate-400">{{ $balance->item->code }}{{ $balance->lot_no ? " • Lot {$balance->lot_no}" : '' }}</div>
                    </td>
                    <td class="px-6 py-3.5">
                        <div class="w-44">
                            <div class="flex items-center justify-between text-[11px] font-semibold text-rose-600 dark:text-rose-400 mb-1">
                                <span>{{ $ratio }}% Tersedia</span>
                                <span class="px-1.5 py-0.5 rounded text-[10px] bg-rose-100 dark:bg-rose-900/40 font-bold">Kritis</span>
                            </div>
                            <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-2 overflow-hidden">
                                <div class="bg-rose-500 h-2 rounded-full transition-all duration-500" style="width: {{ $ratio }}%"></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-3.5 text-right font-mono text-sm">
                        <span class="font-bold text-rose-600 dark:text-rose-400">{{ rtrim(rtrim(number_format($qty, 2, ',', '.'), '0'), ',') }}</span>
                        <span class="text-xs text-slate-400">/ {{ rtrim(rtrim(number_format($min, 2, ',', '.'), '0'), ',') }} {{ $balance->item->unit }}</span>
                    </td>
                    @if(auth()->user()->hasPermission('warehouse_adjustment.create'))
                    <td class="px-6 py-3.5 text-right">
                        <a href="{{ route('warehouse.adjustments.balance.create', ['pop_id' => $balance->pop_id, 'item_id' => $balance->item_id, 'lot_no' => $balance->lot_no]) }}"
                           class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-lg bg-amber-50 hover:bg-amber-100 dark:bg-amber-950/40 dark:hover:bg-amber-900/50 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/></svg>
                            <span>Sesuaikan</span>
                        </a>
                    </td>
                    @endif
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<!-- Riwayat Ledger Mutasi Logistik Terbaru -->
<div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl overflow-hidden shadow-xs">
    <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
        <div class="flex items-center gap-2.5">
            <div class="w-7 h-7 rounded-lg bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400 flex items-center justify-center border border-sky-100 dark:border-sky-800/60">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">Aktivitas Mutasi Logistik Terbaru</h3>
                <p class="text-[11px] text-slate-400">25 transaksi ledger mutasi barang terakhir di seluruh gudang — klik baris buat buka dokumen lengkapnya</p>
            </div>
        </div>
        @if(auth()->user()->hasPermission('warehouse.view'))
        <a href="{{ route('warehouse.history.index') }}" class="text-xs font-semibold text-sky-600 dark:text-sky-400 hover:underline">
            Lihat Semua Riwayat →
        </a>
        @endif
    </div>

    @if($recentLedger->isEmpty())
    <div class="p-16 text-center">
        <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-slate-100 dark:bg-slate-700/50 flex items-center justify-center text-slate-400">
            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </svg>
        </div>
        <h4 class="text-sm font-bold text-slate-700 dark:text-slate-300">Belum ada aktivitas mutasi</h4>
        <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">Transaksi barang masuk, transfer antar cabang, atau penyerahan ke teknisi akan otomatis tercatat di sini.</p>
    </div>
    @else
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-800/50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Waktu</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Tipe Aksi</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Barang / Serial Number</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Asal &amp; Tujuan</th>
                    <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Jumlah Mutasi</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700/50">
                @foreach($recentLedger as $txn)
                @php
                    // Cocokkan value ASLI App\Enums\InventoryTransactionType
                    // ('receive'/'transfer'/'issue'/'return'/'adjustment'/
                    // 'transfer_custody'/'stock_opname'/'install') — versi
                    // sebelumnya nyocokin ke string sintetis yang gak pernah
                    // match ('dispatch_transfer' dst), badge SELALU jatuh ke
                    // default abu-abu (bug ketauan 2026-09-03).
                    $typeBadge = match($txn->type->value ?? '') {
                        'receive' => 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800',
                        'transfer' => 'bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-400 border-sky-200 dark:border-sky-800',
                        'issue' => 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-400 border-indigo-200 dark:border-indigo-800',
                        'return' => 'bg-teal-50 dark:bg-teal-950/40 text-teal-700 dark:text-teal-400 border-teal-200 dark:border-teal-800',
                        'adjustment' => 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-800',
                        'install' => 'bg-violet-50 dark:bg-violet-950/40 text-violet-700 dark:text-violet-400 border-violet-200 dark:border-violet-800',
                        default => 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-600',
                    };

                    // Dokumen sumber baris ini — sebelumnya gak ada satupun
                    // baris ledger yang bisa diklik balik ke Transfer/Issue/
                    // Receive show()-nya (cuma sub-link SN ke Traceability),
                    // jadi begitu geser dari 25 baris ini, dokumennya ilang
                    // gak bisa ditemu lagi kecuali nebak URL (ketauan
                    // 2026-09-03, laporan user "list/detail tersembunyi").
                    $detailRoute = match($txn->type->value ?? '') {
                        'receive' => auth()->user()->hasPermission('warehouse_transfer.view') && $txn->reference_number
                            ? route('warehouse.receive.show', $txn->reference_number) : null,
                        'transfer' => auth()->user()->hasPermission('warehouse_transfer.view') && $txn->inventory_transfer_id
                            ? route('warehouse.transfers.show', $txn->inventory_transfer_id) : null,
                        'issue' => auth()->user()->hasPermission('warehouse_issue.view') && $txn->reference_number
                            ? route('warehouse.issues.show', $txn->reference_number) : null,
                        default => null,
                    };

                    // Transfer nulis 2 baris ledger per pergerakan (dispatch +
                    // confirm) pake reference_number SAMA — badge generik bikin
                    // keliatan kayak duplikat (laporan user 2026-09-03). Dibedain.
                    $typeLabel = match(true) {
                        $txn->type->value === 'transfer' && $txn->from_pop_id !== null => 'Transfer Dikirim',
                        $txn->type->value === 'transfer' && $txn->to_pop_id !== null => 'Transfer Diterima',
                        default => $txn->type->label(),
                    };
                @endphp
                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30 transition-colors {{ $detailRoute ? 'cursor-pointer' : '' }}" @if($detailRoute) onclick="window.location='{{ $detailRoute }}'" @endif>
                    <td class="px-6 py-3.5 whitespace-nowrap text-xs text-slate-500 dark:text-slate-400">
                        <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $txn->created_at->translatedFormat('d M') }}</span>
                        <span class="text-slate-400">{{ $txn->created_at->format('H:i') }}</span>
                    </td>
                    <td class="px-6 py-3.5 whitespace-nowrap">
                        <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-bold border {{ $typeBadge }}">
                            {{ $typeLabel }}
                        </span>
                    </td>
                    <td class="px-6 py-3.5">
                        <div class="text-sm font-semibold text-slate-800 dark:text-slate-200">
                            @if($detailRoute)
                            <a href="{{ $detailRoute }}" class="hover:underline hover:text-sky-600 dark:hover:text-sky-400">{{ $txn->item->name }}</a>
                            @else
                            {{ $txn->item->name }}
                            @endif
                        </div>
                        @if($txn->serial)
                        <div class="text-xs font-mono text-sky-600 dark:text-sky-400 mt-0.5">
                            @if(auth()->user()->hasPermission('warehouse_traceability.view'))
                            <a href="{{ route('warehouse.traceability.index', ['sn' => $txn->serial->serial_number]) }}" onclick="event.stopPropagation()" class="hover:underline flex items-center gap-1">
                                <span>SN: {{ $txn->serial->serial_number }}</span>
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            </a>
                            @else
                            <span>SN: {{ $txn->serial->serial_number }}</span>
                            @endif
                        </div>
                        @endif
                    </td>
                    <td class="px-6 py-3.5 whitespace-nowrap text-xs text-slate-600 dark:text-slate-300">
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-700/60 font-medium">{{ $txn->fromPop->name ?? ($txn->fromTechnician->name ?? 'Pengadaan (Baru)') }}</span>
                            <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                            <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-700/60 font-medium">{{ $txn->toPop->name ?? ($txn->toTechnician->name ?? 'Pelanggan / Luar') }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-3.5 whitespace-nowrap text-right font-mono text-sm font-bold text-slate-800 dark:text-slate-200">
                        {{ rtrim(rtrim(number_format((float) $txn->qty, 2, ',', '.'), '0'), ',') }}
                        <span class="text-xs font-normal text-slate-400">{{ $txn->item->unit }}</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

@endsection
