@extends('layouts.app')

@section('title', 'Opname SN / Roll — Whusnet Operasional')
@section('page_title', 'Opname SN / Roll')

@section('content')
<x-warehouse.header active="stock" title="Opname SN / Roll" subtitle="Catat pemeriksaan fisik per unit; hasil cocok tetap mempunyai jejak, selisih dicatat sebagai koreksi." backUrl="{{ route('warehouse.adjustments.opname.create') }}" />

<div class="max-w-2xl rounded-lg border border-slate-200 bg-white p-6 shadow-xs dark:border-slate-700 dark:bg-slate-800/90 sm:p-8">
    @if(session('error'))
        <div role="alert" class="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs font-semibold text-rose-700 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-300">{{ session('error') }}</div>
    @endif
    <form method="POST" action="{{ route('warehouse.adjustments.unit-opname.store') }}" enctype="multipart/form-data" class="space-y-4" x-data="{ type: '{{ old('tracking_type', 'serialized') }}' }">
        @csrf
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="pop_id" class="mb-1.5 block text-xs font-bold text-slate-700 dark:text-slate-200">Gudang <span class="text-rose-500">*</span></label>
                <select id="pop_id" name="pop_id" required class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                    <option value="">Pilih Gudang</option>
                    @foreach($pops as $pop)
                    <option value="{{ $pop->id }}" @selected(old('pop_id') == $pop->id)>{{ $pop->name }}</option>
                    @endforeach
                </select>
                @error('pop_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="tracking_type" class="mb-1.5 block text-xs font-bold text-slate-700 dark:text-slate-200">Jenis unit <span class="text-rose-500">*</span></label>
                <select id="tracking_type" name="tracking_type" x-model="type" required class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                    <option value="serialized">Barang ber-SN</option>
                    <option value="roll">Roll kabel</option>
                </select>
            </div>
        </div>
        <div>
            <label for="code" class="mb-1.5 block text-xs font-bold text-slate-700 dark:text-slate-200">Nomor SN atau kode roll <span class="text-rose-500">*</span></label>
            <input id="code" name="code" type="text" value="{{ old('code') }}" required maxlength="100" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 font-mono text-xs dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
            @error('code') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>
        <div x-show="type === 'serialized'">
            <label for="is_present" class="mb-1.5 block text-xs font-bold text-slate-700 dark:text-slate-200">Hasil hitung SN</label>
            <select id="is_present" name="is_present" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                <option value="1" @selected(old('is_present', '1') === '1')>Ditemukan sesuai stok</option>
                <option value="0" @selected(old('is_present') === '0')>Tidak ditemukan — catat hilang</option>
            </select>
        </div>
        <div x-show="type === 'roll'" x-cloak>
            <label for="counted_length" class="mb-1.5 block text-xs font-bold text-slate-700 dark:text-slate-200">Sisa fisik roll (meter)</label>
            <input id="counted_length" name="counted_length" type="number" step="0.01" min="0" value="{{ old('counted_length') }}" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Isi 0 bila roll tidak ditemukan. Selisih meter dicatat pada ledger.</p>
        </div>
        <div>
            <label for="reason" class="mb-1.5 block text-xs font-bold text-slate-700 dark:text-slate-200">Catatan pemeriksaan <span class="text-rose-500">*</span></label>
            <textarea id="reason" name="reason" rows="2" required maxlength="500" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">{{ old('reason') }}</textarea>
        </div>
        <div>
            <label for="evidence" class="mb-1.5 block text-xs font-bold text-slate-700 dark:text-slate-200">Bukti foto pemeriksaan <span class="text-rose-500">*</span></label>
            <input id="evidence" name="evidence" type="file" accept="image/*" required class="w-full text-xs text-slate-700 dark:text-slate-200">
            @error('evidence') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>
        <div class="flex items-center justify-between gap-3 border-t border-slate-100 pt-4 dark:border-slate-700">
            <a href="{{ route('warehouse.adjustments.opname.create') }}" class="text-xs font-semibold text-slate-500 hover:underline dark:text-slate-400">Kembali</a>
            <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-xs font-bold text-white hover:bg-indigo-700">Simpan Hasil Opname</button>
        </div>
    </form>
</div>
@endsection
