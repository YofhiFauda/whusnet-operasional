@extends('layouts.app')

@section('title', 'Registrasi Aset Lama · '.$node->code)
@section('page_title', 'Registrasi Aset Infrastruktur Lama')

@section('content')
<div class="mx-auto max-w-2xl space-y-6 pb-16">

    <div class="flex items-center justify-between gap-3 flex-wrap">
        <a href="{{ route('network.nodes.show', $node) }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-border bg-surface hover:bg-surface-muted text-xs font-semibold text-text-secondary hover:text-text-main transition-colors shadow-2xs">
            <svg class="h-3.5 w-3.5 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            &larr; Kembali ke detail node
        </a>

        <div class="flex items-center gap-2 text-xs font-mono">
            <span class="text-text-muted">Node ID:</span>
            <span class="font-bold text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/60 px-2.5 py-0.5 rounded-md border border-sky-200 dark:border-sky-900">{{ $node->code }}</span>
        </div>
    </div>

    <form method="POST" action="{{ route('network.nodes.register-asset.store', $node) }}"
          class="rounded-lg border border-border bg-surface p-6 shadow-2xs space-y-5">
        @csrf

        <div class="flex items-center gap-2 border-b border-border pb-3">
            <span class="w-1.5 h-3.5 bg-sky-600 rounded-full"></span>
            <div>
                <h2 class="text-sm font-bold uppercase tracking-wider text-text-main">Registrasi Aset Infrastruktur Lama</h2>
                <p class="text-xs text-text-muted mt-0.5">Untuk aset yang sudah lama berdiri di {{ $node->breadcrumbLabel() }} tapi belum pernah tercatat di ledger (tidak lewat Terima/Kirim gudang).</p>
            </div>
        </div>

        <div class="grid gap-4">
            <label class="text-xs font-semibold text-text-secondary">Barang <span class="text-rose-500">*</span>
                <select name="item_id" required class="mt-1 block w-full rounded-lg border border-border bg-surface px-3.5 py-2 text-text-main text-xs focus:border-primary focus:ring-1 focus:ring-primary/20 outline-none cursor-pointer">
                    <option value="">Pilih barang aset infrastruktur</option>
                    @foreach($items as $item)
                    <option value="{{ $item->id }}" @selected((int) old('item_id') === $item->id)>{{ $item->name }} ({{ $item->code }})</option>
                    @endforeach
                </select>
            </label>

            <label class="text-xs font-semibold text-text-secondary">Nomor Seri <span class="text-rose-500">*</span>
                <input name="serial_number" required maxlength="100" value="{{ old('serial_number') }}"
                       class="mt-1 block w-full rounded-lg border border-border bg-surface px-3.5 py-2 text-text-main text-xs font-mono focus:border-primary focus:ring-1 focus:ring-primary/20 outline-none"
                       placeholder="Contoh: SN-LEGACY-001">
            </label>

            <label class="text-xs font-semibold text-text-secondary">Alasan Registrasi <span class="text-rose-500">*</span>
                <textarea name="reason" required maxlength="500" rows="3"
                          class="mt-1 block w-full rounded-lg border border-border bg-surface px-3.5 py-2 text-text-main text-xs focus:border-primary focus:ring-1 focus:ring-primary/20 outline-none"
                          placeholder="Contoh: Opname awal, chassis sudah berdiri sejak 2023">{{ old('reason') }}</textarea>
            </label>
        </div>

        @if($errors->any())
        <div class="p-3 rounded-lg bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-900 text-xs font-semibold text-rose-600 dark:text-rose-400">
            {{ $errors->first() }}
        </div>
        @endif

        <div class="pt-4 border-t border-border flex items-center justify-end">
            <button type="submit" class="px-5 py-2 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold shadow-xs transition-colors cursor-pointer">
                Registrasi Aset
            </button>
        </div>
    </form>

</div>
@endsection
