<details open x-show="visibleNode(-{{ $treePop->id }})" x-cloak
         class="rounded-lg border border-slate-200/90 bg-white p-3.5 shadow-2xs dark:border-slate-800 dark:bg-[#131d24]">
    <summary class="font-display flex cursor-pointer list-none items-center justify-between gap-3 px-1 py-1 text-sm font-semibold text-slate-900 dark:text-slate-100">
        <span class="flex min-w-0 items-center gap-2.5">
            <span class="inline-flex size-6 shrink-0 items-center justify-center rounded bg-teal-700 text-[10px] font-bold text-white">{{ $treePop->type === 'pusat' ? 'P' : ($treePop->type === 'cabang' ? 'C' : 'M') }}</span>
            <span class="truncate font-bold">{{ $treePop->name }}</span>
            <span class="text-[10px] font-medium text-slate-500">{{ match ($treePop->type) {'pusat' => 'Pusat / Server Utama', 'cabang' => 'Cabang', default => 'Mini POP'} }}</span>
        </span>
        <button type="button" @click.stop="selectNode(-{{ $treePop->id }})"
                class="rounded border border-slate-300 px-2 py-1 text-[10px] text-sky-700 hover:bg-sky-50 dark:border-slate-700 dark:text-sky-300">Detail</button>
    </summary>

    <div class="ml-3 mt-2 space-y-2 border-l border-teal-200 pl-3 dark:border-teal-900">
        @foreach($rootsByPop->get($treePop->id, collect()) as $treeNode)
            @include('network.map._tree-node')
        @endforeach
        @foreach($topologyPopChildren->get($treePop->id, collect()) as $childPop)
            @include('network.map._pop-tree', ['treePop' => $childPop])
        @endforeach
    </div>
</details>
