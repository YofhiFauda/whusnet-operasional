<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAcquisition;
use App\Models\CustomerService;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use Database\Seeders\ActionSeeder;
use Database\Seeders\BusinessDevelopmentFeatureSeeder;
use Database\Seeders\CustomerAcquisitionFeatureSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Skema 2 (2026-09-12) — Dashboard Omset Sales (Busdev). Omset = Biaya
 * Langganan × 11% (NILAI PPN itu sendiri, dikoreksi ulang user dari versi
 * awal "biaya dikurangi PPN") — lihat
 * CustomerAcquisition::getOmsetSalesAttribute(). Diagregasi per
 * `customers.sales_user_id`.
 */
class SalesOmsetDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(CustomerAcquisitionFeatureSeeder::class);
        $this->seed(BusinessDevelopmentFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    private function makeSales(): User
    {
        $role = Role::where('code', 'sales')->firstOrFail();

        return User::factory()->create(['status' => 'active', 'role_id' => $role->id]);
    }

    private function makeCustomerWithBill(User $sales, float $monthlyBill, ?Pop $pop = null): Customer
    {
        $customer = Customer::factory()->create([
            'sales_user_id' => $sales->id,
            'pop_id' => $pop?->id,
        ]);

        CustomerService::create([
            'customer_id' => $customer->id,
            'package_name_snapshot' => 'Net138',
            'monthly_price' => $monthlyBill,
            'total_monthly_bill' => $monthlyBill,
            'service_status' => 'aktif',
        ]);

        CustomerAcquisition::create([
            'customer_id' => $customer->id,
            'periode' => now()->format('Y-m'),
            'verified_at' => now(),
        ]);

        return $customer;
    }

    public function test_omset_aggregated_correctly_per_sales_with_ppn_formula(): void
    {
        $this->loginAsAdmin();
        $sales = $this->makeSales();

        $this->makeCustomerWithBill($sales, 150000);
        $this->makeCustomerWithBill($sales, 100000);

        $response = $this->get(route('business-development.sales-omset.index'));

        $response->assertOk();
        // (150000 + 100000) * 0.11 = 27.500
        $response->assertSee('27.500');
        $response->assertSee($sales->name);
    }

    /**
     * Contoh persis dari user (2026-09-12, koreksi): Biaya Langganan
     * Rp150.000 → Omset Rp16.500 (150.000 × 11%).
     */
    public function test_omset_matches_users_exact_example(): void
    {
        $this->loginAsAdmin();
        $sales = $this->makeSales();

        $customer = $this->makeCustomerWithBill($sales, 150000);
        $record = CustomerAcquisition::where('customer_id', $customer->id)->firstOrFail();

        $this->assertEquals(16500.0, $record->fresh()->load('customer.customerService')->omset_sales);
    }

    /**
     * Kolom baru (permintaan user, 2026-09-12): tabel utama nambah Total
     * Biaya Langganan & Total Harga Dikurangi PPN (dulu cuma Total Omset).
     * Rincian per pelanggan nambah Tanggal Aktivasi & Harga Dikurangi PPN.
     */
    public function test_new_columns_show_correct_totals_and_breakdown_fields(): void
    {
        $this->loginAsAdmin();
        $sales = $this->makeSales();

        $customer = $this->makeCustomerWithBill($sales, 150000);
        $customer->customerService()->update(['activation_date' => '2026-01-15']);

        $response = $this->get(route('business-development.sales-omset.index'));

        $response->assertOk();
        // Total Biaya Langganan: 150.000. Total Harga Dikurangi PPN: 133.500.
        $response->assertSee('150.000');
        $response->assertSee('133.500');
        $response->assertSee('16.500');
        $response->assertSee('15 Januari 2026');
    }

    /**
     * Filter Role & Nama (2026-09-12, permintaan user — pola sama
     * /customer-acquisitions). Kolom "Role" juga ditambah di tabel utama.
     */
    public function test_filter_by_role_and_name_narrows_dashboard_and_shows_role_column(): void
    {
        $this->loginAsAdmin();
        $salesRole = Role::where('code', 'sales')->firstOrFail();

        $salesA = $this->makeSales();
        $this->makeCustomerWithBill($salesA, 150000);

        $salesB = $this->makeSales();
        $this->makeCustomerWithBill($salesB, 200000);

        // Tanpa filter — kolom Role & kedua nama Sales muncul.
        $all = $this->get(route('business-development.sales-omset.index'));
        $all->assertOk();
        $all->assertSee($salesRole->name);
        $all->assertSee($salesA->name);
        $all->assertSee($salesB->name);

        // Filter role + nama spesifik — cuma satu Sales yang punya baris omset.
        $filtered = $this->get(route('business-development.sales-omset.index', [
            'role_id' => $salesRole->id,
            'sales_user_id' => $salesA->id,
        ]));
        $filtered->assertOk();
        $filtered->assertSee($salesA->name);
        // $salesB masih boleh nongol di dropdown "Nama" (opsi lain), tapi
        // TIDAK boleh punya baris tabel/total omset sendiri.
        $filtered->assertViewHas('bySales', function ($bySales) use ($salesB) {
            return $bySales->pluck('sales_user_id')->doesntContain($salesB->id);
        });
    }

    public function test_pop_scope_restricts_dashboard_visibility(): void
    {
        $popAllowed = Pop::factory()->create(['type' => 'cabang']);
        $popBlocked = Pop::factory()->create(['type' => 'cabang']);

        $role = Role::where('code', 'business_development')->firstOrFail();
        $busdev = User::factory()->create(['status' => 'active', 'role_id' => $role->id]);
        UserRoleScope::create([
            'user_id' => $busdev->id,
            'role_id' => $role->id,
            'scope_type' => 'selected_pop',
        ])->targets()->create(['pop_id' => $popAllowed->id]);

        $salesA = $this->makeSales();
        $this->makeCustomerWithBill($salesA, 150000, $popAllowed);

        $salesB = $this->makeSales();
        $this->makeCustomerWithBill($salesB, 500000, $popBlocked);

        $this->actingAs($busdev);
        $response = $this->get(route('business-development.sales-omset.index'));

        $response->assertOk();
        $response->assertSee($salesA->name);
        // $salesB TETAP boleh nongol di dropdown filter "Nama" (daftar semua
        // user role restricted, gak di-scope POP — sama pola
        // /customer-acquisitions) — yang wajib POP scope itu DATA
        // omsetnya (tabel), bukan daftar nama di filter.
        $response->assertViewHas('bySales', function ($bySales) use ($salesB) {
            return $bySales->pluck('sales_user_id')->doesntContain($salesB->id);
        });
    }

    public function test_permission_gate_blocks_role_without_access(): void
    {
        $sales = $this->makeSales();
        $this->actingAs($sales);

        $response = $this->get(route('business-development.sales-omset.index'));

        $response->assertForbidden();
    }
}
