@props([
    'pop' => 'fromPopId',
    'form',
    'title' => 'Rincian & Pilih Barang',
    'submitLabel' => 'Kirim Sekarang',
    'submitShort' => 'Kirim',
    // Ekspresi Alpine (string) untuk baris kecil di bawah hitungan bar bawah,
    // mis. "(fromPopId ? 'Asal siap' : 'Pilih Asal') + ' • ' + ...".
    'barHint' => "''",
    'emptyPopText' => 'Pilih gudang terlebih dahulu untuk memuat stok fisik.',
    'emptyStockText' => 'Stok kosong di gudang ini.',
    // Judul panel stok di dialog desktop (mobile memakai tab).
    'stockTitle' => 'Stok Tersedia',
])

{{--
    Pemilih stok untuk form Transfer & Serah Teknisi — satu komponen, tiga
    ukuran layar:
    - mobile/tablet (< lg): bottom sheet 90dvh, tab "Pilih dari Stok" /
      "Terpilih", plus bar bawah melayang;
    - laptop/desktop (≥ lg): dialog lebar (max-w-6xl, 85vh) DUA PANEL —
      stok di kiri, "Barang Terpilih" di kanan selalu terlihat, tanpa tab.

    Kenapa desktop juga lewat dialog: dulu pemilih stok desktop cuma panel
    di sidebar 4/12 kolom (± 380px) dengan tinggi terbatas, dan hasil pilihan
    hanya masuk ke textarea form → sempit, dan user tidak sadar barang sudah
    terpilih (keluhan lapangan 2026-11). Dialog memberi ruang untuk ratusan
    chip, panel kanan memberi umpan balik langsung.

    Layout flex-column (header / body scroll / footer tetap): dulu satu
    kontainer `overflow-y-auto` berisi list yang juga `max-h-52` → dua scroll
    bertumpuk dan tombol Kirim ikut terscroll.

    Dialog TIDAK ditutup saat chip ditap. Tutup lewat "Selesai", backdrop,
    tombol ×, atau Esc. Dibuka dari mana saja lewat event window
    `open-stock-sheet` (detail.tab: 'stock' | 'picked', khusus mobile).
--}}
<div x-data="{ openMobileSheet: false, sheetTab: 'stock' }"
     x-effect="document.documentElement.classList.toggle('overflow-hidden', openMobileSheet)"
     @open-stock-sheet.window="sheetTab = $event.detail?.tab || 'stock'; openMobileSheet = true"
     @keydown.escape.window="openMobileSheet = false">

    {{-- Backdrop --}}
    <div x-show="openMobileSheet" x-cloak
         @click="openMobileSheet = false"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50"></div>

    {{-- Sheet / Dialog --}}
    <div x-show="openMobileSheet" x-cloak
         x-transition:enter="transition ease-out duration-250 transform"
         x-transition:enter-start="translate-y-full lg:translate-y-6 lg:opacity-0"
         x-transition:enter-end="translate-y-0 lg:opacity-100"
         x-transition:leave="transition ease-in duration-200 transform"
         x-transition:leave-start="translate-y-0 lg:opacity-100"
         x-transition:leave-end="translate-y-full lg:translate-y-6 lg:opacity-0"
         class="fixed inset-x-0 bottom-0 z-50 flex justify-center pointer-events-none lg:inset-0 lg:items-center lg:p-6">
        <div class="pointer-events-auto w-full sm:max-w-2xl lg:max-w-6xl h-[90dvh] lg:h-[85vh] flex flex-col bg-white dark:bg-slate-900 rounded-t-2xl lg:rounded-2xl border-t border-x lg:border border-slate-200 dark:border-slate-700 shadow-2xl overflow-hidden"
             role="dialog" aria-modal="true" aria-label="{{ $title }}">

            {{-- HEADER (tetap) --}}
            <div class="shrink-0 px-4 sm:px-5 pt-2.5 lg:pt-4 pb-3 border-b border-slate-100 dark:border-slate-800 space-y-3">
                <div class="w-10 h-1 bg-slate-200 dark:bg-slate-700 rounded-full mx-auto lg:hidden"></div>

                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h4 class="text-sm lg:text-base font-semibold text-slate-900 dark:text-slate-100">{{ $title }}</h4>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                            <span class="font-mono font-bold text-sky-600 dark:text-sky-400" x-text="totalSnCount"></span> SN
                            &bull; <span class="font-mono font-bold text-slate-700 dark:text-slate-200" x-text="validItemCount"></span> jenis barang
                            <template x-if="totalQtyCount > 0">
                                <span>&bull; <span class="font-mono font-bold text-slate-700 dark:text-slate-200" x-text="totalQtyCount"></span> qty</span>
                            </template>
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        {{-- Status kelengkapan: di desktop sejajar judul --}}
                        <div class="hidden lg:flex flex-wrap justify-end gap-1.5">
                            {{ $status }}
                        </div>
                        <button type="button" @click="openMobileSheet = false"
                                class="-mr-1.5 p-2 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer" aria-label="Tutup">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>

                {{-- Status kelengkapan ringkas (mobile) --}}
                <div class="flex flex-wrap gap-1.5 lg:hidden">
                    {{ $status }}
                </div>

                {{-- Tab (mobile/tablet saja; desktop menampilkan dua panel) --}}
                <div class="grid grid-cols-2 p-1 bg-slate-100 dark:bg-slate-800 rounded-lg lg:hidden">
                    <button type="button" @click="sheetTab = 'stock'"
                            :class="sheetTab === 'stock' ? 'bg-white dark:bg-slate-900 text-sky-700 dark:text-sky-300 shadow-xs' : 'text-slate-500 dark:text-slate-400'"
                            class="min-h-[38px] rounded-md text-xs font-semibold transition-colors cursor-pointer">
                        Pilih dari Stok
                    </button>
                    <button type="button" @click="sheetTab = 'picked'"
                            :class="sheetTab === 'picked' ? 'bg-white dark:bg-slate-900 text-sky-700 dark:text-sky-300 shadow-xs' : 'text-slate-500 dark:text-slate-400'"
                            class="min-h-[38px] rounded-md text-xs font-semibold transition-colors cursor-pointer inline-flex items-center justify-center gap-1.5">
                        <span>Terpilih</span>
                        <span class="min-w-[20px] px-1.5 py-0.5 rounded-full text-[10px] font-mono font-bold"
                              :class="validItemCount > 0 ? 'bg-sky-600 text-white' : 'bg-slate-200 dark:bg-slate-700 text-slate-500'"
                              x-text="pickedUnitCount()"></span>
                    </button>
                </div>
            </div>

            {{-- BODY: mobile = satu panel per tab; desktop = dua panel berdampingan --}}
            <div class="flex-1 min-h-0 flex flex-col lg:grid lg:grid-cols-[minmax(0,1fr)_360px] lg:grid-rows-[minmax(0,1fr)]">

                {{-- Panel stok --}}
                <div :class="sheetTab === 'stock' ? 'flex-1' : 'hidden lg:block'"
                     class="min-h-0 overflow-y-auto overscroll-contain px-4 sm:px-5 py-4 space-y-3">
                    <div class="hidden lg:flex items-center justify-between">
                        <h5 class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $stockTitle }}</h5>
                        <span class="text-[11px] font-mono text-slate-400" x-show="!loadingStock" x-text="availableStockItems.length + ' jenis barang'"></span>
                    </div>

                    {{ $notice ?? '' }}

                    <x-warehouse.stock-quick-picker
                        :pop="$pop"
                        :empty-pop-text="$emptyPopText"
                        :empty-stock-text="$emptyStockText" />
                </div>

                {{-- Panel terpilih --}}
                <div :class="sheetTab === 'picked' ? 'flex-1' : 'hidden lg:block'"
                     class="min-h-0 overflow-y-auto overscroll-contain px-4 sm:px-5 py-4 space-y-3 lg:border-l border-slate-100 dark:border-slate-800 lg:bg-slate-50/70 dark:lg:bg-slate-950/30">
                    <div class="hidden lg:flex items-center justify-between">
                        <h5 class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Barang Terpilih</h5>
                        <span class="min-w-[22px] px-1.5 py-0.5 rounded-full text-[11px] font-mono font-bold text-center"
                              :class="validItemCount > 0 ? 'bg-sky-600 text-white' : 'bg-slate-200 dark:bg-slate-700 text-slate-500'"
                              x-text="pickedUnitCount()"></span>
                    </div>
                    <x-warehouse.picked-items-list size="roomy">Klik chip SN / lot / roll di panel stok.</x-warehouse.picked-items-list>
                </div>
            </div>

            {{-- FOOTER (tetap) --}}
            <div class="shrink-0 px-4 sm:px-5 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] lg:pb-3 border-t border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 grid grid-cols-[auto_1fr] lg:flex lg:justify-end gap-2">
                <button type="button" @click="openMobileSheet = false"
                        class="min-h-[46px] lg:min-h-[40px] px-4 rounded-lg text-sm font-semibold text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 cursor-pointer">
                    Selesai
                </button>
                <button type="submit" form="{{ $form }}"
                        :disabled="!isFormValid"
                        @click="openMobileSheet = false"
                        class="min-h-[46px] lg:min-h-[40px] inline-flex items-center justify-center gap-2 px-5 bg-sky-600 hover:bg-sky-700 disabled:bg-slate-200 dark:disabled:bg-slate-800 disabled:text-slate-400 text-white rounded-lg text-sm font-semibold transition-colors shadow-xs cursor-pointer disabled:cursor-not-allowed">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                    <span>{{ $submitLabel }}</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Bar bawah melayang (mobile/tablet saja) --}}
    <div class="lg:hidden fixed inset-x-3 bottom-[max(0.75rem,env(safe-area-inset-bottom))] z-40 pointer-events-none">
        <div class="bg-slate-900/95 backdrop-blur-md border border-slate-800 text-white rounded-xl shadow-xl p-2 flex items-center gap-2 max-w-lg mx-auto pointer-events-auto">
            <button type="button" @click="$dispatch('open-stock-sheet', { tab: validItemCount > 0 ? 'picked' : 'stock' })"
                    class="flex-1 min-w-0 min-h-[46px] flex items-center gap-3 pl-2 pr-1 text-left rounded-lg hover:bg-white/5 cursor-pointer">
                <span class="relative shrink-0 w-9 h-9 rounded-lg bg-sky-500/15 text-sky-300 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/></svg>
                    <span x-show="validItemCount > 0" x-cloak
                          class="absolute -top-1.5 -right-1.5 min-w-[18px] h-[18px] px-1 rounded-full bg-sky-500 text-white text-[10px] font-bold font-mono flex items-center justify-center"
                          x-text="pickedUnitCount()"></span>
                </span>
                <span class="min-w-0">
                    <span class="block text-sm font-semibold truncate"
                          x-text="validItemCount > 0 ? (totalSnCount + ' SN • ' + validItemCount + ' jenis barang') : 'Pilih barang dari stok'"></span>
                    <span class="block text-[11px] text-slate-400 truncate" x-text="{{ $barHint }}"></span>
                </span>
                <svg class="ml-auto w-4 h-4 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/></svg>
            </button>

            <button type="submit" form="{{ $form }}"
                    :disabled="!isFormValid"
                    class="min-h-[46px] px-4 bg-sky-600 hover:bg-sky-700 disabled:bg-slate-800 disabled:text-slate-500 text-white font-semibold rounded-lg text-sm flex items-center justify-center gap-1.5 transition-colors active:scale-95 shrink-0 cursor-pointer disabled:cursor-not-allowed">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                <span>{{ $submitShort }}</span>
            </button>
        </div>
    </div>
</div>
