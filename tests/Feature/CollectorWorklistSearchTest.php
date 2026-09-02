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

class CollectorWorklistSearchTest extends TestCase
{
    use RefreshDatabase;

    protected InternetPackage $package;

    protected Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();

        $this->pop = Pop::create([
            'code' => 'POP-WL-SRCH',
            'pop_code' => 'WLS',
            'registration_prefix' => 'CS',
            'cid_prefix' => 'DS',
            'name' => 'POP Worklist Search Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function createKolektor(Pop $pop): User
    {
        $role = Role::where('code', 'kolektor')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $pop->id]);

        return $user;
    }

    private function createCustomerWithUnpaidInvoice(string $name, string $code, string $cid, ?int $collectorId, string $invoiceNum): Customer
    {
        $customer = Customer::create([
            'customer_code' => $code,
            'full_name' => $name,
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $this->pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. '.$code,
            'collector_id' => $collectorId,
            'cid' => $cid,
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
            'invoice_number' => $invoiceNum,
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $this->package->id,
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

        return $customer;
    }

    public function test_kolektor_can_search_by_name(): void
    {
        $kolektor = $this->createKolektor($this->pop);

        $this->createCustomerWithUnpaidInvoice('Budi Utomo', 'C-WL-S1', 'CID001', $kolektor->id, 'INV-BUDI-01');
        $this->createCustomerWithUnpaidInvoice('Siti Aminah', 'C-WL-S2', 'CID002', $kolektor->id, 'INV-SITI-01');

        $response = $this->actingAs($kolektor)->get(route('collector-worklist.index', ['search' => 'Budi']));

        $response->assertOk();
        $response->assertSee('Budi Utomo');
        $response->assertDontSee('Siti Aminah');
    }

    public function test_kolektor_can_search_by_cid(): void
    {
        $kolektor = $this->createKolektor($this->pop);

        $this->createCustomerWithUnpaidInvoice('Budi Utomo', 'C-WL-S1', 'CID001', $kolektor->id, 'INV-BUDI-01');
        $this->createCustomerWithUnpaidInvoice('Siti Aminah', 'C-WL-S2', 'CID002', $kolektor->id, 'INV-SITI-01');

        $response = $this->actingAs($kolektor)->get(route('collector-worklist.index', ['search' => 'CID002']));

        $response->assertOk();
        $response->assertSee('Siti Aminah');
        $response->assertDontSee('Budi Utomo');
    }

    public function test_kolektor_can_search_by_invoice_number(): void
    {
        $kolektor = $this->createKolektor($this->pop);

        $this->createCustomerWithUnpaidInvoice('Budi Utomo', 'C-WL-S1', 'CID001', $kolektor->id, 'INV-BUDI-01');
        $this->createCustomerWithUnpaidInvoice('Siti Aminah', 'C-WL-S2', 'CID002', $kolektor->id, 'INV-SITI-01');

        $response = $this->actingAs($kolektor)->get(route('collector-worklist.index', ['search' => 'BUDI-01']));

        $response->assertOk();
        $response->assertSee('Budi Utomo');
        $response->assertDontSee('Siti Aminah');
    }

    public function test_search_retains_pagination_query_string(): void
    {
        $kolektor = $this->createKolektor($this->pop);

        $this->createCustomerWithUnpaidInvoice('Budi Utomo', 'C-WL-S1', 'CID001', $kolektor->id, 'INV-BUDI-01');

        $response = $this->actingAs($kolektor)->get(route('collector-worklist.index', ['search' => 'Budi']));

        $response->assertOk();
        // Memastikan parameter 'search=Budi' ada di query string paginator
        $paginator = $response->viewData('invoices');
        $this->assertStringContainsString('search=Budi', $paginator->url(1));
    }

    public function test_search_does_not_reveal_other_collector_data(): void
    {
        $kolektorA = $this->createKolektor($this->pop);
        $kolektorB = $this->createKolektor($this->pop);

        $this->createCustomerWithUnpaidInvoice('Budi Utomo', 'C-WL-S1', 'CID001', $kolektorA->id, 'INV-BUDI-01');
        $this->createCustomerWithUnpaidInvoice('Siti Aminah', 'C-WL-S2', 'CID002', $kolektorB->id, 'INV-SITI-01');

        // Kolektor A mencari 'Siti' (milik Kolektor B)
        $response = $this->actingAs($kolektorA)->get(route('collector-worklist.index', ['search' => 'Siti']));

        $response->assertOk();
        $response->assertDontSee('Siti Aminah');
    }
}
