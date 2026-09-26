@props([
    'active' => 'dashboard',
    'title' => null,
    'subtitle' => null,
    'backUrl' => null,
])

{{--
    Header terpadu Gudang — SATU-SATUNYA sumber header/tab-nav/quick-action
    buat SEMUA halaman modul Gudang (Dashboard, Stok, Riwayat, Custody,
    Traceability, Laporan, Permintaan Stok, Scan, Adjustments, Transaksi, dll).
--}}
@php
    $user = auth()->user();
    $canViewWarehouse = $user->hasPermission('warehouse.view');
    $canViewCustody = $user->hasPermission('warehouse_custody.view');
    $canViewTraceability = $user->hasPermission('warehouse_traceability.view');
    $canViewReport = $user->hasPermission('warehouse_report.view');
    $canViewStockRequest = $user->hasPermission('warehouse_stock_request.view');
    $canViewTransfer = $user->hasPermission('warehouse_transfer.view');

    $canReceive = $user->hasPermission('warehouse_transfer.create');
    $canTransfer = $user->hasPermission('warehouse_transfer.create');
    $canIssue = $user->hasPermission('warehouse_issue.create');
    $canAdjust = $user->hasPermission('warehouse_adjustment.create');

    $tabClass = fn (string $key) => $active === $key
        ? 'bg-sky-500 text-white shadow-xs shadow-sky-500/25 dark:bg-sky-500'
        : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60 hover:text-slate-900 dark:hover:text-white';
    $tabIconClass = fn (string $key) => $active === $key ? 'text-white' : 'text-slate-400 dark:text-slate-500';
    $groupLabelClass = 'text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 px-1 shrink-0 self-center';

    $mobDropdownClass = fn (string $key) => $active === $key
        ? 'flex items-center gap-1.5 px-2 py-1.5 rounded-lg bg-sky-50 dark:bg-sky-950/50 text-sky-700 dark:text-sky-300 text-[11px] font-bold border border-sky-200/60 dark:border-sky-800/60'
        : 'flex items-center gap-1.5 px-2 py-1.5 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 text-[11px] font-semibold';
    $mobDropdownIconClass = fn (string $key) => $active === $key ? 'text-sky-600 dark:text-sky-400' : 'text-slate-400 dark:text-slate-500';
@endphp

{{-- =========================================================================
     1. DESKTOP VIEW (lg+) — Header Tab-Nav Penuh dengan Grup Kategori
     ========================================================================= --}}
<div class="hidden lg:block space-y-4 mb-6">
    {{-- Baris 1: Judul + Quick Actions --}}
    <div class="flex items-center justify-between gap-4 pb-2 border-b border-slate-200/80 dark:border-slate-800">
        <div class="flex items-center gap-3">
            @if($backUrl)
            <a href="{{ $backUrl }}"
               class="w-10 h-10 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 flex items-center justify-center transition-all active:scale-95 shadow-2xs shrink-0"
               title="Kembali">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
                </svg>
            </a>
            @endif
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100 tracking-tight mt-0.5">
                    {{ $title ?? 'Gudang & Inventori Logistik' }}
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    {{ $subtitle ?? 'Pusat kendali stok, pergerakan barang, barang di tangan teknisi, dan pelacakan serial number perangkat aktif.' }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2 shrink-0 flex-wrap" x-data="{ openQuickMenu: false }">
            @if($canViewWarehouse)
            <a href="{{ route('warehouse.scan.index') }}"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-lg text-white bg-sky-600 hover:bg-sky-700 dark:bg-sky-500 dark:hover:bg-sky-600 shadow-xs shadow-sky-600/20 transition-all hover:scale-[1.02] active:scale-[0.98]">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 7V5a1 1 0 011-1h2M4 17v2a1 1 0 001 1h2m10-14V5a1 1 0 00-1-1h-2m4 14v2a1 1 0 01-1 1h-2M7 12h10"/>
                </svg>
                <span>Scan Barang</span>
            </a>
            @endif

            @if($canReceive || $canTransfer || $canIssue || $canAdjust)
            <div class="relative" @click.outside="openQuickMenu = false">
                <button @click="openQuickMenu = !openQuickMenu" type="button"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold rounded-lg text-white bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 shadow-xs transition-all cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>Aksi</span>
                    <svg class="w-3.5 h-3.5 transition-transform duration-200" :class="openQuickMenu ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
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
                     class="absolute right-0 mt-1.5 w-64 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xl py-2 z-50 text-xs">
                    @if($canReceive)
                    <p class="px-3.5 pt-1 pb-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Masuk</p>
                    <a href="{{ route('warehouse.receive.create') }}"
                       class="flex items-center gap-2.5 px-3.5 py-2 text-slate-700 dark:text-slate-200 hover:bg-emerald-50 dark:hover:bg-emerald-950/30 hover:text-emerald-700 dark:hover:text-emerald-400 transition-colors">
                        <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        <span class="font-semibold">Terima Barang</span>
                    </a>
                    @endif

                    @if($canTransfer || $canIssue)
                    <p class="px-3.5 pt-2.5 pb-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 border-t border-slate-100 dark:border-slate-700/60 mt-1">Keluar</p>
                    @if($canTransfer)
                    <a href="{{ route('warehouse.transfers.create') }}"
                       class="flex items-center gap-2.5 px-3.5 py-2 text-slate-700 dark:text-slate-200 hover:bg-sky-50 dark:hover:bg-sky-950/30 hover:text-sky-700 dark:hover:text-sky-400 transition-colors">
                        <svg class="w-4 h-4 text-sky-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                        <div><p class="font-semibold">Transfer ke Cabang</p><p class="text-[10px] text-slate-400">Pusat → Gudang Cabang</p></div>
                    </a>
                    @endif
                    @if($canIssue)
                    <a href="{{ route('warehouse.issues.create') }}"
                       class="flex items-center gap-2.5 px-3.5 py-2 text-slate-700 dark:text-slate-200 hover:bg-indigo-50 dark:hover:bg-indigo-950/30 hover:text-indigo-700 dark:hover:text-indigo-400 transition-colors">
                        <svg class="w-4 h-4 text-indigo-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        <div><p class="font-semibold">Serah ke Teknisi</p><p class="text-[10px] text-slate-400">Cabang → Teknisi Lapangan</p></div>
                    </a>
                    @endif
                    @endif

                    @if($canAdjust)
                    <p class="px-3.5 pt-2.5 pb-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 border-t border-slate-100 dark:border-slate-700/60 mt-1">Penyesuaian</p>
                    <div class="flex items-center px-3.5 hover:bg-amber-50 dark:hover:bg-amber-950/30 group">
                        <a href="{{ route('warehouse.adjustments.balance.create') }}"
                           class="flex-1 flex items-center gap-2.5 py-2 text-slate-700 dark:text-slate-200 group-hover:text-amber-700 dark:group-hover:text-amber-400 transition-colors">
                            <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <div><p class="font-semibold">Penyesuaian Saldo</p><p class="text-[10px] text-slate-400">Koreksi manual stok rusak/susut</p></div>
                        </a>
                        <x-warehouse.info-tip align="left">
                            Input manual kalau ada kerugian yang <strong>SUDAH diketahui sebabnya</strong> (barang hilang/rusak). Wajib pilih alasan + lampirkan bukti foto. Beda dari Stock Opname — ini buat kasus yang udah jelas, bukan hasil hitung ulang fisik.
                        </x-warehouse.info-tip>
                    </div>
                    <div class="flex items-center px-3.5 hover:bg-amber-50 dark:hover:bg-amber-950/30 group">
                        <a href="{{ route('warehouse.adjustments.opname.create') }}"
                           class="flex-1 flex items-center gap-2.5 py-2 text-slate-700 dark:text-slate-200 group-hover:text-amber-700 dark:group-hover:text-amber-400 transition-colors">
                            <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            <div><p class="font-semibold">Stock Opname</p><p class="text-[10px] text-slate-400">Catat hasil hitung fisik gudang</p></div>
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

    {{-- Baris 2: Tab nav dikelompokkan per fungsi --}}
    <div x-data="{
             init() {
                 this.$nextTick(() => {
                     const activeTab = this.$el.querySelector('[data-active-tab=\'true\']');
                     if (activeTab) {
                         activeTab.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' });
                     }
                 });
             }
         }"
         class="overflow-x-auto no-scrollbar [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden scroll-smooth pb-1">
        <nav class="flex items-center gap-1.5 min-w-max" aria-label="Navigasi Gudang">
            @if($canViewWarehouse)
            <span class="{{ $groupLabelClass }}">Ringkasan</span>
            <a href="{{ route('warehouse.index') }}"
               data-active-tab="{{ $active === 'dashboard' ? 'true' : 'false' }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ $tabClass('dashboard') }}">
                <svg class="w-3.5 h-3.5 {{ $tabIconClass('dashboard') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                <span>Dashboard</span>
            </a>
            <a href="{{ route('warehouse.history.index') }}"
               data-active-tab="{{ $active === 'history' ? 'true' : 'false' }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ $tabClass('history') }}">
                <svg class="w-3.5 h-3.5 {{ $tabIconClass('history') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Riwayat Mutasi</span>
            </a>
            <a href="{{ route('warehouse.usage.index') }}"
               data-active-tab="{{ $active === 'usage' ? 'true' : 'false' }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ $tabClass('usage') }}">
                <svg class="w-3.5 h-3.5 {{ $tabIconClass('usage') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                <span>Pemakaian Material</span>
            </a>
            @endif
            @if($canViewReport)
            <a href="{{ route('warehouse.reports.index') }}"
               data-active-tab="{{ $active === 'reports' ? 'true' : 'false' }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ $tabClass('reports') }}">
                <svg class="w-3.5 h-3.5 {{ $tabIconClass('reports') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M18.7 8l-5.1 5.1-2.8-2.8L7 14"/></svg>
                <span>Laporan</span>
            </a>
            @endif

            @if($canViewWarehouse || $canViewTraceability || $canViewTransfer)
            <div class="h-4 w-px bg-slate-200 dark:bg-slate-700 mx-1"></div>
            <span class="{{ $groupLabelClass }}">Stok</span>
            @if($canViewWarehouse)
            <a href="{{ route('warehouse.stock.index') }}"
               data-active-tab="{{ $active === 'stock' ? 'true' : 'false' }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ $tabClass('stock') }}">
                <svg class="w-3.5 h-3.5 {{ $tabIconClass('stock') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                <span>Kelola Stok</span>
            </a>
            @endif
            @if($canViewTransfer)
            <a href="{{ route('warehouse.transfers.pending') }}"
               data-active-tab="{{ $active === 'transfers-pending' ? 'true' : 'false' }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ $tabClass('transfers-pending') }}">
                <svg class="w-3.5 h-3.5 {{ $tabIconClass('transfers-pending') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                <span>Konfirmasi Transfer</span>
            </a>
            @endif
            @if($canViewTraceability)
            <a href="{{ route('warehouse.traceability.index') }}"
               data-active-tab="{{ $active === 'traceability' ? 'true' : 'false' }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ $tabClass('traceability') }}">
                <svg class="w-3.5 h-3.5 {{ $tabIconClass('traceability') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/></svg>
                <span>Lacak Barang / SN</span>
            </a>
            @endif
            @endif

            @if($canViewCustody || $canViewWarehouse)
            <div class="h-4 w-px bg-slate-200 dark:bg-slate-700 mx-1"></div>
            <span class="{{ $groupLabelClass }}">Lapangan</span>
            @if($canViewCustody)
            <div class="inline-flex items-center">
                <a href="{{ route('warehouse.custody.index') }}"
                   data-active-tab="{{ $active === 'custody' ? 'true' : 'false' }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ $tabClass('custody') }}">
                    <svg class="w-3.5 h-3.5 {{ $tabIconClass('custody') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    <span>Barang di Tangan Teknisi</span>
                </a>
                <x-warehouse.info-tip>
                    Daftar barang yang lagi <strong>dipegang tiap teknisi</strong> di lapangan (custody) — sudah keluar dari gudang, belum terpasang ke pelanggan. Dipakai buat pantau siapa pegang barang apa, dan alihkan custody / lapor rusak dari sini.
                </x-warehouse.info-tip>
            </div>
            @endif
            @if($canViewWarehouse)
            <a href="{{ route('warehouse.scan.index') }}"
               data-active-tab="{{ $active === 'scan' ? 'true' : 'false' }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ $tabClass('scan') }}">
                <svg class="w-3.5 h-3.5 {{ $tabIconClass('scan') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7V5a1 1 0 011-1h2M4 17v2a1 1 0 001 1h2m10-14V5a1 1 0 00-1-1h-2m4 14v2a1 1 0 01-1 1h-2M7 12h10"/></svg>
                <span>Scan Barang</span>
            </a>
            @endif
            @endif

            @if($canViewStockRequest)
            <div class="h-4 w-px bg-slate-200 dark:bg-slate-700 mx-1"></div>
            <span class="{{ $groupLabelClass }}">Permintaan</span>
            <a href="{{ route('warehouse.stock-requests.index') }}"
               data-active-tab="{{ $active === 'stock-requests' ? 'true' : 'false' }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ $tabClass('stock-requests') }}">
                <svg class="w-3.5 h-3.5 {{ $tabIconClass('stock-requests') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z"/></svg>
                <span>Permintaan Stok</span>
            </a>
            @endif
        </nav>
    </div>
</div>

{{-- =========================================================================
     2. MOBILE & TABLET VIEW (<lg) — Header Kompak dengan Mobilitas Tinggi
     ========================================================================= --}}
<div class="lg:hidden space-y-2.5 pb-3 mb-5 border-b border-slate-200/80 dark:border-slate-800">
    {{-- Baris 1: Tombol Kembali (opsional) + Judul Halaman + Fast Hub --}}
    <div class="flex items-center justify-between gap-2">
        <div class="flex items-center gap-2.5 min-w-0">
            @if($backUrl)
            <a href="{{ $backUrl }}"
               class="w-9 h-9 sm:w-10 sm:h-10 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 flex items-center justify-center transition-all active:scale-95 shadow-2xs shrink-0"
               title="Kembali">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
                </svg>
            </a>
            @endif

            <div class="{{ $backUrl ? 'hidden sm:block ' : '' }}min-w-0">
                <div class="flex items-center gap-1.5 flex-wrap">
                    <h1 class="text-base sm:text-lg font-bold text-slate-900 dark:text-slate-100 tracking-tight truncate">
                        {{ $title ?? 'Gudang & Inventori Logistik' }}
                    </h1>
                </div>
                @if($subtitle)
                <p class="hidden sm:block text-[11px] text-slate-500 dark:text-slate-400 truncate">
                    {{ $subtitle }}
                </p>
                @endif
            </div>
        </div>

        {{-- Fast Hub Dropdown & Scan Shortcut --}}
        <div class="flex items-center gap-2 shrink-0" x-data="{ openMobMenu: false }">
            @if($canViewWarehouse)
            <a href="{{ route('warehouse.scan.index') }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg text-white bg-sky-600 hover:bg-sky-700 dark:bg-sky-500 dark:hover:bg-sky-600 shadow-xs shadow-sky-600/20 transition-all hover:scale-[1.02] active:scale-[0.98]"
               title="Buka Scanner Cepat">
                <svg class="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 7V5a1 1 0 011-1h2M4 17v2a1 1 0 001 1h2m10-14V5a1 1 0 00-1-1h-2m4 14v2a1 1 0 01-1 1h-2M7 12h10"/>
                </svg>
                <span>Scan</span>
            </a>
            @endif

            @if($canReceive || $canTransfer || $canIssue || $canAdjust)
            <div class="relative" @click.outside="openMobMenu = false">
                <button @click="openMobMenu = !openMobMenu" type="button"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-lg text-white bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 shadow-xs transition-all cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                    </svg>
                    <span>Pindah</span>
                    <svg class="w-3.5 h-3.5 transition-transform duration-200" :class="openMobMenu ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                    </svg>
                </button>

                {{-- Popover Quick Actions & Navigation Menu --}}
                <div x-show="openMobMenu" x-cloak
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 translate-y-1 scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                     x-transition:leave-end="opacity-0 translate-y-1 scale-95"
                     class="absolute right-0 mt-2 w-72 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xl py-2 z-50 text-xs divide-y divide-slate-100 dark:divide-slate-700/60">
                    
                    <div class="px-2 py-1.5 space-y-1">
                        <p class="px-2 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Transaksi Cepat</p>
                        
                        @if($canReceive)
                        <a href="{{ route('warehouse.receive.create') }}"
                           class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg text-slate-700 dark:text-slate-200 hover:bg-emerald-50 dark:hover:bg-emerald-950/30 hover:text-emerald-700 dark:hover:text-emerald-400 transition-colors">
                            <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            <div>
                                <p class="font-semibold text-xs">Terima Barang Masuk</p>
                                <p class="text-[10px] text-slate-400">Inbound pengadaan Pusat</p>
                            </div>
                        </a>
                        @endif

                        @if($canTransfer)
                        <a href="{{ route('warehouse.transfers.create') }}"
                           class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg text-slate-700 dark:text-slate-200 hover:bg-sky-50 dark:hover:bg-sky-950/30 hover:text-sky-700 dark:hover:text-sky-400 transition-colors">
                            <svg class="w-4 h-4 text-sky-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                            <div>
                                <p class="font-semibold text-xs">Transfer ke Cabang</p>
                                <p class="text-[10px] text-slate-400">Pusat → Gudang Cabang</p>
                            </div>
                        </a>
                        @endif

                        @if($canIssue)
                        <a href="{{ route('warehouse.issues.create') }}"
                           class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg text-slate-700 dark:text-slate-200 hover:bg-indigo-50 dark:hover:bg-indigo-950/30 hover:text-indigo-700 dark:hover:text-indigo-400 transition-colors">
                            <svg class="w-4 h-4 text-indigo-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            <div>
                                <p class="font-semibold text-xs">Serah ke Teknisi</p>
                                <p class="text-[10px] text-slate-400">Cabang → Teknisi Lapangan</p>
                            </div>
                        </a>
                        @endif

                        @if($canAdjust)
                        <a href="{{ route('warehouse.adjustments.balance.create') }}"
                           class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg text-slate-700 dark:text-slate-200 hover:bg-amber-50 dark:hover:bg-amber-950/30 hover:text-amber-700 dark:hover:text-amber-400 transition-colors">
                            <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <div>
                                <p class="font-semibold text-xs">Penyesuaian Saldo</p>
                                <p class="text-[10px] text-slate-400">Koreksi rusak / susut</p>
                            </div>
                        </a>
                        <a href="{{ route('warehouse.adjustments.opname.create') }}"
                           class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg text-slate-700 dark:text-slate-200 hover:bg-amber-50 dark:hover:bg-amber-950/30 hover:text-amber-700 dark:hover:text-amber-400 transition-colors">
                            <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            <div>
                                <p class="font-semibold text-xs">Stock Opname</p>
                                <p class="text-[10px] text-slate-400">Audit hitung fisik rak</p>
                            </div>
                        </a>
                        @endif
                    </div>

                    <div class="px-2 py-1.5 space-y-1">
                        <p class="px-2 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Halaman Gudang</p>
                        <div class="grid grid-cols-2 gap-1">
                            @if($canViewWarehouse)
                            <a href="{{ route('warehouse.index') }}" class="{{ $mobDropdownClass('dashboard') }}">
                                <svg class="w-3.5 h-3.5 {{ $mobDropdownIconClass('dashboard') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                                <span>Dashboard</span>
                            </a>
                            <a href="{{ route('warehouse.stock.index') }}" class="{{ $mobDropdownClass('stock') }}">
                                <svg class="w-3.5 h-3.5 {{ $mobDropdownIconClass('stock') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                <span>Kelola Stok</span>
                            </a>
                            <a href="{{ route('warehouse.history.index') }}" class="{{ $mobDropdownClass('history') }}">
                                <svg class="w-3.5 h-3.5 {{ $mobDropdownIconClass('history') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>Riwayat</span>
                            </a>
                            <a href="{{ route('warehouse.usage.index') }}" class="{{ $mobDropdownClass('usage') }}">
                                <svg class="w-3.5 h-3.5 {{ $mobDropdownIconClass('usage') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                <span>Pemakaian</span>
                            </a>
                            @endif
                            @if($canViewReport)
                            <a href="{{ route('warehouse.reports.index') }}" class="{{ $mobDropdownClass('reports') }}">
                                <svg class="w-3.5 h-3.5 {{ $mobDropdownIconClass('reports') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M18.7 8l-5.1 5.1-2.8-2.8L7 14"/></svg>
                                <span>Laporan</span>
                            </a>
                            @endif
                            @if($canViewTraceability)
                            <a href="{{ route('warehouse.traceability.index') }}" class="{{ $mobDropdownClass('traceability') }}">
                                <svg class="w-3.5 h-3.5 {{ $mobDropdownIconClass('traceability') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                <span>Lacak SN</span>
                            </a>
                            @endif
                            @if($canViewTransfer)
                            <a href="{{ route('warehouse.transfers.pending') }}" class="{{ $mobDropdownClass('transfers-pending') }}">
                                <svg class="w-3.5 h-3.5 {{ $mobDropdownIconClass('transfers-pending') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                <span>Konfirmasi Transfer</span>
                            </a>
                            @endif
                            @if($canViewCustody)
                            <a href="{{ route('warehouse.custody.index') }}" class="{{ $mobDropdownClass('custody') }}">
                                <svg class="w-3.5 h-3.5 {{ $mobDropdownIconClass('custody') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                <span>Di Teknisi</span>
                            </a>
                            @endif
                            @if($canViewStockRequest)
                            <a href="{{ route('warehouse.stock-requests.index') }}" class="{{ $mobDropdownClass('stock-requests') }}">
                                <svg class="w-3.5 h-3.5 {{ $mobDropdownIconClass('stock-requests') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z"/></svg>
                                <span>Permintaan</span>
                            </a>
                            @endif
                            @if($canViewWarehouse)
                            <a href="{{ route('warehouse.scan.index') }}" class="{{ $mobDropdownClass('scan') }}">
                                <svg class="w-3.5 h-3.5 {{ $mobDropdownIconClass('scan') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7V5a1 1 0 011-1h2M4 17v2a1 1 0 001 1h2m10-14V5a1 1 0 00-1-1h-2m4 14v2a1 1 0 01-1 1h-2M7 12h10"/></svg>
                                <span>Scan Barang</span>
                            </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- Baris 2: Touch Carousel Quick-Nav Tab Strip (Mobilitas Tinggi untuk Pindah Halaman) --}}
    <div x-data="{
             init() {
                 this.$nextTick(() => {
                     const activeTab = this.$el.querySelector('[data-active-tab=\'true\']');
                     if (activeTab) {
                         activeTab.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' });
                     }
                 });
             }
         }"
         class="overflow-x-auto no-scrollbar [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden scroll-smooth flex items-center gap-1.5 pt-0.5 pb-1">
        @if($canViewWarehouse)
        <a href="{{ route('warehouse.index') }}"
           data-active-tab="{{ $active === 'dashboard' ? 'true' : 'false' }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap {{ $tabClass('dashboard') }} transition-all shrink-0">
            <svg class="w-3.5 h-3.5 {{ $tabIconClass('dashboard') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            <span>Dashboard</span>
        </a>
        <a href="{{ route('warehouse.history.index') }}"
           data-active-tab="{{ $active === 'history' ? 'true' : 'false' }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap {{ $tabClass('history') }} transition-all shrink-0">
            <svg class="w-3.5 h-3.5 {{ $tabIconClass('history') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>Riwayat Mutasi</span>
        </a>
        @endif

        @if($canViewReport)
        <a href="{{ route('warehouse.reports.index') }}"
           data-active-tab="{{ $active === 'reports' ? 'true' : 'false' }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap {{ $tabClass('reports') }} transition-all shrink-0">
            <svg class="w-3.5 h-3.5 {{ $tabIconClass('reports') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M18.7 8l-5.1 5.1-2.8-2.8L7 14"/></svg>
            <span>Laporan</span>
        </a>
        @endif

        @if($canViewWarehouse)
        <a href="{{ route('warehouse.stock.index') }}"
           data-active-tab="{{ $active === 'stock' ? 'true' : 'false' }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap {{ $tabClass('stock') }} transition-all shrink-0">
            <svg class="w-3.5 h-3.5 {{ $tabIconClass('stock') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            <span>Kelola Stok</span>
        </a>
        @endif

        @if($canViewTraceability)
        <a href="{{ route('warehouse.traceability.index') }}"
           data-active-tab="{{ $active === 'traceability' ? 'true' : 'false' }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap {{ $tabClass('traceability') }} transition-all shrink-0">
            <svg class="w-3.5 h-3.5 {{ $tabIconClass('traceability') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/></svg>
            <span>Lacak Barang / SN</span>
        </a>
        @endif

        @if($canViewTransfer)
        <a href="{{ route('warehouse.transfers.pending') }}"
           data-active-tab="{{ $active === 'transfers-pending' ? 'true' : 'false' }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap {{ $tabClass('transfers-pending') }} transition-all shrink-0">
            <svg class="w-3.5 h-3.5 {{ $tabIconClass('transfers-pending') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
            <span>Konfirmasi Transfer</span>
        </a>
        @endif

        @if($canViewCustody)
        <a href="{{ route('warehouse.custody.index') }}"
           data-active-tab="{{ $active === 'custody' ? 'true' : 'false' }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap {{ $tabClass('custody') }} transition-all shrink-0">
            <svg class="w-3.5 h-3.5 {{ $tabIconClass('custody') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            <span>Di Teknisi</span>
        </a>
        @endif

        @if($canViewStockRequest)
        <a href="{{ route('warehouse.stock-requests.index') }}"
           data-active-tab="{{ $active === 'stock-requests' ? 'true' : 'false' }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap {{ $tabClass('stock-requests') }} transition-all shrink-0">
            <svg class="w-3.5 h-3.5 {{ $tabIconClass('stock-requests') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z"/></svg>
            <span>Permintaan Stok</span>
        </a>
        @endif

        @if($canViewWarehouse)
        <a href="{{ route('warehouse.scan.index') }}"
           data-active-tab="{{ $active === 'scan' ? 'true' : 'false' }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap {{ $tabClass('scan') }} transition-all shrink-0">
            <svg class="w-3.5 h-3.5 {{ $tabIconClass('scan') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7V5a1 1 0 011-1h2M4 17v2a1 1 0 001 1h2m10-14V5a1 1 0 00-1-1h-2m4 14v2a1 1 0 01-1 1h-2M7 12h10"/></svg>
            <span>Scan Barang</span>
        </a>
        @endif
    </div>
</div>
