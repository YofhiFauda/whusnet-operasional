{{--
    Menu aksi papan Kelola Stok — satu daftar aksi untuk tiga tempat: baris
    tabel, footer drawer, dan (Task 6) sel matriks. URL-nya SELALU dari payload
    `_detail` (route() di server); partial ini hanya memilih mana yang tampil.

    Mode:
    - 'menu'   : dropdown "⋮ Aksi" per baris. Butuh $detail (array hasil decode
                 `_detail`). Tautan dirender sebagai href statis supaya tetap
                 benar walau Alpine gagal dimuat (pelajaran ADHOC-20).
    - 'footer' : deretan tombol di footer drawer. Membaca `detail.actions` dari
                 scope Alpine drawer — drawer satu komponen untuk semua baris,
                 jadi di sini klien MEMILIH di antara URL kiriman server, tidak
                 merakitnya.

    Syarat tampil tiap aksi (pusat/cabang, permission, aturan lot_no) sudah
    diputuskan di `_detail`: URL null = aksi tidak tersedia.
--}}
@php
    $mode ??= 'menu';
    $definitions = [
        ['key' => 'transferUrl', 'label' => 'Kirim Transfer', 'group' => 1, 'class' => 'text-sky-700 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-sky-950/40', 'icon' => 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4'],
        ['key' => 'requestStockUrl', 'label' => 'Minta Stok', 'group' => 1, 'class' => 'text-indigo-700 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/40', 'icon' => 'M12 4v16m8-8H4'],
        ['key' => 'issueUrl', 'label' => 'Serah Teknisi', 'group' => 1, 'class' => 'text-emerald-700 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/40', 'icon' => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a4 4 0 10-4-4'],
        ['key' => 'adjustUrl', 'label' => 'Opname / Sesuaikan', 'group' => 2, 'class' => 'text-amber-700 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/40', 'icon' => 'M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10'],
        ['key' => 'thresholdUrl', 'label' => 'Atur Ambang', 'group' => 2, 'class' => 'text-rose-700 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40', 'icon' => 'M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
    ];
    $tierIcon = 'M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5';
@endphp

@if($mode === 'footer')
<div class="flex flex-wrap items-center gap-2">
    @foreach($definitions as $definition)
    <template x-if="detail.actions.{{ $definition['key'] }}">
        <a :href="detail.actions.{{ $definition['key'] }}"
           class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 {{ $definition['class'] }}">
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $definition['icon'] }}"/></svg>
            <span>{{ $definition['label'] }}</span>
        </a>
    </template>
    @endforeach
    <p x-show="detail.opnamePerTier" class="text-[11px] text-amber-700 dark:text-amber-400">Opname dilakukan per tingkat harga pada tabel di atas.</p>
    <p x-show="!Object.values(detail.actions).some(Boolean) && !detail.opnamePerTier" class="text-[11px] text-slate-500 dark:text-slate-400">Tidak ada aksi yang tersedia untuk Anda di gudang ini.</p>
</div>
@else
@php
    $actions = $detail['actions'];
    $available = array_values(array_filter($definitions, fn (array $definition) => $actions[$definition['key']] !== null));
    $opnamePerTier = $detail['opnamePerTier'];
@endphp
@if($available === [] && ! $opnamePerTier)
<span class="text-slate-300 dark:text-slate-600" aria-hidden="true">—</span><span class="sr-only">Tidak ada aksi</span>
@else
<div x-data="{
        menuOpen: false,
        trigger: null,
        pos: { openUp: false, top: null, bottom: null, left: 0 },
        toggle(e) {
            if (!this.menuOpen) {
                this.trigger = e.currentTarget;
                this.pos = window.calcDropdownPos(e.currentTarget, 224);
            }
            this.menuOpen = !this.menuOpen;
        },
        openTiers() {
            {{-- Buka drawer lewat pemicu baris itu sendiri: payload satu
                 sumber (data-stock-detail) dan fokus kembali ke baris saat
                 drawer ditutup. --}}
            this.menuOpen = false;
            this.trigger?.closest('[data-board-row]')?.querySelector('[data-stock-detail]')?.click();
        },
     }"
     @scroll.window="menuOpen = false" @resize.window="menuOpen = false"
     @keydown.escape="menuOpen = false"
     class="inline-block text-left">
    <button type="button" @click.stop="toggle($event)"
            :aria-expanded="menuOpen.toString()" aria-expanded="false" aria-haspopup="menu"
            aria-label="Aksi untuk {{ $detail['itemName'] }}"
            class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500">
        <span>⋮ Aksi</span>
        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
    </button>

    <template x-teleport="body">
        <div x-show="menuOpen" x-cloak @click.outside="menuOpen = false" @click.stop @keydown.escape="menuOpen = false; trigger?.focus()"
             role="menu"
             x-transition:enter="transition ease-out duration-100"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-75"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             :style="pos.openUp
                 ? { position: 'fixed', bottom: pos.bottom + 'px', left: pos.left + 'px', zIndex: 9999 }
                 : { position: 'fixed', top: pos.top + 'px', left: pos.left + 'px', zIndex: 9999 }"
             class="w-56 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-2xl py-1.5 text-left divide-y divide-slate-100 dark:divide-slate-700/60">
            @foreach(collect($available)->groupBy('group') as $group)
            <div class="py-1">
                @foreach($group as $definition)
                <a href="{{ $actions[$definition['key']] }}" role="menuitem"
                   class="flex items-center gap-2 px-3.5 py-2 text-xs font-semibold transition-colors focus:outline-none focus-visible:bg-slate-100 dark:focus-visible:bg-slate-700 {{ $definition['class'] }}">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $definition['icon'] }}"/></svg>
                    <span>{{ $definition['label'] }}</span>
                </a>
                @endforeach
            </div>
            @endforeach
            @if($opnamePerTier)
            <div class="py-1">
                <button type="button" role="menuitem" @click="openTiers()"
                        class="w-full flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-amber-700 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/40 transition-colors cursor-pointer text-left focus:outline-none focus-visible:bg-slate-100 dark:focus-visible:bg-slate-700">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $tierIcon }}"/></svg>
                    <span>Opname per harga → buka rincian</span>
                </button>
            </div>
            @endif
        </div>
    </template>
</div>
@endif
@endif
