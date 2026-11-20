<?php

namespace App\Enums;

/**
 * Sumber pemicu kasus isolir dibuat (docs/plan/Rancangan_Monitoring_Isolir_Pelanggan.md
 * §21.2, kolom `isolation_cases.trigger_type`).
 */
enum IsolationTriggerType: string
{
    case OVERDUE_BILLING = 'overdue_billing';
    case MANUAL = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::OVERDUE_BILLING => 'Tunggakan',
            self::MANUAL => 'Manual',
        };
    }
}
