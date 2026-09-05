@extends('layouts.app')

@section('title', 'Atur Ambang Stok Rendah - Whusnet Operasional')
@section('page_title', 'Ambang Stok Rendah')

@section('content')

<x-warehouse.header active="stock" title="Atur Ambang Stok Rendah" subtitle="Isi angka minimum — begitu stok gudang turun di bawahnya, badge 'Stok Rendah' otomatis nyala di Kelola Stok & Dashboard." />

<div class="max-w-2xl bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-6 sm:p-8 shadow-xs">
    <div class="mb-6 pb-4 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-rose-50 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400 flex items-center justify-center border border-rose-100 dark:border-rose-800/60">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">Form Ambang Minimum</h3>
                <p class="text-xs text-slate-400">Tanpa ini, badge "Stok Rendah" gak pernah nyala — bukan berarti stok aman, cuma belum ada patokan buat dibandingkan.</p>
            </div>
        </div>
        <a href="{{ route('warehouse.stock.index') }}" class="text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
            ← Kembali
        </a>
    </div>

    @if($currentBalance)
    @php $currentItem = $items->firstWhere('id', (int) request('item_id')); @endphp
    <div class="mb-5 p-3.5 rounded-xl bg-sky-50 dark:bg-sky-950/40 border border-sky-200 dark:border-sky-800/60 flex items-center justify-between text-xs">
        <span class="text-sky-800 dark:text-sky-300 font-medium">Stok saat ini:</span>
        <span class="font-mono font-bold text-sky-700 dark:text-sky-200 text-sm">
            {{ rtrim(rtrim(number_format((float) $currentBalance->qty, 2, ',', '.'), '0'), ',') }} {{ $currentItem->unit ?? '' }}
            @if($currentBalance->minimum_stock !== null)
                <span class="text-slate-400 font-normal">(minimum sekarang: {{ rtrim(rtrim(number_format((float) $currentBalance->minimum_stock, 2, ',', '.'), '0'), ',') }})</span>
            @endif
        </span>
    </div>
    @endif

    <form action="{{ route('warehouse.stock.threshold.store') }}" method="POST" class="space-y-4">
        @csrf

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">Gudang POP <span class="text-rose-500">*</span></label>
                <select name="pop_id" required class="w-full text-xs font-semibold px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition-all">
                    <option value="">— Pilih Gudang —</option>
                    @foreach($pops as $pop)
                    <option value="{{ $pop->id }}" {{ old('pop_id', request('pop_id')) == $pop->id ? 'selected' : '' }}>{{ $pop->name }} ({{ strtoupper($pop->type) }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">Barang <span class="text-rose-500">*</span></label>
                <select name="item_id" required class="w-full text-xs font-semibold px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition-all">
                    <option value="">— Pilih Barang —</option>
                    @foreach($items as $item)
                    <option value="{{ $item->id }}" {{ old('item_id', request('item_id')) == $item->id ? 'selected' : '' }}>{{ $item->code }} — {{ $item->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">No. Lot / Drum (Jika Batch)</label>
                <input type="text" name="lot_no" value="{{ old('lot_no', request('lot_no')) }}" placeholder="mis. LOT-2026-001"
                       class="w-full text-xs font-mono px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition-all">
            </div>
            <div>
                <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">Minimum</label>
                <input type="number" step="0.01" min="0" name="minimum_stock" value="{{ old('minimum_stock', $currentBalance->minimum_stock ?? '') }}" placeholder="mis. 50"
                       class="w-full text-xs font-mono px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition-all">
            </div>
            <div>
                <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">Maximum (Opsional)</label>
                <input type="number" step="0.01" min="0" name="maximum_stock" value="{{ old('maximum_stock', $currentBalance->maximum_stock ?? '') }}" placeholder="mis. 500"
                       class="w-full text-xs font-mono px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition-all">
            </div>
        </div>

        <div class="pt-4 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
            <a href="{{ route('warehouse.stock.index') }}" class="px-4 py-2.5 text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                Batal
            </a>
            <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold shadow-xs shadow-rose-600/20 transition-all hover:scale-[1.02] active:scale-[0.98] cursor-pointer">
                <span>Simpan Ambang</span>
            </button>
        </div>
    </form>
</div>

@endsection
