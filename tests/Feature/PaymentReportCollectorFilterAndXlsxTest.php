<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * E2.7 (filter/kolom Kolektor di laporan) + E2.8 (export XLSX).
 * docs/plan/analisa-billing-tagihan-pembayaran-kolektor.md §D-2, §D-9 no. 3.
 */
class PaymentReportCollectorFilterAndXlsxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function createInvoice(Pop $pop, string $code): Invoice
    {
        $package = InternetPackage::query()->firstOrFail();

        $customer = Customer::create([
            'customer_code' => $code,
            'full_name' => 'Pelanggan '.$code,
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $package->id,
            'address' => 'Jl. '.$code,
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. '.$code,
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

        return Invoice::create([
            'invoice_number' => 'INV-'.$code,
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
            'paid_amount' => 150000,
            'remaining_amount' => 0,
            'invoice_status' => 'lunas',
        ]);
    }

    public function test_filter_by_collector_id_shows_only_that_collectors_payments(): void
    {
        $owner = User::where('email', 'owner@whusnet.net')->firstOrFail();
        $pop = Pop::create([
            'code' => 'POP-RPT1',
            'pop_code' => 'RPT1',
            'registration_prefix' => 'CR',
            'cid_prefix' => 'DR',
            'name' => 'POP Report Filter',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $kolektorRole = Role::where('code', 'kolektor')->firstOrFail();
        $kolektorA = User::factory()->create(['role_id' => $kolektorRole->id, 'status' => 'active']);
        $kolektorB = User::factory()->create(['role_id' => $kolektorRole->id, 'status' => 'active']);

        $invoiceA = $this->createInvoice($pop, 'RPT-A');
        $invoiceB = $this->createInvoice($pop, 'RPT-B');
        $invoiceDirect = $this->createInvoice($pop, 'RPT-D');

        Payment::create([
            'payment_number' => 'PAY-RPT-A', 'invoice_id' => $invoiceA->id, 'customer_id' => $invoiceA->customer_id,
            'pop_id' => $pop->id, 'payment_date' => '2026-06-13', 'payment_method' => 'cash', 'amount' => 150000,
            'received_by' => $owner->id, 'collected_by' => $kolektorA->id, 'payment_status' => 'valid',
        ]);
        Payment::create([
            'payment_number' => 'PAY-RPT-B', 'invoice_id' => $invoiceB->id, 'customer_id' => $invoiceB->customer_id,
            'pop_id' => $pop->id, 'payment_date' => '2026-06-13', 'payment_method' => 'cash', 'amount' => 150000,
            'received_by' => $owner->id, 'collected_by' => $kolektorB->id, 'payment_status' => 'valid',
        ]);
        Payment::create([
            'payment_number' => 'PAY-RPT-D', 'invoice_id' => $invoiceDirect->id, 'customer_id' => $invoiceDirect->customer_id,
            'pop_id' => $pop->id, 'payment_date' => '2026-06-13', 'payment_method' => 'transfer', 'amount' => 150000,
            'received_by' => $owner->id, 'collected_by' => null, 'payment_status' => 'valid',
        ]);

        // Tanpa filter: semua tampil (termasuk non-kolektor), tidak disembunyikan.
        $responseAll = $this->actingAs($owner)->get(route('reports.payments.index'));
        $responseAll->assertSee('PAY-RPT-A');
        $responseAll->assertSee('PAY-RPT-B');
        $responseAll->assertSee('PAY-RPT-D');

        // Filter kolektor A saja.
        $responseA = $this->actingAs($owner)->get(route('reports.payments.index', ['collector_id' => $kolektorA->id]));
        $responseA->assertSee('PAY-RPT-A');
        $responseA->assertDontSee('PAY-RPT-B');
        $responseA->assertDontSee('PAY-RPT-D');
    }

    public function test_xlsx_export_returns_downloadable_file(): void
    {
        $owner = User::where('email', 'owner@whusnet.net')->firstOrFail();
        $pop = Pop::create([
            'code' => 'POP-RPT2',
            'pop_code' => 'RPT2',
            'registration_prefix' => 'CX',
            'cid_prefix' => 'DX',
            'name' => 'POP Report XLSX',
            'type' => 'cabang',
            'status' => 'active',
        ]);
        $invoice = $this->createInvoice($pop, 'RPT-X1');
        Payment::create([
            'payment_number' => 'PAY-RPT-X1', 'invoice_id' => $invoice->id, 'customer_id' => $invoice->customer_id,
            'pop_id' => $pop->id, 'payment_date' => '2026-06-13', 'payment_method' => 'cash', 'amount' => 150000,
            'received_by' => $owner->id, 'payment_status' => 'valid',
        ]);

        $response = $this->actingAs($owner)->get(route('reports.payments.export-xlsx'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_user_without_permission_cannot_export_xlsx(): void
    {
        $teknisi = User::factory()->create([
            'role_id' => Role::where('name', 'Teknisi')->firstOrFail()->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($teknisi)->get(route('reports.payments.export-xlsx'));
        $response->assertForbidden();
    }
}
