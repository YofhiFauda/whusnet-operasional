<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regresi: di mobile (browser & PWA) tarik-ke-bawah tidak bisa memuat ulang
 * halaman. Layout mengunci `html, body { overflow: hidden; overscroll-behavior:
 * none }` dan yang bergulir cuma `#main-content`, jadi pull-to-refresh bawaan
 * browser mati; di PWA standalone fitur itu memang tidak ada.
 *
 * Penggantinya resources/js/pull-to-refresh.js — perilaku gesturnya diuji di
 * tests/Browser/scenarios/pull-to-refresh.spec.js (Playwright). Yang dijaga di
 * sini kontraknya di sumber: modul ikut bundle, target `#main-content` tetap
 * ada, dan Peta Infrastruktur (kanvas di-pan pakai jari) mematikannya.
 */
class PullToRefreshMobileTerpasangTest extends TestCase
{
    #[Test]
    public function modul_pull_to_refresh_ikut_dibundel_di_app_js(): void
    {
        $this->assertStringContainsString(
            "import './pull-to-refresh';",
            file_get_contents(resource_path('js/app.js'))
        );
    }

    #[Test]
    public function layout_punya_main_content_dan_peta_mematikan_pull_to_refresh(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString('<main id="main-content"', $layout);
        $this->assertMatchesRegularExpression(
            "/<main id=\"main-content\"[^>]*@if \(Request::routeIs\('network\.map\.\*'\)\) data-pull-to-refresh=\"off\" @endif/",
            $layout
        );
    }

    #[Test]
    public function modul_membaca_target_dan_opt_out_yang_sama_dengan_layout(): void
    {
        $js = file_get_contents(resource_path('js/pull-to-refresh.js'));

        $this->assertStringContainsString("getElementById('main-content')", $js);
        $this->assertStringContainsString("dataset.pullToRefresh === 'off'", $js);
    }
}
