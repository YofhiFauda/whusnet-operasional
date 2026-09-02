<?php

namespace Tests\Feature\Api\CustomerPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Api\CustomerPortal\Concerns\InteractsWithPortalAuth;
use Tests\TestCase;

/**
 * `StrongPortalPassword` — tolak password yang mengandung `login_id`/nomor
 * HP, dan tolak daftar password umum (business-logic.md §Aktivasi akun).
 */
class PortalPasswordRejectsCommonAndPersonalDataTest extends TestCase
{
    use InteractsWithPortalAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortalClientSecret();
    }

    private function attemptChangeTo(string $newPassword): TestResponse
    {
        $seed = $this->seedActivePortalCustomer();
        $session = $this->loginAndGetTokens($seed['login_id']);

        return $this->withHeaders($this->authenticatedHeaders($session['access_token']))
            ->putJson('/api/customer-portal/me/password', [
                'current_password' => self::PORTAL_TEST_PASSWORD,
                'new_password' => $newPassword,
            ]);
    }

    public function test_password_mengandung_login_id_ditolak(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $session = $this->loginAndGetTokens($seed['login_id']);

        $response = $this->withHeaders($this->authenticatedHeaders($session['access_token']))
            ->putJson('/api/customer-portal/me/password', [
                'current_password' => self::PORTAL_TEST_PASSWORD,
                'new_password' => strtolower($seed['login_id']).'-extra',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('new_password');
    }

    public function test_password_terlalu_pendek_ditolak(): void
    {
        $this->attemptChangeTo('Pendek1')->assertStatus(422);
    }

    public function test_password_dari_daftar_umum_ditolak(): void
    {
        $this->attemptChangeTo('password123')->assertStatus(422);
    }

    public function test_password_kuat_dan_unik_diterima(): void
    {
        $this->attemptChangeTo('Layang-Layang-Sore-42')->assertOk();
    }
}
