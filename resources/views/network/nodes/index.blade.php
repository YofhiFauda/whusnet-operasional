@extends('layouts.app')

@section('title', 'Master Node Jaringan dan Port')
@section('page_title', 'Master Node Jaringan dan Port')

@section('content')
<div class="mx-auto max-w-6xl space-y-5">
    <div class="rounded-xl border border-border bg-surface p-5">
        <h1 class="text-xl font-bold text-text-main">Master Node Jaringan dan Port</h1>
        <p class="mt-1 text-sm text-text-muted">Node jaringan (ODC, ODP, dst) adalah data infrastruktur terpisah dari label Distribusi pembentuk CID. Kapasitas dihitung dari jumlah port child, dan pemakaian port dicatat manual.</p>
        <a href="{{ route('network.inventaris.index') }}" class="mt-3 inline-block text-xs font-semibold text-primary hover:underline">Lihat Inventaris Infrastruktur →</a>
    </div>

    @can('network_nodes.create')
    <form method="POST" action="{{ route('network.nodes.store') }}"
          x-data="{ placement: @js(old('parent_id') ? 'node' : 'pop'), hasPorts: @js((bool) ($nodeTypes->firstWhere('id', (int) old('node_type_id'))?->has_ports ?? true)) }"
          class="grid gap-3 rounded-xl border border-border bg-surface p-5 sm:grid-cols-2 lg:grid-cols-6">
        @csrf
        <label class="text-xs font-semibold text-text-secondary">Tipe Node
            <select name="node_type_id" required x-on:change="hasPorts = $event.target.selectedOptions[0]?.dataset.hasPorts === '1'"
                    class="mt-1 block w-full rounded-lg border border-border bg-surface px-3 py-2 text-text-main">
                <option value="">Pilih Tipe</option>
                @foreach($nodeTypes as $nodeType)
                <option value="{{ $nodeType->id }}" data-has-ports="{{ $nodeType->has_ports ? '1' : '0' }}"
                        @selected(old('node_type_id') == $nodeType->id)>{{ $nodeType->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-xs font-semibold text-text-secondary">Penempatan
            <select x-model="placement" class="mt-1 block w-full rounded-lg border border-border bg-surface px-3 py-2 text-text-main">
                <option value="pop">Langsung di POP</option>
                <option value="node">Di dalam node lain</option>
            </select>
        </label>
        <label x-show="placement === 'pop'" class="text-xs font-semibold text-text-secondary">POP
            <select name="anchor_pop_id" x-bind:disabled="placement !== 'pop'" x-bind:required="placement === 'pop'"
                    class="mt-1 block w-full rounded-lg border border-border bg-surface px-3 py-2 text-text-main">
                <option value="">Pilih POP</option>
                @foreach($pops as $pop)
                <option value="{{ $pop->id }}" @selected(old('anchor_pop_id') == $pop->id)>{{ $pop->name }}</option>
                @endforeach
            </select>
        </label>
        <label x-show="placement === 'node'" class="text-xs font-semibold text-text-secondary">Node Induk
            <select name="parent_id" x-bind:disabled="placement !== 'node'" x-bind:required="placement === 'node'"
                    class="mt-1 block w-full rounded-lg border border-border bg-surface px-3 py-2 text-text-main">
                <option value="">Pilih Node Induk</option>
                @foreach($parentOptions as $parentOption)
                <option value="{{ $parentOption->id }}" @selected(old('parent_id') == $parentOption->id)>{{ $breadcrumbLabels[$parentOption->id] }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-xs font-semibold text-text-secondary">Kode Node
            <input name="code" value="{{ old('code') }}" required maxlength="50" class="mt-1 block w-full rounded-lg border border-border bg-surface px-3 py-2 text-text-main">
        </label>
        <label class="text-xs font-semibold text-text-secondary">Nama Node
            <input name="name" value="{{ old('name') }}" required maxlength="150" class="mt-1 block w-full rounded-lg border border-border bg-surface px-3 py-2 text-text-main">
        </label>
        <label class="text-xs font-semibold text-text-secondary"><span x-text="hasPorts ? 'Jumlah Port' : 'Kapasitas Ringkasan'"></span>
            <input type="number" name="capacity" value="{{ old('capacity') }}" min="0" max="1000" x-bind:required="hasPorts"
                   class="mt-1 block w-full rounded-lg border border-border bg-surface px-3 py-2 text-text-main">
        </label>
        <button type="submit" class="self-end rounded-lg bg-primary px-4 py-2 text-xs font-bold text-white">Tambah Node</button>
        @if($errors->any())
        <p class="text-xs text-error sm:col-span-2 lg:col-span-6">{{ $errors->first() }}</p>
        @endif
    </form>
    @endcan

    <section class="rounded-xl border border-border bg-surface p-5" aria-label="Pohon node jaringan">
        <h2 class="text-base font-bold text-text-main">Pohon Infrastruktur</h2>
        <p class="mt-1 text-xs text-text-muted">Buka POP dan node untuk melihat susunan titik jaringan.</p>
        <div class="mt-4 space-y-2">
            @forelse($treeChildren->get(0, collect())->groupBy('anchor_pop_id') as $roots)
            <details class="rounded-lg border border-border bg-surface-muted p-3" open>
                <summary class="cursor-pointer text-sm font-semibold text-text-main">{{ $roots->first()->anchorPop?->name ?? 'POP tidak tersedia' }}</summary>
                <div class="mt-2 space-y-2 border-l border-border pl-4">
                    @foreach($roots as $treeNode)
                    @include('network.nodes._tree-node', ['treeNode' => $treeNode, 'treeChildren' => $treeChildren])
                    @endforeach
                </div>
            </details>
            @empty
            <p class="text-sm text-text-muted">Belum ada node dalam scope Anda.</p>
            @endforelse
        </div>
    </section>

    <div class="space-y-4">
        @forelse($nodes as $node)
        @php $usagePercent = $node->ports_count > 0 ? round($node->used_ports_count / $node->ports_count * 100) : 0; @endphp
        <section class="rounded-xl border border-border bg-surface p-5" id="node-{{ $node->id }}">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-text-main">{{ $node->code }} · {{ $node->name }}</h2>
                    <p class="text-xs text-text-muted">{{ $node->nodeType?->name }} · {{ $breadcrumbLabels[$node->id] ?? $node->name }} · @if($node->nodeType?->has_ports){{ $node->used_ports_count }}/{{ $node->ports_count }} port terpakai @else Kapasitas ringkasan: {{ $node->capacity ?? '-' }} @endif</p>
                </div>
                {{-- Red Zone dari ambang per tipe node (NetworkNode::isInRedZone()),
                     sama persis dengan dashboard FOP — bukan 85% hardcode. --}}
                @if($node->nodeType?->has_ports)
                @php $isRedZone = $node->isInRedZone(); @endphp
                <span class="rounded-lg border px-2 py-1 text-xs font-bold {{ $isRedZone ? 'border-error-border bg-error-bg text-error' : 'border-border bg-surface-muted text-text-secondary' }}" data-red-zone="{{ $isRedZone ? '1' : '0' }}">
                    {{ $isRedZone ? 'Red Zone · ' : '' }}{{ $usagePercent }}%
                </span>
                @endif
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                @foreach($node->ports as $port)
                @can('network_nodes.update')
                <form method="POST" action="{{ route('network.node-ports.update', $port) }}">
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
            @if($node->deployedSerials->isNotEmpty() || $node->infrastructureBalances->isNotEmpty())
            <div class="mt-4 border-t border-border pt-4">
                <p class="mb-2 text-xs font-semibold text-text-secondary">Aset Terpasang</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($node->deployedSerials as $serial)
                    <a href="{{ route('warehouse.traceability.index', ['sn' => $serial->serial_number]) }}"
                       class="rounded-lg border border-border bg-surface-muted px-3 py-2 text-xs text-text-secondary hover:border-primary"
                       title="Lihat riwayat SN {{ $serial->serial_number }}">
                        SN {{ $serial->serial_number }} · {{ $serial->item?->name }}
                    </a>
                    @endforeach
                    @foreach($node->infrastructureBalances as $balance)
                    @if((float) $balance->qty > 0)
                    <span class="rounded-lg border border-border bg-surface-muted px-3 py-2 text-xs text-text-secondary">
                        {{ $balance->item?->name }}{{ $balance->lot_no ? ' ('.$balance->lot_no.')' : '' }} · {{ rtrim(rtrim(number_format((float) $balance->qty, 2, ',', '.'), '0'), ',') }} {{ $balance->item?->unit }}
                    </span>
                    @endif
                    @endforeach
                </div>
            </div>
            @endif
            @can('network_nodes.update')
            <form method="POST" action="{{ route('network.nodes.move', $node) }}"
                  x-data="{ placement: @js($node->parent_id ? 'node' : 'pop') }"
                  class="mt-4 grid gap-2 border-t border-border pt-4 sm:grid-cols-3">
                @csrf
                @method('PATCH')
                <label class="text-xs font-semibold text-text-secondary">Pindah ke
                    <select x-model="placement" class="mt-1 block w-full rounded-lg border border-border bg-surface px-3 py-2 text-text-main">
                        <option value="pop">POP</option>
                        <option value="node">Node Induk</option>
                    </select>
                </label>
                <label x-show="placement === 'pop'" class="text-xs font-semibold text-text-secondary">POP Tujuan
                    <select name="anchor_pop_id" x-bind:disabled="placement !== 'pop'" x-bind:required="placement === 'pop'"
                            class="mt-1 block w-full rounded-lg border border-border bg-surface px-3 py-2 text-text-main">
                        <option value="">Pilih POP</option>
                        @foreach($pops as $pop)
                        <option value="{{ $pop->id }}" @selected($node->anchor_pop_id == $pop->id)>{{ $pop->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label x-show="placement === 'node'" class="text-xs font-semibold text-text-secondary">Node Induk Tujuan
                    <select name="parent_id" x-bind:disabled="placement !== 'node'" x-bind:required="placement === 'node'"
                            class="mt-1 block w-full rounded-lg border border-border bg-surface px-3 py-2 text-text-main">
                        <option value="">Pilih Node Induk</option>
                        @foreach($parentOptions as $parentOption)
                        <option value="{{ $parentOption->id }}" @selected($node->parent_id == $parentOption->id)>{{ $breadcrumbLabels[$parentOption->id] }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="submit" class="self-end rounded-lg border border-border px-3 py-2 text-xs font-semibold text-text-main">Pindah Node</button>
            </form>
            @endcan
            @if($node->nodeType?->has_ports)
            @can('network_nodes.update')
            <form method="POST" action="{{ route('network.nodes.ports.add', $node) }}" class="mt-4 flex items-end gap-2 border-t border-border pt-4">
                @csrf
                <label class="text-xs font-semibold text-text-secondary">Tambah kapasitas
                    <input type="number" name="additional_capacity" min="1" max="1000" required class="mt-1 block w-24 rounded-lg border border-border bg-surface px-3 py-2 text-text-main">
                </label>
                <button type="submit" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-text-main">Tambah Port</button>
            </form>
            @endcan
            @endif
        </section>
        @empty
        <div class="rounded-xl border border-border bg-surface p-8 text-center text-sm text-text-muted">Belum ada node jaringan pada POP yang dapat Anda akses.</div>
        @endforelse
    </div>
    {{ $nodes->links() }}
</div>
@endsection
