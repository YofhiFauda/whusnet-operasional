<details class="min-w-[16rem] rounded-lg border border-sky-200/70 bg-sky-50/30 p-2 dark:border-sky-900/40 dark:bg-sky-950/20" open>
    <summary class="flex cursor-pointer items-center justify-between gap-2 text-xs font-bold text-text-main">
        <span class="flex min-w-0 items-center gap-2">
            <span class="rounded bg-sky-600 px-1.5 py-1 text-[10px] font-black text-white">{{ $treePop->type === 'pusat' ? 'P' : ($treePop->type === 'cabang' ? 'C' : 'M') }}</span>
            <span class="truncate font-black text-sky-700 dark:text-sky-300">{{ $treePop->name }}</span>
            <span class="text-[10px] font-medium text-text-muted">{{ match ($treePop->type) {'pusat' => 'Pusat / Server Utama', 'cabang' => 'Cabang', default => 'Mini POP'} }}</span>
        </span>
        @can('pops.view')
            <a href="{{ route('master.pop.show', $treePop) }}" @click.stop class="shrink-0 text-[10px] text-sky-700 hover:underline dark:text-sky-300">Detail POP</a>
        @endcan
    </summary>
    <div class="ml-1.5 mt-1.5 space-y-1 border-l border-sky-300/60 pl-1.5 dark:border-sky-800/60">
        @foreach($rootNodesByPop->get($treePop->id, collect()) as $treeNode)
            @include('network.nodes._tree-node', ['treeNode' => $treeNode, 'treeChildren' => $treeChildren])
        @endforeach
        @foreach($topologyPopChildren->get($treePop->id, collect()) as $childPop)
            @include('network.nodes._pop-tree', ['treePop' => $childPop])
        @endforeach
    </div>
</details>
