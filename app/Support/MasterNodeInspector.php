<?php

namespace App\Support;

use App\Models\NetworkNode;

/**
 * Bahan bersama inspector Slot/PON di Master Node (putaran UI 2 ADHOC-213).
 * Satu tempat untuk dua hal yang kalau ditulis ulang per partial/controller
 * pasti menyimpang:
 *
 * 1. Bentuk data "pilih posisi" — dipakai tombol drill-down di panel,
 *    pemilihan ulang `?node=` setelah PRG, dan (bentuk yang sama) baris JSON
 *    `network.nodes.children`. Semua URL dibuat `route()` di server; klien
 *    hanya memakai URL ini, tidak merakit path (konvensi CLAUDE.md).
 * 2. Matematika kapasitas "terpakai / kapasitas / sisa" untuk tingkat yang
 *    TIDAK dihitung `PonCapacityService` (perangkat, Slot, ODP). Kapasitas
 *    null = "belum didata" — bukan nol, bukan "aman" — supaya tampilan tidak
 *    pernah menulis "?"/NaN atau sisa palsu.
 */
final class MasterNodeInspector
{
    /**
     * @return array{id: int, type: string, label: string, path: string, url: string, panel_url: string}
     */
    public static function positionPayload(NetworkNode $node, string $path): array
    {
        return [
            'id' => $node->id,
            'type' => (string) $node->nodeType?->code,
            'label' => $node->displayName(),
            'path' => $path,
            'url' => $node->isSlot()
                ? route('network.infrastructure-slots.show', $node)
                : route('network.infrastructure-ports.show', $node),
            'panel_url' => route('network.nodes.panel', $node),
        ];
    }

    /**
     * @return array{used: int, capacity: ?int, remaining: ?int, is_full: bool, over: bool}
     */
    public static function capacity(?int $capacity, int $used): array
    {
        $known = $capacity !== null && $capacity > 0;

        return [
            'used' => $used,
            'capacity' => $known ? $capacity : null,
            'remaining' => $known ? max(0, $capacity - $used) : null,
            'is_full' => $known && $used >= $capacity,
            // Terdata melebihi kapasitas (data lama / kapasitas diturunkan):
            // ditampilkan eksplisit, bukan disembunyikan oleh max(0, …).
            'over' => $known && $used > $capacity,
        ];
    }
}
