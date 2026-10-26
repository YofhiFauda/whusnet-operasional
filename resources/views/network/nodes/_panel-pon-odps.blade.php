{{--
    Manajemen Node — PON: HANYA daftar ODP anak langsung PON ini (rancangan
    §2.4: ODP = anak `parent_id` PON) + kapasitas tiap ODP, ditambah total
    PON itu sendiri. ODP PON lain tidak ikut.

    Definisi angka:
      PON      : terpakai = pelanggan aktif lewat ODP anak (PonCapacityService),
                 kapasitas = kapasitas PON, sisa, Red Zone (null bila kapasitas/ambang
                 belum didata).
      tiap ODP : kapasitas = jumlah port ODP (`network_node_ports`), terpakai = port
                 `is_used` (pelanggan, sambungan node, atau tanda manual), sisa =
                 kapasitas − terpakai, Red Zone = `NetworkNode::isInRedZone()` (ambang
                 tipe node). "Pelanggan aktif" = kontribusi ODP ini ke terpakai PON
                 (`PonCapacityService::customersPerOdp()`, jumlahnya = terpakai PON).
      ODP tanpa port = kapasitas belum didata.

    Variabel: $pon, $summary, $odps, $customersPerOdp, $odpTypeId, $path,
    $inMasterNode — lihat docblock `NetworkNodeController::panel()`.
--}}
<section id="pon-components-{{ $pon->id }}" data-node-panel="pon" class="space-y-4 rounded-lg border border-border bg-surface p-4 sm:p-5 shadow-2xs">
    <div class="space-y-1" data-pon-total>
        <h2 class="text-xs font-bold uppercase tracking-wider text-text-main">ODP pada <span class="font-mono text-sky-600 dark:text-sky-400">{{ $path }}</span></h2>
        @include('network.nodes._panel-capacity', ['cap' => $summary, 'unit' => $summary['unit_label']])
        <p class="text-[11px] text-text-muted">
            {{ $odps->count() }} ODP ·
            @if($summary['threshold'] !== null) Ambang Red Zone {{ $summary['threshold'] }}% @else Ambang Red Zone belum didata @endif
            · Status {{ $pon->record_status?->label() ?? 'Belum didata' }}
        </p>
    </div>

    @can('network_nodes.update')
    <form method="POST" action="{{ route('network.infrastructure-ports.capacity', $pon) }}" class="flex flex-wrap items-end gap-2 bg-surface-muted/40 p-3 rounded-lg border border-border">
        @csrf @method('PATCH')
        @if($inMasterNode)<input type="hidden" name="master_node" value="{{ $pon->id }}">@endif
        <label class="text-xs font-medium text-text-secondary">Kapasitas PON (pelanggan aktif)
            <input type="number" name="capacity" min="1" max="100000" required value="{{ $pon->capacity }}" placeholder="Belum didata" class="mt-1 block w-36 rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-mono text-text-main focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
        </label>
        <label class="text-xs font-medium text-text-secondary">Ambang Red Zone (%)
            <input type="number" name="red_zone_threshold" min="1" max="100" required value="{{ $pon->red_zone_threshold }}" placeholder="Belum didata" class="mt-1 block w-36 rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-mono text-text-main focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
        </label>
        <button type="submit" class="rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-semibold text-text-main hover:bg-surface-muted transition-colors cursor-pointer shadow-2xs">Simpan kapasitas PON</button>
    </form>
    @endcan

    <div class="divide-y divide-border rounded-lg border border-border overflow-hidden">
        @forelse($odps as $odp)
        @php
            $odpCapacity = \App\Support\MasterNodeInspector::capacity((int) $odp->ports_count, (int) $odp->used_ports_count) + ['red_zone' => $odp->isInRedZone()];
        @endphp
        <div data-panel-odp="{{ $odp->id }}" class="flex flex-wrap items-center justify-between gap-3 bg-surface px-3.5 py-2.5">
            <div class="min-w-0 space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-bold font-mono text-text-main">{{ $odp->code }}</span>
                    <span class="text-xs text-text-muted truncate">{{ $odp->name }}</span>
                    <span class="text-[10px] text-text-muted">· {{ $customersPerOdp[$odp->id] ?? 0 }} pelanggan aktif</span>
                </div>
                @include('network.nodes._panel-capacity', ['cap' => $odpCapacity, 'unit' => 'port ODP'])
            </div>
            <div class="flex shrink-0 items-center gap-1.5">
                {{-- Di Master Node, ODP dibuka di inspector ODP yang sama dengan ODP
                     lain (grid port, modal port, relokasi) lewat index?node=<id> —
                     tautan biasa (panel ini HTML tanpa Alpine), URL dari route(). --}}
                @if($inMasterNode)
                <a href="{{ route('network.nodes.index', ['node' => $odp->id]) }}" data-select-odp="{{ $odp->id }}" class="rounded-lg bg-sky-600 hover:bg-sky-700 px-2.5 py-1 text-[11px] font-semibold text-white transition-colors shadow-2xs">Kelola Port</a>
                @endif
                <a href="{{ route('network.nodes.show', $odp) }}" class="rounded-lg border border-border bg-surface px-2.5 py-1 text-[11px] font-semibold text-sky-600 dark:text-sky-400 hover:bg-surface-muted transition-colors shadow-2xs">Detail</a>
            </div>
        </div>
        @empty
        <p class="bg-surface p-3.5 text-xs text-text-muted">Belum ada ODP yang tersambung ke PON ini.</p>
        @endforelse
    </div>

    @if($odpTypeId)
    @can('network_nodes.create')
    <details class="border-t border-border pt-3">
        <summary class="cursor-pointer text-xs font-semibold text-sky-600 dark:text-sky-400 hover:underline">Tambah ODP pada {{ $pon->displayName() }}</summary>
        {{-- Tanpa `device_type`: field itu penanda form modal Tambah Titik —
             kalau ikut terkirim, gagal validasi di sini membuka modal itu. --}}
        <form method="POST" action="{{ route('network.nodes.store') }}" class="mt-3 grid gap-3 sm:grid-cols-3 bg-surface-muted/30 p-3 rounded-lg border border-border" data-add-odp-form>
            @csrf
            @if($inMasterNode)<input type="hidden" name="master_node" value="{{ $pon->id }}">@endif
            <input type="hidden" name="parent_id" value="{{ $pon->id }}">
            <input type="hidden" name="node_type_id" value="{{ $odpTypeId }}">
            <label class="text-xs font-medium text-text-secondary">Kode ODP <span class="text-rose-500">*</span>
                <input type="text" name="code" required maxlength="50" placeholder="Contoh: ODP-SIMAN-01" class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-mono uppercase text-text-main focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
            </label>
            <label class="text-xs font-medium text-text-secondary">Nama ODP <span class="text-rose-500">*</span>
                <input type="text" name="name" required maxlength="150" class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-1.5 text-xs text-text-main focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
            </label>
            <label class="text-xs font-medium text-text-secondary">Jumlah port ODP <span class="text-rose-500">*</span>
                <input type="number" name="capacity" required min="1" max="1000" value="8" class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-mono text-text-main focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
            </label>
            <label class="text-xs font-medium text-text-secondary sm:col-span-3">Alamat / patokan tiang (opsional)
                <input type="text" name="address" maxlength="2000" class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-1.5 text-xs text-text-main focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
            </label>
            <button type="submit" class="rounded-lg bg-sky-600 hover:bg-sky-700 px-3.5 py-1.5 text-xs font-semibold text-white sm:col-span-3 sm:justify-self-start cursor-pointer shadow-xs transition-colors">Tambah ODP</button>
        </form>
    </details>
    @endcan
    @endif
</section>
