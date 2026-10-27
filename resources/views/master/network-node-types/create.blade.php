@extends('layouts.app')

@section('title', 'Tambah Tipe Node Jaringan - Whusnet Operasional')
@section('page_title', 'Tambah Tipe Node Jaringan')

@section('content')
<!-- Back link and Title Header -->
<div class="mb-6 flex items-center justify-between">
    <a href="{{ route('master.network-node-types.index') }}" class="inline-flex items-center gap-2 text-xs font-bold text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 transition-colors">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        Kembali ke Daftar Tipe Node
    </a>
</div>

<!-- Form Container -->
<form action="{{ route('master.network-node-types.store') }}" method="POST" class="grid grid-cols-1 lg:grid-cols-2 gap-6 max-w-4xl mx-auto">
    @csrf

    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6 shadow-sm col-span-1 lg:col-span-2 space-y-5">
        <div class="border-b border-slate-100 dark:border-slate-700/50 pb-3">
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200">Informasi Tipe Node</h3>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Lengkapi form berikut untuk menambahkan master tipe node jaringan baru.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Kode -->
            <div>
                <label for="code" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Kode <span class="text-rose-500">*</span></label>
                <input type="text" name="code" id="code" value="{{ old('code') }}" required placeholder="Contoh: repeater"
                       class="w-full px-3 py-2 border @error('code') border-rose-500 focus:ring-rose-500 @else border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1">
                @error('code')
                    <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                @enderror
            </div>

            <!-- Nama Tipe -->
            <div>
                <label for="name" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Nama Tipe <span class="text-rose-500">*</span></label>
                <input type="text" name="name" id="name" value="{{ old('name') }}" required placeholder="Contoh: Repeater"
                       class="w-full px-3 py-2 border @error('name') border-rose-500 focus:ring-rose-500 @else border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1">
                @error('name')
                    <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
            <!-- Maks Port -->
            <div>
                <label for="max_ports" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Maks Port</label>
                <input type="number" min="0" name="max_ports" id="max_ports" value="{{ old('max_ports') }}" placeholder="Contoh: 128"
                       class="w-full px-3 py-2 border @error('max_ports') border-rose-500 focus:ring-rose-500 @else border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1">
                <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1">Isi hanya kalau tipe ini punya daftar port (centang "Punya Port" di bawah).</p>
                @error('max_ports')
                    <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                @enderror
            </div>

            <!-- Ambang Zona Merah -->
            <div>
                <label for="red_zone_threshold" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Ambang Zona Merah (%)</label>
                <input type="number" min="0" max="100" name="red_zone_threshold" id="red_zone_threshold" value="{{ old('red_zone_threshold') }}" placeholder="Contoh: 80"
                       class="w-full px-3 py-2 border @error('red_zone_threshold') border-rose-500 focus:ring-rose-500 @else border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1">
                <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1">Isi hanya kalau kapasitasnya dipantau (centang "Pantau Kapasitas" di bawah).</p>
                @error('red_zone_threshold')
                    <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
            <!-- Urutan -->
            <div>
                <label for="sort_order" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Urutan Tampil</label>
                <input type="number" name="sort_order" id="sort_order" value="{{ old('sort_order', 0) }}"
                       class="w-full px-3 py-2 border @error('sort_order') border-rose-500 focus:ring-rose-500 @else border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1">
                @error('sort_order')
                    <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                @enderror
            </div>

            <!-- Status -->
            <div>
                <label for="is_active" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Status</label>
                <select name="is_active" id="is_active"
                        class="w-full px-3 py-2 border @error('is_active') border-rose-500 focus:ring-rose-500 @else border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1 bg-white dark:bg-slate-800">
                    <option value="1" {{ old('is_active', '1') === '1' ? 'selected' : '' }}>Aktif</option>
                    <option value="0" {{ old('is_active') === '0' ? 'selected' : '' }}>Nonaktif</option>
                </select>
                @error('is_active')
                    <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <!-- Flag Perilaku -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
            <div>
                <label class="flex items-start gap-2.5 cursor-pointer">
                    <input type="checkbox" name="has_ports" value="1" {{ old('has_ports') ? 'checked' : '' }}
                           class="mt-0.5 h-4 w-4 rounded border-slate-300 dark:border-slate-600 text-sky-600 focus:ring-sky-500">
                    <span>
                        <span class="block text-xs font-bold text-slate-700 dark:text-slate-300">Punya Port</span>
                        <span class="block text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">Tipe node ini punya daftar port yang bisa diisi pelanggan (mis. ODC, ODP).</span>
                    </span>
                </label>
                @error('has_ports')
                    <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="flex items-start gap-2.5 cursor-pointer">
                    <input type="checkbox" name="monitors_capacity" value="1" {{ old('monitors_capacity') ? 'checked' : '' }}
                           class="mt-0.5 h-4 w-4 rounded border-slate-300 dark:border-slate-600 text-sky-600 focus:ring-sky-500">
                    <span>
                        <span class="block text-xs font-bold text-slate-700 dark:text-slate-300">Pantau Kapasitas</span>
                        <span class="block text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">Kapasitas port node ini dipantau dan ditandai "zona merah" saat terisi di atas ambang.</span>
                    </span>
                </label>
                @error('monitors_capacity')
                    <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                @enderror
            </div>
        </div>

        @include('master.network-node-types._placement-rules')

        <!-- Submit Actions -->
        <div class="flex items-center gap-3 justify-end pt-5 border-t border-slate-100 dark:border-slate-700/50 mt-5">
            <a href="{{ route('master.network-node-types.index') }}" class="inline-flex items-center justify-center px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm text-sm font-semibold text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/50 dark:hover:bg-slate-800/50 transition-colors focus:outline-none cursor-pointer">
                Batal
            </a>
            <button type="submit" class="inline-flex items-center justify-center px-6 py-2.5 border border-transparent rounded-md shadow-sm text-sm font-semibold text-white bg-sky-600 dark:bg-sky-500 hover:bg-sky-700 transition-colors focus:outline-none cursor-pointer">
                Simpan Tipe
            </button>
        </div>
    </div>
</form>
@endsection
