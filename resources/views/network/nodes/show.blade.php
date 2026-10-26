@extends('layouts.app')

@section('title', $node->code.' · Detail Node Jaringan')
@section('page_title', 'Detail Node Jaringan')

@section('content')
<div class="mx-auto max-w-5xl space-y-6 pb-16">

    {{-- Layer 1: Naked Page Header (Design.md §1.7 — tanpa card container) --}}
    <div class="page-header flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div class="space-y-1.5">
            <nav class="flex items-center gap-1.5 text-xs text-text-muted" aria-label="Breadcrumb">
                <a href="{{ route('dashboard') }}" class="hover:text-primary transition-colors">Home</a>
                <span class="text-text-disabled">/</span>
                <a href="{{ route('network.nodes.index') }}" class="hover:text-primary transition-colors">Jaringan &amp; ODN</a>
                <span class="text-text-disabled">/</span>
                <span class="text-text-main font-semibold">{{ $node->code }}</span>
            </nav>
            <div class="flex items-center gap-2.5 flex-wrap">
                <span class="px-2.5 py-0.5 rounded-md text-[10px] font-mono font-bold uppercase tracking-wider border
                    @if(str_contains($node->nodeType?->code ?? '', 'odp')) bg-emerald-50 text-emerald-700 dark:bg-emerald-950/70 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800
                    @elseif(str_contains($node->nodeType?->code ?? '', 'olt') || str_contains($node->nodeType?->code ?? '', 'pon')) bg-amber-50 text-amber-800 dark:bg-amber-950/70 dark:text-amber-300 border-amber-200 dark:border-amber-800
                    @else bg-sky-50 text-sky-700 dark:bg-sky-950/70 dark:text-sky-300 border-sky-200 dark:border-sky-800 @endif">
                    {{ $node->nodeType?->name }}
                </span>
                <h1 class="text-2xl font-bold font-mono tracking-tight text-text-main">
                    {{ $node->code }}
                </h1>
                <span class="text-text-disabled">&bull;</span>
                <span class="text-lg font-medium text-text-secondary truncate">
                    {{ $node->name }}
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-semibold {{ $node->status === 'active' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' : 'bg-surface-muted text-text-muted border border-border' }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $node->status === 'active' ? 'bg-emerald-500 animate-pulse' : 'bg-text-disabled' }}"></span>
                    {{ ucfirst($node->status) }}
                </span>
            </div>
            <p class="text-xs text-text-muted font-medium">
                Jalur Hirarki: <span class="text-text-main font-semibold">{{ $node->breadcrumbLabel() }}</span>
            </p>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap shrink-0">
            <a href="{{ route('network.nodes.index') }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-border bg-surface hover:bg-surface-muted text-xs font-semibold text-text-secondary transition-colors shadow-2xs">
                <svg class="h-3.5 w-3.5 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                <span>Kembali ke Pohon Node</span>
            </a>

            @can('network_nodes.update')
            @if(\App\Enums\InfrastructureSlotKind::forDeviceTypeCode($node->nodeType?->code) !== null)
            <a href="{{ route('network.nodes.generate-structure.create', $node) }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-border bg-surface hover:bg-surface-muted text-xs font-semibold text-text-secondary transition-colors shadow-2xs">
                Generate Slot &amp; PON
            </a>
            @endif
            @endcan

            @can('network_nodes.delete')
            <form method="POST" action="{{ route('network.nodes.destroy', $node) }}" onsubmit="return confirm('Hapus node kosong? Node yang punya relasi akan dinonaktifkan.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-lg border border-rose-300 dark:border-rose-900 px-3 py-1.5 text-xs font-semibold text-rose-700 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 cursor-pointer shadow-2xs transition-colors">
                    Hapus Node
                </button>
            </form>
            @endcan
        </div>
    </div>

    {{-- Layer 2: Single Detail Panel (Design.md §1.3 Type B — Card Budget = 1) --}}
    <div class="rounded-lg border border-border bg-surface shadow-2xs overflow-hidden divide-y divide-border">

        {{-- Section 1: Lokasi & Wilayah --}}
        <div class="p-6 space-y-4">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-xs font-bold uppercase tracking-wider text-text-main flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-sky-600"></span>
                    <span>Lokasi &amp; Titik Geospasial</span>
                </h2>
                <span class="text-[11px] text-text-muted italic">
                    {{ $location['inherited_fields'] === [] ? 'Lokasi dicatat mandiri pada node ini' : 'Sebagian data lokasi diwarisi dari induk/POP' }}
                </span>
            </div>

            <div class="grid gap-3.5 sm:grid-cols-3 text-xs pt-1">
                <div class="p-4 rounded-lg bg-surface-muted/40 border border-border space-y-1">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted block">Alamat / Patokan Tiang</span>
                    <p class="font-semibold text-text-main leading-relaxed">{{ $location['address'] ?: 'Belum dicatat' }}</p>
                </div>
                <div class="p-4 rounded-lg bg-surface-muted/40 border border-border space-y-1">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted block">Wilayah Administratif</span>
                    <p class="font-semibold text-text-main leading-relaxed">{{ collect([$location['village'], $location['district'], $location['city']])->filter()->implode(', ') ?: 'Belum dicatat' }}</p>
                </div>
                <div class="p-4 rounded-lg bg-surface-muted/40 border border-border flex items-center justify-between gap-2">
                    <div class="space-y-1 min-w-0">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted block">Koordinat Maps GPS</span>
                        <p class="font-mono font-semibold text-sky-600 dark:text-sky-400 truncate">
                            {{ $location['latitude'] !== null && $location['longitude'] !== null ? "{$location['latitude']}, {$location['longitude']}" : 'Belum dicatat' }}
                        </p>
                    </div>
                    @if($location['latitude'] !== null && $location['longitude'] !== null)
                    <a href="https://maps.google.com/?q={{ $location['latitude'] }},{{ $location['longitude'] }}" target="_blank" rel="noopener"
                       class="px-3 py-1.5 rounded-lg border border-border bg-surface hover:bg-surface-muted text-xs font-semibold text-sky-600 dark:text-sky-400 transition shadow-2xs shrink-0 flex items-center gap-1">
                        <span>Peta</span>
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </a>
                    @endif
                </div>
            </div>
        </div>

        {{-- Section 2: Aset Gudang Terpasang --}}
        @if($node->assetSummary()->isNotEmpty())
        <div class="p-6 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-xs font-bold uppercase tracking-wider text-text-main flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                    <span>Aset Gudang &amp; Material Terpasang</span>
                </h2>
                <div class="flex items-center gap-2">
                    <a href="{{ route('network.map.index', ['node_id' => $node->id]) }}"
                       class="text-xs font-semibold text-sky-700 dark:text-sky-300 hover:underline">
                        Lihat detail di Peta Infrastruktur &rarr;
                    </a>
                    @can('network_nodes.register_infra')
                    <a href="{{ route('network.nodes.register-asset.create', $node) }}"
                       class="inline-flex items-center gap-1 rounded-lg border border-border bg-surface hover:bg-surface-muted px-2.5 py-1 text-xs font-semibold text-text-secondary transition-colors shadow-2xs">
                        Registrasi Aset Lama
                    </a>
                    @endcan
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-3 pt-1">
                @foreach($node->assetSummary() as $row)
                    <div class="rounded-lg border border-border bg-surface-muted/30 p-3.5 text-xs flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 border border-amber-200/60 dark:border-amber-800 flex items-center justify-center shrink-0">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-text-main truncate">{{ $row['name'] }}</p>
                            <p class="mt-0.5 text-text-muted font-mono">{{ rtrim(rtrim(number_format((float) $row['qty'], 2, ',', '.'), '0'), ',') }} {{ $row['unit'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Special Chassis / Switch Slots --}}
        @if(in_array($node->nodeType?->code, ['olt_chassis', 'switch_mini_pop'], true))
        <div class="p-6">
            @include('network.nodes._device-slots', ['device' => $node])
        </div>
        @endif

        {{-- Section 3: Riwayat Pemasangan Infrastruktur --}}
        @if($infrastructureActions->isNotEmpty())
        <div class="p-6 space-y-4">
            <h2 class="text-xs font-bold uppercase tracking-wider text-text-main flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-sky-600"></span>
                <span>Riwayat Pemasangan Infrastruktur</span>
            </h2>
            <div class="space-y-2">
                @foreach($infrastructureActions as $action)
                    <div class="flex flex-wrap items-start justify-between gap-2 rounded-lg border border-border bg-surface-muted/30 p-3 text-xs">
                        <div class="space-y-0.5">
                            <p class="font-semibold text-text-main">{{ $action->actionLabel() }}</p>
                            <p class="text-text-secondary font-mono text-[11px]">{{ $action->task?->task_number }} · {{ $action->technician?->name }}</p>
                            @if($action->workLocationLabel())
                                <p class="text-text-muted text-[11px]">{{ $action->workLocationLabel() }}</p>
                            @endif
                        </div>
                        <span class="font-mono text-text-muted text-[11px]">@datetime($action->performed_at)</span>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Section 4: Port & Penghuni --}}
        @if($node->ports->isNotEmpty())
        <div class="p-6 space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-text-main flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-sky-600"></span>
                        <span>Terminal Distribusi Port &bull; {{ $node->ports->count() }} Port</span>
                    </h2>
                    <p class="text-xs text-text-muted mt-0.5">Visual soket port splitter/ODP dan status penghuni terdaftar.</p>
                </div>
                @php
                    $usedPortsCount = $node->ports->where('is_used', true)->count();
                    $totalPortsCount = $node->ports->count();
                    $freePortsCount = $totalPortsCount - $usedPortsCount;
                    $usedPercent = $totalPortsCount > 0 ? round(($usedPortsCount / $totalPortsCount) * 100) : 0;
                @endphp
                <div class="flex items-center gap-2 text-xs flex-wrap">
                    <span class="px-2.5 py-1 rounded-lg bg-surface-muted border border-border font-bold text-text-main font-mono">
                        {{ $totalPortsCount }} Port
                    </span>
                    <span class="px-2.5 py-1 rounded-lg bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 text-rose-700 dark:text-rose-300 font-bold font-mono">
                        {{ $usedPortsCount }} terpakai ({{ $usedPercent }}%)
                    </span>
                    <span class="px-2.5 py-1 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900 text-emerald-700 dark:text-emerald-300 font-bold font-mono">
                        {{ $freePortsCount }} Kosong
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                @foreach($node->ports->sortBy('number') as $port)
                @php($assignment = $port->activeAssignment->first())
                <div class="rounded-lg border p-3.5 flex flex-col justify-between shadow-2xs space-y-3 transition-colors
                            {{ $port->is_used
                                ? 'border-border bg-surface'
                                : 'border-2 border-dashed border-emerald-300/80 dark:border-emerald-700/80 bg-surface' }}">
                    <div class="flex items-center justify-between gap-1.5 border-b border-border pb-2">
                        <span class="font-mono font-bold text-xs text-text-main">
                            Port {{ $port->number }}
                        </span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider
                                    {{ $port->is_used
                                        ? ($assignment?->customer
                                            ? 'bg-sky-50 text-sky-800 dark:bg-sky-950/60 dark:text-sky-300 border border-sky-200 dark:border-sky-800'
                                            : ($assignment?->targetNode
                                                ? 'bg-amber-50 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200 dark:border-amber-800'
                                                : 'bg-rose-50 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-200 dark:border-rose-800'))
                                        : 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' }}">
                            {{ $port->is_used ? 'Terpakai' : 'Kosong' }}
                        </span>
                    </div>

                    <div class="text-xs space-y-1.5 flex-1 min-w-0">
                        @if($assignment?->customer)
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted block">Penghuni</span>
                                <span class="font-bold text-text-main truncate block">{{ $assignment->customer->full_name }}</span>
                                @if($assignment->customer->customer_code || $assignment->customer->cid)
                                    <span class="block text-[11px] font-mono text-text-muted">{{ $assignment->customer->customer_code ?: $assignment->customer->cid }}</span>
                                @endif
                                {{-- SN Modem/ONT pelanggan = data sensitif: hanya dengan
                                     `customers.detail.devices.view_sensitive` DAN pelanggan dalam
                                     scope POP aktor. Blok ini sempat hilang saat redesign
                                     (203a018) padahal controller tetap menyiapkan datanya —
                                     dikembalikan beserta tesnya (InfrastructureMapTest). --}}
                                @if($canViewDeviceSerials && ($allowedCustomerPopIds === null || in_array($assignment->customer->pop_id, $allowedCustomerPopIds)))
                                    <div class="pt-1 border-t border-border/40">
                                    @forelse($assignment->customer->networkPortModems() as $modem)
                                        <div class="mt-1 p-1.5 rounded bg-surface/80 border border-border/60 text-[11px] text-text-secondary">
                                            <span class="font-semibold text-text-main">Modem/ONT: {{ $modem['name'] }}</span>
                                            <span class="block font-mono text-[10px] text-sky-700 dark:text-sky-300">SN {{ $modem['serial_number'] }}</span>
                                            <span class="text-[9px] text-text-muted">({{ $modem['source'] }})</span>
                                        </div>
                                    @empty
                                        <span class="block text-[11px] text-amber-700 dark:text-amber-400 font-medium">Modem belum tercatat</span>
                                    @endforelse
                                    </div>
                                @else
                                    <span class="block text-[11px] text-text-muted italic">SN modem dibatasi oleh izin perangkat pelanggan</span>
                                @endif
                            </div>
                        @elseif($assignment?->targetNode)
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted block">Downstream</span>
                                <span class="font-bold text-text-main truncate block">&rarr; {{ $assignment->targetNode->code }}</span>
                                <span class="block text-[11px] text-text-muted">{{ $assignment->targetNode->name }}</span>
                            </div>
                        @elseif($assignment?->manual_label)
                            <div>
                                <span class="font-semibold text-text-main">{{ $assignment->manual_label }}</span>
                                <span class="block text-[11px] text-amber-600 font-medium">Belum diverifikasi</span>
                            </div>
                        @elseif($port->is_used)
                            <span class="text-amber-600 text-xs font-medium">Belum diverifikasi</span>
                        @else
                            <div class="py-2 text-center text-emerald-700 dark:text-emerald-400 font-medium text-xs">
                                Port siap disambungkan
                            </div>
                        @endif
                    </div>

                    @can('network_nodes.update')
                    <div class="pt-2 border-t border-border">
                        @if($port->is_used)
                            <details class="group text-xs">
                                <summary class="flex items-center justify-between cursor-pointer text-rose-600 dark:text-rose-400 hover:text-rose-700 font-semibold py-1">
                                    <span>Kosongkan port</span>
                                    <span class="text-[10px] group-open:rotate-180 transition-transform">&darr;</span>
                                </summary>
                                <form method="POST" action="{{ route('network.node-ports.update', $port) }}" class="mt-2 space-y-2 p-2.5 rounded-lg border border-rose-200 dark:border-rose-900 bg-rose-50/60 dark:bg-rose-950/40">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="is_used" value="0">
                                    <label class="flex items-start gap-1.5 text-text-secondary text-[11px]">
                                        <input type="checkbox" name="physical_removal_confirmed" value="1" required checked>
                                        Pelepasan fisik sudah dilakukan
                                    </label>
                                    <input name="release_note" required maxlength="500" value="Pelepasan port kabel fisik" placeholder="Catatan pelepasan fisik" class="w-full rounded-md border border-border bg-surface px-2 py-1 text-xs text-text-main">
                                    <button type="submit" class="w-full rounded-md bg-rose-600 hover:bg-rose-700 text-white font-bold py-1.5 text-xs shadow-2xs cursor-pointer">
                                        Konfirmasi Lepas Port
                                    </button>
                                </form>
                            </details>
                        @else
                            @if($node->nodeType?->code === 'odp' && $availableCustomers->isNotEmpty())
                                <form method="POST" action="{{ route('network.node-ports.assign-customer', $port) }}" class="space-y-1.5">
                                    @csrf
                                    <select name="customer_id" required class="w-full rounded-md border border-border bg-surface px-2 py-1 text-xs text-text-main">
                                        <option value="">Pilih Pelanggan...</option>
                                        @foreach($availableCustomers as $cust)
                                            <option value="{{ $cust->id }}">{{ $cust->full_name }} ({{ $cust->customer_code ?: ($cust->cid ?: '#'.$cust->id) }})</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="w-full rounded-md bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-1.5 text-xs shadow-2xs transition-colors cursor-pointer">
                                        + Sambung Pelanggan
                                    </button>
                                </form>
                            @elseif($node->children->isNotEmpty())
                                <form method="POST" action="{{ route('network.node-ports.assign-node', $port) }}" class="space-y-1.5">
                                    @csrf
                                    <select name="target_node_id" required class="w-full rounded-md border border-border bg-surface px-2 py-1 text-xs text-text-main">
                                        <option value="">Pilih anak langsung</option>
                                        @foreach($node->children as $child)
                                        <option value="{{ $child->id }}">{{ $child->code }} · {{ $child->name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="w-full rounded-md bg-sky-600 hover:bg-sky-700 text-white font-bold py-1.5 text-xs shadow-2xs transition-colors cursor-pointer">
                                        Hubungkan
                                    </button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('network.node-ports.update', $port) }}">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="is_used" value="1">
                                    <button type="submit" class="w-full rounded-md border border-border bg-surface hover:bg-surface-muted text-text-main font-semibold py-1 text-xs transition-colors cursor-pointer">
                                        Tandai Terpakai Manual
                                    </button>
                                </form>
                            @endif
                        @endif
                    </div>
                    @endcan
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Section 5: Edit Informasi & Lokasi Node --}}
        @can('network_nodes.update')
        <form method="POST" action="{{ route('network.nodes.update', $node) }}" class="p-6 space-y-5">
            @csrf
            @method('PATCH')

            <div class="flex items-center gap-2 border-b border-border pb-3">
                <span class="w-2 h-2 rounded-full bg-sky-600"></span>
                <h2 class="text-xs font-bold uppercase tracking-wider text-text-main">Edit Informasi &amp; Koordinat Node</h2>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                @if($node->isPosition())
                {{-- Kode & tipe Slot/PON/Port diturunkan dari induk; server
                     mengabaikan kiriman keduanya (NetworkNodeController::update). --}}
                <div class="text-xs font-semibold text-text-secondary">Kode Node
                    <p data-derived-code class="mt-1 rounded-lg border border-border bg-surface-muted px-3.5 py-2 text-text-main text-xs font-mono break-all">{{ $node->code }}</p>
                </div>
                <div class="text-xs font-semibold text-text-secondary">Tipe Node
                    <p class="mt-1 rounded-lg border border-border bg-surface-muted px-3.5 py-2 text-text-main text-xs">{{ $node->nodeType?->name }}</p>
                </div>
                <p class="sm:col-span-2 text-xs text-text-muted">Kode dan tipe Slot/PON/Port mengikuti perangkat induk dan tidak dapat diubah.</p>
                @else
                <label class="text-xs font-semibold text-text-secondary">Kode Node <span class="text-rose-500">*</span>
                    <input name="code" required maxlength="50" value="{{ old('code', $node->code) }}" class="mt-1 block w-full rounded-lg border border-border bg-surface px-3.5 py-2 text-text-main text-xs font-mono focus:border-primary focus:ring-1 focus:ring-primary/20 outline-none">
                </label>
                <label class="text-xs font-semibold text-text-secondary">Tipe Node <span class="text-rose-500">*</span>
                    <select name="node_type_id" required class="mt-1 block w-full rounded-lg border border-border bg-surface px-3.5 py-2 text-text-main text-xs focus:border-primary focus:ring-1 focus:ring-primary/20 outline-none cursor-pointer">
                        @foreach($nodeTypes as $type)
                        <option value="{{ $type->id }}" @selected((int) old('node_type_id', $node->node_type_id) === $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </label>
                <p class="sm:col-span-2 text-xs text-text-muted">Kode dan tipe hanya dapat diubah sebelum node memiliki port atau relasi lain. Mengganti kode OLT/Switch ikut mengganti kode Slot/PON/Port di bawahnya.</p>
                @endif
                <label class="text-xs font-semibold text-text-secondary">Nama Node <span class="text-rose-500">*</span>
                    <input name="name" required value="{{ old('name', $node->name) }}"
                           class="mt-1 block w-full rounded-lg border border-border bg-surface px-3.5 py-2 text-text-main text-xs focus:border-primary focus:ring-1 focus:ring-primary/20 outline-none">
                </label>

                <label class="text-xs font-semibold text-text-secondary">Status Operasional <span class="text-rose-500">*</span>
                    <select name="status" class="mt-1 block w-full rounded-lg border border-border bg-surface px-3.5 py-2 text-text-main text-xs focus:border-primary focus:ring-1 focus:ring-primary/20 outline-none cursor-pointer">
                        <option value="active" @selected($node->status === 'active')>Aktif</option>
                        <option value="inactive" @selected($node->status === 'inactive')>Nonaktif</option>
                    </select>
                </label>

                <label class="text-xs font-semibold text-text-secondary sm:col-span-2">Alamat Fisik / Titik Tiang
                    <textarea name="address" rows="2" class="mt-1 block w-full rounded-lg border border-border bg-surface px-3.5 py-2 text-text-main text-xs focus:border-primary focus:ring-1 focus:ring-primary/20 outline-none">{{ old('address', $node->address) }}</textarea>
                </label>

                @foreach(['village' => 'Desa/Kelurahan', 'district' => 'Kecamatan', 'city' => 'Kota/Kabupaten', 'latitude' => 'Latitude', 'longitude' => 'Longitude'] as $field => $label)
                <label class="text-xs font-semibold text-text-secondary">{{ $label }}
                    <input name="{{ $field }}" value="{{ old($field, $node->$field) }}"
                           class="mt-1 block w-full rounded-lg border border-border bg-surface px-3.5 py-2 text-text-main text-xs font-mono focus:border-primary focus:ring-1 focus:ring-primary/20 outline-none">
                </label>
                @endforeach
            </div>

            @if($errors->any())
            <div class="p-3 rounded-lg bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-900 text-xs font-semibold text-rose-600 dark:text-rose-400">
                {{ $errors->first() }}
            </div>
            @endif

            <div class="pt-4 border-t border-border flex items-center justify-end">
                <button type="submit" class="px-5 py-2 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold shadow-xs transition-colors cursor-pointer">
                    Simpan Perubahan Node
                </button>
            </div>
        </form>
        @endcan

    </div>

</div>
@endsection
