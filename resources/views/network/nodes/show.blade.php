@extends('layouts.app')

@section('title', $node->code.' · Detail Node Jaringan')
@section('page_title', 'Detail Node Jaringan')

@section('content')
<div class="mx-auto max-w-5xl space-y-6 pb-16">

    {{-- Top Back & Breadcrumb --}}
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <a href="{{ route('network.nodes.index') }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-border bg-surface hover:bg-surface-muted text-xs font-bold text-text-secondary hover:text-text-main transition-colors shadow-2xs">
            <svg class="h-3.5 w-3.5 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            &larr; Kembali ke pohon infrastruktur
        </a>

        <div class="flex items-center gap-2 text-xs font-mono">
            <span class="text-text-muted">Node ID:</span>
            <span class="font-bold text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/60 px-2.5 py-0.5 rounded-md border border-sky-200 dark:border-sky-900">{{ $node->code }}</span>
        </div>
    </div>

    {{-- Node Header & Summary Card --}}
    <section class="rounded-2xl border border-border bg-surface p-6 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 border-b border-border pb-4">
            <div class="space-y-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-0.5 rounded-md bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300 border border-sky-200 dark:border-sky-800">
                        {{ $node->nodeType?->name }}
                    </span>
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md text-[10px] font-bold {{ $node->status === 'active' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-200' : 'bg-slate-100 text-slate-700 border border-border' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $node->status === 'active' ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                        {{ ucfirst($node->status) }}
                    </span>
                </div>
                <h1 class="text-2xl font-black font-mono tracking-tight text-text-main">
                    {{ $node->code }} · {{ $node->name }}
                </h1>
                <p class="text-xs text-text-muted font-medium">
                    Jalur Hierarki: <span class="text-text-main font-semibold">{{ $node->breadcrumbLabel() }}</span>
                </p>
            </div>
        </div>

        {{-- Effective Location Grid --}}
        <div class="grid gap-4 sm:grid-cols-3 pt-2 text-xs">
            <div class="p-3.5 rounded-xl bg-surface-muted/60 dark:bg-slate-900/40 border border-border space-y-1">
                <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted block">Alamat Efektif</span>
                <p class="font-semibold text-text-main">{{ $location['address'] ?: 'Belum dicatat' }}</p>
            </div>
            <div class="p-3.5 rounded-xl bg-surface-muted/60 dark:bg-slate-900/40 border border-border space-y-1">
                <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted block">Wilayah Administratif</span>
                <p class="font-semibold text-text-main">{{ collect([$location['village'], $location['district'], $location['city']])->filter()->implode(', ') ?: 'Belum dicatat' }}</p>
            </div>
            <div class="p-3.5 rounded-xl bg-surface-muted/60 dark:bg-slate-900/40 border border-border space-y-1">
                <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted block">Koordinat Geospasial</span>
                <p class="font-mono font-semibold text-sky-600 dark:text-sky-400">
                    @if($location['latitude'] !== null && $location['longitude'] !== null)
                        <a href="https://maps.google.com/?q={{ $location['latitude'] }},{{ $location['longitude'] }}" target="_blank" rel="noopener" class="hover:underline">
                            {{ $location['latitude'] }}, {{ $location['longitude'] }} &nearr;
                        </a>
                    @else
                        <span class="text-text-muted italic">Belum dicatat</span>
                    @endif
                </p>
            </div>
        </div>

        <p class="text-[11px] text-text-muted pt-1 italic">
            {{ $location['inherited_fields'] === [] ? 'Lokasi dicatat pada node ini' : 'Sebagian data lokasi diwarisi dari induk/POP' }}
        </p>
    </section>

    <section class="rounded-2xl border border-border bg-surface p-6 shadow-sm space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-black uppercase tracking-wider text-text-main">Aset Gudang Terpasang</h2>
            <span class="text-xs text-text-muted">{{ $node->deployedSerials->count() }} unit serial</span>
        </div>
        @if($node->deployedSerials->isEmpty() && $node->infrastructureBalances->where('qty', '>', 0)->isEmpty())
            <p class="text-xs text-text-muted">Belum ada aset yang dicatat terpasang pada node ini.</p>
        @else
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach($node->deployedSerials as $serial)
                    <div class="rounded-xl border border-border bg-surface-muted/60 p-3 text-xs">
                        <p class="font-semibold text-text-main">{{ $serial->item?->name ?: 'Barang tanpa nama' }}</p>
                        <p class="mt-1 font-mono text-sky-700 dark:text-sky-300">SN {{ $serial->serial_number }}</p>
                    </div>
                @endforeach
                @foreach($node->infrastructureBalances->where('qty', '>', 0) as $balance)
                    <div class="rounded-xl border border-border bg-surface-muted/60 p-3 text-xs">
                        <p class="font-semibold text-text-main">{{ $balance->item?->name ?: 'Barang tanpa nama' }}</p>
                        <p class="mt-1 text-text-secondary">{{ $balance->qty }} {{ $balance->item?->unit }} @if($balance->lot_no) · Lot {{ $balance->lot_no }} @endif</p>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
    @if($node->nodeType?->code === 'olt_chassis')
    <section class="rounded-2xl border border-border bg-surface p-6 shadow-sm space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-black uppercase tracking-wider text-text-main">OLT &middot; Slot dan Kartu</h2>
                <p class="text-xs text-text-muted">{{ $node->oltSlots->filter(fn ($slot) => $slot->cardNode?->deployedSerials->isNotEmpty())->count() }} / {{ $node->oltSlots->count() }} slot memiliki kartu terpasang.</p>
            </div>
            @can('network_nodes.update')
            <form method="POST" action="{{ route('network.nodes.olt-slots.store', $node) }}" class="flex items-end gap-2">
                @csrf
                <label class="text-xs font-semibold text-text-secondary">Total slot
                    <input type="number" name="slot_count" min="1" max="64" value="{{ max(1, $node->oltSlots->count()) }}" required class="mt-1 block w-24 rounded-lg border border-border bg-background px-2 py-2 text-text-main">
                </label>
                <button class="rounded-lg bg-sky-600 px-3 py-2 text-xs font-bold text-white">Tambah Slot</button>
            </form>
            @endcan
        </div>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach($node->oltSlots as $slot)
            <article class="rounded-xl border border-border p-3 text-xs">
                <p class="text-[10px] font-bold uppercase text-text-muted">Slot {{ $slot->number }}</p>
                @if($slot->cardNode)
                <a href="{{ route('network.nodes.show', $slot->cardNode) }}" class="mt-1 block text-sm font-semibold text-sky-700 dark:text-sky-400">{{ $slot->cardNode->code }} &middot; {{ $slot->cardNode->name }}</a>
                <p class="text-text-muted">{{ $slot->cardNode->deployedSerials->count() ? 'Kartu terpasang' : 'Node kartu belum memiliki aset Gudang' }}</p>
                <p class="text-text-muted">{{ $slot->cardNode->ports->where('is_used', true)->count() }} / {{ $slot->cardNode->ports->count() }} port PON terpakai</p>
                @else
                <p class="mt-1 text-sm text-text-muted">Kosong</p>
                @can('network_nodes.update')
                <form method="POST" action="{{ route('network.nodes.olt-slots.card', [$node, $slot]) }}" class="mt-2 space-y-2">
                    @csrf
                    <select name="network_node_id" required class="w-full rounded-lg border border-border bg-background px-2 py-2 text-xs text-text-main">
                        <option value="">Pilih kartu OLT</option>
                        @foreach($cardOptions as $card)
                        <option value="{{ $card->id }}">{{ $card->code }} &middot; {{ $card->name }}</option>
                        @endforeach
                    </select>
                    <button class="rounded-lg border border-sky-600 px-2 py-1 text-xs font-semibold text-sky-700">Tempatkan</button>
                </form>
                @endcan
                @endif
            </article>
            @endforeach
        </div>
        @if($errors->any())<p class="text-xs text-rose-600">{{ $errors->first() }}</p>@endif
    </section>
    @endif
    @if($node->ports->isNotEmpty())
    <section class="rounded-2xl border border-border bg-surface p-6 shadow-sm space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-black uppercase tracking-wider text-text-main">Port &amp; Penghuni</h2>
            <span class="text-xs text-text-muted">{{ $node->ports->where('is_used', true)->count() }} / {{ $node->ports->count() }} terpakai</span>
        </div>
        <div class="overflow-x-auto rounded-xl border border-border">
            <table class="w-full text-left text-xs">
                <thead class="bg-surface-muted text-text-secondary uppercase text-[10px] tracking-wider">
                    <tr><th class="px-4 py-3">Port</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Penghuni / Tujuan</th><th class="px-4 py-3">Tindakan</th></tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($node->ports->sortBy('number') as $port)
                    @php($assignment = $port->activeAssignment->first())
                    <tr>
                        <td class="px-4 py-3 font-mono font-bold text-text-main">{{ $port->number }}</td>
                        <td class="px-4 py-3">{{ $port->is_used ? 'Terpakai' : 'Kosong' }}</td>
                        <td class="px-4 py-3 text-text-main">
                            @if($assignment?->customer)
                                Pelanggan: {{ $assignment->customer->full_name }}
                            @elseif($assignment?->targetNode)
                                Node: {{ $assignment->targetNode->code }} · {{ $assignment->targetNode->name }}
                            @elseif($assignment?->manual_label)
                                {{ $assignment->manual_label }} <span class="ml-2 text-amber-700 dark:text-amber-400">Belum terverifikasi</span>
                            @elseif($port->is_used)
                                <span class="text-amber-700 dark:text-amber-400">Belum terverifikasi</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @can('network_nodes.update')
                            @if($port->is_used)
                            <form method="POST" action="{{ route('network.node-ports.update', $port) }}" class="space-y-2 min-w-56">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="is_used" value="0">
                                <label class="flex items-start gap-2 text-text-secondary">
                                    <input type="checkbox" name="physical_removal_confirmed" value="1" required>
                                    Pelepasan fisik sudah dilakukan
                                </label>
                                <input name="release_note" required maxlength="500" placeholder="Catatan pelepasan fisik" class="w-full rounded-lg border border-border bg-background px-2 py-1.5 text-text-main">
                                <button type="submit" class="rounded-lg border border-rose-300 px-2 py-1 font-semibold text-rose-700 dark:text-rose-300">Kosongkan port</button>
                            </form>
                            @elseif($node->children->isNotEmpty())
                            <form method="POST" action="{{ route('network.node-ports.assign-node', $port) }}" class="flex flex-wrap items-center gap-2 min-w-56">
                                @csrf
                                <select name="target_node_id" required class="min-w-40 rounded-lg border border-border bg-background px-2 py-1.5 text-text-main">
                                    <option value="">Pilih anak langsung</option>
                                    @foreach($node->children as $child)
                                    <option value="{{ $child->id }}">{{ $child->code }} · {{ $child->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="rounded-lg border border-sky-300 px-2 py-1.5 font-semibold text-sky-700 dark:text-sky-300">Hubungkan</button>
                            </form>
                            @else
                            —
                            @endif
                            @endcan
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
    @endif
    {{-- Edit Node Details Form --}}
    @can('network_nodes.update')
    <form method="POST" action="{{ route('network.nodes.update', $node) }}"
          class="rounded-2xl border border-border bg-surface p-6 shadow-sm space-y-5">
        @csrf
        @method('PATCH')

        <div class="flex items-center gap-2 border-b border-border pb-3">
            <span class="w-1.5 h-3.5 bg-sky-600 rounded-full"></span>
            <h2 class="text-sm font-black uppercase tracking-wider text-text-main">Edit Informasi &amp; Lokasi Node</h2>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="text-xs font-bold text-text-secondary">Nama Node <span class="text-rose-500">*</span>
                <input name="name" required value="{{ old('name', $node->name) }}"
                       class="mt-1 block w-full rounded-xl border border-border bg-background px-3.5 py-2 text-text-main text-xs focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
            </label>

            <label class="text-xs font-bold text-text-secondary">Status Operasional <span class="text-rose-500">*</span>
                <select name="status" class="mt-1 block w-full rounded-xl border border-border bg-background px-3.5 py-2 text-text-main text-xs focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                    <option value="active" @selected($node->status === 'active')>Aktif</option>
                    <option value="inactive" @selected($node->status === 'inactive')>Nonaktif</option>
                </select>
            </label>

            <label class="text-xs font-bold text-text-secondary sm:col-span-2">Alamat Fisik / Titik Tiang
                <textarea name="address" rows="2" class="mt-1 block w-full rounded-xl border border-border bg-background px-3.5 py-2 text-text-main text-xs focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">{{ old('address', $node->address) }}</textarea>
            </label>

            @foreach(['village' => 'Desa/Kelurahan', 'district' => 'Kecamatan', 'city' => 'Kota/Kabupaten', 'latitude' => 'Latitude', 'longitude' => 'Longitude'] as $field => $label)
            <label class="text-xs font-bold text-text-secondary">{{ $label }}
                <input name="{{ $field }}" value="{{ old($field, $node->$field) }}"
                       class="mt-1 block w-full rounded-xl border border-border bg-background px-3.5 py-2 text-text-main text-xs font-mono focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
            </label>
            @endforeach
        </div>

        @if($errors->any())
        <div class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-900 text-xs font-semibold text-rose-600 dark:text-rose-400">
            {{ $errors->first() }}
        </div>
        @endif

        <div class="pt-4 border-t border-border flex items-center justify-end">
            <button type="submit" class="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary-hover text-white text-xs font-bold shadow-xs active:scale-95 transition-all cursor-pointer">
                Simpan Detail
            </button>
        </div>
    </form>
    @endcan

</div>
@endsection
