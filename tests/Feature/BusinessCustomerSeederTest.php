<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\InventorySerial;
use Database\Seeders\ActionSeeder;
use Database\Seeders\BusinessCustomerSeeder;
use Database\Seeders\BusinessDevelopmentFeatureSeeder;
use Database\Seeders\CustomerAcquisitionFeatureSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BusinessCustomerSeeder menanam 7 pelanggan dari tabel sumber
 * `docs/plan/bussiness-development/tabel_paket_bisnis.md` — yang dijaga:
 * semuanya benar-benar muncul di List Pelanggan Bisnis dengan angka yang
 * sama, dan seeder aman dijalankan ulang (tidak menggandakan data).
 */
class BusinessCustomerSeederTest extends TestCase
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

    public function test_seeded_customers_show_up_in_business_customer_list_with_source_numbers(): void
    {
        $this->seed(BusinessCustomerSeeder::class);
        $this->loginAsAdmin();

        $response = $this->get(route('business-development.business-customers.index'));

        $response->assertOk();
        foreach ([
            'PT. ANJALIS GROUP INDONESIA', 'KOS THE COZY (DUTA MAHARDIKA)', 'WARUNG KOPI CANGKIR KUMPUL',
            'TEDUH ALAMI RESTO FAMILY &amp; CAFE', 'SPPG BALONG', 'SAVE PLUS GREBEG SURO', 'TAMAN JATIMORI',
        ] as $name) {
            $response->assertSee($name, false);
        }
        $response->assertSee('Rp 5.500.000');
        $response->assertSee('Rp 433.000');
        $response->assertSee('Rp 277.500');
        $response->assertSee('Rp 1.665.000');
        $response->assertSee('Rp 2.500.000');
        $response->assertSee('3 TpLink Omada AX1800');
        $response->assertSee('6 AP FiberHome');
        $response->assertSee('2 ONT F670L');
        $response->assertSee('07 Mei 2026');
        $response->assertSee('13 Agustus 2026');
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(BusinessCustomerSeeder::class);
        $this->seed(BusinessCustomerSeeder::class);

        $this->assertSame(1, Customer::where('full_name', 'SPPG BALONG')->count());
        $this->assertDatabaseCount('customer_acquisitions', 7);
        // 1+1+3 + 6 + 1+1 + 1+1 + 2 + 0 + 1 = 18 unit terpasang.
        $this->assertSame(18, InventorySerial::count());
    }
}
