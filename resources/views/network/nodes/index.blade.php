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
    selectedNodeId: @js($selectedNodeId),
    {{-- Slot/PON yang dipilih ulang lewat `?node=` setelah PRG dari panelnya
         (posisi tidak ada di $nodes — fix C2). Bentuk = baris JSON children. --}}
    selectedPosition: @js($selectedPosition),
    {{-- Leluhur node terpilih (OLT → Slot → PON) yang cabang pohon lazy-nya
         dibuka otomatis saat load, supaya baris ODP/Slot/PON terpilih setelah
         `?node=` terlihat di pohon. Hanya id dalam scope (dihitung server). --}}
    treeOpenIds: @js($treeOpenIds ?? []),
    {{-- Hanya form modal yang membawa `device_type`; error dari form panel
         perangkat (kapasitas, Tambah Slot/PON) yang back() ke sini tidak
         boleh membuka modal Tambah Titik — errornya tampil lewat toast. --}}
    showCreateModal: @js($errors->any() && !old('_method') && old('device_type') !== null),
    createType: @js(old('device_type', '')),
    createPopId: @js(old('anchor_pop_id', '')),
    createDeviceId: @js(old('device_id', '')),
    createSlotId: @js(old('slot_id', '')),
    createPonId: @js(old('parent_id', '')),
    creationPops: @js($pops->map(fn ($pop) => ['id' => $pop->id, 'name' => $pop->name, 'type' => $pop->type, 'cabang_id' => \App\Models\Pop::resolveCabangId($pop->id)])->values()),
    nodeTypeIds: @js($nodeTypes->pluck('id', 'code')),
    {{-- `has_ports` DB mentah cuma separuh cerita — tipe yang juga `InfrastructureSlotKind`
         (switch_mini_pop) kapasitasnya lewat Slot, bukan port lama. Replikasi persis
         $hasLegacyPorts di NetworkNodeController::store() supaya field Kapasitas di modal
         Tambah required/optional sama persis dengan yang ditegakkan server. --}}
    nodeTypeHasPorts: @js($nodeTypes->mapWithKeys(fn ($type) => [
        $type->code => $type->has_ports && \App\Enums\InfrastructureSlotKind::forDeviceTypeCode($type->code) === null,
    ])),
    availableCustomers: @js($availableCustomers),
    {{-- URL per perangkat dirender server-side (route()); Alpine hanya
         MEMILIH di antara URL ini, tidak merakit path dari id (CLAUDE.md). --}}
    hierarchyItems: @js(collect($hierarchy['items'])->map(function ($item) {
        $row = collect($item)->only(['id', 'label', 'name', 'kind', 'type', 'type_id', 'parent', 'breadcrumb'])->all();
        $deviceKind = $item['kind'] === 'node' ? \App\Enums\InfrastructureSlotKind::forDeviceTypeCode($item['type']) : null;
        if ($deviceKind !== null) {
            $row['children_url'] = route('network.nodes.children', $item['id']);
            $row['store_slot_url'] = route('network.nodes.infrastructure-slots.store', $item['id']);
            $row['pon_options_url'] = $deviceKind === \App\Enums\InfrastructureSlotKind::OLT ? route('network.nodes.pon-options', $item['id']) : null;
        }

        return $row;
    })),
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
                        {{-- Slot/PON/Port tidak ada di pohon awal (lazy), jadi tidak bisa difilter di sini. --}}
                        @foreach($nodeTypes->reject(fn ($type) => in_array($type->code, \App\Models\NetworkNode::positionTypeCodes(), true)) as $nodeType)
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

            {{-- Inspector Slot/PON terpisah (`_slot-inspector`/`_pon-inspector`)
                 sudah DIBUANG: keduanya membaca Slot/PON dari `$hierarchy`, padahal
                 sejak Task 11 Master Node tidak memuat posisi sama sekali (lazy) —
                 tak pernah merender apa pun. Slot/PON kini tampil di panel
                 perangkat lazy di bawah dan di halaman Detail masing-masing.
                 `$inspectorNodes` = `$nodes` + paling banyak SATU ODP anak PON yang
                 dipilih lewat `?node=` (putaran UI 3) — ODP itu memakai blok
                 inspector yang sama persis (grid port, modal, relokasi). --}}
            <div id="node-inspector-list">
                @include('network.nodes._inspector-node')
            </div>
            <div id="lazy-odp-inspector"></div>
            <div x-show="odpLoading" x-cloak class="p-5 text-xs text-text-muted">Memuat panel ODP...</div>
            <div x-show="odpLoadError" x-cloak class="p-5 text-xs text-rose-700 dark:text-rose-300">Panel ODP gagal dimuat. Klik ODP lagi untuk mencoba ulang.</div>

            {{-- Inspector Slot/PON (putaran UI 2): SATU blok generik untuk posisi
                 yang dipilih — bukan satu blok per posisi (posisi tidak dimuat ke
                 $nodes, fix C2). Header diisi dari data pilihan (JSON children /
                 tombol "Kelola" / `selectedPosition`) lewat x-text saja; isi panel
                 "Manajemen Node — Slot/PON" diambil dari `panel_url` yang dirender
                 server. Token permintaan di `nodePanel()` mencegah respons pilihan
                 lama (klik cepat A lalu B) tampil di bawah header B. --}}
            <div x-show="selectedPosition && selectedPosition.id === selectedNodeId" x-cloak
                 id="inspector-position" data-position-inspector
                 x-data="nodePanel()"
                 x-effect="load(selectedPosition && selectedPosition.id === selectedNodeId ? selectedPosition.panel_url : null)">
                <div class="px-4 sm:px-6 py-3.5 border-b border-border flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-surface-muted/40">
                    <div class="flex items-center flex-wrap gap-1.5 text-xs text-text-muted min-w-0">
                        <button type="button" @click="activeMobileTab = 'tree'"
                                class="lg:hidden inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-surface border border-border text-xs font-semibold text-text-main hover:bg-surface-muted shadow-2xs mr-1 cursor-pointer shrink-0">
                            <span>Pohon</span>
                        </button>
                        <span class="font-mono text-text-secondary" data-position-path x-text="selectedPosition ? selectedPosition.path : ''"></span>
                    </div>
                    <a :href="selectedPosition ? selectedPosition.url : '#'"
                       class="inline-flex items-center justify-center rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-semibold text-sky-600 dark:text-sky-400 hover:bg-surface-muted shadow-2xs">Buka Detail &rarr;</a>
                </div>
                <div class="p-4 sm:p-6 space-y-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="px-2.5 py-0.5 rounded-md text-[10px] font-mono font-bold uppercase tracking-wider border bg-amber-50 text-amber-800 dark:bg-amber-950/70 dark:text-amber-300 border-amber-200 dark:border-amber-800"
                              x-text="selectedPosition ? lazyTreeBadge(selectedPosition.type) : ''"></span>
                        <h2 class="text-xl font-bold font-mono text-text-main tracking-tight" x-text="selectedPosition ? selectedPosition.label : ''"></h2>
                    </div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-text-main flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-sky-600"></span>
                        <span x-text="'Manajemen Node — ' + (selectedPosition && ['olt_pon'].includes(selectedPosition.type) ? 'PON' : 'Slot')"></span>
                    </h3>
                    <p x-show="loading" class="rounded-lg border border-dashed border-border bg-surface-muted/40 p-4 text-xs text-text-muted">Memuat…</p>
                    <div x-show="failed" class="rounded-lg border border-rose-200 dark:border-rose-900 bg-rose-50 dark:bg-rose-950/40 p-4 text-xs text-rose-700 dark:text-rose-300 flex flex-wrap items-center justify-between gap-2">
                        <span>Gagal memuat panel.</span>
                        <button type="button" @click="retry()" class="rounded-lg border border-rose-300 dark:border-rose-800 bg-surface px-3 py-1 font-semibold cursor-pointer">Coba lagi</button>
                    </div>
                    <div x-ref="body" x-show="loadedUrl" @click="handlePanelClick($event)"></div>
                </div>
            </div>
        </section>
    </div>

    @include('network.nodes._create-point-modal')
    @include('network.nodes._lazy-tree-node-template')

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

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-text-secondary">Desa/Kelurahan</label>
                        <input type="text" name="village" x-model="editModal.village" placeholder="Desa"
                               class="block w-full rounded-lg border border-border bg-surface px-3 py-2 text-text-main text-xs focus:border-primary focus:ring-1 focus:ring-primary/20 outline-none">
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-text-secondary">Kecamatan</label>
                        <input type="text" name="district" x-model="editModal.district" placeholder="Kecamatan"
                               class="block w-full rounded-lg border border-border bg-surface px-3 py-2 text-text-main text-xs focus:border-primary focus:ring-1 focus:ring-primary/20 outline-none">
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-text-secondary">Kota/Kabupaten</label>
                        <input type="text" name="city" x-model="editModal.city" placeholder="Kota/Kabupaten"
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
                    </div>
                    {{-- Lepas port wajib konfirmasi fisik + catatan (`updatePort`:
                         physical_removal_confirmed & release_note). Dulu form ini hanya
                         mengirim is_used=0 → selalu gagal validasi, port tak pernah
                         lepas dari Master Node. Isian sama dengan halaman Detail. --}}
                    <form :action="assignModal.updateUrl" method="POST" class="no-confirm space-y-2 border-t border-border pt-3" data-port-release-form>
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="is_used" value="0">
                        <label class="flex items-center gap-2 text-[11px] text-text-secondary">
                            <input type="checkbox" name="physical_removal_confirmed" value="1" required>
                            <span>Kabel fisik pelanggan/sambungan sudah dilepas dari port ini</span>
                        </label>
                        <input name="release_note" required maxlength="500" value="Pelepasan port kabel fisik" placeholder="Catatan pelepasan fisik"
                               class="block w-full rounded-lg border border-border bg-surface px-3 py-2 text-text-main text-xs focus:border-primary focus:ring-1 focus:ring-primary/20 outline-none">
                        <div class="flex justify-end">
                            <button type="submit" :disabled="!assignModal.updateUrl"
                                    class="px-4 py-2 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold shadow-xs cursor-pointer transition disabled:opacity-50">
                                Lepas / Putus Port Ini
                            </button>
                        </div>
                    </form>
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
                            <form :action="assignModal.assignCustomerUrl" method="POST" class="no-confirm space-y-3">
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
                            <form :action="assignModal.assignNodeUrl" method="POST" class="no-confirm space-y-3">
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
                        <form :action="assignModal.updateUrl" method="POST" class="no-confirm space-y-3">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="is_used" value="1">
                            <input type="hidden" name="master_node" :value="assignModal.nodeId">
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

/**
 * Panel "Manajemen Node — <tipe>" (`network.nodes.panel`), dipakai dua
 * tempat: panel OLT/Switch di blok inspector perangkat (URL tetap dari
 * `data-panel-url`, dibaca di `init()` karena di handler `@click` `$el`
 * menunjuk tombol) dan inspector Slot/PON generik (URL = `panel_url` pilihan
 * saat ini, dikirim x-effect). URL selalu dari server, tidak dirakit di sini.
 *
 * Respons = potongan HTML server dari endpoint kita sendiri (Blade ter-escape,
 * tanpa atribut Alpine) — disisipkan apa adanya, tanpa Alpine.initTree.
 *
 * Balapan klik cepat A lalu B: tiap permintaan dapat nomor `seq`; respons
 * yang nomornya bukan yang terbaru dibuang, dan isi lama dikosongkan begitu
 * permintaan baru mulai — panel A tidak pernah tampil di bawah header B.
 * `failed` untuk URL yang sama menghentikan x-effect mengulang fetch tanpa
 * henti; ulang hanya lewat "Coba lagi" (`retry()`).
 */
function nodePanel() {
    return {
        fixedUrl: null,
        currentUrl: null,
        loadedUrl: null,
        loading: false,
        failed: false,
        seq: 0,
        init() {
            this.fixedUrl = this.$el.dataset.panelUrl || null;
        },
        async load(url = this.fixedUrl, force = false) {
            if (!url) {
                // Pilihan dilepas: batalkan permintaan yang masih jalan dan
                // kosongkan isi supaya pilihan berikutnya tidak sempat melihatnya.
                if (this.currentUrl !== null || this.loading) {
                    this.seq++;
                    this.currentUrl = null;
                    this.loadedUrl = null;
                    this.loading = false;
                    this.failed = false;
                    this.setBody('');
                }
                return;
            }
            if (!force && url === this.currentUrl && (this.loading || this.loadedUrl === url || this.failed)) return;

            const seq = ++this.seq;
            this.currentUrl = url;
            this.loadedUrl = null;
            this.loading = true;
            this.failed = false;
            this.setBody('');
            try {
                const response = await fetch(url, {
                    headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (!response.ok) throw new Error('HTTP ' + response.status);
                const html = await response.text();
                if (seq !== this.seq) return;
                this.setBody(html);
                this.loadedUrl = url;
            } catch {
                if (seq === this.seq) this.failed = true;
            } finally {
                if (seq === this.seq) this.loading = false;
            }
        },
        retry() {
            return this.load(this.currentUrl || this.fixedUrl, true);
        },
        // x-effect di elemen akar komponen jalan SEBELUM anak-anaknya
        // diinisialisasi, jadi `x-ref="body"` belum terdaftar pada panggilan
        // pertama (node terpilih sejak load halaman). Saat itu body memang
        // masih kosong, jadi cukup dilewati; setelah `await fetch` ref sudah ada.
        setBody(html) {
            const body = this.$refs.body;
            if (body) body.innerHTML = html;
        },
    };
}

/**
 * Data pilihan dari tombol "Kelola …" di dalam panel sisipan
 * (`data-select-position`, JSON ter-escape yang dirender server). Panel tidak
 * memuat atribut Alpine, jadi klik ditangkap listener delegasi di kontainer.
 */
function panelSelectionFrom(event) {
    const trigger = event.target.closest('[data-select-position]');
    if (!trigger) return null;
    try {
        const payload = JSON.parse(trigger.dataset.selectPosition);

        return payload && payload.id && payload.panel_url ? payload : null;
    } catch {
        return null;
    }
}

/**
 * Field "PON induk" bercari di modal Tambah ODP dan form Relokasi Uplink ODP
 * di inspector (`data-pon-search-url` pada elemen x-data). Pencarian di server
 * (`network.nodes.pon-search`, ≤20 hasil, ter-scope POP) — URL dari
 * `data-pon-search-url` yang dirender server. Yang dikirim form hanya
 * `parent_id` tersembunyi = id PON terpilih; mengetik ulang membatalkan
 * pilihan supaya teks di kotak tidak pernah berbeda dari PON yang dikirim.
 */
function ponSearchField(initialId) {
    return {
        url: null,
        query: '',
        options: [],
        selectedId: initialId ? String(initialId) : '',
        selectedLabel: '',
        open: false,
        loading: false,
        active: -1,
        timer: null,
        seq: 0,
        init() {
            this.url = this.$el.dataset.ponSearchUrl || null;
        },
        onInput() {
            this.selectedId = '';
            this.selectedLabel = '';
            this.open = true;
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.fetchOptions(), 250);
        },
        async fetchOptions() {
            if (!this.url) return;
            // Nomor urut permintaan: respons lama yang datang belakangan
            // tidak boleh menimpa hasil ketikan terbaru.
            const seq = ++this.seq;
            this.loading = true;
            try {
                const target = new URL(this.url, window.location.origin);
                target.searchParams.set('q', this.query.trim());
                const response = await fetch(target, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                const data = response.ok ? await response.json() : { options: [] };
                if (seq !== this.seq) return;
                this.options = data.options || [];
                this.active = this.options.length > 0 ? 0 : -1;
            } catch {
                if (seq === this.seq) this.options = [];
            } finally {
                if (seq === this.seq) this.loading = false;
            }
        },
        focus() {
            this.open = true;
            if (this.options.length === 0) this.fetchOptions();
        },
        move(step) {
            if (!this.open) { this.open = true; return; }
            if (this.options.length === 0) return;
            this.active = (this.active + step + this.options.length) % this.options.length;
        },
        choose(option) {
            if (!option) return;
            this.selectedId = String(option.id);
            this.selectedLabel = option.label;
            this.query = option.label;
            this.open = false;
        },
        chooseActive() {
            if (this.open && this.active >= 0) this.choose(this.options[this.active]);
        },
    };
}

/**
 * Satu cabang pohon yang anaknya dimuat saat DIBUKA (bukan saat init:
 * `$watch` tidak terpanggil untuk nilai awal, jadi cabang yang dirender
 * terbuka dulu tidak pernah memuat anaknya). Dipakai baik oleh item
 * server-rendered (`_hierarchy-item`) maupun anak lazy bertingkat
 * (perangkat → Slot → PON → ODP) lewat template `#lazy-tree-node-template`.
 */
function lazyTreeNode(childrenUrl) {
    return {
        isOpen: false,
        lazyLoaded: false,
        lazyLoading: false,
        lazyChildren: [],
        childrenUrl: childrenUrl || null,
        async setOpen(open) {
            this.isOpen = open;
            if (!open || this.lazyLoaded || this.lazyLoading || !this.childrenUrl) return;
            this.lazyLoading = true;
            this.lazyChildren = await loadNodeChildren(this.childrenUrl);
            this.lazyLoaded = true;
            this.lazyLoading = false;
        },
        toggle() {
            return this.setOpen(!this.isOpen);
        },
    };
}

function lazyTreeTemplate() {
    return document.getElementById('lazy-tree-node-template')?.innerHTML || '';
}

function lazyTreeBadge(type) {
    return ({ olt_slot: 'SLOT', switch_slot: 'SLOT', olt_pon: 'PON', switch_port: 'PORT', odp: 'ODP' })[type]
        || String(type || '').slice(0, 3).toUpperCase();
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
        selectedPosition: config.selectedPosition || null,
        treeOpenIds: config.treeOpenIds || [],
        odpLoading: false,
        odpLoadError: false,
        odpLoadSeq: 0,
        showCreateModal: config.showCreateModal,
        createType: config.createType,
        createPopId: String(config.createPopId || ''),
        createDeviceId: String(config.createDeviceId || ''),
        createSlotId: String(config.createSlotId || ''),
        createPonId: String(config.createPonId || ''),
        creationPops: config.creationPops || [],
        creationSlots: [],
        creationLoading: false,
        nodeTypeIds: config.nodeTypeIds || {},
        nodeTypeHasPorts: config.nodeTypeHasPorts || {},
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
            updateUrl: '',
            assignCustomerUrl: '',
            assignNodeUrl: '',
        },

        toggleRelocate(nodeId) {
            this.openRelocateId = (this.openRelocateId === nodeId) ? null : nodeId;
        },

        // Perangkat ber-Slot (OLT/Switch) dalam scope — hanya item pohon yang
        // dikirim server membawa URL `children_url`/`store_slot_url`.
        get creationDevices() {
            return Object.values(this.hierarchyItems).filter(item => item.kind === 'node' && item.store_slot_url);
        },

        get creationOlts() {
            return this.creationDevices.filter(item => item.pon_options_url);
        },

        get allowedCreationPops() {
            const allowed = this.createType === 'server' ? ['pusat'] : this.createType === 'switch_mini_pop' ? ['mini_pop'] : ['cabang', 'mini_pop'];
            return this.creationPops.filter(pop => allowed.includes(pop.type));
        },

        selectedCreateDevice() {
            return this.creationDevices.find(device => String(device.id) === String(this.createDeviceId)) || null;
        },

        // Slot TIDAK ada di pohon awal (lazy) — dimuat per OLT saat dipilih
        // (mode Tambah PON), lewat `children_url` yang dirender server pada
        // item perangkat. PON induk ODP tidak lewat sini lagi: field bercari
        // `ponSearchField()` mencari langsung ke server.
        async loadCreateOptions() {
            const device = this.selectedCreateDevice();
            this.createSlotId = '';
            this.creationSlots = [];
            if (!device || this.createType !== 'pon') return;
            this.creationLoading = true;
            const children = await loadNodeChildren(device.children_url);
            this.creationSlots = children.filter(child => child.store_port_url);
            this.creationLoading = false;
        },

        // Target POST dipilih dari URL kiriman server (item perangkat / anak
        // Slot dari endpoint children), bukan dirakit dari id.
        get createSlotAction() {
            return this.selectedCreateDevice()?.store_slot_url || '';
        },

        get createPonAction() {
            return this.creationSlots.find(slot => String(slot.id) === String(this.createSlotId))?.store_port_url || '';
        },

        chooseCreateType(type) {
            this.createType = type;
            this.createPopId = '';
            this.createDeviceId = '';
            this.createSlotId = '';
            this.createPonId = '';
            this.creationSlots = [];
        },

        openCreateModalRoot() {
            this.chooseCreateType('');
            this.showCreateModal = true;
        },

        openAddChildModal(parentId, parentCode, parentName) {
            const parent = this.hierarchyItems['node:' + parentId];
            this.openCreateModalRoot();
            if (parent && parent.store_slot_url) {
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
                // Target form dari data-* kartu port (dirender server lewat route()).
                updateUrl: data.urls?.portUpdateUrl || '',
                assignCustomerUrl: data.urls?.portAssignCustomerUrl || '',
                assignNodeUrl: data.urls?.portAssignNodeUrl || '',
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
            this.odpLoadSeq++;
            this.odpLoading = false;
            this.odpLoadError = false;
            this.markNodeSeen(id);
            this.selectedPosition = null;
            this.selectedNodeId = id;
            this.rememberSelection(id);
            this.portFilter = 'ALL';
            this.inspectorTab = 'ports';
            this.activeMobileTab = 'inspector';
            this.$nextTick(() => {
                const el = document.getElementById('inspector-node-' + id);
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            });
        },

        // Slot/PON (dari pohon lazy, tombol "Kelola" di panel, atau
        // `selectedPosition` server). `position` membawa `panel_url`/`url`
        // yang dirender server; objek disalin supaya state inspector tidak
        // berbagi referensi dengan baris pohon.
        selectPosition(position) {
            if (!position || !position.panel_url) return;
            this.odpLoadSeq++;
            this.odpLoading = false;
            this.odpLoadError = false;
            this.selectedPosition = {
                id: position.id,
                type: position.type,
                label: position.label,
                path: position.path,
                url: position.url,
                panel_url: position.panel_url,
            };
            this.selectedNodeId = position.id;
            this.rememberSelection(position.id);
            this.activeMobileTab = 'inspector';
            this.$nextTick(() => document.getElementById('inspector-position')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }));
        },

        // ODP anak PON tidak dimuat massal di index. Klik mengambil hanya
        // inspector ODP terpilih; URL ?node= tetap dipakai untuk akses langsung
        // dan redirect setelah form disimpan.
        async openOdp(child) {
            if (!child?.id || !child.inspector_url) return;
            if (document.getElementById('inspector-node-' + child.id)) {
                this.selectNode(child.id);
                return;
            }

            const seq = ++this.odpLoadSeq;
            this.odpLoading = true;
            this.odpLoadError = false;
            try {
                const response = await fetch(child.inspector_url, {
                    headers: { Accept: 'text/html' },
                    credentials: 'same-origin',
                });
                if (!response.ok) throw new Error('Gagal memuat inspector ODP');
                const html = await response.text();
                if (seq !== this.odpLoadSeq) return;

                const host = document.getElementById('lazy-odp-inspector');
                if (!host) throw new Error('Kontainer inspector ODP tidak ditemukan');
                Alpine.destroyTree(host);
                host.innerHTML = html;
                if (!host.querySelector('#inspector-node-' + child.id)) throw new Error('Respons inspector ODP tidak valid');
                Alpine.initTree(host);
                this.selectNode(child.id);
            } catch {
                if (seq === this.odpLoadSeq) {
                    this.odpLoading = false;
                    this.odpLoadError = true;
                }
            }
        },

        handlePanelClick(event) {
            const odp = event.target.closest('[data-select-odp]');
            if (odp) {
                event.preventDefault();
                this.openOdp({
                    id: Number(odp.dataset.selectOdp),
                    inspector_url: odp.dataset.inspectorUrl,
                });
                return;
            }
            const position = panelSelectionFrom(event);
            if (position) this.selectPosition(position);
        },

        // Simpan pilihan di URL (`?node=`): form panel yang gagal validasi
        // `back()` ke URL ini, dan index memilih ulang node yang sama —
        // perangkat lewat `$nodes`, Slot/PON lewat `selectedPosition`
        // (NetworkNodeController::index). Tanpa ini user dilempar ke node
        // pertama dan kehilangan konteks.
        rememberSelection(id) {
            try {
                const url = new URL(window.location.href);
                url.searchParams.set('node', id);
                window.history.replaceState(window.history.state, '', url);
            } catch {}
        }
    };
}
</script>
@endsection
