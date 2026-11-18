@extends('layouts.app')

@section('title', 'Barang Nonoperasional - Whusnet Operasional')
@section('page_title', 'Barang Nonoperasional')

@php
    $activeFilterCount = collect(['from_date', 'to_date', 'item_category_id', 'item_id', 'cabang_pop_id'])
        ->filter(fn ($key) => filled($filters[$key] ?? null))
        ->count();

    $field = 'w-full min-h-[40px] rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-sky-600 focus:outline-none focus:ring-2 focus:ring-sky-600/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white';
    $label = 'mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300';

    // Tabel responsif tanpa markup ganda: di < md baris tampil sebagai kartu
    // (tr = flex-wrap, td = item flex), di ≥ md kembali jadi tabel biasa.
    // Sengaja satu markup `<td>` — test scope POP (`assertDontSee('Cabang A</td>')`)
    // tetap menjaga isi tabel, tidak terlewat oleh salinan versi kartu.
    $thead = 'hidden md:table-header-group bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900 dark:text-slate-400';
    $tr = 'flex flex-wrap items-baseline gap-x-3 gap-y-0.5 px-4 py-3 md:table-row md:p-0 hover:bg-slate-50 dark:hover:bg-slate-700/40';
    $td = 'md:table-cell md:px-5 md:py-3';
    $meta = 'order-2 text-xs text-slate-500 dark:text-slate-400 md:text-sm md:text-slate-700 md:dark:text-slate-200';
@endphp

@section('content')
<x-warehouse.header active="stock-requests" title="Barang Nonoperasional" subtitle="Stok merchandise & barang nonoperasional Pusat dan Cabang." />

<div x-data="{ tab: '{{ in_array(request('tab'), ['stock', 'activity', 'report'], true) ? request('tab') : 'stock' }}', showFilter: false }" class="space-y-4">

    @if(session('success'))
        <div role="status" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">{{ session('success') }}</div>
    @endif

    {{-- Aksi + ringkasan --}}
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        {{-- Ringkasan = satu strip pendek (angka di atas, label 1 kata di
             bawah). Versi kotak terpisah + label UPPERCASE dua kata membungkus
             jadi 2 baris di HP dan memakan ± 1/6 layar. --}}
        <dl class="flex divide-x divide-slate-200 rounded-lg border border-slate-200 bg-white dark:divide-slate-700 dark:border-slate-700 dark:bg-slate-800 lg:divide-x-0 lg:gap-6 lg:border-0 lg:bg-transparent lg:dark:bg-transparent">
            @foreach([
                ['Barang', $positions->pluck('item.id')->unique()->count()],
                ['Lokasi', $positions->pluck('pop.id')->unique()->count()],
                ['Perpindahan', $requests->total()],
            ] as [$statLabel, $statValue])
                <div class="flex flex-1 flex-col-reverse items-center px-2 py-1.5 lg:flex-none lg:flex-row lg:items-baseline lg:gap-1.5 lg:p-0">
                    <dt class="whitespace-nowrap text-xs text-slate-500 dark:text-slate-400">{{ $statLabel }}</dt>
                    <dd class="font-mono text-base font-semibold tabular-nums text-slate-900 dark:text-white">{{ $statValue }}</dd>
                </div>
            @endforeach
        </dl>

        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            @if(auth()->user()->hasPermission('warehouse_stock_request.create'))
                <a href="{{ route('warehouse.stock-requests.create') }}" class="order-last text-center text-xs font-semibold text-slate-500 hover:text-sky-700 dark:text-slate-400 dark:hover:text-sky-400 sm:order-first sm:mr-2">Permintaan Stok Operasional →</a>
            @endif
            <div class="grid grid-cols-2 gap-2 sm:flex">
                @if(auth()->user()->hasPermission('warehouse_transfer.create'))
                    <a href="{{ route('warehouse.marketing.receive.create') }}" class="inline-flex min-h-[42px] items-center justify-center gap-1.5 whitespace-nowrap rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 transition hover:border-sky-600 hover:text-sky-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:text-sky-400 sm:px-4">
                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        <span>Barang Masuk<span class="hidden sm:inline"> Pusat</span></span>
                    </a>
                @endif
                @if(auth()->user()->hasPermission('warehouse_stock_request.create'))
                    <a href="{{ route('warehouse.marketing.create') }}" class="inline-flex min-h-[42px] items-center justify-center gap-1.5 whitespace-nowrap rounded-lg bg-sky-600 px-3 text-sm font-semibold text-white transition hover:bg-sky-700 sm:px-4">
                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                        <span class="sm:hidden">Ke Cabang</span><span class="hidden sm:inline">Pindahkan ke Cabang</span>
                    </a>
                @endif
            </div>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">

        {{-- Tab + tombol filter --}}
        <div class="flex items-center gap-2 border-b border-slate-200 p-2 dark:border-slate-700 md:px-4">
            <div role="tablist" aria-label="Data barang nonoperasional" class="grid flex-1 grid-cols-3 gap-1 rounded-lg bg-slate-100 p-1 dark:bg-slate-900 md:flex md:flex-none">
                {{-- Label pendek di HP supaya tidak terpotong ("Laporan ..."). --}}
                @foreach(['stock' => ['Stok', 'Posisi Stok'], 'activity' => ['Aktivitas', 'Aktivitas'], 'report' => ['Laporan', 'Laporan Mutasi']] as $key => [$tabShort, $tabLabel])
                    <button type="button" role="tab" id="tab-{{ $key }}" aria-controls="panel-{{ $key }}" :aria-selected="tab === '{{ $key }}'" @click="tab = '{{ $key }}'"
                            :class="tab === '{{ $key }}' ? 'bg-white text-sky-700 shadow-xs dark:bg-slate-800 dark:text-sky-400' : 'text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white'"
                            class="min-h-[36px] truncate rounded-md px-2 text-sm font-semibold md:px-4"><span class="sm:hidden">{{ $tabShort }}</span><span class="hidden sm:inline">{{ $tabLabel }}</span></button>
                @endforeach
            </div>
            <button type="button" @click="showFilter = ! showFilter" :aria-expanded="showFilter"
                    class="inline-flex min-h-[44px] shrink-0 items-center gap-1.5 rounded-lg border px-3 text-sm font-semibold lg:hidden"
                    :class="showFilter ? 'border-sky-600 bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300' : 'border-slate-300 text-slate-700 dark:border-slate-600 dark:text-slate-200'">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z"/></svg>
                <span class="hidden sm:inline">Filter</span>
                @if($activeFilterCount > 0)
                    <span class="rounded-full bg-sky-600 px-1.5 text-[11px] font-bold text-white">{{ $activeFilterCount }}</span>
                @endif
            </button>
        </div>

        {{-- Filter: terlipat di mobile/tablet, selalu terbuka di desktop.
             Tanggal disembunyikan di tab Posisi Stok karena saldo saat ini
             tidak dipengaruhi tanggal (dulu dijelaskan lewat paragraf). --}}
        <form method="GET" action="{{ route('warehouse.marketing.index') }}"
              :class="showFilter ? 'grid' : 'hidden lg:grid'"
              class="gap-3 border-b border-slate-200 bg-slate-50/60 p-4 dark:border-slate-700 dark:bg-slate-900/40 sm:grid-cols-2 lg:grid-cols-[repeat(5,minmax(0,1fr))_auto] lg:items-end">
            <input type="hidden" name="tab" :value="tab">
            <div x-show="tab !== 'stock'"><label for="from_date" class="{{ $label }}">Dari tanggal</label><input id="from_date" name="from_date" type="date" value="{{ $filters['from_date'] ?? '' }}" class="{{ $field }}"></div>
            <div x-show="tab !== 'stock'"><label for="to_date" class="{{ $label }}">Sampai tanggal</label><input id="to_date" name="to_date" type="date" value="{{ $filters['to_date'] ?? '' }}" class="{{ $field }}"></div>
            <div><label for="item_category_id" class="{{ $label }}">Kategori</label><select id="item_category_id" name="item_category_id" class="{{ $field }}"><option value="">Semua kategori</option>@foreach($filterCategories as $category)<option value="{{ $category->id }}" @selected(($filters['item_category_id'] ?? null) == $category->id)>{{ $category->name }}</option>@endforeach</select></div>
            <div><label for="item_id" class="{{ $label }}">Barang</label><select id="item_id" name="item_id" class="{{ $field }}"><option value="">Semua barang</option>@foreach($filterItems as $item)<option value="{{ $item->id }}" @selected(($filters['item_id'] ?? null) == $item->id)>{{ $item->name }}</option>@endforeach</select></div>
            <div><label for="cabang_pop_id" class="{{ $label }}">Cabang</label><select id="cabang_pop_id" name="cabang_pop_id" class="{{ $field }}"><option value="">Semua cabang</option>@foreach($cabangPops as $pop)<option value="{{ $pop->id }}" @selected(($filters['cabang_pop_id'] ?? null) == $pop->id)>{{ $pop->name }}</option>@endforeach</select></div>
            <div class="flex gap-2 sm:col-span-2 lg:col-span-1 lg:col-start-6">
                <button type="submit" class="min-h-[40px] flex-1 rounded-lg bg-sky-600 px-4 text-sm font-semibold text-white hover:bg-sky-700 lg:flex-none">Terapkan</button>
                <a href="{{ route('warehouse.marketing.index') }}" class="inline-flex min-h-[40px] items-center rounded-lg px-3 text-sm font-semibold text-slate-600 hover:text-sky-700 dark:text-slate-300">Reset</a>
            </div>
        </form>

        {{-- POSISI STOK --}}
        <section id="panel-stock" role="tabpanel" aria-labelledby="tab-stock" x-show="tab === 'stock'">
            <table class="w-full text-sm">
                <thead class="{{ $thead }}"><tr><th scope="col" class="px-5 py-3">Lokasi</th><th scope="col" class="px-5 py-3">Barang</th><th scope="col" class="px-5 py-3 text-right">Tersedia</th></tr></thead>
                <tbody class="block divide-y divide-slate-100 dark:divide-slate-700 md:table-row-group">
                    @forelse($positions as $position)
                        <tr class="{{ $tr }}">
                            <td class="{{ $td }} {{ $meta }} w-full md:w-auto">{{ $position['pop']->name }}</td>
                            <td class="{{ $td }} order-1 min-w-0 flex-1 font-medium text-slate-900 dark:text-white">{{ $position['item']->name }}<span class="ml-2 font-mono text-xs font-normal text-slate-500">{{ $position['item']->code }}</span></td>
                            <td class="{{ $td }} order-1 text-right font-mono font-semibold tabular-nums text-slate-900 dark:text-white">{{ number_format($position['qty'], 2, ',', '.') }} <span class="font-sans text-xs font-normal text-slate-500">{{ $position['item']->unit }}</span></td>
                        </tr>
                    @empty
                        <tr class="block md:table-row"><td colspan="3" class="block px-5 py-12 text-center text-slate-500 md:table-cell">Belum ada stok. Mulai dari “Barang Masuk Pusat”.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        {{-- AKTIVITAS --}}
        <section id="panel-activity" role="tabpanel" aria-labelledby="tab-activity" x-show="tab === 'activity'" x-cloak>
            <h2 class="border-b border-slate-100 px-4 py-3 text-sm font-semibold text-slate-900 dark:border-slate-700 dark:text-white md:px-5">Perpindahan ke Cabang</h2>
            <table class="w-full text-sm">
                <thead class="{{ $thead }}"><tr><th scope="col" class="px-5 py-3">Waktu / Referensi</th><th scope="col" class="px-5 py-3">Cabang</th><th scope="col" class="px-5 py-3">Barang</th><th scope="col" class="px-5 py-3 text-right">Jumlah</th><th scope="col" class="px-5 py-3">Petugas</th></tr></thead>
                <tbody class="block divide-y divide-slate-100 dark:divide-slate-700 md:table-row-group">
                    @forelse($requests as $stockRequest)
                        @foreach($stockRequest->items as $line)
                            <tr class="{{ $tr }}">
                                <td class="{{ $td }} {{ $meta }} order-3">{{ $stockRequest->created_at->format('d/m/Y H:i') }}<div class="hidden font-mono text-xs text-slate-500 md:block">{{ $stockRequest->reference_number }}</div></td>
                                <td class="{{ $td }} {{ $meta }} font-semibold md:font-normal">{{ $stockRequest->cabangPop->name }}</td>
                                <td class="{{ $td }} order-1 min-w-0 flex-1 font-medium text-slate-900 dark:text-white md:font-normal md:text-slate-700 md:dark:text-slate-200">{{ $line->item->name }}</td>
                                <td class="{{ $td }} order-1 text-right font-mono font-semibold tabular-nums text-slate-900 dark:text-white md:font-normal">{{ number_format((float) $line->qty_requested, 2, ',', '.') }}</td>
                                <td class="{{ $td }} {{ $meta }} order-4">{{ $stockRequest->requestedBy->name }}</td>
                            </tr>
                        @endforeach
                    @empty
                        <tr class="block md:table-row"><td colspan="5" class="block px-5 py-10 text-center text-slate-500 md:table-cell">Belum ada perpindahan pada filter ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if($requests->hasPages())
                <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-700 md:px-5">{{ $requests->appends(['tab' => 'activity'])->links() }}</div>
            @endif

            <h2 class="border-y border-slate-100 bg-slate-50/60 px-4 py-3 text-sm font-semibold text-slate-900 dark:border-slate-700 dark:bg-slate-900/40 dark:text-white md:px-5">Barang masuk Pusat</h2>
            <table class="w-full text-sm">
                <thead class="{{ $thead }}"><tr><th scope="col" class="px-5 py-3">Waktu / Referensi</th><th scope="col" class="px-5 py-3">Pusat</th><th scope="col" class="px-5 py-3">Barang</th><th scope="col" class="px-5 py-3 text-right">Masuk</th><th scope="col" class="px-5 py-3">Petugas</th></tr></thead>
                <tbody class="block divide-y divide-slate-100 dark:divide-slate-700 md:table-row-group">
                    @forelse($receipts as $receipt)
                        <tr class="{{ $tr }}">
                            <td class="{{ $td }} {{ $meta }} order-3">{{ $receipt->created_at->format('d/m/Y H:i') }}<div class="hidden font-mono text-xs text-slate-500 md:block">{{ $receipt->reference_number }}</div></td>
                            <td class="{{ $td }} {{ $meta }} font-semibold md:font-normal">{{ $receipt->toPop->name }}</td>
                            <td class="{{ $td }} order-1 min-w-0 flex-1 font-medium text-slate-900 dark:text-white md:font-normal md:text-slate-700 md:dark:text-slate-200">{{ $receipt->item->name }}</td>
                            <td class="{{ $td }} order-1 text-right font-mono font-semibold tabular-nums text-emerald-700 dark:text-emerald-400 md:font-normal">+{{ number_format((float) $receipt->qty, 2, ',', '.') }}</td>
                            <td class="{{ $td }} {{ $meta }} order-4">{{ $receipt->createdBy?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr class="block md:table-row"><td colspan="5" class="block px-5 py-10 text-center text-slate-500 md:table-cell">Belum ada barang masuk pada filter ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if($receipts->hasPages())
                <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-700 md:px-5">{{ $receipts->appends(['tab' => 'activity'])->links() }}</div>
            @endif
        </section>

        {{-- LAPORAN MUTASI --}}
        <section id="panel-report" role="tabpanel" aria-labelledby="tab-report" x-show="tab === 'report'" x-cloak>
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 dark:border-slate-700 md:px-5">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Mutasi stok</h2>
                <a href="{{ route('warehouse.marketing.export', request()->only(['from_date', 'to_date', 'item_id', 'item_category_id', 'cabang_pop_id'])) }}" hx-boost="false"
                   class="inline-flex min-h-[36px] items-center gap-1.5 rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:border-sky-600 hover:text-sky-700 dark:border-slate-600 dark:text-slate-200">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    Ekspor CSV
                </a>
            </div>
            <table class="w-full text-sm">
                <thead class="{{ $thead }}"><tr><th scope="col" class="px-5 py-3">Lokasi</th><th scope="col" class="px-5 py-3">Barang</th><th scope="col" class="px-5 py-3 text-right">Saldo awal</th><th scope="col" class="px-5 py-3 text-right">Masuk</th><th scope="col" class="px-5 py-3 text-right">Keluar</th><th scope="col" class="px-5 py-3 text-right">Saldo akhir</th></tr></thead>
                <tbody class="block divide-y divide-slate-100 dark:divide-slate-700 md:table-row-group">
                    @forelse($stockSummary as $row)
                        <tr class="{{ $tr }}">
                            <td class="{{ $td }} {{ $meta }} w-full md:w-auto">{{ $row['pop']->name }}</td>
                            <td class="{{ $td }} order-1 min-w-0 flex-1 font-medium text-slate-900 dark:text-white md:font-normal">{{ $row['item']->name }}</td>
                            <td class="{{ $td }} {{ $meta }} order-3 font-mono tabular-nums md:text-right"><span class="font-sans md:hidden">Awal </span>{{ number_format($row['opening'], 2, ',', '.') }}</td>
                            <td class="{{ $td }} {{ $meta }} order-3 font-mono tabular-nums md:text-right"><span class="font-sans md:hidden">Masuk </span>{{ number_format($row['incoming'], 2, ',', '.') }}</td>
                            <td class="{{ $td }} {{ $meta }} order-3 font-mono tabular-nums md:text-right"><span class="font-sans md:hidden">Keluar </span>{{ number_format($row['outgoing'], 2, ',', '.') }}</td>
                            <td class="{{ $td }} order-1 text-right font-mono font-semibold tabular-nums text-slate-900 dark:text-white">{{ number_format($row['closing'], 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr class="block md:table-row"><td colspan="6" class="block px-5 py-10 text-center text-slate-500 md:table-cell">Tidak ada saldo barang pada filter ini.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <h3 class="border-y border-slate-100 bg-slate-50/60 px-4 py-3 text-sm font-semibold text-slate-900 dark:border-slate-700 dark:bg-slate-900/40 dark:text-white md:px-5">Rincian pergerakan</h3>
            <table class="w-full text-sm">
                <thead class="{{ $thead }}"><tr><th scope="col" class="px-5 py-3">Waktu / Referensi</th><th scope="col" class="px-5 py-3">Lokasi</th><th scope="col" class="px-5 py-3">Barang</th><th scope="col" class="px-5 py-3">Arah</th><th scope="col" class="px-5 py-3 text-right">Jumlah</th></tr></thead>
                <tbody class="block divide-y divide-slate-100 dark:divide-slate-700 md:table-row-group">
                    @forelse($movements as $movement)
                        @php($location = $movement->toPop ?? $movement->fromPop)
                        <tr class="{{ $tr }}">
                            <td class="{{ $td }} {{ $meta }} order-3">{{ $movement->created_at->format('d/m/Y H:i') }}<div class="hidden font-mono text-xs text-slate-500 md:block">{{ $movement->reference_number ?: 'Barang masuk' }}</div></td>
                            <td class="{{ $td }} {{ $meta }} font-semibold md:font-normal">{{ $location?->name }}</td>
                            <td class="{{ $td }} order-1 min-w-0 flex-1 font-medium text-slate-900 dark:text-white md:font-normal">{{ $movement->item->name }}</td>
                            <td class="{{ $td }} hidden text-slate-700 dark:text-slate-200">{{ $movement->to_pop_id ? 'Masuk' : 'Keluar' }}</td>
                            <td class="{{ $td }} order-1 text-right font-mono font-semibold tabular-nums {{ $movement->to_pop_id ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400' }}">{{ $movement->to_pop_id ? '+' : '-' }}{{ number_format((float) $movement->qty, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr class="block md:table-row"><td colspan="5" class="block px-5 py-10 text-center text-slate-500 md:table-cell">Tidak ada mutasi pada filter ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if($movements->hasPages())
                <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-700 md:px-5">{{ $movements->appends(['tab' => 'report'])->links() }}</div>
            @endif
        </section>
    </div>
</div>
@endsection
