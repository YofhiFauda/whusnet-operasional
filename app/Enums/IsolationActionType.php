<?php

namespace App\Enums;

/**
 * Jenis aksi eksekusi di `isolation_actions` (docs/plan/Rancangan_Monitoring_Isolir_Pelanggan.md §12.4).
 */
enum IsolationActionType: string
{
    case SUSPEND = 'suspend';
    case RESTORE = 'restore';

    public function label(): string
    {
        return match ($this) {
            self::SUSPEND => 'Isolir',
            self::RESTORE => 'Aktifkan Kembali',
        };
    }
}
