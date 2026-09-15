{{--
    Komponen: Baris Material Repeatable
    ===================================
    Dipakai DUA halaman — Laporan Survey (fase estimasi) & Laporan Pemasangan
    (fase terpakai). Satu komponen, bukan dua markup kembar: bentuk barisnya
    harus persis sama supaya perbandingan estimasi-vs-realisasi di halaman
    verifikasi membandingkan hal yang setara.

    Props:
      $name    (string) — nama field array, mis. "materials" → materials[0][qty]
      $items   (Collection<Item>) — master barang aktif
      $rows    (array) — baris awal (prefill). Tiap baris: item_id, item_name,
                         item_type, qty, unit, note
      $emptyLabel (string) — teks saat belum ada baris

    Barang di luar master dicatat lewat pilihan "Lainnya": kolom nama berubah
    jadi input bebas. Baris seperti ini disimpan dengan item_id null dan muncul
    sebagai kandidat penambahan master data.
--}}

@props([
    'name' => 'materials',
    'items' => null,
    'categories' => null,
    'rows' => [],
    'emptyLabel' => 'Belum ada material dicatat.',
    // Mode "Material Terpakai" pasca-Gudang (ADHOC-54, koreksi 2026-09-12) —
    // dipakai installations/report.blade.php (Laporan Pemasangan). Survey
    // (estimasi) & Maintenance TIDAK pakai ini (masih mode lama, item master
    // bebas + "Lainnya") — estimasi belum ada custody buat dicek (survey
    // terjadi SEBELUM barang di-issue ke teknisi).
    //
    // Saat true: dropdown Barang dibatasi ke $custodyOptions doang (item yang
    // BENERAN ada sisa custody-nya di tim ini), opsi "Lainnya (isi manual)"
    // dihapus total, dan tiap opsi nampilin sisa qty. Penegakan SEBENARNYA
    // tetap di server (storePemasangan() + InventoryService::consumeFromCustody()
    // di storeSpeedtest()) — ini cuma pagar UI biar ketauan dari awal, bukan
    // baru gagal di titik penyelesaian.
    'restrictToCustody' => false,
    // Collection/array of {item_id, code, name, unit, type, available} — lihat
    // CustomerInstallationController::eligiblePassiveCustodyForTeam().
    'custodyOptions' => null,
])

@php
    // Fallback query kalau pemanggil belum mengirim prop — komponen ini dipakai
    // dua halaman dan satu di antaranya bisa saja terlewat waktu kategori pindah
    // ke master. Lebih baik satu query ekstra daripada dropdown kosong di
    // laporan lapangan.
    $categoryOptions = collect($categories ?? \App\Models\ItemCategory::options())->map(fn ($category) => [
        'code' => $category->code,
        'name' => $category->name,
        'default_unit' => $category->default_unit,
    ])->values();

    $fallbackType = $categoryOptions->firstWhere('code', \App\Models\ItemCategory::CODE_LAINNYA)
        ? \App\Models\ItemCategory::CODE_LAINNYA
        : ($categoryOptions->first()['code'] ?? \App\Models\ItemCategory::CODE_LAINNYA);

    // Mode custody: opsi dropdown dari $custodyOptions (item yang beneran ada
    // sisanya di tim ini), BUKAN dari seluruh master $items — analog SN
    // Perangkat Aktif yang juga dibatasi custody, bukan seluruh InventorySerial.
    $itemOptions = $restrictToCustody
        ? collect($custodyOptions ?? [])->map(fn ($row) => [
            'id' => $row['item_id'],
            'name' => $row['name'],
            'code' => $row['code'],
            'type' => $row['type'] ?? $fallbackType,
            'unit' => $row['unit'],
            'available' => (float) $row['available'],
        ])->values()
        : collect($items ?? [])->map(fn ($item) => [
            'id' => $item->id,
            'name' => $item->name,
            'code' => $item->code,
            'type' => $item->category?->code ?? $fallbackType,
            'unit' => $item->unit,
        ])->values();

    $initialRows = collect($rows)->map(fn ($row) => [
        'item_id' => $row['item_id'] ?? '',
        'item_name' => $row['item_name'] ?? '',
        'item_type' => $row['item_type'] ?? $fallbackType,
        'qty' => $row['qty'] ?? '',
        'unit' => $row['unit'] ?? '',
        'note' => $row['note'] ?? '',
    ])->values();
@endphp

<div
    x-data="materialRows(
        @js($itemOptions),
        @js($categoryOptions),
        @js($initialRows),
        '{{ $name }}',
        @js($fallbackType),
        @js((bool) $restrictToCustody)
    )"
    class="space-y-3"
>
    <template x-if="rows.length === 0">
        <p class="text-[11px] text-slate-400 dark:text-slate-500 italic py-2">{{ $emptyLabel }}</p>
    </template>

    <template x-for="(row, index) in rows" :key="index">
        <div class="grid grid-cols-12 gap-2 items-start bg-slate-50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-700/50 rounded-lg p-3">
            {{-- Barang --}}
            <div class="col-span-12 md:col-span-4">
                <label class="block mb-1 text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Barang</label>
                <select
                    :name="`${fieldName}[${index}][item_id]`"
                    x-model="row.item_id"
                    @change="onItemChange(index)"
                    class="w-full text-xs font-sans px-2 py-1.5 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400"
                >
                    {{-- Mode custody: TIDAK ada opsi "Lainnya (isi manual)" —
                         barang harus dari custody Gudang, gak boleh nama
                         karangan. Placeholder kosong tetap ada biar select
                         gak "nyangkut" ke opsi pertama tanpa sengaja. --}}
                    <template x-if="restrictToCustody">
                        <option value="">— Pilih Barang —</option>
                    </template>
                    <template x-if="!restrictToCustody">
                        <option value="">— Lainnya (isi manual) —</option>
                    </template>
                    <template x-for="opt in itemOptionsFor(row)" :key="opt.id">
                        <option :value="opt.id" x-text="itemOptionLabel(opt)"></option>
                    </template>
                </select>

                {{-- Nama manual cuma buat mode LAMA (Survey/Maintenance) untuk
                     barang di luar master — mode custody gak punya jalur ini
                     sama sekali, dropdown-nya emang gak nawarin opsi kosong. --}}
                <template x-if="!restrictToCustody && !row.item_id">
                    <input
                        type="text"
                        :name="`${fieldName}[${index}][item_name]`"
                        x-model="row.item_name"
                        placeholder="Nama / spesifikasi barang"
                        class="w-full mt-1.5 text-xs font-sans px-2 py-1.5 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400"
                    >
                </template>

                {{-- Sisa custody item yang lagi kepilih di baris ini — cuma
                     mode custody, biar teknisi liat batasnya SEBELUM ngetik
                     qty, bukan ketauan gagal pas submit. --}}
                <template x-if="restrictToCustody && row.item_id && availableFor(row) !== null">
                    <p class="mt-1 text-[10px] text-slate-500 dark:text-slate-400">
                        Sisa custody tim: <span class="font-semibold" x-text="availableFor(row)"></span> <span x-text="row.unit"></span>
                    </p>
                </template>
            </div>

            {{-- Tipe --}}
            <div class="col-span-6 md:col-span-2">
                <label class="block mb-1 text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Tipe</label>
                <select
                    :name="`${fieldName}[${index}][item_type]`"
                    x-model="row.item_type"
                    @change="onTypeChange(index)"
                    :disabled="!!row.item_id"
                    class="w-full text-xs font-sans px-2 py-1.5 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400 disabled:opacity-60"
                >
                    {{-- Opsi dari master, plus kategori lama baris ini kalau
                         sudah dinonaktifkan admin — lihat catatan di typeOptionsFor(). --}}
                    <template x-for="opt in typeOptionsFor(row)" :key="opt.code">
                        <option :value="opt.code" x-text="opt.name"></option>
                    </template>
                </select>
            </div>

            {{-- Qty --}}
            <div class="col-span-3 md:col-span-2">
                <label class="block mb-1 text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Jumlah</label>
                <input
                    type="number"
                    step="0.01"
                    min="0"
                    :max="restrictToCustody ? availableFor(row) : null"
                    :name="`${fieldName}[${index}][qty]`"
                    x-model="row.qty"
                    class="w-full text-xs font-mono px-2 py-1.5 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400"
                >
                {{-- `max` HTML cuma nahan spinner/keyboard-up, gak nahan ketik
                     manual/paste angka lebih besar — server (storePemasangan())
                     tetap yang nge-final-check, ini murni bantu teknisi liat
                     batasnya. --}}
            </div>

            {{-- Satuan --}}
            <div class="col-span-3 md:col-span-1">
                <label class="block mb-1 text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Satuan</label>
                <input
                    type="text"
                    :name="`${fieldName}[${index}][unit]`"
                    x-model="row.unit"
                    class="w-full text-xs font-mono px-2 py-1.5 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400"
                >
            </div>

            {{-- Catatan --}}
            <div class="col-span-10 md:col-span-2">
                <label class="block mb-1 text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Catatan</label>
                <input
                    type="text"
                    :name="`${fieldName}[${index}][note]`"
                    x-model="row.note"
                    class="w-full text-xs font-sans px-2 py-1.5 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400"
                >
            </div>

            {{-- Hapus --}}
            <div class="col-span-2 md:col-span-1 flex items-end justify-end h-full pb-0.5">
                <button
                    type="button"
                    @click="removeRow(index)"
                    class="text-red-500 hover:text-red-700 dark:hover:text-red-400 p-1.5 rounded hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
                    title="Hapus baris"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3" />
                    </svg>
                </button>
            </div>
        </div>
    </template>

    <button
        type="button"
        @click="addRow()"
        class="inline-flex items-center gap-1.5 text-xs font-semibold text-sky-700 dark:text-sky-300 bg-sky-50 dark:bg-sky-950/40 border border-sky-200 dark:border-sky-800 hover:bg-sky-100 dark:hover:bg-sky-900/50 px-3 py-1.5 rounded-md transition-colors"
    >
        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
        </svg>
        Tambah Barang
    </button>
</div>

@once
@push('scripts')
<script>
function materialRows(itemOptions, categoryOptions, initialRows, fieldName, fallbackType, restrictToCustody) {
    return {
        itemOptions: itemOptions,
        categoryOptions: categoryOptions,
        rows: initialRows,
        fieldName: fieldName,
        fallbackType: fallbackType,
        restrictToCustody: restrictToCustody,

        addRow() {
            this.rows.push({
                item_id: '',
                item_name: '',
                item_type: this.fallbackType,
                qty: '',
                unit: this.defaultUnitFor(this.fallbackType),
                note: '',
            });
        },

        // Kategori baris yang sudah dinonaktifkan admin tetap ditawarkan UNTUK
        // BARIS ITU SAJA. Tanpa ini, <select> tanpa option yang cocok akan
        // jatuh ke option pertama saat render, dan sekadar membuka laporan lama
        // lalu menyimpannya diam-diam memindahkan kategorinya — pemakaian
        // material historis berubah tanpa ada yang mengubahnya.
        typeOptionsFor(row) {
            const known = this.categoryOptions.some(c => c.code === row.item_type);

            if (known || !row.item_type) {
                return this.categoryOptions;
            }

            return [...this.categoryOptions, { code: row.item_type, name: `${row.item_type} (nonaktif)`, default_unit: 'pcs' }];
        },

        defaultUnitFor(code) {
            return this.categoryOptions.find(c => c.code === code)?.default_unit ?? 'pcs';
        },

        // Mode custody: baris LAMA (tersimpan sebelum fitur ini, atau sisa
        // custody item-nya sekarang sudah habis) bisa punya item_id yang gak
        // lagi ada di itemOptions saat ini — tanpa ini <select> jatuh ke
        // opsi pertama diam-diam pas render (sama masalahnya kayak
        // typeOptionsFor() buat kategori nonaktif). Ditambahkan HANYA untuk
        // baris itu sendiri, ditandai jelas "tersimpan" biar teknisi ngerti
        // ini bukan pilihan baru yang bisa diklaim ulang.
        itemOptionsFor(row) {
            if (! this.restrictToCustody) {
                return this.itemOptions;
            }

            const known = this.itemOptions.some(o => String(o.id) === String(row.item_id));

            if (known || ! row.item_id) {
                return this.itemOptions;
            }

            return [...this.itemOptions, {
                id: row.item_id,
                name: `${row.item_name || 'Barang #' + row.item_id} (tersimpan, sisa custody sekarang tidak diketahui)`,
                code: null,
                type: row.item_type,
                unit: row.unit,
                available: null,
            }];
        },

        itemOptionLabel(opt) {
            if (! this.restrictToCustody) {
                return `${opt.code} — ${opt.name}`;
            }

            if (opt.available === null || opt.available === undefined) {
                return opt.name;
            }

            return `${opt.code} — ${opt.name} (sisa ${opt.available} ${opt.unit})`;
        },

        // null = gak ada batas dikenal (mode lama, atau baris legacy yang
        // item_id-nya sudah gak ada di itemOptions) — dipakai template buat
        // nyembunyiin hint sisa & `:max` (max="null" di Alpine artinya atribut
        // dilepas, bukan 0).
        availableFor(row) {
            const opt = this.itemOptions.find(o => String(o.id) === String(row.item_id));

            return opt && opt.available !== undefined ? opt.available : null;
        },

        removeRow(index) {
            this.rows.splice(index, 1);
        },

        // Pilih barang dari master → tipe & satuan ikut master, gak bisa
        // dikarang teknisi. Itu justru gunanya master ada.
        onItemChange(index) {
            const row = this.rows[index];
            const opt = this.itemOptions.find(o => String(o.id) === String(row.item_id));

            if (opt) {
                row.item_name = opt.name;
                row.item_type = opt.type;
                row.unit = opt.unit;
            }
        },

        // Barang "lainnya": satuan default ikut kategori (dari master, bukan
        // if-else 'kabel_dropcore' yang dulu di-hardcode di sini), tapi tetap
        // boleh diubah teknisi.
        onTypeChange(index) {
            const row = this.rows[index];

            if (row.item_id) {
                return;
            }

            row.unit = this.defaultUnitFor(row.item_type);
        },
    };
}
</script>
@endpush
@endonce
