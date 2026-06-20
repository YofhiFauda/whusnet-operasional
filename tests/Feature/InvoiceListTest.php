<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceListTest extends TestCase
{
    use RefreshDatabase;

    protected InternetPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();
    }

    public function test_invoice_list_can_be_filtered_by_pop_period_status_and_customer(): void
    {
        $owner = User::where('email', 'owner@whusnet.net')->firstOrFail();
        $popA = $this->createPop('POP-A', 'PONA', 'POP A');
        $popB = $this->createPop('POP-B', 'PONB', 'POP B');

        $invoiceA = $this->createInvoice($popA, 'Ahmad Invoice Filter', 'INV-202606-9001', '2026-06', 'belum_dibayar');
        $invoiceB = $this->createInvoice($popB, 'Budi Invoice Filter', 'INV-202607-9002', '2026-07', 'lunas');

        $response = $this->actingAs($owner)->get(route('invoices.index', [
            'pop_id' => $popA->id,
            'billing_period' => '2026-06',
            'status' => 'belum_dibayar',
            'search' => 'Ahmad',
        ]));

        $response->assertOk();
        $response->assertSee($invoiceA->invoice_number);
        $response->assertSee('Ahmad Invoice Filter');
        $response->assertDontSee($invoiceB->invoice_number);
        $response->assertDontSee('Budi Invoice Filter');
    }

    public function test_admin_cabang_only_sees_assigned_pop_invoices(): void
    {
        $role = Role::where('name', 'Admin Cabang')->firstOrFail();
        $adminCabang = User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        $popA = $this->createPop('POP-A', 'PONA', 'POP A');
        $popB = $this->createPop('POP-B', 'PONB', 'POP B');
        $adminCabang->pops()->attach($popA->id);

        $invoiceA = $this->createInvoice($popA, 'Customer Cabang Sendiri', 'INV-202606-9101', '2026-06', 'belum_dibayar');
        $invoiceB = $this->createInvoice($popB, 'Customer Cabang Lain', 'INV-202606-9102', '2026-06', 'belum_dibayar');

        $response = $this->actingAs($adminCabang)->get(route('invoices.index'));

        $response->assertOk();
        $response->assertSee($invoiceA->invoice_number);
        $response->assertSee('Customer Cabang Sendiri');
        $response->assertDontSee($invoiceB->invoice_number);
        $response->assertDontSee('Customer Cabang Lain');

        $detailResponse = $this->actingAs($adminCabang)->get(route('invoices.show', $invoiceB->id));
        $detailResponse->assertForbidden();
    }

    public function test_invoice_detail_displays_customer_package_total_and_status(): void
    {
        $owner = User::where('email', 'owner@whusnet.net')->firstOrFail();
        $pop = $this->createPop('POP-C', 'PONC', 'POP C');
        $invoice = $this->createInvoice($pop, 'Detail Invoice Customer', 'INV-202608-9201', '2026-08', 'sebagian');

        $response = $this->actingAs($owner)->get(route('invoices.show', $invoice->id));

        $response->assertOk();
        $response->assertSee($invoice->invoice_number);
        $response->assertSee('Detail Invoice Customer');
        $response->assertSee($invoice->customerService->package_name_snapshot);
        $response->assertSee('Tidak dikenakan'); // ppn=0 maka tampil "Tidak dikenakan"
        $response->assertSee('150.000');
        $response->assertSee('Sebagian');
    }

    protected function createPop(string $code, string $popCode, string $name): Pop
    {
        return Pop::create([
            'code' => $code,
            'pop_code' => $popCode,
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => $name,
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    protected function createInvoice(Pop $pop, string $customerName, string $invoiceNumber, string $period, string $status): Invoice
    {
        $customer = Customer::create([
            'customer_code' => str_replace('INV', 'C', $invoiceNumber),
            'full_name' => $customerName,
            'phone' => '081234567890',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'customer_status' => 'aktif',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. Test Invoice',
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Test Invoice',
            'village' => 'Desa Test',
            'district' => 'Kecamatan Test',
            'city' => 'Kota Test',
            'province' => 'Jawa Timur',
        ]);

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $this->package->id,
            'package_name_snapshot' => 'Paket Test 20 Mbps',
            'download_speed_snapshot' => '20 Mbps',
            'upload_speed_snapshot' => '10 Mbps',
            'monthly_price' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => 150000,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        $paidAmount = $status === 'lunas' ? 150000 : ($status === 'sebagian' ? 50000 : 0);

        return Invoice::create([
            'invoice_number' => $invoiceNumber,
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $this->package->id,
            'billing_period' => $period,
            'issue_date' => "{$period}-01",
            'due_date' => "{$period}-15",
            'subtotal' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 150000,
            'paid_amount' => $paidAmount,
            'remaining_amount' => 150000 - $paidAmount,
            'invoice_status' => $status,
        ]);
    }
}
