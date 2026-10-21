<?php

namespace App\Enums;

/**
 * Satuan kapasitas per tingkat (§12): jangan menjumlahkan "port" lintas
 * tingkat tanpa label. Kapasitas ODP tetap port pelanggan pada
 * `network_node_ports`, bukan enum ini.
 */
enum InfrastructureCapacityUnit: string
{
    case PON = 'pon';
    case PORT = 'port';
    case PELANGGAN_AKTIF = 'pelanggan_aktif';

    public function label(): string
    {
        return match ($this) {
            self::PON => 'PON per slot',
            self::PORT => 'port per slot',
            self::PELANGGAN_AKTIF => 'pelanggan aktif',
        };
    }
}
