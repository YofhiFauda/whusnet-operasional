<?php

namespace App\Services;

use App\Enums\InfrastructurePortKind;
use App\Models\InfrastructureConnection;
use App\Models\InfrastructurePort;
use App\Models\InfrastructureSlot;
use App\Models\NetworkNode;
use App\Models\Pop;
use App\Support\InfrastructureLocation;
use InvalidArgumentException;

/**
 * Satu sumber "lokasi ini berada di bawah apa" untuk SEMUA jalur (Task FOP,
 * laporan, Gudang, Master Node, Peta). Leluhur diturunkan berbasis ID:
 *
 * - POP/Mini POP → `pops.parent_id`.
 * - Node → rantai `parent_id` sampai `anchor_pop_id`; KECUALI node yang punya
 *   uplink PON aktif (ODP): leluhurnya PON → Slot → OLT (§2 "ODP ditautkan
 *   ke PON"). Koneksi Port Switch tidak pernah menjadikan tujuan anak Switch.
 * - Slot → perangkat; Port → slot.
 *
 * Uplink yang belum terverifikasi tetap menjadi leluhur untuk scope (posisi
 * fisiknya memang di sana), tapi TIDAK dihitung sebagai pemakaian PON
 * terverifikasi — itu urusan `PonCapacityService`.
 */
class InfrastructureLocationResolver
{
    private const MAX_DEPTH = 32;

    public function resolve(string $kind, int $id): InfrastructureLocation
    {
        return match ($kind) {
            InfrastructureLocation::POP, InfrastructureLocation::MINI_POP => $this->forPop(Pop::findOrFail($id)),
            InfrastructureLocation::NODE => $this->forNode(NetworkNode::findOrFail($id)),
            InfrastructureLocation::SLOT => $this->forSlot(InfrastructureSlot::findOrFail($id)),
            InfrastructureLocation::PORT => $this->forPort(InfrastructurePort::findOrFail($id)),
            default => throw new InvalidArgumentException("Jenis lokasi {$kind} tidak dikenal."),
        };
    }

    public function forPop(Pop $pop): InfrastructureLocation
    {
        $chain = [];
        $current = $pop;
        $guard = 0;
        while ($current !== null) {
            if (++$guard > self::MAX_DEPTH) {
                throw new InvalidArgumentException('Rantai POP melingkar.');
            }
            $chain[] = [
                'kind' => $current->type === 'mini_pop' ? InfrastructureLocation::MINI_POP : InfrastructureLocation::POP,
                'id' => $current->id,
                'label' => $current->name,
                'type' => $current->type,
            ];
            $current = $current->parent_id ? Pop::find($current->parent_id) : null;
        }
        $chain = array_reverse($chain);
        $last = $chain[array_key_last($chain)];

        return new InfrastructureLocation($last['kind'], $pop->id, Pop::resolveCabangId($pop->id), $chain);
    }

    public function forNode(NetworkNode $node): InfrastructureLocation
    {
        $node->loadMissing('nodeType');
        $self = ['kind' => InfrastructureLocation::NODE, 'id' => $node->id, 'label' => $node->code ?: $node->name, 'type' => $node->nodeType?->code];

        $uplink = InfrastructureConnection::query()->active()
            ->where('target_network_node_id', $node->id)
            ->whereHas('sourcePort', fn ($query) => $query->where('kind', InfrastructurePortKind::PON->value))
            ->with('sourcePort')
            ->first();
        if ($uplink?->sourcePort !== null) {
            $base = $this->forPort($uplink->sourcePort);

            return new InfrastructureLocation(InfrastructureLocation::NODE, $node->id, $base->cabangId, [...$base->ancestors, $self]);
        }

        $nodes = [$self];
        $current = $node;
        $guard = 0;
        while ($current->parent_id !== null) {
            if (++$guard > self::MAX_DEPTH) {
                throw new InvalidArgumentException('Rantai node melingkar.');
            }
            $current = NetworkNode::query()->with('nodeType')->findOrFail($current->parent_id);
            $nodes[] = ['kind' => InfrastructureLocation::NODE, 'id' => $current->id, 'label' => $current->code ?: $current->name, 'type' => $current->nodeType?->code];
        }

        if ($current->anchor_pop_id === null) {
            throw new InvalidArgumentException("Node {$node->code} belum ditambatkan ke POP mana pun.");
        }

        $pop = $this->forPop(Pop::findOrFail($current->anchor_pop_id));

        return new InfrastructureLocation(InfrastructureLocation::NODE, $node->id, $pop->cabangId, [...$pop->ancestors, ...array_reverse($nodes)]);
    }

    public function forSlot(InfrastructureSlot $slot): InfrastructureLocation
    {
        $device = $this->forNode($slot->device ?? NetworkNode::findOrFail($slot->network_node_id));

        return new InfrastructureLocation(InfrastructureLocation::SLOT, $slot->id, $device->cabangId, [
            ...$device->ancestors,
            ['kind' => InfrastructureLocation::SLOT, 'id' => $slot->id, 'label' => "Slot {$slot->number}", 'type' => $slot->kind->value],
        ]);
    }

    public function forPort(InfrastructurePort $port): InfrastructureLocation
    {
        $slot = $this->forSlot($port->slot ?? InfrastructureSlot::findOrFail($port->infrastructure_slot_id));

        return new InfrastructureLocation(InfrastructureLocation::PORT, $port->id, $slot->cabangId, [
            ...$slot->ancestors,
            ['kind' => InfrastructureLocation::PORT, 'id' => $port->id, 'label' => $port->displayName(), 'type' => $port->kind->value],
        ]);
    }
}
