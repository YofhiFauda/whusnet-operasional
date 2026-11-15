{{--
    Langkah Perangkat Tambahan (ADHOC-190 task 4, spec §4.2) — ditampilkan di bawah
    Section Distribusi Jaringan (ODP / OLT). Submit-nya cuma menyimpan PILIHAN SN
    (draft pointer `extra_device_serial_ids`, sejak 2026-11-14) — bisa disimpan
    ulang & diganti, SN baru dipasang di Laporan Speedtest. Tetap punya form sendiri (#form-additional-devices, di-render
    di report.blade.php di luar form-pemasangan). Karena form itu ada di luar
    markup ini, setiap kontrol wajib membawa atribut form="form-additional-devices"
    supaya ikut tersubmit ke form tersebut, bukan ke form-pemasangan. Tidak tampil
    sama sekali kalau paket tidak mengatur perangkat tambahan ($requiredExtraDevices = 0).

    Tidak ada dropdown Status (dihapus 2026-11-14): SN yang dipilih = dipasang.
    Daftar SN = SEMUA perangkat ber-SN installable di custody tim, bukan cuma
    AP/STB (AdditionalDeviceCustodyService::eligibleSerialsForExtraDevice()).

    Baris keputusan dibuat dinamis (Alpine) supaya tidak semua custody langsung
    tampil: teknisi pilih Kategori → Nama Barang (SN) lewat combobox, lalu "Tambah
    Perangkat" untuk baris berikutnya. Baris tanpa SN tidak dikirim (name-nya
    dilepas), jadi server tidak menerima baris kosong.

    Pola Kategori → Nama Barang sama dengan "Barang Dipasang" Step 5
    (<x-installation-item-rows>, ADHOC-297): <select> tunggal berlabel
    "Kategori — Nama · SN" gak bisa dicari, teknisi yang pegang banyak STB/AP
    harus scroll satu-satu. Kolom Jumlah sengaja gak ada — perangkat tambahan
    selalu 1 SN per baris. Combobox di sini TANPA name: nilai dikirim cuma lewat
    input hidden ber-atribut form="form-additional-devices", jadi gak ada field
    nyasar ke form-pemasangan.
--}}
@if($requiredExtraDevices > 0)
    @php
        $installedExtraDevices = $customer->additionalDevices()->whereNotNull('inventory_serial_id')->where('status', 'terpasang')->get();
        $serialOptions = $eligibleExtraDeviceSerials->map(fn ($serial) => [
            'id' => (string) $serial->id,
            'category' => (string) ($serial->item?->item_category_id ?? ''),
            'category_name' => $serial->item?->category?->name ?? 'Tanpa Kategori',
            'label' => ($serial->item?->name).' · SN '.$serial->serial_number,
        ])->values()->all();
        // Prefill: old() (gagal validasi) menang, lalu pilihan yang sudah
        // disimpan — supaya setelah simpan/refresh pilihan tetap tampil dan
        // bisa diganti, sama seperti Barang Dipasang Step 5.
        $savedDecisions = collect($installation?->extra_device_serial_ids ?? [])
            ->map(fn ($serialId) => ['inventory_serial_id' => $serialId])
            ->all();
        $initialRows = collect(old('decisions', $savedDecisions))
            ->filter(fn ($decision) => filled($decision['inventory_serial_id'] ?? null))
            ->map(fn ($decision) => ['category' => '', 'serial' => (string) $decision['inventory_serial_id']])
            ->values()
            ->all();
        $shortageChecked = (bool) old('kekurangan_perangkat', $installation?->extra_device_shortage);
        if ($initialRows === []) {
            $initialRows = [['category' => '', 'serial' => '']];
        }
    @endphp
    <div class="p-3 sm:p-3.5 rounded-lg bg-white/60 dark:bg-slate-900/40 border border-slate-200/70 dark:border-slate-700/60 space-y-3"
         x-data="{
            options: @js($serialOptions),
            rows: @js($initialRows),
            init() {
                // Baris dari old() cuma bawa SN — kategori diturunkan dari opsinya.
                this.rows.forEach((row) => {
                    row.category = row.category || (this.optionById(row.serial)?.category ?? '');
                });
            },
            optionById(id) {
                return this.options.find((option) => option.id === id);
            },
            takenSerials(exceptIndex) {
                return this.rows.filter((row, index) => index !== exceptIndex).map((row) => row.serial).filter(Boolean);
            },
            optionsFor(index) {
                const taken = this.takenSerials(index);
                return this.options.filter((option) => ! taken.includes(option.id));
            },
            categoryOptionsFor(index) {
                const categories = new Map();
                this.optionsFor(index).forEach((option) => {
                    const entry = categories.get(option.category) ?? { name: option.category_name, count: 0 };
                    entry.count++;
                    categories.set(option.category, entry);
                });
                return [...categories].map(([value, entry]) => ({ value, label: entry.name, hint: `${entry.count} barang dipegang` }));
            },
            serialOptionsFor(index) {
                const category = this.rows[index]?.category ?? '';
                return this.optionsFor(index)
                    .filter((option) => ! category || option.category === category)
                    .map((option) => ({ value: option.id, label: option.label, search: `${option.label} ${option.category_name}`, hint: option.category_name }));
            },
            onCategoryChoose(index, value) {
                const row = this.rows[index];
                row.category = value ?? '';
                if (row.serial && this.optionById(row.serial)?.category !== row.category) {
                    row.serial = '';
                }
            },
            onSerialChoose(index, value) {
                const row = this.rows[index];
                row.serial = value ?? '';
                row.category = this.optionById(row.serial)?.category ?? row.category;
            },
            canAddRow() {
                return this.rows.length < this.options.length;
            },
            addRow() {
                this.rows.push({ category: '', serial: '' });
            },
            removeRow(index) {
                this.rows.splice(index, 1);
            },
         }">
        <div>
            <label class="block font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">
                Perangkat Tambahan — Wajib {{ $requiredExtraDevices }} Perangkat
            </label>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">Pilih perangkat ber-SN dari custody tim yang dipasang di pelanggan ini. Pilihan bisa disimpan & diganti kapan saja — baru tercatat <strong>Terpasang</strong> saat Laporan Speedtest. Perangkat yang tidak dipasang (atau dibawa untuk pelanggan lain) cukup tidak dipilih. Wajib disimpan sebelum Laporan Speedtest.</p>
        </div>

        @if($installation?->extra_devices_reported_at)
            <p class="text-[11px] text-emerald-700 dark:text-emerald-400 font-semibold">✓ Pilihan tersimpan {{ $installation->extra_devices_reported_at->format('d/m/Y H:i') }} — masih bisa diganti sampai Laporan Speedtest.</p>
        @endif
        @if($installation?->extra_device_shortage)
            <p class="text-[11px] text-amber-700 dark:text-amber-400 font-semibold">⚠ Kekurangan perangkat dilaporkan: {{ $installation->extra_device_shortage_reason }}</p>
        @endif
        @error('extra_devices')
            <p class="text-[11px] text-rose-600 dark:text-rose-400 font-semibold">{{ $message }}</p>
        @enderror
        @error('decisions')
            <p class="text-[11px] text-rose-600 dark:text-rose-400 font-semibold">{{ $message }}</p>
        @enderror
        @foreach($errors->get('decisions.*') as $messages)
            <p class="text-[11px] text-rose-600 dark:text-rose-400 font-semibold">{{ $messages[0] }}</p>
        @endforeach

        @if($installedExtraDevices->isNotEmpty())
            <ul class="text-[11px] text-emerald-700 dark:text-emerald-400 space-y-0.5">
                @foreach($installedExtraDevices as $device)
                    <li>✓ {{ $device->device_type }}{{ trim(' '.$device->brand.' '.$device->model) !== '' ? ' — '.trim($device->brand.' '.$device->model) : '' }} — SN {{ $device->serial_number }} (sudah terpasang)</li>
                @endforeach
            </ul>
        @endif

        @if($eligibleExtraDeviceSerials->isEmpty())
            <p class="text-[11px] text-amber-600 dark:text-amber-400 font-semibold">⚠ Tim ini tidak memegang perangkat ber-SN di custody. Kalau perangkat kurang di luar kendali Anda, laporkan kekurangan di bawah.</p>
        @else
            <div class="space-y-2">
                <template x-for="(row, index) in rows" :key="index">
                    <div class="flex flex-col sm:flex-row sm:items-end gap-2 p-2.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
                        <div class="w-full sm:w-44 shrink-0">
                            <label class="block mb-1 text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Kategori</label>
                            <x-combobox
                                options-expr="() => categoryOptionsFor(index)"
                                value-expr="() => row.category"
                                on-select-expr="onCategoryChoose(index, value)"
                                placeholder="Pilih kategori..."
                            />
                        </div>
                        <div class="flex-1 min-w-0">
                            <label class="block mb-1 text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Nama Barang</label>
                            <x-combobox
                                options-expr="() => serialOptionsFor(index)"
                                value-expr="() => row.serial"
                                on-select-expr="onSerialChoose(index, value)"
                                placeholder="Ketik nama barang / SN..."
                            />
                        </div>
                        <input type="hidden" form="form-additional-devices" :value="row.serial"
                               :name="row.serial ? `decisions[${index}][inventory_serial_id]` : null">
                        <button type="button" x-show="rows.length > 1" @click="removeRow(index)" title="Hapus baris"
                                class="self-end sm:self-auto px-2 py-2 rounded-lg text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/30">
                            <x-ui.icon name="x" class="w-3.5 h-3.5" />
                        </button>
                    </div>
                </template>

                <button type="button" x-show="canAddRow()" @click="addRow()"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-dashed border-sky-400 dark:border-sky-700 text-xs font-medium text-sky-700 dark:text-sky-300 hover:bg-sky-50 dark:hover:bg-sky-950/30">
                    <span class="text-sm leading-none">+</span>
                    <span>Tambah Perangkat</span>
                </button>
            </div>
        @endif

        <div class="space-y-2">
            <label class="inline-flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300">
                <input type="checkbox" form="form-additional-devices" name="kekurangan_perangkat" value="1" id="kekurangan_perangkat"
                       onchange="document.getElementById('kekurangan-perangkat-alasan-wrap').classList.toggle('hidden', ! this.checked)"
                       @checked($shortageChecked)>
                <span>Laporkan kekurangan perangkat (rusak/hilang di luar kendali)</span>
            </label>
            <div id="kekurangan-perangkat-alasan-wrap" class="{{ $shortageChecked ? '' : 'hidden' }}">
                <textarea form="form-additional-devices" name="kekurangan_perangkat_alasan" rows="2" maxlength="500"
                          class="w-full text-xs px-3.5 py-2.5 border @error('kekurangan_perangkat_alasan') border-rose-400 @else border-slate-200 dark:border-slate-700 @enderror rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500"
                          placeholder="Alasan kekurangan perangkat (wajib)...">{{ old('kekurangan_perangkat_alasan', $installation?->extra_device_shortage_reason) }}</textarea>
                @error('kekurangan_perangkat_alasan')
                    <p class="text-[11px] text-rose-600 dark:text-rose-400 font-semibold mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" form="form-additional-devices" class="w-full sm:w-auto px-4 py-2 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold shadow-2xs inline-flex items-center justify-center gap-1.5">
                <x-ui.icon name="save" class="w-3.5 h-3.5" />
                <span>Simpan Perangkat Tambahan</span>
            </button>
        </div>
    </div>
@endif
