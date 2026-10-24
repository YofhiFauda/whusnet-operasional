{{-- Task 11/12: `$hierarchy['items']` tidak lagi punya `kind` terpisah
'slot'/'port' — Slot/PON/Port Switch sekarang `kind === 'node'` dengan
`type` = kode tipe node (`olt_slot`/`switch_slot`). Dicek lewat `type`,
bukan `kind`, supaya cocok dengan `InfrastructureHierarchyService::build()`
pasca Task 11. --}}
@foreach($hierarchy['items'] as $slotItem)
@if($slotItem['kind'] === 'node' && in_array($slotItem['type'], ['olt_slot', 'switch_slot'], true))
@php
    $isOltSlot = $slotItem['type'] === 'olt_slot';
    $portLabel = $isOltSlot ? 'PON' : 'port';
    $slotPorts = collect($hierarchy['children'][$slotItem['key']] ?? [])
        ->map(fn (string $key) => $hierarchy['items'][$key])
        ->filter(fn (array $item) => $item['kind'] === 'node' && in_array($item['type'], ['olt_pon', 'switch_port'], true));
    $slotCapacity = $slotItem['capacity'];
@endphp
<div id="inspector-slot-{{ $slotItem['id'] }}" x-show="selectedSlotId === {{ $slotItem['id'] }}" x-cloak class="space-y-4 sm:space-y-5 p-3.5 sm:p-6">
    <header class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 border-b border-border pb-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <button type="button" @click="activeMobileTab = 'tree'"
                        class="lg:hidden inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-surface border border-border text-xs font-semibold text-primary hover:text-sky-700 shadow-2xs cursor-pointer shrink-0">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                    <span>Pohon</span>
                </button>
                <p class="text-[10px] font-bold uppercase tracking-wider text-sky-700 dark:text-sky-300 font-mono">{{ $slotItem['type_label'] }} · {{ $slotItem['status_label'] }}</p>
            </div>
            <h2 class="mt-1 text-lg sm:text-xl font-bold font-mono text-text-main">{{ $slotItem['label'] }}@if($slotItem['name']) · {{ $slotItem['name'] }}@endif</h2>
            <p class="mt-1 text-xs text-text-muted font-mono truncate">{{ $slotItem['breadcrumb'] }}</p>
        </div>
        <a href="{{ $slotItem['url'] }}" class="inline-flex items-center justify-center rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-semibold text-sky-600 dark:text-sky-400 hover:bg-surface-muted transition-colors shadow-2xs w-full sm:w-auto">Kelola Slot &rarr;</a>
    </header>

    <section class="rounded-lg border border-border bg-surface-muted/50 p-4">
        <h3 class="text-[10px] font-bold uppercase tracking-wider text-text-muted">Alokasi {{ $portLabel }} pada slot</h3>
        <p class="mt-1.5 text-2xl font-bold font-mono text-text-main tabular-nums">{{ $slotCapacity['used'] }} / {{ $slotCapacity['capacity'] ?? '?' }} <span class="text-xs font-medium text-text-muted">{{ $portLabel }} terdaftar</span></p>
        @if($slotCapacity['capacity'] !== null && $slotCapacity['capacity'] > 0)
            <div class="mt-2.5 h-2 overflow-hidden rounded-full bg-surface border border-border">
                <div class="h-full rounded-full bg-sky-600 dark:bg-sky-500 transition-all duration-300" style="width: {{ min(100, round($slotCapacity['used'] / $slotCapacity['capacity'] * 100)) }}%"></div>
            </div>
            <p class="mt-2 text-[11px] text-text-muted font-mono tabular-nums">{{ max(0, $slotCapacity['capacity'] - $slotCapacity['used']) }} posisi {{ $portLabel }} tersisa</p>
        @else
            <p class="mt-2 text-[11px] text-text-muted">Batas {{ $portLabel }} per slot belum dicatat.</p>
        @endif
    </section>

    <section class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-xs font-bold uppercase tracking-wider text-text-main">{{ $isOltSlot ? 'Kartu dan aset terpasang' : 'Aset terpasang' }}</h3>
            <span class="text-[11px] text-text-muted">{{ count($slotItem['direct_assets']) }} jenis aset tercatat</span>
        </div>
        @forelse($slotItem['direct_assets'] as $asset)
            <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border bg-surface p-3 text-xs">
                <span class="font-semibold text-text-main">{{ $asset['name'] ?? 'Aset tanpa nama' }}</span>
                <span class="text-text-secondary font-mono">{{ $asset['amount'] }} unit
                    @can('warehouse_assets.view')
                        @if($asset['reference']) · <span class="text-text-muted">{{ $asset['reference'] }}</span>@endif
                    @endcan
                </span>
            </div>
        @empty
            <p class="rounded-lg border border-dashed border-border p-3 text-xs text-text-muted">Belum ada kartu atau aset yang tercatat pada slot ini.</p>
        @endforelse
    </section>

    <section class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-xs font-bold uppercase tracking-wider text-text-main">Daftar {{ $portLabel }} pada {{ $slotItem['label'] }}</h3>
            <span class="text-[11px] text-text-muted font-mono">{{ $slotPorts->count() }} {{ $portLabel }} tercatat</span>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            @forelse($slotPorts as $portItem)
                @php
                    $portOdps = $isOltSlot
                        ? collect($hierarchy['children'][$portItem['key']] ?? [])
                            ->map(fn (string $key) => $hierarchy['items'][$key])
                            ->filter(fn (array $item) => $item['type'] === 'odp')
                        : collect();
                @endphp
                <article class="rounded-lg border border-border bg-surface p-3.5 space-y-2 shadow-2xs">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="font-mono text-xs font-bold text-text-main">{{ $portItem['label'] }}</p>
                            <p class="mt-0.5 text-[11px] text-text-muted">{{ $portItem['status_label'] }}</p>
                        </div>
                        @if($isOltSlot)
                            <span class="rounded-full border border-border bg-surface-muted px-2 py-0.5 text-[10px] font-mono font-semibold text-text-secondary tabular-nums">{{ $portItem['capacity']['used'] }} / {{ $portItem['capacity']['capacity'] ?? '?' }} aktif</span>
                        @endif
                    </div>
                    @if($isOltSlot)
                        <p class="text-xs text-text-secondary font-mono">{{ $portOdps->count() }} ODP terhubung</p>
                        @if($portOdps->isNotEmpty())
                            <p class="text-[11px] text-text-muted font-mono truncate">{{ $portOdps->pluck('label')->implode(', ') }}</p>
                        @endif
                        <div class="pt-1">
                            <button type="button" @click="selectPon({{ $portItem['id'] }})" class="text-xs font-semibold text-sky-600 dark:text-sky-400 hover:text-sky-700 cursor-pointer transition-colors">Lihat rincian {{ $portItem['label'] }} &rarr;</button>
                        </div>
                    @else
                        @if($portItem['connection_label'])<p class="text-xs text-text-secondary">Tujuan: <span class="font-semibold">{{ $portItem['connection_label'] }}</span></p>@endif
                        <div class="pt-1">
                            <a href="{{ $portItem['url'] }}" class="inline-block text-xs font-semibold text-sky-600 dark:text-sky-400 hover:text-sky-700 transition-colors">Kelola port &rarr;</a>
                        </div>
                    @endif
                </article>
            @empty
                <p class="col-span-full rounded-lg border border-dashed border-border p-5 text-xs text-text-muted text-center">Belum ada {{ $portLabel }} tercatat pada slot ini.</p>
            @endforelse
        </div>
    </section>
</div>
@endif
@endforeach
