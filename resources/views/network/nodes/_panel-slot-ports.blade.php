{{--
    Manajemen Node — Slot: HANYA daftar PON (Slot OLT) / Port Switch (Slot
    Switch) di Slot ini + kapasitas tiap PON. Slot saudara & ODP tidak ikut.

    Definisi angka:
      Slot     : terpakai = PON/Port terdata, kapasitas = kapasitas Slot.
      tiap PON : terpakai = pelanggan aktif (PonCapacityService — satu pelanggan
                 = satu unit lewat ODP anak langsung), kapasitas = kapasitas PON,
                 sisa = kapasitas − terpakai, Red Zone = terpakai ≥ ambang% × kapasitas
                 (null bila kapasitas/ambang belum didata).
      Port Switch : tidak punya kapasitas pelanggan — yang ditampilkan status
                 keterisiannya (sambungan aktif, InfrastructureConnectionService).

    Variabel: $slot, $ports, $isOlt, $ponSummaries, $occupancies, $path,
    $inMasterNode — lihat docblock `NetworkNodeController::panel()`.
--}}
@php
    $portLabel = $slot->slotKind()?->portKind()?->label() ?? 'Port';
    $slotCapacity = \App\Support\MasterNodeInspector::capacity($slot->capacity, $ports->count());
@endphp
<section id="slot-components-{{ $slot->id }}" data-node-panel="slot" class="space-y-4 rounded-lg border border-border bg-surface p-4 sm:p-5 shadow-2xs">
    <div class="space-y-1">
        <h2 class="text-xs font-bold uppercase tracking-wider text-text-main">{{ $portLabel }} pada <span class="font-mono text-sky-600 dark:text-sky-400">{{ $path }}</span></h2>
        @include('network.nodes._panel-capacity', ['cap' => $slotCapacity, 'unit' => $portLabel.' terdata'])
    </div>

    @can('network_nodes.update')
    <form method="POST" action="{{ route('network.infrastructure-slots.capacity', $slot) }}" class="flex flex-wrap items-end gap-2 bg-surface-muted/40 p-3 rounded-lg border border-border">
        @csrf @method('PATCH')
        @if($inMasterNode)<input type="hidden" name="master_node" value="{{ $slot->id }}">@endif
        <label class="text-xs font-medium text-text-secondary">Kapasitas Slot ({{ $slot->capacity_unit?->label() ?? ($isOlt ? 'PON per slot' : 'port per slot') }})
            <input type="number" name="capacity" min="1" max="9999" required value="{{ $slot->capacity }}" placeholder="Belum didata" class="mt-1 block w-36 rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-mono text-text-main focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
        </label>
        <button type="submit" class="rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-semibold text-text-main hover:bg-surface-muted transition-colors cursor-pointer shadow-2xs">Simpan kapasitas slot</button>
    </form>
    @endcan

    @php
        // Nomor 1..kapasitas Slot supaya yang kosong kelihatan; kapasitas besar
        // tidak digambar penuh (panel berat) — cukup yang terdata.
        $portByNumber = $ports->keyBy('number');
        $maxTiles = 512;
        $tileTotal = ($slot->capacity !== null && $slot->capacity > 0 && $slot->capacity <= $maxTiles) ? (int) $slot->capacity : 0;
        $emptyNumbers = collect($tileTotal > 0 ? range(1, $tileTotal) : [])->reject(fn ($n) => $portByNumber->has($n))->values();
    @endphp
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3" data-slot-ports="{{ $slot->id }}">
        @forelse($ports as $port)
        @php
            $summary = $ponSummaries[$port->id] ?? null;
            $occupancy = $occupancies[$port->id] ?? null;
        @endphp
        <div data-panel-port="{{ $port->id }}" class="flex flex-col justify-between gap-3 rounded-xl border border-sky-200 dark:border-sky-900 bg-surface-muted/30 p-3.5">
            <div class="min-w-0 space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-bold font-mono text-text-main">{{ $port->displayName() }}</span>
                    <span class="text-[10px] text-text-muted">{{ $occupancy?->label() ?? $port->record_status?->label() }}</span>
                    @if($isOlt)
                        <span class="text-[10px] text-text-muted">· {{ (int) $port->children_count }} ODP</span>
                    @endif
                </div>
                @if($summary !== null)
                    @include('network.nodes._panel-capacity', ['cap' => $summary, 'unit' => $summary['unit_label']])
                    @if($summary['red_zone'] === null && $summary['capacity'] !== null)
                        <p class="text-[10px] text-text-muted">Ambang Red Zone belum didata.</p>
                    @endif
                @endif
            </div>
            <div class="flex shrink-0 items-center gap-2">
                @if($inMasterNode && $port->isPon())
                <button type="button" data-select-position="{{ json_encode(\App\Support\MasterNodeInspector::positionPayload($port, $path.' / '.$port->displayName())) }}"
                        class="rounded-lg bg-sky-600 hover:bg-sky-700 px-2.5 py-1 text-[11px] font-semibold text-white cursor-pointer shadow-2xs transition-colors">Kelola ODP &rarr;</button>
                @endif
                <a href="{{ route('network.infrastructure-ports.show', $port) }}" class="rounded-lg border border-border bg-surface px-2.5 py-1 text-[11px] font-semibold text-sky-600 dark:text-sky-400 hover:bg-surface-muted transition-colors shadow-2xs">Detail</a>
            </div>
        </div>
        @empty
            @if($emptyNumbers->isEmpty())
            <p class="rounded-xl border border-border bg-surface p-3.5 text-xs text-text-muted sm:col-span-2 xl:col-span-3">Belum ada {{ $portLabel }} pada Slot ini.</p>
            @endif
        @endforelse
        @foreach($emptyNumbers as $number)
        <div data-panel-empty="{{ $number }}" class="flex items-center justify-between gap-2 rounded-xl border border-dashed border-border bg-surface px-3.5 py-3">
            <span class="text-xs font-bold font-mono text-text-muted">{{ $portLabel }} {{ $number }}</span>
            <span class="text-[10px] text-text-muted">Kosong — belum didata</span>
        </div>
        @endforeach
    </div>

    @can('network_nodes.update')
    <details class="border-t border-border pt-3">
        <summary class="cursor-pointer text-xs font-semibold text-sky-600 dark:text-sky-400 hover:underline">Tambah {{ $portLabel }} pada {{ $slot->displayName() }}</summary>
        <form method="POST" action="{{ route('network.infrastructure-slots.ports.store', $slot) }}" class="mt-3 grid gap-3 sm:grid-cols-3 bg-surface-muted/30 p-3 rounded-lg border border-border">
            @csrf
            @if($inMasterNode)<input type="hidden" name="master_node" value="{{ $slot->id }}">@endif
            <label class="text-xs font-medium text-text-secondary">Nomor {{ $portLabel }} <span class="text-rose-500">*</span>
                <input type="number" name="number" min="1" required class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-mono text-text-main focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
            </label>
            @if($isOlt)
            <label class="text-xs font-medium text-text-secondary">Kapasitas PON (pelanggan aktif)
                <input type="number" name="capacity" min="1" max="100000" class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-mono text-text-main focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
            </label>
            <label class="text-xs font-medium text-text-secondary">Ambang Red Zone (%)
                <input type="number" name="red_zone_threshold" min="1" max="100" class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-mono text-text-main focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
            </label>
            @endif
            <button type="submit" class="rounded-lg bg-sky-600 hover:bg-sky-700 px-3.5 py-1.5 text-xs font-semibold text-white sm:col-span-3 sm:justify-self-start cursor-pointer shadow-xs transition-colors">Tambah {{ $portLabel }}</button>
        </form>
    </details>
    @endcan
</section>
