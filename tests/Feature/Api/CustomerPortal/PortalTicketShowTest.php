<?php

namespace Tests\Feature\Api\CustomerPortal;

use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\CustomerPortal\Concerns\InteractsWithPortalAuth;
use Tests\TestCase;

/**
 * `GET /me/tickets/{ticket_number}` — anti-pola "bind dulu cek belakangan"
 * dihindari sama seperti Fase 3. Nomor milik pelanggan lain → 404.
 */
class PortalTicketShowTest extends TestCase
{
    use InteractsWithPortalAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortalClientSecret();
    }

    private function seedTicketFor(Customer $customer, array $overrides = []): Ticket
    {
        $staff = User::factory()->create();

        return Ticket::create(array_merge([
            'ticket_number' => 'TKT-SHOW-'.random_int(100000, 999999),
            'type' => 'MTN',
            'customer_id' => $customer->id,
            'pop_id' => $customer->pop_id,
            'detail_keluhan' => 'Internet mati total.',
            'priority' => 'High',
            'created_by' => $staff->id,
        ], $overrides));
    }

    public function test_detail_tiket_milik_sendiri(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $this->seedTicketFor($seed['customer'], ['ticket_number' => 'TKT-SHOW-1']);

        $tokens = $this->loginAndGetTokens($seed['login_id']);
        $response = $this->withHeaders($this->authenticatedHeaders($tokens['access_token']))
            ->getJson('/api/customer-portal/me/tickets/TKT-SHOW-1');

        $response->assertOk();
        $response->assertJsonPath('data.ticket_number', 'TKT-SHOW-1');
    }

    public function test_ticket_number_milik_pelanggan_lain_menghasilkan_404(): void
    {
        $seedA = $this->seedActivePortalCustomer();
        $seedB = $this->seedActivePortalCustomer();
        $this->seedTicketFor($seedB['customer'], ['ticket_number' => 'TKT-MILIK-B']);

        $tokensA = $this->loginAndGetTokens($seedA['login_id']);
        $response = $this->withHeaders($this->authenticatedHeaders($tokensA['access_token']))
            ->getJson('/api/customer-portal/me/tickets/TKT-MILIK-B');

        $response->assertStatus(404);
    }

    public function test_ticket_number_tidak_ada_menghasilkan_404(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $tokens = $this->loginAndGetTokens($seed['login_id']);

        $response = $this->withHeaders($this->authenticatedHeaders($tokens['access_token']))
            ->getJson('/api/customer-portal/me/tickets/TKT-TIDAK-ADA');

        $response->assertStatus(404);
    }
}
