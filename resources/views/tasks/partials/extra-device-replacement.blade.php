{{--
    Ganti Perangkat Tambahan (AP/STB) pasca-pemasangan — Laporan Maintenance &
    C-REQ. OPSIONAL: tampil hanya kalau pelanggan punya AP/STB Gudang yang
    sedang terpasang. Eksekusi di AdditionalDeviceReplacementService
    (pasang pengganti dari custody + cabut yang lama + perbarui Detail Pelanggan).

    Field memakai name="extra_replacements[i][...]" dan nama itu DILEPAS selama
    baris belum lengkap (pola sama _additional_devices_step di Pemasangan),
    jadi server tidak pernah menerima baris setengah terisi.
--}}
@if(($replaceableExtraDevices ?? collect())->isNotEmpty())
    @php
        $oldOptions = $replaceableExtraDevices->map(fn ($device) => [
            'id' => (string) $device->id,
            'category' => (string) $device->inventorySerial?->item?->item_category_id,
            // Baris teks bebas (legacy) tidak punya kategori Gudang → category kosong,
            // pengganti apa pun boleh dipilih (data legacy sering tak cocok dengan lapangan).
            'label' => ($device->device_type ?: 'Perangkat Tambahan').' — '.($device->serial_number ? 'SN '.$device->serial_number : 'tanpa SN')
                .($device->inventory_serial_id === null ? ' (data lama, tanpa stok)' : ''),
        ])->values()->all();
        $newOptions = $eligibleExtraSerials->map(fn ($serial) => [
            'id' => (string) $serial->id,
            'category' => (string) $serial->item?->item_category_id,
            'label' => ($serial->item?->category?->name).' — '.($serial->item?->name).' · SN '.$serial->serial_number,
        ])->values()->all();
        $initialReplacementRows = collect(old('extra_replacements', []))
            ->map(fn ($row) => [
                'old' => (string) ($row['old_device_id'] ?? ''),
                'new' => (string) ($row['new_serial_id'] ?? ''),
                'condition' => $row['old_condition'] ?? '',
                'note' => $row['note'] ?? '',
            ])->values()->all();
    @endphp
    <div class="px-6 py-4 text-xs" style="border-bottom:1px solid var(--color-border)"
         x-data="{
            oldOptions: @js($oldOptions),
            newOptions: @js($newOptions),
            rows: @js($initialReplacementRows),
            takenOld(except) { return this.rows.filter((r, i) => i !== except).map(r => r.old).filter(Boolean); },
            takenNew(except) { return this.rows.filter((r, i) => i !== except).map(r => r.new).filter(Boolean); },
            oldFor(i) { const t = this.takenOld(i); return this.oldOptions.filter(o => !t.includes(o.id)); },
            newFor(i) {
                const t = this.takenNew(i);
                const old = this.oldOptions.find(o => o.id === this.rows[i].old);
                return this.newOptions.filter(o => !t.includes(o.id) && (!old || !old.category || o.category === old.category));
            },
            complete(i) { const r = this.rows[i]; return r.old && r.new && r.condition; },
            canAdd() { return this.rows.length < this.oldOptions.length && this.rows.length < this.newOptions.length; },
         }">
        <p class="mb-1" style="font-size:11px;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:var(--color-text-muted)">
            Ganti Perangkat Tambahan (AP/STB) <span class="font-normal normal-case">(opsional)</span>
        </p>
        <p class="text-[10px] mb-3 leading-relaxed font-normal" style="color:var(--color-text-muted)">
            Isi HANYA kalau AP/STB pelanggan rusak atau bermasalah dan Anda memasang penggantinya dari custody. Perangkat lama dari Gudang otomatis dicabut dan masuk alur Terima Retur; perangkat "data lama, tanpa stok" cukup ditandai ditarik karena memang tidak tercatat di Gudang. Detail Pelanggan ikut diperbarui. Pengganti harus sejenis untuk perangkat Gudang (AP diganti AP).
        </p>

        @error('extra_replacements')<p class="text-[11px] mb-2" style="color:var(--color-error)">{{ $message }}</p>@enderror
        @foreach($errors->get('extra_replacements.*') as $messages)
            <p class="text-[11px] mb-2" style="color:var(--color-error)">{{ $messages[0] }}</p>
        @endforeach

        @if($eligibleExtraSerials->isEmpty())
            <p class="text-[10px] leading-relaxed" style="color:var(--color-warning,#d97706)">⚠ Tim ini tidak memegang AP/STB di custody — ambil pengganti dari Gudang dulu kalau perangkat pelanggan perlu diganti.</p>
        @else
            <div class="space-y-2">
                <template x-for="(row, index) in rows" :key="index">
                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 p-2.5 rounded-md" style="border:1px solid var(--color-border);background:var(--color-background)">
                        <select x-model="row.old" @change="row.new = ''"
                                :name="complete(index) ? `extra_replacements[${index}][old_device_id]` : null"
                                class="sm:col-span-4 rounded-md text-xs" style="border:1px solid var(--color-border);background:var(--color-background);color:var(--color-text-main);padding:8px 10px">
                            <option value="">— Perangkat lama (terpasang) —</option>
                            <template x-for="o in oldFor(index)" :key="o.id"><option :value="o.id" x-text="o.label"></option></template>
                        </select>
                        <select x-model="row.new" :disabled="!row.old"
                                :name="complete(index) ? `extra_replacements[${index}][new_serial_id]` : null"
                                class="sm:col-span-4 rounded-md text-xs" style="border:1px solid var(--color-border);background:var(--color-background);color:var(--color-text-main);padding:8px 10px">
                            <option value="">— SN pengganti (custody) —</option>
                            <template x-for="o in newFor(index)" :key="o.id"><option :value="o.id" x-text="o.label"></option></template>
                        </select>
                        <select x-model="row.condition" :disabled="!row.new"
                                :name="complete(index) ? `extra_replacements[${index}][old_condition]` : null"
                                class="sm:col-span-3 rounded-md text-xs" style="border:1px solid var(--color-border);background:var(--color-background);color:var(--color-text-main);padding:8px 10px">
                            <option value="">— Kondisi lama —</option>
                            <option value="rusak">Rusak</option>
                            <option value="baik">Masih baik</option>
                        </select>
                        <button type="button" @click="rows.splice(index, 1)" title="Hapus baris" class="sm:col-span-1 rounded-md text-xs" style="color:var(--color-error)">✕</button>
                        <input type="text" x-model="row.note" maxlength="255" placeholder="Catatan (opsional): mis. port LAN mati"
                               :name="complete(index) ? `extra_replacements[${index}][note]` : null"
                               class="sm:col-span-12 rounded-md text-xs" style="border:1px solid var(--color-border);background:var(--color-background);color:var(--color-text-main);padding:8px 10px">
                    </div>
                </template>
                <button type="button" x-show="canAdd()" @click="rows.push({ old: '', new: '', condition: '', note: '' })"
                        class="px-3 py-1.5 rounded-md text-xs font-medium" style="border:1px dashed var(--color-primary);color:var(--color-primary)">
                    + Ganti Perangkat Tambahan
                </button>
            </div>
        @endif
    </div>
@endif
