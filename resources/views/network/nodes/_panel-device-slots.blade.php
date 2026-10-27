{{--
    Manajemen Node — OLT/Switch: HANYA daftar Slot milik perangkat ini +
    kapasitas tiap Slot (spesifikasi user 2026-10-26). PON/Port Switch tiap
    Slot SENGAJA tidak dirender di sini — dibuka lewat panel Slot (Master
    Node) atau halaman Detail Slot. Dulu kartu PON bersarang di tiap Slot:
    terlalu banyak informasi sekaligus dan memaksa satu query sambungan per
    Port Switch.

    Definisi angka:
      perangkat : terpakai = Slot terdata, kapasitas = kapasitas perangkat (slot).
      tiap Slot : terpakai = PON/Port terdata di Slot itu, kapasitas = kapasitas Slot.
      sisa      = kapasitas − terpakai (tidak di bawah 0); kapasitas null = belum didata.

    Variabel: $device, $slots (withCount children — anak Slot hanya PON/Port; BUKAN withCount positionPorts: whereHas tipe di relasi diri-sendiri itu membandingkan tipe Slot luar, hasilnya selalu 0; nodeType ter-load),
    $inMasterNode (bool) — true = disisipkan ke inspector Master Node: tiap
    form membawa `master_node` (PRG kembali ke Master Node, lihat trait
    RedirectsToMasterNode) dan tiap Slot punya tombol "Kelola" (drill-down
    lewat `data-select-position`, ditangani listener delegasi di index —
    partial ini tidak memuat atribut Alpine apa pun).
--}}
@php
    $isOlt = \App\Enums\InfrastructureSlotKind::forDeviceTypeCode($device->nodeType?->code) === \App\Enums\InfrastructureSlotKind::OLT;
    $childLabel = $isOlt ? 'PON' : 'Port Switch';
    $deviceCapacity = \App\Support\MasterNodeInspector::capacity($device->capacity, $slots->count());
@endphp
<section id="device-components-{{ $device->id }}" data-node-panel="device" class="space-y-4 rounded-lg border border-border bg-surface p-4 sm:p-5 shadow-2xs">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="space-y-1">
            <h2 class="text-xs font-bold uppercase tracking-wider text-text-main">Slot perangkat · <span class="font-mono text-sky-600 dark:text-sky-400">{{ $device->code }}</span></h2>
            <p class="text-xs text-text-muted">Slot merupakan komponen {{ $isOlt ? 'OLT' : 'Switch' }} ini. {{ $isOlt ? 'Setiap Slot memuat PON; PON menghubungkan ODP.' : 'Setiap Slot memuat Port Switch.' }}</p>
            @include('network.nodes._panel-capacity', ['cap' => $deviceCapacity, 'unit' => 'slot'])
        </div>
    </div>

    @can('network_nodes.update')
    <form method="POST" action="{{ route('network.nodes.capacity', $device) }}" class="flex flex-wrap items-end gap-2 bg-surface-muted/40 p-3 rounded-lg border border-border">
        @csrf @method('PATCH')
        @if($inMasterNode)<input type="hidden" name="master_node" value="{{ $device->id }}">@endif
        <label class="text-xs font-medium text-text-secondary">Kapasitas perangkat (slot)
            <input name="capacity" type="number" min="1" max="1000" required value="{{ $device->capacity }}" placeholder="Belum didata" class="mt-1 block w-36 rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-mono text-text-main focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
        </label>
        <button type="submit" class="rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-semibold text-text-main hover:bg-surface-muted transition-colors cursor-pointer shadow-2xs">Simpan kapasitas perangkat</button>
    </form>
    @endcan

    <div class="grid gap-3 lg:grid-cols-2">
        @forelse($slots as $slot)
        @php
            $slotCapacity = \App\Support\MasterNodeInspector::capacity($slot->capacity, (int) $slot->children_count);
            $childByNumber = $slot->children->keyBy('number');
            // Petak 1..kapasitas supaya PON kosong kelihatan. Kapasitas besar tidak
            // digambar penuh (ratusan petak = panel berat) — cukup yang terdata.
            $maxTiles = 512;
            $tileTotal = ($slot->capacity !== null && $slot->capacity > 0 && $slot->capacity <= $maxTiles) ? (int) $slot->capacity : 0;
            $tileNumbers = collect($tileTotal > 0 ? range(1, $tileTotal) : [])
                ->merge($childByNumber->keys())->unique()->sort()->values();
        @endphp
        <div data-panel-slot="{{ $slot->id }}" class="space-y-3 rounded-xl border border-border bg-surface-muted/30 p-3.5">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div class="min-w-0 space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-bold font-mono text-text-main">{{ $slot->displayName() }}</span>
                        @if($slot->record_status)
                            <span class="text-[10px] text-text-muted">{{ $slot->record_status->label() }}</span>
                        @endif
                    </div>
                    @include('network.nodes._panel-capacity', ['cap' => $slotCapacity, 'unit' => $childLabel.' terdata'])
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    @if($inMasterNode)
                    <button type="button" data-select-position="{{ json_encode(\App\Support\MasterNodeInspector::positionPayload($slot, $device->code.' / '.$slot->displayName())) }}"
                            class="rounded-lg bg-sky-600 hover:bg-sky-700 px-2.5 py-1 text-[11px] font-semibold text-white cursor-pointer shadow-2xs transition-colors">Kelola {{ $childLabel }} &rarr;</button>
                    @endif
                    <a href="{{ route('network.infrastructure-slots.show', $slot) }}" class="rounded-lg border border-border bg-surface px-2.5 py-1 text-[11px] font-semibold text-sky-600 dark:text-sky-400 hover:bg-surface-muted transition-colors shadow-2xs">Detail</a>
                </div>
            </div>
            @if($tileNumbers->isNotEmpty())
            <div class="grid grid-cols-4 gap-1.5 sm:grid-cols-6 xl:grid-cols-8" data-slot-tiles="{{ $slot->id }}">
                @foreach($tileNumbers as $number)
                    @php $child = $childByNumber->get($number); @endphp
                    @if($child && $inMasterNode)
                        <button type="button" data-slot-tile="filled" title="{{ $child->displayName() }}"
                                data-select-position="{{ json_encode(\App\Support\MasterNodeInspector::positionPayload($child, $device->code.' / '.$slot->displayName().' / '.$child->displayName())) }}"
                                class="rounded-lg border border-sky-300 dark:border-sky-800 bg-sky-50 dark:bg-sky-950/40 px-1 py-1.5 text-center text-[11px] font-bold font-mono text-sky-700 dark:text-sky-300 hover:bg-sky-100 dark:hover:bg-sky-900/50 cursor-pointer transition-colors">{{ $number }}</button>
                    @elseif($child)
                        <span data-slot-tile="filled" title="{{ $child->displayName() }}" class="rounded-lg border border-sky-300 dark:border-sky-800 bg-sky-50 dark:bg-sky-950/40 px-1 py-1.5 text-center text-[11px] font-bold font-mono text-sky-700 dark:text-sky-300">{{ $number }}</span>
                    @else
                        <span data-slot-tile="empty" title="{{ $childLabel }} {{ $number }} kosong" class="rounded-lg border border-dashed border-border bg-surface px-1 py-1.5 text-center text-[11px] font-mono text-text-muted">{{ $number }}</span>
                    @endif
                @endforeach
            </div>
            <p class="text-[10px] text-text-muted">Terisi = {{ $childLabel }} sudah didata; garis putus-putus = kosong.</p>
            @elseif($slot->capacity !== null && $slot->capacity > $maxTiles)
            <p class="text-[10px] text-text-muted">Kapasitas {{ $slot->capacity }} terlalu besar digambar per petak; buka Kelola {{ $childLabel }}.</p>
            @endif
        </div>
        @empty
        <p class="rounded-xl border border-border bg-surface p-3.5 text-xs text-text-muted lg:col-span-2">Belum ada Slot yang didata pada perangkat ini.</p>
        @endforelse
    </div>

    @can('network_nodes.update')
    <details class="border-t border-border pt-3">
        <summary class="cursor-pointer text-xs font-semibold text-sky-600 dark:text-sky-400 hover:underline">Tambah Slot pada {{ $device->code }}</summary>
        <form method="POST" action="{{ route('network.nodes.infrastructure-slots.store', $device) }}" class="mt-3 grid gap-3 sm:grid-cols-3 bg-surface-muted/30 p-3 rounded-lg border border-border">
            @csrf
            @if($inMasterNode)<input type="hidden" name="master_node" value="{{ $device->id }}">@endif
            <label class="text-xs font-medium text-text-secondary">Nomor Slot <span class="text-rose-500">*</span>
                <input type="number" name="number" min="1" required class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-mono text-text-main focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
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
