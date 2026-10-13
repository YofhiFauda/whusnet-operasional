<details class="rounded-lg border border-border bg-surface p-2" data-tree-node-id="{{ $treeNode->id }}" data-tree-parent-id="{{ $treeNode->parent_id }}">
    <summary class="cursor-pointer text-sm text-text-main">
        <span class="font-semibold">{{ $treeNode->code }}</span> - {{ $treeNode->name }}
        <span class="text-xs text-text-muted">({{ $treeNode->nodeType?->name }})</span>
    </summary>
    @if($treeChildren->has($treeNode->id))
    <div class="mt-2 space-y-2 border-l border-border pl-4">
        @foreach($treeChildren->get($treeNode->id) as $childNode)
        @include('network.nodes._tree-node', ['treeNode' => $childNode, 'treeChildren' => $treeChildren])
        @endforeach
    </div>
    @endif
</details>
