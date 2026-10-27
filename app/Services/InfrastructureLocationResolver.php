<?php

namespace App\Services;

use App\Models\NetworkNode;
use App\Models\Pop;
use App\Support\InfrastructureLocation;
use InvalidArgumentException;

/**
 * Satu sumber "lokasi ini berada di bawah apa" untuk SEMUA jalur (Task FOP,
 * laporan, Gudang, Master Node, Peta). Leluhur diturunkan berbasis ID:
 *
 * - POP/Mini POP → `pops.parent_id`.
 * - Node (termasuk Slot, PON, Port Switch) → HANYA rantai `parent_id` sampai
 *   node ber-`anchor_pop_id`, lalu rantai POP. ODP kini anak langsung PON,
 *   jadi leluhurnya otomatis PON → Slot → OLT → Mini POP → Cabang tanpa
 *   lookup koneksi. ODP lama yang masih menempel langsung di POP tetap
 *   terselesaikan lewat `anchor_pop_id`.
 *
 * Koneksi (`infrastructure_connections`) sengaja TIDAK dipakai untuk menurunkan
 * lokasi: kalau dipakai lagi, pindah uplink akan diam-diam mengubah scope
 * cabang sebuah node. Pemakaian PON terverifikasi urusan `PonCapacityService`.
 */
class InfrastructureLocationResolver
{
    private const MAX_DEPTH = 32;

    public function resolve(string $kind, int $id): InfrastructureLocation
    {
        return match ($kind) {
            InfrastructureLocation::POP, InfrastructureLocation::MINI_POP => $this->forPop(Pop::findOrFail($id)),
            InfrastructureLocation::NODE => $this->forNode($this->loadNode($id)),
            InfrastructureLocation::SLOT => $this->forSlot($this->loadNode($id)),
            InfrastructureLocation::PORT => $this->forPort($this->loadNode($id)),
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
        return $this->walk($node, InfrastructureLocation::NODE);
    }

    public function forSlot(NetworkNode $slot): InfrastructureLocation
    {
        $slot->loadMissing('nodeType');
        if (! $slot->isSlot()) {
            throw new InvalidArgumentException("Node {$slot->code} bukan Slot");
        }

        return $this->walk($slot, InfrastructureLocation::SLOT);
    }

    public function forPort(NetworkNode $port): InfrastructureLocation
    {
        $port->loadMissing('nodeType');
        if (! $port->isPort()) {
            throw new InvalidArgumentException("Node {$port->code} bukan PON atau Port Switch");
        }

        return $this->walk($port, InfrastructureLocation::PORT);
    }

    private function loadNode(int $id): NetworkNode
    {
        return NetworkNode::query()->with('nodeType')->findOrFail($id);
    }

    /**
     * Naik lewat `parent_id`. nodeType dimuat di tiap hop supaya `isSlot()`/
     * `isPort()` tidak memicu query tambahan per node lewat typeCode().
     */
    private function walk(NetworkNode $node, string $kind): InfrastructureLocation
    {
        $node->loadMissing('nodeType');
        $nodes = [$this->entry($node)];
        $current = $node;
        $guard = 0;
        while ($current->parent_id !== null) {
            if (++$guard > self::MAX_DEPTH) {
                throw new InvalidArgumentException('Rantai node melingkar.');
            }
            $current = $this->loadNode($current->parent_id);
            $nodes[] = $this->entry($current);
        }

        if ($current->anchor_pop_id === null) {
            throw new InvalidArgumentException("Node {$node->code} belum ditambatkan ke POP mana pun.");
        }

        $pop = $this->forPop(Pop::findOrFail($current->anchor_pop_id));

        return new InfrastructureLocation($kind, $node->id, $pop->cabangId, [...$pop->ancestors, ...array_reverse($nodes)]);
    }

    /**
     * @return array{kind: string, id: int, label: string, type: ?string}
     */
    private function entry(NetworkNode $node): array
    {
        $isSlot = $node->isSlot();
        $isPort = $node->isPort();

        return [
            'kind' => $isSlot ? InfrastructureLocation::SLOT : ($isPort ? InfrastructureLocation::PORT : InfrastructureLocation::NODE),
            'id' => $node->id,
            'label' => ($isSlot || $isPort) ? $node->displayName() : ($node->code ?: $node->name),
            'type' => $node->nodeType?->code,
        ];
    }
}
