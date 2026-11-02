@extends('layouts.app')

@section('title', 'Pindahkan Barang Nonoperasional - Whusnet Operasional')
@section('page_title', 'Pindahkan Barang Nonoperasional')

@section('content')
<x-warehouse.header active="stock-requests" title="Pindahkan ke Cabang" subtitle="Stok Pusat berkurang dan stok Cabang bertambah segera setelah disimpan." backUrl="{{ route('warehouse.marketing.index') }}" />

<div class="mx-auto max-w-4xl space-y-6" x-data="{ qty: @js(old('qty', '')) }">
    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-600 dark:text-slate-300">
        <span class="font-semibold text-slate-900 dark:text-white">Barang nonoperasional</span>
        <span aria-hidden="true">→</span>
        <span>Pusat</span>
        <span aria-hidden="true">→</span>
        <span>Cabang</span>
        <span class="text-slate-500 dark:text-slate-400">· Satu langkah, tanpa konfirmasi penerimaan lanjutan</span>
    </div>

    @if(session('error'))
        <div role="alert" class="border-l-4 border-rose-600 py-2 pl-4 text-sm text-rose-700 dark:text-rose-300">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('warehouse.marketing.store') }}" class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
        @csrf
        <input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">

        <section class="border-b border-slate-200 p-5 sm:p-7 dark:border-slate-700">
            <div class="mb-5"><p class="font-mono text-xs font-semibold text-sky-700 dark:text-sky-400">01 / RUTE PERPINDAHAN</p><h2 class="mt-1 text-base font-semibold text-slate-900 dark:text-white">Dari mana, ke mana?</h2></div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div><label for="from_pop_id" class="mb-2 block text-sm font-semibold text-slate-700 dark:text-slate-200">Gudang Pusat asal <span class="text-rose-600">*</span></label><select id="from_pop_id" name="from_pop_id" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-sky-600 focus:outline-none focus:ring-2 focus:ring-sky-600/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white"><option value="">Pilih Pusat</option>@foreach($pusatPops as $pop)<option value="{{ $pop->id }}" @selected(old('from_pop_id') == $pop->id)>{{ $pop->name }}</option>@endforeach</select>@error('from_pop_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label for="cabang_pop_id" class="mb-2 block text-sm font-semibold text-slate-700 dark:text-slate-200">Cabang tujuan <span class="text-rose-600">*</span></label><select id="cabang_pop_id" name="cabang_pop_id" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-sky-600 focus:outline-none focus:ring-2 focus:ring-sky-600/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white"><option value="">Pilih Cabang</option>@foreach($cabangPops as $pop)<option value="{{ $pop->id }}" @selected(old('cabang_pop_id', request('pop_id')) == $pop->id)>{{ $pop->name }}</option>@endforeach</select>@error('cabang_pop_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
            </div>
        </section>

        <section class="border-b border-slate-200 p-5 sm:p-7 dark:border-slate-700">
            <div class="mb-5"><p class="font-mono text-xs font-semibold text-sky-700 dark:text-sky-400">02 / BARANG</p><h2 class="mt-1 text-base font-semibold text-slate-900 dark:text-white">Apa yang dipindahkan?</h2></div>
            <div class="grid gap-5 sm:grid-cols-[minmax(0,1fr)_10rem]">
                <div><label for="item_id" class="mb-2 block text-sm font-semibold text-slate-700 dark:text-slate-200">Barang nonoperasional <span class="text-rose-600">*</span></label><select id="item_id" name="item_id" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-sky-600 focus:outline-none focus:ring-2 focus:ring-sky-600/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white"><option value="">Pilih Barang</option>@foreach($items as $item)<option value="{{ $item->id }}" @selected(old('item_id', request('item_id')) == $item->id)>{{ $item->category->name }} — {{ $item->name }} ({{ $item->code }})</option>@endforeach</select>@error('item_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                <div><label for="qty" class="mb-2 block text-sm font-semibold text-slate-700 dark:text-slate-200">Jumlah <span class="text-rose-600">*</span></label><input id="qty" name="qty" type="number" min="0.01" step="0.01" x-model="qty" value="{{ old('qty') }}" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 font-mono text-sm tabular-nums text-slate-900 focus:border-sky-600 focus:outline-none focus:ring-2 focus:ring-sky-600/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white">@error('qty')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
            </div>
        </section>

        <section class="p-5 sm:p-7">
            <label for="notes" class="mb-2 block text-sm font-semibold text-slate-700 dark:text-slate-200">Catatan <span class="font-normal text-slate-500">(opsional)</span></label>
            <textarea id="notes" name="notes" rows="2" placeholder="Contoh: kebutuhan acara Cabang A" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-sky-600 focus:outline-none focus:ring-2 focus:ring-sky-600/20 dark:border-slate-600 dark:bg-slate-900 dark:text-white">{{ old('notes') }}</textarea>
            @error('notes')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror

            <div class="mt-6 border-y border-slate-200 py-4 dark:border-slate-700" role="note">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Yang terjadi setelah disimpan</p>
                <div class="mt-2 flex flex-wrap gap-x-6 gap-y-2 text-sm text-slate-700 dark:text-slate-200">
                    <span>Stok Pusat <strong class="font-mono tabular-nums text-slate-900 dark:text-white" x-text="'−' + (qty || '0')">−0</strong></span>
                    <span>Stok Cabang <strong class="font-mono tabular-nums text-slate-900 dark:text-white" x-text="'+' + (qty || '0')">+0</strong></span>
                </div>
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Jika stok Pusat tidak cukup, transaksi ditolak dan kedua saldo tetap.</p>
            </div>
            <div class="mt-6 flex flex-wrap items-center justify-between gap-3"><a href="{{ route('warehouse.marketing.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 hover:text-sky-700 dark:text-slate-300">Batal</a><button type="submit" class="rounded-lg bg-sky-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-sky-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600">Simpan &amp; pindahkan stok</button></div>
        </section>
    </form>
</div>
@endsection
