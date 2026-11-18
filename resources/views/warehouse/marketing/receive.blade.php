@extends('layouts.app')

@section('title', 'Barang Masuk Pusat - Whusnet Operasional')
@section('page_title', 'Barang Masuk Pusat')

@php
    $field = 'w-full min-h-[44px] rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-sky-600 focus:outline-none focus:ring-2 focus:ring-sky-600/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white';
    $label = 'mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-200';
@endphp

@section('content')
<x-warehouse.header active="stock-requests" title="Barang Masuk Pusat" subtitle="Catat barang nonoperasional yang tiba di Gudang Pusat." backUrl="{{ route('warehouse.marketing.index') }}" />

<div class="mx-auto max-w-3xl space-y-4" x-data="{ qty: @js(old('qty', '')) }">
    @if(session('error'))
        <div role="alert" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm text-rose-700 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-300">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('warehouse.marketing.receive.store') }}" class="space-y-5 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800 sm:p-6">
        @csrf

        <div>
            <label for="pusat_pop_id" class="{{ $label }}">Gudang Pusat <span class="text-rose-600">*</span></label>
            <select id="pusat_pop_id" name="pusat_pop_id" required class="{{ $field }}"><option value="">Pilih Pusat</option>@foreach($pusatPops as $pop)<option value="{{ $pop->id }}" @selected(old('pusat_pop_id') == $pop->id)>{{ $pop->name }}</option>@endforeach</select>
            @error('pusat_pop_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>

        <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_10rem]">
            <div>
                <label for="item_id" class="{{ $label }}">Barang <span class="text-rose-600">*</span></label>
                <select id="item_id" name="item_id" required class="{{ $field }}"><option value="">Pilih Barang</option>@foreach($items as $item)<option value="{{ $item->id }}" @selected(old('item_id') == $item->id)>{{ $item->category->name }} — {{ $item->name }} ({{ $item->code }})</option>@endforeach</select>
                @error('item_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="qty" class="{{ $label }}">Jumlah diterima <span class="text-rose-600">*</span></label>
                <input id="qty" name="qty" type="number" inputmode="decimal" min="0.01" step="0.01" x-model="qty" value="{{ old('qty') }}" required class="{{ $field }} font-mono tabular-nums">
                @error('qty')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <label for="notes" class="{{ $label }}">Catatan <span class="font-normal text-slate-500">(opsional)</span></label>
            <textarea id="notes" name="notes" rows="2" placeholder="Contoh: pengadaan merchandise acara Oktober" class="{{ $field }}">{{ old('notes') }}</textarea>
            @error('notes')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>

        <div role="note" class="flex flex-wrap items-center gap-x-4 gap-y-1 rounded-lg bg-slate-50 px-3 py-2.5 text-sm dark:bg-slate-900/60">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Setelah disimpan:</span>
            <span class="text-slate-700 dark:text-slate-200">Stok Pusat <strong class="font-mono tabular-nums text-emerald-700 dark:text-emerald-400" x-text="'+' + (qty || '0')">+0</strong></span>
            <span class="text-slate-500 dark:text-slate-400">Stok Cabang belum berubah</span>
        </div>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 dark:border-slate-700 sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ route('warehouse.marketing.index') }}" class="inline-flex min-h-[44px] items-center justify-center rounded-lg px-3 text-sm font-semibold text-slate-600 hover:text-sky-700 dark:text-slate-300">Batal</a>
            <button type="submit" class="min-h-[44px] rounded-lg bg-sky-600 px-5 text-sm font-semibold text-white transition hover:bg-sky-700">Simpan barang masuk</button>
        </div>
    </form>
</div>
@endsection
