@if(auth()->user()->hasPermission('network_nodes.create') || auth()->user()->hasPermission('network_nodes.update'))
<div x-show="showCreateModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs" @keydown.escape.window="showCreateModal = false">
    <div @click.outside="showCreateModal = false" class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-lg bg-surface border border-border shadow-2xl animate-in fade-in zoom-in duration-150">
        {{-- Modal Header --}}
        <header class="flex items-center justify-between border-b border-border px-6 py-4 bg-surface-muted/30 shrink-0">
            <div class="flex items-center gap-2.5">
                <div class="p-2 rounded-lg bg-sky-600 text-white shadow-xs">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-text-main">Tambah Titik Node Jaringan</h3>
                    <p class="text-[11px] text-text-muted">Pilih tipe, tentukan induk/penempatan, lalu lengkapi rincian teknis.</p>
                </div>
            </div>
            <button type="button" @click="showCreateModal = false" aria-label="Tutup" class="text-text-muted hover:text-text-main p-1.5 rounded-lg hover:bg-surface-muted transition-colors cursor-pointer">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </header>

        {{-- Modal Body --}}
        <div class="space-y-4 overflow-y-auto p-6 text-xs text-text-main">
            @if($errors->any())
                <div class="rounded-lg bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 p-3 text-xs text-rose-700 dark:text-rose-300 space-y-1">
                    @foreach($errors->all() as $error)
                        <p class="flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                            <span>{{ $error }}</span>
                        </p>
                    @endforeach
                </div>
            @endif

            {{-- 1. Device Type Selector — baca dari $nodeTypes (tipe aktif network_node_types,
                 bukan array hardcode) supaya tipe baru yang dibuat admin lewat Master Tipe
                 Node otomatis muncul di sini tanpa sentuh Blade/JS lagi. `createType` diisi
                 `code` ASLI dari DB (bukan alias singkat lama) biar lurus dengan `nodeTypeIds`
                 yang sudah dikirim per-code dari controller. Slot & PON tetap 2 tombol statis
                 — keduanya bukan baris `network_node_types`, melainkan konsep struktural
                 `InfrastructureSlot`/`InfrastructurePort` yang beda model sepenuhnya. --}}
            <div>
                <label class="mb-2 block text-[10px] font-bold uppercase tracking-wider text-text-muted">1. Tipe Perangkat / Titik</label>
                <div class="grid grid-cols-3 gap-2 sm:grid-cols-6">
                    @foreach($nodeTypes->reject(fn ($type) => $type->code === 'odp') as $nodeType)
                        <button type="button" data-device-type="{{ $nodeType->code }}" @click="chooseCreateType('{{ $nodeType->code }}')" @disabled(! auth()->user()->hasPermission('network_nodes.create'))
                                :class="createType === '{{ $nodeType->code }}' ? 'border-sky-600 bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 font-bold shadow-2xs' : 'border-border bg-surface text-text-secondary hover:bg-surface-muted font-semibold'"
                                class="rounded-lg border p-2.5 text-xs text-center transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed">
                            {{ $nodeType->name }}
                        </button>
                    @endforeach

                    @foreach(['slot' => 'SLOT', 'pon' => 'PON'] as $code => $label)
                        <button type="button" data-device-type="{{ $code }}" @click="chooseCreateType('{{ $code }}')" @disabled(! auth()->user()->hasPermission('network_nodes.update'))
                                :class="createType === '{{ $code }}' ? 'border-sky-600 bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 font-bold shadow-2xs' : 'border-border bg-surface text-text-secondary hover:bg-surface-muted font-semibold'"
                                class="rounded-lg border p-2.5 text-xs text-center transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed">
                            {{ $label }}
                        </button>
                    @endforeach

                    @if($odpType = $nodeTypes->firstWhere('code', 'odp'))
                        <button type="button" data-device-type="odp" @click="chooseCreateType('odp')" @disabled(! auth()->user()->hasPermission('network_nodes.create'))
                                :class="createType === 'odp' ? 'border-sky-600 bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 font-bold shadow-2xs' : 'border-border bg-surface text-text-secondary hover:bg-surface-muted font-semibold'"
                                class="rounded-lg border p-2.5 text-xs text-center transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed">
                            {{ $odpType->name }}
                        </button>
                    @endif
                </div>
            </div>

            {{-- Context Guidance --}}
            <div x-show="createType" class="p-3 rounded-lg bg-surface-muted/50 border border-border text-[11px] text-text-secondary leading-relaxed">
                <p x-show="createType === 'server'"><strong>Server</strong> ditempatkan di <strong>POP Pusat</strong> sebagai akar topologi pusat jaringan.</p>
                <p x-show="createType === 'switch_mini_pop'"><strong>Switch</strong> ditempatkan di <strong>Mini POP</strong> lalu memiliki <strong>SLOT</strong> dan <strong>Port Switch</strong>.</p>
                <p x-show="createType === 'olt_chassis'"><strong>OLT</strong> ditempatkan di <strong>Cabang/Mini POP</strong> lalu memiliki <strong>SLOT</strong> dan <strong>PON</strong>.</p>
                <p x-show="createType === 'slot'"><strong>SLOT</strong> ditambahkan pada perangkat induk <strong>OLT</strong> atau <strong>Switch</strong> yang sudah ada.</p>
                <p x-show="createType === 'pon'"><strong>PON</strong> ditambahkan pada <strong>SLOT OLT</strong> induk; beban port mengikuti pelanggan aktif.</p>
                <p x-show="createType === 'odp'"><strong>ODP</strong> wajib dibuat sebagai anak langsung <strong>PON</strong> (Task 12) — pilih PON induknya di bawah.</p>
            </div>

            {{-- Form A: Nodes (Server, Switch, OLT, ODP, dan tipe baru apapun yang punya
                 anchor_pop_id/parent_id langsung — bukan Slot/PON) --}}
            @can('network_nodes.create')
            <form x-show="!['slot','pon'].includes(createType) && createType" method="POST" action="{{ route('network.nodes.store') }}" class="space-y-4 pt-1">
                @csrf
                <input type="hidden" name="device_type" :value="createType">
                <input type="hidden" name="node_type_id" :value="nodeTypeIds[createType] || ''">

                <div x-show="createType !== 'odp'">
                    <label class="block text-xs font-semibold text-text-secondary mb-1">2. POP penempatan fisik <span class="text-rose-500">*</span></label>
                    <select name="anchor_pop_id" x-model="createPopId" @change="createPonId = ''" :required="createType !== 'odp'"
                            class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-xs text-text-main focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none cursor-pointer">
                        <option value="">Pilih POP Wilayah...</option>
                        <template x-for="pop in allowedCreationPops" :key="pop.id">
                            <option :value="pop.id" x-text="pop.name"></option>
                        </template>
                    </select>
                </div>
                {{-- SATU field PON bercari (umpan balik user 2026-10-26), bukan OLT →
                     PON dua langkah. Pencarian di server (`network.nodes.pon-search`:
                     ≤20 hasil, ter-scope POP) karena PON bisa ribuan — memuat semua ke
                     halaman mengulang masalah O(N²). URL dari server (data-pon-search-url);
                     yang dikirim hanya `parent_id` tersembunyi = PON terpilih. --}}
                <div x-show="createType === 'odp'" data-odp-pon-search
                     data-pon-search-url="{{ route('network.nodes.pon-search') }}"
                     x-data="ponSearchField(@js(old('device_type') === 'odp' ? old('parent_id') : null))"
                     @click.outside="open = false" class="relative">
                    <label for="create-odp-pon-search" class="block text-xs font-semibold text-text-secondary mb-1">2. PON induk <span class="text-rose-500">*</span></label>
                    <input type="hidden" name="parent_id" :value="selectedId" :disabled="createType !== 'odp'">
                    <input id="create-odp-pon-search" type="text" autocomplete="off" x-model="query"
                           @input="onInput()" @focus="focus()"
                           @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
                           @keydown.enter.prevent="chooseActive()" @keydown.escape.stop="open = false"
                           role="combobox" aria-autocomplete="list" aria-controls="create-odp-pon-results" :aria-expanded="open"
                           placeholder="Ketik kode/nama OLT atau kode PON, mis. OLT-SIMAN-S1-P2"
                           class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-xs font-mono text-text-main focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                    <ul id="create-odp-pon-results" x-show="open" x-cloak role="listbox"
                        class="absolute z-10 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-border bg-surface shadow-lg">
                        <li x-show="loading" class="px-3 py-2 text-[11px] text-text-muted">Mencari PON…</li>
                        <template x-for="(option, index) in options" :key="option.id">
                            <li role="option" :aria-selected="index === active"
                                @mousedown.prevent="choose(option)" @mouseenter="active = index"
                                :class="index === active ? 'bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300' : 'text-text-main'"
                                class="cursor-pointer px-3 py-2 font-mono text-xs" x-text="option.label"></li>
                        </template>
                        <li x-show="!loading && options.length === 0" class="px-3 py-2 text-[11px] text-text-muted">Tidak ada PON yang cocok dalam scope Anda.</li>
                    </ul>
                    <p class="mt-1 text-[11px]" :class="selectedId ? 'text-emerald-600 dark:text-emerald-400' : 'text-text-muted'"
                       x-text="selectedId ? 'PON terpilih: ' + (selectedLabel || ('#' + selectedId)) : 'Pilih satu PON dari hasil pencarian (maks. 20 ditampilkan — persempit dengan mengetik).'"></p>
                    <p class="mt-1 text-[11px] text-text-muted">ODP wajib anak langsung PON — tidak lagi ditambatkan ke POP. PON juga bisa ditambah ODP langsung dari halaman Detail PON.</p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-semibold text-text-secondary mb-1">Kode Titik <span class="text-rose-500">*</span></label>
                        <input name="code" value="{{ old('code') }}" required maxlength="50" placeholder="Contoh: ODP-SIMAN-01"
                               class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-xs font-mono uppercase text-text-main focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-text-secondary mb-1">Nama Titik <span class="text-rose-500">*</span></label>
                        <input name="name" value="{{ old('name') }}" required maxlength="150" placeholder="Nama Titik atau Lokasi"
                                class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-xs text-text-main focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-semibold text-text-secondary mb-1">
                            <span x-text="nodeTypeHasPorts[createType] ? 'Jumlah Port *' : 'Kapasitas (opsional, tidak dipakai tipe ini)'"></span>
                        </label>
                        <input id="create-modal-capacity-input" name="capacity" type="number" value="{{ old('capacity') }}" min="1" max="1000" :required="!!nodeTypeHasPorts[createType]" :disabled="!nodeTypeHasPorts[createType]" placeholder="Contoh: 8"
                               class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-xs font-mono text-text-main focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-text-secondary mb-1">Alamat / Patokan Tiang</label>
                        <input name="address" value="{{ old('address') }}" placeholder="Jl. Raya Siman No. 12 / Tiang PLN #4"
                               class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-xs text-text-main focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-semibold text-text-secondary mb-1">Latitude</label>
                        <input name="latitude" type="number" step="any" value="{{ old('latitude') }}" placeholder="-7.865120"
                               class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-xs font-mono text-text-main focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-text-secondary mb-1">Longitude</label>
                        <input name="longitude" type="number" step="any" value="{{ old('longitude') }}" placeholder="111.469800"
                               class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-xs font-mono text-text-main focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                    </div>
                </div>

                <div class="pt-3 border-t border-border flex items-center justify-end gap-2">
                    <button type="button" @click="showCreateModal = false" class="px-4 py-2 rounded-lg border border-border bg-surface hover:bg-surface-muted text-text-secondary text-xs font-semibold cursor-pointer transition-colors">Batal</button>
                    <button type="submit" class="rounded-lg bg-sky-600 hover:bg-sky-700 px-5 py-2 text-xs font-bold text-white shadow-xs cursor-pointer transition-colors">Simpan Titik</button>
                </div>
            </form>
            @endcan

            {{-- Form B: SLOT --}}
            @can('network_nodes.update')
            <form x-show="createType === 'slot'" method="POST" :action="createSlotAction" class="space-y-4 pt-1">
                @csrf
                <input type="hidden" name="device_type" value="slot">
                <div>
                    <label class="block text-xs font-semibold text-text-secondary mb-1">2. Perangkat induk (OLT / Switch) <span class="text-rose-500">*</span></label>
                    <select name="device_id" x-model="createDeviceId" required
                            class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-xs text-text-main focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none cursor-pointer">
                        <option value="">Pilih OLT / Switch...</option>
                        <template x-for="device in creationDevices" :key="device.id">
                            <option :value="device.id" x-text="device.breadcrumb"></option>
                        </template>
                    </select>
                </div>
                {{-- Kode & nama Slot diturunkan otomatis (`{kode perangkat}-S{n}`);
                     tidak ada isian kode/label bebas. --}}
                <div>
                    <label class="block text-xs font-semibold text-text-secondary mb-1">Nomor SLOT <span class="text-rose-500">*</span></label>
                    <input name="number" type="number" min="1" max="999" value="{{ old('number') }}" required placeholder="Contoh: 1"
                           class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-xs font-mono text-text-main focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-text-secondary mb-1">Kapasitas Port SLOT</label>
                    <input name="capacity" type="number" min="1" max="9999" value="{{ old('capacity') }}" placeholder="Contoh: 16"
                           class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-xs font-mono text-text-main focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                </div>
                <div class="pt-3 border-t border-border flex items-center justify-end gap-2">
                    <button type="button" @click="showCreateModal = false" class="px-4 py-2 rounded-lg border border-border bg-surface hover:bg-surface-muted text-text-secondary text-xs font-semibold cursor-pointer transition-colors">Batal</button>
                    <button type="submit" :disabled="!createSlotAction" class="rounded-lg bg-sky-600 hover:bg-sky-700 px-5 py-2 text-xs font-bold text-white shadow-xs cursor-pointer transition-colors disabled:opacity-40">Simpan SLOT</button>
                </div>
            </form>

            {{-- Form C: PON --}}
            <form x-show="createType === 'pon'" method="POST" :action="createPonAction" class="space-y-4 pt-1">
                @csrf
                <input type="hidden" name="device_type" value="pon">
                {{-- Slot dimuat per OLT lewat `children_url` item perangkat; target
                     POST = `store_port_url` Slot terpilih (keduanya dari server). --}}
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-semibold text-text-secondary mb-1">2. OLT <span class="text-rose-500">*</span></label>
                        <select x-model="createDeviceId" @change="loadCreateOptions()" required
                                class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-xs text-text-main focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none cursor-pointer">
                            <option value="">Pilih OLT...</option>
                            <template x-for="device in creationOlts" :key="device.id">
                                <option :value="device.id" x-text="device.breadcrumb"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-text-secondary mb-1">3. Slot OLT induk <span class="text-rose-500">*</span></label>
                        <select x-model="createSlotId" required
                                class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-xs text-text-main focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none cursor-pointer">
                            <option value="" x-text="creationLoading ? 'Memuat Slot...' : (createDeviceId && creationSlots.length === 0 ? 'OLT ini belum punya Slot' : 'Pilih SLOT OLT...')"></option>
                            <template x-for="slot in creationSlots" :key="slot.id">
                                <option :value="slot.id" x-text="slot.label"></option>
                            </template>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-text-secondary mb-1">Nomor PON <span class="text-rose-500">*</span></label>
                    <input name="number" type="number" min="1" max="999" value="{{ old('number') }}" required placeholder="Contoh: 1"
                           class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-xs font-mono text-text-main focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-text-secondary mb-1">Kapasitas Pelanggan PON</label>
                    <input name="capacity" type="number" min="1" max="100000" value="{{ old('capacity') }}" placeholder="Contoh: 128"
                           class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-xs font-mono text-text-main focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                </div>
                <div class="pt-3 border-t border-border flex items-center justify-end gap-2">
                    <button type="button" @click="showCreateModal = false" class="px-4 py-2 rounded-lg border border-border bg-surface hover:bg-surface-muted text-text-secondary text-xs font-semibold cursor-pointer transition-colors">Batal</button>
                    <button type="submit" :disabled="!createPonAction" class="rounded-lg bg-sky-600 hover:bg-sky-700 px-5 py-2 text-xs font-bold text-white shadow-xs cursor-pointer transition-colors disabled:opacity-40">Simpan PON</button>
                </div>
            </form>
            @endcan
        </div>
    </div>
</div>
@endif
