<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regresi ADHOC-297 P8: `<body hx-boost="true">` mengeksekusi ulang script
 * halaman tiap navigasi, tapi `document`/`window` tidak ikut ditukar. Listener
 * yang dipasang langsung ke sana menumpuk per kunjungan: Escape/Ctrl+D jalan
 * berkali-kali (2 listener toggle tema = tema tidak berubah), `payment-recorded`
 * diproses dobel, dragover papan FOP auto-scroll makin cepat.
 *
 * Aturan: script halaman memakai `window.pageListen(target, type, fn)`, atau
 * `{ once: true }` untuk listener sekali pakai.
 */
class ListenerGlobalTidakMenumpukTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Pengecualian beralasan:
     * - layouts/app: definisi pageListen/onPageReady di <head> (sekali per tab)
     *   + blok yang sudah dijaga flag per tab (Rupiah).
     * - components/toast: dibungkus hx-preserve, script-nya tidak dieksekusi ulang.
     *
     * @var list<string>
     */
    private const ALLOWED_FILES = [
        'layouts/app.blade.php',
        'components/toast.blade.php',
    ];

    #[Test]
    public function view_tidak_memasang_listener_global_tanpa_page_listen(): void
    {
        $offenders = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            $path = str_replace('\\', '/', $file->getRelativePathname());
            if (! str_ends_with($path, '.blade.php') || in_array($path, self::ALLOWED_FILES, true)) {
                continue;
            }

            foreach (explode("\n", $file->getContents()) as $index => $line) {
                if (preg_match('/\b(document|window)\.addEventListener\(\s*[\'"]([^\'"]+)/', $line, $match) !== 1) {
                    continue;
                }
                if ($match[2] === 'DOMContentLoaded' || str_contains($line, 'once: true')) {
                    continue;
                }
                $offenders[] = $path.':'.($index + 1).' '.$match[2];
            }
        }

        $this->assertSame([], $offenders, 'Pakai window.pageListen(target, type, fn) — listener document/window menumpuk tiap navigasi hx-boost.');
    }

    #[Test]
    public function page_listen_mencopot_listener_halaman_yang_ditinggal(): void
    {
        $source = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString('window.pageListen = function (target, type, handler, options) {', $source);
        $this->assertMatchesRegularExpression(
            "/addEventListener\\('htmx:afterSettle', function \\(\\) \\{.*?if \\(!b\\.anchor\\.isConnected\\) \\{\\s*b\\.target\\.removeEventListener\\(b\\.type, b\\.wrapped, b\\.options\\);/s",
            $source
        );
        // Helper wajib di <head> — <head> tidak ikut ditukar, jadi registry-nya
        // hidup sepanjang tab dan tidak ikut terdefinisi ulang.
        $this->assertLessThan(strpos($source, '</head>'), strpos($source, 'window.pageListen = function'));
    }

    #[Test]
    public function shortcut_dan_konfirmasi_submit_layout_tidak_menumpuk(): void
    {
        $source = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString("window.pageListen(document, 'keydown', e => {", $source);
        $this->assertStringContainsString("window.pageListen(document, 'submit', function(e) {", $source);
    }

    #[Test]
    public function banner_realtime_bind_pusher_sekali_per_tab(): void
    {
        $source = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertMatchesRegularExpression(
            '/if \(window\.__realtimeBanner\) \{\s*window\.__realtimeBanner\.sync\(\);\s*return;/',
            $source
        );
        $this->assertSame(1, substr_count($source, "pusher.connection.bind('state_change'"));
    }

    #[Test]
    public function style_halaman_dirender_di_body_supaya_ikut_navigasi_boost(): void
    {
        $source = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $bodyStart = strpos($source, '<body hx-boost="true"');

        $this->assertNotFalse($bodyStart);
        $this->assertGreaterThan($bodyStart, strpos($source, "@stack('styles')"));
        $this->assertGreaterThan($bodyStart, strpos($source, "@yield('styles')"));
    }

    #[Test]
    public function style_halaman_master_node_ikut_dirender(): void
    {
        $this->loginAsAdmin();

        $this->get(route('network.nodes.index'))
            ->assertOk()
            ->assertSee('.tree-line::before', false);
    }
}
