<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regresi ADHOC-297 P4 (view realtime): dengan `<body hx-boost="true">`,
 * `window.Echo` hidup sepanjang tab sementara halaman berganti tanpa reload.
 * `Echo.private(ch).listen()` langsung di view ⇒ tiap kunjungan menumpuk
 * handler baru di channel yang sama, dan handler halaman lama tetap jalan di
 * halaman berikutnya (fetch/refresh/toast ganda).
 *
 * Aturan: listener milik halaman WAJIB lewat `window.pageEcho()` (lepas
 * otomatis begitu #main-content halaman itu terganti). Pengecualian cuma
 * listener sepanjang-tab yang punya guard sekali-per-dokumen sendiri.
 */
class ListenerRealtimeHalamanTidakMenumpukTest extends TestCase
{
    /**
     * Listener sepanjang tab — guard `window.__*EchoBound` sendiri.
     *
     * @var list<string>
     */
    private const GLOBAL_LISTENERS = [
        'components/notification-dropdown.blade.php',
        'tasks/own.blade.php',
    ];

    #[Test]
    public function view_tidak_memanggil_echo_private_langsung(): void
    {
        $offenders = collect(File::allFiles(resource_path('views')))
            ->filter(fn ($file) => str_ends_with($file->getFilename(), '.blade.php'))
            ->filter(fn ($file) => str_contains($file->getContents(), 'window.Echo.private('))
            ->map(fn ($file) => str_replace('\\', '/', $file->getRelativePathname()))
            ->reject(fn (string $path) => in_array($path, self::GLOBAL_LISTENERS, true))
            ->values()
            ->all();

        $this->assertSame([], $offenders, 'Listener Echo halaman wajib lewat window.pageEcho() — kalau tidak, menumpuk tiap navigasi boost.');
    }

    #[Test]
    public function page_echo_melepas_listener_saat_halaman_terganti(): void
    {
        $source = file_get_contents(resource_path('js/page-echo.js'));

        $this->assertStringContainsString("document.getElementById('main-content')", $source);
        $this->assertStringContainsString('.isConnected', $source);
        $this->assertStringContainsString('channel.stopListening(binding.event, binding.handler)', $source);
        $this->assertStringContainsString("document.addEventListener('htmx:afterSettle', sweep)", $source);
        $this->assertStringContainsString('window.pageEcho = pageEcho', $source);
    }

    #[Test]
    public function page_echo_dimuat_sebelum_echo_ready(): void
    {
        $source = file_get_contents(resource_path('js/echo.js'));

        $this->assertStringContainsString("import './page-echo';", $source);
        $this->assertLessThan(
            strpos($source, "new CustomEvent('echo:ready')"),
            strpos($source, "import './page-echo';")
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function viewDenganListenerDomContentLoaded(): array
    {
        return [
            'antrean verifikasi' => ['verifications/queue.blade.php'],
            'daftar tagihan' => ['invoices/index.blade.php'],
            'detail pelanggan' => ['customers/show.blade.php'],
        ];
    }

    /**
     * DOMContentLoaded cuma nyala sekali per tab — halaman yang dibuka lewat
     * navigasi boost gak pernah memasang listener realtime-nya.
     */
    #[Test]
    #[DataProvider('viewDenganListenerDomContentLoaded')]
    public function listener_realtime_dipasang_lewat_on_page_ready(string $view): void
    {
        $source = file_get_contents(resource_path('views/'.$view));

        $this->assertMatchesRegularExpression('/window\.onPageReady\(function \(\) \{\s*if \(typeof window\.Echo/', $source);
    }
}
