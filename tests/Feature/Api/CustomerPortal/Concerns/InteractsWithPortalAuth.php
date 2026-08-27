<?php

namespace Tests\Feature\Api\CustomerPortal\Concerns;

use App\Models\Customer;
use App\Models\CustomerPortalAccount;
use App\Models\Pop;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;

/**
 * Boilerplate bareng buat test auth portal pelanggan (docs/api/api-portal-
 * pelanggan/, Fase 2) — client secret, seed pelanggan+akun portal, helper
 * panggil /auth/login lewat HTTP asli (bukan bikin token manual) supaya
 * test benar-benar membuktikan jalur endpoint, bukan cuma model.
 */
trait InteractsWithPortalAuth
{
    protected const PORTAL_CLIENT_SECRET = 'test-portal-client-secret';

    protected const PORTAL_TEST_PASSWORD = 'Kuda-Nil-Rajin-88';

    protected function setUpPortalClientSecret(): void
    {
        config(['webhooks.portal_client_secret' => self::PORTAL_CLIENT_SECRET]);
    }

    protected function seedPop(string $cidPrefix = 'PNG'): Pop
    {
        return Pop::factory()->create(['cid_prefix' => $cidPrefix]);
    }

    /**
     * @return array{customer: Customer, account: CustomerPortalAccount, login_id: string}
     */
    protected function seedActivePortalCustomer(?Pop $pop = null, array $customerOverrides = []): array
    {
        $pop ??= $this->seedPop();

        $customer = Customer::factory()->create(array_merge(['pop_id' => $pop->id], $customerOverrides));

        $loginId = $customer->portal_login_id;

        $account = CustomerPortalAccount::create([
            'customer_id' => $customer->id,
            'login_id' => $loginId,
            'password_hash' => Hash::make(self::PORTAL_TEST_PASSWORD),
            'status' => 'active',
            'claimed_at' => now(),
        ]);

        return ['customer' => $customer, 'account' => $account, 'login_id' => $loginId];
    }

    protected function portalClientHeaders(): array
    {
        return ['X-Portal-Client' => self::PORTAL_CLIENT_SECRET];
    }

    protected function loginJson(string $loginId, string $password): TestResponse
    {
        return $this->withHeaders($this->portalClientHeaders())
            ->postJson('/api/customer-portal/auth/login', [
                'login_id' => $loginId,
                'password' => $password,
            ]);
    }

    /**
     * Login sukses lewat HTTP asli, balikin pasangan token mentah.
     *
     * @return array{access_token: string, refresh_token: string}
     */
    protected function loginAndGetTokens(string $loginId, string $password = self::PORTAL_TEST_PASSWORD): array
    {
        $response = $this->loginJson($loginId, $password);
        $response->assertOk();

        return [
            'access_token' => $response->json('access_token'),
            'refresh_token' => $response->json('refresh_token'),
        ];
    }

    protected function authenticatedHeaders(string $accessToken): array
    {
        return array_merge($this->portalClientHeaders(), [
            'Authorization' => 'Bearer '.$accessToken,
        ]);
    }
}
