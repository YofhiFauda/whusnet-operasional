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
 * Hub Kolektor — daftar (index) + detail 2-tab (show). Pengganti "Atur
 * Kolektor" + dropdown "Tab Kolektor" lama (permintaan user 2026-08-03):
 * admin lihat worklist tiap kolektor + assign, satu tempat.
 */
class CollectorHubTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function createAdmin(Pop $pop): User
    {
        $role = Role::where('name', 'POP Admin')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $user->pops()->attach($pop->id);

        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $pop->id]);

        return $user;
    }

    private function createPop(string $code): Pop
    {
        return Pop::create([
            'code' => $code,
            'pop_code' => $code,
            'registration_prefix' => 'C'.substr($code, -1),
            'cid_prefix' => 'D'.substr($code, -1),
            'name' => 'POP '.$code,
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    public function test_index_lists_collectors_with_customer_count_and_unpaid_total(): void
    {
        $pop = $this->createPop('HUB1');
        $admin = $this->createAdmin($pop);

        $kolektorRole = Role::where('code', 'kolektor')->firstOrFail();
        $kolektor = User::factory()->create(['name' => 'Budi Kolektor', 'role_id' => $kolektorRole->id, 'status' => 'active']);

        $package = InternetPackage::query()->firstOrFail();
        $customer = Customer::create([
            'customer_code' => 'C-HUB-001',
            'full_name' => 'Pelanggan Hub Test',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $package->id,
            'address' => 'Jl. Hub Test',
            'collector_id' => $kolektor->id,
        ]);
        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Hub Test',
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
        Invoice::create([
            'invoice_number' => 'INV-HUB-001',
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

        $response = $this->actingAs($admin)->get(route('collectors.index'));

        $response->assertOk();
        $response->assertSee('Budi Kolektor');
        $response->assertSee('150.000'); // total tunggakan tampil
    }

    public function test_show_default_tab_is_worklist_and_assign_tab_accessible(): void
    {
        $pop = $this->createPop('HUB2');
        $admin = $this->createAdmin($pop);

        $kolektorRole = Role::where('code', 'kolektor')->firstOrFail();
        $kolektor = User::factory()->create(['role_id' => $kolektorRole->id, 'status' => 'active']);

        $responseDefault = $this->actingAs($admin)->get(route('collectors.show', $kolektor->id));
        $responseDefault->assertOk();
        $responseDefault->assertSee('Worklist &amp; Bayar', false);

        $responseAssign = $this->actingAs($admin)->get(route('collectors.show', ['collector' => $kolektor->id, 'tab' => 'assign']));
        $responseAssign->assertOk();
        $responseAssign->assertSee('Atur Pelanggan');
    }

    public function test_kolektor_role_cannot_access_hub_without_permission(): void
    {
        $kolektorRole = Role::where('code', 'kolektor')->firstOrFail();
        $kolektor = User::factory()->create(['role_id' => $kolektorRole->id, 'status' => 'active']);

        $response = $this->actingAs($kolektor)->get(route('collectors.index'));
        $response->assertForbidden();
    }
}
