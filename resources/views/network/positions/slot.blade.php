@extends('layouts.app')

@php($slotKind = $slot->slotKind())

@section('title', $slot->displayName().' · '.$slot->parent->code)
@section('page_title', 'Detail Slot')

@section('content')
<div class="mx-auto max-w-5xl space-y-6 pb-16">
    <a href="{{ route('network.nodes.show', $slot->parent) }}" class="inline-flex min-h-10 items-center text-sm text-text-secondary hover:text-primary">&larr; Kembali ke {{ $slot->parent->code }}</a>

    @if(session('success'))<div role="status" class="border-l-4 border-emerald-500 py-2 pl-4 text-sm text-emerald-700">{{ session('success') }}</div>@endif
    @if($errors->any())<div role="alert" class="border-l-4 border-error py-2 pl-4 text-sm text-error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

    <section class="rounded-2xl border border-border bg-surface p-6 shadow-sm space-y-3">
        <p class="text-xs font-semibold uppercase tracking-wider text-text-muted">{{ $slotKind->label() }}</p>
        <h1 class="text-2xl font-black font-mono text-text-main">{{ $slot->displayName() }}</h1>
        <p class="text-xs text-text-muted">Jalur: <span class="font-semibold text-text-main">{{ $location->breadcrumb() }}</span></p>
        <dl class="grid gap-3 pt-2 text-xs sm:grid-cols-3">
            <div><dt class="text-text-muted">Status data</dt><dd class="font-semibold text-text-main">{{ $slot->record_status?->label() }}</dd></div>
            <div><dt class="text-text-muted">Kapasitas</dt><dd class="font-semibold text-text-main">{{ $ports->count() }} / {{ $slot->capacity ?? '?' }} {{ $slot->capacity_unit?->label() }}</dd></div>
            <div><dt class="text-text-muted">Koordinat</dt><dd class="text-text-secondary">Mengikuti perangkat {{ $slot->parent->code }}</dd></div>
        </dl>
    </section>

    @if($canManage)
    <form method="POST" action="{{ route('network.infrastructure-slots.capacity', $slot) }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-border bg-surface p-4">
        @csrf @method('PATCH')
        <label class="text-xs text-text-secondary">Kapasitas ({{ $slot->capacity_unit?->label() }})
            <input type="number" name="capacity" min="1" max="9999" required value="{{ $slot->capacity }}" placeholder="Belum didata" class="mt-1 block w-40 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-main">
        </label>
        <button type="submit" class="min-h-10 rounded-lg bg-primary px-4 text-sm font-semibold text-white">Simpan kapasitas slot</button>
    </form>
    @endif

    <section class="rounded-2xl border border-border bg-surface p-6 shadow-sm space-y-3">
        <h2 class="text-sm font-black uppercase tracking-wider text-text-main">{{ $slotKind->portKind()->label() }} pada slot ini</h2>
        @forelse($ports as $row)
            <a href="{{ route('network.infrastructure-ports.show', $row['port']) }}" class="flex items-center justify-between border-t border-border pt-2 text-sm hover:text-primary">
                <span class="font-mono font-semibold">{{ $row['port']->displayName() }}</span>
                <span class="text-xs text-text-muted">{{ $row['occupancy'] ?? $row['port']->record_status?->label() }}</span>
            </a>
        @empty
            <p class="text-xs text-text-muted">Slot kosong — belum ada {{ $slotKind->portKind()->label() }} yang didata.</p>
        @endforelse

        @if($canManage)
        <form method="POST" action="{{ route('network.infrastructure-slots.ports.store', $slot) }}" class="grid gap-3 border-t border-border pt-3 sm:grid-cols-4">
            @csrf
            <label class="text-xs text-text-secondary">Nomor {{ $slotKind->portKind()->label() }}
                <input type="number" name="number" min="1" required value="{{ old('number') }}" class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-main">
            </label>
            @if($slotKind === \App\Enums\InfrastructureSlotKind::OLT)
                <label class="text-xs text-text-secondary">Kapasitas (pelanggan aktif)
                    <input type="number" name="capacity" min="1" value="{{ old('capacity') }}" class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-main">
                </label>
                <label class="text-xs text-text-secondary">Ambang Red Zone (%)
                    <input type="number" name="red_zone_threshold" min="1" max="100" value="{{ old('red_zone_threshold') }}" class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-main">
                </label>
            @endif
            <button type="submit" class="min-h-10 rounded-lg bg-primary px-4 text-sm font-semibold text-white hover:bg-primary-hover sm:col-span-4 sm:justify-self-start">Tambah {{ $slotKind->portKind()->label() }}</button>
        </form>
        @endif
    </section>

    @include('network.positions._assets-history', ['directAssets' => $directAssets, 'history' => $history, 'actions' => $actions])
</div>
@endsection
