<?php

namespace Tests\Feature\Api\CustomerPortal;

use App\Enums\WorkflowTransition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\CustomerPortal\Concerns\InteractsWithPortalAuth;
use Tests\TestCase;

/**
 * Pelanggan `terminated` → akun portal `disabled` + semua token dicabut,
 * lewat CustomerObserver (business-logic.md §Token, database-schema.md).
 */
class PortalCustomerObserverDisablesPortalOnTerminatedTest extends TestCase
{
    use InteractsWithPortalAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortalClientSecret();
    }

    public function test_pelanggan_terminated_menonaktifkan_akun_portal_dan_mencabut_token(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $session = $this->loginAndGetTokens($seed['login_id']);

        $this->withHeaders($this->authenticatedHeaders($session['access_token']))
            ->getJson('/api/customer-portal/me')
            ->assertOk();

        $seed['customer']->update(['status' => WorkflowTransition::TERMINATED->value]);

        $this->assertSame('disabled', $seed['account']->fresh()->status);

        $this->withHeaders($this->authenticatedHeaders($session['access_token']))
            ->getJson('/api/customer-portal/me')
            ->assertStatus(401);
    }

    public function test_perubahan_status_selain_terminated_tidak_menyentuh_akun_portal(): void
    {
        $seed = $this->seedActivePortalCustomer();

        $seed['customer']->update(['status' => WorkflowTransition::SUSPENDED->value]);

        $this->assertSame('active', $seed['account']->fresh()->status);
    }
}
