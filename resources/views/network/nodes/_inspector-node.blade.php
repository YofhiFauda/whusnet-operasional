            @forelse($inspectorNodes ?? $nodes as $node)
            @php
                $usagePercent = $node->ports_count > 0 ? round($node->used_ports_count / $node->ports_count * 100) : 0;
                $isRedZone = $node->isInRedZone();
                $location = $node->effectiveLocation();
                $childDownstreams = $treeChildren->get($node->id, collect());
                $assignedTargetNodeIds = $node->ports->flatMap(fn ($p) => $p->activeAssignment->pluck('target_node_id'))->filter()->all();
                $availableChildren = $childDownstreams->map(fn ($c) => [
                    'id' => $c->id,
                    'code' => $c->code,
                    'name' => $c->name,
                    'type' => $c->nodeType?->name ?? 'Node',
                    'is_connected' => in_array($c->id, $assignedTargetNodeIds, true),
                ])->values()->all();
                $breadcrumbParts = array_filter(explode(' / ', $breadcrumbLabels[$node->id] ?? $node->name));
                $typeCode = $node->nodeType?->code ?? 'other';
                // Sekali per node, bukan per kartu port (dulu dihitung ulang di tiap port).
                $nodeCabangId = $cabangByNodeId[$node->id] ?? null;
            @endphp

            <div x-show="selectedNodeId === {{ $node->id }}"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 id="inspector-node-{{ $node->id }}">

                <div class="flex flex-col" id="node-{{ $node->id }}">

                    {{-- Sticky Top Bar: Breadcrumb Path & Quick Actions --}}
                    <div class="px-4 sm:px-6 py-3.5 border-b border-border flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-surface-muted/40">
                        {{-- Hierarchy Path Breadcrumb & Mobile Back Button --}}
                        <div id="nodeBreadcrumb" class="flex items-center flex-wrap gap-1 text-xs text-text-muted min-w-0">
                            <button type="button" @click="activeMobileTab = 'tree'"
                                    class="lg:hidden inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-surface border border-border text-xs font-semibold text-text-main hover:bg-surface-muted shadow-2xs mr-1 cursor-pointer shrink-0">
                                <svg class="w-3.5 h-3.5 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                                <span>Pohon</span>
                            </button>
                            @foreach($breadcrumbParts as $index => $part)
                                @if(! $loop->last)
                                    <span class="hover:text-primary transition font-mono truncate max-w-[110px] sm:max-w-none">{{ $part }}</span>
                                    <svg class="w-3 h-3 text-text-muted/60 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                    </svg>
                                @else
                                    <span class="font-bold text-text-main bg-surface px-2 py-0.5 rounded-md border border-border shadow-2xs font-mono truncate max-w-[160px] sm:max-w-none">
                                        {{ $part }}
                                    </span>
                                @endif
                            @endforeach
                        </div>

                        {{-- Action Buttons --}}
                        <div class="flex items-center gap-1.5 sm:gap-2 flex-wrap w-full sm:w-auto">
                            @if(in_array($typeCode, ['olt_chassis', 'switch_mini_pop'], true))
                            <a href="#device-panel-{{ $node->id }}" class="flex-1 sm:flex-initial inline-flex items-center justify-center rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-semibold text-primary hover:bg-surface-muted transition shadow-2xs">
                                Daftar Slot
                            </a>
                            @else
                            @can('network_nodes.create')
                            <button type="button" @click="openAddChildModal({{ $node->id }}, @js($node->code), @js($node->name))"
                                    class="flex-1 sm:flex-initial inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-surface border border-border text-text-secondary hover:bg-surface-muted transition shadow-2xs cursor-pointer">
                                <svg class="w-3.5 h-3.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                </svg>
                                <span>+ Sub-Node</span>
                            </button>
                            @endcan
                            @endif

                            @can('network_nodes.update')
                            <button type="button" @click="openEditModal({{ $node->id }}, @js($node->code), @js($node->name), {{ $node->node_type_id }}, @js($node->status), @js($location['address'] ?? ''), @js($location['village'] ?? ''), @js($location['district'] ?? ''), @js($location['city'] ?? ''), @js($location['latitude'] ?? ''), @js($location['longitude'] ?? ''))"
                                    class="flex-1 sm:flex-initial inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-sky-600 text-white hover:bg-sky-700 transition shadow-xs cursor-pointer">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                <span>Edit Detail</span>
                            </button>
                            @endcan

                            @can('warehouse_assets.create')
                            <a href="{{ route('network.nodes.register-asset.create', $node) }}"
                               class="flex-1 sm:flex-initial inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-surface border border-border text-text-secondary hover:bg-surface-muted transition shadow-2xs"
                               title="Daftarkan Aset / Barcode">
                                <svg class="w-3.5 h-3.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                </svg>
                                <span>Registrasi Aset</span>
                            </a>
                            @endcan

                            @can('network_nodes.delete')
                            <form method="POST" action="{{ route('network.nodes.destroy', $node) }}"
                                  onsubmit="return confirm('Hapus node kosong? Node yang punya relasi akan dinonaktifkan.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-red-50 text-red-700 border border-red-200 hover:bg-red-100 dark:bg-red-950/40 dark:text-red-300 dark:border-red-800 dark:hover:bg-red-950/70 transition shadow-2xs cursor-pointer">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16" />
                                    </svg>
                                    <span>Hapus Node</span>
                                </button>
                            </form>
                            @endcan

                            <a href="{{ route('network.nodes.show', $node) }}"
                               class="inline-flex items-center justify-center p-1.5 rounded-lg border border-border bg-surface hover:bg-surface-muted text-text-secondary transition shadow-2xs"
                               title="Buka Halaman Detail Penuh">
                                <svg class="w-4 h-4 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                </svg>
                            </a>
                        </div>
                    </div>

                    {{-- Primary Identity Row & Capacity Metric --}}
                    <div class="p-4 sm:p-6 border-b border-border bg-surface">
                        <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-4">
                            <div class="space-y-2 min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="px-2.5 py-0.5 rounded-md text-[10px] font-mono font-bold uppercase tracking-wider border
                                        @if(str_contains($typeCode, 'odp')) bg-emerald-50 text-emerald-700 dark:bg-emerald-950/70 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800
                                        @elseif(str_contains($typeCode, 'feeder')) bg-sky-50 text-sky-700 dark:bg-sky-950/70 dark:text-sky-300 border-sky-200 dark:border-sky-800
                                        @elseif(str_contains($typeCode, 'olt') || str_contains($typeCode, 'pon')) bg-amber-50 text-amber-800 dark:bg-amber-950/70 dark:text-amber-300 border-amber-200 dark:border-amber-800
                                        @elseif(str_contains($typeCode, 'aggregation')) bg-purple-50 text-purple-700 dark:bg-purple-950/70 dark:text-purple-300 border-purple-200 dark:border-purple-800
                                        @elseif(str_contains($typeCode, 'backbone')) bg-indigo-50 text-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800
                                        @else bg-surface-muted text-text-secondary border-border @endif">
                                        {{ $node->nodeType?->name }}
                                    </span>

                                    <h1 class="text-xl sm:text-2xl font-bold font-mono text-text-main tracking-tight">
                                        {{ $node->code }}
                                    </h1>
                                    <span class="text-text-disabled">&bull;</span>
                                    <span class="text-base sm:text-lg font-medium text-text-secondary truncate">
                                        {{ $node->name }}
                                    </span>

                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-semibold {{ $node->status === 'active' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' : 'bg-surface-muted text-text-muted border border-border' }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $node->status === 'active' ? 'bg-emerald-500 animate-pulse' : 'bg-text-disabled' }}"></span>
                                        {{ ucfirst($node->status) }}
                                    </span>
                                </div>

                                <div class="text-xs text-text-muted flex items-center gap-3 flex-wrap">
                                    <span class="flex items-center gap-1.5">
                                        <span class="text-text-disabled">POP:</span>
                                        <strong class="text-text-main font-semibold">{{ $node->anchorPop?->name ?? ($inspectorPopLabels[$node->id] ?? '—') }}</strong>
                                    </span>
                                    @if($location['address'])
                                    <span class="text-text-disabled">&bull;</span>
                                    <span class="flex items-center gap-1.5 truncate max-w-sm" title="{{ $location['address'] }}">
                                        <svg class="w-3.5 h-3.5 text-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        </svg>
                                        <span class="truncate">{{ $location['address'] }}</span>
                                    </span>
                                    @endif
                                </div>
                            </div>

                            {{-- Visual Capacity Meter (Goal-Gradient Effect) --}}
                            @if($node->nodeType?->has_ports && ! in_array($typeCode, ['olt_chassis', 'switch_mini_pop'], true))
                            <div class="bg-surface-muted/60 rounded-lg p-3.5 border border-border w-full xl:w-auto xl:min-w-[290px]">
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="text-xs font-semibold text-text-secondary">Okupansi Port</span>
                                    <span class="text-xs font-mono font-bold px-2 py-0.5 rounded tabular-nums {{ $isRedZone ? 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300 border border-rose-300 animate-pulse' : 'bg-surface text-text-main border border-border' }}"
                                          data-red-zone="{{ $isRedZone ? '1' : '0' }}">
                                        {{ $isRedZone ? 'Red Zone · ' : '' }}{{ $usagePercent }}%
                                    </span>
                                </div>
                                <div class="w-full h-2 bg-surface rounded-full overflow-hidden mb-2 border border-border">
                                    <div class="h-full transition-all duration-300 {{ $isRedZone ? 'bg-rose-500' : 'bg-sky-600' }}" style="width: {{ $usagePercent }}%;"></div>
                                </div>
                                <div class="flex items-center justify-between text-[11px] text-text-muted">
                                    <span><strong class="text-text-main font-mono tabular-nums">{{ $node->used_ports_count }}</strong> / <strong class="text-text-main font-mono tabular-nums">{{ $node->ports_count }}</strong> port</span>
                                    <span class="{{ $isRedZone ? 'text-rose-600 dark:text-rose-400 font-bold' : 'text-emerald-600 dark:text-emerald-400' }} font-medium font-mono tabular-nums">
                                        {{ max(0, $node->ports_count - $node->used_ports_count) }} port tersedia
                                    </span>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>

                    {{-- Ergonomic Operator Tab Navigation Bar --}}
                    <div class="flex items-center border-b border-border px-4 sm:px-6 bg-surface overflow-x-auto scrollbar-none gap-2">
                        @if(! in_array($typeCode, ['olt_chassis', 'switch_mini_pop'], true) && ($node->nodeType?->has_ports || $node->ports->isNotEmpty()))
                        <button type="button" @click="inspectorTab = 'ports'"
                                :class="inspectorTab === 'ports' ? 'border-sky-600 text-sky-600 dark:text-sky-400 font-bold' : 'border-transparent text-text-muted hover:text-text-main font-medium'"
                                class="py-3 px-2 text-xs border-b-2 transition-colors cursor-pointer whitespace-nowrap flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                            </svg>
                            <span>Manajemen Port &amp; Sambungan</span>
                            <span class="ml-1 px-1.5 py-0.2 rounded-full font-mono text-[10px] bg-surface-muted text-text-secondary">{{ $node->ports->count() }}</span>
                        </button>
                        @endif

                        <button type="button" @click="inspectorTab = 'location'"
                                :class="inspectorTab === 'location' ? 'border-sky-600 text-sky-600 dark:text-sky-400 font-bold' : 'border-transparent text-text-muted hover:text-text-main font-medium'"
                                class="py-3 px-2 text-xs border-b-2 transition-colors cursor-pointer whitespace-nowrap flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <span>Lokasi &amp; Koordinat</span>
                        </button>

                        @if($node->assetSummary()->isNotEmpty())
                        <button type="button" @click="inspectorTab = 'assets'"
                                :class="inspectorTab === 'assets' ? 'border-sky-600 text-sky-600 dark:text-sky-400 font-bold' : 'border-transparent text-text-muted hover:text-text-main font-medium'"
                                class="py-3 px-2 text-xs border-b-2 transition-colors cursor-pointer whitespace-nowrap flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                            <span>Aset Terpasang</span>
                            <span class="ml-1 px-1.5 py-0.2 rounded-full font-mono text-[10px] bg-surface-muted text-text-secondary">{{ $node->assetSummary()->count() }}</span>
                        </button>
                        @endif

                        @if($childDownstreams->isNotEmpty())
                        <button type="button" @click="inspectorTab = 'children'"
                                :class="inspectorTab === 'children' ? 'border-sky-600 text-sky-600 dark:text-sky-400 font-bold' : 'border-transparent text-text-muted hover:text-text-main font-medium'"
                                class="py-3 px-2 text-xs border-b-2 transition-colors cursor-pointer whitespace-nowrap flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                            </svg>
                            <span>Sub-Node ({{ $childDownstreams->count() }})</span>
                        </button>
                        @endif

                        @can('network_nodes.update')
                        <button type="button" @click="inspectorTab = 'relocate'"
                                :class="inspectorTab === 'relocate' ? 'border-sky-600 text-sky-600 dark:text-sky-400 font-bold' : 'border-transparent text-text-muted hover:text-text-main font-medium'"
                                class="py-3 px-2 text-xs border-b-2 transition-colors cursor-pointer whitespace-nowrap flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                            </svg>
                            <span>Relokasi Uplink</span>
                        </button>
                        @endcan
                    </div>

                    {{-- Main Inspector Content Area --}}
                    <div class="p-4 sm:p-6 space-y-6">

                        {{-- Chassis / Switch Special Component Slots --}}
                        @if(in_array($typeCode, ['olt_chassis', 'switch_mini_pop'], true))
                            {{-- Panel Manajemen Node — OLT/Switch (HANYA daftar Slot + kapasitas
                                 tiap Slot, form kapasitas & Tambah Slot) dimuat LAZY untuk
                                 perangkat yang DIPILIH saja, dari `network.nodes.panel` (URL
                                 dirender server di data-panel-url, tidak dirakit klien).
                                 Merender panel di sini untuk setiap perangkat = index O(N²)
                                 (final review C2). Isi sisipan adalah HTML server biasa (form
                                 ber-@csrf, <details>) tanpa atribut Alpine, jadi tidak perlu
                                 Alpine.initTree; tombol "Kelola" di dalamnya ditangani
                                 listener delegasi `@click` di kontainer ini. --}}
                            <section id="device-panel-{{ $node->id }}" data-node-panel-host
                                     data-panel-url="{{ route('network.nodes.panel', $node) }}"
                                     x-data="nodePanel()"
                                     x-effect="if (selectedNodeId === {{ $node->id }}) load()"
                                     class="pb-2 space-y-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <h3 class="text-xs font-bold uppercase tracking-wider text-text-main flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-sky-600"></span>
                                        <span>Manajemen Node — {{ $typeCode === 'olt_chassis' ? 'OLT' : 'Switch' }}</span>
                                    </h3>
                                    <div class="flex flex-wrap gap-2 text-xs">
                                        @can('network_nodes.update')
                                        <a href="{{ route('network.nodes.generate-structure.create', $node) }}" class="inline-flex items-center rounded-lg border border-border bg-surface px-3 py-1.5 font-semibold text-text-secondary hover:bg-surface-muted shadow-2xs">Generate struktur</a>
                                        @endcan
                                        <a href="{{ route('network.nodes.show', $node) }}#device-components-{{ $node->id }}" class="inline-flex items-center rounded-lg border border-border bg-surface px-3 py-1.5 font-semibold text-sky-600 dark:text-sky-400 hover:bg-surface-muted shadow-2xs">Buka Detail &rarr;</a>
                                    </div>
                                </div>
                                <p x-show="loading" class="rounded-lg border border-dashed border-border bg-surface-muted/40 p-4 text-xs text-text-muted">Memuat daftar Slot…</p>
                                <div x-show="failed" x-cloak class="rounded-lg border border-rose-200 dark:border-rose-900 bg-rose-50 dark:bg-rose-950/40 p-4 text-xs text-rose-700 dark:text-rose-300 flex flex-wrap items-center justify-between gap-2">
                                    <span>Gagal memuat panel perangkat.</span>
                                    <button type="button" @click="retry()" class="rounded-lg border border-rose-300 dark:border-rose-800 bg-surface px-3 py-1 font-semibold cursor-pointer">Coba lagi</button>
                                </div>
                                <div x-ref="body" x-show="loadedUrl" @click="handlePanelClick($event)"></div>
                            </section>
                        @endif

                        {{-- TAB 1: Manajemen Port & Sambungan --}}
                        @if(! in_array($typeCode, ['olt_chassis', 'switch_mini_pop'], true) && ($node->nodeType?->has_ports || $node->ports->isNotEmpty()))
                        <div x-show="inspectorTab === 'ports'" class="space-y-4">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-xs font-bold uppercase tracking-wider text-text-main flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-sky-600"></span>
                                        <span>Terminal Distribusi Port &bull; {{ $node->nodeType?->name }}</span>
                                    </h3>
                                    <p class="text-xs text-text-muted mt-0.5">
                                        Klik pada port untuk menghubungkan pelanggan, anak node, atau mengelola sambungan.
                                    </p>
                                </div>

                                {{-- Port Filter Controls & Add Port Inline Form --}}
                                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                                    <div class="grid grid-cols-3 sm:flex items-center bg-surface-muted rounded-lg p-0.5 border border-border">
                                        <button type="button" @click="portFilter = 'ALL'"
                                                :class="{ 'bg-surface text-text-main shadow-xs font-semibold': portFilter === 'ALL', 'text-text-muted hover:text-text-main': portFilter !== 'ALL' }"
                                                class="px-3 py-1.5 text-center rounded-md text-xs transition cursor-pointer">
                                            Semua
                                        </button>
                                        <button type="button" @click="portFilter = 'AVAILABLE'"
                                                :class="{ 'bg-surface text-text-main shadow-xs font-semibold': portFilter === 'AVAILABLE', 'text-text-muted hover:text-text-main': portFilter !== 'AVAILABLE' }"
                                                class="px-3 py-1.5 text-center rounded-md text-xs transition cursor-pointer">
                                            Tersedia
                                        </button>
                                        <button type="button" @click="portFilter = 'USED'"
                                                :class="{ 'bg-surface text-text-main shadow-xs font-semibold': portFilter === 'USED', 'text-text-muted hover:text-text-main': portFilter !== 'USED' }"
                                                class="px-3 py-1.5 text-center rounded-md text-xs transition cursor-pointer">
                                            Terpakai
                                        </button>
                                    </div>

                                    @can('network_nodes.update')
                                    <div class="h-6 w-px bg-border mx-1 hidden sm:block"></div>

                                    <form method="POST" action="{{ route('network.nodes.ports.add', $node) }}" class="flex items-center w-full sm:w-auto" data-add-ports-form>
                                        @csrf
                                        {{-- PRG kembali ke node ini terpilih (ODP anak PON tidak ada di daftar default). --}}
                                        <input type="hidden" name="master_node" value="{{ $node->id }}">
                                        <input type="number" name="additional_capacity" min="1" max="1000" value="4" required
                                               class="w-14 sm:w-16 px-2.5 py-1.5 text-xs font-mono tabular-nums font-semibold text-center bg-surface border border-r-0 border-border rounded-l-lg text-text-main focus:border-primary focus:ring-1 focus:ring-primary/20 outline-none" title="Jumlah Port Tambahan">
                                        <button type="submit" class="flex-1 sm:flex-initial justify-center px-3.5 py-1.5 bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold rounded-r-lg transition flex items-center gap-1.5 shadow-xs cursor-pointer">
                                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                            </svg>
                                            <span>Tambah Port</span>
                                        </button>
                                    </form>
                                    @endcan
                                </div>
                            </div>

                            {{-- Hardware Patch Panel Container --}}
                            <div class="bg-surface-muted/40 p-3.5 sm:p-5 rounded-lg border border-border">
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                                    @forelse($node->ports->sortBy('number') as $port)
                                        @php($assignment = $port->activeAssignment->first())
                                        {{-- Target form modal port (Lepas, Sambung Pelanggan, Hubungkan
                                             Node, Tandai Manual) dirender server di data-* kartu ini lalu
                                             diteruskan ke modal lewat `$el.dataset` — dulu modal merakit
                                             `'/network/node-ports/' + portId` di klien (konvensi CLAUDE.md). --}}
                                        @can('network_nodes.update')
                                            @if($port->is_used)
                                                {{-- Port Terpakai (High Contrast, Clean Badges) --}}
                                                <div x-show="portFilter === 'ALL' || portFilter === 'USED'"
                                                     data-odp-port="{{ $port->id }}" data-port-update-url="{{ route('network.node-ports.update', $port) }}"
                                                     data-port-assign-customer-url="{{ route('network.node-ports.assign-customer', $port) }}" data-port-assign-node-url="{{ route('network.node-ports.assign-node', $port) }}"
                                                     @click="openAssignModal({
                                                         portId: {{ $port->id }},
                                                         urls: $el.dataset,
                                                         portNumber: {{ $port->number }},
                                                         nodeId: {{ $node->id }},
                                                         nodeCode: @js($node->code),
                                                         nodeName: @js($node->name),
                                                         nodeType: @js($node->nodeType?->code ?? ''),
                                                         cabangId: @js($nodeCabangId),
                                                         children: @js($availableChildren),
                                                         isUsed: true,
                                                         tenantType: @js($assignment?->customer ? 'customer' : ($assignment?->targetNode ? 'node' : ($assignment?->manual_label ? 'manual' : 'unknown'))),
                                                         tenantName: @js($assignment?->customer ? $assignment->customer->full_name : ($assignment?->targetNode ? $assignment->targetNode->code . ' · ' . $assignment->targetNode->name : ($assignment?->manual_label ?? 'Belum diverifikasi'))),
                                                         tenantSubtitle: @js($assignment?->customer ? ($assignment->customer->customer_code ?: ($assignment->customer->cid ?: '#'.$assignment->customer->id)) : ($assignment?->targetNode && $assignment->target_port_id && $assignment->relationLoaded('targetPort') ? 'Port ' . $assignment->targetPort?->number : '')),
                                                         customerUrl: @js($assignment?->customer ? route('customers.show', $assignment->customer) : '')
                                                     })"
                                                     class="group bg-surface hover:bg-sky-50/40 dark:hover:bg-sky-950/20 border border-border hover:border-sky-400 dark:hover:border-sky-600 rounded-lg p-3.5 cursor-pointer transition shadow-2xs flex flex-col justify-between min-h-[96px]">
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-xs font-bold font-mono tabular-nums text-sky-600 dark:text-sky-400">Port {{ $port->number }}</span>
                                                        <span class="inline-flex items-center gap-1 text-[10px] font-mono font-semibold text-rose-700 dark:text-rose-300 bg-rose-50 dark:bg-rose-950/60 px-2 py-0.5 rounded-full border border-rose-200 dark:border-rose-900">
                                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                                            Terpakai
                                                        </span>
                                                    </div>
                                                    <div class="mt-2.5 min-w-0">
                                                        <div class="text-xs font-semibold text-text-main truncate" title="{{ $assignment?->customer ? $assignment->customer->full_name : ($assignment?->targetNode ? $assignment->targetNode->code . ' · ' . $assignment->targetNode->name : 'Terpakai') }}">
                                                            @if($assignment?->customer)
                                                                {{ $assignment->customer->full_name }}
                                                            @elseif($assignment?->targetNode)
                                                                &rarr; {{ $assignment->targetNode->code }}
                                                            @elseif($assignment?->manual_label)
                                                                {{ $assignment->manual_label }}
                                                            @else
                                                                Terpakai
                                                            @endif
                                                        </div>
                                                        <div class="text-[11px] font-mono tabular-nums text-text-muted flex items-center justify-between mt-1">
                                                            <span class="truncate">
                                                                @if($assignment?->customer)
                                                                    {{ $assignment->customer->customer_code ?: ($assignment->customer->cid ?: '#'.$assignment->customer->id) }}
                                                                @elseif($assignment?->targetNode)
                                                                    Sub-Node
                                                                @else
                                                                    Manual
                                                                @endif
                                                            </span>
                                                            <span class="text-sky-600 opacity-0 group-hover:opacity-100 font-sans font-medium text-[10px] transition shrink-0">Kelola &rarr;</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            @else
                                                {{-- Port Tersedia (Emerald Dashed, Actionable) --}}
                                                <div x-show="portFilter === 'ALL' || portFilter === 'AVAILABLE'"
                                                     data-odp-port="{{ $port->id }}" data-port-update-url="{{ route('network.node-ports.update', $port) }}"
                                                     data-port-assign-customer-url="{{ route('network.node-ports.assign-customer', $port) }}" data-port-assign-node-url="{{ route('network.node-ports.assign-node', $port) }}"
                                                     @click="openAssignModal({
                                                         portId: {{ $port->id }},
                                                         urls: $el.dataset,
                                                         portNumber: {{ $port->number }},
                                                         nodeId: {{ $node->id }},
                                                         nodeCode: @js($node->code),
                                                         nodeName: @js($node->name),
                                                         nodeType: @js($node->nodeType?->code ?? ''),
                                                         cabangId: @js($nodeCabangId),
                                                         children: @js($availableChildren),
                                                         isUsed: false,
                                                         tenantType: '',
                                                         tenantName: '',
                                                         tenantSubtitle: '',
                                                         customerUrl: ''
                                                     })"
                                                     class="group bg-surface hover:bg-emerald-50/40 dark:hover:bg-emerald-950/20 border-2 border-dashed border-emerald-300/80 dark:border-emerald-700/70 hover:border-emerald-500 rounded-lg p-3.5 cursor-pointer transition flex flex-col justify-between min-h-[96px]">
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-xs font-bold font-mono tabular-nums text-text-main group-hover:text-emerald-800 dark:group-hover:text-emerald-200">Port {{ $port->number }}</span>
                                                        <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/60 px-2 py-0.5 rounded-full border border-emerald-200 dark:border-emerald-800">
                                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                            Tersedia
                                                        </span>
                                                    </div>
                                                    <div class="mt-2.5 flex items-center justify-between text-xs text-emerald-700 dark:text-emerald-300 font-medium">
                                                        <span class="flex items-center gap-1">
                                                            <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                                            </svg>
                                                            <span>Hubungkan</span>
                                                        </span>
                                                        <span class="opacity-0 group-hover:opacity-100 transition text-[10px] font-semibold">&rarr;</span>
                                                    </div>
                                                </div>
                                            @endif
                                        @else
                                            <div class="p-3.5 rounded-lg border border-border bg-surface text-xs space-y-1">
                                                <div class="flex items-center justify-between gap-1">
                                                    <span class="font-mono tabular-nums font-bold text-text-main">Port {{ $port->number }}</span>
                                                    <span class="w-2 h-2 rounded-full {{ $port->is_used ? 'bg-rose-500' : 'bg-emerald-500' }}"></span>
                                                </div>
                                                <div class="text-[11px] text-text-muted truncate">
                                                    @if($assignment?->customer) Pelanggan: {{ $assignment->customer->full_name }}
                                                    @elseif($assignment?->targetNode) &rarr; {{ $assignment->targetNode->code }}
                                                    @elseif($assignment?->manual_label) {{ $assignment->manual_label }}
                                                    @else {{ $port->is_used ? 'Terpakai' : 'Kosong' }} @endif
                                                </div>
                                            </div>
                                        @endcan
                                    @empty
                                        <div class="col-span-full p-8 rounded-lg border border-dashed border-border text-center text-xs text-text-muted space-y-2">
                                            <p class="font-medium text-text-secondary">Belum ada port yang dibuat untuk node ini.</p>
                                            <p>Gunakan formulir "+ Tambah Port" di atas untuk menambahkan port distribusi.</p>
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                        @endif

                        {{-- TAB 2: Lokasi & Koordinat --}}
                        <div x-show="inspectorTab === 'location'" class="space-y-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <h3 class="text-xs font-bold uppercase tracking-wider text-text-main flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-sky-600"></span>
                                        <span>Lokasi Fisik &amp; Koordinat Geospasial</span>
                                    </h3>
                                    <p class="text-xs text-text-muted mt-0.5">Rincian alamat tiang, cakupan wilayah, dan titik peta GPS.</p>
                                </div>
                                <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold {{ $location['source_node_id'] === $node->id ? 'text-emerald-700 bg-emerald-50 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' : 'text-sky-700 bg-sky-50 dark:bg-sky-950/60 dark:text-sky-300 border border-sky-200 dark:border-sky-800' }} px-3 py-1 rounded-full">
                                    {{ $location['source_node_id'] === $node->id ? 'Titik Koordinat Mandiri' : 'Mewarisi lokasi Induk / POP' }}
                                </span>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3.5 pt-1">
                                <div class="bg-surface p-4 rounded-lg border border-border space-y-1">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted">Alamat / Patokan Tiang</span>
                                    <p class="text-xs font-semibold text-text-main leading-relaxed">{{ $location['address'] ?: '—' }}</p>
                                </div>
                                <div class="bg-surface p-4 rounded-lg border border-border space-y-1">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted">Wilayah / Coverage Area</span>
                                    <p class="text-xs font-semibold text-text-main leading-relaxed">{{ collect([$location['village'], $location['district'], $location['city']])->filter()->implode(', ') ?: '—' }}</p>
                                </div>
                                <div class="bg-surface p-4 rounded-lg border border-border flex items-center justify-between gap-2">
                                    <div class="min-w-0 space-y-1">
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted">Koordinat Maps GPS</span>
                                        <p class="text-xs font-mono tabular-nums font-semibold text-text-main truncate">
                                            {{ $location['latitude'] !== null && $location['longitude'] !== null ? "{$location['latitude']}, {$location['longitude']}" : '—' }}
                                        </p>
                                    </div>
                                    @if($location['latitude'] !== null && $location['longitude'] !== null)
                                    <a href="https://maps.google.com/?q={{ $location['latitude'] }},{{ $location['longitude'] }}" target="_blank" rel="noopener"
                                       class="px-3 py-1.5 rounded-lg border border-border bg-surface hover:bg-surface-muted text-xs font-semibold text-sky-600 dark:text-sky-400 transition shadow-2xs shrink-0 flex items-center gap-1.5" title="Buka di Google Maps">
                                        <span>Buka Maps</span>
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                    </a>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- TAB 3: Aset Gudang & Material Terpasang --}}
                        @if($node->assetSummary()->isNotEmpty())
                        <div x-show="inspectorTab === 'assets'" class="space-y-4">
                            <div class="flex items-center justify-between gap-2">
                                <div>
                                    <h3 class="text-xs font-bold uppercase tracking-wider text-text-main flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-sky-600"></span>
                                        <span>Aset &amp; Material Terpasang</span>
                                    </h3>
                                    <p class="text-xs text-text-muted mt-0.5">Ringkasan perangkat, splitter, dan kabel dari stok logistik gudang.</p>
                                </div>
                                <a href="{{ route('network.map.index', ['node_id' => $node->id]) }}"
                                   class="text-xs font-semibold text-sky-600 dark:text-sky-400 hover:underline">
                                    Lihat di Peta Infrastruktur &rarr;
                                </a>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 pt-1">
                                @foreach($node->assetSummary() as $row)
                                <div class="p-3.5 rounded-lg border border-border bg-surface flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 border border-amber-200/60 dark:border-amber-800 flex items-center justify-center shrink-0">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-xs font-semibold text-text-main truncate">{{ $row['name'] }}</div>
                                        <div class="text-[11px] font-mono text-text-muted mt-0.5">
                                            {{ rtrim(rtrim(number_format((float) $row['qty'], 2, ',', '.'), '0'), ',') }} {{ $row['unit'] }}
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        {{-- TAB 4: Sub-Node Downstream --}}
                        @if($childDownstreams->isNotEmpty())
                        <div x-show="inspectorTab === 'children'" class="space-y-4">
                            <div class="flex items-center justify-between gap-2">
                                <div>
                                    <h3 class="text-xs font-bold uppercase tracking-wider text-text-main flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-sky-600"></span>
                                        <span>Sub-Node Turunan Langsung ({{ $childDownstreams->count() }} Downstream)</span>
                                    </h3>
                                    <p class="text-xs text-text-muted mt-0.5">Klik node di bawah untuk langsung beralih ke inspeksi detailnya.</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 pt-1">
                                @foreach($childDownstreams as $child)
                                <button type="button" @click="selectNode({{ $child->id }})"
                                        class="text-left p-3.5 rounded-lg border border-border bg-surface hover:border-sky-500 hover:bg-sky-50/30 dark:hover:bg-sky-950/20 transition shadow-2xs group cursor-pointer">
                                    <div class="flex items-center justify-between gap-1">
                                        <span class="font-mono tabular-nums font-bold text-sky-600 dark:text-sky-400 text-xs">{{ $child->code }}</span>
                                        <span class="text-[10px] font-bold text-text-muted">({{ $child->nodeType?->name }})</span>
                                    </div>
                                    <div class="text-xs font-semibold text-text-main truncate mt-1.5">{{ $child->name }}</div>
                                    <div class="text-[11px] text-text-muted mt-1 font-mono tabular-nums">
                                        {{ $child->used_ports_count ?? 0 }} / {{ $child->ports_count ?? 0 }} port terpakai
                                    </div>
                                </button>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        {{-- TAB 5: Relokasi Posisi Induk Node --}}
                        @can('network_nodes.update')
                        <div x-show="inspectorTab === 'relocate'" class="space-y-4">
                            <div>
                                <h3 class="text-xs font-bold uppercase tracking-wider text-text-main flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                    <span>Relokasi Posisi Induk Node (Uplink)</span>
                                </h3>
                                <p class="text-xs text-text-muted mt-0.5">
                                    Pindahkan node ini beserta seluruh jalur anak di bawahnya ke POP, OLT, atau Feeder lain.
                                </p>
                            </div>

                            <div class="p-4 rounded-lg border border-border bg-surface-muted/30">
                                @if($typeCode === 'odp')
                                {{-- ODP wajib berinduk PON (server menolak selain PON —
                                     NetworkNodeService::move). PON tujuan dipilih lewat SATU field
                                     bercari yang sama dengan Tambah ODP (`network.nodes.pon-search`,
                                     ≤20 hasil, ter-scope POP, URL dari server) — dulu select OLT dari
                                     semua OLT scope di tiap blok ODP lalu PON per OLT. Yang dikirim
                                     hanya `parent_id` tersembunyi = PON terpilih. --}}
                                <form method="POST" action="{{ route('network.nodes.move', $node) }}"
                                      data-odp-relocate data-pon-search-url="{{ route('network.nodes.pon-search') }}"
                                      x-data="ponSearchField(null)" @click.outside="open = false" class="space-y-4">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="master_node" value="{{ $node->id }}">
                                    <input type="hidden" name="parent_id" :value="selectedId">
                                    <p class="text-[11px] text-text-muted">Induk sekarang: <span class="font-mono text-text-secondary" data-current-uplink>{{ $node->parent_id ? (implode(' / ', array_slice(array_values($breadcrumbParts), 0, -1)) ?: '—') : '—' }}</span></p>
                                    <div class="relative">
                                        <label for="relocate-pon-search-{{ $node->id }}" class="block text-xs font-bold text-text-secondary mb-1">PON Tujuan <span class="text-rose-500">*</span></label>
                                        <input id="relocate-pon-search-{{ $node->id }}" type="text" autocomplete="off" x-model="query"
                                               @input="onInput()" @focus="focus()"
                                               @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
                                               @keydown.enter.prevent="chooseActive()" @keydown.escape.stop="open = false"
                                               role="combobox" aria-autocomplete="list" aria-controls="relocate-pon-results-{{ $node->id }}" :aria-expanded="open"
                                               placeholder="Ketik kode/nama OLT atau kode PON, mis. OLT-SIMAN-S1-P2"
                                               class="block w-full rounded-lg border border-border bg-surface px-3 py-2 text-text-main text-xs font-mono focus:border-primary focus:ring-1 focus:ring-primary/20 outline-none">
                                        <ul id="relocate-pon-results-{{ $node->id }}" x-show="open" x-cloak role="listbox"
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
                                    </div>
                                    <div class="pt-2 flex justify-end">
                                        <button type="submit" :disabled="!selectedId" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold rounded-lg transition shadow-xs cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                                            Simpan Relokasi Uplink
                                        </button>
                                    </div>
                                </form>
                                @else
                                <form method="POST" action="{{ route('network.nodes.move', $node) }}"
                                      x-data="{ placement: @js($node->parent_id ? 'node' : 'pop') }"
                                      class="space-y-4">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="master_node" value="{{ $node->id }}">
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <label class="block text-xs font-bold text-text-secondary mb-1">Pindah Penempatan Ke</label>
                                            <select x-model="placement" name="placement" class="block w-full rounded-lg border border-border bg-surface px-3 py-2 text-text-main text-xs focus:border-primary focus:ring-1 focus:ring-primary/20 outline-none cursor-pointer">
                                                <option value="pop">Cabang / POP</option>
                                                <option value="node">Node Induk Lain</option>
                                            </select>
                                        </div>

                                        <div x-show="placement === 'pop'">
                                            <label class="block text-xs font-bold text-text-secondary mb-1">POP Tujuan</label>
                                            <select name="anchor_pop_id" x-bind:disabled="placement !== 'pop'" x-bind:required="placement === 'pop'"
                                                    class="block w-full rounded-lg border border-border bg-surface px-3 py-2 text-text-main text-xs focus:border-primary focus:ring-1 focus:ring-primary/20 outline-none cursor-pointer">
                                                <option value="">Pilih POP</option>
                                                @foreach($pops as $pop)
                                                <option value="{{ $pop->id }}" @selected($node->anchor_pop_id == $pop->id)>{{ $pop->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div x-show="placement === 'node'">
                                            <label class="block text-xs font-bold text-text-secondary mb-1">Node Induk Tujuan</label>
                                            <select name="parent_id" x-bind:disabled="placement !== 'node'" x-bind:required="placement === 'node'"
                                                    class="block w-full rounded-lg border border-border bg-surface px-3 py-2 text-text-main text-xs focus:border-primary focus:ring-1 focus:ring-primary/20 outline-none cursor-pointer">
                                                <option value="">Pilih Node Induk</option>
                                                @foreach($relocateTargets as $parentOption)
                                                @if($parentOption->id !== $node->id)
                                                <option value="{{ $parentOption->id }}" @selected($node->parent_id == $parentOption->id)>{{ $breadcrumbLabels[$parentOption->id] ?? $parentOption->name }}</option>
                                                @endif
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="pt-2 flex justify-end">
                                        <button type="submit" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold rounded-lg transition shadow-xs cursor-pointer">
                                            Simpan Relokasi Uplink
                                        </button>
                                    </div>
                                </form>
                                @endif
                            </div>
                        </div>
                        @endcan

                    </div>
                </div>
            </div>
            @empty
            <div class="rounded-lg border border-border bg-surface p-12 text-center text-xs text-text-muted space-y-2">
                <p class="font-bold text-text-main text-sm">Belum ada node jaringan pada POP yang dapat Anda akses.</p>
                <p>Klik tombol "+ Tambah Titik Node Baru" di atas untuk menambahkan titik jaringan pertama Anda.</p>
            </div>
            @endforelse

