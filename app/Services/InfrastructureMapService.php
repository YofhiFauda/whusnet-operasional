<?php

namespace App\Services;

use App\Enums\InventoryTransactionType;
use App\Enums\SerialStatus;
use App\Models\InventoryTransaction;
use App\Models\NetworkNode;
use App\Models\OltSlot;
use App\Models\Pop;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InfrastructureMapService
{
    public function __construct(private readonly EffectiveAccessService $access) {}

    /**
     * @return array<string, mixed>
     */
    public function build(User $user, ?int $selectedPopId, string $sort): array
    {
        $cabangs = Pop::forUser($user)
            ->where('type', 'cabang')
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        $popIds = $this->access->hasAllPopAccess($user)
            ? null
            : $this->access->getAllowedPopIds($user);

        if ($selectedPopId !== null) {
            $selected = $cabangs->contains('id', $selectedPopId)
                ? Pop::query()->where('id', $selectedPopId)
                    ->orWhere('parent_id', $selectedPopId)
                    ->pluck('id')->map(fn ($id): int => (int) $id)->all()
                : [];

            $popIds = $popIds === null
                ? $selected
                : array_values(array_intersect($popIds, $selected));
        }

        $nodes = NetworkNode::query()
            ->when($popIds !== null, fn ($query) => $query->whereIn('network_nodes.id', $this->scopedNodeIds($popIds)))
            ->with([
                'nodeType',
                'anchorPop.parent',
                'ports.activeAssignment.customer.customerTechnicalDetail',
                'ports.activeAssignment.customer.inventorySerials' => fn ($query) => $query
                    ->where('status', SerialStatus::INSTALLED->value)
                    ->with('item'),
                'ports.activeAssignment.customer.internetPackage',
                'ports.activeAssignment.targetNode',
                'deployedSerials' => fn ($query) => $query
                    ->where('status', SerialStatus::DEPLOYED->value)
                    ->with('item'),
                'infrastructureBalances' => fn ($query) => $query
                    ->where('qty', '>', 0)
                    ->with('item'),
            ])
            ->withCount([
                'ports',
                'ports as used_ports_count' => fn ($query) => $query->where('is_used', true),
            ])
            ->orderBy('code')
            ->get();

        $nodeIds = $nodes->pluck('id')->all();
        $serialIds = $nodes->flatMap(fn (NetworkNode $node) => $node->deployedSerials->pluck('id'))->all();
        $serialEvents = collect();
        $quantityEvents = collect();

        if ($serialIds !== []) {
            $latest = InventoryTransaction::query()
                ->selectRaw('MAX(id)')
                ->where('type', InventoryTransactionType::DEPLOY->value)
                ->whereIn('serial_id', $serialIds)
                ->groupBy('serial_id');

            $serialEvents = InventoryTransaction::query()
                ->whereIn('id', $latest)
                ->with('fromTechnician', 'fopTask')
                ->get()
                ->keyBy('serial_id');
        }

        if ($nodeIds !== []) {
            $latest = InventoryTransaction::query()
                ->selectRaw('MAX(id)')
                ->where('type', InventoryTransactionType::DEPLOY->value)
                ->whereIn('to_network_node_id', $nodeIds)
                ->whereNull('serial_id')
                ->groupBy('to_network_node_id', 'item_id', 'lot_no');

            $quantityEvents = InventoryTransaction::query()
                ->whereIn('id', $latest)
                ->with('fromTechnician', 'fopTask')
                ->get()
                ->keyBy(fn (InventoryTransaction $event): string => $this->balanceKey(
                    $event->to_network_node_id,
                    $event->item_id,
                    $event->lot_no
                ));
        }

        $assets = [];
        $paths = [];
        $nodeIndex = [];
        $nodesById = $nodes->keyBy('id');

        foreach ($nodes as $node) {
            $rows = [];

            foreach ($node->deployedSerials as $serial) {
                $event = $serialEvents->get($serial->id);
                if ($event?->to_network_node_id !== $node->id) {
                    $event = null;
                }

                $rows[] = [
                    'kind' => 'SN',
                    'name' => $serial->item?->name ?? 'Barang tanpa nama',
                    'reference' => $serial->serial_number,
                    'amount' => '1 '.$serial->item?->unit,
                    'installed_at' => $event?->created_at?->format('d M Y H:i'),
                    'technician' => $event?->fromTechnician?->name,
                    'task' => $event?->fopTask?->task_number,
                    'trace_url' => route('warehouse.traceability.index', ['sn' => $serial->serial_number]),
                ];
            }

            foreach ($node->infrastructureBalances as $balance) {
                $event = $quantityEvents->get($this->balanceKey(
                    $node->id,
                    $balance->item_id,
                    $balance->lot_no
                ));
                $lot = $balance->lot_no ?: null;

                $rows[] = [
                    'kind' => $lot ? 'ROLL' : 'QTY',
                    'name' => $balance->item?->name ?? 'Barang tanpa nama',
                    'reference' => $lot,
                    'amount' => rtrim(rtrim(number_format((float) $balance->qty, 2, '.', ''), '0'), '.').' '.$balance->item?->unit,
                    'installed_at' => $event?->created_at?->format('d M Y H:i'),
                    'technician' => $event?->fromTechnician?->name,
                    'task' => $event?->fopTask?->task_number,
                    'trace_url' => $lot ? route('warehouse.traceability.index', ['roll' => $lot]) : null,
                ];
            }

            $assets[$node->id] = $rows;
            $paths[$node->id] = $this->pathFor($node, $nodesById, $paths);
            $nodeIndex[$node->id] = [
                'parent' => $node->parent_id,
                'type' => $node->nodeType?->code ?? '',
                'search' => mb_strtolower(implode(' ', array_filter([
                    $node->code,
                    $node->name,
                    $node->nodeType?->name,
                    $paths[$node->id],
                    ...array_map(fn (array $row): string => $row['name'].' '.($row['reference'] ?? ''), $rows),
                ]))),
            ];
        }

        $inventoryNodes = match ($sort) {
            'utilization' => $nodes->sortByDesc(fn (NetworkNode $node): float => $node->ports_count > 0
                ? $node->used_ports_count / $node->ports_count
                : -1)->values(),
            'free' => $nodes->sortByDesc(fn (NetworkNode $node): int => max(0, $node->ports_count - $node->used_ports_count))->values(),
            default => $nodes->sortBy(fn (NetworkNode $node): string => $paths[$node->id])->values(),
        };

        $miniPopIds = $selectedPopId !== null && ! $cabangs->contains('id', $selectedPopId)
            ? []
            : Pop::forUser($user)->where('type', 'mini_pop')
                ->when($selectedPopId !== null, fn ($query) => $query->where('parent_id', $selectedPopId))
                ->pluck('id')->all();

        // Slot OLT kini dimiliki node chassis (`olt_slots.chassis_node_id`),
        // bukan POP langsung — satu Mini POP bisa punya beberapa chassis.
        // Buat tetap bisa menampilkan rekap per Mini POP, cari dulu node
        // chassis yang ditambatkan ke tiap Mini POP, lalu petakan
        // chassis_node_id → anchor_pop_id supaya hasil groupBy tetap berupa
        // Collection dikunci id Mini POP, sama seperti sebelumnya (dipakai
        // `$slotsByPop->has($popId)`/`->get($popId)` di view peta jaringan).
        $chassisNodesForSlots = $miniPopIds !== []
            ? NetworkNode::query()->whereIn('anchor_pop_id', $miniPopIds)
                ->whereHas('nodeType', fn ($query) => $query->where('code', 'olt_chassis'))
                ->get(['id', 'anchor_pop_id'])
            : collect();
        $miniPopIdByChassisNodeId = $chassisNodesForSlots->pluck('anchor_pop_id', 'id');

        $slotsByPop = Schema::hasTable('olt_slots') && $chassisNodesForSlots->isNotEmpty()
            ? OltSlot::query()->whereIn('chassis_node_id', $miniPopIdByChassisNodeId->keys())
                ->with('cardNode')->orderBy('number')->get()
                ->groupBy(fn (OltSlot $slot): ?int => $miniPopIdByChassisNodeId->get($slot->chassis_node_id))
            : collect();
        $freeOltSlots = $slotsByPop->sum(fn ($slots) => $slots->whereNull('card_node_id')->count());

        $clientNodes = [];
        foreach ($nodes as $node) {
            $portsList = [];
            $downstreamCustomers = [];

            foreach ($node->ports->sortBy('number') as $port) {
                $assignment = $port->activeAssignment->first();
                $customer = $assignment?->customer;
                $targetNode = $assignment?->targetNode;

                $pType = 'empty';
                $pLabel = 'Kosong';
                $cid = null;
                $ontSn = null;
                $ontModel = null;
                $plan = null;

                if ($customer) {
                    $pType = 'customer';
                    $pLabel = $customer->full_name;
                    $cid = $customer->customer_number;
                    $ontSn = $customer->customerTechnicalDetail?->router_or_ont_serial
                        ?? $customer->inventorySerials->first()?->serial_number;
                    $ontModel = $customer->inventorySerials->first()?->item?->name ?? 'ONT / Modem';
                    $plan = $customer->internetPackage?->name ?? '';

                    $downstreamCustomers[] = [
                        'port' => $port->number,
                        'name' => $customer->full_name,
                        'cid' => $cid,
                        'ont_model' => $ontModel,
                        'ont_sn' => $ontSn,
                        'plan' => $plan,
                    ];
                } elseif ($targetNode) {
                    $pType = 'node';
                    $pLabel = $targetNode->name;
                } elseif ($assignment?->manual_label) {
                    $pType = 'manual';
                    $pLabel = $assignment->manual_label;
                } elseif ($port->is_used) {
                    $pType = 'manual';
                    $pLabel = 'Terpakai';
                }

                $portsList[] = [
                    'n' => $port->number,
                    'is_used' => (bool) $port->is_used,
                    'type' => $pType,
                    'label' => $pLabel,
                    'cid' => $cid,
                    'ont_sn' => $ontSn,
                    'ont_model' => $ontModel,
                    'plan' => $plan,
                    'target_node_id' => $targetNode?->id,
                    'target_node_code' => $targetNode?->code,
                    'target_node_name' => $targetNode?->name,
                ];
            }

            $popSlots = [];
            if ($node->anchor_pop_id && $slotsByPop->has($node->anchor_pop_id)) {
                foreach ($slotsByPop->get($node->anchor_pop_id) as $slot) {
                    $popSlots[] = [
                        'slot' => $slot->number,
                        'card_code' => $slot->cardNode?->code,
                        'card_name' => $slot->cardNode?->name,
                        'card_id' => $slot->card_node_id,
                    ];
                }
            }

            $totalPorts = (int) $node->ports_count;
            $usedPorts = (int) $node->used_ports_count;
            $utilizationPct = $totalPorts > 0 ? round(100 * $usedPorts / $totalPorts) : null;
            $nodeAssets = $assets[$node->id] ?? [];

            $clientNodes[$node->id] = [
                'id' => $node->id,
                'code' => $node->code,
                'name' => $node->name,
                'type_code' => $node->nodeType?->code ?? 'other',
                'type_name' => $node->nodeType?->name ?? 'Node',
                'status' => $node->status,
                'parent_id' => $node->parent_id,
                'anchor_pop_id' => $node->anchor_pop_id,
                'anchor_pop_name' => $node->anchorPop?->name,
                'path' => $paths[$node->id] ?? '',
                'capacity' => $node->capacity,
                'ports_count' => $totalPorts,
                'used_ports_count' => $usedPorts,
                'free_ports_count' => max(0, $totalPorts - $usedPorts),
                'utilization_percent' => $utilizationPct,
                'is_red_zone' => $node->isInRedZone(),
                'address' => $node->address,
                'coordinates' => ($node->latitude && $node->longitude) ? "{$node->latitude}, {$node->longitude}" : null,
                'detail_url' => route('network.nodes.show', $node),
                'ports' => $portsList,
                'customers' => $downstreamCustomers,
                'slots' => $popSlots,
                'active_assets' => array_values(array_filter($nodeAssets, fn (array $a): bool => ($a['kind'] ?? '') === 'SN')),
                'passive_assets' => array_values(array_filter($nodeAssets, fn (array $a): bool => ($a['kind'] ?? '') !== 'SN')),
                'children_ids' => $nodes->where('parent_id', $node->id)->pluck('id')->values()->all(),
            ];
        }

        return [
            'nodes' => $nodes,
            'clientNodes' => $clientNodes,
            'inventoryNodes' => $inventoryNodes,
            'rootsByPop' => $nodes->filter(fn (NetworkNode $node): bool => $node->parent_id === null || ! $nodesById->has($node->parent_id))->groupBy('anchor_pop_id'),
            'childrenByNode' => $nodes->whereNotNull('parent_id')->groupBy('parent_id'),
            'assetsByNode' => $assets,
            'pathsByNode' => $paths,
            'nodeIndex' => $nodeIndex,
            'cabangs' => $cabangs,
            'slotsByPop' => $slotsByPop,
            'selectedPopId' => $selectedPopId,
            'sort' => $sort,
            'summary' => [
                'nodes' => $nodes->count(),
                'ports' => (int) $nodes->sum('ports_count'),
                'used_ports' => (int) $nodes->sum('used_ports_count'),
                'free_olt_slots' => $freeOltSlots,
                'assets' => array_sum(array_map('count', $assets)),
                'red_zone' => $nodes->filter(fn (NetworkNode $node): bool => $node->isInRedZone())->count(),
            ],
        ];
    }

    /**
     * @param  list<int>  $popIds
     * @return list<int>
     */
    private function scopedNodeIds(array $popIds): array
    {
        if ($popIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($popIds), '?'));
        $sql = <<<SQL
            WITH RECURSIVE scoped AS (
                SELECT id FROM network_nodes WHERE anchor_pop_id IN ({$placeholders})
                UNION
                SELECT child.id FROM network_nodes child
                JOIN scoped parent ON child.parent_id = parent.id
                WHERE child.anchor_pop_id IS NULL
            )
            SELECT id FROM scoped
            SQL;

        return array_map(
            static fn (object $row): int => (int) $row->id,
            DB::select($sql, array_values($popIds)),
        );
    }

    private function balanceKey(?int $nodeId, ?int $itemId, ?string $lotNo): string
    {
        return $nodeId.':'.$itemId.':'.($lotNo ?? '');
    }

    /**
     * @param  Collection<int, NetworkNode>  $nodesById
     * @param  array<int, string>  $memo
     */
    private function pathFor(NetworkNode $node, Collection $nodesById, array &$memo): string
    {
        if (isset($memo[$node->id])) {
            return $memo[$node->id];
        }

        $parent = $node->parent_id ? $nodesById->get($node->parent_id) : null;
        if ($parent !== null) {
            return $memo[$node->id] = $this->pathFor($parent, $nodesById, $memo).' / '.$node->name;
        }

        $anchor = $node->anchorPop;
        $prefix = collect([$anchor?->parent?->name, $anchor?->name])->filter()->unique()->implode(' / ');

        return $memo[$node->id] = trim($prefix.' / '.$node->name, ' /');
    }
}
