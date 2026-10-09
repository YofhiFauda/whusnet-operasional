@extends('layouts.app')

@section('title', 'Tambah Agent - Whusnet Operasional')
@section('page_title', 'Tambah Agent')
@section('breadcrumb_parent', 'Master Agent')
@section('breadcrumb_parent_url', route('business-development.agents.index'))

@section('content')
<div class="max-w-2xl">
    {{-- ── LAYER 1: PAGE HEADER (NAKED) ── --}}
    <div class="flex items-center justify-between gap-4 mb-5">
        <div class="space-y-1">
            <div class="flex items-center gap-2">
                <a href="{{ route('business-development.agents.index') }}"
                   class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors shadow-2xs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>
                <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    Tambah Mitra Agent
                </h1>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 pl-10">
                Daftarkan data mitra perantara baru untuk pilihan referral pada pendaftaran pelanggan.
            </p>
        </div>
    </div>

    {{-- ── LAYER 2: FORM CONTAINER (CARD BUDGET = 1 — TYPE B SESUAI DESIGN.MD) ── --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200/70 dark:border-slate-700/60 rounded-lg shadow-2xs overflow-hidden">
        <div class="p-5 sm:p-6 border-b border-slate-100 dark:border-slate-700/60 flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-sky-100 dark:bg-sky-900/60 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100">
                    Informasi Mitra Agent
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Lengkapi kode identitas, nama lengkap, dan nomor kontak mitra.
                </p>
            </div>
        </div>

        <form action="{{ route('business-development.agents.store') }}" method="POST" class="p-5 sm:p-6">
            @csrf
            @include('business-development.agents._form')

            <div class="flex items-center justify-end gap-2.5 pt-6 mt-6 border-t border-slate-100 dark:border-slate-700/60">
                <a href="{{ route('business-development.agents.index') }}"
                   class="px-4 py-2 text-xs sm:text-sm font-medium rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors shadow-2xs">
                    Batal
                </a>
                <button type="submit"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-xs sm:text-sm font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white transition-colors shadow-2xs cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Simpan Agent
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
