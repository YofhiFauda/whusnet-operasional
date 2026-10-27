@extends('layouts.app')

@section('title', 'Buat Jual Beli SN')
@section('page_title', 'Buat Jual Beli SN')

@section('content')
<x-warehouse.header active="branch-sales" title="Buat Jual Beli SN" subtitle="Pilih Cabang penjual, lalu beri harga jual pada tiap unit ber-SN yang dikirim." />
<div class="mx-auto max-w-5xl space-y-5">
    <a href="{{ route('warehouse.branch-sales.index') }}" class="text-sm font-semibold text-sky-700 hover:underline">← Daftar jual beli</a>
    @if(session('error'))<div role="alert" class="rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif
    @if($errors->any())<div role="alert" class="rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ $errors->first() }}</div>@endif
    <form method="GET" action="{{ route('warehouse.branch-sales.create') }}" class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
        <label for="from_pop_id" class="mb-2 block text-sm font-semibold">Cabang penjual</label>
        <div class="flex gap-3">
            <select id="from_pop_id" name="from_pop_id" class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white p-2 dark:border-slate-700 dark:bg-slate-800">
                <option value="">Pilih Cabang</option>
                @foreach($sourcePops as $pop)<option value="{{ $pop->id }}" @selected($source?->id === $pop->id)>{{ $pop->name }}</option>@endforeach
            </select>
            <input type="search" name="q" value="{{ $search }}" placeholder="Cari nomor SN" aria-label="Cari nomor SN" class="min-w-0 w-44 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
            <button class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Tampilkan SN</button>
        </div>
    </form>
    @if($source)
    <form method="POST" action="{{ route('warehouse.branch-sales.store') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="from_pop_id" value="{{ $source->id }}">
        <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <label for="to_pop_id" class="mb-2 block text-sm font-semibold">Cabang pembeli</label>
            <select id="to_pop_id" name="to_pop_id" required class="w-full rounded-lg border border-slate-300 bg-white p-2 dark:border-slate-700 dark:bg-slate-800">
                <option value="">Pilih Cabang tujuan</option>
                @foreach($destinationPops as $pop)
                    @if($pop->id !== $source->id)<option value="{{ $pop->id }}" @selected(old('to_pop_id') == $pop->id)>{{ $pop->name }}</option>@endif
                @endforeach
            </select>
        </div>
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500 dark:bg-slate-800"><tr><th class="p-3">Pilih</th><th class="p-3">Barang</th><th class="p-3">Nomor SN</th><th class="p-3">Harga jual / SN</th></tr></thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($serials as $serial)
                    <tr>
                        <td class="p-3"><input type="checkbox" name="lines[{{ $serial->id }}][serial_id]" value="{{ $serial->id }}" @checked(old('lines.'.$serial->id.'.serial_id')) aria-label="Pilih {{ $serial->serial_number }}"></td>
                        <td class="p-3">{{ $serial->item->name }}</td>
                        <td class="p-3 font-mono">{{ $serial->serial_number }}</td>
                        <td class="p-3"><input type="number" name="lines[{{ $serial->id }}][sale_price]" value="{{ old('lines.'.$serial->id.'.sale_price') }}" min="0.01" step="0.01" placeholder="Rp" class="w-40 rounded-lg border border-slate-300 bg-white p-2 dark:border-slate-700 dark:bg-slate-800"></td>
                    </tr>
                    @empty<tr><td colspan="4" class="p-8 text-center text-slate-500">Tidak ada SN tersedia di Cabang ini.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
        <p class="text-xs text-slate-500">Daftar menampilkan 100 SN pertama. Harga hanya wajib untuk SN yang dicentang.</p>
        <button class="rounded-lg bg-sky-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-sky-800">Kirim dan buat dokumen</button>
    </form>
    @endif
</div>
@endsection
