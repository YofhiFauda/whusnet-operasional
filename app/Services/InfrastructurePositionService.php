<?php

namespace App\Services;

use App\Enums\InfrastructureCapacityUnit;
use App\Enums\InfrastructurePortKind;
use App\Enums\InfrastructureRecordStatus;
use App\Enums\InfrastructureSlotKind;
use App\Models\AuditLog;
use App\Models\InfrastructurePort;
use App\Models\InfrastructureSlot;
use App\Models\NetworkNode;
use App\Models\OltSlot;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Satu-satunya penulis Slot/Port kanonis. Aturan yang dijaga di sini:
 *
 * - Slot hanya di perangkat OLT (`olt_chassis`) atau Switch (`switch_mini_pop`);
 *   jenis slot diturunkan dari tipe perangkat, tidak bisa dipilih bebas.
 * - PON hanya di Slot OLT, Port Switch hanya di Slot Switch (§4.2).
 * - Nomor & kode unik dalam induknya (unique index DB + pesan ramah di sini).
 *
 * Pemeriksaan POP scope aktor dilakukan pemanggil (controller/laporan) lewat
 * `InfrastructureLocationResolver` — service ini tidak tahu siapa aktornya.
 */
class InfrastructurePositionService
{
    public const LOGICAL_SLOT_LABEL = 'Slot internal/logis';

    /**
     * @param  array{code?: ?string, label?: ?string, capacity?: ?int, status?: InfrastructureRecordStatus|string, notes?: ?string, is_logical?: bool, legacy_olt_slot_id?: ?int}  $attributes
     */
    public function createSlot(NetworkNode $device, int $number, array $attributes = []): InfrastructureSlot
    {
        if ($number < 1) {
            throw new InvalidArgumentException('Nomor slot harus mulai dari 1.');
        }

        return DB::transaction(function () use ($device, $number, $attributes): InfrastructureSlot {
            $device = NetworkNode::query()->with('nodeType')->lockForUpdate()->findOrFail($device->id);
            $kind = InfrastructureSlotKind::forDeviceTypeCode($device->nodeType?->code);

            if ($kind === null) {
                throw new InvalidArgumentException('Slot hanya dapat dibuat pada OLT atau Switch Mini POP.');
            }

            if (($attributes['is_logical'] ?? false) && $kind !== InfrastructureSlotKind::SWITCH) {
                throw new InvalidArgumentException('Slot internal/logis hanya untuk Switch.');
            }

            if ($device->capacity !== null && $number > $device->capacity) {
                throw new InvalidArgumentException("Nomor slot melebihi kapasitas perangkat {$device->capacity} slot.");
            }
            if (! isset($attributes['legacy_olt_slot_id'])) {
                $legacy = OltSlot::query()->where('chassis_node_id', $device->id)->where('number', $number)->first();
                if ($legacy !== null && ! InfrastructureSlot::query()->where('network_node_id', $device->id)->where('number', $number)->exists()) {
                    $slot = app(InfrastructureLegacyBackfillService::class)->syncOltSlot($legacy);
                    $slot->update(array_intersect_key($attributes, array_flip(['code', 'label', 'capacity', 'status', 'notes'])));

                    return $slot;
                }
            }

            if (InfrastructureSlot::query()->where('network_node_id', $device->id)->where('number', $number)->exists()) {
                throw new InvalidArgumentException("Slot {$number} sudah ada pada perangkat {$device->code}.");
            }

            $code = $this->normalizeCode($attributes['code'] ?? null);
            if ($code !== null && InfrastructureSlot::query()->where('network_node_id', $device->id)->where('code', $code)->exists()) {
                throw new InvalidArgumentException("Kode slot {$code} sudah dipakai pada perangkat {$device->code}.");
            }

            return $this->guardUnique(fn () => InfrastructureSlot::create([
                'network_node_id' => $device->id,
                'number' => $number,
                'kind' => $kind,
                'code' => $code,
                'label' => $attributes['label'] ?? null,
                'is_logical' => (bool) ($attributes['is_logical'] ?? false),
                'status' => $attributes['status'] ?? InfrastructureRecordStatus::BELUM_DIDATA,
                'capacity' => $attributes['capacity'] ?? null,
                'capacity_unit' => $kind === InfrastructureSlotKind::OLT
                    ? InfrastructureCapacityUnit::PON
                    : InfrastructureCapacityUnit::PORT,
                'legacy_olt_slot_id' => $attributes['legacy_olt_slot_id'] ?? null,
                'notes' => $attributes['notes'] ?? null,
            ]));
        });
    }

    /**
     * Switch port tetap (non-modular) memakai Slot 1 berlabel "Slot
     * internal/logis" (§1, §16.2) supaya urutan Switch → Slot → Port tetap
     * seragam tanpa mengesankan ada kartu yang bisa dicabut. Idempoten.
     */
    public function ensureLogicalSlot(NetworkNode $switch): InfrastructureSlot
    {
        $existing = InfrastructureSlot::query()->where('network_node_id', $switch->id)->where('number', 1)->first();
        if ($existing !== null) {
            if ($existing->kind !== InfrastructureSlotKind::SWITCH) {
                throw new InvalidArgumentException('Slot internal/logis hanya untuk Switch.');
            }

            return $existing;
        }

        return $this->createSlot($switch, 1, ['is_logical' => true, 'label' => self::LOGICAL_SLOT_LABEL]);
    }

    /**
     * @param  array{code?: ?string, capacity?: ?int, red_zone_threshold?: ?int, status?: InfrastructureRecordStatus|string, notes?: ?string, legacy_network_node_id?: ?int}  $attributes
     */
    public function createPort(InfrastructureSlot $slot, int $number, InfrastructurePortKind $kind, array $attributes = []): InfrastructurePort
    {
        if ($number < 1) {
            throw new InvalidArgumentException('Nomor port harus mulai dari 1.');
        }

        return DB::transaction(function () use ($slot, $number, $kind, $attributes): InfrastructurePort {
            $slot = InfrastructureSlot::query()->lockForUpdate()->findOrFail($slot->id);

            if ($slot->kind->portKind() !== $kind) {
                throw new InvalidArgumentException($kind === InfrastructurePortKind::PON
                    ? 'PON hanya boleh berada di Slot OLT.'
                    : 'Port Switch hanya boleh berada di Slot Switch.');
            }

            if ($slot->capacity !== null && $number > $slot->capacity) {
                throw new InvalidArgumentException("Nomor port melebihi kapasitas slot {$slot->capacity}.");
            }

            if (InfrastructurePort::query()->where('infrastructure_slot_id', $slot->id)->where('number', $number)->exists()) {
                throw new InvalidArgumentException("{$kind->label()} {$number} sudah ada pada slot ini.");
            }

            $code = $this->normalizeCode($attributes['code'] ?? null);
            if ($code !== null && InfrastructurePort::query()->where('infrastructure_slot_id', $slot->id)->where('code', $code)->exists()) {
                throw new InvalidArgumentException("Kode port {$code} sudah dipakai pada slot ini.");
            }

            $threshold = $attributes['red_zone_threshold'] ?? null;
            if ($threshold !== null && ($kind !== InfrastructurePortKind::PON || $threshold < 1 || $threshold > 100)) {
                throw new InvalidArgumentException('Ambang Red Zone hanya untuk PON dan bernilai 1–100 persen.');
            }

            $capacity = $attributes['capacity'] ?? null;
            if ($capacity !== null && ($kind !== InfrastructurePortKind::PON || $capacity < 1)) {
                throw new InvalidArgumentException('Kapasitas pelanggan aktif hanya untuk PON dan minimal 1.');
            }

            return $this->guardUnique(fn () => InfrastructurePort::create([
                'infrastructure_slot_id' => $slot->id,
                'number' => $number,
                'kind' => $kind,
                'code' => $code,
                'status' => $attributes['status'] ?? InfrastructureRecordStatus::BELUM_DIDATA,
                'capacity' => $capacity,
                'capacity_unit' => $kind->capacityUnit(),
                'red_zone_threshold' => $threshold,
                'legacy_network_node_id' => $attributes['legacy_network_node_id'] ?? null,
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
            $highest = max((int) $device->infrastructureSlots()->max('number'), (int) $device->oltSlots()->max('number'));
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

    public function updateSlotCapacity(InfrastructureSlot $slot, int $capacity, User $actor): InfrastructureSlot
    {
        return DB::transaction(function () use ($slot, $capacity, $actor): InfrastructureSlot {
            $slot = InfrastructureSlot::query()->lockForUpdate()->findOrFail($slot->id);
            $highest = (int) $slot->ports()->max('number');
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

    private function auditCapacity(NetworkNode|InfrastructureSlot $position, ?int $old, int $capacity, User $actor): void
    {
        AuditLog::create([
            'user_id' => $actor->id, 'module' => 'Kapasitas Infrastruktur', 'action' => 'capacity_change',
            'auditable_type' => $position::class, 'auditable_id' => $position->id,
            'old_values' => ['capacity' => $old], 'new_values' => ['capacity' => $capacity],
            'ip_address' => request()?->ip(), 'user_agent' => request()?->userAgent(), 'created_at' => now(),
        ]);
    }

    private function normalizeCode(?string $code): ?string
    {
        $code = $code === null ? null : trim($code);

        return $code === '' ? null : $code;
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
