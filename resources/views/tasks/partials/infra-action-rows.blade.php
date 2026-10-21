<div class="mb-4 border-b border-border pb-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm font-semibold text-text-main">Baris aksi pekerjaan</p>
            <p class="mt-1 text-xs text-text-secondary">Setiap tindakan mempunyai lokasi, barang, dan kunci sendiri agar stok tidak berkurang dua kali.</p>
        </div>
        <button type="button" @click="addInfraRow()" class="rounded-lg border border-primary px-3 py-2 text-xs font-semibold text-primary">Tambah aksi</button>
    </div>
    <p x-show="infraRows.length === 0" class="mt-3 text-xs text-text-muted">Belum ada baris aksi. Form sederhana di bawah tetap tersedia.</p>
    <div class="mt-3 divide-y divide-border" x-cloak x-show="infraRows.length > 0">
        <template x-for="(row, index) in infraRows" :key="row.action_key">
            <div class="py-4">
                <input type="hidden" :name="'infra_actions['+index+'][action_key]'" :value="row.action_key">
                <div class="mb-3 flex items-center justify-between">
                    <p class="font-semibold text-text-main" x-text="'Aksi '+(index+1)"></p>
                    <button type="button" @click="infraRows.splice(index,1)" class="text-xs font-medium text-error">Hapus</button>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block text-xs font-medium text-text-secondary">Tindakan
                        <select x-model="row.action_type" :name="'infra_actions['+index+'][action_type]'" class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-main">
                            <option value="inspect">Periksa</option><option value="install">Pasang</option>
                            <option value="retrieve">Cabut SN</option><option value="replace">Ganti SN</option>
                            <option value="port_connect">Sambung port</option><option value="port_release">Lepas port</option>
                        </select>
                    </label>
                    <label class="block text-xs font-medium text-text-secondary">Titik jaringan
                        <select x-model="row.network_node_id" :name="'infra_actions['+index+'][network_node_id]'" class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-main">
                            <option value="">Pilih node (O-REQ inspeksi kantor boleh kosong)</option>
                            @foreach($infraNodeOptions as $node)
                                <option value="{{ $node->id }}">{{ $node->breadcrumbLabel() }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <template x-if="['install','replace'].includes(row.action_type)">
                    <label class="mt-3 block text-xs font-medium text-text-secondary">SN baru
                        <select x-model="row.serial_id" :name="'infra_actions['+index+'][serial_id]'" class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-main">
                            <option value="">Tidak pakai SN</option>
                            @foreach($eligibleInfraSerials as $serial)
                                <option value="{{ $serial->id }}">{{ $serial->serial_number }} · {{ $serial->item?->name }}</option>
                            @endforeach
                        </select>
                    </label>
                </template>
                <template x-if="['retrieve','replace'].includes(row.action_type)">
                    <label class="mt-3 block text-xs font-medium text-text-secondary">SN lama pada node ini
                        <select x-model="row.old_serial_id" :name="'infra_actions['+index+'][old_serial_id]'" class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-main">
                            <option value="">Pilih SN terpasang</option>
                            @foreach($eligibleInfraRetrievableSerials as $serial)
                                <option value="{{ $serial->id }}">{{ $serial->serial_number }} · {{ $serial->currentNetworkNode?->breadcrumbLabel() }}</option>
                            @endforeach
                        </select>
                    </label>
                </template>
                <template x-if="row.action_type === 'install'">
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <label class="block text-xs font-medium text-text-secondary">Barang qty
                            <select x-model="row.item_id" :name="'infra_actions['+index+'][item_id]'" class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-main">
                                <option value="">Tidak pakai barang qty</option>
                                @foreach($eligibleInfraCustody as $item)
                                    <option value="{{ $item['item_id'] }}">{{ $item['name'] }} · sisa {{ $item['available'] }} {{ $item['unit'] }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-xs font-medium text-text-secondary">Jumlah barang
                            <input type="number" min="0.01" step="0.01" x-model="row.qty" :name="'infra_actions['+index+'][qty]'" class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-main">
                        </label>
                        <label class="block text-xs font-medium text-text-secondary">Roll kabel
                            <select x-model="row.roll_id" :name="'infra_actions['+index+'][roll_id]'" class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-main">
                                <option value="">Tidak pakai roll</option>
                                @foreach($eligibleInfraRolls as $roll)
                                    <option value="{{ $roll->id }}">{{ $roll->roll_code }} · sisa {{ $roll->length_remaining }} m</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-xs font-medium text-text-secondary">Meter terpakai
                            <input type="number" min="0.01" step="0.01" x-model="row.meters" :name="'infra_actions['+index+'][meters]'" class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-main">
                        </label>
                        <p class="sm:col-span-2 text-xs text-text-muted">Pilih tepat satu: SN, barang qty, atau roll.</p>
                    </div>
                </template>
                <template x-if="['install','replace'].includes(row.action_type) && @js($infraNodeOptions->filter(fn ($node) => $node->nodeType?->code === 'olt_card')->pluck('id')->values()->all()).map(String).includes(String(row.network_node_id))">
                    <label class="mt-3 block text-xs font-medium text-text-secondary">Slot chassis OLT
                        <select x-model="row.slot_id" :name="'infra_actions['+index+'][slot_id]'" class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-main">
                            <option value="">Pilih slot</option>
                            @foreach($infraSlotOptions as $slot)
                                <option value="{{ $slot->id }}">Chassis #{{ $slot->chassis_node_id }} · slot {{ $slot->number }}</option>
                            @endforeach
                        </select>
                    </label>
                </template>
                <template x-if="['inspect','port_connect','port_release'].includes(row.action_type)">
                    <label class="mt-3 block text-xs font-medium text-text-secondary">Port asal (opsional untuk pemeriksaan)
                        <select x-model="row.port_id" :name="'infra_actions['+index+'][port_id]'" class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-main">
                            <option value="">Pilih port</option>
                            @foreach($infraPortOptions as $port)
                                <option value="{{ $port->id }}">Node #{{ $port->network_node_id }} · port {{ $port->number }}</option>
                            @endforeach
                        </select>
                    </label>
                </template>
                <template x-if="row.action_type === 'port_connect'">
                    <label class="mt-3 block text-xs font-medium text-text-secondary">Node tujuan
                        <select x-model="row.target_node_id" :name="'infra_actions['+index+'][target_node_id]'" class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-main">
                            <option value="">Pilih node anak</option>
                            @foreach($infraNodeOptions as $node)
                                <option value="{{ $node->id }}">{{ $node->breadcrumbLabel() }}</option>
                            @endforeach
                        </select>
                    </label>
                </template>
                <template x-if="row.action_type === 'port_connect'">
                    <label class="mt-3 block text-xs font-medium text-text-secondary">Port tujuan
                        <select x-model="row.target_port_id" :name="'infra_actions['+index+'][target_port_id]'" class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-main">
                            <option value="">Pilih port pada node tujuan</option>
                            @foreach($infraPortOptions as $port)
                                <option value="{{ $port->id }}">Node #{{ $port->network_node_id }} · port {{ $port->number }}</option>
                            @endforeach
                        </select>
                    </label>
                </template>
                <label class="mt-3 block text-xs font-medium text-text-secondary">Hasil tindakan
                    <textarea x-model="row.result_note" :name="'infra_actions['+index+'][result_note]'" rows="2" maxlength="1000" class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-main" placeholder="Kondisi dan hasil pekerjaan"></textarea>
                </label>
            </div>
        </template>
    </div>
    @error('infra_actions')<p class="mt-2 text-xs text-error">{{ $message }}</p>@enderror
</div>
