{{--
    Langkah Perangkat Tambahan (ADHOC-190 task 4, spec §4.2) — ditampilkan di bawah
    Section Distribusi Jaringan (ODP / OLT). Submit-nya langsung memasang SN ke
    pelanggan, jadi tetap punya form sendiri (#form-additional-devices, di-render
    di report.blade.php di luar form-pemasangan). Karena form itu ada di luar
    markup ini, setiap kontrol wajib membawa atribut form="form-additional-devices"
    supaya ikut tersubmit ke form tersebut, bukan ke form-pemasangan. Tidak tampil
    sama sekali kalau paket tidak mengatur perangkat tambahan ($requiredExtraDevices = 0).

    Baris keputusan dibuat dinamis (Alpine) supaya tidak semua custody langsung
    tampil: teknisi pilih SN lewat combobox, lalu "Tambah Perangkat" untuk baris
    berikutnya. Baris tanpa SN tidak dikirim (name-nya dilepas), jadi server tidak
    menerima baris kosong.
--}}
@if($requiredExtraDevices > 0)
    @php
        $installedExtraDevices = $customer->additionalDevices()->whereNotNull('inventory_serial_id')->where('status', 'terpasang')->get();
        $outcomeOptions = ['terpasang' => 'Terpasang', 'kembali_gudang' => 'Kembali ke Gudang', 'rusak' => 'Rusak', 'hilang' => 'Hilang'];
        $serialOptions = $eligibleExtraDeviceSerials->map(fn ($serial) => [
            'id' => (string) $serial->id,
            'label' => ($serial->item?->category?->name).' — '.($serial->item?->name).' · SN '.$serial->serial_number,
        ])->values()->all();
        $initialRows = collect(old('decisions', []))
            ->filter(fn ($decision) => filled($decision['inventory_serial_id'] ?? null))
            ->map(fn ($decision) => ['serial' => (string) $decision['inventory_serial_id'], 'outcome' => $decision['outcome'] ?? ''])
            ->values()
            ->all();
        if ($initialRows === []) {
            $initialRows = [['serial' => '', 'outcome' => '']];
        }
    @endphp
    <div class="p-3 sm:p-3.5 rounded-lg bg-white/60 dark:bg-slate-900/40 border border-slate-200/70 dark:border-slate-700/60 space-y-3"
         x-data="{
            options: @js($serialOptions),
            rows: @js($initialRows),
            takenSerials(exceptIndex) {
                return this.rows.filter((row, index) => index !== exceptIndex).map((row) => row.serial).filter(Boolean);
            },
            optionsFor(index) {
                const taken = this.takenSerials(index);
                return this.options.filter((option) => ! taken.includes(option.id));
            },
            canAddRow() {
                return this.rows.length < this.options.length;
            },
            addRow() {
                this.rows.push({ serial: '', outcome: '' });
            },
            removeRow(index) {
                this.rows.splice(index, 1);
            },
         }">
        <div>
            <label class="block font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">
                Perangkat Tambahan (AP / Router / STB) — Wajib {{ $requiredExtraDevices }} Perangkat
            </label>
            @if($additionalDeviceRequirements !== [])
                <p class="text-[11px] font-semibold text-sky-700 dark:text-sky-300">Kebutuhan paket: 1 modem utama + {{ $additionalDeviceRequirements['access_point'] ?? 0 }} AP.</p>
            @endif
            <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">Pilih perangkat tambahan pelanggan ini dari custody tim, lalu beri status akhirnya. Perangkat yang dibawa untuk pelanggan lain tidak perlu dipilih. Wajib disimpan sebelum Laporan Speedtest.</p>
        </div>

        @if($installation?->extra_devices_reported_at)
            <p class="text-[11px] text-emerald-700 dark:text-emerald-400 font-semibold">✓ Sudah dilaporkan {{ $installation->extra_devices_reported_at->format('d/m/Y H:i') }}.</p>
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
                    <li>✓ {{ $device->device_type }} — SN {{ $device->serial_number }} (sudah terpasang)</li>
                @endforeach
            </ul>
        @endif

        @if($eligibleExtraDeviceSerials->isEmpty())
            <p class="text-[11px] text-amber-600 dark:text-amber-400 font-semibold">⚠ Tim ini tidak memegang perangkat tambahan di custody. Kalau perangkat kurang di luar kendali Anda, laporkan kekurangan di bawah.</p>
        @else
            <div class="space-y-2">
                <template x-for="(row, index) in rows" :key="index">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-2 p-2.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
                        <select form="form-additional-devices" x-model="row.serial"
                                :name="row.serial ? `decisions[${index}][inventory_serial_id]` : null"
                                class="flex-1 min-w-0 text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500">
                            <option value="">— Pilih SN perangkat —</option>
                            <template x-for="option in optionsFor(index)" :key="option.id">
                                <option :value="option.id" x-text="option.label"></option>
                            </template>
                        </select>
                        <select form="form-additional-devices" x-model="row.outcome" :disabled="! row.serial"
                                :name="row.serial ? `decisions[${index}][outcome]` : null"
                                class="text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 disabled:opacity-50 sm:w-44">
                            <option value="">— Pilih status —</option>
                            @foreach($outcomeOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
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
                       @checked(old('kekurangan_perangkat'))>
                <span>Laporkan kekurangan perangkat (rusak/hilang di luar kendali)</span>
            </label>
            <div id="kekurangan-perangkat-alasan-wrap" class="{{ old('kekurangan_perangkat') ? '' : 'hidden' }}">
                <textarea form="form-additional-devices" name="kekurangan_perangkat_alasan" rows="2" maxlength="500"
                          class="w-full text-xs px-3.5 py-2.5 border @error('kekurangan_perangkat_alasan') border-rose-400 @else border-slate-200 dark:border-slate-700 @enderror rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500"
                          placeholder="Alasan kekurangan perangkat (wajib)...">{{ old('kekurangan_perangkat_alasan') }}</textarea>
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
