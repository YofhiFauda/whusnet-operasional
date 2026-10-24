{{-- Task 11/12: PON sekarang `kind === 'node'`, `type === 'olt_pon'` (lihat
catatan di `_slot-inspector.blade.php`). --}}
@foreach($hierarchy['items'] as $ponItem)
@if($ponItem['kind'] === 'node' && $ponItem['type'] === 'olt_pon')
@php
    $capacity = $ponItem['capacity'];
    $odps = collect($hierarchy['children'][$ponItem['key']] ?? [])->map(fn (string $key) => $hierarchy['items'][$key])->filter(fn (array $item) => $item['type'] === 'odp');
    $splitters = collect($ponItem['direct_assets'])->filter(fn (array $asset) => str_starts_with(strtoupper((string) ($asset['item_code'] ?? '')), 'SPL-') || str_contains(strtolower((string) ($asset['name'] ?? '')), 'splitter'));
@endphp
<div id="inspector-pon-{{ $ponItem['id'] }}" x-show="selectedPonId === {{ $ponItem['id'] }}" x-cloak class="space-y-4 sm:space-y-5 p-3.5 sm:p-6">
    <header class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 border-b border-border pb-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <button type="button" @click="activeMobileTab = 'tree'"
                        class="lg:hidden inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-surface border border-border text-xs font-semibold text-primary hover:text-sky-700 shadow-2xs cursor-pointer shrink-0">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                    <span>Pohon</span>
                </button>
                <p class="text-[10px] font-bold uppercase tracking-wider text-sky-700 dark:text-sky-300 font-mono">PON · {{ $ponItem['status_label'] }}</p>
            </div>
            <h2 class="text-lg sm:text-xl font-bold font-mono text-text-main">{{ $ponItem['label'] }}</h2>
            <p class="mt-1 text-xs text-text-muted font-mono truncate">{{ $ponItem['breadcrumb'] }}</p>
        </div>
        <a href="{{ $ponItem['url'] }}" class="inline-flex items-center justify-center rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-semibold text-sky-600 dark:text-sky-400 hover:bg-surface-muted transition-colors shadow-2xs w-full sm:w-auto">Kelola PON &rarr;</a>
    </header>
    <section class="rounded-lg border border-border bg-surface-muted/50 p-4">
        <h3 class="text-[10px] font-bold uppercase tracking-wider text-text-muted">Beban pelanggan PON</h3>
        <p class="mt-1.5 text-2xl font-bold font-mono text-text-main tabular-nums">{{ $capacity['used'] }} / {{ $capacity['capacity'] ?? '?' }} <span class="text-xs font-medium text-text-muted">pelanggan aktif</span></p>
        @if($capacity['capacity'] !== null && $capacity['capacity'] > 0)
            <div class="mt-2.5 h-2 overflow-hidden rounded-full bg-surface border border-border">
                <div class="h-full rounded-full bg-sky-600 dark:bg-sky-500 transition-all duration-300" style="width: {{ min(100, round($capacity['used'] / $capacity['capacity'] * 100)) }}%"></div>
            </div>
            <p class="mt-2 text-[11px] text-text-muted font-mono tabular-nums">{{ $capacity['remaining'] }} kapasitas tersisa</p>
        @else
            <p class="mt-2 text-[11px] text-text-muted">Batas pelanggan belum dicatat.</p>
        @endif
    </section>
    <section class="space-y-3">
        <div class="flex items-center justify-between gap-2">
            <h3 class="text-xs font-bold uppercase tracking-wider text-text-main">ODP dan splitter pada PON · {{ $odps->count() }} ODP</h3>
            <span class="text-[11px] text-text-muted font-mono">{{ $odps->count() }} terhubung</span>
        </div>
        @foreach($splitters as $splitter)
            <p class="rounded-lg border border-border bg-surface p-3 text-xs text-text-main">Splitter langsung: <span class="font-semibold">{{ $splitter['name'] }}</span> · <span class="font-mono">{{ $splitter['amount'] }} unit</span></p>
        @endforeach
        @forelse($odps as $odp)
            @php
                $customers = collect($hierarchy['children'][$odp['key']] ?? [])->map(fn (string $key) => $hierarchy['items'][$key])->filter(fn (array $item) => $item['kind'] === 'customer');
                $odpSplitters = collect($odp['direct_assets'])->filter(fn (array $asset) => str_starts_with(strtoupper((string) ($asset['item_code'] ?? '')), 'SPL-') || str_contains(strtolower((string) ($asset['name'] ?? '')), 'splitter'));
            @endphp
            <article class="rounded-lg border border-border bg-surface p-4 space-y-2 shadow-2xs">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <a href="{{ $odp['url'] }}" class="font-mono text-xs font-bold text-sky-600 dark:text-sky-400 hover:underline">{{ $odp['label'] }}</a>
                    {{-- Konsep "uplink terverifikasi" dihapus Task 9 — ODP->PON
                    murni `parent_id`, jadi tidak ada status verifikasi per ODP. --}}
                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full border bg-surface-muted text-text-secondary border-border">{{ $odp['status_label'] }}</span>
                </div>
                <p class="text-xs text-text-secondary font-mono">{{ $customers->count() }} pelanggan · {{ $odp['capacity']['used'] ?? 0 }} / {{ $odp['capacity']['capacity'] ?? '?' }} port ODP terpakai</p>
                @foreach($odpSplitters as $splitter)
                    <p class="text-xs text-text-main font-mono">{{ $splitter['name'] }}@if($splitter['reference']) · <span class="text-text-muted">{{ $splitter['reference'] }}</span>@endif</p>
                @endforeach
                @if($customers->isNotEmpty())
                    <div class="mt-2.5 space-y-1 bg-surface-muted/50 p-2.5 rounded-lg border border-border">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-text-muted">Pelanggan terhubung</p>
                        @foreach($customers as $customer)
                            <p class="text-xs text-text-main font-mono">{{ $customer['label'] }}@if($customer['name']) · <span class="font-sans text-text-secondary">{{ $customer['name'] }}</span>@endif</p>
                        @endforeach
                    </div>
                @endif
            </article>
        @empty
            <p class="rounded-lg border border-dashed border-border p-5 text-xs text-text-muted text-center">Belum ada ODP terhubung.</p>
        @endforelse
    </section>
</div>
@endif
@endforeach
