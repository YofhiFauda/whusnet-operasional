<?php

namespace App\Services;

use App\Enums\InfrastructurePortKind;
use App\Enums\SerialStatus;
use App\Models\Customer;
use App\Models\InfrastructureBalance;
use App\Models\InfrastructureConnection;
use App\Models\InfrastructurePort;
use App\Models\InfrastructureSlot;
use App\Models\InventorySerial;
use App\Models\NetworkNode;
use App\Models\Pop;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * SATU proyeksi hierarki infrastruktur (§1, §5, Task 10) untuk Master Node,
 * Peta, dan detail posisi:
 *
 *   Server Utama → POP Pusat → POP Cabang → Mini POP
 *     ├── OLT → Slot → PON → ODP → ONT pelanggan
 *     └── Switch → Slot → Port (berhenti; tujuan port = info koneksi)
 *
 * Proyeksi dari beberapa relasi — bukan satu tabel pohon. Parent lokasi
 * (pops.parent_id, network_nodes.parent_id/anchor_pop_id, slot/port FK)
 * dibedakan dari koneksi (ODP di bawah PON lewat `infrastructure_connections`;
 * `relation = connection`, `verified` menandai status verifikasinya). Node
 * legacy (feeder/PON node lama) tetap tampil di parent lamanya.
 *
 * Server Utama yang ditambatkan ke POP Pusat diproyeksikan DI ATAS Pusat
 * tanpa mengubah data (tidak ada siklus parent di database).
 *
 * Scope: hanya POP yang boleh dilihat aktor (`Pop::forUser`) dan node di
 * dalamnya; SN ONT pelanggan butuh `customers.detail.devices.view_sensitive`.
 * Nama/kode jalur diturunkan dari relasi FK saat proyeksi dibuat.
 */
class InfrastructureHierarchyService
{
    public function __construct(
        private readonly EffectiveAccessService $access,
        private readonly InfrastructureConnectionService $connections,
        private readonly PonCapacityService $ponCapacity,
    ) {}

    /**
     * @return array{items: array<string, array<string, mixed>>, children: array<string, list<string>>, roots: list<string>}
     */
    public function build(?User $user, ?int $cabangId = null): array
    {
        $pops = $this->visiblePops($user, $cabangId);
        $popIds = $pops->pluck('id')->all();
        $nodes = $this->visibleNodes($popIds);
        $nodesById = $nodes->keyBy('id');
        $slots = InfrastructureSlot::query()->whereIn('network_node_id', $nodes->pluck('id'))->orderBy('number')->get();
        $ports = InfrastructurePort::query()->whereIn('infrastructure_slot_id', $slots->pluck('id'))->orderBy('number')->get();
        $portIds = $ports->pluck('id')->all();
        $activeConnections = InfrastructureConnection::query()->active()
            ->whereIn('source_infrastructure_port_id', $portIds)->with('targetNode', 'targetPort')->get();
        $uplinkByTarget = $activeConnections->filter(fn (InfrastructureConnection $connection): bool => $connection->target_network_node_id !== null
            && $ports->firstWhere('id', $connection->source_infrastructure_port_id)?->kind === InfrastructurePortKind::PON)
            ->keyBy('target_network_node_id');
        // $user null = proyeksi sistem (rekonsiliasi terjadwal), tanpa
        // pembatasan POP — TIDAK pernah dipakai untuk tampilan pengguna.
        $canSeeSerials = $user === null || $user->hasPermission('customers.detail.devices.view_sensitive');

        $items = [];

        foreach ($pops as $pop) {
            $items['pop:'.$pop->id] = $this->item('pop:'.$pop->id, $pop->type === 'mini_pop' ? 'mini_pop' : 'pop', $pop->id, 'pop_'.$pop->type,
                $pop->name, $pops->contains('id', $pop->parent_id) ? 'pop:'.$pop->parent_id : null, [
                    'has_pin' => true,
                    'latitude' => $pop->latitude,
                    'longitude' => $pop->longitude,
                    'type_label' => match ($pop->type) {
                        'pusat' => 'POP Pusat', 'cabang' => 'POP Cabang', default => 'Mini POP'
                    },
                    'url' => $user?->hasPermission('pops.view') ? route('master.pop.show', $pop) : null,
                ]);
        }

        foreach ($nodes as $node) {
            $uplink = $uplinkByTarget->get($node->id);
            $parent = match (true) {
                $uplink !== null => 'port:'.$uplink->source_infrastructure_port_id,
                $node->parent_id !== null && $nodesById->has($node->parent_id) => 'node:'.$node->parent_id,
                $node->anchor_pop_id !== null && in_array($node->anchor_pop_id, $popIds, true) => 'pop:'.$node->anchor_pop_id,
                default => null,
            };
            $isOdp = $node->nodeType?->code === 'odp';
            $items['node:'.$node->id] = $this->item('node:'.$node->id, 'node', $node->id, $node->nodeType?->code ?? 'other',
                $node->code, $parent, [
                    'name' => $node->name,
                    'type_id' => $node->node_type_id,
                    'anchor_pop_id' => $node->anchor_pop_id,
                    'type_label' => $node->nodeType?->name ?? 'Node',
                    'has_pin' => true,
                    'latitude' => $node->latitude,
                    'longitude' => $node->longitude,
                    'status' => $node->status,
                    'relation' => $uplink !== null ? 'connection' : 'location',
                    'verified' => $uplink !== null ? $uplink->isVerified() : ($isOdp ? false : null),
                    'uplink_unverified' => $isOdp && ($uplink === null || ! $uplink->isVerified()),
                    'capacity' => $node->nodeType?->has_ports ? [
                        'used' => (int) $node->used_ports_count,
                        'capacity' => (int) $node->ports_count,
                        'unit_label' => 'port pelanggan',
                        'red_zone' => $node->isInRedZone(),
                    ] : null,
                    'url' => route('network.nodes.show', $node),
                ]);
        }

        foreach ($slots as $slot) {
            $items['slot:'.$slot->id] = $this->item('slot:'.$slot->id, 'slot', $slot->id, $slot->kind->value, "Slot {$slot->number}",
                'node:'.$slot->network_node_id, [
                    'name' => $slot->label,
                    'type_label' => $slot->kind->label(),
                    'status_label' => $slot->status->label(),
                    'capacity' => ['used' => $ports->where('infrastructure_slot_id', $slot->id)->count(), 'capacity' => $slot->capacity,
                        'unit_label' => $slot->capacity_unit?->label()],
                    'url' => route('network.infrastructure-slots.show', $slot),
                ]);
        }

        foreach ($ports as $port) {
            $outgoing = $activeConnections->where('source_infrastructure_port_id', $port->id);
            $switchTarget = $port->kind === InfrastructurePortKind::SWITCH ? $outgoing->first() : null;
            $summary = $port->kind === InfrastructurePortKind::PON ? $this->ponCapacity->summary($port) : null;
            $items['port:'.$port->id] = $this->item('port:'.$port->id, 'port', $port->id, $port->kind->value, $port->displayName(),
                'slot:'.$port->infrastructure_slot_id, [
                    'type_label' => $port->kind->label(),
                    'status_label' => $this->connections->occupancyOf($port)->label(),
                    'capacity' => $summary,
                    'connection_label' => $switchTarget === null ? null
                        : ($switchTarget->target_label ?? $switchTarget->targetNode?->code ?? $switchTarget->targetPort?->displayName()),
                    'connection_verified' => $switchTarget?->isVerified(),
                    'url' => route('network.infrastructure-ports.show', $port),
                ]);
        }

        $this->attachCustomers($items, $nodes, $popIds, $canSeeSerials, $user);
        $this->attachDirectAssets($items, $popIds, $nodes, $slots, $ports);
        $this->attachServerProjection($items, $nodes);

        $children = [];
        $roots = [];
        foreach ($items as $key => $item) {
            if ($item['parent'] !== null && isset($items[$item['parent']])) {
                $children[$item['parent']][] = $key;
            } else {
                $items[$key]['parent'] = null;
                $roots[] = $key;
            }
        }

        foreach (array_keys($items) as $key) {
            $items[$key]['breadcrumb'] = $this->breadcrumb($items, $key);
        }

        return ['items' => $items, 'children' => $children, 'roots' => $roots];
    }

    /**
     * Empat metrik rekonsiliasi per POP Pusat/Cabang (Task 1/11, §16.4 #6): ODP,
     * pelanggan terhubung, port pelanggan terpakai, aset DEPLOYED.
     *
     * @return array<int, array{odp: int, customers: int, used_ports: int, assets: int}>
     */
    public function metricsByCabang(?User $user): array
    {
        $projection = $this->build($user);
        $metrics = [];
        foreach ($projection['items'] as $item) {
            if ($item['kind'] === 'pop' && in_array($item['type'], ['pop_pusat', 'pop_cabang'], true)) {
                $metrics[$item['id']] = ['odp' => 0, 'customers' => 0, 'used_ports' => 0, 'assets' => 0];
            }
        }

        foreach ($projection['items'] as $key => $item) {
            $cabangId = $this->cabangOf($projection['items'], $key);
            if ($cabangId === null || ! isset($metrics[$cabangId])) {
                continue;
            }
            if ($item['kind'] === 'node') {
                if ($item['type'] === 'odp') {
                    $metrics[$cabangId]['odp']++;
                }
                $metrics[$cabangId]['used_ports'] += $item['capacity']['used'] ?? 0;
            }
            if ($item['kind'] === 'customer') {
                $metrics[$cabangId]['customers']++;
            }
            $metrics[$cabangId]['assets'] += count(array_filter($item['direct_assets'], fn (array $asset): bool => $asset['kind'] === 'SN'));
        }

        return $metrics;
    }

    /**
     * @return Collection<int, Pop>
     */
    private function visiblePops(?User $user, ?int $cabangId): Collection
    {
        return ($user === null ? Pop::query() : Pop::forUser($user))
            ->whereIn('type', ['pusat', 'cabang', 'mini_pop'])
            ->where('status', 'active')
            ->when($cabangId !== null, fn ($query) => $query->where(fn ($scope) => $scope->where('id', $cabangId)->orWhere('parent_id', $cabangId)))
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  list<int>  $popIds
     * @return Collection<int, NetworkNode>
     */
    private function visibleNodes(array $popIds): Collection
    {
        if ($popIds === []) {
            return collect();
        }

        $placeholders = implode(',', array_fill(0, count($popIds), '?'));
        $ids = array_map(static fn (object $row): int => (int) $row->id, DB::select(<<<SQL
            WITH RECURSIVE scoped AS (
                SELECT id FROM network_nodes WHERE anchor_pop_id IN ({$placeholders})
                UNION
                SELECT child.id FROM network_nodes child JOIN scoped parent ON child.parent_id = parent.id
                WHERE child.anchor_pop_id IS NULL
            )
            SELECT id FROM scoped
            SQL, array_values($popIds)));

        return NetworkNode::query()->whereIn('id', $ids)
            ->with(['nodeType', 'ports.activeAssignment.customer.customerDevice', 'ports.activeAssignment.customer.customerTechnicalDetail',
                'ports.activeAssignment.customer.inventorySerials' => fn ($query) => $query
                    ->where('status', SerialStatus::INSTALLED->value)->with('item.category')])
            ->withCount(['ports', 'ports as used_ports_count' => fn ($query) => $query->where('is_used', true)])
            ->orderBy('code')
            ->get();
    }

    /**
     * ONT = daun visual pelanggan pada port ODP (bukan network node). Semua
     * ONT milik satu pelanggan terlihat; tanpa SN → «ONT belum tercatat».
     *
     * @param  array<string, array<string, mixed>>  $items
     * @param  Collection<int, NetworkNode>  $nodes
     * @param  list<int>  $popIds
     */
    private function attachCustomers(array &$items, Collection $nodes, array $popIds, bool $canSeeSerials, ?User $user): void
    {
        foreach ($nodes as $node) {
            if ($node->nodeType?->code !== 'odp') {
                continue;
            }
            foreach ($node->ports as $port) {
                /** @var Customer|null $customer */
                $customer = $port->activeAssignment->first()?->customer;
                if ($customer === null || ! in_array((int) $customer->pop_id, $popIds, true)) {
                    continue;
                }
                $onts = $canSeeSerials ? array_map(fn (array $modem): array => [
                    'serial_number' => $modem['serial_number'], 'name' => $modem['name'],
                ], $customer->networkPortModems()) : [];
                usort($onts, fn (array $a, array $b): int => strcmp($a['serial_number'], $b['serial_number']));

                $items['customer:'.$customer->id] = $this->item('customer:'.$customer->id, 'customer', $customer->id, 'customer_ont',
                    $customer->full_name, 'node:'.$node->id, [
                        'name' => $customer->cid ?? $customer->customer_code,
                        'type_label' => 'ONT pelanggan',
                        'port_number' => $port->number,
                        'onts' => $onts,
                        'onts_hidden' => ! $canSeeSerials,
                        'ont_label' => $canSeeSerials && $onts === [] ? 'ONT belum tercatat' : null,
                        'url' => $user?->hasPermission('customers.view') ? route('customers.show', $customer) : null,
                    ]);
            }
        }
    }

    /**
     * Aset langsung per posisi (bukan ringkasan turunan) — SN DEPLOYED pada
     * kolom lokasinya dan saldo qty non-kabel per lokasi.
     *
     * @param  array<string, array<string, mixed>>  $items
     * @param  list<int>  $popIds
     * @param  Collection<int, NetworkNode>  $nodes
     * @param  Collection<int, InfrastructureSlot>  $slots
     * @param  Collection<int, InfrastructurePort>  $ports
     */
    private function attachDirectAssets(array &$items, array $popIds, Collection $nodes, Collection $slots, Collection $ports): void
    {
        $serials = InventorySerial::query()->with('item')->where('status', SerialStatus::DEPLOYED->value)
            ->where(fn ($query) => $query->whereIn('current_mini_pop_id', $popIds)
                ->orWhereIn('current_network_node_id', $nodes->pluck('id'))
                ->orWhereIn('current_infrastructure_slot_id', $slots->pluck('id'))
                ->orWhereIn('current_infrastructure_port_id', $ports->pluck('id')))
            ->get();
        foreach ($serials as $serial) {
            $key = match (true) {
                $serial->current_infrastructure_port_id !== null => 'port:'.$serial->current_infrastructure_port_id,
                $serial->current_infrastructure_slot_id !== null => 'slot:'.$serial->current_infrastructure_slot_id,
                $serial->current_network_node_id !== null => 'node:'.$serial->current_network_node_id,
                default => 'pop:'.$serial->current_mini_pop_id,
            };
            if (isset($items[$key])) {
                $items[$key]['direct_assets'][] = ['kind' => 'SN', 'name' => $serial->item?->name, 'reference' => $serial->serial_number, 'amount' => '1'];
            }
        }

        $balances = InfrastructureBalance::query()->with('item')->where('qty', '>', 0)
            ->where(fn ($query) => $query->whereIn('pop_id', $popIds)->orWhereIn('network_node_id', $nodes->pluck('id'))
                ->orWhereIn('infrastructure_slot_id', $slots->pluck('id'))->orWhereIn('infrastructure_port_id', $ports->pluck('id')))
            ->get();
        foreach ($balances as $balance) {
            $key = match (true) {
                $balance->infrastructure_port_id !== null => 'port:'.$balance->infrastructure_port_id,
                $balance->infrastructure_slot_id !== null => 'slot:'.$balance->infrastructure_slot_id,
                $balance->network_node_id !== null => 'node:'.$balance->network_node_id,
                default => 'pop:'.$balance->pop_id,
            };
            if (isset($items[$key])) {
                $items[$key]['direct_assets'][] = ['kind' => $balance->lot_no !== '' ? 'ROLL' : 'QTY', 'name' => $balance->item?->name,
                    'reference' => $balance->lot_no ?: null, 'amount' => rtrim(rtrim(number_format((float) $balance->qty, 2, '.', ''), '0'), '.')];
            }
        }
    }

    /**
     * Server Utama ditambatkan ke POP Pusat tetapi tampil di atasnya.
     *
     * @param  array<string, array<string, mixed>>  $items
     * @param  Collection<int, NetworkNode>  $nodes
     */
    private function attachServerProjection(array &$items, Collection $nodes): void
    {
        $server = $nodes->first(fn (NetworkNode $node): bool => $node->nodeType?->code === 'server'
            && $node->parent_id === null && isset($items['pop:'.$node->anchor_pop_id]));
        if ($server === null) {
            return;
        }

        $popKey = 'pop:'.$server->anchor_pop_id;
        if ($items[$popKey]['parent'] !== null) {
            return;
        }
        $items['node:'.$server->id]['parent'] = null;
        $items[$popKey]['parent'] = 'node:'.$server->id;
        $items[$popKey]['relation'] = 'location';
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function item(string $key, string $kind, int $id, string $type, string $label, ?string $parent, array $extra = []): array
    {
        return [
            'key' => $key,
            'kind' => $kind,
            'id' => $id,
            'type' => $type,
            'label' => $label,
            'name' => null,
            'type_label' => null,
            'parent' => $parent,
            'relation' => 'location',
            'verified' => null,
            'uplink_unverified' => false,
            'status_label' => null,
            'capacity' => null,
            'has_pin' => false,
            'latitude' => null,
            'longitude' => null,
            'direct_assets' => [],
            'url' => null,
            ...$extra,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $items
     */
    private function breadcrumb(array $items, string $key): string
    {
        $chain = [];
        $guard = 0;
        $current = $key;
        while ($current !== null && isset($items[$current]) && $guard++ < 32) {
            array_unshift($chain, $items[$current]);
            $current = $items[$current]['parent'];
        }

        // Sama aturan `InfrastructureLocation::breadcrumb()`: Server (proyeksi
        // navigasi) dan POP Pusat tidak ditampilkan bila lokasi ada di bawah
        // Cabang — breadcrumb operasional dimulai dari Cabang (§12).
        $hasCabang = collect($chain)->contains(fn (array $item): bool => $item['type'] === 'pop_cabang');
        $labels = collect($chain)
            ->reject(fn (array $item): bool => $item['key'] !== $key
                && (($item['kind'] === 'node' && $item['type'] === 'server') || ($hasCabang && $item['type'] === 'pop_pusat')))
            ->pluck('label');

        return $labels->implode(' → ');
    }

    /**
     * @param  array<string, array<string, mixed>>  $items
     */
    private function cabangOf(array $items, string $key): ?int
    {
        $current = $key;
        $guard = 0;
        $pusatId = null;
        while ($current !== null && isset($items[$current]) && $guard++ < 32) {
            $item = $items[$current];
            if ($item['kind'] === 'pop' && $item['type'] === 'pop_cabang') {
                return $item['id'];
            }
            if ($item['kind'] === 'pop' && $item['type'] === 'pop_pusat') {
                $pusatId = $item['id'];
            }
            if ($item['kind'] === 'node' && $item['type'] === 'server' && $item['anchor_pop_id'] !== null
                && isset($items['pop:'.$item['anchor_pop_id']])
                && $items['pop:'.$item['anchor_pop_id']]['type'] === 'pop_pusat') {
                $pusatId = $item['anchor_pop_id'];
            }
            $current = $item['parent'];
        }

        return $pusatId;
    }
}
