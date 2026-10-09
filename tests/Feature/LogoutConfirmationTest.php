<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tombol Keluar di layout operasional harus minta konfirmasi dulu.
 * Dulu `logout-form` langsung di-submit tanpa pesan, jadi sekali klik
 * user langsung ter-logout (sering kepencet tidak sengaja di sidebar).
 * Global submit-confirm di layout sengaja melewati logout-form, jadi
 * konfirmasinya ditaruh di onclick tombol.
 */
class LogoutConfirmationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_logout_buttons_ask_for_confirmation_before_submitting(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $response = $this->get('/customers');

        $response->assertOk();
        $response->assertSee("window.Confirm('Konfirmasi Keluar', 'Yakin ingin keluar dari sistem?'", false);
        $response->assertDontSee("onclick=\"event.preventDefault(); document.getElementById('logout-form').submit();\"", false);
    }
}
