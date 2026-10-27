@extends('layouts.app')

@section('title', 'Laporan ' . $task->task_type->value . ' — ' . $task->task_number)

@php
    $isOfficeRequest = $task->task_type === \App\Enums\TaskType::OREQ;
    $infraUi = [
        'taskType' => $isOfficeRequest ? 'oreq' : 'infr',
        'rows' => old('infra_actions', []),
        'summary' => old('kendala_teknis', ''),
        'customerModemChoice' => old('modem_ownership_choice', 'tidak_ganti'),
        'customerRoll' => old('selected_inventory_roll_id', ''),
        'materialAction' => old('infra_material_action', ''),
        'materialSource' => old('infra_material_source_transaction_id', ''),
        'materialQty' => old('infra_material_qty', ''),
        'materialReason' => old('infra_material_reason', ''),
        'materialNode' => old('infra_target_network_node_id', ''),
        'materialRoll' => old('infra_material_correct_roll_id', ''),
        'nodes' => $infraNodeOptions->map(fn ($node) => [
            'id' => $node->id, 'label' => $node->breadcrumbLabel(),
            'type' => $node->nodeType?->code, 'type_id' => $node->node_type_id, 'parent_id' => $node->parent_id,
        ])->values()->all(),
        // Dropdown lokasi bertingkat (rancangan hierarki §10.2): opsi diambil
        // dari server per induk terpilih — URL dirender server, klien hanya
        // menambah query level/parent.
        'scope' => $infraScope,
        'locationOptionsUrl' => route('tasks.infrastructure.location-options', $task),
        'locationAssetsUrl' => route('tasks.infrastructure.location-assets', $task),
        'serials' => $eligibleInfraSerials->map(fn ($serial) => [
            'id' => $serial->id, 'label' => $serial->item?->name . ' · SN ' . $serial->serial_number,
            'item_node_type_id' => $serial->item?->infrastructure_node_type_id,
            'item_node_type_code' => $serial->item?->infrastructureNodeType?->code,
            'location_kind' => $serial->item?->infrastructure_location_kind?->value,
        ])->values()->all(),
        'oldSerials' => $eligibleInfraRetrievableSerials->map(fn ($serial) => [
            'id' => $serial->id, 'label' => $serial->item?->name . ' · SN ' . $serial->serial_number,
            'node_id' => $serial->current_network_node_id,
        ])->values()->all(),
        'items' => $eligibleInfraCustody->map(fn ($item) => [
            'id' => $item['item_id'], 'label' => $item['name'] . ' · sisa ' . $item['available'] . ' ' . $item['unit'],
        ])->values()->all(),
        'rolls' => $eligibleInfraRolls->map(fn ($roll) => [
            'id' => $roll->id, 'label' => $roll->item?->name . ' · ' . $roll->roll_code . ' · sisa ' . $roll->length_remaining . ' m',
        ])->values()->all(),
        'ports' => $infraPortOptions->map(fn ($port) => [
            'id' => $port->id, 'node_id' => $port->network_node_id,
            'label' => (string) $port->number, 'used' => (bool) $port->is_used,
        ])->values()->all(),
        'sources' => $infraMaterialSources->map(fn ($source) => [
            'id' => $source->id,
            'label' => $source->reference_number . ' · ' . $source->item?->name . ' · ' . $source->qty . ' ' . $source->item?->unit . ' · ' . $source->toNetworkNode?->breadcrumbLabel(),
        ])->values()->all(),
    ];
@endphp

@section('content')
<div class="mx-auto max-w-[960px] px-3 pb-24 pt-4 sm:px-5 sm:pb-8">
    <a href="{{ route('tasks.show', $task) }}" class="mb-3 inline-flex min-h-11 items-center text-sm text-text-secondary hover:text-primary">← Kembali ke detail task</a>
    <header class="mb-6">
        <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-text-muted">{{ $isOfficeRequest ? 'Pekerjaan internal · O-REQ' : 'Pekerjaan jaringan · INFR-REQ' }}</p>
        <h1 class="page-header-title">Laporan pekerjaan</h1>
        <p class="page-header-subtitle mt-1">Catat apa yang dilakukan di lapangan. Isian berikutnya mengikuti tindakan yang Anda pilih.</p>
    </header>

    @if($errors->any() || session('error'))
        <div role="alert" class="mb-5 border-l-4 border-error py-2 pl-4 text-sm text-error">
            <p class="font-semibold">Laporan belum tersimpan.</p>
            @if(session('error'))<p>{{ session('error') }}</p>@endif
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    <form action="{{ route('tasks.maintenance.store', $task) }}" method="POST" enctype="multipart/form-data"
          class="no-confirm" x-data="infrastructureReport(@js($infraUi))" x-ref="form"
          data-location-options-url="{{ route('tasks.infrastructure.location-options', $task) }}"
          data-location-assets-url="{{ route('tasks.infrastructure.location-assets', $task) }}"
          @input="reviewing = false" @change="reviewing = false" @submit.prevent="submit()">
        @csrf
        <div class="rounded-lg border border-border bg-surface">
            <section class="border-b border-border px-4 py-5 sm:px-6">
                <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-text-muted">Tugas dari FOP</p>
                <div class="grid gap-3 text-sm sm:grid-cols-2">
                    <div><span class="block text-xs text-text-muted">Nomor task</span><span class="font-mono font-semibold text-text-main">{{ $task->task_number }}</span></div>
                    <div><span class="block text-xs text-text-muted">Wilayah</span><span class="font-medium text-text-main">{{ $task->pop?->name ?? 'Belum ditentukan' }}</span></div>
                    <div class="sm:col-span-2"><span class="block text-xs text-text-muted">Pekerjaan yang diminta</span><span class="font-medium text-text-main">{{ $task->description ?: $task->title }}</span></div>
                </div>
            </section>
            <section class="border-b border-border px-4 py-5 sm:px-6">
                <label for="kendala_teknis" class="block text-xs font-semibold uppercase tracking-wider text-text-muted">01 · Ringkasan pekerjaan <span class="text-error">*</span></label>
                <p class="mb-3 mt-1 text-sm text-text-secondary">Apa yang ditemukan, apa yang dikerjakan, dan bagaimana hasilnya? Ini yang pertama dibaca FOP.</p>
                <textarea id="kendala_teknis" name="kendala_teknis" rows="4" required x-model="summary"
                          class="w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-text-main focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                          placeholder="Contoh: Ditemukan port ODC longgar. Koneksi diperbaiki dan sinyal kembali normal.">{{ old('kendala_teknis') }}</textarea>
            </section>

            <section class="border-b border-border px-4 py-5 sm:px-6">
                <p class="text-xs font-semibold uppercase tracking-wider text-text-muted">02 · Apa yang dikerjakan?</p>
                <p class="mt-1 text-sm text-text-secondary">Pilih jalur yang sesuai. Stok hanya berubah bila Anda mencatat pemakaian atau pelepasan barang.</p>
                <div class="mt-4 grid gap-x-5 border-t border-border sm:grid-cols-3">
                    <button type="button" @click="setMode('actions')" :aria-pressed="mode === 'actions'"
                            class="border-b border-border py-3 text-left text-sm font-semibold"
                            :class="mode === 'actions' ? 'text-primary' : 'text-text-main hover:text-primary'">
                        Tindakan di jaringan
                        <span class="mt-1 block text-xs font-normal text-text-muted">Periksa, pasang, cabut, ganti, atau sambung port.</span>
                    </button>
                    <button type="button" @click="setMode('correction')" :aria-pressed="mode === 'correction'"
                            class="border-b border-border py-3 text-left text-sm font-semibold"
                            :class="mode === 'correction' ? 'text-primary' : 'text-text-main hover:text-primary'">
                        Koreksi / cabut material
                        <span class="mt-1 block text-xs font-normal text-text-muted">Perbaiki catatan qty/roll atau cabut fisiknya.</span>
                    </button>
                    <button type="button" @click="setMode('none')" :aria-pressed="mode === 'none'"
                            class="border-b border-border py-3 text-left text-sm font-semibold"
                            :class="mode === 'none' ? 'text-primary' : 'text-text-main hover:text-primary'">
                        Tanpa tindakan jaringan
                        <span class="mt-1 block text-xs font-normal text-text-muted">{{ $isOfficeRequest ? 'Untuk pekerjaan kantor tanpa lokasi jaringan.' : 'Jika pekerjaan tidak mengubah titik jaringan.' }}</span>
                    </button>
                </div>
                <p x-show="mode === 'none'" x-cloak class="mt-4 text-sm text-text-secondary">Tidak ada perubahan pada aset, slot, atau port jaringan. Bila bagian pelanggan di bawah diisi, pemakaian barang pelanggan tetap dicatat.</p>

                <fieldset x-show="mode === 'actions'" :disabled="mode !== 'actions'" x-cloak class="mt-5 min-w-0">
                    <p class="text-sm font-semibold text-text-main">Pilih tindakan</p>
                    <p class="mt-1 text-xs text-text-muted">Satu tindakan = satu lokasi dan satu hasil. Tambah lagi bila pekerjaan mencakup beberapa langkah.</p>
                    <div class="mt-3 grid gap-x-6 sm:grid-cols-2">
                        <template x-for="(name, type) in actionNames" :key="type">
                            <button type="button" @click="addAction(type)" class="flex min-h-16 items-start gap-3 border-b border-border py-3 text-left hover:text-primary">
                                <span class="font-mono text-base text-primary">+</span>
                                <span><span class="block text-sm font-semibold text-text-main" x-text="name"></span><span class="mt-0.5 block text-xs text-text-muted" x-text="actionHints[type]"></span></span>
                            </button>
                        </template>
                    </div>
                    <p x-show="rows.length === 0" class="mt-4 text-xs text-text-muted">Belum ada tindakan. Pilih satu di atas untuk mulai mengisi.</p>
                    <div x-ref="actionList" class="mt-5 divide-y divide-border border-t border-border">
                        <template x-for="(row, index) in rows" :key="row.action_key">
                            <div class="py-5">
                                <input type="hidden" :name="'infra_actions['+index+'][action_key]'" :value="row.action_key">
                                <input type="hidden" :name="'infra_actions['+index+'][action_type]'" :value="row.action_type">
                                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wider text-text-muted" x-text="'Tindakan '+(index+1)"></p>
                                        <h3 class="mt-1 text-base font-semibold text-text-main" x-text="actionNames[row.action_type]"></h3>
                                        <p class="mt-0.5 text-xs text-text-muted" x-text="actionHints[row.action_type]"></p>
                                    </div>
                                    <button type="button" @click="removeAction(index)" class="min-h-10 text-xs font-semibold text-error hover:underline">Hapus tindakan</button>
                                </div>
                                @include('tasks.partials.infra-location-picker')
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <template x-if="row.action_type === 'install'">
                                        <label class="block text-sm font-medium text-text-secondary">Jenis barang <span class="text-error">*</span>
                                            <select x-model="row.material_kind" :disabled="isCard(row)" class="mt-1.5 w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-text-main">
                                                <option value="serial">Perangkat bernomor seri (SN)</option>
                                                <option value="quantity">Barang satuan (qty)</option>
                                                <option value="roll">Kabel dari roll (meter)</option>
                                            </select>
                                            <span x-show="isCard(row)" class="mt-1 block text-xs font-normal text-text-muted">Kartu OLT harus memakai SN.</span>
                                        </label>
                                    </template>
                                    <template x-if="(row.action_type === 'install' && row.material_kind === 'serial') || row.action_type === 'replace'">
                                        <label class="block text-sm font-medium text-text-secondary">SN yang dipasang <span class="text-error">*</span>
                                            <select x-model="row.serial_id" :name="'infra_actions['+index+'][serial_id]'"
                                                    class="mt-1.5 w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-text-main">
                                                <option value="">Pilih dari barang yang dibawa tim</option>
                                                <template x-for="serial in serialsFor(row)" :key="serial.id"><option :value="serial.id" x-text="serial.label"></option></template>
                                            </select>
                                        </label>
                                    </template>
                                    <template x-if="row.action_type === 'retrieve' || row.action_type === 'replace'">
                                        <label class="block text-sm font-medium text-text-secondary">SN lama yang dilepas <span class="text-error">*</span>
                                            <select x-model="row.old_serial_id" :name="'infra_actions['+index+'][old_serial_id]'"
                                                    class="mt-1.5 w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-text-main">
                                                <option value="">Pilih SN yang terpasang di lokasi ini</option>
                                                <template x-for="serial in serialsAt(row)" :key="serial.id"><option :value="serial.id" x-text="serial.label"></option></template>
                                            </select>
                                            <span x-show="row.location_mode === 'legacy' ? !row.network_node_id : !deepestLocation(row)" class="mt-1 block text-xs font-normal text-text-muted">Pilih lokasi dulu — hanya SN yang terpasang tepat di lokasi itu yang muncul.</span>
                                        </label>
                                    </template>
                                    <template x-if="row.action_type === 'install' && row.material_kind === 'quantity'">
                                        <label class="block text-sm font-medium text-text-secondary">Barang yang dipasang <span class="text-error">*</span>
                                            <select x-model="row.item_id" :name="'infra_actions['+index+'][item_id]'"
                                                    class="mt-1.5 w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-text-main">
                                                <option value="">Pilih barang satuan dari tim</option>
                                                <template x-for="item in items" :key="item.id"><option :value="item.id" x-text="item.label"></option></template>
                                            </select>
                                        </label>
                                    </template>
                                    <template x-if="row.action_type === 'install' && row.material_kind === 'quantity'">
                                        <label class="block text-sm font-medium text-text-secondary">Jumlah terpakai <span class="text-error">*</span>
                                            <input type="number" min="0.01" step="0.01" x-model="row.qty" :name="'infra_actions['+index+'][qty]'" placeholder="Contoh: 2"
                                                   class="mt-1.5 w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-text-main">
                                        </label>
                                    </template>
                                    <template x-if="row.action_type === 'install' && row.material_kind === 'roll'">
                                        <label class="block text-sm font-medium text-text-secondary">Roll kabel <span class="text-error">*</span>
                                            <select x-model="row.roll_id" :name="'infra_actions['+index+'][roll_id]'"
                                                    class="mt-1.5 w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-text-main">
                                                <option value="">Pilih roll dari tim</option>
                                                <template x-for="roll in rolls" :key="roll.id"><option :value="roll.id" x-text="roll.label"></option></template>
                                            </select>
                                        </label>
                                    </template>
                                    <template x-if="row.action_type === 'install' && row.material_kind === 'roll'">
                                        <label class="block text-sm font-medium text-text-secondary">Meter terpakai <span class="text-error">*</span>
                                            <input type="number" min="0.01" step="0.01" x-model="row.meters" :name="'infra_actions['+index+'][meters]'" placeholder="Contoh: 12.5"
                                                   class="mt-1.5 w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-text-main">
                                            <span class="mt-1 block text-xs font-normal text-text-muted">Hanya mengurangi sisa roll; kabel tidak menjadi saldo di lokasi.</span>
                                        </label>
                                    </template>
                                    <template x-if="row.action_type === 'install' && row.material_kind === 'roll' && row.location_mode === 'hierarchy'">
                                        <div class="grid gap-4 sm:col-span-2 sm:grid-cols-2">
                                            <label class="block text-sm font-medium text-text-secondary">Ujung sambungan dari <span class="text-xs font-normal text-text-muted">(opsional)</span>
                                                <select x-model="row.end_from" :name="'infra_actions['+index+'][end_from]'" class="mt-1.5 w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-text-main">
                                                    <option value="">Tidak dicatat</option>
                                                    <template x-for="end in endOptions(row)" :key="end.value"><option :value="end.value" x-text="end.label"></option></template>
                                                </select>
                                            </label>
                                            <label class="block text-sm font-medium text-text-secondary">Ujung sambungan ke <span class="text-xs font-normal text-text-muted">(opsional)</span>
                                                <select x-model="row.end_to" :name="'infra_actions['+index+'][end_to]'" class="mt-1.5 w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-text-main">
                                                    <option value="">Tidak dicatat</option>
                                                    <template x-for="end in endOptions(row)" :key="end.value"><option :value="end.value" x-text="end.label"></option></template>
                                                </select>
                                            </label>
                                            <label class="block text-sm font-medium text-text-secondary">Latitude titik kerja <span class="text-xs font-normal text-text-muted">(opsional)</span>
                                                <input type="number" step="0.0000001" min="-90" max="90" x-model="row.work_latitude" :name="'infra_actions['+index+'][work_latitude]'" class="mt-1.5 w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-text-main">
                                            </label>
                                            <label class="block text-sm font-medium text-text-secondary">Longitude titik kerja <span class="text-xs font-normal text-text-muted">(opsional)</span>
                                                <input type="number" step="0.0000001" min="-180" max="180" x-model="row.work_longitude" :name="'infra_actions['+index+'][work_longitude]'" class="mt-1.5 w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-text-main">
                                            </label>
                                        </div>
                                    </template>
                                    <template x-if="['inspect', 'port_connect', 'port_release'].includes(row.action_type)">
                                        <label class="block text-sm font-medium text-text-secondary">Port pada lokasi ini <span x-show="row.action_type !== 'inspect'" class="text-error">*</span>
                                            <select x-model="row.port_id" :name="'infra_actions['+index+'][port_id]'"
                                                    class="mt-1.5 w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-text-main">
                                                <option value="">Pilih port</option>
                                                <template x-for="port in portsAt(portNodeId(row), row.action_type)" :key="port.id"><option :value="port.id" x-text="'Port ' + port.label"></option></template>
                                            </select>
                                            <span x-show="!portNodeId(row)" class="mt-1 block text-xs font-normal text-text-muted">Pilih lokasi node (mis. ODP) dulu agar portnya terlihat.</span>
                                        </label>
                                    </template>
                                    <template x-if="row.action_type === 'port_connect'">
                                        <label class="block text-sm font-medium text-text-secondary">Node tujuan <span class="text-error">*</span>
                                            <select x-model="row.target_node_id" @change="row.target_port_id = ''" :name="'infra_actions['+index+'][target_node_id]'"
                                                    class="mt-1.5 w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-text-main">
                                                <option value="">Pilih node anak yang dihubungkan</option>
                                                <template x-for="node in targetNodes(row)" :key="node.id"><option :value="node.id" x-text="node.label"></option></template>
                                            </select>
                                        </label>
                                    </template>
                                    <template x-if="row.action_type === 'port_connect'">
                                        <label class="block text-sm font-medium text-text-secondary">Port pada node tujuan <span class="text-error">*</span>
                                            <select x-model="row.target_port_id" :name="'infra_actions['+index+'][target_port_id]'"
                                                    class="mt-1.5 w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-text-main">
                                                <option value="">Pilih port tujuan yang kosong</option>
                                                <template x-for="port in portsAt(row.target_node_id, 'port_connect')" :key="port.id"><option :value="port.id" x-text="'Port ' + port.label"></option></template>
                                            </select>
                                        </label>
                                    </template>
                                    <label class="block text-sm font-medium text-text-secondary sm:col-span-2">Hasil tindakan <span class="text-error">*</span>
                                        <textarea x-model="row.result_note" :name="'infra_actions['+index+'][result_note]'" rows="2" placeholder="Contoh: SN terpasang dan lampu indikator normal."
                                                  class="mt-1.5 w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-text-main"></textarea>
                                    </label>
                                </div>
                            </div>
                        </template>
                    </div>
                </fieldset>

                <fieldset x-show="mode === 'correction'" :disabled="mode !== 'correction'" x-cloak class="mt-5 min-w-0">
                    <p class="text-sm font-semibold text-text-main">Perbaiki pencatatan material</p>
                    <p class="mt-1 text-xs text-text-muted">Gunakan transaksi pemasangan sebelumnya sebagai acuan. Koreksi tidak mengurangi stok tim untuk kedua kalinya.</p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <label class="block text-sm font-medium text-text-secondary">Apa yang dilakukan? <span class="text-error">*</span>
                            <select name="infra_material_action" x-model="correction.action" class="mt-1.5 w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-text-main">
                                <option value="">Pilih tindakan</option>
                                <option value="correct">Koreksi jumlah / lokasi</option>
                                <option value="retrieve">Cabut material dari jaringan</option>
                            </select>
                        </label>
                        <label class="block text-sm font-medium text-text-secondary">Transaksi pemasangan asal <span class="text-error">*</span>
                            <select name="infra_material_source_transaction_id" x-model="correction.source" class="mt-1.5 w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-text-main">
                                <option value="">Pilih barang yang pernah dipasang</option>
                                <template x-for="source in sources" :key="source.id"><option :value="source.id" x-text="source.label"></option></template>
                            </select>
                        </label>
                        <label class="block text-sm font-medium text-text-secondary"><span x-text="correction.action === 'retrieve' ? 'Jumlah yang dicabut' : 'Jumlah yang benar'"></span> <span class="text-error">*</span>
                            <input type="number" min="0" step="0.01" name="infra_material_qty" x-model="correction.qty" placeholder="Contoh: 2" class="mt-1.5 w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-text-main">
                        </label>
                        <template x-if="correction.action === 'correct'">
                            <label class="block text-sm font-medium text-text-secondary">Lokasi pencatatan yang benar <span class="text-error">*</span>
                                <select name="infra_target_network_node_id" x-model="correction.node" class="mt-1.5 w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-text-main">
                                    <option value="">Pilih titik jaringan</option>
                                    <template x-for="node in nodes" :key="node.id"><option :value="node.id" x-text="node.label"></option></template>
                                </select>
                            </label>
                        </template>
                        <template x-if="correction.action === 'correct'">
                            <label class="block text-sm font-medium text-text-secondary">Roll pengganti <span class="text-xs font-normal text-text-muted">(jika berubah)</span>
                                <select name="infra_material_correct_roll_id" x-model="correction.roll" class="mt-1.5 w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-text-main">
                                    <option value="">Tetap memakai roll semula</option>
                                    <template x-for="roll in rolls" :key="roll.id"><option :value="roll.id" x-text="roll.label"></option></template>
                                </select>
                            </label>
                        </template>
                        <label class="block text-sm font-medium text-text-secondary sm:col-span-2">Alasan koreksi / pencabutan <span class="text-error">*</span>
                            <textarea name="infra_material_reason" x-model="correction.reason" rows="2" placeholder="Jelaskan keadaan fisik dan catatan yang perlu diperbaiki."
                                      class="mt-1.5 w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-text-main"></textarea>
                        </label>
                    </div>
                </fieldset>
            </section>

            @include('tasks.partials.customer-report-extra')

            <section class="border-b border-border px-4 py-5 sm:px-6">
                <p class="text-xs font-semibold uppercase tracking-wider text-text-muted">03 · Bukti & alat kerja</p>
                <p class="mt-1 text-sm text-text-secondary">Tambahkan foto dan alat yang digunakan bila ada. Data ini membantu FOP meninjau hasil kerja.</p>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm font-medium text-text-secondary">Foto lokasi / hasil <span class="text-xs font-normal text-text-muted">(opsional)</span>
                        <input type="file" name="opm_photo" accept="image/*" class="mt-1.5 block w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-text-main">
                    </label>
                    <label class="block text-sm font-medium text-text-secondary">Foto pengukuran <span class="text-xs font-normal text-text-muted">(opsional)</span>
                        <input type="file" name="speedtest_photo" accept="image/*" class="mt-1.5 block w-full rounded-lg border border-border bg-surface px-3 py-2.5 text-sm text-text-main">
                    </label>
                </div>
                @if($workTools->isNotEmpty())
                    <details class="mt-5 border-t border-border pt-4">
                        <summary class="cursor-pointer text-sm font-semibold text-primary">Catat alat kerja yang dipakai (opsional)</summary>
                        <div class="mt-4">
                            <x-work-tool-checklist name="work_tools" :tools="$workTools" :rows="$workToolRows" />
                        </div>
                    </details>
                @endif
            </section>
            <section x-show="reviewing" x-ref="review" x-cloak class="border-b border-border px-4 py-5 sm:px-6">
                <p class="text-xs font-semibold uppercase tracking-wider text-text-muted">04 · Tinjau laporan</p>
                <h2 class="mt-1 text-lg font-semibold text-text-main">Pastikan catatan sesuai pekerjaan fisik</h2>
                <p class="mt-3 text-sm text-text-main" x-text="summary"></p>
                <div x-show="mode === 'actions'" class="mt-4 divide-y divide-border border-t border-border">
                    <template x-for="(row, index) in rows" :key="row.action_key">
                        <div class="py-3 text-sm">
                            <p class="font-semibold text-text-main" x-text="(index + 1) + '. ' + actionNames[row.action_type]"></p>
                            <p class="mt-1 text-text-secondary"><span x-text="locationLabel(row)"></span> · <span x-text="assetSummary(row)"></span></p>
                            <p class="mt-1 text-text-muted" x-text="row.result_note"></p>
                            <p class="mt-1 text-xs font-medium text-primary" x-text="stockEffects[row.action_type]"></p>
                        </div>
                    </template>
                </div>
                <p x-show="mode === 'correction'" class="mt-3 text-sm text-text-secondary">
                    <span x-text="correction.action === 'retrieve' ? 'Pencabutan material' : 'Koreksi material'"></span>
                    · <span x-text="optionName(sources, correction.source)"></span>
                    · <span x-text="correction.qty"></span>
                    <span x-show="correction.action === 'correct'">· <span x-text="nodeName(correction.node)"></span></span>
                </p>
                <p x-show="mode === 'correction'" class="mt-1 text-xs text-text-muted" x-text="correction.reason"></p>
                <p x-show="mode === 'none'" class="mt-3 text-sm text-text-secondary">Tanpa perubahan aset jaringan, node, slot, atau port. Material pelanggan tetap dicatat bila diisi.</p>
                <p class="mt-4 text-xs text-text-muted">Setelah dikirim, laporan ini menyelesaikan task dan menjadi catatan Inventaris serta Gudang sesuai tindakan di atas.</p>
            </section>

            <div role="alert" x-show="formError" x-ref="formError" x-cloak class="border-b border-error px-4 py-3 text-sm font-medium text-error" x-text="formError"></div>
            <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6">
                <a href="{{ route('tasks.show', $task) }}" class="inline-flex min-h-11 items-center text-sm font-medium text-text-secondary hover:text-primary">Batal, kembali ke task</a>
                <div class="flex flex-wrap gap-2">
                    <button type="button" x-show="reviewing" @click="reviewing = false" x-cloak class="inline-flex min-h-11 items-center rounded-lg border border-border px-4 py-2 text-sm font-semibold text-text-main hover:bg-surface-alt">Ubah laporan</button>
                    <button type="button" x-show="!reviewing" @click="review()" class="inline-flex min-h-11 items-center rounded-lg bg-primary px-5 py-2 text-sm font-semibold text-white hover:bg-primary-hover">Tinjau laporan</button>
                    <button type="submit" x-show="reviewing" :disabled="submitting" x-cloak class="inline-flex min-h-11 items-center rounded-lg bg-primary px-5 py-2 text-sm font-semibold text-white hover:bg-primary-hover disabled:opacity-50">
                        <span x-text="submitting ? 'Mengirim laporan…' : 'Kirim laporan & selesaikan task'"></span>
                    </button>
                </div>
            </div>
        </div>
        <input type="hidden" name="infra_material_action_key" value="{{ old('infra_material_action_key', $infraMaterialActionKey) }}">
    </form>
</div>
@endsection
