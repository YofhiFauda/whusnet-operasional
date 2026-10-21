{{--
    Pemilih "Lokasi yang dikerjakan" per baris (rancangan hierarki §10.1–10.4).
    Opsi tiap tingkat diambil dari server (`locationOptionsUrl`) setelah induk
    dipilih; tingkat akar scope terkunci. «Dikosongkan» = null eksplisit dan
    seluruh anak null/disabled. Mode "Titik jaringan lama" menampung node
    legacy yang belum dipetakan ke hierarki baru — tetap dicek server dengan
    guard scope yang sama.
--}}
<div class="mb-4 border border-border px-3 py-3 sm:px-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm font-medium text-text-secondary">Lokasi yang dikerjakan <span x-show="taskType === 'infr' || row.action_type !== 'inspect'" class="text-error">*</span></p>
        <div class="flex gap-3 text-xs" x-show="scope">
            <button type="button" @click="setLocationMode(row, 'hierarchy')" :class="row.location_mode === 'hierarchy' ? 'font-semibold text-primary' : 'text-text-muted hover:text-primary'">Hierarki</button>
            <button type="button" @click="setLocationMode(row, 'legacy')" :class="row.location_mode === 'legacy' ? 'font-semibold text-primary' : 'text-text-muted hover:text-primary'">Titik jaringan lama</button>
        </div>
    </div>
    <p x-show="scope" class="mt-1 text-xs text-text-muted">Scope task: <span x-text="scope?.breadcrumb"></span></p>

    <template x-if="row.location_mode === 'hierarchy'">
        <div class="mt-3 grid gap-3 sm:grid-cols-3">
            <template x-for="level in locationLevels" :key="level">
                <label class="block text-xs font-medium text-text-secondary">
                    <span x-text="levelNames[level]"></span>
                    <select x-model="row.loc[level]" @change="levelChanged(row, level)"
                            :disabled="!levelEnabled(row, level)"
                            class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-main disabled:cursor-not-allowed disabled:bg-surface-alt disabled:text-text-muted">
                        <option value="" x-text="row.levels[level].loading ? 'Memuat…' : (row.levels[level].disabled ? 'Tidak tersedia' : 'Belum dipilih')"></option>
                        <option value="none" x-show="row.levels[level].allow_none && !levelLocked(row, level)">Dikosongkan</option>
                        <template x-for="option in row.levels[level].options" :key="option.id">
                            <option :value="String(option.id)" x-text="option.label" :selected="String(option.id) === row.loc[level]"></option>
                        </template>
                    </select>
                </label>
            </template>
        </div>
    </template>
    <template x-for="(value, name) in locationInputs(row)" :key="name">
        <input type="hidden" :name="'infra_actions['+index+']['+name+']'" :value="value">
    </template>

    <template x-if="row.location_mode === 'legacy'">
        <label class="mt-3 block text-xs font-medium text-text-secondary">Titik jaringan lama
            <select x-model="row.network_node_id" @change="nodeChanged(row)" :name="'infra_actions['+index+'][network_node_id]'"
                    class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-main">
                <option value="">Pilih titik jaringan</option>
                <template x-for="node in nodes" :key="node.id"><option :value="node.id" x-text="node.label"></option></template>
            </select>
        </label>
    </template>
    <span x-show="taskType === 'oreq' && row.action_type === 'inspect'" class="mt-2 block text-xs text-text-muted">Pemeriksaan kantor boleh tanpa lokasi jaringan.</span>

    <div x-show="row.location_mode === 'hierarchy' && deepestLocation(row)" class="mt-3 grid gap-2 border-t border-border pt-3 text-xs sm:grid-cols-2">
        <div>
            <p class="font-semibold text-text-main">Aset terpasang langsung di sini</p>
            <p x-show="(row.assets?.direct || []).length === 0" class="text-text-muted">Tidak ada.</p>
            <template x-for="asset in (row.assets?.direct || [])" :key="asset.id">
                <p class="font-mono text-text-secondary" x-text="asset.serial_number + ' · ' + (asset.item || '')"></p>
            </template>
        </div>
        <div>
            <p class="font-semibold text-text-main">Aset pada turunan lokasi ini</p>
            <p class="text-text-secondary" x-text="(row.assets?.descendant_count || 0) + ' SN (tidak bisa dicabut dari tingkat ini)'"></p>
        </div>
    </div>
</div>
