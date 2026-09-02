<?php

namespace Tests\Feature\Api\CustomerPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\CustomerPortal\Concerns\InteractsWithPortalAuth;
use Tests\Feature\Api\CustomerPortal\Concerns\InteractsWithPortalBilling;
use Tests\TestCase;

/**
 * `GET /me/invoices/{invoice_number}` — anti-pola "bind dulu cek belakangan"
 * WAJIB dihindari (docblock ScopedToAuthenticatedCustomer). Nomor milik
 * pelanggan lain → 404, bukan 403, bukan 200.
 */
class PortalInvoiceShowTest extends TestCase
{
    use InteractsWithPortalAuth, InteractsWithPortalBilling, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortalClientSecret();
    }

    public function test_detail_tagihan_menyertakan_pembayaran_yang_menempel(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $invoice = $this->seedInvoice($seed['customer'], ['invoice_number' => 'INV-DETAIL-1']);
        $this->seedPayment($invoice, ['payment_number' => 'PAY-DETAIL-1']);

        $tokens = $this->loginAndGetTokens($seed['login_id']);
        $response = $this->withHeaders($this->authenticatedHeaders($tokens['access_token']))
            ->getJson('/api/customer-portal/me/invoices/INV-DETAIL-1');

        $response->assertOk();
        $response->assertJsonPath('data.invoice_number', 'INV-DETAIL-1');
        $response->assertJsonPath('data.payments.0.payment_number', 'PAY-DETAIL-1');
    }

    public function test_invoice_number_milik_pelanggan_lain_menghasilkan_404(): void
    {
        $seedA = $this->seedActivePortalCustomer();
        $seedB = $this->seedActivePortalCustomer();
        $this->seedInvoice($seedB['customer'], ['invoice_number' => 'INV-MILIK-B']);

        $tokensA = $this->loginAndGetTokens($seedA['login_id']);
        $response = $this->withHeaders($this->authenticatedHeaders($tokensA['access_token']))
            ->getJson('/api/customer-portal/me/invoices/INV-MILIK-B');

        $response->assertStatus(404);
    }

    public function test_invoice_number_tidak_ada_menghasilkan_404(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $tokens = $this->loginAndGetTokens($seed['login_id']);

        $response = $this->withHeaders($this->authenticatedHeaders($tokens['access_token']))
            ->getJson('/api/customer-portal/me/invoices/INV-TIDAK-ADA');

        $response->assertStatus(404);
    }

    public function test_paid_amount_dan_remaining_amount_dibaca_apa_adanya_bukan_dihitung_ulang(): void
    {
        $seed = $this->seedActivePortalCustomer();
        // Nilai paid_amount/remaining_amount SENGAJA gak konsisten sama
        // payment yang menempel — membuktikan Resource baca kolom apa
        // adanya, gak menghitung ulang dari payments().
        $invoice = $this->seedInvoice($seed['customer'], [
            'invoice_number' => 'INV-RAW-1',
            'total_amount' => 150000,
            'paid_amount' => 50000,
            'remaining_amount' => 100000,
            'invoice_status' => 'sebagian',
        ]);

        $tokens = $this->loginAndGetTokens($seed['login_id']);
        $response = $this->withHeaders($this->authenticatedHeaders($tokens['access_token']))
            ->getJson('/api/customer-portal/me/invoices/INV-RAW-1');

        $response->assertJsonPath('data.paid_amount', '50000.00');
        $response->assertJsonPath('data.remaining_amount', '100000.00');
    }
}
