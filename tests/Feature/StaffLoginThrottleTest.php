<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ADHOC-224 — POST /login (staf) dulu tanpa limiter: tebak password tak
 * terbatas, dan tiap percobaan memakan ±250 ms CPU bcrypt, jadi satu klien
 * ±20 req/detik cukup menjenuhkan lima worker php-fpm.
 */
class StaffLoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    private function attempt(string $email, string $password = 'salah'): TestResponse
    {
        return $this->post('/login', ['email' => $email, 'password' => $password]);
    }

    #[Test]
    public function percobaan_ke_11_pada_email_yang_sama_diblokir_dengan_pesan_bahasa_indonesia(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->attempt('target@whusnet.net')->assertSessionHasErrors('email');
        }

        $response = $this->attempt('target@whusnet.net');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $response->assertHeader('Retry-After');
        $response->assertSessionHasErrors('email');
        $response->assertHeader('Retry-After');
    }

    #[Test]
    public function email_lain_dari_ip_yang_sama_tidak_ikut_terblokir_oleh_batas_per_email(): void
    {
        for ($i = 0; $i < 11; $i++) {
            $this->attempt('target@whusnet.net');
        }

        $response = $this->attempt('lain@whusnet.net');

        $response->assertHeaderMissing('Retry-After');
        $response->assertSessionHasErrors('email');
    }

    #[Test]
    public function penyapuan_banyak_email_dari_satu_ip_diblokir_pada_percobaan_ke_61(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->attempt("sapu{$i}@whusnet.net");
        }

        $response = $this->attempt('sapu60@whusnet.net');

        $response->assertHeader('Retry-After');
        $response->assertSessionHasErrors('email');
    }

    #[Test]
    public function login_benar_tetap_berhasil_di_bawah_batas(): void
    {
        $user = User::factory()->create([
            'email' => 'owner@whusnet.net',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        for ($i = 0; $i < 3; $i++) {
            $this->attempt('owner@whusnet.net');
        }

        $this->attempt('owner@whusnet.net', 'password')->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }
}
