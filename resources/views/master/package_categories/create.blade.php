@extends('layouts.app')

@section('title', 'Tambah Kategori Paket - Whusnet Operasional')
@section('page_title', 'Tambah Kategori Paket')

@section('content')
<div class="max-w-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6">
    <form action="{{ route('master.package-categories.store') }}" method="POST" class="space-y-4">
        @csrf

        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Nama Kategori</label>
            <input type="text" name="name" value="{{ old('name') }}" required maxlength="100"
                   class="w-full px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all"
                   placeholder="Mis. Paket Bisnis Enterprise">
            @error('name')
                <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}
                   class="rounded border-slate-300 dark:border-slate-600 text-sky-600 focus:ring-sky-500/40">
            <label for="is_active" class="text-xs font-medium text-slate-600 dark:text-slate-300">Kategori aktif (muncul di dropdown Paket)</label>
        </div>

        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                Role Validasi Biaya Instalasi
            </label>
            <select name="installation_fee_approval_role_id" class="w-full px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                <option value="">— Tidak perlu (alur biasa, seperti Home Broadband) —</option>
                @foreach($roles as $role)
                    <option value="{{ $role->id }}" {{ (string) old('installation_fee_approval_role_id') === (string) $role->id ? 'selected' : '' }}>
                        {{ $role->name }}
                    </option>
                @endforeach
            </select>
            <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">
                User dengan role ini yang bisa mengisi kolom "Biaya Instalasi" pelanggan di kategori ini pada modul Busdev. Kosongkan kalau kategori ini tidak butuh validasi tambahan.
            </p>
        </div>

        <div class="flex items-center gap-2 pt-2">
            <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 text-white text-xs font-semibold rounded-xl shadow-xs transition-colors cursor-pointer">
                Simpan
            </button>
            <a href="{{ route('master.package-categories.index') }}" class="px-4 py-2 text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700/50 transition-colors">
                Batal
            </a>
        </div>
    </form>
</div>
@endsection
