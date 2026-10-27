@extends('layouts.app')

@section('title', 'Pemeriksaan Barang Selesai Diperbaiki — Whusnet Operasional')
@section('page_title', 'Pemeriksaan Barang Diperbaiki')

@section('content')
<x-warehouse.header active="returns" title="Barang Selesai Diperbaiki" subtitle="Pastikan perangkat telah diperiksa secara fisik sebelum kembali tersedia." backUrl="{{ route('warehouse.damaged.index') }}" />

<div class="max-w-2xl rounded-lg border border-slate-200/80 bg-white p-6 shadow-xs dark:border-slate-700/80 dark:bg-slate-800/90 sm:p-8">
    <div class="mb-5 border-b border-slate-100 pb-4 dark:border-slate-700/60">
        <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ $serial->item->name }}</h2>
        <p class="mt-1 font-mono text-xs font-bold text-sky-600 dark:text-sky-400">SN {{ $serial->serial_number }}</p>
        <p class="mt-2 text-xs text-slate-600 dark:text-slate-300">Lokasi pemeriksaan: {{ $serial->currentPop->name }} · Status: {{ $serial->status->label() }}</p>
    </div>

    @if(session('error'))
        <div role="alert" class="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs font-semibold text-rose-700 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-300">{{ session('error') }}</div>
    @endif

    <p class="mb-5 rounded-lg border border-sky-200 bg-sky-50 p-3 text-xs text-sky-800 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-200">
        Setelah disimpan, barang kembali tersedia. Harga perolehan awal tetap dipakai; pemulihan kerugian dicatat sebagai mutasi baru.
    </p>

    <form method="POST" action="{{ route('warehouse.adjustments.serial.repair.store', $serial) }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        <div>
            <label for="reason" class="mb-1.5 block text-xs font-bold text-slate-700 dark:text-slate-200">Hasil pemeriksaan dan alasan dinyatakan layak <span class="text-rose-500">*</span></label>
            <textarea id="reason" name="reason" rows="3" required maxlength="500" class="w-full rounded-lg border border-slate-200 bg-slate-50/50 px-3 py-2.5 text-xs text-slate-800 focus:border-sky-500 focus:outline-none dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-200">{{ old('reason') }}</textarea>
            @error('reason') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="evidence" class="mb-1.5 block text-xs font-bold text-slate-700 dark:text-slate-200">Foto bukti pemeriksaan <span class="text-rose-500">*</span></label>
            <input id="evidence" type="file" name="evidence" accept="image/*" required class="w-full rounded-lg border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs text-slate-800 file:mr-3 file:rounded-lg file:border-0 file:bg-sky-100 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-sky-700 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-200 dark:file:bg-sky-900/40 dark:file:text-sky-300">
            @error('evidence') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>
        <div class="flex items-center justify-between gap-3 border-t border-slate-100 pt-4 dark:border-slate-700/60">
            <a href="{{ route('warehouse.damaged.index') }}" class="rounded-lg px-4 py-2.5 text-xs font-semibold text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-700">Batal</a>
            <button type="submit" class="rounded-lg bg-sky-600 px-5 py-2.5 text-xs font-bold text-white hover:bg-sky-700">Nyatakan Layak Pakai</button>
        </div>
    </form>
</div>
@endsection
