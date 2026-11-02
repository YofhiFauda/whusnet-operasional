{{--
    Drawer rincian barang per gudang (spec §3 Drawer) — menggantikan modal
    Serial Number & modal Roll khusus di papan. Satu komponen untuk semua
    baris (dan sel matriks Task 6): dibuka lewat event window
    `open-stock-drawer` dengan payload dari partial `_detail`.

    Data:
    - SN & roll di-fetch dari `detail.serialsUrl` / `detail.rollsUrl` — URL
      lengkap (termasuk pop_id & item_id) dirender server lewat route();
      endpoint itu sendiri menjalankan assertPopInScope() (spec §8), jadi
      payload yang diutak-atik di klien tetap ditolak 403.
    - QUANTITY: rincian per tingkat harga sudah ada di payload, tanpa fetch.

    Yang SENGAJA tidak ada: barang di teknisi / dalam pengiriman (spec §1) dan
    istilah "lot" — tingkat harga ditulis "Harga lama"/"Harga baru".

    Aksesibilitas: role="dialog" + aria-modal, x-trap (plugin focus) mengunci
    fokus di panel dan mengembalikannya ke pemicu saat ditutup; Esc menutup.
--}}
<div x-data="stockDrawer()" @open-stock-drawer.window="show($event.detail)">
    <template x-teleport="body">
        <div x-show="open" x-cloak
             class="fixed inset-0 z-[9998] overflow-hidden"
             role="dialog" aria-modal="true" aria-labelledby="stock-drawer-title"
             @keydown.escape.window="open && close()">
            <div x-show="open" @click="close()"
                 x-transition:enter="transition-opacity ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                 x-transition:leave="transition-opacity ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                 class="absolute inset-0 bg-slate-900/60 backdrop-blur-xs" aria-hidden="true"></div>

            <div class="absolute inset-y-0 right-0 flex max-w-full sm:pl-10 pointer-events-none">
                <div x-show="open" x-trap.noscroll="open"
                     x-transition:enter="transform transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                     x-transition:leave="transform transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
                     class="pointer-events-auto w-screen max-w-2xl h-full flex flex-col bg-white dark:bg-slate-900 border-l border-slate-200 dark:border-slate-800 shadow-2xl">

                    {{-- Header: gudang · kategori, nama barang --}}
                    <div class="px-4 sm:px-5 py-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/60 flex items-start justify-between gap-3 shrink-0">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-1.5 text-[11px] text-slate-500 dark:text-slate-400">
                                <span class="font-bold text-slate-700 dark:text-slate-200" x-text="detail.popName"></span>
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold border"
                                      :class="detail.popType === 'pusat' ? 'bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-300 border-sky-200 dark:border-sky-800' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
                                      x-text="(detail.popType || '').toUpperCase()"></span>
                                <span aria-hidden="true">·</span>
                                <span x-text="detail.categoryName || 'Tanpa Kategori'"></span>
                            </div>
                            <h2 id="stock-drawer-title" class="mt-1 text-base font-bold text-slate-900 dark:text-slate-100 leading-snug" x-text="detail.itemName"></h2>
                            <p class="font-mono text-[11px] text-slate-400 dark:text-slate-500" x-text="detail.itemCode"></p>
                        </div>
                        <button type="button" @click="close()" aria-label="Tutup rincian (Esc)"
                                class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-200/60 dark:hover:bg-slate-700/60 transition-colors cursor-pointer shrink-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    {{-- Strip ringkas: Tersedia, Kondisi (atau padanannya), Nilai --}}
                    <dl class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 p-4 bg-slate-100/70 dark:bg-slate-950/40 border-b border-slate-200 dark:border-slate-800 shrink-0">
                        <div class="p-2.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                            <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Tersedia</dt>
                            <dd class="mt-0.5 flex flex-wrap items-baseline gap-1">
                                <span class="text-base font-bold font-mono tabular-nums" :class="detail.isLow ? 'text-rose-600 dark:text-rose-400' : 'text-slate-900 dark:text-slate-100'" x-text="fmtNumber(detail.qty)"></span>
                                <span class="text-[11px] text-slate-500 dark:text-slate-400" x-text="detail.unit"></span>
                                <span x-show="detail.isLow" class="text-[11px] font-semibold text-rose-700 dark:text-rose-300">· Menipis</span>
                            </dd>
                        </div>
                        <div class="p-2.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                            <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400" x-text="secondTile().label"></dt>
                            <dd class="mt-0.5 text-xs font-semibold text-slate-800 dark:text-slate-100" x-text="secondTile().value"></dd>
                        </div>
                        <template x-if="totalValue() !== null">
                            <div class="p-2.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                                <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Nilai</dt>
                                <dd class="mt-0.5 text-base font-bold font-mono tabular-nums text-slate-900 dark:text-slate-100" x-text="fmtRupiah(totalValue())"></dd>
                            </div>
                        </template>
                    </dl>

                    {{-- Pencarian (SN & roll) + filter kondisi (SN) --}}
                    <div x-show="detail.tracking !== 'quantity'" class="px-4 py-3 border-b border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center gap-2 shrink-0">
                        <div class="relative flex-1">
                            <label for="stock-drawer-search" class="sr-only" x-text="detail.tracking === 'roll' ? 'Cari kode roll atau vendor' : 'Cari serial number'"></label>
                            <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                            <input id="stock-drawer-search" type="search" x-model.debounce.150ms="search" autocomplete="off"
                                   :placeholder="detail.tracking === 'roll' ? 'Cari kode roll atau vendor…' : 'Cari serial number…'"
                                   class="w-full h-9 pl-8 pr-3 text-xs border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:bg-white dark:focus:bg-slate-800 focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                        </div>
                        <div x-show="detail.tracking === 'serialized'">
                            <label for="stock-drawer-condition" class="sr-only">Filter kondisi</label>
                            <select id="stock-drawer-condition" x-model="condition"
                                    class="h-9 w-full sm:w-auto px-2 text-xs border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                <option value="all">Semua kondisi</option>
                                <option value="new">Baru</option>
                                <option value="used_unchecked">Bekas Belum Dicek</option>
                                <option value="used_checked">Bekas Sudah Dicek</option>
                                <option value="used_damaged">Bekas Rusak</option>
                            </select>
                        </div>
                    </div>

                    {{-- Tabel rincian --}}
                    <div class="flex-1 overflow-y-auto" aria-live="polite" :aria-busy="loading.toString()">
                        <div x-show="loading" class="py-12 flex items-center justify-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                            <svg class="w-4 h-4 animate-spin text-sky-500" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            <span>Memuat rincian…</span>
                        </div>

                        <div x-show="!loading && error" class="m-4 p-3 rounded-lg border border-rose-200 dark:border-rose-800 bg-rose-50 dark:bg-rose-950/40 text-xs text-rose-800 dark:text-rose-300 flex items-center justify-between gap-3">
                            <span x-text="error"></span>
                            <button type="button" x-show="canRetry" @click="reload()" class="shrink-0 px-2.5 py-1 rounded-md bg-white dark:bg-slate-900 border border-rose-200 dark:border-rose-800 font-semibold cursor-pointer hover:bg-rose-100 dark:hover:bg-rose-900/40">Coba lagi</button>
                        </div>

                        {{-- SN --}}
                        <template x-if="!loading && !error && detail.tracking === 'serialized'">
                            <div>
                                <p x-show="serials.length === 0" class="py-12 text-center text-xs italic text-slate-500 dark:text-slate-400">Tidak ada SN berstatus tersedia di gudang ini.</p>
                                <p x-show="serials.length > 0 && filteredSerials().length === 0" class="py-12 text-center text-xs italic text-slate-500 dark:text-slate-400">Tidak ada SN yang cocok dengan pencarian/filter.</p>
                                <div x-show="filteredSerials().length > 0" class="overflow-x-auto">
                                    <table class="min-w-full text-xs text-left">
                                        <thead class="sticky top-0 bg-slate-50 dark:bg-slate-800 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                                            <tr>
                                                <th scope="col" class="px-4 py-2.5">Serial number</th>
                                                <th scope="col" class="px-4 py-2.5">Kondisi</th>
                                                <th scope="col" class="px-4 py-2.5 text-right">Harga beli</th>
                                                <th scope="col" class="px-4 py-2.5 text-right">Diterima</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                            <template x-for="serial in filteredSerials()" :key="serial.serial_number">
                                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                                    <td class="px-4 py-2.5 font-mono font-bold text-slate-800 dark:text-slate-200 select-all" x-text="serial.serial_number"></td>
                                                    <td class="px-4 py-2.5">
                                                        {{-- Warna = token .badge-* yang sama dengan x-warehouse.condition-badge --}}
                                                        <span class="badge" :class="conditionBadge(serial)" x-text="conditionLabel(serial)"></span>
                                                    </td>
                                                    <td class="px-4 py-2.5 text-right font-mono text-slate-700 dark:text-slate-200" x-text="serial.unit_price_snapshot !== null ? fmtRupiah(serial.unit_price_snapshot) : '—'"></td>
                                                    <td class="px-4 py-2.5 text-right text-slate-500 dark:text-slate-400 whitespace-nowrap" x-text="serial.received_at || '—'"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                                <p x-show="serials.length >= limit" class="px-4 py-2 text-[11px] text-slate-500 dark:text-slate-400">Menampilkan 200 SN pertama — gunakan Lacak Barang untuk mencari satu SN tertentu.</p>
                            </div>
                        </template>

                        {{-- Roll --}}
                        <template x-if="!loading && !error && detail.tracking === 'roll'">
                            <div>
                                <p x-show="rolls.length === 0" class="py-12 text-center text-xs italic text-slate-500 dark:text-slate-400">Tidak ada roll berstatus tersedia di gudang ini.</p>
                                <p x-show="rolls.length > 0 && filteredRolls().length === 0" class="py-12 text-center text-xs italic text-slate-500 dark:text-slate-400">Tidak ada roll yang cocok dengan pencarian.</p>
                                <div x-show="filteredRolls().length > 0" class="overflow-x-auto">
                                    <table class="min-w-full text-xs text-left">
                                        <thead class="sticky top-0 bg-slate-50 dark:bg-slate-800 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                                            <tr>
                                                <th scope="col" class="px-4 py-2.5">Kode roll</th>
                                                <th scope="col" class="px-4 py-2.5 text-right">Sisa meter</th>
                                                <th scope="col" class="px-4 py-2.5">Vendor</th>
                                                <th scope="col" class="px-4 py-2.5 text-right">Diterima</th>
                                                <th scope="col" class="px-4 py-2.5 text-right">Nilai</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                            <template x-for="roll in filteredRolls()" :key="roll.roll_code">
                                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                                    <td class="px-4 py-2.5 font-mono font-bold text-slate-800 dark:text-slate-200 select-all" x-text="roll.roll_code"></td>
                                                    <td class="px-4 py-2.5 text-right font-mono whitespace-nowrap">
                                                        <span class="font-semibold text-slate-900 dark:text-slate-100" x-text="fmtNumber(roll.length_remaining)"></span>
                                                        <span class="text-slate-400 dark:text-slate-500" x-text="'/ ' + fmtNumber(roll.length_total) + ' m'"></span>
                                                    </td>
                                                    <td class="px-4 py-2.5 text-slate-600 dark:text-slate-300" x-text="roll.vendor || '—'"></td>
                                                    <td class="px-4 py-2.5 text-right text-slate-500 dark:text-slate-400 whitespace-nowrap" x-text="roll.received_at || '—'"></td>
                                                    <td class="px-4 py-2.5 text-right font-mono text-slate-700 dark:text-slate-200 whitespace-nowrap" x-text="roll.total_value !== null ? fmtRupiah(roll.total_value) : '—'"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                                <p x-show="rolls.length >= limit" class="px-4 py-2 text-[11px] text-slate-500 dark:text-slate-400">Menampilkan 200 roll pertama — gunakan Lacak Barang untuk mencari satu roll tertentu.</p>
                            </div>
                        </template>

                        {{-- QUANTITY: satu baris per tingkat harga, Opname masing-masing --}}
                        <template x-if="detail.tracking === 'quantity'">
                            <div>
                                <p x-show="detail.priceTiers.length === 0" class="py-12 text-center text-xs italic text-slate-500 dark:text-slate-400">Belum ada rincian harga untuk barang ini di gudang ini.</p>
                                <div x-show="detail.priceTiers.length > 0" class="overflow-x-auto">
                                    <table class="min-w-full text-xs text-left">
                                        <thead class="bg-slate-50 dark:bg-slate-800 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                                            <tr>
                                                <th scope="col" class="px-4 py-2.5">Tingkat</th>
                                                <th scope="col" class="px-4 py-2.5 text-right">Harga</th>
                                                <th scope="col" class="px-4 py-2.5 text-right">Jumlah</th>
                                                <th scope="col" class="px-4 py-2.5 text-right"><span class="sr-only">Aksi</span></th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                            <template x-for="(tier, index) in detail.priceTiers" :key="index">
                                                <tr>
                                                    <td class="px-4 py-2.5 font-semibold text-slate-800 dark:text-slate-200" x-text="tier.label"></td>
                                                    <td class="px-4 py-2.5 text-right font-mono text-slate-700 dark:text-slate-200" x-text="tier.price !== null ? fmtRupiah(tier.price) : '—'"></td>
                                                    <td class="px-4 py-2.5 text-right font-mono whitespace-nowrap"><span class="font-semibold text-slate-900 dark:text-slate-100" x-text="fmtNumber(tier.qty)"></span> <span class="text-slate-500 dark:text-slate-400" x-text="detail.unit"></span></td>
                                                    <td class="px-4 py-2.5 text-right">
                                                        <template x-if="tier.opnameUrl">
                                                            <a :href="tier.opnameUrl" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/40 text-[11px] font-semibold text-amber-800 dark:text-amber-300 hover:bg-amber-100 dark:hover:bg-amber-900/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500">Opname</a>
                                                        </template>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Footer: menu aksi yang sama dengan baris --}}
                    <div class="px-4 sm:px-5 py-3 border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/60 shrink-0">
                        @include('warehouse.stock.board._actions', ['mode' => 'footer'])
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

@push('scripts')
<script>
/**
 * Drawer rincian papan Kelola Stok. `detail` = payload partial `_detail`
 * (semua URL dari server). SN/roll di-fetch per pembukaan; token permintaan
 * membuang respons basi bila pengguna berpindah baris sebelum fetch selesai.
 */
function stockDrawer() {
    const emptyDetail = () => ({
        popId: null, itemId: null, itemName: '', itemCode: '', popName: '', popType: '', categoryName: null,
        tracking: 'quantity', qty: 0, unit: '', rollCount: null, isLow: false, conditionLabel: '',
        serialsUrl: null, rollsUrl: null, priceTiers: [], opnamePerTier: false,
        actions: { transferUrl: null, requestStockUrl: null, issueUrl: null, adjustUrl: null, thresholdUrl: null },
    });

    return {
        open: false,
        detail: emptyDetail(),
        loading: false,
        error: null,
        canRetry: false,
        serials: [],
        rolls: [],
        search: '',
        condition: 'all',
        limit: 200, // = batas endpoint serials()/rolls()
        token: 0,

        show(detail) {
            this.detail = Object.assign(emptyDetail(), detail);
            this.serials = [];
            this.rolls = [];
            this.search = '';
            this.condition = 'all';
            this.error = null;
            this.open = true;
            this.reload();
        },
        close() {
            this.open = false;
            this.token++; // respons yang masih di jalan tidak lagi mengisi drawer
            this.loading = false;
        },
        reload() {
            if (this.detail.serialsUrl) {
                this.load(this.detail.serialsUrl, 'serials');
            } else if (this.detail.rollsUrl) {
                this.load(this.detail.rollsUrl, 'rolls');
            }
        },
        load(url, key) {
            const token = ++this.token;
            this.loading = true;
            this.error = null;
            fetch(url, { headers: { 'Accept': 'application/json' } })
                .then((response) => {
                    if (!response.ok) {
                        const err = new Error('http');
                        err.status = response.status;
                        throw err;
                    }
                    return response.json();
                })
                .then((data) => {
                    if (token === this.token) {
                        this[key] = Array.isArray(data[key]) ? data[key] : [];
                    }
                })
                .catch((err) => {
                    if (token !== this.token) {
                        return;
                    }
                    const forbidden = err.status === 403;
                    this.canRetry = !forbidden;
                    this.error = forbidden ? 'Anda tidak punya akses ke gudang ini.' : 'Gagal memuat rincian. Periksa koneksi lalu coba lagi.';
                })
                .finally(() => {
                    if (token === this.token) {
                        this.loading = false;
                    }
                });
        },

        conditionKey(serial) {
            if (serial.condition === 'used_damaged') return 'used_damaged';
            if (serial.condition === 'used_good') return serial.condition_checked ? 'used_checked' : 'used_unchecked';
            return 'new';
        },
        conditionLabel(serial) {
            return { new: 'Baru', used_unchecked: 'Bekas Belum Dicek', used_checked: 'Bekas Sudah Dicek', used_damaged: 'Bekas Rusak' }[this.conditionKey(serial)];
        },
        conditionBadge(serial) {
            return { new: 'badge-success', used_unchecked: 'badge-info', used_checked: 'badge-warning', used_damaged: 'badge-error' }[this.conditionKey(serial)];
        },
        filteredSerials() {
            const term = this.search.trim().toLowerCase();
            return this.serials.filter((serial) =>
                (this.condition === 'all' || this.conditionKey(serial) === this.condition)
                && (term === '' || String(serial.serial_number).toLowerCase().includes(term)));
        },
        filteredRolls() {
            const term = this.search.trim().toLowerCase();
            return this.rolls.filter((roll) => term === ''
                || String(roll.roll_code).toLowerCase().includes(term)
                || String(roll.vendor || '').toLowerCase().includes(term));
        },

        secondTile() {
            if (this.detail.tracking === 'serialized') {
                return { label: 'Kondisi', value: this.detail.conditionLabel || '—' };
            }
            if (this.detail.tracking === 'roll') {
                return { label: 'Jumlah roll', value: (this.detail.rollCount ?? 0) + ' roll' };
            }
            return { label: 'Tingkat harga', value: this.detail.priceTiers.length > 1 ? 'Harga lama & baru' : (this.detail.priceTiers.length === 1 ? '1 harga' : '—') };
        },
        /**
         * Nilai hanya bila SELURUH baris diketahui harganya dan daftar tidak
         * terpotong batas 200 — angka sebagian akan terbaca sebagai total.
         */
        totalValue() {
            const sum = (rows, pick) => {
                if (rows.length === 0 || rows.some((row) => pick(row) === null || pick(row) === undefined)) return null;
                return rows.reduce((total, row) => total + Number(pick(row)), 0);
            };
            if (this.loading || this.error) return null;
            if (this.detail.tracking === 'serialized') {
                return this.serials.length >= this.limit ? null : sum(this.serials, (serial) => serial.unit_price_snapshot);
            }
            if (this.detail.tracking === 'roll') {
                return this.rolls.length >= this.limit ? null : sum(this.rolls, (roll) => roll.total_value);
            }
            return sum(this.detail.priceTiers, (tier) => (tier.price === null ? null : tier.price * tier.qty));
        },

        fmtNumber(value) {
            return Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 });
        },
        fmtRupiah(value) {
            return 'Rp ' + Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: 0 });
        },
    };
}
</script>
@endpush
