<?php

namespace App\Enums;

enum InfrastructurePortKind: string
{
    case PON = 'pon';
    case SWITCH = 'switch';

    public function label(): string
    {
        return match ($this) {
            self::PON => 'PON',
            self::SWITCH => 'Port Switch',
        };
    }

    /**
     * Satuan kapasitas wajib ikut jenis port (§12/§16.3): kapasitas PON =
     * jumlah pelanggan aktif (keputusan user 21-10-2026). Port Switch tidak
     * punya kapasitas pelanggan — satu port = satu sambungan fisik.
     */
    public function capacityUnit(): ?InfrastructureCapacityUnit
    {
        return match ($this) {
            self::PON => InfrastructureCapacityUnit::PELANGGAN_AKTIF,
            self::SWITCH => null,
        };
    }
}
