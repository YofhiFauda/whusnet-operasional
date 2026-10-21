@php
    $hasChildren = $rootNodesByPop->has($treePop->id) || $topologyPopChildren->has($treePop->id);
    $popTypeLabel = match ($treePop->type) {
        'pusat' => 'Pusat / Server Utama',
        'cabang' => 'Cabang',
        default => 'Mini POP'
    };
@endphp

<div class="tree-line select-none my-0.5" x-data="{ isOpen: true }" @tree-toggle-all.window="isOpen = $event.detail.open">
    <div class="tree-node-item group flex items-center justify-between gap-2 px-2.5 py-1.5 rounded-xl cursor-pointer transition-all hover:bg-slate-100/80 dark:hover:bg-slate-800/60 text-slate-700 dark:text-slate-200">
        <div class="flex items-center gap-2 min-w-0 flex-1">
            {{-- Expand/Collapse Chevron --}}
            <button type="button" @click.stop="isOpen = !isOpen" class="w-5 h-5 rounded-md flex items-center justify-center text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-200/60 dark:hover:bg-slate-700 transition shrink-0" title="Buka/Tutup">
                <svg class="w-3.5 h-3.5 transition-transform duration-150" :class="{ 'rotate-0': isOpen, '-rotate-90': !isOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            {{-- POP Type Badge --}}
            <span class="px-1.5 py-0.5 text-[10px] font-mono font-bold rounded border bg-blue-600 text-white border-blue-600 shrink-0">
                {{ $treePop->type === 'pusat' ? 'P' : ($treePop->type === 'cabang' ? 'C' : 'M') }}
            </span>

            {{-- Code & Name --}}
            <div class="min-w-0 flex-1 truncate text-left" title="{{ $treePop->name }} — {{ $popTypeLabel }}">
                <span class="text-xs font-semibold text-slate-800 dark:text-slate-200 font-mono">{{ $treePop->name }}</span>
                <span class="text-slate-300 dark:text-slate-600 mx-1">-</span>
                <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">{{ $popTypeLabel }}</span>
            </div>
        </div>

        {{-- Right Side POP Detail Pill --}}
        <div class="flex items-center gap-1.5 shrink-0">
            @can('pops.view')
                <a href="{{ route('master.pop.show', $treePop) }}"
                   @click.stop
                   class="text-[10px] font-semibold text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/60 px-1.5 py-0.5 rounded opacity-85 group-hover:opacity-100 transition hover:underline">
                    Detail POP
                </a>
            @endcan
        </div>
    </div>

    {{-- Children Container --}}
    @if($hasChildren)
        <div x-show="isOpen" class="ml-4 pl-2 space-y-0.5">
            @foreach($rootNodesByPop->get($treePop->id, collect()) as $treeNode)
                @include('network.nodes._tree-node', ['treeNode' => $treeNode, 'treeChildren' => $treeChildren])
            @endforeach
            @foreach($topologyPopChildren->get($treePop->id, collect()) as $childPop)
                @include('network.nodes._pop-tree', ['treePop' => $childPop])
            @endforeach
        </div>
    @endif
</div>
