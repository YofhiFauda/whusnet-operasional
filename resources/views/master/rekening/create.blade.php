@extends('layouts.app')

@section('title', 'Tambah Rekening Bank - Whusnet Operasional')
@section('page_title', 'Tambah Rekening Bank')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <a href="{{ route('master.rekening.index') }}" class="inline-flex items-center gap-2 text-xs font-bold text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 transition-colors">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        Kembali ke Daftar Rekening
    </a>
</div>

<form action="{{ route('master.rekening.store') }}" method="POST" class="grid grid-cols-1 lg:grid-cols-2 gap-6 max-w-4xl mx-auto">
    @csrf

    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6 shadow-sm col-span-1 lg:col-span-2 space-y-5">
        <div class="border-b border-slate-100 dark:border-slate-700/50 pb-3">
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200">Informasi Rekening</h3>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Rekening aktif muncul di dropdown "Rekening Tujuan" saat mencatat pembayaran metode Transfer, untuk semua POP.</p>
        </div>

        @include('master.rekening._form', ['account' => null])

        <div class="flex items-center gap-3 justify-end pt-5 border-t border-slate-100 dark:border-slate-700/50 mt-5">
            <a href="{{ route('master.rekening.index') }}" class="inline-flex items-center justify-center px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm text-sm font-semibold text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors focus:outline-none cursor-pointer">
                Batal
            </a>
            <button type="submit" class="inline-flex items-center justify-center px-6 py-2.5 border border-transparent rounded-md shadow-sm text-sm font-semibold text-white bg-sky-600 dark:bg-sky-500 hover:bg-sky-700 transition-colors focus:outline-none cursor-pointer">
                Simpan Rekening
            </button>
        </div>
    </div>
</form>
@endsection
