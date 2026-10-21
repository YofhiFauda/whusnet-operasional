@extends('layouts.app')

@section('title', $port->displayName().' · '.$port->slot->device->code)
@section('page_title', 'Detail '.$port->kind->label())

@section('content')
<div class="mx-auto max-w-5xl space-y-6 pb-16">
    <a href="{{ route('network.infrastructure-slots.show', $port->slot) }}" class="inline-flex min-h-10 items-center text-sm text-text-secondary hover:text-primary">&larr; Kembali ke {{ $port->slot->displayName() }}</a>

    @if(session('success'))<div role="status" class="border-l-4 border-emerald-500 py-2 pl-4 text-sm text-emerald-700">{{ session('success') }}</div>@endif
    @if($errors->any())<div role="alert" class="border-l-4 border-error py-2 pl-4 text-sm text-error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

    <section class="rounded-2xl border border-border bg-surface p-6 shadow-sm space-y-3">
        <p class="text-xs font-semibold uppercase tracking-wider text-text-muted">{{ $port->kind->label() }}</p>
        <h1 class="text-2xl font-black font-mono text-text-main">{{ $port->displayName() }}</h1>
        <p class="text-xs text-text-muted">Jalur: <span class="font-semibold text-text-main">{{ $location->breadcrumb() }}</span></p>
        <dl class="grid gap-3 pt-2 text-xs sm:grid-cols-3">
            <div><dt class="text-text-muted">Status data</dt><dd class="font-semibold text-text-main">{{ $port->status->label() }}</dd></div>
            <div><dt class="text-text-muted">Pemakaian</dt><dd class="font-semibold text-text-main">{{ $occupancy }}</dd></div>
            <div><dt class="text-text-muted">Koordinat</dt><dd class="text-text-secondary">Mengikuti perangkat {{ $port->slot->device->code }}</dd></div>
        </dl>
    </section>

    @if($capacity !== null)
    <section class="rounded-2xl border border-border bg-surface p-6 shadow-sm space-y-3">
        <h2 class="text-sm font-black uppercase tracking-wider text-text-main">Kapasitas PON</h2>
        <p class="text-sm text-text-main">
            <span class="font-mono font-bold">{{ $capacity['used'] }}</span>
            @if($capacity['capacity'] !== null) / <span class="font-mono font-bold">{{ $capacity['capacity'] }}</span> @endif
            {{ $capacity['unit_label'] }} terverifikasi
            @if($capacity['unverified'] > 0)<span class="text-text-muted">(+{{ $capacity['unverified'] }} pada koneksi belum terverifikasi, tidak dihitung)</span>@endif
        </p>
        @if($capacity['red_zone'] === null)
            <p class="text-xs text-text-muted">Kapasitas atau ambang belum terdata — status Red Zone belum bisa dinilai.</p>
        @elseif($capacity['red_zone'])
            <p class="text-xs font-semibold text-error">Red Zone: pemakaian ≥ {{ $capacity['threshold'] }}% kapasitas.</p>
        @else
            <p class="text-xs text-emerald-700">Di bawah ambang Red Zone {{ $capacity['threshold'] }}%.</p>
        @endif
        @if($canManage)
        <form method="POST" action="{{ route('network.infrastructure-ports.capacity', $port) }}" class="flex flex-wrap items-end gap-3 border-t border-border pt-3">
            @csrf
            @method('PATCH')
            <label class="text-xs text-text-secondary">Kapasitas (pelanggan aktif)
                <input type="number" name="capacity" min="1" value="{{ old('capacity', $port->capacity) }}" required class="mt-1 block w-36 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-main">
            </label>
            <label class="text-xs text-text-secondary">Ambang Red Zone (%)
                <input type="number" name="red_zone_threshold" min="1" max="100" value="{{ old('red_zone_threshold', $port->red_zone_threshold) }}" required class="mt-1 block w-36 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-main">
            </label>
            <button type="submit" class="min-h-10 rounded-lg bg-primary px-4 text-sm font-semibold text-white hover:bg-primary-hover">Simpan kapasitas</button>
        </form>
        @endif
    </section>
    @endif

    <section class="rounded-2xl border border-border bg-surface p-6 shadow-sm space-y-3">
        <h2 class="text-sm font-black uppercase tracking-wider text-text-main">Koneksi</h2>
        <p class="text-xs text-text-muted">{{ $port->kind === \App\Enums\InfrastructurePortKind::PON ? 'Satu PON dapat melayani beberapa ODP; satu ODP hanya punya satu uplink aktif.' : 'Jalur Switch berhenti di Port; tujuan dicatat sebagai sambungan fisik tersendiri.' }}</p>
        @forelse($connections as $connection)
            <div class="flex flex-wrap items-start justify-between gap-3 border-t border-border pt-3 text-sm">
                <div>
                    <p class="font-semibold text-text-main">
                        {{ $connection->targetNode?->code ?? $connection->targetPort?->displayName() ?? $connection->target_label ?? 'Tujuan tidak tercatat' }}
                        @if($connection->targetNode)<span class="font-normal text-text-muted">· {{ $connection->targetNode->name }}</span>@endif
                    </p>
                    <p class="text-xs {{ $connection->isVerified() ? 'text-emerald-700' : 'text-amber-700' }}">{{ $connection->isVerified() ? 'Terverifikasi' : 'Koneksi belum terverifikasi' }}</p>
                    @if($connection->active_key === null)<p class="text-xs text-text-muted">Dilepas {{ $connection->released_at?->format('d M Y H:i') }} — {{ $connection->release_note }}</p>@endif
                </div>
                @if($canManage && $connection->active_key === 1)
                <div class="flex flex-wrap gap-2" x-data="{ releasing: false }">
                    @unless($connection->isVerified())
                    <form method="POST" action="{{ route('network.infrastructure-connections.verify', $connection) }}">@csrf<button type="submit" class="min-h-9 rounded-lg border border-border px-3 text-xs font-semibold text-text-main">Tandai terverifikasi</button></form>
                    @endunless
                    <button type="button" @click="releasing = !releasing" class="min-h-9 rounded-lg border border-rose-300 px-3 text-xs font-semibold text-rose-700">Lepas</button>
                    <form x-show="releasing" x-cloak method="POST" action="{{ route('network.infrastructure-connections.release', $connection) }}" class="flex w-full gap-2">
                        @csrf
                        <input type="text" name="reason" required maxlength="500" placeholder="Alasan pelepasan" class="flex-1 rounded-lg border border-border bg-surface px-3 py-1.5 text-xs text-text-main">
                        <button type="submit" class="rounded-lg bg-rose-600 px-3 text-xs font-semibold text-white">Lepas</button>
                    </form>
                </div>
                @endif
            </div>
        @empty
            <p class="text-xs text-text-muted">Belum ada koneksi.</p>
        @endforelse

        @if($canManage)
        <form method="POST" action="{{ route('network.infrastructure-ports.connections.store', $port) }}" class="grid gap-3 border-t border-border pt-3 sm:grid-cols-3">
            @csrf
            @if($port->kind === \App\Enums\InfrastructurePortKind::PON)
                <label class="text-xs text-text-secondary sm:col-span-2">Hubungkan ODP
                    <select name="target_network_node_id" required class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-main">
                        <option value="">Pilih ODP satu Cabang yang belum ber-uplink</option>
                        @foreach($odpCandidates as $odp)<option value="{{ $odp->id }}">{{ $odp->code }} · {{ $odp->name }}</option>@endforeach
                    </select>
                </label>
            @else
                <label class="text-xs text-text-secondary sm:col-span-2">Tujuan sambungan (label)
                    <input type="text" name="target_label" maxlength="150" required placeholder="Contoh: Radio PTP gedung A" class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-main">
                </label>
            @endif
            <label class="flex items-center gap-2 text-xs text-text-secondary"><input type="checkbox" name="verified" value="1"> Sudah diverifikasi di lapangan</label>
            <button type="submit" class="min-h-10 rounded-lg bg-primary px-4 text-sm font-semibold text-white hover:bg-primary-hover sm:col-span-3 sm:justify-self-start">Catat sambungan</button>
        </form>
        @endif
    </section>

    @include('network.positions._assets-history', ['directAssets' => $directAssets, 'history' => $history, 'actions' => $actions])
</div>
@endsection
