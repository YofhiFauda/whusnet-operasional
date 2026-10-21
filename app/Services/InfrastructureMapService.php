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

        $canViewDeviceSerials = $user->hasPermission('customers.detail.devices.view_sensitive');

        $nodes = NetworkNode::query()
            ->when($popIds !== null, fn ($query) => $query->whereIn('network_nodes.id', $this->scopedNodeIds($popIds)))
            ->with([
                'nodeType',
                'anchorPop.parent',
                'ports.activeAssignment.customer.customerTechnicalDetail',
                'ports.activeAssignment.customer.customerDevice',
                'ports.activeAssignment.customer.inventorySerials' => fn ($query) => $query
                    ->where('status', SerialStatus::INSTALLED->value)
                    ->with('item.category'),
                'ports.activeAssignment.targetNode',
                'ports.activeAssignment.targetPort',
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
                ->with('fromTechnician', 'fopTask.task')
                ->get()
                ->keyBy('serial_id');
        }

        if ($nodeIds !== []) {
            $latest = InventoryTransaction::query()
                ->selectRaw('MAX(id)')
                ->where(function ($query): void {
                    $query->where('type', InventoryTransactionType::DEPLOY->value)
                        ->orWhere(function ($correction): void {
                            $correction->where('type', InventoryTransactionType::ADJUSTMENT->value)
                                ->where('reason', 'infra_record_correction');
                        });
                })
                ->whereIn('to_network_node_id', $nodeIds)
                ->whereNull('serial_id')
                ->groupBy('to_network_node_id', 'item_id', DB::raw("CASE WHEN roll_id IS NULL THEN '' ELSE COALESCE(lot_no, '') END"));

            $quantityEvents = InventoryTransaction::query()
                ->whereIn('id', $latest)
                ->with('fromTechnician', 'createdBy', 'fopTask.task')
                ->get()
                ->keyBy(fn (InventoryTransaction $event): string => $this->balanceKey(
                    $event->to_network_node_id,
                    $event->item_id,
                    $event->roll_id === null ? '' : $event->lot_no
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
                    'task' => $event?->fopTask?->task?->task_number ?? $event?->fopTask?->task_number,
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
                    'technician' => $event?->fromTechnician?->name ?? $event?->createdBy?->name,
                    'task' => $event?->fopTask?->task?->task_number ?? $event?->fopTask?->task_number,
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

        // Sejak Task 1, chassis OLT boleh ditambatkan langsung ke POP
        // `cabang` (bukan cuma Mini POP) — `$miniPopIds` doang bikin chassis
        // yang ditambatkan ke Cabang kehilangan slot OLT-nya di peta ini.
        $cabangIdsForChassis = $selectedPopId !== null && ! $cabangs->contains('id', $selectedPopId)
            ? []
            : ($selectedPopId !== null ? [$selectedPopId] : $cabangs->pluck('id')->all());
        $anchorPopIds = array_values(array_unique(array_merge($miniPopIds, $cabangIdsForChassis)));

        // Slot OLT dimiliki node chassis (`olt_slots.chassis_node_id`), bukan
        // POP langsung — satu Mini POP/Cabang bisa punya beberapa chassis.
        // Cari dulu node chassis yang ditambatkan ke tiap POP, lalu petakan
        // chassis_node_id -> anchor_pop_id supaya groupBy tetap Collection
        // dikunci id POP seperti sebelumnya ($slotsByPop->has($popId)/get()).
        $chassisNodesForSlots = $anchorPopIds !== []
            ? NetworkNode::query()->whereIn('anchor_pop_id', $anchorPopIds)
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

        $nodeDetails = [];
        foreach ($nodes as $node) {
            $rows = $assets[$node->id] ?? [];
            $portList = [];
            foreach ($node->ports->sortBy('number') as $port) {
                $assignment = $port->activeAssignment->first();
                $kind = 'empty';
                $label = 'Port kosong';
                $customerName = null;
                $customerCid = null;
                $customerModems = [];
                $customerModemsHidden = false;
                $targetNodeId = null;
                $targetNodeCode = null;
                $targetNodeName = null;

                if ($assignment) {
                    if ($assignment->customer) {
                        $kind = 'customer';
                        $customerName = $assignment->customer->full_name;
                        $customerModemsHidden = ! $canViewDeviceSerials
                            || ($popIds !== null && ! in_array($assignment->customer->pop_id, $popIds));
                        if (! $customerModemsHidden) {
                            $customerModems = $assignment->customer->networkPortModems();
                        }
                        $customerCid = $assignment->customer->cid ?? $assignment->customer->customer_code ?? ('CID-'.$assignment->customer->id);
                        $label = $customerName;
                    } elseif ($assignment->targetNode) {
                        $kind = 'node';
                        $targetNodeId = $assignment->targetNode->id;
                        $targetNodeCode = $assignment->targetNode->code;
                        $targetNodeName = $assignment->targetNode->name;
                        $label = $targetNodeCode.' ('.$targetNodeName.')'
                            .($assignment->targetPort ? ' · port '.$assignment->targetPort->number : '');
                    } elseif ($assignment->manual_label) {
                        $kind = 'manual';
                        $label = $assignment->manual_label;
                    }
                } elseif ($port->is_used) {
                    $kind = 'manual';
                    $label = 'Terpakai (belum terverifikasi)';
                }

                $portList[] = [
                    'id' => $port->id,
                    'number' => $port->number,
                    'is_used' => (bool) $port->is_used,
                    'kind' => $kind,
                    'label' => $label,
                    'customer_name' => $customerName,
                    'customer_cid' => $customerCid,
                    'customer_modems' => $customerModems,
                    'customer_modems_hidden' => $customerModemsHidden,
                    'target_node_id' => $targetNodeId,
                    'target_node_code' => $targetNodeCode,
                    'target_node_name' => $targetNodeName,
                    'target_port_number' => $assignment?->targetPort?->number,
                ];
            }

            $nodeDetails[$node->id] = [
                'id' => $node->id,
                'code' => $node->code,
                'name' => $node->name,
                'type' => $node->nodeType?->code ?? 'other',
                'typeName' => $node->nodeType?->name ?? 'Node',
                'parent_id' => $node->parent_id,
                'anchor_pop_id' => $node->anchor_pop_id,
                'anchor_pop_name' => $node->anchorPop?->name,
                'cabang_name' => $node->anchorPop?->parent?->name ?? $node->anchorPop?->name ?? '—',
                'status' => $node->status,
                'path' => $paths[$node->id],
                'ports_count' => (int) $node->ports_count,
                'used_ports_count' => (int) $node->used_ports_count,
                'is_red_zone' => $node->isInRedZone(),
                'ports' => $portList,
                'assets' => $rows,
                'location_description' => $node->location_description,
                'coverage_area' => $node->coverage_area,
                'latitude' => $node->latitude,
                'longitude' => $node->longitude,
                'show_url' => route('network.nodes.show', $node),
            ];
        }

        $slotsData = [];
        foreach ($slotsByPop as $popId => $slots) {
            $slotsData[$popId] = $slots->map(fn ($slot) => [
                'number' => $slot->number,
                'card_node_id' => $slot->card_node_id,
                'card_code' => $slot->cardNode?->code ?? null,
                'card_name' => $slot->cardNode?->name ?? null,
            ])->all();
        }

        $topologyPops = Pop::forUser($user)
            ->whereIn('type', ['pusat', 'cabang', 'mini_pop'])
            ->where('status', 'active')
            ->when($selectedPopId !== null, function ($query) use ($selectedPopId, $cabangs): void {
                if (! $cabangs->contains('id', $selectedPopId)) {
                    $query->whereIn('id', []);

                    return;
                }

                $query->where(fn ($scope) => $scope->where('id', $selectedPopId)->orWhere('parent_id', $selectedPopId));
            })
            ->orderBy('name')
            ->get();
        $visiblePopsById = $topologyPops->keyBy('id');
        $topologyDetails = [];
        $topologyIndex = [];
        foreach ($topologyPops as $pop) {
            $parent = $visiblePopsById->get($pop->parent_id);
            $id = -$pop->id;
            $parentId = $parent ? -$parent->id : null;
            $uplink = $pop->uplink_node_id !== null ? $nodesById->get($pop->uplink_node_id) : null;
            if ($parent !== null && $uplink !== null && $uplink->resolvePop()?->id === $parent->id) {
                $parentId = $uplink->id;
            }
            $typeName = match ($pop->type) {
                'pusat' => 'Pusat / Server Utama',
                'cabang' => 'Cabang',
                default => 'Mini POP',
            };
            $path = $parent ? $parent->name.' / '.$pop->name : $pop->name;
            $topologyDetails[$id] = [
                'id' => $id,
                'code' => $pop->name,
                'name' => $typeName,
                'type' => 'pop_'.$pop->type,
                'typeName' => $typeName,
                'parent_id' => $parentId,
                'anchor_pop_id' => null,
                'anchor_pop_name' => $pop->name,
                'cabang_name' => $pop->type === 'mini_pop' ? ($parent?->name ?? $pop->name) : $pop->name,
                'status' => $pop->status,
                'path' => $path,
                'ports_count' => 0,
                'used_ports_count' => 0,
                'is_red_zone' => false,
                'ports' => [],
                'assets' => [],
                'location_description' => $pop->address,
                'coverage_area' => null,
                'latitude' => $pop->latitude,
                'longitude' => $pop->longitude,
                'show_url' => $user->hasPermission('pops.view') ? route('master.pop.show', $pop) : null,
            ];
            $topologyIndex[$id] = [
                'parent' => $parentId,
                'type' => 'pop_'.$pop->type,
                'search' => mb_strtolower($path.' '.$typeName),
            ];
        }
        foreach ($nodes as $node) {
            $parentId = $node->parent_id !== null && $nodesById->has($node->parent_id)
                ? $node->parent_id
                : ($visiblePopsById->has($node->anchor_pop_id) ? -$node->anchor_pop_id : null);
            $topologyDetails[$node->id] = [...$nodeDetails[$node->id], 'parent_id' => $parentId, 'relation' => 'location', 'verified' => null];
            $topologyIndex[$node->id] = [...$nodeIndex[$node->id], 'parent' => $parentId];
        }

        $this->applyHierarchyProjection($user, $selectedPopId, $topologyDetails, $topologyIndex);

        foreach ($nodes as $node) {
            foreach ($node->ports as $port) {
                $customer = $port->activeAssignment->first()?->customer;
                if ($customer === null || ($popIds !== null && ! in_array($customer->pop_id, $popIds))) {
                    continue;
                }

                $modems = $customer->networkPortModems();
                if ($modems === []) {
                    continue;
                }

                $id = -1000000000 - $customer->id;
                $drop = $nodes->firstWhere('code', "CUST-{$customer->id}-DC")
                    ?? $nodes->firstWhere('code', "TOPO-CUST-{$customer->id}-DC");
                $parentId = $drop?->parent_id === $node->id ? $drop->id : $node->id;
                $cid = $customer->cid ?? $customer->customer_code ?? 'CID-'.$customer->id;
                $path = $paths[$node->id].' / Port '.$port->number.' / '.$cid;
                $topologyDetails[$id] = [
                    'id' => $id,
                    'code' => $cid,
                    'name' => $customer->full_name,
                    'type' => 'customer_ont',
                    'typeName' => 'ONT dipasang ke pelanggan',
                    'parent_id' => $parentId,
                    'anchor_pop_id' => null,
                    'anchor_pop_name' => $nodeDetails[$node->id]['anchor_pop_name'],
                    'cabang_name' => $nodeDetails[$node->id]['cabang_name'],
                    'status' => 'active',
                    'path' => $path,
                    'ports_count' => 0,
                    'used_ports_count' => 0,
                    'is_red_zone' => false,
                    'ports' => [],
                    'assets' => [],
                    'location_description' => 'Terhubung melalui '.$node->code.' port '.$port->number,
                    'coverage_area' => null,
                    'latitude' => null,
                    'longitude' => null,
                    'show_url' => $user->hasPermission('customers.view') ? route('customers.show', $customer) : null,
                    'customer_modems' => $canViewDeviceSerials ? $modems : [],
                    'customer_modems_hidden' => ! $canViewDeviceSerials,
                ];
                $topologyIndex[$id] = [
                    'parent' => $parentId,
                    'type' => 'customer_ont',
                    'search' => mb_strtolower($path.' '.$customer->full_name),
                ];
            }
        }

        return [
            'nodes' => $nodes,
            'inventoryNodes' => $inventoryNodes,
            'rootsByPop' => $nodes->filter(fn (NetworkNode $node): bool => $node->parent_id === null || ! $nodesById->has($node->parent_id))->groupBy('anchor_pop_id'),
            'childrenByNode' => $nodes->whereNotNull('parent_id')->groupBy('parent_id'),
            'assetsByNode' => $assets,
            'pathsByNode' => $paths,
            'nodeIndex' => $nodeIndex,
            'nodeDetails' => $nodeDetails,
            'topologyDetails' => $topologyDetails,
            'topologyIndex' => $topologyIndex,
            'topologyPops' => $topologyPops,
            'topologyPopChildren' => $topologyPops->groupBy(fn (Pop $pop): int => $visiblePopsById->has($pop->parent_id) ? $pop->parent_id : 0),
            'slotsData' => $slotsData,
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
     * Peta memakai proyeksi hierarki yang SAMA dengan Master Node (Task 10):
     * ODP ber-uplink PON digantung di kartu OLT pemilik PON itu (garis
     * koneksi, `relation = connection`, `verified` menandai status), Server
     * Utama diproyeksikan di atas POP Pusat. Slot/PON/Port tidak menjadi
     * kartu/pin sendiri — tampil di panel perangkat (`slots`).
     *
     * @param  array<int, array<string, mixed>>  $topologyDetails
     * @param  array<int, array<string, mixed>>  $topologyIndex
     */
    private function applyHierarchyProjection(User $user, ?int $selectedPopId, array &$topologyDetails, array &$topologyIndex): void
    {
        $projection = app(InfrastructureHierarchyService::class)->build($user, $selectedPopId);
        $items = $projection['items'];

        foreach ($items as $key => $item) {
            if ($item['kind'] !== 'node' || ! isset($topologyDetails[$item['id']])) {
                continue;
            }
            $nodeId = $item['id'];
            $topologyDetails[$nodeId]['verified'] = $item['verified'];
            $topologyDetails[$nodeId]['uplink_unverified'] = $item['uplink_unverified'];
            $topologyDetails[$nodeId]['breadcrumb'] = $item['breadcrumb'];

            if ($item['relation'] === 'connection') {
                $port = $items[$item['parent']] ?? null;
                $slot = $port ? ($items[$port['parent']] ?? null) : null;
                $device = $slot ? ($items[$slot['parent']] ?? null) : null;
                if ($device !== null && isset($topologyDetails[$device['id']])) {
                    $topologyDetails[$nodeId]['parent_id'] = $device['id'];
                    $topologyDetails[$nodeId]['relation'] = 'connection';
                    $topologyDetails[$nodeId]['via_slot'] = $slot['label'];
                    $topologyDetails[$nodeId]['via_port'] = $port['label'];
                    $topologyIndex[$nodeId]['parent'] = $device['id'];
                }
            }

            $slots = [];
            foreach ($projection['children'][$key] ?? [] as $childKey) {
                if ($items[$childKey]['kind'] !== 'slot') {
                    continue;
                }
                $slots[] = [
                    'label' => $items[$childKey]['label'],
                    'status' => $items[$childKey]['status_label'],
                    'url' => $items[$childKey]['url'],
                    'ports' => array_map(fn (string $portKey): array => [
                        'label' => $items[$portKey]['label'],
                        'status' => $items[$portKey]['status_label'],
                        'capacity' => $items[$portKey]['capacity'],
                        'connection' => $items[$portKey]['connection_label'] ?? null,
                        'url' => $items[$portKey]['url'],
                    ], array_values(array_filter($projection['children'][$childKey] ?? [], fn (string $child): bool => $items[$child]['kind'] === 'port'))),
                ];
            }
            $topologyDetails[$nodeId]['slots'] = $slots;

            // Aset di Slot/Port milik perangkat ini tampil di panel perangkat
            // dengan posisi tepatnya (Slot/Port tidak punya kartu sendiri).
            foreach ($projection['children'][$key] ?? [] as $slotKey) {
                foreach ([$slotKey, ...($projection['children'][$slotKey] ?? [])] as $positionKey) {
                    foreach ($items[$positionKey]['direct_assets'] as $asset) {
                        $topologyDetails[$nodeId]['assets'][] = [...$asset, 'position' => $items[$positionKey]['breadcrumb'],
                            'installed_at' => null, 'technician' => null, 'task' => null,
                            'trace_url' => $asset['kind'] === 'SN' ? route('warehouse.traceability.index', ['sn' => $asset['reference']]) : null];
                    }
                }
            }
        }

        foreach ($items as $item) {
            if ($item['kind'] === 'mini_pop' && isset($topologyDetails[-$item['id']])) {
                foreach ($item['direct_assets'] as $asset) {
                    $topologyDetails[-$item['id']]['assets'][] = [...$asset, 'position' => $item['breadcrumb'],
                        'installed_at' => null, 'technician' => null, 'task' => null,
                        'trace_url' => $asset['kind'] === 'SN' ? route('warehouse.traceability.index', ['sn' => $asset['reference']]) : null];
                }
            }
        }

        foreach ($items as $item) {
            if ($item['kind'] === 'pop' && $item['parent'] !== null && str_starts_with($item['parent'], 'node:')) {
                $serverId = (int) substr($item['parent'], 5);
                if (isset($topologyDetails[-$item['id']], $topologyDetails[$serverId])) {
                    $topologyDetails[$serverId]['parent_id'] = null;
                    $topologyIndex[$serverId]['parent'] = null;
                    $topologyDetails[-$item['id']]['parent_id'] = $serverId;
                    $topologyIndex[-$item['id']]['parent'] = $serverId;
                }
            }
        }
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
