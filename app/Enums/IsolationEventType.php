<?php

namespace App\Enums;

/**
 * Jenis kejadian di timeline kasus isolir (`isolation_events`, append-only —
 * docs/plan/Rancangan_Monitoring_Isolir_Pelanggan.md §12.3).
 */
enum IsolationEventType: string
{
    case CASE_CREATED = 'case_created';
    case INVOICE_LINKED = 'invoice_linked';
    case ISOLATION_STARTED = 'isolation_started';
    case ISOLATION_SUCCEEDED = 'isolation_succeeded';
    case ISOLATION_FAILED = 'isolation_failed';
    case PAYMENT_VERIFIED = 'payment_verified';
    case RESTORATION_QUEUED = 'restoration_queued';
    case RESTORATION_STARTED = 'restoration_started';
    case RESTORATION_SUCCEEDED = 'restoration_succeeded';
    case RESTORATION_FAILED = 'restoration_failed';
    case CASE_CANCELLED = 'case_cancelled';
    case RETRY_REQUESTED = 'retry_requested';
    // Fix bug 2026-11-20: `restoration_pending` artinya "sudah lunas,
    // nunggu NOC" — kalau ada tagihan baru numpuk & lewat jatuh tempo LAGI
    // SEBELUM NOC konfirmasi, kasusnya dibalik ke `suspended` (bukan diam
    // di `restoration_pending` dengan piutang yang sudah > 0 lagi). Lihat
    // `IsolationCaseService::handlePaymentVerified()`.
    case RESTORATION_REVERTED = 'restoration_reverted';
    case ADMIN_MARKED_EXECUTE = 'admin_marked_execute';
    case ADMIN_MARKED_TOLERATE = 'admin_marked_tolerate';

    public function label(): string
    {
        return match ($this) {
            self::CASE_CREATED => 'Kasus dibuat',
            self::INVOICE_LINKED => 'Invoice ditautkan',
            self::ISOLATION_STARTED => 'Isolir dimulai',
            self::ISOLATION_SUCCEEDED => 'Isolir berhasil',
            self::ISOLATION_FAILED => 'Isolir gagal',
            self::PAYMENT_VERIFIED => 'Pembayaran terverifikasi',
            self::RESTORATION_QUEUED => 'Menunggu pemulihan',
            self::RESTORATION_STARTED => 'Pemulihan dimulai',
            self::RESTORATION_SUCCEEDED => 'Pemulihan berhasil',
            self::RESTORATION_FAILED => 'Pemulihan gagal',
            self::CASE_CANCELLED => 'Kasus dibatalkan',
            self::RETRY_REQUESTED => 'Dicoba ulang',
            self::RESTORATION_REVERTED => 'Piutang muncul lagi, dibatalkan dari antrean pemulihan',
            self::ADMIN_MARKED_EXECUTE => 'Admin memutuskan: Eksekusi Isolir',
            self::ADMIN_MARKED_TOLERATE => 'Admin memutuskan: Toleransi',
        };
    }
}
