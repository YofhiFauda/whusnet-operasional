@extends('layouts.app')

@section('title', 'Stock Opname - Whusnet Operasional')
@section('page_title', 'Stock Opname')

@section('content')

<x-warehouse.header active="stock" title="Stock Opname" subtitle="Catat hasil hitung fisik barang di gudang — sistem hitung sendiri selisihnya, hasil PAS (gak ada selisih) tetap wajib disimpan sebagai bukti opname sudah dilakukan." />

<div class="max-w-2xl bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-6 sm:p-8 shadow-xs">
    <div class="mb-6 pb-4 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center border border-indigo-100 dark:border-indigo-800/60">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
            </div>
            <div>
                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">Form Hasil Hitung Fisik</h3>
                <p class="text-xs text-slate-400">Isi jumlah barang yang BENERAN dihitung sekarang, bukan selisihnya — sistem yang hitung selisih otomatis.</p>
            </div>
        </div>
        <a href="{{ route('warehouse.stock.index') }}" class="text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
            ← Kembali
        </a>
    </div>

    @if($currentBalance)
    @php $currentItem = $items->firstWhere('id', (int) request('item_id')); @endphp
    <div class="mb-5 p-3.5 rounded-xl bg-sky-50 dark:bg-sky-950/40 border border-sky-200 dark:border-sky-800/60 flex items-center justify-between text-xs">
        <span class="text-sky-800 dark:text-sky-300 font-medium">Stok saat ini di sistem:</span>
        <span class="font-mono font-bold text-sky-700 dark:text-sky-200 text-sm">
            {{ rtrim(rtrim(number_format((float) $currentBalance->qty, 2, ',', '.'), '0'), ',') }} {{ $currentItem->unit ?? '' }}
        </span>
    </div>
    @endif

    <form action="{{ route('warehouse.adjustments.opname.store') }}" method="POST" class="space-y-4">
        @csrf

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">Gudang POP <span class="text-rose-500">*</span></label>
                <select name="pop_id" required class="w-full text-xs font-semibold px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
                    <option value="">— Pilih Gudang —</option>
                    @foreach($pops as $pop)
                    <option value="{{ $pop->id }}" {{ old('pop_id', request('pop_id')) == $pop->id ? 'selected' : '' }}>{{ $pop->name }} ({{ strtoupper($pop->type) }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">Barang <span class="text-rose-500">*</span></label>
                <select name="item_id" required class="w-full text-xs font-semibold px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
                    <option value="">— Pilih Barang —</option>
                    @foreach($items as $item)
                    <option value="{{ $item->id }}" {{ old('item_id', request('item_id')) == $item->id ? 'selected' : '' }}>{{ $item->code }} — {{ $item->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">No. Lot / Drum (Jika Batch)</label>
                <input type="text" name="lot_no" value="{{ old('lot_no', request('lot_no')) }}" placeholder="mis. LOT-2026-001"
                       class="w-full text-xs font-mono px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
            </div>
            <div>
                <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">Jumlah Fisik Dihitung <span class="text-rose-500">*</span></label>
                <input type="number" step="0.01" min="0" name="counted_qty" required placeholder="mis. 95"
                       class="w-full text-xs font-mono font-bold px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
            </div>
        </div>

        <div>
            <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">Catatan Tambahan (Opsional)</label>
            <textarea name="notes" rows="2" placeholder="Keterangan tambahan hasil hitung..."
                      class="w-full text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"></textarea>
        </div>

        <div class="pt-4 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
            <a href="{{ route('warehouse.stock.index') }}" class="px-4 py-2.5 text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                Batal
            </a>
            <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-xs shadow-indigo-600/20 transition-all hover:scale-[1.02] active:scale-[0.98] cursor-pointer">
                <span>Simpan Hasil Opname</span>
            </button>
        </div>
    </form>
</div>

@endsection
