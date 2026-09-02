<?php

namespace Tests\Feature\Api\CustomerPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\CustomerPortal\Concerns\InteractsWithPortalAuth;
use Tests\TestCase;

/**
 * `PUT /me/password` — token lain mati, sesi PEMANGGIL tetap hidup (beda
 * dari logout yang cabut semua tanpa kecuali); `current_password` wajib
 * (sesi dicuri gak cukup ambil alih akun permanen).
 */
class PortalPasswordChangeRevokesOtherTokensTest extends TestCase
{
    use InteractsWithPortalAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortalClientSecret();
    }

    public function test_ganti_password_mencabut_token_lain_sesi_pemanggil_tetap_hidup(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $sessionCaller = $this->loginAndGetTokens($seed['login_id']);
        $sessionOther = $this->loginAndGetTokens($seed['login_id']);

        $response = $this->withHeaders($this->authenticatedHeaders($sessionCaller['access_token']))
            ->putJson('/api/customer-portal/me/password', [
                'current_password' => self::PORTAL_TEST_PASSWORD,
                'new_password' => 'Gajah-Terbang-Malam-77',
            ]);

        $response->assertOk();

        // Sesi pemanggil TETAP hidup.
        $this->withHeaders($this->authenticatedHeaders($sessionCaller['access_token']))
            ->getJson('/api/customer-portal/me')
            ->assertOk();

        // Sesi lain MATI.
        $this->withHeaders($this->authenticatedHeaders($sessionOther['access_token']))
            ->getJson('/api/customer-portal/me')
            ->assertStatus(401);

        // Password baru beneran kepakai buat login berikutnya.
        $this->loginJson($seed['login_id'], 'Gajah-Terbang-Malam-77')->assertOk();
    }

    public function test_current_password_wajib_dan_gagal_kalau_salah(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $session = $this->loginAndGetTokens($seed['login_id']);

        $response = $this->withHeaders($this->authenticatedHeaders($session['access_token']))
            ->putJson('/api/customer-portal/me/password', [
                'current_password' => 'password-yang-salah',
                'new_password' => 'Gajah-Terbang-Malam-77',
            ]);

        $response->assertStatus(422);

        // Password lama TETAP berfungsi.
        $this->loginJson($seed['login_id'], self::PORTAL_TEST_PASSWORD)->assertOk();
    }
}
