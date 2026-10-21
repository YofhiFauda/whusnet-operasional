@extends('layouts.app')

@section('title', 'Master Node Jaringan & Port')
@section('page_title', 'Master Node Jaringan & Port')

@push('styles')
<style>
    /* Tree connector lines matching master-node-whusnet.html */
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
        background-color: #e2e8f0;
    }
    .dark .tree-line::before {
        background-color: #334155;
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
        background-color: #e2e8f0;
    }
    .dark .tree-node-item::before {
        background-color: #334155;
    }
</style>
@endpush

@section('content')
<div x-data="networkNodeManager({
    selectedNodeId: @js($nodes->first()?->id),
    showCreateModal: @js($errors->any() && !old('_method')),
    placement: @js(old('parent_id') ? 'node' : 'pop'),
    createParentId: @js(old('parent_id') ? (int) old('parent_id') : null),
    hasPorts: @js((bool) ($nodeTypes->firstWhere('id', (int) old('node_type_id'))?->has_ports ?? true)),
    availableCustomers: @js($availableCustomers),
})" class="mx-auto space-y-5 pb-6">

    {{-- Layer 1: Naked Page Header (tanpa card container / tanpa gradien) --}}
    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-slate-100 tracking-tight flex items-center gap-2.5">
                <span class="w-2.5 h-6 bg-blue-600 rounded-full shrink-0"></span>
                <span>Infrastruktur Jaringan &amp; Manajemen ODN</span>
            </h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-2xl leading-relaxed">
                Susunan Pusat, Cabang/Mini POP, dan node jaringan. Periksa jalur serta penggunaan port, aset terpasang, dan titik yang mendekati kapasitas.
            </p>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            @can('network_nodes.create')
            <button type="button" @click="openCreateModalRoot()"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow-sm transition-colors cursor-pointer">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                <span>Tambah Node Baru</span>
            </button>
            @endcan
            <a href="{{ route('network.map.index') }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-200 transition-colors shadow-2xs">
                <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                </svg>
                <span>Peta Infrastruktur ISP</span>
            </a>
        </div>
    </div>

    {{-- Layer 2: Flat Summary Strip (Satu strip datar dengan divider vertikal) --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 bg-white dark:bg-slate-800/90 border border-slate-200 dark:border-slate-700 rounded-xl divide-y sm:divide-y-0 sm:divide-x divide-slate-200 dark:divide-slate-700 overflow-hidden shadow-2xs">
        {{-- Col 1: Total Titik Jaringan --}}
        <div class="p-3.5 sm:p-4 flex flex-col justify-between gap-1">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">TOTAL TITIK JARINGAN</span>
                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
            </div>
            <div class="flex items-baseline justify-between mt-1">
                <span class="text-xl sm:text-2xl font-bold font-mono text-blue-600 dark:text-blue-400 tabular-nums">{{ $totalNodesCount }}</span>
                <span class="text-[11px] text-slate-400">Node</span>
            </div>
            <p class="text-[11px] text-slate-400">Titik sebar dalam scope Anda</p>
        </div>

        {{-- Col 2: Kapasitas Port Fisik --}}
        <div class="p-3.5 sm:p-4 flex flex-col justify-between gap-1">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">KAPASITAS PORT FISIK</span>
                <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
            </div>
            <div class="flex items-baseline justify-between mt-1">
                <span class="text-xl sm:text-2xl font-bold font-mono text-slate-800 dark:text-slate-100 tabular-nums">{{ $totalPortsCount }}</span>
                <span class="text-[11px] text-slate-400">Port</span>
            </div>
            <p class="text-[11px] text-slate-400">Total port pada seluruh tipe node</p>
        </div>

        {{-- Col 3: Port Terpakai & Persentase --}}
        @php
            $globalPercent = $totalPortsCount > 0 ? round(($totalUsedPortsCount / $totalPortsCount) * 100) : 0;
        @endphp
        <div class="p-3.5 sm:p-4 flex flex-col justify-between gap-1">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">PORT TERPAKAI</span>
                <span class="w-2 h-2 rounded-full {{ $globalPercent >= 80 ? 'bg-rose-500' : 'bg-emerald-500' }}"></span>
            </div>
            <div class="flex items-baseline gap-1.5 mt-1">
                <span class="text-xl sm:text-2xl font-bold font-mono {{ $globalPercent >= 80 ? 'text-rose-600' : 'text-emerald-600 dark:text-emerald-400' }} tabular-nums">{{ $totalUsedPortsCount }}</span>
                <span class="text-xs font-semibold text-slate-400">({{ $globalPercent }}%)</span>
            </div>
            <div class="w-full bg-slate-100 dark:bg-slate-700 rounded-full h-1.5 overflow-hidden mt-1">
                <div class="h-1.5 rounded-full {{ $globalPercent >= 80 ? 'bg-rose-500' : 'bg-emerald-500' }}" style="width: {{ $globalPercent }}%"></div>
            </div>
        </div>

        {{-- Col 4: Titik Red Zone --}}
        <div class="p-3.5 sm:p-4 flex flex-col justify-between gap-1">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">TITIK RED ZONE</span>
                <span class="w-2 h-2 rounded-full bg-rose-500 {{ $redZoneNodesCount > 0 ? 'animate-ping' : '' }}"></span>
            </div>
            <div class="flex items-baseline justify-between mt-1">
                <span class="text-xl sm:text-2xl font-bold font-mono {{ $redZoneNodesCount > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-800 dark:text-slate-100' }} tabular-nums">{{ $redZoneNodesCount }}</span>
                <span class="text-[11px] text-slate-400">Node</span>
            </div>
            <p class="text-[11px] text-slate-400">
                {{ $redZoneNodesCount > 0 ? 'Menurut ambang batas tipe node' : 'Tidak ada node melewati ambang tipenya' }}
            </p>
        </div>
    </div>

    {{-- Layer 3: Main Split Workspace matching master-node-whusnet.html --}}
    <div class="flex flex-col lg:flex-row gap-5 items-start">

        {{-- ========================================================================= --}}
        {{-- PANEL KIRI: HIRARKI TOPOLOGI JARINGAN (w-[430px] xl:w-[460px])            --}}
        {{-- ========================================================================= --}}
        <section class="w-full lg:w-[430px] xl:w-[460px] bg-white dark:bg-slate-800/90 rounded-2xl border border-slate-200/90 dark:border-slate-700 shadow-sm flex flex-col overflow-hidden shrink-0" aria-label="Pohon node jaringan">
            {{-- Tree Header & Filter --}}
            <div class="p-4 border-b border-slate-100 dark:border-slate-700/80 space-y-3 bg-white dark:bg-slate-800/90">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                            <svg class="h-4 w-4 text-blue-600 dark:text-blue-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h8m-8 6h16" />
                            </svg>
                            <span>Hirarki Topologi Node</span>
                        </h2>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">Pilih node untuk mengelola port &amp; lokasi</p>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <button type="button" @click="expandAll(true)" class="px-2 py-1 text-xs font-medium text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-700 rounded-md transition cursor-pointer" title="Buka Semua Hirarki">
                            Buka Semua
                        </button>
                        <button type="button" @click="expandAll(false)" class="px-2 py-1 text-xs font-medium text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-700 rounded-md transition cursor-pointer" title="Tutup Semua Hirarki">
                            Tutup
                        </button>
                    </div>
                </div>

                {{-- Search & Type Filter inside Tree (Law of Proximity) --}}
                <div class="flex gap-2">
                    <div class="relative flex-1">
                        <svg class="h-3.5 w-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input x-model="treeSearchQuery" type="text" placeholder="Filter nama node / kode..."
                               class="w-full pl-8 pr-3 py-1.5 text-xs bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-800 dark:text-slate-100 focus:bg-white dark:focus:bg-slate-800 focus:border-blue-500 outline-none transition">
                    </div>
                    <select x-model="typeFilter" class="text-xs bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg px-2.5 py-1.5 text-slate-700 dark:text-slate-200 font-medium focus:bg-white dark:focus:bg-slate-800 focus:border-blue-500 outline-none cursor-pointer">
                        <option value="all">Semua Tipe</option>
                        @foreach($nodeTypes as $nodeType)
                            <option value="{{ $nodeType->id }}">{{ $nodeType->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Legend / Quick Summary Bar --}}
            <div class="px-4 py-2 bg-slate-50 dark:bg-slate-900/60 border-b border-slate-100 dark:border-slate-700/80 flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
                <span>Total: <strong class="text-slate-700 dark:text-slate-200 font-mono">{{ $totalNodesCount }}</strong> Node</span>
                <div class="flex items-center gap-3">
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> Normal</span>
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-rose-500"></span> Red Zone</span>
                </div>
            </div>

            {{-- Scrollable Tree List (Single-line indent, zero nested boxes!) --}}
            <div id="networkTreeContainer" class="max-h-[calc(100vh-280px)] overflow-y-auto p-3 space-y-0.5 select-none">
                {{-- Proyeksi hierarki tunggal (InfrastructureHierarchyService) — sama
                     dengan Peta: Server → POP → Mini POP → OLT/Switch → Slot → PON/Port →
                     ODP → ONT. Garis koneksi (ODP di bawah PON) dibedakan dari lokasi. --}}
                @forelse($hierarchy['roots'] as $hierarchyKey)
                    @include('network.nodes._hierarchy-item', ['hierarchyKey' => $hierarchyKey])
                @empty
                    <div class="rounded-xl border border-dashed border-slate-200 dark:border-slate-700 p-6 text-center text-xs text-slate-400">
                        Belum ada POP dalam scope Anda.
                    </div>
                @endforelse
            </div>
        </section>

        {{-- ========================================================================= --}}
        {{-- PANEL KANAN: DETAIL NODE & MANAJEMEN PORT (flex-1)                        --}}
        {{-- ========================================================================= --}}
        <section class="flex-1 w-full bg-white dark:bg-slate-800/90 rounded-2xl border border-slate-200/90 dark:border-slate-700 shadow-sm flex flex-col overflow-hidden" id="node-inspector">
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

            <div x-show="selectedNodeId === {{ $node->id }}"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 id="inspector-node-{{ $node->id }}">

                <div class="flex flex-col" id="node-{{ $node->id }}">

                    {{-- Sticky Top Bar: Breadcrumb Path & Quick Actions matching master-node-whusnet.html --}}
                    <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700/80 flex flex-wrap items-center justify-between gap-3 bg-slate-50/50 dark:bg-slate-900/40">
                        {{-- Hierarchy Path Breadcrumb --}}
                        <div id="nodeBreadcrumb" class="flex items-center flex-wrap gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                            @foreach($breadcrumbParts as $index => $part)
                                @if(! $loop->last)
                                    <span class="hover:text-blue-600 transition font-mono">{{ $part }}</span>
                                    <svg class="w-3 h-3 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                    </svg>
                                @else
                                    <span class="hover:text-blue-600 transition font-bold text-slate-800 dark:text-slate-100 bg-white dark:bg-slate-800 px-2 py-0.5 rounded-md border border-slate-200 dark:border-slate-700 shadow-2xs font-mono">
                                        {{ $part }}
                                    </span>
                                @endif
                            @endforeach
                        </div>

                        {{-- Primary Action Buttons --}}
                        <div class="flex items-center gap-2">
                            @can('network_nodes.create')
                            <button type="button" @click="openAddChildModal({{ $node->id }}, @js($node->code), @js($node->name))"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 transition shadow-sm cursor-pointer">
                                <svg class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                </svg>
                                <span>+ Sub-Node</span>
                            </button>
                            @endcan

                            @can('network_nodes.update')
                            <button type="button" @click="openEditModal({{ $node->id }}, @js($node->code), @js($node->name), {{ $node->node_type_id }}, @js($node->status), @js($location['address'] ?? ''), @js($location['village'] ?? ''), @js($location['district'] ?? ''), @js($location['city'] ?? ''), @js($location['latitude'] ?? ''), @js($location['longitude'] ?? ''))"
                                    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-xs font-semibold bg-blue-600 text-white hover:bg-blue-700 transition shadow-sm shadow-blue-600/10 cursor-pointer">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                <span>Edit Detail Node</span>
                            </button>
                            @endcan

                            @can('warehouse_assets.create')
                            <a href="{{ route('network.nodes.register-asset.create', $node) }}"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 transition shadow-sm"
                               title="Daftarkan Aset / Barcode">
                                <svg class="w-3.5 h-3.5 text-indigo-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                </svg>
                                <span>Registrasi Aset</span>
                            </a>
                            @endcan
                        </div>
                    </div>

                    {{-- Main Scrollable Inspector Body --}}
                    <div class="p-6 space-y-6">

                        {{-- 1. Node Identity & Capacity Utilization Card --}}
                        <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-6 pb-6 border-b border-slate-100 dark:border-slate-800">
                            <div class="space-y-2 min-w-0">
                                <div class="flex flex-wrap items-center gap-2.5">
                                    <span class="px-2.5 py-1 rounded-md text-xs font-mono font-bold uppercase tracking-wider border
                                        @if(str_contains($typeCode, 'odp')) bg-emerald-50 text-emerald-700 dark:bg-emerald-950/70 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800
                                        @elseif(str_contains($typeCode, 'feeder')) bg-sky-50 text-sky-700 dark:bg-sky-950/70 dark:text-sky-300 border-sky-200 dark:border-sky-800
                                        @elseif(str_contains($typeCode, 'olt') || str_contains($typeCode, 'pon')) bg-amber-50 text-amber-800 dark:bg-amber-950/70 dark:text-amber-300 border-amber-200 dark:border-amber-800
                                        @elseif(str_contains($typeCode, 'aggregation')) bg-purple-50 text-purple-700 dark:bg-purple-950/70 dark:text-purple-300 border-purple-200 dark:border-purple-800
                                        @elseif(str_contains($typeCode, 'backbone')) bg-indigo-50 text-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800
                                        @else bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-300 dark:border-slate-700 @endif">
                                        {{ $node->nodeType?->name }}
                                    </span>

                                    <h1 class="text-xl sm:text-2xl font-bold font-mono text-slate-900 dark:text-slate-100 tracking-tight">
                                        {{ $node->code }}
                                    </h1>
                                    <span class="text-slate-300 dark:text-slate-600 text-xl font-light">&bull;</span>
                                    <span class="text-base sm:text-lg font-medium text-slate-600 dark:text-slate-300 truncate">
                                        {{ $node->name }}
                                    </span>

                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                        Active
                                    </span>
                                </div>

                                <p class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-2">
                                    <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span>Tipe Perangkat: <strong>{{ $node->nodeType?->name }}</strong></span>
                                    @if($node->anchorPop)
                                        <span class="text-slate-300 dark:text-slate-600">&bull;</span>
                                        <span>POP: <strong>{{ $node->anchorPop->name }}</strong></span>
                                    @endif
                                </p>
                            </div>

                            {{-- Visual Utilization Meter (Goal-Gradient Effect) --}}
                            @if($node->nodeType?->has_ports)
                            <div class="bg-slate-50 dark:bg-slate-900/60 rounded-xl p-3.5 border border-slate-200/80 dark:border-slate-700 min-w-[260px] sm:min-w-[300px]">
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-400">Okupansi Port</span>
                                    <span class="text-xs font-mono font-bold px-2 py-0.5 rounded {{ $isRedZone ? 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300 border border-rose-300/80 animate-pulse' : 'bg-slate-200 dark:bg-slate-700 text-slate-800 dark:text-slate-200' }}"
                                          data-red-zone="{{ $isRedZone ? '1' : '0' }}">
                                        {{ $isRedZone ? 'Red Zone · ' : '' }}{{ $usagePercent }}% Terpakai
                                    </span>
                                </div>
                                <div class="w-full h-2.5 bg-slate-200 dark:bg-slate-700 rounded-full overflow-hidden mb-2">
                                    <div class="h-full transition-all duration-300 {{ $isRedZone ? 'bg-rose-500' : 'bg-emerald-500' }}" style="width: {{ $usagePercent }}%;"></div>
                                </div>
                                <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
                                    <span><strong class="text-slate-800 dark:text-slate-200 font-mono">{{ $node->used_ports_count }}</strong> dari <strong class="text-slate-800 dark:text-slate-200 font-mono">{{ $node->ports_count }}</strong> port terpakai</span>
                                    <span class="{{ $isRedZone ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }} font-medium font-mono">
                                        {{ $node->ports_count - $node->used_ports_count }} slot tersedia
                                    </span>
                                </div>
                            </div>
                            @endif
                        </div>

                        {{-- 2. Physical Location & Coordinates Section --}}
                        <div class="bg-slate-50/70 dark:bg-slate-900/40 rounded-xl border border-slate-200/80 dark:border-slate-700 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-lg bg-blue-100/80 dark:bg-blue-950/70 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </div>
                                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">Lokasi Fisik &amp; Koordinat</h3>
                                </div>
                                <span class="inline-flex items-center gap-1.5 text-[11px] font-medium {{ $location['source_node_id'] === $node->id ? 'text-emerald-700 bg-emerald-50 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' : 'text-blue-700 bg-blue-50 dark:bg-blue-950/60 dark:text-blue-300 border border-blue-200/60 dark:border-blue-800' }} px-2.5 py-1 rounded-full">
                                    {{ $location['source_node_id'] === $node->id ? 'Titik Koordinat Mandiri' : 'Mewarisi lokasi Induk / POP' }}
                                </span>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-1">
                                <div class="bg-white dark:bg-slate-800 p-3 rounded-lg border border-slate-200/60 dark:border-slate-700">
                                    <div class="text-[11px] text-slate-400 font-medium mb-1">Alamat / Patokan Tiang</div>
                                    <div class="text-xs font-medium text-slate-700 dark:text-slate-200">{{ $location['address'] ?: '—' }}</div>
                                </div>
                                <div class="bg-white dark:bg-slate-800 p-3 rounded-lg border border-slate-200/60 dark:border-slate-700">
                                    <div class="text-[11px] text-slate-400 font-medium mb-1">Wilayah / Coverage Area</div>
                                    <div class="text-xs font-medium text-slate-700 dark:text-slate-200">{{ collect([$location['village'], $location['district'], $location['city']])->filter()->implode(', ') ?: '—' }}</div>
                                </div>
                                <div class="bg-white dark:bg-slate-800 p-3 rounded-lg border border-slate-200/60 dark:border-slate-700 flex items-center justify-between">
                                    <div>
                                        <div class="text-[11px] text-slate-400 font-medium mb-1">Koordinat Maps</div>
                                        <div class="text-xs font-mono font-medium text-slate-700 dark:text-slate-200">
                                            {{ $location['latitude'] !== null && $location['longitude'] !== null ? "{$location['latitude']}, {$location['longitude']}" : '—' }}
                                        </div>
                                    </div>
                                    @if($location['latitude'] !== null && $location['longitude'] !== null)
                                    <a href="https://maps.google.com/?q={{ $location['latitude'] }},{{ $location['longitude'] }}" target="_blank" rel="noopener"
                                       class="p-1.5 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-950 rounded-lg transition" title="Buka di Google Maps">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                    </a>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- 3. Port Management Section & Hardware Patch Panel Grid --}}
                        @if($node->nodeType?->has_ports || $node->ports->isNotEmpty())
                        <div class="space-y-4">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="w-2.5 h-2.5 rounded-full bg-blue-600"></span>
                                        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 uppercase tracking-wide">
                                            Manajemen Port Node &bull; <span class="text-blue-600 dark:text-blue-400">{{ $node->nodeType?->name }}</span>
                                        </h3>
                                    </div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                        Klik pada port untuk menghubungkan pelanggan, melihat detail tujuan, atau menandai port.
                                    </p>
                                </div>

                                {{-- Port Filter Controls & Add Port Inline Form --}}
                                <div class="flex items-center gap-2 self-start sm:self-auto flex-wrap">
                                    <div class="flex items-center bg-slate-100 dark:bg-slate-900 rounded-lg p-1 border border-slate-200 dark:border-slate-700">
                                        <button type="button" @click="portFilter = 'ALL'"
                                                :class="{ 'bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 shadow-sm font-semibold': portFilter === 'ALL', 'text-slate-600 dark:text-slate-400 hover:text-slate-900': portFilter !== 'ALL' }"
                                                class="px-2.5 py-1 rounded-md text-xs transition cursor-pointer">
                                            Semua
                                        </button>
                                        <button type="button" @click="portFilter = 'AVAILABLE'"
                                                :class="{ 'bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 shadow-sm font-semibold': portFilter === 'AVAILABLE', 'text-slate-600 dark:text-slate-400 hover:text-slate-900': portFilter !== 'AVAILABLE' }"
                                                class="px-2.5 py-1 rounded-md text-xs transition cursor-pointer">
                                            Tersedia
                                        </button>
                                        <button type="button" @click="portFilter = 'USED'"
                                                :class="{ 'bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 shadow-sm font-semibold': portFilter === 'USED', 'text-slate-600 dark:text-slate-400 hover:text-slate-900': portFilter !== 'USED' }"
                                                class="px-2.5 py-1 rounded-md text-xs transition cursor-pointer">
                                            Terpakai
                                        </button>
                                    </div>

                                    @can('network_nodes.update')
                                    <div class="h-6 w-px bg-slate-200 dark:bg-slate-700 mx-1 hidden sm:block"></div>

                                    <form method="POST" action="{{ route('network.nodes.ports.add', $node) }}" class="flex items-center">
                                        @csrf
                                        <input type="number" name="additional_capacity" min="1" max="1000" value="4" required
                                               class="w-14 px-2.5 py-1.5 text-xs font-mono font-semibold text-center bg-white dark:bg-slate-800 border border-r-0 border-slate-300 dark:border-slate-600 rounded-l-lg focus:border-blue-500 outline-none" title="Jumlah Port Baru">
                                        <button type="submit" class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold rounded-r-lg transition flex items-center gap-1.5 shadow-sm cursor-pointer">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                            </svg>
                                            <span>Tambah Port</span>
                                        </button>
                                    </form>
                                    @endcan
                                </div>
                            </div>

                            {{-- Hardware Patch Panel Container --}}
                            <div class="bg-slate-50 dark:bg-slate-900/60 p-4 rounded-2xl border border-slate-200/90 dark:border-slate-700/80">
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                                    @forelse($node->ports->sortBy('number') as $port)
                                        @php($assignment = $port->activeAssignment->first())
                                        @can('network_nodes.update')
                                            @if($port->is_used)
                                                {{-- Port Terpakai (Matching master-node-whusnet.html) --}}
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
                                                     class="group bg-white dark:bg-slate-800 hover:bg-blue-50/30 dark:hover:bg-blue-950/30 border border-slate-200 dark:border-slate-700 hover:border-blue-400 dark:hover:border-blue-500 rounded-xl p-3.5 cursor-pointer transition-all shadow-2xs flex flex-col justify-between min-h-[88px]">
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-xs font-bold font-mono text-blue-700 dark:text-blue-400">Port {{ $port->number }}</span>
                                                        <span class="inline-flex items-center gap-1 text-[10px] font-mono font-semibold text-rose-700 dark:text-rose-300 bg-rose-50 dark:bg-rose-950/60 px-2 py-0.5 rounded-full border border-rose-200 dark:border-rose-900">
                                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                                            Terpakai
                                                        </span>
                                                    </div>
                                                    <div class="mt-2 min-w-0">
                                                        <div class="text-xs font-semibold text-slate-800 dark:text-slate-200 truncate" title="{{ $assignment?->customer ? $assignment->customer->full_name : ($assignment?->targetNode ? $assignment->targetNode->code . ' · ' . $assignment->targetNode->name : 'Terpakai') }}">
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
                                                        <div class="text-[11px] font-mono text-slate-400 flex items-center justify-between mt-0.5">
                                                            <span class="truncate">
                                                                @if($assignment?->customer)
                                                                    {{ $assignment->customer->customer_code ?: ($assignment->customer->cid ?: '#'.$assignment->customer->id) }}
                                                                @elseif($assignment?->targetNode)
                                                                    Sub-Node
                                                                @else
                                                                    Manual
                                                                @endif
                                                            </span>
                                                            <span class="text-blue-600 dark:text-blue-400 opacity-0 group-hover:opacity-100 font-sans font-medium text-[10px] transition shrink-0">Edit &rarr;</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            @else
                                                {{-- Port Tersedia (Matching master-node-whusnet.html) --}}
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
                                                     class="group bg-white dark:bg-slate-800 hover:bg-emerald-50/50 dark:hover:bg-emerald-950/30 border-2 border-dashed border-emerald-300/90 dark:border-emerald-700/80 hover:border-emerald-500 rounded-xl p-3.5 cursor-pointer transition-all flex flex-col justify-between min-h-[88px]">
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-xs font-bold font-mono text-slate-800 dark:text-slate-200 group-hover:text-emerald-900 dark:group-hover:text-emerald-200">Port {{ $port->number }}</span>
                                                        <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/60 px-2 py-0.5 rounded-full border border-emerald-200 dark:border-emerald-800">
                                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                            Tersedia
                                                        </span>
                                                    </div>
                                                    <div class="mt-3 flex items-center justify-between text-xs text-emerald-700 dark:text-emerald-300 font-medium">
                                                        <span class="flex items-center gap-1">
                                                            <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                                            </svg>
                                                            <span>Hubungkan Tujuan</span>
                                                        </span>
                                                        <svg class="w-3.5 h-3.5 opacity-0 group-hover:opacity-100 transition shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                                        </svg>
                                                    </div>
                                                </div>
                                            @endif
                                        @else
                                            <div class="p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs space-y-1">
                                                <div class="flex items-center justify-between gap-1">
                                                    <span class="font-mono font-bold">Port {{ $port->number }}</span>
                                                    <span class="w-2 h-2 rounded-full {{ $port->is_used ? 'bg-rose-500' : 'bg-emerald-500' }}"></span>
                                                </div>
                                                <div class="text-[11px] text-slate-500 truncate">
                                                    @if($assignment?->customer) Pelanggan: {{ $assignment->customer->full_name }}
                                                    @elseif($assignment?->targetNode) &rarr; {{ $assignment->targetNode->code }}
                                                    @elseif($assignment?->manual_label) {{ $assignment->manual_label }}
                                                    @else {{ $port->is_used ? 'Terpakai' : 'Kosong' }} @endif
                                                </div>
                                            </div>
                                        @endcan
                                    @empty
                                        <div class="col-span-full p-6 rounded-xl border border-dashed border-slate-200 dark:border-slate-700 text-center text-xs text-slate-400">
                                            Belum ada port yang dibuat untuk node ini. Klik "Tambah Port" di atas untuk menambahkan slot.
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                        @endif

                        {{-- 4. Aset Terpasang (Gudang & Material) --}}
                        @if($node->deployedSerials->isNotEmpty() || $node->infrastructureBalances->isNotEmpty())
                        <div class="space-y-2.5 pt-4 border-t border-slate-100 dark:border-slate-800">
                            <div class="flex items-center gap-2">
                                <span class="w-1.5 h-3.5 bg-indigo-600 rounded-full"></span>
                                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 dark:text-slate-200">Aset Terpasang</h3>
                            </div>

                            <div class="flex flex-wrap gap-2 pt-1">
                                @foreach($node->deployedSerials as $serial)
                                <a href="{{ route('warehouse.traceability.index', ['sn' => $serial->serial_number]) }}"
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-blue-200 dark:border-blue-900 bg-blue-50 dark:bg-blue-950/60 text-xs font-mono font-semibold text-blue-700 dark:text-blue-300 hover:border-blue-400 transition shadow-2xs"
                                   title="Lihat riwayat SN {{ $serial->serial_number }}">
                                    <svg class="h-3.5 w-3.5 text-blue-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                                    </svg>
                                    <span>SN {{ $serial->serial_number }} &bull; {{ $serial->item?->name }}</span>
                                </a>
                                @endforeach

                                @foreach($node->infrastructureBalances as $balance)
                                @if((float) $balance->qty > 0)
                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-semibold text-slate-700 dark:text-slate-300">
                                    <svg class="h-3.5 w-3.5 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                    </svg>
                                    <span>{{ $balance->item?->name }}{{ $balance->lot_no ? ' ('.$balance->lot_no.')' : '' }} &bull; {{ rtrim(rtrim(number_format((float) $balance->qty, 2, ',', '.'), '0'), ',') }} {{ $balance->item?->unit }}</span>
                                </span>
                                @endif
                                @endforeach
                            </div>
                        </div>
                        @endif

                        {{-- 5. Child Downstream Nodes (Tracing siapa anak di bawah node ini) --}}
                        @if($childDownstreams->isNotEmpty())
                        <div class="space-y-2.5 pt-4 border-t border-slate-100 dark:border-slate-800">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="w-1.5 h-3.5 bg-violet-600 rounded-full"></span>
                                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 dark:text-slate-200">
                                        Sub-Node Turunan Langsung ({{ $childDownstreams->count() }} Downstream)
                                    </h3>
                                </div>
                                <span class="text-[10px] text-slate-400">Klik node untuk beralih inspeksi</span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2.5 pt-1">
                                @foreach($childDownstreams as $child)
                                <button type="button" @click="selectNode({{ $child->id }})"
                                        class="text-left p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:border-blue-400 dark:hover:border-blue-500 hover:bg-blue-50/40 dark:hover:bg-blue-950/30 transition-all shadow-2xs group cursor-pointer">
                                    <div class="flex items-center justify-between gap-1">
                                        <span class="font-mono font-bold text-blue-600 dark:text-blue-400 text-xs">{{ $child->code }}</span>
                                        <span class="text-[10px] font-bold text-slate-400">({{ $child->nodeType?->name }})</span>
                                    </div>
                                    <div class="text-xs font-semibold text-slate-800 dark:text-slate-200 truncate mt-1">{{ $child->name }}</div>
                                    <div class="text-[10px] text-slate-400 mt-0.5">
                                        {{ $child->used_ports_count ?? 0 }}/{{ $child->ports_count ?? 0 }} port terpakai
                                    </div>
                                </button>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        {{-- 6. Node Relocation / Pindah Posisi Induk Node matching master-node-whusnet.html --}}
                        @can('network_nodes.update')
                        <div class="pt-4 border-t border-slate-100 dark:border-slate-800">
                            <div class="border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden bg-white dark:bg-slate-800">
                                <button type="button" @click="toggleRelocate({{ $node->id }})"
                                        class="w-full px-4 py-3.5 flex items-center justify-between text-left hover:bg-slate-50 dark:hover:bg-slate-700/50 transition cursor-pointer select-none">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 border border-amber-200/60 dark:border-amber-800 flex items-center justify-center shrink-0">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                            </svg>
                                        </div>
                                        <div>
                                            <div class="text-xs font-bold text-slate-800 dark:text-slate-100">Relokasi / Pindah Posisi Induk Node (Uplink)</div>
                                            <div class="text-[11px] text-slate-500 dark:text-slate-400">Pindahkan node ini beserta seluruh turunannya ke POP, OLT, atau Feeder lain</div>
                                        </div>
                                    </div>
                                    <svg class="w-4 h-4 text-slate-400 transition-transform duration-200 shrink-0"
                                         :class="{ 'rotate-180': openRelocateId === {{ $node->id }} }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>

                                <div x-show="openRelocateId === {{ $node->id }}" x-cloak class="px-4 pb-4 pt-2 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40">
                                    <form method="POST" action="{{ route('network.nodes.move', $node) }}"
                                          x-data="{ placement: @js($node->parent_id ? 'node' : 'pop') }"
                                          class="space-y-3 pt-1">
                                        @csrf
                                        @method('PATCH')
                                        <div class="grid gap-3 sm:grid-cols-2">
                                            <div>
                                                <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1">Pindah ke</label>
                                                <select x-model="placement" name="placement" class="block w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-800 dark:text-slate-100 text-xs focus:border-blue-500 outline-none cursor-pointer">
                                                    <option value="pop">Cabang / POP</option>
                                                    <option value="node">Node Induk Lain</option>
                                                </select>
                                            </div>

                                            <div x-show="placement === 'pop'">
                                                <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1">POP Tujuan</label>
                                                <select name="anchor_pop_id" x-bind:disabled="placement !== 'pop'" x-bind:required="placement === 'pop'"
                                                        class="block w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-800 dark:text-slate-100 text-xs focus:border-blue-500 outline-none cursor-pointer">
                                                    <option value="">Pilih POP</option>
                                                    @foreach($pops as $pop)
                                                    <option value="{{ $pop->id }}" @selected($node->anchor_pop_id == $pop->id)>{{ $pop->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div x-show="placement === 'node'">
                                                <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1">Node Induk Tujuan</label>
                                                <select name="parent_id" x-bind:disabled="placement !== 'node'" x-bind:required="placement === 'node'"
                                                        class="block w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-800 dark:text-slate-100 text-xs focus:border-blue-500 outline-none cursor-pointer">
                                                    <option value="">Pilih Node Induk</option>
                                                    @foreach($parentOptions as $parentOption)
                                                    @if($parentOption->id !== $node->id)
                                                    <option value="{{ $parentOption->id }}" @selected($node->parent_id == $parentOption->id)>{{ $breadcrumbLabels[$parentOption->id] ?? $parentOption->name }}</option>
                                                    @endif
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="pt-1 flex justify-end">
                                            <button type="submit" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold rounded-lg transition shadow-sm cursor-pointer">
                                                Simpan Relokasi
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        @endcan

                    </div>
                </div>
            </div>
            @empty
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-12 text-center text-xs text-slate-400 space-y-2">
                <p class="font-bold text-slate-800 dark:text-slate-100 text-sm">Belum ada node jaringan pada POP yang dapat Anda akses.</p>
                <p>Klik tombol "+ Tambah Node Baru" di atas untuk menambahkan titik jaringan.</p>
            </div>
            @endforelse
        </section>
    </div>

    {{-- ========================================================================= --}}
    {{-- MODAL TAMBAH NODE BARU (Sub-Node & Root Node Dialog)                      --}}
    {{-- ========================================================================= --}}
    @can('network_nodes.create')
    <div x-show="showCreateModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs"
         @keydown.escape.window="showCreateModal = false">
        <div @click.outside="showCreateModal = false"
             class="w-full max-w-3xl rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-2xl overflow-hidden max-h-[90vh] flex flex-col animate-in fade-in zoom-in duration-150">

            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="p-2 rounded-lg bg-blue-600 text-white shadow-sm">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-800 dark:text-slate-100">Tambah Titik Node Jaringan Baru</h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">Daftarkan ODC, ODP, Router, atau PON Port ke pohon topologi</p>
                    </div>
                </div>
                <button type="button" @click="showCreateModal = false" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 cursor-pointer p-1 rounded-lg">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form method="POST" action="{{ route('network.nodes.store') }}" class="p-6 overflow-y-auto space-y-4">
                @csrf
                <div class="rounded-xl border border-blue-200 dark:border-blue-900 bg-blue-50 dark:bg-blue-950/30 p-3 text-xs text-blue-900 dark:text-blue-200">
                    <p class="font-bold">Pusat / Server Utama, Cabang, dan Mini POP terhubung otomatis dari Master POP.</p>
                    <p class="mt-1">Pilih perangkat jaringan seperti router, OLT, ODC, atau ODP, lalu tentukan posisi induknya.</p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300">Tipe Perangkat <span class="text-rose-500">*</span></label>
                        <select name="node_type_id" required x-on:change="hasPorts = $event.target.selectedOptions[0]?.dataset.hasPorts === '1'"
                                class="block w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-800 dark:text-slate-100 text-xs focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 outline-none transition cursor-pointer">
                            <option value="">Pilih Tipe Perangkat</option>
                            @foreach($nodeTypes as $nodeType)
                            <option value="{{ $nodeType->id }}" data-has-ports="{{ $nodeType->has_ports ? '1' : '0' }}"
                                    @selected(old('node_type_id') == $nodeType->id)>{{ $nodeType->name }} @if($nodeType->has_ports)(Ada Port)@endif</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300">Koneksi Jalur <span class="text-rose-500">*</span></label>
                        <select x-model="placement" name="placement" class="block w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-800 dark:text-slate-100 text-xs focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 outline-none transition cursor-pointer">
                            <option value="pop">Menempel ke POP</option>
                            <option value="node">Anak dari Node Jaringan (Sub-Node)</option>
                        </select>
                    </div>

                    <div x-show="placement === 'pop'" class="space-y-1">
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300">POP / Cabang <span class="text-rose-500">*</span></label>
                        <select name="anchor_pop_id" x-bind:disabled="placement !== 'pop'" x-bind:required="placement === 'pop'"
                                class="block w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-800 dark:text-slate-100 text-xs focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 outline-none transition cursor-pointer">
                            <option value="">Pilih POP</option>
                            @foreach($pops as $pop)
                            <option value="{{ $pop->id }}" @selected(old('anchor_pop_id') == $pop->id)>{{ $pop->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div x-show="placement === 'node'" class="space-y-1 sm:col-span-2 lg:col-span-1">
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300">Node Induk (Parent) <span class="text-rose-500">*</span></label>
                        <select id="create-modal-parent-select" name="parent_id" x-model="createParentId" x-bind:disabled="placement !== 'node'" x-bind:required="placement === 'node'"
                                class="block w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-800 dark:text-slate-100 text-xs focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 outline-none transition cursor-pointer">
                            <option value="">Pilih Node Induk</option>
                            @foreach($parentOptions as $parentOption)
                            <option value="{{ $parentOption->id }}" @selected(old('parent_id') == $parentOption->id)>
                                {{ $breadcrumbLabels[$parentOption->id] ?? $parentOption->name }} ({{ $parentOption->nodeType?->name }})
                            </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300">Kode Node <span class="text-rose-500">*</span></label>
                        <input type="text" name="code" value="{{ old('code') }}" required placeholder="Mis. ODP-SIMAN-01"
                               class="block w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-800 dark:text-slate-100 text-xs font-mono uppercase focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 outline-none transition">
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300">Nama Node <span class="text-rose-500">*</span></label>
                        <input type="text" name="name" value="{{ old('name') }}" required placeholder="Mis. ODP Siman Perempatan"
                               class="block w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-800 dark:text-slate-100 text-xs focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 outline-none transition">
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300">
                            <span x-text="hasPorts ? 'Kapasitas Port Awal' : 'Kapasitas / Keterangan (Opsional)'"></span>
                        </label>
                        <input id="create-modal-capacity-input" type="number" name="capacity" value="{{ old('capacity', 8) }}" min="0" max="1000"
                               class="block w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-800 dark:text-slate-100 text-xs font-mono focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 outline-none transition">
                        <div x-show="hasPorts" class="flex items-center gap-1.5 pt-1">
                            <span class="text-[10px] text-slate-400">Preset:</span>
                            <button type="button" @click="quickCapacity(8)" class="px-2 py-0.5 rounded border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-[10px] font-mono hover:bg-slate-50 cursor-pointer">8</button>
                            <button type="button" @click="quickCapacity(12)" class="px-2 py-0.5 rounded border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-[10px] font-mono hover:bg-slate-50 cursor-pointer">12</button>
                            <button type="button" @click="quickCapacity(16)" class="px-2 py-0.5 rounded border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-[10px] font-mono hover:bg-slate-50 cursor-pointer">16</button>
                            <button type="button" @click="quickCapacity(24)" class="px-2 py-0.5 rounded border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-[10px] font-mono hover:bg-slate-50 cursor-pointer">24</button>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300">Alamat / Patokan Tiang</label>
                        <input type="text" name="address" value="{{ old('address') }}" placeholder="Mis. Depan Balai Desa Siman Tiang T-04"
                               class="block w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-800 dark:text-slate-100 text-xs focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 outline-none transition">
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300">Latitude</label>
                        <input type="number" step="0.0000001" name="latitude" value="{{ old('latitude') }}" placeholder="-7.865120"
                               class="block w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-800 dark:text-slate-100 text-xs font-mono focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 outline-none transition">
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300">Longitude</label>
                        <input type="number" step="0.0000001" name="longitude" value="{{ old('longitude') }}" placeholder="111.469800"
                               class="block w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-800 dark:text-slate-100 text-xs font-mono focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 outline-none transition">
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 dark:border-slate-700 flex items-center justify-end gap-2.5">
                    <button type="button" @click="showCreateModal = false"
                            class="px-4 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold cursor-pointer transition-colors">
                        Batal
                    </button>
                    <button type="submit"
                            class="px-5 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-sm cursor-pointer transition-colors">
                        Simpan Node Baru
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endcan

    {{-- ========================================================================= --}}
    {{-- MODAL EDIT DETAIL NODE                                                   --}}
    {{-- ========================================================================= --}}
    @can('network_nodes.update')
    <div x-show="editModal.open" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs"
         @keydown.escape.window="editModal.open = false">
        <div @click.outside="editModal.open = false"
             class="w-full max-w-2xl rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-2xl overflow-hidden max-h-[90vh] flex flex-col animate-in fade-in zoom-in duration-150">

            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="p-2 rounded-lg bg-blue-600 text-white shadow-sm">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-800 dark:text-slate-100">Edit Detail Node</h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400" x-text="'Perbarui data untuk node ' + editModal.code"></p>
                    </div>
                </div>
                <button type="button" @click="editModal.open = false" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 cursor-pointer p-1 rounded-lg">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form :action="'/network/nodes/' + editModal.id" method="POST" class="p-6 overflow-y-auto space-y-4">
                @csrf
                @method('PATCH')

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300">Kode Node</label>
                        <input type="text" name="code" x-model="editModal.code" required
                               class="block w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-800 dark:text-slate-100 text-xs font-mono uppercase focus:border-blue-500 outline-none">
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300">Nama Node <span class="text-rose-500">*</span></label>
                        <input type="text" name="name" x-model="editModal.name" required
                               class="block w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-800 dark:text-slate-100 text-xs focus:border-blue-500 outline-none">
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300">Status Operasional</label>
                        <select name="status" x-model="editModal.status"
                                class="block w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-800 dark:text-slate-100 text-xs focus:border-blue-500 outline-none cursor-pointer">
                            <option value="active">Active (Beroperasi)</option>
                            <option value="inactive">Inactive (Nonaktif)</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300">Alamat / Patokan Tiang</label>
                        <input type="text" name="address" x-model="editModal.address" placeholder="Patokan Tiang"
                               class="block w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-800 dark:text-slate-100 text-xs focus:border-blue-500 outline-none">
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300">Latitude</label>
                        <input type="number" step="0.0000001" name="latitude" x-model="editModal.latitude" placeholder="-7.865120"
                               class="block w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-800 dark:text-slate-100 text-xs font-mono focus:border-blue-500 outline-none">
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300">Longitude</label>
                        <input type="number" step="0.0000001" name="longitude" x-model="editModal.longitude" placeholder="111.469800"
                               class="block w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-800 dark:text-slate-100 text-xs font-mono focus:border-blue-500 outline-none">
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 dark:border-slate-700 flex items-center justify-end gap-2.5">
                    <button type="button" @click="editModal.open = false"
                            class="px-4 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold cursor-pointer transition-colors">
                        Batal
                    </button>
                    <button type="submit"
                            class="px-5 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-sm cursor-pointer transition-colors">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endcan

    {{-- ========================================================================= --}}
    {{-- MODAL SAMBUNG / KELOLA PENGHUNI PORT (Matching portModal in prototype)    --}}
    {{-- ========================================================================= --}}
    @can('network_nodes.update')
    <div x-show="assignModal.open" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs"
         @keydown.escape.window="assignModal.open = false">
        <div @click.outside="assignModal.open = false"
             class="w-full max-w-lg rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-150">

            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="p-2 rounded-lg" :class="assignModal.isUsed ? 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-800 dark:text-slate-100 flex items-center gap-1.5">
                            <span>Port</span>
                            <span class="font-mono text-blue-600 dark:text-blue-400" x-text="assignModal.portNumber"></span>
                            <span>&bull;</span>
                            <span x-text="assignModal.isUsed ? 'Status Terpakai' : 'Sambungkan Tujuan'"></span>
                        </h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400" x-text="assignModal.nodeCode + ' · ' + assignModal.nodeName"></p>
                    </div>
                </div>
                <button type="button" @click="assignModal.open = false" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 cursor-pointer p-1 rounded-lg">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- 1. State: Port SUDAH TERPAKAI --}}
            <template x-if="assignModal.isUsed">
                <div class="p-6 space-y-4">
                    <div class="p-4 rounded-xl border border-rose-200 dark:border-rose-900 bg-rose-50/70 dark:bg-rose-950/30 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-rose-700 dark:text-rose-300">Penghuni Port Aktif</span>
                            <span class="text-xs font-mono font-bold text-rose-600 bg-white dark:bg-rose-900/60 px-2 py-0.5 rounded border border-rose-200 dark:border-rose-800"
                                  x-text="assignModal.tenantType === 'customer' ? 'Pelanggan' : (assignModal.tenantType === 'node' ? 'Sub-Node' : 'Manual')"></span>
                        </div>
                        <div class="text-base font-bold text-slate-900 dark:text-slate-100" x-text="assignModal.tenantName"></div>
                        <template x-if="assignModal.tenantSubtitle">
                            <div class="text-xs font-mono text-slate-500 dark:text-slate-400" x-text="assignModal.tenantSubtitle"></div>
                        </template>
                    </div>

                    <div class="flex items-center justify-between gap-3 pt-2">
                        <div>
                            <template x-if="assignModal.customerUrl">
                                <a :href="assignModal.customerUrl"
                                   class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-200 transition shadow-2xs">
                                    <span>Lihat Profil Pelanggan</span>
                                    <svg class="w-3.5 h-3.5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
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
                                    class="px-4 py-2 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shadow-sm cursor-pointer transition">
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
                    <div class="flex border-b border-slate-200 dark:border-slate-700 gap-2 text-xs font-semibold pb-1">
                        <button type="button" @click="assignModal.type = 'customer'"
                                :class="assignModal.type === 'customer' ? 'text-blue-600 border-b-2 border-blue-600 pb-2' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 pb-2'"
                                class="transition cursor-pointer">
                            Sambung Pelanggan
                        </button>
                        <button type="button" @click="assignModal.type = 'node'"
                                :class="assignModal.type === 'node' ? 'text-blue-600 border-b-2 border-blue-600 pb-2' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 pb-2'"
                                class="transition cursor-pointer">
                            Hubungkan Anak Node
                        </button>
                        <button type="button" @click="assignModal.type = 'manual'"
                                :class="assignModal.type === 'manual' ? 'text-blue-600 border-b-2 border-blue-600 pb-2' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 pb-2'"
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
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Pilih Pelanggan <span class="text-rose-500">*</span></label>
                                    <select name="customer_id" required class="block w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-800 dark:text-slate-100 text-xs focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 outline-none">
                                        <option value="">Pilih Pelanggan di Cabang Ini...</option>
                                        <template x-for="cust in modalCustomers" :key="cust.id">
                                            <option :value="cust.id" x-text="cust.full_name + ' (' + (cust.customer_code || cust.cid || ('#' + cust.id)) + ')'"></option>
                                        </template>
                                    </select>
                                    <p class="text-[10px] text-slate-400 mt-1">Daftar pelanggan di cabang terkait yang belum menempati port ODP.</p>
                                </div>

                                <div class="pt-2 flex items-center justify-end gap-2">
                                    <button type="button" @click="assignModal.open = false" class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-500 text-xs font-semibold cursor-pointer">Batal</button>
                                    <button type="submit" class="px-4 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm cursor-pointer">
                                        + Sambungkan Pelanggan
                                    </button>
                                </div>
                            </form>
                        </template>
                        <template x-if="modalCustomers.length === 0">
                            <div class="p-4 rounded-xl border border-dashed border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/30 text-center space-y-1">
                                <p class="text-xs font-bold text-slate-800 dark:text-slate-100">Tidak Ada Pelanggan Siap Sambung</p>
                                <p class="text-[11px] text-slate-400 leading-relaxed">
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
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Pilih Anak Langsung <span class="text-rose-500">*</span></label>
                                    <select name="target_node_id" required class="block w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-800 dark:text-slate-100 text-xs focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 outline-none">
                                        <option value="">Pilih Anak Langsung (ODP / Sub-Node)...</option>
                                        <template x-for="child in unconnectedChildren" :key="child.id">
                                            <option :value="child.id" x-text="child.code + ' · ' + child.name + (child.type ? ' (' + child.type + ')' : '')"></option>
                                        </template>
                                    </select>
                                    <p class="text-[10px] text-slate-400 mt-1">Hubungkan port ini ke anak node langsung (ODP/sub-node) di bawah node ini.</p>
                                </div>

                                <div class="pt-2 flex items-center justify-end gap-2">
                                    <button type="button" @click="assignModal.open = false" class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-500 text-xs font-semibold cursor-pointer">Batal</button>
                                    <button type="submit" class="px-4 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-sm cursor-pointer">
                                        Hubungkan Node
                                    </button>
                                </div>
                            </form>
                        </template>
                        <template x-if="unconnectedChildren.length === 0">
                            <div class="p-4 rounded-xl border border-dashed border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/30 text-center space-y-2">
                                <template x-if="assignModal.children && assignModal.children.length > 0">
                                    <div>
                                        <p class="text-xs font-bold text-slate-800 dark:text-slate-100">Semua Anak Node Sudah Terhubung</p>
                                        <p class="text-[11px] text-slate-400 leading-relaxed mt-1">
                                            Seluruh anak node (ODP/sub-node) di bawah node ini sudah menempati port lain.
                                        </p>
                                    </div>
                                </template>
                                <template x-if="!assignModal.children || assignModal.children.length === 0">
                                    <div>
                                        <p class="text-xs font-bold text-slate-800 dark:text-slate-100">Belum Ada Anak Node Terdaftar</p>
                                        <p class="text-[11px] text-slate-400 leading-relaxed mt-1">
                                            Untuk menghubungkan port ke anak node (misal ODP), node anak harus didaftarkan terlebih dahulu dengan induk node ini.
                                        </p>
                                    </div>
                                </template>
                                <div class="pt-2">
                                    <button type="button" @click="assignModal.open = false; openAddChildModal(assignModal.nodeId, assignModal.nodeCode, assignModal.nodeName)"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-sm cursor-pointer">
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
                            <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                                Tandai port ini terpakai secara manual tanpa mengaitkannya ke data pelanggan atau anak node. Status akan tercatat sebagai <em>belum terverifikasi</em>.
                            </p>

                            <div class="pt-2 flex items-center justify-end gap-2">
                                <button type="button" @click="assignModal.open = false" class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-500 text-xs font-semibold cursor-pointer">Batal</button>
                                <button type="submit" class="px-4 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold shadow-sm cursor-pointer">
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
function networkNodeManager(config) {
    return {
        treeSearchQuery: '',
        typeFilter: 'all',
        portFilter: 'ALL',
        openRelocateId: null,
        selectedNodeId: config.selectedNodeId,
        showCreateModal: config.showCreateModal,
        placement: config.placement,
        createParentId: config.createParentId || null,
        hasPorts: config.hasPorts,
        availableCustomers: config.availableCustomers || [],

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

        openCreateModalRoot() {
            this.placement = 'pop';
            this.createParentId = null;
            this.showCreateModal = true;
        },

        openAddChildModal(parentId, parentCode, parentName) {
            this.placement = 'node';
            this.createParentId = parentId;
            this.showCreateModal = true;
            this.$nextTick(() => {
                const select = document.getElementById('create-modal-parent-select');
                if (select) select.value = parentId;
            });
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

        selectNode(id) {
            this.selectedNodeId = id;
            this.portFilter = 'ALL';
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
