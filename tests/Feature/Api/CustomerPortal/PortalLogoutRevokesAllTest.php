<?php

namespace Tests\Feature\Api\CustomerPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\CustomerPortal\Concerns\InteractsWithPortalAuth;
use Tests\TestCase;

/**
 * `/auth/logout` (bukan cuma `/auth/logout-all`) mencabut SEMUA token
 * pelanggan itu — keputusan user 2026-08-24: access token tidak punya
 * rantai ke refresh pasangannya tanpa client kirim refresh_token tambahan,
 * jadi logout satu-sesi disamakan perilakunya dengan logout-all.
 */
class PortalLogoutRevokesAllTest extends TestCase
{
    use InteractsWithPortalAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortalClientSecret();
    }

    public function test_logout_mencabut_semua_token_pelanggan_itu(): void
    {
        $seed = $this->seedActivePortalCustomer();

        // Dua "sesi" (dua kali login berturut — device berbeda).
        $sessionA = $this->loginAndGetTokens($seed['login_id']);
        $sessionB = $this->loginAndGetTokens($seed['login_id']);

        $logout = $this->withHeaders($this->authenticatedHeaders($sessionA['access_token']))
            ->postJson('/api/customer-portal/auth/logout');
        $logout->assertOk();

        // Sesi A (yang logout) mati.
        $meA = $this->withHeaders($this->authenticatedHeaders($sessionA['access_token']))
            ->getJson('/api/customer-portal/me');
        $meA->assertStatus(401);

        // Sesi B (device lain) IKUT mati — bukti behavior disamakan logout-all.
        $meB = $this->withHeaders($this->authenticatedHeaders($sessionB['access_token']))
            ->getJson('/api/customer-portal/me');
        $meB->assertStatus(401);
    }

    public function test_logout_all_mencabut_semua_token_pelanggan_itu(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $sessionA = $this->loginAndGetTokens($seed['login_id']);
        $sessionB = $this->loginAndGetTokens($seed['login_id']);

        $this->withHeaders($this->authenticatedHeaders($sessionA['access_token']))
            ->postJson('/api/customer-portal/auth/logout-all')
            ->assertOk();

        $this->withHeaders($this->authenticatedHeaders($sessionB['access_token']))
            ->getJson('/api/customer-portal/me')
            ->assertStatus(401);
    }
}
