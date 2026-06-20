@extends('layouts.app')

@section('title', 'Ubah Distribusi - Whusnet Operasional')
@section('page_title', 'Ubah Distribusi')

@section('content')
<!-- Back link and Title Header -->
<div class="mb-6 flex items-center justify-between">
    <a href="{{ route('master.distribusi.index') }}" class="inline-flex items-center gap-2 text-xs font-bold text-slate-500 hover:text-slate-800 transition-colors">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        Kembali ke Daftar Distribusi
    </a>
</div>

<!-- Form Container -->
<form action="{{ route('master.distribusi.update', $distribusi) }}" method="POST" class="grid grid-cols-1 lg:grid-cols-2 gap-6 max-w-4xl mx-auto">
    @csrf
    @method('PUT')

    <div class="bg-white border border-slate-200 rounded-lg p-6 shadow-sm col-span-1 lg:col-span-2 space-y-5">
        <div class="border-b border-slate-100 pb-3">
            <h3 class="text-sm font-bold text-slate-800">Informasi Distribusi</h3>
            <p class="text-xs text-slate-400 mt-0.5">Ubah data master distribusi.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Kode Distribusi -->
            <div>
                <label for="code" class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">Kode Distribusi <span class="text-rose-500">*</span></label>
                <input type="text" name="code" id="code" value="{{ old('code', $distribusi->code) }}" required
                       class="w-full px-3 py-2 border @error('code') border-rose-500 focus:ring-rose-500 @else border-slate-300 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm text-slate-800 focus:outline-none focus:ring-1">
                @error('code')
                    <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                @enderror
            </div>

            <!-- Nama Distribusi -->
            <div>
                <label for="name" class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">Nama Distribusi <span class="text-rose-500">*</span></label>
                <input type="text" name="name" id="name" value="{{ old('name', $distribusi->name) }}" required
                       class="w-full px-3 py-2 border @error('name') border-rose-500 focus:ring-rose-500 @else border-slate-300 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm text-slate-800 focus:outline-none focus:ring-1">
                @error('name')
                    <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
            <!-- POP / Cabang -->
            <div>
                <label for="pop_id" class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">Cabang / POP <span class="text-rose-500">*</span></label>
                <select name="pop_id" id="pop_id" required
                        class="w-full px-3 py-2 border @error('pop_id') border-rose-500 focus:ring-rose-500 @else border-slate-300 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm text-slate-800 focus:outline-none focus:ring-1 bg-white">
                    <option value="">-- Pilih Cabang / POP --</option>
                    @foreach($pops as $pop)
                        <option value="{{ $pop->id }}" {{ old('pop_id', $distribusi->pop_id) == $pop->id ? 'selected' : '' }}>
                            {{ $pop->name }} ({{ $pop->code }})
                        </option>
                    @endforeach
                </select>
                @error('pop_id')
                    <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="space-y-4 pt-2">
            <!-- Deskripsi -->
            <div>
                <label for="description" class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">Deskripsi Distribusi <span class="text-rose-500">*</span></label>
                <textarea name="description" id="description" rows="3" required
                          class="w-full px-3 py-2 border @error('description') border-rose-500 focus:ring-rose-500 @else border-slate-300 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm text-slate-800 focus:outline-none focus:ring-1">{{ old('description', $distribusi->description) }}</textarea>
                @error('description')
                    <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <!-- Submit Actions -->
        <div class="flex items-center gap-3 justify-end pt-5 border-t border-slate-100 mt-5">
            <a href="{{ route('master.distribusi.index') }}" class="inline-flex items-center justify-center px-4 py-2.5 border border-slate-300 rounded-md shadow-sm text-sm font-semibold text-slate-700 bg-white hover:bg-slate-50 transition-colors focus:outline-none cursor-pointer">
                Batal
            </a>
            <button type="submit" class="inline-flex items-center justify-center px-6 py-2.5 border border-transparent rounded-md shadow-sm text-sm font-semibold text-white bg-sky-600 hover:bg-sky-700 transition-colors focus:outline-none cursor-pointer">
                Simpan Perubahan
            </button>
        </div>
    </div>
</form>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function() {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.classList.add('opacity-75', 'cursor-not-allowed');
                    submitBtn.innerHTML = `
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Memproses...
                    `;
                }
            });
        }
    });
</script>
@endsection