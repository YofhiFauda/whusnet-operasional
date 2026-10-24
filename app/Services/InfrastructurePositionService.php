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
