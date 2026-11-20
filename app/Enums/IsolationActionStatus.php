<?php

namespace App\Enums;

/**
 * Status eksekusi aksi isolir/pemulihan (`isolation_actions.status`,
 * docs/plan/Rancangan_Monitoring_Isolir_Pelanggan.md §12.4). Batch pertama
 * cuma manual/sinkron (klik = `succeeded` langsung, §21.1 #4) — `queued`/
 * `in_progress` disiapkan di skema supaya integrasi perangkat nanti tidak
 * perlu migrasi ulang.
 */
enum IsolationActionStatus: string
{
    case QUEUED = 'queued';
    case IN_PROGRESS = 'in_progress';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::QUEUED => 'Menunggu',
            self::IN_PROGRESS => 'Berjalan',
            self::SUCCEEDED => 'Berhasil',
            self::FAILED => 'Gagal',
            self::CANCELLED => 'Dibatalkan',
        };
    }
}
