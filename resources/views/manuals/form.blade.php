@extends('layouts.app')

@section('title', $article->exists ? 'Sunting Panduan' : 'Artikel Baru')
@section('page_title', 'Kelola Panduan')

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('manuals.manage') }}" class="text-sm font-semibold text-sky-700 hover:underline dark:text-sky-300">← Kelola panduan</a>
            <h1 class="mt-3 text-3xl font-semibold text-slate-900 dark:text-white">{{ $article->exists ? 'Sunting artikel' : 'Artikel baru' }}</h1>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Simpan draf, tinjau revisinya, lalu terbitkan saat siap dibaca.</p>
        </div>
        @if($article->exists)
            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $article->published_revision_id ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">{{ $article->published_revision_id ? 'Terbit · versi '.$article->publishedRevision->version : 'Draf' }}</span>
        @endif
    </div>

    @if($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700" role="alert">
            <p class="font-semibold">Periksa kembali isian berikut:</p>
            <ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_280px]">
        <form id="manual-form" method="POST" action="{{ $article->exists ? route('manuals.update', $article) : route('manuals.store') }}" class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800">
            @csrf
            @if($article->exists) @method('PUT') @endif
            @if(! $article->exists)
                <div>
                    <label for="slug" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Slug URL</label>
                    <input id="slug" name="slug" required maxlength="160" pattern="[a-z0-9]+(-[a-z0-9]+)*" value="{{ old('slug') }}" placeholder="contoh-tambah-odp" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                    <p class="mt-1 text-xs text-slate-500">Huruf kecil, angka, dan tanda minus. Tidak berubah setelah artikel dibuat.</p>
                </div>
            @else
                <p class="text-xs text-slate-500">URL: /panduan/{{ $article->slug }}</p>
            @endif
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="title" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Judul</label>
                    <input id="title" name="title" required maxlength="255" value="{{ old('title', $article->title) }}" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                </div>
                <div>
                    <label for="category" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Kategori</label>
                    <input id="category" name="category" required maxlength="100" value="{{ old('category', $article->category) }}" placeholder="Infrastruktur" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                </div>
            </div>
            <div>
                <label for="summary" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Ringkasan</label>
                <textarea id="summary" name="summary" required maxlength="500" rows="2" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">{{ old('summary', $article->summary) }}</textarea>
            </div>
            <div>
                <label for="body_markdown" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Isi panduan · Markdown</label>
                <textarea id="body_markdown" name="body_markdown" required rows="22" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-3 font-mono text-sm leading-6 dark:border-slate-600 dark:bg-slate-900 dark:text-white">{{ old('body_markdown', $article->body_markdown) }}</textarea>
                <p class="mt-1 text-xs text-slate-500">Gunakan ## untuk subjudul, daftar bernomor untuk langkah, dan **teks** untuk penekanan. HTML mentah tidak ditampilkan.</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="required_permission" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Izin pembaca</label>
                    <select id="required_permission" name="required_permission" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                        <option value="">Semua staf yang login</option>
                        @foreach($permissions as $permission)
                            <option value="{{ $permission }}" @selected(old('required_permission', $article->required_permission) === $permission)>{{ $permission }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="route_names_text" class="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Route halaman terkait</label>
                    <textarea id="route_names_text" name="route_names_text" rows="3" maxlength="1000" placeholder="network.map.index&#10;network.nodes.index" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-mono text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">{{ old('route_names_text', implode("\n", $article->route_names ?? [])) }}</textarea>
                    <p class="mt-1 text-xs text-slate-500">Opsional. Satu nama route per baris, maksimal 10. Artikel yang sama dapat dipakai di beberapa halaman.</p>
                </div>
            </div>
            <button type="submit" class="rounded-lg bg-sky-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-sky-600">Simpan draf</button>
        </form>

        @if($article->exists)
            <aside class="space-y-5">
                <section class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
                    <h2 class="font-semibold text-slate-900 dark:text-white">Publikasi</h2>
                    <a href="{{ route('manuals.preview', $article) }}" class="mt-3 block text-sm font-semibold text-sky-700 hover:underline dark:text-sky-300">Pratinjau draf tersimpan →</a>
                    <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">Hanya revisi tersimpan yang bisa diterbitkan. Perubahan di formulir belum tersimpan tidak ikut terbit.</p>
                    @if(auth()->user()->hasPermission('manuals.publish'))
                        <form method="POST" action="{{ route('manuals.publish', $article) }}" class="mt-4">@csrf<button class="w-full rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-600">Terbitkan revisi terbaru</button></form>
                        @if($article->published_revision_id)
                            <form method="POST" action="{{ route('manuals.unpublish', $article) }}" class="mt-2" onsubmit="return confirm('Tarik panduan dari publikasi?')">@csrf<button class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700">Tarik publikasi</button></form>
                        @endif
                    @endif
                    @if($article->published_revision_id)
                        <a class="mt-4 block text-sm font-semibold text-sky-700 hover:underline dark:text-sky-300" href="{{ route('manuals.show', $article->slug) }}">Lihat versi terbit →</a>
                    @endif
                </section>
                <section class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
                    <h2 class="font-semibold text-slate-900 dark:text-white">Riwayat revisi</h2>
                    <ol class="mt-4 space-y-3">
                        @foreach($article->revisions as $revision)
                            <li class="border-l-2 border-slate-200 pl-3 text-sm dark:border-slate-600">
                                <p class="font-semibold text-slate-800 dark:text-slate-100">Versi {{ $revision->version }} @if($article->published_revision_id === $revision->id)<span class="text-emerald-700 dark:text-emerald-300">· terbit</span>@endif</p>
                                <p class="text-xs text-slate-500">{{ $revision->created_at?->format('d M Y H:i') }}</p>
                                @if(auth()->user()->hasPermission('manuals.update') && $revision->id !== $article->revisions->first()?->id)
                                    <form method="POST" action="{{ route('manuals.restore', [$article, $revision]) }}" class="mt-1" onsubmit="return confirm('Pulihkan versi ini sebagai draf baru?')">@csrf<button class="text-xs font-semibold text-sky-700 hover:underline dark:text-sky-300">Pulihkan sebagai draf</button></form>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </section>
            </aside>
        @endif
    </div>
</div>
@endsection
