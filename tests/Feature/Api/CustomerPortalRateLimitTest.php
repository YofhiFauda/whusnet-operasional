<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Limiter `customer-portal-api` (docs/api/api-portal-pelanggan/, Fase 0) —
 * 120 req/menit, keyed per bearer token atau IP kalau tanpa token
 * (AppServiceProvider::boot()). Test ini loop 120x lalu pastikan permintaan
 * ke-121 kena 429, pola sama dengan
 * NetworkAssignmentTest::test_rate_limit_20_per_menit.
 */
class CustomerPortalRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_ping_rate_limit_120_per_menit(): void
    {
        for ($i = 0; $i < 120; $i++) {
            $this->getJson('/api/customer-portal/ping');
        }

        $response = $this->getJson('/api/customer-portal/ping');

        $response->assertStatus(429);
    }
}
