<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * config/cors.php (docs/api/api-portal-pelanggan/, Fase 0) — scope SEMPIT ke
 * `api/customer-portal/*` saja. Tiga hal yang wajib dibuktikan, bukan
 * diasumsikan: origin yang di-whitelist lolos, origin lain ditolak, dan
 * scope-nya TIDAK bocor ke route api-pop-distribusi (`api/v1/*`) yang sudah
 * ada — itu server-to-server, gak boleh diam-diam ikut kena kebijakan baru.
 */
class CustomerPortalCorsTest extends TestCase
{
    use RefreshDatabase;

    private const ALLOWED_ORIGIN = 'https://portal.contoh-whusnet.test';

    protected function setUp(): void
    {
        parent::setUp();

        config(['cors.allowed_origins' => [self::ALLOWED_ORIGIN]]);
    }

    private function preflight(string $uri, string $origin): TestResponse
    {
        return $this->withHeaders([
            'Origin' => $origin,
            'Access-Control-Request-Method' => 'GET',
        ])->options($uri);
    }

    public function test_preflight_dari_origin_portal_yang_di_whitelist_lolos(): void
    {
        $response = $this->preflight('/api/customer-portal/ping', self::ALLOWED_ORIGIN);

        $response->assertHeader('Access-Control-Allow-Origin', self::ALLOWED_ORIGIN);
    }

    public function test_preflight_dari_origin_lain_ditolak(): void
    {
        $response = $this->preflight('/api/customer-portal/ping', 'https://origin-asing.test');

        $this->assertNotSame(
            'https://origin-asing.test',
            $response->headers->get('Access-Control-Allow-Origin')
        );
    }

    public function test_route_pop_distribusi_tidak_ikut_kena_kebijakan_cors_baru(): void
    {
        // /api/v1/* di luar `paths` config/cors.php — HandleCors harus
        // melewatinya sama sekali, bukan cuma menolak origin-nya.
        $response = $this->preflight('/api/v1/pop-distribusi', self::ALLOWED_ORIGIN);

        $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));
    }
}
