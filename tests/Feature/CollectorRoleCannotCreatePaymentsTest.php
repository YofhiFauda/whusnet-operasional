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
 * Batas kewenangan role `kolektor` SETELAH kolektor-2.0.
 *
 * Yang BERUBAH (§8 dokumen 2.0 merevisi §B-8 no. 4 dokumen lama): kolektor
 * sekarang boleh mencatat pembayaran — tapi HANYA lewat
 * `/collector-worklist/pay`, hanya untuk pelanggan ber-`collector_id`
 * dirinya. Uji jalur itu ada di CollectorSelfPaymentTest.
 *
 * Yang TETAP, dan itulah yang dikunci di file ini: kolektor tidak punya
 * `payments.create` (bayar invoice mana pun), tidak bisa membuka Worksheet
 * Admin, tidak bisa mencatat pembayaran atas nama kolektor lain lewat rute
 * admin, dan tidak punya `customers.view` penuh.
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

    public function test_kolektor_role_seeded_with_own_worklist_permissions_only(): void
    {
        $kolektor = $this->createKolektor();

        $this->assertTrue($kolektor->hasPermission('kolektor.view'));
        $this->assertTrue($kolektor->hasPermission('kolektor.pay'));
        $this->assertTrue($kolektor->hasPermission('kolektor.deposit'));
        $this->assertTrue($kolektor->hasPermission('kolektor.visit'));

        // Kolektor menyetor, TIDAK memverifikasi. Kalau dia punya
        // `collector_worksheet.validate`, dia bisa menutup setorannya sendiri
        // dan cross check kehilangan seluruh gunanya.
        $this->assertFalse($kolektor->hasPermission('collector_worksheet.validate'));
        $this->assertFalse($kolektor->hasPermission('collector_worksheet.approve'));

        // Batasnya di sini: `kolektor.pay` bukan `payments.create`. Yang satu
        // cuma berlaku di rute worklist yang memaksa collector = auth user,
        // yang satu lagi hak bayar invoice mana pun di halaman Tagihan.
        $this->assertFalse($kolektor->hasPermission('payments.create'));
        $this->assertFalse($kolektor->hasPermission('customers.view'));
        $this->assertFalse($kolektor->hasPermission('collector_worksheet.view'));
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

    /**
     * Rute batch ADMIN menerima id kolektor dari URL. Kolektor tak boleh
     * menyentuhnya sama sekali — kalau boleh, kolektor A bisa mencatat
     * pembayaran atas nama kolektor B (itulah alasan rute kolektor dibuat
     * tanpa parameter, §9).
     */
    public function test_kolektor_cannot_access_admin_batch_endpoint_nor_worksheet(): void
    {
        $kolektor = $this->createKolektor();

        $response = $this->actingAs($kolektor)->get(route('collector-worksheet.index'));
        $response->assertForbidden();

        $response = $this->actingAs($kolektor)->get(route('collector-worksheet.show', $kolektor->id));
        $response->assertForbidden();

        $response = $this->actingAs($kolektor)->post(route('payment-batches.store', $kolektor->id), [
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
