@php
    $usedPorts = (int) ($treeNode->used_ports_count ?? 0);
    $totalPorts = (int) ($treeNode->ports_count ?? 0);
    $hasPorts = (bool) ($treeNode->nodeType?->has_ports);
    $isRed = $treeNode->isInRedZone();
    $typeCode = $treeNode->nodeType?->code ?? 'other';
@endphp

<details class="group/tree my-1 min-w-[16rem] rounded-lg border border-border/70 bg-surface hover:bg-surface-muted/40"
         data-tree-node-id="{{ $treeNode->id }}"
         data-tree-parent-id="{{ $treeNode->parent_id }}"
         :class="{ 'ring-2 ring-sky-500/50 border-sky-400 bg-sky-50/50 dark:bg-sky-950/30': selectedNodeId === {{ $treeNode->id }}, 'opacity-40': typeFilter !== 'all' && typeFilter !== '{{ $treeNode->node_type_id }}' }"
         open>
    <summary class="flex cursor-pointer select-none items-start gap-2 px-2 py-2 text-xs">
        <span class="mt-0.5 shrink-0 rounded border border-border bg-surface-muted px-1.5 py-0.5 text-[9px] font-black uppercase text-text-secondary">
            {{ match (true) {
                $typeCode === 'server' => 'SRV',
                $typeCode === 'backbone' => 'BB',
                $typeCode === 'aggregation_switch' => 'AGG',
                $typeCode === 'olt_chassis' => 'OLT',
                $typeCode === 'pon_port' => 'PON',
                $typeCode === 'feeder' => 'FDR',
                $typeCode === 'odp' => 'ODP',
                $typeCode === 'drop_cable' => 'DC',
                $typeCode === 'ont' => 'ONT',
                default => strtoupper(substr($typeCode, 0, 3)),
            } }}
        </span>
        <button type="button" @click.stop.prevent="selectNode({{ $treeNode->id }})"
                class="min-w-0 flex-1 text-left hover:text-sky-700 dark:hover:text-sky-300"
                title="Inspeksi {{ $treeNode->code }}">
            <span class="block break-all font-mono text-[11px] font-bold leading-4 text-sky-700 dark:text-sky-300">{{ $treeNode->code }}</span>
            <span class="block break-words text-[11px] leading-4 text-text-main">{{ $treeNode->name }}</span>
            <span class="block text-[10px] leading-4 text-text-muted">{{ $treeNode->nodeType?->name }}</span>
        </button>
        @if($hasPorts && $totalPorts > 0)
            <span class="mt-0.5 shrink-0 rounded-full px-1.5 py-0.5 text-[9px] font-bold {{ $isRed ? 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' }}">
                {{ $usedPorts }}/{{ $totalPorts }}
            </span>
        @endif
        <svg class="mt-1 h-3.5 w-3.5 shrink-0 text-text-muted transition-transform group-open/tree:rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
        </svg>
    </summary>

    @if($treeChildren->has($treeNode->id) || $uplinkPopChildren->has($treeNode->id))
        <div class="ml-1.5 space-y-1 border-l border-sky-200 pb-1 pl-1.5 dark:border-sky-900">
            @foreach($treeChildren->get($treeNode->id, collect()) as $childNode)
                @include('network.nodes._tree-node', ['treeNode' => $childNode, 'treeChildren' => $treeChildren])
            @endforeach
            @foreach($uplinkPopChildren->get($treeNode->id, collect()) as $childPop)
                @include('network.nodes._pop-tree', ['treePop' => $childPop])
            @endforeach
        </div>
    @endif
</details>
