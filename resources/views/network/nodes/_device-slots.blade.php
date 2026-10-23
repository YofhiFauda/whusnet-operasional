@php
    $deviceItem = $hierarchy['items']['node:'.$device->id] ?? null;
    $deviceCapacity = $deviceItem['capacity'] ?? null;
    $isOlt = $device->nodeType?->code === 'olt_chassis';
@endphp
<section id="device-components-{{ $device->id }}" class="space-y-4 rounded-lg border border-border bg-surface p-4 sm:p-5 shadow-2xs">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-xs font-bold uppercase tracking-wider text-text-main">Slot perangkat · <span class="font-mono text-sky-600 dark:text-sky-400">{{ $device->code }}</span></h2>
            <p class="mt-1 text-xs text-text-muted">Slot merupakan komponen {{ $isOlt ? 'OLT' : 'Switch' }} ini. {{ $isOlt ? 'Setiap Slot memuat PON; PON menghubungkan ODP.' : 'Setiap Slot memuat Port Switch.' }}</p>
        </div>
        <p class="text-xs text-text-secondary font-mono tabular-nums bg-surface-muted px-2.5 py-1 rounded-md border border-border">
            {{ $device->infrastructureSlots->count() }} slot didata /
            {{ $device->capacity ?? '?' }} slot kapasitas
            @if($deviceCapacity) · {{ $deviceCapacity['used'] }} slot terisi kartu @endif
        </p>
    </div>

    @can('network_nodes.update')
    <form method="POST" action="{{ route('network.nodes.capacity', $device) }}" class="flex flex-wrap items-end gap-2 bg-surface-muted/40 p-3 rounded-lg border border-border">
        @csrf @method('PATCH')
        <label class="text-xs font-medium text-text-secondary">Kapasitas perangkat (slot)
            <input name="capacity" type="number" min="1" max="1000" required value="{{ $device->capacity }}" placeholder="Belum didata" class="mt-1 block w-36 rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-mono text-text-main focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
        </label>
        <button type="submit" class="rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-semibold text-text-main hover:bg-surface-muted transition-colors cursor-pointer shadow-2xs">Simpan kapasitas perangkat</button>
    </form>
    @endcan

    <div class="space-y-3 border-l-2 border-sky-300 dark:border-sky-800 pl-3">
        @forelse($device->infrastructureSlots->sortBy('number') as $componentSlot)
        @php
            $slotItem = $hierarchy['items']['slot:'.$componentSlot->id] ?? null;
            $portLabel = $componentSlot->kind->portKind()->label();
        @endphp
        <details @if($componentSlot->ports->isNotEmpty()) open @endif data-component-slot="{{ $componentSlot->id }}" class="rounded-lg border border-border bg-surface overflow-hidden">
            <summary class="flex cursor-pointer flex-wrap items-center justify-between gap-2 bg-surface-muted px-3.5 py-2 text-xs text-text-main font-semibold hover:bg-surface-muted/80 transition-colors">
                <span>{{ $componentSlot->displayName() }} @if($componentSlot->label) · <span class="font-normal text-text-muted">{{ $componentSlot->label }}</span> @endif</span>
                <span class="font-mono text-text-muted">{{ $componentSlot->ports->count() }} / {{ $componentSlot->capacity ?? '?' }} {{ $componentSlot->capacity_unit?->label() }}</span>
            </summary>
            <div class="space-y-3 p-3.5 bg-surface">
                <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                    <a href="{{ route('network.infrastructure-slots.show', $componentSlot) }}" class="font-semibold text-sky-600 dark:text-sky-400 hover:underline">Detail {{ $componentSlot->displayName() }} &rarr;</a>
                    <span class="text-text-muted text-[11px]">{{ $componentSlot->status->label() }}</span>
                </div>
                @can('network_nodes.update')
                <form method="POST" action="{{ route('network.infrastructure-slots.capacity', $componentSlot) }}" class="flex flex-wrap items-end gap-2 bg-surface-muted/40 p-2.5 rounded-lg border border-border">
                    @csrf @method('PATCH')
                    <label class="text-xs font-medium text-text-secondary">Kapasitas ({{ $componentSlot->capacity_unit?->label() }})
                        <input type="number" name="capacity" min="1" max="9999" required value="{{ $componentSlot->capacity }}" placeholder="Belum didata" class="mt-1 block w-36 rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-mono text-text-main focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                    </label>
                    <button type="submit" class="rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-semibold text-text-main hover:bg-surface-muted transition-colors cursor-pointer shadow-2xs">Simpan kapasitas slot</button>
                </form>
                @endcan
                {{-- Patch panel port/PON --}}
                <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
                    @forelse($componentSlot->ports->sortBy('number') as $componentPort)
                    @php
                        $portItem = $hierarchy['items']['port:'.$componentPort->id] ?? null;
                        $portCapacity = $portItem['capacity'] ?? null;
                        $statusLabel = $portItem['status_label'] ?? $componentPort->status->label();
                        $isTerpakai = str_starts_with($statusLabel, 'Terpakai');
                        $isTerverifikasi = str_contains($statusLabel, 'terverifikasi') && ! str_contains($statusLabel, 'belum');
                        $isRusak = str_starts_with($statusLabel, 'Rusak');
                        $cardClass = match (true) {
                            $isRusak => 'bg-surface-muted border-border opacity-70',
                            $isTerpakai && $isTerverifikasi => 'bg-surface border-border hover:border-sky-400',
                            $isTerpakai => 'bg-amber-50/60 dark:bg-amber-950/30 border-amber-300/80 dark:border-amber-800',
                            default => 'bg-surface border-2 border-dashed border-emerald-300/80 dark:border-emerald-700/80 hover:border-emerald-500',
                        };
                        $badgeClass = match (true) {
                            $isRusak => 'text-text-muted bg-surface-muted border-border',
                            $isTerpakai && $isTerverifikasi => 'text-rose-700 bg-rose-50 border-rose-200 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-900',
                            $isTerpakai => 'text-amber-700 bg-amber-50 border-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-900',
                            default => 'text-emerald-700 bg-emerald-50 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800',
                        };
                    @endphp
                    <div data-component-port="{{ $componentPort->id }}" class="rounded-lg border p-3 {{ $cardClass }} flex flex-col justify-between min-h-[88px] transition-colors shadow-2xs">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs font-bold font-mono text-text-main">{{ $componentPort->displayName() }}</span>
                            <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full border {{ $badgeClass }}">{{ $statusLabel }}</span>
                        </div>
                        <div class="mt-2 min-w-0 flex items-end justify-between gap-2">
                            <div class="min-w-0">
                                @if($portCapacity)
                                    <div class="text-xs font-semibold font-mono text-text-main tabular-nums">{{ $portCapacity['used'] }} / {{ $portCapacity['capacity'] ?? '?' }} {{ $portCapacity['unit_label'] }}</div>
                                @endif
                                @if($portItem['connection_label'] ?? null)
                                    <div class="text-[11px] text-text-muted truncate font-mono" title="{{ $portItem['connection_label'] }}">&rarr; {{ $portItem['connection_label'] }}</div>
                                @endif
                            </div>
                            <a href="{{ route('network.infrastructure-ports.show', $componentPort) }}" class="shrink-0 rounded-lg border border-border bg-surface px-2.5 py-1 text-[11px] font-semibold text-sky-600 dark:text-sky-400 hover:bg-surface-muted transition-colors shadow-2xs">Detail &rarr;</a>
                        </div>
                    </div>
                    @empty
                    <p class="col-span-full text-xs text-text-muted">Belum ada {{ $portLabel }} pada Slot ini.</p>
                    @endforelse
                </div>
                @can('network_nodes.update')
                <details class="border-t border-border pt-2.5">
                    <summary class="cursor-pointer text-xs font-semibold text-sky-600 dark:text-sky-400 hover:underline">Tambah {{ $portLabel }} pada {{ $componentSlot->displayName() }}</summary>
                    <form method="POST" action="{{ route('network.infrastructure-slots.ports.store', $componentSlot) }}" class="mt-3 grid gap-3 sm:grid-cols-2 bg-surface-muted/30 p-3 rounded-lg border border-border">
                        @csrf
                        <label class="text-xs font-medium text-text-secondary">Nomor {{ $portLabel }} <span class="text-rose-500">*</span>
                            <input type="number" name="number" min="1" required class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-mono text-text-main focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                        </label>
                        <label class="text-xs font-medium text-text-secondary">Kode (opsional)
                            <input type="text" name="code" maxlength="50" class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-mono text-text-main focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                        </label>
                        @if($isOlt)
                        <label class="text-xs font-medium text-text-secondary">Kapasitas PON (pelanggan aktif)
                            <input type="number" name="capacity" min="1" max="100000" class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-mono text-text-main focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                        </label>
                        <label class="text-xs font-medium text-text-secondary">Ambang Red Zone (%)
                            <input type="number" name="red_zone_threshold" min="1" max="100" class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-mono text-text-main focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                        </label>
                        @endif
                        <button type="submit" class="rounded-lg bg-sky-600 hover:bg-sky-700 px-3.5 py-1.5 text-xs font-semibold text-white sm:justify-self-start cursor-pointer shadow-xs transition-colors">Tambah {{ $portLabel }}</button>
                    </form>
                </details>
                @endcan
            </div>
        </details>
        @empty
        <p class="text-xs text-text-muted">Belum ada Slot yang didata pada perangkat ini.</p>
        @endforelse
    </div>

    @can('network_nodes.update')
    <details class="border-t border-border pt-3">
        <summary class="cursor-pointer text-xs font-semibold text-sky-600 dark:text-sky-400 hover:underline">Tambah Slot pada {{ $device->code }}</summary>
        <form method="POST" action="{{ route('network.nodes.infrastructure-slots.store', $device) }}" class="mt-3 grid gap-3 sm:grid-cols-3 bg-surface-muted/30 p-3 rounded-lg border border-border">
            @csrf
            <label class="text-xs font-medium text-text-secondary">Nomor Slot <span class="text-rose-500">*</span>
                <input type="number" name="number" min="1" required class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-mono text-text-main focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
            </label>
            <label class="text-xs font-medium text-text-secondary">Kode (opsional)
                <input type="text" name="code" maxlength="50" class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-mono text-text-main focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
            </label>
            <label class="text-xs font-medium text-text-secondary">Kapasitas ({{ $isOlt ? 'PON per slot' : 'port per slot' }})
                <input type="number" name="capacity" min="1" max="9999" class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-mono text-text-main focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
            </label>
            @unless($isOlt)<label class="flex items-center gap-2 text-xs text-text-secondary sm:col-span-3"><input type="checkbox" name="is_logical" value="1"> Slot internal/logis (Switch port tetap)</label>@endunless
            <button type="submit" class="rounded-lg bg-sky-600 hover:bg-sky-700 px-3.5 py-1.5 text-xs font-semibold text-white sm:col-span-3 sm:justify-self-start cursor-pointer shadow-xs transition-colors">Tambah Slot</button>
        </form>
    </details>
    @endcan
</section>
