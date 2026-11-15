<?php

namespace Tests\Feature\Api\CustomerPortal;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\CustomerPortalAccount;
use App\Models\Invoice;
use App\Services\CustomerQrTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Api\CustomerPortal\Concerns\InteractsWithPortalAuth;
use Tests\Feature\Api\CustomerPortal\Concerns\InteractsWithPortalBilling;
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
    use InteractsWithPortalAuth, InteractsWithPortalBilling, RefreshDatabase;

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
        $response->assertJsonPath('login_id', $customer->portal_login_id);
        $response->assertJsonPath('account_status', 'pending_claim');
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
        $this->assertSame(['login_id', 'account_status', 'billing'], array_keys($response->json()));
    }

    /**
     * Tamu scan QR → langsung lihat tagihan (2026-11-14). Cuma tagihan
     * belum lunas, nama tersamar, whitelist kolom sempit.
     */
    #[Test]
    public function tamu_dapat_ringkasan_tagihan_belum_lunas_tersamar(): void
    {
        $pop = $this->seedPop();
        $customer = Customer::factory()->create([
            'pop_id' => $pop->id,
            'full_name' => 'Masudah Yuni Fitri',
        ]);

        $this->makeInvoice($customer, '2026-09', InvoiceStatus::LUNAS, '0.00');
        $this->makeInvoice($customer, '2026-10', InvoiceStatus::SEBAGIAN, '50000.00');
        $this->makeInvoice($customer, '2026-11', InvoiceStatus::BELUM_DIBAYAR, '150000.00');
        $this->makeInvoice($customer, '2026-08', InvoiceStatus::BATAL, '150000.00');

        $response = $this->resolveJson($this->validCode($customer));

        $response->assertOk();
        $response->assertJsonPath('billing.customer_name', 'MASUDAH Y***** F*****');
        $response->assertJsonPath('billing.display_id', $customer->display_id);
        $response->assertJsonPath('billing.outstanding_total', '200000.00');
        $response->assertJsonCount(2, 'billing.invoices');
        $response->assertJsonPath('billing.invoices.0.billing_period', '2026-10');
        $response->assertJsonPath('billing.invoices.0.invoice_status.value', 'sebagian');
        $response->assertJsonPath('billing.invoices.1.billing_period', '2026-11');
    }

    #[Test]
    public function ringkasan_tagihan_tamu_tidak_membocorkan_data_pribadi(): void
    {
        $pop = $this->seedPop();
        $customer = Customer::factory()->create(['pop_id' => $pop->id]);
        $invoice = $this->makeInvoice($customer, '2026-11', InvoiceStatus::BELUM_DIBAYAR, '150000.00');

        $response = $this->resolveJson($this->validCode($customer));

        $response->assertOk();
        $this->assertSame(
            ['customer_name', 'display_id', 'outstanding_total', 'invoices'],
            array_keys($response->json('billing'))
        );
        $this->assertSame(
            ['billing_period', 'due_date', 'remaining_amount', 'invoice_status'],
            array_keys($response->json('billing.invoices.0'))
        );
        $this->assertStringNotContainsString($invoice->invoice_number, $response->getContent());
        if ($customer->primary_phone) {
            $this->assertStringNotContainsString($customer->primary_phone, $response->getContent());
        }
    }

    #[Test]
    public function pelanggan_tanpa_tunggakan_dapat_daftar_kosong(): void
    {
        $pop = $this->seedPop();
        $customer = Customer::factory()->create(['pop_id' => $pop->id]);
        $this->makeInvoice($customer, '2026-11', InvoiceStatus::LUNAS, '0.00');

        $response = $this->resolveJson($this->validCode($customer));

        $response->assertOk();
        $response->assertJsonPath('billing.outstanding_total', '0.00');
        $response->assertJsonCount(0, 'billing.invoices');
    }

    private function validCode(Customer $customer): string
    {
        $qrService = app(CustomerQrTokenService::class);
        $token = $qrService->issue($customer);
        $signature = $qrService->signature((int) $customer->pop_id, $customer->customer_code, $token->token);

        return "{$token->token}.{$signature}";
    }

    private function makeInvoice(Customer $customer, string $period, InvoiceStatus $status, string $remaining): Invoice
    {
        return $this->seedInvoice($customer, [
            'due_date' => $period.'-15',
            'billing_period' => $period,
            'total_amount' => '150000.00',
            'remaining_amount' => $remaining,
            'invoice_status' => $status->value,
        ]);
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
