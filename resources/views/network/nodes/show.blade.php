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

        <div class="flex items-center gap-3">
            @can('network_nodes.delete')
            <form method="POST" action="{{ route('network.nodes.destroy', $node) }}" onsubmit="return confirm('Hapus node kosong? Node yang punya relasi akan dinonaktifkan.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-xl border border-rose-300 px-3 py-1.5 text-xs font-bold text-rose-700 hover:bg-rose-50">Hapus / Nonaktifkan</button>
            </form>
            @endcan
            <div class="flex items-center gap-2 text-xs font-mono">
            <span class="text-text-muted">Node ID:</span>
            <span class="font-bold text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/60 px-2.5 py-0.5 rounded-md border border-sky-200 dark:border-sky-900">{{ $node->code }}</span>
            </div>
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
            <div class="flex items-center gap-2">
                <span class="text-xs text-text-muted">{{ $node->deployedSerials->count() }} unit serial</span>
                @can('network_nodes.register_infra')
                <a href="{{ route('network.nodes.register-asset.create', $node) }}"
                   class="inline-flex items-center gap-1 rounded-lg border border-sky-300 px-2.5 py-1 text-xs font-semibold text-sky-700 dark:text-sky-300 hover:bg-sky-50 dark:hover:bg-sky-950/50 transition-colors">
                    Registrasi Aset Lama
                </a>
                @endcan
            </div>
        </div>
        @if($node->deployedSerials->isEmpty() && $node->infrastructureBalances->where('qty', '>', 0)->isEmpty())
            <p class="text-xs text-text-muted">Belum ada aset yang dicatat terpasang pada node ini.</p>
        @else
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach($node->deployedSerials as $serial)
                    <div class="rounded-xl border border-border bg-surface-muted/60 p-3 text-xs">
                        <p class="font-semibold text-text-main">{{ $serial->item?->name ?: 'Barang tanpa nama' }}</p>
                        <a href="{{ route('warehouse.traceability.index', ['sn' => $serial->serial_number]) }}"
                           class="mt-1 font-mono text-sky-700 dark:text-sky-300 font-bold hover:underline inline-flex items-center gap-1"
                           title="Lihat riwayat pergerakan barang">
                            SN {{ $serial->serial_number }} &nearr;
                        </a>
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
    @if(in_array($node->nodeType?->code, ['olt_chassis', 'switch_mini_pop'], true))
    {{-- Slot kanonis (rancangan hierarki §2/§16.2): OLT → Slot → PON, Switch → Slot → Port. --}}
    <section class="rounded-2xl border border-border bg-surface p-6 shadow-sm space-y-4">
        <div>
            <h2 class="text-sm font-black uppercase tracking-wider text-text-main">Slot perangkat</h2>
            <p class="text-xs text-text-muted">{{ $node->infrastructureSlots->count() }} slot didata. Slot boleh kosong; kartu/modul dicatat sebagai aset terpasang di Slot.</p>
        </div>
        @forelse($node->infrastructureSlots as $infraSlot)
            <a href="{{ route('network.infrastructure-slots.show', $infraSlot) }}" class="flex items-center justify-between border-t border-border pt-2 text-sm hover:text-primary">
                <span class="font-mono font-semibold">{{ $infraSlot->displayName() }}</span>
                <span class="text-xs text-text-muted">{{ $infraSlot->ports_count }} {{ $infraSlot->kind->portKind()->label() }} · {{ $infraSlot->status->label() }}</span>
            </a>
        @empty
            <p class="text-xs text-text-muted">Belum ada slot.</p>
        @endforelse
        @can('network_nodes.update')
        <form method="POST" action="{{ route('network.nodes.infrastructure-slots.store', $node) }}" class="grid gap-3 border-t border-border pt-3 sm:grid-cols-4">
            @csrf
            <label class="text-xs text-text-secondary">Nomor slot
                <input type="number" name="number" min="1" required value="{{ old('number') }}" class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-main">
            </label>
            <label class="text-xs text-text-secondary">Kode (opsional)
                <input type="text" name="code" maxlength="50" value="{{ old('code') }}" class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-main">
            </label>
            <label class="text-xs text-text-secondary">Kapasitas ({{ $node->nodeType?->code === 'olt_chassis' ? 'PON' : 'port' }} per slot)
                <input type="number" name="capacity" min="1" value="{{ old('capacity') }}" class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-main">
            </label>
            @if($node->nodeType?->code === 'switch_mini_pop')
            <label class="flex items-center gap-2 text-xs text-text-secondary"><input type="checkbox" name="is_logical" value="1"> Slot internal/logis (switch port tetap)</label>
            @endif
            <button type="submit" class="min-h-10 rounded-lg bg-primary px-4 text-sm font-semibold text-white hover:bg-primary-hover sm:col-span-4 sm:justify-self-start">Tambah slot</button>
            @error('number')<p class="text-xs text-error sm:col-span-4">{{ $message }}</p>@enderror
        </form>
        @endcan
    </section>
    @endif
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
    <section class="rounded-2xl border border-border bg-surface p-6 shadow-sm space-y-5">
        {{-- Header & Kapasitas Ringkas --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-border pb-4">
            <div>
                <h2 class="text-sm font-black uppercase tracking-wider text-text-main flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-sky-500"></span>
                    Port &amp; Penghuni
                </h2>
                <p class="text-xs text-text-muted mt-0.5">Visual soket port splitter/ODP. Klik atau gunakan form pada port kosong untuk menyambungkan pelanggan.</p>
            </div>
            @php
                $usedPortsCount = $node->ports->where('is_used', true)->count();
                $totalPortsCount = $node->ports->count();
                $freePortsCount = $totalPortsCount - $usedPortsCount;
                $usedPercent = $totalPortsCount > 0 ? round(($usedPortsCount / $totalPortsCount) * 100) : 0;
            @endphp
            <div class="flex items-center gap-2 text-xs flex-wrap">
                <span class="px-2.5 py-1 rounded-lg bg-surface-muted border border-border font-bold text-text-main">
                    Total: {{ $totalPortsCount }} Port
                </span>
                <span class="px-2.5 py-1 rounded-lg bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 text-rose-700 dark:text-rose-300 font-bold">
                    {{ $usedPortsCount }} / {{ $totalPortsCount }} terpakai ({{ $usedPercent }}%)
                </span>
                <span class="px-2.5 py-1 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900 text-emerald-700 dark:text-emerald-300 font-bold">
                    {{ $freePortsCount }} Kosong
                </span>
            </div>
        </div>

        {{-- Visual Grid Port Matrix --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3.5">
            @foreach($node->ports->sortBy('number') as $port)
            @php($assignment = $port->activeAssignment->first())
            <div class="rounded-xl border transition-all shadow-2xs flex flex-col justify-between
                        {{ $port->is_used
                            ? ($assignment?->customer
                                ? 'border-sky-300/80 bg-sky-50/40 dark:border-sky-900/80 dark:bg-sky-950/20'
                                : ($assignment?->targetNode
                                    ? 'border-amber-300/80 bg-amber-50/40 dark:border-amber-900/80 dark:bg-amber-950/20'
                                    : 'border-rose-300/80 bg-rose-50/40 dark:border-rose-900/80 dark:bg-rose-950/20'))
                            : 'border-emerald-200 bg-emerald-50/20 dark:border-emerald-900/60 dark:bg-emerald-950/10' }} p-3.5 space-y-3">

                {{-- Header Socket --}}
                <div class="flex items-center justify-between gap-1.5 border-b border-border/60 pb-2">
                    <span class="font-mono font-bold text-xs text-text-main flex items-center gap-1">
                        Port {{ $port->number }}
                        <span class="text-[10px] text-text-muted font-normal">(#{{ $port->number }})</span>
                    </span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider
                                {{ $port->is_used
                                    ? ($assignment?->customer
                                        ? 'bg-sky-100 text-sky-800 dark:bg-sky-900/50 dark:text-sky-300'
                                        : ($assignment?->targetNode
                                            ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300'
                                            : 'bg-rose-100 text-rose-800 dark:bg-rose-900/50 dark:text-rose-300'))
                                    : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300' }}">
                        {{ $port->is_used ? 'Terpakai' : 'Kosong' }}
                    </span>
                </div>

                {{-- Body Info Socket --}}
                <div class="text-xs space-y-1.5 flex-1">
                    @if($assignment?->customer)
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted block">Penghuni Port</span>
                            <span class="font-bold text-text-main">Pelanggan: {{ $assignment->customer->full_name }}</span>
                            @if($assignment->customer->customer_code || $assignment->customer->cid)
                                <span class="block text-[11px] font-mono text-text-muted">{{ $assignment->customer->customer_code ?: $assignment->customer->cid }}</span>
                            @endif
                        </div>
                        @if($canViewDeviceSerials && ($allowedCustomerPopIds === null || in_array($assignment->customer->pop_id, $allowedCustomerPopIds)))
                            <div class="pt-1 border-t border-border/40">
                            @forelse($assignment->customer->networkPortModems() as $modem)
                                <div class="mt-1 p-1.5 rounded bg-surface/80 border border-border/60 text-[11px] text-text-secondary">
                                    <span class="font-semibold text-text-main">Modem/ONT: {{ $modem['name'] }}</span>
                                    <span class="block font-mono text-[10px] text-sky-700 dark:text-sky-300">SN {{ $modem['serial_number'] }}</span>
                                    <span class="text-[9px] text-text-muted">({{ $modem['source'] }})</span>
                                </div>
                            @empty
                                <span class="block text-[11px] text-amber-700 dark:text-amber-400 font-medium">Modem belum tercatat</span>
                            @endforelse
                            </div>
                        @else
                            <span class="block text-[11px] text-text-muted italic">SN modem dibatasi oleh izin perangkat pelanggan</span>
                        @endif
                    @elseif($assignment?->targetNode)
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted block">Jalur Uplink / Turunan</span>
                            <span class="font-bold text-text-main">Node: {{ $assignment->targetNode->code }} · {{ $assignment->targetNode->name }}</span>
                            @if($assignment->targetPort)
                                <span class="block text-[11px] font-mono text-text-secondary">port {{ $assignment->targetPort->number }}</span>
                            @endif
                        </div>
                    @elseif($assignment?->manual_label)
                        <div class="text-text-main">
                            <span class="font-semibold">{{ $assignment->manual_label }}</span>
                            <span class="block text-[11px] text-amber-700 dark:text-amber-400 font-medium">Belum terverifikasi</span>
                        </div>
                    @elseif($port->is_used)
                        <span class="text-amber-700 dark:text-amber-400 text-xs font-medium">Belum terverifikasi</span>
                    @else
                        <div class="py-2 text-center text-emerald-700 dark:text-emerald-400 font-medium text-xs">
                            Port siap disambungkan
                        </div>
                    @endif
                </div>

                {{-- Action Panel Socket --}}
                @can('network_nodes.update')
                <div class="pt-2 border-t border-border/60">
                    @if($port->is_used)
                        <details class="group text-xs">
                            <summary class="flex items-center justify-between cursor-pointer text-rose-600 dark:text-rose-400 hover:text-rose-700 font-bold py-1">
                                <span>Kosongkan port</span>
                                <span class="text-[10px] group-open:rotate-180 transition-transform">&darr;</span>
                            </summary>
                            <form method="POST" action="{{ route('network.node-ports.update', $port) }}" class="mt-2 space-y-2 p-2.5 rounded-lg border border-rose-200 dark:border-rose-900 bg-rose-50/60 dark:bg-rose-950/40">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="is_used" value="0">
                                <label class="flex items-start gap-1.5 text-text-secondary text-[11px]">
                                    <input type="checkbox" name="physical_removal_confirmed" value="1" required checked>
                                    Pelepasan fisik sudah dilakukan
                                </label>
                                <input name="release_note" required maxlength="500" value="Pelepasan port kabel fisik" placeholder="Catatan pelepasan fisik" class="w-full rounded-md border border-border bg-background px-2 py-1 text-xs text-text-main">
                                <button type="submit" class="w-full rounded-md bg-rose-600 hover:bg-rose-700 text-white font-bold py-1.5 text-xs shadow-2xs cursor-pointer">
                                    Konfirmasi Lepas Port
                                </button>
                            </form>
                        </details>
                    @else
                        {{-- Opsi Sambung Pelanggan Langsung (jika ODP dan ada pelanggan tersedia) --}}
                        @if($node->nodeType?->code === 'odp' && $availableCustomers->isNotEmpty())
                            <form method="POST" action="{{ route('network.node-ports.assign-customer', $port) }}" class="space-y-1.5">
                                @csrf
                                <select name="customer_id" required class="w-full rounded-md border border-border bg-background px-2 py-1.5 text-xs text-text-main focus:ring-2 focus:ring-emerald-500/30">
                                    <option value="">Pilih Pelanggan...</option>
                                    @foreach($availableCustomers as $cust)
                                        <option value="{{ $cust->id }}">{{ $cust->full_name }} ({{ $cust->customer_code ?: ($cust->cid ?: '#'.$cust->id) }})</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="w-full rounded-md bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-1.5 text-xs shadow-2xs transition-colors cursor-pointer">
                                    + Sambung Pelanggan
                                </button>
                            </form>
                        @elseif($node->children->isNotEmpty())
                            <form method="POST" action="{{ route('network.node-ports.assign-node', $port) }}" class="space-y-1.5">
                                @csrf
                                <select name="target_node_id" required class="w-full rounded-md border border-border bg-background px-2 py-1.5 text-xs text-text-main">
                                    <option value="">Pilih anak langsung</option>
                                    @foreach($node->children as $child)
                                    <option value="{{ $child->id }}">{{ $child->code }} · {{ $child->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="w-full rounded-md bg-sky-600 hover:bg-sky-700 text-white font-bold py-1.5 text-xs shadow-2xs transition-colors cursor-pointer">
                                    Hubungkan
                                </button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('network.node-ports.update', $port) }}">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="is_used" value="1">
                                <button type="submit" class="w-full rounded-md border border-border bg-surface hover:bg-surface-muted text-text-main font-semibold py-1.5 text-xs transition-colors cursor-pointer">
                                    Tandai Terpakai Manual
                                </button>
                            </form>
                        @endif
                    @endif
                </div>
                @endcan
            </div>
            @endforeach
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
            <label class="text-xs font-bold text-text-secondary">Kode Node <span class="text-rose-500">*</span>
                <input name="code" required maxlength="50" value="{{ old('code', $node->code) }}" class="mt-1 block w-full rounded-xl border border-border bg-background px-3.5 py-2 text-text-main text-xs font-mono">
            </label>
            <label class="text-xs font-bold text-text-secondary">Tipe Node <span class="text-rose-500">*</span>
                <select name="node_type_id" required class="mt-1 block w-full rounded-xl border border-border bg-background px-3.5 py-2 text-text-main text-xs">
                    @foreach($nodeTypes as $type)
                    <option value="{{ $type->id }}" @selected((int) old('node_type_id', $node->node_type_id) === $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
            </label>
            <p class="sm:col-span-2 text-xs text-text-muted">Kode dan tipe hanya dapat diubah sebelum node memiliki port atau relasi lain.</p>
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
