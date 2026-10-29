<?php

namespace App\Services;

use App\Enums\InventoryTransactionType;
use App\Enums\SerialStatus;
use App\Models\InventoryTransaction;
use App\Models\NetworkNode;
use App\Models\Pop;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class InfrastructureMapService
{
    public function __construct(private readonly EffectiveAccessService $access) {}

    /**
     * @return array<string, mixed>
     */
    public function build(User $user, ?int $selectedPopId, string $sort, bool $withCustomerOnt = false): array
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
        // Tautan "Riwayat Gudang" cuma boleh terbentuk buat aktor yang
        // beneran pegang warehouse_traceability.view — network_nodes.view
        // gak otomatis ngikutin izin itu (role matrix dinamis, bukan
        // keduanya selalu digrant bareng).
        $canViewTraceability = $user->hasPermission('warehouse_traceability.view');

        $nodes = NetworkNode::query()
            ->when($popIds !== null, fn ($query) => $query->whereIn('network_nodes.id', $this->scopedNodeIds($popIds)))
            ->with([
                'nodeType',
                'anchorPop.parent',
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

        // Daftar port (+ pelanggan, modem, SN terpasang) dulu di-eager-load untuk
        // SEMUA node sekaligus: di 15k pelanggan itu puluhan ribu model plus
        // payload JS ratusan MB → OOM. Default sekarang kosong; panel node
        // mengambilnya lewat `portsFor()` hanya untuk node yang diklik. Mode
        // `$withCustomerOnt` (kartu ONT) memakai satu query baris mentah
        // (`ontRows()`), bukan 100 ribu model Eloquent.
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
                    'trace_url' => $canViewTraceability ? route('warehouse.traceability.index', ['sn' => $serial->serial_number]) : null,
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
                    'trace_url' => ($lot && $canViewTraceability) ? route('warehouse.traceability.index', ['roll' => $lot]) : null,
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

        // Slot OLT kini baris `network_nodes` (anak langsung chassis, tipe
        // `olt_slot`) — tabel `olt_slots` + konsep kartu sudah dibuang. "Slot
        // kosong" diartikan ulang: Slot yang belum punya PON satu pun, karena
        // tidak ada lagi kartu yang bisa kosong/terisi.
        $chassisIdsForSlots = $anchorPopIds !== []
            ? NetworkNode::query()->whereIn('anchor_pop_id', $anchorPopIds)
                ->whereHas('nodeType', fn ($query) => $query->where('code', 'olt_chassis'))
                ->pluck('id')
            : collect();
        $freeOltSlots = $chassisIdsForSlots->isNotEmpty()
            ? NetworkNode::query()->whereIn('parent_id', $chassisIdsForSlots)
                ->whereHas('nodeType', fn ($query) => $query->where('code', 'olt_slot'))
                ->whereDoesntHave('children')->count()
            : 0;

        $nodeDetails = [];
        foreach ($nodes as $node) {
            $rows = $assets[$node->id] ?? [];
            $portList = [];
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
                'ports_loaded' => $node->ports_count === 0,
                'ports_url' => $node->ports_count > 0 ? route('network.map.ports', $node) : null,
                'assets' => $rows,
                'location_description' => $node->location_description,
                'coverage_area' => $node->coverage_area,
                'latitude' => $node->latitude,
                'longitude' => $node->longitude,
                'show_url' => route('network.nodes.show', $node),
            ];
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

        // Peta butuh rantai lengkap OLT→Slot→PON→ODP sekaligus untuk kanvas
        // (deep link & tray "slots" di kartu perangkat) — $withPositions =
        // true supaya lazy load Task 11 (default Master Node) tidak
        // menyembunyikan Slot/PON dari Peta.
        // Proyeksi hierarki memakan ±3 dtk (menghidrasi ulang semua node) dan hanya
        // berisi array biasa; dipakai bersama tab Topologi, Pohon, dan Inventaris, jadi
        // pindah tab tidak mengulangnya. Kunci memuat user (scope POP) + cabang terpilih.
        $hierarchy = Cache::remember(
            self::hierarchyCacheKey($user->id, $selectedPopId),
            now()->addMinutes(5),
            fn (): array => app(InfrastructureHierarchyService::class)->build($user, $selectedPopId, withPositions: true),
        );
        $this->applyHierarchyProjection($hierarchy, $topologyDetails, $topologyIndex, $canViewTraceability);

        // Kartu ONT: hanya identitas pelanggan. SN modem (4 relasi per pelanggan)
        // TIDAK dihitung di sini — dulu 15k pelanggan × relasi itu menghabiskan
        // 512 MB. Modem diambil saat kartu dipilih, lewat `modems_url` (daftar port
        // node induk, yang sudah menghormati izin & scope). Pencarian ke kode node
        // memakai peta berindeks, bukan firstWhere() per pelanggan (O(N²)).
        $nodesByCode = $withCustomerOnt ? $nodes->keyBy('code') : collect();
        foreach ($withCustomerOnt ? $this->ontRows($nodeIds) : [] as $row) {
            $node = $nodesById->get($row->node_id);
            $port = $row;
            $customer = $row;
            if ($node !== null) {
                if ($popIds !== null && ! in_array($customer->pop_id, $popIds)) {
                    continue;
                }

                $id = -1000000000 - $customer->id;
                $drop = $nodesByCode->get("CUST-{$customer->id}-DC")
                    ?? $nodesByCode->get("TOPO-CUST-{$customer->id}-DC");
                $parentId = $drop?->parent_id === $node->id ? $drop->id : $node->id;
                $cid = $customer->cid ?? $customer->customer_code ?? 'CID-'.$customer->id;
                $path = $paths[$node->id].' / Port '.$port->number.' / '.$cid;
                $topologyDetails[$id] = $this->ontDetail(
                    $user, $customer, $id, $cid, $parentId, $path,
                    $nodeDetails[$node->id]['anchor_pop_name'], $nodeDetails[$node->id]['cabang_name'],
                    $node->code, $port->number, $nodeDetails[$node->id]['ports_url'],
                    $canViewDeviceSerials,
                );
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
            'hierarchy' => $hierarchy,
            'topologyDetails' => $topologyDetails,
            'topologyIndex' => $topologyIndex,
            'topologyPops' => $topologyPops,
            'topologyPopChildren' => $topologyPops->groupBy(fn (Pop $pop): int => $visiblePopsById->has($pop->parent_id) ? $pop->parent_id : 0),
            'cabangs' => $cabangs,
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

    public static function hierarchyCacheKey(int $userId, ?int $selectedPopId): string
    {
        return 'peta:hier:'.$userId.':'.($selectedPopId ?? 'all');
    }

    /**
     * Peta memakai proyeksi hierarki yang SAMA dengan Master Node, dibangun
     * dengan `$withPositions = true` (Task 11) supaya Slot/PON/Port Switch
     * ikut terbawa. Sejak Slot/PON/Port Switch adalah baris `network_nodes`
     * sungguhan (bukan `infrastructure_slots`/`infrastructure_ports` lagi),
     * mereka SUDAH tampil sebagai kartu kanvas sendiri lewat `$nodeDetails`/
     * `$topologyDetails` yang dibangun dari `$nodes` di atas (parent_id apa
     * adanya) — tidak perlu lagi dinamespace ID negatif seperti dulu. Method
     * ini HANYA menambahkan info yang tidak ada di proyeksi `$nodes` biasa:
     * kapasitas PON (pelanggan aktif), breadcrumb jalur lengkap, dan tray
     * "slots" ringkas pada kartu perangkat OLT/Switch.
     *
     * @param  array<int, array<string, mixed>>  $topologyDetails
     * @param  array<int, array<string, mixed>>  $topologyIndex
     */
    private function applyHierarchyProjection(array $projection, array &$topologyDetails, array &$topologyIndex, bool $canViewTraceability): void
    {
        $items = $projection['items'];
        $isSlotType = fn (string $type): bool => in_array($type, ['olt_slot', 'switch_slot'], true);
        $isPositionPortType = fn (string $type): bool => in_array($type, ['olt_pon', 'switch_port'], true);

        foreach ($items as $key => $item) {
            if ($item['kind'] !== 'node' || ! isset($topologyDetails[$item['id']])) {
                continue;
            }
            $nodeId = $item['id'];
            $topologyDetails[$nodeId]['breadcrumb'] = $item['breadcrumb'];
            $topologyDetails[$nodeId]['path'] = $item['breadcrumb'];
            if ($item['capacity'] !== null) {
                $topologyDetails[$nodeId]['capacity'] = $item['capacity'];
            }

            // Slot/PON/Port Switch mencatat aset lewat kolom
            // `current_infrastructure_slot_id`/`_port_id` (nama historis,
            // bukan `current_network_node_id`) — relasi `deployedSerials()`
            // node (dipakai `$nodeDetails` di atas) TIDAK menangkapnya, jadi
            // digabung di sini dari `direct_assets` hierarki yang sudah
            // menyatukan ketiga kolom lokasi (`InfrastructureHierarchyService::attachDirectAssets()`).
            if ($isSlotType($item['type']) || $isPositionPortType($item['type'])) {
                foreach ($item['direct_assets'] as $asset) {
                    $topologyDetails[$nodeId]['assets'][] = [...$asset, 'position' => $item['breadcrumb'],
                        'installed_at' => null, 'technician' => null, 'task' => null,
                        'trace_url' => ($asset['kind'] === 'SN' && $canViewTraceability) ? route('warehouse.traceability.index', ['sn' => $asset['reference']]) : null];
                }
            }

            if (! in_array($item['type'], ['olt_chassis', 'switch_mini_pop'], true)) {
                continue;
            }

            $slots = [];
            foreach ($projection['children'][$key] ?? [] as $childKey) {
                if (! $isSlotType($items[$childKey]['type'])) {
                    continue;
                }
                $slots[] = [
                    'label' => $items[$childKey]['label'],
                    'capacity' => $items[$childKey]['capacity'],
                    'status' => $items[$childKey]['status_label'],
                    'url' => $items[$childKey]['url'],
                    'ports' => array_map(fn (string $portKey): array => [
                        'label' => $items[$portKey]['label'],
                        'status' => $items[$portKey]['status_label'],
                        'capacity' => $items[$portKey]['capacity'],
                        'connection' => $items[$portKey]['connection_label'] ?? null,
                        'url' => $items[$portKey]['url'],
                        'odps' => array_map(fn (string $odpKey): array => [
                            'label' => $items[$odpKey]['label'], 'url' => $items[$odpKey]['url'],
                        ], array_values(array_filter($projection['children'][$portKey] ?? [],
                            fn (string $child): bool => $items[$child]['type'] === 'odp'))),
                    ], array_values(array_filter($projection['children'][$childKey] ?? [], fn (string $child): bool => $isPositionPortType($items[$child]['type'])))),
                ];
            }
            $topologyDetails[$nodeId]['slots'] = $slots;
        }

        foreach ($items as $item) {
            if ($item['kind'] === 'mini_pop' && isset($topologyDetails[-$item['id']])) {
                foreach ($item['direct_assets'] as $asset) {
                    $topologyDetails[-$item['id']]['assets'][] = [...$asset, 'position' => $item['breadcrumb'],
                        'installed_at' => null, 'technician' => null, 'task' => null,
                        'trace_url' => ($asset['kind'] === 'SN' && $canViewTraceability) ? route('warehouse.traceability.index', ['sn' => $asset['reference']]) : null];
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
     * Pisahkan kartu ODP dari payload kanvas awal: di jaringan besar ODP
     * adalah ~90% node (3.072 dari 3.494 di data uji 15k) dan membuat HTML
     * puluhan MB-per-sepuluh + DOM berat di HP/jaringan lemah. Kanvas hanya
     * menerima node struktur (POP, OLT, Slot, PON, ...); tiap PON diberi
     * `lazy_children_count` + `lazy_url`, dan detail ODP-nya diambil saat PON
     * dibuka. Indeks pencarian (`topologyIndex`) TETAP lengkap supaya ODP
     * bisa dicari tanpa dimuat.
     *
     * @param  array<int, array<string, mixed>>  $topologyDetails
     * @param  array<string, mixed>  $urlQuery  pop_id/sort yang dibawa ke URL anak
     * @return array{details: array<int, array<string, mixed>>, lazy: array<int, array<int, array<string, mixed>>>}
     */
    public function splitLazyOdp(array $topologyDetails, array $urlQuery): array
    {
        $lazy = [];
        foreach ($topologyDetails as $id => $detail) {
            if (($detail['type'] ?? '') === 'odp' && isset($topologyDetails[$detail['parent_id'] ?? null]) && $detail['parent_id'] > 0) {
                $lazy[$detail['parent_id']][$id] = $detail;
            }
        }

        foreach ($lazy as $parentId => $children) {
            foreach (array_keys($children) as $childId) {
                unset($topologyDetails[$childId]);
            }
            $topologyDetails[$parentId]['lazy_children_count'] = count($children);
            $topologyDetails[$parentId]['lazy_url'] = route('network.map.children', ['node' => $parentId] + $urlQuery);
        }

        return ['details' => $topologyDetails, 'lazy' => $lazy];
    }

    /**
     * Mode ONT: kartu ONT (12 ribu+ di data uji 15k) dikeluarkan dari payload
     * awal. Tiap induknya (ODP) diberi `lazy_url`; ONT per induk diambil saat
     * ODP dibuka, atau per Slot sekaligus (`ont_batch_url`) untuk Buka Semua —
     * puluhan permintaan, bukan ribuan. Indeks pencarian TETAP lengkap.
     *
     * @param  array<int, array<string, mixed>>  $topologyDetails
     * @param  array<string, mixed>  $urlQuery  pop_id/sort/ont yang dibawa ke URL anak
     * @return array{details: array<int, array<string, mixed>>, lazy: array<int, array<int, array<string, mixed>>>, slotOf: array<int, int>}
     */
    public function splitLazyOnt(array $topologyDetails, array $urlQuery): array
    {
        $lazy = [];
        foreach ($topologyDetails as $id => $detail) {
            if (($detail['type'] ?? '') === 'customer_ont' && isset($topologyDetails[$detail['parent_id'] ?? null])) {
                $lazy[$detail['parent_id']][$id] = $detail;
            }
        }

        $slotOf = [];
        $slotCounts = [];
        foreach ($lazy as $parentId => $children) {
            foreach (array_keys($children) as $childId) {
                unset($topologyDetails[$childId]);
            }
            $topologyDetails[$parentId]['lazy_children_count'] = count($children);
            $topologyDetails[$parentId]['lazy_url'] = route('network.map.children', ['node' => $parentId] + $urlQuery);

            // Slot terdekat di atas induk; batas langkah menjaga dari rantai induk yang berputar.
            $cursor = $parentId;
            for ($hops = 0; $cursor !== null && isset($topologyDetails[$cursor]) && $hops < 20; $hops++) {
                if (in_array($topologyDetails[$cursor]['type'] ?? '', ['olt_slot', 'switch_slot'], true)) {
                    $slotOf[$parentId] = $cursor;
                    $slotCounts[$cursor] = ($slotCounts[$cursor] ?? 0) + count($children);
                    break;
                }
                $cursor = $topologyDetails[$cursor]['parent_id'] ?? null;
            }
        }

        foreach ($slotCounts as $slotId => $count) {
            $topologyDetails[$slotId]['ont_batch_count'] = $count;
            $topologyDetails[$slotId]['ont_batch_url'] = route('network.map.children-batch', ['node' => $slotId] + $urlQuery);
        }

        return ['details' => $topologyDetails, 'lazy' => $lazy, 'slotOf' => $slotOf];
    }

    /**
     * Satu baris per port terisi: identitas pelanggan saja, tanpa model
     * Eloquent (15k pelanggan × model port/assignment/customer = 100k objek).
     * Kursor menjaga memori tetap datar; hasil diurutkan seperti port di node.
     *
     * @param  array<int, int>  $nodeIds
     * @return \Generator<int, object>
     */
    private function ontRows(array $nodeIds): \Generator
    {
        $query = DB::table('network_node_ports as p')
            ->join('network_port_assignments as a', fn ($join) => $join->on('a.network_node_port_id', '=', 'p.id')->where('a.active_key', 1))
            ->join('customers as c', 'c.id', '=', 'a.customer_id')
            ->whereIn('p.network_node_id', $nodeIds)
            ->orderBy('p.network_node_id')
            ->orderBy('p.number')
            ->select('p.network_node_id as node_id', 'p.number', 'c.id', 'c.cid', 'c.customer_code', 'c.full_name', 'c.pop_id');

        foreach ($query->cursor() as $row) {
            yield $row;
        }
    }

    /**
     * Relasi berat milik daftar port. Dipisah supaya `build()` (mode ONT) dan
     * `portsFor()` (lazy per node) memuat persis hal yang sama.
     *
     * @return array<int|string, mixed>
     */
    private function portRelations(): array
    {
        return [
            'ports.activeAssignment.customer.customerTechnicalDetail',
            'ports.activeAssignment.customer.customerDevice',
            'ports.activeAssignment.customer.inventorySerials' => fn ($query) => $query
                ->where('status', SerialStatus::INSTALLED->value)
                ->with('item.category'),
            'ports.activeAssignment.targetNode',
            'ports.activeAssignment.targetPort',
        ];
    }

    /**
     * Detail satu kartu ONT pelanggan. Satu-satunya pembangun: dipakai `build()`
     * (mode ONT) dan `ontDetailFor()` (kartu tunggal hasil cari), supaya isinya
     * tidak menyimpang.
     *
     * @return array<string, mixed>
     */
    private function ontDetail(User $user, object $customer, int $id, string $cid, int $parentId, string $path, ?string $anchorPopName, ?string $cabangName, string $nodeCode, int $portNumber, ?string $portsUrl, bool $canViewDeviceSerials): array
    {
        return [
            'id' => $id,
            'code' => $cid,
            'name' => $customer->full_name,
            'type' => 'customer_ont',
            'typeName' => 'ONT pelanggan',
            'parent_id' => $parentId,
            'anchor_pop_id' => null,
            'anchor_pop_name' => $anchorPopName,
            'cabang_name' => $cabangName,
            'status' => 'active',
            'path' => $path,
            'ports_count' => 0,
            'used_ports_count' => 0,
            'is_red_zone' => false,
            'ports' => [],
            'assets' => [],
            'location_description' => 'Terhubung melalui '.$nodeCode.' port '.$portNumber,
            'coverage_area' => null,
            'latitude' => null,
            'longitude' => null,
            'show_url' => $user->hasPermission('customers.view') ? route('customers.show', $customer->id) : null,
            'customer_modems' => [],
            'customer_modems_hidden' => ! $canViewDeviceSerials,
            'modems_url' => $canViewDeviceSerials ? $portsUrl : null,
            'modems_loaded' => ! $canViewDeviceSerials,
        ];
    }

    /**
     * Satu kartu ONT pelanggan beserta induknya, tanpa membangun seluruh peta:
     * hasil cari menampilkan ONT-nya saja di kanvas biasa. Pelanggan atau node
     * di luar scope POP → null (404 yang sama dengan pelanggan yang tidak ada).
     *
     * @return array{node: array<string, mixed>, parent_id: int}|null
     */
    public function ontDetailFor(User $user, int $customerId): ?array
    {
        $popIds = $this->access->hasAllPopAccess($user) ? null : $this->access->getAllowedPopIds($user);

        $query = DB::table('network_node_ports as p')
            ->join('network_port_assignments as a', fn ($join) => $join->on('a.network_node_port_id', '=', 'p.id')->where('a.active_key', 1))
            ->join('customers as c', 'c.id', '=', 'a.customer_id')
            ->join('network_nodes as n', 'n.id', '=', 'p.network_node_id')
            ->where('c.id', $customerId)
            ->select('n.id as node_id', 'n.code as node_code', 'n.name as node_name', 'n.parent_id', 'n.anchor_pop_id', 'p.number', 'c.id', 'c.cid', 'c.customer_code', 'c.full_name', 'c.pop_id');
        if ($popIds !== null) {
            $query->whereIn('c.pop_id', $popIds)->whereIn('p.network_node_id', $this->scopedNodeIds($popIds) ?: [0]);
        }
        $row = $query->first();
        if ($row === null) {
            return null;
        }

        // Jalur: nama node dari akar sampai node induk (maks. 12 tingkat).
        $names = [$row->node_name];
        $anchorPopId = $row->anchor_pop_id;
        for ($cursor = $row->parent_id, $hops = 0; $cursor !== null && $hops < 12; $hops++) {
            $ancestor = DB::table('network_nodes')->where('id', $cursor)->first(['name', 'parent_id', 'anchor_pop_id']);
            if ($ancestor === null) {
                break;
            }
            array_unshift($names, $ancestor->name);
            $anchorPopId ??= $ancestor->anchor_pop_id;
            $cursor = $ancestor->parent_id;
        }
        $popName = $anchorPopId !== null ? DB::table('pops')->where('id', $anchorPopId)->value('name') : null;

        // Kabel drop pelanggan (kartu sendiri bila ada), sama seperti di build().
        $dropId = DB::table('network_nodes')
            ->whereIn('code', ["CUST-{$row->id}-DC", "TOPO-CUST-{$row->id}-DC"])
            ->where('parent_id', $row->node_id)
            ->value('id');
        $parentId = (int) ($dropId ?? $row->node_id);
        $cid = $row->cid ?? $row->customer_code ?? 'CID-'.$row->id;
        $canViewDeviceSerials = $user->hasPermission('customers.detail.devices.view_sensitive');

        return [
            'node' => $this->ontDetail(
                $user, $row, -1000000000 - $row->id, $cid, $parentId,
                implode(' / ', $names).' / Port '.$row->number.' / '.$cid,
                $popName, $popName, $row->node_code, $row->number,
                route('network.map.ports', ['node' => $row->node_id]),
                $canViewDeviceSerials,
            ),
            'parent_id' => $parentId,
        ];
    }

    /**
     * Cari pelanggan yang terpasang di port node (nama, CID/kode, dan — bila
     * punya izin SN perangkat — serial modem). Dipakai kotak cari Peta saat
     * kartu ONT tidak ikut halaman (indeks pencarian tidak memuat SN). Scope POP
     * ikut: pelanggan dan node harus berada di POP yang boleh dilihat user.
     *
     * @return list<array<string, mixed>>
     */
    public function searchCustomers(User $user, string $term, int $limit = 20): array
    {
        $term = trim($term);
        if (mb_strlen($term) < 3) {
            return [];
        }

        $popIds = $this->access->hasAllPopAccess($user) ? null : $this->access->getAllowedPopIds($user);
        $canViewSerials = $user->hasPermission('customers.detail.devices.view_sensitive');
        $like = '%'.addcslashes($term, '%_\\').'%';

        $query = DB::table('network_node_ports as p')
            ->join('network_port_assignments as a', fn ($join) => $join->on('a.network_node_port_id', '=', 'p.id')->where('a.active_key', 1))
            ->join('customers as c', 'c.id', '=', 'a.customer_id')
            ->join('network_nodes as n', 'n.id', '=', 'p.network_node_id')
            ->where(function ($where) use ($like, $canViewSerials): void {
                $where->where('c.full_name', 'like', $like)
                    ->orWhere('c.cid', 'like', $like)
                    ->orWhere('c.customer_code', 'like', $like);
                if ($canViewSerials) {
                    $where->orWhere('c.ont_sn', 'like', $like)
                        ->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('customer_technical_details as t')
                            ->whereColumn('t.customer_id', 'c.id')->where('t.router_or_ont_serial', 'like', $like))
                        ->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('customer_devices as d')
                            ->whereColumn('d.customer_id', 'c.id')->where('d.serial_number', 'like', $like))
                        ->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('inventory_serials as i')
                            ->whereColumn('i.customer_id', 'c.id')->where('i.status', SerialStatus::INSTALLED->value)
                            ->where('i.serial_number', 'like', $like));
                }
            })
            ->orderBy('c.full_name')
            ->limit($limit)
            ->select('c.id', 'c.cid', 'c.customer_code', 'c.full_name', 'n.code as node_code', 'p.number');

        if ($popIds !== null) {
            $query->whereIn('c.pop_id', $popIds)->whereIn('p.network_node_id', $this->scopedNodeIds($popIds) ?: [0]);
        }

        $needle = mb_strtolower($term);

        return $query->get()->map(function (object $row) use ($needle): array {
            $cid = $row->cid ?? $row->customer_code ?? 'CID-'.$row->id;
            $match = match (true) {
                str_contains(mb_strtolower((string) $row->full_name), $needle) => 'Nama',
                str_contains(mb_strtolower((string) $cid), $needle) => 'CID',
                default => 'SN',
            };

            return [
                'ont_id' => -1000000000 - $row->id,
                'cid' => $cid,
                'name' => $row->full_name,
                'node_code' => $row->node_code,
                'port' => $row->number,
                'match' => $match,
                'detail_url' => route('network.map.ont', ['customer' => $row->id]),
            ];
        })->all();
    }

    /**
     * Daftar port satu node untuk panel detail Peta (dimuat saat node diklik).
     * Node di luar scope POP user → null (controller menjawab 404 yang sama
     * dengan node yang tidak ada, supaya keberadaannya tidak bocor).
     *
     * @return list<array<string, mixed>>|null
     */
    public function portsFor(User $user, NetworkNode $node): ?array
    {
        $popIds = $this->access->hasAllPopAccess($user) ? null : $this->access->getAllowedPopIds($user);
        if ($popIds !== null && ! in_array($node->id, $this->scopedNodeIds($popIds), true)) {
            return null;
        }

        $node->load($this->portRelations());

        return $this->buildPortList($node->ports, $popIds, $user->hasPermission('customers.detail.devices.view_sensitive'));
    }

    /**
     * @param  iterable<int, NetworkNodePort>  $ports
     * @param  list<int>|null  $popIds
     * @return list<array<string, mixed>>
     */
    private function buildPortList(iterable $ports, ?array $popIds, bool $canViewDeviceSerials): array
    {
        $portList = [];
        foreach (collect($ports)->sortBy('number') as $port) {
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

        return $portList;
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
