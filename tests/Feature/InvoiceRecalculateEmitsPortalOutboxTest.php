<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPortalAccount;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use App\Models\WebhookOutbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `SendInvoiceUpdatedWebhook` (docs/api/api-portal-pelanggan/, Fase 3
 * §3 Bagian B) — dipicu SATU-SATUNYA dari `Invoice::recalculateFromPayments()`
 * lewat event `InvoiceStatusUpdated` yang SUDAH ada, bukan `PaymentObserver`.
 */
class InvoiceRecalculateEmitsPortalOutboxTest extends TestCase
{
    use RefreshDatabase;

    private function seedInvoiceWithPortalAccount(float $totalAmount = 150000): Invoice
    {
        $pop = Pop::create([
            'code' => 'IEO', 'pop_code' => 'IEO',
            'registration_prefix' => 'PNG', 'cid_prefix' => 'D',
            'name' => 'POP IEO', 'type' => 'cabang', 'status' => 'active',
        ]);

        $customer = Customer::create([
            'customer_code' => 'RQ'.random_int(100000, 999999),
            'full_name' => 'Pelanggan Outbox Test',
            'primary_phone' => '081200000001',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'address' => 'Jl. Outbox Test',
        ]);

        CustomerPortalAccount::create([
            'customer_id' => $customer->id,
            'login_id' => "PNG-{$customer->customer_code}",
            'password_hash' => bcrypt('placeholder'),
            'status' => 'active',
        ]);

        $package = InternetPackage::create([
            'package_code' => 'PKT-'.random_int(1000, 9999),
            'name' => 'Paket Outbox',
            'category' => 'rumahan',
            'package_group' => 'reguler',
            'bandwidth_label' => '20 Mbps',
            'monthly_price' => $totalAmount,
        ]);

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'monthly_price' => $totalAmount,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => $totalAmount,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        return Invoice::create([
            'invoice_number' => 'INV-OUTBOX-'.random_int(100000, 999999),
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $package->id,
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'subtotal' => $totalAmount,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => $totalAmount,
            'paid_amount' => 0,
            'remaining_amount' => $totalAmount,
            'invoice_status' => 'belum_dibayar',
        ]);
    }

    public function test_bayar_penuh_menerbitkan_invoice_updated_dengan_status_lunas(): void
    {
        $invoice = $this->seedInvoiceWithPortalAccount(150000);
        Payment::create([
            'payment_number' => 'PAY-OUTBOX-1',
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $invoice->pop_id,
            'payment_date' => '2026-06-10',
            'payment_method' => 'cash',
            'amount' => 150000,
            'payment_status' => 'valid',
        ]);

        $invoice->recalculateFromPayments();

        $row = WebhookOutbox::where('destination', 'customer_portal')->where('event', 'invoice.updated')->first();
        $this->assertNotNull($row);
        $this->assertSame('lunas', $row->payload['invoice']['invoice_status']);
        $this->assertSame('150000.00', $row->payload['invoice']['paid_amount']);
        $this->assertSame('0.00', $row->payload['invoice']['remaining_amount']);
    }

    public function test_bayar_sebagian_menerbitkan_invoice_updated_dengan_status_sebagian(): void
    {
        $invoice = $this->seedInvoiceWithPortalAccount(150000);
        Payment::create([
            'payment_number' => 'PAY-OUTBOX-2',
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $invoice->pop_id,
            'payment_date' => '2026-06-10',
            'payment_method' => 'cash',
            'amount' => 50000,
            'payment_status' => 'valid',
        ]);

        $invoice->recalculateFromPayments();

        $row = WebhookOutbox::where('destination', 'customer_portal')->first();
        $this->assertSame('sebagian', $row->payload['invoice']['invoice_status']);
        $this->assertSame('50000.00', $row->payload['invoice']['paid_amount']);
        $this->assertSame('100000.00', $row->payload['invoice']['remaining_amount']);
    }

    public function test_tolak_pembayaran_menerbitkan_invoice_updated(): void
    {
        $invoice = $this->seedInvoiceWithPortalAccount(150000);
        $payment = Payment::create([
            'payment_number' => 'PAY-OUTBOX-3',
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $invoice->pop_id,
            'payment_date' => '2026-06-10',
            'payment_method' => 'cash',
            'amount' => 150000,
            'payment_status' => 'valid',
        ]);
        $invoice->recalculateFromPayments();
        WebhookOutbox::query()->delete(); // reset — jalur bayar sudah diverifikasi test lain

        $payment->update(['payment_status' => 'ditolak']);
        $invoice->recalculateFromPayments();

        $row = WebhookOutbox::where('destination', 'customer_portal')->first();
        $this->assertNotNull($row);
        $this->assertSame('belum_dibayar', $row->payload['invoice']['invoice_status']);
    }

    public function test_invoice_batal_tidak_menerbitkan_event(): void
    {
        $invoice = $this->seedInvoiceWithPortalAccount(150000);
        $invoice->update(['invoice_status' => 'batal']);

        $invoice->recalculateFromPayments(); // early-return di dalam method

        $this->assertSame(0, WebhookOutbox::where('destination', 'customer_portal')->count());
    }

    public function test_payload_tidak_memuat_pii(): void
    {
        $invoice = $this->seedInvoiceWithPortalAccount(150000);
        Payment::create([
            'payment_number' => 'PAY-OUTBOX-4',
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $invoice->pop_id,
            'payment_date' => '2026-06-10',
            'payment_method' => 'cash',
            'amount' => 150000,
            'payment_status' => 'valid',
        ]);
        $invoice->recalculateFromPayments();

        $row = WebhookOutbox::where('destination', 'customer_portal')->first();
        $encoded = json_encode($row->payload);

        $this->assertStringNotContainsString('Pelanggan Outbox Test', $encoded);
        $this->assertStringNotContainsString('081200000001', $encoded);
        $this->assertStringNotContainsString('Jl. Outbox Test', $encoded);
        $this->assertArrayHasKey('login_id', $row->payload['customer']);
        $this->assertCount(1, $row->payload['customer']);
    }

    public function test_payload_state_penuh_bukan_delta(): void
    {
        $invoice = $this->seedInvoiceWithPortalAccount(150000);
        Payment::create([
            'payment_number' => 'PAY-OUTBOX-5',
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $invoice->pop_id,
            'payment_date' => '2026-06-10',
            'payment_method' => 'cash',
            'amount' => 150000,
            'payment_status' => 'valid',
        ]);
        $invoice->recalculateFromPayments();

        $row = WebhookOutbox::where('destination', 'customer_portal')->first();
        foreach (['invoice_number', 'invoice_status', 'total_amount', 'paid_amount', 'remaining_amount'] as $field) {
            $this->assertArrayHasKey($field, $row->payload['invoice']);
        }
    }

    public function test_rollback_transaksi_menghasilkan_nol_baris_outbox(): void
    {
        $invoice = $this->seedInvoiceWithPortalAccount(150000);

        try {
            DB::transaction(function () use ($invoice) {
                Payment::create([
                    'payment_number' => 'PAY-OUTBOX-6',
                    'invoice_id' => $invoice->id,
                    'customer_id' => $invoice->customer_id,
                    'pop_id' => $invoice->pop_id,
                    'payment_date' => '2026-06-10',
                    'payment_method' => 'cash',
                    'amount' => 150000,
                    'payment_status' => 'valid',
                ]);
                $invoice->recalculateFromPayments();

                throw new \RuntimeException('paksa rollback');
            });
        } catch (\RuntimeException) {
            // sengaja ditelan — yang diuji cuma efek sampingnya
        }

        $this->assertSame(0, WebhookOutbox::where('destination', 'customer_portal')->count());
    }

    public function test_pelanggan_tanpa_akun_portal_tidak_menerbitkan_event(): void
    {
        // Pola sama seedInvoiceWithPortalAccount TAPI tanpa
        // CustomerPortalAccount::create() — pelanggan belum diprovision.
        $pop = Pop::create([
            'code' => 'NPA', 'pop_code' => 'NPA',
            'registration_prefix' => 'NPA', 'cid_prefix' => 'D',
            'name' => 'POP NPA', 'type' => 'cabang', 'status' => 'active',
        ]);
        $customer = Customer::create([
            'customer_code' => 'RQ'.random_int(100000, 999999),
            'full_name' => 'Belum Punya Akun Portal',
            'primary_phone' => '081200000002',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'address' => 'Jl. Belum Punya Akun',
        ]);
        $package = InternetPackage::create([
            'package_code' => 'PKT-'.random_int(1000, 9999), 'name' => 'Paket NPA',
            'category' => 'rumahan', 'package_group' => 'reguler', 'bandwidth_label' => '20 Mbps',
            'monthly_price' => 150000,
        ]);
        $service = CustomerService::create([
            'customer_id' => $customer->id, 'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name, 'monthly_price' => 150000,
            'discount' => 0, 'ppn' => 0, 'total_monthly_bill' => 150000,
            'activation_date' => '2026-06-01', 'due_date' => '2026-06-15',
            'service_status' => 'aktif', 'billing_status' => 'active',
        ]);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-NPA-1', 'invoice_type' => 'bulanan',
            'customer_id' => $customer->id, 'pop_id' => $pop->id,
            'customer_service_id' => $service->id, 'internet_package_id' => $package->id,
            'billing_period' => '2026-06', 'issue_date' => '2026-06-01', 'due_date' => '2026-06-15',
            'subtotal' => 150000, 'discount' => 0, 'ppn' => 0, 'total_amount' => 150000,
            'paid_amount' => 0, 'remaining_amount' => 150000, 'invoice_status' => 'belum_dibayar',
        ]);

        Payment::create([
            'payment_number' => 'PAY-NPA-1', 'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id, 'pop_id' => $invoice->pop_id,
            'payment_date' => '2026-06-10', 'payment_method' => 'cash',
            'amount' => 150000, 'payment_status' => 'valid',
        ]);
        $invoice->recalculateFromPayments();

        $this->assertSame(0, WebhookOutbox::where('destination', 'customer_portal')->count());
    }
}
