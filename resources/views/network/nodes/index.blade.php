@extends('layouts.app')

@section('title', 'Master Node Jaringan & ODN - Whusnet Operasional')
@section('page_title', 'Master Node Jaringan & ODN')

@push('styles')
<style>
    /* Tree connector lines matching Design.md enterprise guidelines */
    .tree-line {
        position: relative;
    }
    .tree-line::before {
        content: "";
        position: absolute;
        left: -14px;
        top: 0;
        bottom: 0;
        width: 1px;
        background-color: var(--color-border);
    }
    .tree-node-item {
        position: relative;
    }
    .tree-node-item::before {
        content: "";
        position: absolute;
        left: -14px;
        top: 50%;
        width: 12px;
        height: 1px;
        background-color: var(--color-border);
    }
</style>
@endpush

@section('content')
<div x-data="networkNodeManager({
    selectedNodeId: @js($nodes->first(fn ($candidate) => ! in_array($candidate->nodeType?->code, ['pon_port', 'olt_card', 'aggregation_switch', 'backbone', 'feeder', 'drop_cable'], true))?->id),
    showCreateModal: @js($errors->any() && !old('_method')),
    createType: @js(old('device_type', '')),
    createPopId: @js(old('anchor_pop_id', '')),
    createDeviceId: @js(old('device_id', '')),
    createSlotId: @js(old('slot_id', '')),
    createPonId: @js(old('uplink_pon_id', '')),
    creationPops: @js($pops->map(fn ($pop) => ['id' => $pop->id, 'name' => $pop->name, 'type' => $pop->type, 'cabang_id' => \App\Models\Pop::resolveCabangId($pop->id)])->values()),
    nodeTypeIds: @js($nodeTypes->pluck('id', 'code')),
    slotStoreUrl: @js(route('network.nodes.infrastructure-slots.store', ['node' => '__ID__'])),
    ponStoreUrl: @js(route('network.infrastructure-slots.ports.store', ['slot' => '__ID__'])),
    availableCustomers: @js($availableCustomers),
    hierarchyItems: @js(collect($hierarchy['items'])->map(fn ($item) => collect($item)->only(['id', 'label', 'name', 'kind', 'type', 'type_id', 'parent', 'breadcrumb']))),
    hierarchyChildren: @js($hierarchy['children']),
})" class="space-y-5 pb-10">

    {{-- Layer 1: Naked Page Header (Design.md §1.7 — tanpa card container, solid sky blue) --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-text-muted mb-1.5" aria-label="Breadcrumb">
                <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors">Home</a>
                <span class="text-text-disabled">/</span>
                <span class="text-text-secondary font-medium">Jaringan</span>
                <span class="text-text-disabled">/</span>
                <span class="text-text-main font-semibold">Master Node &amp; ODN</span>
            </nav>
            <h1 class="text-2xl font-bold text-text-main tracking-tight">Master Node Jaringan &amp; ODN</h1>
            <p class="text-xs text-text-muted mt-1 max-w-2xl leading-relaxed">
                Manajemen terpadu topologi distribusi optik: Pusat, Cabang/Mini POP, OLT, Slot, Port PON, ODC, ODP, hingga sambungan pelanggan aktif.
            </p>
        </div>

        <div class="flex items-center gap-2.5 w-full sm:w-auto">
            @if(auth()->user()->hasPermission('network_nodes.create') || auth()->user()->hasPermission('network_nodes.update'))
            <button type="button" @click="openCreateModalRoot()"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold shadow-xs transition-colors cursor-pointer w-full sm:w-auto">
                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                <span>Tambah Titik Node Baru</span>
            </button>
            @endif
            <a href="{{ route('network.map.index') }}"
               class="inline-flex items-center justify-center gap-2 px-3.5 py-2 rounded-lg border border-border bg-surface hover:bg-surface-muted text-xs font-semibold text-text-secondary transition-colors shadow-2xs w-full sm:w-auto">
                <svg class="h-4 w-4 text-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                </svg>
                <span>Peta Infrastruktur</span>
            </a>
        </div>
    </div>

    {{-- Layer 2: Flat Summary Strip (Design.md §1.6 & §9.1 — 1 container dengan divider vertikal) --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 bg-surface border border-border rounded-lg overflow-hidden shadow-2xs">
        {{-- Col 1: Total Titik Jaringan --}}
        <div class="p-3.5 sm:p-4 flex flex-col justify-between gap-1 border-r border-b lg:border-b-0 border-border">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-semibold text-text-muted uppercase tracking-wider">TOTAL TITIK JARINGAN</span>
                <span class="w-2 h-2 rounded-full bg-sky-500"></span>
            </div>
            <div class="flex items-baseline justify-between mt-1">
                <span class="text-xl sm:text-2xl font-bold font-mono text-sky-600 dark:text-sky-400 tabular-nums">{{ $totalNodesCount }}</span>
                <span class="text-[11px] text-text-muted font-medium">Titik Aktif</span>
            </div>
            <p class="text-[11px] text-text-muted truncate">Dalam wilayah POP scope Anda</p>
        </div>

        {{-- Col 2: Kapasitas Port Fisik --}}
        <div class="p-3.5 sm:p-4 flex flex-col justify-between gap-1 border-b lg:border-b-0 lg:border-r border-border">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-semibold text-text-muted uppercase tracking-wider">KAPASITAS PORT FISIK</span>
                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
            </div>
            <div class="flex items-baseline justify-between mt-1">
                <span class="text-xl sm:text-2xl font-bold font-mono text-text-main tabular-nums">{{ $totalPortsCount }}</span>
                <span class="text-[11px] text-text-muted font-medium">Total Port</span>
            </div>
            <p class="text-[11px] text-text-muted truncate">Kapasitas seluruh tipe node</p>
        </div>

        {{-- Col 3: Port Terpakai & Persentase Okupansi --}}
        @php
            $globalPercent = $totalPortsCount > 0 ? round(($totalUsedPortsCount / $totalPortsCount) * 100) : 0;
        @endphp
        <div class="p-3.5 sm:p-4 flex flex-col justify-between gap-1 border-r lg:border-r border-border">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-semibold text-text-muted uppercase tracking-wider">PORT TERPAKAI</span>
                <span class="w-2 h-2 rounded-full {{ $globalPercent >= 80 ? 'bg-rose-500' : 'bg-emerald-500' }}"></span>
            </div>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-xl sm:text-2xl font-bold font-mono {{ $globalPercent >= 80 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }} tabular-nums">{{ $totalUsedPortsCount }}</span>
                <span class="text-xs font-semibold font-mono text-text-muted tabular-nums">({{ $globalPercent }}%)</span>
            </div>
            <div class="w-full bg-surface-muted rounded-full h-1.5 overflow-hidden mt-1 border border-border">
                <div class="h-1.5 rounded-full {{ $globalPercent >= 80 ? 'bg-rose-500' : 'bg-emerald-500' }} transition-all duration-300" style="width: {{ $globalPercent }}%"></div>
            </div>
        </div>

        {{-- Col 4: Titik Red Zone --}}
        <div class="p-3.5 sm:p-4 flex flex-col justify-between gap-1">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-semibold text-text-muted uppercase tracking-wider">TITIK RED ZONE</span>
                <span class="w-2 h-2 rounded-full bg-rose-500 {{ $redZoneNodesCount > 0 ? 'animate-ping' : '' }}"></span>
            </div>
            <div class="flex items-baseline justify-between mt-1">
                <span class="text-xl sm:text-2xl font-bold font-mono {{ $redZoneNodesCount > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-text-main' }} tabular-nums">{{ $redZoneNodesCount }}</span>
                <span class="text-[11px] text-text-muted font-medium">Node Kritis</span>
            </div>
            <p class="text-[11px] text-text-muted truncate">
                {{ $redZoneNodesCount > 0 ? 'Perlu ekspansi kapasitas segera' : 'Kapasitas aman di bawah ambang' }}
            </p>
        </div>
    </div>

    {{-- Segmented View Switcher untuk Layar Mobile & Tablet (< 1024px) --}}
    <div class="sticky top-14 z-20 flex lg:hidden bg-surface p-1 rounded-lg border border-border shadow-xs">
        <button type="button" @click="activeMobileTab = 'tree'"
                :class="activeMobileTab === 'tree' ? 'bg-sky-600 text-white font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-surface-muted'"
                class="flex-1 py-2 text-xs rounded-md text-center transition-colors cursor-pointer flex items-center justify-center gap-1.5">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h8m-8 6h16"/>
            </svg>
            <span>Pohon Node &amp; ODN</span>
        </button>
        <button type="button" @click="activeMobileTab = 'inspector'"
                :class="activeMobileTab === 'inspector' ? 'bg-sky-600 text-white font-bold shadow-xs' : 'text-text-muted hover:text-text-main hover:bg-surface-muted'"
                class="flex-1 py-2 text-xs rounded-md text-center transition-colors cursor-pointer flex items-center justify-center gap-1.5">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            <span>Detail &amp; Port</span>
        </button>
    </div>

    {{-- Layer 3: Main Split Workspace (Card Budget = 1 per Panel, 8px Radius) --}}
    <div class="flex flex-col lg:flex-row gap-5 items-start">

        {{-- ========================================================================= --}}
        {{-- PANEL KIRI: HIRARKI TOPOLOGI JARINGAN (w-[410px] xl:w-[450px])            --}}
        {{-- ========================================================================= --}}
        <section :class="{
                    'hidden lg:flex': activeMobileTab !== 'tree',
                    'flex': activeMobileTab === 'tree',
                    'lg:w-[480px] xl:w-[540px] 2xl:w-[580px]': treeWidth === 'normal',
                    'lg:w-[620px] xl:w-[700px] 2xl:w-[780px]': treeWidth === 'wide',
                 }"
                 class="w-full bg-surface rounded-lg border border-border shadow-2xs flex-col overflow-hidden shrink-0 transition-[width] duration-200"
                 aria-label="Pohon node jaringan">

            {{-- Tree Header & Quick Actions --}}
            <div class="p-3.5 border-b border-border space-y-3 bg-surface">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <h2 class="text-xs font-bold text-text-main uppercase tracking-wider flex items-center gap-1.5">
                            <svg class="h-4 w-4 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h8m-8 6h16" />
                            </svg>
                            <span>Hirarki Topologi ODN</span>
                        </h2>
                        <p class="text-[11px] text-text-muted mt-0.5">Pilih node untuk mengelola port &amp; sambungan</p>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <button type="button" @click="toggleTreeWidth()"
                                class="px-2 py-1 text-xs font-medium text-text-muted hover:text-text-main hover:bg-surface-muted rounded-md transition-colors cursor-pointer flex items-center gap-1"
                                :title="treeWidth === 'normal' ? 'Perlebar panel hierarki' : 'Kembalikan ukuran normal panel'">
                            <svg class="w-3.5 h-3.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h8m0 0l-3-3m3 3l-3 3m-8 7h8m-8 0l3-3m-3 3l3 3" />
                            </svg>
                            <span class="hidden sm:inline" x-text="treeWidth === 'normal' ? 'Lebarkan' : 'Perkecil'"></span>
                        </button>
                        <button type="button" @click="expandAll(true)"
                                class="px-2 py-1 text-xs font-medium text-text-muted hover:text-text-main hover:bg-surface-muted rounded-md transition-colors cursor-pointer"
                                title="Buka Semua Cabang">
                            Buka Semua
                        </button>
                        <button type="button" @click="expandAll(false)"
                                class="px-2 py-1 text-xs font-medium text-text-muted hover:text-text-main hover:bg-surface-muted rounded-md transition-colors cursor-pointer"
                                title="Tutup Semua Cabang">
                            Tutup
                        </button>
                    </div>
                </div>

                {{-- Naked Filter Bar (Design.md §1.8) --}}
                <div class="flex gap-2">
                    <div class="relative flex-1">
                        <svg class="h-3.5 w-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-text-muted pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input x-model="treeSearchQuery" type="text" placeholder="Filter nama / kode node..."
                               class="w-full pl-8 pr-7 py-1.5 text-xs bg-surface-muted border border-border rounded-full text-text-main placeholder:text-text-muted focus:bg-surface focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-colors font-sans">
                        <button type="button" x-show="treeSearchQuery" @click="treeSearchQuery = ''"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-text-muted hover:text-text-main p-0.5 cursor-pointer">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <select x-model="typeFilter"
                            class="text-xs bg-surface-muted border border-border rounded-lg px-2.5 py-1.5 text-text-secondary font-medium focus:bg-surface focus:border-primary outline-none cursor-pointer">
                        <option value="all">Semua Tipe</option>
                        @foreach($nodeTypes as $nodeType)
                            <option value="{{ $nodeType->id }}">{{ $nodeType->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Legend / Summary Bar --}}
            <div class="px-3.5 py-2 bg-surface-muted/50 border-b border-border flex items-center justify-between text-[11px] text-text-muted">
                <span>Total: <strong class="text-text-main font-mono tabular-nums">{{ $totalNodesCount }}</strong> Node</span>
                <div class="flex items-center gap-3">
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> Normal</span>
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-rose-500"></span> Red Zone</span>
                </div>
            </div>

            {{-- Scrollable Tree List --}}
            <div id="networkTreeContainer" class="max-h-[calc(100vh-280px)] overflow-y-auto overflow-x-auto p-3 space-y-0.5 select-none scrollbar-thin">
                @forelse($hierarchy['roots'] as $hierarchyKey)
                    @include('network.nodes._hierarchy-item', ['hierarchyKey' => $hierarchyKey])
                @empty
                    <div class="rounded-lg border border-dashed border-border p-8 text-center text-xs text-text-muted space-y-2">
                        <svg class="w-8 h-8 mx-auto text-text-disabled" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                        <p class="font-medium text-text-secondary">Belum ada POP dalam scope Anda.</p>
                    </div>
                @endforelse
            </div>

            @php
                $legacyNodes = $nodes->filter(fn ($candidate) => in_array($candidate->nodeType?->code, ['pon_port', 'olt_card', 'aggregation_switch', 'backbone', 'feeder', 'drop_cable'], true));
            @endphp
            @if($legacyNodes->isNotEmpty())
            <details class="border-t border-border bg-amber-500/10 p-3 text-xs">
                <summary class="cursor-pointer text-xs font-semibold text-amber-700 dark:text-amber-400">Menunggu pemetaan legacy · {{ $legacyNodes->count() }} referensi lama</summary>
                <p class="mt-2 text-xs text-text-muted leading-relaxed">Referensi dan histori lama tetap tersimpan. Hubungan Slot/PON yang belum diverifikasi tidak dimasukkan ke pohon operasional.</p>
                <ul class="mt-2 space-y-1">
                    @foreach($legacyNodes as $legacyNode)
                    <li data-legacy-node="{{ $legacyNode->id }}" class="text-xs">
                        <a href="{{ route('network.nodes.show', $legacyNode) }}" class="font-mono text-text-secondary hover:underline">{{ $legacyNode->code }}</a>
                        <span class="text-text-muted">· {{ $legacyNode->nodeType?->name }}</span>
                    </li>
                    @endforeach
                </ul>
            </details>
            @endif
        </section>

        {{-- ========================================================================= --}}
        {{-- PANEL KANAN: DETAIL NODE & MANAJEMEN PORT (flex-1)                        --}}
        {{-- ========================================================================= --}}
        <section :class="{ 'hidden lg:flex': activeMobileTab !== 'inspector', 'flex': activeMobileTab === 'inspector' }"
                 class="flex-1 w-full bg-surface rounded-lg border border-border shadow-2xs flex-col overflow-hidden" id="node-inspector">

            @include('network.nodes._slot-inspector')
            @include('network.nodes._pon-inspector')

            @forelse($nodes as $node)
            @php
                $usagePercent = $node->ports_count > 0 ? round($node->used_ports_count / $node->ports_count * 100) : 0;
                $isRedZone = $node->isInRedZone();
                $location = $node->effectiveLocation();
                $childDownstreams = $treeChildren->get($node->id, collect());
                $assignedTargetNodeIds = $node->ports->flatMap(fn ($p) => $p->activeAssignment->pluck('target_node_id'))->filter()->all();
                $availableChildren = $childDownstreams->map(fn ($c) => [
                    'id' => $c->id,
                    'code' => $c->code,
                    'name' => $c->name,
                    'type' => $c->nodeType?->name ?? 'Node',
                    'is_connected' => in_array($c->id, $assignedTargetNodeIds, true),
                ])->values()->all();
                $breadcrumbParts = array_filter(explode(' / ', $breadcrumbLabels[$node->id] ?? $node->name));
                $typeCode = $node->nodeType?->code ?? 'other';
            @endphp

            <div x-show="selectedSlotId === null && selectedPonId === null && selectedNodeId === {{ $node->id }}"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 id="inspector-node-{{ $node->id }}">

                <div class="flex flex-col" id="node-{{ $node->id }}">

                    {{-- Sticky Top Bar: Breadcrumb Path & Quick Actions --}}
                    <div class="px-4 sm:px-6 py-3.5 border-b border-border flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-surface-muted/40">
                        {{-- Hierarchy Path Breadcrumb & Mobile Back Button --}}
                        <div id="nodeBreadcrumb" class="flex items-center flex-wrap gap-1 text-xs text-text-muted min-w-0">
                            <button type="button" @click="activeMobileTab = 'tree'"
                                    class="lg:hidden inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-surface border border-border text-xs font-semibold text-text-main hover:bg-surface-muted shadow-2xs mr-1 cursor-pointer shrink-0">
                                <svg class="w-3.5 h-3.5 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                                <span>Pohon</span>
                            </button>
                            @foreach($breadcrumbParts as $index => $part)
                                @if(! $loop->last)
                                    <span class="hover:text-primary transition font-mono truncate max-w-[110px] sm:max-w-none">{{ $part }}</span>
                                    <svg class="w-3 h-3 text-text-muted/60 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                    </svg>
                                @else
                                    <span class="font-bold text-text-main bg-surface px-2 py-0.5 rounded-md border border-border shadow-2xs font-mono truncate max-w-[160px] sm:max-w-none">
                                        {{ $part }}
                                    </span>
                                @endif
                            @endforeach
                        </div>

                        {{-- Action Buttons --}}
                        <div class="flex items-center gap-1.5 sm:gap-2 flex-wrap w-full sm:w-auto">
                            @if(in_array($typeCode, ['olt_chassis', 'switch_mini_pop'], true))
                            <a href="#device-components-{{ $node->id }}" class="flex-1 sm:flex-initial inline-flex items-center justify-center rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-semibold text-primary hover:bg-surface-muted transition shadow-2xs">
                                Slot / {{ $typeCode === 'olt_chassis' ? 'PON' : 'Port' }}
                            </a>
                            @else
                            @can('network_nodes.create')
                            <button type="button" @click="openAddChildModal({{ $node->id }}, @js($node->code), @js($node->name))"
                                    class="flex-1 sm:flex-initial inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-surface border border-border text-text-secondary hover:bg-surface-muted transition shadow-2xs cursor-pointer">
                                <svg class="w-3.5 h-3.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                </svg>
                                <span>+ Sub-Node</span>
                            </button>
                            @endcan
                            @endif

                            @can('network_nodes.update')
                            <button type="button" @click="openEditModal({{ $node->id }}, @js($node->code), @js($node->name), {{ $node->node_type_id }}, @js($node->status), @js($location['address'] ?? ''), @js($location['village'] ?? ''), @js($location['district'] ?? ''), @js($location['city'] ?? ''), @js($location['latitude'] ?? ''), @js($location['longitude'] ?? ''))"
                                    class="flex-1 sm:flex-initial inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-sky-600 text-white hover:bg-sky-700 transition shadow-xs cursor-pointer">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                <span>Edit Detail</span>
                            </button>
                            @endcan

                            @can('warehouse_assets.create')
                            <a href="{{ route('network.nodes.register-asset.create', $node) }}"
                               class="flex-1 sm:flex-initial inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-surface border border-border text-text-secondary hover:bg-surface-muted transition shadow-2xs"
                               title="Daftarkan Aset / Barcode">
                                <svg class="w-3.5 h-3.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                </svg>
                                <span>Registrasi Aset</span>
                            </a>
                            @endcan

                            <a href="{{ route('network.nodes.show', $node) }}"
                               class="inline-flex items-center justify-center p-1.5 rounded-lg border border-border bg-surface hover:bg-surface-muted text-text-secondary transition shadow-2xs"
                               title="Buka Halaman Detail Penuh">
                                <svg class="w-4 h-4 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                </svg>
                            </a>
                        </div>
                    </div>

                    {{-- Primary Identity Row & Capacity Metric --}}
                    <div class="p-4 sm:p-6 border-b border-border bg-surface">
                        <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-4">
                            <div class="space-y-2 min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="px-2.5 py-0.5 rounded-md text-[10px] font-mono font-bold uppercase tracking-wider border
                                        @if(str_contains($typeCode, 'odp')) bg-emerald-50 text-emerald-700 dark:bg-emerald-950/70 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800
                                        @elseif(str_contains($typeCode, 'feeder')) bg-sky-50 text-sky-700 dark:bg-sky-950/70 dark:text-sky-300 border-sky-200 dark:border-sky-800
                                        @elseif(str_contains($typeCode, 'olt') || str_contains($typeCode, 'pon')) bg-amber-50 text-amber-800 dark:bg-amber-950/70 dark:text-amber-300 border-amber-200 dark:border-amber-800
                                        @elseif(str_contains($typeCode, 'aggregation')) bg-purple-50 text-purple-700 dark:bg-purple-950/70 dark:text-purple-300 border-purple-200 dark:border-purple-800
                                        @elseif(str_contains($typeCode, 'backbone')) bg-indigo-50 text-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800
                                        @else bg-surface-muted text-text-secondary border-border @endif">
                                        {{ $node->nodeType?->name }}
                                    </span>

                                    <h1 class="text-xl sm:text-2xl font-bold font-mono text-text-main tracking-tight">
                                        {{ $node->code }}
                                    </h1>
                                    <span class="text-text-disabled">&bull;</span>
                                    <span class="text-base sm:text-lg font-medium text-text-secondary truncate">
                                        {{ $node->name }}
                                    </span>

                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-semibold {{ $node->status === 'active' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' : 'bg-surface-muted text-text-muted border border-border' }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $node->status === 'active' ? 'bg-emerald-500 animate-pulse' : 'bg-text-disabled' }}"></span>
                                        {{ ucfirst($node->status) }}
                                    </span>
                                </div>

                                <div class="text-xs text-text-muted flex items-center gap-3 flex-wrap">
                                    <span class="flex items-center gap-1.5">
                                        <span class="text-text-disabled">POP:</span>
                                        <strong class="text-text-main font-semibold">{{ $node->anchorPop?->name ?? '—' }}</strong>
                                    </span>
                                    @if($location['address'])
                                    <span class="text-text-disabled">&bull;</span>
                                    <span class="flex items-center gap-1.5 truncate max-w-sm" title="{{ $location['address'] }}">
                                        <svg class="w-3.5 h-3.5 text-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        </svg>
                                        <span class="truncate">{{ $location['address'] }}</span>
                                    </span>
                                    @endif
                                </div>
                            </div>

                            {{-- Visual Capacity Meter (Goal-Gradient Effect) --}}
                            @if($node->nodeType?->has_ports && ! in_array($typeCode, ['olt_chassis', 'switch_mini_pop'], true))
                            <div class="bg-surface-muted/60 rounded-lg p-3.5 border border-border w-full xl:w-auto xl:min-w-[290px]">
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="text-xs font-semibold text-text-secondary">Okupansi Port</span>
                                    <span class="text-xs font-mono font-bold px-2 py-0.5 rounded tabular-nums {{ $isRedZone ? 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300 border border-rose-300 animate-pulse' : 'bg-surface text-text-main border border-border' }}"
                                          data-red-zone="{{ $isRedZone ? '1' : '0' }}">
                                        {{ $isRedZone ? 'Red Zone · ' : '' }}{{ $usagePercent }}%
                                    </span>
                                </div>
                                <div class="w-full h-2 bg-surface rounded-full overflow-hidden mb-2 border border-border">
                                    <div class="h-full transition-all duration-300 {{ $isRedZone ? 'bg-rose-500' : 'bg-sky-600' }}" style="width: {{ $usagePercent }}%;"></div>
                                </div>
                                <div class="flex items-center justify-between text-[11px] text-text-muted">
                                    <span><strong class="text-text-main font-mono tabular-nums">{{ $node->used_ports_count }}</strong> / <strong class="text-text-main font-mono tabular-nums">{{ $node->ports_count }}</strong> port</span>
                                    <span class="{{ $isRedZone ? 'text-rose-600 dark:text-rose-400 font-bold' : 'text-emerald-600 dark:text-emerald-400' }} font-medium font-mono tabular-nums">
                                        {{ max(0, $node->ports_count - $node->used_ports_count) }} port tersedia
                                    </span>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>

                    {{-- Ergonomic Operator Tab Navigation Bar --}}
                    <div class="flex items-center border-b border-border px-4 sm:px-6 bg-surface overflow-x-auto scrollbar-none gap-2">
                        @if(! in_array($typeCode, ['olt_chassis', 'switch_mini_pop'], true) && ($node->nodeType?->has_ports || $node->ports->isNotEmpty()))
                        <button type="button" @click="inspectorTab = 'ports'"
                                :class="inspectorTab === 'ports' ? 'border-sky-600 text-sky-600 dark:text-sky-400 font-bold' : 'border-transparent text-text-muted hover:text-text-main font-medium'"
                                class="py-3 px-2 text-xs border-b-2 transition-colors cursor-pointer whitespace-nowrap flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                            </svg>
                            <span>Manajemen Port &amp; Sambungan</span>
                            <span class="ml-1 px-1.5 py-0.2 rounded-full font-mono text-[10px] bg-surface-muted text-text-secondary">{{ $node->ports->count() }}</span>
                        </button>
                        @endif

                        <button type="button" @click="inspectorTab = 'location'"
                                :class="inspectorTab === 'location' ? 'border-sky-600 text-sky-600 dark:text-sky-400 font-bold' : 'border-transparent text-text-muted hover:text-text-main font-medium'"
                                class="py-3 px-2 text-xs border-b-2 transition-colors cursor-pointer whitespace-nowrap flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <span>Lokasi &amp; Koordinat</span>
                        </button>

                        @if($node->assetSummary()->isNotEmpty())
                        <button type="button" @click="inspectorTab = 'assets'"
                                :class="inspectorTab === 'assets' ? 'border-sky-600 text-sky-600 dark:text-sky-400 font-bold' : 'border-transparent text-text-muted hover:text-text-main font-medium'"
                                class="py-3 px-2 text-xs border-b-2 transition-colors cursor-pointer whitespace-nowrap flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                            <span>Aset Terpasang</span>
                            <span class="ml-1 px-1.5 py-0.2 rounded-full font-mono text-[10px] bg-surface-muted text-text-secondary">{{ $node->assetSummary()->count() }}</span>
                        </button>
                        @endif

                        @if($childDownstreams->isNotEmpty())
                        <button type="button" @click="inspectorTab = 'children'"
                                :class="inspectorTab === 'children' ? 'border-sky-600 text-sky-600 dark:text-sky-400 font-bold' : 'border-transparent text-text-muted hover:text-text-main font-medium'"
                                class="py-3 px-2 text-xs border-b-2 transition-colors cursor-pointer whitespace-nowrap flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                            </svg>
                            <span>Sub-Node ({{ $childDownstreams->count() }})</span>
                        </button>
                        @endif

                        @can('network_nodes.update')
                        <button type="button" @click="inspectorTab = 'relocate'"
                                :class="inspectorTab === 'relocate' ? 'border-sky-600 text-sky-600 dark:text-sky-400 font-bold' : 'border-transparent text-text-muted hover:text-text-main font-medium'"
                                class="py-3 px-2 text-xs border-b-2 transition-colors cursor-pointer whitespace-nowrap flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                            </svg>
                            <span>Relokasi Uplink</span>
                        </button>
                        @endcan
                    </div>

                    {{-- Main Inspector Content Area --}}
                    <div class="p-4 sm:p-6 space-y-6">

                        {{-- Chassis / Switch Special Component Slots --}}
                        @if(in_array($typeCode, ['olt_chassis', 'switch_mini_pop'], true))
                            <div class="pb-2 rounded-lg border border-border bg-surface-muted/40 p-4 text-xs text-text-muted">
                                {{-- `_device-slots.blade.php` masih berbasis `InfrastructureSlot`/`InfrastructurePort`
                                     (tabelnya sudah di-drop sebelum Task 11, lihat
                                     `database/migrations/2026_10_24_000002_retarget_location_foreign_keys_to_network_nodes.php`)
                                     — di luar scope file Task 11 untuk disesuaikan ulang
                                     ke `NetworkNode::slots()`/`positionPorts()`. Dipanggil di
                                     sini akan melempar "lazy loading disabled" dan menjatuhkan
                                     seluruh halaman Master Node. Buka pohon di sebelah kiri
                                     (lazy load Slot/PON lewat `network.nodes.children`) untuk
                                     navigasi komponen perangkat ini sampai panel ini disusun ulang. --}}
                                Panel komponen Slot/PON perangkat ini sedang disusun ulang untuk model node baru.
                                Buka cabang <span class="font-mono font-semibold">{{ $node->code }}</span> di pohon sebelah kiri untuk menelusuri Slot/PON-nya.
                            </div>
                        @endif

                        {{-- TAB 1: Manajemen Port & Sambungan --}}
                        @if(! in_array($typeCode, ['olt_chassis', 'switch_mini_pop'], true) && ($node->nodeType?->has_ports || $node->ports->isNotEmpty()))
                        <div x-show="inspectorTab === 'ports'" class="space-y-4">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-xs font-bold uppercase tracking-wider text-text-main flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-sky-600"></span>
                                        <span>Terminal Distribusi Port &bull; {{ $node->nodeType?->name }}</span>
                                    </h3>
                                    <p class="text-xs text-text-muted mt-0.5">
                                        Klik pada port untuk menghubungkan pelanggan, anak node, atau mengelola sambungan.
                                    </p>
                                </div>

                                {{-- Port Filter Controls & Add Port Inline Form --}}
                                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                                    <div class="grid grid-cols-3 sm:flex items-center bg-surface-muted rounded-lg p-0.5 border border-border">
                                        <button type="button" @click="portFilter = 'ALL'"
                                                :class="{ 'bg-surface text-text-main shadow-xs font-semibold': portFilter === 'ALL', 'text-text-muted hover:text-text-main': portFilter !== 'ALL' }"
                                                class="px-3 py-1.5 text-center rounded-md text-xs transition cursor-pointer">
                                            Semua
                                        </button>
                                        <button type="button" @click="portFilter = 'AVAILABLE'"
                                                :class="{ 'bg-surface text-text-main shadow-xs font-semibold': portFilter === 'AVAILABLE', 'text-text-muted hover:text-text-main': portFilter !== 'AVAILABLE' }"
                                                class="px-3 py-1.5 text-center rounded-md text-xs transition cursor-pointer">
                                            Tersedia
                                        </button>
                                        <button type="button" @click="portFilter = 'USED'"
                                                :class="{ 'bg-surface text-text-main shadow-xs font-semibold': portFilter === 'USED', 'text-text-muted hover:text-text-main': portFilter !== 'USED' }"
                                                class="px-3 py-1.5 text-center rounded-md text-xs transition cursor-pointer">
                                            Terpakai
                                        </button>
                                    </div>

                                    @can('network_nodes.update')
                                    <div class="h-6 w-px bg-border mx-1 hidden sm:block"></div>

                                    <form method="POST" action="{{ route('network.nodes.ports.add', $node) }}" class="flex items-center w-full sm:w-auto">
                                        @csrf
                                        <input type="number" name="additional_capacity" min="1" max="1000" value="4" required
                                               class="w-14 sm:w-16 px-2.5 py-1.5 text-xs font-mono tabular-nums font-semibold text-center bg-surface border border-r-0 border-border rounded-l-lg text-text-main focus:border-primary focus:ring-1 focus:ring-primary/20 outline-none" title="Jumlah Port Tambahan">
                                        <button type="submit" class="flex-1 sm:flex-initial justify-center px-3.5 py-1.5 bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold rounded-r-lg transition flex items-center gap-1.5 shadow-xs cursor-pointer">
                                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                            </svg>
                                            <span>Tambah Port</span>
                                        </button>
                                    </form>
                                    @endcan
                                </div>
                            </div>

                            {{-- Hardware Patch Panel Container --}}
                            <div class="bg-surface-muted/40 p-3.5 sm:p-5 rounded-lg border border-border">
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                                    @forelse($node->ports->sortBy('number') as $port)
                                        @php($assignment = $port->activeAssignment->first())
                                        @can('network_nodes.update')
                                            @if($port->is_used)
                                                {{-- Port Terpakai (High Contrast, Clean Badges) --}}
                                                <div x-show="portFilter === 'ALL' || portFilter === 'USED'"
                                                     @click="openAssignModal({
                                                         portId: {{ $port->id }},
                                                         portNumber: {{ $port->number }},
                                                         nodeId: {{ $node->id }},
                                                         nodeCode: @js($node->code),
                                                         nodeName: @js($node->name),
                                                         nodeType: @js($node->nodeType?->code ?? ''),
                                                         cabangId: @js(\App\Models\Pop::resolveCabangId($node->resolvePop()?->id)),
                                                         children: @js($availableChildren),
                                                         isUsed: true,
                                                         tenantType: @js($assignment?->customer ? 'customer' : ($assignment?->targetNode ? 'node' : ($assignment?->manual_label ? 'manual' : 'unknown'))),
                                                         tenantName: @js($assignment?->customer ? $assignment->customer->full_name : ($assignment?->targetNode ? $assignment->targetNode->code . ' · ' . $assignment->targetNode->name : ($assignment?->manual_label ?? 'Belum diverifikasi'))),
                                                         tenantSubtitle: @js($assignment?->customer ? ($assignment->customer->customer_code ?: ($assignment->customer->cid ?: '#'.$assignment->customer->id)) : ($assignment?->targetNode && $assignment->target_port_id && $assignment->relationLoaded('targetPort') ? 'Port ' . $assignment->targetPort?->number : '')),
                                                         customerUrl: @js($assignment?->customer ? route('customers.show', $assignment->customer) : '')
                                                     })"
                                                     class="group bg-surface hover:bg-sky-50/40 dark:hover:bg-sky-950/20 border border-border hover:border-sky-400 dark:hover:border-sky-600 rounded-lg p-3.5 cursor-pointer transition shadow-2xs flex flex-col justify-between min-h-[96px]">
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-xs font-bold font-mono tabular-nums text-sky-600 dark:text-sky-400">Port {{ $port->number }}</span>
                                                        <span class="inline-flex items-center gap-1 text-[10px] font-mono font-semibold text-rose-700 dark:text-rose-300 bg-rose-50 dark:bg-rose-950/60 px-2 py-0.5 rounded-full border border-rose-200 dark:border-rose-900">
                                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                                            Terpakai
                                                        </span>
                                                    </div>
                                                    <div class="mt-2.5 min-w-0">
                                                        <div class="text-xs font-semibold text-text-main truncate" title="{{ $assignment?->customer ? $assignment->customer->full_name : ($assignment?->targetNode ? $assignment->targetNode->code . ' · ' . $assignment->targetNode->name : 'Terpakai') }}">
                                                            @if($assignment?->customer)
                                                                {{ $assignment->customer->full_name }}
                                                            @elseif($assignment?->targetNode)
                                                                &rarr; {{ $assignment->targetNode->code }}
                                                            @elseif($assignment?->manual_label)
                                                                {{ $assignment->manual_label }}
                                                            @else
                                                                Terpakai
                                                            @endif
                                                        </div>
                                                        <div class="text-[11px] font-mono tabular-nums text-text-muted flex items-center justify-between mt-1">
                                                            <span class="truncate">
                                                                @if($assignment?->customer)
                                                                    {{ $assignment->customer->customer_code ?: ($assignment->customer->cid ?: '#'.$assignment->customer->id) }}
                                                                @elseif($assignment?->targetNode)
                                                                    Sub-Node
                                                                @else
                                                                    Manual
                                                                @endif
                                                            </span>
                                                            <span class="text-sky-600 opacity-0 group-hover:opacity-100 font-sans font-medium text-[10px] transition shrink-0">Kelola &rarr;</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            @else
                                                {{-- Port Tersedia (Emerald Dashed, Actionable) --}}
                                                <div x-show="portFilter === 'ALL' || portFilter === 'AVAILABLE'"
                                                     @click="openAssignModal({
                                                         portId: {{ $port->id }},
                                                         portNumber: {{ $port->number }},
                                                         nodeId: {{ $node->id }},
                                                         nodeCode: @js($node->code),
                                                         nodeName: @js($node->name),
                                                         nodeType: @js($node->nodeType?->code ?? ''),
                                                         cabangId: @js(\App\Models\Pop::resolveCabangId($node->resolvePop()?->id)),
                                                         children: @js($availableChildren),
                                                         isUsed: false,
                                                         tenantType: '',
                                                         tenantName: '',
                                                         tenantSubtitle: '',
                                                         customerUrl: ''
                                                     })"
                                                     class="group bg-surface hover:bg-emerald-50/40 dark:hover:bg-emerald-950/20 border-2 border-dashed border-emerald-300/80 dark:border-emerald-700/70 hover:border-emerald-500 rounded-lg p-3.5 cursor-pointer transition flex flex-col justify-between min-h-[96px]">
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-xs font-bold font-mono tabular-nums text-text-main group-hover:text-emerald-800 dark:group-hover:text-emerald-200">Port {{ $port->number }}</span>
                                                        <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/60 px-2 py-0.5 rounded-full border border-emerald-200 dark:border-emerald-800">
                                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                            Tersedia
                                                        </span>
                                                    </div>
                                                    <div class="mt-2.5 flex items-center justify-between text-xs text-emerald-700 dark:text-emerald-300 font-medium">
                                                        <span class="flex items-center gap-1">
                                                            <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                                            </svg>
                                                            <span>Hubungkan</span>
                                                        </span>
                                                        <span class="opacity-0 group-hover:opacity-100 transition text-[10px] font-semibold">&rarr;</span>
                                                    </div>
                                                </div>
                                            @endif
                                        @else
                                            <div class="p-3.5 rounded-lg border border-border bg-surface text-xs space-y-1">
                                                <div class="flex items-center justify-between gap-1">
                                                    <span class="font-mono tabular-nums font-bold text-text-main">Port {{ $port->number }}</span>
                                                    <span class="w-2 h-2 rounded-full {{ $port->is_used ? 'bg-rose-500' : 'bg-emerald-500' }}"></span>
                                                </div>
                                                <div class="text-[11px] text-text-muted truncate">
                                                    @if($assignment?->customer) Pelanggan: {{ $assignment->customer->full_name }}
                                                    @elseif($assignment?->targetNode) &rarr; {{ $assignment->targetNode->code }}
                                                    @elseif($assignment?->manual_label) {{ $assignment->manual_label }}
                                                    @else {{ $port->is_used ? 'Terpakai' : 'Kosong' }} @endif
                                                </div>
                                            </div>
                                        @endcan
                                    @empty
                                        <div class="col-span-full p-8 rounded-lg border border-dashed border-border text-center text-xs text-text-muted space-y-2">
                                            <p class="font-medium text-text-secondary">Belum ada port yang dibuat untuk node ini.</p>
                                            <p>Gunakan formulir "+ Tambah Port" di atas untuk menambahkan port distribusi.</p>
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                        @endif

                        {{-- TAB 2: Lokasi & Koordinat --}}
                        <div x-show="inspectorTab === 'location'" class="space-y-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <h3 class="text-xs font-bold uppercase tracking-wider text-text-main flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-sky-600"></span>
                                        <span>Lokasi Fisik &amp; Koordinat Geospasial</span>
                                    </h3>
                                    <p class="text-xs text-text-muted mt-0.5">Rincian alamat tiang, cakupan wilayah, dan titik peta GPS.</p>
                                </div>
                                <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold {{ $location['source_node_id'] === $node->id ? 'text-emerald-700 bg-emerald-50 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' : 'text-sky-700 bg-sky-50 dark:bg-sky-950/60 dark:text-sky-300 border border-sky-200 dark:border-sky-800' }} px-3 py-1 rounded-full">
                                    {{ $location['source_node_id'] === $node->id ? 'Titik Koordinat Mandiri' : 'Mewarisi lokasi Induk / POP' }}
                                </span>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3.5 pt-1">
                                <div class="bg-surface p-4 rounded-lg border border-border space-y-1">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted">Alamat / Patokan Tiang</span>
                                    <p class="text-xs font-semibold text-text-main leading-relaxed">{{ $location['address'] ?: '—' }}</p>
                                </div>
                                <div class="bg-surface p-4 rounded-lg border border-border space-y-1">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted">Wilayah / Coverage Area</span>
                                    <p class="text-xs font-semibold text-text-main leading-relaxed">{{ collect([$location['village'], $location['district'], $location['city']])->filter()->implode(', ') ?: '—' }}</p>
                                </div>
                                <div class="bg-surface p-4 rounded-lg border border-border flex items-center justify-between gap-2">
                                    <div class="min-w-0 space-y-1">
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted">Koordinat Maps GPS</span>
                                        <p class="text-xs font-mono tabular-nums font-semibold text-text-main truncate">
                                            {{ $location['latitude'] !== null && $location['longitude'] !== null ? "{$location['latitude']}, {$location['longitude']}" : '—' }}
                                        </p>
                                    </div>
                                    @if($location['latitude'] !== null && $location['longitude'] !== null)
                                    <a href="https://maps.google.com/?q={{ $location['latitude'] }},{{ $location['longitude'] }}" target="_blank" rel="noopener"
                                       class="px-3 py-1.5 rounded-lg border border-border bg-surface hover:bg-surface-muted text-xs font-semibold text-sky-600 dark:text-sky-400 transition shadow-2xs shrink-0 flex items-center gap-1.5" title="Buka di Google Maps">
                                        <span>Buka Maps</span>
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                    </a>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- TAB 3: Aset Gudang & Material Terpasang --}}
                        @if($node->assetSummary()->isNotEmpty())
                        <div x-show="inspectorTab === 'assets'" class="space-y-4">
                            <div class="flex items-center justify-between gap-2">
                                <div>
                                    <h3 class="text-xs font-bold uppercase tracking-wider text-text-main flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-sky-600"></span>
                                        <span>Aset &amp; Material Terpasang</span>
                                    </h3>
                                    <p class="text-xs text-text-muted mt-0.5">Ringkasan perangkat, splitter, dan kabel dari stok logistik gudang.</p>
                                </div>
                                <a href="{{ route('network.map.index', ['node_id' => $node->id]) }}"
                                   class="text-xs font-semibold text-sky-600 dark:text-sky-400 hover:underline">
                                    Lihat di Peta Infrastruktur &rarr;
                                </a>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 pt-1">
                                @foreach($node->assetSummary() as $row)
                                <div class="p-3.5 rounded-lg border border-border bg-surface flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 border border-amber-200/60 dark:border-amber-800 flex items-center justify-center shrink-0">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-xs font-semibold text-text-main truncate">{{ $row['name'] }}</div>
                                        <div class="text-[11px] font-mono text-text-muted mt-0.5">
                                            {{ rtrim(rtrim(number_format((float) $row['qty'], 2, ',', '.'), '0'), ',') }} {{ $row['unit'] }}
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        {{-- TAB 4: Sub-Node Downstream --}}
                        @if($childDownstreams->isNotEmpty())
                        <div x-show="inspectorTab === 'children'" class="space-y-4">
                            <div class="flex items-center justify-between gap-2">
                                <div>
                                    <h3 class="text-xs font-bold uppercase tracking-wider text-text-main flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-sky-600"></span>
                                        <span>Sub-Node Turunan Langsung ({{ $childDownstreams->count() }} Downstream)</span>
                                    </h3>
                                    <p class="text-xs text-text-muted mt-0.5">Klik node di bawah untuk langsung beralih ke inspeksi detailnya.</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 pt-1">
                                @foreach($childDownstreams as $child)
                                <button type="button" @click="selectNode({{ $child->id }})"
                                        class="text-left p-3.5 rounded-lg border border-border bg-surface hover:border-sky-500 hover:bg-sky-50/30 dark:hover:bg-sky-950/20 transition shadow-2xs group cursor-pointer">
                                    <div class="flex items-center justify-between gap-1">
                                        <span class="font-mono tabular-nums font-bold text-sky-600 dark:text-sky-400 text-xs">{{ $child->code }}</span>
                                        <span class="text-[10px] font-bold text-text-muted">({{ $child->nodeType?->name }})</span>
                                    </div>
                                    <div class="text-xs font-semibold text-text-main truncate mt-1.5">{{ $child->name }}</div>
                                    <div class="text-[11px] text-text-muted mt-1 font-mono tabular-nums">
                                        {{ $child->used_ports_count ?? 0 }} / {{ $child->ports_count ?? 0 }} port terpakai
                                    </div>
                                </button>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        {{-- TAB 5: Relokasi Posisi Induk Node --}}
                        @can('network_nodes.update')
                        <div x-show="inspectorTab === 'relocate'" class="space-y-4">
                            <div>
                                <h3 class="text-xs font-bold uppercase tracking-wider text-text-main flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                    <span>Relokasi Posisi Induk Node (Uplink)</span>
                                </h3>
                                <p class="text-xs text-text-muted mt-0.5">
                                    Pindahkan node ini beserta seluruh jalur anak di bawahnya ke POP, OLT, atau Feeder lain.
                                </p>
                            </div>

                            <div class="p-4 rounded-lg border border-border bg-surface-muted/30">
                                <form method="POST" action="{{ route('network.nodes.move', $node) }}"
                                      x-data="{ placement: @js($node->parent_id ? 'node' : 'pop') }"
                                      class="space-y-4">
                                    @csrf
                                    @method('PATCH')
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <label class="block text-xs font-bold text-text-secondary mb-1">Pindah Penempatan Ke</label>
                                            <select x-model="placement" name="placement" class="block w-full rounded-lg border border-border bg-surface px-3 py-2 text-text-main text-xs focus:border-primary focus:ring-1 focus:ring-primary/20 outline-none cursor-pointer">
                                                <option value="pop">Cabang / POP</option>
                                                <option value="node">Node Induk Lain</option>
                                            </select>
                                        </div>

                                        <div x-show="placement === 'pop'">
                                            <label class="block text-xs font-bold text-text-secondary mb-1">POP Tujuan</label>
                                            <select name="anchor_pop_id" x-bind:disabled="placement !== 'pop'" x-bind:required="placement === 'pop'"
                                                    class="block w-full rounded-lg border border-border bg-surface px-3 py-2 text-text-main text-xs focus:border-primary focus:ring-1 focus:ring-primary/20 outline-none cursor-pointer">
                                                <option value="">Pilih POP</option>
                                                @foreach($pops as $pop)
                                                <option value="{{ $pop->id }}" @selected($node->anchor_pop_id == $pop->id)>{{ $pop->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div x-show="placement === 'node'">
                                            <label class="block text-xs font-bold text-text-secondary mb-1">Node Induk Tujuan</label>
                                            <select name="parent_id" x-bind:disabled="placement !== 'node'" x-bind:required="placement === 'node'"
                                                    class="block w-full rounded-lg border border-border bg-surface px-3 py-2 text-text-main text-xs focus:border-primary focus:ring-1 focus:ring-primary/20 outline-none cursor-pointer">
                                                <option value="">Pilih Node Induk</option>
                                                @foreach($parentOptions as $parentOption)
                                                @if($parentOption->id !== $node->id)
                                                <option value="{{ $parentOption->id }}" @selected($node->parent_id == $parentOption->id)>{{ $breadcrumbLabels[$parentOption->id] ?? $parentOption->name }}</option>
                                                @endif
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="pt-2 flex justify-end">
                                        <button type="submit" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold rounded-lg transition shadow-xs cursor-pointer">
                                            Simpan Relokasi Uplink
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        @endcan

                    </div>
                </div>
            </div>
            @empty
            <div class="rounded-lg border border-border bg-surface p-12 text-center text-xs text-text-muted space-y-2">
                <p class="font-bold text-text-main text-sm">Belum ada node jaringan pada POP yang dapat Anda akses.</p>
                <p>Klik tombol "+ Tambah Titik Node Baru" di atas untuk menambahkan titik jaringan pertama Anda.</p>
            </div>
            @endforelse
        </section>
    </div>

    @include('network.nodes._create-point-modal')

    {{-- ========================================================================= --}}
    {{-- MODAL EDIT DETAIL NODE                                                   --}}
    {{-- ========================================================================= --}}
    @can('network_nodes.update')
    <div x-show="editModal.open" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
         @keydown.escape.window="editModal.open = false">
        <div @click.outside="editModal.open = false"
             class="w-full max-w-2xl rounded-lg bg-surface border border-border shadow-2xl overflow-hidden max-h-[90vh] flex flex-col animate-in fade-in zoom-in duration-150">

            <div class="px-6 py-4 border-b border-border bg-surface-muted/40 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="p-2 rounded-lg bg-sky-100 text-sky-700 dark:bg-sky-950/70 dark:text-sky-300">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold uppercase tracking-wider text-text-main">Edit Detail Node</h3>
                        <p class="text-[11px] text-text-muted" x-text="'Perbarui data untuk node ' + editModal.code"></p>
                    </div>
                </div>
                <button type="button" @click="editModal.open = false" class="text-text-muted hover:text-text-main cursor-pointer p-1.5 rounded-lg hover:bg-surface-muted transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form :action="'/network/nodes/' + editModal.id" method="POST" class="p-6 overflow-y-auto space-y-4">
                @csrf
                @method('PATCH')

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-text-secondary">Kode Node</label>
                        <input type="text" name="code" x-model="editModal.code" required
                               class="block w-full rounded-lg border border-border bg-surface px-3 py-2 text-text-main text-xs font-mono uppercase focus:border-primary focus:ring-1 focus:ring-primary/20 outline-none">
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-bold text-text-secondary">Nama Node <span class="text-rose-500">*</span></label>
                        <input type="text" name="name" x-model="editModal.name" required
                               class="block w-full rounded-lg border border-border bg-surface px-3 py-2 text-text-main text-xs focus:border-primary focus:ring-1 focus:ring-primary/20 outline-none">
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-text-secondary">Status Operasional</label>
                        <select name="status" x-model="editModal.status"
                                class="block w-full rounded-lg border border-border bg-surface px-3 py-2 text-text-main text-xs focus:border-primary focus:ring-1 focus:ring-primary/20 outline-none cursor-pointer">
                            <option value="active">Active (Beroperasi)</option>
                            <option value="inactive">Inactive (Nonaktif)</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-bold text-text-secondary">Alamat / Patokan Tiang</label>
                        <input type="text" name="address" x-model="editModal.address" placeholder="Patokan Tiang"
                               class="block w-full rounded-lg border border-border bg-surface px-3 py-2 text-text-main text-xs focus:border-primary focus:ring-1 focus:ring-primary/20 outline-none">
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-text-secondary">Latitude</label>
                        <input type="number" step="0.0000001" name="latitude" x-model="editModal.latitude" placeholder="-7.865120"
                               class="block w-full rounded-lg border border-border bg-surface px-3 py-2 text-text-main text-xs font-mono tabular-nums focus:border-primary focus:ring-1 focus:ring-primary/20 outline-none">
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-text-secondary">Longitude</label>
                        <input type="number" step="0.0000001" name="longitude" x-model="editModal.longitude" placeholder="111.469800"
                               class="block w-full rounded-lg border border-border bg-surface px-3 py-2 text-text-main text-xs font-mono tabular-nums focus:border-primary focus:ring-1 focus:ring-primary/20 outline-none">
                    </div>
                </div>

                <div class="pt-4 border-t border-border flex items-center justify-end gap-2.5">
                    <button type="button" @click="editModal.open = false"
                            class="px-4 py-2 rounded-lg border border-border bg-surface hover:bg-surface-muted text-text-secondary text-xs font-semibold cursor-pointer transition shadow-2xs">
                        Batal
                    </button>
                    <button type="submit"
                            class="px-5 py-2 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold shadow-xs cursor-pointer transition">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endcan

    {{-- ========================================================================= --}}
    {{-- MODAL SAMBUNG / KELOLA PENGHUNI PORT                                     --}}
    {{-- ========================================================================= --}}
    @can('network_nodes.update')
    <div x-show="assignModal.open" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
         @keydown.escape.window="assignModal.open = false">
        <div @click.outside="assignModal.open = false"
             class="w-full max-w-lg rounded-lg bg-surface border border-border shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-150">

            <div class="px-6 py-4 border-b border-border bg-surface-muted/40 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="p-2 rounded-lg" :class="assignModal.isUsed ? 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold uppercase tracking-wider text-text-main flex items-center gap-1.5">
                            <span>Port</span>
                            <span class="font-mono tabular-nums text-sky-600 dark:text-sky-400" x-text="assignModal.portNumber"></span>
                            <span>&bull;</span>
                            <span x-text="assignModal.isUsed ? 'Status Terpakai' : 'Sambungkan Tujuan'"></span>
                        </h3>
                        <p class="text-[11px] text-text-muted" x-text="assignModal.nodeCode + ' · ' + assignModal.nodeName"></p>
                    </div>
                </div>
                <button type="button" @click="assignModal.open = false" class="text-text-muted hover:text-text-main cursor-pointer p-1.5 rounded-lg hover:bg-surface-muted transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- 1. State: Port SUDAH TERPAKAI --}}
            <template x-if="assignModal.isUsed">
                <div class="p-6 space-y-4">
                    <div class="p-4 rounded-lg border border-rose-200 dark:border-rose-900 bg-rose-50/70 dark:bg-rose-950/30 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-rose-700 dark:text-rose-300">Penghuni Port Aktif</span>
                            <span class="text-xs font-mono font-bold text-rose-600 bg-surface px-2 py-0.5 rounded border border-rose-200 dark:border-rose-800"
                                  x-text="assignModal.tenantType === 'customer' ? 'Pelanggan' : (assignModal.tenantType === 'node' ? 'Sub-Node' : 'Manual')"></span>
                        </div>
                        <div class="text-base font-bold text-text-main" x-text="assignModal.tenantName"></div>
                        <template x-if="assignModal.tenantSubtitle">
                            <div class="text-xs font-mono tabular-nums text-text-muted" x-text="assignModal.tenantSubtitle"></div>
                        </template>
                    </div>

                    <div class="flex items-center justify-between gap-3 pt-2">
                        <div>
                            <template x-if="assignModal.customerUrl">
                                <a :href="assignModal.customerUrl"
                                   class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-border bg-surface hover:bg-surface-muted text-xs font-semibold text-text-secondary transition shadow-2xs">
                                    <span>Lihat Profil Pelanggan</span>
                                    <svg class="w-3.5 h-3.5 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                    </svg>
                                </a>
                            </template>
                        </div>

                        <form :action="'/network/node-ports/' + assignModal.portId" method="POST" class="no-confirm">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="is_used" value="0">
                            <button type="submit"
                                    class="px-4 py-2 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold shadow-xs cursor-pointer transition">
                                Lepas / Putus Port Ini
                            </button>
                        </form>
                    </div>
                </div>
            </template>

            {{-- 2. State: Port KOSONG / TERSEDIA --}}
            <template x-if="!assignModal.isUsed">
                <div class="p-6 space-y-4">
                    {{-- Nav Tabs --}}
                    <div class="flex border-b border-border gap-2 text-xs font-semibold pb-1">
                        <button type="button" @click="assignModal.type = 'customer'"
                                :class="assignModal.type === 'customer' ? 'text-sky-600 border-b-2 border-sky-600 pb-2 font-bold' : 'text-text-muted hover:text-text-main pb-2'"
                                class="transition cursor-pointer">
                            Sambung Pelanggan
                        </button>
                        <button type="button" @click="assignModal.type = 'node'"
                                :class="assignModal.type === 'node' ? 'text-sky-600 border-b-2 border-sky-600 pb-2 font-bold' : 'text-text-muted hover:text-text-main pb-2'"
                                class="transition cursor-pointer">
                            Hubungkan Anak Node
                        </button>
                        <button type="button" @click="assignModal.type = 'manual'"
                                :class="assignModal.type === 'manual' ? 'text-sky-600 border-b-2 border-sky-600 pb-2 font-bold' : 'text-text-muted hover:text-text-main pb-2'"
                                class="transition cursor-pointer">
                            Tandai Manual
                        </button>
                    </div>

                    {{-- Opsi 1: Sambung Pelanggan --}}
                    <div x-show="assignModal.type === 'customer'">
                        <template x-if="modalCustomers.length > 0">
                            <form :action="'/network/node-ports/' + assignModal.portId + '/assign-customer'" method="POST" class="no-confirm space-y-3">
                                @csrf
                                <div>
                                    <label class="block text-xs font-bold text-text-secondary mb-1">Pilih Pelanggan <span class="text-rose-500">*</span></label>
                                    <select name="customer_id" required class="block w-full rounded-lg border border-border bg-surface px-3 py-2 text-text-main text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none">
                                        <option value="">Pilih Pelanggan di Cabang Ini...</option>
                                        <template x-for="cust in modalCustomers" :key="cust.id">
                                            <option :value="cust.id" x-text="cust.full_name + ' (' + (cust.customer_code || cust.cid || ('#' + cust.id)) + ')'"></option>
                                        </template>
                                    </select>
                                    <p class="text-[10px] text-text-muted mt-1">Daftar pelanggan di cabang terkait yang belum menempati port ODP.</p>
                                </div>

                                <div class="pt-2 flex items-center justify-end gap-2">
                                    <button type="button" @click="assignModal.open = false" class="px-3 py-1.5 rounded-lg border border-border hover:bg-surface-muted text-text-secondary text-xs font-semibold cursor-pointer shadow-2xs">Batal</button>
                                    <button type="submit" class="px-4 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-xs cursor-pointer">
                                        + Sambungkan Pelanggan
                                    </button>
                                </div>
                            </form>
                        </template>
                        <template x-if="modalCustomers.length === 0">
                            <div class="p-4 rounded-lg border border-dashed border-border bg-surface-muted/30 text-center space-y-1">
                                <p class="text-xs font-bold text-text-main">Tidak Ada Pelanggan Siap Sambung</p>
                                <p class="text-[11px] text-text-muted leading-relaxed">
                                    Semua pelanggan di cabang ini sudah memiliki port aktif atau belum ada pelanggan terdaftar.
                                </p>
                            </div>
                        </template>
                    </div>

                    {{-- Opsi 2: Hubungkan Anak Node (ODP / Sub-Node) --}}
                    <div x-show="assignModal.type === 'node'">
                        <template x-if="unconnectedChildren.length > 0">
                            <form :action="'/network/node-ports/' + assignModal.portId + '/assign-node'" method="POST" class="no-confirm space-y-3">
                                @csrf
                                <div>
                                    <label class="block text-xs font-bold text-text-secondary mb-1">Pilih Anak Langsung <span class="text-rose-500">*</span></label>
                                    <select name="target_node_id" required class="block w-full rounded-lg border border-border bg-surface px-3 py-2 text-text-main text-xs focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 outline-none">
                                        <option value="">Pilih Anak Langsung (ODP / Sub-Node)...</option>
                                        <template x-for="child in unconnectedChildren" :key="child.id">
                                            <option :value="child.id" x-text="child.code + ' · ' + child.name + (child.type ? ' (' + child.type + ')' : '')"></option>
                                        </template>
                                    </select>
                                    <p class="text-[10px] text-text-muted mt-1">Hubungkan port ini ke anak node langsung (ODP/sub-node) di bawah node ini.</p>
                                </div>

                                <div class="pt-2 flex items-center justify-end gap-2">
                                    <button type="button" @click="assignModal.open = false" class="px-3 py-1.5 rounded-lg border border-border hover:bg-surface-muted text-text-secondary text-xs font-semibold cursor-pointer shadow-2xs">Batal</button>
                                    <button type="submit" class="px-4 py-1.5 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold shadow-xs cursor-pointer">
                                        Hubungkan Node
                                    </button>
                                </div>
                            </form>
                        </template>
                        <template x-if="unconnectedChildren.length === 0">
                            <div class="p-4 rounded-lg border border-dashed border-border bg-surface-muted/30 text-center space-y-2">
                                <template x-if="assignModal.children && assignModal.children.length > 0">
                                    <div>
                                        <p class="text-xs font-bold text-text-main">Semua Anak Node Sudah Terhubung</p>
                                        <p class="text-[11px] text-text-muted leading-relaxed mt-1">
                                            Seluruh anak node (ODP/sub-node) di bawah node ini sudah menempati port lain.
                                        </p>
                                    </div>
                                </template>
                                <template x-if="!assignModal.children || assignModal.children.length === 0">
                                    <div>
                                        <p class="text-xs font-bold text-text-main">Belum Ada Anak Node Terdaftar</p>
                                        <p class="text-[11px] text-text-muted leading-relaxed mt-1">
                                            Untuk menghubungkan port ke anak node (misal ODP), node anak harus didaftarkan terlebih dahulu dengan induk node ini.
                                        </p>
                                    </div>
                                </template>
                                <div class="pt-2">
                                    <button type="button" @click="assignModal.open = false; openAddChildModal(assignModal.nodeId, assignModal.nodeCode, assignModal.nodeName)"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold shadow-xs cursor-pointer">
                                        + Tambah Node Anak Baru
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Opsi 3: Tandai Manual --}}
                    <div x-show="assignModal.type === 'manual'">
                        <form :action="'/network/node-ports/' + assignModal.portId" method="POST" class="no-confirm space-y-3">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="is_used" value="1">
                            <p class="text-xs text-text-muted leading-relaxed">
                                Tandai port ini terpakai secara manual tanpa mengaitkannya ke data pelanggan atau anak node. Status akan tercatat sebagai <em>belum terverifikasi</em>.
                            </p>

                            <div class="pt-2 flex items-center justify-end gap-2">
                                <button type="button" @click="assignModal.open = false" class="px-3 py-1.5 rounded-lg border border-border hover:bg-surface-muted text-text-secondary text-xs font-semibold cursor-pointer shadow-2xs">Batal</button>
                                <button type="submit" class="px-4 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold shadow-xs cursor-pointer">
                                    Tandai Terpakai Manual
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </template>
        </div>
    </div>
    @endcan

</div>

<script>
/**
 * Lazy load anak Slot/PON/Port (Task 11, rancangan "Slot & PON sebagai
 * node" §7) — `url` SELALU datang dari atribut `data-children-url` yang
 * dirender server-side lewat `route()` (konvensi CLAUDE.md), tidak pernah
 * dirakit dari id/type di sini.
 */
async function loadNodeChildren(url) {
    if (!url) {
        return [];
    }
    try {
        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) {
            return [];
        }
        const data = await response.json();

        return data.children || [];
    } catch {
        return [];
    }
}

function networkNodeManager(config) {
    return {
        activeMobileTab: 'tree',
        treeWidth: localStorage.getItem('whusnet_tree_width') || 'normal',
        toggleTreeWidth() {
            this.treeWidth = this.treeWidth === 'normal' ? 'wide' : 'normal';
            localStorage.setItem('whusnet_tree_width', this.treeWidth);
        },
        treeSearchQuery: '',
        typeFilter: 'all',
        portFilter: 'ALL',
        inspectorTab: 'ports',
        openRelocateId: null,
        selectedNodeId: config.selectedNodeId,
        selectedPonId: null,
        selectedSlotId: null,
        showCreateModal: config.showCreateModal,
        createType: config.createType,
        createPopId: String(config.createPopId || ''),
        createDeviceId: String(config.createDeviceId || ''),
        createSlotId: String(config.createSlotId || ''),
        createPonId: String(config.createPonId || ''),
        creationPops: config.creationPops || [],
        nodeTypeIds: config.nodeTypeIds || {},
        slotStoreUrl: config.slotStoreUrl,
        ponStoreUrl: config.ponStoreUrl,
        availableCustomers: config.availableCustomers || [],
        hierarchyItems: config.hierarchyItems || {},
        hierarchyChildren: config.hierarchyChildren || {},

        editModal: {
            open: false,
            id: null,
            code: '',
            name: '',
            nodeTypeId: null,
            status: 'active',
            address: '',
            village: '',
            district: '',
            city: '',
            latitude: '',
            longitude: '',
        },

        assignModal: {
            open: false,
            portId: null,
            portNumber: null,
            nodeId: null,
            nodeCode: '',
            nodeName: '',
            nodeType: '',
            cabangId: null,
            children: [],
            type: 'customer',
            isUsed: false,
            tenantType: '',
            tenantName: '',
            tenantSubtitle: '',
            customerUrl: '',
        },

        toggleRelocate(nodeId) {
            this.openRelocateId = (this.openRelocateId === nodeId) ? null : nodeId;
        },

        get creationDevices() {
            return Object.values(this.hierarchyItems).filter(item => item.kind === 'node' && ['olt_chassis', 'switch_mini_pop'].includes(item.type));
        },

        get creationSlots() {
            return Object.values(this.hierarchyItems).filter(item => item.kind === 'slot' && item.type === 'olt');
        },

        get creationPons() {
            return Object.values(this.hierarchyItems).filter(item => item.kind === 'port' && item.type === 'pon');
        },

        get allowedCreationPops() {
            const allowed = this.createType === 'server' ? ['pusat'] : this.createType === 'switch' ? ['mini_pop'] : ['cabang', 'mini_pop'];
            return this.creationPops.filter(pop => allowed.includes(pop.type));
        },

        cabangFor(item) {
            let current = item;
            while (current) {
                if (current.kind === 'pop' && current.type === 'pop_cabang') return String(current.id);
                current = this.hierarchyItems[current.parent];
            }
            return '';
        },

        get allowedCreationPons() {
            const pop = this.creationPops.find(candidate => String(candidate.id) === this.createPopId);
            return this.creationPons.filter(pon => pop && this.cabangFor(pon) === String(pop.cabang_id));
        },

        get createSlotAction() {
            return this.createDeviceId ? this.slotStoreUrl.replace('__ID__', this.createDeviceId) : '';
        },

        get createPonAction() {
            return this.createSlotId ? this.ponStoreUrl.replace('__ID__', this.createSlotId) : '';
        },

        chooseCreateType(type) {
            this.createType = type;
            this.createPopId = '';
            this.createDeviceId = '';
            this.createSlotId = '';
            this.createPonId = '';
        },

        openCreateModalRoot() {
            this.chooseCreateType('');
            this.showCreateModal = true;
        },

        openAddChildModal(parentId, parentCode, parentName) {
            const parent = this.hierarchyItems['node:' + parentId];
            this.openCreateModalRoot();
            if (parent && ['olt_chassis', 'switch_mini_pop'].includes(parent.type)) {
                this.createType = 'slot';
                this.createDeviceId = String(parentId);
            }
        },

        openEditModal(id, code, name, nodeTypeId, status, address, village, district, city, lat, lng) {
            this.editModal = {
                open: true,
                id: id,
                code: code,
                name: name,
                nodeTypeId: nodeTypeId,
                status: status || 'active',
                address: address || '',
                village: village || '',
                district: district || '',
                city: city || '',
                latitude: lat || '',
                longitude: lng || '',
            };
        },

        openAssignModal(data) {
            const hasChildren = (data.children && data.children.length > 0);
            let defaultType = 'manual';
            if (data.nodeType === 'odp') {
                defaultType = 'customer';
            } else if (hasChildren) {
                defaultType = 'node';
            } else {
                defaultType = 'manual';
            }

            this.assignModal = {
                open: true,
                portId: data.portId,
                portNumber: data.portNumber,
                nodeId: data.nodeId,
                nodeCode: data.nodeCode,
                nodeName: data.nodeName,
                nodeType: data.nodeType,
                cabangId: data.cabangId,
                children: data.children || [],
                isUsed: Boolean(data.isUsed),
                tenantType: data.tenantType || '',
                tenantName: data.tenantName || '',
                tenantSubtitle: data.tenantSubtitle || '',
                customerUrl: data.customerUrl || '',
                type: defaultType,
            };
        },

        get modalCustomers() {
            if (!this.assignModal.cabangId) {
                return this.availableCustomers;
            }
            const list = this.availableCustomers.filter(c => c.cabang_id === this.assignModal.cabangId);
            return list.length > 0 ? list : this.availableCustomers;
        },

        get unconnectedChildren() {
            if (!this.assignModal.children) return [];
            return this.assignModal.children.filter(c => !c.is_connected);
        },

        quickCapacity(val) {
            const input = document.getElementById('create-modal-capacity-input');
            if (input) {
                input.value = val;
                input.dispatchEvent(new Event('input'));
            }
        },

        expandAll(open) {
            window.dispatchEvent(new CustomEvent('tree-toggle-all', { detail: { open } }));
        },

        matchesHierarchyFilter(key) {
            const item = this.hierarchyItems[key];
            if (!item) return false;
            const query = this.treeSearchQuery.toLowerCase().trim();
            const matchesType = this.typeFilter === 'all' || String(item.type_id) === String(this.typeFilter);
            const matchesText = !query || ((item.label || '') + ' ' + (item.name || '')).toLowerCase().includes(query);
            return (matchesType && matchesText) ||
                (this.hierarchyChildren[key] || []).some(child => this.matchesHierarchyFilter(child));
        },

        matchesTreeFilter(id, typeId, searchableText) {
            if (this.typeFilter !== 'all' && String(this.typeFilter) !== String(typeId)) {
                return false;
            }
            if (this.treeSearchQuery.trim() !== '') {
                const query = this.treeSearchQuery.toLowerCase().trim();
                return searchableText.includes(query);
            }
            return true;
        },

        selectSlot(id) {
            this.selectedSlotId = id;
            this.selectedPonId = null;
            this.activeMobileTab = 'inspector';
            this.$nextTick(() => {
                const el = document.getElementById('inspector-slot-' + id);
                if (el) el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
        },

        selectPon(id) {
            this.selectedSlotId = null;
            this.selectedPonId = id;
            this.activeMobileTab = 'inspector';
            this.$nextTick(() => {
                const el = document.getElementById('inspector-pon-' + id);
                if (el) el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
        },

        markNodeSeen(id) {
            const row = document.querySelector('[data-tree-node-id="' + id + '"]');
            const unseenBadge = row?.querySelector('[data-unseen-badge]');
            if (!unseenBadge) return;

            unseenBadge.remove();
            const countEl = document.getElementById('node-attention-count');
            if (countEl) {
                const remaining = parseInt(countEl.textContent, 10) - 1;
                if (remaining > 0) {
                    countEl.textContent = remaining;
                } else {
                    countEl.remove();
                }
            }

            fetch(row.dataset.nodeSeenUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                credentials: 'same-origin',
            });
        },

        selectNode(id) {
            this.markNodeSeen(id);
            this.selectedSlotId = null;
            this.selectedPonId = null;
            this.selectedNodeId = id;
            this.portFilter = 'ALL';
            this.inspectorTab = 'ports';
            this.activeMobileTab = 'inspector';
            this.$nextTick(() => {
                const el = document.getElementById('inspector-node-' + id);
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            });
        }
    };
}
</script>
@endsection
