{{-- Filter bar bersama tiga halaman daftar pelanggan.

     Hidden `status_group` SENGAJA dipertahankan: halaman Putus & Gagal memang
     sudah punya route sendiri (grup dipaksa dari controller, query string tidak
     dipercaya), TAPI grup `survey`/`verification` masih hidup sebagai
     /customers?status_group=… — tanpa hidden input ini, menekan "Cari" dari dua
     grup itu melempar balik ke daftar default. Lihat
     CustomerListFilterKeepsStatusGroupTest. --}}
<div class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-200/80 dark:border-slate-800/80 shadow-sm space-y-3 mb-5">

    <!-- Status Tabs & Search Row -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
        <!-- Status Tabs Filter -->
        <div class="flex items-center p-1 bg-slate-100 dark:bg-slate-800/80 rounded-xl w-fit text-xs font-semibold shrink-0">
            <a href="{{ route('customers.index') }}"
               class="px-3.5 py-1.5 rounded-lg transition-all flex items-center gap-2 cursor-pointer
                      {{ $status === '' && empty($statusGroup) ? 'bg-white dark:bg-slate-900 text-sky-600 dark:text-sky-400 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100' }}">
                <span>Semua</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono
                             {{ $status === '' && empty($statusGroup) ? 'bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400' : 'bg-slate-200/60 dark:bg-slate-700 text-slate-600 dark:text-slate-300' }}">{{ $totalCustomers }}</span>
            </a>
            <a href="{{ route('customers.index', ['status' => 'active']) }}"
               class="px-3.5 py-1.5 rounded-lg transition-all flex items-center gap-2 cursor-pointer
                      {{ $status === 'active' ? 'bg-white dark:bg-slate-900 text-emerald-600 dark:text-emerald-400 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100' }}">
                <span>Aktif</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono
                             {{ $status === 'active' ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400' : 'bg-slate-200/60 dark:bg-slate-700 text-slate-600 dark:text-slate-300' }}">{{ $statusCounts['active'] ?? 0 }}</span>
            </a>
            <a href="{{ route('customers.index', ['status' => 'suspended']) }}"
               class="px-3.5 py-1.5 rounded-lg transition-all flex items-center gap-2 cursor-pointer
                      {{ $status === 'suspended' ? 'bg-white dark:bg-slate-900 text-amber-600 dark:text-amber-400 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100' }}">
                <span>Isolir</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono
                             {{ $status === 'suspended' ? 'bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400' : 'bg-slate-200/60 dark:bg-slate-700 text-slate-600 dark:text-slate-300' }}">{{ $statusCounts['suspended'] ?? 0 }}</span>
            </a>
        </div>

        <!-- Search & Density Control -->
        <div class="flex items-center gap-3 w-full lg:w-auto">
            <form action="{{ url()->current() }}" method="GET" id="searchForm" class="relative flex-1 max-w-full sm:max-w-md lg:max-w-xs xl:max-w-md">
                @if($statusGroup !== '')
                    <input type="hidden" name="status_group" value="{{ $statusGroup }}">
                @endif
                @if($status !== '')
                    <input type="hidden" name="status" value="{{ $status }}">
                @endif
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 dark:text-slate-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="search" id="search" value="{{ $search }}"
                       placeholder="Cari Nama, CID, HP, atau Desa..."
                       class="w-full h-9 pl-10 pr-4 rounded-full border border-slate-200 dark:border-slate-700
                              bg-slate-50/50 dark:bg-slate-800/60 text-xs text-slate-800 dark:text-slate-100
                              placeholder-slate-400 dark:placeholder-slate-500
                              focus:outline-none focus:bg-white dark:focus:bg-slate-800 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-all">
            </form>

            <div class="hidden sm:flex items-center gap-2 text-xs text-slate-500 shrink-0">
                <div class="flex items-center p-1 bg-slate-100 dark:bg-slate-800 rounded-xl text-[11px] font-medium">
                    <button type="button" onclick="setDensity('comfortable')" id="density-comfortable" class="px-2.5 py-1 rounded-lg transition-all">Longgar</button>
                    <button type="button" onclick="setDensity('compact')" id="density-compact" class="px-2.5 py-1 rounded-lg transition-all">Rapat</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Multi Dropdown Filters Grid -->
    <form action="{{ url()->current() }}" method="GET" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-3 2xl:grid-cols-6 gap-2.5 pt-2 border-t border-slate-100 dark:border-slate-800" id="filterForm">
        @if($statusGroup !== '')
            <input type="hidden" name="status_group" value="{{ $statusGroup }}">
        @endif
        @if($status !== '')
            <input type="hidden" name="status" value="{{ $status }}">
        @endif
        @if($search !== '')
            <input type="hidden" name="search" value="{{ $search }}">
        @endif

        {{-- POP Filter --}}
        <x-ui.pop-filter :selected-cabang="$selectedCabang" :selected-mini="$selectedMini" />

        {{-- Wilayah Filter --}}
        <x-ui.wilayah-filter :selected-districts="$selectedDistricts" :selected-villages="$selectedVillages" />

        {{-- Paket --}}
        <select name="package_id" id="package_id" onchange="this.form.submit()"
                class="w-full h-9 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs text-slate-700 dark:text-slate-200 focus:outline-none focus:border-sky-500">
            <option value="">Semua Paket Internet</option>
            @foreach($packages as $package)
                <option value="{{ $package->id }}" {{ $packageId == $package->id ? 'selected' : '' }}>{{ $package->package_code }} - {{ $package->name }}</option>
            @endforeach
        </select>

        {{-- Kelengkapan --}}
        <select name="completeness_status" id="completeness_status" onchange="this.form.submit()"
                class="w-full h-9 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs text-slate-700 dark:text-slate-200 focus:outline-none focus:border-sky-500">
            <option value="">Semua Kelengkapan Berkas</option>
            <option value="draft" {{ $completenessStatus === 'draft' ? 'selected' : '' }}>Draft</option>
            <option value="perlu_dilengkapi" {{ $completenessStatus === 'perlu_dilengkapi' ? 'selected' : '' }}>Perlu Dilengkapi</option>
            <option value="lengkap" {{ $completenessStatus === 'lengkap' ? 'selected' : '' }}>Lengkap</option>
            <option value="siap_billing" {{ $completenessStatus === 'siap_billing' ? 'selected' : '' }}>Siap Billing</option>
        </select>

        {{-- Kolektor --}}
        @if($collectorOptions->isNotEmpty())
        <select name="collector_id" id="collector_id" onchange="this.form.submit()"
                class="w-full h-9 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs text-slate-700 dark:text-slate-200 focus:outline-none focus:border-sky-500">
            <option value="">Semua (Kolektor &amp; Tanpa Kolektor)</option>
            <option value="none" {{ $collectorId === 'none' ? 'selected' : '' }}>Belum Ada Kolektor</option>
            @foreach($collectorOptions as $collectorOption)
                <option value="{{ $collectorOption->id }}" {{ (string) $collectorId === (string) $collectorOption->id ? 'selected' : '' }}>{{ $collectorOption->name }}</option>
            @endforeach
        </select>
        @endif

        {{-- Reset Button --}}
        <div class="col-span-1 sm:col-span-2 md:col-span-1 lg:col-span-1 flex items-center gap-2">
            <a href="{{ url()->current() }}"
               class="w-full h-9 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-semibold inline-flex items-center justify-center gap-1.5 transition-colors">
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <span>Reset Filter</span>
            </a>
        </div>
    </form>
</div>
