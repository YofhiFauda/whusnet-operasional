@extends('layouts.app')

@section('title', 'Tambah Kategori Barang - Whusnet Operasional')
@section('page_title', 'Tambah Kategori Barang')

@section('content')
<!-- Back link and Title Header -->
<div class="mb-6 flex items-center justify-between">
    <a href="{{ route('master.item-categories.index') }}" class="inline-flex items-center gap-2 text-xs font-bold text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 transition-colors">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        Kembali ke Daftar Kategori
    </a>
</div>

<!-- Form Container -->
<form action="{{ route('master.item-categories.store') }}" method="POST" class="grid grid-cols-1 lg:grid-cols-2 gap-6 max-w-4xl mx-auto">
    @csrf

    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6 shadow-sm col-span-1 lg:col-span-2 space-y-5">
        <div class="border-b border-slate-100 dark:border-slate-700/50 pb-3">
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200">Informasi Kategori</h3>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Kode ikut tersimpan di setiap baris material yang memakai kategori ini, jadi pilih yang tidak akan diubah lagi. Satuan default cuma mengisi otomatis kolom satuan di form laporan — teknisi tetap boleh mengubahnya.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Kode -->
            <div>
                <label for="code" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Kode Kategori <span class="text-rose-500">*</span></label>
                <input type="text" name="code" id="code" value="{{ old('code') }}" required placeholder="Contoh: kabel_feeder"
                       class="w-full px-3 py-2 border @error('code') border-rose-500 focus:ring-rose-500 @else border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm font-mono text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1">
                <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1">Huruf kecil, angka, dan underscore saja.</p>
                @error('code')
                    <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                @enderror
            </div>

            <!-- Nama -->
            <div>
                <label for="name" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Nama Kategori <span class="text-rose-500">*</span></label>
                <input type="text" name="name" id="name" value="{{ old('name') }}" required placeholder="Contoh: Kabel Feeder"
                       class="w-full px-3 py-2 border @error('name') border-rose-500 focus:ring-rose-500 @else border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1">
                @error('name')
                    <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
            <!-- Satuan Default -->
            <div>
                <label for="default_unit" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Satuan Default <span class="text-rose-500">*</span></label>
                <input type="text" name="default_unit" id="default_unit" value="{{ old('default_unit', 'pcs') }}" required placeholder="meter / pcs / roll"
                       class="w-full px-3 py-2 border @error('default_unit') border-rose-500 focus:ring-rose-500 @else border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm font-mono text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1">
                @error('default_unit')
                    <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                @enderror
            </div>

            <!-- Urutan -->
            <div>
                <label for="sort_order" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Urutan Tampil</label>
                <input type="number" name="sort_order" id="sort_order" value="{{ old('sort_order', 100) }}" min="0" max="9999"
                       class="w-full px-3 py-2 border @error('sort_order') border-rose-500 focus:ring-rose-500 @else border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm font-mono text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1">
                @error('sort_order')
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

        <!-- Submit Actions -->
        <div class="flex items-center gap-3 justify-end pt-5 border-t border-slate-100 dark:border-slate-700/50 mt-5">
            <a href="{{ route('master.item-categories.index') }}" class="inline-flex items-center justify-center px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm text-sm font-semibold text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors focus:outline-none cursor-pointer">
                Batal
            </a>
            <button type="submit" class="inline-flex items-center justify-center px-6 py-2.5 border border-transparent rounded-md shadow-sm text-sm font-semibold text-white bg-sky-600 dark:bg-sky-500 hover:bg-sky-700 transition-colors focus:outline-none cursor-pointer">
                Simpan Kategori
            </button>
        </div>
    </div>
</form>
@endsection
