<?php

namespace Tests\Feature\Api\CustomerPortal;

use App\Enums\InvoiceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Api\CustomerPortal\Concerns\InteractsWithPortalAuth;
use Tests\Feature\Api\CustomerPortal\Concerns\InteractsWithPortalBilling;
use Tests\TestCase;

/**
 * `GET /me/invoices` (docs/api/api-portal-pelanggan/business-logic.md §2).
 */
class PortalInvoiceIndexTest extends TestCase
{
    use InteractsWithPortalAuth, InteractsWithPortalBilling, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortalClientSecret();
    }

    private function getInvoices(string $accessToken, string $query = ''): TestResponse
    {
        return $this->withHeaders($this->authenticatedHeaders($accessToken))
            ->getJson('/api/customer-portal/me/invoices'.$query);
    }

    public function test_daftar_tagihan_hanya_milik_pelanggan_sendiri(): void
    {
        $seedA = $this->seedActivePortalCustomer();
        $seedB = $this->seedActivePortalCustomer();
        $this->seedInvoice($seedA['customer']);
        $this->seedInvoice($seedB['customer']);

        $tokensA = $this->loginAndGetTokens($seedA['login_id']);
        $response = $this->getInvoices($tokensA['access_token']);

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_filter_status_membatasi_hasil(): void
    {
        $seed = $this->seedActivePortalCustomer();
        // billing_period sengaja dibedakan juga — InvoiceObserver menolak
        // duplikat burst (customer+type+period+amount sama dalam 300 detik).
        $this->seedInvoice($seed['customer'], ['invoice_status' => InvoiceStatus::LUNAS->value, 'invoice_number' => 'INV-A', 'billing_period' => '2026-06']);
        $this->seedInvoice($seed['customer'], ['invoice_status' => InvoiceStatus::BELUM_DIBAYAR->value, 'invoice_number' => 'INV-B', 'billing_period' => '2026-07']);

        $tokens = $this->loginAndGetTokens($seed['login_id']);
        $response = $this->getInvoices($tokens['access_token'], '?status=lunas');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('INV-A', $response->json('data.0.invoice_number'));
    }

    public function test_exclude_status_mengecualikan_satu_status(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $this->seedInvoice($seed['customer'], ['invoice_status' => InvoiceStatus::LUNAS->value, 'invoice_number' => 'INV-C', 'billing_period' => '2026-06']);
        $this->seedInvoice($seed['customer'], ['invoice_status' => InvoiceStatus::BELUM_DIBAYAR->value, 'invoice_number' => 'INV-D', 'billing_period' => '2026-07']);

        $tokens = $this->loginAndGetTokens($seed['login_id']);
        $response = $this->getInvoices($tokens['access_token'], '?exclude_status=lunas');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('INV-D', $response->json('data.0.invoice_number'));
    }

    public function test_exclude_status_diabaikan_kalau_status_juga_dikirim(): void
    {
        $seed = $this->seedActivePortalCustomer();
        // status= menang — exclude_status jangan diam-diam nyaring hasil
        // eksplisit yang diminta caller.
        $this->seedInvoice($seed['customer'], ['invoice_status' => InvoiceStatus::LUNAS->value, 'invoice_number' => 'INV-E', 'billing_period' => '2026-06']);
        $this->seedInvoice($seed['customer'], ['invoice_status' => InvoiceStatus::BELUM_DIBAYAR->value, 'invoice_number' => 'INV-F', 'billing_period' => '2026-07']);

        $tokens = $this->loginAndGetTokens($seed['login_id']);
        $response = $this->getInvoices($tokens['access_token'], '?status=lunas&exclude_status=lunas');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('INV-E', $response->json('data.0.invoice_number'));
    }

    public function test_filter_period_membatasi_hasil(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $this->seedInvoice($seed['customer'], ['billing_period' => '2026-06', 'invoice_number' => 'INV-JUN']);
        $this->seedInvoice($seed['customer'], ['billing_period' => '2026-07', 'invoice_number' => 'INV-JUL']);

        $tokens = $this->loginAndGetTokens($seed['login_id']);
        $response = $this->getInvoices($tokens['access_token'], '?period=2026-07');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('INV-JUL', $response->json('data.0.invoice_number'));
    }

    public function test_kolom_haram_tidak_muncul(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $this->seedInvoice($seed['customer']);

        $tokens = $this->loginAndGetTokens($seed['login_id']);
        $response = $this->getInvoices($tokens['access_token']);

        $item = $response->json('data.0');
        foreach (['id', 'pop_id', 'customer_service_id', 'internet_package_id'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $item);
        }
    }

    public function test_nominal_berupa_string_desimal(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $this->seedInvoice($seed['customer'], ['total_amount' => 150000, 'remaining_amount' => 150000]);

        $tokens = $this->loginAndGetTokens($seed['login_id']);
        $response = $this->getInvoices($tokens['access_token']);

        $this->assertSame('150000.00', $response->json('data.0.total_amount'));
        $this->assertIsString($response->json('data.0.total_amount'));
    }
}
