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
    'rows' => [],
    'emptyLabel' => 'Belum ada material dicatat.',
])

@php
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
@endphp

<div
    x-data="materialRows(
        @js($itemOptions),
        @js($initialRows),
        '{{ $name }}'
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
                    <option value="">— Lainnya (isi manual) —</option>
                    <template x-for="opt in itemOptions" :key="opt.id">
                        <option :value="opt.id" x-text="`${opt.code} — ${opt.name}`"></option>
                    </template>
                </select>

                {{-- Nama manual hanya muncul untuk barang di luar master --}}
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
                    @foreach(\App\Enums\MaterialType::cases() as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Qty --}}
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
@endpush
@endonce
