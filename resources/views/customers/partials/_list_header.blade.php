{{-- Header + tombol Import/Tambah. Tombolnya digerbangi permission masing-masing
     (customers.import.view / customers.create), jadi aman ikut di halaman Putus &
     Gagal juga — role yang cuma boleh melihat arsip tidak melihatnya. --}}
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4 mb-5">
    <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-50 tracking-tight">{{ $pageTitle }}</h1>
        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">Kelola data pelanggan, jaringan distribusi, status layanan internet, dan penagihan.</p>
    </div>
    <div class="flex items-center gap-2.5 flex-wrap shrink-0">
        @if(auth()->user()->hasPermission('customers.import.view'))
        <a href="/customers/import"
           class="h-9 px-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900
                  text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800
                  text-xs font-semibold inline-flex items-center gap-2 transition-all shadow-sm">
            <svg class="h-4 w-4 text-slate-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
            </svg>
            <span>Import Pelanggan</span>
        </a>
        @endif
        @if(auth()->user()->hasPermission('customers.create'))
        <a href="/customers/create"
           class="h-9 px-4 rounded-xl bg-sky-600 hover:bg-sky-700 text-white
                  text-xs font-semibold inline-flex items-center gap-2 transition-all shadow-md shadow-sky-600/20 active:scale-95">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            <span>Tambah Pelanggan</span>
        </a>
        @endif
    </div>
</div>
