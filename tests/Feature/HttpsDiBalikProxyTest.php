<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Aplikasi selalu dilayani lewat proxy (nginx, dan di produksi ditambah
 * Cloudflare Tunnel). Kalau header X-Forwarded-* tidak dipercaya, seluruh
 * tautan yang dibuat Laravel jadi http:// di halaman https — mixed content,
 * dan aksi POST/redirect gagal tanpa pesan.
 */
class HttpsDiBalikProxyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/__probe-skema', fn () => response()->json([
            'secure' => request()->isSecure(),
            'scheme' => request()->getScheme(),
            'url' => url('/dashboard'),
            'root' => request()->getSchemeAndHttpHost(),
        ]))->middleware('web');
    }

    #[Test]
    public function request_dengan_x_forwarded_proto_https_dianggap_aman(): void
    {
        $this->get('/__probe-skema', ['X-Forwarded-Proto' => 'https'])
            ->assertOk()
            ->assertJson([
                'secure' => true,
                'scheme' => 'https',
                'url' => 'https://localhost/dashboard',
            ]);
    }

    #[Test]
    public function host_asli_dari_tunnel_dipakai_untuk_membuat_url(): void
    {
        $this->get('/__probe-skema', [
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'contoh.trycloudflare.com',
            'X-Forwarded-Port' => '443',
        ])
            ->assertOk()
            ->assertJson([
                'root' => 'https://contoh.trycloudflare.com',
                'url' => 'https://contoh.trycloudflare.com/dashboard',
            ]);
    }

    #[Test]
    public function tanpa_header_proxy_skema_tetap_http(): void
    {
        $this->get('/__probe-skema')
            ->assertOk()
            ->assertJson([
                'secure' => false,
                'scheme' => 'http',
                'url' => 'http://localhost/dashboard',
            ]);
    }
}
