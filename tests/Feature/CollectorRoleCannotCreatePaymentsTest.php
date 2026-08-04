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
 * Role `kolektor` (RBAC baru, docs/plan/analisa-billing-tagihan-pembayaran-
 * kolektor.md §B-8 no. 4 & no. 5) — hak paling minimal. TIDAK boleh input
 * pembayaran lewat jalur mana pun (single-payment maupun batch), dan
 * worklist-nya cuma baca pelanggan sendiri, bukan `customers.view` penuh.
 */
class CollectorRoleCannotCreatePaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function createKolektor(): User
    {
        $role = Role::where('code', 'kolektor')->firstOrFail();

        return User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
    }

    private function createInvoice(): Invoice
    {
        $package = InternetPackage::query()->firstOrFail();
        $pop = Pop::create([
            'code' => 'POP-KOL1',
            'pop_code' => 'KOL1',
            'registration_prefix' => 'CK',
            'cid_prefix' => 'DK',
            'name' => 'POP Kolektor Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);
        $customer = Customer::create([
            'customer_code' => 'C-KOL-0001',
            'full_name' => 'Pelanggan Kolektor Test',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $package->id,
            'address' => 'Jl. Kolektor Test',
        ]);
        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Kolektor Test',
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
            'invoice_number' => 'INV-KOL-0001',
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
    }

    public function test_kolektor_role_seeded_with_only_kolektor_view_permission(): void
    {
        $kolektor = $this->createKolektor();

        $this->assertTrue($kolektor->hasPermission('kolektor.view'));
        $this->assertFalse($kolektor->hasPermission('payments.create'));
        $this->assertFalse($kolektor->hasPermission('customers.view'));
    }

    public function test_kolektor_cannot_access_single_payment_form(): void
    {
        $kolektor = $this->createKolektor();
        $invoice = $this->createInvoice();

        $response = $this->actingAs($kolektor)->get(route('invoices.payments.create', $invoice->id));
        $response->assertForbidden();
    }

    public function test_kolektor_cannot_store_single_payment(): void
    {
        $kolektor = $this->createKolektor();
        $invoice = $this->createInvoice();

        $response = $this->actingAs($kolektor)->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 150000,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_kolektor_cannot_access_batch_endpoint(): void
    {
        $kolektor = $this->createKolektor();

        $response = $this->actingAs($kolektor)->get(route('collectors.show', $kolektor->id));
        $response->assertForbidden();

        $response = $this->actingAs($kolektor)->post(route('collector-batch.store', $kolektor->id), [
            'idempotency_key' => 'test-key',
            'rows' => [],
        ]);
        $response->assertForbidden();
    }

    public function test_kolektor_can_only_access_own_worklist(): void
    {
        $kolektor = $this->createKolektor();

        $response = $this->actingAs($kolektor)->get(route('collector-worklist.index'));
        $response->assertOk();
    }

    public function test_kolektor_cannot_access_full_customer_list(): void
    {
        $kolektor = $this->createKolektor();

        $response = $this->actingAs($kolektor)->get(route('customers.index'));
        $response->assertForbidden();
    }
}
