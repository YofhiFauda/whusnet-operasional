@props([
    // Nama properti Alpine yang menyimpan POP sumber stok (`fromPopId` di
    // Transfer, `cabangPopId` di Serah Teknisi). Dipakai cuma untuk gerbang
    // "sudah pilih gudang?" — data stok tetap dari `availableStockItems`.
    'pop' => 'fromPopId',
    'emptyPopText' => 'Pilih gudang terlebih dahulu untuk memuat stok fisik.',
    'emptyStockText' => 'Stok kosong di gudang ini.',
])

@php
    $chipBase = 'min-h-[36px] px-2.5 py-1.5 text-[11px] rounded-lg';
@endphp

{{--
    Picker stok bersama Transfer Antar Gudang & Serah Terima Teknisi —
    hanya dirender di dalam stock-picker-sheet (bottom sheet mobile /
    dialog lebar desktop). Dulu ada varian "sidebar" untuk panel sempit di
    kolom kanan desktop; dihapus setelah desktop pindah ke dialog.

    Kenapa chip TOGGLE dan punya state "terpilih": dulu tap chip cuma
    `$dispatch('pick-…')` dan (di mobile) drawer langsung ditutup — chip
    tidak berubah tampilan, user tidak tahu SN sudah masuk (keluhan lapangan
    2026-11). Sekarang chip terpilih berwarna penuh + centang, kartunya
    ditandai "n dipilih", tap kedua membatalkan.

    Stok banyak (puluhan jenis × ratusan SN) tidak boleh jadi dinding chip:
    - kartu barang berupa accordion (isi via x-if); terbuka otomatis hanya
      bila jenis barang ≤ 3, sedang mencari, atau barang itu sudah dipilih;
    - chip per barang dibatasi (chipLimit) + "Tampilkan semua"; chip yang
      SUDAH dipilih selalu tampil walau di luar batas;
    - filter kategori (dari itemOptions, tanpa ubah endpoint stok);
    - pencarian + filter menempel di atas (sticky) saat list discroll.

    Bergantung pada `warehouseStockPickerMixin()` (di bawah) yang di-merge ke
    manager halaman, plus `filteredAvailableStock`, `filteredSerials/Lots/
    Rolls`, `onPickSerial/Qty/Roll` milik manager.
--}}
<div {{ $attributes->class(['space-y-3']) }}
     x-data="{ pickerCategory: '', pickerOpen: {}, pickerShowAll: {}, chipLimit: 12 }"
     x-init="$watch('stockSearchQuery', () => { pickerOpen = {} }); $watch('availableStockItems', () => { pickerCategory = ''; pickerOpen = {}; pickerShowAll = {} })">

    {{-- Pencarian + filter kategori (sticky; -top-4/-mx-4 menetralkan padding panel induk) --}}
    <div x-show="{{ $pop }} && availableStockItems.length > 0"
         class="space-y-2 sticky -top-4 z-10 -mx-4 sm:-mx-5 px-4 sm:px-5 py-2 bg-white/95 dark:bg-slate-900/95 backdrop-blur-sm border-b border-slate-100 dark:border-slate-800">
        <div class="relative">
            <input type="text" x-model.debounce.200ms="stockSearchQuery" placeholder="Cari nama barang, SKU, SN, lot, atau roll..."
                   class="w-full min-h-[44px] text-sm font-medium pl-8 pr-8 py-2.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-colors">
            <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-3.5 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
            </svg>
            <button type="button" x-show="stockSearchQuery.length > 0" @click="stockSearchQuery = ''"
                    class="absolute right-2 top-2.5 p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-md transition-colors cursor-pointer"
                    title="Hapus pencarian">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Filter kategori: geser horizontal, tidak membungkus jadi banyak baris --}}
        <div x-show="stockCategories().length > 1" class="flex gap-1.5 overflow-x-auto -mx-1 px-1 pb-1">
            <button type="button" @click="pickerCategory = ''"
                    :class="pickerCategory === '' ? 'bg-slate-800 dark:bg-slate-100 text-white dark:text-slate-900 border-slate-800 dark:border-slate-100' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
                    class="shrink-0 min-h-[32px] px-3 inline-flex items-center gap-1 rounded-full border text-xs font-semibold whitespace-nowrap cursor-pointer">
                Semua <span class="font-mono opacity-70" x-text="filteredAvailableStock.length"></span>
            </button>
            <template x-for="cat in stockCategories()" :key="cat.name">
                <button type="button" @click="pickerCategory = pickerCategory === cat.name ? '' : cat.name"
                        :class="pickerCategory === cat.name ? 'bg-slate-800 dark:bg-slate-100 text-white dark:text-slate-900 border-slate-800 dark:border-slate-100' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
                        class="shrink-0 min-h-[32px] px-3 inline-flex items-center gap-1 rounded-full border text-xs font-semibold whitespace-nowrap cursor-pointer">
                    <span x-text="cat.name"></span>
                    <span class="font-mono opacity-70" x-text="cat.count"></span>
                </button>
            </template>
        </div>

        <div class="flex items-center justify-between gap-2 text-[11px] text-slate-500 dark:text-slate-400">
            <span>Ketuk chip untuk <strong class="text-sky-600 dark:text-sky-400">pilih</strong>, ketuk lagi untuk <strong class="text-rose-600 dark:text-rose-400">batal</strong>.</span>
            <button type="button" x-show="pickerItemsFor(pickerCategory).length > 1"
                    @click="pickerOpen = setAllPickerOpen(pickerItemsFor(pickerCategory), ! pickerItemsFor(pickerCategory).every(i => isPickerItemOpen(i, pickerOpen)))"
                    class="shrink-0 font-semibold text-sky-600 dark:text-sky-400 hover:underline cursor-pointer"
                    x-text="pickerItemsFor(pickerCategory).every(i => isPickerItemOpen(i, pickerOpen)) ? 'Lipat semua' : 'Buka semua'"></button>
        </div>
    </div>

    {{-- Belum pilih gudang --}}
    <template x-if="!{{ $pop }}">
        <p class="py-6 text-center text-xs text-slate-400">{{ $emptyPopText }}</p>
    </template>

    {{-- Loading --}}
    <template x-if="{{ $pop }} && loadingStock">
        <div class="py-6 text-center text-slate-400 flex items-center justify-center gap-2">
            <svg class="w-4 h-4 animate-spin text-sky-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            <span class="text-xs">Memuat data stok...</span>
        </div>
    </template>

    {{-- Gudang kosong --}}
    <template x-if="{{ $pop }} && !loadingStock && availableStockItems.length === 0">
        <p class="py-6 text-center text-xs text-slate-400 italic">{{ $emptyStockText }}</p>
    </template>

    {{-- Pencarian / kategori tanpa hasil --}}
    <template x-if="{{ $pop }} && !loadingStock && availableStockItems.length > 0 && pickerItemsFor(pickerCategory).length === 0">
        <div class="py-4 px-3 text-center rounded-lg border border-dashed border-slate-200 dark:border-slate-700/80 bg-slate-50/50 dark:bg-slate-900/40">
            <p class="text-xs font-semibold text-slate-700 dark:text-slate-300">Tidak ada stok yang cocok</p>
            <p class="text-[11px] text-slate-400 mt-0.5" x-show="stockSearchQuery">Tidak ditemukan untuk "<span class="font-mono text-sky-600 dark:text-sky-400" x-text="stockSearchQuery"></span>"</p>
            <button type="button" @click="stockSearchQuery = ''; pickerCategory = ''" class="mt-2 text-[11px] font-semibold text-sky-600 hover:text-sky-700 dark:text-sky-400 dark:hover:text-sky-300 underline cursor-pointer">
                Reset Pencarian
            </button>
        </div>
    </template>

    {{-- Daftar kartu barang (accordion) --}}
    <div x-show="{{ $pop }} && !loadingStock && pickerItemsFor(pickerCategory).length > 0"
         class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 items-start">
        <template x-for="item in pickerItemsFor(pickerCategory)" :key="item.item_id">
            <div class="rounded-lg border transition-colors overflow-hidden"
                 :class="pickedCountFor(item) > 0
                    ? 'border-sky-400 dark:border-sky-600 bg-sky-50/70 dark:bg-sky-950/30 ring-1 ring-sky-400/30'
                    : 'border-slate-200/80 dark:border-slate-700/60 bg-slate-50/70 dark:bg-slate-900/40'">

                {{-- Header kartu = tombol buka/lipat --}}
                <button type="button"
                        @click="pickerOpen = { ...pickerOpen, [item.item_id]: ! isPickerItemOpen(item, pickerOpen) }"
                        :aria-expanded="isPickerItemOpen(item, pickerOpen)"
                        class="w-full p-3 min-h-[52px] flex items-center justify-between gap-2 text-left cursor-pointer">
                    <div class="min-w-0">
                        <p class="text-sm leading-snug font-semibold text-slate-800 dark:text-slate-200" x-text="item.name"></p>
                        <p class="text-[10px] font-mono text-slate-400 truncate">
                            <span x-show="item.code" x-text="item.code + ' · '"></span><span x-text="availableLabel(item)"></span>
                        </p>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                        <span x-show="pickedCountFor(item) > 0" x-cloak
                              class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-sky-600 text-white">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                            <span x-text="pickedCountFor(item) + ' dipilih'"></span>
                        </span>
                        <svg class="w-4 h-4 text-slate-400 transition-transform" :class="isPickerItemOpen(item, pickerOpen) && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                </button>

                {{-- Isi kartu: x-if (bukan x-show) supaya chip kartu terlipat
                     tidak ikut dirender — ini yang menjaga drawer tetap ringan. --}}
                <template x-if="isPickerItemOpen(item, pickerOpen)">
                    <div class="space-y-2 px-3 pb-3">

                        {{-- Chip SN --}}
                        <template x-if="item.serials && item.serials.length > 0">
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="sn in limitChips(item, 'serial', pickerShowAll[item.item_id], chipLimit)" :key="sn">
                                    <button type="button" @click="toggleSerial(item.item_id, sn)"
                                            :aria-pressed="isSerialPicked(item.item_id, sn)"
                                            :class="isSerialPicked(item.item_id, sn)
                                                ? 'bg-sky-600 border-sky-600 text-white shadow-sm'
                                                : 'bg-white dark:bg-slate-800 border-sky-200 dark:border-sky-800 text-sky-700 dark:text-sky-300 hover:border-sky-400'"
                                            class="{{ $chipBase }} inline-flex items-center gap-1 font-mono font-bold border transition-colors cursor-pointer active:scale-95">
                                        <svg x-show="isSerialPicked(item.item_id, sn)" class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                        <svg x-show="!isSerialPicked(item.item_id, sn)" class="w-3 h-3 shrink-0 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                        <span x-text="sn"></span>
                                    </button>
                                </template>
                            </div>
                        </template>

                        {{-- Chip Lot / Qty --}}
                        <template x-if="item.lots && item.lots.length > 0">
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="lot in limitChips(item, 'lot', pickerShowAll[item.item_id], chipLimit)" :key="lot.lot_no ?? '-'">
                                    <button type="button" @click="toggleLot(item.item_id, lot)"
                                            :aria-pressed="isLotPicked(item.item_id, lot.lot_no)"
                                            :class="isLotPicked(item.item_id, lot.lot_no)
                                                ? 'bg-slate-800 dark:bg-slate-200 border-slate-800 dark:border-slate-200 text-white dark:text-slate-900 shadow-sm'
                                                : 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:border-slate-400'"
                                            class="{{ $chipBase }} inline-flex items-center gap-1 font-mono font-bold border transition-colors cursor-pointer active:scale-95">
                                        <svg x-show="isLotPicked(item.item_id, lot.lot_no)" class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                        <span><span x-show="lot.lot_no" x-text="lot.lot_no + ': '"></span><span x-text="lot.qty"></span> <span x-text="item.unit"></span></span>
                                    </button>
                                </template>
                            </div>
                        </template>

                        {{-- Chip Roll --}}
                        <template x-if="item.rolls && item.rolls.length > 0">
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="roll in limitChips(item, 'roll', pickerShowAll[item.item_id], chipLimit)" :key="roll.roll_code">
                                    <button type="button" @click="toggleRoll(item.item_id, roll.roll_code)"
                                            :aria-pressed="isRollPicked(item.item_id, roll.roll_code)"
                                            :class="isRollPicked(item.item_id, roll.roll_code)
                                                ? 'bg-amber-600 border-amber-600 text-white shadow-sm'
                                                : 'bg-white dark:bg-slate-800 border-amber-200 dark:border-amber-800 text-amber-700 dark:text-amber-300 hover:border-amber-400'"
                                            class="{{ $chipBase }} inline-flex items-center gap-1 font-mono font-bold border transition-colors cursor-pointer active:scale-95">
                                        <svg x-show="isRollPicked(item.item_id, roll.roll_code)" class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                        <span><span x-text="roll.roll_code"></span> <span class="opacity-80" x-text="'(' + roll.length_remaining + ' m)'"></span></span>
                                    </button>
                                </template>
                            </div>
                        </template>

                        {{-- Tampilkan semua / lebih sedikit --}}
                        <template x-if="hiddenChipCount(item, chipLimit) > 0">
                            <button type="button"
                                    @click="pickerShowAll = { ...pickerShowAll, [item.item_id]: ! pickerShowAll[item.item_id] }"
                                    class="w-full min-h-[38px] rounded-lg border border-dashed border-slate-300 dark:border-slate-600 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-800 cursor-pointer"
                                    x-text="pickerShowAll[item.item_id] ? 'Tampilkan lebih sedikit' : ('Tampilkan semua (+' + hiddenChipCount(item, chipLimit) + ' lagi)')"></button>
                        </template>
                    </div>
                </template>
            </div>
        </template>
    </div>
</div>

@once
@push('scripts')
<script>
/**
 * Method bersama picker stok (Transfer & Serah Teknisi). Di-merge ke objek
 * manager lewat Object.assign(manager, warehouseStockPickerMixin()) — JANGAN
 * dibalik (mixin sebagai target), karena Object.assign mengevaluasi getter
 * sumber jadi nilai mati dan `isFormValid` dkk. berhenti reaktif.
 * Karena itu mixin ini juga sengaja tanpa getter.
 */
function warehouseStockPickerMixin() {
    return {
        splitCodes(text) {
            return (text || '').split(/[\r\n,]+/).map(s => s.trim()).filter(s => s.length > 0);
        },

        rowsForItem(itemId) {
            return this.rows.filter(r => String(r.item_id) === String(itemId));
        },

        isSerialPicked(itemId, sn) {
            return this.rowsForItem(itemId).some(r => this.splitCodes(r.serial_numbers).includes(sn));
        },

        isRollPicked(itemId, rollCode) {
            return this.rowsForItem(itemId).some(r => this.splitCodes(r.roll_codes).includes(rollCode));
        },

        isLotPicked(itemId, lotNo) {
            return this.rowsForItem(itemId).some(r => (r.lot_no || '') === (lotNo || '') && parseFloat(r.qty) > 0);
        },

        pickedCountFor(item) {
            return this.rowsForItem(item.item_id).reduce((sum, r) => {
                if (r.tracking_type === 'serialized') return sum + this.splitCodes(r.serial_numbers).length;
                if (r.tracking_type === 'roll') return sum + this.splitCodes(r.roll_codes).length;
                return sum + (parseFloat(r.qty) > 0 ? 1 : 0);
            }, 0);
        },

        // Baris yang kosong setelah chip terakhirnya dibatalkan dibuang,
        // kecuali itu satu-satunya baris — form selalu menyisakan 1 baris
        // kosong supaya user tetap bisa isi manual.
        releaseRow(row) {
            const index = this.rows.indexOf(row);
            if (index === -1) return;

            if (this.rows.length > 1) {
                this.rows.splice(index, 1);
                return;
            }

            Object.assign(row, { item_id: '', category_id: '', tracking_type: '', unit: '', qty: '', lot_no: '', serial_numbers: '', roll_codes: '' });
        },

        unpickCode(itemId, field, code) {
            const row = this.rowsForItem(itemId).find(r => this.splitCodes(r[field]).includes(code));
            if (! row) return;

            const remaining = this.splitCodes(row[field]).filter(c => c !== code);
            row[field] = remaining.join('\n');

            if (remaining.length === 0) {
                this.releaseRow(row);
            }
        },

        toggleSerial(itemId, sn) {
            if (this.isSerialPicked(itemId, sn)) {
                this.unpickCode(itemId, 'serial_numbers', sn);
            } else {
                this.onPickSerial({ itemId: itemId, serialNumber: sn });
            }
            navigator.vibrate?.(8);
        },

        toggleRoll(itemId, rollCode) {
            if (this.isRollPicked(itemId, rollCode)) {
                this.unpickCode(itemId, 'roll_codes', rollCode);
            } else {
                this.onPickRoll({ itemId: itemId, rollCode: rollCode });
            }
            navigator.vibrate?.(8);
        },

        toggleLot(itemId, lot) {
            const row = this.rowsForItem(itemId).find(r => (r.lot_no || '') === (lot.lot_no || '') && parseFloat(r.qty) > 0);
            if (row) {
                this.releaseRow(row);
            } else {
                this.onPickQty({ itemId: itemId, lotNo: lot.lot_no, qty: lot.qty });
            }
            navigator.vibrate?.(8);
        },

        // ---- Navigasi stok banyak (kategori, accordion, batas chip) ----
        // Kategori diambil dari itemOptions (payload available-stock tidak
        // membawa kategori) supaya endpoint stok tidak perlu diubah.

        stockCategoryOf(item) {
            const opt = this.itemOptions.find(o => String(o.id) === String(item.item_id));
            return (opt && opt.category_name) || 'Lainnya';
        },

        stockCategories() {
            const counts = {};
            this.filteredAvailableStock.forEach(item => {
                const name = this.stockCategoryOf(item);
                counts[name] = (counts[name] || 0) + 1;
            });
            return Object.keys(counts).sort().map(name => ({ name: name, count: counts[name] }));
        },

        pickerItemsFor(category) {
            if (! category) return this.filteredAvailableStock;
            return this.filteredAvailableStock.filter(item => this.stockCategoryOf(item) === category);
        },

        availableLabel(item) {
            if (Array.isArray(item.serials) && item.serials.length > 0) return item.serials.length + ' SN tersedia';
            if (Array.isArray(item.rolls) && item.rolls.length > 0) return item.rolls.length + ' roll tersedia';
            if (Array.isArray(item.lots) && item.lots.length > 0) return item.lots.length + ' lot tersedia';
            return '';
        },

        // Pilihan eksplisit user (buka/lipat) selalu menang; tanpa itu kartu
        // terbuka hanya kalau jenis barang sedikit, sedang mencari, atau
        // barang itu sudah punya pilihan.
        isPickerItemOpen(item, openMap) {
            const explicit = openMap ? openMap[item.item_id] : undefined;
            if (explicit !== undefined) return explicit;
            if ((this.stockSearchQuery || '').trim() !== '') return true;
            if (this.pickedCountFor(item) > 0) return true;
            return this.filteredAvailableStock.length <= 3;
        },

        setAllPickerOpen(items, open) {
            const map = {};
            items.forEach(item => { map[item.item_id] = open; });
            return map;
        },

        chipSource(item, kind) {
            if (kind === 'serial') return this.filteredSerials(item);
            if (kind === 'roll') return this.filteredRolls(item);
            return this.filteredLots(item);
        },

        chipPicked(item, kind, entry) {
            if (kind === 'serial') return this.isSerialPicked(item.item_id, entry);
            if (kind === 'roll') return this.isRollPicked(item.item_id, entry.roll_code);
            return this.isLotPicked(item.item_id, entry.lot_no);
        },

        // Chip yang sudah dipilih selalu ikut tampil walau di luar batas,
        // supaya pilihan tidak "hilang" saat daftar dipersingkat.
        limitChips(item, kind, showAll, limit) {
            const list = this.chipSource(item, kind);
            if (showAll || list.length <= limit) return list;
            return list.filter((entry, i) => i < limit || this.chipPicked(item, kind, entry));
        },

        hiddenChipCount(item, limit) {
            return ['serial', 'lot', 'roll'].reduce((sum, kind) => {
                const list = this.chipSource(item, kind);
                return sum + (list.length - this.limitChips(item, kind, false, limit).length);
            }, 0);
        },

        // Jumlah "pilihan": tiap SN/roll dihitung 1, baris reguler/lot dihitung 1.
        pickedUnitCount() {
            return this.pickedRows().reduce((sum, p) => sum + (p.codes.length || 1), 0);
        },

        // Ringkasan untuk tab "Terpilih" di drawer mobile.
        pickedRows() {
            return this.rows
                .filter(r => r.item_id)
                .map(r => {
                    const opt = this.itemOptions.find(o => String(o.id) === String(r.item_id));
                    const codes = r.tracking_type === 'serialized'
                        ? this.splitCodes(r.serial_numbers)
                        : (r.tracking_type === 'roll' ? this.splitCodes(r.roll_codes) : []);
                    return {
                        row: r,
                        name: opt ? opt.name : 'Barang',
                        code: opt ? opt.code : '',
                        field: r.tracking_type === 'serialized' ? 'serial_numbers' : 'roll_codes',
                        codes: codes,
                    };
                });
        },
    };
}
</script>
@endpush
@endonce
