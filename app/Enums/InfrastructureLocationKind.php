<?php

namespace App\Enums;

use App\Support\InfrastructureLocation;

/**
 * Jenis posisi tempat aset berserial SKU ini boleh dipasang (Master Barang,
 * §10.4 "barang baru cocok dengan lokasi akhir"). Null di master = `NODE`
 * (perilaku lama: dipasang di network node, dibatasi tipe node).
 *
 * Untuk `company_asset` (Switch/SFP), kolom ini WAJIB diisi eksplisit — itulah
 * "jalur custody → deploy eksplisit" §Task 6: tanpa pernyataan admin, alat
 * kerja perusahaan tidak pernah bisa tercatat netap di jaringan.
 */
enum InfrastructureLocationKind: string
{
    case POP = 'pop';

    case NODE = 'node';
    case MINI_POP = 'mini_pop';
    case SLOT = 'slot';
    case PORT = 'port';

    public function label(): string
    {
        return match ($this) {
            self::POP => 'Langsung di POP/Cabang',
            self::NODE => 'Node/perangkat (OLT, Switch, ODP, …)',
            self::MINI_POP => 'Langsung di Mini POP',
            self::SLOT => 'Slot perangkat (kartu/modul)',
            self::PORT => 'PON/Port (SFP/transceiver)',
        };
    }

    public function matchesLocation(InfrastructureLocation $location): bool
    {
        return match ($this) {
            self::POP => $location->kind === InfrastructureLocation::POP && ($location->ancestors[array_key_last($location->ancestors)]['type'] ?? null) === 'cabang',
            self::NODE => $location->kind === InfrastructureLocation::NODE,
            self::MINI_POP => $location->kind === InfrastructureLocation::MINI_POP,
            self::SLOT => $location->kind === InfrastructureLocation::SLOT,
            self::PORT => $location->kind === InfrastructureLocation::PORT,
        };
    }
}
