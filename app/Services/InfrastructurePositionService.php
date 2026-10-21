<?php

namespace App\Services;

use App\Enums\InfrastructureCapacityUnit;
use App\Enums\InfrastructurePortKind;
use App\Enums\InfrastructureRecordStatus;
use App\Enums\InfrastructureSlotKind;
use App\Models\InfrastructurePort;
use App\Models\InfrastructureSlot;
use App\Models\NetworkNode;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
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
