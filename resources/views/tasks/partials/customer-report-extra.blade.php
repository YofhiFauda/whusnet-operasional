@if($task->customer_id)
    <section class="border-b border-border px-4 py-5 sm:px-6">
        <details @if($errors->any()) open @endif>
            <summary class="cursor-pointer text-sm font-semibold text-primary">Pekerjaan pada pelanggan (opsional)</summary>
            <p class="mt-2 text-sm text-text-secondary">Buka bila task ini juga mengganti modem atau memakai material yang ditinggal di pelanggan. Barang jaringan dicatat di tindakan pada langkah 02.</p>
            <div class="mt-5 border-t border-border pt-4">
                <p class="mb-3 text-sm font-semibold text-text-main">Material pelanggan</p>
                <p class="mb-3 text-xs text-text-muted">Pilih hanya barang pasif dari custody tim yang ditinggal di pelanggan. Jangan catat barang yang sama lagi pada tindakan jaringan.</p>
                <x-material-rows name="materials" :categories="$itemCategories" :rows="$materialRows"
                                 :restrict-to-custody="true" :custody-options="$eligiblePassiveCustody"
                                 empty-label="Tidak ada material pelanggan yang dipakai." />
            </div>
            <div class="mt-5 border-t border-border pt-4">
                <label class="block text-sm font-semibold text-text-main">Modem pelanggan</label>
                <p class="mt-1 text-xs text-text-muted">Biarkan Tidak ganti bila pekerjaan ini tidak menyentuh modem pelanggan.</p>
                <select name="modem_ownership_choice" x-model="customerModemChoice" class="mt-3 w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-text-main sm:max-w-md">
                    <option value="tidak_ganti">Tidak ganti modem</option>
                    <option value="sewa_gudang">Pasang modem sewa dari Gudang</option>
                    <option value="beli_gudang">Pelanggan beli modem dari Gudang</option>
                    <option value="beli_sendiri">Pelanggan memakai modem sendiri</option>
                </select>
                <fieldset x-show="['sewa_gudang', 'beli_gudang'].includes(customerModemChoice)" :disabled="!['sewa_gudang', 'beli_gudang'].includes(customerModemChoice)" x-cloak class="mt-4 min-w-0">
                    <label class="block text-sm font-medium text-text-secondary">SN modem dari custody tim <span class="text-error">*</span>
                        <select name="selected_inventory_serial_id" required class="mt-1.5 w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-text-main">
                            <option value="">Pilih SN modem pelanggan</option>
                            @foreach($eligibleSerials as $serial)
                                <option value="{{ $serial->id }}" @selected((string) old('selected_inventory_serial_id') === (string) $serial->id)>{{ $serial->item?->name }} · SN {{ $serial->serial_number }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div x-show="customerModemChoice === 'beli_gudang'" x-cloak class="mt-4">
                        <label class="block text-sm font-medium text-text-secondary">Harga jual modem <span class="text-error">*</span>
                            <input type="number" step="0.01" min="0.01" :disabled="customerModemChoice !== 'beli_gudang'" name="modem_sale_price" required value="{{ old('modem_sale_price') }}" placeholder="Contoh: 150000"
                                   class="mt-1.5 w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-text-main">
                        </label>
                    </div>
                </fieldset>
                <fieldset x-show="customerModemChoice === 'beli_sendiri'" :disabled="customerModemChoice !== 'beli_sendiri'" x-cloak class="mt-4 grid min-w-0 gap-4 sm:grid-cols-3">
                    <label class="block text-sm font-medium text-text-secondary">Merek <span class="text-error">*</span>
                        <input type="text" name="manual_device_brand" required value="{{ old('manual_device_brand') }}" maxlength="100" placeholder="Contoh: TP-Link"
                               class="mt-1.5 w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-text-main">
                    </label>
                    <label class="block text-sm font-medium text-text-secondary">Model <span class="text-error">*</span>
                        <input type="text" name="manual_device_model" required value="{{ old('manual_device_model') }}" maxlength="100" placeholder="Contoh: Archer C6"
                               class="mt-1.5 w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-text-main">
                    </label>
                    <label class="block text-sm font-medium text-text-secondary">Nomor seri <span class="text-error">*</span>
                        <input type="text" name="manual_device_serial_number" required value="{{ old('manual_device_serial_number') }}" maxlength="100" placeholder="SN pada stiker"
                               class="mt-1.5 w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-text-main">
                    </label>
                </fieldset>
            </div>
            <div class="mt-5 border-t border-border pt-4">
                <p class="text-sm font-semibold text-text-main">Kabel pelanggan dari roll <span class="font-normal text-text-muted">(opsional)</span></p>
                <p class="mt-1 text-xs text-text-muted">Gunakan untuk kabel yang ditinggal di pelanggan. Kabel feeder/distribusi ke node dicatat pada tindakan jaringan.</p>
                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm font-medium text-text-secondary">Roll pelanggan
                        <select name="selected_inventory_roll_id" x-model="customerRoll" class="mt-1.5 w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-text-main">
                            <option value="">Tidak memakai roll pelanggan</option>
                            @foreach($eligibleRolls as $roll)
                                <option value="{{ $roll->id }}" @selected((string) old('selected_inventory_roll_id') === (string) $roll->id)>{{ $roll->item?->name }} · {{ $roll->roll_code }} · sisa {{ $roll->length_remaining }} m</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-medium text-text-secondary">Meter terpakai
                        <input type="number" step="0.01" min="0.01" :disabled="!customerRoll" name="roll_meters_used" required value="{{ old('roll_meters_used') }}" placeholder="Contoh: 8.5"
                               class="mt-1.5 w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-text-main">
                    </label>
                </div>
            </div>
        </details>
    </section>
@endif
