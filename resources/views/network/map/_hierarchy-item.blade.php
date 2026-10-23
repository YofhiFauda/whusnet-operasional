@php
    $item = $hierarchy['items'][$hierarchyKey];
    $children = $hierarchy['children'][$hierarchyKey] ?? [];
    $isLocation = in_array($item['kind'], ['pop', 'mini_pop'], true);
    $selectedId = $isLocation ? -$item['id'] : ($item['kind'] === 'customer' ? -1000000000 - $item['id'] : $item['id']);
    $isPosition = in_array($item['kind'], ['slot', 'port'], true);
@endphp
@unless(in_array($item['type'], ['pon_port', 'olt_card', 'aggregation_switch', 'backbone', 'feeder', 'drop_cable'], true))
<div data-map-hierarchy-key="{{ $hierarchyKey }}" x-show="matchesHierarchyFilter(@js($hierarchyKey))" x-data="{ isOpen: {{ $item['type'] === 'odp' ? 'false' : 'true' }} }" class="space-y-1">
    <div class="flex flex-wrap items-center gap-2 rounded-md px-2 py-1.5 text-xs hover:bg-slate-100 dark:hover:bg-slate-800">
        <span class="rounded border border-slate-200 px-1 py-0.5 text-[10px] text-slate-500 dark:border-slate-700">{{ $item['type_label'] }}</span>
        @if($item['type'] === 'odp' && $children !== [])
            <button type="button" @click="isOpen = !isOpen" class="w-4 text-slate-500 hover:text-sky-700" title="Buka/Tutup pelanggan">
                <span x-text="isOpen ? '▾' : '▸'"></span>
            </button>
        @endif
        @if($isPosition)
            <a href="{{ $item['url'] }}" class="font-mono font-semibold text-sky-700 hover:underline dark:text-sky-300">{{ $item['label'] }}</a>
        @else
            <button type="button" @click="selectNode({{ $selectedId }}){{ $item['type'] === 'odp' ? '; isOpen = true' : '' }}" class="cursor-pointer text-left font-mono font-semibold text-slate-800 hover:text-sky-700 dark:text-slate-200">{{ $item['label'] }} @if($item['name']) · {{ $item['name'] }} @endif</button>
        @endif
        @if($item['capacity'])
            <span class="text-[10px] text-slate-500">{{ $item['capacity']['used'] }} / {{ $item['capacity']['capacity'] ?? '?' }} {{ $item['capacity']['unit_label'] }}</span>
        @endif
        @if($item['uplink_unverified'])<span class="text-[10px] text-amber-700 dark:text-amber-400">uplink belum terverifikasi</span>@endif
        @if($item['kind'] === 'customer' && $item['ont_label'])<span class="text-[10px] text-slate-500">{{ $item['ont_label'] }}</span>@endif
    </div>
    @if($children !== [])
        <div x-show="isOpen" class="ml-4 space-y-1 border-l border-slate-200 pl-3 dark:border-slate-700">
            @foreach($children as $childKey)
                @include('network.map._hierarchy-item', ['hierarchyKey' => $childKey])
            @endforeach
        </div>
    @endif
</div>
@endunless
