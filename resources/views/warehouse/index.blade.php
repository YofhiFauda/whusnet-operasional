@extends('layouts.app')

@section('title', 'Dasbor & Riwayat Gudang - Whusnet Operasional')
@section('page_title', 'Dasbor Gudang')

@section('content')

@php
    $user = auth()->user();
    $canViewWarehouse = $user->hasPermission('warehouse.view');
    $canViewCustody = $user->hasPermission('warehouse_custody.view');
    $canViewTraceability = $user->hasPermission('warehouse_traceability.view');
    $canViewReport = $user->hasPermission('warehouse_report.view');
    $canViewStockRequest = $user->hasPermission('warehouse_stock_request.view');
    $canReceive = $user->hasPermission('warehouse_transfer.create');
    $canTransfer = $user->hasPermission('warehouse_transfer.create');
    $canIssue = $user->hasPermission('warehouse_issue.create');
    $canAdjust = $user->hasPermission('warehouse_adjustment.create');
@endphp

<div class="space-y-6 pb-12">

    {{-- Header terpadu (Design System Naked Header) --}}
    <x-warehouse.header active="dashboard" title="Dasbor & Riwayat Gudang" subtitle="Pusat kendali stok fisik, arus barang harian, custody teknisi, dan buku besar logistik ISP." />

    {{-- Scope Selector POP (Naked context filter) --}}
    <form action="{{ route('warehouse.index') }}" method="GET" id="scopeForm" class="flex justify-end -mt-2">
        <div class="relative">
            <select name="pop_id"
                    id="scopeSelect"
                    onchange="document.getElementById('scopeForm').submit()"
                    aria-label="Filter cakupan gudang"
                    class="appearance-none bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-700 rounded-lg pl-3.5 pr-8 py-2 text-xs font-semibold shadow-2xs hover:border-slate-300 dark:hover:border-slate-600 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 cursor-pointer transition-colors">
                <option value="" {{ empty($selectedPopId) ? 'selected' : '' }}>
                    Semua POP (Nasional)
                </option>
                @foreach($pops as $pop)
                <option value="{{ $pop->id }}" {{ (string) $selectedPopId === (string) $pop->id ? 'selected' : '' }}>
                    [{{ $pop->type === 'pusat' ? 'Pusat' : 'Cabang' }}] {{ $pop->name }}
                </option>
                @endforeach
            </select>
            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5 text-slate-400 dark:text-slate-500">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                </svg>
            </div>
        </div>
    </form>

    {{-- ═══════════════════════════════════════════════════════
         ZONA 1 : 5 METRIC KPI CARDS (Design System Type C)
         Stok Kritis | Permintaan Pending | ONT Siap | Custody | Karantina
    ═══════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3.5">
        {{-- KPI 1: Stok Kritis --}}
        <div class="bg-white dark:bg-slate-800 border {{ $stats['low_stock_count'] > 0 ? 'border-rose-200 dark:border-rose-900/60' : 'border-slate-200 dark:border-slate-700' }} rounded-lg p-4 flex flex-col justify-between shadow-2xs">
            <div>
                <span class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                    Stok Kritis
                </span>
                <div class="mt-1.5 flex items-baseline gap-1.5">
                    <span class="text-2xl font-bold font-mono tabular-nums {{ $stats['low_stock_count'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-900 dark:text-slate-100' }} tracking-tight">
                        {{ $stats['low_stock_count'] }}
                    </span>
                    <span class="text-xs font-semibold text-slate-400 dark:text-slate-500">Item</span>
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-100 dark:border-slate-700/60 text-[11.5px] text-slate-500 dark:text-slate-400 flex items-center justify-between">
                <span>{{ $stats['low_stock_count'] > 0 ? 'Di bawah ambang minimum' : 'Semua stok aman' }}</span>
                @if($stats['low_stock_count'] > 0)
                <a href="{{ route('warehouse.stock.index', ['low_stock_only' => 1]) }}" class="text-rose-600 dark:text-rose-400 font-semibold hover:underline">Lihat →</a>
                @endif
            </div>
        </div>

        {{-- KPI 2: Permintaan Stok Pending --}}
        <div class="bg-white dark:bg-slate-800 border {{ $stats['pending_stock_request_count'] > 0 ? 'border-amber-200 dark:border-amber-900/60' : 'border-slate-200 dark:border-slate-700' }} rounded-lg p-4 flex flex-col justify-between shadow-2xs">
            <div>
                <span class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                    Permintaan Stok Pending
                </span>
                <div class="mt-1.5 flex items-baseline gap-1.5">
                    <span class="text-2xl font-bold font-mono tabular-nums {{ $stats['pending_stock_request_count'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-900 dark:text-slate-100' }} tracking-tight">
                        {{ $stats['pending_stock_request_count'] }}
                    </span>
                    <span class="text-xs font-semibold text-slate-400 dark:text-slate-500">Tiket</span>
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-100 dark:border-slate-700/60 text-[11.5px] text-slate-500 dark:text-slate-400 flex items-center justify-between">
                <span>Dari Cabang, antre di Pusat</span>
                @if($canViewStockRequest)
                <a href="{{ route('warehouse.stock-requests.index') }}" class="text-amber-600 dark:text-amber-400 font-semibold hover:underline">Proses →</a>
                @endif
            </div>
        </div>

        {{-- KPI 3: ONT Siap Pasang --}}
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-4 flex flex-col justify-between shadow-2xs">
            <div>
                <span class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                    ONT / Router Siap Pasang
                </span>
                <div class="mt-1.5 flex items-baseline gap-1.5">
                    <span class="text-2xl font-bold font-mono tabular-nums text-emerald-600 dark:text-emerald-400 tracking-tight">
                        {{ $stats['serial_tersedia'] }}
                    </span>
                    <span class="text-xs font-semibold text-slate-400 dark:text-slate-500">Unit</span>
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-100 dark:border-slate-700/60 text-[11.5px] text-slate-500 dark:text-slate-400 flex items-center justify-between">
                <span class="inline-flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    <span>Status AVAILABLE</span>
                </span>
                <a href="{{ route('warehouse.traceability.index', ['status' => 'available']) }}" class="text-emerald-600 dark:text-emerald-400 font-semibold hover:underline">Lacak →</a>
            </div>
        </div>

        {{-- KPI 4: Custody Teknisi --}}
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-4 flex flex-col justify-between shadow-2xs">
            <div>
                <span class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                    Custody Teknisi
                </span>
                <div class="mt-1.5 flex items-baseline gap-1.5">
                    <span class="text-2xl font-bold font-mono tabular-nums text-slate-900 dark:text-slate-100 tracking-tight">
                        {{ $stats['custody_serial_count'] }}
                    </span>
                    <span class="text-xs font-semibold text-slate-400 dark:text-slate-500">Unit SN</span>
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-100 dark:border-slate-700/60 text-[11.5px] text-slate-500 dark:text-slate-400 flex items-center justify-between">
                <span>{{ $stats['custody_technician_count'] }} teknisi bertugas</span>
                <a href="{{ route('warehouse.custody.index') }}" class="text-sky-600 dark:text-sky-400 font-semibold hover:underline">Detail →</a>
            </div>
        </div>

        {{-- KPI 5: Karantina --}}
        <div class="bg-white dark:bg-slate-800 border {{ $stats['quarantine_count'] > 0 ? 'border-rose-200 dark:border-rose-900/60' : 'border-slate-200 dark:border-slate-700' }} rounded-lg p-4 flex flex-col justify-between shadow-2xs">
            <div>
                <span class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                    Karantina
                </span>
                <div class="mt-1.5 flex items-baseline gap-1.5">
                    <span class="text-2xl font-bold font-mono tabular-nums {{ $stats['quarantine_count'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-900 dark:text-slate-100' }} tracking-tight">
                        {{ $stats['quarantine_count'] }}
                    </span>
                    <span class="text-xs font-semibold text-slate-400 dark:text-slate-500">Unit SN</span>
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-100 dark:border-slate-700/60 text-[11.5px] text-slate-500 dark:text-slate-400">
                <span>{{ $stats['quarantine_count'] > 0 ? 'Butuh keputusan scrap/kembali vendor' : 'Tidak ada unit dikarantina' }}</span>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════
         ZONA 2 : OPERASIONAL HARIAN & ALERT KRITIS (7 / 5 Grid)
         Arus Barang Hari Ini (Left) & Peringatan Stok Rendah (Right)
    ═══════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
        {{-- Left: Arus Barang Hari Ini (7 Cols) --}}
        <div class="lg:col-span-7 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-5 flex flex-col justify-between shadow-2xs">
            <div>
                <div class="flex items-center gap-2 pb-3.5 border-b border-slate-100 dark:border-slate-700/60">
                    <span class="w-2 h-2 rounded-full bg-sky-500 shrink-0"></span>
                    <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">
                        Arus Barang Hari Ini
                    </h3>
                </div>

                <div class="divide-y divide-dashed divide-slate-100 dark:divide-slate-700/60 text-xs">
                    {{-- Row 1: Receive --}}
                    <div class="flex items-center justify-between py-3">
                        <span class="text-slate-600 dark:text-slate-300 font-medium">Masuk (Receive)</span>
                        <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400 text-sm tabular-nums">
                            +{{ $stats['today_receive_count'] }} Transaksi
                        </span>
                    </div>

                    {{-- Row 2: Issue --}}
                    <div class="flex items-center justify-between py-3">
                        <span class="text-slate-600 dark:text-slate-300 font-medium">Keluar ke Teknisi (Issue)</span>
                        <span class="font-mono font-bold text-rose-600 dark:text-rose-400 text-sm tabular-nums">
                            −{{ $stats['today_issue_count'] }} Transaksi
                        </span>
                    </div>

                    {{-- Row 3: Transfer Hari Ini --}}
                    <div class="flex items-center justify-between py-3">
                        <span class="text-slate-600 dark:text-slate-300 font-medium">Transfer Keluar (Cabang)</span>
                        <span class="font-mono font-bold text-sky-600 dark:text-sky-400 text-sm tabular-nums">
                            {{ $stats['today_transfer_count'] }} Transaksi
                        </span>
                    </div>

                    {{-- Row 4: Penyesuaian Saldo --}}
                    <div class="flex items-center justify-between py-3">
                        <span class="text-slate-600 dark:text-slate-300 font-medium">Kerugian (Adjustment)</span>
                        <span class="font-mono font-bold {{ $stats['today_adjustment_count'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400' }} text-sm tabular-nums">
                            {{ $stats['today_adjustment_count'] }} Kejadian
                        </span>
                    </div>
                </div>
            </div>

            {{-- Transfer In-Transit --}}
            @if($transitTransfers->isNotEmpty())
            <div class="pt-3 border-t border-slate-100 dark:border-slate-700/60">
                <p class="text-[10px] font-bold uppercase tracking-wider text-sky-600 dark:text-sky-400 mb-2">
                    Transfer Menunggu Diterima ({{ $transitTransfers->count() }})
                </p>
                <div class="space-y-1.5">
                    @foreach($transitTransfers->take(5) as $transfer)
                    <a href="{{ route('warehouse.transfers.show', $transfer->id) }}"
                       class="flex items-center justify-between gap-2 px-2.5 py-2 rounded-lg bg-sky-50/60 dark:bg-sky-950/30 border border-sky-100 dark:border-sky-900/50 hover:bg-sky-100 dark:hover:bg-sky-900/40 transition-colors text-[11.5px]">
                        <span class="font-mono font-semibold text-sky-700 dark:text-sky-300 truncate">
                            {{ $transfer->reference_number }}
                        </span>
                        <span class="text-slate-500 dark:text-slate-400 truncate">
                            {{ $transfer->fromPop->name ?? '—' }} → {{ $transfer->toPop->name ?? '—' }}
                        </span>
                    </a>
                    @endforeach
                </div>
                @if($transitTransfers->count() > 5)
                <a href="{{ route('warehouse.history.index', ['type' => 'transfer']) }}" class="block mt-2 text-[11px] text-sky-600 dark:text-sky-400 font-semibold hover:underline">
                    Lihat {{ $transitTransfers->count() - 5 }} transfer in-transit lainnya →
                </a>
                @endif
            </div>
            @endif

            <div class="pt-3 border-t border-slate-100 dark:border-slate-700/60 text-[11.5px] text-slate-400 flex items-center justify-between gap-x-3 gap-y-1.5">
                <span>Dipantau realtime per {{ now()->translatedFormat('d F Y') }}</span>
                <a href="{{ route('warehouse.history.index') }}" class="text-sky-600 dark:text-sky-400 font-semibold hover:underline">Buka Ledger Lengkap →</a>
            </div>
        </div>

        {{-- Right: Peringatan Stok Rendah (5 Cols) --}}
        <div class="lg:col-span-5 bg-white dark:bg-slate-800 border {{ $stats['low_stock_count'] > 0 ? 'border-rose-200 dark:border-rose-900/60' : 'border-slate-200 dark:border-slate-700' }} rounded-lg p-5 flex flex-col justify-between shadow-2xs">
            <div>
                <div class="flex items-center justify-between pb-3.5 border-b {{ $stats['low_stock_count'] > 0 ? 'border-rose-100 dark:border-rose-900/40' : 'border-slate-100 dark:border-slate-700/60' }}">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full {{ $stats['low_stock_count'] > 0 ? 'bg-rose-500' : 'bg-slate-400' }} shrink-0"></span>
                        <h3 class="text-xs font-bold {{ $stats['low_stock_count'] > 0 ? 'text-rose-800 dark:text-rose-300' : 'text-slate-800 dark:text-slate-200' }} uppercase tracking-wider">
                            Peringatan Stok Rendah
                        </h3>
                    </div>
                    @if($stats['low_stock_count'] > 0)
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 uppercase tracking-wider">
                        {{ $stats['low_stock_count'] }} Kritis
                    </span>
                    @endif
                </div>

                @if($lowStock->isEmpty())
                <div class="py-8 text-center text-xs">
                    <div class="w-8 h-8 rounded-full bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mx-auto mb-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                        </svg>
                    </div>
                    <p class="font-semibold text-slate-700 dark:text-slate-300">Semua stok aman</p>
                    <p class="text-slate-400 text-[11px] mt-0.5">Tidak ada item di bawah ambang minimum stok.</p>
                </div>
                @else
                <div class="space-y-3.5 py-3">
                    @foreach($lowStock->take(3) as $balance)
                    @php
                        $qty = (float) $balance->qty;
                        $min = (float) ($balance->minimum_stock ?? 1);
                        $ratio = $min > 0 ? min(100, round(($qty / $min) * 100)) : 0;
                        $isCritical = $ratio <= 50;
                    @endphp
                    <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-700/30 border border-slate-200/70 dark:border-slate-700/60 space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-semibold text-slate-800 dark:text-slate-200 truncate max-w-[65%]">
                                {{ $balance->item->name }}
                            </span>
                            <span class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">
                                {{ $balance->pop->name }}
                            </span>
                        </div>

                        {{-- Track & Fill --}}
                        <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-1.5 overflow-hidden">
                            <div class="{{ $isCritical ? 'bg-rose-500' : 'bg-amber-500' }} h-1.5 rounded-full transition-all duration-300"
                                 style="width: {{ $ratio }}%"></div>
                        </div>

                        <div class="flex items-center justify-between text-[11px]">
                            <span class="font-mono text-slate-600 dark:text-slate-300 tabular-nums">
                                Sisa {{ rtrim(rtrim(number_format($qty, 2, ',', '.'), '0'), ',') }} / Min {{ rtrim(rtrim(number_format($min, 2, ',', '.'), '0'), ',') }} {{ $balance->item->unit }}
                            </span>

                            @if($canTransfer)
                            <a href="{{ route('warehouse.transfers.create', ['pop_id' => $balance->pop_id, 'item_id' => $balance->item_id]) }}"
                               class="px-2 py-0.5 rounded text-[11px] font-semibold text-sky-700 dark:text-sky-300 bg-sky-50 dark:bg-sky-950/50 hover:bg-sky-100 dark:hover:bg-sky-900/60 border border-sky-200 dark:border-sky-800/60 transition-colors">
                                Kirim Transfer
                            </a>
                            @elseif($user->hasPermission('warehouse_stock_request.create'))
                            <a href="{{ route('warehouse.stock-requests.create', ['pop_id' => $balance->pop_id, 'item_id' => $balance->item_id]) }}"
                               class="px-2 py-0.5 rounded text-[11px] font-semibold text-sky-700 dark:text-sky-300 bg-sky-50 dark:bg-sky-950/50 hover:bg-sky-100 dark:hover:bg-sky-900/60 border border-sky-200 dark:border-sky-800/60 transition-colors">
                                Minta Stok
                            </a>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>

            @if($lowStock->count() > 3)
            <div class="pt-2 border-t border-slate-100 dark:border-slate-700/60">
                <a href="{{ route('warehouse.stock.index', ['low_stock_only' => 1]) }}"
                   class="text-xs font-semibold text-rose-600 dark:text-rose-400 hover:underline">
                    + {{ $lowStock->count() - 3 }} item kritis lainnya →
                </a>
            </div>
            @endif
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════
         ZONA 3 : AUDIT & KONTROL INTERNAL (6 / 6 Grid)
         Stock Opname Jatuh Tempo (Left) & Custody Teknisi & Karantina (Right)
    ═══════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {{-- Left: Stock Opname Jatuh Tempo --}}
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-5 flex flex-col justify-between shadow-2xs">
            <div>
                <div class="flex items-center gap-2 pb-3.5 border-b border-slate-100 dark:border-slate-700/60">
                    <span class="w-2 h-2 rounded-full bg-amber-500 shrink-0"></span>
                    <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">
                        Stock Opname Jatuh Tempo
                    </h3>
                </div>

                @if($opnameDueList->isEmpty())
                <div class="py-8 text-center text-xs text-slate-400">
                    <p class="font-medium text-slate-600 dark:text-slate-300">Semua item teraudit</p>
                    <p class="text-[11px] mt-0.5">Belum ada saldo stok yang memerlukan audit fisik mendesak.</p>
                </div>
                @else
                <div class="divide-y divide-dashed divide-slate-100 dark:divide-slate-700/60 text-xs">
                    @foreach($opnameDueList->take(4) as $due)
                    <div class="flex items-center justify-between py-2.5">
                        <div class="min-w-0 pr-2">
                            <span class="font-semibold text-slate-800 dark:text-slate-200 truncate block">
                                {{ $due['item_name'] }}
                            </span>
                            <span class="text-[11px] text-slate-400">
                                {{ $due['pop_name'] }}{{ $due['lot_no'] ? ' · Lot '.$due['lot_no'] : '' }}
                            </span>
                        </div>
                        <span class="font-mono font-bold text-xs shrink-0 tabular-nums {{ $due['days_since'] === null || $due['days_since'] > 30 ? 'text-rose-600 dark:text-rose-400' : 'text-amber-600 dark:text-amber-400' }}">
                            {{ $due['days_since'] === null ? 'Belum pernah' : $due['days_since'].' hari lalu' }}
                        </span>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>

            <div class="pt-3.5 border-t border-slate-100 dark:border-slate-700/60">
                @if($canAdjust)
                <a href="{{ route('warehouse.adjustments.opname.create') }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-amber-700 dark:text-amber-300 bg-amber-50 hover:bg-amber-100 dark:bg-amber-950/50 dark:hover:bg-amber-900/60 border border-amber-200 dark:border-amber-800/80 transition-colors">
                    <span>Mulai Opname Sekarang</span>
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                    </svg>
                </a>
                @endif
            </div>
        </div>

        {{-- Right: Custody Teknisi & Karantina --}}
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-5 flex flex-col justify-between shadow-2xs">
            <div>
                <div class="flex items-center gap-2 pb-3.5 border-b border-slate-100 dark:border-slate-700/60">
                    <span class="w-2 h-2 rounded-full bg-sky-500 shrink-0"></span>
                    <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">
                        Custody Teknisi &amp; Karantina
                    </h3>
                </div>

                @if($topCustodyTechnicians->isEmpty())
                <div class="py-8 text-center text-xs text-slate-400">
                    <p class="font-medium text-slate-600 dark:text-slate-300">Tidak ada custody aktif</p>
                    <p class="text-[11px] mt-0.5">Semua material &amp; perangkat berada di dalam rak gudang.</p>
                </div>
                @else
                <div class="divide-y divide-dashed divide-slate-100 dark:divide-slate-700/60 text-xs">
                    @foreach($topCustodyTechnicians->take(3) as $tech)
                    <div class="flex items-center justify-between py-2.5">
                        <span class="font-semibold text-slate-800 dark:text-slate-200">
                            {{ $tech['name'] }}
                        </span>
                        <span class="font-mono font-bold text-xs text-slate-700 dark:text-slate-300 tabular-nums">
                            {{ $tech['total'] }} item aktif
                        </span>
                    </div>
                    @endforeach
                </div>
                @endif

                @if($stats['quarantine_count'] > 0)
                <div class="mt-3 flex items-center justify-between py-2 px-3 rounded-lg bg-rose-50 dark:bg-rose-950/30 border border-rose-100 dark:border-rose-900/50 text-xs">
                    <span class="font-semibold text-rose-700 dark:text-rose-300 font-mono">
                        {{ $stats['quarantine_count'] }} unit QUARANTINE
                    </span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 dark:bg-rose-900/60 text-rose-700 dark:text-rose-300 uppercase tracking-wider">
                        Menunggu BAP
                    </span>
                </div>
                @endif
            </div>

            <div class="pt-3.5 border-t border-slate-100 dark:border-slate-700/60">
                @if($canViewCustody)
                <a href="{{ route('warehouse.custody.index') }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-sky-700 dark:text-sky-300 bg-sky-50 hover:bg-sky-100 dark:bg-sky-950/50 dark:hover:bg-sky-900/60 border border-sky-200 dark:border-sky-800/80 transition-colors">
                    <span>Lihat Detail Custody</span>
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                    </svg>
                </a>
                @endif
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════
         ZONA 4 : CARD PER GUDANG (POP) — ARUS OPERASIONAL LOGISTIK
         Sesuai Spesifikasi Design.md (Solid Sky Blue, 8px Radius, Single Panel Surface)
         1. Barang Masuk (Receive / Inbound)
         2. Transfer Distribusi (Transfer / Logistics)
         3. Serah Terima Teknisi (Issue & Custody)
    ═══════════════════════════════════════════════════════ --}}
    <div x-data="{
            popFilter: 'all',
            searchPop: '',
            matchesFilter(type, name, code) {
                const matchesType = this.popFilter === 'all' || this.popFilter === type;
                const matchesSearch = !this.searchPop || 
                    name.toLowerCase().includes(this.searchPop.toLowerCase()) || 
                    code.toLowerCase().includes(this.searchPop.toLowerCase());
                return matchesType && matchesSearch;
            }
         }" 
         class="space-y-4">

        {{-- Section Header Bar & Interactive Filter --}}
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-4 sm:p-5 shadow-2xs flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2.5">
                    <span class="w-2 h-2 rounded-full bg-sky-500"></span>
                    <h3 class="text-xs font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider">
                        Aktivitas &amp; Mutasi per Gudang (POP)
                    </h3>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                        {{ $popCards->count() }} Titik Logistik
                    </span>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Kendali arus fisik harian, distribusi antar-cabang, dan alokasi custody teknisi per lokasi operasional.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5 self-start lg:self-auto">
                {{-- Client-side Filter Segmented Control --}}
                <div class="inline-flex p-1 rounded-lg bg-slate-100 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-700 text-xs font-semibold">
                    <button type="button" 
                            @click="popFilter = 'all'"
                            :class="popFilter === 'all' ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 shadow-2xs' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'"
                            class="px-2.5 py-1 rounded-md transition-all cursor-pointer">
                        Semua POP ({{ $popCards->count() }})
                    </button>
                    @if($popCards->where('pop.type', 'pusat')->isNotEmpty())
                    <button type="button" 
                            @click="popFilter = 'pusat'"
                            :class="popFilter === 'pusat' ? 'bg-white dark:bg-slate-800 text-sky-600 dark:text-sky-400 shadow-2xs' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'"
                            class="px-2.5 py-1 rounded-md transition-all cursor-pointer flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
                        <span>Pusat</span>
                        <span class="text-[10px] font-mono opacity-80">({{ $popCards->where('pop.type', 'pusat')->count() }})</span>
                    </button>
                    @endif
                    @if($popCards->where('pop.type', 'cabang')->isNotEmpty())
                    <button type="button" 
                            @click="popFilter = 'cabang'"
                            :class="popFilter === 'cabang' ? 'bg-white dark:bg-slate-800 text-sky-600 dark:text-sky-400 shadow-2xs' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'"
                            class="px-2.5 py-1 rounded-md transition-all cursor-pointer flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                        <span>Cabang</span>
                        <span class="text-[10px] font-mono opacity-80">({{ $popCards->where('pop.type', 'cabang')->count() }})</span>
                    </button>
                    @endif
                </div>

                @if($canViewWarehouse)
                <a href="{{ route('warehouse.history.index') }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 transition-all shadow-2xs">
                    <span>Buku Besar Lengkap</span>
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                    </svg>
                </a>
                @endif
            </div>
        </div>

        @if($popCards->isEmpty())
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-16 text-center text-slate-400 text-xs shadow-2xs">
            <div class="w-12 h-12 mx-auto mb-3 rounded-lg bg-slate-100 dark:bg-slate-700/50 flex items-center justify-center text-slate-400">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z"/>
                </svg>
            </div>
            <p class="font-bold text-slate-700 dark:text-slate-300 text-sm">Tidak ada data gudang</p>
            <p class="text-slate-400 mt-1 max-w-sm mx-auto">Tidak ditemukan data gudang aktif dalam cakupan akses yang Anda pilih.</p>
        </div>
        @else
        <div class="space-y-4">
            @foreach($popCards as $card)
            @php
                $pop = $card['pop'];
                $isPusat = $pop->type === 'pusat';
                $receiveData = $card['receive'];
                $transferData = $card['transfer'];
                $issueData = $card['issue'];
                $stockData = $card['stock'];
            @endphp
            <div x-show="matchesFilter('{{ $pop->type }}', '{{ addslashes($pop->name) }}', '{{ addslashes($pop->code) }}')"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="bg-white dark:bg-slate-800 border {{ $isPusat ? 'border-sky-300 dark:border-sky-800 ring-1 ring-sky-500/15' : 'border-slate-200 dark:border-slate-700' }} rounded-lg overflow-hidden shadow-2xs transition-all">
                
                {{-- 1. CARD TOP BANNER & IDENTITY (Clean Surface, No Gradients) --}}
                <div class="p-5 border-b border-slate-100 dark:border-slate-700/60 bg-white dark:bg-slate-800">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="flex items-start sm:items-center gap-3.5">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 {{ $isPusat ? 'bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 border border-sky-200/80 dark:border-sky-800/80' : 'bg-slate-100 dark:bg-slate-700/60 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-600' }}">
                                @if($isPusat)
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6h1.5m-1.5 3h1.5m-1.5 3h1.5M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/>
                                </svg>
                                @else
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009 9.35c.692-.802 1.716-.802 2.409 0 1.161.85 2.75.85 3.91 0 .693-.802 1.717-.802 2.41 0a2.993 2.993 0 001.521-.615 3.001 3.001 0 003.75.615m-16.5 0l.75-5.25A2.25 2.25 0 014.49 2.25h15.02a2.25 2.25 0 012.24 1.85l.75 5.25"/>
                                </svg>
                                @endif
                            </div>
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100 tracking-tight">
                                        {{ $pop->name }}
                                    </h3>
                                    @if($isPusat)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-sky-50 text-sky-700 dark:bg-sky-950/60 dark:text-sky-300 border border-sky-200 dark:border-sky-800">
                                        <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
                                        Hub Pusat Logistik
                                    </span>
                                    @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-700 dark:bg-slate-700/60 dark:text-slate-300 border border-slate-200 dark:border-slate-600">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                        Cabang Distribusi
                                    </span>
                                    @endif
                                </div>
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500 dark:text-slate-400 mt-1">
                                    <span class="inline-flex items-center gap-1">
                                        <span class="text-slate-400">Kode:</span>
                                        <span class="font-mono font-semibold text-slate-700 dark:text-slate-300">{{ $pop->code }}</span>
                                    </span>
                                    <span>&bull;</span>
                                    <span class="inline-flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                                        <span>{{ $pop->city ?? 'Area Operasional' }}</span>
                                    </span>
                                    @if($pop->pic_name)
                                    <span>&bull;</span>
                                    <span class="inline-flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                                        <span>PIC: <strong class="text-slate-700 dark:text-slate-200 font-semibold">{{ $pop->pic_name }}</strong></span>
                                    </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Header Quick Actions --}}
                        <div class="flex items-center gap-2 self-start md:self-auto shrink-0">
                            <a href="{{ route('warehouse.history.index', ['pop_id' => $pop->id]) }}"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 shadow-2xs transition-colors">
                                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/>
                                </svg>
                                <span>Buku Besar POP</span>
                            </a>
                            <a href="{{ route('warehouse.stock.index', ['pop_id' => $pop->id]) }}"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-sky-700 dark:text-sky-300 bg-sky-50 hover:bg-sky-100 dark:bg-sky-950/60 dark:hover:bg-sky-900/80 border border-sky-200/80 dark:border-sky-800/80 shadow-2xs transition-colors">
                                <svg class="w-3.5 h-3.5 text-sky-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/>
                                </svg>
                                <span>Stok Gudang</span>
                            </a>
                        </div>
                    </div>

                    {{-- 2. LIVE TELEMETRY STRIP (3 Clean Summary Chips) --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 mt-4 pt-4 border-t border-slate-100 dark:border-slate-700/60 text-xs">
                        {{-- Telemetry 1: Inbound --}}
                        <div class="flex items-center justify-between p-2.5 rounded-lg bg-slate-50/70 dark:bg-slate-900/40 border border-slate-200/80 dark:border-slate-700/80">
                            <span class="text-slate-600 dark:text-slate-300 font-medium flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                Inbound Hari Ini
                            </span>
                            <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400 tabular-nums">
                                +{{ $receiveData['today_count'] }} Trx
                            </span>
                        </div>

                        {{-- Telemetry 2: Transfer --}}
                        <div class="flex items-center justify-between p-2.5 rounded-lg bg-slate-50/70 dark:bg-slate-900/40 border border-slate-200/80 dark:border-slate-700/80">
                            <span class="text-slate-600 dark:text-slate-300 font-medium flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
                                Transfer Keluar
                            </span>
                            <div class="flex items-center gap-1.5">
                                <span class="font-mono font-bold text-sky-600 dark:text-sky-400 tabular-nums">
                                    {{ $transferData['today_count'] }} Trx
                                </span>
                                @if($transferData['in_transit_out'] > 0)
                                <span class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-300">
                                    {{ $transferData['in_transit_out'] }} Transit
                                </span>
                                @endif
                            </div>
                        </div>

                        {{-- Telemetry 3: Issue / Custody --}}
                        <div class="flex items-center justify-between p-2.5 rounded-lg bg-slate-50/70 dark:bg-slate-900/40 border border-slate-200/80 dark:border-slate-700/80">
                            <span class="text-slate-600 dark:text-slate-300 font-medium flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                Serah Teknisi
                            </span>
                            <span class="font-mono font-bold text-slate-700 dark:text-slate-300 tabular-nums">
                                −{{ $issueData['today_count'] }} Trx
                            </span>
                        </div>
                    </div>
                </div>

                {{-- 3. THE 3 LOGISTICAL STREAMS (MATRIX 3 KOLOM) --}}
                <div class="grid grid-cols-1 lg:grid-cols-3 divide-y lg:divide-y-0 lg:divide-x divide-slate-100 dark:divide-slate-700/60 bg-white dark:bg-slate-800">
                    
                    {{-- ══════════════════════════════════════════════════
                         PILAR 1 : BARANG MASUK (RECEIVE / INBOUND)
                         ══════════════════════════════════════════════════ --}}
                    <div class="p-5 flex flex-col justify-between space-y-4">
                        <div class="space-y-3.5">
                            {{-- Stream Header --}}
                            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/60">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                    <div>
                                        <h4 class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                            Barang Masuk
                                        </h4>
                                        <p class="text-[11px] font-semibold text-slate-800 dark:text-slate-200">
                                            {{ $isPusat ? 'Pengadaan Vendor & Registrasi SN' : 'Penerimaan Masuk Cabang' }}
                                        </p>
                                    </div>
                                </div>
                                <span class="px-2 py-0.5 rounded-full text-xs font-mono font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/80">
                                    +{{ $receiveData['today_count'] }}
                                </span>
                            </div>

                            {{-- Mini Stream List --}}
                            <div class="space-y-2">
                                @if($receiveData['recent']->isEmpty())
                                <div class="py-7 text-center rounded-lg border border-dashed border-slate-200 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/30 text-xs text-slate-400 space-y-1">
                                    <p class="font-medium text-slate-600 dark:text-slate-300">Belum ada barang masuk</p>
                                    <p class="text-[10.5px]">Penerimaan baru akan tercatat otomatis di sini.</p>
                                </div>
                                @else
                                @foreach($receiveData['recent'] as $rcv)
                                @php
                                    $rcvRoute = $canTransfer && $rcv->reference_number ? route('warehouse.receive.show', $rcv->reference_number) : null;
                                @endphp
                                <div class="p-3 rounded-lg bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 text-xs shadow-2xs hover:border-slate-300 dark:hover:border-slate-600 transition-colors group">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            @if($rcvRoute)
                                            <a href="{{ $rcvRoute }}" class="font-semibold text-slate-800 dark:text-slate-200 group-hover:text-sky-600 dark:group-hover:text-sky-400 group-hover:underline truncate block">
                                                {{ $rcv->item->name }}
                                            </a>
                                            @else
                                            <span class="font-semibold text-slate-800 dark:text-slate-200 truncate block">
                                                {{ $rcv->item->name }}
                                            </span>
                                            @endif
                                            @if($rcv->item->category)
                                            <span class="text-[10px] text-slate-400 font-medium">
                                                {{ $rcv->item->category->name }}
                                            </span>
                                            @endif
                                        </div>
                                        <span class="inline-flex px-2 py-0.5 rounded-md font-mono font-bold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/60 dark:border-emerald-800/60 text-xs shrink-0 tabular-nums">
                                            +{{ rtrim(rtrim(number_format((float) $rcv->qty, 2, ',', '.'), '0'), ',') }} {{ $rcv->item->unit }}
                                        </span>
                                    </div>
                                    <div class="mt-2 pt-1.5 border-t border-slate-100 dark:border-slate-700/50 flex items-center justify-between text-[10.5px] text-slate-400 font-mono">
                                        <span class="truncate">{{ $rcv->reference_number ? 'Ref: '.$rcv->reference_number : 'Oleh: '.($rcv->createdBy->name ?? 'Sistem') }}</span>
                                        <span class="shrink-0">{{ $rcv->created_at->format('d/m H:i') }}</span>
                                    </div>
                                    @if($rcv->serial)
                                    <div class="mt-1 flex items-center gap-1 text-[10.5px] font-mono text-sky-700 dark:text-sky-300 bg-sky-50 dark:bg-sky-950/40 px-2 py-0.5 rounded border border-sky-100 dark:border-sky-900/40 truncate">
                                        <span>SN: {{ $rcv->serial->serial_number }}</span>
                                    </div>
                                    @endif
                                </div>
                                @endforeach
                                @endif
                            </div>
                        </div>

                        {{-- Stream CTA Action --}}
                        <div class="pt-2">
                            @if($isPusat && $canReceive)
                            <a href="{{ route('warehouse.receive.create', ['pop_id' => $pop->id]) }}"
                               class="w-full flex items-center justify-center gap-1.5 py-2 px-3 rounded-lg text-xs font-semibold text-white bg-sky-600 hover:bg-sky-700 shadow-2xs transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                <span>Catat Penerimaan Baru</span>
                            </a>
                            @else
                            <a href="{{ route('warehouse.history.index', ['pop_id' => $pop->id, 'type' => 'receive']) }}"
                               class="w-full flex items-center justify-center gap-1 py-2 px-3 rounded-lg text-xs font-semibold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 transition-colors shadow-2xs">
                                <span>Lihat Log Penerimaan →</span>
                            </a>
                            @endif
                        </div>
                    </div>

                    {{-- ══════════════════════════════════════════════════
                         PILAR 2 : TRANSFER DISTRIBUSI (LOGISTICS)
                         ══════════════════════════════════════════════════ --}}
                    <div class="p-5 flex flex-col justify-between space-y-4">
                        <div class="space-y-3.5">
                            {{-- Stream Header --}}
                            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/60">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-sky-500"></span>
                                    <div>
                                        <h4 class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                            Transfer Antar Gudang
                                        </h4>
                                        <p class="text-[11px] font-semibold text-slate-800 dark:text-slate-200">
                                            {{ $isPusat ? 'Distribusi Logistik ke Cabang' : 'Mutasi Masuk & Minta Stok' }}
                                        </p>
                                    </div>
                                </div>
                                <span class="px-2 py-0.5 rounded-full text-xs font-mono font-bold bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800/80">
                                    {{ $transferData['today_count'] }}
                                </span>
                            </div>

                            {{-- In-Transit Radar Alert --}}
                            @if($transferData['in_transit_out'] > 0 || $transferData['in_transit_in'] > 0)
                            <div class="p-2.5 rounded-lg bg-amber-50 dark:bg-amber-950/50 border border-amber-200 dark:border-amber-800/80 flex items-center justify-between text-xs shadow-2xs">
                                <div class="flex items-center gap-2 text-amber-900 dark:text-amber-200 font-semibold">
                                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                    <span>
                                        @if($isPusat)
                                        {{ $transferData['in_transit_out'] }} Transfer Sedang Dikirim
                                        @else
                                        {{ $transferData['in_transit_in'] }} Transfer Menunggu Diterima
                                        @endif
                                    </span>
                                </div>
                                <span class="font-mono text-[10px] font-bold uppercase tracking-wider text-amber-700 dark:text-amber-300 bg-amber-100 dark:bg-amber-900/60 px-1.5 py-0.5 rounded">
                                    In-Transit
                                </span>
                            </div>
                            @endif

                            {{-- Mini Stream List --}}
                            <div class="space-y-2">
                                @if($transferData['recent']->isEmpty())
                                <div class="py-7 text-center rounded-lg border border-dashed border-slate-200 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/30 text-xs text-slate-400 space-y-1">
                                    <p class="font-medium text-slate-600 dark:text-slate-300">Belum ada mutasi transfer</p>
                                    <p class="text-[10.5px]">Distribusi antar gudang akan muncul di sini.</p>
                                </div>
                                @else
                                @foreach($transferData['recent'] as $trf)
                                @php
                                    $trfRoute = $canTransfer && $trf->inventory_transfer_id ? route('warehouse.transfers.show', $trf->inventory_transfer_id) : null;
                                    $isOutgoing = (int) $trf->from_pop_id === (int) $pop->id;
                                    $targetPopName = $isOutgoing
                                        ? ($trf->toPop->name ?? ($trf->transfer->toPop->name ?? 'Cabang Tujuan'))
                                        : ($trf->fromPop->name ?? 'Gudang Asal');
                                @endphp
                                <div class="p-3 rounded-lg bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 text-xs shadow-2xs hover:border-slate-300 dark:hover:border-slate-600 transition-colors group">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            @if($trfRoute)
                                            <a href="{{ $trfRoute }}" class="font-semibold text-slate-800 dark:text-slate-200 group-hover:text-sky-600 dark:group-hover:text-sky-400 group-hover:underline truncate block">
                                                {{ $trf->item->name }}
                                            </a>
                                            @else
                                            <span class="font-semibold text-slate-800 dark:text-slate-200 truncate block">
                                                {{ $trf->item->name }}
                                            </span>
                                            @endif
                                            <div class="flex items-center gap-1.5 text-[10.5px] font-semibold text-slate-500 dark:text-slate-400 mt-0.5">
                                                @if($isOutgoing)
                                                <svg class="w-3 h-3 text-sky-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                                                <span class="text-sky-600 dark:text-sky-400 truncate">{{ $targetPopName }}</span>
                                                @else
                                                <svg class="w-3 h-3 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                                                <span class="text-emerald-600 dark:text-emerald-400 truncate">{{ $targetPopName }}</span>
                                                @endif
                                            </div>
                                        </div>
                                        <span class="inline-flex px-2 py-0.5 rounded-md font-mono font-bold {{ $isOutgoing ? 'text-sky-700 dark:text-sky-300 bg-sky-50 dark:bg-sky-950/60 border border-sky-200/60 dark:border-sky-800/60' : 'text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/60 dark:border-emerald-800/60' }} text-xs shrink-0 tabular-nums">
                                            {{ $isOutgoing ? '−' : '+' }}{{ rtrim(rtrim(number_format((float) $trf->qty, 2, ',', '.'), '0'), ',') }} {{ $trf->item->unit }}
                                        </span>
                                    </div>
                                    <div class="mt-2 pt-1.5 border-t border-slate-100 dark:border-slate-700/50 flex items-center justify-between text-[10.5px] text-slate-400 font-mono">
                                        <span class="truncate">{{ $trf->reference_number ?? ($trf->transfer->reference_number ?? 'Transfer') }}</span>
                                        <span class="shrink-0">{{ $trf->created_at->format('d/m H:i') }}</span>
                                    </div>
                                    @if($trf->serial)
                                    <div class="mt-1 flex items-center gap-1 text-[10.5px] font-mono text-sky-700 dark:text-sky-300 bg-sky-50 dark:bg-sky-950/40 px-2 py-0.5 rounded border border-sky-100 dark:border-sky-900/40 truncate">
                                        <span>SN: {{ $trf->serial->serial_number }}</span>
                                    </div>
                                    @endif
                                </div>
                                @endforeach
                                @endif
                            </div>
                        </div>

                        {{-- Stream CTA Action --}}
                        <div class="pt-2">
                            @if($isPusat && $canTransfer)
                            <a href="{{ route('warehouse.transfers.create', ['from_pop_id' => $pop->id]) }}"
                               class="w-full flex items-center justify-center gap-1.5 py-2 px-3 rounded-lg text-xs font-semibold text-white bg-sky-600 hover:bg-sky-700 shadow-2xs transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/></svg>
                                <span>Kirim Transfer ke Cabang</span>
                            </a>
                            @elseif(!$isPusat && $user->hasPermission('warehouse_stock_request.create'))
                            <a href="{{ route('warehouse.stock-requests.create', ['pop_id' => $pop->id]) }}"
                               class="w-full flex items-center justify-center gap-1.5 py-2 px-3 rounded-lg text-xs font-semibold text-white bg-sky-600 hover:bg-sky-700 shadow-2xs transition-colors">
                                <span>+ Minta Stok ke Pusat</span>
                            </a>
                            @else
                            <a href="{{ route('warehouse.history.index', ['pop_id' => $pop->id, 'type' => 'transfer']) }}"
                               class="w-full flex items-center justify-center gap-1 py-2 px-3 rounded-lg text-xs font-semibold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 transition-colors shadow-2xs">
                                <span>Lihat Log Transfer →</span>
                            </a>
                            @endif
                        </div>
                    </div>

                    {{-- ══════════════════════════════════════════════════
                         PILAR 3 : SERAH TERIMA KE TEKNISI (ISSUE & CUSTODY)
                         ══════════════════════════════════════════════════ --}}
                    <div class="p-5 flex flex-col justify-between space-y-4">
                        <div class="space-y-3.5">
                            {{-- Stream Header --}}
                            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/60">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                                    <div>
                                        <h4 class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                            Diserahkan ke Teknisi
                                        </h4>
                                        <p class="text-[11px] font-semibold text-slate-800 dark:text-slate-200">
                                            Handover Material &amp; Tanggung Jawab
                                        </p>
                                    </div>
                                </div>
                                <span class="px-2 py-0.5 rounded-full text-xs font-mono font-bold bg-slate-100 dark:bg-slate-700/60 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-600">
                                    −{{ $issueData['today_count'] }}
                                </span>
                            </div>

                            {{-- Custody Active Summary --}}
                            @if($issueData['custody_serial'] > 0 || $issueData['custody_material'] > 0)
                            <div class="p-2.5 rounded-lg bg-slate-50/80 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 flex items-center justify-between text-xs shadow-2xs">
                                <div class="text-slate-700 dark:text-slate-300 font-medium">
                                    <span>Custody: </span>
                                    <strong class="font-mono font-bold text-slate-900 dark:text-slate-100">{{ $issueData['custody_serial'] }} SN</strong>
                                    <span class="text-slate-400">&bull;</span>
                                    <strong class="font-mono font-bold text-slate-900 dark:text-slate-100">{{ $issueData['custody_material'] }} Material</strong>
                                </div>
                                <a href="{{ route('warehouse.custody.index') }}" class="font-semibold text-sky-600 dark:text-sky-400 hover:underline text-[11px]">
                                    Lacak →
                                </a>
                            </div>
                            @endif

                            {{-- Mini Stream List --}}
                            <div class="space-y-2">
                                @if($issueData['recent']->isEmpty())
                                <div class="py-7 text-center rounded-lg border border-dashed border-slate-200 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/30 text-xs text-slate-400 space-y-1">
                                    <p class="font-medium text-slate-600 dark:text-slate-300">Belum ada serah terima</p>
                                    <p class="text-[10.5px]">Pengeluaran ke teknisi akan tercatat di sini.</p>
                                </div>
                                @else
                                @foreach($issueData['recent'] as $iss)
                                @php
                                    $issRoute = $canIssue && $iss->reference_number ? route('warehouse.issues.show', $iss->reference_number) : null;
                                @endphp
                                <div class="p-3 rounded-lg bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 text-xs shadow-2xs hover:border-slate-300 dark:hover:border-slate-600 transition-colors group">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            @if($issRoute)
                                            <a href="{{ $issRoute }}" class="font-semibold text-slate-800 dark:text-slate-200 group-hover:text-sky-600 dark:group-hover:text-sky-400 group-hover:underline truncate block">
                                                {{ $iss->item->name }}
                                            </a>
                                            @else
                                            <span class="font-semibold text-slate-800 dark:text-slate-200 truncate block">
                                                {{ $iss->item->name }}
                                            </span>
                                            @endif
                                            <div class="flex items-center gap-1.5 text-[10.5px] text-slate-600 dark:text-slate-300 font-medium mt-0.5">
                                                <span class="w-4 h-4 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 flex items-center justify-center text-[9px] font-bold">
                                                    {{ strtoupper(substr($iss->toTechnician->name ?? 'T', 0, 1)) }}
                                                </span>
                                                <span class="truncate">{{ $iss->toTechnician->name ?? 'Teknisi Lapangan' }}</span>
                                            </div>
                                        </div>
                                        <span class="inline-flex px-2 py-0.5 rounded-md font-mono font-bold text-rose-700 dark:text-rose-300 bg-rose-50 dark:bg-rose-950/60 border border-rose-200/60 dark:border-rose-800/60 text-xs shrink-0 tabular-nums">
                                            −{{ rtrim(rtrim(number_format((float) $iss->qty, 2, ',', '.'), '0'), ',') }} {{ $iss->item->unit }}
                                        </span>
                                    </div>
                                    <div class="mt-2 pt-1.5 border-t border-slate-100 dark:border-slate-700/50 flex items-center justify-between text-[10.5px] text-slate-400 font-mono">
                                        <span class="truncate">{{ $iss->reference_number ?? 'Handover' }}</span>
                                        <span class="shrink-0">{{ $iss->created_at->format('d/m H:i') }}</span>
                                    </div>
                                    @if($iss->serial)
                                    <div class="mt-1 flex items-center gap-1 text-[10.5px] font-mono text-sky-700 dark:text-sky-300 bg-sky-50 dark:bg-sky-950/40 px-2 py-0.5 rounded border border-sky-100 dark:border-sky-900/40 truncate">
                                        <span>SN: {{ $iss->serial->serial_number }}</span>
                                    </div>
                                    @endif
                                </div>
                                @endforeach
                                @endif
                            </div>
                        </div>

                        {{-- Stream CTA Action --}}
                        <div class="pt-2">
                            @if($canIssue)
                            <a href="{{ route('warehouse.issues.create', ['pop_id' => $pop->id]) }}"
                               class="w-full flex items-center justify-center gap-1.5 py-2 px-3 rounded-lg text-xs font-semibold text-white bg-sky-600 hover:bg-sky-700 shadow-2xs transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                                <span>Serahkan ke Teknisi</span>
                            </a>
                            @else
                            <a href="{{ route('warehouse.history.index', ['pop_id' => $pop->id, 'type' => 'issue']) }}"
                               class="w-full flex items-center justify-center gap-1 py-2 px-3 rounded-lg text-xs font-semibold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 transition-colors shadow-2xs">
                                <span>Lihat Log Issue →</span>
                            </a>
                            @endif
                        </div>
                    </div>

                </div>

                {{-- 4. CARD FOOTER (TELEMETRY & INVENTORY HEALTH) --}}
                <div class="px-5 sm:px-6 py-3 bg-slate-50/70 dark:bg-slate-900/50 border-t border-slate-100 dark:border-slate-700/60 flex flex-wrap items-center justify-between gap-3 text-xs">
                    <div class="flex flex-wrap items-center gap-3 text-slate-500 dark:text-slate-400 text-xs">
                        <span class="inline-flex items-center gap-1.5 font-medium">
                            <span class="w-1.5 h-1.5 rounded-full bg-slate-400 dark:bg-slate-500"></span>
                            <span>Total SKU:</span>
                            <strong class="text-slate-800 dark:text-slate-200 font-mono">{{ $stockData['total_sku'] }}</strong> jenis item
                        </span>
                        <span>&bull;</span>
                        @if($stockData['low_stock_count'] > 0)
                        <span class="text-rose-600 dark:text-rose-400 font-semibold flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                            <span>{{ $stockData['low_stock_count'] }} item di bawah ambang minimum</span>
                        </span>
                        @else
                        <span class="text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                            <span>Semua Stok Terjaga Aman</span>
                        </span>
                        @endif
                    </div>

                    <div class="flex items-center gap-3 font-semibold">
                        <a href="{{ route('warehouse.stock.index', ['pop_id' => $pop->id]) }}" class="text-sky-600 dark:text-sky-400 hover:underline inline-flex items-center gap-1">
                            <span>Manajemen Stok Lengkap</span>
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                            </svg>
                        </a>
                    </div>
                </div>

            </div>
            @endforeach
        </div>
        @endif
    </div>

</div>

@endsection
