{{--
    Toolbar papan Kelola Stok: Tab Mode Tampilan, tombol bentang/ciutkan,
    cari, kategori, tracking segmented tabs, pill Menipis, dan chip filter aktif.

    Semua URL (chip, "Hapus semua", "Cari di semua gudang terjangkau") dirakit
    server lewat route() — klien tidak menyusun query string sendiri (aturan
    repo, PostTargetRenderedServerSideTest).

    `pop_id` dibedakan tiga keadaan: berisi id, string kosong (user sengaja
    minta "semua gudang"), atau absen (pakai pilihan switcher di session —
    ResolvesSelectedPop). Karena itu "Hapus semua" dan "semua gudang" menulis
    `pop_id=` eksplisit, bukan membuangnya: kalau dibuang, pilihan gudang di
    session diam-diam menyaring lagi dan tombol itu terlihat tidak bekerja.
--}}
@php
    $f = $boardFilters;
    // Sub-tampilan papan: tabel (papan) atau matriks. Ikut terbawa ke semua URL
    // filter/chip supaya mengetik pencarian tidak melempar user keluar matriks.
    $boardView = $viewMode === 'matrix' ? 'matrix' : 'papan';
    $trackingLabels = [
        \App\Enums\TrackingType::SERIALIZED->value => 'SN',
        \App\Enums\TrackingType::ROLL->value => 'Roll',
        \App\Enums\TrackingType::QUANTITY->value => 'Quantity',
    ];
    $popParam = $f->popId ?? (request()->has('pop_id') ? '' : null);
    $boardBase = array_filter([
        'view' => $boardView,
        'search' => $f->search !== '' ? $f->search : null,
        'pop_id' => $popParam,
        'category_id' => $f->categoryId,
        'tracking' => $f->tracking,
        'low_stock_only' => $f->lowOnly ? 1 : null,
    ], fn ($value) => $value !== null);
    $boardUrl = fn (array $override) => route('warehouse.stock.index', array_filter(
        array_merge($boardBase, $override),
        fn ($value) => $value !== null
    ));

    // Nama gudang diambil dari $pops (sudah ter-scope): pop_id luar scope
    // tidak boleh menampilkan namanya walau ada di URL.
    $selectedPop = $f->popId !== null ? $pops->firstWhere('id', $f->popId) : null;
    $selectedCategory = $f->categoryId !== null ? $categories->firstWhere('id', $f->categoryId) : null;

    $chips = collect([
        $f->search !== '' ? ['label' => 'Cari: "'.$f->search.'"', 'url' => $boardUrl(['search' => null])] : null,
        // Pengguna satu gudang tidak diberi chip gudang: tidak ada yang bisa dihapus.
        $f->popId !== null && ! $lockedSinglePop
            ? ['label' => 'Gudang: '.($selectedPop?->name ?? 'tidak terjangkau'), 'url' => $boardUrl(['pop_id' => ''])]
            : null,
        $f->categoryId !== null ? ['label' => 'Kategori: '.($selectedCategory?->name ?? 'tidak aktif'), 'url' => $boardUrl(['category_id' => null])] : null,
        $f->tracking !== null ? ['label' => 'Tracking: '.$trackingLabels[$f->tracking], 'url' => $boardUrl(['tracking' => null])] : null,
        $f->lowOnly ? ['label' => 'Hanya menipis', 'url' => $boardUrl(['low_stock_only' => null])] : null,
    ])->filter()->values();

    $searchTruncated = $searchTruncated ?? $groups->contains(fn ($group) => $group['search_truncated'] ?? false);
    $offerAllWarehouses = $f->popId !== null && $f->search !== '' && ! $lockedSinglePop;

@endphp

<div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-3 sm:p-4 shadow-xs mb-4 space-y-3 transition-colors">
    {{-- BARIS 1: Navigasi Tampilan (Tabs) & Kontrol Band (Bentang / Ciutkan) --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 pb-2.5 border-b border-slate-100 dark:border-slate-800">
        {{-- Dua tampilan papan: tabel dan matriks. --}}
        <div class="inline-flex items-center p-1 bg-slate-100 dark:bg-slate-800/80 rounded-xl text-xs font-semibold overflow-x-auto no-scrollbar max-w-full" role="tablist" aria-label="Mode tampilan stok">
            {{-- Tab 1: Papan (Tabel) --}}
            <a href="{{ $boardUrl(['view' => 'papan']) }}" role="tab" aria-selected="{{ $boardView === 'papan' ? 'true' : 'false' }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg transition-all whitespace-nowrap cursor-pointer {{ $boardView === 'papan' ? 'bg-white dark:bg-slate-900 text-sky-600 dark:text-sky-400 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100' }}">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/>
                </svg>
                <span>Papan</span>
            </a>

            {{-- Tab 2: Matriks --}}
            <a href="{{ $boardUrl(['view' => 'matrix']) }}" role="tab" aria-selected="{{ $boardView === 'matrix' ? 'true' : 'false' }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg transition-all whitespace-nowrap cursor-pointer {{ $boardView === 'matrix' ? 'bg-white dark:bg-slate-900 text-sky-600 dark:text-sky-400 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100' }}">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0 3v4.5m0 3v1.5m6-16.5v4.5m0 3v4.5m0 3v1.5m6-16.5v4.5m0 3v4.5m0 3v1.5M3.75 3.75h16.5M3.75 8.25h16.5M3.75 12.75h16.5M3.75 17.25h16.5M3.75 20.25h16.5" />
                </svg>
                <span>Matriks</span>
            </a>

        </div>

        {{-- Aksi Cepat Band: Bentang & Ciutkan (Segmented Action Group) --}}
        <div class="inline-flex items-center p-0.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-800/80 shadow-2xs shrink-0 self-end sm:self-center">
            <button type="button" @click="expandAll()"
                    class="h-7 px-2.5 rounded-md hover:bg-white dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 hover:text-sky-600 dark:hover:text-sky-400 text-xs font-semibold inline-flex items-center gap-1.5 transition-all cursor-pointer"
                    title="Buka semua gudang dan kategori">
                <svg class="w-3.5 h-3.5 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/>
                </svg>
                <span>Bentang semua</span>
            </button>
            <div class="h-3.5 w-px bg-slate-200 dark:bg-slate-700 mx-0.5" aria-hidden="true"></div>
            <button type="button" @click="collapseAll()"
                    class="h-7 px-2.5 rounded-md hover:bg-white dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 hover:text-slate-900 dark:hover:text-slate-100 text-xs font-semibold inline-flex items-center gap-1.5 transition-all cursor-pointer"
                    title="Tutup semua gudang dan kategori">
                <svg class="w-3.5 h-3.5 text-slate-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 5l-4 4-4-4m8 14l-4-4-4 4"/>
                </svg>
                <span>Ciutkan</span>
            </button>
        </div>
    </div>

    {{-- BARIS 2: Filter Tabs Cepat (Tracking & Menipis) + Pencarian & Kategori --}}
    <form method="GET" action="{{ route('warehouse.stock.index') }}" class="flex flex-col lg:flex-row lg:items-center justify-between gap-2.5" role="search" aria-label="Saring papan stok">
        <input type="hidden" name="view" value="{{ $boardView }}">
        @if($popParam !== null)
        <input type="hidden" name="pop_id" value="{{ $popParam }}">
        @endif
        @if($f->tracking !== null)
        <input type="hidden" name="tracking" value="{{ $f->tracking }}">
        @endif

        {{-- Sisi 1: Filter Cepat via Tab Tracking & Status Menipis --}}
        <div class="flex items-center gap-1.5 shrink-0 overflow-x-auto no-scrollbar py-0.5">
            {{-- Unified Tracking Filter Tabs --}}
            <div class="inline-flex items-center p-1 bg-slate-100 dark:bg-slate-800/80 rounded-xl text-xs font-semibold" role="tablist" aria-label="Filter jenis tracking">
                {{-- Tab: Semua Tracking --}}
                <a href="{{ $boardUrl(['tracking' => null]) }}" role="tab" aria-selected="{{ $f->tracking === null ? 'true' : 'false' }}"
                   class="px-2.5 py-1.5 rounded-lg transition-all cursor-pointer {{ $f->tracking === null ? 'bg-white dark:bg-slate-900 text-sky-600 dark:text-sky-400 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100' }}">
                    Semua
                </a>

                {{-- Tab: SN --}}
                <a href="{{ $boardUrl(['tracking' => \App\Enums\TrackingType::SERIALIZED->value]) }}" role="tab" aria-selected="{{ $f->tracking === \App\Enums\TrackingType::SERIALIZED->value ? 'true' : 'false' }}"
                   class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg transition-all cursor-pointer {{ $f->tracking === \App\Enums\TrackingType::SERIALIZED->value ? 'bg-white dark:bg-slate-900 text-sky-600 dark:text-sky-400 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100' }}">
                    <span class="font-mono text-[11px] font-bold text-sky-500 dark:text-sky-400">#</span>
                    <span>SN</span>
                </a>

                {{-- Tab: Roll --}}
                <a href="{{ $boardUrl(['tracking' => \App\Enums\TrackingType::ROLL->value]) }}" role="tab" aria-selected="{{ $f->tracking === \App\Enums\TrackingType::ROLL->value ? 'true' : 'false' }}"
                   class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg transition-all cursor-pointer {{ $f->tracking === \App\Enums\TrackingType::ROLL->value ? 'bg-white dark:bg-slate-900 text-sky-600 dark:text-sky-400 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100' }}">
                    <svg class="w-3.5 h-3.5 text-indigo-500 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                    <span>Roll</span>
                </a>

                {{-- Tab: Quantity --}}
                <a href="{{ $boardUrl(['tracking' => \App\Enums\TrackingType::QUANTITY->value]) }}" role="tab" aria-selected="{{ $f->tracking === \App\Enums\TrackingType::QUANTITY->value ? 'true' : 'false' }}"
                   class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg transition-all cursor-pointer {{ $f->tracking === \App\Enums\TrackingType::QUANTITY->value ? 'bg-white dark:bg-slate-900 text-sky-600 dark:text-sky-400 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100' }}">
                    <span>Curah (Qty)</span>
                </a>
            </div>

            <div class="h-4 w-px bg-slate-200 dark:bg-slate-700 mx-0.5 hidden sm:block" aria-hidden="true"></div>

            {{-- Quick Filter: Menipis (Stok Rendah) --}}
            <label class="inline-flex items-center justify-center gap-1.5 h-8 px-2.5 rounded-xl border text-xs font-semibold cursor-pointer transition-all select-none {{ $f->lowOnly ? 'bg-rose-50 dark:bg-rose-950/40 border-rose-300 dark:border-rose-800 text-rose-700 dark:text-rose-300 shadow-xs ring-1 ring-rose-400/20' : 'bg-slate-100/70 dark:bg-slate-800/80 border-slate-200/80 dark:border-slate-700/80 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' }}"
                   title="Saring hanya barang yang stoknya di bawah batas minimum">
                <input type="checkbox" name="low_stock_only" value="1" @checked($f->lowOnly) onchange="this.form.submit()" class="sr-only">
                <span class="w-2 h-2 rounded-full {{ $f->lowOnly ? 'bg-rose-500 animate-pulse' : 'bg-amber-500' }}" aria-hidden="true"></span>
                <span>Menipis</span>
            </label>
        </div>

        {{-- Sisi 2: Pencarian & Kategori --}}
        <div class="flex items-center gap-2 flex-1 min-w-0 max-w-full lg:max-w-2xl justify-end">
            {{-- Search Input with integrated clear button --}}
            <div class="relative flex-1 min-w-[11rem]">
                <label for="board-search" class="sr-only">Cari barang</label>
                <input type="search" name="search" id="board-search" value="{{ $f->search }}"
                       placeholder="Cari nama, kode, SN, atau nomor roll…"
                       class="w-full h-8.5 pl-8.5 pr-8 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/70 dark:bg-slate-800/70 text-slate-800 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:bg-white dark:focus:bg-slate-800 focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all shadow-2xs">
                <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400 dark:text-slate-500" aria-hidden="true">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                    </svg>
                </span>
                @if($f->search !== '')
                <a href="{{ $boardUrl(['search' => null]) }}" class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer" title="Hapus pencarian">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </a>
                @endif
            </div>

            {{-- Kategori Select --}}
            <div class="relative shrink-0 w-36 sm:w-44">
                <label for="board-category" class="sr-only">Kategori</label>
                <select name="category_id" id="board-category" onchange="this.form.submit()"
                        class="w-full h-8.5 pl-7 pr-7 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all shadow-2xs cursor-pointer appearance-none truncate">
                    <option value="">Semua kategori</option>
                    @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected($f->categoryId === $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                <span class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none text-slate-400 dark:text-slate-500" aria-hidden="true">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/>
                    </svg>
                </span>
                <span class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none text-slate-400 dark:text-slate-500" aria-hidden="true">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                </span>
            </div>

            <button type="submit" class="h-8.5 px-3 rounded-lg bg-sky-600 hover:bg-sky-700 active:scale-98 text-white text-xs font-semibold transition-all shadow-2xs cursor-pointer shrink-0">Cari</button>
        </div>
    </form>

    {{-- BARIS 3: Chip Filter Aktif & Informasi Pencarian --}}
    @if($chips->isNotEmpty() || $offerAllWarehouses || $searchTruncated)
    <div class="flex flex-wrap items-center justify-between gap-2 pt-2.5 border-t border-slate-100 dark:border-slate-800 text-xs">
        @if($chips->isNotEmpty())
        <div class="flex flex-wrap items-center gap-1.5" aria-label="Filter aktif">
            <span class="text-[10.5px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mr-0.5">Filter aktif</span>
            @foreach($chips as $chip)
            <a href="{{ $chip['url'] }}" title="Hapus filter ini"
               class="inline-flex items-center gap-1.5 pl-2.5 pr-1.5 py-0.5 rounded-full text-xs font-medium bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-300 border border-sky-200/80 dark:border-sky-800/80 hover:bg-sky-100 dark:hover:bg-sky-900/50 transition-colors cursor-pointer">
                <span>{{ $chip['label'] }}</span>
                <span class="inline-flex items-center justify-center w-3.5 h-3.5 rounded-full bg-sky-200/80 dark:bg-sky-800 text-sky-800 dark:text-sky-200" aria-hidden="true">
                    <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </span>
                <span class="sr-only">Hapus filter {{ $chip['label'] }}</span>
            </a>
            @endforeach
            <a href="{{ route('warehouse.stock.index', ['view' => $boardView, 'pop_id' => '']) }}" class="text-xs font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 underline underline-offset-2 ml-1 cursor-pointer">Hapus semua</a>
        </div>
        @endif

        @if($offerAllWarehouses || $searchTruncated)
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 ml-auto text-xs">
            @if($offerAllWarehouses)
            <a href="{{ $boardUrl(['pop_id' => '']) }}" class="font-semibold text-sky-600 dark:text-sky-400 hover:underline underline-offset-2 cursor-pointer">Cari di semua gudang terjangkau →</a>
            @endif
            @if($searchTruncated)
            <p class="text-amber-700 dark:text-amber-400 text-[11.5px]">Hasil pencarian SN/kode roll dibatasi 50 pasangan gudang–barang. Persempit kata kunci untuk hasil lengkap.</p>
            @endif
        </div>
        @endif
    </div>
    @endif
</div>

