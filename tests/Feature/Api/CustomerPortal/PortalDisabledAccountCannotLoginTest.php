<?php

namespace Tests\Feature\Api\CustomerPortal;

use App\Enums\WorkflowTransition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Api\CustomerPortal\Concerns\InteractsWithPortalAuth;
use Tests\TestCase;

/**
 * Pelanggan terminated: CustomerObserver mencabut token lama dan men-`disabled`
 * akun, tapi login() dulu tidak pernah membaca status akun — pelanggan putus
 * masih bisa login ulang pakai password lama dan dapat pasangan token baru.
 */
class PortalDisabledAccountCannotLoginTest extends TestCase
{
    use InteractsWithPortalAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortalClientSecret();
    }

    #[Test]
    public function akun_disabled_tidak_bisa_login_ulang_dengan_password_lama(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $seed['customer']->update(['status' => WorkflowTransition::TERMINATED->value]);
        $this->assertSame('disabled', $seed['account']->fresh()->status);

        $this->loginJson($seed['login_id'], self::PORTAL_TEST_PASSWORD)->assertStatus(401);
    }

    #[Test]
    public function akun_disabled_dijawab_identik_dengan_password_salah(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $seed['customer']->update(['status' => WorkflowTransition::TERMINATED->value]);

        $disabled = $this->loginJson($seed['login_id'], self::PORTAL_TEST_PASSWORD);
        $wrongPassword = $this->loginJson($seed['login_id'], 'password-yang-salah-sekali');

        $this->assertSame($wrongPassword->status(), $disabled->status());
        $this->assertSame($wrongPassword->json(), $disabled->json());
    }
}
