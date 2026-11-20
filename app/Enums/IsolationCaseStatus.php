<?php

namespace App\Enums;

/**
 * Status lifecycle kasus isolir (docs/plan/Rancangan_Monitoring_Isolir_Pelanggan.md
 * §6.1/§21). TERPISAH dari status layanan (`Customer::status`/`active`/
 * `suspended`) dan status tagihan (`InvoiceStatus`) — jangan dicampur
 * (prinsip §4.1 dokumen tersebut).
 */
enum IsolationCaseStatus: string
{
    case WAITING_ISOLATION = 'waiting_isolation';
    case READY_FOR_ISOLATION = 'ready_for_isolation';
    case ISOLATION_IN_PROGRESS = 'isolation_in_progress';
    case ISOLATION_FAILED = 'isolation_failed';
    case SUSPENDED = 'suspended';
    case RESTORATION_PENDING = 'restoration_pending';
    case RESTORATION_IN_PROGRESS = 'restoration_in_progress';
    case RESTORATION_FAILED = 'restoration_failed';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::WAITING_ISOLATION => 'Menunggu Isolir',
            self::READY_FOR_ISOLATION => 'Siap Diisolir',
            self::ISOLATION_IN_PROGRESS => 'Proses Isolir',
            self::ISOLATION_FAILED => 'Isolir Gagal',
            self::SUSPENDED => 'Sudah Diisolir',
            self::RESTORATION_PENDING => 'Menunggu Pemulihan',
            self::RESTORATION_IN_PROGRESS => 'Proses Pemulihan',
            self::RESTORATION_FAILED => 'Pemulihan Gagal',
            self::COMPLETED => 'Selesai',
            self::CANCELLED => 'Dibatalkan',
        };
    }

    /**
     * Kasus dianggap "aktif" (masih perlu dipantau/ditindaklanjuti) selama
     * belum `completed`/`cancelled` — dipakai guard BR-03 (satu kasus aktif
     * per layanan).
     */
    public function isActive(): bool
    {
        return ! in_array($this, [self::COMPLETED, self::CANCELLED], true);
    }

    /**
     * Tab "Menunggu Isolir" (§8.3) — antrean REVIEW ADMIN. Admin belum
     * memutuskan (default) atau sudah menoleransi; NOC TIDAK PERNAH melihat
     * kasus di grup ini (follow-up 2026-11-19: pisahkan antrean Admin dari
     * antrean eksekusi NOC biar gak ada miss komunikasi "NOC pikir sudah di-acc,
     * padahal belum").
     *
     * @return list<self>
     */
    public static function waitingIsolationGroup(): array
    {
        return [self::WAITING_ISOLATION];
    }

    /**
     * Tab "Siap Isolir" (§8.3) — antrean EKSEKUSI NOC, isinya CUMA kasus yang
     * sudah di-"Eksekusi" Admin (`IsolationCaseService::setAdminDecision()`).
     * `ISOLATION_FAILED` ikut di sini (bukan balik ke antrean Admin) karena
     * gagal eksekusi itu urusan retry NOC, keputusan Admin sudah final begitu
     * sampai sini.
     *
     * @return list<self>
     */
    public static function readyForIsolationGroup(): array
    {
        return [self::READY_FOR_ISOLATION, self::ISOLATION_IN_PROGRESS, self::ISOLATION_FAILED];
    }

    /**
     * Gabungan dua grup di atas — "belum pernah disuspend sama sekali",
     * dipakai gerbang yang berlaku SELAMA kasus belum benar-benar diisolir
     * di lapangan (koreksi/tarik keputusan Admin, batalkan kasus, auto-batal
     * kalau lunas sebelum isolir). Lihat `IsolationCaseService::setAdminDecision()`,
     * `cancelCase()`, `handlePaymentVerified()`.
     *
     * @return list<self>
     */
    public static function preSuspensionGroup(): array
    {
        return [...self::waitingIsolationGroup(), ...self::readyForIsolationGroup()];
    }

    /**
     * Tab "Pemulihan".
     *
     * @return list<self>
     */
    public static function restorationGroup(): array
    {
        return [self::RESTORATION_PENDING, self::RESTORATION_IN_PROGRESS, self::RESTORATION_FAILED];
    }

    /**
     * Tab "Riwayat".
     *
     * @return list<self>
     */
    public static function historyGroup(): array
    {
        return [self::COMPLETED, self::CANCELLED];
    }
}
