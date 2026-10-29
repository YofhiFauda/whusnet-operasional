{{--
    Toolbar papan Kelola Stok: cari, kategori, tracking, pill Menipis, chip
    filter aktif, dan tombol bentang/ciutkan (state Alpine milik `stockBoard`
    di pembungkus index.blade.php).

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
    $trackingLabels = [
        \App\Enums\TrackingType::SERIALIZED->value => 'SN',
        \App\Enums\TrackingType::ROLL->value => 'Roll',
        \App\Enums\TrackingType::QUANTITY->value => 'Quantity',
    ];
    $popParam = $f->popId ?? (request()->has('pop_id') ? '' : null);
    $boardBase = array_filter([
        'view' => 'papan',
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

    $searchTruncated = $groups->contains(fn ($group) => $group['search_truncated']);
    $offerAllWarehouses = $f->popId !== null && $f->search !== '' && ! $lockedSinglePop;
    $fieldClass = 'h-9 px-3 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all shadow-2xs';
@endphp

<div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-3 sm:p-4 shadow-xs mb-4 space-y-3">
    <div class="flex flex-col xl:flex-row xl:items-center gap-3">
        <form method="GET" action="{{ route('warehouse.stock.index') }}" class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-2 flex-1" role="search" aria-label="Saring papan stok">
            <input type="hidden" name="view" value="papan">
            @if($popParam !== null)
            <input type="hidden" name="pop_id" value="{{ $popParam }}">
            @endif

            <div class="relative flex-1 min-w-0 sm:min-w-[16rem]">
                <label for="board-search" class="sr-only">Cari barang</label>
                <input type="search" name="search" id="board-search" value="{{ $f->search }}"
                       placeholder="Cari nama, kode, SN, atau kode roll…"
                       class="w-full h-9 pl-9 pr-3 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-800/60 text-slate-800 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:bg-white dark:focus:bg-slate-800 focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all shadow-2xs">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 dark:text-slate-500" aria-hidden="true">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                </span>
            </div>

            <label for="board-category" class="sr-only">Kategori</label>
            <select name="category_id" id="board-category" onchange="this.form.submit()" class="{{ $fieldClass }}">
                <option value="">Semua kategori</option>
                @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected($f->categoryId === $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>

            <label for="board-tracking" class="sr-only">Tracking</label>
            <select name="tracking" id="board-tracking" onchange="this.form.submit()" class="{{ $fieldClass }}">
                <option value="">Semua tracking</option>
                @foreach($trackingLabels as $value => $label)
                <option value="{{ $value }}" @selected($f->tracking === $value)>{{ $label }}</option>
                @endforeach
            </select>

            {{-- Pill Menipis: checkbox asli (bisa dijangkau keyboard), status
                 ditulis sebagai teks, bukan cuma warna. --}}
            <label class="inline-flex items-center justify-center gap-1.5 h-9 px-3 rounded-lg border text-xs font-semibold cursor-pointer transition-colors focus-within:ring-2 focus-within:ring-sky-500/30 {{ $f->lowOnly ? 'bg-rose-50 dark:bg-rose-950/40 border-rose-300 dark:border-rose-800 text-rose-700 dark:text-rose-300' : 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700' }}">
                <input type="checkbox" name="low_stock_only" value="1" @checked($f->lowOnly) onchange="this.form.submit()" class="sr-only">
                <span class="w-1.5 h-1.5 rounded-full {{ $f->lowOnly ? 'bg-rose-500' : 'bg-amber-500' }}" aria-hidden="true"></span>
                <span>Menipis</span>
            </label>

            <button type="submit" class="h-9 px-3.5 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold transition-colors shadow-2xs">Cari</button>
        </form>

        <div class="flex items-center gap-2 shrink-0">
            <button type="button" @click="expandAll()" class="h-9 px-3 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold inline-flex items-center gap-1.5 transition-colors shadow-2xs">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg>
                Bentang semua
            </button>
            <button type="button" @click="collapseAll()" class="h-9 px-3 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold inline-flex items-center gap-1.5 transition-colors shadow-2xs">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 5l-4 4-4-4m8 14l-4-4-4 4"/></svg>
                Ciutkan
            </button>
        </div>
    </div>

    @if($chips->isNotEmpty())
    <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-100 dark:border-slate-800" aria-label="Filter aktif">
        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Filter aktif</span>
        @foreach($chips as $chip)
        <a href="{{ $chip['url'] }}" title="Hapus filter ini"
           class="inline-flex items-center gap-1.5 pl-2.5 pr-1.5 py-1 rounded-full text-xs font-semibold bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800 hover:bg-sky-100 dark:hover:bg-sky-900/50 transition-colors">
            <span>{{ $chip['label'] }}</span>
            <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-sky-200/70 dark:bg-sky-800 text-sky-800 dark:text-sky-200" aria-hidden="true">
                <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </span>
            <span class="sr-only">Hapus filter {{ $chip['label'] }}</span>
        </a>
        @endforeach
        <a href="{{ route('warehouse.stock.index', ['view' => 'papan', 'pop_id' => '']) }}" class="text-xs font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 underline underline-offset-2 ml-1">Hapus semua</a>
    </div>
    @endif

    @if($offerAllWarehouses || $searchTruncated)
    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
        @if($offerAllWarehouses)
        <a href="{{ $boardUrl(['pop_id' => '']) }}" class="font-semibold text-sky-700 dark:text-sky-400 hover:underline underline-offset-2">Cari di semua gudang terjangkau →</a>
        @endif
        @if($searchTruncated)
        {{-- Pencarian SN/roll dibatasi 50 pasangan gudang-barang (pengaman
             beban); angka di papan bisa belum lengkap — katakan terus terang. --}}
        <p class="text-amber-700 dark:text-amber-400">Hasil pencarian SN/kode roll dibatasi 50 pasangan gudang–barang. Persempit kata kunci untuk hasil lengkap.</p>
        @endif
    </div>
    @endif
</div>
