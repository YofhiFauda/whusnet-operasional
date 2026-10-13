@php
    $usedPorts = (int) ($treeNode->used_ports_count ?? 0);
    $totalPorts = (int) ($treeNode->ports_count ?? 0);
    $hasPorts = (bool) ($treeNode->nodeType?->has_ports);
    $isRed = $treeNode->isInRedZone();
    $typeCode = $treeNode->nodeType?->code ?? 'other';
@endphp

<details class="group/tree my-1.5 rounded-xl border border-border/80 bg-surface/90 hover:bg-surface transition-all shadow-2xs"
         data-tree-node-id="{{ $treeNode->id }}"
         data-tree-parent-id="{{ $treeNode->parent_id }}"
         :class="{ 'ring-2 ring-sky-500/50 border-sky-400 bg-sky-50/50 dark:bg-sky-950/30': selectedNodeId === {{ $treeNode->id }} }"
         open>
    <summary class="cursor-pointer px-3 py-2.5 flex items-center justify-between gap-2 text-xs select-none hover:text-primary transition-colors"
             @click="selectNode({{ $treeNode->id }})">
        <div class="flex items-center gap-2 min-w-0">
            {{-- Type Icon Pill --}}
            <span class="p-1 rounded-md shrink-0 flex items-center justify-center text-[10px] font-bold
                @if(str_contains($typeCode, 'odc')) bg-amber-100 text-amber-700 dark:bg-amber-950/70 dark:text-amber-300 border border-amber-300/60
                @elseif(str_contains($typeCode, 'odp')) bg-sky-100 text-sky-700 dark:bg-sky-950/70 dark:text-sky-300 border border-sky-300/60
                @elseif(str_contains($typeCode, 'router') || str_contains($typeCode, 'switch')) bg-purple-100 text-purple-700 dark:bg-purple-950/70 dark:text-purple-300 border border-purple-300/60
                @elseif(str_contains($typeCode, 'pon') || str_contains($typeCode, 'card')) bg-indigo-100 text-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-300 border border-indigo-300/60
                @else bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border border-border
                @endif"
                title="Tipe: {{ $treeNode->nodeType?->name }}">
                @if(str_contains($typeCode, 'odc'))
                    ODC
                @elseif(str_contains($typeCode, 'odp'))
                    ODP
                @elseif(str_contains($typeCode, 'router'))
                    RTR
                @elseif(str_contains($typeCode, 'switch'))
                    SW
                @elseif(str_contains($typeCode, 'pon'))
                    PON
                @else
                    NOD
                @endif
            </span>

            {{-- Code and Name (Required by test assertSee) --}}
            <div class="truncate text-text-main flex items-center gap-1.5 min-w-0">
                <span class="font-bold font-mono text-sky-600 dark:text-sky-400 shrink-0">{{ $treeNode->code }}</span>
                <span class="text-text-muted shrink-0">-</span>
                <span class="font-medium truncate text-text-main" title="{{ $treeNode->name }}">{{ $treeNode->name }}</span>
            </div>
            <span class="text-[10px] text-text-muted shrink-0">({{ $treeNode->nodeType?->name }})</span>
        </div>

        {{-- Right Port Status & Red Zone Pill --}}
        <div class="flex items-center gap-2 shrink-0">
            @if($hasPorts && $totalPorts > 0)
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-mono font-bold
                    @if($isRed)
                        bg-rose-100 text-rose-700 dark:bg-rose-950/80 dark:text-rose-300 border border-rose-300/70 animate-pulse
                    @else
                        bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border border-border
                    @endif">
                    <span class="w-1.5 h-1.5 rounded-full {{ $isRed ? 'bg-rose-500' : 'bg-emerald-500' }}"></span>
                    {{ $usedPorts }}/{{ $totalPorts }} port
                </span>
            @endif

            <svg class="w-3.5 h-3.5 text-text-muted transition-transform group-open/tree:rotate-90 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
        </div>
    </summary>

    @if($treeChildren->has($treeNode->id))
        <div class="mt-1 space-y-1.5 border-l-2 border-border/80 ml-5 pl-3 pb-2 pt-1">
            @foreach($treeChildren->get($treeNode->id) as $childNode)
                @include('network.nodes._tree-node', ['treeNode' => $childNode, 'treeChildren' => $treeChildren])
            @endforeach
        </div>
    @endif
</details>
