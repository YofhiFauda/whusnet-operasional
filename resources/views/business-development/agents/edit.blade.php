@extends('layouts.app')

@section('title', 'Ubah Agent - Whusnet Operasional')
@section('page_title', 'Ubah Agent')
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
                <div class="flex items-center gap-2.5 flex-wrap">
                    <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        Ubah Data Agent
                    </h1>
                    <span class="font-mono text-xs font-bold text-sky-700 dark:text-sky-300 bg-sky-50 dark:bg-sky-950/60 px-2 py-0.5 rounded border border-sky-200/60 dark:border-sky-800/50">
                        {{ $agent->code }}
                    </span>
                </div>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 pl-10">
                Perbarui rincian informasi dan status aktifitas mitra agent <strong class="text-slate-700 dark:text-slate-300 font-semibold">{{ $agent->name }}</strong>.
            </p>
        </div>
    </div>

    {{-- ── LAYER 2: FORM CONTAINER (CARD BUDGET = 1 — TYPE B SESUAI DESIGN.MD) ── --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200/70 dark:border-slate-700/60 rounded-lg shadow-2xs overflow-hidden">
        <div class="p-5 sm:p-6 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-sky-100 dark:bg-sky-900/60 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100">
                        Formulir Perubahan Data
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Pastikan kode agent dan nomor kontak valid.
                    </p>
                </div>
            </div>

            <div>
                @if($agent->is_active)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200/80 dark:border-emerald-800/60">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        Aktif
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200/80 dark:border-slate-700">
                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                        Nonaktif
                    </span>
                @endif
            </div>
        </div>

        <form action="{{ route('business-development.agents.update', $agent) }}" method="POST" class="p-5 sm:p-6">
            @csrf
            @method('PUT')
            @include('business-development.agents._form', ['agent' => $agent])

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
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
