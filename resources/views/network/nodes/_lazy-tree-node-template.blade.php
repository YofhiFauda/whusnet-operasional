{{--
    Satu baris anak pohon yang dimuat lazy (perangkat → Slot → PON → ODP).
    Dirender ulang secara rekursif lewat `x-html="lazyTreeTemplate()"` di
    dalam `x-for` milik induknya; `x-html` Alpine 3 meng-inisialisasi isi
    yang disisipkan, dan `child` diambil dari scope `x-for` terdekat.
    Rekursi berhenti sendiri: kontainer anak hanya dirender (`x-if`) setelah
    cabang dibuka & dimuat, jadi tidak ada sisipan tak berujung.

    Semua URL (`child.url`, `child.children_url`, `child.panel_url`) datang
    dari JSON `network.nodes.children` yang dirender server via `route()` —
    template ini tidak pernah merakit path dari id (konvensi CLAUDE.md).
    Teks node (label/kode) hanya lewat x-text, tidak pernah x-html.

    Baris ber-`panel_url` (Slot, PON) DIPILIH saat diklik → inspector kanan
    memuat panel "Manajemen Node — Slot/PON" (`selectPosition`). Baris lain
    (ODP, Port Switch) tetap tautan ke halaman Detail-nya.
--}}
<template id="lazy-tree-node-template">
    <div class="tree-line select-none my-0.5" :data-hierarchy-key="child.key" :data-lazy-node-id="child.id"
         x-data="lazyTreeNode(child.children_url)">
        <div class="tree-node-item group flex items-center justify-between gap-1.5 sm:gap-2 rounded-lg border border-transparent px-2 py-1.5 transition-colors hover:bg-surface-muted"
             :class="{ 'cursor-pointer': child.panel_url, 'bg-sky-50 dark:bg-sky-950/60 border-l-2 border-l-sky-600 shadow-2xs': child.panel_url && selectedNodeId === child.id }"
             @click="if (child.panel_url) selectPosition(child)"
             :title="child.path || child.label">
            <div class="flex min-w-0 flex-1 items-center gap-1.5 sm:gap-2">
                <template x-if="child.children_url">
                    <button type="button" @click.stop="toggle()" class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md text-text-muted hover:bg-surface-muted hover:text-text-main transition-colors cursor-pointer" title="Buka/Tutup Cabang">
                        <svg class="h-3.5 w-3.5 transition-transform duration-150" :class="{ '-rotate-90': !isOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                </template>
                <template x-if="!child.children_url">
                    <span class="flex h-5 w-5 shrink-0 items-center justify-center text-xs text-text-disabled">•</span>
                </template>
                <span class="shrink-0 rounded-md border border-border bg-surface-muted px-1.5 py-0.5 font-mono text-[10px] font-bold text-text-secondary" x-text="lazyTreeBadge(child.type)"></span>
                <template x-if="child.panel_url">
                    <span class="min-w-0 truncate font-mono text-xs font-semibold text-text-main" x-text="child.label"></span>
                </template>
                <template x-if="!child.panel_url">
                    <a :href="child.url" @click.stop class="min-w-0 truncate font-mono text-xs font-semibold text-text-main hover:text-primary hover:underline" x-text="child.label"></a>
                </template>
            </div>
            <template x-if="child.capacity !== null || child.used !== null">
                <span class="shrink-0 rounded-full border border-border bg-surface-muted px-1.5 py-0.5 font-mono text-[10px] font-bold tabular-nums text-text-secondary">
                    <span x-text="child.capacity === null ? (child.used ?? '–') + ' · belum didata' : (child.used ?? '–') + '/' + child.capacity"></span>
                </span>
            </template>
        </div>
        <template x-if="lazyLoaded || lazyLoading">
            <div x-show="isOpen" class="ml-2 sm:ml-3 space-y-0.5 pl-1 sm:pl-1.5 border-l border-border/50">
                <p x-show="lazyLoading" class="px-2 py-1 text-[11px] text-text-muted">Memuat…</p>
                <template x-for="child in lazyChildren" :key="child.key">
                    <div x-html="lazyTreeTemplate()"></div>
                </template>
                <p x-show="lazyLoaded && lazyChildren.length === 0" class="px-2 py-1 text-[11px] text-text-muted">Belum ada anak tercatat.</p>
            </div>
        </template>
    </div>
</template>
