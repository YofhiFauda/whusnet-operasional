<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regresi ADHOC-297 bug #1 lanjutan (keputusan user 2026-11-14): teknisi yang
 * sedang membuka Tasks Saya menerima DUA tanda untuk satu jadwal task — toast
 * lonceng (AppNotification) + notifikasi OS (Web Push, AppPushNotification).
 *
 * Service worker sekarang tidak menampilkan notifikasi OS selama ada tab
 * Whusnet yang terlihat (toast sudah muncul di sana), tapi ack delivery tetap
 * dikirim. Aplikasi tertutup / di belakang / HP terkunci ⇒ push tampil normal.
 */
class PushTidakDobelSaatAplikasiTerbukaTest extends TestCase
{
    private function serviceWorker(): string
    {
        return file_get_contents(public_path('sw.js'));
    }

    #[Test]
    public function notifikasi_os_dilewati_kalau_ada_tab_terlihat(): void
    {
        $source = $this->serviceWorker();

        $this->assertStringContainsString("clients.matchAll({ type: 'window', includeUncontrolled: true })", $source);
        $this->assertStringContainsString("client.visibilityState === 'visible'", $source);

        // showNotification hanya dipanggil di cabang "tidak ada tab terlihat",
        // bukan langsung di awal handler seperti dulu.
        $this->assertStringNotContainsString('const tasks = [self.registration.showNotification(', $source);
    }

    #[Test]
    public function ack_delivery_tetap_dikirim(): void
    {
        $this->assertStringContainsString('fetch(payload.data.ackUrl)', $this->serviceWorker());
    }

    #[Test]
    public function sw_baru_langsung_aktif_tanpa_menunggu_tab_ditutup(): void
    {
        $source = $this->serviceWorker();

        $this->assertStringContainsString('self.skipWaiting()', $source);
        $this->assertStringContainsString('self.clients.claim()', $source);
    }
}
