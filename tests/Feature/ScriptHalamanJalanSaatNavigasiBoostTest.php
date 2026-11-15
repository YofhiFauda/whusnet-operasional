<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regresi ADHOC-297 P3: `<body hx-boost="true">` mengganti halaman tanpa
 * reload, jadi `DOMContentLoaded` cuma nyala SEKALI per tab. Script halaman
 * yang menunggu event itu tidak pernah jalan kalau halamannya dibuka lewat
 * klik menu — form tidak terinisialisasi (wizard pelanggan, metode bayar,
 * cascade wilayah, matrix role, …) sampai user me-refresh manual.
 *
 * Aturan: script halaman memakai `window.onPageReady(fn)` (layouts/app).
 */
class ScriptHalamanJalanSaatNavigasiBoostTest extends TestCase
{
    /**
     * Pengecualian beralasan:
     * - layouts/app: definisi onPageReady sendiri.
     * - components/toast: flash sudah langsung jalan kalau dokumen siap
     *   (cabang `readyState`), DOMContentLoaded cuma untuk load pertama.
     * - dashboard: render chart dipanggil langsung / lewat `chart:ready`;
     *   DOMContentLoaded cuma cadangan load pertama.
     *
     * @var list<string>
     */
    private const ALLOWED = [
        'layouts/app.blade.php',
        'components/toast.blade.php',
        'dashboard.blade.php',
    ];

    #[Test]
    public function view_tidak_menunggu_dom_content_loaded(): void
    {
        $offenders = collect(File::allFiles(resource_path('views')))
            ->filter(fn ($file) => str_ends_with($file->getFilename(), '.blade.php'))
            ->filter(fn ($file) => preg_match('/addEventListener\(\s*[\'"]DOMContentLoaded/', $file->getContents()) === 1)
            ->map(fn ($file) => str_replace('\\', '/', $file->getRelativePathname()))
            ->reject(fn (string $path) => in_array($path, self::ALLOWED, true))
            ->values()
            ->all();

        $this->assertSame([], $offenders, 'Pakai window.onPageReady(fn) — DOMContentLoaded tidak nyala lagi setelah navigasi hx-boost.');
    }

    #[Test]
    public function masking_rupiah_layout_memasang_listener_document_sekali_per_tab(): void
    {
        $source = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString('if (window.__rupiahBound) {', $source);
        $this->assertStringContainsString('window.onPageReady(formatAwal);', $source);
    }

    #[Test]
    public function countdown_antrean_verifikasi_tidak_menumpuk_interval(): void
    {
        $source = file_get_contents(resource_path('views/verifications/queue.blade.php'));

        $this->assertStringContainsString('clearInterval(window.__verificationCountdownTimer);', $source);
    }

    #[Test]
    public function spinner_submit_master_menempel_ke_form_halaman_bukan_logout(): void
    {
        foreach (['master/pop/create', 'master/pop/edit', 'master/distribusi/create', 'master/distribusi/edit'] as $view) {
            $source = file_get_contents(resource_path("views/{$view}.blade.php"));

            $this->assertStringContainsString("document.querySelector('#main-content form')", $source, $view);
            $this->assertStringNotContainsString("const form = document.querySelector('form')", $source, $view);
        }
    }
}
