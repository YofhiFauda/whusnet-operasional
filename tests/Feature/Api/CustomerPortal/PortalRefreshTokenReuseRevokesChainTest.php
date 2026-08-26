<?php

namespace Tests\Feature\Api\CustomerPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\CustomerPortal\Concerns\InteractsWithPortalAuth;
use Tests\TestCase;

/**
 * Refresh token yang SUDAH dipakai (revoked) dipakai lagi = indikasi
 * pencurian. Flowchart.md eksplisit "cabut seluruh rantai, PAKSA LOGIN
 * ULANG" — diimplementasikan sebagai revoke SEMUA token pelanggan itu,
 * bukan cuma turunan token yang di-reuse.
 */
class PortalRefreshTokenReuseRevokesChainTest extends TestCase
{
    use InteractsWithPortalAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortalClientSecret();
    }

    public function test_refresh_dipakai_dua_kali_mencabut_semua_token_dan_paksa_login_ulang(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $tokens = $this->loginAndGetTokens($seed['login_id']);

        // Pemakaian pertama — sah, memutar rantai.
        $rotated = $this->withHeaders($this->portalClientHeaders())
            ->postJson('/api/customer-portal/auth/refresh', ['refresh_token' => $tokens['refresh_token']]);
        $rotated->assertOk();
        $newAccessToken = $rotated->json('access_token');

        // Pemakaian KEDUA atas refresh token LAMA yang sama — reuse.
        $reuse = $this->withHeaders($this->portalClientHeaders())
            ->postJson('/api/customer-portal/auth/refresh', ['refresh_token' => $tokens['refresh_token']]);

        $reuse->assertStatus(401);

        // Access token hasil rotasi yang SAH pun ikut tercabut — bukan cuma
        // turunan token yang di-reuse, tapi SEMUA token pelanggan itu.
        $meAfterReuse = $this->withHeaders($this->authenticatedHeaders($newAccessToken))
            ->getJson('/api/customer-portal/me');
        $meAfterReuse->assertStatus(401);
    }
}
