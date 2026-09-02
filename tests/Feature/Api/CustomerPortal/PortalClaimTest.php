<?php

namespace Tests\Feature\Api\CustomerPortal;

use App\Models\Customer;
use App\Models\CustomerPortalAccount;
use App\Services\CustomerQrTokenService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Feature\Api\CustomerPortal\Concerns\InteractsWithPortalAuth;
use Tests\TestCase;

/**
 * `/auth/claim` (docs/plan/qr-code/rancangan-qr-pelanggan-final.md §6.6.5) —
 * diaktifkan 2026-08-26 setelah modul QR/PIN (Fase 2) jalan. Menggantikan
 * `PortalClaimStubReturns501Test` yang membuktikan endpoint ini SENGAJA
 * mati sebelumnya.
 */
class PortalClaimTest extends TestCase
{
    use InteractsWithPortalAuth, RefreshDatabase;

    private const NEW_PASSWORD = 'Gajah-Ungu-Terbang-77';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortalClientSecret();
        config(['qr.secret' => 'test-qr-hmac-secret-claim']);
    }

    /**
     * @return array{customer: Customer, account: CustomerPortalAccount, login_id: string, plain_pin: string}
     */
    private function seedPendingClaimCustomerWithPin(): array
    {
        $pop = $this->seedPop();
        $customer = Customer::factory()->create(['pop_id' => $pop->id]);
        $loginId = $customer->portal_login_id;

        $account = CustomerPortalAccount::create([
            'customer_id' => $customer->id,
            'login_id' => $loginId,
            'password_hash' => Hash::make('placeholder-tidak-pernah-dipakai'),
            'status' => 'pending_claim',
        ]);

        $qrService = app(CustomerQrTokenService::class);
        $token = $qrService->issue($customer);
        $plainPin = $qrService->issuePin($token);

        return ['customer' => $customer, 'account' => $account, 'login_id' => $loginId, 'plain_pin' => $plainPin];
    }

    private function claimJson(string $loginId, string $pin, string $newPassword = self::NEW_PASSWORD)
    {
        return $this->withHeaders($this->portalClientHeaders())
            ->postJson('/api/customer-portal/auth/claim', [
                'login_id' => $loginId,
                'pin' => $pin,
                'new_password' => $newPassword,
            ]);
    }

    public function test_klaim_berhasil_dengan_login_id_dan_pin_yang_benar(): void
    {
        $seed = $this->seedPendingClaimCustomerWithPin();

        $response = $this->claimJson($seed['login_id'], $seed['plain_pin']);

        $response->assertOk();
        $response->assertJsonStructure(['access_token', 'refresh_token', 'token_type', 'expires_in']);

        $account = $seed['account']->fresh();
        $this->assertSame('active', $account->status);
        $this->assertNotNull($account->claimed_at);
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $account->password_hash));
    }

    public function test_klaim_sekali_lagi_setelah_aktif_409(): void
    {
        $seed = $this->seedPendingClaimCustomerWithPin();
        $this->claimJson($seed['login_id'], $seed['plain_pin'])->assertOk();

        $response = $this->claimJson($seed['login_id'], $seed['plain_pin']);

        $response->assertStatus(409);
    }

    public function test_pin_salah_401_pesan_sama_dengan_login_id_tidak_ada(): void
    {
        $seed = $this->seedPendingClaimCustomerWithPin();

        $pinSalah = $this->claimJson($seed['login_id'], '000001');
        $loginIdTidakAda = $this->claimJson('XXX-TIDAKADA999', '000001');

        $pinSalah->assertStatus(401);
        $loginIdTidakAda->assertStatus(401);
        $this->assertSame($pinSalah->json('message'), $loginIdTidakAda->json('message'));

        // Akun TETAP pending_claim — PIN salah tidak boleh diam-diam
        // mengaktifkan atau mengubah apa pun.
        $this->assertSame('pending_claim', $seed['account']->fresh()->status);
    }

    public function test_pelanggan_belum_punya_token_qr_401(): void
    {
        $pop = $this->seedPop();
        $customer = Customer::factory()->create(['pop_id' => $pop->id]);
        $loginId = $customer->portal_login_id;

        CustomerPortalAccount::create([
            'customer_id' => $customer->id,
            'login_id' => $loginId,
            'password_hash' => Hash::make('placeholder'),
            'status' => 'pending_claim',
        ]);

        $response = $this->claimJson($loginId, '123456');

        $response->assertStatus(401);
    }

    public function test_password_baru_lemah_ditolak_validasi(): void
    {
        $seed = $this->seedPendingClaimCustomerWithPin();

        $response = $this->claimJson($seed['login_id'], $seed['plain_pin'], 'pendek');

        $response->assertStatus(422);
        $this->assertSame('pending_claim', $seed['account']->fresh()->status);
    }

    /**
     * §6.5.4 lockout PIN (5 gagal/15 menit) ikut berlaku di jalur klaim —
     * "tidak ada jalur bypass kedua" (§6.6.5).
     */
    public function test_pin_terkunci_setelah_5_gagal_menolak_klaim_423(): void
    {
        // Longgarkan limiter kredensial portal (5/15menit) SUPAYA yang
        // kena tes di sini beneran lockout PIN per-token (§6.5.4), bukan
        // numpang kena limiter API yang kebetulan angkanya sama.
        RateLimiter::for('customer-portal-auth', fn () => Limit::perMinute(1000));
        RateLimiter::for('customer-portal-auth-ip', fn () => Limit::perMinute(1000));

        $seed = $this->seedPendingClaimCustomerWithPin();

        for ($i = 0; $i < 5; $i++) {
            $this->claimJson($seed['login_id'], '000001');
        }

        $response = $this->claimJson($seed['login_id'], $seed['plain_pin']);

        $response->assertStatus(423);
        $this->assertSame('pending_claim', $seed['account']->fresh()->status);
    }

    /**
     * Regresi nyata (2026-08-26) — ketemu lewat verifikasi HTTP MANUAL
     * terhadap dev DB sungguhan, BUKAN test suite ini: `login_id` yang
     * TERSIMPAN di `customer_portal_accounts` bisa basi (formula lama,
     * `registration_prefix-customer_code`) kalau baris dibuat sebelum
     * revisi formula ke `cid_prefix`, padahal kartu yang staf cetak
     * SEKARANG selalu pakai `Customer::portal_login_id` (accessor,
     * formula terkini). Data nyata: 99/100 akun dev ternyata basi begini,
     * `claim()` gagal total kalau dicoba pakai login_id yang BENERAN
     * tercetak di kartu. `seedPendingClaimCustomerWithPin()` di file ini
     * SELALU pakai accessor terkini buat login_id-nya — makanya skenario
     * ini gak pernah ke-exercise test lain manapun di file ini walau
     * semuanya hijau. Fix produksinya: `customers:backfill-portal-login-id
     * --resync` (lihat CustomersBackfillPortalLoginIdCommandTest).
     */
    public function test_login_id_basi_formula_lama_gagal_klaim_walau_pin_benar(): void
    {
        $pop = $this->seedPop(); // registration_prefix default beda dari cid_prefix
        $customer = Customer::factory()->create(['pop_id' => $pop->id]);
        $staleLoginId = "{$pop->registration_prefix}-{$customer->customer_code}"; // formula LAMA

        $this->assertNotSame($customer->portal_login_id, $staleLoginId, 'fixture harus benar-benar beda dari formula terkini');

        CustomerPortalAccount::create([
            'customer_id' => $customer->id,
            'login_id' => $staleLoginId,
            'password_hash' => Hash::make('placeholder'),
            'status' => 'pending_claim',
        ]);

        $qrService = app(CustomerQrTokenService::class);
        $token = $qrService->issue($customer);
        $plainPin = $qrService->issuePin($token);

        // Login ID yang BENERAN tercetak di kartu sekarang (formula
        // terkini) — inilah yang pelanggan/staf pegang secara fisik.
        $withPrintedLoginId = $this->claimJson($customer->portal_login_id, $plainPin);
        $withPrintedLoginId->assertStatus(401);

        // login_id LAMA (basi, gak pernah dicetak lagi) justru masih "jalan"
        // — persis bug-nya: kredensial yang beneran dipegang orang gagal,
        // yang gak pernah ada gagal-nya duluan.
        $withStaleLoginId = $this->claimJson($staleLoginId, $plainPin);
        $withStaleLoginId->assertOk();
    }
}
