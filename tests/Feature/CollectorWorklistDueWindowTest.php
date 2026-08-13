<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Jendela tagih Worklist Kolektor — `config('billing.collector_due_window_days')`.
 *
 * Tiga aturan §10 yang gampang tertukar, ketiganya diuji di sini:
 *   1. tagihan yang masih jauh dari jatuh tempo TIDAK muncul;
 *   2. tagihan dalam jendela muncul walau belum jatuh tempo;
 *   3. begitu satu pelanggan masuk daftar, SELURUH tunggakannya ikut tampil —
 *      termasuk yang di luar jendela. Kolektor cuma lewat sebulan sekali;
 *      kalau tunggakan lama dan tagihan berjalan pecah ke dua kunjungan, dia
 *      harus datang dua kali ke pintu yang sama.
 */
class CollectorWorklistDueWindowTest extends TestCase
{
    use RefreshDatabase;

    protected InternetPackage $package;

    protected Pop $pop;

    protected User $kolektor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();

        $this->pop = Pop::create([
            'code' => 'POP-DW1',
            'pop_code' => 'DW1',
            'registration_prefix' => 'CD',
            'cid_prefix' => 'DD',
            'name' => 'POP Due Window',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $role = Role::where('code', 'kolektor')->firstOrFail();
        $this->kolektor = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $scope = UserRoleScope::create([
            'user_id' => $this->kolektor->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $this->pop->id]);
    }

    private function createCustomer(string $code): Customer
    {
        $customer = Customer::create([
            'customer_code' => $code,
            'full_name' => 'Pelanggan '.$code,
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $this->pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. '.$code,
            'collector_id' => $this->kolektor->id,
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. '.$code,
            'village' => 'Desa Test',
            'district' => 'Kecamatan Test',
            'city' => 'Kota Test',
            'province' => 'Jawa Timur',
        ]);

        return $customer;
    }

    private function createInvoice(Customer $customer, string $number, string $dueDate): Invoice
    {
        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $this->package->id,
            'package_name_snapshot' => $this->package->name,
            'monthly_price' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => 150000,
            'activation_date' => '2026-06-01',
            'due_date' => $dueDate,
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        return Invoice::create([
            'invoice_number' => $number,
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $this->package->id,
            'billing_period' => substr($dueDate, 0, 7),
            'issue_date' => $dueDate,
            'due_date' => $dueDate,
            'subtotal' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'invoice_status' => 'belum_dibayar',
        ]);
    }

    public function test_invoice_far_beyond_due_window_is_hidden(): void
    {
        config(['billing.collector_due_window_days' => 7]);

        $customer = $this->createCustomer('C-DW-FAR');
        $this->createInvoice($customer, 'INV-DW-FAR', now()->addDays(30)->toDateString());

        $response = $this->actingAs($this->kolektor)->get(route('collector-worklist.index'));

        $response->assertOk();
        $response->assertDontSee('INV-DW-FAR');
    }

    public function test_invoice_inside_due_window_is_shown_before_due_date(): void
    {
        config(['billing.collector_due_window_days' => 7]);

        $customer = $this->createCustomer('C-DW-SOON');
        $this->createInvoice($customer, 'INV-DW-SOON', now()->addDays(3)->toDateString());

        $response = $this->actingAs($this->kolektor)->get(route('collector-worklist.index'));

        $response->assertOk();
        $response->assertSee('INV-DW-SOON');
    }

    public function test_all_outstanding_invoices_shown_once_customer_enters_worklist(): void
    {
        config(['billing.collector_due_window_days' => 7]);

        $customer = $this->createCustomer('C-DW-MIX');
        $this->createInvoice($customer, 'INV-DW-LAMA', now()->subDays(40)->toDateString());
        $this->createInvoice($customer, 'INV-DW-JAUH', now()->addDays(30)->toDateString());

        $response = $this->actingAs($this->kolektor)->get(route('collector-worklist.index'));

        $response->assertOk();
        $response->assertSee('INV-DW-LAMA');
        // Ikut tampil MESKI di luar jendela, karena pelanggannya sudah harus
        // didatangi — sekali datang, seluruh tunggakannya selesai.
        $response->assertSee('INV-DW-JAUH');
    }

    /**
     * Jendela dibaca dari config, bukan angka mati di query — supaya tiap POP
     * bisa disetel tanpa deploy.
     */
    public function test_due_window_is_configurable(): void
    {
        config(['billing.collector_due_window_days' => 45]);

        $customer = $this->createCustomer('C-DW-CFG');
        $this->createInvoice($customer, 'INV-DW-CFG', now()->addDays(30)->toDateString());

        $response = $this->actingAs($this->kolektor)->get(route('collector-worklist.index'));

        $response->assertOk();
        $response->assertSee('INV-DW-CFG');
    }

    /**
     * Worksheet Admin sengaja TIDAK memakai jendela: admin bukan pengetuk
     * pintu, dia butuh gambaran penuh untuk cross check.
     */
    public function test_admin_worksheet_shows_invoices_outside_due_window(): void
    {
        config(['billing.collector_due_window_days' => 7]);

        $customer = $this->createCustomer('C-DW-ADMIN');
        $this->createInvoice($customer, 'INV-DW-ADMIN', now()->addDays(30)->toDateString());

        $admin = User::factory()->create([
            'role_id' => Role::where('code', 'owner')->firstOrFail()->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->get(route('collector-worksheet.show', $this->kolektor->id));

        $response->assertOk();
        $response->assertSee('INV-DW-ADMIN');
    }
}
