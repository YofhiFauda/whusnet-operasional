<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Customer;
use App\Models\RestrictedPackage;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\BusinessDevelopmentFeatureSeeder;
use Database\Seeders\BusinessDevelopmentSeeder;
use Database\Seeders\CustomerAcquisitionFeatureSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\InternetPackageSeeder;
use Database\Seeders\PonorogoRegionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SalesSeeder;
use Database\Seeders\TechnicianSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Data demo Business Development (Skema 1-3, 2026-09-12) — reuse role
 * Sales/Teknisi yang sudah ada (`SalesSeeder`/`TechnicianSeeder`), bukan
 * bikin user baru. Cakupan: user Busdev demo, 8 paket restriksi contoh
 * (Net138/150/165/198 + Khusus), Master Agent contoh, dan pelanggan demo
 * ber-`sales_user_id` (Sales & Teknisi) supaya Dashboard Omset Sales +
 * filter "Diinput Oleh" langsung ada isinya.
 */
class BusinessDevelopmentSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PonorogoRegionSeeder::class);
        $this->seed(InternetPackageSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(CustomerAcquisitionFeatureSeeder::class);
        $this->seed(BusinessDevelopmentFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(TechnicianSeeder::class);
        $this->seed(SalesSeeder::class);
    }

    public function test_seeder_creates_busdev_demo_user(): void
    {
        $this->seed(BusinessDevelopmentSeeder::class);

        $busdev = User::where('email', 'busdev@whusnet.com')->first();
        $this->assertNotNull($busdev);
        $this->assertSame('business_development', $busdev->role->code);
    }

    public function test_seeder_populates_the_8_example_restricted_packages(): void
    {
        $this->seed(BusinessDevelopmentSeeder::class);

        $codes = RestrictedPackage::with('package')->get()->pluck('package.package_code')->sort()->values();

        $this->assertEquals(
            collect(['Net138', 'Net138 Khusus', 'Net150', 'Net150 Khusus', 'Net165', 'Net165 Khusus', 'Net198', 'Net198 Khusus'])->sort()->values(),
            $codes
        );
    }

    public function test_seeder_creates_demo_agents(): void
    {
        $this->seed(BusinessDevelopmentSeeder::class);

        $this->assertGreaterThanOrEqual(2, Agent::count());
    }

    public function test_seeder_creates_demo_customers_linked_to_existing_sales_and_teknisi(): void
    {
        $this->seed(BusinessDevelopmentSeeder::class);

        $sales = User::where('email', 'sales@whusnet.com')->firstOrFail();
        $teknisi = User::where('email', 'teknisi1@whusnet.com')->firstOrFail();

        $this->assertDatabaseHas('customers', ['full_name' => 'Pelanggan Demo Sales 1', 'sales_user_id' => $sales->id]);
        $this->assertDatabaseHas('customers', ['full_name' => 'Pelanggan Demo Teknisi 1', 'sales_user_id' => $teknisi->id]);

        $salesCustomer = Customer::where('full_name', 'Pelanggan Demo Sales 1')->firstOrFail();
        $this->assertNotNull($salesCustomer->customerService);
        $this->assertNotNull($salesCustomer->customerService->total_monthly_bill);
        $this->assertDatabaseHas('customer_acquisitions', ['customer_id' => $salesCustomer->id]);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(BusinessDevelopmentSeeder::class);
        $this->seed(BusinessDevelopmentSeeder::class);

        $this->assertSame(8, RestrictedPackage::count());
        $this->assertSame(2, Agent::count());
        $this->assertSame(1, User::where('email', 'busdev@whusnet.com')->count());
        $this->assertSame(3, Customer::whereIn('full_name', [
            'Pelanggan Demo Sales 1', 'Pelanggan Demo Sales 2', 'Pelanggan Demo Teknisi 1',
        ])->count());
    }

    public function test_omset_dashboard_shows_seeded_demo_data(): void
    {
        $this->seed(BusinessDevelopmentSeeder::class);

        $busdev = User::where('email', 'busdev@whusnet.com')->firstOrFail();
        $this->actingAs($busdev);

        $response = $this->get(route('business-development.sales-omset.index'));

        $response->assertOk();
        $response->assertSee('Sales Demo');
    }
}
