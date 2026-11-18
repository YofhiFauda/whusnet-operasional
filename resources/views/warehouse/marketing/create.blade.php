@extends('layouts.app')

@section('title', 'Pindahkan Barang Nonoperasional - Whusnet Operasional')
@section('page_title', 'Pindahkan Barang Nonoperasional')

@php
    $field = 'w-full min-h-[44px] rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-sky-600 focus:outline-none focus:ring-2 focus:ring-sky-600/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white';
    $label = 'mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-200';
@endphp

@section('content')
<x-warehouse.header active="stock-requests" title="Pindahkan ke Cabang" subtitle="Stok Pusat berkurang, stok Cabang bertambah saat disimpan." backUrl="{{ route('warehouse.marketing.index') }}" />

<div class="mx-auto max-w-3xl space-y-4" x-data="{ qty: @js(old('qty', '')) }">
    @if(session('error'))
        <div role="alert" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm text-rose-700 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-300">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('warehouse.marketing.store') }}" class="space-y-5 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800 sm:p-6">
        @csrf
        <input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">

        <div class="grid items-end gap-4 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)]">
            <div>
                <label for="from_pop_id" class="{{ $label }}">Dari Pusat <span class="text-rose-600">*</span></label>
                <select id="from_pop_id" name="from_pop_id" required class="{{ $field }}"><option value="">Pilih Pusat</option>@foreach($pusatPops as $pop)<option value="{{ $pop->id }}" @selected(old('from_pop_id') == $pop->id)>{{ $pop->name }}</option>@endforeach</select>
                @error('from_pop_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
            <svg class="mx-auto hidden h-5 w-5 text-slate-400 sm:mb-3 sm:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
            <div>
                <label for="cabang_pop_id" class="{{ $label }}">Ke Cabang <span class="text-rose-600">*</span></label>
                <select id="cabang_pop_id" name="cabang_pop_id" required class="{{ $field }}"><option value="">Pilih Cabang</option>@foreach($cabangPops as $pop)<option value="{{ $pop->id }}" @selected(old('cabang_pop_id', request('pop_id')) == $pop->id)>{{ $pop->name }}</option>@endforeach</select>
                @error('cabang_pop_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_10rem]">
            <div>
                <label for="item_id" class="{{ $label }}">Barang <span class="text-rose-600">*</span></label>
                <select id="item_id" name="item_id" required class="{{ $field }}"><option value="">Pilih Barang</option>@foreach($items as $item)<option value="{{ $item->id }}" @selected(old('item_id', request('item_id')) == $item->id)>{{ $item->category->name }} — {{ $item->name }} ({{ $item->code }})</option>@endforeach</select>
                @error('item_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="qty" class="{{ $label }}">Jumlah <span class="text-rose-600">*</span></label>
                <input id="qty" name="qty" type="number" inputmode="decimal" min="0.01" step="0.01" x-model="qty" value="{{ old('qty') }}" required class="{{ $field }} font-mono tabular-nums">
                @error('qty')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <label for="notes" class="{{ $label }}">Catatan <span class="font-normal text-slate-500">(opsional)</span></label>
            <textarea id="notes" name="notes" rows="2" placeholder="Contoh: kebutuhan acara Cabang A" class="{{ $field }}">{{ old('notes') }}</textarea>
            @error('notes')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>

        {{-- Dampak simpan: satu baris, bukan kotak penjelasan. Stok Pusat
             kurang → transaksi ditolak di server, saldo tetap. --}}
        <div role="note" class="flex flex-wrap items-center gap-x-4 gap-y-1 rounded-lg bg-slate-50 px-3 py-2.5 text-sm dark:bg-slate-900/60">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Setelah disimpan:</span>
            <span class="text-slate-700 dark:text-slate-200">Stok Pusat <strong class="font-mono tabular-nums text-rose-700 dark:text-rose-400" x-text="'−' + (qty || '0')">−0</strong></span>
            <span class="text-slate-700 dark:text-slate-200">Stok Cabang <strong class="font-mono tabular-nums text-emerald-700 dark:text-emerald-400" x-text="'+' + (qty || '0')">+0</strong></span>
        </div>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 dark:border-slate-700 sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ route('warehouse.marketing.index') }}" class="inline-flex min-h-[44px] items-center justify-center rounded-lg px-3 text-sm font-semibold text-slate-600 hover:text-sky-700 dark:text-slate-300">Batal</a>
            <button type="submit" class="min-h-[44px] rounded-lg bg-sky-600 px-5 text-sm font-semibold text-white transition hover:bg-sky-700">Simpan &amp; pindahkan stok</button>
        </div>
    </form>
</div>
@endsection
