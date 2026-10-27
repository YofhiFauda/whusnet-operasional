@extends('layouts.app')

@section('title', 'Generate Slot & PON · '.$node->code)
@section('page_title', 'Generate Slot & '.$kind->portKind()->label())

@section('content')
@php $isOlt = $kind === \App\Enums\InfrastructureSlotKind::OLT; @endphp
<div class="mx-auto max-w-2xl space-y-6 pb-16">
    <a href="{{ route('network.nodes.show', $node) }}" class="inline-flex min-h-10 items-center text-sm text-text-secondary hover:text-primary">&larr; Kembali ke {{ $node->code }}</a>

    @if($errors->any())<div role="alert" class="border-l-4 border-error py-2 pl-4 text-sm text-error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

    <section class="rounded-2xl border border-border bg-surface p-6 shadow-sm space-y-2">
        <p class="text-xs font-semibold uppercase tracking-wider text-text-muted">{{ $node->nodeType?->name }}</p>
        <h1 class="text-2xl font-black font-mono text-text-main">{{ $node->code }}</h1>
        <p class="text-xs text-text-secondary">
            Hanya nomor yang belum ada yang dibuat; slot dan {{ $kind->portKind()->label() }} yang sudah ada tidak diubah.
            @unless($isOlt) Switch memakai satu slot internal/logis. @endunless
        </p>
    </section>

    <form method="POST" action="{{ route('network.nodes.generate-structure.store', $node) }}" class="space-y-4 rounded-2xl border border-border bg-surface p-6 shadow-sm">
        @csrf
        @if($isOlt)
        <label class="block text-xs text-text-secondary">Jumlah slot (1–64)
            <input type="number" name="slot_count" min="1" max="32" required value="{{ old('slot_count') }}" class="mt-1 block w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-main">
        </label>
        @endif
        <label class="block text-xs text-text-secondary">{{ $isOlt ? 'PON' : 'Port' }} per slot (1–128)
            <input type="number" name="ports_per_slot" min="1" max="64" required value="{{ old('ports_per_slot') }}" class="mt-1 block w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-main">
        </label>
        @if($isOlt)
        <label class="block text-xs text-text-secondary">Kapasitas pelanggan aktif per PON baru (opsional)
            <input type="number" name="pon_capacity" min="1" value="{{ old('pon_capacity') }}" class="mt-1 block w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-main">
        </label>
        @endif
        <div class="flex flex-wrap gap-3 pt-2">
            <button type="submit" class="min-h-10 rounded-lg bg-primary px-4 text-sm font-semibold text-white">Generate</button>
            <a href="{{ route('network.nodes.show', $node) }}" class="inline-flex min-h-10 items-center rounded-lg border border-border px-4 text-sm text-text-secondary">Batal</a>
        </div>
    </form>
</div>
@endsection
