@php
    $deviceItem = $hierarchy['items']['node:'.$device->id] ?? null;
    $deviceCapacity = $deviceItem['capacity'] ?? null;
    $isOlt = $device->nodeType?->code === 'olt_chassis';
@endphp
<section id="device-components-{{ $device->id }}" class="space-y-4 rounded-xl border border-border bg-surface p-4 sm:p-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-sm font-bold text-text-main">Slot perangkat · {{ $device->code }}</h2>
            <p class="mt-1 text-xs text-text-muted">Slot merupakan komponen {{ $isOlt ? 'OLT' : 'Switch' }} ini. {{ $isOlt ? 'Setiap Slot memuat PON; PON menghubungkan ODP.' : 'Setiap Slot memuat Port Switch.' }}</p>
        </div>
        <p class="text-xs text-text-secondary">
            {{ $device->infrastructureSlots->count() }} slot didata /
            {{ $device->capacity ?? '?' }} slot kapasitas
            @if($deviceCapacity) · {{ $deviceCapacity['used'] }} slot terisi kartu @endif
        </p>
    </div>

    @can('network_nodes.update')
    <form method="POST" action="{{ route('network.nodes.capacity', $device) }}" class="flex flex-wrap items-end gap-2">
        @csrf @method('PATCH')
        <label class="text-xs text-text-secondary">Kapasitas perangkat (slot)
            <input name="capacity" type="number" min="1" max="1000" required value="{{ $device->capacity }}" placeholder="Belum didata" class="mt-1 block w-36 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-main">
        </label>
        <button type="submit" class="min-h-9 rounded-lg border border-border px-3 text-xs font-semibold text-text-main hover:bg-surface-muted">Simpan kapasitas perangkat</button>
    </form>
    @endcan

    <div class="space-y-3 border-l-2 border-sky-200 pl-3 dark:border-sky-800">
        @forelse($device->infrastructureSlots->sortBy('number') as $componentSlot)
        @php
            $slotItem = $hierarchy['items']['slot:'.$componentSlot->id] ?? null;
            $portLabel = $componentSlot->kind->portKind()->label();
        @endphp
        <details @if($componentSlot->ports->isNotEmpty()) open @endif data-component-slot="{{ $componentSlot->id }}" class="rounded-lg border border-border">
            <summary class="flex cursor-pointer flex-wrap items-center justify-between gap-2 bg-surface-muted px-3 py-2 text-xs text-text-main">
                <span class="font-semibold">{{ $componentSlot->displayName() }} @if($componentSlot->label) · {{ $componentSlot->label }} @endif</span>
                <span>{{ $componentSlot->ports->count() }} / {{ $componentSlot->capacity ?? '?' }} {{ $componentSlot->capacity_unit?->label() }}</span>
            </summary>
            <div class="space-y-3 p-3">
                <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                    <a href="{{ route('network.infrastructure-slots.show', $componentSlot) }}" class="font-semibold text-primary hover:underline">Detail {{ $componentSlot->displayName() }}</a>
                    <span class="text-text-muted">{{ $componentSlot->status->label() }}</span>
                </div>
                @can('network_nodes.update')
                <form method="POST" action="{{ route('network.infrastructure-slots.capacity', $componentSlot) }}" class="flex flex-wrap items-end gap-2">
                    @csrf @method('PATCH')
                    <label class="text-xs text-text-secondary">Kapasitas ({{ $componentSlot->capacity_unit?->label() }})
                        <input type="number" name="capacity" min="1" max="9999" required value="{{ $componentSlot->capacity }}" placeholder="Belum didata" class="mt-1 block w-36 rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-main">
                    </label>
                    <button type="submit" class="min-h-9 rounded-lg border border-border px-3 text-xs font-semibold text-text-main hover:bg-surface-muted">Simpan kapasitas slot</button>
                </form>
                @endcan
                {{-- Patch panel port/PON — visual sama seperti Manajemen Port Node ODP
                     (warna status + badge kapasitas), TAPI klik kartu TIDAK pindah
                     halaman: connect PON→ODP perlu pilih ODP+verified+notes (mutasi
                     kompleks), jadi tombol "Detail" eksplisit sesuai pola 2 CLAUDE.md
                     (mutasi kompleks = halaman sendiri, bukan modal). --}}
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @forelse($componentSlot->ports->sortBy('number') as $componentPort)
                    @php
                        $portItem = $hierarchy['items']['port:'.$componentPort->id] ?? null;
                        $portCapacity = $portItem['capacity'] ?? null;
                        $portOdps = $componentPort->kind === \App\Enums\InfrastructurePortKind::PON
                            ? array_values(array_filter($hierarchy['children']['port:'.$componentPort->id] ?? [],
                                fn (string $childKey): bool => $hierarchy['items'][$childKey]['type'] === 'odp'))
                            : [];
                        $statusLabel = $portItem['status_label'] ?? $componentPort->status->label();
                        $isTerpakai = str_starts_with($statusLabel, 'Terpakai');
                        $isTerverifikasi = str_contains($statusLabel, 'terverifikasi') && ! str_contains($statusLabel, 'belum');
                        $isRusak = str_starts_with($statusLabel, 'Rusak');
                        $cardClass = match (true) {
                            $isRusak => 'bg-surface-muted border-border opacity-70',
                            $isTerpakai && $isTerverifikasi => 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700',
                            $isTerpakai => 'bg-amber-50/60 dark:bg-amber-950/30 border-amber-300/80 dark:border-amber-800',
                            default => 'bg-white dark:bg-slate-800 border-2 border-dashed border-emerald-300/90 dark:border-emerald-700/80',
                        };
                        $badgeClass = match (true) {
                            $isRusak => 'text-text-muted bg-surface-muted border-border',
                            $isTerpakai && $isTerverifikasi => 'text-rose-700 bg-rose-50 border-rose-200 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-900',
                            $isTerpakai => 'text-amber-700 bg-amber-50 border-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-900',
                            default => 'text-emerald-700 bg-emerald-50 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800',
                        };
                    @endphp
                    <div data-component-port="{{ $componentPort->id }}" class="rounded-xl border p-3 {{ $cardClass }} flex flex-col justify-between min-h-[88px]">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs font-bold font-mono text-text-main">{{ $componentPort->displayName() }}</span>
                            <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full border {{ $badgeClass }}">{{ $statusLabel }}</span>
                        </div>
                        <div class="mt-2 min-w-0 flex items-end justify-between gap-2">
                            <div class="min-w-0">
                                @if($portCapacity)
                                    <div class="text-xs font-semibold text-text-main">{{ $portCapacity['used'] }} / {{ $portCapacity['capacity'] ?? '?' }} {{ $portCapacity['unit_label'] }}</div>
                                @endif
                                @if($portItem['connection_label'] ?? null)
                                    <div class="text-[11px] text-text-muted truncate" title="{{ $portItem['connection_label'] }}">&rarr; {{ $portItem['connection_label'] }}</div>
                                @endif
                            </div>
                            <a href="{{ route('network.infrastructure-ports.show', $componentPort) }}" class="shrink-0 rounded-lg border border-border px-2 py-1 text-[11px] font-semibold text-primary hover:bg-surface-muted">Detail &rarr;</a>
                        </div>
                        {{-- Daftar ODP/splitter yang nyambung ke PON ini — view-only
                             (pola 1 CLAUDE.md), satu PON bisa punya banyak ODP. Lepas/
                             hubungkan baru tetap lewat tombol Detail di atas. --}}
                        @if($portOdps !== [])
                        <div class="mt-2 space-y-1 border-t border-border/60 pt-2">
                            @foreach($portOdps as $odpKey)
                            @php($odpItem = $hierarchy['items'][$odpKey])
                            <a href="{{ $odpItem['url'] }}" class="flex items-center justify-between gap-1 text-[11px] hover:text-primary">
                                <span class="truncate font-mono">&rarr; {{ $odpItem['label'] }}</span>
                                <span class="shrink-0 {{ $odpItem['verified'] ? 'text-emerald-600' : 'text-amber-600' }}">{{ $odpItem['verified'] ? 'terverifikasi' : 'belum verifikasi' }}</span>
                            </a>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    @empty
                    <p class="col-span-full text-xs text-text-muted">Belum ada {{ $portLabel }} pada Slot ini.</p>
                    @endforelse
                </div>
                @can('network_nodes.update')
                <details class="border-t border-border pt-2">
                    <summary class="cursor-pointer text-xs font-semibold text-primary">Tambah {{ $portLabel }} pada {{ $componentSlot->displayName() }}</summary>
                    <form method="POST" action="{{ route('network.infrastructure-slots.ports.store', $componentSlot) }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                        @csrf
                        <label class="text-xs text-text-secondary">Nomor {{ $portLabel }}
                            <input type="number" name="number" min="1" required class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-main">
                        </label>
                        <label class="text-xs text-text-secondary">Kode (opsional)
                            <input type="text" name="code" maxlength="50" class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-main">
                        </label>
                        @if($isOlt)
                        <label class="text-xs text-text-secondary">Kapasitas PON (pelanggan aktif)
                            <input type="number" name="capacity" min="1" max="100000" class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-main">
                        </label>
                        <label class="text-xs text-text-secondary">Ambang Red Zone (%)
                            <input type="number" name="red_zone_threshold" min="1" max="100" class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-main">
                        </label>
                        @endif
                        <button type="submit" class="min-h-9 rounded-lg bg-primary px-3 text-xs font-semibold text-white sm:justify-self-start">Tambah {{ $portLabel }}</button>
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
        <summary class="cursor-pointer text-xs font-semibold text-primary">Tambah Slot pada {{ $device->code }}</summary>
        <form method="POST" action="{{ route('network.nodes.infrastructure-slots.store', $device) }}" class="mt-3 grid gap-3 sm:grid-cols-3">
            @csrf
            <label class="text-xs text-text-secondary">Nomor Slot
                <input type="number" name="number" min="1" required class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-main">
            </label>
            <label class="text-xs text-text-secondary">Kode (opsional)
                <input type="text" name="code" maxlength="50" class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-main">
            </label>
            <label class="text-xs text-text-secondary">Kapasitas ({{ $isOlt ? 'PON per slot' : 'port per slot' }})
                <input type="number" name="capacity" min="1" max="9999" class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-main">
            </label>
            @unless($isOlt)<label class="flex items-center gap-2 text-xs text-text-secondary"><input type="checkbox" name="is_logical" value="1"> Slot internal/logis (Switch port tetap)</label>@endunless
            <button type="submit" class="min-h-9 rounded-lg bg-primary px-3 text-xs font-semibold text-white sm:col-span-3 sm:justify-self-start">Tambah Slot</button>
        </form>
    </details>
    @endcan
</section>
