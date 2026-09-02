<?php

namespace Tests\Feature\Api\CustomerPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\CustomerPortal\Concerns\InteractsWithPortalAuth;
use Tests\TestCase;

/**
 * Limiter `customer-portal-auth` (5/15menit per IP+login_id) SENDIRIAN gak
 * cukup — memberi ember baru tiap login_id, jadi penyapuan banyak login_id
 * beda dari satu IP gak pernah menyentuh batas itu (keputusan.md §1).
 * `customer-portal-auth-ip` (30/15menit murni per IP) yang menutupnya.
 */
class PortalLoginIpSweepThrottleTest extends TestCase
{
    use InteractsWithPortalAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortalClientSecret();
    }

    public function test_31_percobaan_ke_31_login_id_berbeda_dari_satu_ip_kena_429(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $response = $this->loginJson("XXX-NOTFOUND{$i}", 'password-asal');
            $response->assertStatus(401);
        }

        $response = $this->loginJson('XXX-NOTFOUND30', 'password-asal');

        $response->assertStatus(429);
    }
}
