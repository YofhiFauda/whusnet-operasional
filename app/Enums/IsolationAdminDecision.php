<?php

namespace App\Enums;

/**
 * Keputusan Admin per kasus Monitoring Isolir, SEBELUM NOC boleh isolir
 * (docs/plan/Rancangan_Monitoring_Isolir_Pelanggan.md §21, follow-up
 * 2026-11-19) — "Admin menandai mana yang harus diisolir, mana yang masih
 * ditoleransi; NOC mengeksekusi perintah Admin." Default kasus (NULL,
 * belum diputuskan) diperlakukan SAMA dengan TOLERATE di gerbang NOC —
 * lihat `CustomerController::toggleSuspend()`.
 */
enum IsolationAdminDecision: string
{
    case EXECUTE = 'execute';
    case TOLERATE = 'tolerate';

    public function label(): string
    {
        return match ($this) {
            self::EXECUTE => 'Eksekusi Isolir',
            self::TOLERATE => 'Toleransi',
        };
    }
}
