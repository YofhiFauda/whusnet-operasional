@props([
    'active' => 'dashboard',
    'title' => null,
    'subtitle' => null,
    'backUrl' => null,
])

{{--
    Header terpadu Gudang — SATU-SATUNYA sumber header/tab-nav/quick-action
    buat SEMUA halaman modul Gudang (Dashboard, Stok, Riwayat, Custody,
    Traceability, Laporan, Permintaan Stok, Scan, Adjustments, Transaksi, Retur, dll).
--}}
@php
    $user = auth()->user();
    $canViewWarehouse = $user->hasPermission('warehouse.view');
    $canViewCustody = $user->hasPermission('warehouse_custody.view');
    $canViewTraceability = $user->hasPermission('warehouse_traceability.view');
    $canViewReport = $user->hasPermission('warehouse_report.view');
    $canViewSoldToCustomer = $user->hasPermission('warehouse_report.sold_to_customer.view');
    $canViewStockRequest = $user->hasPermission('warehouse_stock_request.view');
    $canViewTransfer = $user->hasPermission('warehouse_transfer.view');
    $canViewBranchSale = $user->hasPermission('warehouse_branch_sale.view');

    $canReceive = $user->hasPermission('warehouse_transfer.create');
    $canTransfer = $user->hasPermission('warehouse_transfer.create');
    $canIssue = $user->hasPermission('warehouse_issue.create');
    $canAdjust = $user->hasPermission('warehouse_adjustment.create');
    $canReassign = $user->hasPermission('warehouse_reassign.create');

    $isTransfersActive = in_array($active, ['transfers', 'transfers-pending', 'branch-sales', 'transfers-create', 'transfers-reverse'], true);
    $isReturnsActive = in_array($active, ['returns', 'returns-from-customer', 'from-customer', 'retrievals', 'damaged', 'pusat', 'dispatch'], true);
    $isFieldActive = in_array($active, ['custody', 'scan', 'traceability'], true);
    $isReportsActive = in_array($active, ['history', 'usage', 'reports', 'sold-to-customer'], true);

    $transfersActiveLabel = match ($active) {
        'transfers' => 'Daftar Transfer',
        'transfers-pending' => 'Konfirmasi',
        'branch-sales' => 'Jual Beli',
        'transfers-create' => 'Pusat → Cabang',
        'transfers-reverse' => 'Cabang → Pusat',
        default => null,
    };

    $returnsActiveLabel = match ($active) {
        'returns' => 'Terima Retur',
        'returns-from-customer', 'from-customer' => 'Dari Pelanggan',
        'dispatch' => 'Kirim ke Pusat',
        'pusat' => 'Terima di Pusat',
        'damaged' => 'Modem Rusak',
        'retrievals' => 'Riwayat Ambil',
        default => null,
    };

    $fieldActiveLabel = match ($active) {
        'custody' => 'Di Teknisi',
        'scan' => 'Scan Barang',
        'traceability' => 'Lacak SN',
        default => null,
    };

    $reportsActiveLabel = match ($active) {
        'history' => 'Riwayat Mutasi',
        'usage' => 'Pemakaian',
        'reports' => 'Laporan Stok',
        'sold-to-customer' => 'Barang Terjual',
        default => null,
    };

    $transfersUrl = $canViewTransfer
        ? route('warehouse.transfers.index')
        : ($canViewBranchSale ? route('warehouse.branch-sales.index') : ($canTransfer ? route('warehouse.transfers.create') : '#'));

    $returnsUrl = $canReassign
        ? route('warehouse.returns.index')
        : ($canViewWarehouse ? route('warehouse.damaged.index') : '#');

    $fieldUrl = $canViewCustody
        ? route('warehouse.custody.index')
        : ($canViewWarehouse ? route('warehouse.scan.index') : ($canViewTraceability ? route('warehouse.traceability.index') : '#'));

    $reportsUrl = $canViewWarehouse
        ? route('warehouse.history.index')
        : ($canViewReport ? route('warehouse.reports.index') : ($canViewSoldToCustomer ? route('warehouse.reports.sold-to-customer') : '#'));

    $tabClass = fn (string $key) => (($key === 'returns' && $isReturnsActive) || ($key === 'transfers' && $isTransfersActive) || ($key === 'custody' && $isFieldActive) || ($key === 'reports' && $isReportsActive) || $active === $key)
        ? 'bg-sky-600 text-white shadow-xs dark:bg-sky-500'
        : 'text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-700/80 hover:text-slate-900 dark:hover:text-white';

    $tabIconClass = fn (string $key) => (($key === 'returns' && $isReturnsActive) || ($key === 'transfers' && $isTransfersActive) || ($key === 'custody' && $isFieldActive) || ($key === 'reports' && $isReportsActive) || $active === $key)
        ? 'text-white'
        : 'text-slate-400 dark:text-slate-500';

    $vItemClass = fn (bool $isActive) => $isActive
        ? 'flex items-center justify-between gap-2.5 px-3 py-2 rounded-lg bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 font-bold border border-sky-200/80 dark:border-sky-800/70 shadow-2xs transition-colors'
        : 'flex items-center justify-between gap-2.5 px-3 py-2 rounded-lg text-slate-700 dark:text-slate-200 hover:bg-slate-100/90 dark:hover:bg-slate-700/60 hover:text-slate-900 dark:hover:text-white font-medium border border-transparent transition-colors';

    $vIconWrapClass = fn (bool $isActive, string $defaultBg = 'bg-slate-100 dark:bg-slate-700/70 text-slate-500 dark:text-slate-400') => $isActive
        ? 'w-7 h-7 rounded-md bg-sky-600 text-white flex items-center justify-center shrink-0 shadow-2xs'
        : "w-7 h-7 rounded-md {$defaultBg} flex items-center justify-center shrink-0";
@endphp

{{-- =========================================================================
     1. DESKTOP VIEW (lg+) — Compact 7-Tab Toolbar dengan Vertical Hover Dropdown
     ========================================================================= --}}
<div class="hidden lg:block space-y-3.5 mb-6">
    {{-- Baris 1: Judul + Cabang Switcher + Universal Search + Quick Actions --}}
    <div class="flex items-center justify-between gap-4 pb-3 border-b border-slate-200/80 dark:border-slate-800">
        <div class="flex items-center gap-3 min-w-0">
            @if($backUrl)
            <a href="{{ $backUrl }}"
               class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 flex items-center justify-center transition-all active:scale-95 shadow-2xs shrink-0"
               title="Kembali">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
                </svg>
            </a>
            @endif
            <div class="min-w-0">
                <h1 class="text-xl font-bold text-slate-900 dark:text-slate-100 tracking-tight truncate">
                    {{ $title ?? 'Gudang & Inventori Logistik' }}
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 truncate">
                    {{ $subtitle ?? 'Pusat kendali stok, pergerakan barang, barang di tangan teknisi, dan pelacakan serial number perangkat aktif.' }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2.5 shrink-0" x-data="{ openQuickMenu: false }">
            {{-- Konteks cabang global (analisa-ui-ux-warehouse.md §S1) --}}
            @if(($warehousePops ?? collect())->count() > 1)
            <form action="{{ route('warehouse.switch-pop') }}" method="POST" class="flex items-center gap-1.5">
                @csrf
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 shrink-0">Cabang:</span>
                <div class="relative">
                    <select name="pop_id" onchange="this.form.submit()"
                            aria-label="Pilih cabang gudang — berlaku di semua halaman Gudang"
                            class="appearance-none bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800 rounded-lg pl-2.5 pr-7 py-1.5 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-sky-500/30 cursor-pointer hover:bg-sky-100 dark:hover:bg-sky-900/50 transition-colors">
                        <option value="" {{ $warehouseSelectedPopId === null ? 'selected' : '' }}>Semua Cabang Saya</option>
                        @foreach($warehousePops as $pop)
                        <option value="{{ $pop->id }}" {{ $warehouseSelectedPopId === $pop->id ? 'selected' : '' }}>{{ $pop->name }} ({{ strtoupper($pop->type) }})</option>
                        @endforeach
                    </select>
                    <svg class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 w-3 h-3 text-sky-500 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                    </svg>
                </div>
            </form>
            @endif

            {{-- Pencarian universal (analisa-ui-ux-warehouse.md §S2) --}}
            @if($canViewWarehouse)
            <form action="{{ route('warehouse.search') }}" method="GET" class="relative w-56 xl:w-64">
                <input type="text" name="q" value="{{ request()->routeIs('warehouse.search') ? request('q') : '' }}"
                       placeholder="Cari SN, roll, transfer, surat jalan..."
                       class="w-full pl-8 pr-3 py-1.5 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                <svg class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                </svg>
            </form>

            <a href="{{ route('warehouse.scan.index') }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg text-white bg-sky-600 hover:bg-sky-700 dark:bg-sky-500 dark:hover:bg-sky-600 shadow-xs shadow-sky-600/20 transition-all active:scale-95">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 7V5a1 1 0 011-1h2M4 17v2a1 1 0 001 1h2m10-14V5a1 1 0 00-1-1h-2m4 14v2a1 1 0 01-1 1h-2M7 12h10"/>
                </svg>
                <span>Scan Barang</span>
            </a>
            @endif

            @if($canReceive || $canTransfer || $canIssue || $canAdjust)
            <div class="relative" @click.outside="openQuickMenu = false">
                <button @click="openQuickMenu = !openQuickMenu" type="button"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-lg text-white bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 shadow-xs transition-all cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>Aksi Cepat</span>
                    <svg class="w-3 h-3 transition-transform duration-200" :class="openQuickMenu ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                    </svg>
                </button>

                <div x-show="openQuickMenu" x-cloak
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 translate-y-1 scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                     x-transition:leave-end="opacity-0 translate-y-1 scale-95"
                     class="absolute right-0 mt-1.5 w-64 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xl py-2 z-50 text-xs">
                    @if($canReceive || $canReassign)
                    <p class="px-3.5 pt-1 pb-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Masuk & Retur</p>
                    @if($canReceive)
                    <a href="{{ route('warehouse.receive.create') }}"
                       class="flex items-center gap-2.5 px-3.5 py-2 text-slate-700 dark:text-slate-200 hover:bg-emerald-50 dark:hover:bg-emerald-950/30 hover:text-emerald-700 dark:hover:text-emerald-400 transition-colors">
                        <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        <span class="font-semibold">Terima Barang Masuk</span>
                    </a>
                    @endif
                    @if($canReassign)
                    <a href="{{ route('warehouse.returns.index') }}"
                       class="flex items-center gap-2.5 px-3.5 py-2 text-slate-700 dark:text-slate-200 hover:bg-teal-50 dark:hover:bg-teal-950/30 hover:text-teal-700 dark:hover:text-teal-400 transition-colors">
                        <svg class="w-4 h-4 text-teal-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/></svg>
                        <div><p class="font-semibold">Terima Retur Modem</p><p class="text-[11px] text-slate-500 dark:text-slate-400">Konfirmasi modem dari teknisi</p></div>
                    </a>
                    <a href="{{ route('warehouse.returns.from-customer.create') }}"
                       class="flex items-center gap-2.5 px-3.5 py-2 text-slate-700 dark:text-slate-200 hover:bg-teal-50 dark:hover:bg-teal-950/30 hover:text-teal-700 dark:hover:text-teal-400 transition-colors">
                        <svg class="w-4 h-4 text-teal-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.765z"/></svg>
                        <div><p class="font-semibold">Terima Modem dari Pelanggan</p><p class="text-[11px] text-slate-500 dark:text-slate-400">Pelanggan antar langsung</p></div>
                    </a>
                    @endif
                    @endif

                    @if($canTransfer || $canIssue)
                    <p class="px-3.5 pt-2.5 pb-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 border-t border-slate-100 dark:border-slate-700/60 mt-1">Keluar</p>
                    @if($canTransfer)
                    <a href="{{ route('warehouse.transfers.create') }}"
                       class="flex items-center gap-2.5 px-3.5 py-2 text-slate-700 dark:text-slate-200 hover:bg-sky-50 dark:hover:bg-sky-950/30 hover:text-sky-700 dark:hover:text-sky-400 transition-colors">
                        <svg class="w-4 h-4 text-sky-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                        <div><p class="font-semibold">Transfer ke Cabang</p><p class="text-[11px] text-slate-500 dark:text-slate-400">Pusat → Gudang Cabang</p></div>
                    </a>
                    @endif
                    @if($canIssue)
                    <a href="{{ route('warehouse.issues.create') }}"
                       class="flex items-center gap-2.5 px-3.5 py-2 text-slate-700 dark:text-slate-200 hover:bg-indigo-50 dark:hover:bg-indigo-950/30 hover:text-indigo-700 dark:hover:text-indigo-400 transition-colors">
                        <svg class="w-4 h-4 text-indigo-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        <div><p class="font-semibold">Serah ke Teknisi</p><p class="text-[11px] text-slate-500 dark:text-slate-400">Cabang → Teknisi Lapangan</p></div>
                    </a>
                    @endif
                    @endif

                    @if($canAdjust)
                    <p class="px-3.5 pt-2.5 pb-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 border-t border-slate-100 dark:border-slate-700/60 mt-1">Penyesuaian</p>
                    <div class="flex items-center px-3.5 hover:bg-amber-50 dark:hover:bg-amber-950/30 group">
                        <a href="{{ route('warehouse.adjustments.balance.create') }}"
                           class="flex-1 flex items-center gap-2.5 py-2 text-slate-700 dark:text-slate-200 group-hover:text-amber-700 dark:group-hover:text-amber-400 transition-colors">
                            <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <div><p class="font-semibold">Penyesuaian Saldo</p><p class="text-[11px] text-slate-500 dark:text-slate-400">Koreksi manual stok rusak/susut</p></div>
                        </a>
                        <x-warehouse.info-tip align="left">
                            Input manual kalau ada kerugian yang <strong>SUDAH diketahui sebabnya</strong> (barang hilang/rusak). Wajib pilih alasan + lampirkan bukti foto. Beda dari Stock Opname — ini buat kasus yang udah jelas, bukan hasil hitung ulang fisik.
                        </x-warehouse.info-tip>
                    </div>
                    <div class="flex items-center px-3.5 hover:bg-amber-50 dark:hover:bg-amber-950/30 group">
                        <a href="{{ route('warehouse.adjustments.opname.create') }}"
                           class="flex-1 flex items-center gap-2.5 py-2 text-slate-700 dark:text-slate-200 group-hover:text-amber-700 dark:group-hover:text-amber-400 transition-colors">
                            <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            <div><p class="font-semibold">Stock Opname</p><p class="text-[11px] text-slate-500 dark:text-slate-400">Catat hasil hitung fisik gudang</p></div>
                        </a>
                        <x-warehouse.info-tip align="left">
                            Catat hasil <strong>hitung fisik</strong> stok gudang (jumlah beneran di rak) — sistem otomatis hitung selisih vs catatan sendiri, staf gak input selisihnya manual. Dipakai buat audit stok berkala, bukan koreksi kasus tunggal (itu Penyesuaian Saldo).
                        </x-warehouse.info-tip>
                    </div>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- Baris 2: Main Toolbar (7 Kategori Utama dengan Vertical Hover Dropdown Menu — Tanpa Overflow Clipping) --}}
    <div x-data="{ openMenu: null }"
         @keydown.escape.window="openMenu = null"
         class="rounded-xl bg-slate-100/90 dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700/80 p-1.5 shadow-2xs">

        <nav class="flex flex-wrap items-center gap-1" aria-label="Navigasi Utama Gudang">
            {{-- 1. Dashboard --}}
            @if($canViewWarehouse)
            <a href="{{ route('warehouse.index') }}"
               @mouseenter="openMenu = null"
               data-active-tab="{{ $active === 'dashboard' ? 'true' : 'false' }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ $tabClass('dashboard') }}">
                <svg class="w-3.5 h-3.5 {{ $tabIconClass('dashboard') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                <span>Dashboard</span>
            </a>

            {{-- 2. Kelola Stok --}}
            <a href="{{ route('warehouse.stock.index') }}"
               @mouseenter="openMenu = null"
               data-active-tab="{{ $active === 'stock' ? 'true' : 'false' }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ $tabClass('stock') }}">
                <svg class="w-3.5 h-3.5 {{ $tabIconClass('stock') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                <span>Kelola Stok</span>
            </a>
            @endif

            {{-- 3. Daftar Transaksi (Vertical Hover Dropdown: Daftar Transfer, Konfirmasi, Jual Beli Barang, Pusat -> Cabang, Cabang -> Pusat) --}}
            @if($canViewTransfer || $canViewBranchSale || $canTransfer)
            <div class="relative"
                 @mouseenter="openMenu = 'transfers'"
                 @mouseleave="if (openMenu === 'transfers') openMenu = null"
                 @click.outside="if (openMenu === 'transfers') openMenu = null">
                <div class="inline-flex items-center rounded-lg transition-all {{ $isTransfersActive ? 'bg-sky-600 text-white shadow-xs dark:bg-sky-500' : 'text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-700/80 hover:text-slate-900 dark:hover:text-white' }}"
                     :class="(!{{ $isTransfersActive ? 'true' : 'false' }} && openMenu === 'transfers') ? 'bg-white dark:bg-slate-700 text-sky-600 dark:text-sky-400 shadow-2xs ring-1 ring-slate-200 dark:ring-slate-600' : ''">
                    <a href="{{ $transfersUrl }}"
                       data-active-tab="{{ $isTransfersActive ? 'true' : 'false' }}"
                       class="inline-flex items-center gap-1.5 pl-3 pr-1.5 py-1.5 text-xs font-semibold">
                        <svg class="w-3.5 h-3.5 {{ $isTransfersActive ? 'text-white' : 'text-slate-400 dark:text-slate-500' }}" :class="(!{{ $isTransfersActive ? 'true' : 'false' }} && openMenu === 'transfers') ? 'text-sky-500' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                        <span>Daftar Transaksi</span>
                        @if($transfersActiveLabel)
                            <span class="px-1.5 py-0.5 rounded bg-white/20 text-[10px] font-bold tracking-tight">{{ $transfersActiveLabel }}</span>
                        @endif
                        @if(($badge_transfer_confirmation_count ?? 0) > 0)
                            <span data-badge="transfer-confirmation" class="rounded-full border border-amber-500/20 bg-amber-500/15 px-1.5 py-0.5 text-[9px] font-bold {{ $isTransfersActive ? 'bg-white/20 text-white border-white/30' : 'text-amber-600 dark:text-amber-400' }}">{{ $badge_transfer_confirmation_count }}</span>
                        @endif
                    </a>
                    <button type="button"
                            @click.prevent="openMenu = (openMenu === 'transfers' ? null : 'transfers')"
                            class="pr-2.5 pl-1 py-1.5 opacity-75 hover:opacity-100 cursor-pointer"
                            title="Menu Daftar Transaksi">
                        <svg class="w-3 h-3 transition-transform duration-150" :class="openMenu === 'transfers' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                    </button>
                </div>

                {{-- Vertical Hover Popover: Daftar Transaksi --}}
                <div x-show="openMenu === 'transfers'" x-cloak
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 translate-y-1 scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                     x-transition:leave-end="opacity-0 translate-y-1 scale-95"
                     class="absolute left-0 top-full pt-1.5 w-72 z-50">
                    <div class="rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xl p-2 text-xs divide-y divide-slate-100 dark:divide-slate-700/60">
                        <div class="pb-1.5 space-y-1">
                            <p class="px-2.5 pt-1 pb-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Daftar & Transaksi</p>
                            @if($canViewTransfer)
                            <a href="{{ route('warehouse.transfers.index') }}" class="{{ $vItemClass($active === 'transfers') }}">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="{{ $vIconWrapClass($active === 'transfers', 'bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400') }}">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate">Daftar Transfer</p>
                                        <p class="text-[11px] font-normal text-slate-500 dark:text-slate-400 truncate">Semua riwayat & surat jalan</p>
                                    </div>
                                </div>
                            </a>
                            <a href="{{ route('warehouse.transfers.pending') }}" class="{{ $vItemClass($active === 'transfers-pending') }}">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="{{ $vIconWrapClass($active === 'transfers-pending', 'bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400') }}">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate">Konfirmasi Transfer</p>
                                        <p class="text-[11px] font-normal text-slate-500 dark:text-slate-400 truncate">Barang dalam perjalanan (in-transit)</p>
                                    </div>
                                </div>
                                @if(($badge_transfer_confirmation_count ?? 0) > 0)
                                    <span data-badge="transfer-confirmation" class="rounded-full border border-amber-500/20 bg-amber-500/15 px-1.5 py-0.5 text-[10px] font-bold text-amber-600 dark:text-amber-400 shrink-0">{{ $badge_transfer_confirmation_count }}</span>
                                @endif
                            </a>
                            @endif
                            @if($canViewBranchSale)
                            <a href="{{ route('warehouse.branch-sales.index') }}" class="{{ $vItemClass($active === 'branch-sales') }}">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="{{ $vIconWrapClass($active === 'branch-sales', 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400') }}">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate">Jual Beli Barang</p>
                                        <p class="text-[11px] font-normal text-slate-500 dark:text-slate-400 truncate">Jual beli SN antar cabang</p>
                                    </div>
                                </div>
                            </a>
                            @endif
                        </div>

                        @if($canTransfer)
                        <div class="pt-1.5 space-y-1">
                            <p class="px-2.5 pt-1 pb-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Buat Transfer Baru</p>
                            <a href="{{ route('warehouse.transfers.create') }}" class="{{ $vItemClass($active === 'transfers-create') }}">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="{{ $vIconWrapClass($active === 'transfers-create', 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400') }}">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate">Transfer Pusat → Cabang</p>
                                        <p class="text-[11px] font-normal text-slate-500 dark:text-slate-400 truncate">Distribusi stok ke cabang</p>
                                    </div>
                                </div>
                            </a>
                            <a href="{{ route('warehouse.transfers.create', ['direction' => 'reverse']) }}" class="{{ $vItemClass($active === 'transfers-reverse') }}">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="{{ $vIconWrapClass($active === 'transfers-reverse', 'bg-teal-50 dark:bg-teal-950/50 text-teal-600 dark:text-teal-400') }}">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/></svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate">Transfer Cabang → Pusat</p>
                                        <p class="text-[11px] font-normal text-slate-500 dark:text-slate-400 truncate">Kirim balik stok ke pusat</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endif

            {{-- 4. Barang Retur (Vertical Hover Dropdown: 6 Tab Lengkap) --}}
            @if($canReassign || $canViewWarehouse)
            <div class="relative"
                 @mouseenter="openMenu = 'returns'"
                 @mouseleave="if (openMenu === 'returns') openMenu = null"
                 @click.outside="if (openMenu === 'returns') openMenu = null">
                <div class="inline-flex items-center rounded-lg transition-all {{ $isReturnsActive ? 'bg-sky-600 text-white shadow-xs dark:bg-sky-500' : 'text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-700/80 hover:text-slate-900 dark:hover:text-white' }}"
                     :class="(!{{ $isReturnsActive ? 'true' : 'false' }} && openMenu === 'returns') ? 'bg-white dark:bg-slate-700 text-sky-600 dark:text-sky-400 shadow-2xs ring-1 ring-slate-200 dark:ring-slate-600' : ''">
                    <a href="{{ $returnsUrl }}"
                       data-active-tab="{{ $isReturnsActive ? 'true' : 'false' }}"
                       class="inline-flex items-center gap-1.5 pl-3 pr-1.5 py-1.5 text-xs font-semibold">
                        <svg class="w-3.5 h-3.5 {{ $isReturnsActive ? 'text-white' : 'text-slate-400 dark:text-slate-500' }}" :class="(!{{ $isReturnsActive ? 'true' : 'false' }} && openMenu === 'returns') ? 'text-sky-500' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/></svg>
                        <span>Barang Retur</span>
                        @if($returnsActiveLabel)
                            <span class="px-1.5 py-0.5 rounded bg-white/20 text-[10px] font-bold tracking-tight">{{ $returnsActiveLabel }}</span>
                        @endif
                    </a>
                    <button type="button"
                            @click.prevent="openMenu = (openMenu === 'returns' ? null : 'returns')"
                            class="pr-2.5 pl-1 py-1.5 opacity-75 hover:opacity-100 cursor-pointer"
                            title="Menu 6 Tab Barang Retur">
                        <svg class="w-3 h-3 transition-transform duration-150" :class="openMenu === 'returns' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                    </button>
                </div>

                {{-- Vertical Hover Popover: 6 Tab Barang Retur --}}
                <div x-show="openMenu === 'returns'" x-cloak
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 translate-y-1 scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                     x-transition:leave-end="opacity-0 translate-y-1 scale-95"
                     class="absolute left-0 top-full pt-1.5 w-76 z-50">
                    <div class="rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xl p-2 text-xs space-y-1">
                        <p class="px-2.5 pt-1 pb-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">6 Alur Barang Retur & Kerugian</p>
                        @if($canReassign)
                        <a href="{{ route('warehouse.returns.index') }}" class="{{ $vItemClass($active === 'returns') }}">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="{{ $vIconWrapClass($active === 'returns', 'bg-teal-50 dark:bg-teal-950/50 text-teal-600 dark:text-teal-400') }}">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate">Terima Retur</p>
                                    <p class="text-[11px] font-normal text-slate-500 dark:text-slate-400 truncate">Konfirmasi modem transit dari teknisi</p>
                                </div>
                            </div>
                        </a>
                        <a href="{{ route('warehouse.returns.from-customer.create') }}" class="{{ $vItemClass(in_array($active, ['returns-from-customer', 'from-customer'], true)) }}">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="{{ $vIconWrapClass(in_array($active, ['returns-from-customer', 'from-customer'], true), 'bg-teal-50 dark:bg-teal-950/50 text-teal-600 dark:text-teal-400') }}">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.765z"/></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate">Terima Modem dari Pelanggan</p>
                                    <p class="text-[11px] font-normal text-slate-500 dark:text-slate-400 truncate">Pelanggan antar langsung ke cabang</p>
                                </div>
                            </div>
                        </a>
                        <a href="{{ route('warehouse.returns.dispatch.index') }}" class="{{ $vItemClass($active === 'dispatch') }}">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="{{ $vIconWrapClass($active === 'dispatch', 'bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400') }}">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4"/></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate">Kirim ke Pusat</p>
                                    <p class="text-[11px] font-normal text-slate-500 dark:text-slate-400 truncate">Kirim modem retur cabang ke Pusat</p>
                                </div>
                            </div>
                        </a>
                        <a href="{{ route('warehouse.returns.pusat.index') }}" class="{{ $vItemClass($active === 'pusat') }}">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="{{ $vIconWrapClass($active === 'pusat', 'bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400') }}">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate">Terima di Pusat</p>
                                    <p class="text-[11px] font-normal text-slate-500 dark:text-slate-400 truncate">Verifikasi final retur di Gudang Pusat</p>
                                </div>
                            </div>
                        </a>
                        @endif
                        @if($canViewWarehouse)
                        <a href="{{ route('warehouse.damaged.index') }}" class="{{ $vItemClass($active === 'damaged') }}">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="{{ $vIconWrapClass($active === 'damaged', 'bg-rose-50 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400') }}">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate">Modem Rusak</p>
                                    <p class="text-[11px] font-normal text-slate-500 dark:text-slate-400 truncate">Daftar SN, roll & stok rusak/karantina</p>
                                </div>
                            </div>
                        </a>
                        <a href="{{ route('warehouse.retrievals.index') }}" class="{{ $vItemClass($active === 'retrievals') }}">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="{{ $vIconWrapClass($active === 'retrievals', 'bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400') }}">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate">Riwayat Pengambilan Alat</p>
                                    <p class="text-[11px] font-normal text-slate-500 dark:text-slate-400 truncate">Log penarikan alat pelanggan putus</p>
                                </div>
                            </div>
                        </a>
                        @endif
                    </div>
                </div>
            </div>
            @endif

            {{-- 5. Teknisi & Lapangan (Vertical Hover Dropdown) --}}
            @if($canViewCustody || $canViewWarehouse || $canViewTraceability)
            <div class="relative"
                 @mouseenter="openMenu = 'field'"
                 @mouseleave="if (openMenu === 'field') openMenu = null"
                 @click.outside="if (openMenu === 'field') openMenu = null">
                <div class="inline-flex items-center rounded-lg transition-all {{ $isFieldActive ? 'bg-sky-600 text-white shadow-xs dark:bg-sky-500' : 'text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-700/80 hover:text-slate-900 dark:hover:text-white' }}"
                     :class="(!{{ $isFieldActive ? 'true' : 'false' }} && openMenu === 'field') ? 'bg-white dark:bg-slate-700 text-sky-600 dark:text-sky-400 shadow-2xs ring-1 ring-slate-200 dark:ring-slate-600' : ''">
                    <a href="{{ $fieldUrl }}"
                       data-active-tab="{{ $isFieldActive ? 'true' : 'false' }}"
                       class="inline-flex items-center gap-1.5 pl-3 pr-1.5 py-1.5 text-xs font-semibold">
                        <svg class="w-3.5 h-3.5 {{ $isFieldActive ? 'text-white' : 'text-slate-400 dark:text-slate-500' }}" :class="(!{{ $isFieldActive ? 'true' : 'false' }} && openMenu === 'field') ? 'text-sky-500' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        <span>Teknisi & Lapangan</span>
                        @if($fieldActiveLabel)
                            <span class="px-1.5 py-0.5 rounded bg-white/20 text-[10px] font-bold tracking-tight">{{ $fieldActiveLabel }}</span>
                        @endif
                    </a>
                    <button type="button"
                            @click.prevent="openMenu = (openMenu === 'field' ? null : 'field')"
                            class="pr-2.5 pl-1 py-1.5 opacity-75 hover:opacity-100 cursor-pointer"
                            title="Menu Teknisi & Lapangan">
                        <svg class="w-3 h-3 transition-transform duration-150" :class="openMenu === 'field' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                    </button>
                </div>

                {{-- Vertical Hover Popover: Teknisi & Lapangan --}}
                <div x-show="openMenu === 'field'" x-cloak
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 translate-y-1 scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                     x-transition:leave-end="opacity-0 translate-y-1 scale-95"
                     class="absolute left-0 top-full pt-1.5 w-72 z-50">
                    <div class="rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xl p-2 text-xs space-y-1">
                        <p class="px-2.5 pt-1 pb-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Operasional Lapangan</p>
                        @if($canViewCustody)
                        <a href="{{ route('warehouse.custody.index') }}" class="{{ $vItemClass($active === 'custody') }}">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="{{ $vIconWrapClass($active === 'custody', 'bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400') }}">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate">Barang di Tangan Teknisi</p>
                                    <p class="text-[11px] font-normal text-slate-500 dark:text-slate-400 truncate">Stok material & SN dibawa teknisi</p>
                                </div>
                            </div>
                        </a>
                        @endif
                        @if($canViewWarehouse)
                        <a href="{{ route('warehouse.scan.index') }}" class="{{ $vItemClass($active === 'scan') }}">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="{{ $vIconWrapClass($active === 'scan', 'bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400') }}">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7V5a1 1 0 011-1h2M4 17v2a1 1 0 001 1h2m10-14V5a1 1 0 00-1-1h-2m4 14v2a1 1 0 01-1 1h-2M7 12h10"/></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate">Scan Barang</p>
                                    <p class="text-[11px] font-normal text-slate-500 dark:text-slate-400 truncate">Scan barcode SN / roll cepat</p>
                                </div>
                            </div>
                        </a>
                        @endif
                        @if($canViewTraceability)
                        <a href="{{ route('warehouse.traceability.index') }}" class="{{ $vItemClass($active === 'traceability') }}">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="{{ $vIconWrapClass($active === 'traceability', 'bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400') }}">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate">Lacak Barang / SN</p>
                                    <p class="text-[11px] font-normal text-slate-500 dark:text-slate-400 truncate">Histori perjalanan perangkat & roll</p>
                                </div>
                            </div>
                        </a>
                        @endif
                    </div>
                </div>
            </div>
            @endif

            {{-- 6. Permintaan Stok --}}
            @if($canViewStockRequest)
            <a href="{{ route('warehouse.stock-requests.index') }}"
               @mouseenter="openMenu = null"
               data-active-tab="{{ $active === 'stock-requests' ? 'true' : 'false' }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ $tabClass('stock-requests') }}">
                <svg class="w-3.5 h-3.5 {{ $tabIconClass('stock-requests') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z"/></svg>
                <span>Permintaan Stok</span>
            </a>
            @endif

            {{-- 7. Laporan & Mutasi (Vertical Hover Dropdown) --}}
            @if($canViewWarehouse || $canViewReport || $canViewSoldToCustomer)
            <div class="relative"
                 @mouseenter="openMenu = 'reports'"
                 @mouseleave="if (openMenu === 'reports') openMenu = null"
                 @click.outside="if (openMenu === 'reports') openMenu = null">
                <div class="inline-flex items-center rounded-lg transition-all {{ $isReportsActive ? 'bg-sky-600 text-white shadow-xs dark:bg-sky-500' : 'text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-700/80 hover:text-slate-900 dark:hover:text-white' }}"
                     :class="(!{{ $isReportsActive ? 'true' : 'false' }} && openMenu === 'reports') ? 'bg-white dark:bg-slate-700 text-sky-600 dark:text-sky-400 shadow-2xs ring-1 ring-slate-200 dark:ring-slate-600' : ''">
                    <a href="{{ $reportsUrl }}"
                       data-active-tab="{{ $isReportsActive ? 'true' : 'false' }}"
                       class="inline-flex items-center gap-1.5 pl-3 pr-1.5 py-1.5 text-xs font-semibold">
                        <svg class="w-3.5 h-3.5 {{ $isReportsActive ? 'text-white' : 'text-slate-400 dark:text-slate-500' }}" :class="(!{{ $isReportsActive ? 'true' : 'false' }} && openMenu === 'reports') ? 'text-sky-500' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M18.7 8l-5.1 5.1-2.8-2.8L7 14"/></svg>
                        <span>Laporan & Mutasi</span>
                        @if($reportsActiveLabel)
                            <span class="px-1.5 py-0.5 rounded bg-white/20 text-[10px] font-bold tracking-tight">{{ $reportsActiveLabel }}</span>
                        @endif
                    </a>
                    <button type="button"
                            @click.prevent="openMenu = (openMenu === 'reports' ? null : 'reports')"
                            class="pr-2.5 pl-1 py-1.5 opacity-75 hover:opacity-100 cursor-pointer"
                            title="Menu Laporan & Mutasi">
                        <svg class="w-3 h-3 transition-transform duration-150" :class="openMenu === 'reports' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                    </button>
                </div>

                {{-- Vertical Hover Popover: Laporan & Mutasi --}}
                <div x-show="openMenu === 'reports'" x-cloak
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 translate-y-1 scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                     x-transition:leave-end="opacity-0 translate-y-1 scale-95"
                     class="absolute right-0 top-full pt-1.5 w-72 z-50">
                    <div class="rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xl p-2 text-xs space-y-1">
                        <p class="px-2.5 pt-1 pb-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Mutasi & Pelaporan</p>
                        @if($canViewWarehouse)
                        <a href="{{ route('warehouse.history.index') }}" class="{{ $vItemClass($active === 'history') }}">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="{{ $vIconWrapClass($active === 'history', 'bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400') }}">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate">Riwayat Mutasi</p>
                                    <p class="text-[11px] font-normal text-slate-500 dark:text-slate-400 truncate">Buku besar pergerakan barang</p>
                                </div>
                            </div>
                        </a>
                        <a href="{{ route('warehouse.usage.index') }}" class="{{ $vItemClass($active === 'usage') }}">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="{{ $vIconWrapClass($active === 'usage', 'bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400') }}">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate">Pemakaian Material</p>
                                    <p class="text-[11px] font-normal text-slate-500 dark:text-slate-400 truncate">Rekap pemakaian oleh teknisi</p>
                                </div>
                            </div>
                        </a>
                        @endif
                        @if($canViewReport)
                        <a href="{{ route('warehouse.reports.index') }}" class="{{ $vItemClass($active === 'reports') }}">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="{{ $vIconWrapClass($active === 'reports', 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400') }}">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M18.7 8l-5.1 5.1-2.8-2.8L7 14"/></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate">Laporan Stok</p>
                                    <p class="text-[11px] font-normal text-slate-500 dark:text-slate-400 truncate">Ringkasan valuasi & posisi stok</p>
                                </div>
                            </div>
                        </a>
                        @endif
                        @if($canViewSoldToCustomer)
                        <a href="{{ route('warehouse.reports.sold-to-customer') }}" class="{{ $vItemClass($active === 'sold-to-customer') }}">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="{{ $vIconWrapClass($active === 'sold-to-customer', 'bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400') }}">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate">Laporan Barang Terjual</p>
                                    <p class="text-[11px] font-normal text-slate-500 dark:text-slate-400 truncate">Perangkat ber-SN milik pelanggan</p>
                                </div>
                            </div>
                        </a>
                        @endif
                    </div>
                </div>
            </div>
            @endif
        </nav>
    </div>
</div>

{{-- =========================================================================
     2. MOBILE & TABLET VIEW (<lg) — Semua 7 Menu Terlihat Sekaligus + Floating Vertical Popover
     ========================================================================= --}}
<div class="lg:hidden space-y-2.5 pb-2.5 mb-4 border-b border-slate-200/80 dark:border-slate-800"
     x-data="{
         mobGroup: null,
         toggleMobGroup(name) {
             this.mobGroup = (this.mobGroup === name) ? null : name;
         }
     }"
     @keydown.escape.window="mobGroup = null">

    {{-- Baris 1: Tombol Kembali (opsional) + Judul Halaman + Fast Actions Hub --}}
    <div class="flex items-center justify-between gap-2">
        <div class="flex items-center gap-2 min-w-0 flex-1">
            @if($backUrl)
            <a href="{{ $backUrl }}"
               class="w-8 h-8 sm:w-9 sm:h-9 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 flex items-center justify-center transition-all active:scale-95 shadow-2xs shrink-0"
               title="Kembali">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
                </svg>
            </a>
            @endif

            <div class="min-w-0 flex-1">
                <h1 class="text-sm sm:text-base font-bold text-slate-900 dark:text-slate-100 tracking-tight leading-snug truncate">
                    {{ $title ?? 'Gudang & Inventori Logistik' }}
                </h1>
                @if($subtitle)
                <p class="hidden sm:block text-[11px] text-slate-500 dark:text-slate-400 truncate mt-0.5">
                    {{ $subtitle }}
                </p>
                @endif
            </div>
        </div>

        {{-- Fast Hub Dropdown & Scan Shortcut --}}
        <div class="flex items-center gap-1.5 shrink-0" x-data="{ openMobMenu: false }">
            @if($canViewWarehouse)
            <a href="{{ route('warehouse.scan.index') }}"
               class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold rounded-lg text-white bg-sky-600 hover:bg-sky-700 active:scale-95 shadow-xs shadow-sky-600/20 transition-all shrink-0"
               title="Buka Scanner Cepat">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 7V5a1 1 0 011-1h2M4 17v2a1 1 0 001 1h2m10-14V5a1 1 0 00-1-1h-2m4 14v2a1 1 0 01-1 1h-2M7 12h10"/>
                </svg>
                <span>Scan</span>
            </a>
            @endif

            @if($canReceive || $canTransfer || $canIssue || $canAdjust)
            <div class="relative" @click.outside="openMobMenu = false">
                <button @click="openMobMenu = !openMobMenu" type="button"
                        class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-bold rounded-lg text-white bg-slate-800 dark:bg-slate-700 hover:bg-slate-900 active:scale-95 shadow-2xs transition-all cursor-pointer shrink-0">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>Aksi</span>
                    <svg class="w-3 h-3 opacity-80 transition-transform duration-200" :class="openMobMenu ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                    </svg>
                </button>

                <div x-show="openMobMenu" x-cloak
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 translate-y-1 scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                     x-transition:leave-end="opacity-0 translate-y-1 scale-95"
                     class="absolute right-0 mt-2 w-72 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-2xl py-2 z-50 text-xs divide-y divide-slate-100 dark:divide-slate-700/60">
                    <div class="px-2 py-1 space-y-1">
                        <p class="px-2 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Masuk & Keluar Cepat</p>
                        @if($canReceive)
                        <a href="{{ route('warehouse.receive.create') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg text-slate-700 dark:text-slate-200 hover:bg-emerald-50 dark:hover:bg-emerald-950/30">
                            <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            <div><p class="font-semibold text-xs">Terima Barang Masuk</p><p class="text-[11px] text-slate-500 dark:text-slate-400">Inbound pengadaan Pusat</p></div>
                        </a>
                        @endif
                        @if($canTransfer)
                        <a href="{{ route('warehouse.transfers.create') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg text-slate-700 dark:text-slate-200 hover:bg-sky-50 dark:hover:bg-sky-950/30">
                            <svg class="w-4 h-4 text-sky-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                            <div><p class="font-semibold text-xs">Transfer ke Cabang</p><p class="text-[11px] text-slate-500 dark:text-slate-400">Pusat → Gudang Cabang</p></div>
                        </a>
                        @endif
                        @if($canIssue)
                        <a href="{{ route('warehouse.issues.create') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg text-slate-700 dark:text-slate-200 hover:bg-indigo-50 dark:hover:bg-indigo-950/30">
                            <svg class="w-4 h-4 text-indigo-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <div><p class="font-semibold text-xs">Serah ke Teknisi</p><p class="text-[11px] text-slate-500 dark:text-slate-400">Cabang → Teknisi Lapangan</p></div>
                        </a>
                        @endif
                    </div>
                    @if($canAdjust)
                    <div class="px-2 pt-1.5 pb-1 space-y-1">
                        <p class="px-2 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Penyesuaian & Opname</p>
                        <a href="{{ route('warehouse.adjustments.balance.create') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg text-slate-700 dark:text-slate-200 hover:bg-amber-50 dark:hover:bg-amber-950/30">
                            <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <div><p class="font-semibold text-xs">Penyesuaian Saldo</p><p class="text-[11px] text-slate-500 dark:text-slate-400">Koreksi rusak / susut</p></div>
                        </a>
                        <a href="{{ route('warehouse.adjustments.opname.create') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg text-slate-700 dark:text-slate-200 hover:bg-amber-50 dark:hover:bg-amber-950/30">
                            <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            <div><p class="font-semibold text-xs">Stock Opname</p><p class="text-[11px] text-slate-500 dark:text-slate-400">Audit hitung fisik rak</p></div>
                        </a>
                    </div>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- Baris 2: Cabang Switcher + Pencarian Universal dalam 1 Baris Kompak --}}
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-1.5">
        @if(($warehousePops ?? collect())->count() > 1)
        <form action="{{ route('warehouse.switch-pop') }}" method="POST" class="relative shrink-0 sm:w-48">
            @csrf
            <select name="pop_id" onchange="this.form.submit()"
                    aria-label="Pilih cabang gudang — berlaku di semua halaman Gudang"
                    class="w-full appearance-none bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800 rounded-lg pl-2.5 pr-7 py-1.5 text-[11px] font-bold focus:outline-none focus:ring-2 focus:ring-sky-500/30">
                <option value="" {{ $warehouseSelectedPopId === null ? 'selected' : '' }}>Cabang: Semua Cabang</option>
                @foreach($warehousePops as $pop)
                <option value="{{ $pop->id }}" {{ $warehouseSelectedPopId === $pop->id ? 'selected' : '' }}>{{ $pop->name }} ({{ strtoupper($pop->type) }})</option>
                @endforeach
            </select>
            <svg class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 w-3 h-3 text-sky-500 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
            </svg>
        </form>
        @endif

        @if($canViewWarehouse)
        <form action="{{ route('warehouse.search') }}" method="GET" class="relative flex-1 min-w-0">
            <input type="text" name="q" value="{{ request()->routeIs('warehouse.search') ? request('q') : '' }}"
                   placeholder="Cari SN, roll, transfer, surat jalan..."
                   class="w-full pl-8 pr-3 py-1.5 text-[11px] font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
            <svg class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
            </svg>
        </form>
        @endif
    </div>

    {{-- Baris 3: Mobile Navigation Grid/Wrap Bar (Semua 7 Kategori Terlihat Tanpa Geser Horizontal) + Floating Vertical Popover --}}
    <div class="relative" @click.outside="mobGroup = null">
        <div class="rounded-xl bg-slate-100/90 dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700/80 p-1.5 shadow-2xs">
            <nav class="flex flex-wrap items-center gap-1" aria-label="Navigasi Utama Gudang Mobile">
                {{-- 1. Dashboard --}}
                @if($canViewWarehouse)
                <a href="{{ route('warehouse.index') }}"
                   data-active-tab="{{ $active === 'dashboard' ? 'true' : 'false' }}"
                   class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-[11px] font-semibold transition-all {{ $tabClass('dashboard') }}">
                    <svg class="w-3.5 h-3.5 {{ $tabIconClass('dashboard') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    <span>Dashboard</span>
                </a>

                {{-- 2. Kelola Stok --}}
                <a href="{{ route('warehouse.stock.index') }}"
                   data-active-tab="{{ $active === 'stock' ? 'true' : 'false' }}"
                   class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-[11px] font-semibold transition-all {{ $tabClass('stock') }}">
                    <svg class="w-3.5 h-3.5 {{ $tabIconClass('stock') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    <span>Kelola Stok</span>
                </a>
                @endif

                {{-- 3. Daftar Transaksi --}}
                @if($canViewTransfer || $canViewBranchSale || $canTransfer)
                <button type="button" @click="toggleMobGroup('transfers')"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-[11px] font-semibold transition-all cursor-pointer {{ $isTransfersActive ? 'bg-sky-600 text-white shadow-xs dark:bg-sky-500' : 'text-slate-700 dark:text-slate-200 hover:bg-white dark:hover:bg-slate-700' }}"
                        :class="(!{{ $isTransfersActive ? 'true' : 'false' }} && mobGroup === 'transfers') ? 'bg-white dark:bg-slate-700 text-sky-600 dark:text-sky-400 shadow-2xs ring-1 ring-slate-200 dark:ring-slate-600' : ''">
                    <svg class="w-3.5 h-3.5 {{ $isTransfersActive ? 'text-white' : 'text-slate-400 dark:text-slate-500' }}" :class="(!{{ $isTransfersActive ? 'true' : 'false' }} && mobGroup === 'transfers') ? 'text-sky-500' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                    <span>Daftar Transaksi</span>
                    @if($transfersActiveLabel)
                        <span class="px-1.5 py-0.2 rounded bg-white/20 text-[10px] font-bold">{{ $transfersActiveLabel }}</span>
                    @endif
                    @if(($badge_transfer_confirmation_count ?? 0) > 0)
                        <span class="rounded-full border border-amber-500/20 bg-amber-500/15 px-1.5 py-0.2 text-[9px] font-bold {{ $isTransfersActive ? 'bg-white/20 text-white border-white/30' : 'text-amber-600 dark:text-amber-400' }}">{{ $badge_transfer_confirmation_count }}</span>
                    @endif
                    <svg class="w-3 h-3 opacity-80 transition-transform duration-150" :class="mobGroup === 'transfers' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                </button>
                @endif

                {{-- 4. Barang Retur --}}
                @if($canReassign || $canViewWarehouse)
                <button type="button" @click="toggleMobGroup('returns')"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-[11px] font-semibold transition-all cursor-pointer {{ $isReturnsActive ? 'bg-sky-600 text-white shadow-xs dark:bg-sky-500' : 'text-slate-700 dark:text-slate-200 hover:bg-white dark:hover:bg-slate-700' }}"
                        :class="(!{{ $isReturnsActive ? 'true' : 'false' }} && mobGroup === 'returns') ? 'bg-white dark:bg-slate-700 text-sky-600 dark:text-sky-400 shadow-2xs ring-1 ring-slate-200 dark:ring-slate-600' : ''">
                    <svg class="w-3.5 h-3.5 {{ $isReturnsActive ? 'text-white' : 'text-slate-400 dark:text-slate-500' }}" :class="(!{{ $isReturnsActive ? 'true' : 'false' }} && mobGroup === 'returns') ? 'text-sky-500' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/></svg>
                    <span>Barang Retur</span>
                    @if($returnsActiveLabel)
                        <span class="px-1.5 py-0.2 rounded bg-white/20 text-[10px] font-bold">{{ $returnsActiveLabel }}</span>
                    @endif
                    <svg class="w-3 h-3 opacity-80 transition-transform duration-150" :class="mobGroup === 'returns' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                </button>
                @endif

                {{-- 5. Teknisi & Lapangan --}}
                @if($canViewCustody || $canViewWarehouse || $canViewTraceability)
                <button type="button" @click="toggleMobGroup('field')"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-[11px] font-semibold transition-all cursor-pointer {{ $isFieldActive ? 'bg-sky-600 text-white shadow-xs dark:bg-sky-500' : 'text-slate-700 dark:text-slate-200 hover:bg-white dark:hover:bg-slate-700' }}"
                        :class="(!{{ $isFieldActive ? 'true' : 'false' }} && mobGroup === 'field') ? 'bg-white dark:bg-slate-700 text-sky-600 dark:text-sky-400 shadow-2xs ring-1 ring-slate-200 dark:ring-slate-600' : ''">
                    <svg class="w-3.5 h-3.5 {{ $isFieldActive ? 'text-white' : 'text-slate-400 dark:text-slate-500' }}" :class="(!{{ $isFieldActive ? 'true' : 'false' }} && mobGroup === 'field') ? 'text-sky-500' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span>Lapangan</span>
                    @if($fieldActiveLabel)
                        <span class="px-1.5 py-0.2 rounded bg-white/20 text-[10px] font-bold">{{ $fieldActiveLabel }}</span>
                    @endif
                    <svg class="w-3 h-3 opacity-80 transition-transform duration-150" :class="mobGroup === 'field' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                </button>
                @endif

                {{-- 6. Permintaan Stok --}}
                @if($canViewStockRequest)
                <a href="{{ route('warehouse.stock-requests.index') }}"
                   data-active-tab="{{ $active === 'stock-requests' ? 'true' : 'false' }}"
                   class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-[11px] font-semibold transition-all {{ $tabClass('stock-requests') }}">
                    <svg class="w-3.5 h-3.5 {{ $tabIconClass('stock-requests') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z"/></svg>
                    <span>Permintaan Stok</span>
                </a>
                @endif

                {{-- 7. Laporan & Mutasi --}}
                @if($canViewWarehouse || $canViewReport || $canViewSoldToCustomer)
                <button type="button" @click="toggleMobGroup('reports')"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-[11px] font-semibold transition-all cursor-pointer {{ $isReportsActive ? 'bg-sky-600 text-white shadow-xs dark:bg-sky-500' : 'text-slate-700 dark:text-slate-200 hover:bg-white dark:hover:bg-slate-700' }}"
                        :class="(!{{ $isReportsActive ? 'true' : 'false' }} && mobGroup === 'reports') ? 'bg-white dark:bg-slate-700 text-sky-600 dark:text-sky-400 shadow-2xs ring-1 ring-slate-200 dark:ring-slate-600' : ''">
                    <svg class="w-3.5 h-3.5 {{ $isReportsActive ? 'text-white' : 'text-slate-400 dark:text-slate-500' }}" :class="(!{{ $isReportsActive ? 'true' : 'false' }} && mobGroup === 'reports') ? 'text-sky-500' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M18.7 8l-5.1 5.1-2.8-2.8L7 14"/></svg>
                    <span>Laporan</span>
                    @if($reportsActiveLabel)
                        <span class="px-1.5 py-0.2 rounded bg-white/20 text-[10px] font-bold">{{ $reportsActiveLabel }}</span>
                    @endif
                    <svg class="w-3 h-3 opacity-80 transition-transform duration-150" :class="mobGroup === 'reports' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                </button>
                @endif
            </nav>
        </div>

        {{-- Floating Vertical Dropdown Popover Mobile (Tidak menggeser konten, lengkap dengan ikon & deskripsi) --}}
        <div x-show="mobGroup !== null" x-cloak
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 translate-y-1 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100 translate-y-0 scale-100"
             x-transition:leave-end="opacity-0 translate-y-1 scale-95"
             class="absolute left-0 right-0 top-full mt-1.5 z-50 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-2xl p-2.5 text-xs">

            {{-- Mobile Vertical List: Daftar Transaksi --}}
            <div x-show="mobGroup === 'transfers'" class="space-y-1.5">
                <div class="flex items-center justify-between px-2 py-1 border-b border-slate-100 dark:border-slate-700/60">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Daftar Transaksi & Transfer</span>
                    <button type="button" @click="mobGroup = null" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-0.5">✕</button>
                </div>
                <div class="flex flex-col gap-1">
                    @if($canViewTransfer)
                    <a href="{{ route('warehouse.transfers.index') }}" class="{{ $vItemClass($active === 'transfers') }}">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="{{ $vIconWrapClass($active === 'transfers', 'bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400') }}">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate">Daftar Transfer</p>
                                <p class="text-[11px] font-normal text-slate-500 dark:text-slate-400 truncate">Semua riwayat & surat jalan</p>
                            </div>
                        </div>
                    </a>
                    <a href="{{ route('warehouse.transfers.pending') }}" class="{{ $vItemClass($active === 'transfers-pending') }}">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="{{ $vIconWrapClass($active === 'transfers-pending', 'bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400') }}">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate">Konfirmasi Transfer</p>
                                <p class="text-[11px] font-normal text-slate-500 dark:text-slate-400 truncate">Barang dalam perjalanan (in-transit)</p>
                            </div>
                        </div>
                        @if(($badge_transfer_confirmation_count ?? 0) > 0)
                            <span class="rounded-full border border-amber-500/20 bg-amber-500/15 px-1.5 py-0.5 text-[10px] font-bold text-amber-600 dark:text-amber-400 shrink-0">{{ $badge_transfer_confirmation_count }}</span>
                        @endif
                    </a>
                    @endif
                    @if($canViewBranchSale)
                    <a href="{{ route('warehouse.branch-sales.index') }}" class="{{ $vItemClass($active === 'branch-sales') }}">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="{{ $vIconWrapClass($active === 'branch-sales', 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400') }}">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate">Jual Beli Barang</p>
                                <p class="text-[11px] font-normal text-slate-500 dark:text-slate-400 truncate">Jual beli SN antar cabang</p>
                            </div>
                        </div>
                    </a>
                    @endif
                    @if($canTransfer)
                    <div class="pt-1 mt-0.5 border-t border-slate-100 dark:border-slate-700/60">
                        <p class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Buat Transfer Baru</p>
                    </div>
                    <a href="{{ route('warehouse.transfers.create') }}" class="{{ $vItemClass($active === 'transfers-create') }}">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="{{ $vIconWrapClass($active === 'transfers-create', 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400') }}">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate">Transfer Pusat → Cabang</p>
                                <p class="text-[11px] font-normal text-slate-500 dark:text-slate-400 truncate">Distribusi stok ke cabang</p>
                            </div>
                        </div>
                    </a>
                    <a href="{{ route('warehouse.transfers.create', ['direction' => 'reverse']) }}" class="{{ $vItemClass($active === 'transfers-reverse') }}">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="{{ $vIconWrapClass($active === 'transfers-reverse', 'bg-teal-50 dark:bg-teal-950/50 text-teal-600 dark:text-teal-400') }}">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/></svg>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate">Transfer Cabang → Pusat</p>
                                <p class="text-[11px] font-normal text-slate-500 dark:text-slate-400 truncate">Kirim balik stok ke pusat</p>
                            </div>
                        </div>
                    </a>
                    @endif
                </div>
            </div>

            {{-- Mobile Vertical List: 6 Tab Barang Retur --}}
            <div x-show="mobGroup === 'returns'" class="space-y-1.5">
                <div class="flex items-center justify-between px-2 py-1 border-b border-slate-100 dark:border-slate-700/60">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">6 Alur Barang Retur & Kerugian</span>
                    <button type="button" @click="mobGroup = null" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-0.5">✕</button>
                </div>
                <div class="flex flex-col gap-1">
                    @if($canReassign)
                    <a href="{{ route('warehouse.returns.index') }}" class="{{ $vItemClass($active === 'returns') }}">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="{{ $vIconWrapClass($active === 'returns', 'bg-teal-50 dark:bg-teal-950/50 text-teal-600 dark:text-teal-400') }}">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/></svg>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate">Terima Retur</p>
                                <p class="text-[11px] font-normal text-slate-500 dark:text-slate-400 truncate">Konfirmasi modem transit dari teknisi</p>
                            </div>
                        </div>
                    </a>
                    <a href="{{ route('warehouse.returns.from-customer.create') }}" class="{{ $vItemClass(in_array($active, ['returns-from-customer', 'from-customer'], true)) }}">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="{{ $vIconWrapClass(in_array($active, ['returns-from-customer', 'from-customer'], true), 'bg-teal-50 dark:bg-teal-950/50 text-teal-600 dark:text-teal-400') }}">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.765z"/></svg>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate">Terima Modem dari Pelanggan</p>
                                <p class="text-[11px] font-normal text-slate-500 dark:text-slate-400 truncate">Pelanggan antar langsung ke cabang</p>
                            </div>
                        </div>
                    </a>
                    <a href="{{ route('warehouse.returns.dispatch.index') }}" class="{{ $vItemClass($active === 'dispatch') }}">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="{{ $vIconWrapClass($active === 'dispatch', 'bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400') }}">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4"/></svg>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate">Kirim ke Pusat</p>
                                <p class="text-[11px] font-normal text-slate-500 dark:text-slate-400 truncate">Kirim modem retur cabang ke Pusat</p>
                            </div>
                        </div>
                    </a>
                    <a href="{{ route('warehouse.returns.pusat.index') }}" class="{{ $vItemClass($active === 'pusat') }}">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="{{ $vIconWrapClass($active === 'pusat', 'bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400') }}">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate">Terima di Pusat</p>
                                <p class="text-[11px] font-normal text-slate-500 dark:text-slate-400 truncate">Verifikasi final retur di Gudang Pusat</p>
                            </div>
                        </div>
                    </a>
                    @endif
                    @if($canViewWarehouse)
                    <a href="{{ route('warehouse.damaged.index') }}" class="{{ $vItemClass($active === 'damaged') }}">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="{{ $vIconWrapClass($active === 'damaged', 'bg-rose-50 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400') }}">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate">Modem Rusak</p>
                                <p class="text-[11px] font-normal text-slate-500 dark:text-slate-400 truncate">Daftar SN, roll & stok rusak/karantina</p>
                            </div>
                        </div>
                    </a>
                    <a href="{{ route('warehouse.retrievals.index') }}" class="{{ $vItemClass($active === 'retrievals') }}">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="{{ $vIconWrapClass($active === 'retrievals', 'bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400') }}">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate">Riwayat Pengambilan Alat</p>
                                <p class="text-[11px] font-normal text-slate-500 dark:text-slate-400 truncate">Log penarikan alat pelanggan putus</p>
                            </div>
                        </div>
                    </a>
                    @endif
                </div>
            </div>

            {{-- Mobile Vertical List: Teknisi & Lapangan --}}
            <div x-show="mobGroup === 'field'" class="space-y-1.5">
                <div class="flex items-center justify-between px-2 py-1 border-b border-slate-100 dark:border-slate-700/60">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Operasional Lapangan</span>
                    <button type="button" @click="mobGroup = null" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-0.5">✕</button>
                </div>
                <div class="flex flex-col gap-1">
                    @if($canViewCustody)
                    <a href="{{ route('warehouse.custody.index') }}" class="{{ $vItemClass($active === 'custody') }}">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="{{ $vIconWrapClass($active === 'custody', 'bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400') }}">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate">Barang di Tangan Teknisi</p>
                                <p class="text-[11px] font-normal text-slate-500 dark:text-slate-400 truncate">Stok material & SN dibawa teknisi</p>
                            </div>
                        </div>
                    </a>
                    @endif
                    @if($canViewWarehouse)
                    <a href="{{ route('warehouse.scan.index') }}" class="{{ $vItemClass($active === 'scan') }}">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="{{ $vIconWrapClass($active === 'scan', 'bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400') }}">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7V5a1 1 0 011-1h2M4 17v2a1 1 0 001 1h2m10-14V5a1 1 0 00-1-1h-2m4 14v2a1 1 0 01-1 1h-2M7 12h10"/></svg>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate">Scan Barang</p>
                                <p class="text-[11px] font-normal text-slate-500 dark:text-slate-400 truncate">Scan barcode SN / roll cepat</p>
                            </div>
                        </div>
                    </a>
                    @endif
                    @if($canViewTraceability)
                    <a href="{{ route('warehouse.traceability.index') }}" class="{{ $vItemClass($active === 'traceability') }}">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="{{ $vIconWrapClass($active === 'traceability', 'bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400') }}">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/></svg>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate">Lacak Barang / SN</p>
                                <p class="text-[11px] font-normal text-slate-500 dark:text-slate-400 truncate">Histori perjalanan perangkat & roll</p>
                            </div>
                        </div>
                    </a>
                    @endif
                </div>
            </div>

            {{-- Mobile Vertical List: Laporan & Mutasi --}}
            <div x-show="mobGroup === 'reports'" class="space-y-1.5">
                <div class="flex items-center justify-between px-2 py-1 border-b border-slate-100 dark:border-slate-700/60">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Mutasi & Pelaporan</span>
                    <button type="button" @click="mobGroup = null" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-0.5">✕</button>
                </div>
                <div class="flex flex-col gap-1">
                    @if($canViewWarehouse)
                    <a href="{{ route('warehouse.history.index') }}" class="{{ $vItemClass($active === 'history') }}">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="{{ $vIconWrapClass($active === 'history', 'bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400') }}">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate">Riwayat Mutasi</p>
                                <p class="text-[11px] font-normal text-slate-500 dark:text-slate-400 truncate">Buku besar pergerakan barang</p>
                            </div>
                        </div>
                    </a>
                    <a href="{{ route('warehouse.usage.index') }}" class="{{ $vItemClass($active === 'usage') }}">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="{{ $vIconWrapClass($active === 'usage', 'bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400') }}">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate">Pemakaian Material</p>
                                <p class="text-[11px] font-normal text-slate-500 dark:text-slate-400 truncate">Rekap pemakaian oleh teknisi</p>
                            </div>
                        </div>
                    </a>
                    @endif
                    @if($canViewReport)
                    <a href="{{ route('warehouse.reports.index') }}" class="{{ $vItemClass($active === 'reports') }}">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="{{ $vIconWrapClass($active === 'reports', 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400') }}">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M18.7 8l-5.1 5.1-2.8-2.8L7 14"/></svg>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate">Laporan Stok</p>
                                <p class="text-[11px] font-normal text-slate-500 dark:text-slate-400 truncate">Ringkasan valuasi & posisi stok</p>
                            </div>
                        </div>
                    </a>
                    @endif
                    @if($canViewSoldToCustomer)
                    <a href="{{ route('warehouse.reports.sold-to-customer') }}" class="{{ $vItemClass($active === 'sold-to-customer') }}">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="{{ $vIconWrapClass($active === 'sold-to-customer', 'bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400') }}">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate">Laporan Barang Terjual</p>
                                <p class="text-[11px] font-normal text-slate-500 dark:text-slate-400 truncate">Perangkat ber-SN milik pelanggan</p>
                            </div>
                        </div>
                    </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
