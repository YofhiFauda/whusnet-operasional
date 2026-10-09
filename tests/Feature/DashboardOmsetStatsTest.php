<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\PackageCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardOmsetStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_dashboard_displays_omset_sales_teknisi_and_bisnis_cards(): void
    {
        $pop = Pop::first() ?? Pop::factory()->create(['name' => 'POP Omset Test']);

        $salesRole = Role::where('code', 'sales')->firstOrFail();
        $teknisiRole = Role::where('code', 'teknisi')->firstOrFail();
        $ownerRole = Role::where('name', 'Owner')->firstOrFail();

        $salesUser = User::factory()->create([
            'role_id' => $salesRole->id,
            'name' => 'Sales Person Demo',
            'status' => 'active',
        ]);

        $teknisiUser = User::factory()->create([
            'role_id' => $teknisiRole->id,
            'name' => 'Teknisi Field Demo',
            'status' => 'active',
        ]);

        $owner = User::factory()->create([
            'role_id' => $ownerRole->id,
            'status' => 'active',
        ]);

        $homeCat = PackageCategory::firstOrCreate(['name' => 'Home Broadband'], ['is_active' => true]);
        $bisnisCat = PackageCategory::firstOrCreate(
            ['name' => 'Bisnis Enterprise'],
            ['is_active' => true, 'installation_fee_approval_role_id' => $ownerRole->id]
        );

        $homePackage = InternetPackage::first() ?? InternetPackage::create([
            'package_code' => 'TEST-HOME-100K',
            'name' => 'Paket Home 100k',
            'category' => 'Home Broadband',
            'package_group' => 'reguler',
            'bandwidth_label' => '10 Mbps',
            'download_speed_mbps' => 10,
            'upload_speed_mbps' => 10,
            'contention_ratio' => 1,
            'monthly_price' => 100000,
            'ppn' => 0,
            'discount_default' => 0,
            'total_price' => 100000,
        ]);

        $bisnisPackage = InternetPackage::create([
            'package_code' => 'TEST-BISNIS-500K',
            'name' => 'Paket Bisnis Pro 500k',
            'category' => 'Bisnis Enterprise',
            'package_group' => 'reguler',
            'bandwidth_label' => '50 Mbps',
            'download_speed_mbps' => 50,
            'upload_speed_mbps' => 50,
            'contention_ratio' => 1,
            'monthly_price' => 500000,
            'ppn' => 0,
            'discount_default' => 0,
            'total_price' => 500000,
        ]);

        // Customer 1: Sales + Home Broadband (100k)
        $c1 = Customer::factory()->create([
            'sales_user_id' => $salesUser->id,
            'pop_id' => $pop->id,
            'status' => 'active',
        ]);
        CustomerService::create([
            'customer_id' => $c1->id,
            'internet_package_id' => $homePackage->id,
            'package_name_snapshot' => $homePackage->name,
            'monthly_price' => 100000,
            'total_monthly_bill' => 100000,
            'service_status' => 'aktif',
        ]);

        // Customer 2: Teknisi + Home Broadband (200k)
        $c2 = Customer::factory()->create([
            'sales_user_id' => $teknisiUser->id,
            'pop_id' => $pop->id,
            'status' => 'active',
        ]);
        CustomerService::create([
            'customer_id' => $c2->id,
            'internet_package_id' => $homePackage->id,
            'package_name_snapshot' => $homePackage->name,
            'monthly_price' => 200000,
            'total_monthly_bill' => 200000,
            'service_status' => 'aktif',
        ]);

        // Customer 3: Sales + Bisnis Enterprise (500k)
        $c3 = Customer::factory()->create([
            'sales_user_id' => $salesUser->id,
            'pop_id' => $pop->id,
            'status' => 'active',
        ]);
        CustomerService::create([
            'customer_id' => $c3->id,
            'internet_package_id' => $bisnisPackage->id,
            'package_name_snapshot' => $bisnisPackage->name,
            'monthly_price' => 500000,
            'total_monthly_bill' => 500000,
            'service_status' => 'aktif',
        ]);

        $response = $this->actingAs($owner)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Omset Sales');
        $response->assertSee('Omset Teknisi');
        $response->assertSee('Omset Bisnis');

        $stats = $response->viewData('stats');

        $this->assertEquals(600000.0, $stats['omset_sales_amount']); // 100k + 500k
        $this->assertEquals(2, $stats['omset_sales_count']);

        $this->assertEquals(200000.0, $stats['omset_teknisi_amount']); // 200k
        $this->assertEquals(1, $stats['omset_teknisi_count']);

        $this->assertEquals(500000.0, $stats['omset_bisnis_amount']); // 500k
        $this->assertEquals(1, $stats['omset_bisnis_count']);
    }
}
