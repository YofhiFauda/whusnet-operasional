{{-- Strip statistik. Angkanya SELALU tentang populasi aktif/isolir (bukan isi
     tabel di bawahnya), jadi identik di ketiga halaman daftar. --}}
<div class="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
    <!-- Total -->
    <div class="p-3.5 sm:p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 shadow-sm flex items-center justify-between card-interactive min-w-0">
        <div class="min-w-0 flex-1 pr-2">
            <p class="text-[10px] sm:text-[11px] font-semibold uppercase tracking-wider text-slate-400 truncate">Total Pelanggan</p>
            <h3 class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ number_format($totalCustomers) }}</h3>
            <p class="text-[10px] text-slate-500 mt-0.5 truncate">Terdaftar dalam sistem</p>
        </div>
        <div class="w-10 h-10 rounded-xl bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
        </div>
    </div>

    <!-- Aktif -->
    <div class="p-3.5 sm:p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 shadow-sm flex items-center justify-between card-interactive min-w-0">
        <div class="min-w-0 flex-1 pr-2">
            <p class="text-[10px] sm:text-[11px] font-semibold uppercase tracking-wider text-slate-400 truncate">Layanan Aktif</p>
            <h3 class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ number_format($statusCounts['active'] ?? 0) }}</h3>
            <p class="text-[10px] text-slate-500 mt-0.5 truncate">Berlangganan lancar</p>
        </div>
        <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
    </div>

    <!-- Isolir -->
    <div class="p-3.5 sm:p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 shadow-sm flex items-center justify-between card-interactive min-w-0">
        <div class="min-w-0 flex-1 pr-2">
            <p class="text-[10px] sm:text-[11px] font-semibold uppercase tracking-wider text-slate-400 truncate">Isolir / Suspend</p>
            <h3 class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">{{ number_format($statusCounts['suspended'] ?? 0) }}</h3>
            <p class="text-[10px] text-slate-500 mt-0.5 truncate">Penangguhan sementara</p>
        </div>
        <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
        </div>
    </div>

    <!-- Lewat Tempo -->
    <div class="p-3.5 sm:p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 shadow-sm flex items-center justify-between card-interactive min-w-0">
        <div class="min-w-0 flex-1 pr-2">
            <p class="text-[10px] sm:text-[11px] font-semibold uppercase tracking-wider text-slate-400 truncate">Lewat Tempo</p>
            <h3 class="text-2xl font-bold text-rose-600 dark:text-rose-400 mt-1">{{ number_format($overdueCount ?? 0) }}</h3>
            <p class="text-[10px] text-slate-500 mt-0.5 truncate">Menunggu pembayaran</p>
        </div>
        <div class="w-10 h-10 rounded-xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
    </div>
</div>
