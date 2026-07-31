

<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'name' => 'materials',
    'items' => null,
    'rows' => [],
    'emptyLabel' => 'Belum ada material dicatat.',
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
    'rows' => [],
    'emptyLabel' => 'Belum ada material dicatat.',
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $itemOptions = collect($items ?? [])->map(fn ($item) => [
        'id' => $item->id,
        'name' => $item->name,
        'code' => $item->code,
        'type' => $item->type->value,
        'unit' => $item->unit,
    ])->values();

    $initialRows = collect($rows)->map(fn ($row) => [
        'item_id' => $row['item_id'] ?? '',
        'item_name' => $row['item_name'] ?? '',
        'item_type' => $row['item_type'] ?? 'lainnya',
        'qty' => $row['qty'] ?? '',
        'unit' => $row['unit'] ?? '',
        'note' => $row['note'] ?? '',
    ])->values();
?>

<div
    x-data="materialRows(
        <?php echo \Illuminate\Support\Js::from($itemOptions)->toHtml() ?>,
        <?php echo \Illuminate\Support\Js::from($initialRows)->toHtml() ?>,
        '<?php echo e($name); ?>'
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
                    <option value="">— Lainnya (isi manual) —</option>
                    <template x-for="opt in itemOptions" :key="opt.id">
                        <option :value="opt.id" x-text="`${opt.code} — ${opt.name}`"></option>
                    </template>
                </select>

                
                <template x-if="!row.item_id">
                    <input
                        type="text"
                        :name="`${fieldName}[${index}][item_name]`"
                        x-model="row.item_name"
                        placeholder="Nama / spesifikasi barang"
                        class="w-full mt-1.5 text-xs font-sans px-2 py-1.5 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400"
                    >
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
                    <?php $__currentLoopData = \App\Enums\MaterialType::cases(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($type->value); ?>"><?php echo e($type->label()); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>

            
            <div class="col-span-3 md:col-span-2">
                <label class="block mb-1 text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Jumlah</label>
                <input
                    type="number"
                    step="0.01"
                    min="0"
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

<?php if (! $__env->hasRenderedOnce('8487db61-c3b6-495f-8876-f0a0bf99a1de')): $__env->markAsRenderedOnce('8487db61-c3b6-495f-8876-f0a0bf99a1de'); ?>
<?php $__env->startPush('scripts'); ?>
<script>
function materialRows(itemOptions, initialRows, fieldName) {
    return {
        itemOptions: itemOptions,
        rows: initialRows,
        fieldName: fieldName,

        addRow() {
            this.rows.push({
                item_id: '',
                item_name: '',
                item_type: 'lainnya',
                qty: '',
                unit: 'pcs',
                note: '',
            });
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

        // Barang "lainnya": satuan default ikut tipe, tapi tetap boleh diubah.
        onTypeChange(index) {
            const row = this.rows[index];

            if (row.item_id) {
                return;
            }

            row.unit = row.item_type === 'kabel_dropcore' ? 'meter' : 'pcs';
        },
    };
}
</script>
<?php $__env->stopPush(); ?>
<?php endif; ?>
<?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/components/material-rows.blade.php ENDPATH**/ ?>