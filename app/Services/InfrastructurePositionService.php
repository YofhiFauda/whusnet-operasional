<?php

namespace App\Services;

use App\Enums\InfrastructureCapacityUnit;
use App\Enums\InfrastructurePortKind;
use App\Enums\InfrastructureRecordStatus;
use App\Enums\InfrastructureSlotKind;
use App\Models\AuditLog;
use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Satu-satunya penulis Slot/PON/Port Switch. Sejak ADHOC-213 ketiganya adalah
 * baris `network_nodes` (bukan tabel `infrastructure_*` lagi). Aturan yang dijaga:
 *
 * - Slot hanya di perangkat OLT (`olt_chassis`) atau Switch (`switch_mini_pop`);
 *   jenis slot diturunkan dari tipe perangkat, tidak bisa dipilih bebas.
 * - PON hanya di Slot OLT, Port Switch hanya di Slot Switch (§4.2). Aturan
 *   tipe-induk TIDAK ditulis ulang di sini: divalidasi lewat
 *   `NetworkNodeService::assertTypePlacement` (tabel `network_node_type_rules`)
 *   supaya hanya ada satu sumber kebenaran.
 * - Nomor unik dalam induknya (unique index DB + pesan ramah di sini).
 *
 * Pemeriksaan POP scope aktor dilakukan pemanggil (controller/laporan) lewat
 * `InfrastructureLocationResolver` — service ini tidak tahu siapa aktornya.
 */
class InfrastructurePositionService
{
    public const LOGICAL_SLOT_LABEL = 'Slot internal/logis';

    private const MAX_CODE_LENGTH = 120;

    /**
     * Kode posisi diturunkan dari kode induk (`{induk}-S3`, `{induk}-S3-P5`)
     * supaya unik tanpa input manual. Lebar kolom `code` hanya bisa diuji di
     * MySQL, jadi batasnya DIJAGA DI SINI: tanpa guard, kode kepanjangan jadi
     * error SQL mentah (500) di production, bukan pesan ramah.
     */
    public static function derivedCode(NetworkNode $parent, string $letter, int $number): string
    {
        $code = "{$parent->code}-{$letter}{$number}";

        if (mb_strlen($code) > self::MAX_CODE_LENGTH) {
            throw new InvalidArgumentException('Kode turunan melebihi 120 karakter.');
        }

        return $code;
    }

    /**
     * @param  array{capacity?: ?int, status?: InfrastructureRecordStatus|string, notes?: ?string, is_logical?: bool}  $attributes
     */
    public function createSlot(NetworkNode $device, int $number, array $attributes = []): NetworkNode
    {
        if ($number < 1) {
            throw new InvalidArgumentException('Nomor slot harus mulai dari 1.');
        }

        return DB::transaction(function () use ($device, $number, $attributes): NetworkNode {
            $device = NetworkNode::query()->with('nodeType')->lockForUpdate()->findOrFail($device->id);
            $kind = InfrastructureSlotKind::forDeviceTypeCode($device->nodeType?->code);

            if ($kind === null) {
                throw new InvalidArgumentException('Slot hanya dapat dibuat pada OLT atau Switch Mini POP.');
            }

            $isLogical = (bool) ($attributes['is_logical'] ?? false);
            if ($isLogical && $kind !== InfrastructureSlotKind::SWITCH) {
                throw new InvalidArgumentException('Slot internal/logis hanya untuk Switch.');
            }

            if ($device->capacity !== null && $number > $device->capacity) {
                throw new InvalidArgumentException("Nomor slot melebihi kapasitas perangkat {$device->capacity} slot.");
            }

            if ($device->children()->where('number', $number)->exists()) {
                throw new InvalidArgumentException("Slot {$number} sudah ada pada perangkat {$device->code}.");
            }

            $type = NetworkNodeType::query()->where('code', $kind->typeCode())->firstOrFail();
            app(NetworkNodeService::class)->assertTypePlacement($type, $device, null);

            return $this->guardUnique(fn () => NetworkNode::create([
                'parent_id' => $device->id,
                'anchor_pop_id' => null,
                'node_type_id' => $type->id,
                'number' => $number,
                'code' => self::derivedCode($device, 'S', $number),
                'name' => $isLogical ? self::LOGICAL_SLOT_LABEL : "Slot {$number}",
                'is_logical' => $isLogical,
                'record_status' => $attributes['status'] ?? InfrastructureRecordStatus::BELUM_DIDATA,
                'capacity' => $attributes['capacity'] ?? null,
                'capacity_unit' => $kind === InfrastructureSlotKind::OLT
                    ? InfrastructureCapacityUnit::PON
                    : InfrastructureCapacityUnit::PORT,
                'status' => 'active',
                'notes' => $attributes['notes'] ?? null,
            ]));
        });
    }

    /**
     * Switch port tetap (non-modular) memakai Slot 1 berlabel "Slot
     * internal/logis" (§1, §16.2) supaya urutan Switch → Slot → Port tetap
     * seragam tanpa mengesankan ada kartu yang bisa dicabut. Idempoten.
     */
    public function ensureLogicalSlot(NetworkNode $switch): NetworkNode
    {
        $switch->loadMissing('nodeType');
        if (InfrastructureSlotKind::forDeviceTypeCode($switch->nodeType?->code) !== InfrastructureSlotKind::SWITCH) {
            throw new InvalidArgumentException('Slot internal/logis hanya untuk Switch.');
        }

        $existing = $switch->slots()->where('number', 1)->first();
        if ($existing !== null) {
            return $existing;
        }

        return $this->createSlot($switch, 1, ['is_logical' => true]);
    }

    /**
     * Jenis port (PON/Port Switch) diturunkan dari tipe Slot, tidak dipilih
     * pemanggil — jadi "PON di Slot Switch" mustahil dibuat lewat service ini.
     *
     * @param  array{capacity?: ?int, red_zone_threshold?: ?int, status?: InfrastructureRecordStatus|string, notes?: ?string}  $attributes
     */
    public function createPort(NetworkNode $slot, int $number, array $attributes = []): NetworkNode
    {
        if ($number < 1) {
            throw new InvalidArgumentException('Nomor port harus mulai dari 1.');
        }

        return DB::transaction(function () use ($slot, $number, $attributes): NetworkNode {
            $slot = NetworkNode::query()->with('nodeType')->lockForUpdate()->findOrFail($slot->id);
            $kind = $slot->slotKind()?->portKind();

            if ($kind === null) {
                throw new InvalidArgumentException('PON atau Port Switch hanya dapat dibuat pada Slot.');
            }

            if ($slot->capacity !== null && $number > $slot->capacity) {
                throw new InvalidArgumentException("Nomor port melebihi kapasitas slot {$slot->capacity}.");
            }

            if ($slot->children()->where('number', $number)->exists()) {
                throw new InvalidArgumentException("{$kind->label()} {$number} sudah ada pada slot ini.");
            }

            $threshold = $attributes['red_zone_threshold'] ?? null;
            if ($threshold !== null && ($kind !== InfrastructurePortKind::PON || $threshold < 1 || $threshold > 100)) {
                throw new InvalidArgumentException('Ambang Red Zone hanya untuk PON dan bernilai 1–100 persen.');
            }

            $capacity = $attributes['capacity'] ?? null;
            if ($capacity !== null && ($kind !== InfrastructurePortKind::PON || $capacity < 1)) {
                throw new InvalidArgumentException('Kapasitas pelanggan aktif hanya untuk PON dan minimal 1.');
            }

            $type = NetworkNodeType::query()->where('code', $kind->typeCode())->firstOrFail();
            app(NetworkNodeService::class)->assertTypePlacement($type, $slot, null);

            return $this->guardUnique(fn () => NetworkNode::create([
                'parent_id' => $slot->id,
                'anchor_pop_id' => null,
                'node_type_id' => $type->id,
                'number' => $number,
                'code' => self::derivedCode($slot, 'P', $number),
                'name' => ($kind === InfrastructurePortKind::PON ? 'PON' : 'Port').' '.$number,
                'is_logical' => false,
                'record_status' => $attributes['status'] ?? InfrastructureRecordStatus::BELUM_DIDATA,
                'capacity' => $capacity,
                'capacity_unit' => $kind->capacityUnit(),
                'red_zone_threshold' => $threshold,
                'status' => 'active',
                'notes' => $attributes['notes'] ?? null,
            ]));
        });
    }

    /**
     * Generate massal Slot + PON/Port Switch (spec §6). Idempoten: hanya nomor
     * yang belum ada yang dibuat; node lama tidak disentuh kecuali kapasitas
     * yang SEMPIT dinaikkan (createSlot/createPort menolak nomor > kapasitas,
     * jadi tanpa itu generate ulang pada perangkat berkapasitas kecil gagal).
     * Kapasitas tidak pernah diturunkan.
     *
     * Satu transaksi + audit per-node dimatikan: 16 slot x 8 PON = 144 baris
     * audit "created" akan menenggelamkan jejak; satu ringkasan sudah cukup,
     * dan kalau satu node gagal semuanya batal (tidak ada struktur setengah jadi).
     *
     * @param  ?int  $ponCapacity  kapasitas pelanggan aktif PON BARU (hanya OLT); null = dibiarkan kosong.
     * @return array{slots_created: int, ports_created: int}
     */
    public function generateStructure(NetworkNode $device, int $slotCount, int $portsPerSlot, ?int $ponCapacity, User $actor): array
    {
        return DB::transaction(function () use ($device, $slotCount, $portsPerSlot, $ponCapacity, $actor): array {
            $device = NetworkNode::query()->with('nodeType')->lockForUpdate()->findOrFail($device->id);
            $kind = InfrastructureSlotKind::forDeviceTypeCode($device->nodeType?->code);
            if ($kind === null) {
                throw new InvalidArgumentException('Generate struktur hanya untuk OLT atau Switch Mini POP.');
            }
            if ($portsPerSlot < 1 || ($kind === InfrastructureSlotKind::OLT && $slotCount < 1)) {
                throw new InvalidArgumentException('Jumlah slot dan port harus minimal 1.');
            }

            $isOlt = $kind === InfrastructureSlotKind::OLT;
            // Switch: hanya satu Slot internal/logis (§1), $slotCount diabaikan.
            $slotNumbers = $isOlt ? range(1, $slotCount) : [1];
            $created = ['slots_created' => 0, 'ports_created' => 0];

            NetworkNode::withoutEvents(function () use ($device, $slotNumbers, $portsPerSlot, $ponCapacity, $isOlt, &$created): void {
                // Kapasitas perangkat dinaikkan DULU: createSlot menolak nomor > kapasitas.
                // Lantai = max(nomor anak tertinggi yang sudah ada, nilai diminta): capacity null
                // berarti "tak terbatas", jadi mengisinya dengan angka lebih kecil dari anak
                // yang ada akan menciptakan state yang ditolak updateDeviceCapacity.
                $needed = max(max($slotNumbers), (int) $device->slots()->reorder()->max('number'));
                if ($device->capacity === null || $device->capacity < $needed) {
                    $device->forceFill(['capacity' => $needed])->saveQuietly();
                }

                $existing = $device->slots()->get()->keyBy('number');
                foreach ($slotNumbers as $number) {
                    $slot = $existing->get($number);
                    if ($slot === null) {
                        $slot = $isOlt
                            ? $this->createSlot($device, $number, ['capacity' => $portsPerSlot])
                            : $this->ensureLogicalSlot($device);
                        $created['slots_created']++;
                    }
                    // Kapasitas slot dinaikkan DULU: createPort menolak nomor > kapasitas.
                    $have = $slot->positionPorts()->pluck('number')->all();
                    $slotFloor = max($portsPerSlot, $have === [] ? 0 : max($have));
                    if ($slot->capacity === null || $slot->capacity < $slotFloor) {
                        $slot->forceFill(['capacity' => $slotFloor])->saveQuietly();
                    }

                    foreach (range(1, $portsPerSlot) as $portNumber) {
                        if (in_array($portNumber, $have, true)) {
                            continue;
                        }
                        $this->createPort($slot, $portNumber, $isOlt && $ponCapacity !== null ? ['capacity' => $ponCapacity] : []);
                        $created['ports_created']++;
                    }
                }
            });

            AuditLog::create([
                'user_id' => $actor->id, 'module' => 'Struktur Infrastruktur', 'action' => 'generate_structure',
                'auditable_type' => NetworkNode::class, 'auditable_id' => $device->id,
                'old_values' => null,
                'new_values' => [
                    'device_id' => $device->id, 'device_code' => $device->code,
                    'slot_count' => $isOlt ? $slotCount : 1, 'ports_per_slot' => $portsPerSlot,
                    ...$created,
                ],
                'ip_address' => request()?->ip(), 'user_agent' => request()?->userAgent(), 'created_at' => now(),
            ]);

            return $created;
        });
    }

    /**
     * Turunkan ulang kode Slot/PON/Port di bawah `$parent` setelah kode
     * induknya diganti (rename perangkat). Kode posisi = jalur induk
     * (`derivedCode()`), jadi tanpa ini `OLT-LAMA-S3-P5` tetap menempel di
     * bawah `OLT-BARU` dan pencarian/laporan berbasis kode menyesatkan.
     * Pemanggil WAJIB membungkusnya dalam transaksi yang sama dengan rename:
     * kalau satu kode bentrok (unique global) atau melewati 120 karakter,
     * seluruh rename batal — tidak ada subtree setengah berganti kode.
     *
     * Tanpa audit per baris (saveQuietly): perubahan ini akibat mekanis dari
     * rename induk yang sudah diaudit pemanggil.
     *
     * @return int jumlah posisi yang kodenya berubah
     */
    public function rederiveCodes(NetworkNode $parent): int
    {
        $changed = 0;
        $children = NetworkNode::query()->where('parent_id', $parent->id)->with('nodeType')->get()
            ->filter(fn (NetworkNode $child): bool => $child->isPosition());

        foreach ($children as $child) {
            $code = self::derivedCode($parent, $child->isSlot() ? 'S' : 'P', (int) $child->number);
            if ($child->code !== $code) {
                $this->guardUnique(fn () => $child->forceFill(['code' => $code])->saveQuietly());
                $changed++;
            }
            $changed += $this->rederiveCodes($child);
        }

        return $changed;
    }

    public function updateDeviceCapacity(NetworkNode $device, int $capacity, User $actor): NetworkNode
    {
        return DB::transaction(function () use ($device, $capacity, $actor): NetworkNode {
            $device = NetworkNode::query()->with('nodeType')->lockForUpdate()->findOrFail($device->id);
            if (InfrastructureSlotKind::forDeviceTypeCode($device->nodeType?->code) === null) {
                throw ValidationException::withMessages(['capacity' => 'Kapasitas slot hanya untuk OLT atau Switch.']);
            }
            // reorder(): relasi slots() sudah orderBy; max() + ORDER BY kolom
            // non-agregat ditolak MySQL ONLY_FULL_GROUP_BY.
            $highest = (int) $device->slots()->reorder()->max('number');
            if ($capacity < 1 || $capacity < $highest) {
                throw ValidationException::withMessages(['capacity' => "Kapasitas tidak boleh di bawah posisi slot tertinggi {$highest}."]);
            }
            $old = $device->capacity;
            if ($old !== $capacity) {
                $device->forceFill(['capacity' => $capacity])->saveQuietly();
                $this->auditCapacity($device, $old, $capacity, $actor);
            }

            return $device;
        });
    }

    public function updateSlotCapacity(NetworkNode $slot, int $capacity, User $actor): NetworkNode
    {
        return DB::transaction(function () use ($slot, $capacity, $actor): NetworkNode {
            $slot = NetworkNode::query()->with('nodeType')->lockForUpdate()->findOrFail($slot->id);
            if (! $slot->isSlot()) {
                throw ValidationException::withMessages(['capacity' => 'Kapasitas port hanya untuk Slot.']);
            }
            $highest = (int) $slot->positionPorts()->reorder()->max('number');
            if ($capacity < 1 || $capacity < $highest) {
                throw ValidationException::withMessages(['capacity' => "Kapasitas tidak boleh di bawah nomor port tertinggi {$highest}."]);
            }
            $old = $slot->capacity;
            if ($old !== $capacity) {
                $slot->forceFill(['capacity' => $capacity])->saveQuietly();
                $this->auditCapacity($slot, $old, $capacity, $actor);
            }

            return $slot;
        });
    }

    private function auditCapacity(NetworkNode $position, ?int $old, int $capacity, User $actor): void
    {
        AuditLog::create([
            'user_id' => $actor->id, 'module' => 'Kapasitas Infrastruktur', 'action' => 'capacity_change',
            'auditable_type' => NetworkNode::class, 'auditable_id' => $position->id,
            'old_values' => ['capacity' => $old], 'new_values' => ['capacity' => $capacity],
            'ip_address' => request()?->ip(), 'user_agent' => request()?->userAgent(), 'created_at' => now(),
        ]);
    }

    /**
     * Race dua request bersamaan tetap ditangkap unique index DB; ubah jadi
     * pesan domain yang sama dengan cek awal, bukan 500.
     *
     * @template T
     *
     * @param  callable(): T  $create
     * @return T
     */
    private function guardUnique(callable $create): mixed
    {
        try {
            return $create();
        } catch (UniqueConstraintViolationException) {
            throw new InvalidArgumentException('Nomor atau kode posisi sudah dipakai pada induk yang sama.');
        }
    }
}
