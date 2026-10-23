@php
    $item = $hierarchy['items'][$hierarchyKey];
    $childKeys = $hierarchy['children'][$hierarchyKey] ?? [];
    $isNode = $item['kind'] === 'node';
    $badge = match (true) {
        $item['kind'] === 'pop' => $item['type'] === 'pop_pusat' ? 'P' : 'C',
        $item['kind'] === 'mini_pop' => 'M',
        $item['kind'] === 'slot' => 'SLOT',
        $item['kind'] === 'port' => $item['type'] === 'pon' ? 'PON' : 'PORT',
        $item['kind'] === 'customer' => 'ONT',
        $item['type'] === 'server' => 'SRV',
        $item['type'] === 'olt_chassis' => 'OLT',
        $item['type'] === 'switch_mini_pop' => 'SW',
        $item['type'] === 'odp' => 'ODP',
        default => strtoupper(substr($item['type'], 0, 3)),
    };
    $capacity = $item['capacity'];
    $fullName = $item['name'] ? ($item['name'] !== $item['label'] ? "{$item['name']} ({$item['label']})" : $item['name']) : $item['label'];
    $itemTooltip = $fullName . ($item['breadcrumb'] ? " · {$item['breadcrumb']}" : '');
@endphp
@unless(in_array($item['type'], ['pon_port', 'olt_card', 'aggregation_switch', 'backbone', 'feeder', 'drop_cable'], true))
<div class="tree-line select-none my-0.5"
     data-hierarchy-key="{{ $hierarchyKey }}"
     x-show="matchesHierarchyFilter(@js($hierarchyKey))"
     @if($isNode)
         data-tree-node-id="{{ $item['id'] }}"
         data-node-seen-url="{{ route('network.nodes.seen', $item['id']) }}"
         @if(str_starts_with((string) $item['parent'], 'node:')) data-tree-parent-id="{{ substr($item['parent'], 5) }}" @endif
     @endif
     x-data="{ isOpen: {{ $item['type'] === 'odp' ? 'false' : 'true' }} }"
     @tree-toggle-all.window="isOpen = $event.detail.open">
    <div class="tree-node-item group flex items-center justify-between gap-1.5 sm:gap-2 rounded-lg border border-transparent px-2 py-1.5 transition-colors hover:bg-surface-muted {{ $isNode || $item['type'] === 'pon' || $item['kind'] === 'slot' ? 'cursor-pointer' : '' }}"
         title="{{ $itemTooltip }}"
         @if($isNode) @click="selectNode({{ $item['id'] }}){{ $item['type'] === 'odp' ? '; isOpen = true' : '' }}" :class="{ 'bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border-l-2 border-l-sky-600 font-semibold shadow-2xs': selectedNodeId === {{ $item['id'] }} && selectedPonId === null && selectedSlotId === null }"
         @elseif($item['kind'] === 'slot') @click="selectSlot({{ $item['id'] }})" :class="{ 'bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border-l-2 border-l-sky-600 font-semibold shadow-2xs': selectedSlotId === {{ $item['id'] }} }"
         @elseif($item['type'] === 'pon') @click="selectPon({{ $item['id'] }})" :class="{ 'bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border-l-2 border-l-sky-600 font-semibold shadow-2xs': selectedPonId === {{ $item['id'] }} }" @endif>
        <div class="flex min-w-0 flex-1 items-center gap-1.5 sm:gap-2">
            @if($childKeys !== [])
                <button type="button" @click.stop="isOpen = !isOpen" class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md text-text-muted hover:bg-surface-muted hover:text-text-main transition-colors cursor-pointer" title="Buka/Tutup Cabang">
                    <svg class="h-3.5 w-3.5 transition-transform duration-150" :class="{ '-rotate-90': !isOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                </button>
            @else
                <span class="flex h-5 w-5 shrink-0 items-center justify-center text-xs text-text-disabled">•</span>
            @endif
            <span class="shrink-0 rounded-md border px-1.5 py-0.5 font-mono text-[10px] font-bold {{ in_array($item['kind'], ['pop', 'mini_pop'], true) ? 'border-sky-600 bg-sky-600 text-white' : (in_array($item['type'], ['olt_chassis', 'server'], true) ? 'border-sky-200 dark:border-sky-800 bg-sky-100 dark:bg-sky-950/80 text-sky-700 dark:text-sky-300' : ($item['type'] === 'odp' ? 'border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300' : 'border-border bg-surface-muted text-text-secondary')) }}">{{ $badge }}</span>
            @if($item['relation'] === 'connection')
                <span class="shrink-0 text-[10px] {{ $item['verified'] ? 'text-emerald-600' : 'text-amber-600' }}" title="{{ $item['verified'] ? 'Koneksi PON terverifikasi' : 'Koneksi belum terverifikasi' }}">⇢</span>
            @endif
            <div class="min-w-0 flex-1 truncate text-left" title="{{ $itemTooltip }}">
                @if($item['type'] === 'odp')
                    <span class="font-mono text-xs font-semibold text-text-main" title="{{ $itemTooltip }}">{{ $item['name'] ?: $item['label'] }}</span>
                    @if($item['name'] && $item['name'] !== $item['label'])
                        <span class="mx-1 text-text-disabled">-</span><span class="font-mono text-xs text-text-muted" title="{{ $itemTooltip }}">{{ $item['label'] }}</span>
                    @endif
                @elseif($item['type'] === 'pon' || $item['kind'] === 'slot')
                    <span class="font-mono text-xs font-semibold text-text-main" title="{{ $itemTooltip }}">{{ $item['label'] }}</span>
                @elseif($item['url'] && ! $isNode)
                    <a href="{{ $item['url'] }}" class="font-mono text-xs font-semibold text-text-main hover:text-primary hover:underline" title="{{ $itemTooltip }}">{{ $item['label'] }}</a>
                @else
                    <span class="font-mono text-xs font-semibold text-text-main" title="{{ $itemTooltip }}">{{ $item['label'] }}</span>
                @endif
                @if($item['type'] !== 'odp' && $item['name'])
                    <span class="mx-1 text-text-disabled">-</span><span class="text-xs text-text-muted" title="{{ $itemTooltip }}">{{ $item['name'] }}</span>
                @endif
                @if(in_array($item['kind'], ['pop', 'mini_pop'], true) && !empty($item['type_label']))
                    <span class="mx-1 text-text-disabled">-</span><span class="text-xs text-text-muted">{{ $item['type_label'] }}</span>
                @endif
                @if($item['kind'] === 'customer')
                    <span class="ml-1 text-[10px] text-text-muted font-mono">Port {{ $item['port_number'] }} ·
                        @if($item['onts_hidden']) SN ONT tersembunyi
                        @elseif($item['ont_label']) {{ $item['ont_label'] }}
                        @else {{ collect($item['onts'])->pluck('serial_number')->implode(', ') }}
                        @endif
                    </span>
                @endif
                @if($item['uplink_unverified'] && $item['relation'] !== 'connection')
                    <span class="ml-1 text-[10px] text-amber-600 font-medium">uplink belum terverifikasi</span>
                @endif
                @if($item['kind'] === 'port' && $item['connection_label'])
                    <span class="ml-1 text-[10px] text-text-muted font-mono">→ {{ $item['connection_label'] }}</span>
                @endif
            </div>
        </div>
        <div class="flex shrink-0 items-center gap-1 sm:gap-1.5">
            @if($isNode && in_array($item['id'], $unseenNodeIds ?? [], true))
                <span data-unseen-badge class="shrink-0 rounded-full border border-sky-500/20 bg-sky-500/15 px-1.5 py-0.5 text-[10px] font-bold text-sky-600 dark:text-sky-400" title="Ada perangkat baru terpasang — buka node untuk menandai sudah dilihat">Baru</span>
            @endif
            @if($capacity)
                <span class="rounded-full border px-1.5 py-0.5 font-mono text-[10px] font-bold tabular-nums {{ ($capacity['red_zone'] ?? false) ? 'border-rose-300 bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-900' : 'border-border bg-surface-muted text-text-secondary' }}"
                      title="{{ ($capacity['capacity'] ?? null) === null ? 'kapasitas belum didata' : ($capacity['unit_label'] ?? '') }}">{{ $capacity['used'] }}/{{ $capacity['capacity'] ?? '?' }}<span class="hidden 2xl:inline"> {{ $capacity['unit_label'] ?? '' }}</span></span>
            @endif
        </div>
    </div>
    @if($childKeys !== [])
        <div x-show="isOpen" class="ml-2 sm:ml-3 space-y-0.5 pl-1 sm:pl-1.5 border-l border-border/50">
            @foreach($childKeys as $childKey)
                @include('network.nodes._hierarchy-item', ['hierarchyKey' => $childKey])
            @endforeach
        </div>
    @endif
</div>
@endunless
