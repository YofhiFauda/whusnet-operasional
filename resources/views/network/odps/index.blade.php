@extends('layouts.app')

@section('title', 'Master ODP dan Port')
@section('page_title', 'Master ODP dan Port')

@section('content')
<div class="mx-auto max-w-6xl space-y-5">
    <div class="rounded-xl border border-border bg-surface p-5">
        <h1 class="text-xl font-bold text-text-main">Master ODP dan Port</h1>
        <p class="mt-1 text-sm text-text-muted">ODP adalah data infrastruktur terpisah dari label Distribusi pembentuk CID. Kapasitas dihitung dari jumlah port child, dan pemakaian port dicatat manual.</p>
    </div>

    @can('network_odps.create')
    <form method="POST" action="{{ route('network.odps.store') }}" class="grid gap-3 rounded-xl border border-border bg-surface p-5 sm:grid-cols-2 lg:grid-cols-5">
        @csrf
        <label class="text-xs font-semibold text-text-secondary">POP
            <select name="pop_id" required class="mt-1 block w-full rounded-lg border border-border bg-surface px-3 py-2 text-text-main">
                <option value="">Pilih POP</option>
                @foreach($pops as $pop)
                <option value="{{ $pop->id }}" @selected(old('pop_id') == $pop->id)>{{ $pop->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-xs font-semibold text-text-secondary">Kode ODP
            <input name="code" value="{{ old('code') }}" required maxlength="50" class="mt-1 block w-full rounded-lg border border-border bg-surface px-3 py-2 text-text-main">
        </label>
        <label class="text-xs font-semibold text-text-secondary">Nama ODP
            <input name="name" value="{{ old('name') }}" required maxlength="150" class="mt-1 block w-full rounded-lg border border-border bg-surface px-3 py-2 text-text-main">
        </label>
        <label class="text-xs font-semibold text-text-secondary">Kapasitas Port
            <input type="number" name="capacity" value="{{ old('capacity', 8) }}" min="1" max="128" required class="mt-1 block w-full rounded-lg border border-border bg-surface px-3 py-2 text-text-main">
        </label>
        <button type="submit" class="self-end rounded-lg bg-primary px-4 py-2 text-xs font-bold text-white">Tambah ODP</button>
        @if($errors->any())
        <p class="text-xs text-error sm:col-span-2 lg:col-span-5">{{ $errors->first() }}</p>
        @endif
    </form>
    @endcan

    <div class="space-y-4">
        @forelse($odps as $odp)
        @php $usagePercent = $odp->ports_count > 0 ? round($odp->used_ports_count / $odp->ports_count * 100) : 0; @endphp
        <section class="rounded-xl border border-border bg-surface p-5" id="odp-{{ $odp->id }}">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-text-main">{{ $odp->code }} · {{ $odp->name }}</h2>
                    <p class="text-xs text-text-muted">{{ $odp->pop?->name }} · {{ $odp->used_ports_count }}/{{ $odp->ports_count }} port terpakai</p>
                </div>
                <span class="rounded-lg border px-2 py-1 text-xs font-bold {{ $usagePercent >= 85 ? 'border-error-border bg-error-bg text-error' : 'border-border bg-surface-muted text-text-secondary' }}">
                    {{ $usagePercent >= 85 ? 'Red Zone · ' : '' }}{{ $usagePercent }}%
                </span>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                @foreach($odp->ports as $port)
                @can('network_odps.update')
                <form method="POST" action="{{ route('network.odp-ports.update', $port) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="is_used" value="{{ $port->is_used ? 0 : 1 }}">
                    <button type="submit" class="rounded-lg border px-3 py-2 text-xs font-semibold {{ $port->is_used ? 'border-error-border bg-error-bg text-error' : 'border-success-border bg-success-bg text-success' }}" title="Ubah status port {{ $port->number }}">
                        Port {{ $port->number }} · {{ $port->is_used ? 'Terpakai' : 'Kosong' }}
                    </button>
                </form>
                @else
                <span class="rounded-lg border border-border px-3 py-2 text-xs text-text-secondary">Port {{ $port->number }} · {{ $port->is_used ? 'Terpakai' : 'Kosong' }}</span>
                @endcan
                @endforeach
            </div>
            @can('network_odps.update')
            <form method="POST" action="{{ route('network.odps.ports.add', $odp) }}" class="mt-4 flex items-end gap-2 border-t border-border pt-4">
                @csrf
                <label class="text-xs font-semibold text-text-secondary">Tambah kapasitas
                    <input type="number" name="additional_capacity" min="1" max="128" required class="mt-1 block w-24 rounded-lg border border-border bg-surface px-3 py-2 text-text-main">
                </label>
                <button type="submit" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-text-main">Tambah Port</button>
            </form>
            @endcan
        </section>
        @empty
        <div class="rounded-xl border border-border bg-surface p-8 text-center text-sm text-text-muted">Belum ada ODP pada POP yang dapat Anda akses.</div>
        @endforelse
    </div>
    {{ $odps->links() }}
</div>
@endsection
