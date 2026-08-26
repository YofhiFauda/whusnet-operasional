<?php

namespace Tests\Feature\Api\CustomerPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\CustomerPortal\Concerns\InteractsWithPortalAuth;
use Tests\Feature\Api\CustomerPortal\Concerns\InteractsWithPortalBilling;
use Tests\TestCase;

/**
 * `GET /me/balance` — saldo lebih-bayar pelanggan (dikonfirmasi user
 * 2026-08-25: saldo + riwayat mutasi ringkas).
 */
class PortalBalanceTest extends TestCase
{
    use InteractsWithPortalAuth, InteractsWithPortalBilling, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortalClientSecret();
    }

    public function test_saldo_sesuai_customer_balance_service(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $this->seedBalanceMutation($seed['customer'], ['type' => 'credit', 'amount' => 80000]);
        $this->seedBalanceMutation($seed['customer'], ['type' => 'debit', 'amount' => 30000]);

        $tokens = $this->loginAndGetTokens($seed['login_id']);
        $response = $this->withHeaders($this->authenticatedHeaders($tokens['access_token']))
            ->getJson('/api/customer-portal/me/balance');

        $response->assertOk();
        $response->assertJsonPath('data.balance', '50000.00');
    }

    public function test_mutasi_tidak_memuat_pop_id_dan_created_by(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $this->seedBalanceMutation($seed['customer']);

        $tokens = $this->loginAndGetTokens($seed['login_id']);
        $response = $this->withHeaders($this->authenticatedHeaders($tokens['access_token']))
            ->getJson('/api/customer-portal/me/balance');

        $item = $response->json('data.mutations.0');
        $this->assertArrayNotHasKey('pop_id', $item);
        $this->assertArrayNotHasKey('created_by', $item);
        $this->assertArrayNotHasKey('id', $item);
        $this->assertArrayNotHasKey('payment_id', $item);
    }

    public function test_mutasi_note_tidak_memuat_nama_staf(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $this->seedBalanceMutation($seed['customer'], ['note' => 'Lebih bayar dari PAY-202608-0042']);

        $tokens = $this->loginAndGetTokens($seed['login_id']);
        $response = $this->withHeaders($this->authenticatedHeaders($tokens['access_token']))
            ->getJson('/api/customer-portal/me/balance');

        $this->assertSame('Lebih bayar dari PAY-202608-0042', $response->json('data.mutations.0.note'));
    }

    public function test_amount_mutasi_string_desimal_dan_selalu_positif(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $this->seedBalanceMutation($seed['customer'], ['type' => 'debit', 'amount' => 25000]);

        $tokens = $this->loginAndGetTokens($seed['login_id']);
        $response = $this->withHeaders($this->authenticatedHeaders($tokens['access_token']))
            ->getJson('/api/customer-portal/me/balance');

        $this->assertSame('25000.00', $response->json('data.mutations.0.amount'));
        $this->assertSame('debit', $response->json('data.mutations.0.type'));
        $this->assertSame('Keluar', $response->json('data.mutations.0.type_label'));
    }

    public function test_saldo_pelanggan_lain_tidak_bocor(): void
    {
        $seedA = $this->seedActivePortalCustomer();
        $seedB = $this->seedActivePortalCustomer();
        $this->seedBalanceMutation($seedA['customer'], ['type' => 'credit', 'amount' => 999999]);
        $this->seedBalanceMutation($seedB['customer'], ['type' => 'credit', 'amount' => 10000]);

        $tokensB = $this->loginAndGetTokens($seedB['login_id']);
        $response = $this->withHeaders($this->authenticatedHeaders($tokensB['access_token']))
            ->getJson('/api/customer-portal/me/balance');

        $response->assertJsonPath('data.balance', '10000.00');
        $this->assertCount(1, $response->json('data.mutations'));
    }
}
