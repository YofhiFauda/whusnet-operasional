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

/**
 * Worklist kolektor — read-only, cuma pelanggan ber-`collector_id = dirinya
 * sendiri` yang tunggak. Kolektor A tak boleh lihat pelanggan kolektor B
 * (docs/plan/analisa-billing-tagihan-pembayaran-kolektor.md §B-8 no. 5).
 */
class CollectorWorklistScopeTest extends TestCase
{
    use RefreshDatabase;

    protected InternetPackage $package;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();
    }

    private function createKolektor(): User
    {
        $role = Role::where('code', 'kolektor')->firstOrFail();

        return User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
    }

    private function createCustomerWithUnpaidInvoice(Pop $pop, string $code, ?int $collectorId, float $remaining = 100000): Customer
    {
        $customer = Customer::create([
            'customer_code' => $code,
            'full_name' => 'Pelanggan '.$code,
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. '.$code,
            'collector_id' => $collectorId,
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
            'internet_package_id' => $this->package->id,
            'package_name_snapshot' => $this->package->name,
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
            'invoice_number' => 'INV-'.$code,
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $this->package->id,
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'subtotal' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 150000,
            'paid_amount' => 150000 - $remaining,
            'remaining_amount' => $remaining,
            'invoice_status' => $remaining <= 0 ? 'lunas' : ($remaining >= 150000 ? 'belum_dibayar' : 'sebagian'),
        ]);

        return $customer;
    }

    public function test_kolektor_sees_only_own_customers_with_outstanding_invoices(): void
    {
        $pop = Pop::create([
            'code' => 'POP-WL1',
            'pop_code' => 'WL1',
            'registration_prefix' => 'CW',
            'cid_prefix' => 'DW',
            'name' => 'POP Worklist Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $kolektorA = $this->createKolektor();
        $kolektorB = $this->createKolektor();

        $this->createCustomerWithUnpaidInvoice($pop, 'C-WL-A1', $kolektorA->id);
        $this->createCustomerWithUnpaidInvoice($pop, 'C-WL-B1', $kolektorB->id);
        $this->createCustomerWithUnpaidInvoice($pop, 'C-WL-N1', null); // tanpa kolektor

        $response = $this->actingAs($kolektorA)->get(route('collector-worklist.index'));

        $response->assertOk();
        $response->assertSee('Pelanggan C-WL-A1');
        $response->assertDontSee('Pelanggan C-WL-B1');
        $response->assertDontSee('Pelanggan C-WL-N1');
    }

    public function test_worklist_excludes_customers_with_fully_paid_invoices(): void
    {
        $pop = Pop::create([
            'code' => 'POP-WL2',
            'pop_code' => 'WL2',
            'registration_prefix' => 'CX',
            'cid_prefix' => 'DX',
            'name' => 'POP Worklist Lunas',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $kolektor = $this->createKolektor();
        $this->createCustomerWithUnpaidInvoice($pop, 'C-WL-LUNAS', $kolektor->id, remaining: 0);

        $response = $this->actingAs($kolektor)->get(route('collector-worklist.index'));

        $response->assertOk();
        $response->assertDontSee('Pelanggan C-WL-LUNAS');
    }

    public function test_worklist_has_no_input_or_action_elements(): void
    {
        $pop = Pop::create([
            'code' => 'POP-WL3',
            'pop_code' => 'WL3',
            'registration_prefix' => 'CY',
            'cid_prefix' => 'DY',
            'name' => 'POP Worklist Nol Aksi',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $kolektor = $this->createKolektor();
        $this->createCustomerWithUnpaidInvoice($pop, 'C-WL-NOACT', $kolektor->id);

        $response = $this->actingAs($kolektor)->get(route('collector-worklist.index'));

        $response->assertOk();
        // Nol elemen input/aksi TERKAIT PEMBAYARAN di konten worklist — bukan
        // cek seluruh halaman (layout punya form/button lain: logout, dst).
        $response->assertDontSee('cb-amount', false);
        $response->assertDontSee('bulk-pay', false);
        $response->assertDontSee(route('invoices.payments.store', 1), false);
    }
}
