<?php

namespace App\Services;

use App\Enums\InfrastructurePortKind;
use App\Enums\SerialStatus;
use App\Models\InfrastructureBalance;
use App\Models\InfrastructureConnection;
use App\Models\InventorySerial;
use App\Models\NetworkNode;
use App\Models\Pop;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * SATU proyeksi hierarki infrastruktur untuk Master Node, Peta, dan metrik
 * rekonsiliasi (Task 10/11, rancangan "Slot & PON sebagai node" §7):
 *
 *   Server Utama → POP Pusat → POP Cabang → Mini POP
 *     ├── OLT → Slot → PON → ODP → ONT pelanggan
 *     └── Switch → Slot → Port Switch (berhenti; tujuan port = info koneksi)
 *
 * Sejak Task 11, Slot/PON/Port Switch adalah baris `network_nodes` SUNGGUHAN
 * (parent_id), BUKAN lagi tabel `infrastructure_slots`/`infrastructure_ports`
 * terpisah — item proyeksi karena itu hanya ada dua `kind`: `pop` dan `node`.
 * Tidak ada lagi `slot:`/`port:` sebagai kind terpisah.
 *
 * LAZY LOAD (§7 rancangan): tingkat Slot ke bawah (Slot, PON/Port Switch, ODP
 * yang jadi anak PON) TIDAK dimuat di proyeksi default (`$withPositions =
 * false`, dipakai Master Node) — perangkat (OLT/Switch) ditandai
 * `has_children` dan sisanya diambil lewat endpoint
 * `network.nodes.children` saat dibuka. `$withPositions = true` (dipakai
 * Peta & `metricsByCabang()`) memuat seluruh subtree seperti sebelumnya,
 * karena kanvas Peta & metrik ODP butuh rantai lengkap sekaligus.
 *
 * ODP lama yang masih ditambatkan LANGSUNG ke POP (anchor_pop_id, bukan
 * PON) — peninggalan sebelum keputusan "ODP wajib punya PON" — tetap tampil
 * sejajar perangkat lain di bawah POP-nya; keduanya sama-sama node
 * top-level (parent_id null), jadi tidak kena lazy load.
 *
 * Scope: hanya POP yang boleh dilihat aktor (`Pop::forUser`) dan node di
 * dalamnya; SN ONT pelanggan butuh `customers.detail.devices.view_sensitive`.
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
    public function build(?User $user, ?int $cabangId = null, bool $withPositions = false): array
    {
        $pops = $this->visiblePops($user, $cabangId);
        $popIds = $pops->pluck('id')->all();
        $nodes = $this->visibleNodes($popIds, $withPositions);
        $nodesById = $nodes->keyBy('id');

        // Koneksi non-pohon (§3) HANYA untuk Port Switch -> Port Switch/node/label;
        // ODP->PON murni parent_id, tidak lagi lewat tabel ini (Task 9).
        $switchPortIds = $nodes->filter(fn (NetworkNode $node): bool => $node->portKind() === InfrastructurePortKind::SWITCH)
            ->pluck('id')->all();
        $activeConnections = $switchPortIds !== []
            ? InfrastructureConnection::query()->active()->whereIn('source_infrastructure_port_id', $switchPortIds)
                ->with('targetNode', 'targetPort')->get()
            : collect();

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
            $parent = match (true) {
                $node->parent_id !== null && $nodesById->has($node->parent_id) => 'node:'.$node->parent_id,
                $node->anchor_pop_id !== null && in_array($node->anchor_pop_id, $popIds, true) => 'pop:'.$node->anchor_pop_id,
                default => null,
            };
            $isSwitchPort = $node->portKind() === InfrastructurePortKind::SWITCH;
            $outgoing = $isSwitchPort ? $activeConnections->firstWhere('source_infrastructure_port_id', $node->id) : null;
            $capacity = match (true) {
                $node->isPon() => $this->ponCapacity->summary($node),
                (bool) $node->nodeType?->has_ports => [
                    'used' => (int) $node->used_ports_count,
                    'capacity' => (int) $node->ports_count,
                    'unit_label' => 'port pelanggan',
                    'red_zone' => $node->isInRedZone(),
                ],
                default => null,
            };

            $items['node:'.$node->id] = $this->item('node:'.$node->id, 'node', $node->id, $node->nodeType?->code ?? 'other',
                $node->isPosition() ? $node->displayName() : $node->code, $parent, [
                    'name' => $node->name,
                    'type_id' => $node->node_type_id,
                    'anchor_pop_id' => $node->anchor_pop_id,
                    'type_label' => $node->nodeType?->name ?? 'Node',
                    'has_pin' => ! $node->isPosition(),
                    'latitude' => $node->latitude,
                    'longitude' => $node->longitude,
                    'status' => $node->status,
                    'status_label' => $node->isPosition() ? $node->record_status?->label() : null,
                    'has_children' => (int) ($node->children_count ?? 0) > 0,
                    'capacity' => $capacity,
                    'connection_label' => $outgoing === null ? null
                        : ($outgoing->target_network_node_id !== null && ! $nodesById->has($outgoing->target_network_node_id)
                            || $outgoing->target_infrastructure_port_id !== null && ! $nodesById->has($outgoing->target_infrastructure_port_id)
                            ? 'Tujuan di luar scope'
                            : ($outgoing->target_label ?? $outgoing->targetNode?->code ?? $outgoing->targetPort?->displayName())),
                    'connection_verified' => $outgoing?->isVerified(),
                    'url' => route('network.nodes.show', $node),
                ]);
        }

        $this->attachDirectAssets($items, $popIds, $nodes);
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
     * pelanggan terhubung, port pelanggan terpakai, aset DEPLOYED. Selalu
     * lewat subtree LENGKAP ($withPositions) — kalau tidak, ODP yang jadi
     * anak PON tidak pernah dihitung karena lazy load.
     *
     * @return array<int, array{odp: int, customers: int, used_ports: int, assets: int}>
     */
    public function metricsByCabang(?User $user): array
    {
        $projection = $this->build($user, withPositions: true);
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
                    // Hanya port pelanggan ODP yang dihitung — PON juga
                    // punya 'capacity.used' (pelanggan aktif), tapi itu
                    // satuan berbeda dan pelanggan yang sama sudah terhitung
                    // lewat port ODP-nya; menjumlahkan keduanya dobel-hitung
                    // sejak PON jadi item `kind = node` (Task 11).
                    $metrics[$cabangId]['used_ports'] += $item['capacity']['used'] ?? 0;
                }
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
     * Node yang boleh dilihat aktor. `$withPositions = false` (default,
     * Master Node) HANYA memuat node top-level (`anchor_pop_id` terisi —
     * perangkat OLT/Switch/Server, dan ODP lama yang masih anchor-POP
     * langsung) sehingga Slot/PON/Port/ODP-di-bawah-PON tidak pernah ikut
     * sebelum diminta lewat endpoint lazy. `$withPositions = true` (Peta,
     * metrik) memuat seluruh subtree lewat CTE rekursif seperti proyeksi
     * lama.
     *
     * @param  list<int>  $popIds
     * @return Collection<int, NetworkNode>
     */
    private function visibleNodes(array $popIds, bool $withPositions): Collection
    {
        if ($popIds === []) {
            return collect();
        }

        $query = NetworkNode::query()
            ->with(['nodeType', 'ports.activeAssignment.customer.customerDevice', 'ports.activeAssignment.customer.customerTechnicalDetail',
                'ports.activeAssignment.customer.inventorySerials' => fn ($q) => $q
                    ->where('status', SerialStatus::INSTALLED->value)->with('item.category')])
            ->withCount(['ports', 'ports as used_ports_count' => fn ($q) => $q->where('is_used', true), 'children'])
            ->orderBy('code');

        if (! $withPositions) {
            return $query->whereIn('anchor_pop_id', $popIds)->get();
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

        return $query->whereIn('id', $ids)->get();
    }

    /**
     * Aset langsung per posisi (bukan ringkasan turunan) — SN DEPLOYED pada
     * kolom lokasinya dan saldo qty non-kabel per lokasi. Kolom
     * `current_infrastructure_slot_id`/`current_infrastructure_port_id`
     * (nama historis dipertahankan, §4 rancangan) kini menunjuk
     * `network_nodes.id` juga, jadi ketiganya diproyeksikan ke `node:` yang
     * sama — `isset($items[$key])` otomatis menyaring posisi yang belum
     * dimuat (lazy).
     *
     * @param  array<string, array<string, mixed>>  $items
     * @param  list<int>  $popIds
     * @param  Collection<int, NetworkNode>  $nodes
     */
    private function attachDirectAssets(array &$items, array $popIds, Collection $nodes): void
    {
        $nodeIds = $nodes->pluck('id')->all();
        $serials = InventorySerial::query()->with('item')->where('status', SerialStatus::DEPLOYED->value)
            ->where(fn ($query) => $query->whereIn('current_infrastructure_pop_id', $popIds)
                ->orWhereIn('current_mini_pop_id', $popIds)
                ->orWhereIn('current_network_node_id', $nodeIds)
                ->orWhereIn('current_infrastructure_slot_id', $nodeIds)
                ->orWhereIn('current_infrastructure_port_id', $nodeIds))
            ->get();
        foreach ($serials as $serial) {
            $key = match (true) {
                $serial->current_infrastructure_port_id !== null => 'node:'.$serial->current_infrastructure_port_id,
                $serial->current_infrastructure_slot_id !== null => 'node:'.$serial->current_infrastructure_slot_id,
                $serial->current_network_node_id !== null => 'node:'.$serial->current_network_node_id,
                $serial->current_mini_pop_id !== null => 'pop:'.$serial->current_mini_pop_id,
                default => 'pop:'.$serial->current_infrastructure_pop_id,
            };
            if (isset($items[$key])) {
                $items[$key]['direct_assets'][] = ['kind' => 'SN', 'name' => $serial->item?->name, 'item_code' => $serial->item?->code, 'reference' => $serial->serial_number, 'amount' => '1'];
            }
        }

        $balances = InfrastructureBalance::query()->with('item')->where('qty', '>', 0)
            ->where(fn ($query) => $query->whereIn('pop_id', $popIds)
                ->orWhereIn('network_node_id', $nodeIds)
                ->orWhereIn('infrastructure_slot_id', $nodeIds)
                ->orWhereIn('infrastructure_port_id', $nodeIds))
            ->get();
        foreach ($balances as $balance) {
            $key = match (true) {
                $balance->infrastructure_port_id !== null => 'node:'.$balance->infrastructure_port_id,
                $balance->infrastructure_slot_id !== null => 'node:'.$balance->infrastructure_slot_id,
                $balance->network_node_id !== null => 'node:'.$balance->network_node_id,
                default => 'pop:'.$balance->pop_id,
            };
            if (isset($items[$key])) {
                $items[$key]['direct_assets'][] = ['kind' => $balance->lot_no !== '' ? 'ROLL' : 'QTY', 'name' => $balance->item?->name,
                    'item_code' => $balance->item?->code, 'reference' => $balance->lot_no ?: null, 'amount' => rtrim(rtrim(number_format((float) $balance->qty, 2, '.', ''), '0'), '.')];
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
            'has_children' => false,
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
