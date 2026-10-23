<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditLogs;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
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
    use HasFactory, RecordsAuditLogs;

    protected string $auditModule = 'Node Jaringan';

    // SENGAJA cuma 'created', bukan 'updated': `NetworkNodeService::move()`
    // udah nulis AuditLog eksplisit sendiri saat `$node->update([...])` —
    // kalau 'updated' ikut di sini, satu pemindahan jadi dua baris audit.
    // Belum ada jalur edit field node lain (NetworkNodeController cuma
    // store/addPorts/updatePort). Kalau nanti ada form edit node, audit-nya
    // tulis eksplisit di jalur itu atau pindahkan move() ke trait ini —
    // jangan dua-duanya.
    protected array $auditEvents = ['created'];

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
    /**
     * @return array{address:?string,village:?string,district:?string,city:?string,latitude:mixed,longitude:mixed,source_node_id:?int,source_pop_id:?int,inherited_fields:list<string>}
     */
    public function effectiveLocation(): array
    {
        $ancestors = [];
        $current = $this;
        while ($current !== null) {
            $ancestors[] = $current;
            $current = $current->resolveParentNode();
        }

        $top = end($ancestors) ?: null;
        $pop = $top?->resolveAnchorPop();
        $fields = ['address', 'village', 'district', 'city', 'latitude', 'longitude'];
        $location = [];
        $sources = [];
        $inheritedFields = [];

        foreach ($fields as $field) {
            foreach ($ancestors as $ancestor) {
                if ($ancestor->{$field} !== null && $ancestor->{$field} !== '') {
                    $location[$field] = $ancestor->{$field};
                    $sources[$field] = $ancestor;
                    break;
                }
            }

            if (! array_key_exists($field, $location)) {
                $location[$field] = $pop?->{$field};
                $sources[$field] = null;
            }

            if ($location[$field] !== null && $sources[$field]?->id !== $this->id) {
                $inheritedFields[] = $field;
            }
        }

        $addressSource = $sources['address'];

        return [
            ...$location,
            'source_node_id' => $addressSource?->id,
            'source_pop_id' => $addressSource === null ? $pop?->id : null,
            'inherited_fields' => $inheritedFields,
        ];
    }

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

    public function oltSlot(): HasOne
    {
        return $this->hasOne(OltSlot::class, 'card_node_id');
    }

    /**
     * Slot milik chassis OLT ini (node bertipe `olt_chassis`) — satu Mini POP
     * bisa punya beberapa chassis, jadi nomor slot discope per-chassis lewat
     * relasi ini, bukan lagi per-POP (`Pop::oltSlots()` dihapus Task 2).
     *
     * @return HasMany<OltSlot, $this>
     */
    public function oltSlots(): HasMany
    {
        return $this->hasMany(OltSlot::class, 'chassis_node_id');
    }

    /**
     * Slot kanonis (OLT/Switch) — pengganti `oltSlots()` untuk struktur baru.
     * `oltSlots()` tetap dibaca untuk histori/migrasi.
     *
     * @return HasMany<InfrastructureSlot, $this>
     */
    public function infrastructureSlots(): HasMany
    {
        return $this->hasMany(InfrastructureSlot::class)->orderBy('number');
    }

    public function nodeType(): BelongsTo
    {
        return $this->belongsTo(NetworkNodeType::class);
    }

    /**
     * Port node ini, selalu terurut `number` ASC — urutan ini dipakai UI
     * daftar port & kalkulasi kapasitas, jadi jangan diandalkan dari urutan
     * insert.
     *
     * @return HasMany<NetworkNodePort, $this>
     */
    public function ports(): HasMany
    {
        return $this->hasMany(NetworkNodePort::class)->orderBy('number');
    }

    /**
     * SN serialized (modem, ONT, dst) yang SEDANG dideploy netap di node ini
     * (`InventorySerial::status = DEPLOYED`). Reverse direction dari
     * `current_network_node_id` (Task 8) — Task 8 sendiri tidak menambah
     * relasi balik ini di `NetworkNode`, baru ditambahkan di Task 13 supaya
     * halaman node bisa menampilkan aset Gudang yang nempel.
     *
     * @return HasMany<InventorySerial, $this>
     */
    public function deployedSerials(): HasMany
    {
        return $this->hasMany(InventorySerial::class, 'current_network_node_id');
    }

    /**
     * Aksi laporan O-REQ/INFR-REQ yang perangkat induknya node ini (termasuk
     * slot/port di bawah OLT/Switch), dipakai untuk riwayat & badge perangkat baru.
     *
     * @return HasMany<TaskInfrastructureAction, $this>
     */
    public function deviceInfrastructureActions(): HasMany
    {
        return $this->hasMany(TaskInfrastructureAction::class, 'device_network_node_id');
    }

    /**
     * Saldo stok QUANTITY/ROLL yang dideploy ke node ini (lihat docblock
     * `InfrastructureBalance` — proyeksi dari ledger `inventory_transactions`,
     * bukan sumber kebenaran sendiri). Reverse direction `InfrastructureBalance::networkNode()`.
     *
     * @return HasMany<InfrastructureBalance, $this>
     */
    public function infrastructureBalances(): HasMany
    {
        return $this->hasMany(InfrastructureBalance::class);
    }

    /**
     * Ringkasan jumlah aset Gudang per barang di node ini — SN/lot/teknisi/
     * jam/nomor task SENGAJA tidak disertakan di sini. Master Node cuma
     * butuh "berapa barang apa" buat kerja; detail lengkap itu domain Peta
     * Infrastruktur (`InfrastructureMapService`), lihat
     * docs/plan/infrastruktur/rancangan-pembagian-detail-master-node-vs-peta-infrastruktur.md.
     *
     * Dihitung di model, bukan `@php` inline di Blade — file
     * `network/nodes/index.blade.php` sudah sangat besar & bernested, dan
     * blok `@php...@endphp` tambahan di titik tertentu terbukti membuat
     * compiler Blade gagal (unexpected end of file) meski sintaksnya sendiri
     * valid berdiri sendiri. Panggil method ini, jangan tulis ulang logicnya
     * di view manapun.
     *
     * @return Collection<int, array{name: string, unit: ?string, qty: float}>
     */
    public function assetSummary(): Collection
    {
        return $this->deployedSerials
            ->groupBy('item_id')
            ->map(fn ($group) => [
                'name' => $group->first()->item?->name ?: 'Barang tanpa nama',
                'unit' => $group->first()->item?->unit,
                'qty' => (float) $group->count(),
            ])
            ->values()
            ->merge(
                $this->infrastructureBalances
                    ->where('qty', '>', 0)
                    ->groupBy('item_id')
                    ->map(fn ($group) => [
                        'name' => $group->first()->item?->name ?: 'Barang tanpa nama',
                        'unit' => $group->first()->item?->unit,
                        'qty' => (float) $group->sum('qty'),
                    ])
                    ->values()
            );
    }

    /**
     * Naik rantai `parent` sampai node puncak (yang punya `anchor_pop_id`),
     * lalu resolve anchor-nya ke Cabang lewat `Pop::resolveCabangId()`:
     * anchor Mini POP → Cabang induknya, anchor Cabang → dirinya, anchor
     * Pusat → Pusat. JANGAN balik ke "kalau anchor punya parent, ambil
     * parent-nya" — Cabang produksi punya parent (Pusat), jadi node yang
     * ditambatkan ke Cabang bakal terbaca Pusat.
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
                $node = $node->resolveParentNode();
            }

            if ($node === null || $node->anchor_pop_id === null) {
                return null;
            }

            $cabangId = Pop::resolveCabangId($node->anchor_pop_id);

            if ($cabangId === null) {
                return null;
            }

            if ($node->relationLoaded('anchorPop') && $node->anchorPop?->id === $cabangId) {
                return $node->anchorPop;
            }

            return Pop::find($cabangId);
        });
    }

    /**
     * Satu-satunya sumber aturan "node ini Red Zone?" — dipakai dashboard FOP
     * (`FopUnifiedDashboardService`) DAN halaman listing Network Node. Dulu
     * listing pakai 85% hardcode sementara dashboard pakai ambang per tipe
     * (`red_zone_threshold`), jadi node yang sama bisa Red Zone di satu
     * layar dan normal di layar lain.
     *
     * Ambang disimpan sebagai PERSEN (80 = 80%). `used/ports >= threshold/100`
     * dikali silang jadi `used*100 >= threshold*ports`: aritmetika integer
     * murni, tanpa risiko float tepat di ambang (mis. 7/10 vs 70%).
     *
     * `ports_count`/`used_ports_count` = alias `withCount()` dari query
     * pemanggil, bukan kolom. Instance tanpa withCount → dianggap 0 port →
     * false (aman, gak melempar). Tipe tanpa ambang (null) tidak pernah Red
     * Zone. `<= 0` (bukan `=== 0`): withCount bisa balik string numerik di
     * driver tertentu.
     */
    public function isInRedZone(): bool
    {
        $nodeType = $this->relationLoaded('nodeType')
            ? $this->nodeType
            : ($this->node_type_id ? NetworkNodeType::find($this->node_type_id) : null);

        $threshold = $nodeType?->red_zone_threshold;

        if ($threshold === null || (int) ($this->ports_count ?? 0) <= 0) {
            return false;
        }

        return ($this->used_ports_count ?? 0) * 100 >= $threshold * $this->ports_count;
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
            $top = $top->resolveParentNode();
        }

        $anchor = $top?->resolveAnchorPop();

        if ($anchor !== null) {
            $anchorParent = $anchor->relationLoaded('parent')
                ? $anchor->parent
                : ($anchor->parent_id ? Pop::find($anchor->parent_id) : null);

            if ($anchor->parent_id !== null && $anchorParent !== null) {
                $parts[] = $anchorParent->name;
            }
            $parts[] = $anchor->name;
        }

        $nodeNames = [];
        $node = $this;
        while ($node !== null) {
            $nodeNames[] = $node->name;
            $node = $node->resolveParentNode();
        }

        return implode(' → ', array_merge($parts, array_reverse($nodeNames)));
    }

    private function resolveParentNode(): ?self
    {
        return $this->relationLoaded('parent')
            ? $this->parent
            : ($this->parent_id ? self::find($this->parent_id) : null);
    }

    private function resolveAnchorPop(): ?Pop
    {
        return $this->relationLoaded('anchorPop')
            ? $this->anchorPop
            : ($this->anchor_pop_id ? Pop::find($this->anchor_pop_id) : null);
    }
}
