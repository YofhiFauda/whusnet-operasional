@extends('layouts.app')

@section('title', 'Master Node Jaringan & Port')
@section('page_title', 'Master Node Jaringan & Port')

@section('content')
<div x-data="{
    typeFilter: 'all',
    selectedNodeId: @js($nodes->first()?->id),
    showCreateModal: @js($errors->any() && !old('_method')),
    placement: @js(old('parent_id') ? 'node' : 'pop'),
    hasPorts: @js((bool) ($nodeTypes->firstWhere('id', (int) old('node_type_id'))?->has_ports ?? true)),
    selectNode(id) {
        this.selectedNodeId = id;
        this.$nextTick(() => {
            const el = document.getElementById('inspector-node-' + id);
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });
    }
}" class="mx-auto max-w-7xl space-y-6 pb-16">

    {{-- Top Command Hub Header --}}
    <div class="relative overflow-hidden rounded-2xl border border-sky-200/80 dark:border-sky-900/60 bg-gradient-to-br from-sky-50/80 via-surface to-surface dark:from-sky-950/30 dark:via-surface dark:to-surface p-6 shadow-sm">
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-black uppercase tracking-wider bg-sky-600 text-white shadow-2xs">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        FTTH ODN TOPOLOGY
                    </span>
                    <span class="text-xs font-mono font-bold text-text-muted">Master Node Jaringan &amp; Port</span>
                </div>
                <h1 class="text-2xl font-black tracking-tight text-text-main">
                    Infrastruktur Jaringan &amp; Manajemen ODN
                </h1>
                <p class="text-xs text-text-muted max-w-2xl leading-relaxed">
                    Susunan Pusat, Cabang/Mini POP, dan node jaringan. Periksa jalur serta penggunaan port, aset terpasang, dan titik yang mendekati kapasitas.
                </p>
            </div>

            <div class="flex items-center gap-2.5 flex-wrap">
                @can('network_nodes.create')
                <button type="button" @click="showCreateModal = true"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold shadow-xs active:scale-95 transition-all cursor-pointer">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    + Tambah Node Baru
                </button>
                @endcan
                <a href="{{ route('network.map.index') }}" class="inline-flex items-center gap-1.5 rounded border border-sky-300 bg-white px-4 py-2 text-xs font-semibold text-sky-800 hover:bg-sky-50 dark:border-sky-800 dark:bg-slate-800 dark:text-sky-300">
                    Peta Infrastruktur ISP
                </a>
            </div>
        </div>
    </div>

    {{-- KPI Metric Overview Bar --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Stat 1: Total Nodes --}}
        <div class="rounded-xl border border-border bg-surface p-4 shadow-2xs space-y-1">
            <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted flex items-center justify-between">
                <span>Total Titik Jaringan</span>
                <span class="w-2 h-2 rounded-full bg-sky-500"></span>
            </span>
            <div class="text-2xl font-black font-mono text-sky-600 dark:text-sky-400">
                {{ $totalNodesCount }} <span class="text-xs font-normal text-text-muted">Node</span>
            </div>
            <p class="text-[11px] text-text-muted">Titik sebar dalam scope Anda</p>
        </div>

        {{-- Stat 2: Total Port Fisik --}}
        <div class="rounded-xl border border-border bg-surface p-4 shadow-2xs space-y-1">
            <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted flex items-center justify-between">
                <span>Kapasitas Port Fisik</span>
                <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
            </span>
            <div class="text-2xl font-black font-mono text-text-main">
                {{ $totalPortsCount }} <span class="text-xs font-normal text-text-muted">Port</span>
            </div>
            <p class="text-[11px] text-text-muted">Total port pada seluruh tipe node</p>
        </div>

        {{-- Stat 3: Port Terpakai & Persentase --}}
        @php
            $globalPercent = $totalPortsCount > 0 ? round(($totalUsedPortsCount / $totalPortsCount) * 100) : 0;
        @endphp
        <div class="rounded-xl border border-border bg-surface p-4 shadow-2xs space-y-1">
            <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted flex items-center justify-between">
                <span>Port Terpakai</span>
                <span class="w-2 h-2 rounded-full {{ $globalPercent >= 80 ? 'bg-rose-500' : 'bg-emerald-500' }}"></span>
            </span>
            <div class="text-2xl font-black font-mono {{ $globalPercent >= 80 ? 'text-rose-600' : 'text-emerald-600 dark:text-emerald-400' }}">
                {{ $totalUsedPortsCount }} <span class="text-xs font-semibold text-text-muted">({{ $globalPercent }}%)</span>
            </div>
            <div class="w-full bg-surface-muted rounded-full h-1.5 overflow-hidden">
                <div class="h-1.5 rounded-full {{ $globalPercent >= 80 ? 'bg-rose-500' : 'bg-emerald-500' }}" style="width: {{ $globalPercent }}%"></div>
            </div>
        </div>

        {{-- Stat 4: Red Zone Warning --}}
        <div class="rounded-xl border border-border bg-surface p-4 shadow-2xs space-y-1">
            <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted flex items-center justify-between">
                <span>Titik Red Zone</span>
                <span class="w-2 h-2 rounded-full bg-rose-500 {{ $redZoneNodesCount > 0 ? 'animate-ping' : '' }}"></span>
            </span>
            <div class="text-2xl font-black font-mono {{ $redZoneNodesCount > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-text-main' }}">
                {{ $redZoneNodesCount }} <span class="text-xs font-normal text-text-muted">Node</span>
            </div>
            <p class="text-[11px] text-text-muted">
                {{ $redZoneNodesCount > 0 ? 'Menurut ambang tipe node' : 'Tidak ada node melewati ambang tipenya' }}
            </p>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- MODAL TAMBAH NODE BARU (Modern Dialog with All Form Fields)             --}}
    {{-- ========================================================================= --}}
    @can('network_nodes.create')
    <div x-show="showCreateModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs"
         @keydown.escape.window="showCreateModal = false">
        <div @click.outside="showCreateModal = false"
             class="w-full max-w-3xl rounded-2xl bg-surface border border-border shadow-2xl overflow-hidden max-h-[90vh] flex flex-col animate-in fade-in zoom-in duration-150">

            <div class="px-6 py-4 border-b border-border bg-sky-50/50 dark:bg-sky-950/30 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="p-2 rounded-xl bg-sky-600 text-white shadow-xs">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-wider text-text-main">Tambah Titik Node Jaringan Baru</h3>
                        <p class="text-[11px] text-text-muted">Daftarkan ODC, ODP, Router, atau PON Port ke pohon topologi</p>
                    </div>
                </div>
                <button type="button" @click="showCreateModal = false" class="text-text-muted hover:text-text-main cursor-pointer p-1 rounded-lg">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form method="POST" action="{{ route('network.nodes.store') }}" class="p-6 overflow-y-auto space-y-4">
                @csrf
                <div class="rounded-xl border border-sky-200 bg-sky-50 p-3 text-xs text-sky-900 dark:border-sky-900 dark:bg-sky-950/30 dark:text-sky-200">
                    <p class="font-bold">Pusat / Server Utama, Cabang, dan Mini POP muncul otomatis dari Master POP.</p>
                    <p class="mt-1">Tambahkan perangkat jaringan seperti router, OLT, ODC, atau ODP di form ini, lalu pilih POP tempatnya berada.</p>
                    @can('pops.view')
                        <a href="{{ route('master.pop.index') }}" class="mt-2 inline-flex font-bold underline underline-offset-2">Buka Master POP</a>
                    @endcan
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <label class="text-xs font-bold text-text-secondary">Tipe Node <span class="text-rose-500">*</span>
                        <select name="node_type_id" required x-on:change="hasPorts = $event.target.selectedOptions[0]?.dataset.hasPorts === '1'"
                                class="mt-1 block w-full rounded-xl border border-border bg-background px-3 py-2 text-text-main text-xs focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                            <option value="">Pilih Tipe Node</option>
                            @foreach($nodeTypes as $nodeType)
                            <option value="{{ $nodeType->id }}" data-has-ports="{{ $nodeType->has_ports ? '1' : '0' }}"
                                    @selected(old('node_type_id') == $nodeType->id)>{{ $nodeType->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="text-xs font-bold text-text-secondary">Penempatan <span class="text-rose-500">*</span>
                        <select x-model="placement" name="placement" class="mt-1 block w-full rounded-xl border border-border bg-background px-3 py-2 text-text-main text-xs focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                            <option value="pop">Langsung di Cabang / POP</option>
                            <option value="node">Di dalam Node Induk (Child)</option>
                        </select>
                    </label>

                    <label x-show="placement === 'pop'" class="text-xs font-bold text-text-secondary">POP Anchor <span class="text-rose-500">*</span>
                        <select name="anchor_pop_id" x-bind:disabled="placement !== 'pop'" x-bind:required="placement === 'pop'"
                                class="mt-1 block w-full rounded-xl border border-border bg-background px-3 py-2 text-text-main text-xs focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                            <option value="">Pilih Cabang / POP</option>
                            @foreach($pops as $pop)
                            <option value="{{ $pop->id }}" @selected(old('anchor_pop_id') == $pop->id)>{{ $pop->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label x-show="placement === 'node'" class="text-xs font-bold text-text-secondary">Node Induk <span class="text-rose-500">*</span>
                        <select name="parent_id" x-bind:disabled="placement !== 'node'" x-bind:required="placement === 'node'"
                                class="mt-1 block w-full rounded-xl border border-border bg-background px-3 py-2 text-text-main text-xs focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                            <option value="">Pilih Node Induk</option>
                            @foreach($parentOptions as $parentOption)
                            <option value="{{ $parentOption->id }}" @selected(old('parent_id') == $parentOption->id)>{{ $breadcrumbLabels[$parentOption->id] ?? $parentOption->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="text-xs font-bold text-text-secondary">Kode Node <span class="text-rose-500">*</span>
                        <input name="code" value="{{ old('code') }}" required maxlength="50" placeholder="Mis. ODC-01, ODP-SMN-04"
                               class="mt-1 block w-full rounded-xl border border-border bg-background px-3 py-2 text-text-main text-xs font-mono uppercase focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                    </label>

                    <label class="text-xs font-bold text-text-secondary">Nama Node <span class="text-rose-500">*</span>
                        <input name="name" value="{{ old('name') }}" required maxlength="150" placeholder="Mis. ODC Siman Krajan"
                               class="mt-1 block w-full rounded-xl border border-border bg-background px-3 py-2 text-text-main text-xs focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                    </label>

                    <label class="text-xs font-bold text-text-secondary">
                        <span x-text="hasPorts ? 'Jumlah Port Fisik (Splitter)' : 'Kapasitas Ringkasan'"></span>
                        <input type="number" name="capacity" value="{{ old('capacity') }}" min="0" max="1000" x-bind:required="hasPorts" placeholder="Mis. 8, 16, 32"
                               class="mt-1 block w-full rounded-xl border border-border bg-background px-3 py-2 text-text-main text-xs font-mono focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                    </label>
                </div>

                {{-- Location Section --}}
                <div class="border-t border-border pt-4 space-y-3">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-text-main flex items-center gap-1.5">
                        <svg class="h-3.5 w-3.5 text-sky-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Lokasi Fisik &amp; Koordinat (Opsional — kosongkan jika mewarisi induk)
                    </h4>

                    <label class="block text-xs font-bold text-text-secondary">Alamat Fisik / Titik Tiang
                        <textarea name="address" rows="2" placeholder="Detail posisi tiang, patokan jalan, RT/RW..."
                                  class="mt-1 block w-full rounded-xl border border-border bg-background px-3 py-2 text-text-main text-xs focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">{{ old('address') }}</textarea>
                    </label>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <label class="text-xs font-bold text-text-secondary">Desa / Kelurahan
                            <input name="village" value="{{ old('village') }}" placeholder="Desa..."
                                   class="mt-1 block w-full rounded-xl border border-border bg-background px-3 py-2 text-text-main text-xs">
                        </label>
                        <label class="text-xs font-bold text-text-secondary">Kecamatan
                            <input name="district" value="{{ old('district') }}" placeholder="Kecamatan..."
                                   class="mt-1 block w-full rounded-xl border border-border bg-background px-3 py-2 text-text-main text-xs">
                        </label>
                        <label class="text-xs font-bold text-text-secondary">Kota / Kabupaten
                            <input name="city" value="{{ old('city') }}" placeholder="Kota..."
                                   class="mt-1 block w-full rounded-xl border border-border bg-background px-3 py-2 text-text-main text-xs">
                        </label>
                        <label class="text-xs font-bold text-text-secondary">Latitude
                            <input name="latitude" value="{{ old('latitude') }}" placeholder="-7.865..."
                                   class="mt-1 block w-full rounded-xl border border-border bg-background px-3 py-2 text-text-main text-xs font-mono">
                        </label>
                        <label class="text-xs font-bold text-text-secondary">Longitude
                            <input name="longitude" value="{{ old('longitude') }}" placeholder="111.469..."
                                   class="mt-1 block w-full rounded-xl border border-border bg-background px-3 py-2 text-text-main text-xs font-mono">
                        </label>
                    </div>
                </div>

                @if($errors->any())
                <div class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-900 text-xs font-semibold text-rose-600 dark:text-rose-400">
                    {{ $errors->first() }}
                </div>
                @endif

                <div class="pt-4 border-t border-border flex items-center justify-end gap-2.5">
                    <button type="button" @click="showCreateModal = false" class="px-4 py-2 rounded-xl text-xs font-bold text-text-muted hover:bg-surface-muted transition-colors cursor-pointer">
                        Batal
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold shadow-xs transition-all cursor-pointer">
                        Tambah Node
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endcan

    {{-- ========================================================================= --}}
    {{-- POHON NODE DAN PANEL INSPEKSI                                            --}}
    {{-- ========================================================================= --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        {{-- Pohon node membutuhkan ruang untuk kedalaman rantai. --}}
        <div class="lg:col-span-6 xl:col-span-5 space-y-4">
            <section class="rounded-2xl border border-border bg-surface p-5 shadow-2xs space-y-4" aria-label="Pohon node jaringan">
                <div class="flex items-center justify-between gap-2 border-b border-border pb-3">
                    <div>
                        <h2 class="text-sm font-black uppercase tracking-wider text-text-main flex items-center gap-2">
                            <span class="w-1.5 h-3.5 bg-sky-600 rounded-full"></span>
                            Pohon Infrastruktur
                        </h2>
                        <p class="text-[11px] text-text-muted mt-0.5">Buka POP dan node untuk melihat susunan titik jaringan.</p>
                    </div>
                    <span class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-surface-muted text-text-muted font-bold">
                        {{ $totalNodesCount }} nodes
                    </span>
                </div>

                <label class="block text-[11px] font-semibold text-text-secondary">
                    Sorot tipe node
                    <select x-model="typeFilter" class="mt-1 w-full rounded-lg border border-border bg-background px-3 py-2 text-xs text-text-main">
                        <option value="all">Semua tipe</option>
                        @foreach($nodeTypes as $nodeType)
                        <option value="{{ $nodeType->id }}">{{ $nodeType->name }}</option>
                        @endforeach
                    </select>
                </label>

                {{-- POP adalah lokasi yang sudah ada; node perangkat menempel di bawahnya. --}}
                <div class="max-h-[75vh] space-y-2 overflow-auto pr-1">
                    @forelse($topologyPopChildren->get(0, collect()) as $treePop)
                        @include('network.nodes._pop-tree')
                    @empty
                        <div class="rounded-xl border border-dashed border-border p-8 text-center">
                            <p class="text-xs font-bold text-text-main">Belum ada POP dalam scope Anda.</p>
                        </div>
                    @endforelse

                    @foreach($rootNodesByPop->get(0, collect()) as $treeNode)
                        @include('network.nodes._tree-node', ['treeNode' => $treeNode, 'treeChildren' => $treeChildren])
                    @endforeach
                </div>
            </section>
        </div>

        {{-- Detail node yang dipilih. --}}
        <div class="lg:col-span-6 xl:col-span-7 space-y-4" id="node-inspector">
            @forelse($nodes as $node)
            @php
                $usagePercent = $node->ports_count > 0 ? round($node->used_ports_count / $node->ports_count * 100) : 0;
                $isRedZone = $node->isInRedZone();
                $location = $node->effectiveLocation();
                $childDownstreams = $treeChildren->get($node->id, collect());
            @endphp

            <div x-show="selectedNodeId === {{ $node->id }}"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 id="inspector-node-{{ $node->id }}"
                 class="space-y-4">

                {{-- Section Container (Also maintains id="node-{$node->id}" for tests) --}}
                <section class="rounded-2xl border border-border bg-surface p-6 shadow-sm space-y-6" id="node-{{ $node->id }}">

                    {{-- 1. Traceability Pathway Breadcrumb --}}
                    <div class="flex items-center justify-between gap-3 flex-wrap pb-4 border-b border-border">
                        <div class="flex items-center gap-1.5 text-xs text-text-muted flex-wrap">
                            <span class="font-bold text-sky-600 dark:text-sky-400">Jalur ODN:</span>
                            <span class="font-mono text-text-main font-semibold">{{ $breadcrumbLabels[$node->id] ?? $node->name }}</span>
                        </div>

                        <div class="flex items-center gap-2">
                            <a href="{{ route('network.nodes.show', $node) }}"
                               class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-border bg-surface hover:bg-surface-muted text-xs font-bold text-text-main transition-colors shadow-2xs">
                                <svg class="h-3.5 w-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                Edit Detail
                            </a>
                        </div>
                    </div>

                    {{-- 2. Node Header & Health Status --}}
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                        <div class="space-y-1.5 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h2 class="text-xl font-black font-mono tracking-tight text-text-main">
                                    <a class="hover:text-primary transition-colors" href="{{ route('network.nodes.show', $node) }}">
                                        {{ $node->code }} · {{ $node->name }}
                                    </a>
                                </h2>

                                <span class="text-xs font-bold px-2.5 py-0.5 rounded-md border border-border bg-surface-muted text-text-secondary">
                                    {{ $node->nodeType?->name }}
                                </span>

                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md text-xs font-bold {{ $node->status === 'active' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200' : 'bg-slate-100 text-slate-700 border border-border' }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $node->status === 'active' ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                    {{ ucfirst($node->status) }}
                                </span>
                            </div>

                            <p class="text-xs text-text-muted">
                                @if($node->nodeType?->has_ports)
                                    {{ $node->used_ports_count }}/{{ $node->ports_count }} port terpakai ({{ $node->ports_count - $node->used_ports_count }} slot kosong tersedia)
                                @else
                                    Kapasitas ringkasan: {{ $node->capacity ?? '-' }}
                                @endif
                            </p>
                        </div>

                        {{-- Red Zone Badge per tipe node (NetworkNode::isInRedZone()) --}}
                        @if($node->nodeType?->has_ports)
                        <div class="shrink-0">
                            <span class="inline-flex items-center gap-1.5 rounded-xl border px-3 py-1.5 text-xs font-black font-mono shadow-2xs {{ $isRedZone ? 'border-rose-300 bg-rose-50 text-rose-700 dark:bg-rose-950/80 dark:text-rose-300 animate-pulse' : 'border-border bg-surface-muted text-text-secondary' }}"
                                  data-red-zone="{{ $isRedZone ? '1' : '0' }}">
                                @if($isRedZone)
                                    <svg class="h-4 w-4 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                @endif
                                {{ $isRedZone ? 'Red Zone · ' : '' }}{{ $usagePercent }}%
                            </span>
                        </div>
                        @endif
                    </div>

                    {{-- 3. Effective Location Card --}}
                    <div class="p-4 rounded-xl bg-surface-muted/60 dark:bg-slate-900/40 border border-border space-y-2 text-xs">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted flex items-center justify-between">
                            <span class="flex items-center gap-1.5">
                                <svg class="h-3.5 w-3.5 text-sky-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                Lokasi Fisik &amp; Koordinat
                            </span>
                            <span class="font-normal italic">{{ $location['source_node_id'] === $node->id ? 'Lokasi mandiri node ini' : 'Mewarisi lokasi induk/POP' }}</span>
                        </span>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 pt-1">
                            <div>
                                <span class="text-[10px] text-text-muted block">Alamat / Patokan:</span>
                                <span class="font-semibold text-text-main">{{ $location['address'] ?: '—' }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-text-muted block">Wilayah:</span>
                                <span class="font-semibold text-text-main">{{ collect([$location['village'], $location['district'], $location['city']])->filter()->implode(', ') ?: '—' }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-text-muted block">Koordinat Maps:</span>
                                @if($location['latitude'] !== null && $location['longitude'] !== null)
                                    <a href="https://maps.google.com/?q={{ $location['latitude'] }},{{ $location['longitude'] }}" target="_blank" rel="noopener"
                                       class="font-mono text-sky-600 dark:text-sky-400 font-bold hover:underline inline-flex items-center gap-1">
                                        {{ $location['latitude'] }}, {{ $location['longitude'] }} &nearr;
                                    </a>
                                @else
                                    <span class="text-text-muted italic">—</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- 4. Visual Port Matrix (Splitter / Patch Panel Grid) --}}
                    @if($node->nodeType?->has_ports)
                    <div class="space-y-3 pt-2">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <h3 class="text-xs font-black uppercase tracking-wider text-text-main flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-sky-500"></span>
                                    PORT NODE · {{ $node->nodeType?->name }}
                                </h3>
                                <p class="text-[11px] text-text-muted">Pilih port terpakai untuk melihat tujuan. Port kosong dapat ditandai terpakai secara manual.</p>
                            </div>

                            @can('network_nodes.update')
                            <form method="POST" action="{{ route('network.nodes.ports.add', $node) }}" class="flex items-center gap-1.5">
                                @csrf
                                <input type="number" name="additional_capacity" min="1" max="1000" required placeholder="+Qty"
                                       class="w-16 text-xs rounded-lg border border-border bg-background px-2 py-1 text-center font-mono">
                                <button type="submit" class="px-2.5 py-1 rounded-lg bg-surface border border-border hover:bg-surface-muted text-xs font-bold text-text-main shadow-2xs cursor-pointer">
                                    Tambah Port
                                </button>
                            </form>
                            @endcan
                        </div>

                        {{-- Ports Visual Grid --}}
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-2 pt-1">
                            @forelse($node->ports as $port)
                                @php($assignment = $port->activeAssignment->first())
                                @can('network_nodes.update')
                                @if($port->is_used)
                                <a href="{{ route('network.nodes.show', $node) }}" class="block w-full rounded-xl border border-rose-200 bg-rose-50/80 p-2.5 text-xs font-bold text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300">Port #{{ $port->number }} · Terpakai<br><span class="text-[10px] font-normal">
                                    @if($assignment?->customer) {{ $assignment->customer->full_name }}
                                    @elseif($assignment?->targetNode) → {{ $assignment->targetNode->code }}
                                    @elseif($assignment?->manual_label) {{ $assignment->manual_label }} · Belum diverifikasi
                                    @else Belum diverifikasi @endif
                                </span></a>
                                @else
                                <form method="POST" action="{{ route('network.node-ports.update', $port) }}" class="w-full">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="is_used" value="{{ $port->is_used ? 0 : 1 }}">
                                    <button type="submit"
                                            class="w-full text-left p-2.5 rounded-xl border transition-all cursor-pointer shadow-2xs group
                                            {{ $port->is_used
                                                ? 'border-rose-200 dark:border-rose-900 bg-rose-50/80 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 hover:bg-rose-100'
                                                : 'border-emerald-200 dark:border-emerald-900 bg-emerald-50/80 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-100' }}"
                                            title="Tandai port {{ $port->number }} terpakai secara manual">
                                        <div class="flex items-center justify-between gap-1 mb-1">
                                            <span class="text-xs font-black font-mono">#{{ $port->number }}</span>
                                            <span class="w-2 h-2 rounded-full {{ $port->is_used ? 'bg-rose-500' : 'bg-emerald-500' }}"></span>
                                        </div>
                                        <div class="text-[11px] font-bold truncate">
                                            {{ $port->is_used ? 'Terpakai' : 'Tandai terpakai' }}
                                        </div>
                                    </button>
                                </form>
                                @endif
                                @else
                                <div class="p-2.5 rounded-xl border border-border bg-surface text-xs space-y-1">
                                    <div class="flex items-center justify-between gap-1">
                                        <span class="font-mono font-bold">#{{ $port->number }}</span>
                                        <span class="w-2 h-2 rounded-full {{ $port->is_used ? 'bg-rose-500' : 'bg-emerald-500' }}"></span>
                                    </div>
                                    <div class="text-[11px] text-text-secondary truncate">
                                        @if($assignment?->customer) Pelanggan: {{ $assignment->customer->full_name }}
                                        @elseif($assignment?->targetNode) → {{ $assignment->targetNode->code }}
                                        @elseif($assignment?->manual_label) {{ $assignment->manual_label }} · Belum diverifikasi
                                        @else {{ $port->is_used ? 'Terpakai · belum diverifikasi' : 'Kosong' }} @endif
                                    </div>
                                </div>
                                @endcan
                            @empty
                            <div class="col-span-full p-4 rounded-xl border border-dashed border-border text-center text-xs text-text-muted">
                                Belum ada port yang dibuat untuk node ini.
                            </div>
                            @endforelse
                        </div>
                    </div>
                    @endif

                    {{-- 5. Aset Terpasang (Gudang & Material) --}}
                    @if($node->deployedSerials->isNotEmpty() || $node->infrastructureBalances->isNotEmpty())
                    <div class="space-y-2.5 pt-4 border-t border-border">
                        <div class="flex items-center gap-2">
                            <span class="w-1.5 h-3.5 bg-indigo-600 rounded-full"></span>
                            <h3 class="text-xs font-black uppercase tracking-wider text-text-main">Aset Terpasang</h3>
                        </div>

                        <div class="flex flex-wrap gap-2 pt-1">
                            @foreach($node->deployedSerials as $serial)
                            <a href="{{ route('warehouse.traceability.index', ['sn' => $serial->serial_number]) }}"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-sky-200 dark:border-sky-900 bg-sky-50 dark:bg-sky-950/60 text-xs font-mono font-semibold text-sky-700 dark:text-sky-300 hover:border-primary transition-colors shadow-2xs"
                               title="Lihat riwayat SN {{ $serial->serial_number }}">
                                <svg class="h-3.5 w-3.5 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                                </svg>
                                SN {{ $serial->serial_number }} · {{ $serial->item?->name }}
                            </a>
                            @endforeach

                            @foreach($node->infrastructureBalances as $balance)
                            @if((float) $balance->qty > 0)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-border bg-surface-muted text-xs font-semibold text-text-secondary">
                                <svg class="h-3.5 w-3.5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                                {{ $balance->item?->name }}{{ $balance->lot_no ? ' ('.$balance->lot_no.')' : '' }} · {{ rtrim(rtrim(number_format((float) $balance->qty, 2, ',', '.'), '0'), ',') }} {{ $balance->item?->unit }}
                            </span>
                            @endif
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- 6. Child Downstream Nodes (Tracing siapa anak di bawah node ini) --}}
                    @if($childDownstreams->isNotEmpty())
                    <div class="space-y-2.5 pt-4 border-t border-border">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span class="w-1.5 h-3.5 bg-violet-600 rounded-full"></span>
                                <h3 class="text-xs font-black uppercase tracking-wider text-text-main">
                                    Sub-Node Turunan Langsung ({{ $childDownstreams->count() }} Downstream)
                                </h3>
                            </div>
                            <span class="text-[10px] text-text-muted">Klik node untuk beralih inspeksi</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2.5 pt-1">
                            @foreach($childDownstreams as $child)
                            <button type="button" @click="selectNode({{ $child->id }})"
                                    class="text-left p-3 rounded-xl border border-border bg-surface hover:border-sky-400 hover:bg-sky-50/40 dark:hover:bg-sky-950/30 transition-all shadow-2xs group cursor-pointer">
                                <div class="flex items-center justify-between gap-1">
                                    <span class="font-mono font-bold text-sky-600 dark:text-sky-400 text-xs">{{ $child->code }}</span>
                                    <span class="text-[10px] font-bold text-text-muted">({{ $child->nodeType?->name }})</span>
                                </div>
                                <div class="text-xs font-semibold text-text-main truncate mt-1">{{ $child->name }}</div>
                                <div class="text-[10px] text-text-muted mt-0.5">
                                    {{ $child->used_ports_count ?? 0 }}/{{ $child->ports_count ?? 0 }} port terpakai
                                </div>
                            </button>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- 7. Manajemen Pindah Node (Relocate) --}}
                    @can('network_nodes.update')
                    <div class="pt-4 border-t border-border">
                        <details class="group/relocate rounded-xl border border-border bg-surface-muted/40 p-4">
                            <summary class="cursor-pointer text-xs font-bold text-text-secondary hover:text-text-main flex items-center justify-between select-none">
                                <span class="flex items-center gap-1.5">
                                    <svg class="h-4 w-4 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                    </svg>
                                    Relokasi / Pindah Posisi Node Ini ke POP atau Node Lain
                                </span>
                                <span class="text-[10px] text-sky-600 font-bold group-open/relocate:rotate-180 transition-transform">&darr;</span>
                            </summary>

                            <form method="POST" action="{{ route('network.nodes.move', $node) }}"
                                  x-data="{ placement: @js($node->parent_id ? 'node' : 'pop') }"
                                  class="mt-4 grid gap-3 sm:grid-cols-3 pt-2">
                                @csrf
                                @method('PATCH')
                                <label class="text-xs font-bold text-text-secondary">Pindah ke
                                    <select x-model="placement" name="placement" class="mt-1 block w-full rounded-xl border border-border bg-background px-3 py-2 text-text-main text-xs">
                                        <option value="pop">Cabang / POP</option>
                                        <option value="node">Node Induk Lain</option>
                                    </select>
                                </label>

                                <label x-show="placement === 'pop'" class="text-xs font-bold text-text-secondary">POP Tujuan
                                    <select name="anchor_pop_id" x-bind:disabled="placement !== 'pop'" x-bind:required="placement === 'pop'"
                                            class="mt-1 block w-full rounded-xl border border-border bg-background px-3 py-2 text-text-main text-xs">
                                        <option value="">Pilih POP</option>
                                        @foreach($pops as $pop)
                                        <option value="{{ $pop->id }}" @selected($node->anchor_pop_id == $pop->id)>{{ $pop->name }}</option>
                                        @endforeach
                                    </select>
                                </label>

                                <label x-show="placement === 'node'" class="text-xs font-bold text-text-secondary">Node Induk Tujuan
                                    <select name="parent_id" x-bind:disabled="placement !== 'node'" x-bind:required="placement === 'node'"
                                            class="mt-1 block w-full rounded-xl border border-border bg-background px-3 py-2 text-text-main text-xs">
                                        <option value="">Pilih Node Induk</option>
                                        @foreach($parentOptions as $parentOption)
                                        @if($parentOption->id !== $node->id)
                                        <option value="{{ $parentOption->id }}" @selected($node->parent_id == $parentOption->id)>{{ $breadcrumbLabels[$parentOption->id] ?? $parentOption->name }}</option>
                                        @endif
                                        @endforeach
                                    </select>
                                </label>

                                <button type="submit" class="self-end px-4 py-2 rounded-xl bg-surface border border-border hover:bg-surface-muted text-xs font-bold text-text-main shadow-2xs cursor-pointer transition-all">
                                    Pindah Node
                                </button>
                            </form>
                        </details>
                    </div>
                    @endcan

                </section>
            </div>
            @empty
            <div class="rounded-2xl border border-border bg-surface p-12 text-center text-xs text-text-muted space-y-2">
                <p class="font-bold text-text-main text-sm">Belum ada node jaringan pada POP yang dapat Anda akses.</p>
                <p>Klik tombol "+ Tambah Node Baru" di atas untuk menambahkan titik jaringan.</p>
            </div>
            @endforelse
        </div>
    </div>


</div>
@endsection
