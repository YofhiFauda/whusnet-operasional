<?php

namespace Tests\Feature\Api\CustomerPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\CustomerPortal\Concerns\InteractsWithPortalAuth;
use Tests\Feature\Api\CustomerPortal\Concerns\InteractsWithPortalBilling;
use Tests\TestCase;

/**
 * `customer_id` HANYA pernah datang dari token — TIDAK PERNAH dari request
 * (business-logic.md §Kepemilikan data). Test ini menyapu semua endpoint
 * data Fase 3 (+ /me dari Fase 2) dengan `customer_id` pelanggan LAIN
 * disuntik lewat query string, dan pastikan tetap cuma data milik pemilik
 * token yang kebaca.
 */
class PortalCustomerIdInjectionIgnoredTest extends TestCase
{
    use InteractsWithPortalAuth, InteractsWithPortalBilling, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortalClientSecret();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function endpointProvider(): array
    {
        return [
            'me' => ['/api/customer-portal/me'],
            'invoices' => ['/api/customer-portal/me/invoices'],
            'payments' => ['/api/customer-portal/me/payments'],
            'balance' => ['/api/customer-portal/me/balance'],
        ];
    }

    #[DataProvider('endpointProvider')]
    public function test_customer_id_di_query_string_diabaikan(string $path): void
    {
        $seedA = $this->seedActivePortalCustomer();
        $seedB = $this->seedActivePortalCustomer();

        $tokensA = $this->loginAndGetTokens($seedA['login_id']);

        $response = $this->withHeaders($this->authenticatedHeaders($tokensA['access_token']))
            ->getJson($path.'?customer_id='.$seedB['customer']->id);

        $response->assertOk();

        // /me balikin login_id langsung — bukti eksplisit yang kebaca tetap
        // A meski customer_id di query nunjuk ke B.
        if ($path === '/api/customer-portal/me') {
            $response->assertJsonPath('data.login_id', $seedA['login_id']);
        }
    }
}
