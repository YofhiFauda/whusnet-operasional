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
@endphp
@unless(in_array($item['type'], ['pon_port', 'olt_card', 'aggregation_switch', 'backbone', 'feeder', 'drop_cable'], true))
<div class="tree-line select-none my-0.5"
     data-hierarchy-key="{{ $hierarchyKey }}"
     x-show="matchesHierarchyFilter(@js($hierarchyKey))"
     @if($isNode)
         data-tree-node-id="{{ $item['id'] }}"
         @if(str_starts_with((string) $item['parent'], 'node:')) data-tree-parent-id="{{ substr($item['parent'], 5) }}" @endif
     @endif
     x-data="{ isOpen: true }"
     @tree-toggle-all.window="isOpen = $event.detail.open">
    <div class="tree-node-item group flex items-center justify-between gap-2 rounded-xl border border-transparent px-2.5 py-1.5 transition-all hover:bg-slate-100/80 dark:hover:bg-slate-800/60 {{ $isNode ? 'cursor-pointer' : '' }}"
         @if($isNode) @click="selectNode({{ $item['id'] }})" :class="{ 'bg-blue-50 dark:bg-blue-950/50 border-blue-200 dark:border-blue-800 font-semibold': selectedNodeId === {{ $item['id'] }} }" @endif>
        <div class="flex min-w-0 flex-1 items-center gap-2">
            @if($childKeys !== [])
                <button type="button" @click.stop="isOpen = !isOpen" class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md text-slate-400 hover:bg-slate-200/60 hover:text-slate-700" title="Buka/Tutup">
                    <svg class="h-3.5 w-3.5 transition-transform duration-150" :class="{ '-rotate-90': !isOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                </button>
            @else
                <span class="flex h-5 w-5 shrink-0 items-center justify-center text-xs text-slate-300">•</span>
            @endif
            <span class="shrink-0 rounded border px-1.5 py-0.5 font-mono text-[10px] font-bold {{ in_array($item['kind'], ['pop', 'mini_pop'], true) ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-300 bg-slate-100 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300' }}">{{ $badge }}</span>
            @if($item['relation'] === 'connection')
                <span class="shrink-0 text-[10px] {{ $item['verified'] ? 'text-emerald-600' : 'text-amber-600' }}" title="{{ $item['verified'] ? 'Koneksi PON terverifikasi' : 'Koneksi belum terverifikasi' }}">⇢</span>
            @endif
            <div class="min-w-0 flex-1 truncate text-left" title="{{ $item['breadcrumb'] }}">
                @if($item['url'] && ! $isNode)
                    <a href="{{ $item['url'] }}" class="font-mono text-xs font-semibold text-slate-800 hover:underline dark:text-slate-200">{{ $item['label'] }}</a>
                @else
                    <span class="font-mono text-xs font-semibold text-slate-800 dark:text-slate-200">{{ $item['label'] }}</span>
                @endif
                @if($item['name'])<span class="mx-1 text-slate-300">-</span><span class="text-xs text-slate-500">{{ $item['name'] }}</span>@endif
                @if($item['kind'] === 'customer')
                    <span class="ml-1 text-[10px] text-slate-500">Port {{ $item['port_number'] }} ·
                        @if($item['onts_hidden']) SN ONT tersembunyi
                        @elseif($item['ont_label']) {{ $item['ont_label'] }}
                        @else {{ collect($item['onts'])->pluck('serial_number')->implode(', ') }}
                        @endif
                    </span>
                @endif
                @if($item['uplink_unverified'] && $item['relation'] !== 'connection')
                    <span class="ml-1 text-[10px] text-amber-600">uplink belum terverifikasi</span>
                @endif
                @if($item['kind'] === 'port' && $item['connection_label'])
                    <span class="ml-1 text-[10px] text-slate-500">→ {{ $item['connection_label'] }}</span>
                @endif
            </div>
        </div>
        <div class="flex shrink-0 items-center gap-1.5">
            @if($item['direct_assets'] !== [])
                <span class="rounded-full border border-sky-200 bg-sky-50 px-1.5 py-0.5 text-[10px] font-semibold text-sky-700" title="Aset terpasang langsung">{{ count($item['direct_assets']) }} aset</span>
            @endif
            @if($capacity)
                <span class="rounded-full border px-1.5 py-0.5 font-mono text-[10px] font-bold {{ ($capacity['red_zone'] ?? false) ? 'border-rose-300 bg-rose-100 text-rose-700' : 'border-slate-200 bg-slate-100 text-slate-700' }}"
                      title="{{ ($capacity['capacity'] ?? null) === null ? 'kapasitas belum didata' : ($capacity['unit_label'] ?? '') }}">{{ $capacity['used'] }} / {{ $capacity['capacity'] ?? '?' }} {{ $capacity['unit_label'] ?? '' }}</span>
            @endif
        </div>
    </div>
    @if($childKeys !== [])
        <div x-show="isOpen" class="ml-4 space-y-0.5 pl-2">
            @foreach($childKeys as $childKey)
                @include('network.nodes._hierarchy-item', ['hierarchyKey' => $childKey])
            @endforeach
        </div>
    @endif
</div>
@endunless
