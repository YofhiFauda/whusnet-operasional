<?php

namespace App\Enums;

/**
 * Status verifikasi biaya C-REQ oleh CS (role helpdesk) — cuma bermakna
 * kalau `TaskCreqDetail::is_billable = true`. Lihat docs/plan/task-teknisi/
 * rancangan-biaya-creq-verifikasi-cs.md §3-4.
 */
enum CReqVerificationStatus: string
{
    case PENDING = 'pending';
    case VERIFIED = 'verified';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Menunggu Verifikasi',
            self::VERIFIED => 'Diverifikasi',
            self::REJECTED => 'Ditolak',
        };
    }
}
