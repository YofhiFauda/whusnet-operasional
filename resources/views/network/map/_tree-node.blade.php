@php
    $typeCode = $treeNode->nodeType?->code ?? 'other';
    $typeName = $treeNode->nodeType?->name ?? 'Node';
    $used = (int) ($treeNode->used_ports_count ?? 0);
    $total = (int) ($treeNode->ports_count ?? 0);
    $percent = $total > 0 ? round(100 * $used / $total) : null;
    $isRedZone = $treeNode->isInRedZone() || ($percent !== null && $percent >= 90);
    $children = $childrenByNode->get($treeNode->id, collect());
    $assetCount = count($assetsByNode[$treeNode->id] ?? []);

    $typePill = str_contains($typeCode, 'odp')
        ? 'bg-amber-50 text-amber-800 border-amber-300 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-700/60'
        : (str_contains($typeCode, 'odc')
            ? 'bg-yellow-50 text-yellow-800 border-yellow-300 dark:bg-yellow-950/40 dark:text-yellow-300 dark:border-yellow-700/60'
            : (str_contains($typeCode, 'olt') || str_contains($typeCode, 'pon')
                ? 'bg-emerald-50 text-emerald-800 border-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-700/60'
                : 'bg-sky-50 text-sky-800 border-sky-300 dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-700/60'));
@endphp

<details open x-show="visibleNode({{ $treeNode->id }})" x-cloak class="group/map-node relative pl-4 my-0.5">
    <summary class="list-none cursor-pointer flex items-center gap-1.5 py-1 px-1 rounded-md transition-colors before:absolute before:left-0 before:top-0 before:bottom-0 before:w-px before:bg-slate-300 dark:before:bg-slate-700">
        <span class="absolute left-0 top-1/2 w-4 border-t border-slate-300 dark:border-slate-700"></span>

        @if($children->isNotEmpty())
            <span class="shrink-0 size-4 flex items-center justify-center font-mono text-xs text-slate-400 group-open/map-node:rotate-90 transition-transform select-none">›</span>
        @else
            <span class="shrink-0 size-4"></span>
        @endif

        <div @click="selectNode({{ $treeNode->id }})"
             class="flex-1 min-w-0 flex items-center justify-between gap-2 px-2.5 py-1.5 rounded border transition-all cursor-pointer"
             :class="selectedNodeId === {{ $treeNode->id }} ? 'border-sky-500 bg-sky-50 shadow-xs ring-1 ring-sky-500/20 dark:border-sky-400 dark:bg-sky-950/40' : 'border-slate-200/80 bg-white hover:border-slate-300 hover:bg-slate-50/80 dark:border-slate-700/70 dark:bg-[#152126] dark:hover:bg-slate-800/60'">

            <div class="flex items-center gap-2 min-w-0">
                <span class="size-2 rounded-full shrink-0 {{ $treeNode->status === 'active' ? 'bg-emerald-500' : 'bg-slate-400' }}" title="Status: {{ $treeNode->status }}"></span>
                <span class="shrink-0 font-mono text-[10px] uppercase font-semibold px-1.5 py-0.5 rounded border {{ $typePill }}">
                    {{ strtoupper($typeCode) }}
                </span>
                <span class="font-mono text-xs font-semibold text-slate-900 dark:text-slate-100 shrink-0">{{ $treeNode->code }}</span>
                <span class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $treeNode->name }}</span>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                @if($total > 0)
                    <span class="font-mono text-[11px] tabular-nums font-medium {{ $isRedZone ? 'text-rose-600 dark:text-rose-400 font-bold' : ($percent >= 70 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-700 dark:text-emerald-400') }}">
                        {{ $used }}/{{ $total }}
                    </span>
                @endif
                @if($assetCount > 0)
                    <span class="font-mono text-[10px] text-sky-700 dark:text-sky-300 bg-sky-50 dark:bg-sky-950/50 px-1 py-0.5 rounded border border-sky-200 dark:border-sky-800">
                        {{ $assetCount }} aset
                    </span>
                @endif
            </div>
        </div>
    </summary>

    @if($children->isNotEmpty())
        <div class="ml-3 pl-1 border-l border-slate-200 dark:border-slate-700/80 space-y-0.5">
            @foreach($children as $childNode)
                @include('network.map._tree-node', ['treeNode' => $childNode])
            @endforeach
        </div>
    @endif
</details>
