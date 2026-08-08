<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use Database\Seeders\InternetPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `billing:reconcile-invoice-status` — jaring pengaman desync
 * paid_amount/remaining_amount/invoice_status terhadap payment valid yang
 * sebenarnya tercatat (docs/plan/analisa-billing-tagihan-pembayaran-
 * kolektor.md §A-7 #2, §D-7a). Fixture sengaja pakai `Invoice::withoutEvents()`
 * + update langsung untuk menanam desync — lewat jalur normal,
 * `recalculateFromPayments()` tidak akan pernah menghasilkan state ini.
 */
class ReconcileInvoiceStatusCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(InternetPackageSeeder::class);
    }

    private function createDesyncedInvoice(float $storedPaid, float $actualPaymentAmount, float $totalAmount = 150000): Invoice
    {
        $package = InternetPackage::query()->firstOrFail();
        $pop = Pop::create([
            'code' => 'POP-RECON',
            'pop_code' => 'RECON',
            'registration_prefix' => 'CR',
            'cid_prefix' => 'DR',
            'name' => 'POP Reconcile Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $customer = Customer::create([
            'customer_code' => 'C-RECON-'.random_int(1000, 9999),
            'full_name' => 'Pelanggan Reconcile Test',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $package->id,
            'address' => 'Jl. Reconcile Test',
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Reconcile Test',
            'village' => 'Desa Test',
            'district' => 'Kecamatan Test',
            'city' => 'Kota Test',
            'province' => 'Jawa Timur',
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

        $invoice = Invoice::create([
            'invoice_number' => 'INV-RECON-'.random_int(1000, 9999),
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

        // Payment valid yang SEBENARNYA tercatat.
        Payment::withoutEvents(function () use ($invoice, $actualPaymentAmount) {
            Payment::create([
                'payment_number' => 'PAY-RECON-'.random_int(1000, 9999),
                'invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'pop_id' => $invoice->pop_id,
                'payment_date' => '2026-06-13',
                'payment_method' => 'cash',
                'amount' => $actualPaymentAmount,
                'payment_status' => 'valid',
            ]);
        });

        // Tanam desync: paid_amount di kolom invoice sengaja BEDA dari
        // payment yang sebenarnya tercatat (mis. hasil koreksi manual data).
        Invoice::withoutEvents(function () use ($invoice, $storedPaid) {
            $invoice->forceFill([
                'paid_amount' => $storedPaid,
                'remaining_amount' => max(0, $invoice->total_amount - $storedPaid),
                'invoice_status' => $storedPaid > 0 ? 'sebagian' : 'belum_dibayar',
            ])->save();
        });

        return $invoice->fresh();
    }

    public function test_dry_run_reports_desync_without_changing_data(): void
    {
        $invoice = $this->createDesyncedInvoice(storedPaid: 0, actualPaymentAmount: 50000);

        $this->artisan('billing:reconcile-invoice-status')
            ->expectsOutputToContain('Total temuan   : 1')
            ->expectsOutputToContain('jalankan ulang dengan --fix')
            ->assertExitCode(0);

        // Dry-run tidak mengubah apa pun, meski selisihnya kecil.
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'paid_amount' => 0,
        ]);
    }

    public function test_fix_corrects_desync_below_threshold(): void
    {
        $invoice = $this->createDesyncedInvoice(storedPaid: 0, actualPaymentAmount: 50000);

        $this->artisan('billing:reconcile-invoice-status --fix')
            ->assertExitCode(0);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'paid_amount' => 50000,
            'remaining_amount' => 100000,
            'invoice_status' => 'sebagian',
        ]);
    }

    public function test_fix_skips_desync_above_threshold_and_flags_for_manual_review(): void
    {
        // Selisih 200rb, threshold default 100rb — harus TIDAK diperbaiki.
        $invoice = $this->createDesyncedInvoice(storedPaid: 0, actualPaymentAmount: 150000);

        $this->artisan('billing:reconcile-invoice-status --fix')
            ->expectsOutputToContain('PERLU REVIEW MANUAL')
            ->assertExitCode(0);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'paid_amount' => 0,
        ]);
    }

    public function test_consistent_invoice_produces_no_findings(): void
    {
        $package = InternetPackage::query()->firstOrFail();
        $pop = Pop::create([
            'code' => 'POP-RECON-OK',
            'pop_code' => 'RECOK',
            'registration_prefix' => 'CO',
            'cid_prefix' => 'DO',
            'name' => 'POP Reconcile OK',
            'type' => 'cabang',
            'status' => 'active',
        ]);
        $customer = Customer::create([
            'customer_code' => 'C-RECON-OK',
            'full_name' => 'Pelanggan Konsisten',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $package->id,
            'address' => 'Jl. Konsisten',
        ]);
        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Konsisten',
            'village' => 'Desa Test',
            'district' => 'Kecamatan Test',
            'city' => 'Kota Test',
            'province' => 'Jawa Timur',
        ]);
        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'monthly_price' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => 150000,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);
        Invoice::create([
            'invoice_number' => 'INV-RECON-OK',
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $package->id,
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'subtotal' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'invoice_status' => 'belum_dibayar',
        ]);

        $this->artisan('billing:reconcile-invoice-status')
            ->expectsOutputToContain('Tidak ada temuan')
            ->assertExitCode(0);
    }
}
