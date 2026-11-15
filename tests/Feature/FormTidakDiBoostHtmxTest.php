<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regresi ADHOC-297 (P1): `<body hx-boost="true">` ikut mem-boost SEMUA
 * `<form>`. Listener submit htmx di elemen form memanggil preventDefault()
 * lalu langsung mengirim XHR — tanpa peduli handler lain sudah membatalkan
 * event. Akibat yang terbukti (simulasi jsdom pakai htmx 2.0.11 repo):
 *   - dialog konfirmasi global (layouts/app.blade.php) gak pernah tampil;
 *   - `onsubmit="return confirm()"` → klik Batal tetap terkirim;
 *   - handler `confirmAction`/fetch → terkirim dobel;
 *   - `form.action` yang diubah JS → terkirim ke URL lama;
 *   - `target="_blank"` → menimpa tab sendiri.
 *
 * Keputusan user 2026-11-13: form kembali submit native (reload penuh),
 * navigasi link tetap boost. Perilaku browser-nya gak bisa diuji PHPUnit,
 * jadi yang dijaga di sini kontraknya di sumber (pola sama seperti
 * PostTargetRenderedServerSideTest).
 */
class FormTidakDiBoostHtmxTest extends TestCase
{
    private function appJs(): string
    {
        return file_get_contents(resource_path('js/app.js'));
    }

    #[Test]
    public function semua_form_dikeluarkan_dari_boost_sebelum_htmx_memprosesnya(): void
    {
        $js = $this->appJs();

        $this->assertStringContainsString("addEventListener('htmx:beforeProcessNode'", $js);
        $this->assertMatchesRegularExpression("/tagName === 'FORM'/", $js);
        $this->assertStringContainsString("setAttribute('hx-boost', 'false')", $js);
    }

    #[Test]
    public function form_yang_menulis_hx_boost_sendiri_tidak_ditimpa(): void
    {
        // Opt-in eksplisit (hx-boost="true" di form) tetap dihormati.
        $this->assertStringContainsString("hasAttribute('hx-boost')", $this->appJs());
    }

    #[Test]
    public function fallback_response_error_hanya_untuk_navigasi_get(): void
    {
        // GET ke URL milik POST/PUT/DELETE = 405 "tidak terjadi apa-apa".
        $js = $this->appJs();

        $this->assertMatchesRegularExpression(
            "/htmx:responseError'[\s\S]*?verb[\s\S]*?!== 'get'[\s\S]*?return;[\s\S]*?window\.location\.href/",
            $js
        );
    }

    #[Test]
    public function body_tetap_boost_untuk_navigasi_link(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString('<body hx-boost="true"', $layout);
    }
}
