<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\IsolationAdminDecision;
use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\District;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\IsolationCase;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\Village;
use App\Services\IsolationCaseService;
use App\Services\IsolationExecutionService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\InternetPackageSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PonorogoRegionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SubscriptionStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Endpoint eksekusi isolir dari Web A
 * (docs/superpowers/specs/2026-11-20-webhook-isolir-web-a-design.md).
 */
class IsolationExecutionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['webhooks.web_a_write_token' => 'token-benar']);

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SubscriptionStatusSeeder::class);
        $this->seed(InternetPackageSeeder::class);
        $this->seed(PonorogoRegionSeeder::class);

        $this->pop = Pop::create([
            'name' => 'POP Ponorogo Kota',
            'code' => 'PNO-01',
            'pop_code' => 'PNO',
            'cid_prefix' => 'P',
            'type' => 'branch',
            'status' => 'active',
        ]);

        $this->package = InternetPackage::first();
    }

    #[Test]
    public function token_salah_ditolak_401(): void
    {
        $this->postJson('/api/v1/isolation-cases/execute', ['case_number' => 'ISO-TEST'], [
            'Authorization' => 'Bearer token-salah',
        ])->assertStatus(401);
    }

    #[Test]
    public function tanpa_token_ditolak_401(): void
    {
        $this->postJson('/api/v1/isolation-cases/execute', ['case_number' => 'ISO-TEST'])
            ->assertStatus(401);
    }

    #[Test]
    public function eksekusi_sukses_mengubah_status_pelanggan(): void
    {
        $this->travelTo(now()->parse('2026-09-25'));

        $customer = $this->createCustomer('active');
        $case = $this->makeReadyCase($customer);

        $this->postJson('/api/v1/isolation-cases/execute', ['case_number' => $case->case_number], [
            'Authorization' => 'Bearer token-benar',
        ])->assertStatus(200)->assertJson(['success' => true, 'already_suspended' => false]);

        $this->assertSame('suspended', $customer->refresh()->status);

        $this->travelBack();
    }

    #[Test]
    public function eksekusi_case_number_tidak_ada_422(): void
    {
        $this->postJson('/api/v1/isolation-cases/execute', ['case_number' => 'TIDAK-ADA'], [
            'Authorization' => 'Bearer token-benar',
        ])->assertStatus(422);
    }

    #[Test]
    public function eksekusi_kasus_belum_ready_422(): void
    {
        $this->travelTo(now()->parse('2026-09-25'));

        $customer = $this->createCustomer('active');
        $this->createOverdueInvoice($customer, '2026-08');
        $this->artisan('isolir:sync-cases')->assertSuccessful();
        $case = IsolationCase::where('service_id', $customer->customerService->id)->first();

        $this->postJson('/api/v1/isolation-cases/execute', ['case_number' => $case->case_number], [
            'Authorization' => 'Bearer token-benar',
        ])->assertStatus(422);

        $this->travelBack();
    }

    #[Test]
    public function eksekusi_sudah_suspended_200_idempotent(): void
    {
        $this->travelTo(now()->parse('2026-09-25'));

        $customer = $this->createCustomer('active');
        $case = $this->makeReadyCase($customer);

        app(IsolationExecutionService::class)->suspendFromWebA($customer);

        $this->postJson('/api/v1/isolation-cases/execute', ['case_number' => $case->refresh()->case_number], [
            'Authorization' => 'Bearer token-benar',
        ])->assertStatus(200)->assertJson(['success' => true, 'already_suspended' => true]);

        $this->travelBack();
    }

    protected Pop $pop;

    protected InternetPackage $package;

    protected User $adminUser;

    protected function createCustomer(string $status = 'active'): Customer
    {
        $city = City::query()->where('name', 'Ponorogo')->firstOrFail();
        $district = District::query()->where('city_id', $city->id)->firstOrFail();
        $village = Village::query()->where('district_id', $district->id)->firstOrFail();

        static $seq = 0;
        $seq++;

        $customer = Customer::create([
            'customer_code' => 'RQ'.str_pad((string) $seq, 6, '0', STR_PAD_LEFT),
            'cid' => 'P00RQ'.str_pad((string) $seq, 6, '0', STR_PAD_LEFT),
            'full_name' => 'Pelanggan Api '.$seq,
            'gender' => 'Laki-laki',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => $status,
            'pop_id' => $this->pop->id,
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. Diponegoro No. 10',
            'data_completeness_status' => 'siap_billing',
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Diponegoro No. 10',
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
        ]);

        CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $this->package->id,
            'package_name_snapshot' => $this->package->name,
            'service_status' => $status === 'active' ? 'aktif' : 'isolir',
            'billing_status' => 'active',
            'monthly_price' => $this->package->monthly_price,
            'total_monthly_bill' => $this->package->monthly_price,
            'billing_cycle' => 'monthly',
        ]);

        return $customer;
    }

    protected function createOverdueInvoice(Customer $customer, string $periode = '2026-08'): Invoice
    {
        return Invoice::create([
            'invoice_number' => 'INV-PTG-'.$customer->id.'-'.$periode,
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $customer->pop_id,
            'customer_service_id' => $customer->customerService->id,
            'internet_package_id' => $this->package->id,
            'billing_period' => $periode,
            'issue_date' => $periode.'-01',
            'due_date' => $periode.'-10',
            'subtotal' => 100000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 100000,
            'paid_amount' => 0,
            'remaining_amount' => 100000,
            'invoice_status' => InvoiceStatus::BELUM_DIBAYAR->value,
        ]);
    }

    protected function makeReadyCase(Customer $customer): IsolationCase
    {
        $this->createOverdueInvoice($customer, '2026-08');
        $this->artisan('isolir:sync-cases')->assertSuccessful();

        $case = IsolationCase::where('service_id', $customer->customerService->id)->first();

        $role = Role::where('name', 'Admin')->firstOrFail();
        $this->adminUser = $this->adminUser ?? User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        UserRoleScope::firstOrCreate([
            'user_id' => $this->adminUser->id,
        ], [
            'role_id' => $this->adminUser->role_id,
            'scope_type' => 'all_pop',
        ]);

        app(IsolationCaseService::class)->setAdminDecision($case, IsolationAdminDecision::EXECUTE, $this->adminUser, null);

        return $case->refresh();
    }
}
