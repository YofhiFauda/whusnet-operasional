<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regresi laporan user 2026-11-14: di Laporan PSB / Maintenance / C-REQ,
 * baris "Barang Dipasang (Modem / Roll Kabel / Pasif)" yang sudah disimpan
 * HILANG setelah halaman di-refresh, dan dropdown-nya kosong.
 *
 * Akar masalah (direproduksi di Chrome headless dengan HTML laporan PSB
 * tersimpan): `window.withLegacyOption` didefinisikan di app.js SETELAH
 * `Alpine.start()`. start() me-render halaman secara sinkron, baris prefill
 * (punya option_id) memanggil itemOptionsWithLegacy() → withLegacyOption()
 * yang belum ada ⇒ "Alpine Expression Error: withLegacyOption is not
 * defined" ⇒ label & daftar opsi combobox kosong. Baris baru gak kena karena
 * tanpa option_id fungsi itu tak dipanggil.
 */
class BarangDipasangHilangSetelahRefreshTest extends TestCase
{
    #[Test]
    public function helper_global_untuk_komponen_alpine_didefinisikan_sebelum_alpine_start(): void
    {
        $appJs = file_get_contents(resource_path('js/app.js'));

        $startPosition = strpos($appJs, "\nAlpine.start();");
        $this->assertNotFalse($startPosition, 'Alpine.start() tidak ditemukan di app.js.');

        $helperPosition = strpos($appJs, 'window.withLegacyOption = ');
        $this->assertNotFalse($helperPosition, 'window.withLegacyOption tidak ditemukan di app.js.');

        $this->assertLessThan(
            $startPosition,
            $helperPosition,
            'window.withLegacyOption harus didefinisikan SEBELUM Alpine.start() — kalau tidak, baris Barang Dipasang tersimpan kosong setelah refresh.'
        );
    }
}
