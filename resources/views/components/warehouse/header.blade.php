@props([
    'active' => 'dashboard',
    'title' => null,
    'subtitle' => null,
])

@php
    $user = auth()->user();
    $canViewWarehouse = $user->hasPermission('warehouse.view');
    $canViewCustody = $user->hasPermission('warehouse_custody.view');
    $canViewTraceability = $user->hasPermission('warehouse_traceability.view');
    $canViewReport = $user->hasPermission('warehouse_report.view');
    $canViewStockRequest = $user->hasPermission('warehouse_stock_request.view');
    $canViewItems = $user->hasPermission('items.view');
    $canViewCategories = $user->hasPermission('item_categories.view');

    $canReceive = $user->hasPermission('warehouse_transfer.create');
    $canTransfer = $user->hasPermission('warehouse_transfer.create');
    $canIssue = $user->hasPermission('warehouse_issue.create');
    $canAdjust = $user->hasPermission('warehouse_adjustment.create');
@endphp

<div class="mb-6 space-y-4">
    <!-- Header Banner & Action Bar -->
    <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-5 shadow-xs backdrop-blur-sm">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <!-- Left: Title & Subtitle / Brand -->
            <div class="flex items-start sm:items-center gap-3.5">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-tr from-sky-600 via-indigo-600 to-sky-500 flex items-center justify-center text-white shadow-md shadow-sky-500/20 shrink-0">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100 tracking-tight">
                            {{ $title ?? 'Gudang & Inventori Logistik' }}
                        </h2>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-sky-50 dark:bg-sky-950/50 text-sky-700 dark:text-sky-300 border border-sky-200/70 dark:border-sky-800/60">
                            <span class="w-1.5 h-1.5 rounded-full bg-sky-500 animate-pulse"></span>
                            ISP Master Data
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        {{ $subtitle ?? 'Pusat kendali stok, pergerakan barang, barang di tangan teknisi, dan pelacakan serial number perangkat ISP.' }}
                    </p>
                </div>
            </div>

            <!-- Right: Quick Actions Group -->
            <div class="flex items-center gap-2 shrink-0 flex-wrap" x-data="{ openQuickMenu: false }">
                @if($canReceive)
                <a href="{{ route('warehouse.receive.create') }}"
                   class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-xl text-white bg-emerald-600 hover:bg-emerald-700 dark:bg-emerald-500 dark:hover:bg-emerald-600 shadow-xs shadow-emerald-600/20 transition-all hover:scale-[1.02] active:scale-[0.98]">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>Barang Masuk</span>
                </a>
                @endif

                @if($canTransfer)
                <a href="{{ route('warehouse.transfers.create') }}"
                   class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-xl text-white bg-sky-600 hover:bg-sky-700 dark:bg-sky-500 dark:hover:bg-sky-600 shadow-xs shadow-sky-600/20 transition-all hover:scale-[1.02] active:scale-[0.98]">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                    </svg>
                    <span>Transfer Cabang</span>
                </a>
                @endif

                @if($canIssue || $canAdjust)
                <!-- Dropdown More Actions -->
                <div class="relative" @click.outside="openQuickMenu = false">
                    <button @click="openQuickMenu = !openQuickMenu"
                            type="button"
                            class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl text-slate-700 dark:text-slate-200 bg-slate-100 hover:bg-slate-200/80 dark:bg-slate-700/70 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-600/60 transition-colors">
                        <svg class="w-4 h-4 text-slate-500 dark:text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 18.75a.75.75 0 110-1.5.75.75 0 010 1.5z" />
                        </svg>
                        <span>Aksi Lainnya</span>
                        <svg class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200" :class="openQuickMenu ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                        </svg>
                    </button>

                    <div x-show="openQuickMenu"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 translate-y-1 scale-95"
                         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                         x-transition:leave-end="opacity-0 translate-y-1 scale-95"
                         class="absolute right-0 mt-1.5 w-56 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xl py-1.5 z-50 text-xs"
                         style="display: none;">
                        @if($canIssue)
                        <a href="{{ route('warehouse.issues.create') }}"
                           class="flex items-center gap-2.5 px-3.5 py-2 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-sky-600 dark:hover:text-sky-400 transition-colors">
                            <svg class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            <div>
                                <p class="font-semibold">Serah ke Teknisi</p>
                                <p class="text-[10px] text-slate-400">Serahkan material / perangkat</p>
                            </div>
                        </a>
                        @endif

                        @if($canAdjust)
                        <a href="{{ route('warehouse.adjustments.balance.create') }}"
                           class="flex items-center gap-2.5 px-3.5 py-2 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-amber-600 dark:hover:text-amber-400 transition-colors">
                            <svg class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            <div>
                                <p class="font-semibold">Penyesuaian Stok</p>
                                <p class="text-[10px] text-slate-400">Koreksi manual stok rusak / susut</p>
                            </div>
                        </a>

                        {{-- Stock Opname (Fase 2 P1) — BEDA dari Penyesuaian Stok di atas:
                             opname input jumlah fisik hasil hitung (boleh hasilnya PAS,
                             tetap wajib tercatat), bukan delta koreksi manual. --}}
                        <a href="{{ route('warehouse.adjustments.opname.create') }}"
                           class="flex items-center gap-2.5 px-3.5 py-2 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                            <svg class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                            <div>
                                <p class="font-semibold">Stock Opname</p>
                                <p class="text-[10px] text-slate-400">Catat hasil hitung fisik gudang</p>
                            </div>
                        </a>
                        @endif
                    </div>
                </div>
                @endif
            </div>
        </div>

        <!-- Navigation Tabs Hub -->
        <div class="mt-4 pt-3.5 border-t border-slate-100 dark:border-slate-700/60 overflow-x-auto no-scrollbar">
            <div class="flex items-center gap-1.5 min-w-max">
                @if($canViewWarehouse)
                <a href="{{ route('warehouse.index') }}"
                   class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold transition-all {{ $active === 'dashboard' ? 'bg-sky-500 text-white shadow-xs shadow-sky-500/25 dark:bg-sky-500' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60 hover:text-slate-900 dark:hover:text-white' }}">
                    <svg class="w-4 h-4 {{ $active === 'dashboard' ? 'text-white' : 'text-slate-400 dark:text-slate-500' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    <span>Dashboard &amp; Mutasi</span>
                </a>

                <a href="{{ route('warehouse.stock.index') }}"
                   class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold transition-all {{ $active === 'stock' ? 'bg-sky-500 text-white shadow-xs shadow-sky-500/25 dark:bg-sky-500' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60 hover:text-slate-900 dark:hover:text-white' }}">
                    <svg class="w-4 h-4 {{ $active === 'stock' ? 'text-white' : 'text-slate-400 dark:text-slate-500' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                    <span>Kelola Stok</span>
                </a>

                <a href="{{ route('warehouse.history.index') }}"
                   class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold transition-all {{ $active === 'history' ? 'bg-sky-500 text-white shadow-xs shadow-sky-500/25 dark:bg-sky-500' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60 hover:text-slate-900 dark:hover:text-white' }}">
                    <svg class="w-4 h-4 {{ $active === 'history' ? 'text-white' : 'text-slate-400 dark:text-slate-500' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>Riwayat Mutasi</span>
                </a>
                @endif

                @if($canViewCustody)
                <a href="{{ route('warehouse.custody.index') }}"
                   class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold transition-all {{ $active === 'custody' ? 'bg-sky-500 text-white shadow-xs shadow-sky-500/25 dark:bg-sky-500' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60 hover:text-slate-900 dark:hover:text-white' }}">
                    <svg class="w-4 h-4 {{ $active === 'custody' ? 'text-white' : 'text-slate-400 dark:text-slate-500' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <span>Barang di Tangan Teknisi</span>
                </a>
                @endif

                @if($canViewTraceability)
                <a href="{{ route('warehouse.traceability.index') }}"
                   class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold transition-all {{ $active === 'traceability' ? 'bg-sky-500 text-white shadow-xs shadow-sky-500/25 dark:bg-sky-500' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60 hover:text-slate-900 dark:hover:text-white' }}">
                    <svg class="w-4 h-4 {{ $active === 'traceability' ? 'text-white' : 'text-slate-400 dark:text-slate-500' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/>
                    </svg>
                    <span>Lacak Barang / SN</span>
                </a>
                @endif

                @if($canViewReport)
                <a href="{{ route('warehouse.reports.index') }}"
                   class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold transition-all {{ $active === 'reports' ? 'bg-sky-500 text-white shadow-xs shadow-sky-500/25 dark:bg-sky-500' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60 hover:text-slate-900 dark:hover:text-white' }}">
                    <svg class="w-4 h-4 {{ $active === 'reports' ? 'text-white' : 'text-slate-400 dark:text-slate-500' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M18.7 8l-5.1 5.1-2.8-2.8L7 14"/>
                    </svg>
                    <span>Laporan</span>
                </a>
                @endif

                @if($canViewStockRequest)
                <a href="{{ route('warehouse.stock-requests.index') }}"
                   class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold transition-all {{ $active === 'stock-requests' ? 'bg-sky-500 text-white shadow-xs shadow-sky-500/25 dark:bg-sky-500' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60 hover:text-slate-900 dark:hover:text-white' }}">
                    <svg class="w-4 h-4 {{ $active === 'stock-requests' ? 'text-white' : 'text-slate-400 dark:text-slate-500' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z"/>
                    </svg>
                    <span>Permintaan Stok</span>
                </a>
                @endif

                <div class="h-4 w-px bg-slate-200 dark:bg-slate-700 mx-1"></div>

                @if($canViewItems)
                <a href="{{ route('master.items.index') }}"
                   class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold transition-all {{ $active === 'items' ? 'bg-sky-500 text-white shadow-xs shadow-sky-500/25 dark:bg-sky-500' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60 hover:text-slate-900 dark:hover:text-white' }}">
                    <svg class="w-4 h-4 {{ $active === 'items' ? 'text-white' : 'text-slate-400 dark:text-slate-500' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                    <span>Katalog Barang</span>
                </a>
                @endif

                @if($canViewCategories)
                <a href="{{ route('master.item-categories.index') }}"
                   class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold transition-all {{ $active === 'categories' ? 'bg-sky-500 text-white shadow-xs shadow-sky-500/25 dark:bg-sky-500' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60 hover:text-slate-900 dark:hover:text-white' }}">
                    <svg class="w-4 h-4 {{ $active === 'categories' ? 'text-white' : 'text-slate-400 dark:text-slate-500' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 12V7a4 4 0 014-4z"/>
                    </svg>
                    <span>Kategori Barang</span>
                </a>
                @endif
            </div>
        </div>
    </div>
</div>
