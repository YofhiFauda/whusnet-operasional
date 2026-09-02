<?php

namespace Tests\Feature\QrCode;

use App\Models\Customer;
use App\Models\CustomerQrToken;
use App\Models\Pop;
use App\Services\CustomerQrTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * docs/plan/qr-code/rancangan-qr-pelanggan-final.md §6.5, §10 Fase 2 —
 * "Test wajib fase ini" level Service (`CustomerQrTokenService::issuePin/
 * verifyPin/changePin`).
 */
class CustomerQrPinServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['qr.secret' => 'test-qr-hmac-secret-fase-2']);
    }

    private function tokenFor(): CustomerQrToken
    {
        $pop = Pop::create([
            'code' => 'PIN-A', 'pop_code' => 'PNA', 'registration_prefix' => 'C', 'cid_prefix' => 'D',
            'name' => 'POP PIN Test', 'type' => 'cabang', 'status' => 'active',
        ]);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'customer_code' => 'RQ003001']);

        return app(CustomerQrTokenService::class)->issue($customer);
    }

    /**
     * Koreksi 2026-08-26 (perintah eksplisit user): `pin_hash` sekarang
     * REVERSIBLE (Crypt::encryptString), bukan bcrypt — `/qr/cetak` wajib
     * bisa nunjukin PIN kapan pun. Kolomnya tetap bukan plaintext MENTAH
     * di DB (terenkripsi AES-256 pakai APP_KEY), tapi beda dari hash: bisa
     * dibalikin, bukan buta permanen.
     */
    #[Test]
    public function issue_pin_menyimpan_terenkripsi_reversible_bukan_plaintext(): void
    {
        $token = $this->tokenFor();
        $service = app(CustomerQrTokenService::class);

        $plainPin = $service->issuePin($token);

        $this->assertMatchesRegularExpression('/^\d{6}$/', $plainPin);
        $token->refresh();
        $this->assertNotSame($plainPin, $token->pin_hash);
        $this->assertSame($plainPin, Crypt::decryptString($token->pin_hash));
        $this->assertSame($plainPin, $service->revealPin($token));
        $this->assertTrue($token->pin_must_change);
        $this->assertNotNull($token->pin_expires_at);
    }

    #[Test]
    public function reset_pin_tidak_mengubah_token_atau_signature(): void
    {
        $token = $this->tokenFor();
        $service = app(CustomerQrTokenService::class);
        $originalToken = $token->token;
        $originalSignature = $service->signature($token->signed_pop_id, $token->signed_customer_code, $token->token);

        $service->issuePin($token);
        $service->issuePin($token->fresh()); // "lupa PIN" → terbitkan ulang

        $token->refresh();
        $this->assertSame($originalToken, $token->token);
        $this->assertSame(
            $originalSignature,
            $service->signature($token->signed_pop_id, $token->signed_customer_code, $token->token)
        );
    }

    #[Test]
    public function verify_pin_benar_berhasil_dan_reset_percobaan(): void
    {
        $token = $this->tokenFor();
        $service = app(CustomerQrTokenService::class);
        $plainPin = $service->issuePin($token);

        $result = $service->verifyPin($token->fresh(), $plainPin);

        $this->assertSame('success', $result['outcome']);
        $this->assertSame(0, $token->fresh()->pin_failed_attempts);
    }

    #[Test]
    public function lima_gagal_mengunci_pin_dan_bertahan_walau_cache_di_flush(): void
    {
        $token = $this->tokenFor();
        $service = app(CustomerQrTokenService::class);
        $service->issuePin($token);

        for ($i = 0; $i < 5; $i++) {
            $service->verifyPin($token->fresh(), '000001'); // pasti salah, generateStrongPin tolak 000000/pola lemah
        }

        $token->refresh();
        $this->assertSame(5, $token->pin_failed_attempts);
        $this->assertNotNull($token->pin_locked_until);

        // Lockout ada di kolom DB, BUKAN cache — flush cache TIDAK BOLEH
        // membuka kunci (§6.5.4: "rate limiter berbasis cache saja tidak
        // cukup, cache flush menghapus hitungan, itu jalur bypass gampang").
        Cache::flush();

        $result = $service->verifyPin($token->fresh(), '000001');
        $this->assertSame('locked', $result['outcome']);
    }

    #[Test]
    public function pin_kedaluwarsa_ditolak_walau_pinnya_benar(): void
    {
        $token = $this->tokenFor();
        $service = app(CustomerQrTokenService::class);
        $plainPin = $service->issuePin($token);

        $token->update(['pin_expires_at' => now()->subDay()]);

        $result = $service->verifyPin($token->fresh(), $plainPin);

        $this->assertSame('expired', $result['outcome']);
    }

    #[Test]
    public function pelanggan_tanpa_pin_aktif_gagal_lewat_verify_pin_tapi_bisa_lewat_4_digit_hp(): void
    {
        $token = $this->tokenFor(); // belum pernah issuePin()
        $token->customer->update(['primary_phone' => '081234565678']);
        $service = app(CustomerQrTokenService::class);

        $this->assertFalse($token->hasActivePin());
        $this->assertSame('expired', $service->verifyPin($token, '123456')['outcome']);
        $this->assertTrue($service->verifyLegacyPhoneSuffix($token->fresh(), '5678'));
        $this->assertFalse($service->verifyLegacyPhoneSuffix($token->fresh(), '9999'));
    }

    #[Test]
    public function change_pin_menolak_pin_baru_sama_dengan_lama(): void
    {
        $token = $this->tokenFor();
        $service = app(CustomerQrTokenService::class);
        $plainPin = $service->issuePin($token);

        $this->expectException(RuntimeException::class);
        $service->changePin($token->fresh(), $plainPin);
    }

    #[Test]
    public function change_pin_menolak_pola_lemah(): void
    {
        $token = $this->tokenFor();
        $service = app(CustomerQrTokenService::class);
        $service->issuePin($token);

        $this->expectException(RuntimeException::class);
        $service->changePin($token->fresh(), '123456');
    }

    #[Test]
    public function change_pin_berhasil_matikan_wajib_ganti_dan_tidak_ikut_kedaluwarsa(): void
    {
        $token = $this->tokenFor();
        $service = app(CustomerQrTokenService::class);
        $service->issuePin($token);

        $service->changePin($token->fresh(), '481037');

        $fresh = $token->fresh();
        $this->assertFalse($fresh->pin_must_change);
        $this->assertNull($fresh->pin_expires_at);
        $this->assertNotNull($fresh->pin_first_used_at);
        $this->assertSame('481037', Crypt::decryptString($fresh->pin_hash));
    }
}
