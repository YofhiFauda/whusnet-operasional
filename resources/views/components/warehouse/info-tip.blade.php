@props([
    'align' => 'right', // 'right' (default, dipasang di kanan icon) atau 'left'
])

{{--
    Icon info kecil (2026-09-07) — dipasang di sebelah label/tombol yang
    istilahnya butuh penjelasan (Opname, Custody, Penyesuaian Saldo — 3
    istilah ini yang paling sering ditanya user). Klik toggle popover kecil,
    BUKAN navigasi/mutasi apa pun — makanya @click.stop.prevent, biar aman
    ditaruh nempel sama <a> tombol aksi tanpa ikut ke-trigger.

    Popover-nya `x-teleport` ke <body> + `position: fixed` dihitung dari
    posisi tombol (bukan `absolute` nempel parent) — instance di tab nav
    Custody ketaruh di dalam kontainer `overflow-x-auto` (header.blade.php),
    popover `absolute` biasa KEPOTONG/tenggelam kena `overflow` parent-nya.
    Teleport keluar dari parent manapun, jadi gak pernah ketutup overflow
    kontainer di mana pun komponen ini dipasang.
--}}
<div class="inline-block" x-data="{
        open: false,
        pos: { top: 0, left: 0 },
        toggle() {
            if (! this.open) {
                const r = this.$refs.trigger.getBoundingClientRect();
                this.pos = {
                    top: r.bottom + 6,
                    left: {{ $align === 'left' ? 'Math.max(8, r.right - 256)' : 'Math.min(r.left, window.innerWidth - 264)' }},
                };
            }
            this.open = ! this.open;
        },
     }">
    <button type="button" x-ref="trigger" @click.stop.prevent="toggle()"
            class="ml-1 w-4 h-4 inline-flex items-center justify-center rounded-full text-[10px] font-bold text-slate-400 dark:text-slate-500 bg-slate-100 dark:bg-slate-700/60 hover:bg-slate-200 dark:hover:bg-slate-600 hover:text-slate-600 dark:hover:text-slate-300 transition-colors cursor-pointer"
            aria-label="Penjelasan fungsi ini">
        i
    </button>

    <template x-teleport="body">
        <div x-show="open" x-cloak @click.outside="open = false" @click.stop
             x-transition:enter="transition ease-out duration-100"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             :style="`top:${pos.top}px; left:${pos.left}px;`"
             class="fixed z-[9999] w-64 p-3 rounded-xl bg-slate-900 dark:bg-slate-950 text-slate-100 text-[11px] leading-relaxed shadow-xl border border-slate-700/60">
            {{ $slot }}
        </div>
    </template>
</div>
