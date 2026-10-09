@extends('layouts.app')

@section('title', 'Konfirmasi Terima Pusat — Whusnet Operasional')
@section('page_title', 'Konfirmasi Terima Pusat')

@section('content')

<x-warehouse.header active="returns" title="Konfirmasi Terima di Gudang Pusat" subtitle="Kondisi FINAL ditentukan di sini — gate pemeriksaan dilepas dan stok Pusat bertambah setelah disimpan." backUrl="{{ route('warehouse.returns.pusat.index') }}" />

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
    <div class="lg:col-span-5 space-y-5">
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-5 sm:p-7 shadow-xs space-y-4">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-bold uppercase tracking-wider text-sky-600 dark:text-sky-400">Identitas Modem</span>
                <x-warehouse.origin-badge :log="$serial->latestRetrievalLog" />
            </div>
            <p class="font-mono font-extrabold text-lg text-slate-900 dark:text-white">{{ $serial->serial_number }}</p>
            <p class="text-xs text-slate-600 dark:text-slate-300">Model saat ini: <span class="font-bold text-slate-800 dark:text-slate-100">{{ $serial->item?->name ?? 'Belum ditentukan' }}</span></p>
            <p class="text-xs text-slate-600 dark:text-slate-300">Kondisi observasi Cabang: <x-warehouse.condition-badge :condition="$serial->condition" :checked="false" /></p>

            @if($serial->latestRetrievalLog)
            <div class="pt-3 border-t border-slate-100 dark:border-slate-700/60 text-xs text-slate-600 dark:text-slate-400 space-y-1">
                <p>Pelanggan asal: <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $serial->latestRetrievalLog->customer?->full_name ?? '-' }}</span></p>
                @if($serial->latestRetrievalLog->notes)
                <p class="italic">"{{ $serial->latestRetrievalLog->notes }}"</p>
                @endif
            </div>
            @endif
        </div>
    </div>

    <div class="lg:col-span-7 space-y-5 lg:sticky lg:top-6">
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-5 sm:p-7 shadow-xs space-y-5">
            <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100">Konfirmasi Terima Pusat</h2>

            @if(session('error'))
            <div class="p-3.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-xs text-rose-700 dark:text-rose-300">
                {{ session('error') }}
            </div>
            @endif

            @if($errors->any())
            <div class="p-3.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-xs text-rose-700 dark:text-rose-300 space-y-1">
                @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
                @endforeach
            </div>
            @endif

            <form action="{{ route('warehouse.returns.pusat.store', $serial) }}" method="POST" class="space-y-4">
                @csrf

                <div>
                    <label class="block mb-2 text-xs font-bold text-slate-700 dark:text-slate-200">Kondisi Fisik FINAL <span class="text-rose-500">*</span></label>
                    <div class="space-y-2">
                        <label class="flex items-start gap-3 p-3.5 rounded-xl border border-slate-200 dark:border-slate-700 cursor-pointer">
                            <input type="radio" name="condition" value="used_good" checked>
                            <span class="text-xs font-bold text-slate-900 dark:text-slate-100">Bekas — Kondisi Baik (Ready)</span>
                        </label>
                        <label class="flex items-start gap-3 p-3.5 rounded-xl border border-slate-200 dark:border-slate-700 cursor-pointer">
                            <input type="radio" name="condition" value="used_damaged">
                            <span class="text-xs font-bold text-slate-900 dark:text-slate-100">Bekas — Rusak / Afkir</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">Koreksi Model Master (Opsional)</label>
                    <select name="item_id" class="w-full text-xs font-semibold px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200">
                        <option value="">— Tetap: {{ $serial->item?->name ?? 'Pilih Model' }} —</option>
                        @foreach($items as $item)
                        <option value="{{ $item->id }}">{{ $item->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">Catatan Penerimaan Pusat (Opsional)</label>
                    <textarea name="notes" rows="3" maxlength="500" class="w-full text-xs px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200"></textarea>
                </div>

                <div class="pt-4 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between gap-3">
                    <a href="{{ route('warehouse.returns.pusat.index') }}" class="px-4 py-2.5 text-xs font-bold text-slate-500 hover:text-slate-800">Batal</a>
                    <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-sky-600 hover:bg-sky-700 text-white rounded-xl text-xs font-bold shadow-xs shadow-sky-600/25">
                        Konfirmasi Terima Pusat
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
