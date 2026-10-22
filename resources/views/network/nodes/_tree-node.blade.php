@php
    $usedPorts = (int) ($treeNode->used_ports_count ?? 0);
    $totalPorts = (int) ($treeNode->ports_count ?? 0);
    $hasPorts = (bool) ($treeNode->nodeType?->has_ports);
    $isRed = $treeNode->isInRedZone();
    $typeCode = $treeNode->nodeType?->code ?? 'other';
    $hasChildren = $treeChildren->has($treeNode->id) || $uplinkPopChildren->has($treeNode->id);
@endphp

<div class="tree-line select-none my-0.5"
     data-tree-node-id="{{ $treeNode->id }}"
     data-tree-parent-id="{{ $treeNode->parent_id }}"
     x-show="matchesTreeFilter({{ $treeNode->id }}, '{{ $treeNode->node_type_id }}', @js(strtolower($treeNode->code . ' ' . $treeNode->name)))"
     x-data="{ isOpen: true }"
     @tree-toggle-all.window="isOpen = $event.detail.open">
    <div class="tree-node-item group flex items-center justify-between gap-2 px-2.5 py-1.5 rounded-lg cursor-pointer transition-colors hover:bg-surface-muted"
         :class="{
             'bg-primary-soft text-primary-hover border-l-2 border-primary font-semibold shadow-2xs': selectedNodeId === {{ $treeNode->id }},
             'text-text-main border-l-2 border-transparent': selectedNodeId !== {{ $treeNode->id }}
         }"
         @click="selectNode({{ $treeNode->id }})">
        <div class="flex items-center gap-2 min-w-0 flex-1">
            {{-- Expand/Collapse Chevron --}}
            @if($hasChildren)
                <button type="button" @click.stop="isOpen = !isOpen" class="w-5 h-5 rounded-md flex items-center justify-center text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-200/60 dark:hover:bg-slate-700 transition shrink-0" title="Buka/Tutup">
                    <svg class="w-3.5 h-3.5 transition-transform duration-150" :class="{ 'rotate-0': isOpen, '-rotate-90': !isOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
            @else
                <span class="w-5 h-5 shrink-0 flex items-center justify-center text-slate-300 dark:text-slate-600 text-xs">•</span>
            @endif

            {{-- Type Badge matching master-node-whusnet.html --}}
            <span class="px-1.5 py-0.5 text-[10px] font-mono font-bold rounded border shrink-0
                @if(str_contains($typeCode, 'odp')) bg-emerald-50 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800
                @elseif(str_contains($typeCode, 'feeder')) bg-sky-50 text-sky-700 dark:bg-sky-950/70 dark:text-sky-300 border-sky-200 dark:border-sky-800
                @elseif(str_contains($typeCode, 'olt') || str_contains($typeCode, 'pon')) bg-amber-50 text-amber-800 dark:bg-amber-950/70 dark:text-amber-300 border-amber-200 dark:border-amber-800
                @elseif(str_contains($typeCode, 'aggregation')) bg-purple-50 text-purple-700 dark:bg-purple-950/70 dark:text-purple-300 border-purple-200 dark:border-purple-800
                @elseif(str_contains($typeCode, 'backbone')) bg-indigo-50 text-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800
                @else bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-300 dark:border-slate-700
                @endif"
                title="Tipe: {{ $treeNode->nodeType?->name }}">
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

            {{-- Code & Name --}}
            <div class="min-w-0 flex-1 truncate text-left" title="{{ $treeNode->code }} — {{ $treeNode->name }} ({{ $treeNode->nodeType?->name }})">
                <span class="text-xs font-semibold text-slate-800 dark:text-slate-200 font-mono">{{ $treeNode->code }}</span>
                <span class="text-slate-300 dark:text-slate-600 mx-1">-</span>
                <span class="text-xs text-slate-500 dark:text-slate-400 font-medium truncate">{{ $treeNode->name }}</span>
            </div>
        </div>

        {{-- Right Side Status Pill --}}
        <div class="flex items-center gap-1.5 shrink-0">
            @if($hasPorts && $totalPorts > 0)
                <span class="inline-flex items-center gap-1 text-[10px] font-mono font-bold px-1.5 py-0.5 rounded-full border shrink-0
                    @if($isRed)
                        bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300 border-rose-300/80 animate-pulse
                    @else
                        bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700
                    @endif">
                    <span class="w-1.5 h-1.5 rounded-full {{ $isRed ? 'bg-rose-500' : 'bg-emerald-500' }}"></span>
                    {{ $usedPorts }}/{{ $totalPorts }}
                </span>
            @endif
        </div>
    </div>

    {{-- Children Container with tree-line indentation --}}
    @if($hasChildren)
        <div x-show="isOpen" class="ml-4 pl-2 space-y-0.5">
            @foreach($treeChildren->get($treeNode->id, collect()) as $childNode)
                @include('network.nodes._tree-node', ['treeNode' => $childNode, 'treeChildren' => $treeChildren])
            @endforeach
            @foreach($uplinkPopChildren->get($treeNode->id, collect()) as $childPop)
                @include('network.nodes._pop-tree', ['treePop' => $childPop])
            @endforeach
        </div>
    @endif
</div>
