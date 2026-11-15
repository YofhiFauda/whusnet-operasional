<?php

namespace App\Support;

/**
 * Deteksi merek HP dari User-Agent (ADHOC-288 Tahap 8, dasar dashboard
 * delivery rate per merek/OS dan halaman onboarding "Aktifkan notifikasi").
 * Daftar regex sesuai `docs/plan/notifications/Web Push Notification
 * Laravel + Next.js yang Tahan Battery Saver.md` §3 — perkiraan dari string
 * UA, BUKAN deteksi presisi (nama menu whitelist beda tiap versi OS).
 */
class DeviceBrandDetector
{
    /**
     * @var array<string, string>
     */
    private const PATTERNS = [
        '/xiaomi|redmi|poco|miui/i' => 'Xiaomi/Redmi/POCO',
        '/oppo|cph\d|realme|rmx\d/i' => 'OPPO/realme',
        '/vivo/i' => 'vivo',
        '/samsung|sm-/i' => 'Samsung',
        '/iphone|ipad/i' => 'iPhone/iPad',
    ];

    public static function detect(?string $userAgent): string
    {
        if (empty($userAgent)) {
            return 'Tidak diketahui';
        }

        foreach (self::PATTERNS as $pattern => $brand) {
            if (preg_match($pattern, $userAgent)) {
                return $brand;
            }
        }

        return 'Lainnya';
    }
}
