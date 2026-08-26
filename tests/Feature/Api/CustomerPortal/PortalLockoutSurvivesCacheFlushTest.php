<?php

namespace Tests\Feature\Api\CustomerPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Feature\Api\CustomerPortal\Concerns\InteractsWithPortalAuth;
use Tests\TestCase;

/**
 * Lockout akun (`locked_until`) disimpan DI DB, bukan cuma cache — cache
 * bisa di-flush, dan kalau lockout cuma hidup di sana, flush membukanya
 * lagi (alasan sama seperti lockout PIN §6.5.4).
 */
class PortalLockoutSurvivesCacheFlushTest extends TestCase
{
    use InteractsWithPortalAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortalClientSecret();
    }

    public function test_cache_flush_setelah_5_gagal_tidak_membuka_kunci(): void
    {
        $seed = $this->seedActivePortalCustomer();

        for ($i = 0; $i < 5; $i++) {
            $response = $this->loginJson($seed['login_id'], 'password-salah-terus');
            $response->assertStatus(401);
        }

        $this->assertTrue($seed['account']->fresh()->isLocked());

        // Flush cache — kalau lockout cuma hidup di rate limiter (cache),
        // ini akan membukanya. Lockout DB tidak boleh ikut hilang.
        Cache::flush();

        $response = $this->loginJson($seed['login_id'], self::PORTAL_TEST_PASSWORD);

        $response->assertStatus(423);
    }
}
