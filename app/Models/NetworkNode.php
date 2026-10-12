<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Pohon simpul jaringan (ODC/ODP/splitter/dst) — model "folder di drive":
 * sebuah node BERADA di dalam node induk (`parent_id`) ATAU ditambatkan
 * langsung ke POP (`anchor_pop_id`), tidak pernah dua-duanya, tidak pernah
 * tidak ada satu pun. Aturan XOR itu ditegakkan `NetworkNodeService`
 * (Task 6) — model ini SENGAJA tidak menolak kombinasi ilegal, supaya
 * migration seeding/import bisa bertahap tanpa kena guard sebelum
 * service-nya ada.
 */
#[Fillable([
    'parent_id',
    'anchor_pop_id',
    'node_type_id',
    'code',
    'name',
    'capacity',
    'address',
    'village',
    'district',
    'city',
    'latitude',
    'longitude',
    'status',
    'notes',
])]
class NetworkNode extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'anchor_pop_id' => 'integer',
            'node_type_id' => 'integer',
            'capacity' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    /**
     * Node induk langsung di pohon `network_nodes` (bukan POP).
     *
     * @return BelongsTo<NetworkNode, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(NetworkNode::class, 'parent_id');
    }

    /**
     * Anak langsung — BUKAN rekursif. Dipakai UI pohon per level.
     *
     * @return HasMany<NetworkNode, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(NetworkNode::class, 'parent_id');
    }

    /**
     * POP tempat node ini ditambatkan langsung (kalau dia node puncak
     * subtree). Null kalau node ini berada di dalam node induk lain.
     *
     * @return BelongsTo<Pop, $this>
     */
    public function anchorPop(): BelongsTo
    {
        return $this->belongsTo(Pop::class, 'anchor_pop_id');
    }

    public function nodeType(): BelongsTo
    {
        return $this->belongsTo(NetworkNodeType::class);
    }

    /**
     * Naik rantai `parent` sampai node puncak (yang punya `anchor_pop_id`),
     * lalu resolve ke Cabang. Pohon POP dangkal (Cabang → Mini POP, lihat
     * `Pop::resolveCabangId()`), jadi "naik" di sisi POP cukup satu langkah:
     * kalau anchor-nya Mini POP (punya `parent_id`), kembalikan Cabang
     * induknya; kalau anchor-nya sudah Cabang/Pusat (`parent_id` null),
     * kembalikan anchor itu sendiri.
     *
     * Di-memo pakai `once()` — dipanggil berulang dari breadcrumb/listing
     * dalam satu request/loop tampilan, dan hasilnya tidak berubah selama
     * instance model ini hidup.
     */
    public function resolvePop(): ?Pop
    {
        return once(function (): ?Pop {
            $node = $this;
            while ($node !== null && $node->anchor_pop_id === null) {
                $node = $node->parent;
            }

            $anchor = $node?->anchorPop;

            if ($anchor === null) {
                return null;
            }

            return $anchor->parent_id !== null ? $anchor->parent : $anchor;
        });
    }

    /**
     * Ambil node yang anchor-nya ada di `$popIds`, PLUS semua turunannya di
     * pohon `network_nodes`, berapapun dalamnya — satu query recursive CTE,
     * bukan N query jalan-jalan PHP. Divalidasi jalan di sqlite (test) dan
     * MySQL 8 (produksi), keduanya dukung `WITH RECURSIVE` native.
     *
     * Binding `$popIds` SELALU lewat placeholder `?` — jangan diinterpolasi
     * ke string SQL (celah injection kalau sumbernya input scope POP user).
     *
     * @param  Builder<NetworkNode>  $query
     * @param  list<int>  $popIds
     * @return Builder<NetworkNode>
     */
    public function scopeInPops(Builder $query, array $popIds): Builder
    {
        $table = $this->getTable();

        if ($popIds === []) {
            // Scope kosong = tidak ada POP yang boleh diakses — deny-by-default,
            // bukan "semua node" (array kosong di IN (...) tanpa guard ini
            // bisa salah ditafsirkan jadi bypass filter).
            return $query->whereRaw('1 = 0');
        }

        $placeholders = implode(',', array_fill(0, count($popIds), '?'));

        $sql = <<<SQL
            WITH RECURSIVE tree AS (
                SELECT id FROM {$table} WHERE anchor_pop_id IN ({$placeholders})
                UNION ALL
                SELECT n.id FROM {$table} n JOIN tree t ON n.parent_id = t.id
            )
            SELECT id FROM tree
            SQL;

        $ids = array_map(
            static fn (object $row): int => (int) $row->id,
            DB::select($sql, array_values($popIds)),
        );

        return $query->whereIn("{$table}.id", $ids);
    }

    /**
     * Label breadcrumb lengkap: nama Cabang, (Mini POP kalau anchor-nya di
     * sana), lalu nama setiap node dari puncak subtree sampai node ini,
     * dipisah " → ". Null-safe — kalau `resolvePop()`/anchor tidak
     * terselesaikan (mis. data belum lengkap/XOR belum ditegakkan), bagian
     * POP cuma dilewati, bukan melempar error.
     */
    public function breadcrumbLabel(): string
    {
        $parts = [];

        $top = $this;
        while ($top !== null && $top->anchor_pop_id === null) {
            $top = $top->parent;
        }

        $anchor = $top?->anchorPop;

        if ($anchor !== null) {
            if ($anchor->parent_id !== null && $anchor->parent !== null) {
                $parts[] = $anchor->parent->name;
            }
            $parts[] = $anchor->name;
        }

        $nodeNames = [];
        $node = $this;
        while ($node !== null) {
            $nodeNames[] = $node->name;
            $node = $node->parent;
        }

        return implode(' → ', array_merge($parts, array_reverse($nodeNames)));
    }
}
