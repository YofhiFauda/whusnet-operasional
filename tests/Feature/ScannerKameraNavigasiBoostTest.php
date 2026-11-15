<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regresi ADHOC-297 P5: dengan `<body hx-boost="true">` module scanner cuma
 * dieksekusi sekali per tab.
 *   - Dulu blok scanner dipasang di top-level module ⇒ kunjungan ke-2 ke
 *     halaman scan (Scan Barang, Receive, Transfer, Issue, Scan QR) tombol
 *     scan / kamera mati sampai refresh manual.
 *   - Kamera cuma dimatikan di `pagehide`, yang gak nyala saat navigasi boost
 *     ⇒ kamera tetap merekam di background setelah pindah menu.
 */
class ScannerKameraNavigasiBoostTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function scannerModules(): array
    {
        return [
            'barcode gudang' => ['js/barcode-scan.js', 'mountBarcodeScanners', 'stopScanners'],
            'QR pelanggan' => ['js/qr-scan.js', 'mountQrScanner', 'stopQrScanner'],
        ];
    }

    #[Test]
    #[DataProvider('scannerModules')]
    public function scanner_dipasang_ulang_tiap_navigasi_boost(string $module, string $mount, string $stop): void
    {
        $source = file_get_contents(resource_path($module));

        $this->assertStringContainsString("function {$mount}(", $source);
        $this->assertMatchesRegularExpression(
            "/addEventListener\\('htmx:afterSettle', \\(\\) => \\{\\s*{$stop}\\(true\\);\\s*{$mount}\\(\\);/",
            $source
        );
    }

    #[Test]
    #[DataProvider('scannerModules')]
    public function kamera_dimatikan_sebelum_body_diganti(string $module, string $mount, string $stop): void
    {
        $source = file_get_contents(resource_path($module));

        $this->assertMatchesRegularExpression(
            "/addEventListener\\('htmx:beforeSwap', \\(event\\) => \\{\\s*if \\(event\\.detail\\.target === document\\.body\\) \\{\\s*{$stop}\\(false\\);/",
            $source
        );
        $this->assertStringContainsString("window.addEventListener('pagehide', () => {$stop}(false));", $source);
    }

    #[Test]
    #[DataProvider('scannerModules')]
    public function stream_yang_terbuka_setelah_halaman_ditinggal_langsung_dimatikan(string $module): void
    {
        $this->assertMatchesRegularExpression(
            '/start\(\)\.then\(\(\) => \{\s*if \(!\w+\.isConnected\) \{\s*scanner\.stop\(\);/',
            file_get_contents(resource_path($module))
        );
    }

    #[Test]
    public function blok_scanner_tidak_dipasang_dua_kali(): void
    {
        $this->assertStringContainsString('wrapper.dataset.barcodeMounted', file_get_contents(resource_path('js/barcode-scan.js')));
        $this->assertStringContainsString('videoEl.dataset.qrMounted', file_get_contents(resource_path('js/qr-scan.js')));
    }
}
