{{--
    Matriks papan Kelola Stok: baris = barang (dikelompokkan per kategori),
    kolom = gudang, sel = tersedia. Data dari WarehouseStockBoardService::matrix()
    (pivot build(), jadi angka sama dengan tabel).

    Aturan tampilan (spec §3):
    - Kolom pertama & header sticky; area gudang menggulir horizontal. Wadahnya
      `overflow-auto` + tinggi maksimum supaya sticky atas juga berfungsi
      (sticky selalu relatif ke wadah gulir terdekat).
    - Sel kosong/menipis ditandai warna DAN teks ("⚠ kosong"/"⚠ menipis").
    - Sel ber-stok = tombol pemicu drawer yang sama dengan baris tabel: payload
      dari partial `_detail`, `data-stock-detail` pada tombol, `data-board-row`
      pada wadahnya — drawer mencari pemicunya lewat `closest('[data-board-row]')`.
      Sel kosong bukan tombol: tidak ada yang bisa dibuka.
    - Tanpa custody/dalam perjalanan dan tanpa istilah "lot" (spec §1).
--}}
@php
    $pops = $matrix['pops'];
    $fmtQty = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
    $trackingLabel = fn (\App\Enums\TrackingType $tracking) => match ($tracking) {
        \App\Enums\TrackingType::SERIALIZED => 'SN',
        \App\Enums\TrackingType::ROLL => 'Roll',
        \App\Enums\TrackingType::QUANTITY => 'Quantity',
    };
    $colspan = $pops->count() + 1;
@endphp

<div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl shadow-xs overflow-hidden">
    @if($emptyState !== null)
        @include('warehouse.stock.board._empty')
    @endif

    @if($matrix['categories']->isNotEmpty())
    <div class="overflow-auto max-h-[70vh]" tabindex="0" role="region" aria-label="Matriks stok barang per gudang">
        <table class="min-w-full text-left text-xs border-separate border-spacing-0">
            <thead>
                <tr class="text-[11px] font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300">
                    <th scope="col" class="sticky top-0 left-0 z-30 min-w-[11rem] sm:min-w-[16rem] px-4 py-3 bg-slate-50 dark:bg-slate-800 border-b border-r border-slate-200 dark:border-slate-700">Barang</th>
                    @foreach($pops as $pop)
                    <th scope="col" class="sticky top-0 z-20 min-w-[7.5rem] px-3 py-3 text-right whitespace-nowrap bg-slate-50 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
                        <span class="block normal-case tracking-normal text-xs font-bold text-slate-800 dark:text-slate-100">{{ $pop->name }}</span>
                        <span class="block text-[10px] {{ $pop->isPusat() ? 'text-sky-700 dark:text-sky-300' : 'text-slate-500 dark:text-slate-400' }}">{{ strtoupper($pop->type) }}</span>
                    </th>
                    @endforeach
                </tr>
            </thead>

            <tbody>
                @foreach($matrix['categories'] as $categoryGroup)
                @php
                    $category = $categoryGroup['category'];
                    $catKey = 'm-'.($category?->id ?? 0);
                @endphp
                {{-- Band kategori: tombol menempel ke kiri supaya tetap terlihat saat digulir. --}}
                <tr class="bg-slate-100 dark:bg-slate-800/70">
                    <td colspan="{{ $colspan }}" class="p-0 border-y border-slate-200 dark:border-slate-700">
                        <button type="button" @click="toggleCategory(@js($catKey))"
                                :aria-expanded="isCategoryOpen(@js($catKey)).toString()"
                                aria-expanded="true"
                                class="sticky left-0 flex items-center gap-2 px-4 py-2 text-left hover:bg-slate-200/70 dark:hover:bg-slate-700/60 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-inset transition-colors cursor-pointer">
                            <svg class="w-4 h-4 text-slate-500 dark:text-slate-400 transition-transform duration-200" :class="isCategoryOpen(@js($catKey)) ? '' : '-rotate-90'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            <span class="font-bold text-slate-800 dark:text-slate-100">{{ $category?->name ?? 'Tanpa Kategori' }}</span>
                            <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400"><span class="font-mono">{{ $categoryGroup['rows']->count() }}</span> SKU</span>
                        </button>
                    </td>
                </tr>

                @foreach($categoryGroup['rows'] as $matrixRow)
                @php $item = $matrixRow['item']; @endphp
                <tr x-show="isCategoryOpen(@js($catKey))" class="group">
                    <th scope="row" class="sticky left-0 z-10 min-w-[11rem] sm:min-w-[16rem] px-4 py-2.5 font-normal bg-white dark:bg-slate-900 group-hover:bg-slate-50 dark:group-hover:bg-slate-800 border-b border-r border-slate-100 dark:border-slate-800">
                        <div class="font-mono text-[11px] text-slate-400 dark:text-slate-500">{{ $item->code }}</div>
                        <div class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $item->name }}</div>
                        <div class="text-[11px] text-slate-500 dark:text-slate-400">{{ $trackingLabel($matrixRow['tracking']) }}</div>
                    </th>

                    @foreach($pops as $pop)
                    @php
                        $cell = $matrixRow['cells'][$pop->id];
                        $cellRow = $cell['row'];
                        $tone = $cell['is_low'] || $cellRow === null
                            ? 'bg-rose-50/60 dark:bg-rose-950/20'
                            : '';
                    @endphp
                    @if($cellRow !== null)
                    @php
                        $detailJson = trim(view('warehouse.stock.board._detail', ['pop' => $pop, 'row' => $cellRow, 'canAdjust' => $canAdjust, 'canTransfer' => $canTransfer, 'canIssue' => $canIssue, 'canRequestStock' => $canRequestStock])->render());
                    @endphp
                    <td class="p-0 text-right whitespace-nowrap border-b border-slate-100 dark:border-slate-800 {{ $tone }} group-hover:bg-slate-50 dark:group-hover:bg-slate-800/60"
                        data-board-row data-pop-id="{{ $pop->id }}" data-item-id="{{ $item->id }}">
                        <button type="button" data-stock-detail="{{ $detailJson }}" aria-haspopup="dialog"
                                aria-label="Rincian {{ $item->name }} di {{ $pop->name }}: {{ $fmtQty($cell['qty']) }} {{ $matrixRow['unit'] }}{{ $cell['is_low'] ? ', menipis' : '' }}"
                                @click="$el.focus(); $dispatch('open-stock-drawer', JSON.parse($el.dataset.stockDetail))"
                                class="block w-full px-3 py-2.5 text-right hover:bg-sky-50 dark:hover:bg-sky-950/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-inset cursor-pointer transition-colors">
                            <span class="text-sm font-extrabold font-mono tabular-nums {{ $cell['is_low'] ? 'text-rose-700 dark:text-rose-300' : 'text-slate-900 dark:text-slate-100' }}">{{ $fmtQty($cell['qty']) }}</span>
                            <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">{{ $matrixRow['unit'] }}</span>
                            @if($cell['is_low'])
                            <span class="block text-[11px] font-semibold text-rose-700 dark:text-rose-300">⚠ menipis</span>
                            @endif
                        </button>
                    </td>
                    @else
                    <td class="px-3 py-2.5 text-right whitespace-nowrap border-b border-slate-100 dark:border-slate-800 {{ $tone }}">
                        <span class="text-sm font-mono tabular-nums text-slate-400 dark:text-slate-500">0</span>
                        <span class="block text-[11px] font-semibold text-rose-700 dark:text-rose-300">⚠ kosong</span>
                    </td>
                    @endif
                    @endforeach
                </tr>
                @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

@include('warehouse.stock.board._drawer')

@include('warehouse.stock.board._script')
