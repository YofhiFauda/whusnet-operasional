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
     * Kode tipe node PON/Port Switch (baris `network_nodes`, ADHOC-213) —
     * satu-satunya pemetaan enum -> kode tipe, jangan tulis string literal.
     */
    public function typeCode(): string
    {
        return match ($this) {
            self::PON => 'olt_pon',
            self::SWITCH => 'switch_port',
        };
    }

    public static function fromPortTypeCode(?string $typeCode): ?self
    {
        return match ($typeCode) {
            'olt_pon' => self::PON,
            'switch_port' => self::SWITCH,
            default => null,
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
