@extends('layouts.app')

@section('title', $revision->title)
@section('page_title', 'Pusat Panduan')

@section('content')
<style>
    .manual-body { line-height: 1.8; }
    .manual-body h1,.manual-body h2,.manual-body h3 { color: inherit; font-weight: 700; line-height: 1.25; margin: 1.8em 0 .6em; }
    .manual-body h1 { font-size: 1.7rem; }
    .manual-body h2 { font-size: 1.35rem; border-bottom: 1px solid #cbd5e1; padding-bottom: .4em; }
    .manual-body h3 { font-size: 1.1rem; }
    .manual-body p,.manual-body ul,.manual-body ol,.manual-body table { margin: .9em 0; }
    .manual-body ul { list-style: disc; padding-left: 1.5rem; }
    .manual-body ol { list-style: decimal; padding-left: 1.5rem; }
    .manual-body a { color: #0369a1; text-decoration: underline; }
    .manual-body code { background: #e2e8f0; border-radius: .2rem; padding: .1rem .25rem; color: #0f172a; }
    .manual-body pre { overflow-x: auto; background: #0f172a; color: white; padding: 1rem; border-radius: .5rem; }
    .manual-body pre code { background: transparent; color: inherit; padding: 0; }
    .manual-body table { display: block; overflow-x: auto; border-collapse: collapse; max-width: 100%; }
    .manual-body th,.manual-body td { border: 1px solid #cbd5e1; padding: .55rem .75rem; text-align: left; vertical-align: top; }
    .dark .manual-body code { background: #334155; color: #f1f5f9; }
</style>
<div class="mx-auto max-w-5xl">
    <nav class="mb-6 text-sm text-slate-500 dark:text-slate-400">
        <a class="hover:text-sky-600" href="{{ route('manuals.index') }}">Pusat Panduan</a> / {{ $revision->category }}
    </nav>
    <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
        <header class="border-b border-slate-200 bg-slate-50 px-6 py-8 dark:border-slate-700 dark:bg-slate-900/50 sm:px-10">
            <p class="text-xs font-bold uppercase tracking-widest text-sky-700 dark:text-sky-300">{{ $revision->category }}</p>
            <h1 class="mt-3 text-3xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ $revision->title }}</h1>
            <p class="mt-3 text-base text-slate-600 dark:text-slate-300">{{ $revision->summary }}</p>
            @if($isPreview)
                <p class="mt-5 inline-block rounded bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800">Pratinjau draf · belum terbit</p>
            @else
                <p class="mt-5 text-xs text-slate-500 dark:text-slate-400">Versi {{ $revision->version }} · Diterbitkan {{ $article->published_at?->format('d M Y H:i') }}</p>
            @endif
        </header>
        <div class="manual-body px-6 py-8 text-sm text-slate-800 dark:text-slate-200 sm:px-10">{!! $html !!}</div>
    </article>
    <div class="mt-6 flex flex-wrap gap-4 text-sm">
        <a href="{{ route('manuals.index') }}" class="font-semibold text-sky-700 hover:underline dark:text-sky-300">← Semua panduan</a>
        @if($isPreview || auth()->user()->hasPermission('manuals.update'))
            <a href="{{ route('manuals.edit', $article) }}" class="font-semibold text-slate-600 hover:underline dark:text-slate-300">Sunting draf</a>
        @endif
    </div>
</div>
@endsection
