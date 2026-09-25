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
 * Kapan tagihan boleh ditagih kolektor — berbasis PERIODE, bukan `due_date`.
 *
 * Nama kelas dipertahankan dari era jendela `collector_due_window_days`
 * (sudah dihapus): aturannya kini `billing_period <= bulan berjalan`, selaras
 * dengan `Invoice::scopePiutang()`. Yang diuji:
 *   1. tagihan periode yang belum dimulai TIDAK muncul;
 *   2. tagihan periode berjalan muncul walau `due_date`-nya belum lewat —
 *      termasuk `due_date` di akhir bulan (label UI, bukan gerbang);
 *   3. begitu satu pelanggan masuk daftar, SELURUH tunggakannya ikut tampil.
 *      Kolektor cuma lewat sebulan sekali; kalau tunggakan lama dan tagihan
 *      berjalan pecah ke dua kunjungan, dia harus datang dua kali.
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

    private function createInvoice(Customer $customer, string $number, string $dueDate, ?string $billingPeriod = null): Invoice
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
            'billing_period' => $billingPeriod ?? substr($dueDate, 0, 7),
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

    public function test_invoice_periode_belum_dimulai_tidak_muncul(): void
    {
        $this->travelTo('2026-09-23 10:00:00');

        $customer = $this->createCustomer('C-DW-FAR');
        $this->createInvoice($customer, 'INV-DW-FAR', '2026-10-10', '2026-10');

        $response = $this->actingAs($this->kolektor)->get(route('collector-worklist.index'));

        $response->assertOk();
        $response->assertDontSee('INV-DW-FAR');
    }

    /**
     * Tanggal 1 tagihan bulanan terbit → hari itu juga sudah boleh ditagih.
     * Jendela lama (`due_date` 10 ≤ hari ini + 7) menyembunyikannya sampai
     * tanggal 3.
     */
    public function test_invoice_periode_berjalan_muncul_sejak_tanggal_terbit(): void
    {
        $this->travelTo('2026-09-01 08:00:00');

        $customer = $this->createCustomer('C-DW-SOON');
        $this->createInvoice($customer, 'INV-DW-SOON', '2026-09-10', '2026-09');

        $response = $this->actingAs($this->kolektor)->get(route('collector-worklist.index'));

        $response->assertOk();
        $response->assertSee('INV-DW-SOON');
    }

    /**
     * `due_date` cuma label UI — nilainya di akhir bulan pun tidak boleh
     * menahan tagihan periode berjalan dari daftar kolektor.
     */
    public function test_due_date_akhir_bulan_tidak_menahan_tagihan_periode_berjalan(): void
    {
        $this->travelTo('2026-09-02 08:00:00');

        $customer = $this->createCustomer('C-DW-EOM');
        $this->createInvoice($customer, 'INV-DW-EOM', '2026-09-30', '2026-09');

        $response = $this->actingAs($this->kolektor)->get(route('collector-worklist.index'));

        $response->assertOk();
        $response->assertSee('INV-DW-EOM');
    }

    public function test_all_outstanding_invoices_shown_once_customer_enters_worklist(): void
    {
        $this->travelTo('2026-09-23 10:00:00');

        $customer = $this->createCustomer('C-DW-MIX');
        $this->createInvoice($customer, 'INV-DW-LAMA', '2026-08-10', '2026-08');
        $this->createInvoice($customer, 'INV-DW-JAUH', '2026-10-10', '2026-10');

        $response = $this->actingAs($this->kolektor)->get(route('collector-worklist.index'));

        $response->assertOk();
        $response->assertSee('INV-DW-LAMA');
        // Ikut tampil MESKI periodenya belum dimulai, karena pelanggannya
        // sudah harus didatangi — sekali datang, seluruh tunggakannya selesai.
        $response->assertSee('INV-DW-JAUH');
    }

    /**
     * Worksheet Admin sengaja TIDAK memfilter periode: admin bukan pengetuk
     * pintu, dia butuh gambaran penuh untuk cross check.
     */
    public function test_admin_worksheet_shows_invoices_outside_due_window(): void
    {
        $this->travelTo('2026-09-23 10:00:00');

        $customer = $this->createCustomer('C-DW-ADMIN');
        $this->createInvoice($customer, 'INV-DW-ADMIN', '2026-10-10', '2026-10');

        $admin = User::factory()->create([
            'role_id' => Role::where('code', 'owner')->firstOrFail()->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->get(route('collector-worksheet.show', $this->kolektor->id));

        $response->assertOk();
        $response->assertSee('INV-DW-ADMIN');
    }
}
