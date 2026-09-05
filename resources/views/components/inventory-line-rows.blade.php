{{--
    Baris Repeatable Transfer/Issue/Receive (Whusnet Redesign)
    ===================================================
    Dipakai warehouse.transfers.create, warehouse.issues.create,
    warehouse.receive.create. Mode ditentukan tracking_type item yang dipilih.

    Props:
      $name      (string) — nama field array, mis. "lines" → lines[0][item_id]
      $items     (Collection<Item>) — master barang aktif, py tracking_type
      $withPrice (bool) — tampilkan input Harga Satuan per baris (khusus Receive).

    Kategori → Barang cascading dropdown (2026-09-04, laporan user: nyari 1
    barang dari daftar SEMUA item itu lama, apalagi di HP). `<select>` item
    lama nampung SEMUA barang aktif rata dalam satu daftar panjang — staf
    harus scroll/ketik cari sendiri. Sekarang dua langkah: pilih Kategori
    dulu (mempersempit), baru daftar Barang muncul TERFILTER ke kategori itu.
    Kategori `<select>` MURNI filter sisi klien — gak py `name`, gak ikut
    ke-submit, cuma `item_id` yang beneran dikirim ke server (sama kayak
    sebelumnya, gak ada perubahan kontrak backend).
--}}

@props(['name' => 'lines', 'items' => [], 'withPrice' => false])

@php
    $itemOptions = collect($items)->map(fn ($item) => [
        'id' => $item->id,
        'label' => "{$item->code} — {$item->name}",
        'tracking_type' => $item->tracking_type->value,
        'unit' => $item->unit,
        'category_id' => $item->item_category_id,
    ])->values();

    $categoryOptions = collect($items)
        ->pluck('category')
        ->filter()
        ->unique('id')
        ->sortBy('name')
        ->map(fn ($category) => ['id' => $category->id, 'name' => $category->name])
        ->values();
@endphp

<div x-data="inventoryLineRows(@js($itemOptions), @js($categoryOptions), '{{ $name }}')" @pick-serial.window="onPickSerial($event.detail)" @pick-qty.window="onPickQty($event.detail)" class="space-y-3">
    <template x-if="rows.length === 0">
        <div class="p-6 text-center border-2 border-dashed border-slate-200 dark:border-slate-700/80 rounded-2xl bg-slate-50/50 dark:bg-slate-900/30">
            <svg class="w-8 h-8 mx-auto text-slate-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/>
            </svg>
            <p class="text-xs font-semibold text-slate-600 dark:text-slate-300">Belum ada barang yang ditambahkan</p>
            <p class="text-[11px] text-slate-400 mt-0.5">Klik tombol "+ Tambah Baris Barang" di bawah untuk mulai mencatat.</p>
        </div>
    </template>

    <template x-for="(row, index) in rows" :key="index">
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl p-3.5 sm:p-4 shadow-xs space-y-3 transition-all">
            <!-- Row Header -->
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                    <span>Barang #</span><span x-text="index + 1"></span>
                </span>
                <button type="button" @click="removeRow(index)" class="p-2.5 min-w-[40px] min-h-[40px] flex items-center justify-center text-rose-500 hover:text-rose-700 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-xl border border-rose-200 dark:border-rose-900/60 transition-colors shrink-0 cursor-pointer" title="Hapus baris ini">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3" />
                    </svg>
                </button>
            </div>

            <!-- Kategori → Barang (cascading, lihat docblock atas) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                <div>
                    <label class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Kategori</label>
                    <select x-model="row.category_id" @change="row.item_id = ''; row.tracking_type = ''; row.unit = ''"
                        class="w-full min-h-[44px] text-xs font-semibold px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/60 dark:bg-slate-900/80 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                        <option value="">-- Semua Kategori --</option>
                        <template x-for="cat in categoryOptions" :key="cat.id">
                            <option :value="cat.id" x-text="cat.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Nama Barang</label>
                    <select :name="`${fieldName}[${index}][item_id]`" x-model="row.item_id" @change="onItemChange(index)" required
                        class="w-full min-h-[44px] text-xs font-semibold px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/60 dark:bg-slate-900/80 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                        <option value="">— Pilih Barang —</option>
                        <template x-for="opt in filteredItemOptions(row)" :key="opt.id">
                            <option :value="opt.id" x-text="opt.label"></option>
                        </template>
                    </select>
                </div>
            </div>

            <!-- Serialized Mode (Textarea SN) -->
            <template x-if="row.tracking_type === 'serialized'">
                <div class="bg-cyan-50/40 dark:bg-cyan-950/20 border border-cyan-100 dark:border-cyan-900/40 rounded-xl p-3.5 space-y-2.5">
                    <div class="flex items-center justify-between">
                        <label class="text-[10px] font-bold uppercase tracking-wider text-cyan-800 dark:text-cyan-300">
                            Nomor Seri (Tempel banyak sekaligus, 1 baris per SN)
                        </label>
                        <span class="text-[11px] font-bold font-mono px-2 py-0.5 rounded-full" :class="serialCount(row) > 0 ? 'bg-emerald-100 dark:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' : 'bg-slate-100 dark:bg-slate-700 text-slate-400'" x-text="serialCount(row) + ' SN Terdeteksi'"></span>
                    </div>
                    <textarea :name="`${fieldName}[${index}][serial_numbers]`" x-model="row.serial_numbers" rows="3" placeholder="Contoh:&#10;ZTE00012345&#10;ZTE00012346&#10;ZTE00012347"
                        class="w-full text-xs font-mono px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-cyan-500/20 focus:border-cyan-500 leading-relaxed"></textarea>

                    @if($withPrice)
                    <div class="w-full sm:w-64 pt-1">
                        <label class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Harga Beli Satuan (Rp) — Berlaku per SN</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-xs font-bold text-slate-400">Rp</span>
                            <input type="number" step="1" min="1" :name="`${fieldName}[${index}][unit_price]`" x-model="row.unit_price" required placeholder="mis. 250000"
                                class="w-full min-h-[44px] pl-9 pr-3 py-1.5 text-xs font-mono font-bold border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                        </div>
                    </div>
                    @endif
                </div>
            </template>

            <!-- Quantity / Batch Mode -->
            <template x-if="row.tracking_type !== 'serialized' && row.tracking_type !== ''">
                <div class="bg-slate-50/70 dark:bg-slate-900/40 border border-slate-100 dark:border-slate-700/60 rounded-xl p-3.5">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                        <div>
                            <label class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                <span>Jumlah Qty (</span><span class="font-bold text-sky-600" x-text="row.unit"></span><span>)</span>
                            </label>
                            <input type="number" step="0.01" min="0.01" :name="`${fieldName}[${index}][qty]`" x-model="row.qty" required placeholder="0.00"
                                class="w-full min-h-[44px] px-3 py-1.5 text-xs font-mono font-bold border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                        </div>

                        <template x-if="row.tracking_type === 'batch'">
                            <div>
                                <label class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-purple-600 dark:text-purple-400">No. Lot / Drum</label>
                                <input type="text" :name="`${fieldName}[${index}][lot_no]`" x-model="row.lot_no" placeholder="mis. LOT-2026-001"
                                    class="w-full min-h-[44px] px-3 py-1.5 text-xs font-mono border border-purple-200 dark:border-purple-800 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500">
                            </div>
                        </template>

                        @if($withPrice)
                        <div>
                            <label class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Harga Satuan (Rp)</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-xs font-bold text-slate-400">Rp</span>
                                <input type="number" step="1" min="1" :name="`${fieldName}[${index}][unit_price]`" x-model="row.unit_price" required placeholder="mis. 5000"
                                    class="w-full min-h-[44px] pl-9 pr-3 py-1.5 text-xs font-mono font-bold border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </template>
        </div>
    </template>

    <button type="button" @click="addRow()" class="w-full sm:w-auto min-h-[44px] inline-flex items-center justify-center gap-2 text-xs font-bold text-sky-700 dark:text-sky-300 bg-sky-50 hover:bg-sky-100 dark:bg-sky-950/40 dark:hover:bg-sky-900/50 border border-sky-200 dark:border-sky-800/80 px-4 py-2.5 rounded-xl shadow-xs transition-all cursor-pointer">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
        </svg>
        <span>+ Tambah Baris Barang</span>
    </button>
</div>

@once
@push('scripts')
<script>
function inventoryLineRows(itemOptions, categoryOptions, fieldName) {
    return {
        itemOptions: itemOptions,
        categoryOptions: categoryOptions,
        fieldName: fieldName,
        rows: [],

        init() {
            if (this.rows.length === 0) {
                this.addRow();
            }
        },

        addRow() {
            this.rows.push({ item_id: '', category_id: '', tracking_type: '', unit: '', qty: '', lot_no: '', serial_numbers: '', unit_price: '' });
        },

        removeRow(index) {
            this.rows.splice(index, 1);
        },

        // Barang di-filter ke Kategori yang lagi kepilih di baris itu — kalau
        // belum pilih kategori, tampilin semua (biar staf yang udah HAFAL
        // nama barangnya gak wajib pilih kategori dulu, murni bantuan
        // opsional buat yang belum hafal).
        filteredItemOptions(row) {
            if (! row.category_id) {
                return this.itemOptions;
            }

            return this.itemOptions.filter(o => String(o.category_id) === String(row.category_id));
        },

        onItemChange(index) {
            const row = this.rows[index];
            const opt = this.itemOptions.find(o => String(o.id) === String(row.item_id));

            if (opt) {
                row.tracking_type = opt.tracking_type;
                row.unit = opt.unit;
                // Sinkronin kategori dari barang yang KEPILIH — nutup celah
                // baris yang item_id-nya di-set programatis (scan/klik chip
                // stok tersedia, lihat onPickSerial/onPickQty di bawah)
                // TANPA staf pernah nyentuh dropdown Kategori sama sekali;
                // tanpa ini dropdown Kategori bakal kosong padahal Barang-nya
                // udah kepilih, bingung dilihat.
                row.category_id = opt.category_id ?? '';
            }
        },

        serialCount(row) {
            return (row.serial_numbers || '')
                .split(/[\r\n,]+/)
                .map(s => s.trim())
                .filter(s => s.length > 0)
                .length;
        },

        onPickSerial(detail) {
            let row = this.rows.find(r => String(r.item_id) === String(detail.itemId));

            if (! row) {
                this.addRow();
                row = this.rows[this.rows.length - 1];
                row.item_id = detail.itemId;
                this.onItemChange(this.rows.length - 1);
            }

            const existing = (row.serial_numbers || '').split(/[\r\n,]+/).map(s => s.trim()).filter(s => s.length > 0);

            if (! existing.includes(detail.serialNumber)) {
                existing.push(detail.serialNumber);
                row.serial_numbers = existing.join('\n');
            }
        },

        onPickQty(detail) {
            let row = this.rows.find(r => String(r.item_id) === String(detail.itemId) && (r.lot_no || '') === (detail.lotNo || ''));

            if (! row) {
                this.addRow();
                row = this.rows[this.rows.length - 1];
                row.item_id = detail.itemId;
                this.onItemChange(this.rows.length - 1);
            }

            row.lot_no = detail.lotNo || '';
            row.qty = detail.qty;
        },
    };
}
</script>
@endpush
@endonce
