<?php

namespace Tests\Feature\Api\CustomerPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\CustomerPortal\Concerns\InteractsWithPortalAuth;
use Tests\TestCase;

/**
 * `/auth/claim` STUB — modul QR/PIN (docs/plan/qr-code/ §7.6) nol kode dan
 * nol keputusan operasional, keputusan user 2026-08-24: fondasi Fase 2 lain
 * dibangun penuh, endpoint ini ditahan sampai modul itu kelar. Rate limiter
 * TETAP terpasang supaya begitu diaktifkan nanti tidak lupa memasangnya.
 */
class PortalClaimStubReturns501Test extends TestCase
{
    use InteractsWithPortalAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortalClientSecret();
    }

    public function test_claim_mengembalikan_501_pesan_jelas(): void
    {
        $response = $this->withHeaders($this->portalClientHeaders())
            ->postJson('/api/customer-portal/auth/claim', ['login_id' => 'PNG-RQ000631', 'pin' => '123456']);

        $response->assertStatus(501);
        $this->assertStringContainsString('QR/PIN', $response->json('message'));
    }

    public function test_claim_tetap_kena_rate_limiter_auth_dan_auth_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->withHeaders($this->portalClientHeaders())
                ->postJson('/api/customer-portal/auth/claim', ['login_id' => 'PNG-RQ000631', 'pin' => '000000']);
        }

        $response = $this->withHeaders($this->portalClientHeaders())
            ->postJson('/api/customer-portal/auth/claim', ['login_id' => 'PNG-RQ000631', 'pin' => '000000']);

        $response->assertStatus(429);
    }
}
