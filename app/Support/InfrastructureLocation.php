<?php

namespace App\Support;

/**
 * Lokasi akhir = jenis posisi + FK ID tepat (§10.1, §16.1). Dibuat HANYA
 * oleh `InfrastructureLocationResolver`, yang menurunkan rantai leluhur dari
 * relasi ID (pops.parent_id, network_nodes.parent_id/anchor_pop_id, slot →
 * perangkat, port → slot, ODP → uplink PON aktif). Label/kode cuma untuk
 * tampilan — jangan pernah dipakai untuk membandingkan lokasi.
 *
 * `ancestors` terurut dari akar (POP teratas) sampai lokasi ini sendiri.
 */
final class InfrastructureLocation
{
    public const POP = 'pop';

    public const MINI_POP = 'mini_pop';

    public const NODE = 'node';

    public const SLOT = 'slot';

    public const PORT = 'port';

    /**
     * @param  list<array{kind: string, id: int, label: string, type: ?string}>  $ancestors
     */
    public function __construct(
        public readonly string $kind,
        public readonly int $id,
        public readonly ?int $cabangId,
        public readonly array $ancestors,
    ) {}

    public function key(): string
    {
        return $this->kind.':'.$this->id;
    }

    /**
     * @return list<string>
     */
    public function ancestorKeys(): array
    {
        return array_map(static fn (array $ancestor): string => $ancestor['kind'].':'.$ancestor['id'], $this->ancestors);
    }

    /**
     * True kalau `$other` adalah lokasi ini sendiri atau turunannya.
     */
    public function contains(self $other): bool
    {
        return in_array($this->key(), $other->ancestorKeys(), true);
    }

    public function label(): string
    {
        return $this->ancestors[array_key_last($this->ancestors)]['label'];
    }

    /**
     * Tipe node (kode `network_node_types`) bila lokasi ini node, mis. `odp`.
     */
    public function nodeTypeCode(): ?string
    {
        return $this->kind === self::NODE ? $this->ancestors[array_key_last($this->ancestors)]['type'] : null;
    }

    public function breadcrumb(): string
    {
        $labels = array_column($this->ancestors, 'label');

        // POP Pusat tidak ikut ditampilkan saat lokasi berada di bawah
        // Cabang — breadcrumb operasional dimulai dari Cabang (§12).
        if (count($this->ancestors) > 1 && ($this->ancestors[0]['type'] ?? null) === 'pusat') {
            array_shift($labels);
        }

        return implode(' → ', $labels);
    }

    /**
     * ID POP terdalam pada rantai (Mini POP bila ada, kalau tidak Cabang/POP)
     * — dipakai cek POP scope aktor.
     */
    public function deepestPopId(): ?int
    {
        $popId = null;
        foreach ($this->ancestors as $ancestor) {
            if (in_array($ancestor['kind'], [self::POP, self::MINI_POP], true)) {
                $popId = $ancestor['id'];
            }
        }

        return $popId;
    }

    /**
     * ID ancestor dengan jenis tertentu (yang terdalam), mis. Mini POP induk
     * atau perangkat induk slot.
     */
    public function ancestorId(string $kind): ?int
    {
        $found = null;
        foreach ($this->ancestors as $ancestor) {
            if ($ancestor['kind'] === $kind) {
                $found = $ancestor['id'];
            }
        }

        return $found;
    }

    /**
     * @return array{key: string, kind: string, id: int, cabang_id: ?int, breadcrumb: string, ancestors: list<array{kind: string, id: int, label: string, type: ?string}>}
     */
    public function snapshot(): array
    {
        return [
            'key' => $this->key(),
            'kind' => $this->kind,
            'id' => $this->id,
            'cabang_id' => $this->cabangId,
            'breadcrumb' => $this->breadcrumb(),
            'ancestors' => $this->ancestors,
        ];
    }
}
