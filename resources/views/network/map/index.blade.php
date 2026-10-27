@extends('layouts.app')

@section('title', 'Peta Infrastruktur ISP')
@section('page_title', 'Peta Infrastruktur ISP')

@section('content')
<style>
    @import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans+Condensed:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap');

    .isp-infra { font-family: 'IBM Plex Sans', ui-sans-serif, system-ui, sans-serif; }
    .isp-infra .font-display { font-family: 'IBM Plex Sans Condensed', sans-serif; }
    .isp-infra .font-mono { font-family: 'IBM Plex Mono', monospace; font-variant-numeric: tabular-nums; }
    .isp-infra summary::-webkit-details-marker { display: none; }

    .canvas-dot-grid {
        touch-action: none;
        user-select: none;
        background-image: radial-gradient(rgba(148, 163, 184, 0.3) 1px, transparent 1px);
        background-size: 20px 20px;
    }
    .dark .canvas-dot-grid {
        background-image: radial-gradient(rgba(71, 85, 105, 0.45) 1px, transparent 1px);
        background-size: 20px 20px;
    }

    .custom-scroll {
        scrollbar-width: thin;
        scrollbar-color: rgba(148, 163, 184, 0.4) transparent;
    }
    .dark .custom-scroll {
        scrollbar-color: rgba(71, 85, 105, 0.5) transparent;
    }
</style>

<div
    x-data="{
        tab: @js(isset($initialNodeId) ? 'inventaris' : (request('tab') === 'inventaris' ? 'inventaris' : (request('tab') === 'pohon' ? 'pohon' : 'topologi'))),
        selectedNodeId: @js($initialNodeId ?? ($topologyPops->isNotEmpty() ? -$topologyPops->first()->id : $nodes->first()?->id)),
        selectedPort: null,
        drawerOpen: true,
        query: '',
        typeFilter: 'all',
        depthFilter: 'all',
        sort: @js($sort),
        index: @js($topologyIndex),
        nodes: @js($topologyDetails),
        hierarchyItems: @js(collect($hierarchy['items'])->map(fn ($item) => collect($item)->only(['label', 'name', 'type', 'parent']))),
        hierarchyChildren: @js($hierarchy['children']),
        visibleIds: {},
        collapsedIds: new Set(),
        layout: null,
        zoom: 1.0,
        panX: 40,
        panY: 40,
        isPanning: false,
        panStart: null,
        panMoved: false,

        init() {
            this.recompute();
            this.$nextTick(() => {
                if (this.tab === 'topologi') this.zoomFit();
            });
            if (this.selectedNodeId && this.nodes[this.selectedNodeId]) {
                const firstPort = this.nodes[this.selectedNodeId].ports?.[0];
                if (firstPort) this.selectedPort = firstPort.number;
            }
        },

        recompute() {
            const visible = {};
            const q = this.query.trim().toLowerCase();
            for (const [id, node] of Object.entries(this.index)) {
                if (this.typeFilter !== 'all' && node.type !== this.typeFilter) continue;
                if (q && !node.search.includes(q)) continue;
                let current = id;
                while (current && this.index[current]) {
                    visible[current] = true;
                    current = this.index[current].parent;
                }
            }
            this.visibleIds = visible;
            this.layout = this.computeLayout();
        },

        visibleNode(id) {
            return !this.query.trim() && this.typeFilter === 'all' ? true : !!this.visibleIds[id];
        },

        matchesHierarchyFilter(key) {
            const item = this.hierarchyItems[key];
            if (!item) return false;
            const query = this.query.toLowerCase().trim();
            const matches = (this.typeFilter === 'all' || item.type === this.typeFilter) &&
                (!query || ((item.label || '') + ' ' + (item.name || '')).toLowerCase().includes(query));
            return matches || (this.hierarchyChildren[key] || []).some(child => this.matchesHierarchyFilter(child));
        },

        matchesNode(id) {
            const node = this.index[id];
            return !!node && (this.typeFilter === 'all' || node.type === this.typeFilter)
                && (!this.query.trim() || node.search.includes(this.query.trim().toLowerCase()));
        },

        selectNode(id) {
            this.selectedNodeId = id;
            this.selectedPort = null;
            this.drawerOpen = true;
            if (this.nodes[id]?.ports?.length) {
                const activeP = this.nodes[id].ports.find(p => p.is_used);
                this.selectedPort = activeP ? activeP.number : this.nodes[id].ports[0].number;
            }
        },

        toggleCollapse(id) {
            if (this.collapsedIds.has(id)) {
                this.collapsedIds.delete(id);
            } else {
                this.collapsedIds.add(id);
            }
            this.depthFilter = 'custom';
            this.layout = this.computeLayout();
        },

        isCollapsed(id) {
            return this.collapsedIds.has(id);
        },

        applyDepth(val) {
            this.depthFilter = val;
            this.collapsedIds.clear();
            if (val !== 'all' && val !== 'custom') {
                for (const [id, n] of Object.entries(this.nodes)) {
                    const t = (n.type || '').toLowerCase();
                    if (val === 'pop' && t.startsWith('pop_') && !Object.values(this.nodes).some(child => child.parent_id === +id && child.type?.startsWith('pop_'))) {
                        this.collapsedIds.add(+id);
                    } else if (val === 'olt' && (t.includes('olt') || t.includes('card'))) {
                        this.collapsedIds.add(+id);
                    } else if (val === 'odp' && t.includes('odp')) {
                        this.collapsedIds.add(+id);
                    }
                }
            }
            this.layout = this.computeLayout();
            this.$nextTick(() => this.zoomFit());
        },

        zoomAt(factor, clientX = null, clientY = null) {
            const canvas = this.$refs.topologyCanvas;
            if (!canvas) return;
            const bounds = canvas.getBoundingClientRect();
            const focusX = clientX === null ? bounds.width / 2 : clientX - bounds.left;
            const focusY = clientY === null ? bounds.height / 2 : clientY - bounds.top;
            const nextZoom = Math.max(0.2, Math.min(2, this.zoom * factor));
            const ratio = nextZoom / this.zoom;
            this.panX = focusX - (focusX - this.panX) * ratio;
            this.panY = focusY - (focusY - this.panY) * ratio;
            this.zoom = nextZoom;
        },

        zoomIn() {
            this.zoomAt(1.2);
        },

        zoomOut() {
            this.zoomAt(1 / 1.2);
        },

        zoomFit() {
            const canvas = this.$refs.topologyCanvas;
            if (!canvas || !this.layout?.order.length) return;
            const positions = this.layout.order.map(id => this.layout.pos[id]);
            const width = Math.max(...positions.map(pos => pos.x)) + this.layout.cardW + 12;
            const height = Math.max(...positions.map(pos => pos.y)) + this.layout.cardH;
            this.zoom = Math.max(0.25, Math.min(1.1, (canvas.clientWidth - 40) / width, (canvas.clientHeight - 40) / height));
            this.panX = 20;
            this.panY = Math.max(20, (canvas.clientHeight - height * this.zoom) / 2);
        },

        onCanvasWheel(event) {
            if (!event.ctrlKey) return;
            event.preventDefault();
            this.zoomAt(event.deltaY < 0 ? 1.12 : 1 / 1.12, event.clientX, event.clientY);
        },

        onPanStart(event) {
            if (event.button !== 0) return;
            event.preventDefault();
            this.panStart = {
                pointerId: event.pointerId,
                x: event.clientX,
                y: event.clientY,
                panX: this.panX,
                panY: this.panY
            };
            this.panMoved = false;
        },

        onPanMove(event) {
            if (!this.panStart || event.pointerId !== this.panStart.pointerId) return;
            const dx = event.clientX - this.panStart.x;
            const dy = event.clientY - this.panStart.y;
            if (!this.panMoved && Math.abs(dx) + Math.abs(dy) <= 4) return;
            this.panMoved = true;
            this.isPanning = true;
            this.panX = this.panStart.panX + dx;
            this.panY = this.panStart.panY + dy;
        },

        onPanEnd(event) {
            if (!this.panStart || event.pointerId !== this.panStart.pointerId) return;
            this.panStart = null;
            this.isPanning = false;
        },

        get currentSelectedNode() {
            return this.nodes[this.selectedNodeId] || null;
        },

        get currentSelectedPortData() {
            if (!this.currentSelectedNode || !this.selectedPort) return null;
            return this.currentSelectedNode.ports?.find(p => p.number === this.selectedPort) || null;
        },

        getNodeStyle(type) {
            const t = (type || '').toLowerCase();
            if (t === 'customer_ont') {
                return { tag: 'ONT', color: '#7c3aed', border: '#8b5cf6', bg: 'rgba(237, 233, 254, 0.5)' };
            }
            if (t.includes('odp')) {
                return { tag: 'ODP', color: '#ea580c', border: '#f97316', bg: 'rgba(254, 243, 199, 0.4)' };
            }
            if (t.includes('odc')) {
                return { tag: 'ODC', color: '#b45309', border: '#d97706', bg: 'rgba(254, 249, 195, 0.4)' };
            }
            // Slot/PON/Port: baris `network_nodes` sungguhan sejak Task 11
            // (bukan lagi kanvas negatif-offset dari tabel lama) — tipe
            // literalnya langsung dari `network_node_types.code`, beda warna
            // dari kartu device-nya supaya kelihatan sebagai lapisan terpisah.
            if (t === 'olt_slot' || t === 'switch_slot') {
                return { tag: 'SLOT', color: '#0e7490', border: '#22d3ee', bg: 'rgba(207, 250, 254, 0.4)' };
            }
            if (t === 'olt_pon') {
                return { tag: 'PON', color: '#166534', border: '#4ade80', bg: 'rgba(220, 252, 231, 0.4)' };
            }
            if (t === 'switch_port') {
                return { tag: 'PORT', color: '#3730a3', border: '#818cf8', bg: 'rgba(224, 231, 255, 0.4)' };
            }
            if (t.includes('olt') || t.includes('pon')) {
                return { tag: 'OLT', color: '#047857', border: '#10b981', bg: 'rgba(209, 250, 229, 0.4)' };
            }
            if (t.includes('switch')) {
                return { tag: 'SWITCH', color: '#4338ca', border: '#6366f1', bg: 'rgba(224, 231, 255, 0.4)' };
            }
            if (t.includes('pop') || t.includes('cabang') || t.includes('pusat')) {
                return { tag: t.includes('pusat') ? 'PUSAT' : (t.includes('mini') ? 'MINI POP' : 'CABANG'), color: '#0f766e', border: '#14b8a6', bg: 'rgba(204, 251, 241, 0.4)' };
            }
            return { tag: 'CORE', color: '#0284c7', border: '#38bdf8', bg: 'rgba(224, 242, 254, 0.4)' };
        },

        computeLayout() {
            const CARD_W = 220;
            const CARD_H = 64;
            const COL_W = 280;
            const ROW_H = 82;
            const pos = {};
            const edges = [];
            const order = [];
            let row = 0;
            let maxDepth = 0;

            const getKids = (id) => {
                if (this.collapsedIds.has(+id)) return [];
                const kids = [];
                for (const [cid, n] of Object.entries(this.nodes)) {
                    if (n.parent_id == id && this.visibleNode(+cid) && (this.depthFilter !== 'pop' || n.type?.startsWith('pop_'))) {
                        kids.push(+cid);
                    }
                }
                return kids;
            };

            const walk = (id, depth) => {
                order.push(id);
                maxDepth = Math.max(maxDepth, depth);
                const kids = getKids(id);
                if (!kids.length) {
                    pos[id] = { x: depth * COL_W, y: row * ROW_H, depth };
                    row++;
                    return;
                }
                kids.forEach(k => {
                    edges.push({ from: id, to: k });
                    walk(k, depth + 1);
                });
                pos[id] = {
                    x: depth * COL_W,
                    y: (pos[kids[0]].y + pos[kids[kids.length - 1]].y) / 2,
                    depth
                };
            };

            const roots = Object.values(this.nodes)
                .filter(n => (!n.parent_id || !this.nodes[n.parent_id]) && this.visibleNode(n.id))
                .map(n => n.id);

            roots.forEach(r => walk(r, 0));

            return {
                pos,
                edges,
                order,
                width: Math.max(860, maxDepth * COL_W + CARD_W + 100),
                height: Math.max(520, row * ROW_H + 100),
                cardW: CARD_W,
                cardH: CARD_H
            };
        },

        edgePaths(layout) {
            const activePath = new Set();
            let current = this.selectedNodeId;
            while (current && this.nodes[current]) {
                activePath.add(current);
                current = this.nodes[current].parent_id;
            }

            return layout.edges.map(edge => {
                const from = layout.pos[edge.from];
                const to = layout.pos[edge.to];
                if (!from || !to) return '';
                const x1 = from.x + layout.cardW + 12;
                const y1 = from.y + layout.cardH / 2;
                const x2 = to.x;
                const y2 = to.y + layout.cardH / 2;
                const midX = x1 + (x2 - x1) / 2;
                const stroke = activePath.has(edge.to) ? '#0284c7' : 'currentColor';
                const strokeWidth = activePath.has(edge.to) ? 2.2 : 1.6;
                return `<path d='M ${x1},${y1} C ${midX},${y1} ${midX},${y2} ${x2},${y2}' fill='none' stroke='${stroke}' stroke-width='${strokeWidth}' />`;
            }).join('');
        }
    }"
    class="isp-infra flex h-full w-full flex-col bg-[#f8fafc] text-slate-900 dark:bg-[#0c1319] dark:text-slate-100 overflow-hidden">

    <!-- TOP HEADER -->
    <header class="shrink-0 border-b border-slate-200 bg-white px-5 py-4 dark:border-slate-800 dark:bg-[#131d24] sm:px-7">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span class="inline-flex size-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <p class="font-display text-xs font-semibold uppercase tracking-[0.15em] text-sky-700 dark:text-sky-400">Peta Jaringan & Topologi Distribusi</p>
                </div>
                <h1 class="font-display text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100 sm:text-3xl">Peta Infrastruktur ISP</h1>
                <p class="mt-0.5 text-xs text-slate-600 dark:text-slate-400">Kapasitas, slot kosong, aset Gudang, dan topologi dari POP sampai pelanggan.</p>
            </div>

            <!-- ACTION BUTTONS -->
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('manuals.context', ['route' => 'network.map.index']) }}"
                   class="inline-flex items-center gap-1.5 rounded-md border border-sky-300 bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-800 transition hover:bg-sky-100 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-300">
                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                    Panduan
                </a>
                <a href="{{ route('network.nodes.index') }}"
                   class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 shadow-xs transition hover:border-sky-500 hover:text-sky-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="7" height="7" x="3" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="14" rx="1"></rect><rect width="7" height="7" x="3" y="14" rx="1"></rect></svg>
                    Kelola Node
                </a>
                <a href="{{ route('network.map.index') }}"
                   class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 shadow-xs transition hover:border-sky-500 hover:text-sky-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"></path><path d="M16 21h5v-5"></path></svg>
                    Muat Ulang
                </a>
            </div>
        </div>

        <!-- SEARCH & CABANG BAR -->
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="relative flex-1">
                <svg class="pointer-events-none absolute left-3 top-2.5 size-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.35-4.35"></path></svg>
                <input
                    type="search"
                    x-model="query"
                    @input.debounce.150ms="recompute()"
                    placeholder="Cari perangkat, kode, SN, barang Gudang, atau nama pelanggan..."
                    class="w-full rounded-md border border-slate-300 bg-slate-50/70 py-2 pl-9 pr-3 text-xs text-slate-900 placeholder:text-slate-500 focus:border-sky-500 focus:bg-white focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
            </div>

            <form method="GET" action="{{ route('network.map.index') }}" class="flex items-center gap-2 shrink-0">
                <input type="hidden" name="tab" x-model="tab">
                <input type="hidden" name="sort" value="{{ $sort }}">
                <label for="map-pop" class="font-display text-[11px] font-semibold uppercase tracking-wider text-slate-500">Cabang</label>
                <select id="map-pop" name="pop_id" onchange="this.form.submit()"
                        class="rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-800 shadow-xs focus:border-sky-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                    <option value="">Semua dalam scope</option>
                    @foreach($cabangs as $cabang)
                        <option value="{{ $cabang->id }}" @selected($selectedPopId === $cabang->id)>{{ $cabang->name }}</option>
                    @endforeach
                </select>
            </form>
        </div>
    </header>

    <!-- METRIC KPI SUMMARY ROW (5 CARDS) -->
    <div class="shrink-0 grid grid-cols-2 divide-x divide-y divide-slate-200 border-b border-slate-200 bg-white dark:divide-slate-800 dark:border-slate-800 dark:bg-[#111920] sm:grid-cols-3 sm:divide-y-0 lg:grid-cols-5">
        <div class="px-5 py-3.5">
            <span class="font-display text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Titik Jaringan</span>
            <div class="font-mono mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">
                {{ $summary['nodes'] }} <span class="text-xs font-normal text-slate-500">node</span>
            </div>
        </div>
        <div class="px-5 py-3.5">
            <span class="font-display text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Port Terpakai</span>
            <div class="font-mono mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">
                {{ $summary['used_ports'] }}/{{ $summary['ports'] }}
                @if($summary['ports'] > 0)
                    <span class="text-xs font-normal text-slate-500">({{ round(100 * $summary['used_ports'] / $summary['ports']) }}%)</span>
                @endif
            </div>
        </div>
        <div class="px-5 py-3.5">
            <span class="font-display text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Slot OLT Kosong</span>
            <div class="font-mono mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">
                {{ $summary['free_olt_slots'] }} <span class="text-xs font-normal text-slate-500">slot</span>
            </div>
        </div>
        <div class="px-5 py-3.5">
            <span class="font-display text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Aset Terpasang</span>
            <div class="font-mono mt-1 text-xl font-bold text-sky-700 dark:text-sky-400">
                {{ $summary['assets'] }} <span class="text-xs font-normal text-slate-500">baris</span>
            </div>
        </div>
        <div class="px-5 py-3.5">
            <span class="font-display text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Zona Kritis (≥85%)</span>
            <div class="font-mono mt-1 text-xl font-bold {{ $summary['red_zone'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                {{ $summary['red_zone'] }} <span class="text-xs font-normal text-slate-500">node</span>
            </div>
        </div>
    </div>

    <!-- MAIN NAV TABS BAR -->
    <div class="shrink-0 flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-100/70 px-5 py-1.5 dark:border-slate-800 dark:bg-[#131d24] sm:px-7">
        <div role="tablist" aria-label="Tampilan Utama" class="flex gap-1">
            <button
                type="button"
                role="tab"
                @click="tab = 'topologi'; $nextTick(() => zoomFit())"
                :aria-selected="tab === 'topologi'"
                :class="tab === 'topologi' ? 'bg-white text-slate-900 shadow-xs border-b-2 border-sky-600 dark:bg-slate-800 dark:text-slate-100' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/60 dark:text-slate-400 dark:hover:text-slate-200'"
                class="font-display inline-flex items-center gap-1.5 rounded-t px-3.5 py-2 text-xs font-semibold transition">
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>
                Topologi
            </button>

            <button
                type="button"
                role="tab"
                @click="tab = 'pohon'"
                :aria-selected="tab === 'pohon'"
                :class="tab === 'pohon' ? 'bg-white text-slate-900 shadow-xs border-b-2 border-sky-600 dark:bg-slate-800 dark:text-slate-100' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/60 dark:text-slate-400 dark:hover:text-slate-200'"
                class="font-display inline-flex items-center gap-1.5 rounded-t px-3.5 py-2 text-xs font-semibold transition">
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12H3"></path><path d="M12 3v18"></path><path d="M18 6l-6-3-6 3"></path></svg>
                Per POP (Pohon)
            </button>

            <button
                type="button"
                role="tab"
                @click="tab = 'inventaris'"
                :aria-selected="tab === 'inventaris'"
                :class="tab === 'inventaris' ? 'bg-white text-slate-900 shadow-xs border-b-2 border-sky-600 dark:bg-slate-800 dark:text-slate-100' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/60 dark:text-slate-400 dark:hover:text-slate-200'"
                class="font-display inline-flex items-center gap-1.5 rounded-t px-3.5 py-2 text-xs font-semibold transition">
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                Inventaris
            </button>
        </div>

        <div class="flex items-center gap-3">
            <span class="font-mono text-[11px] text-slate-500 dark:text-slate-400">
                {{ $nodes->count() }} node dalam cakupan
            </span>
            <button type="button" @click="drawerOpen = !drawerOpen"
                    class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 shadow-xs transition hover:border-sky-500 hover:text-sky-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                <svg class="size-3.5 transition-transform" :class="drawerOpen ? '' : 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"></path></svg>
                <span x-text="drawerOpen ? 'Tutup Detail' : 'Buka Detail'"></span>
            </button>
        </div>
    </div>

    <!-- MAIN BODY: WORKSPACE (FULL) + DRAWER DETAIL (OVERLAY KANAN) -->
    <div class="relative flex-1 min-h-0 overflow-hidden">

        <!-- LEFT WORKSPACE AREA -->
        <main class="h-full w-full min-w-0 bg-[#f8fafc] dark:bg-[#0c1319] flex flex-col">

            <!-- TAB 1: TOPOLOGI (SVG CANVAS) -->
            <section x-show="tab === 'topologi'" class="flex-1 flex flex-col min-h-0">
                <!-- TOPOLOGY TOOLBAR -->
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-white/80 px-4 py-2.5 backdrop-blur-xs dark:border-slate-800 dark:bg-[#131d24]">
                    <div class="flex flex-wrap items-center gap-2">
                        <!-- DEPTH SELECTOR -->
                        <div class="flex items-center gap-1.5">
                            <label for="topo-depth" class="font-display text-[11px] font-semibold uppercase text-slate-500">Kedalaman</label>
                            <select id="topo-depth" x-model="depthFilter" @change="applyDepth($event.target.value)"
                                    class="rounded border border-slate-300 bg-white px-2 py-1 text-xs text-slate-800 shadow-2xs dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                                <option value="custom">Kustom</option>
                                <option value="all">Semua (sampai ONT/daun)</option>
                                <option value="odp">Sampai ODP</option>
                                <option value="olt">Sampai OLT / PON</option>
                                <option value="pop">Sampai POP</option>
                            </select>
                        </div>

                        <!-- ZOOM BUTTONS -->
                        <div class="flex items-center rounded border border-slate-300 bg-white dark:border-slate-700 dark:bg-slate-800 shadow-2xs">
                            <button type="button" @click="zoomOut()" aria-label="Perkecil" class="px-2 py-1 text-xs font-mono font-bold hover:bg-slate-100 dark:hover:bg-slate-700">−</button>
                            <span class="px-2 font-mono text-[11px] font-medium" x-text="Math.round(zoom * 100) + '%'">100%</span>
                            <button type="button" @click="zoomIn()" aria-label="Perbesar" class="px-2 py-1 text-xs font-mono font-bold hover:bg-slate-100 dark:hover:bg-slate-700">+</button>
                            <button type="button" @click="zoomFit()" class="border-l border-slate-200 dark:border-slate-700 px-2 py-1 font-display text-[11px] font-semibold hover:bg-slate-100 dark:hover:bg-slate-700">Pas</button>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 text-[11px] text-slate-500 dark:text-slate-400">
                        <span class="inline-flex items-center gap-1"><span class="size-2 rounded-full bg-emerald-500"></span> Aktif</span>
                        <span class="inline-flex items-center gap-1"><span class="size-2 rounded-full bg-slate-400"></span> Nonaktif</span>
                        <span class="inline-flex items-center gap-1"><span class="size-2 rounded-full bg-rose-500"></span> Penuh</span>
                    </div>
                </div>

                <!-- SVG INTERACTIVE CANVAS -->
                <div x-ref="topologyCanvas"
                     class="relative flex-1 min-h-[580px] overflow-hidden canvas-dot-grid"
                     @pointerdown="onPanStart($event)"
                     @pointermove.window="onPanMove($event)"
                     @pointerup.window="onPanEnd($event)"
                     @pointercancel.window="onPanEnd($event)"
                     @wheel="onCanvasWheel($event)"
                     :class="isPanning ? 'cursor-grabbing' : 'cursor-grab'">

                    @if($topologyPops->isEmpty() && $nodes->isEmpty())
                        <div class="flex h-full items-center justify-center p-8 text-center">
                            <div class="max-w-md rounded-lg border border-dashed border-slate-300 bg-white p-6 dark:border-slate-700 dark:bg-[#131d24]">
                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">Belum ada node jaringan</p>
                                <p class="mt-1 text-xs text-slate-500">Belum ada node dalam cabang yang Anda pilih atau Anda belum memiliki izin.</p>
                            </div>
                        </div>
                    @else
                        <!-- DYNAMIC SVG TOPOLOGY -->
                        <div x-show="layout">
                            <div class="absolute left-0 top-0"
                                 :style="`width: ${layout.width}px; height: ${layout.height}px; transform: translate(${panX}px, ${panY}px) scale(${zoom}); transform-origin: 0 0; transition: ${isPanning ? 'none' : 'transform 0.1s ease-out'};`">
                                <svg :width="layout.width" :height="layout.height"
                                     class="pointer-events-none absolute left-0 top-0 text-slate-300 dark:text-slate-700/80"
                                     x-html="edgePaths(layout)"></svg>

                                <template x-for="id in layout.order" :key="id">
                                    <svg :width="layout.cardW + 12" :height="layout.cardH"
                                         :style="`left: ${layout.pos[id]?.x || 0}px; top: ${layout.pos[id]?.y || 0}px;`"
                                         class="absolute topo-card cursor-pointer">
                                        <g @click="panMoved || selectNode(id)">

                                            <!-- CARD BACKGROUND & BORDER -->
                                            <rect
                                                :width="layout.cardW"
                                                :height="layout.cardH"
                                                rx="6"
                                                :class="selectedNodeId === id
                                                    ? 'fill-sky-50/90 stroke-sky-500 dark:fill-sky-950/60 dark:stroke-sky-400 stroke-2 shadow-md'
                                                    : 'fill-white stroke-slate-200 hover:stroke-slate-400 dark:fill-[#152126] dark:stroke-slate-700 dark:hover:stroke-slate-500 stroke-1 shadow-xs'"
                                            />

                                            <!-- TYPE COLOR INDICATOR STRIP -->
                                            <rect
                                                x="10" y="10" width="6" height="6" rx="1.5"
                                                :fill="getNodeStyle(nodes[id]?.type).color"
                                            />

                                            <!-- TYPE BADGE -->
                                            <text
                                                x="22" y="16"
                                                class="font-mono text-[9px] font-bold uppercase tracking-wider fill-slate-500 dark:fill-slate-400"
                                                x-text="getNodeStyle(nodes[id]?.type).tag">
                                            </text>

                                            <!-- STATUS BADGE / RED ZONE BADGE -->
                                            <text
                                                x="210" y="16"
                                                text-anchor="end"
                                                class="font-mono text-[9px] font-bold"
                                                :class="nodes[id]?.is_red_zone ? 'fill-rose-600 dark:fill-rose-400' : 'fill-slate-400'"
                                                x-text="nodes[id]?.is_red_zone ? 'PENUH' : (nodes[id]?.status === 'active' ? '' : 'NONAKTIF')">
                                            </text>

                                            <!-- CODE & NAME -->
                                            <text
                                                x="10" y="34"
                                                class="font-mono text-[12px] font-bold fill-slate-900 dark:fill-slate-100"
                                                x-text="(nodes[id]?.code || '').length > 22 ? (nodes[id]?.code || '').substring(0, 20) + '…' : nodes[id]?.code">
                                            </text>
                                            <text
                                                x="10" y="47"
                                                class="text-[11px] fill-slate-500 dark:fill-slate-400"
                                                x-text="(nodes[id]?.name || '').length > 26 ? (nodes[id]?.name || '').substring(0, 24) + '…' : nodes[id]?.name">
                                            </text>

                                            <!-- UTILIZATION MICRO BAR -->
                                            <g x-show="nodes[id]?.ports_count > 0">
                                                <g>
                                                    <rect x="0" :y="layout.cardH - 3" :width="layout.cardW" height="3" class="fill-slate-100 dark:fill-slate-800" rx="0"/>
                                                    <rect
                                                        x="0" :y="layout.cardH - 3"
                                                        :width="nodes[id].ports_count ? Math.min(layout.cardW, (nodes[id].used_ports_count / nodes[id].ports_count) * layout.cardW) : 0"
                                                        height="3"
                                                        :fill="nodes[id].is_red_zone ? '#e11d48' : ((nodes[id].used_ports_count / nodes[id].ports_count) >= 0.7 ? '#f59e0b' : '#10b981')"
                                                    />
                                                </g>
                                            </g>

                                            <!-- COLLAPSE/EXPAND TOGGLE BUTTON ON RIGHT EDGE (IF HAS CHILDREN) -->
                                            <g x-show="Object.values(nodes).some(child => child.parent_id === id)">
                                                <g :transform="`translate(${layout.cardW}, ${layout.cardH / 2})`"
                                                   @click.stop="panMoved || toggleCollapse(id)"
                                                   class="cursor-pointer hover:opacity-80">
                                                    <circle r="9" class="fill-white stroke-slate-300 dark:fill-[#131d24] dark:stroke-slate-600" stroke-width="1.2"/>
                                                    <text text-anchor="middle" dy="0.32em" class="font-mono text-[9px] font-bold fill-slate-700 dark:fill-slate-300"
                                                          x-text="isCollapsed(id) ? '+' : '−'"></text>
                                                </g>
                                            </g>
                                        </g>
                                    </svg>
                                </template>
                            </div>
                        </div>
                    @endif

                    <div class="pointer-events-none absolute bottom-3 left-4 rounded bg-white/85 px-2.5 py-1 text-[11px] text-slate-500 shadow-2xs backdrop-blur-xs dark:bg-slate-900/85 dark:text-slate-400">
                        Seret kanvas untuk menggeser · Ctrl + scroll untuk zoom · Klik node untuk detail
                    </div>
                </div>
            </section>

            <!-- TAB 2: PER POP (POHON HIERARKI) -->
            <section x-show="tab === 'pohon'" class="flex-1 overflow-auto p-5 custom-scroll">
                @if($topologyPops->isEmpty() && $nodes->isEmpty())
                    <div class="rounded-lg border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-500 dark:border-slate-700 dark:bg-[#131d24]">
                        Belum ada POP atau node jaringan dalam scope Anda.
                    </div>
                @else
                    <div class="space-y-4">
                        @foreach($hierarchy['roots'] as $hierarchyKey)
                            @include('network.map._hierarchy-item', ['hierarchyKey' => $hierarchyKey])
                        @endforeach
                    </div>
                @endif
            </section>

            <!-- TAB 3: INVENTARIS (TABULAR VIEW) -->
            <section x-show="tab === 'inventaris'" class="flex-1 p-5 overflow-auto custom-scroll">
                <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xs dark:border-slate-800 dark:bg-[#131d24]">
                    <!-- INVENTARIS TOOLBAR -->
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50/70 px-4 py-3 dark:border-slate-800 dark:bg-slate-800/40">
                        <div>
                            <h2 class="font-display text-sm font-bold text-slate-900 dark:text-slate-100">Daftar Inventaris Jaringan</h2>
                            <p class="text-[11px] text-slate-500">Kapasitas, utilisasi port, dan bukti aset fisik yang menempel di lapangan.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <!-- EXPORT CSV FORM -->
                            <form method="GET" action="{{ route('network.map.export') }}">
                                @if($selectedPopId)<input type="hidden" name="pop_id" value="{{ $selectedPopId }}">@endif
                                <input type="hidden" name="sort" value="{{ $sort }}">
                                <input type="hidden" name="q" :value="query">
                                <input type="hidden" name="type" :value="typeFilter">
                                <button type="submit"
                                        class="inline-flex items-center gap-1 rounded border border-sky-300 bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-800 hover:bg-sky-100 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-300">
                                    <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                    Ekspor CSV
                                </button>
                            </form>

                            <!-- SORT SELECTOR -->
                            <form method="GET" action="{{ route('network.map.index') }}" class="flex items-center gap-1.5">
                                @if($selectedPopId)<input type="hidden" name="pop_id" value="{{ $selectedPopId }}">@endif
                                <input type="hidden" name="tab" value="inventaris">
                                <label for="map-sort" class="font-display text-[10px] font-semibold uppercase text-slate-500">Urut</label>
                                <select id="map-sort" name="sort" onchange="this.form.submit()"
                                        class="rounded border border-slate-300 bg-white px-2 py-1 text-xs text-slate-800 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                                    <option value="topology" @selected($sort === 'topology')>Urutan topologi</option>
                                    <option value="utilization" @selected($sort === 'utilization')>Utilisasi tertinggi</option>
                                    <option value="free" @selected($sort === 'free')>Kosong terbanyak</option>
                                </select>
                            </form>
                        </div>
                    </div>

                    <!-- TABLE -->
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[760px] text-left text-xs">
                            <thead class="font-display bg-slate-100/80 text-[10px] font-semibold uppercase tracking-wider text-slate-600 dark:bg-slate-800/80 dark:text-slate-300">
                                <tr>
                                    <th class="px-4 py-3">Perangkat</th>
                                    <th class="px-4 py-3">Lokasi / Jalur</th>
                                    <th class="px-4 py-3">Pemakaian / Kapasitas</th>
                                    <th class="px-4 py-3">Kosong</th>
                                    <th class="px-4 py-3">Utilisasi</th>
                                    <th class="px-4 py-3">Status & Aset</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                                @forelse($inventoryNodes as $node)
                                    @php
                                        $positionCapacity = $topologyDetails[$node->id]['capacity'] ?? null;
                                        $total = $positionCapacity ? ($positionCapacity['capacity'] ?? null) : (int) $node->ports_count;
                                        $used = $positionCapacity ? $positionCapacity['used'] : (int) $node->used_ports_count;
                                        $percent = $total > 0 ? round(100 * $used / $total) : null;
                                        $nodeAssets = $assetsByNode[$node->id] ?? [];
                                    @endphp
                                    <tr x-show="matchesNode({{ $node->id }})"
                                        @click="selectNode({{ $node->id }})"
                                        class="cursor-pointer transition hover:bg-sky-50/70 dark:hover:bg-sky-950/30"
                                        :class="selectedNodeId === {{ $node->id }} ? 'bg-sky-50 dark:bg-sky-950/40 ring-1 ring-inset ring-sky-500/30' : ''">
                                        <td class="px-4 py-3">
                                            <div class="font-mono text-xs font-bold text-slate-900 dark:text-slate-100">{{ $node->code }}</div>
                                            <div class="text-xs text-slate-600 dark:text-slate-300">{{ $node->name }}</div>
                                            <span class="font-mono mt-0.5 inline-block text-[10px] uppercase text-slate-400">{{ $node->nodeType?->name ?? 'Node' }}</span>
                                        </td>
                                        <td class="max-w-[220px] px-4 py-3 text-slate-600 dark:text-slate-300 truncate font-mono text-[11px]">
                                            {{ $pathsByNode[$node->id] }}
                                        </td>
                                        <td class="font-mono px-4 py-3 font-medium">
                                            {{ $used }} / {{ $total ?? '?' }}
                                            <span class="text-[10px] font-normal text-slate-500">{{ $positionCapacity['unit_label'] ?? 'port' }}</span>
                                        </td>
                                        <td class="font-mono px-4 py-3 font-medium text-slate-500">
                                            {{ $total > 0 ? $total - $used : '—' }}
                                        </td>
                                        <td class="px-4 py-3">
                                            @if($percent !== null)
                                                <div class="flex items-center gap-2">
                                                    <span class="font-mono text-xs font-bold {{ $percent >= 90 ? 'text-rose-600 dark:text-rose-400' : ($percent >= 70 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400') }}">
                                                        {{ $percent }}%
                                                    </span>
                                                    <div class="h-1.5 w-16 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                                        <div class="h-full {{ $percent >= 90 ? 'bg-rose-600' : ($percent >= 70 ? 'bg-amber-500' : 'bg-emerald-500') }}" style="width: {{ $percent }}%"></div>
                                                    </div>
                                                </div>
                                            @else
                                                <span class="text-slate-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex rounded px-1.5 py-0.5 text-[10px] font-semibold {{ $node->status === 'active' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}">
                                                {{ $node->status === 'active' ? 'Aktif' : 'Nonaktif' }}
                                            </span>
                                            @if(count($nodeAssets) > 0)
                                                <span class="font-mono block mt-1 text-[10px] text-sky-700 dark:text-sky-400">
                                                    {{ count($nodeAssets) }} aset Gudang
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-8 text-center text-xs text-slate-500">Belum ada perangkat dalam cakupan ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </main>

        <!-- RIGHT DETAIL PANEL (DRAWER): PANEL TIGA LAPIS (POSISI / KAPASITAS / BARANG GUDANG) -->
        <aside
            x-show="drawerOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
            class="absolute inset-y-0 right-0 z-20 w-full sm:w-[420px] border-l border-slate-200 bg-white p-5 shadow-xl overflow-y-auto custom-scroll flex flex-col gap-5 dark:border-slate-800 dark:bg-[#111920]"
            aria-label="Detail Tiga Lapis">

            <div class="flex items-center justify-between -mt-1 -mb-1">
                <span class="font-display text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Detail Node</span>
                <button type="button" @click="drawerOpen = false" aria-label="Tutup detail"
                        class="inline-flex size-6 items-center justify-center rounded text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"></path><path d="M6 6l12 12"></path></svg>
                </button>
            </div>

            <!-- BLADE SSR FALLBACK / SERVER RENDERING FOR PHPUNIT ASSERTIONS -->
            <div class="sr-only">
                @foreach($nodes as $testNode)
                    <div>
                        <span>{{ $testNode->code }}</span>
                        <span>{{ $testNode->name }}</span>
                        @if($testNode->ports_count > 0)
                            <span>{{ $testNode->used_ports_count }}/{{ $testNode->ports_count }}</span>
                        @endif
                        @foreach($assetsByNode[$testNode->id] ?? [] as $testAsset)
                            <span>{{ $testAsset['reference'] ?? '' }}</span>
                            <span>{{ $testAsset['name'] ?? '' }}</span>
                            <span>{{ $testAsset['technician'] ?? '' }}</span>
                        @endforeach
                    </div>
                @endforeach
            </div>

            <!-- DYNAMIC DETAIL RENDERED BY ALPINE -->
            <template x-if="currentSelectedNode">
                <div class="space-y-5">
                    <!-- HEADER DETAIL -->
                    <div class="border-b border-slate-200 pb-4 dark:border-slate-800">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-1.5">
                                <span class="font-mono px-2 py-0.5 text-[10px] font-bold uppercase rounded border"
                                      :style="`color:${getNodeStyle(currentSelectedNode.type).color}; border-color:${getNodeStyle(currentSelectedNode.type).border}; background:${getNodeStyle(currentSelectedNode.type).bg}`"
                                      x-text="currentSelectedNode.typeName || currentSelectedNode.type">
                                </span>
                                <span class="px-2 py-0.5 text-[10px] font-semibold rounded border"
                                      :class="currentSelectedNode.status === 'active' ? 'bg-emerald-50 text-emerald-700 border-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 border-slate-300'"
                                      x-text="currentSelectedNode.status === 'active' ? 'Aktif' : 'Nonaktif'">
                                </span>
                            </div>

                            <a x-show="currentSelectedNode.show_url" :href="currentSelectedNode.show_url"
                               class="inline-flex items-center gap-1 text-xs font-semibold text-sky-700 hover:text-sky-900 hover:underline dark:text-sky-400">
                                Buka Detail →
                            </a>
                        </div>

                        <h2 class="font-mono mt-2 text-xl font-bold tracking-tight text-slate-900 dark:text-slate-100" x-text="currentSelectedNode.code"></h2>
                        <p class="text-xs text-slate-600 dark:text-slate-300 font-medium" x-text="currentSelectedNode.name"></p>
                        <div class="mt-2 font-mono text-[11px] leading-relaxed text-slate-500 dark:text-slate-400 break-words bg-slate-50 dark:bg-slate-900/60 p-2 rounded border border-slate-200/80 dark:border-slate-800"
                             x-text="currentSelectedNode.path"></div>
                    </div>

                    <template x-if="currentSelectedNode.type === 'customer_ont'">
                        <section class="rounded-lg border border-violet-200 bg-violet-50/60 p-3.5 text-xs dark:border-violet-900 dark:bg-violet-950/20">
                            <p class="font-semibold text-violet-800 dark:text-violet-300">Perangkat dipasang ke pelanggan</p>
                            <p class="mt-1 text-slate-600 dark:text-slate-300">Titik ini hanya menampilkan jalur pelanggan di peta; ONT bukan aset atau Master Node Infrastruktur.</p>
                            <template x-for="(modem, index) in currentSelectedNode.customer_modems" :key="modem.serial_number + index">
                                <p class="mt-2 text-slate-700 dark:text-slate-200">
                                    <span class="font-semibold" x-text="modem.name"></span>
                                    <span class="font-mono" x-text="' · SN ' + modem.serial_number"></span>
                                </p>
                            </template>
                            <p x-show="currentSelectedNode.customer_modems_hidden" class="mt-2 text-slate-500">SN modem dibatasi oleh izin perangkat pelanggan.</p>
                        </section>
                    </template>

                    <!-- LAPIS 1: POSISI -->
                    <section class="rounded-lg border border-slate-200/90 bg-slate-50/50 p-3.5 dark:border-slate-800 dark:bg-slate-900/40">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-display text-[10px] font-bold uppercase tracking-wider text-sky-800 dark:text-sky-400">Lapis 1 · Posisi & Penempatan</span>
                        </div>
                        <dl class="grid grid-cols-[100px_minmax(0,1fr)] gap-y-1.5 text-xs">
                            <dt class="text-slate-500">Cabang:</dt>
                            <dd class="font-medium text-slate-800 dark:text-slate-200" x-text="currentSelectedNode.cabang_name || '—'"></dd>

                            <dt class="text-slate-500">Anchor POP:</dt>
                            <dd class="font-medium text-slate-800 dark:text-slate-200" x-text="currentSelectedNode.anchor_pop_name || 'Tidak ada anchor langsung'"></dd>

                            <template x-if="currentSelectedNode.location_description">
                                <dt class="text-slate-500">Lokasi Fisik:</dt>
                                <dd class="text-slate-700 dark:text-slate-300" x-text="currentSelectedNode.location_description"></dd>
                            </template>

                            <template x-if="currentSelectedNode.latitude && currentSelectedNode.longitude">
                                <dt class="text-slate-500">Koordinat:</dt>
                                <dd class="font-mono text-[11px] text-slate-700 dark:text-slate-300" x-text="`${currentSelectedNode.latitude}, ${currentSelectedNode.longitude}`"></dd>
                            </template>

                            <template x-if="currentSelectedNode.coverage_area">
                                <dt class="text-slate-500">Cakupan:</dt>
                                <dd class="text-slate-700 dark:text-slate-300" x-text="currentSelectedNode.coverage_area"></dd>
                            </template>
                        </dl>
                    </section>

                    <!-- LAPIS 2: KAPASITAS & PETA PORT -->
                    <section class="rounded-lg border border-slate-200/90 bg-white p-3.5 shadow-2xs dark:border-slate-800 dark:bg-[#131d24]">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-display text-[10px] font-bold uppercase tracking-wider text-emerald-800 dark:text-emerald-400">Lapis 2 · Kapasitas & Peta Port</span>
                            <template x-if="currentSelectedNode.ports_count > 0">
                                <span class="font-mono text-xs font-bold"
                                      :class="currentSelectedNode.is_red_zone ? 'text-rose-600 dark:text-rose-400' : 'text-slate-700 dark:text-slate-300'"
                                      x-text="`${currentSelectedNode.used_ports_count}/${currentSelectedNode.ports_count} port (${Math.round(100 * currentSelectedNode.used_ports_count / currentSelectedNode.ports_count)}%)`">
                                </span>
                            </template>
                        </div>

                        <!-- PROGRESS BAR -->
                        <template x-if="currentSelectedNode.ports_count > 0">
                            <div class="mb-3">
                                <div class="h-2 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                    <div class="h-full transition-all"
                                         :style="`width: ${(currentSelectedNode.used_ports_count / currentSelectedNode.ports_count) * 100}%`"
                                         :class="currentSelectedNode.is_red_zone ? 'bg-rose-600' : ((currentSelectedNode.used_ports_count / currentSelectedNode.ports_count) >= 0.7 ? 'bg-amber-500' : 'bg-emerald-500')">
                                    </div>
                                </div>
                                <div class="mt-1 flex justify-between font-mono text-[10px] text-slate-500">
                                    <span>Tersedia: <b x-text="currentSelectedNode.ports_count - currentSelectedNode.used_ports_count"></b> port</span>
                                    <template x-if="currentSelectedNode.is_red_zone">
                                        <span class="font-bold text-rose-600 dark:text-rose-400">ZONA KRITIS / PENUH</span>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <!-- VISUAL PORT GRID -->
                        <template x-if="currentSelectedNode.ports && currentSelectedNode.ports.length > 0">
                            <div>
                                <p class="text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Matriks Port (Klik port untuk melihat penghuni):</p>
                                <div class="grid grid-cols-4 sm:grid-cols-6 lg:grid-cols-4 xl:grid-cols-6 gap-1.5">
                                    <template x-for="port in currentSelectedNode.ports" :key="port.number">
                                        <button
                                            type="button"
                                            @click="selectedPort = port.number"
                                            class="font-mono flex h-8 items-center justify-center rounded border text-xs font-bold transition cursor-pointer"
                                            :class="(() => {
                                                let base = '';
                                                if (port.kind === 'customer') {
                                                    base = 'bg-sky-50 text-sky-800 border-sky-300 dark:bg-sky-950/50 dark:text-sky-300 dark:border-sky-800';
                                                } else if (port.kind === 'node') {
                                                    base = 'bg-emerald-50 text-emerald-800 border-emerald-300 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800';
                                                } else if (port.kind === 'manual') {
                                                    base = 'bg-amber-50 text-amber-800 border-amber-300 dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-800';
                                                } else {
                                                    base = 'bg-white text-slate-400 border-slate-200 dark:bg-slate-800 dark:text-slate-500 dark:border-slate-700';
                                                }
                                                return selectedPort === port.number
                                                    ? base + ' ring-2 ring-slate-900 dark:ring-white scale-105 z-10'
                                                    : base + ' hover:border-slate-400';
                                            })()"
                                            :title="`Port ${port.number}: ${port.label}`">
                                            <span x-text="port.number"></span>
                                        </button>
                                    </template>
                                </div>

                                <!-- PORT COLOR LEGEND -->
                                <div class="mt-2.5 flex flex-wrap gap-x-3 gap-y-1 text-[10px] text-slate-500 dark:text-slate-400">
                                    <span class="inline-flex items-center gap-1"><span class="size-2 rounded-xs bg-sky-400"></span> Pelanggan</span>
                                    <span class="inline-flex items-center gap-1"><span class="size-2 rounded-xs bg-emerald-400"></span> Node Hilir</span>
                                    <span class="inline-flex items-center gap-1"><span class="size-2 rounded-xs bg-amber-400"></span> Manual</span>
                                    <span class="inline-flex items-center gap-1"><span class="size-2 rounded-xs bg-slate-300 dark:bg-slate-600"></span> Kosong</span>
                                </div>

                                <!-- SELECTED PORT DETAIL CARD -->
                                <template x-if="currentSelectedPortData">
                                    <div class="mt-3 rounded border border-slate-200 bg-slate-50 p-2.5 text-xs dark:border-slate-800 dark:bg-slate-900/60">
                                        <div class="flex items-center justify-between">
                                            <span class="font-mono font-bold text-slate-900 dark:text-slate-100" x-text="`Port ${currentSelectedPortData.number}`"></span>
                                            <span class="font-mono text-[10px] px-1.5 py-0.5 rounded font-semibold"
                                                  :class="currentSelectedPortData.is_used ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-400'"
                                                  x-text="currentSelectedPortData.is_used ? 'Terpakai' : 'Kosong'">
                                            </span>
                                        </div>

                                        <div class="mt-1.5 space-y-1">
                                            <p class="font-medium text-slate-800 dark:text-slate-200" x-text="currentSelectedPortData.label"></p>

                                            <template x-if="currentSelectedPortData.customer_cid">
                                                <p class="font-mono text-[11px] text-sky-700 dark:text-sky-400" x-text="`ID Pelanggan: ${currentSelectedPortData.customer_cid}`"></p>
                                            </template>

                                            <template x-if="currentSelectedPortData.kind === 'customer'">
                                                <div class="space-y-1 border-t border-slate-200 pt-2 dark:border-slate-700">
                                                    <template x-for="(modem, index) in currentSelectedPortData.customer_modems" :key="modem.serial_number + index">
                                                        <p class="text-[11px] text-slate-700 dark:text-slate-300">
                                                            <span class="font-semibold" x-text="modem.name"></span>
                                                            <span class="font-mono" x-text="' · SN ' + modem.serial_number"></span>
                                                            <span class="text-slate-500 dark:text-slate-400" x-text="' (' + modem.source + ')'"></span>
                                                        </p>
                                                    </template>
                                                    <p x-show="currentSelectedPortData.customer_modems_hidden" class="text-[11px] text-slate-500 dark:text-slate-400">SN modem dibatasi oleh izin perangkat pelanggan</p>
                                                    <p x-show="!currentSelectedPortData.customer_modems_hidden && !currentSelectedPortData.customer_modems.length" class="text-[11px] text-amber-700 dark:text-amber-400">Modem belum tercatat</p>
                                                </div>
                                            </template>

                                            <template x-if="currentSelectedPortData.target_node_id">
                                                <button type="button" @click="selectNode(currentSelectedPortData.target_node_id)"
                                                        class="mt-1 inline-flex items-center gap-1 rounded border border-sky-300 bg-sky-50 px-2 py-0.5 text-[11px] font-semibold text-sky-800 hover:bg-sky-100 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-300">
                                                    Pilih Node Ini →
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <template x-if="currentSelectedNode.capacity">
                            <p class="mt-3 text-xs text-slate-600 dark:text-slate-300" x-text="currentSelectedNode.capacity.used + ' / ' + (currentSelectedNode.capacity.capacity ?? '?') + ' ' + currentSelectedNode.capacity.unit_label"></p>
                        </template>
                        <template x-if="currentSelectedNode.slots && currentSelectedNode.slots.length">
                            <div class="mt-4 space-y-2 border-t border-slate-200 pt-3 dark:border-slate-800" data-map-device-components>
                                <p class="text-xs font-semibold text-slate-700 dark:text-slate-300">Komponen perangkat: Slot dan PON/Port</p>
                                <template x-for="slot in currentSelectedNode.slots" :key="slot.url">
                                    <details class="rounded-lg border border-slate-200 p-2 dark:border-slate-700" :open="slot.ports.length > 0">
                                        <summary class="cursor-pointer text-xs font-semibold text-slate-700 dark:text-slate-200">
                                            <span x-text="slot.label"></span>
                                            <span class="font-normal text-slate-500" x-text="slot.capacity ? ' · ' + slot.capacity.used + ' / ' + (slot.capacity.capacity ?? '?') + ' ' + slot.capacity.unit_label : ''"></span>
                                        </summary>
                                        <div class="mt-2 space-y-2 border-l-2 border-slate-200 pl-2 dark:border-slate-700">
                                            <a :href="slot.url" class="text-[11px] text-sky-700 hover:underline dark:text-sky-300">Detail Slot / kapasitas</a>
                                            <template x-for="port in slot.ports" :key="port.url">
                                                <div class="text-xs">
                                                    <a :href="port.url" class="font-semibold text-sky-700 hover:underline dark:text-sky-300" x-text="port.label"></a>
                                                    <span class="text-slate-500" x-text="port.capacity ? ' · ' + port.capacity.used + ' / ' + (port.capacity.capacity ?? '?') + ' ' + port.capacity.unit_label : ' · ' + port.status"></span>
                                                    <template x-for="odp in port.odps" :key="odp.url">
                                                        <a :href="odp.url" class="mt-1 block border-l border-slate-200 pl-3 text-[11px] text-slate-600 hover:underline dark:border-slate-700 dark:text-slate-300" x-text="odp.label"></a>
                                                    </template>
                                                </div>
                                            </template>
                                            <p x-show="!slot.ports.length" class="text-[11px] text-slate-500">Belum ada PON/Port didata.</p>
                                        </div>
                                    </details>
                                </template>
                            </div>
                        </template>
                    </section>

                    <!-- LAPIS 3: BARANG GUDANG FISIK (AKTIF & PASIF) -->
                    <section class="rounded-lg border border-slate-200/90 bg-slate-50/50 p-3.5 dark:border-slate-800 dark:bg-slate-900/40">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-display text-[10px] font-bold uppercase tracking-wider text-amber-800 dark:text-amber-400">Lapis 3 · Barang Gudang Fisik</span>
                            <span class="font-mono text-xs font-semibold text-slate-500" x-text="`${currentSelectedNode.assets?.length || 0} barang`"></span>
                        </div>

                        <template x-if="currentSelectedNode.assets && currentSelectedNode.assets.length > 0">
                            <div class="space-y-2">
                                <template x-for="(asset, idx) in currentSelectedNode.assets" :key="idx">
                                    <div class="rounded border border-slate-200 bg-white p-2.5 text-xs shadow-2xs dark:border-slate-800 dark:bg-[#152126]">
                                        <div class="flex items-start justify-between gap-2">
                                            <span class="font-semibold text-slate-900 dark:text-slate-100" x-text="asset.name"></span>
                                            <span class="font-mono px-1.5 py-0.5 rounded text-[9px] font-semibold"
                                                  :class="asset.kind === 'SN' ? 'bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300'"
                                                  x-text="asset.kind">
                                            </span>
                                        </div>

                                        <template x-if="asset.reference">
                                            <p class="font-mono mt-1 text-[11px] font-bold text-sky-700 dark:text-sky-400" x-text="asset.reference"></p>
                                        </template>

                                        <p class="mt-0.5 font-mono text-[11px] text-slate-500" x-text="asset.amount"></p>

                                        <div class="mt-1.5 border-t border-slate-100 pt-1.5 text-[10px] text-slate-500 dark:border-slate-800 space-y-0.5">
                                            <p x-text="`Pasang: ${asset.installed_at || 'Belum ada tanggal'}`"></p>
                                            <template x-if="asset.technician">
                                                <p x-text="`Teknisi: ${asset.technician}`"></p>
                                            </template>
                                            <template x-if="asset.task">
                                                <p x-text="`Task FOP: ${asset.task}`"></p>
                                            </template>
                                        </div>

                                        <template x-if="asset.trace_url">
                                            <a :href="asset.trace_url"
                                               class="mt-2 inline-flex items-center gap-1 font-semibold text-sky-700 hover:underline dark:text-sky-400 text-[11px]">
                                                Riwayat Gudang →
                                            </a>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <template x-if="!currentSelectedNode.assets || currentSelectedNode.assets.length === 0">
                            <p class="rounded border border-dashed border-slate-300 p-3 text-center text-xs text-slate-400 dark:border-slate-800">
                                Belum ada aset Gudang yang tercatat terpasang pada titik ini.
                            </p>
                        </template>
                    </section>
                </div>
            </template>

            <!-- EMPTY STATE WHEN NO NODE SELECTED -->
            <template x-if="!currentSelectedNode">
                <div class="flex h-64 items-center justify-center text-center p-6 text-xs text-slate-400">
                    Pilih titik jaringan di peta atau inventaris untuk memeriksa detail tiga lapis.
                </div>
            </template>
        </aside>
    </div>
</div>
@endsection
