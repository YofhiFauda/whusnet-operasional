{{-- Filter bar bersama tiga halaman daftar pelanggan.

     Hidden `status_group` SENGAJA dipertahankan: halaman Putus & Gagal memang
     sudah punya route sendiri (grup dipaksa dari controller, query string tidak
     dipercaya), TAPI grup `survey`/`verification` masih hidup sebagai
     /customers?status_group=… — tanpa hidden input ini, menekan "Cari" dari dua
     grup itu melempar balik ke daftar default. Lihat
     CustomerListFilterKeepsStatusGroupTest. --}}
@php
    $activeFilterCount = 0;
    if (!empty($search)) $activeFilterCount++;
    if (!empty($status)) $activeFilterCount++;
    if (!empty($packageId)) $activeFilterCount++;
    if (!empty($completenessStatus)) $activeFilterCount++;
    if (!empty($collectorId)) $activeFilterCount++;
    if (!empty($selectedCabang)) $activeFilterCount += is_array($selectedCabang) ? count($selectedCabang) : 1;
    if (!empty($selectedMini)) $activeFilterCount += is_array($selectedMini) ? count($selectedMini) : 1;
    if (!empty($selectedDistricts) && count((array)$selectedDistricts) > 0) $activeFilterCount += count((array)$selectedDistricts);
    if (!empty($selectedVillages) && count((array)$selectedVillages) > 0) $activeFilterCount += count((array)$selectedVillages);

    $hasActiveFilter = $activeFilterCount > 0;
@endphp

<section aria-labelledby="heading-filter-pelanggan"
         x-data="{
             mobileFilterOpen: {{ $hasActiveFilter ? 'true' : 'false' }},
             isDesktop: window.innerWidth >= 768
         }"
         @resize.window.debounce.100ms="isDesktop = window.innerWidth >= 768"
         class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm mb-5 overflow-hidden transition-all">
    <h2 id="heading-filter-pelanggan" class="sr-only">Filter dan Pencarian Pelanggan</h2>

    {{-- Mobile Header: Toggle Card Filter (< 768px / md:hidden) --}}
    <div class="md:hidden p-3.5 flex items-center justify-between gap-3 bg-slate-50/70 dark:bg-slate-800/60 border-b border-slate-100 dark:border-slate-800 cursor-pointer select-none"
         @click="if (!isDesktop) mobileFilterOpen = !mobileFilterOpen">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-8 h-8 rounded-xl bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0 border border-sky-100 dark:border-sky-900/40">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                </svg>
            </div>
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-slate-800 dark:text-slate-100">Filter &amp; Pencarian</span>
                    @if($hasActiveFilter)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-sky-100 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border border-sky-200/80 dark:border-sky-800/60">
                            <span class="w-1.5 h-1.5 rounded-full bg-sky-500 animate-pulse"></span>
                            <span>{{ $activeFilterCount }} Filter Aktif</span>
                        </span>
                    @endif
                </div>
                {{-- Preview singkat filter saat collapsed di mobile --}}
                <p x-show="!mobileFilterOpen" class="text-[11px] text-slate-500 dark:text-slate-400 truncate mt-0.5">
                    @if(!empty($search))
                        Cari: <span class="font-medium text-slate-700 dark:text-slate-300">"{{ $search }}"</span>
                    @elseif(!empty($status))
                        Status: <span class="font-medium text-slate-700 dark:text-slate-300">{{ ucfirst($status) }}</span>
                    @elseif($hasActiveFilter)
                        Filter diterapkan
                    @else
                        Semua pelanggan (Ketuk untuk cari &amp; filter)
                    @endif
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            @if($hasActiveFilter)
                <a href="{{ url()->current() }}"
                   @click.stop
                   class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg transition-colors border border-rose-200 dark:border-rose-900/60"
                   title="Reset Semua Filter">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    <span>Reset</span>
                </a>
            @endif

            <button type="button"
                    @click.stop="mobileFilterOpen = !mobileFilterOpen"
                    :aria-expanded="mobileFilterOpen ? 'true' : 'false'"
                    aria-controls="customer-filters-content"
                    class="flex items-center gap-1 text-xs font-semibold text-sky-600 dark:text-sky-400 py-1 pl-1 cursor-pointer">
                <span x-text="mobileFilterOpen ? 'Tutup' : 'Buka'"></span>
                <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" :class="mobileFilterOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- Konten Filter: Terbuka selalu di Desktop, Collapsible di Mobile --}}
    <div id="customer-filters-content"
         x-show="isDesktop || mobileFilterOpen"
         x-collapse
         x-cloak
         class="p-4 space-y-3">
    <!-- Status Tabs & Search Row -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
        <!-- Status Tabs Filter -->
        <nav aria-label="Filter status pelanggan" class="flex items-center p-1 bg-slate-100 dark:bg-slate-800/80 rounded-xl w-fit text-xs font-semibold shrink-0">
            <a href="{{ route('customers.index') }}"
               @if($status === '' && empty($statusGroup)) aria-current="page" @endif
               class="px-3.5 py-1.5 rounded-lg transition-all flex items-center gap-2 cursor-pointer
                      {{ $status === '' && empty($statusGroup) ? 'bg-white dark:bg-slate-900 text-sky-700 dark:text-sky-300 shadow-sm' : 'text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-slate-100' }}">
                <span>Semua</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono
                             {{ $status === '' && empty($statusGroup) ? 'bg-sky-100 dark:bg-sky-950/60 text-sky-800 dark:text-sky-200' : 'bg-slate-200/80 dark:bg-slate-700 text-slate-700 dark:text-slate-200' }}">{{ $totalCustomers }}</span>
            </a>
            <a href="{{ route('customers.index', ['status' => 'active']) }}"
               @if($status === 'active') aria-current="page" @endif
               class="px-3.5 py-1.5 rounded-lg transition-all flex items-center gap-2 cursor-pointer
                      {{ $status === 'active' ? 'bg-white dark:bg-slate-900 text-emerald-700 dark:text-emerald-300 shadow-sm' : 'text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-slate-100' }}">
                <span>Aktif</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono
                             {{ $status === 'active' ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-200' : 'bg-slate-200/80 dark:bg-slate-700 text-slate-700 dark:text-slate-200' }}">{{ $statusCounts['active'] ?? 0 }}</span>
            </a>
            <a href="{{ route('customers.index', ['status' => 'suspended']) }}"
               @if($status === 'suspended') aria-current="page" @endif
               class="px-3.5 py-1.5 rounded-lg transition-all flex items-center gap-2 cursor-pointer
                      {{ $status === 'suspended' ? 'bg-white dark:bg-slate-900 text-amber-800 dark:text-amber-300 shadow-sm' : 'text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-slate-100' }}">
                <span>Isolir</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono
                             {{ $status === 'suspended' ? 'bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-200' : 'bg-slate-200/80 dark:bg-slate-700 text-slate-700 dark:text-slate-200' }}">{{ $statusCounts['suspended'] ?? 0 }}</span>
            </a>
        </nav>

        <!-- Search & Density Control -->
        <div class="flex items-center gap-3 w-full lg:w-auto">
            <form action="{{ url()->current() }}" method="GET" id="searchForm" role="search" class="relative flex-1 max-w-full sm:max-w-md lg:max-w-xs xl:max-w-md">
                @if($statusGroup !== '')
                    <input type="hidden" name="status_group" value="{{ $statusGroup }}">
                @endif
                @if($status !== '')
                    <input type="hidden" name="status" value="{{ $status }}">
                @endif
                <label for="search" class="sr-only">Cari pelanggan</label>
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 dark:text-slate-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="search" id="search" value="{{ $search }}"
                       placeholder="Cari Nama, CID, HP, atau Desa..."
                       aria-label="Cari pelanggan berdasarkan Nama, CID, HP, atau Desa"
                       class="w-full h-9 pl-10 pr-4 rounded-full border border-slate-200 dark:border-slate-700
                              bg-slate-50/50 dark:bg-slate-800/60 text-xs text-slate-800 dark:text-slate-100
                              placeholder-slate-400 dark:placeholder-slate-500
                              focus:outline-none focus:bg-white dark:focus:bg-slate-800 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-all">
            </form>

            {{-- Pemilih kolom (cuma List Pelanggan utama). Centang → kolom masuk tabel,
                 disimpan per user di DB lalu halaman dimuat ulang (kolom baru butuh
                 data dari server). Urutan diatur dengan drag-drop header tabel.
                 URL simpan/reset dirender server lewat route(). --}}
            @if($customizable)
            {{-- Panel diteleport ke <body> (bukan absolute di dalam bar ini): bar filter
                 punya `overflow-hidden` di section induk (buat rapihin sudut rounded header
                 mobile), yang diam-diam memotong dropdown absolute mana pun yang nongol
                 di dalamnya — cuma paragraf keterangan yang kelihatan, daftar checkbox
                 kolomnya terpotong. Posisi dihitung manual dari bounding rect tombol,
                 pola yang sama dipakai dropdown "Kelola" di warehouse/custody/index. --}}
            <div class="relative shrink-0 hidden sm:block"
                 x-data="{
                    open: false, busy: false,
                    pos: { top: 0, left: 0 },
                    visible: @js(array_keys($columns)),
                    updateUrl: @js(route('customers.list-columns.update')),
                    resetUrl: @js(route('customers.list-columns.destroy')),
                    toggle(key) { const i = this.visible.indexOf(key); i >= 0 ? this.visible.splice(i, 1) : this.visible.push(key); },
                    openPanel(e) {
                        const menuWidth = 256;
                        const r = e.currentTarget.getBoundingClientRect();
                        let left = r.right - menuWidth;
                        if (left < 8) left = 8;
                        if (left + menuWidth > window.innerWidth - 8) left = Math.max(8, window.innerWidth - menuWidth - 8);
                        this.pos = { top: Math.round(r.bottom + 8), left: Math.round(left) };
                        this.open = true;
                    },
                    async send(method, url, body) {
                        this.busy = true;
                        try {
                            const res = await fetch(url, { method, body, headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content } });
                            if (!res.ok) throw new Error('gagal');
                            window.location.reload();
                        } catch (e) {
                            this.busy = false;
                            alert('Kolom gagal disimpan.');
                        }
                    },
                    apply() {
                        if (!this.visible.length) { alert('Pilih minimal satu kolom.'); return; }
                        this.send('PUT', this.updateUrl, JSON.stringify({ columns: this.visible }));
                    },
                    reset() { this.send('DELETE', this.resetUrl); }
                 }"
                 @scroll.window="open = false" @resize.window="open = false">
                <button type="button" @click="open ? (open = false) : openPanel($event)"
                        :aria-expanded="open ? 'true' : 'false'"
                        aria-haspopup="dialog"
                        aria-label="Atur kolom tabel"
                        class="h-9 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m6 10V7M4 7h16M4 17h16"/></svg>
                    <span>Kolom</span>
                </button>
                <template x-teleport="body">
                    <div x-show="open" x-cloak x-transition
                         @click.outside="open = false" @keydown.escape.window="open = false"
                         role="dialog" aria-label="Pengaturan visibilitas kolom tabel"
                         :style="`position: fixed; top: ${pos.top}px; left: ${pos.left}px; z-index: 9999;`"
                         class="w-64 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-lg p-3 space-y-2">
                        <p class="text-[11px] text-slate-600 dark:text-slate-400">Centang kolom yang ditampilkan. Geser judul kolom di tabel untuk mengubah urutan.</p>
                        <div class="max-h-64 overflow-y-auto space-y-1">
                            @foreach(\App\Support\CustomerListColumns::all() as $colKey => $colDef)
                                <label class="flex items-center gap-2 px-1.5 py-1 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 text-xs text-slate-700 dark:text-slate-200 cursor-pointer">
                                    <input type="checkbox" class="rounded border-slate-300 text-sky-600"
                                           aria-label="{{ $colDef['label'] }}"
                                           :checked="visible.includes('{{ $colKey }}')" @change="toggle('{{ $colKey }}')">
                                    <span>{{ $colDef['label'] }}</span>
                                </label>
                            @endforeach
                        </div>
                        <div class="flex items-center justify-between gap-2 pt-1 border-t border-slate-100 dark:border-slate-800">
                            <button type="button" @click="reset()" :disabled="busy" class="text-xs text-slate-600 hover:text-rose-600 dark:text-slate-400 dark:hover:text-rose-400">Reset</button>
                            <button type="button" @click="apply()" :disabled="busy"
                                    class="h-8 px-3 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold disabled:opacity-50">Terapkan</button>
                        </div>
                    </div>
                </template>
            </div>
            @endif

            <div class="hidden sm:flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400 shrink-0">
                <div role="group" aria-label="Kerapatan tampilan tabel" class="flex items-center p-1 bg-slate-100 dark:bg-slate-800 rounded-xl text-[11px] font-medium">
                    <button type="button" onclick="setDensity('comfortable')" id="density-comfortable" aria-label="Kerapatan tampilan longgar" class="px-2.5 py-1 rounded-lg transition-all">Longgar</button>
                    <button type="button" onclick="setDensity('compact')" id="density-compact" aria-label="Kerapatan tampilan rapat" class="px-2.5 py-1 rounded-lg transition-all">Rapat</button>
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
        <div>
            <label for="package_id" class="sr-only">Filter Paket Internet</label>
            <select name="package_id" id="package_id" onchange="this.form.submit()"
                    aria-label="Filter Paket Internet"
                    class="w-full h-9 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs text-slate-700 dark:text-slate-200 focus:outline-none focus:border-sky-500">
                <option value="">Semua Paket Internet</option>
                @foreach($packages as $package)
                    <option value="{{ $package->id }}" {{ $packageId == $package->id ? 'selected' : '' }}>{{ $package->package_code }} - {{ $package->name }}</option>
                @endforeach
            </select>
        </div>

        {{-- Kelengkapan --}}
        <div>
            <label for="completeness_status" class="sr-only">Filter Kelengkapan Berkas</label>
            <select name="completeness_status" id="completeness_status" onchange="this.form.submit()"
                    aria-label="Filter Kelengkapan Berkas"
                    class="w-full h-9 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs text-slate-700 dark:text-slate-200 focus:outline-none focus:border-sky-500">
                <option value="">Semua Kelengkapan Berkas</option>
                <option value="draft" {{ $completenessStatus === 'draft' ? 'selected' : '' }}>Draft</option>
                <option value="perlu_dilengkapi" {{ $completenessStatus === 'perlu_dilengkapi' ? 'selected' : '' }}>Perlu Dilengkapi</option>
                <option value="lengkap" {{ $completenessStatus === 'lengkap' ? 'selected' : '' }}>Lengkap</option>
                <option value="siap_billing" {{ $completenessStatus === 'siap_billing' ? 'selected' : '' }}>Siap Billing</option>
            </select>
        </div>

        {{-- Kolektor --}}
        @if($collectorOptions->isNotEmpty())
        <div>
            <label for="collector_id" class="sr-only">Filter Kolektor</label>
            <select name="collector_id" id="collector_id" onchange="this.form.submit()"
                    aria-label="Filter Kolektor"
                    class="w-full h-9 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs text-slate-700 dark:text-slate-200 focus:outline-none focus:border-sky-500">
                <option value="">Semua (Kolektor &amp; Tanpa Kolektor)</option>
                <option value="none" {{ $collectorId === 'none' ? 'selected' : '' }}>Belum Ada Kolektor</option>
                @foreach($collectorOptions as $collectorOption)
                    <option value="{{ $collectorOption->id }}" {{ (string) $collectorId === (string) $collectorOption->id ? 'selected' : '' }}>{{ $collectorOption->name }}</option>
                @endforeach
            </select>
        </div>
        @endif

        {{-- Reset Button --}}
        <div class="col-span-1 sm:col-span-2 md:col-span-1 lg:col-span-1 flex items-center gap-2">
            <a href="{{ url()->current() }}"
               aria-label="Reset semua filter"
               class="w-full h-9 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-semibold inline-flex items-center justify-center gap-1.5 transition-colors">
                <svg class="w-3.5 h-3.5 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <span>Reset Filter</span>
            </a>
        </div>
    </form>
    </div>
</section>
