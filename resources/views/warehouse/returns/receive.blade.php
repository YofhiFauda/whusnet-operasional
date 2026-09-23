@extends('layouts.app')

@section('title', 'Terima Retur Modem - Whusnet Operasional')
@section('page_title', 'Terima Retur Modem')

@section('content')

<x-warehouse.header active="custody" title="Terima Retur Modem" subtitle="Konfirmasi penerimaan fisik modem hasil pengambilan alat." backUrl="{{ route('warehouse.returns.index') }}" />

<div class="max-w-2xl bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-6 sm:p-8 shadow-xs">
    <div class="mb-5 pb-4 border-b border-slate-100 dark:border-slate-700/60">
        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $serial->item?->name }}</h3>
        <p class="text-xs font-mono font-bold text-sky-600 dark:text-sky-400">SN: {{ $serial->serial_number }}</p>
    </div>

    <div class="mb-5 p-3.5 rounded-lg bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700/60 text-xs space-y-1.5">
        <div class="flex justify-between"><span class="text-slate-600 dark:text-slate-400 font-medium">Dari pelanggan</span><span class="font-bold text-slate-900 dark:text-slate-100">{{ $serial->customer?->full_name ?? '-' }}</span></div>
        <div class="flex justify-between"><span class="text-slate-600 dark:text-slate-400 font-medium">Dibawa teknisi</span><span class="font-bold text-slate-900 dark:text-slate-100">{{ $serial->currentTechnician?->name ?? '-' }}</span></div>
        <div class="flex justify-between"><span class="text-slate-600 dark:text-slate-400 font-medium">Diterima di gudang</span><span class="font-bold text-slate-900 dark:text-slate-100">{{ $serial->issuedFromPop?->name ?? '-' }}</span></div>
    </div>

    @if(session('error'))
    <div class="mb-4 p-3 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700">{{ session('error') }}</div>
    @endif
    @if($errors->any())
    <div class="mb-4 p-3 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700">
        @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
    </div>
    @endif

    <form action="{{ route('warehouse.returns.receive.store', $serial) }}" method="POST" class="space-y-4">
        @csrf

        <div>
            <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">Kondisi Fisik Setelah Diperiksa <span class="text-rose-500">*</span></label>
            <div class="flex items-center gap-4 p-3 rounded-lg bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700/60">
                <label class="inline-flex items-center gap-2 text-xs font-bold text-slate-700 dark:text-slate-200 cursor-pointer">
                    <input type="radio" name="condition" value="used_good" @checked(old('condition', 'used_good') === 'used_good') class="text-sky-600 focus:ring-sky-500">
                    <span>Bekas — Kondisi Baik</span>
                </label>
                <label class="inline-flex items-center gap-2 text-xs font-bold text-slate-700 dark:text-slate-200 cursor-pointer">
                    <input type="radio" name="condition" value="used_damaged" @checked(old('condition') === 'used_damaged') class="text-sky-600 focus:ring-sky-500">
                    <span>Bekas — Rusak</span>
                </label>
            </div>
        </div>

        <div>
            <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">Koreksi Model Barang (Opsional)</label>
            <select name="item_id" class="w-full text-xs font-semibold px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                <option value="">— Tetap: {{ $serial->item?->name }} —</option>
                @foreach($items as $item)
                <option value="{{ $item->id }}" @selected((string) old('item_id') === (string) $item->id)>{{ $item->name }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Isi kalau modem tercatat sebagai "Modem Pelanggan Lama" atau salah model.</p>
            @if($legacyHint && $legacyHint['label'])
            <p class="mt-1 text-[11px] font-semibold text-amber-600 dark:text-amber-400">
                Data lama pelanggan mencatat perangkat: "{{ $legacyHint['label'] }}"
                @if($legacyHint['item']) — kemungkinan {{ $legacyHint['item']->name }}@endif
                (petunjuk saja, cocokkan dengan fisik).
            </p>
            @endif
        </div>

        <div>
            <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">Nilai Taksiran (Rp) — Opsional</label>
            <input type="text" inputmode="decimal" data-rupiah name="estimated_value" value="{{ old('estimated_value') }}" placeholder="mis. 150.000"
                   class="w-full text-xs font-semibold px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
            <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Modem lama tidak punya harga beli. Kosong tetap bisa diterima, dihitung Rp 0 di Laporan Bulanan.</p>
        </div>

        <div>
            <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">Catatan (Opsional)</label>
            <textarea name="notes" rows="2" maxlength="500" placeholder="Keterangan hasil pemeriksaan..."
                      class="w-full text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">{{ old('notes') }}</textarea>
        </div>

        <div class="pt-4 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
            <a href="{{ route('warehouse.returns.index') }}" class="px-4 py-2.5 text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">Batal</a>
            <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-sky-600 hover:bg-sky-700 text-white rounded-lg text-xs font-bold shadow-xs shadow-sky-600/20 transition-all hover:scale-[1.02] active:scale-[0.98] cursor-pointer">
                <span>Terima ke Gudang</span>
            </button>
        </div>
    </form>
</div>

@endsection
