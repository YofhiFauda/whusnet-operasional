{{--
    Tabel berkelompok papan Kelola Stok: band Gudang (gelap) → band Kategori →
    baris Barang (spec §3). Data dari WarehouseStockBoardService::build().

    Semua gudang & baris dirender server lalu dilipat Alpine (`stockBoard`,
    spec §9). Band gudang yang terlipat tetap menunjukkan jumlah SKU & menipis
    supaya Pusat bisa dipantau tanpa dibuka.

    Yang SENGAJA tidak ada: custody teknisi / dalam perjalanan (spec §1) dan
    istilah "lot" — dua tingkat harga QUANTITY ditulis "Harga lama"/"Harga baru".
--}}
@php
    $showActionColumn = $canAdjust || $canTransfer || $canIssue || $canRequestStock;
    // Tanpa nol di belakang, pemisah Indonesia — sama dengan tampilan lama.
    $fmtQty = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
    $trackingLabel = fn (\App\Enums\TrackingType $tracking) => match ($tracking) {
        \App\Enums\TrackingType::SERIALIZED => 'SN',
        \App\Enums\TrackingType::ROLL => 'Roll',
        \App\Enums\TrackingType::QUANTITY => 'Quantity',
    };
    $trackingClass = fn (\App\Enums\TrackingType $tracking) => match ($tracking) {
        \App\Enums\TrackingType::SERIALIZED => 'bg-cyan-50 dark:bg-cyan-950/40 text-cyan-700 dark:text-cyan-300 border-cyan-200 dark:border-cyan-800',
        \App\Enums\TrackingType::ROLL => 'bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 border-amber-200 dark:border-amber-800',
        \App\Enums\TrackingType::QUANTITY => 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700',
    };
    // Kolom tabel: 6 tetap + kolom Aksi bila ada izin aksi apa pun. Band
    // gudang/kategori memakai angka yang sama supaya tidak kurang/lebih kolom.
    $colspan = $showActionColumn ? 7 : 6;
    // Label kondisi SN dan pasangan "Harga lama/baru" dihitung di partial
    // `_detail` (satu sumber dengan drawer & menu aksi), bukan di sini.
@endphp

<div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl shadow-xs overflow-hidden">
    @if($emptyState !== null)
        @include('warehouse.stock.board._empty')
    @endif

    @if($groups->isNotEmpty())
    <div class="overflow-x-auto">
        <table class="min-w-full text-left text-xs">
            <thead class="bg-slate-50/80 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-700">
                <tr class="text-[11px] font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300">
                    <th scope="col" class="px-4 py-3">Barang</th>
                    <th scope="col" class="px-4 py-3 hidden md:table-cell">Tracking</th>
                    <th scope="col" class="px-4 py-3 text-right">Tersedia</th>
                    <th scope="col" class="px-4 py-3">Kesehatan</th>
                    <th scope="col" class="px-4 py-3 hidden md:table-cell">Kondisi</th>
                    <th scope="col" class="px-4 py-3 hidden md:table-cell">Opname terakhir</th>
                    @if($showActionColumn)
                    <th scope="col" class="px-4 py-3 text-right"><span class="sr-only sm:not-sr-only">Aksi</span></th>
                    @endif
                </tr>
            </thead>

            @foreach($groups as $group)
            @php
                $pop = $group['pop'];
                $popKey = (int) $pop->id;
            @endphp
            <tbody class="border-b border-slate-200 dark:border-slate-800" data-board-pop="{{ $popKey }}">
                {{-- Band gudang --}}
                <tr class="bg-slate-900 dark:bg-slate-950 text-white">
                    <td colspan="{{ $colspan }}" class="p-0">
                        <button type="button" @click="togglePop({{ $popKey }})"
                                :aria-expanded="isPopOpen({{ $popKey }}).toString()"
                                aria-expanded="{{ $startOpen ? 'true' : 'false' }}"
                                class="w-full flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1.5 px-4 py-3 text-left hover:bg-slate-800 dark:hover:bg-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400 focus-visible:ring-inset transition-colors">
                            <span class="flex flex-wrap items-center gap-2">
                                <svg class="w-4 h-4 text-slate-300 transition-transform duration-200" :class="isPopOpen({{ $popKey }}) ? '' : '-rotate-90'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                <span class="font-bold text-sm tracking-wide uppercase">{{ $pop->name }}</span>
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold border {{ $pop->isPusat() ? 'bg-sky-950 text-sky-300 border-sky-600/60' : 'bg-slate-800 text-slate-300 border-slate-600' }}">{{ strtoupper($pop->type) }}</span>
                            </span>
                            <span class="flex items-center gap-2 text-xs text-slate-300 pl-6 sm:pl-0">
                                @if($group['sku_count'] === 0)
                                <span class="italic">Belum ada stok tersedia</span>
                                @else
                                <span><strong class="text-white font-mono">{{ $group['sku_count'] }}</strong> SKU</span>
                                <span aria-hidden="true">·</span>
                                <span class="{{ $group['low_count'] > 0 ? 'text-amber-300 font-semibold' : '' }}">{{ $group['low_count'] }} menipis</span>
                                @endif
                            </span>
                        </button>
                    </td>
                </tr>

                @foreach($group['categories'] as $categoryGroup)
                @php
                    $category = $categoryGroup['category'];
                    $catKey = $popKey.'-'.($category?->id ?? 0);
                @endphp
                {{-- Band kategori ("rak" dalam bahasa pengguna) --}}
                <tr class="bg-slate-100 dark:bg-slate-800/70 border-y border-slate-200 dark:border-slate-700"
                    x-show="isPopOpen({{ $popKey }})" @unless($startOpen) x-cloak @endunless>
                    <td colspan="{{ $colspan }}" class="p-0">
                        <button type="button" @click="toggleCategory(@js($catKey))"
                                :aria-expanded="isCategoryOpen(@js($catKey)).toString()"
                                aria-expanded="true"
                                class="w-full flex items-center justify-between gap-2 px-4 py-2 text-left hover:bg-slate-200/70 dark:hover:bg-slate-700/60 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-inset transition-colors">
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-slate-500 dark:text-slate-400 transition-transform duration-200" :class="isCategoryOpen(@js($catKey)) ? '' : '-rotate-90'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                <span class="font-bold text-slate-800 dark:text-slate-100">{{ $category?->name ?? 'Tanpa Kategori' }}</span>
                            </span>
                            <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400"><span class="font-mono">{{ $categoryGroup['sku_count'] }}</span> SKU</span>
                        </button>
                    </td>
                </tr>

                @foreach($categoryGroup['rows'] as $row)
                @php
                    $item = $row['item'];
                    $tracking = $row['tracking'];
                    $isRoll = $tracking === \App\Enums\TrackingType::ROLL;
                    // Payload drawer + URL aksi: satu partial (dipakai juga sel
                    // matriks). JSON mentahnya ditaruh apa adanya di
                    // data-stock-detail; versi array dipakai menu aksi server.
                    $detailJson = trim(view('warehouse.stock.board._detail', compact('pop', 'row', 'canAdjust', 'canTransfer', 'canIssue', 'canRequestStock'))->render());
                    $detail = json_decode($detailJson, true, flags: JSON_THROW_ON_ERROR);
                    $tiers = count($detail['priceTiers']) > 1 ? $detail['priceTiers'] : [];
                    $condition = $detail['conditionLabel'];
                    $opname = $row['last_opname_at'];
                @endphp
                {{-- Klik di mana saja pada baris membuka drawer lewat tombol nama
                     barang (satu-satunya pemicu, jadi keyboard Enter/Space dan
                     fokus-kembali bekerja sama persis). Sel aksi menghentikan
                     klik supaya menu tidak ikut membuka drawer. --}}
                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors align-top cursor-pointer {{ $row['is_low'] ? 'bg-rose-50/30 dark:bg-rose-950/10' : '' }}"
                    data-board-row data-pop-id="{{ $popKey }}" data-item-id="{{ $item->id }}"
                    @click="$el.querySelector('[data-stock-detail]').click()"
                    x-show="isPopOpen({{ $popKey }}) && isCategoryOpen(@js($catKey))" @unless($startOpen) x-cloak @endunless>
                    {{-- Kode + Barang (di mobile juga memuat tracking/kondisi/opname) --}}
                    <td class="px-4 py-3">
                        <div class="font-mono text-[11px] text-slate-400 dark:text-slate-500">{{ $item->code }}</div>
                        <button type="button" data-stock-detail="{{ $detailJson }}" aria-haspopup="dialog"
                                @click.stop="$el.focus(); $dispatch('open-stock-drawer', JSON.parse($el.dataset.stockDetail))"
                                class="text-left text-sm font-semibold text-slate-900 dark:text-slate-100 hover:text-sky-700 dark:hover:text-sky-300 hover:underline underline-offset-2 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-1 dark:focus-visible:ring-offset-slate-900 cursor-pointer">{{ $item->name }}</button>
                        @if($row['match_label'])
                        <div class="mt-1 inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-semibold bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-300">{{ $row['match_label'] }}</div>
                        @endif
                        <div class="md:hidden mt-1.5 space-y-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                            <div><span class="font-semibold">{{ $trackingLabel($tracking) }}</span>@if($condition !== '') · {{ $condition }}@endif</div>
                            @foreach($tiers as $tier)
                            <div>{{ $tier['label'] }}: {{ $fmtQty($tier['qty']) }} {{ $row['unit'] }}@if($tier['price'] !== null) · Rp {{ number_format($tier['price'], 0, ',', '.') }}@endif</div>
                            @endforeach
                            <div class="{{ $opname ? '' : 'text-amber-700 dark:text-amber-400' }}">Opname: {{ $opname ? $opname->diffForHumans() : 'Belum pernah' }}</div>
                        </div>
                    </td>

                    <td class="px-4 py-3 hidden md:table-cell whitespace-nowrap">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold border {{ $trackingClass($tracking) }}">{{ $trackingLabel($tracking) }}</span>
                    </td>

                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <div><span class="text-base font-extrabold font-mono tabular-nums {{ $row['is_low'] ? 'text-rose-600 dark:text-rose-400' : 'text-slate-900 dark:text-slate-100' }}">{{ $fmtQty($row['qty']) }}</span> <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $row['unit'] }}</span></div>
                        @if($isRoll)
                        <div class="text-[11px] text-slate-500 dark:text-slate-400">{{ $row['roll_count'] }} roll</div>
                        @endif
                    </td>

                    <td class="px-4 py-3 whitespace-nowrap">
                        @if($row['is_low'])
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-rose-100 dark:bg-rose-950/40 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500" aria-hidden="true"></span>Menipis
                        </span>
                        @else
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span>Aman
                        </span>
                        @endif
                        @if($row['min'] !== null)
                        <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 font-mono">Min {{ $fmtQty($row['min']) }}</div>
                        @endif
                    </td>

                    <td class="px-4 py-3 hidden md:table-cell text-[11px] text-slate-600 dark:text-slate-300">
                        @if($condition !== '')
                        {{ $condition }}
                        @elseif($tiers !== [])
                        @foreach($tiers as $tier)
                        <div class="whitespace-nowrap"><span class="font-semibold">{{ $tier['label'] }}</span>: <span class="font-mono">{{ $fmtQty($tier['qty']) }}</span> {{ $row['unit'] }}@if($tier['price'] !== null) · <span class="font-mono">Rp {{ number_format($tier['price'], 0, ',', '.') }}</span>@endif</div>
                        @endforeach
                        @else
                        <span class="text-slate-400 dark:text-slate-500" aria-label="Tidak ada rincian kondisi">—</span>
                        @endif
                    </td>

                    <td class="px-4 py-3 hidden md:table-cell whitespace-nowrap text-[11px] {{ $opname ? 'text-slate-500 dark:text-slate-400' : 'text-amber-700 dark:text-amber-400 font-medium' }}">
                        @if($opname)
                        <span title="{{ $opname->translatedFormat('d M Y H:i') }}">{{ $opname->diffForHumans() }}</span>
                        @else
                        Belum pernah
                        @endif
                    </td>

                    @if($showActionColumn)
                    <td class="px-4 py-3 text-right whitespace-nowrap cursor-default" @click.stop>
                        @include('warehouse.stock.board._actions', ['mode' => 'menu', 'detail' => $detail])
                    </td>
                    @endif
                </tr>
                @endforeach
                @endforeach
            </tbody>
            @endforeach
        </table>
    </div>
    @endif
</div>

@include('warehouse.stock.board._drawer')

@include('warehouse.stock.board._script')
