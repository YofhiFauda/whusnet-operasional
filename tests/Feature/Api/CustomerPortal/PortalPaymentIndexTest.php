<?php

namespace Tests\Feature\Api\CustomerPortal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Api\CustomerPortal\Concerns\InteractsWithPortalAuth;
use Tests\Feature\Api\CustomerPortal\Concerns\InteractsWithPortalBilling;
use Tests\TestCase;

/**
 * `GET /me/payments` (docs/api/api-portal-pelanggan/business-logic.md §2).
 */
class PortalPaymentIndexTest extends TestCase
{
    use InteractsWithPortalAuth, InteractsWithPortalBilling, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortalClientSecret();
    }

    private function getPayments(string $accessToken, string $query = ''): TestResponse
    {
        return $this->withHeaders($this->authenticatedHeaders($accessToken))
            ->getJson('/api/customer-portal/me/payments'.$query);
    }

    public function test_riwayat_pembayaran_hanya_milik_pelanggan_sendiri(): void
    {
        $seedA = $this->seedActivePortalCustomer();
        $seedB = $this->seedActivePortalCustomer();
        $invoiceA = $this->seedInvoice($seedA['customer'], ['invoice_number' => 'INV-PA']);
        $invoiceB = $this->seedInvoice($seedB['customer'], ['invoice_number' => 'INV-PB']);
        $this->seedPayment($invoiceA, ['payment_number' => 'PAY-A']);
        $this->seedPayment($invoiceB, ['payment_number' => 'PAY-B']);

        $tokensA = $this->loginAndGetTokens($seedA['login_id']);
        $response = $this->getPayments($tokensA['access_token']);

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('PAY-A', $response->json('data.0.payment_number'));
    }

    public function test_overpay_amount_dan_billing_period_ikut_keluar(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $invoice = $this->seedInvoice($seed['customer'], ['billing_period' => '2026-06']);
        $this->seedPayment($invoice, ['amount' => 170000, 'overpay_amount' => 20000, 'billing_period' => '2026-06']);

        $tokens = $this->loginAndGetTokens($seed['login_id']);
        $response = $this->getPayments($tokens['access_token']);

        $response->assertJsonPath('data.0.overpay_amount', '20000.00');
        $response->assertJsonPath('data.0.billing_period', '2026-06');
    }

    public function test_pembayaran_ditolak_tetap_tampil_tanpa_reject_reason(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $invoice = $this->seedInvoice($seed['customer']);
        $this->seedPayment($invoice, [
            'payment_status' => 'ditolak',
            'reject_reason' => 'Setoran kolektor belum masuk.',
        ]);

        $tokens = $this->loginAndGetTokens($seed['login_id']);
        $response = $this->getPayments($tokens['access_token']);

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertArrayNotHasKey('reject_reason', $response->json('data.0'));
        $this->assertSame('belum terverifikasi — hubungi admin', $response->json('data.0.payment_status.label'));
        $this->assertFalse($response->json('data.0.has_receipt'));
    }

    public function test_bank_name_dan_account_number_tidak_muncul(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $invoice = $this->seedInvoice($seed['customer']);
        $this->seedPayment($invoice, [
            'payment_method' => 'transfer',
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
        ]);

        $tokens = $this->loginAndGetTokens($seed['login_id']);
        $response = $this->getPayments($tokens['access_token']);

        $item = $response->json('data.0');
        $this->assertArrayNotHasKey('bank_name', $item);
        $this->assertArrayNotHasKey('account_number', $item);
    }

    public function test_kolom_haram_lain_tidak_muncul(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $invoice = $this->seedInvoice($seed['customer']);
        $this->seedPayment($invoice, ['note' => 'catatan internal rahasia']);

        $tokens = $this->loginAndGetTokens($seed['login_id']);
        $response = $this->getPayments($tokens['access_token']);

        $item = $response->json('data.0');
        foreach (['id', 'received_by', 'collected_by', 'note', 'proof_file', 'idempotency_key', 'invoice_id', 'collected_date'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $item);
        }
    }

    public function test_filter_status_dan_period(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $invoiceJun = $this->seedInvoice($seed['customer'], ['billing_period' => '2026-06', 'invoice_number' => 'INV-F-JUN']);
        $invoiceJul = $this->seedInvoice($seed['customer'], ['billing_period' => '2026-07', 'invoice_number' => 'INV-F-JUL']);
        $this->seedPayment($invoiceJun, ['payment_number' => 'PAY-F-JUN', 'billing_period' => '2026-06', 'payment_status' => 'valid']);
        $this->seedPayment($invoiceJul, ['payment_number' => 'PAY-F-JUL', 'billing_period' => '2026-07', 'payment_status' => 'ditolak']);

        $responseStatus = $this->getPayments($this->loginAndGetTokens($seed['login_id'])['access_token'], '?status=ditolak');
        $this->assertCount(1, $responseStatus->json('data'));
        $this->assertSame('PAY-F-JUL', $responseStatus->json('data.0.payment_number'));
    }

    public function test_pembayaran_yang_dicatat_kolektor_langsung_terlihat_tanpa_job_apa_pun(): void
    {
        // Kolektor mencatat lewat CollectorPaymentController pun ujungnya
        // Payment::create biasa — tidak ada job/queue yang perlu ditunggu.
        // Test ini cukup buktikan Payment::create langsung kelihatan.
        $seed = $this->seedActivePortalCustomer();
        $invoice = $this->seedInvoice($seed['customer']);
        $this->seedPayment($invoice, ['payment_number' => 'PAY-KOLEKTOR']);

        $tokens = $this->loginAndGetTokens($seed['login_id']);
        $response = $this->getPayments($tokens['access_token']);

        $this->assertSame('PAY-KOLEKTOR', $response->json('data.0.payment_number'));
    }
}
