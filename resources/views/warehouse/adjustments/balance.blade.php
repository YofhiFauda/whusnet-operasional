@extends('layouts.app')

@section('title', 'Penyesuaian Stok Gudang - Whusnet Operasional')
@section('page_title', 'Penyesuaian Stok')

@section('content')

<x-warehouse.header active="stock" title="Penyesuaian Stok (Stock Adjustment / Opname)" subtitle="Koreksi stok fisik gudang akibat selisih opname, kerusakan material, atau penyusutan." />

<div class="max-w-2xl bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-6 sm:p-8 shadow-xs">
    <div class="mb-6 pb-4 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 flex items-center justify-center border border-amber-100 dark:border-amber-800/60">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <div>
                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">Form Koreksi Stok Fisik</h3>
                <p class="text-xs text-slate-400">Nilai negatif (-) untuk susut/rusak, nilai positif (+) untuk temuan opname.</p>
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

    <form action="{{ route('warehouse.adjustments.balance.store') }}" method="POST" class="space-y-4">
        @csrf

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">Gudang POP <span class="text-rose-500">*</span></label>
                <select name="pop_id" required class="w-full text-xs font-semibold px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                    <option value="">— Pilih Gudang —</option>
                    @foreach($pops as $pop)
                    <option value="{{ $pop->id }}" {{ old('pop_id', request('pop_id')) == $pop->id ? 'selected' : '' }}>{{ $pop->name }} ({{ strtoupper($pop->type) }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">Barang <span class="text-rose-500">*</span></label>
                <select name="item_id" required class="w-full text-xs font-semibold px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
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
                       class="w-full text-xs font-mono px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
            </div>
            <div>
                <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">Jumlah Perubahan (+/-) <span class="text-rose-500">*</span></label>
                <input type="number" step="0.01" name="qty_delta" required placeholder="mis. -5 atau +10"
                       class="w-full text-xs font-mono font-bold px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
            </div>
        </div>

        <div>
            <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">Alasan Penyesuaian <span class="text-rose-500">*</span></label>
            <input type="text" name="reason" required maxlength="255" list="reason-suggestions" placeholder="Pilih atau ketik alasan (mis. selisih_opname, rusak_terjatuh)..."
                   class="w-full text-xs font-medium px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
            <datalist id="reason-suggestions">
                <option value="selisih_opname">
                <option value="rusak_terjatuh">
                <option value="hilang_transit">
                <option value="rusak_kelembaban">
            </datalist>
        </div>

        <div>
            <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">Catatan Tambahan (Opsional)</label>
            <textarea name="notes" rows="2" placeholder="Keterangan detail hasil pemeriksaan opname..."
                      class="w-full text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all"></textarea>
        </div>

        <div class="pt-4 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
            <a href="{{ route('warehouse.stock.index') }}" class="px-4 py-2.5 text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                Batal
            </a>
            <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold shadow-xs shadow-amber-600/20 transition-all hover:scale-[1.02] active:scale-[0.98] cursor-pointer">
                <span>Simpan Penyesuaian Stok</span>
            </button>
        </div>
    </form>
</div>

@endsection
