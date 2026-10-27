@php
    $hasChildren = $rootNodesByPop->has($treePop->id) || $topologyPopChildren->has($treePop->id);
    $popTypeLabel = match ($treePop->type) {
        'pusat' => 'Pusat / Server Utama',
        'cabang' => 'Cabang',
        default => 'Mini POP'
    };
@endphp

<div class="tree-line select-none my-0.5" x-data="{ isOpen: true }" @tree-toggle-all.window="isOpen = $event.detail.open">
    <div class="tree-node-item group flex items-center justify-between gap-2 px-2.5 py-1.5 rounded-lg cursor-pointer transition-colors hover:bg-surface-muted text-text-main">
        <div class="flex items-center gap-2 min-w-0 flex-1">
            {{-- Expand/Collapse Chevron --}}
            <button type="button" @click.stop="isOpen = !isOpen" class="w-5 h-5 rounded-md flex items-center justify-center text-text-muted hover:text-text-main hover:bg-surface-muted transition-colors shrink-0" title="Buka/Tutup">
                <svg class="w-3.5 h-3.5 transition-transform duration-150" :class="{ 'rotate-0': isOpen, '-rotate-90': !isOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            {{-- POP Type Badge --}}
            <span class="px-1.5 py-0.5 text-[10px] font-mono font-bold rounded border bg-sky-600 text-white border-sky-600 shrink-0">
                {{ $treePop->type === 'pusat' ? 'P' : ($treePop->type === 'cabang' ? 'C' : 'M') }}
            </span>

            {{-- Code & Name --}}
            <div class="min-w-0 flex-1 truncate text-left" title="{{ $treePop->name }} — {{ $popTypeLabel }}">
                <span class="text-xs font-semibold text-text-main font-mono">{{ $treePop->name }}</span>
                <span class="text-text-muted mx-1">-</span>
                <span class="text-xs text-text-muted font-medium">{{ $popTypeLabel }}</span>
            </div>
        </div>

        {{-- Right Side POP Detail Pill --}}
        <div class="flex items-center gap-1.5 shrink-0">
            @can('pops.view')
                <a href="{{ route('master.pop.show', $treePop) }}"
                   @click.stop
                   class="text-[10px] font-semibold text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/60 border border-sky-200 dark:border-sky-900 px-1.5 py-0.5 rounded opacity-90 group-hover:opacity-100 transition-colors hover:underline">
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
