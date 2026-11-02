{{--
    Komponen: Baris Barang Dipasang (Modem + Roll Kabel + Pasif, gabungan)
    =======================================================================
    Dipakai HANYA Laporan Pemasangan PSB (Form 1 / storePemasangan()) — ganti
    3 picker terpisah (combobox SN Modem, combobox Roll Kabel, komponen
    material-rows Pasif) jadi 1 dropdown growable. Lihat
    docs/superpowers/specs/2026-11-02-unify-dropdown-item-psb-design.md.

    PENTING: komponen ini TIDAK mengubah backend sama sekali. Tiap baris
    merender `name` attribute PERSIS seperti field lama:
      - mechanism=modem → name="selected_inventory_serial_id" (singular, maks 1 baris aktif)
      - mechanism=roll  → name="selected_inventory_roll_id" + name="roll_meters_used" (singular, maks 1 baris aktif)
      - mechanism=pasif → name="materials[{index}][item_id|item_type|qty|unit|note]" (array, unlimited)
    storePemasangan() membaca field-field itu tanpa tahu UI-nya sudah digabung.

    Props:
      $serialOptions  (Collection<InventorySerial>) — dari eligibleSerialsForTeam(), ->item harus sudah dimuat.
      $rollOptions    (Collection<InventoryRoll>) — dari eligibleRollsForTeam(), ->item harus sudah dimuat.
      $custodyOptions (Collection/array {item_id, code, name, unit, type, available}) — dari eligiblePassiveCustodyForTeam().
      $initialSerialId (int|null) — prefill, biasanya $installation->selected_inventory_serial_id.
      $initialRollId   (int|null) — prefill, $installation->selected_inventory_roll_id.
      $initialRollMeters (float|null) — prefill, $installation->roll_meters_used.
      $initialMaterialRows (array) — prefill baris pasif, format sama $rows di material-rows.blade.php.
      $modemAcquisitionExpr (string) — ekspresi Alpine yang mengevaluasi nilai radio
        "Cara Pemilikan Modem" di scope luar (biasanya cuma `modemAcquisition`, lihat
        x-data di report.blade.php). Opsi "Modem" disembunyikan & input SN-nya
        didisable saat ekspresi ini === 'beli_sendiri' — identik perilaku lama
        (fieldset disabled), tidak menduplikasi aturan di backend.
--}}

@props([
    'serialOptions' => null,
    'rollOptions' => null,
    'custodyOptions' => null,
    'initialSerialId' => null,
    'initialRollId' => null,
    'initialRollMeters' => null,
    'initialMaterialRows' => [],
    'modemAcquisitionExpr' => 'null',
    'emptyLabel' => 'Belum ada barang dipilih.',
])

@php
    $itemOptions = collect()
        ->concat(collect($serialOptions ?? [])->map(fn ($serial) => [
            'mechanism' => 'modem',
            'option_id' => (string) $serial->id,
            'label' => trim(($serial->item->name ?? 'Modem').' — SN '.$serial->serial_number),
            'type' => null,
            'unit' => null,
            'available' => null,
        ]))
        ->concat(collect($rollOptions ?? [])->map(fn ($roll) => [
            'mechanism' => 'roll',
            'option_id' => (string) $roll->id,
            'label' => trim(($roll->item->name ?? 'Kabel').' — Roll '.$roll->roll_code),
            'type' => null,
            'unit' => 'meter',
            'available' => (float) $roll->length_remaining,
        ]))
        ->concat(collect($custodyOptions ?? [])->map(fn ($row) => [
            'mechanism' => 'pasif',
            'option_id' => (string) $row['item_id'],
            'label' => trim(($row['code'] ?? '').' — '.$row['name']).' (sisa '.$row['available'].' '.$row['unit'].')',
            'type' => $row['type'] ?? null,
            'unit' => $row['unit'],
            'available' => (float) $row['available'],
        ]))
        ->values();

    $initialRows = collect();
    if ($initialSerialId) {
        $initialRows->push(['mechanism' => 'modem', 'option_id' => (string) $initialSerialId, 'qty' => '', 'note' => '']);
    }
    if ($initialRollId) {
        $initialRows->push(['mechanism' => 'roll', 'option_id' => (string) $initialRollId, 'qty' => (string) ($initialRollMeters ?? ''), 'note' => '']);
    }
    foreach ($initialMaterialRows as $row) {
        if (! empty($row['item_id'])) {
            $initialRows->push(['mechanism' => 'pasif', 'option_id' => (string) $row['item_id'], 'qty' => (string) ($row['qty'] ?? ''), 'note' => (string) ($row['note'] ?? '')]);
        }
    }
@endphp

<div
    x-data="installationItemRows(@js($itemOptions), @js($initialRows->values()))"
    class="space-y-2"
>
    <template x-if="rows.length === 0">
        <p class="text-[11px] text-slate-400 dark:text-slate-500 italic py-2">{{ $emptyLabel }}</p>
    </template>

    <template x-for="(row, index) in rows" :key="index">
        <div class="grid grid-cols-12 gap-2 items-start bg-slate-50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-700/50 rounded-lg p-3">
            <div class="col-span-8 md:col-span-7">
                <label class="block mb-1 text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Barang (custody tim)</label>
                <x-combobox
                    options-expr="() => comboOptionsFor(index, {{ $modemAcquisitionExpr }})"
                    value-expr="() => selectedComboValue(row)"
                    on-select-expr="onChoose(index, value)"
                    placeholder="Ketik nama barang / SN / kode roll..."
                />
            </div>

            {{-- id="selected_inventory_serial_id" WAJIB ada — getMissingFieldsFrom()
                 (validasi klien tombol Aktivasi, report.blade.php) baca nilai lewat
                 document.getElementById('selected_inventory_serial_id'), bukan name. --}}
            <template x-if="row.mechanism === 'modem'">
                <input type="hidden" id="selected_inventory_serial_id" name="selected_inventory_serial_id" :value="row.option_id" :disabled="({{ $modemAcquisitionExpr }}) === 'beli_sendiri'">
            </template>

            <template x-if="row.mechanism === 'roll'">
                <div class="col-span-4 md:col-span-3">
                    <label class="block mb-1 text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Meter Terpakai</label>
                    <input type="hidden" name="selected_inventory_roll_id" :value="row.option_id">
                    <input
                        type="number" step="0.01" min="0.01"
                        name="roll_meters_used" x-model="row.qty"
                        :max="availableFor(row)"
                        placeholder="cth: 150"
                        class="w-full text-xs font-mono px-2 py-1.5 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400"
                    >
                </div>
            </template>

            <template x-if="row.mechanism === 'pasif'">
                <div class="col-span-4 md:col-span-2">
                    <label class="block mb-1 text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Jumlah</label>
                    <input type="hidden" :name="`materials[${index}][item_id]`" :value="row.option_id">
                    <input type="hidden" :name="`materials[${index}][item_type]`" :value="typeFor(row)">
                    <input type="hidden" :name="`materials[${index}][unit]`" :value="unitFor(row)">
                    <input
                        type="number" step="0.01" min="0"
                        :name="`materials[${index}][qty]`" x-model="row.qty"
                        :max="availableFor(row)"
                        placeholder="cth: 10"
                        class="w-full text-xs font-mono px-2 py-1.5 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400"
                    >
                </div>
            </template>

            <template x-if="row.mechanism === 'pasif'">
                <div class="col-span-8 md:col-span-2">
                    <label class="block mb-1 text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Catatan</label>
                    <input
                        type="text"
                        :name="`materials[${index}][note]`" x-model="row.note"
                        placeholder="opsional"
                        class="w-full text-xs font-sans px-2 py-1.5 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400"
                    >
                </div>
            </template>

            <template x-if="row.mechanism && availableFor(row) !== null">
                <p class="col-span-12 md:col-span-2 text-[10px] text-slate-500 dark:text-slate-400 self-center">Sisa custody: <span class="font-semibold" x-text="availableFor(row)"></span></p>
            </template>

            <div class="col-span-12 md:col-span-1 flex items-end justify-end h-full pb-0.5">
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
function installationItemRows(itemOptions, initialRows) {
    return {
        itemOptions: itemOptions,
        rows: initialRows.length ? initialRows : [],

        addRow() {
            this.rows.push({ mechanism: '', option_id: '', qty: '', note: '' });
        },

        removeRow(index) {
            this.rows.splice(index, 1);
        },

        // Baris Modem/Roll yang sudah dipakai baris lain disembunyikan dari
        // opsi — mencegah 2 baris modem/roll aktif bersamaan (field tujuannya
        // singular di DB, lihat komentar header komponen). Baris Pasif TIDAK
        // difilter dobel — item yang sama boleh muncul di >1 baris, sama
        // perilaku komponen material-rows sekarang (server yang menjumlah qty).
        // Mekanisme "singular" (maks 1 baris aktif) — satu tempat, dipakai
        // optionsFor() di bawah. Field tujuannya singular di DB (lihat
        // komentar header komponen), jadi SEMUA opsi mekanisme ini wajib
        // hilang dari baris lain begitu SATU baris manapun sudah memakainya
        // — bukan cuma opsi yang persis sama option_id-nya (itu bug: 2 SN
        // BEDA tetap bisa lolos jadi 2 baris modem sekaligus).
        singularMechanisms: ['modem', 'roll'],

        optionsFor(index, modemAcquisition) {
            const row = this.rows[index];
            const usedSingularMechanisms = this.rows
                .filter((r, i) => i !== index && this.singularMechanisms.includes(r.mechanism))
                .map(r => r.mechanism);

            return this.itemOptionsWithLegacy(row).filter(opt => {
                if (modemAcquisition === 'beli_sendiri' && opt.mechanism === 'modem') {
                    return false;
                }
                if (this.singularMechanisms.includes(opt.mechanism)) {
                    return ! usedSingularMechanisms.includes(opt.mechanism);
                }
                return true;
            });
        },

        // Baris lama yang option_id-nya sudah tidak ada di custody sekarang
        // (dicabut/dipindah gudang) tetap ditawarkan UNTUK BARIS ITU SAJA,
        // ditandai "tersimpan" — sama pola itemOptionsFor() legacy-row di
        // material-rows.blade.php. Tanpa ini <select> jatuh diam-diam ke opsi
        // pertama saat render, dan submit ulang menimpa field jadi barang lain.
        itemOptionsWithLegacy(row) {
            if (! row || ! row.option_id) {
                return this.itemOptions;
            }

            const known = this.itemOptions.some(o => o.mechanism === row.mechanism && String(o.option_id) === String(row.option_id));

            if (known) {
                return this.itemOptions;
            }

            return [...this.itemOptions, {
                mechanism: row.mechanism,
                option_id: row.option_id,
                label: `(tersimpan, sudah tidak ada di custody sekarang) #${row.option_id}`,
                type: null,
                unit: null,
                available: null,
            }];
        },

        comboOptionsFor(index, modemAcquisition) {
            return this.optionsFor(index, modemAcquisition).map(opt => ({
                value: `${opt.mechanism}:${opt.option_id}`,
                label: opt.label,
                search: opt.label,
            }));
        },

        selectedComboValue(row) {
            return row.mechanism && row.option_id ? `${row.mechanism}:${row.option_id}` : '';
        },

        onChoose(index, value) {
            const [mechanism, optionId] = String(value ?? '').split(':');
            this.rows[index].mechanism = mechanism ?? '';
            this.rows[index].option_id = optionId ?? '';
            this.rows[index].qty = '';
            this.rows[index].note = '';
        },

        findOption(row) {
            return this.itemOptions.find(o => o.mechanism === row.mechanism && String(o.option_id) === String(row.option_id));
        },

        availableFor(row) {
            const opt = this.findOption(row);
            return opt && opt.available !== undefined ? opt.available : null;
        },

        typeFor(row) {
            return this.findOption(row)?.type ?? '';
        },

        unitFor(row) {
            return this.findOption(row)?.unit ?? '';
        },
    };
}
</script>
@endpush
@endonce
