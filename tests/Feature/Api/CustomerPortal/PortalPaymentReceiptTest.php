<?php

namespace Tests\Feature\Api\CustomerPortal;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\CustomerPortal\Concerns\InteractsWithPortalAuth;
use Tests\Feature\Api\CustomerPortal\Concerns\InteractsWithPortalBilling;
use Tests\TestCase;

/**
 * `GET /me/payments/{payment_number}/receipt`
 * (docs/api/api-portal-pelanggan/business-logic.md §3 Bagian A).
 */
class PortalPaymentReceiptTest extends TestCase
{
    use InteractsWithPortalAuth, InteractsWithPortalBilling, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortalClientSecret();
    }

    public function test_kwitansi_tidak_memuat_penerima_penagih_catatan_dicetak(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $invoice = $this->seedInvoice($seed['customer']);
        $staf = User::factory()->create();
        $this->seedPayment($invoice, [
            'payment_number' => 'PAY-RECEIPT-1',
            'received_by' => $staf->id,
            'collected_by' => $staf->id,
            'note' => 'catatan kerja internal',
        ]);

        $tokens = $this->loginAndGetTokens($seed['login_id']);
        $response = $this->withHeaders($this->authenticatedHeaders($tokens['access_token']))
            ->getJson('/api/customer-portal/me/payments/PAY-RECEIPT-1/receipt');

        $response->assertOk();
        $data = $response->json('data');
        foreach (['penerima', 'penagih', 'catatan', 'dicetak'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $data);
        }
    }

    public function test_kwitansi_menyertakan_status_valid_dan_keterangan_cicilan(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $invoice = $this->seedInvoice($seed['customer']);
        $this->seedPayment($invoice, ['payment_number' => 'PAY-RECEIPT-2']);

        $tokens = $this->loginAndGetTokens($seed['login_id']);
        $response = $this->withHeaders($this->authenticatedHeaders($tokens['access_token']))
            ->getJson('/api/customer-portal/me/payments/PAY-RECEIPT-2/receipt');

        $response->assertJsonPath('data.status_valid', true);
        $response->assertJsonStructure(['data' => ['keterangan_cicilan']]);
    }

    public function test_kwitansi_menyertakan_dibayar_raw_dan_tanggal_bayar_iso(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $invoice = $this->seedInvoice($seed['customer']);
        $this->seedPayment($invoice, ['payment_number' => 'PAY-RECEIPT-3', 'amount' => 150000, 'payment_date' => '2026-06-10']);

        $tokens = $this->loginAndGetTokens($seed['login_id']);
        $response = $this->withHeaders($this->authenticatedHeaders($tokens['access_token']))
            ->getJson('/api/customer-portal/me/payments/PAY-RECEIPT-3/receipt');

        $response->assertJsonPath('data.dibayar_raw', '150000.00');
        $this->assertStringStartsWith('2026-06-10', $response->json('data.tanggal_bayar_iso'));
    }

    public function test_kwitansi_pembayaran_ditolak_tetap_bisa_diakses_status_valid_false(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $invoice = $this->seedInvoice($seed['customer']);
        $this->seedPayment($invoice, ['payment_number' => 'PAY-RECEIPT-4', 'payment_status' => 'ditolak']);

        $tokens = $this->loginAndGetTokens($seed['login_id']);
        $response = $this->withHeaders($this->authenticatedHeaders($tokens['access_token']))
            ->getJson('/api/customer-portal/me/payments/PAY-RECEIPT-4/receipt');

        $response->assertOk();
        $response->assertJsonPath('data.status_valid', false);
    }

    public function test_payment_number_milik_pelanggan_lain_menghasilkan_404(): void
    {
        $seedA = $this->seedActivePortalCustomer();
        $seedB = $this->seedActivePortalCustomer();
        $invoiceB = $this->seedInvoice($seedB['customer']);
        $this->seedPayment($invoiceB, ['payment_number' => 'PAY-MILIK-B']);

        $tokensA = $this->loginAndGetTokens($seedA['login_id']);
        $response = $this->withHeaders($this->authenticatedHeaders($tokensA['access_token']))
            ->getJson('/api/customer-portal/me/payments/PAY-MILIK-B/receipt');

        $response->assertStatus(404);
    }

    public function test_binding_tidak_lewat_route_model_binding_by_id(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $invoice = $this->seedInvoice($seed['customer']);
        $payment = $this->seedPayment($invoice, ['payment_number' => 'PAY-REGRESSION-1']);

        $tokens = $this->loginAndGetTokens($seed['login_id']);
        // Kirim ID numerik payment di posisi payment_number — kalau binding
        // salah pakai id, ini akan ketemu payment tsb. Harus 404.
        $response = $this->withHeaders($this->authenticatedHeaders($tokens['access_token']))
            ->getJson('/api/customer-portal/me/payments/'.$payment->id.'/receipt');

        $response->assertStatus(404);
    }

    // ================= GET /receipt.pdf =================

    public function test_kwitansi_pdf_bisa_dilihat_inline(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $invoice = $this->seedInvoice($seed['customer']);
        $staf = User::factory()->create();
        $this->seedPayment($invoice, [
            'payment_number' => 'PAY-PDF-1',
            'received_by' => $staf->id,
            'note' => 'catatan kerja internal',
        ]);

        $tokens = $this->loginAndGetTokens($seed['login_id']);
        $response = $this->withHeaders($this->authenticatedHeaders($tokens['access_token']))
            ->get('/api/customer-portal/me/payments/PAY-PDF-1/receipt.pdf');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('inline', $response->headers->get('content-disposition'));
    }

    public function test_kwitansi_pdf_dengan_download_true_jadi_attachment(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $invoice = $this->seedInvoice($seed['customer']);
        $this->seedPayment($invoice, ['payment_number' => 'PAY-PDF-2']);

        $tokens = $this->loginAndGetTokens($seed['login_id']);
        $response = $this->withHeaders($this->authenticatedHeaders($tokens['access_token']))
            ->get('/api/customer-portal/me/payments/PAY-PDF-2/receipt.pdf?download=1');

        $response->assertOk();
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('kwitansi-PAY-PDF-2.pdf', $response->headers->get('content-disposition'));
    }

    public function test_kwitansi_pdf_milik_pelanggan_lain_menghasilkan_404(): void
    {
        $seedA = $this->seedActivePortalCustomer();
        $seedB = $this->seedActivePortalCustomer();
        $invoiceB = $this->seedInvoice($seedB['customer']);
        $this->seedPayment($invoiceB, ['payment_number' => 'PAY-PDF-MILIK-B']);

        $tokensA = $this->loginAndGetTokens($seedA['login_id']);
        $response = $this->withHeaders($this->authenticatedHeaders($tokensA['access_token']))
            ->get('/api/customer-portal/me/payments/PAY-PDF-MILIK-B/receipt.pdf');

        $response->assertStatus(404);
    }

    // ================= GET /receipt-view (modal "Lihat") =================

    public function test_kwitansi_html_untuk_modal_tidak_memuat_toolbar_atau_data_pegawai(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $invoice = $this->seedInvoice($seed['customer']);
        $staf = User::factory()->create();
        $this->seedPayment($invoice, [
            'payment_number' => 'PAY-VIEW-1',
            'received_by' => $staf->id,
            'note' => 'catatan kerja internal',
        ]);

        $tokens = $this->loginAndGetTokens($seed['login_id']);
        $response = $this->withHeaders($this->authenticatedHeaders($tokens['access_token']))
            ->get('/api/customer-portal/me/payments/PAY-VIEW-1/receipt-view');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/html; charset=UTF-8');
        $response->assertSee('PAY-VIEW-1', false);
        $response->assertDontSee('Cetak Kwitansi', false);
        $response->assertDontSee('Diterima oleh', false);
        $response->assertDontSee('catatan kerja internal', false);
    }

    public function test_kwitansi_html_milik_pelanggan_lain_menghasilkan_404(): void
    {
        $seedA = $this->seedActivePortalCustomer();
        $seedB = $this->seedActivePortalCustomer();
        $invoiceB = $this->seedInvoice($seedB['customer']);
        $this->seedPayment($invoiceB, ['payment_number' => 'PAY-VIEW-MILIK-B']);

        $tokensA = $this->loginAndGetTokens($seedA['login_id']);
        $response = $this->withHeaders($this->authenticatedHeaders($tokensA['access_token']))
            ->get('/api/customer-portal/me/payments/PAY-VIEW-MILIK-B/receipt-view');

        $response->assertStatus(404);
    }
}
