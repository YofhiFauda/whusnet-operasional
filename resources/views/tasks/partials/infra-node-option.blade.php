<details class="rounded-lg border border-border bg-surface-muted p-2" data-infra-node-id="{{ $infraTreeNode->id }}" data-infra-parent-id="{{ $infraTreeNode->parent_id }}" open>
    <summary class="cursor-pointer text-xs font-semibold text-text-main">{{ $infraTreeNode->code }} - {{ $infraTreeNode->name }}</summary>
    <label class="mt-2 flex items-center gap-2 text-xs text-text-secondary">
        <input type="radio" name="infra_target_network_node_id" value="{{ $infraTreeNode->id }}"
               @checked((string) old('infra_target_network_node_id') === (string) $infraTreeNode->id)>
        Pilih titik ini
    </label>
    @if($infraNodeChildren->has($infraTreeNode->id))
    <div class="mt-2 space-y-2 border-l border-border pl-3">
        @foreach($infraNodeChildren->get($infraTreeNode->id) as $infraChildNode)
        @include('tasks.partials.infra-node-option', ['infraTreeNode' => $infraChildNode, 'infraNodeChildren' => $infraNodeChildren])
        @endforeach
    </div>
    @endif
</details>
