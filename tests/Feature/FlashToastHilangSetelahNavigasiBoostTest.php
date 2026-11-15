<?php

namespace Tests\Feature;

use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regresi 2026-11-11: PIC Gudang tekan "Serahkan Barang Sekarang", server
 * nolak (`back()->with('error', ...)`), tapi layar diam total — gak ada
 * toast apa pun. Penyebab: pemanggil `Toast.error(session('error'))` ada di
 * dalam `<script id="toast-script" hx-preserve>` DAN dibungkus
 * `DOMContentLoaded`. Submit form lewat hx-boost = swap body, bukan page
 * load: script hx-preserve gak dieksekusi ulang (versi lama yang dipakai,
 * flash-nya kosong) & `DOMContentLoaded` gak pernah nyala lagi. Semua flash
 * success/error/validasi sesudah navigasi boost hilang tanpa jejak.
 */
class FlashToastHilangSetelahNavigasiBoostTest extends TestCase
{
    private function renderToast(): string
    {
        return (string) $this->blade('<x-toast />');
    }

    private function preservedScript(string $html): string
    {
        $this->assertMatchesRegularExpression('/<script id="toast-script" hx-preserve="true">(.*?)<\/script>/s', $html);
        preg_match('/<script id="toast-script" hx-preserve="true">(.*?)<\/script>/s', $html, $m);

        return $m[1];
    }

    private function flashScript(string $html): string
    {
        $this->assertMatchesRegularExpression('/<script id="toast-flash">(.*?)<\/script>/s', $html);
        preg_match('/<script id="toast-flash">(.*?)<\/script>/s', $html, $m);

        return $m[1];
    }

    #[Test]
    public function flash_error_dipanggil_dari_script_yang_tidak_di_preserve(): void
    {
        view()->share('errors', new ViewErrorBag);
        session()->flash('error', 'Cabang Caruban bukan gudang yang Anda kelola sebagai PIC Gudang.');

        $html = $this->renderToast();

        $this->assertStringNotContainsString('Toast.error(', $this->preservedScript($html));
        $this->assertStringContainsString('bukan gudang yang Anda kelola', $this->flashScript($html));
    }

    #[Test]
    public function flash_tidak_menunggu_domcontentloaded_kalau_dokumen_sudah_siap(): void
    {
        view()->share('errors', new ViewErrorBag);
        session()->flash('success', 'Serah terima berhasil.');

        $script = $this->flashScript($this->renderToast());

        // Swap boost terjadi saat readyState sudah 'complete' — wajib ada
        // jalur langsung jalan, bukan cuma listener DOMContentLoaded.
        $this->assertStringContainsString("document.readyState === 'loading'", $script);
        $this->assertStringContainsString('Serah terima berhasil.', $script);
    }

    #[Test]
    public function toast_notifikasi_realtime_dipanggil_sebagai_method(): void
    {
        // `(Toast[type] || Toast.info)(...)` melepas `this` ⇒ "this.show is
        // not a function" (browser log 2026-11-11), notifikasi realtime gak muncul.
        $source = file_get_contents(resource_path('views/components/notification-dropdown.blade.php'));

        $this->assertDoesNotMatchRegularExpression('/\(\s*window\.Toast\[[^\]]+\]\s*\|\|/', $source);
        $this->assertStringContainsString('window.Toast[type](notification.title, notification.message)', $source);
    }

    #[Test]
    public function error_validasi_juga_lewat_script_flash(): void
    {
        view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag(['lines' => ['Baris barang wajib diisi.']])));

        $html = $this->renderToast();

        $this->assertStringNotContainsString('Baris barang wajib diisi.', $this->preservedScript($html));
        $this->assertStringContainsString('Baris barang wajib diisi.', $this->flashScript($html));
    }
}
