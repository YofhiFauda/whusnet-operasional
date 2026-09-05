@extends('layouts.app')

@section('title', 'Tambah Barang - Whusnet Operasional')
@section('page_title', 'Tambah Barang/Material')

@section('content')
<!-- Back link and Title Header -->
<div class="mb-6 flex items-center justify-between">
    <a href="{{ route('master.items.index') }}" class="inline-flex items-center gap-2 text-xs font-bold text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 transition-colors">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        Kembali ke Daftar Barang
    </a>
</div>

<!-- Form Container -->
<form action="{{ route('master.items.store') }}" method="POST" class="grid grid-cols-1 lg:grid-cols-2 gap-6 max-w-4xl mx-auto" x-data="{ trackingType: '{{ old('tracking_type', 'quantity') }}' }">
    @csrf

    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6 shadow-sm col-span-1 lg:col-span-2 space-y-5">
        <div class="border-b border-slate-100 dark:border-slate-700/50 pb-3">
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200">Informasi Barang</h3>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Kode dipakai sebagai identitas tetap barang. Satuan menentukan cara teknisi mencatat jumlah di lapangan.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Kode -->
            <div>
                <label for="code" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Kode Barang <span class="text-rose-500">*</span></label>
                <input type="text" name="code" id="code" value="{{ old('code') }}" required placeholder="Contoh: DC-1C"
                       class="w-full px-3 py-2 border @error('code') border-rose-500 focus:ring-rose-500 @else border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm font-mono text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1">
                @error('code')
                    <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                @enderror
            </div>

            <!-- Nama -->
            <div>
                <label for="name" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Nama Barang <span class="text-rose-500">*</span></label>
                <input type="text" name="name" id="name" value="{{ old('name') }}" required placeholder="Contoh: Kabel Dropcore 1 Core"
                       class="w-full px-3 py-2 border @error('name') border-rose-500 focus:ring-rose-500 @else border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1">
                @error('name')
                    <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
            <!-- Tipe -->
            <div>
                <label for="item_category_id" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Kategori <span class="text-rose-500">*</span></label>
                <select name="item_category_id" id="item_category_id" required
                        class="w-full px-3 py-2 border @error('item_category_id') border-rose-500 focus:ring-rose-500 @else border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1 bg-white dark:bg-slate-800">
                    <option value="">-- Pilih Kategori --</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ (int) old('item_category_id') === $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('item_category_id')
                    <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                @enderror
            </div>

            <!-- Satuan -->
            <div>
                <label for="unit" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Satuan <span class="text-rose-500">*</span></label>
                <input type="text" name="unit" id="unit" value="{{ old('unit', 'pcs') }}" required placeholder="meter / pcs / roll"
                       class="w-full px-3 py-2 border @error('unit') border-rose-500 focus:ring-rose-500 @else border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm font-mono text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1">
                @error('unit')
                    <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                @enderror
            </div>

            <!-- Status -->
            <div>
                <label for="is_active" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Status <span class="text-rose-500">*</span></label>
                <select name="is_active" id="is_active" required
                        class="w-full px-3 py-2 border @error('is_active') border-rose-500 focus:ring-rose-500 @else border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1 bg-white dark:bg-slate-800">
                    <option value="1" {{ old('is_active', '1') === '1' ? 'selected' : '' }}>Aktif</option>
                    <option value="0" {{ old('is_active') === '0' ? 'selected' : '' }}>Nonaktif</option>
                </select>
                @error('is_active')
                    <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="border-t border-slate-100 dark:border-slate-700/50 pt-4 mt-1">
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200">Cara Lacak Stok</h3>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Pilih <strong>Bernomor Seri</strong> buat barang aktif (modem, ONT, router) — cuma barang berjenis ini yang muncul di dropdown SN Laporan Pemasangan. Kabel/konektor/aksesoris pakai Kuantitas atau Batch.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            @foreach($trackingTypes as $tt)
            <label class="flex items-start gap-2 border rounded-md p-3 cursor-pointer transition-colors {{ old('tracking_type', 'quantity') === $tt->value ? 'border-sky-500 bg-sky-50 dark:bg-sky-950/30' : 'border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600' }}">
                <input type="radio" name="tracking_type" value="{{ $tt->value }}" x-model="trackingType" {{ old('tracking_type', 'quantity') === $tt->value ? 'checked' : '' }} class="mt-0.5">
                <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">{{ $tt->label() }}</span>
            </label>
            @endforeach
        </div>
        @error('tracking_type')
            <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
        @enderror

        <div x-show="trackingType === 'serialized'" x-cloak class="pt-2">
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Kepemilikan</label>
            <select name="ownership_mode" class="w-full sm:w-1/2 px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md text-sm text-slate-800 dark:text-slate-200 bg-white dark:bg-slate-800 focus:outline-none focus:ring-1 focus:ring-sky-500">
                @foreach($ownershipModes as $om)
                <option value="{{ $om->value }}" {{ old('ownership_mode', 'installable') === $om->value ? 'selected' : '' }}>{{ $om->label() }}</option>
                @endforeach
            </select>
            <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1">"Aset Perusahaan" buat alat kerja (OTDR, laptop) — gak pernah tercatat terpasang ke pelanggan, cuma dipinjam-pakaikan lalu wajib balik.</p>
        </div>

        <!-- Submit Actions -->
        <div class="flex items-center gap-3 justify-end pt-5 border-t border-slate-100 dark:border-slate-700/50 mt-5">
            <a href="{{ route('master.items.index') }}" class="inline-flex items-center justify-center px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm text-sm font-semibold text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors focus:outline-none cursor-pointer">
                Batal
            </a>
            <button type="submit" class="inline-flex items-center justify-center px-6 py-2.5 border border-transparent rounded-md shadow-sm text-sm font-semibold text-white bg-sky-600 dark:bg-sky-500 hover:bg-sky-700 transition-colors focus:outline-none cursor-pointer">
                Simpan Barang
            </button>
        </div>
    </div>
</form>
@endsection
