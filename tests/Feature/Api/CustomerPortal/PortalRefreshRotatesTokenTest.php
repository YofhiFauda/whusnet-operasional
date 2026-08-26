<?php

namespace Tests\Feature\Api\CustomerPortal;

use App\Models\CustomerPortalToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\CustomerPortal\Concerns\InteractsWithPortalAuth;
use Tests\TestCase;

/**
 * `/auth/refresh` sukses menerbitkan pasangan baru, menandai refresh lama
 * terpakai (`revoked_at`), dan me-rantai lewat `parent_id`.
 */
class PortalRefreshRotatesTokenTest extends TestCase
{
    use InteractsWithPortalAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortalClientSecret();
    }

    public function test_refresh_sukses_menerbitkan_pasangan_baru_menandai_lama_terpakai(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $tokens = $this->loginAndGetTokens($seed['login_id']);

        $oldRefreshHash = hash('sha256', $tokens['refresh_token']);
        $oldRefreshModel = CustomerPortalToken::where('token_hash', $oldRefreshHash)->firstOrFail();

        $response = $this->withHeaders($this->portalClientHeaders())
            ->postJson('/api/customer-portal/auth/refresh', ['refresh_token' => $tokens['refresh_token']]);

        $response->assertOk();
        $newAccess = $response->json('access_token');
        $newRefresh = $response->json('refresh_token');

        $this->assertNotSame($tokens['access_token'], $newAccess);
        $this->assertNotSame($tokens['refresh_token'], $newRefresh);

        $this->assertNotNull($oldRefreshModel->fresh()->revoked_at);

        $newRefreshModel = CustomerPortalToken::where('token_hash', hash('sha256', $newRefresh))->firstOrFail();
        $this->assertSame($oldRefreshModel->id, $newRefreshModel->parent_id);

        // Access token baru langsung bisa dipakai.
        $me = $this->withHeaders($this->authenticatedHeaders($newAccess))->getJson('/api/customer-portal/me');
        $me->assertOk();
    }
}
