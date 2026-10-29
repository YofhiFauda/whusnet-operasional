@extends('layouts.app')

@section('title', 'Peta Infrastruktur ISP')
@section('page_title', 'Peta Infrastruktur ISP')

@section('content')
{{-- Font IBM Plex dimuat async (bukan @import di <style>, yang memblokir render dan
     membuat rantai permintaan CSS → font). Teks tampil dulu dengan font cadangan. --}}
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans+Condensed:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" media="print" onload="this.media='all'">
<style>
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

@php
    // Tab Pohon & Inventaris dirender server-side HANYA bila sedang dibuka: di
    // jaringan besar tiap tab memuat ribuan baris/kartu, dan tab yang
    // tersembunyi (x-show) tetap ikut dirender + dikirim ke browser. Pindah ke
    // tab itu = navigasi biasa ke URL berikut (dirender server, bukan dirakit klien).
    // Tab "Barang Terpasang" TIDAK LEWAT view ini sama sekali — lihat
    // `network.map.terpasang` + komentar di InfrastructureMapController::index().
    $activeTab = isset($initialNodeId) ? 'inventaris' : (in_array(request('tab'), ['inventaris', 'pohon'], true) ? request('tab') : 'topologi');
    $tabUrl = fn (string $tab): string => route('network.map.index', array_filter(['pop_id' => $selectedPopId, 'sort' => $sort, 'ont' => $withCustomerOnt ? 1 : null, 'tab' => $tab]));
    $openAllUrl = route('network.map.index', array_filter(['pop_id' => $selectedPopId, 'sort' => $sort, 'ont' => 1, 'expand' => 'all']));
@endphp
<script type="application/json" id="peta-data">{!! json_encode([
    'index' => $topologyIndex,
    'nodes' => $topologyDetails,
    'hierarchyItems' => collect($hierarchy['items'])->map(fn ($item) => collect($item)->only(['label', 'name', 'type', 'parent'])),
    'hierarchyChildren' => $hierarchy['children'],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) !!}</script>
<script>window.__petaData = JSON.parse(document.getElementById('peta-data').textContent);</script>
<div
    class="isp-infra relative flex h-full w-full flex-col bg-[#f8fafc] text-slate-900 dark:bg-[#0c1319] dark:text-slate-100 overflow-hidden"
    x-data="{
        tab: @js($activeTab),
        serverTab: @js($activeTab),
        withOnt: @js($ontActive ?? false),
        ontRequested: @js($withCustomerOnt),
        topologiUrl: @js($tabUrl('topologi')),
        startExpanded: @js(request('expand') === 'all' || ($ontActive ?? false)),
        openAllUrl: @js($openAllUrl),
        selectedNodeId: @js($initialNodeId ?? ($firstPopId !== null ? -$firstPopId : $firstNodeId)),
        selectedPort: null,
        drawerOpen: @js(isset($initialNodeId) ? true : false),
        statsOpen: false,
        query: '',
        typeFilter: 'all',
        depthFilter: 'all',
        sort: @js($sort),
        // Data besar berasal dari <script type=application/json> di atas — bukan
        // literal di atribut x-data. Di atribut, tiap tanda kutip jadi &quot; (HTML
        // membengkak) dan Alpine harus mengurai literal multi-MB lewat new Function.
        // `index` dibekukan: objek beku tidak dibungkus proxy reaktif, padahal
        // ribuan entrinya hanya dibaca (pencarian), tidak pernah diubah.
        index: Object.freeze(window.__petaData.index),
        nodes: window.__petaData.nodes,
        hierarchyItems: window.__petaData.hierarchyItems,
        hierarchyChildren: window.__petaData.hierarchyChildren,
        visibleIds: {},
        collapsedIds: new Set(),
        kidsOf: {},
        lazyHint: '',
        ontProgress: '',
        ontLoading: false,
        switchingTab: false,
        // Di bawah zoom ini kartu ONT disembunyikan (ikhtisar sampai ODP).
        ontMinZoom: 0.3,
        searchUrl: @js(route('network.map.search')),
        searchResults: [],
        searchBusy: false,
        // Kata cari tidak cocok dengan satu pun node di indeks (mis. nama/CID/SN pelanggan):
        // kanvas dibiarkan utuh, hasilnya ada di daftar dari server.
        localMiss: false,
        searchSeq: 0,
        layout: null,
        renderIds: [],
        cullFrame: null,
        zoom: 1.0,
        panX: 40,
        panY: 40,
        isPanning: false,
        panStart: null,
        panMoved: false,

        init() {
            // Jaringan besar (ribuan ODP): kanvas dimulai dengan PON terlipat —
            // merender semua kartu ODP sekaligus membuat halaman berat dibuka.
            // Klik tombol lipat di kartu PON untuk membuka ODP-nya; mencari
            // tetap menembus lipatan.
            if (!this.startExpanded && (Object.keys(this.nodes).length > 400 || Object.values(this.nodes).some(n => n.lazy_url))) {
                for (const [id, n] of Object.entries(this.nodes)) {
                    // Slot & PON terlipat: awalnya cuma POP → OLT → Slot yang menjadi kartu (puluhan),
                    // bukan ratusan PON. Setiap kartu membawa belasan binding Alpine, jadi jumlah
                    // kartu awal menentukan lama inisialisasi (TBT).
                    if (['olt_slot', 'switch_slot', 'olt_pon'].includes(n.type) || n.lazy_url) this.collapsedIds.add(+id);
                }
                // Node terpilih (deep link) tidak boleh tersembunyi di bawah lipatan.
                let cursor = this.selectedNodeId ? this.nodes[this.selectedNodeId]?.parent_id : null;
                while (cursor && this.nodes[cursor]) {
                    this.collapsedIds.delete(+cursor);
                    cursor = this.nodes[cursor].parent_id;
                }
                this.depthFilter = 'custom';
            }
            // Hanya kartu yang masuk viewport yang dirender (lihat cullVisible).
            // Tanpa ini Buka Semua di cabang besar membuat ±13 ribu kartu SVG
            // berisi belasan binding Alpine masing-masing — GB memori di browser.
            for (const key of ['layout', 'panX', 'panY', 'zoom']) {
                this.$watch(key, () => this.scheduleCull());
            }
            // Tab Pohon/Inventaris: kanvas tersembunyi, tata letaknya baru disusun saat
            // tab Topologi dibuka (goTab) — bukan di setiap pemuatan halaman.
            if (this.tab === 'topologi') this.recompute();
            this.$nextTick(() => {
                if (this.tab === 'topologi') this.zoomFit();
                this.scheduleCull();
            });
            if (this.withOnt && this.tab === 'topologi') this.loadAllOnt();
            if (this.selectedNodeId && this.nodes[this.selectedNodeId]) {
                const firstPort = this.nodes[this.selectedNodeId].ports?.[0];
                if (firstPort) this.selectedPort = firstPort.number;
                this.loadPorts(this.selectedNodeId);
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
            this.localMiss = q !== '' && this.typeFilter === 'all' && Object.keys(visible).length === 0;
            this.layout = this.computeLayout();
            this.loadSearchMatches();
        },

        // Indeks pencarian memuat SEMUA node, tetapi kartu ODP baru ada setelah
        // PON-nya dimuat. Saat mencari, muat PON yang berisi hasil — dibatasi
        // supaya pencarian lebar (misalnya kata odp) tidak menarik ribuan kartu di jaringan lemah.
        async loadSearchMatches() {
            this.lazyHint = '';
            if (this.query.trim() === '' && this.typeFilter === 'all') return;
            const pending = new Set();
            for (const id of Object.keys(this.visibleIds)) {
                if (this.nodes[id]) continue;
                const parent = this.index[id]?.parent;
                if (parent && this.nodes[parent]?.lazy_url && !this.nodes[parent].lazy_loaded) pending.add(parent);
            }
            if (pending.size === 0) return;
            if (pending.size > 12) {
                this.lazyHint = `Hasil ada di ${pending.size} induk (PON/ODP) — persempit pencarian agar kartunya dimuat.`;
                return;
            }
            await Promise.all([...pending].map(pid => this.loadChildren(pid)));
            this.layout = this.computeLayout();
        },

        visibleNode(id) {
            return (!this.query.trim() && this.typeFilter === 'all') || this.localMiss ? true : !!this.visibleIds[id];
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
            this.pickDefaultPort(id);
            this.loadPorts(id);
            this.loadOntModems(id);
        },

        // Kartu ONT hanya membawa identitas pelanggan; SN modemnya diambil dari
        // daftar port node induk (URL dari server) saat kartu dipilih.
        async loadOntModems(id) {
            const node = this.nodes[id];
            if (!node || node.type !== 'customer_ont' || node.modems_loaded || !node.modems_url) return;
            node.modems_loaded = true;
            try {
                const response = await fetch(node.modems_url, { headers: { 'Accept': 'application/json' } });
                if (!response.ok) throw new Error(response.status);
                const port = (await response.json()).ports.find(p => p.customer_cid === node.code);
                node.customer_modems = port?.customer_modems || [];
                node.customer_modems_hidden = port?.customer_modems_hidden ?? node.customer_modems_hidden;
                node.typeName = node.customer_modems.length ? 'ONT dipasang ke pelanggan' : 'ONT belum tercatat';
            } catch (e) {
                node.modems_loaded = false;
            }
        },

        // Pindah tab di halaman yang sama tanpa permintaan server. Topologi dari
        // halaman Pohon/Inventaris dimuat ulang bila pengguna meminta kartu ONT
        // (data ONT tidak dibangun untuk tab lain).
        goTab(name, url) {
            // View ini (`network.map.index`) cuma nanganin Topologi/Pohon/Inventaris —
            // Barang Terpasang pindah view terpisah (`network.map.terpasang`, tanpa Alpine
            // sama sekali, lihat InfrastructureMapController::index()). Pindah KE tab
            // manapun SELAIN tab yang sedang aktif = reload server biasa. Klik ulang tab
            // yang sedang aktif tetap instan (klien saja, tanpa reload).
            if (name === this.serverTab) {
                this.tab = name;
                if (name === 'topologi') {
                    if (!this.layout) this.recompute();
                    this.$nextTick(() => {
                        this.zoomFit();
                        this.scheduleCull();
                    });
                }
                return;
            }
            // Tab ini dibangun server (beberapa detik): beri tahu pengguna segera, jangan membeku.
            this.switchingTab = true;
            window.location.href = url || this.topologiUrl;
        },

        pickDefaultPort(id) {
            if (this.nodes[id]?.ports?.length) {
                const activeP = this.nodes[id].ports.find(p => p.is_used);
                this.selectedPort = activeP ? activeP.number : this.nodes[id].ports[0].number;
            }
        },

        // Daftar port tidak ikut payload awal (15k pelanggan → OOM); diambil dari
        // URL yang diberikan server saat node diklik, sekali per node.
        async loadPorts(id) {
            const node = this.nodes[id];
            if (!node || node.ports_loaded || !node.ports_url) return;
            node.ports_loaded = true;
            try {
                const response = await fetch(node.ports_url, { headers: { 'Accept': 'application/json' } });
                if (!response.ok) throw new Error(response.status);
                node.ports = (await response.json()).ports;
                if (this.selectedNodeId === id) this.pickDefaultPort(id);
            } catch (e) {
                node.ports_loaded = false;
            }
        },

        // ODP tidak ikut payload awal; detailnya diambil dari URL yang diberikan
        // server saat PON-nya dibuka. Aman dipanggil berulang (sekali per PON).
        async loadChildren(id) {
            const node = this.nodes[id];
            if (!node || !node.lazy_url || node.lazy_loaded || node.lazy_loading) return;
            node.lazy_loading = true;
            try {
                const response = await fetch(node.lazy_url, { headers: { 'Accept': 'application/json' } });
                if (!response.ok) throw new Error(response.status);
                const { nodes } = await response.json();
                for (const [cid, detail] of Object.entries(nodes)) this.nodes[cid] = detail;
                node.lazy_loaded = true;
            } catch (e) {
                node.lazy_error = true;
            } finally {
                node.lazy_loading = false;
            }
        },

        // Mode ONT: kartu ONT tidak ikut halaman awal. Dimuat per Slot (puluhan
        // permintaan, 3 sekaligus), lalu sisa induk yang tidak punya Slot satu per
        // satu. Tata letak disusun ulang tiap batch selesai, jadi kartu muncul
        // bertahap dan kanvas tetap bisa digeser selama memuat.
        async loadAllOnt() {
            if (this.ontLoading) return;
            this.ontLoading = true;
            let failed = false;
            const run = async (queue, total, load) => {
                while (queue.length) {
                    const node = queue.shift();
                    if (!(await load(node))) failed = true;
                    this.ontProgress = `Memuat ONT pelanggan… ${total - queue.length}/${total}`;
                    this.layout = this.computeLayout();
                }
            };
            const batches = Object.values(this.nodes).filter(n => n.ont_batch_url && !n.ont_batch_loaded);
            await Promise.all([1, 2, 3].map(() => run(batches, batches.length, n => this.loadOntBatch(n))));
            const rest = Object.values(this.nodes).filter(n => n.lazy_url && !n.lazy_loaded);
            await Promise.all([1, 2, 3].map(() => run(rest, rest.length, async n => {
                await this.loadChildren(n.id);
                return !!n.lazy_loaded;
            })));
            this.ontProgress = failed ? 'Sebagian ONT gagal dimuat — muat ulang halaman untuk mencoba lagi.' : '';
            this.ontLoading = false;
            this.layout = this.computeLayout();
        },

        async loadOntBatch(slot) {
            try {
                const response = await fetch(slot.ont_batch_url, { headers: { 'Accept': 'application/json' } });
                if (!response.ok) throw new Error(response.status);
                const { nodes } = await response.json();
                for (const [cid, detail] of Object.entries(nodes)) {
                    this.nodes[cid] = detail;
                    const parent = this.nodes[detail.parent_id];
                    if (parent) parent.lazy_loaded = true;
                }
                slot.ont_batch_loaded = true;
                return true;
            } catch (e) {
                return false;
            }
        },

        // Indeks pencarian di halaman tidak memuat SN dan, di mode ONT sembunyi,
        // tidak memuat pelanggan sama sekali. Nama / CID / SN dicari di server
        // (scope POP dan izin SN dihormati di sana), maks. 20 hasil.
        async searchCustomers() {
            const term = this.query.trim();
            const seq = ++this.searchSeq;
            if (term.length < 3) {
                this.searchResults = [];
                return;
            }
            this.searchBusy = true;
            try {
                const url = new URL(this.searchUrl, window.location.origin);
                url.searchParams.set('q', term);
                const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (!response.ok) throw new Error(response.status);
                const { results } = await response.json();
                if (seq === this.searchSeq) this.searchResults = results;
            } catch (e) {
                if (seq === this.searchSeq) this.searchResults = [];
            } finally {
                if (seq === this.searchSeq) this.searchBusy = false;
            }
        },

        clearSearch() {
            this.query = '';
            this.searchResults = [];
            this.searchSeq++;
            this.recompute();
        },

        // Tampilkan ONT hasil cari langsung di kanvas ini — tanpa memuat ulang
        // halaman dan tanpa memuat ribuan ONT lain: hanya satu kartu (+ ODP
        // induknya bila belum dimuat) yang diambil dari server.
        async openSearchResult(result) {
            if (this.nodes[result.ont_id]) {
                this.focusOnt(result.ont_id);
                return;
            }
            this.searchBusy = true;
            try {
                const response = await fetch(result.detail_url, { headers: { 'Accept': 'application/json' } });
                if (!response.ok) throw new Error(response.status);
                const { node, parent_id } = await response.json();
                await this.ensureLoaded(parent_id);
                if (!this.nodes[parent_id]) throw new Error('induk tidak termuat');
                this.nodes[node.id] = node;
                this.focusOnt(node.id);
            } catch (e) {
                this.lazyHint = 'Kartu pelanggan tidak bisa ditampilkan. Coba lagi.';
            } finally {
                this.searchBusy = false;
            }
        },

        // Muat anak-anak lazy dari leluhur terdekat yang sudah ada sampai node `id`
        // tersedia (jalur induk dibaca dari indeks, yang memuat semua node).
        async ensureLoaded(id) {
            for (let step = 0; step < 6 && !this.nodes[id]; step++) {
                let cursor = id;
                while (cursor != null && !this.nodes[cursor]) cursor = this.index[cursor]?.parent;
                const holder = cursor != null ? this.nodes[cursor] : null;
                if (!holder || !holder.lazy_url || holder.lazy_loaded) return;
                await this.loadChildren(cursor);
            }
        },

        // Buka lipatan induknya, pilih kartu, lalu geser kanvas ke sana.
        focusOnt(id) {
            if (!this.nodes[id]) return false;
            let cursor = this.nodes[id].parent_id;
            for (let hops = 0; cursor != null && this.nodes[cursor] && hops < 50; hops++) {
                this.collapsedIds.delete(+cursor);
                cursor = this.nodes[cursor].parent_id;
            }
            this.query = '';
            this.searchResults = [];
            this.searchSeq++;
            this.recompute();
            this.selectNode(id);
            this.$nextTick(() => this.centerOn(id));
            return true;
        },

        centerOn(id) {
            const pos = this.layout?.pos[id];
            const canvas = this.$refs.topologyCanvas;
            if (!pos || !canvas) return;
            this.zoom = 1;
            this.panX = canvas.clientWidth / 2 - (pos.x + this.layout.cardW / 2);
            this.panY = canvas.clientHeight / 2 - (pos.y + this.layout.cardH / 2);
        },

        hasKids(id) {
            return (this.kidsOf[id]?.length || 0) > 0 || (this.nodes[id]?.lazy_children_count || 0) > 0;
        },

        async toggleCollapse(id) {
            if (this.collapsedIds.has(id) && this.nodes[id]?.lazy_url && !this.nodes[id].lazy_loaded) {
                await this.loadChildren(id);
            }
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

        // Buka Semua = sampai ONT pelanggan. Kartu ONT tidak ikut payload biasa
        // (ribuan pelanggan), jadi dari mode biasa halaman dimuat ulang dalam mode
        // ONT (URL dari server) dengan semua lipatan terbuka. Di mode ONT semua
        // data sudah ada, cukup buka lipatannya.
        async expandAll() {
            if (!this.withOnt) {
                if (confirm('Buka semua sampai ONT akan memuat seluruh pelanggan di jaringan ini (bisa ribuan kartu) dan lambat di perangkat/jaringan lemah. Lanjutkan?')) {
                    window.location.href = this.openAllUrl;
                }
                return;
            }
            await this.loadAllOnt();
            this.collapsedIds.clear();
            this.depthFilter = 'custom';
            this.layout = this.computeLayout();
            this.$nextTick(() => this.zoomFit());
        },

        // Tutup Semua = hanya sampai Slot: semua Slot dilipat, jadi PON, ODP,
        // dan ONT di bawahnya tersembunyi.
        collapseToSlots() {
            this.collapsedIds.clear();
            for (const [id, n] of Object.entries(this.nodes)) {
                if (['olt_slot', 'switch_slot'].includes(n.type)) this.collapsedIds.add(+id);
            }
            this.depthFilter = 'custom';
            this.layout = this.computeLayout();
            this.$nextTick(() => this.zoomFit());
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
            const nextZoom = Math.max(0.1, Math.min(2, this.zoom * factor));
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

        scheduleCull() {
            if (this.cullFrame) return;
            this.cullFrame = requestAnimationFrame(() => {
                this.cullFrame = null;
                this.cullVisible();
            });
        },

        // Pilih kartu yang beririsan dengan viewport (+ margin satu layar
        // supaya geser pelan tidak berkedip). Layout beku, jadi menyisirnya
        // murah; yang mahal itu DOM-nya, dan itu yang dibatasi di sini.
        cullVisible() {
            const layout = this.layout;
            const canvas = this.$refs.topologyCanvas;
            if (!layout) return;
            const w = canvas?.clientWidth || 1200;
            const h = canvas?.clientHeight || 800;
            const z = this.zoom || 1;
            const x0 = -this.panX / z - w / z;
            const x1 = (w - this.panX) / z + w / z;
            const y0 = -this.panY / z - h / z;
            const y1 = (h - this.panY) / z + h / z;
            const hideOnt = z < this.ontMinZoom;
            const ids = [];
            for (const id of layout.order) {
                if (hideOnt && this.index[id]?.type === 'customer_ont') continue;
                const p = layout.pos[id];
                if (p.x + layout.cardW + 12 >= x0 && p.x <= x1 && p.y + layout.cardH >= y0 && p.y <= y1) ids.push(id);
            }
            const prev = this.renderIds;
            if (prev.length === ids.length && prev.every((v, i) => v === ids[i])) return;
            this.renderIds = ids;
        },

        zoomFit() {
            const canvas = this.$refs.topologyCanvas;
            if (!canvas || !this.layout?.order.length) return;
            const positions = this.layout.order.map(id => this.layout.pos[id]);
            const width = Math.max(...positions.map(pos => pos.x)) + this.layout.cardW + 12;
            const height = Math.max(...positions.map(pos => pos.y)) + this.layout.cardH;
            this.zoom = Math.max(0.1, Math.min(1.1, (canvas.clientWidth - 40) / width, (canvas.clientHeight - 40) / height));
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
            let maxX = 0;
            // ONT satu induk disusun dalam blok kolom (bukan satu baris per ONT):
            // tinggi kanvas ±3x lebih pendek di cabang besar.
            const BLOCK_COLS = 6;
            const BLOCK_GAP = 16;

            // Peta anak dibangun SEKALI per layout (O(N)). Dulu getKids menyisir
            // seluruh node untuk tiap node → O(N²): 3.500 node = 12 juta iterasi
            // di setiap collapse/cari/ganti kedalaman.
            // `raw`: menyisir ribuan node lewat proxy reaktif membuat satu proxy
            // per node (memori + waktu) padahal di sini hanya dibaca.
            const nodes = window.Alpine?.raw ? window.Alpine.raw(this.nodes) : this.nodes;
            const kidsOf = {};
            for (const [cid, n] of Object.entries(nodes)) {
                if (n.parent_id != null) (kidsOf[n.parent_id] ||= []).push(+cid);
            }
            this.kidsOf = kidsOf;
            const filtering = (this.query.trim() !== '' || this.typeFilter !== 'all') && !this.localMiss;

            const getKids = (id) => {
                // Saat mencari/memfilter, lipatan diabaikan supaya hasil di dalam
                // node yang dilipat (mis. ODP di bawah PON) tetap muncul.
                if (this.collapsedIds.has(+id) && !filtering) return [];
                return (kidsOf[id] || []).filter(cid =>
                    this.visibleNode(cid) && (this.depthFilter !== 'pop' || nodes[cid].type?.startsWith('pop_')));
            };

            const walk = (id, depth) => {
                order.push(id);
                maxDepth = Math.max(maxDepth, depth);
                const kids = getKids(id);
                if (kids.length > 1 && kids.every(k => nodes[k].type === 'customer_ont')) {
                    const cols = Math.min(BLOCK_COLS, kids.length);
                    const rows = Math.ceil(kids.length / cols);
                    maxDepth = Math.max(maxDepth, depth + 1);
                    kids.forEach((k, i) => {
                        order.push(k);
                        edges.push({ from: id, to: k });
                        const x = (depth + 1) * COL_W + (i % cols) * (CARD_W + 12 + BLOCK_GAP);
                        maxX = Math.max(maxX, x);
                        pos[k] = { x, y: (row + Math.floor(i / cols)) * ROW_H, depth: depth + 1 };
                    });
                    pos[id] = { x: depth * COL_W, y: (row * ROW_H + (row + rows - 1) * ROW_H) / 2, depth };
                    row += rows;
                    return;
                }
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

            const roots = Object.values(nodes)
                .filter(n => (!n.parent_id || !nodes[n.parent_id]) && this.visibleNode(n.id))
                .map(n => n.id);

            roots.forEach(r => walk(r, 0));

            // Beku: layout hanya diganti utuh, tidak pernah diubah sebagian. Objek
            // beku tidak dibungkus proxy, jadi ribuan `pos` tidak jadi ribuan proxy.
            return Object.freeze({
                pos,
                edges,
                order,
                width: Math.max(860, maxDepth * COL_W + CARD_W + 100, maxX + CARD_W + 100),
                height: Math.max(520, row * ROW_H + 100),
                cardW: CARD_W,
                cardH: CARD_H
            });
        },

        edgePath(layout, from, to, stroke, strokeWidth) {
            const x1 = from.x + layout.cardW + 12;
            const y1 = from.y + layout.cardH / 2;
            const x2 = to.x;
            const y2 = to.y + layout.cardH / 2;
            const midX = x1 + (x2 - x1) / 2;
            return `<path d='M ${x1},${y1} C ${midX},${y1} ${midX},${y2} ${x2},${y2}' fill='none' stroke='${stroke}' stroke-width='${strokeWidth}' />`;
        },

        // Jalur induk → kartu terpilih digambar di lapisan sendiri (beberapa
        // garis saja), jadi memilih kartu tidak membangun ulang ribuan garis dasar.
        activePathSvg(layout) {
            const parts = [];
            let current = this.selectedNodeId;
            for (let hops = 0; current != null && this.nodes[current] && hops < 50; hops++) {
                const parent = this.nodes[current].parent_id;
                const from = layout.pos[parent];
                const to = layout.pos[current];
                if (from && to) parts.push(this.edgePath(layout, from, to, '#0284c7', 2.2));
                current = parent;
            }
            return parts.join('');
        },

        // Hanya garis yang menyentuh kartu yang sedang dirender (renderIds);
        // tidak bergantung pada kartu terpilih.
        edgePaths(layout) {
            const shown = new Set(this.renderIds);
            const hideOnt = this.zoom < this.ontMinZoom;
            return layout.edges.filter(edge => (shown.has(edge.from) || shown.has(edge.to))
                && !(hideOnt && this.index[edge.to]?.type === 'customer_ont')).map(edge => {
                const from = layout.pos[edge.from];
                const to = layout.pos[edge.to];
                if (!from || !to) return '';
                return this.edgePath(layout, from, to, 'currentColor', 1.6);
        }).join('');
    }
}">

    <div x-show="switchingTab" x-cloak class="absolute inset-x-0 top-0 z-50 h-0.5 overflow-hidden bg-sky-100 dark:bg-sky-950" role="progressbar" aria-label="Memuat tab">
        <div class="h-full w-1/3 animate-pulse bg-sky-600"></div>
    </div>

    <!-- FLOATING TOP NAVIGATION ISLAND (TABS, CABANG, SEARCH, ONT) -->
    <nav aria-label="Navigasi Peta" class="absolute top-3 left-3 z-30 flex flex-wrap items-center gap-2 pointer-events-auto max-w-[calc(100vw-5rem)] sm:max-w-[calc(100vw-18rem)]">
        <!-- BRAND & TITLE PILL -->
        <div class="flex items-center gap-2 rounded-xl border border-slate-200/80 bg-white/95 px-3 py-1.5 shadow-md backdrop-blur-md dark:border-slate-800 dark:bg-[#131d24]/95">
            <span class="inline-flex size-2 rounded-full bg-emerald-500 animate-pulse"></span>
            <div class="leading-none">
                <h1 class="font-display text-xs font-bold uppercase tracking-wider text-slate-900 dark:text-slate-100">Peta Infrastruktur ISP</h1>
            </div>
        </div>

        <!-- VIEW MODE TABS (FLOATING BUTTONS) -->
        <div role="tablist" aria-label="Tampilan Utama" class="flex items-center rounded-xl border border-slate-200/80 bg-white/95 p-1 shadow-md backdrop-blur-md dark:border-slate-800 dark:bg-[#131d24]/95">
            <button
                type="button"
                role="tab"
                @click="goTab('topologi', topologiUrl)"
                :aria-selected="tab === 'topologi'"
                :class="tab === 'topologi' ? 'bg-sky-600 text-white shadow-xs font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-300 dark:hover:text-white dark:hover:bg-slate-800'"
                class="font-display inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs transition cursor-pointer">
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>
                Topologi
            </button>

            <button
                type="button"
                role="tab"
                @click="goTab('pohon', @js($tabUrl('pohon')))"
                :aria-selected="tab === 'pohon'"
                :class="tab === 'pohon' ? 'bg-sky-600 text-white shadow-xs font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-300 dark:hover:text-white dark:hover:bg-slate-800'"
                class="font-display inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs transition cursor-pointer">
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12H3"></path><path d="M12 3v18"></path><path d="M18 6l-6-3-6 3"></path></svg>
                Per POP (Pohon)
            </button>

            <button
                type="button"
                role="tab"
                @click="goTab('inventaris', @js($tabUrl('inventaris')))"
                :aria-selected="tab === 'inventaris'"
                :class="tab === 'inventaris' ? 'bg-sky-600 text-white shadow-xs font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-300 dark:hover:text-white dark:hover:bg-slate-800'"
                class="font-display inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs transition cursor-pointer">
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                Inventaris
            </button>

            <a href="{{ $tabUrl('terpasang') }}" role="tab" aria-selected="false"
               class="font-display inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white dark:hover:bg-slate-800">
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7 12 3 4 7l8 4 8-4Z"></path><path d="M4 7v10l8 4 8-4V7"></path><path d="M12 11v10"></path></svg>
                Barang Terpasang
            </a>
        </div>

        <!-- CABANG & ONT FILTER (FLOATING SELECT & BUTTON) -->
        <form method="GET" action="{{ route('network.map.index') }}" class="flex items-center gap-1.5 rounded-xl border border-slate-200/80 bg-white/95 p-1 shadow-md backdrop-blur-md dark:border-slate-800 dark:bg-[#131d24]/95">
            <input type="hidden" name="tab" x-model="tab">
            <input type="hidden" name="sort" value="{{ $sort }}">
            @if($withCustomerOnt)<input type="hidden" name="ont" value="1">@endif
            <a href="{{ route('network.map.index', array_filter(['pop_id' => $selectedPopId, 'sort' => $sort, 'ont' => $withCustomerOnt ? null : 1])) }}"
               title="{{ $withCustomerOnt ? 'Sembunyikan kartu ONT pelanggan' : 'Tampilkan kartu ONT pelanggan di kanvas' }}"
               class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-semibold transition {{ $withCustomerOnt ? 'bg-violet-100 text-violet-800 dark:bg-violet-950/70 dark:text-violet-300' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                <span class="size-1.5 rounded-full {{ $withCustomerOnt ? 'bg-violet-600 dark:bg-violet-400' : 'bg-slate-400' }}"></span>
                <span>ONT: {{ $withCustomerOnt ? 'tampil' : 'sembunyi' }}</span>
            </a>
            <span class="h-4 w-px bg-slate-200 dark:bg-slate-700"></span>
            <label for="map-pop" class="sr-only">Cabang</label>
            <div class="relative flex items-center">
                <svg class="pointer-events-none absolute left-2 size-3.5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                <select id="map-pop" name="pop_id" onchange="this.form.submit()"
                        class="rounded-lg border-0 bg-transparent py-1 pl-7 pr-7 text-xs font-semibold text-slate-800 focus:ring-2 focus:ring-sky-500 focus:outline-none dark:text-slate-200 cursor-pointer">
                    <option value="">Semua Cabang</option>
                    @foreach($cabangs as $cabang)
                        <option value="{{ $cabang->id }}" @selected($selectedPopId === $cabang->id)>{{ $cabang->name }}</option>
                    @endforeach
                </select>
            </div>
        </form>

        <!-- FLOATING SEARCH -->
        <div class="relative flex items-center rounded-xl border border-slate-200/80 bg-white/95 shadow-md backdrop-blur-md dark:border-slate-800 dark:bg-[#131d24]/95">
            <svg class="pointer-events-none absolute left-3 size-3.5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.35-4.35"></path></svg>
            <input
                type="search"
                x-model="query"
                @input.debounce.150ms="recompute()"
                @input.debounce.400ms="searchCustomers()"
                placeholder="Cari perangkat, pelanggan, CID, SN..."
                class="w-32 sm:w-44 focus:w-60 transition-all duration-200 rounded-xl border-0 bg-transparent py-1.5 pl-8 pr-3 text-xs text-slate-900 placeholder:text-slate-400 focus:ring-2 focus:ring-sky-500 focus:outline-none dark:text-slate-100">
            <button x-show="query" @click="clearSearch()" type="button" class="pr-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-xs cursor-pointer">✕</button>
            <div x-show="query.trim().length >= 3 && (searchBusy || searchResults.length)" x-cloak
                 class="absolute left-0 top-full z-40 mt-1 max-h-72 w-80 overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-xl dark:border-slate-700 dark:bg-[#131d24]">
                <p x-show="searchBusy" class="px-3 py-2 text-[11px] text-slate-500">Mencari pelanggan…</p>
                <template x-for="result in searchResults" :key="result.ont_id">
                    <button type="button" @click="openSearchResult(result)" class="flex w-full flex-col rounded-lg px-3 py-1.5 text-left text-xs hover:bg-sky-50 dark:hover:bg-slate-800">
                        <span class="font-semibold text-slate-900 dark:text-slate-100" x-text="result.name"></span>
                        <span class="font-mono text-[11px] text-slate-500" x-text="result.cid + ' · ' + result.node_code + ' port ' + result.port + ' · cocok ' + result.match"></span>
                    </button>
                </template>
            </div>
        </div>
    </nav>

    <!-- FLOATING TOP-RIGHT ACTIONS & METRICS HUD -->
    <div class="absolute top-3 right-3 z-30 flex items-center gap-2 pointer-events-auto" @click.outside="statsOpen = false">
        <!-- METRICS HUD BUTTON & DROPDOWN -->
        <div class="relative">
            <button
                type="button"
                @click="statsOpen = !statsOpen"
                class="flex items-center gap-2 rounded-xl border border-slate-200/80 bg-white/95 px-3 py-1.5 text-xs font-semibold shadow-md backdrop-blur-md transition hover:border-sky-500 hover:text-sky-700 dark:border-slate-800 dark:bg-[#131d24]/95 dark:text-slate-200 dark:hover:border-sky-400 cursor-pointer"
                :class="statsOpen ? 'ring-2 ring-sky-500/30 border-sky-500' : ''">
                <span class="font-mono text-slate-700 dark:text-slate-300">{{ $summary['nodes'] }} <span class="text-[10px] text-slate-400">node</span></span>
                <span class="h-3 w-px bg-slate-200 dark:bg-slate-700"></span>
                <span class="font-mono text-slate-700 dark:text-slate-300">{{ $summary['ports'] > 0 ? round(100 * $summary['used_ports'] / $summary['ports']) : 0 }}% <span class="text-[10px] text-slate-400">port</span></span>
                @if($summary['red_zone'] > 0)
                    <span class="h-3 w-px bg-slate-200 dark:bg-slate-700"></span>
                    <span class="font-mono font-bold text-rose-600 dark:text-rose-400">{{ $summary['red_zone'] }} <span class="text-[10px]">kritis</span></span>
                @endif
                <svg class="size-3 text-slate-400 transition-transform duration-200" :class="statsOpen ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
            </button>

            <!-- FLOATING METRICS POPOVER CARD -->
            <div
                x-show="statsOpen"
                x-cloak
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                x-transition:leave-end="opacity-0 translate-y-2 scale-95"
                class="absolute right-0 top-full mt-2 w-72 sm:w-80 rounded-2xl border border-slate-200/90 bg-white/98 p-4 shadow-2xl backdrop-blur-md dark:border-slate-800 dark:bg-[#111920]/98 z-50">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2.5 dark:border-slate-800">
                    <h2 class="font-display text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300">Ringkasan Kapasitas Jaringan</h2>
                    <span class="font-mono text-[10px] text-slate-400">{{ $nodeCount }} node dalam cakupan</span>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-2.5">
                    <div class="rounded-xl border border-slate-100 bg-slate-50/80 p-2.5 dark:border-slate-800 dark:bg-slate-900/60">
                        <span class="text-[10px] font-medium text-slate-500 uppercase">Titik Jaringan</span>
                        <div class="font-mono mt-0.5 text-base font-bold text-slate-900 dark:text-slate-100">{{ $summary['nodes'] }} <span class="text-xs font-normal text-slate-400">node</span></div>
                    </div>
                    <div class="rounded-xl border border-slate-100 bg-slate-50/80 p-2.5 dark:border-slate-800 dark:bg-slate-900/60">
                        <span class="text-[10px] font-medium text-slate-500 uppercase">Port Terpakai</span>
                        <div class="font-mono mt-0.5 text-base font-bold text-slate-900 dark:text-slate-100">
                            {{ $summary['used_ports'] }}/{{ $summary['ports'] }}
                            @if($summary['ports'] > 0)
                                <span class="text-[11px] font-normal text-slate-400">({{ round(100 * $summary['used_ports'] / $summary['ports']) }}%)</span>
                            @endif
                        </div>
                    </div>
                    <div class="rounded-xl border border-slate-100 bg-slate-50/80 p-2.5 dark:border-slate-800 dark:bg-slate-900/60">
                        <span class="text-[10px] font-medium text-slate-500 uppercase">Slot OLT Kosong</span>
                        <div class="font-mono mt-0.5 text-base font-bold text-slate-900 dark:text-slate-100">{{ $summary['free_olt_slots'] }} <span class="text-xs font-normal text-slate-400">slot</span></div>
                    </div>
                    <div class="rounded-xl border border-slate-100 bg-slate-50/80 p-2.5 dark:border-slate-800 dark:bg-slate-900/60">
                        <span class="text-[10px] font-medium text-slate-500 uppercase">Aset Terpasang</span>
                        <div class="font-mono mt-0.5 text-base font-bold text-sky-700 dark:text-sky-400">{{ $summary['assets'] }} <span class="text-xs font-normal text-slate-400">baris</span></div>
                    </div>
                    <div class="col-span-2 rounded-xl border border-slate-100 bg-slate-50/80 p-2.5 dark:border-slate-800 dark:bg-slate-900/60 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-medium text-slate-500 uppercase">Zona Kritis (≥85%)</span>
                            <div class="font-mono mt-0.5 text-base font-bold {{ $summary['red_zone'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                {{ $summary['red_zone'] }} <span class="text-xs font-normal text-slate-400">node</span>
                            </div>
                        </div>
                        @if($summary['red_zone'] > 0)
                            <span class="rounded-lg bg-rose-100 px-2 py-1 text-[10px] font-bold text-rose-700 dark:bg-rose-950/60 dark:text-rose-300">Perlu Perhatian</span>
                        @else
                            <span class="rounded-lg bg-emerald-100 px-2 py-1 text-[10px] font-bold text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">Normal</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- FLOATING DETAIL TOGGLE BUTTON -->
        <button
            type="button"
            @click="drawerOpen = !drawerOpen"
            class="flex items-center gap-1.5 rounded-xl border border-slate-200/80 bg-white/95 px-3 py-1.5 text-xs font-semibold shadow-md backdrop-blur-md transition hover:border-sky-500 hover:text-sky-700 dark:border-slate-800 dark:bg-[#131d24]/95 dark:text-slate-200 dark:hover:border-sky-400 cursor-pointer"
            :class="drawerOpen ? 'bg-sky-50 text-sky-800 border-sky-400 dark:bg-sky-950/60 dark:text-sky-300 dark:border-sky-700' : ''">
            <svg class="size-3.5 transition-transform" :class="drawerOpen ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M15 3v18"/></svg>
            <span x-text="drawerOpen ? 'Tutup Detail' : 'Detail Node'"></span>
        </button>
    </div>

    <!-- MAIN BODY: WORKSPACE (FULL SCREEN CANVAS FOCUS) -->
    <div class="relative flex-1 h-full w-full min-h-0 overflow-hidden">

        <!-- LEFT WORKSPACE AREA -->
        <main class="h-full w-full min-w-0 bg-[#f8fafc] dark:bg-[#0c1319] flex flex-col">

            <!-- TAB 1: TOPOLOGI (SVG CANVAS FULL FOCUS) -->
            <section x-show="tab === 'topologi'" class="relative flex-1 flex flex-col min-h-0 h-full w-full overflow-hidden">
                <!-- FLOATING LAZY HINT -->
                <div x-show="lazyHint || ontProgress" x-cloak x-transition class="absolute top-16 left-1/2 -translate-x-1/2 z-30 pointer-events-auto max-w-lg rounded-xl border border-amber-300 bg-amber-50/95 px-4 py-2 text-xs font-medium text-amber-900 shadow-lg backdrop-blur-md dark:border-amber-700/60 dark:bg-amber-950/90 dark:text-amber-200">
                    <p x-text="lazyHint || ontProgress"></p>
                </div>

                <!-- FLOATING BOTTOM CANVAS CONTROLS DOCK (ZOOM, DEPTH, EXPAND, ACTIONS) -->
                <aside aria-label="Kontrol Kanvas" class="absolute bottom-3 left-3 z-30 flex flex-wrap items-center gap-1.5 pointer-events-auto rounded-2xl border border-slate-200/80 bg-white/95 p-1.5 shadow-lg backdrop-blur-md dark:border-slate-800 dark:bg-[#131d24]/95">
                    <!-- ZOOM CONTROLS -->
                    <div class="flex items-center rounded-xl bg-slate-100/90 dark:bg-slate-800/90 p-0.5 border border-slate-200/50 dark:border-slate-700/50">
                        <button type="button" @click="zoomOut()" aria-label="Perkecil" class="flex size-7 items-center justify-center rounded-lg text-xs font-mono font-bold text-slate-700 hover:bg-white hover:text-sky-700 dark:text-slate-300 dark:hover:bg-slate-700 cursor-pointer transition">−</button>
                        <span class="px-2 font-mono text-[11px] font-semibold text-slate-800 dark:text-slate-200" x-text="Math.round(zoom * 100) + '%'">100%</span>
                        <button type="button" @click="zoomIn()" aria-label="Perbesar" class="flex size-7 items-center justify-center rounded-lg text-xs font-mono font-bold text-slate-700 hover:bg-white hover:text-sky-700 dark:text-slate-300 dark:hover:bg-slate-700 cursor-pointer transition">+</button>
                        <button type="button" @click="zoomFit()" class="rounded-lg px-2 py-1 font-display text-[11px] font-semibold text-slate-700 hover:bg-white hover:text-sky-700 dark:text-slate-300 dark:hover:bg-slate-700 cursor-pointer transition">Pas</button>
                    </div>

                    <span class="h-5 w-px bg-slate-200 dark:bg-slate-700"></span>

                    <!-- DEPTH SELECTOR -->
                    <div class="flex items-center gap-1">
                        <label for="topo-depth" class="sr-only">Kedalaman</label>
                        <select id="topo-depth" x-model="depthFilter" @change="applyDepth($event.target.value)"
                                class="rounded-xl border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-semibold text-slate-800 shadow-2xs dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 cursor-pointer">
                            <option value="custom">Kedalaman: Kustom</option>
                            <option value="all">Kedalaman: Semua (ONT/daun)</option>
                            <option value="odp">Kedalaman: Sampai ODP</option>
                            <option value="olt">Kedalaman: Sampai OLT / PON</option>
                            <option value="pop">Kedalaman: Sampai POP</option>
                        </select>
                    </div>

                    <!-- BUKA / TUTUP SEMUA -->
                    <div class="flex items-center rounded-xl bg-slate-100/90 dark:bg-slate-800/90 p-0.5 border border-slate-200/50 dark:border-slate-700/50">
                        <button type="button" @click="expandAll()" title="Buka semua cabang sampai ONT pelanggan"
                                class="rounded-lg px-2.5 py-1 font-display text-[11px] font-semibold text-slate-700 hover:bg-white hover:text-sky-700 dark:text-slate-300 dark:hover:bg-slate-700 cursor-pointer transition">Buka Semua</button>
                        <button type="button" @click="collapseToSlots()" title="Tutup semua, hanya tampil sampai Slot"
                                class="rounded-lg px-2.5 py-1 font-display text-[11px] font-semibold text-slate-700 hover:bg-white hover:text-sky-700 dark:text-slate-300 dark:hover:bg-slate-700 cursor-pointer transition">Tutup Semua</button>
                    </div>

                    <span class="h-5 w-px bg-slate-200 dark:bg-slate-700"></span>

                    <!-- ACTION SHORTCUTS -->
                    <div class="flex items-center gap-1">
                        <a href="{{ route('network.map.index', array_filter(['pop_id' => $selectedPopId, 'sort' => $sort, 'fresh' => 1])) }}"
                           title="Muat ulang cache topologi"
                           class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-sky-500 hover:text-sky-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"></path><path d="M16 21h5v-5"></path></svg>
                            <span>Muat Ulang</span>
                        </a>
                        <a href="{{ route('network.nodes.index') }}"
                           class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-sky-500 hover:text-sky-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="7" height="7" x="3" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="3" rx="1"></rect><rect width="7" height="7" x="14" y="14" rx="1"></rect><rect width="7" height="7" x="3" y="14" rx="1"></rect></svg>
                            <span>Kelola Node</span>
                        </a>
                        <a href="{{ route('manuals.context', ['route' => 'network.map.index']) }}"
                           class="inline-flex items-center gap-1 rounded-xl border border-sky-300 bg-sky-50 px-2.5 py-1.5 text-xs font-semibold text-sky-800 transition hover:bg-sky-100 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-300">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                            <span>Panduan</span>
                        </a>
                    </div>
                </aside>

                <!-- FLOATING BOTTOM-RIGHT LEGEND & GESTURE HINT -->
                <aside aria-label="Petunjuk Kanvas" class="pointer-events-none absolute bottom-3 right-3 z-20 hidden md:flex items-center gap-3 rounded-xl border border-slate-200/80 bg-white/90 px-3 py-1.5 text-[11px] text-slate-500 shadow-md backdrop-blur-md dark:border-slate-800 dark:bg-[#131d24]/90 dark:text-slate-400">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1"><span class="size-2 rounded-full bg-emerald-500"></span> Aktif</span>
                        <span class="inline-flex items-center gap-1"><span class="size-2 rounded-full bg-slate-400"></span> Nonaktif</span>
                        <span class="inline-flex items-center gap-1"><span class="size-2 rounded-full bg-rose-500"></span> Penuh</span>
                    </div>
                    <span class="h-3 w-px bg-slate-200 dark:bg-slate-700"></span>
                    <span>Seret kanvas · Ctrl + scroll zoom · Klik node detail</span>
                </aside>

                <!-- SVG INTERACTIVE CANVAS (FULL SCREEN) -->
                <div x-ref="topologyCanvas"
                     class="relative flex-1 h-full w-full overflow-hidden canvas-dot-grid"
                     @pointerdown="onPanStart($event)"
                     @pointermove.window="onPanMove($event)"
                     @pointerup.window="onPanEnd($event)"
                     @pointercancel.window="onPanEnd($event)"
                     @wheel="onCanvasWheel($event)"
                     :class="isPanning ? 'cursor-grabbing' : 'cursor-grab'">

                    @if(! $hasPops && $nodeCount === 0)
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
                                 :style="layout ? `width: ${layout.width}px; height: ${layout.height}px; transform: translate(${panX}px, ${panY}px) scale(${zoom}); transform-origin: 0 0; transition: ${isPanning ? 'none' : 'transform 0.1s ease-out'};` : ''">
                                <svg :width="layout?.width || 0" :height="layout?.height || 0"
                                     class="pointer-events-none absolute left-0 top-0 text-slate-300 dark:text-slate-700/80"
                                     x-html="layout ? edgePaths(layout) : ''"></svg>
                                <svg :width="layout?.width || 0" :height="layout?.height || 0"
                                     class="pointer-events-none absolute left-0 top-0"
                                     x-html="layout ? activePathSvg(layout) : ''"></svg>

                                <template x-for="id in renderIds" :key="id">
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
                                            <g x-show="hasKids(id)">
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

                    <div x-show="withOnt && zoom < ontMinZoom" x-cloak class="pointer-events-none absolute bottom-12 left-4 rounded bg-violet-50/95 px-2.5 py-1 text-[11px] font-medium text-violet-800 shadow-2xs dark:bg-violet-950/80 dark:text-violet-200">
                        Ikhtisar: kartu ONT disembunyikan — perbesar untuk menampilkannya
                    </div>
                    <div class="pointer-events-none absolute bottom-3 left-4 rounded bg-white/85 px-2.5 py-1 text-[11px] text-slate-500 shadow-2xs backdrop-blur-xs dark:bg-slate-900/85 dark:text-slate-400">
                        Seret kanvas untuk menggeser · Ctrl + scroll untuk zoom · Klik node untuk detail
                    </div>
                </div>
            </section>

            <!-- TAB 2: PER POP (POHON HIERARKI) -->
            @if($activeTab === 'pohon')
            <section x-show="tab === 'pohon'" class="flex-1 overflow-auto px-5 pb-5 pt-28 sm:pt-16 custom-scroll">
                @if(! $hasPops && $nodeCount === 0)
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

            @endif

            <!-- TAB 3: INVENTARIS (TABULAR VIEW) -->
            @if($activeTab === 'inventaris')
            <section x-show="tab === 'inventaris'" class="flex-1 px-5 pb-5 pt-28 sm:pt-16 overflow-auto custom-scroll">
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

                    {{--
                        FILTER CASCADING: POP -> MINI POP -> OLT -> SLOT -> PON -> ODP, "Semua" default.
                        Sama pola dengan tab Terpasang (ADHOC-237/238) — subtree besar tanpa filter
                        dirender sekaligus = DOM raksasa, makanya paginasi di bawah juga wajib.
                    --}}
                    <div class="border-b border-slate-200 bg-slate-50/50 px-4 py-2.5 dark:border-slate-800 dark:bg-slate-800/20">
                        <form method="GET" action="{{ route('network.map.index') }}" class="flex flex-wrap items-center gap-2">
                            <input type="hidden" name="tab" value="inventaris">
                            @if($selectedPopId)<input type="hidden" name="pop_id" value="{{ $selectedPopId }}">@endif
                            <input type="hidden" name="sort" value="{{ $sort }}">

                            <select name="inv_pop_id" onchange="this.form.submit()" class="rounded border border-slate-300 bg-white px-2 py-1 text-xs dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                                <option value="">Semua POP</option>
                                @foreach($cabangs as $cabang)
                                    <option value="{{ $cabang->id }}" @selected($invSelected['pop_id'] === $cabang->id)>{{ $cabang->name }}</option>
                                @endforeach
                            </select>

                            <select name="inv_mini_id" onchange="this.form.submit()" @disabled($invMiniPops->isEmpty()) class="rounded border border-slate-300 bg-white px-2 py-1 text-xs disabled:opacity-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                                <option value="">Semua Mini POP</option>
                                @foreach($invMiniPops as $mini)
                                    <option value="{{ $mini->id }}" @selected($invSelected['mini_id'] === $mini->id)>{{ $mini->name }}</option>
                                @endforeach
                            </select>

                            <select name="inv_olt_id" onchange="this.form.submit()" @disabled($invOltOptions->isEmpty()) class="rounded border border-slate-300 bg-white px-2 py-1 text-xs disabled:opacity-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                                <option value="">Semua OLT</option>
                                @foreach($invOltOptions as $olt)
                                    <option value="{{ $olt->id }}" @selected($invSelected['olt_id'] === $olt->id)>{{ $olt->code }}</option>
                                @endforeach
                            </select>

                            <select name="inv_slot_id" onchange="this.form.submit()" @disabled($invSlotOptions->isEmpty()) class="rounded border border-slate-300 bg-white px-2 py-1 text-xs disabled:opacity-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                                <option value="">Semua Slot</option>
                                @foreach($invSlotOptions as $slot)
                                    <option value="{{ $slot->id }}" @selected($invSelected['slot_id'] === $slot->id)>{{ $slot->code }}</option>
                                @endforeach
                            </select>

                            <select name="inv_pon_id" onchange="this.form.submit()" @disabled($invPonOptions->isEmpty()) class="rounded border border-slate-300 bg-white px-2 py-1 text-xs disabled:opacity-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                                <option value="">Semua PON</option>
                                @foreach($invPonOptions as $pon)
                                    <option value="{{ $pon->id }}" @selected($invSelected['pon_id'] === $pon->id)>{{ $pon->code }}</option>
                                @endforeach
                            </select>

                            <select name="inv_odp_id" onchange="this.form.submit()" @disabled($invOdpOptions->isEmpty()) class="rounded border border-slate-300 bg-white px-2 py-1 text-xs disabled:opacity-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                                <option value="">Semua ODP</option>
                                @foreach($invOdpOptions as $odp)
                                    <option value="{{ $odp->id }}" @selected($invSelected['odp_id'] === $odp->id)>{{ $odp->code }}</option>
                                @endforeach
                            </select>
                        </form>
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
                                    <tr data-inventory-row x-show="matchesNode({{ $node->id }})"
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

                    @if($invTotalRows > 0)
                        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 px-4 py-2.5 text-[11px] text-slate-500 dark:border-slate-800 dark:text-slate-400">
                            <span>
                                Baris {{ (($invPage - 1) * $invPerPage) + 1 }}–{{ min($invPage * $invPerPage, $invTotalRows) }}
                                dari {{ $invTotalRows }}
                                (hal. {{ $invPage }}/{{ $invLastPage }})
                            </span>
                            <div class="flex items-center gap-1.5">
                                @if($invPage > 1)
                                    <a href="{{ request()->fullUrlWithQuery(['inv_page' => $invPage - 1]) }}"
                                       class="rounded border border-slate-300 px-2 py-1 font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">← Sebelumnya</a>
                                @endif
                                @if($invPage < $invLastPage)
                                    <a href="{{ request()->fullUrlWithQuery(['inv_page' => $invPage + 1]) }}"
                                       class="rounded border border-slate-300 px-2 py-1 font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Selanjutnya →</a>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </section>
            @endif

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
