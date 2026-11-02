{{-- Strip statistik. Angkanya SELALU tentang populasi aktif/isolir (bukan isi
     tabel di bawahnya), jadi identik di ketiga halaman daftar. --}}
<section aria-labelledby="heading-stats-pelanggan"
         x-data="{
             mobileStatsOpen: false,
             isDesktop: window.innerWidth >= 768
         }"
         @resize.window.debounce.100ms="isDesktop = window.innerWidth >= 768"
         class="mb-4 sm:mb-6">
    <h2 id="heading-stats-pelanggan" class="sr-only">Ringkasan Statistik Pelanggan</h2>

    {{-- Mobile Header: Toggle Card Indikator (< 768px / md:hidden) --}}
    <div class="md:hidden mb-3">
        <button type="button"
                @click="mobileStatsOpen = !mobileStatsOpen"
                :aria-expanded="mobileStatsOpen ? 'true' : 'false'"
                aria-controls="customer-stats-content"
                class="w-full flex items-center justify-between p-3.5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 shadow-xs text-left transition-all active:scale-[0.99] cursor-pointer">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-xl bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0 border border-sky-100 dark:border-sky-900/40">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-slate-800 dark:text-slate-100">Ringkasan Statistik</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border border-sky-200/60 dark:border-sky-800/60 font-mono">{{ number_format($totalCustomers) }}</span>
                    </div>
                    {{-- Mini preview ringkas saat collapsed di mobile --}}
                    <p x-show="!mobileStatsOpen" class="text-[11px] text-slate-600 dark:text-slate-400 truncate mt-0.5">
                        <span class="text-emerald-700 dark:text-emerald-400 font-semibold">{{ number_format($statusCounts['active'] ?? 0) }} Aktif</span> •
                        <span class="text-amber-700 dark:text-amber-400 font-semibold">{{ number_format($statusCounts['suspended'] ?? 0) }} Isolir</span> •
                        <span class="text-rose-700 dark:text-rose-400 font-semibold">{{ number_format($overdueCount ?? 0) }} Piutang</span>
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-1.5 shrink-0 pl-2">
                <span class="text-xs font-semibold text-sky-600 dark:text-sky-400" x-text="mobileStatsOpen ? 'Tutup' : 'Lihat'"></span>
                <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" :class="mobileStatsOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
        </button>
    </div>

    {{-- Konten Grid: Terbuka selalu di Desktop, Collapsible di Mobile --}}
    <div id="customer-stats-content" x-show="isDesktop || mobileStatsOpen" x-collapse x-cloak>
        <div class="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
            <!-- Total -->
            <div class="p-3.5 sm:p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 shadow-sm flex items-center justify-between card-interactive min-w-0">
                <div class="min-w-0 flex-1 pr-2">
                    <h3 class="text-[10px] sm:text-[11px] font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 truncate">Total Pelanggan</h3>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ number_format($totalCustomers) }}</p>
                    <p class="text-[10px] text-slate-600 dark:text-slate-400 mt-0.5 truncate">Terdaftar dalam sistem</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
            </div>

            <!-- Aktif -->
            <div class="p-3.5 sm:p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 shadow-sm flex items-center justify-between card-interactive min-w-0">
                <div class="min-w-0 flex-1 pr-2">
                    <h3 class="text-[10px] sm:text-[11px] font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 truncate">Layanan Aktif</h3>
                    <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ number_format($statusCounts['active'] ?? 0) }}</p>
                    <p class="text-[10px] text-slate-600 dark:text-slate-400 mt-0.5 truncate">Berlangganan lancar</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>

            <!-- Isolir -->
            <div class="p-3.5 sm:p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 shadow-sm flex items-center justify-between card-interactive min-w-0">
                <div class="min-w-0 flex-1 pr-2">
                    <h3 class="text-[10px] sm:text-[11px] font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 truncate">Isolir / Suspend</h3>
                    <p class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">{{ number_format($statusCounts['suspended'] ?? 0) }}</p>
                    <p class="text-[10px] text-slate-600 dark:text-slate-400 mt-0.5 truncate">Penangguhan sementara</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                </div>
            </div>

            <!-- Lewat Tempo -->
            <div class="p-3.5 sm:p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 shadow-sm flex items-center justify-between card-interactive min-w-0">
                <div class="min-w-0 flex-1 pr-2">
                    <h3 class="text-[10px] sm:text-[11px] font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 truncate">Piutang</h3>
                    <p class="text-2xl font-bold text-rose-600 dark:text-rose-400 mt-1">{{ number_format($overdueCount ?? 0) }}</p>
                    <p class="text-[10px] text-slate-600 dark:text-slate-400 mt-0.5 truncate">Menunggu pembayaran</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </div>
    </div>
</section>
