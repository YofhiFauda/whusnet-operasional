<?php

namespace App\Enums;

/**
 * Jenis Slot ditentukan perangkat induknya — Slot OLT memuat PON, Slot Switch
 * memuat Port Switch (§2 rancangan hierarki). Tidak dipilih bebas oleh user
 * supaya PON tidak bisa "menyusup" ke Switch lewat form.
 */
enum InfrastructureSlotKind: string
{
    case OLT = 'olt';
    case SWITCH = 'switch';

    public function label(): string
    {
        return match ($this) {
            self::OLT => 'Slot OLT',
            self::SWITCH => 'Slot Switch',
        };
    }

    public function portKind(): InfrastructurePortKind
    {
        return match ($this) {
            self::OLT => InfrastructurePortKind::PON,
            self::SWITCH => InfrastructurePortKind::SWITCH,
        };
    }

    /**
     * Kode tipe node perangkat yang boleh punya slot jenis ini. `olt_chassis`
     * = OLT pada rancangan; `switch_mini_pop` = Switch operasional (§16.2).
     * `aggregation_switch` lama SENGAJA tidak masuk.
     */
    public function typeCode(): string
    {
        return match ($this) {
            self::OLT => 'olt_slot',
            self::SWITCH => 'switch_slot',
        };
    }

    public static function fromSlotTypeCode(?string $typeCode): ?self
    {
        return match ($typeCode) {
            'olt_slot' => self::OLT,
            'switch_slot' => self::SWITCH,
            default => null,
        };
    }

    public static function forDeviceTypeCode(?string $typeCode): ?self
    {
        return match ($typeCode) {
            'olt_chassis' => self::OLT,
            'switch_mini_pop' => self::SWITCH,
            default => null,
        };
    }
}
