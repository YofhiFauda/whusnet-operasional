@extends('layouts.app')

@section('title', 'Ajukan Permintaan Stok - Whusnet Operasional')
@section('page_title', 'Ajukan Permintaan Stok')

@section('content')

<x-warehouse.header active="stock-requests" title="Ajukan Permintaan Stok" subtitle="Beritahu Gudang Pusat barang apa yang mau habis di cabang Anda — jangan nunggu sampai bener-bener kosong." backUrl="{{ route('warehouse.stock-requests.index') }}" />

@php
    // Kategori → Barang cascading (2026-09-07) — SAMAIN pola sama
    // `<x-inventory-line-rows>` yang dipakai Barang Masuk/Transfer/Issue,
    // biar staf gak bingung 1 halaman ada cascade 1 halaman enggak. TIDAK
    // reuse komponennya langsung: itu punya mode capture Serial Number
    // (nama beneran satuan barang yang lagi bergerak), sedangkan Permintaan
    // Stok minta by QTY doang — cabang belum tau SN mana yang bakal dikirim
    // Pusat, jadi mode SN gak relevan di sini.
    $itemOptions = collect($items)->map(fn ($item) => [
        'id' => $item->id,
        'label' => "{$item->code} — {$item->name} ({$item->unit})",
        'category_id' => $item->item_category_id,
    ])->values();

    $categoryOptions = collect($items)
        ->pluck('category')
        ->filter()
        ->unique('id')
        ->sortBy('name')
        ->map(fn ($category) => ['id' => $category->id, 'name' => $category->name])
        ->values();
@endphp

<div class="max-w-3xl bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-6 sm:p-8 shadow-xs">
    <form action="{{ route('warehouse.stock-requests.store') }}" method="POST" class="space-y-5" x-data="stockRequestForm(@js($itemOptions), @js($categoryOptions))">
        @csrf

        <div>
            <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">Gudang Cabang <span class="text-rose-500">*</span></label>
            <select name="cabang_pop_id" required class="w-full text-xs font-semibold px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                <option value="">— Pilih Cabang —</option>
                @foreach($cabangPops as $pop)
                <option value="{{ $pop->id }}" {{ old('cabang_pop_id', request()->query('pop_id')) == $pop->id ? 'selected' : '' }}>{{ $pop->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <div class="flex items-center justify-between mb-1.5">
                <label class="text-xs font-bold text-slate-700 dark:text-slate-200">Barang yang Diminta <span class="text-rose-500">*</span></label>
            </div>

            <template x-for="(row, index) in rows" :key="index">
                <div class="bg-slate-50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-700/50 rounded-lg p-3 mb-2.5 flex flex-wrap items-end gap-2">
                    <div class="w-full sm:w-40">
                        <label class="block mb-1 text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Kategori</label>
                        <select x-model="row.category_id" @change="row.item_id = ''"
                                class="w-full text-xs px-2 py-1.5 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500">
                            <option value="">— semua kategori —</option>
                            <template x-for="cat in categoryOptions" :key="cat.id">
                                <option :value="cat.id" x-text="cat.name"></option>
                            </template>
                        </select>
                    </div>
                    <div class="flex-1 min-w-[12rem]">
                        <label class="block mb-1 text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Barang</label>
                        <select :name="`lines[${index}][item_id]`" x-model="row.item_id" required
                                class="w-full text-xs px-2 py-1.5 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500">
                            <option value="">— pilih barang —</option>
                            <template x-for="opt in filteredItemOptions(row)" :key="opt.id">
                                <option :value="opt.id" x-text="opt.label"></option>
                            </template>
                        </select>
                    </div>
                    <div class="w-28">
                        <label class="block mb-1 text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Qty Diminta</label>
                        <input type="number" step="0.01" min="0.01" :name="`lines[${index}][qty_requested]`" x-model="row.qty_requested" required
                               class="w-full text-xs font-mono px-2 py-1.5 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500">
                    </div>
                    <div class="w-32">
                        <label class="block mb-1 text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Lot (Opsional)</label>
                        <input type="text" :name="`lines[${index}][lot_no]`" x-model="row.lot_no" placeholder="kalau BATCH"
                               class="w-full text-xs font-mono px-2 py-1.5 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500">
                    </div>
                    <button type="button" @click="rows.splice(index, 1)" class="text-red-500 hover:text-red-700 p-1.5 rounded hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors shrink-0" title="Hapus baris">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/></svg>
                    </button>
                </div>
            </template>

            <button type="button" @click="rows.push({category_id: '', item_id: '', qty_requested: '', lot_no: ''})" class="inline-flex items-center gap-1.5 text-xs font-semibold text-sky-700 dark:text-sky-300 bg-sky-50 dark:bg-sky-950/40 border border-sky-200 dark:border-sky-800 hover:bg-sky-100 dark:hover:bg-sky-900/50 px-3 py-1.5 rounded-md transition-colors">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Tambah Barang
            </button>
        </div>

        <div>
            <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">Catatan (Opsional)</label>
            <textarea name="notes" rows="2" placeholder="mis. buat pemasangan minggu ini, ada 20 unit terjadwal..."
                      class="w-full text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">{{ old('notes') }}</textarea>
        </div>

        <div class="pt-4 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
            <a href="{{ route('warehouse.stock-requests.index') }}" class="px-4 py-2.5 text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                Batal
            </a>
            <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-sky-600 hover:bg-sky-700 text-white rounded-lg text-xs font-bold shadow-xs shadow-sky-600/20 transition-all hover:scale-[1.02] active:scale-[0.98] cursor-pointer">
                <span>Ajukan Permintaan</span>
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
function stockRequestForm(itemOptions, categoryOptions) {
    // Prefill dari Kelola Stok ("Minta Stok" per baris, 2026-09-07) — baca
    // langsung query string di klien, gak nambah param baru ke
    // WarehouseStockRequestController::create() (tetap tipis, cuma render).
    const params = new URLSearchParams(window.location.search);
    const itemId = params.get('item_id') || '';
    const lotNo = params.get('lot_no') || '';
    const prefillCategoryId = itemId
        ? (itemOptions.find(o => String(o.id) === String(itemId))?.category_id ?? '')
        : '';

    return {
        itemOptions: itemOptions,
        categoryOptions: categoryOptions,
        rows: [{ category_id: prefillCategoryId, item_id: itemId, qty_requested: '', lot_no: lotNo }],

        // Kategori → Barang cascading (sama pola `inventoryLineRows()` di
        // komponen x-inventory-line-rows) — belum pilih kategori = tampil semua
        // barang, biar staf yang udah hafal nama barangnya gak wajib pilih
        // kategori dulu.
        filteredItemOptions(row) {
            if (! row.category_id) {
                return this.itemOptions;
            }

            return this.itemOptions.filter(o => String(o.category_id) === String(row.category_id));
        },
    };
}
</script>
@endpush

@endsection
