<?php

namespace App\Services;

use App\Enums\InfrastructurePortKind;
use App\Enums\InfrastructureSlotKind;
use App\Enums\SerialStatus;
use App\Models\FopTask;
use App\Models\InfrastructureConnection;
use App\Models\InfrastructurePort;
use App\Models\InfrastructureSlot;
use App\Models\InventorySerial;
use App\Models\NetworkNode;
use App\Models\Pop;
use App\Models\User;
use App\Support\InfrastructureLocation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sumber opsi dropdown lokasi bertingkat (§10.2) — server yang menurunkan
 * anak induk terpilih, dibatasi irisan scope yang SAMA dengan validasi
 * simpan (`InfrastructureReportLocationParser`). Browser tidak pernah
 * menerima seluruh topologi Cabang untuk disaring sendiri.
 */
class InfrastructureLocationOptionsService
{
    /** @var array<string, int> */
    public const LEVEL_DEPTH = ['pop' => 0, 'mini_pop' => 1, 'device' => 2, 'slot' => 3, 'port' => 4, 'odp' => 5];

    public function __construct(
        private readonly InfrastructureLocationResolver $resolver,
        private readonly FopTaskInfrastructureScopeService $scopes,
        private readonly EffectiveAccessService $access,
        private readonly InventoryService $inventory,
    ) {}

    /**
     * @return array{options: list<array{id: int, label: string, type: ?string, breadcrumb: string}>, allow_none: bool, disabled: bool}
     */
    public function forTask(FopTask $fopTask, User $actor, string $level, ?int $parentId): array
    {
        $root = $this->scopes->rootFor($fopTask);
        $rootDepth = $this->depthOf($root);
        $cabangId = Pop::resolveCabangId($fopTask->pop_id);
        $depth = $this->depth($level);

        if ($depth === 0) {
            return $this->result([$this->resolver->forPop(Pop::findOrFail($cabangId))], false);
        }

        $parent = $this->parentLocation($level, $parentId);
        $inScope = static fn (InfrastructureLocation $location): bool => $root->contains($location) || $location->contains($root);
        if ($parent->cabangId !== $cabangId || ! $inScope($parent)) {
            throw ValidationException::withMessages(['parent' => 'Induk berada di luar scope task.']);
        }

        [$children, $disabled] = $this->children($level, $parent);
        $children = $children->filter(fn (InfrastructureLocation $location): bool => $inScope($location) && $this->reachable($location, $actor));

        return $this->result($children->values()->all(), $depth > $rootDepth, $disabled);
    }

    /**
     * Opsi form pembuatan/edit Task FOP: belum ada akar — dibatasi Cabang
     * task dan POP scope aktor; semua tingkat di bawah Cabang opsional.
     *
     * @return array{options: list<array{id: int, label: string, type: ?string, breadcrumb: string}>, allow_none: bool, disabled: bool}
     */
    public function forScopeForm(Pop $taskPop, User $actor, string $level, ?int $parentId): array
    {
        $cabangId = Pop::resolveCabangId($taskPop->id);
        $parent = $this->parentLocation($level, $parentId);
        if ($parent->cabangId !== $cabangId || ! $this->reachable($parent, $actor, allowAncestor: true)) {
            throw ValidationException::withMessages(['parent' => 'Induk berada di luar Cabang task atau POP scope Anda.']);
        }

        [$children, $disabled] = $this->children($level, $parent);

        return $this->result(
            $children->filter(fn (InfrastructureLocation $location): bool => $this->reachable($location, $actor))->values()->all(),
            true,
            $disabled,
        );
    }

    /**
     * Aset berserial yang TERPASANG LANGSUNG di lokasi (kandidat cabut/ganti)
     * dipisah dari ringkasan aset turunannya (§10.4).
     *
     * @return array{breadcrumb: string, direct: list<array{id: int, serial_number: string, item: ?string}>, descendant_count: int}
     */
    public function assetsAt(FopTask $fopTask, User $actor, string $kind, int $id): array
    {
        $location = $this->resolver->resolve($kind, $id);
        $this->scopes->assertLocationInTaskScope($fopTask, $location, $actor, 'location');

        $column = array_key_first(array_filter($this->inventory->serialLocationColumns($location), static fn ($value) => $value !== null));
        $direct = $column === null ? collect() : InventorySerial::query()->with('item')
            ->where('status', SerialStatus::DEPLOYED->value)->where($column, $id)->orderBy('serial_number')->get();

        $subtree = $this->subtreeIds($location);
        $descendantCount = InventorySerial::query()->where('status', SerialStatus::DEPLOYED->value)
            ->whereNotIn('id', $direct->pluck('id'))
            ->where(function ($query) use ($subtree): void {
                $query->whereIn('current_infrastructure_pop_id', $subtree[InfrastructureLocation::POP] ?? [])
                    ->orWhereIn('current_mini_pop_id', $subtree[InfrastructureLocation::MINI_POP] ?? [])
                    ->orWhereIn('current_network_node_id', $subtree[InfrastructureLocation::NODE] ?? [])
                    ->orWhereIn('current_infrastructure_slot_id', $subtree[InfrastructureLocation::SLOT] ?? [])
                    ->orWhereIn('current_infrastructure_port_id', $subtree[InfrastructureLocation::PORT] ?? []);
            })->count();

        return [
            'breadcrumb' => $location->breadcrumb(),
            'direct' => $direct->map(fn (InventorySerial $serial): array => [
                'id' => $serial->id, 'serial_number' => $serial->serial_number, 'item' => $serial->item?->name,
            ])->values()->all(),
            'descendant_count' => $descendantCount,
        ];
    }

    /**
     * Jalur tetap dari Cabang sampai akar scope task — tingkat ini terkunci di
     * form (akar tidak bisa «Dikosongkan»).
     *
     * @return array{depth: int, breadcrumb: string, path: array<string, array{id: int, label: string}>}
     */
    public function rootPathFor(FopTask $fopTask): array
    {
        $root = $this->scopes->rootFor($fopTask);
        $levels = array_flip(self::LEVEL_DEPTH);
        $path = [];
        $afterPort = false;
        $cabangId = (int) $root->cabangId;

        foreach ($root->ancestors as $ancestor) {
            $level = match ($ancestor['kind']) {
                InfrastructureLocation::POP => $ancestor['id'] === $cabangId ? 'pop' : null,
                InfrastructureLocation::MINI_POP => 'mini_pop',
                InfrastructureLocation::SLOT => 'slot',
                InfrastructureLocation::PORT => 'port',
                default => $afterPort ? 'odp' : 'device',
            };
            if ($ancestor['kind'] === InfrastructureLocation::PORT) {
                $afterPort = true;
            }
            if ($level !== null) {
                $path[$level] = ['id' => $ancestor['id'], 'label' => $ancestor['label']];
            }
        }

        $depth = $this->depthOf($root);

        return [
            'depth' => $depth,
            'breadcrumb' => $root->breadcrumb(),
            'path' => array_filter($path, static fn (string $level): bool => self::LEVEL_DEPTH[$level] <= $depth, ARRAY_FILTER_USE_KEY),
            'levels' => $levels,
        ];
    }

    /**
     * Resolve the canonical subtree once, then count deployed assets in SQL.
     * Active PON uplinks replace legacy node placement; Switch links are not
     * hierarchy edges. UNION deduplicates paths and terminates corrupt cycles.
     *
     * @return array<string, list<int>>
     */
    private function subtreeIds(InfrastructureLocation $location): array
    {
        $rows = DB::select(<<<'SQL'
            WITH RECURSIVE pon_links AS (
                SELECT connection.target_network_node_id AS node_id,
                    connection.source_infrastructure_port_id AS port_id
                FROM infrastructure_connections connection
                JOIN infrastructure_ports port ON port.id = connection.source_infrastructure_port_id
                WHERE connection.active_key = 1 AND port.kind = 'pon'
                    AND connection.target_network_node_id IS NOT NULL
            ), edges AS (
                SELECT CASE WHEN parent.type = 'mini_pop' THEN 'mini_pop' ELSE 'pop' END AS parent_kind,
                    parent.id AS parent_id,
                    CASE WHEN child.type = 'mini_pop' THEN 'mini_pop' ELSE 'pop' END AS kind, child.id AS id
                FROM pops child JOIN pops parent ON parent.id = child.parent_id
                UNION ALL
                SELECT CASE WHEN node.parent_id IS NOT NULL THEN 'node'
                        WHEN pop.type = 'mini_pop' THEN 'mini_pop' ELSE 'pop' END,
                    COALESCE(node.parent_id, node.anchor_pop_id), 'node', node.id
                FROM network_nodes node LEFT JOIN pops pop ON pop.id = node.anchor_pop_id
                WHERE NOT EXISTS (SELECT 1 FROM pon_links WHERE pon_links.node_id = node.id)
                UNION ALL
                SELECT 'node', network_node_id, 'slot', id FROM infrastructure_slots
                UNION ALL
                SELECT 'slot', infrastructure_slot_id, 'port', id FROM infrastructure_ports
                UNION ALL
                SELECT 'port', port_id, 'node', node_id FROM pon_links
            ), subtree (kind, id) AS (
                SELECT CAST(? AS CHAR(16)), ?
                UNION
                SELECT edge.kind, edge.id FROM edges edge
                    JOIN subtree parent ON edge.parent_kind = parent.kind AND edge.parent_id = parent.id
            )
            SELECT kind, id FROM subtree
            SQL, [$location->kind, $location->id]);

        $ids = [];
        foreach ($rows as $row) {
            $ids[$row->kind][] = (int) $row->id;
        }

        return $ids;
    }

    private function depth(string $level): int
    {
        if (! array_key_exists($level, self::LEVEL_DEPTH)) {
            throw ValidationException::withMessages(['level' => 'Tingkat lokasi tidak dikenal.']);
        }

        return self::LEVEL_DEPTH[$level];
    }

    private function depthOf(InfrastructureLocation $root): int
    {
        return match ($root->kind) {
            InfrastructureLocation::POP => 0,
            InfrastructureLocation::MINI_POP => 1,
            InfrastructureLocation::SLOT => 3,
            InfrastructureLocation::PORT => 4,
            default => in_array(InfrastructureLocation::PORT, array_column($root->ancestors, 'kind'), true) ? 5 : 2,
        };
    }

    private function parentLocation(string $level, ?int $parentId): InfrastructureLocation
    {
        $kind = match ($this->depth($level)) {
            1 => InfrastructureLocation::POP,
            2 => InfrastructureLocation::MINI_POP,
            3 => InfrastructureLocation::NODE,
            4 => InfrastructureLocation::SLOT,
            5 => InfrastructureLocation::PORT,
            default => null,
        };
        if ($kind === null || $parentId === null) {
            throw ValidationException::withMessages(['parent' => 'Induk wajib dipilih.']);
        }

        $parent = $this->resolver->resolve($kind, $parentId);
        $valid = match ($kind) {
            InfrastructureLocation::POP => $parent->kind === InfrastructureLocation::POP,
            InfrastructureLocation::MINI_POP => $parent->kind === InfrastructureLocation::MINI_POP,
            InfrastructureLocation::NODE => InfrastructureSlotKind::forDeviceTypeCode($parent->nodeTypeCode()) !== null,
            default => true,
        };
        if (! $valid) {
            throw ValidationException::withMessages(['parent' => 'Jenis induk tidak cocok dengan tingkat lokasi.']);
        }

        return $parent;
    }

    /**
     * @return array{0: Collection<int, InfrastructureLocation>, 1: bool}
     */
    private function children(string $level, InfrastructureLocation $parent): array
    {
        return match ($level) {
            'mini_pop' => [Pop::query()->where('parent_id', $parent->id)->where('type', 'mini_pop')->orderBy('name')->get()
                ->map(fn (Pop $pop) => $this->resolver->forPop($pop)), false],
            'device' => [NetworkNode::query()->with('nodeType')->where('anchor_pop_id', $parent->id)
                ->whereHas('nodeType', fn ($query) => $query->whereIn('code', ['olt_chassis', 'switch_mini_pop']))
                ->orderBy('code')->get()->map(fn (NetworkNode $node) => $this->resolver->forNode($node)), false],
            'slot' => [InfrastructureSlot::query()->with('device')->where('network_node_id', $parent->id)->orderBy('number')->get()
                ->map(fn (InfrastructureSlot $slot) => $this->resolver->forSlot($slot)), false],
            'port' => [InfrastructurePort::query()->with('slot.device')->where('infrastructure_slot_id', $parent->id)->orderBy('number')->get()
                ->map(fn (InfrastructurePort $port) => $this->resolver->forPort($port)), false],
            'odp' => $this->odpChildren($parent),
            default => throw ValidationException::withMessages(['level' => 'Tingkat lokasi tidak dikenal.']),
        };
    }

    /**
     * @return array{0: Collection<int, InfrastructureLocation>, 1: bool}
     */
    private function odpChildren(InfrastructureLocation $port): array
    {
        $model = InfrastructurePort::findOrFail($port->id);
        if ($model->kind !== InfrastructurePortKind::PON) {
            // Jalur Switch berhenti di Port (§10.2) — dropdown ODP disabled.
            return [collect(), true];
        }

        $odps = NetworkNode::query()->with('nodeType')
            ->whereIn('id', InfrastructureConnection::query()->active()->where('source_infrastructure_port_id', $model->id)->select('target_network_node_id'))
            ->whereHas('nodeType', fn ($query) => $query->where('code', 'odp'))
            ->orderBy('code')->get();

        return [$odps->map(fn (NetworkNode $odp) => $this->resolver->forNode($odp)), false];
    }

    private function reachable(InfrastructureLocation $location, User $actor, bool $allowAncestor = false): bool
    {
        if ($this->access->hasAllPopAccess($actor)) {
            return true;
        }

        $allowed = $this->access->getAllowedPopIds($actor);
        $popId = $location->deepestPopId();
        if ($popId !== null && in_array($popId, $allowed, true)) {
            return true;
        }

        // POP Cabang tetap boleh jadi induk navigasi untuk aktor yang hanya
        // punya scope Mini POP di bawahnya.
        return $allowAncestor && Pop::query()->whereIn('id', $allowed)->where('parent_id', $location->id)->exists();
    }

    /**
     * @param  list<InfrastructureLocation>  $locations
     * @return array{options: list<array{id: int, label: string, type: ?string, breadcrumb: string}>, allow_none: bool, disabled: bool}
     */
    private function result(array $locations, bool $allowNone, bool $disabled = false): array
    {
        return [
            'options' => array_map(fn (InfrastructureLocation $location): array => [
                'id' => $location->id,
                'label' => $location->label(),
                'type' => $location->ancestors[array_key_last($location->ancestors)]['type'],
                'breadcrumb' => $location->breadcrumb(),
            ], $locations),
            'allow_none' => $allowNone,
            'disabled' => $disabled,
        ];
    }
}
