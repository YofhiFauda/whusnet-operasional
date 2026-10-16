@extends('layouts.app')

@section('title', 'Pusat Panduan')
@section('page_title', 'Pusat Panduan')

@section('content')
<div class="mx-auto max-w-6xl space-y-8">
    <header class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-950 px-6 py-10 text-white dark:border-slate-700 sm:px-10">
        <p class="text-xs font-bold uppercase tracking-[0.25em] text-sky-300">Referensi kerja</p>
        <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Pusat Panduan</h1>
        <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300">Cari langkah kerja sesuai fitur dan tugas Anda. Artikel yang tampil mengikuti hak akses Anda.</p>
        <form action="{{ route('manuals.index') }}" method="GET" class="mt-7 flex max-w-2xl gap-2">
            <label class="sr-only" for="manual-search">Cari panduan</label>
            <input id="manual-search" name="q" type="search" value="{{ $search }}" placeholder="Cari ODP, port, switch, laporan..." class="min-w-0 flex-1 rounded-lg border border-slate-600 bg-white px-4 py-3 text-sm text-slate-900 focus:border-sky-400 focus:outline-none">
            <button class="rounded-lg bg-sky-500 px-5 py-3 text-sm font-semibold text-white hover:bg-sky-400">Cari</button>
        </form>
    </header>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-600 dark:text-slate-300">{{ $articles->total() }} panduan {{ $search ? 'untuk “'.$search.'”' : 'tersedia' }}</p>
        @if(auth()->user()->hasPermission('manuals.create') || auth()->user()->hasPermission('manuals.update') || auth()->user()->hasPermission('manuals.publish'))
            <a href="{{ route('manuals.manage') }}" class="text-sm font-semibold text-sky-700 hover:underline dark:text-sky-300">Kelola panduan →</a>
        @endif
    </div>

    @forelse($articles as $article)
        @if($loop->first)<div class="grid gap-4 md:grid-cols-2">@endif
        <a href="{{ route('manuals.show', $article->slug) }}" class="group rounded-xl border border-slate-200 bg-white p-6 transition hover:border-sky-400 hover:shadow-md dark:border-slate-700 dark:bg-slate-800">
            <div class="flex items-center justify-between gap-4">
                <span class="rounded bg-sky-50 px-2 py-1 text-[11px] font-bold uppercase tracking-wider text-sky-700 dark:bg-sky-900/40 dark:text-sky-300">{{ $article->publishedRevision->category }}</span>
                <span class="text-slate-400 transition group-hover:translate-x-1 group-hover:text-sky-600">→</span>
            </div>
            <h2 class="mt-5 text-lg font-semibold text-slate-900 dark:text-white">{{ $article->publishedRevision->title }}</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ $article->publishedRevision->summary }}</p>
            <p class="mt-5 text-xs text-slate-500 dark:text-slate-400">Diperbarui {{ $article->published_at?->format('d M Y') }}</p>
        </a>
        @if($loop->last)</div>@endif
    @empty
        <div class="rounded-xl border border-dashed border-slate-300 px-6 py-12 text-center text-sm text-slate-500 dark:border-slate-600 dark:text-slate-400">Belum ada panduan yang cocok. Coba kata kunci lain.</div>
    @endforelse

    {{ $articles->links() }}
</div>
@endsection
