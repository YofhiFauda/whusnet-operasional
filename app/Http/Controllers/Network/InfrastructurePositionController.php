<?php

namespace App\Http\Controllers\Network;

use App\Enums\InfrastructurePortKind;
use App\Enums\InfrastructureRecordStatus;
use App\Enums\InfrastructureSlotKind;
use App\Http\Controllers\Controller;
use App\Models\InfrastructureConnection;
use App\Models\InfrastructurePort;
use App\Models\InfrastructureSlot;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\NetworkNode;
use App\Models\TaskInfrastructureAction;
use App\Services\EffectiveAccessService;
use App\Services\InfrastructureConnectionService;
use App\Services\InfrastructureLocationResolver;
use App\Services\InfrastructurePositionService;
use App\Services\PonCapacityService;
use App\Support\InfrastructureLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Detail & aksi kontekstual posisi kanonis (Task 10, §5 "Aksi tambah
 * mengikuti konteks"): perangkat → tambah Slot, Slot → tambah PON/Port,
 * PON → hubungkan ODP. Detail hanya baca (tanpa mutasi). Semua aksi tulis
 * redirect balik ke halaman Detail (PRG) dan memeriksa POP scope aktor
 * lewat rantai lokasi.
 */
class InfrastructurePositionController extends Controller
{
    public function __construct(
        private readonly EffectiveAccessService $access,
        private readonly InfrastructureLocationResolver $resolver,
    ) {}

    public function showSlot(InfrastructureSlot $slot): View
    {
        $location = $this->authorizeLocation($this->resolver->forSlot($slot));
        $slot->load('device.nodeType', 'ports');
        $connections = app(InfrastructureConnectionService::class);

        return view('network.positions.slot', [
            'slot' => $slot,
            'location' => $location,
            'ports' => $slot->ports->map(fn (InfrastructurePort $port): array => [
                'port' => $port,
                'occupancy' => $connections->occupancyOf($port)->label(),
            ]),
            'directAssets' => $this->directSerials('current_infrastructure_slot_id', $slot->id),
            'history' => $this->history('infrastructure_slot_id', $slot->id),
            'actions' => TaskInfrastructureAction::query()->where('work_infrastructure_slot_id', $slot->id)->with('technician')->latest('id')->limit(20)->get(),
            'canManage' => auth()->user()->hasPermission('network_nodes.update'),
        ]);
    }

    public function showPort(InfrastructurePort $port, PonCapacityService $capacity): View
    {
        $location = $this->authorizeLocation($this->resolver->forPort($port));
        $port->load('slot.device');
        $connections = InfrastructureConnection::query()
            ->where(fn ($query) => $query->where('source_infrastructure_port_id', $port->id)->orWhere('target_infrastructure_port_id', $port->id))
            ->with('targetNode', 'targetPort.slot.device', 'sourcePort.slot.device')->orderByDesc('active_key')->latest('id')->get()
            ->filter(fn (InfrastructureConnection $connection): bool => $this->canAccessConnection($connection))->values();
        $cabangId = $location->cabangId;

        return view('network.positions.port', [
            'port' => $port,
            'location' => $location,
            'occupancy' => app(InfrastructureConnectionService::class)->occupancyOf($port)->label(),
            'capacity' => $port->kind === InfrastructurePortKind::PON ? $capacity->summary($port) : null,
            'connections' => $connections,
            // ODP kandidat: satu Cabang & belum punya uplink aktif (§2).
            'odpCandidates' => $port->kind === InfrastructurePortKind::PON
                ? NetworkNode::query()->whereHas('nodeType', fn ($query) => $query->where('code', 'odp'))
                    ->whereNotIn('id', InfrastructureConnection::query()->active()->whereNotNull('target_network_node_id')->select('target_network_node_id'))
                    ->orderBy('code')->get()->filter(function (NetworkNode $node) use ($cabangId): bool {
                        try {
                            $candidate = $this->resolver->forNode($node);
                        } catch (InvalidArgumentException) {
                            return false;
                        }

                        return $candidate->cabangId === $cabangId && $this->canAccessLocation($candidate);
                    })->values()
                : collect(),
            'directAssets' => $this->directSerials('current_infrastructure_port_id', $port->id),
            'history' => $this->history('infrastructure_port_id', $port->id),
            'actions' => TaskInfrastructureAction::query()->where('work_infrastructure_port_id', $port->id)->with('technician')->latest('id')->limit(20)->get(),
            'canManage' => auth()->user()->hasPermission('network_nodes.update'),
        ]);
    }

    public function storeSlot(Request $request, NetworkNode $node, InfrastructurePositionService $positions): RedirectResponse
    {
        $this->authorizeLocation($this->resolver->forNode($node));
        $validated = $request->validate([
            'number' => ['required', 'integer', 'min:1', 'max:999'],
            'code' => ['nullable', 'string', 'max:50'],
            'label' => ['nullable', 'string', 'max:100'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'is_logical' => ['nullable', 'boolean'],
        ]);

        $this->domain(fn () => $positions->createSlot($node, (int) $validated['number'], [
            'code' => $validated['code'] ?? null,
            'label' => ($validated['is_logical'] ?? false) ? InfrastructurePositionService::LOGICAL_SLOT_LABEL : ($validated['label'] ?? null),
            'capacity' => $validated['capacity'] ?? null,
            'is_logical' => (bool) ($validated['is_logical'] ?? false),
            'status' => InfrastructureRecordStatus::TERVERIFIKASI,
        ]), 'number');

        return redirect()->route('network.nodes.show', $node)->with('success', "Slot {$validated['number']} ditambahkan.");
    }

    public function updateDeviceCapacity(Request $request, NetworkNode $node, InfrastructurePositionService $positions): RedirectResponse
    {
        $this->authorizeLocation($this->resolver->forNode($node));
        $validated = $request->validate(['capacity' => ['required', 'integer', 'min:1', 'max:1000']]);
        $positions->updateDeviceCapacity($node, (int) $validated['capacity'], $request->user());

        return redirect()->route('network.nodes.show', $node)->with('success', 'Kapasitas slot perangkat diperbarui.');
    }

    public function updateSlotCapacity(Request $request, InfrastructureSlot $slot, InfrastructurePositionService $positions): RedirectResponse
    {
        $this->authorizeLocation($this->resolver->forSlot($slot));
        $validated = $request->validate(['capacity' => ['required', 'integer', 'min:1', 'max:9999']]);
        $positions->updateSlotCapacity($slot, (int) $validated['capacity'], $request->user());

        return redirect()->route('network.infrastructure-slots.show', $slot)->with('success', 'Kapasitas slot diperbarui.');
    }

    public function storePort(Request $request, InfrastructureSlot $slot, InfrastructurePositionService $positions): RedirectResponse
    {
        $this->authorizeLocation($this->resolver->forSlot($slot));
        $isPon = $slot->kind === InfrastructureSlotKind::OLT;
        $validated = $request->validate([
            'number' => ['required', 'integer', 'min:1', 'max:999'],
            'code' => ['nullable', 'string', 'max:50'],
            'capacity' => [$isPon ? 'nullable' : 'prohibited', 'integer', 'min:1', 'max:100000'],
            'red_zone_threshold' => [$isPon ? 'nullable' : 'prohibited', 'integer', 'min:1', 'max:100'],
        ]);

        $this->domain(fn () => $positions->createPort($slot, (int) $validated['number'], $slot->kind->portKind(), [
            'code' => $validated['code'] ?? null,
            'capacity' => isset($validated['capacity']) ? (int) $validated['capacity'] : null,
            'red_zone_threshold' => isset($validated['red_zone_threshold']) ? (int) $validated['red_zone_threshold'] : null,
            'status' => InfrastructureRecordStatus::TERVERIFIKASI,
        ]), 'number');

        return redirect()->route('network.infrastructure-slots.show', $slot)->with('success', $slot->kind->portKind()->label()." {$validated['number']} ditambahkan.");
    }

    public function connect(Request $request, InfrastructurePort $port, InfrastructureConnectionService $connections): RedirectResponse
    {
        $this->authorizeLocation($this->resolver->forPort($port));
        $validated = $request->validate([
            'target_network_node_id' => [Rule::requiredIf($port->kind === InfrastructurePortKind::PON), 'nullable', 'integer', Rule::exists('network_nodes', 'id')],
            'target_infrastructure_port_id' => ['nullable', 'integer', Rule::exists('infrastructure_ports', 'id')],
            'target_label' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $target = isset($validated['target_network_node_id']) ? NetworkNode::findOrFail($validated['target_network_node_id']) : null;
        if ($target !== null) {
            $this->authorizeLocation($this->resolver->forNode($target));
        }

        $targetPort = isset($validated['target_infrastructure_port_id']) ? InfrastructurePort::findOrFail($validated['target_infrastructure_port_id']) : null;
        if ($targetPort !== null) {
            $this->authorizeLocation($this->resolver->forPort($targetPort));
        }

        $this->domain(fn () => $connections->connect($port, $request->user(), targetNode: $target, targetPort: $targetPort,
            targetLabel: $validated['target_label'] ?? null, verified: true, notes: $validated['notes'] ?? null),
            'target_network_node_id');

        return redirect()->route('network.infrastructure-ports.show', $port)->with('success', 'Sambungan dicatat.');
    }

    public function verifyConnection(Request $request, InfrastructureConnection $connection, InfrastructureConnectionService $connections): RedirectResponse
    {
        abort_unless($this->canAccessConnection($connection), 403);
        $this->domain(fn () => $connections->verify($connection, $request->user()), 'connection');

        return redirect()->route('network.infrastructure-ports.show', $connection->source_infrastructure_port_id)->with('success', 'Sambungan diverifikasi.');
    }

    public function releaseConnection(Request $request, InfrastructureConnection $connection, InfrastructureConnectionService $connections): RedirectResponse
    {
        abort_unless($this->canAccessConnection($connection), 403);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->domain(fn () => $connections->release($connection, $request->user(), $validated['reason']), 'reason');

        return redirect()->route('network.infrastructure-ports.show', $connection->source_infrastructure_port_id)->with('success', 'Sambungan dilepas; histori tetap tersimpan.');
    }

    private function authorizeLocation(InfrastructureLocation $location): InfrastructureLocation
    {
        abort_unless($this->canAccessLocation($location), 403);

        return $location;
    }

    private function canAccessLocation(InfrastructureLocation $location): bool
    {
        $user = auth()->user();
        if ($this->access->hasAllPopAccess($user)) {
            return true;
        }

        $popId = $location->deepestPopId();

        return $popId !== null && in_array($popId, $this->access->getAllowedPopIds($user), true);
    }

    private function canAccessConnection(InfrastructureConnection $connection): bool
    {
        if (! $this->canAccessLocation($this->resolver->forPort($connection->sourcePort))) {
            return false;
        }
        if ($connection->targetNode !== null && ! $this->canAccessLocation($this->resolver->forNode($connection->targetNode))) {
            return false;
        }

        return $connection->targetPort === null || $this->canAccessLocation($this->resolver->forPort($connection->targetPort));
    }

    /**
     * @return Collection<int, InventorySerial>
     */
    private function directSerials(string $column, int $id)
    {
        return InventorySerial::query()->with('item')->where('status', 'deployed')->where($column, $id)->orderBy('serial_number')->get();
    }

    /**
     * @return Collection<int, InventoryTransaction>
     */
    private function history(string $suffix, int $id)
    {
        return InventoryTransaction::query()
            ->where(fn ($query) => $query->where("to_{$suffix}", $id)->orWhere("from_{$suffix}", $id))
            ->with('item', 'serial', 'fopTask', 'createdBy')->latest('id')->limit(30)->get();
    }

    private function domain(callable $callback, string $field): mixed
    {
        try {
            return $callback();
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([$field => $exception->getMessage()]);
        }
    }
}
