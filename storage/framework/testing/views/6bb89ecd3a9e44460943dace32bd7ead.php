

<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
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
]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter(([
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
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
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
?>

<div
    x-data="materialRows(
        <?php echo \Illuminate\Support\Js::from($itemOptions)->toHtml() ?>,
        <?php echo \Illuminate\Support\Js::from($categoryOptions)->toHtml() ?>,
        <?php echo \Illuminate\Support\Js::from($initialRows)->toHtml() ?>,
        '<?php echo e($name); ?>',
        <?php echo \Illuminate\Support\Js::from($fallbackType)->toHtml() ?>,
        <?php echo \Illuminate\Support\Js::from((bool) $restrictToCustody)->toHtml() ?>
    )"
    class="space-y-3"
>
    <template x-if="rows.length === 0">
        <p class="text-[11px] text-slate-400 dark:text-slate-500 italic py-2"><?php echo e($emptyLabel); ?></p>
    </template>

    <template x-for="(row, index) in rows" :key="index">
        <div class="grid grid-cols-12 gap-2 items-start bg-slate-50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-700/50 rounded-lg p-3">
            
            <div class="col-span-12 md:col-span-4">
                <label class="block mb-1 text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Barang</label>
                <select
                    :name="`${fieldName}[${index}][item_id]`"
                    x-model="row.item_id"
                    @change="onItemChange(index)"
                    class="w-full text-xs font-sans px-2 py-1.5 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400"
                >
                    
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

                
                <template x-if="!restrictToCustody && !row.item_id">
                    <input
                        type="text"
                        :name="`${fieldName}[${index}][item_name]`"
                        x-model="row.item_name"
                        placeholder="Nama / spesifikasi barang"
                        class="w-full mt-1.5 text-xs font-sans px-2 py-1.5 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400"
                    >
                </template>

                
                <template x-if="restrictToCustody && row.item_id && availableFor(row) !== null">
                    <p class="mt-1 text-[10px] text-slate-500 dark:text-slate-400">
                        Sisa custody tim: <span class="font-semibold" x-text="availableFor(row)"></span> <span x-text="row.unit"></span>
                    </p>
                </template>
            </div>

            
            <div class="col-span-6 md:col-span-2">
                <label class="block mb-1 text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Tipe</label>
                <select
                    :name="`${fieldName}[${index}][item_type]`"
                    x-model="row.item_type"
                    @change="onTypeChange(index)"
                    :disabled="!!row.item_id"
                    class="w-full text-xs font-sans px-2 py-1.5 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400 disabled:opacity-60"
                >
                    
                    <template x-for="opt in typeOptionsFor(row)" :key="opt.code">
                        <option :value="opt.code" x-text="opt.name"></option>
                    </template>
                </select>
            </div>

            
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
                
            </div>

            
            <div class="col-span-3 md:col-span-1">
                <label class="block mb-1 text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Satuan</label>
                <input
                    type="text"
                    :name="`${fieldName}[${index}][unit]`"
                    x-model="row.unit"
                    class="w-full text-xs font-mono px-2 py-1.5 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400"
                >
            </div>

            
            <div class="col-span-10 md:col-span-2">
                <label class="block mb-1 text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Catatan</label>
                <input
                    type="text"
                    :name="`${fieldName}[${index}][note]`"
                    x-model="row.note"
                    class="w-full text-xs font-sans px-2 py-1.5 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400"
                >
            </div>

            
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

<?php if (! $__env->hasRenderedOnce('9c91b626-9d22-4849-8a79-d103664f6373')): $__env->markAsRenderedOnce('9c91b626-9d22-4849-8a79-d103664f6373'); ?>
<?php $__env->startPush('scripts'); ?>
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
<?php $__env->stopPush(); ?>
<?php endif; ?>
<?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/components/material-rows.blade.php ENDPATH**/ ?>