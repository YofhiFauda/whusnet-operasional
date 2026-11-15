<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regresi: ikon aplikasi terpasang = logo penuh tanpa padding.
 *
 * Setelah data situs dihapus, user selalu mendarat di halaman login dan
 * meng-install dari sana. Halaman login dulu tanpa <link rel="manifest">,
 * sehingga Chrome bikin pintasan biasa dari favicon — bukan ikon PWA
 * ber-padding dari manifest.
 */
class PwaInstallIconLoginPageTest extends TestCase
{
    #[Test]
    public function halaman_login_membawa_manifest_dan_apple_touch_icon(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee('images/apple-touch-icon.png', false);
    }

    #[Test]
    public function manifest_memakai_ikon_maskable_berpadding_bukan_logo_penuh(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.json')), true);
        $icons = collect($manifest['icons']);

        $this->assertTrue($icons->contains('purpose', 'maskable'));
        $this->assertFalse($icons->contains(fn ($icon) => str_contains($icon['src'], 'logo-192')));

        foreach ($icons as $icon) {
            $path = public_path(strtok(ltrim($icon['src'], '/'), '?'));
            $this->assertFileExists($path);
            [$width, $height] = getimagesize($path);
            $this->assertSame($icon['sizes'], "{$width}x{$height}");
        }
    }
}
