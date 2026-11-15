{{--
    Komponen: Baris Barang Dipasang (Modem + Roll Kabel + Pasif)
    ============================================================
    Dipakai Laporan Pemasangan PSB (Form 1 / storePemasangan()) DAN Laporan
    Maintenance/C-REQ (TaskMaintenanceController::store()). Lihat
    docs/superpowers/specs/2026-11-02-unify-dropdown-item-psb-design.md.

    Tiap baris = Kategori → Nama Barang → Jumlah (permintaan user 2026-11-13,
    ADHOC-297). Dulu satu combobox gabungan "Barang (custody tim)" berisi semua
    SN + roll + pasif sekaligus — teknisi yang memegang banyak barang susah
    mencarinya. Opsi Kategori & Nama Barang HANYA dari barang yang dipegang tim
    task (custody), bukan master barang.

    PENTING: komponen ini TIDAK mengubah backend. Tiap baris merender `name`
    attribute PERSIS seperti field lama:
      - mechanism=modem → name="selected_inventory_serial_id" (singular, maks 1 baris aktif)
      - mechanism=roll  → name="selected_inventory_roll_id" + name="roll_meters_used" (singular, maks 1 baris aktif)
      - mechanism=pasif → name="materials[{index}][item_id|item_type|qty|unit|note]" (array, unlimited)

    Props:
      $serialOptions  (Collection<InventorySerial>) — dari eligibleSerialsForTeam().
      $rollOptions    (Collection<InventoryRoll>) — dari eligibleRollsForTeam().
      $custodyOptions (Collection/array {item_id, code, name, unit, type, available, category_name?}) — dari eligiblePassiveCustodyForTeam().
      $initialSerialId (int|null) — prefill, biasanya $installation->selected_inventory_serial_id.
      $initialRollId   (int|null) — prefill, $installation->selected_inventory_roll_id.
      $initialRollMeters (float|null) — prefill, $installation->roll_meters_used.
      $initialMaterialRows (array) — prefill baris pasif, format sama $rows di material-rows.blade.php.
      $modemBlockedExpr (string) — ekspresi Alpine BOOLEAN yang menentukan kapan
        opsi Modem disembunyikan & input SN-nya didisable. Tiap pemanggil beda
        jumlah pilihan ownership modem (PSB: acquisition === 'beli_sendiri';
        Maintenance/C-REQ: ownership 'tidak_ganti' / 'beli_sendiri').
--}}

@props([
    'serialOptions' => null,
    'rollOptions' => null,
    'custodyOptions' => null,
    'initialSerialId' => null,
    'initialRollId' => null,
    'initialRollMeters' => null,
    'initialMaterialRows' => [],
    'modemBlockedExpr' => 'false',
    'emptyLabel' => 'Belum ada barang dipilih.',
])

@php
    $serialOptions = $serialOptions ?? collect();
    $rollOptions = $rollOptions ?? collect();

    // Kategori dibaca dari relasi item->category — muat sekaligus (bukan
    // lazy per SN/roll) supaya teknisi yang pegang puluhan SN gak memicu
    // puluhan query.
    foreach ([$serialOptions, $rollOptions] as $models) {
        if ($models instanceof \Illuminate\Database\Eloquent\Collection) {
            $models->loadMissing('item.category');
        }
    }

    $custodyRows = collect($custodyOptions ?? []);

    // Custody pasif cuma bawa kode kategori (`type`) — nama kategorinya
    // diambil sekali query, kecuali pemanggil sudah mengirim category_name.
    $missingCategoryCodes = $custodyRows
        ->filter(fn ($row) => empty($row['category_name']) && ! empty($row['type']))
        ->pluck('type')
        ->unique()
        ->values();
    $categoryNames = $missingCategoryCodes->isNotEmpty()
        ? \App\Models\ItemCategory::whereIn('code', $missingCategoryCodes)->pluck('name', 'code')
        : collect();

    $formatNumber = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');

    $itemOptions = collect()
        ->concat(collect($serialOptions)->map(fn ($serial) => [
            'mechanism' => 'modem',
            'option_id' => (string) $serial->id,
            'category_code' => $serial->item?->category?->code ?? 'modem',
            'category_name' => $serial->item?->category?->name ?? 'Modem',
            'label' => trim(($serial->item?->name ?? 'Modem').' — SN '.$serial->serial_number),
            'hint' => null,
            'type' => null,
            'unit' => 'unit',
            'available' => null,
        ]))
        ->concat(collect($rollOptions)->map(fn ($roll) => [
            'mechanism' => 'roll',
            'option_id' => (string) $roll->id,
            'category_code' => $roll->item?->category?->code ?? 'roll',
            'category_name' => $roll->item?->category?->name ?? 'Roll Kabel',
            'label' => trim(($roll->item?->name ?? 'Kabel').' — Roll '.$roll->roll_code),
            'hint' => 'Sisa '.$formatNumber($roll->length_remaining).' meter',
            'type' => null,
            'unit' => 'meter',
            'available' => (float) $roll->length_remaining,
        ]))
        ->concat($custodyRows->map(fn ($row) => [
            'mechanism' => 'pasif',
            'option_id' => (string) $row['item_id'],
            'category_code' => $row['type'] ?? 'lainnya',
            'category_name' => $row['category_name'] ?? $categoryNames[$row['type'] ?? ''] ?? 'Tanpa Kategori',
            'label' => trim($row['name'].(! empty($row['code']) ? ' ('.$row['code'].')' : '')),
            'hint' => 'Sisa '.$formatNumber($row['available']).' '.$row['unit'],
            'type' => $row['type'] ?? null,
            'unit' => $row['unit'],
            'available' => (float) $row['available'],
        ]))
        ->values();

    $initialRows = collect();
    if ($initialSerialId) {
        // Cari category_code dari $itemOptions (custody tim saat ini).
        // Jika SN sudah bukan di custody (sudah installed), fallback query DB
        // supaya kategori tetap tampil dengan benar — bukan '__tersimpan'.
        $serialOpt = $itemOptions->first(fn ($o) => $o['mechanism'] === 'modem' && $o['option_id'] === (string) $initialSerialId);
        $serialCategory = $serialOpt['category_code'] ?? null;
        if (! $serialCategory) {
            $serialCategory = \App\Models\InventorySerial::find($initialSerialId)?->item?->category?->code ?? 'modem';
        }
        $initialRows->push(['mechanism' => 'modem', 'option_id' => (string) $initialSerialId, 'category' => $serialCategory, 'qty' => '1', 'note' => '']);
    }
    if ($initialRollId) {
        $rollOpt = $itemOptions->first(fn ($o) => $o['mechanism'] === 'roll' && $o['option_id'] === (string) $initialRollId);
        $rollCategory = $rollOpt['category_code'] ?? null;
        if (! $rollCategory) {
            $rollCategory = \App\Models\InventoryRoll::find($initialRollId)?->item?->category?->code ?? 'roll';
        }
        $initialRows->push(['mechanism' => 'roll', 'option_id' => (string) $initialRollId, 'category' => $rollCategory, 'qty' => (string) ($initialRollMeters ?? ''), 'note' => '']);
    }
    foreach ($initialMaterialRows as $row) {
        if (! empty($row['item_id'])) {
            $pasifOpt = $itemOptions->first(fn ($o) => $o['mechanism'] === 'pasif' && $o['option_id'] === (string) $row['item_id']);
            $pasifCategory = $pasifOpt['category_code'] ?? $row['item_type'] ?? $row['type'] ?? '';
            $initialRows->push(['mechanism' => 'pasif', 'option_id' => (string) $row['item_id'], 'category' => $pasifCategory, 'qty' => (string) ($row['qty'] ?? ''), 'note' => (string) ($row['note'] ?? '')]);
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
            {{-- `{!! !!}` di ekspresi combobox, BUKAN `{{ }}`: options-expr di-echo
                 `{{ }}` LAGI di dalam <x-combobox>. Escape dua kali ⇒ kutip jadi
                 `&amp;#039;`, Alpine SyntaxError ⇒ combobox kosong, teknisi gak bisa
                 pilih barang (ADHOC-297 bug #3). Aman: nilainya ekspresi tulisan
                 developer di view pemanggil, bukan input user. Penjaga:
                 BarangDipasangComboboxKosongTest. --}}

            {{-- Kategori — cuma kategori yang barangnya DIPEGANG tim. --}}
            <div class="col-span-12 sm:col-span-5">
                <label class="block mb-1 text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Kategori</label>
                <x-combobox
                    options-expr="() => categoryOptionsFor(index, ({!! $modemBlockedExpr !!}))"
                    value-expr="() => row.category"
                    on-select-expr="onCategoryChoose(index, value)"
                    placeholder="Pilih kategori..."
                />
            </div>

            {{-- Nama Barang — terfilter kategori baris ini. --}}
            <div class="col-span-12 sm:col-span-7">
                <label class="block mb-1 text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Nama Barang</label>
                <x-combobox
                    options-expr="() => comboOptionsFor(index, ({!! $modemBlockedExpr !!}))"
                    value-expr="() => selectedComboValue(row)"
                    on-select-expr="onChoose(index, value)"
                    placeholder="Ketik nama barang / SN / kode roll..."
                />
            </div>

            {{-- id="selected_inventory_serial_id" WAJIB ada — getMissingFieldsFrom()
                 (validasi klien tombol Aktivasi, report.blade.php) baca nilai lewat
                 document.getElementById('selected_inventory_serial_id'), bukan name. --}}
            <template x-if="row.mechanism === 'modem'">
                <input type="hidden" id="selected_inventory_serial_id" name="selected_inventory_serial_id" :value="row.option_id" :disabled="({{ $modemBlockedExpr }})">
            </template>
            <template x-if="row.mechanism === 'roll'">
                <input type="hidden" name="selected_inventory_roll_id" :value="row.option_id">
            </template>
            <template x-if="row.mechanism === 'pasif'">
                <div class="hidden">
                    <input type="hidden" :name="`materials[${index}][item_id]`" :value="row.option_id">
                    <input type="hidden" :name="`materials[${index}][item_type]`" :value="typeFor(row)">
                    <input type="hidden" :name="`materials[${index}][unit]`" :value="unitFor(row)">
                </div>
            </template>

            {{-- Jumlah — modem selalu 1 unit (1 SN = 1 perangkat), roll = meter
                 terpakai, pasif = qty (dibatasi sisa custody tim). --}}
            <div class="col-span-6 sm:col-span-4">
                <label class="block mb-1 text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Jumlah</label>
                <div class="flex items-center gap-1.5">
                    <template x-if="!row.mechanism">
                        <input type="text" disabled placeholder="—" class="w-full text-xs font-mono px-2 py-1.5 border border-slate-200 dark:border-slate-700 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-400">
                    </template>
                    <template x-if="row.mechanism === 'modem'">
                        <input type="text" value="1" readonly class="w-full text-xs font-mono px-2 py-1.5 border border-slate-200 dark:border-slate-700 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                    </template>
                    <template x-if="row.mechanism === 'roll'">
                        <input
                            type="number" step="0.01" min="0.01"
                            name="roll_meters_used" x-model="row.qty"
                            :max="availableFor(row)"
                            placeholder="cth: 150"
                            class="w-full text-xs font-mono px-2 py-1.5 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400"
                        >
                    </template>
                    <template x-if="row.mechanism === 'pasif'">
                        <input
                            type="number" step="0.01" min="0"
                            :name="`materials[${index}][qty]`" x-model="row.qty"
                            :max="availableFor(row)"
                            placeholder="cth: 10"
                            class="w-full text-xs font-mono px-2 py-1.5 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400"
                        >
                    </template>
                    <span class="text-[10px] text-slate-500 dark:text-slate-400 whitespace-nowrap" x-text="unitLabel(row)"></span>
                </div>
                <template x-if="row.mechanism && availableFor(row) !== null">
                    <p class="mt-1 text-[10px] text-slate-500 dark:text-slate-400">Sisa: <span class="font-semibold" x-text="availableFor(row)"></span></p>
                </template>
            </div>

            {{-- Catatan — cuma barang pasif (field materials[i][note] sudah ada di backend). --}}
            <div class="col-span-5 sm:col-span-7">
                <template x-if="row.mechanism === 'pasif'">
                    <div>
                        <label class="block mb-1 text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Catatan</label>
                        <input
                            type="text"
                            :name="`materials[${index}][note]`" x-model="row.note"
                            placeholder="opsional"
                            class="w-full text-xs font-sans px-2 py-1.5 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400"
                        >
                    </div>
                </template>
            </div>

            <div class="col-span-1 flex items-start justify-end pt-5">
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
        rows: initialRows.map(row => ({ category: '', ...row })),

        // Pemilik baris saat ini per mekanisme singular ({modem: 2, roll: 0}).
        // Dihitung ulang hanya saat baris benar-benar berubah (dirty flag) —
        // tanpa cache, tiap combobox di tiap baris me-scan seluruh rows,
        // O(rows²) per render walau isinya belum berubah.
        _mechanismOwnersCache: null,
        _mechanismOwnersDirty: true,

        // Mekanisme yang field tujuannya SINGULAR di DB (maks 1 baris aktif):
        // begitu satu baris memakai modem/roll, SEMUA opsi mekanisme itu
        // hilang dari baris lain — bukan cuma option_id yang sama (2 SN
        // berbeda tetap gak boleh jadi 2 baris modem). Pasif boleh dobel,
        // server yang menjumlah qty.
        singularMechanisms: ['modem', 'roll'],

        init() {
            // Prefill (old()/data tersimpan) cuma bawa mechanism + option_id —
            // kategori baris diturunkan dari opsinya.
            this.rows.forEach(row => {
                if (! row.category) {
                    row.category = this.optionFor(row)?.category_code ?? '';
                }
            });
        },

        addRow() {
            this.rows.push({ category: '', mechanism: '', option_id: '', qty: '', note: '' });
            this._mechanismOwnersDirty = true;
        },

        removeRow(index) {
            this.rows.splice(index, 1);
            this._mechanismOwnersDirty = true;
        },

        mechanismOwners() {
            if (this._mechanismOwnersDirty || this._mechanismOwnersCache === null) {
                const owners = {};
                this.rows.forEach((r, i) => {
                    if (this.singularMechanisms.includes(r.mechanism)) {
                        owners[r.mechanism] = i;
                    }
                });
                this._mechanismOwnersCache = owners;
                this._mechanismOwnersDirty = false;
            }

            return this._mechanismOwnersCache;
        },

        // Semua opsi yang BOLEH dipilih baris ini (custody tim, minus modem
        // yang diblokir ownership & mekanisme singular yang sudah dipakai
        // baris lain) — sumber tunggal buat combobox Kategori & Nama Barang.
        optionsFor(index, modemBlocked) {
            const owners = this.mechanismOwners();
            const currentRow = this.rows[index];

            return this.itemOptionsWithLegacy(currentRow).filter(opt => {
                if (modemBlocked && opt.mechanism === 'modem') {
                    // Izinkan opsi modem ini tetap tampil HANYA jika baris ini
                    // sudah memilihnya — supaya label tetap terbaca meski
                    // modemBlocked=true (Maintenance "tidak_ganti"). Tanpa ini
                    // combobox Nama Barang kosong sehingga label hilang.
                    if (currentRow.mechanism === 'modem' && String(opt.option_id) === String(currentRow.option_id)) {
                        return true;
                    }
                    return false;
                }
                if (this.singularMechanisms.includes(opt.mechanism)) {
                    return ! (opt.mechanism in owners) || owners[opt.mechanism] === index;
                }
                return true;
            });
        },

        // Baris lama yang option_id-nya sudah tidak ada di custody sekarang
        // (dicabut/dipindah gudang) tetap ditawarkan UNTUK BARIS ITU SAJA,
        // ditandai "tersimpan" (withLegacyOption() di app.js, dipakai juga
        // material-rows). Tanpa ini pilihan diam-diam kosong saat render dan
        // submit ulang menghapus barangnya.
        itemOptionsWithLegacy(row) {
            if (! row || ! row.option_id) {
                return this.itemOptions;
            }

            return withLegacyOption(
                this.itemOptions,
                o => o.mechanism === row.mechanism && String(o.option_id) === String(row.option_id),
                () => ({
                    mechanism: row.mechanism,
                    option_id: row.option_id,
                    category_code: row.category || '__tersimpan',
                    category_name: row.category ? row.category : 'Tersimpan (sudah tidak ada di custody)',
                    label: `(tersimpan, sudah tidak ada di custody sekarang) #${row.option_id}`,
                    hint: null,
                    type: null,
                    unit: null,
                    available: null,
                })
            );
        },

        categoryOptionsFor(index, modemBlocked) {
            const categories = new Map();

            this.optionsFor(index, modemBlocked).forEach(opt => {
                const entry = categories.get(opt.category_code) ?? { name: opt.category_name, count: 0 };
                entry.count++;
                categories.set(opt.category_code, entry);
            });

            return [...categories].map(([code, entry]) => ({
                value: code,
                label: entry.name,
                search: entry.name,
                hint: `${entry.count} barang dipegang`,
            }));
        },

        comboOptionsFor(index, modemBlocked) {
            const category = this.rows[index]?.category ?? '';

            return this.optionsFor(index, modemBlocked)
                .filter(opt => ! category || opt.category_code === category)
                .map(opt => ({
                    value: `${opt.mechanism}:${opt.option_id}`,
                    label: opt.label,
                    search: `${opt.label} ${opt.category_name}`,
                    hint: opt.hint,
                }));
        },

        selectedComboValue(row) {
            return row.mechanism && row.option_id ? `${row.mechanism}:${row.option_id}` : '';
        },

        onCategoryChoose(index, value) {
            const row = this.rows[index];
            row.category = value ?? '';

            // Ganti kategori ⇒ barang yang sudah dipilih tapi beda kategori
            // dilepas, supaya gak ada baris "Kategori Kabel, Barang Modem".
            if (row.mechanism && this.optionFor(row)?.category_code !== row.category) {
                row.mechanism = '';
                row.option_id = '';
                row.qty = '';
                row.note = '';
                this._mechanismOwnersDirty = true;
            }
        },

        onChoose(index, value) {
            const row = this.rows[index];
            const [mechanism, optionId] = String(value ?? '').split(':');
            row.mechanism = mechanism ?? '';
            row.option_id = optionId ?? '';
            row.qty = row.mechanism === 'modem' ? '1' : '';
            row.note = '';
            // Pilih barang langsung (tanpa kategori dulu) ⇒ kategorinya ikut terisi.
            row.category = this.optionFor(row)?.category_code ?? row.category;
            this._mechanismOwnersDirty = true;
        },

        // Termasuk opsi "tersimpan" untuk baris lama yang barangnya sudah
        // keluar dari custody.
        optionFor(row) {
            return this.itemOptionsWithLegacy(row)
                .find(o => o.mechanism === row.mechanism && String(o.option_id) === String(row.option_id));
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

        unitLabel(row) {
            if (row.mechanism === 'modem') {
                return 'unit';
            }
            if (row.mechanism === 'roll') {
                return 'meter';
            }

            return this.unitFor(row);
        },
    };
}
</script>
@endpush
@endonce
