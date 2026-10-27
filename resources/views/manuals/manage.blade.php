@extends('layouts.app')

@section('title', 'Kelola Panduan')
@section('page_title', 'Kelola Panduan')

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="text-xs font-bold uppercase tracking-widest text-sky-700 dark:text-sky-300">Pusat Panduan</p>
            <h1 class="mt-2 text-3xl font-semibold text-slate-900 dark:text-white">Kelola artikel</h1>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Draf dapat disunting tanpa mengubah versi yang sedang terbit.</p>
            <a href="{{ route('manuals.context', ['route' => 'manuals.manage']) }}" class="mt-2 inline-block text-sm font-semibold text-sky-700 hover:underline dark:text-sky-300">? Cara mengelola panduan</a>
        </div>
        @if(auth()->user()->hasPermission('manuals.create'))
            <a href="{{ route('manuals.create') }}" class="rounded-lg bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-600">+ Artikel baru</a>
        @endif
    </div>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-slate-900/60 dark:text-slate-400">
                <tr><th class="px-5 py-4">Artikel</th><th class="px-5 py-4">Kategori</th><th class="px-5 py-4">Status</th><th class="px-5 py-4">Diubah</th><th class="px-5 py-4"></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
            @forelse($articles as $article)
                <tr>
                    <td class="px-5 py-4 font-semibold text-slate-900 dark:text-white">{{ $article->title }}<div class="mt-1 font-normal text-xs text-slate-500">{{ $article->slug }}</div></td>
                    <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $article->category }}</td>
                    <td class="px-5 py-4"><span class="rounded px-2 py-1 text-xs font-semibold {{ $article->published_revision_id ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">{{ $article->published_revision_id ? 'Terbit' : 'Draf' }}</span></td>
                    <td class="px-5 py-4 text-slate-500">{{ $article->updated_at?->format('d M Y H:i') }}</td>
                    <td class="px-5 py-4 text-right">
                        <div class="flex flex-col items-end gap-2">
                            @if(auth()->user()->hasPermission('manuals.update'))<a class="font-semibold text-sky-700 hover:underline dark:text-sky-300" href="{{ route('manuals.edit', $article) }}">Kelola</a>@endif
                            @if(auth()->user()->hasPermission('manuals.publish'))
                                <form method="POST" action="{{ route('manuals.publish', $article) }}">@csrf<button class="text-xs font-semibold text-emerald-700 hover:underline dark:text-emerald-300">Terbitkan revisi terbaru</button></form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">Belum ada artikel.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $articles->links() }}
</div>
@endsection
