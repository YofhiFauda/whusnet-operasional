<?php

namespace Tests\Feature\Api\CustomerPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\CustomerPortal\Concerns\InteractsWithPortalAuth;
use Tests\TestCase;

/**
 * Login ke `login_id` yang sama sekali gak punya akun portal HARUS dijawab
 * SAMA PERSIS dengan password salah — pesan beda ("akun belum diaktifkan")
 * membocorkan bahwa login_id itu valid, dan seluruh guna throttle hilang
 * (flowchart.md §1).
 */
class PortalUnclaimedAccountLooksIdenticalTest extends TestCase
{
    use InteractsWithPortalAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortalClientSecret();
    }

    public function test_login_tanpa_akun_portal_401_pesan_sama_dengan_password_salah(): void
    {
        $seed = $this->seedActivePortalCustomer();

        $tanpaAkun = $this->loginJson('XXX-TIDAKADA999', 'password-apa-saja');
        $passwordSalah = $this->loginJson($seed['login_id'], 'password-salah-total');

        $tanpaAkun->assertStatus(401);
        $passwordSalah->assertStatus(401);
        $this->assertSame($tanpaAkun->json('message'), $passwordSalah->json('message'));
    }
}
