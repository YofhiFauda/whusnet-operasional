<?php

namespace Tests\Feature\Api\CustomerPortal;

use App\Support\PortalClientIp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Api\CustomerPortal\Concerns\InteractsWithPortalAuth;
use Tests\TestCase;

/**
 * ADHOC-224 — limiter Portal untuk ±30.000 pelanggan.
 *
 * Portal Pelanggan adalah BFF: Laravel dipanggil dari SERVER Next.js, jadi
 * `$request->ip()` sama untuk semua pelanggan. Dulu limiter per-IP
 * (`customer-portal-auth-ip` 30/15 menit, `customer-portal-api` 120/menit tanpa
 * token) menghitung seluruh pelanggan sebagai satu klien → begitu satu jatah
 * habis, login SEMUA orang kena 429. Tes ini menjaga: IP pelanggan asli dipakai
 * sebagai kunci, header IP tidak bisa dipalsukan tanpa secret, dan penyerang
 * tidak bisa lolos dengan merotasi header Authorization.
 */
class PortalLimiterScalingTest extends TestCase
{
    use InteractsWithPortalAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortalClientSecret();
    }

    private function loginFromClientIp(string $clientIp, string $loginId): TestResponse
    {
        return $this->withHeaders($this->portalClientHeaders() + [PortalClientIp::HEADER => $clientIp])
            ->postJson('/api/customer-portal/auth/login', [
                'login_id' => $loginId,
                'password' => 'password-asal',
            ]);
    }

    #[Test]
    public function jatah_login_satu_pelanggan_tidak_menghabiskan_jatah_pelanggan_lain(): void
    {
        // Pelanggan A menyapu 150 login_id → jatah IP-nya habis.
        for ($i = 0; $i < 150; $i++) {
            $this->loginFromClientIp('203.0.113.10', "XXX-A{$i}")->assertStatus(401);
        }
        $this->loginFromClientIp('203.0.113.10', 'XXX-A150')->assertStatus(429);

        // Pelanggan B (IP asli lain, request dari server portal yang SAMA) tetap bisa login.
        $this->loginFromClientIp('198.51.100.20', 'XXX-B0')->assertStatus(401);
    }

    #[Test]
    public function header_ip_tanpa_secret_valid_ditolak_dan_tidak_bisa_memalsukan_kunci(): void
    {
        $response = $this->withHeaders([
            'X-Portal-Client' => 'secret-salah',
            PortalClientIp::HEADER => '203.0.113.99',
        ])->postJson('/api/customer-portal/auth/login', ['login_id' => 'XXX', 'password' => 'x']);

        $response->assertStatus(401);
    }

    #[Test]
    public function ping_tanpa_secret_tidak_bisa_lolos_limiter_dengan_memalsukan_header_ip(): void
    {
        // Setiap request mengaku IP berbeda, tapi tanpa secret yang valid header
        // itu diabaikan → tetap satu ember per IP sebenarnya (120/menit).
        for ($i = 0; $i < 120; $i++) {
            $this->withHeaders([PortalClientIp::HEADER => "203.0.113.{$i}"])->getJson('/api/customer-portal/ping');
        }

        $this->withHeaders([PortalClientIp::HEADER => '203.0.113.200'])
            ->getJson('/api/customer-portal/ping')
            ->assertStatus(429);
    }

    #[Test]
    public function bearer_acak_tanpa_secret_ditolak_401_dan_tidak_pernah_menghabiskan_limiter(): void
    {
        // Dulu throttle jalan SEBELUM portal_client dengan kunci Bearer mentah:
        // Bearer acak tiap request = ember baru = limiter tak pernah terpicu,
        // sementara tiap request tetap memakan worker. Sekarang ditolak murah.
        for ($i = 0; $i < 130; $i++) {
            $this->withHeaders(['Authorization' => 'Bearer acak-'.$i])
                ->getJson('/api/customer-portal/me')
                ->assertStatus(401);
        }
    }

    #[Test]
    public function resolve_memakai_header_hanya_bila_secret_valid_dan_ip_sah(): void
    {
        $withSecret = fn (array $extra) => Request::create('/x', 'GET', [], [], [], ['REMOTE_ADDR' => '10.0.0.5']);

        $valid = $withSecret([]);
        $valid->headers->add(['X-Portal-Client' => self::PORTAL_CLIENT_SECRET, PortalClientIp::HEADER => '203.0.113.7']);
        $this->assertSame('203.0.113.7', PortalClientIp::resolve($valid));

        $ipv6 = $withSecret([]);
        $ipv6->headers->add(['X-Portal-Client' => self::PORTAL_CLIENT_SECRET, PortalClientIp::HEADER => '2001:db8::1']);
        $this->assertSame('2001:db8::1', PortalClientIp::resolve($ipv6));

        $noSecret = $withSecret([]);
        $noSecret->headers->add([PortalClientIp::HEADER => '203.0.113.7']);
        $this->assertSame('10.0.0.5', PortalClientIp::resolve($noSecret));

        $wrongSecret = $withSecret([]);
        $wrongSecret->headers->add(['X-Portal-Client' => 'salah', PortalClientIp::HEADER => '203.0.113.7']);
        $this->assertSame('10.0.0.5', PortalClientIp::resolve($wrongSecret));

        foreach (['bukan-ip', '', '999.1.1.1', '203.0.113.7, 10.0.0.1'] as $garbage) {
            $bad = $withSecret([]);
            $bad->headers->add(['X-Portal-Client' => self::PORTAL_CLIENT_SECRET, PortalClientIp::HEADER => $garbage]);
            $this->assertSame('10.0.0.5', PortalClientIp::resolve($bad), "Header '{$garbage}' harus jatuh ke IP asli.");
        }
    }

    #[Test]
    public function batas_total_login_semua_pelanggan_300_per_menit(): void
    {
        // 300 login dari 300 IP pelanggan berbeda masih lolos (puncak sah
        // ≈ 5 login/detik), lalu yang ke-301 kena batas global.
        for ($i = 0; $i < 300; $i++) {
            $ip = '10.'.intdiv($i, 250).'.'.($i % 250).'.1';
            $this->loginFromClientIp($ip, "XXX-T{$i}")->assertStatus(401);
        }

        $this->loginFromClientIp('172.16.0.1', 'XXX-T300')->assertStatus(429);
    }
}
