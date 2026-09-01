<?php

namespace Tests\Feature\Api\CustomerPortal;

use App\Models\Customer;
use App\Models\CustomerPortalAccount;
use App\Services\CustomerQrTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Api\CustomerPortal\Concerns\InteractsWithPortalAuth;
use Tests\TestCase;

/**
 * `GET /api/customer-portal/qr/resolve` (2026-08-27, keputusan eksplisit
 * user: scan QR pelanggan SELALU ke Portal, gerbang tagihan internal
 * `QrBillingController` dicabut). Dipanggil Portal (app terpisah) buat
 * pre-fill `login_id` di halaman klaim — SENGAJA nol PIN di payload,
 * PIN cuma diverifikasi di `/auth/claim`.
 */
class PortalQrResolveTest extends TestCase
{
    use InteractsWithPortalAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortalClientSecret();
        config(['qr.secret' => 'test-qr-hmac-secret-resolve']);
    }

    private function resolveJson(string $code)
    {
        return $this->withHeaders($this->portalClientHeaders())
            ->getJson('/api/customer-portal/qr/resolve?code='.$code);
    }

    #[Test]
    public function kode_valid_akun_pending_claim_balikin_login_id_dan_status(): void
    {
        $pop = $this->seedPop();
        $customer = Customer::factory()->create(['pop_id' => $pop->id]);
        CustomerPortalAccount::create([
            'customer_id' => $customer->id,
            'login_id' => $customer->portal_login_id,
            'password_hash' => Hash::make('placeholder'),
            'status' => 'pending_claim',
        ]);

        $qrService = app(CustomerQrTokenService::class);
        $token = $qrService->issue($customer);
        $signature = $qrService->signature((int) $pop->id, $customer->customer_code, $token->token);

        $response = $this->resolveJson("{$token->token}.{$signature}");

        $response->assertOk();
        $response->assertExactJson([
            'login_id' => $customer->portal_login_id,
            'account_status' => 'pending_claim',
        ]);
    }

    #[Test]
    public function kode_valid_akun_sudah_active_tetap_balikin_login_id(): void
    {
        $seed = $this->seedActivePortalCustomer();

        $qrService = app(CustomerQrTokenService::class);
        $token = $qrService->issue($seed['customer']);
        $signature = $qrService->signature(
            (int) $seed['customer']->pop_id,
            $seed['customer']->customer_code,
            $token->token
        );

        $response = $this->resolveJson("{$token->token}.{$signature}");

        $response->assertOk();
        $response->assertJsonPath('account_status', 'active');
        // Portal-nya sendiri yang menentukan arahkan ke /login kalau
        // account_status=active — resolve() ini cuma ngasih fakta.
    }

    #[Test]
    public function belum_pernah_ada_akun_portal_tetap_balikin_login_id_status_null(): void
    {
        $pop = $this->seedPop();
        $customer = Customer::factory()->create(['pop_id' => $pop->id]);
        // Sengaja TANPA CustomerPortalAccount::create() — mensimulasikan
        // ensureAccountExists() belum sempat jalan.

        $qrService = app(CustomerQrTokenService::class);
        $token = $qrService->issue($customer);
        $signature = $qrService->signature((int) $pop->id, $customer->customer_code, $token->token);

        $response = $this->resolveJson("{$token->token}.{$signature}");

        $response->assertOk();
        $response->assertJsonPath('login_id', $customer->portal_login_id);
        $response->assertJsonPath('account_status', null);
    }

    #[Test]
    public function pin_tidak_pernah_muncul_di_payload(): void
    {
        $pop = $this->seedPop();
        $customer = Customer::factory()->create(['pop_id' => $pop->id]);

        $qrService = app(CustomerQrTokenService::class);
        $token = $qrService->issue($customer);
        $qrService->issuePin($token);
        $signature = $qrService->signature((int) $pop->id, $customer->customer_code, $token->token);

        $response = $this->resolveJson("{$token->token}.{$signature}");

        $response->assertOk();
        $this->assertArrayNotHasKey('pin', $response->json());
        $this->assertSame(['login_id', 'account_status'], array_keys($response->json()));
    }

    #[Test]
    public function signature_salah_404_generik(): void
    {
        $pop = $this->seedPop();
        $customer = Customer::factory()->create(['pop_id' => $pop->id]);
        $qrService = app(CustomerQrTokenService::class);
        $token = $qrService->issue($customer);

        $response = $this->resolveJson("{$token->token}.ZZZZZZZZZZ");

        $response->assertNotFound();
        $response->assertJsonMissing(['login_id']);
    }

    #[Test]
    public function token_tidak_ketemu_404_sama_persis_dengan_signature_salah(): void
    {
        $pop = $this->seedPop();
        $customer = Customer::factory()->create(['pop_id' => $pop->id]);
        $qrService = app(CustomerQrTokenService::class);
        $token = $qrService->issue($customer);

        $tidakKetemu = $this->resolveJson('ZZZZZZZZZZZZZZZZZZZZZZZZZZ.ZZZZZZZZZZ');
        $sigSalah = $this->resolveJson("{$token->token}.ZZZZZZZZZZ");

        $tidakKetemu->assertNotFound();
        $sigSalah->assertNotFound();
        $this->assertSame($tidakKetemu->json('message'), $sigSalah->json('message'));
    }

    #[Test]
    public function token_dicabut_404(): void
    {
        $pop = $this->seedPop();
        $customer = Customer::factory()->create(['pop_id' => $pop->id]);
        $qrService = app(CustomerQrTokenService::class);
        $token = $qrService->issue($customer);
        $signature = $qrService->signature((int) $pop->id, $customer->customer_code, $token->token);
        $qrService->revoke($token, 'test');

        $response = $this->resolveJson("{$token->token}.{$signature}");

        $response->assertNotFound();
    }

    #[Test]
    public function tanpa_client_secret_401(): void
    {
        $pop = $this->seedPop();
        $customer = Customer::factory()->create(['pop_id' => $pop->id]);
        $qrService = app(CustomerQrTokenService::class);
        $token = $qrService->issue($customer);
        $signature = $qrService->signature((int) $pop->id, $customer->customer_code, $token->token);

        $response = $this->getJson("/api/customer-portal/qr/resolve?code={$token->token}.{$signature}");

        $response->assertUnauthorized();
    }
}
