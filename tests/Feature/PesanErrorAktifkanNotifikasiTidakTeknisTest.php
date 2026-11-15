<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Gejala: notifikasi sudah aktif di satu browser, buka browser lain, popup
 * "Aktifkan Notifikasi" muncul, klik Aktifkan ⇒ toast menampilkan pesan
 * mentah DOMException dari PushManager (mis. "Registration failed - push
 * service error") karena err.message diteruskan apa adanya.
 *
 * Sekarang semua error alur subscribe diterjemahkan di webpush.js sebelum
 * sampai ke toast (banner & halaman Profil sama-sama lewat
 * window.WebPush.subscribe()).
 */
class PesanErrorAktifkanNotifikasiTidakTeknisTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function error_subscribe_diterjemahkan_sebelum_sampai_toast(): void
    {
        $js = file_get_contents(resource_path('js/webpush.js'));

        // Semua jalur subscribe dibungkus penerjemah error.
        $this->assertStringContainsString('throw friendlySubscribeError(err);', $js);

        // Kasus yang paling sering di browser kedua: layanan push browser
        // gak bisa dihubungi (Brave, incognito) & izin ditolak.
        $this->assertStringContainsString("name === 'AbortError' || /push service/i.test(message)", $js);
        $this->assertStringContainsString("name === 'NotAllowedError'", $js);

        // Detail teknis tetap tersedia buat debugging, cuma pindah ke console.
        $this->assertStringContainsString("console.error('[WebPush] Gagal mengaktifkan notifikasi:', err);", $js);
    }

    /**
     * Gejala produksi: VAPID_PUBLIC_KEY kosong ⇒ toast "Cannot read
     * properties of null (reading 'length')" dari urlBase64ToUint8Array(null).
     */
    #[Test]
    public function kunci_vapid_kosong_dijaga_sebelum_dikonversi(): void
    {
        $js = file_get_contents(resource_path('js/webpush.js'));

        $this->assertStringContainsString('if (!publicKey) {', $js);
        $this->assertLessThan(
            strpos($js, 'urlBase64ToUint8Array(publicKey)'),
            strpos($js, 'if (!publicKey) {'),
            'Guard publicKey harus jalan SEBELUM konversi.'
        );
    }

    #[Test]
    public function popup_tidak_ditawarkan_kalau_server_belum_punya_kunci_vapid(): void
    {
        config(['webpush.vapid.public_key' => null]);
        $this->loginAsAdmin();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Aktifkan Notifikasi Perangkat');
    }

    #[Test]
    public function popup_ditawarkan_kalau_kunci_vapid_ada(): void
    {
        config(['webpush.vapid.public_key' => 'kunci-uji']);
        $this->loginAsAdmin();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Aktifkan Notifikasi Perangkat');
    }

    /**
     * Gejala: popup ditutup lewat tombol X ⇒ besok (bahkan halaman
     * berikutnya) ditawari lagi, karena X manggil Dialog.close() langsung
     * tanpa lewat "Nanti Saja".
     */
    #[Test]
    public function tutup_popup_lewat_x_sama_dengan_nanti_saja(): void
    {
        $blade = file_get_contents(resource_path('views/components/webpush-banner.blade.php'));

        $this->assertMatchesRegularExpression(
            "/onClose: function \\(\\) \\{\\s*localStorage\\.setItem\\(dismissKey, '1'\\);/",
            $blade
        );
    }

    /**
     * Gejala: aktivasi gagal ⇒ popup muncul lagi di tiap halaman karena
     * cuma sukses / "Nanti Saja" yang menandai sudah ditawari.
     */
    #[Test]
    public function gagal_aktivasi_tetap_menandai_popup_sudah_ditawarkan(): void
    {
        $blade = file_get_contents(resource_path('views/components/webpush-banner.blade.php'));
        $catch = substr($blade, strpos($blade, '.catch(function (err) {'));

        $this->assertStringContainsString(
            "localStorage.setItem(dismissKey, '1');",
            substr($catch, 0, strpos($catch, 'window.Dialog.close();'))
        );
    }
}
