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
 * Worklist kolektor — cuma pelanggan ber-`collector_id = dirinya sendiri`
 * yang tunggak, dan cuma yang masuk POP scope efektifnya. Kolektor A tak
 * boleh lihat pelanggan kolektor B.
 *
 * Sejak kolektor-2.0 halaman ini punya tombol bayar (§8) — yang dulu diuji
 * sebagai "nol aksi" sekarang diuji sebaliknya, dan ditambah lapis kedua
 * POP scope (§14.2 no. 4).
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

    /**
     * Kolektor + POP scope-nya. Scope WAJIB diisi: worklist memakai
     * `applyUserScope()`, dan repo ini deny-by-default — kolektor tanpa scope
     * memang tidak melihat apa pun. Itu perilaku yang benar, bukan artefak
     * test: user yang belum di-setup scope-nya tak boleh bocor lihat data.
     */
    private function createKolektor(?Pop $pop = null): User
    {
        $role = Role::where('code', 'kolektor')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        if ($pop) {
            $scope = UserRoleScope::create([
                'user_id' => $user->id,
                'role_id' => $role->id,
                'scope_type' => ScopeType::SELECTED_POP,
            ]);
            UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $pop->id]);
        }

        return $user;
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

        $kolektorA = $this->createKolektor($pop);
        $kolektorB = $this->createKolektor($pop);

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

        $kolektor = $this->createKolektor($pop);
        $this->createCustomerWithUnpaidInvoice($pop, 'C-WL-LUNAS', $kolektor->id, remaining: 0);

        $response = $this->actingAs($kolektor)->get(route('collector-worklist.index'));

        $response->assertOk();
        $response->assertDontSee('Pelanggan C-WL-LUNAS');
    }

    /**
     * Kebalikan test lama "nol aksi": worklist sekarang punya input bayar,
     * dan endpoint tujuannya WAJIB rute kolektor tanpa parameter. Kalau suatu
     * saat halaman ini menunjuk `payment-batches/{collector}`, kolektor bisa
     * ditipu mengirim id kolektor lain — itulah yang dijaga di sini.
     */
    public function test_worklist_exposes_pay_form_pointing_to_self_service_route(): void
    {
        $pop = Pop::create([
            'code' => 'POP-WL3',
            'pop_code' => 'WL3',
            'registration_prefix' => 'CY',
            'cid_prefix' => 'DY',
            'name' => 'POP Worklist Bayar',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $kolektor = $this->createKolektor($pop);
        $this->createCustomerWithUnpaidInvoice($pop, 'C-WL-PAY', $kolektor->id);

        $response = $this->actingAs($kolektor)->get(route('collector-worklist.index'));

        $response->assertOk();
        $response->assertSee('cb-amount', false);
        $response->assertSee(route('collector-worklist.pay'), false);

        // Rute admin (ber-{collector}) tak boleh muncul di halaman kolektor.
        $response->assertDontSee(route('payment-batches.store', $kolektor->id), false);
        $response->assertDontSee(route('invoices.payments.store', 1), false);
    }

    /**
     * Lapis kedua: `collector_id` cocok TAPI POP-nya di luar scope efektif
     * kolektor. Terjadi kalau kolektor dipindah cabang sesudah assign — assign
     * lama tak otomatis dibersihkan. Tanpa applyUserScope() di worklist,
     * pelanggan cabang lama tetap kelihatan dan bisa ditagih.
     */
    public function test_worklist_hides_assigned_customer_outside_collector_pop_scope(): void
    {
        $popDalam = Pop::create([
            'code' => 'POP-WL4',
            'pop_code' => 'WL4',
            'registration_prefix' => 'CZ',
            'cid_prefix' => 'DZ',
            'name' => 'POP Dalam Scope',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $popLuar = Pop::create([
            'code' => 'POP-WL5',
            'pop_code' => 'WL5',
            'registration_prefix' => 'CQ',
            'cid_prefix' => 'DQ',
            'name' => 'POP Luar Scope',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        // Scope-nya disusun manual di bawah, jadi helper dipanggil tanpa POP.
        $kolektor = $this->createKolektor();

        // Scope efektif kolektor: cuma POP dalam.
        $scope = UserRoleScope::create([
            'user_id' => $kolektor->id,
            'role_id' => $kolektor->role_id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $popDalam->id]);

        $this->createCustomerWithUnpaidInvoice($popDalam, 'C-WL-IN', $kolektor->id);
        $this->createCustomerWithUnpaidInvoice($popLuar, 'C-WL-OUT', $kolektor->id);

        $response = $this->actingAs($kolektor)->get(route('collector-worklist.index'));

        $response->assertOk();
        $response->assertSee('Pelanggan C-WL-IN');
        $response->assertDontSee('Pelanggan C-WL-OUT');
    }
}
