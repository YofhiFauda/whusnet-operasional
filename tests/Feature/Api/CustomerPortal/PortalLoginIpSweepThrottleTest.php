<?php

namespace Tests\Feature\Api\CustomerPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\CustomerPortal\Concerns\InteractsWithPortalAuth;
use Tests\TestCase;

/**
 * Limiter `customer-portal-auth` (5/15menit per IP+login_id) SENDIRIAN gak
 * cukup — memberi ember baru tiap login_id, jadi penyapuan banyak login_id
 * beda dari satu IP gak pernah menyentuh batas itu (keputusan.md §1).
 * `customer-portal-auth-ip` (150/15menit murni per IP pelanggan; dulu 30, dinaikkan
 * karena ±30.000 pelanggan dan NAT bersama) yang menutupnya.
 */
class PortalLoginIpSweepThrottleTest extends TestCase
{
    use InteractsWithPortalAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortalClientSecret();
    }

    public function test_151_percobaan_ke_151_login_id_berbeda_dari_satu_ip_kena_429(): void
    {
        for ($i = 0; $i < 150; $i++) {
            $response = $this->loginJson("XXX-NOTFOUND{$i}", 'password-asal');
            $response->assertStatus(401);
        }

        $response = $this->loginJson('XXX-NOTFOUND150', 'password-asal');

        $response->assertStatus(429);
    }
}
