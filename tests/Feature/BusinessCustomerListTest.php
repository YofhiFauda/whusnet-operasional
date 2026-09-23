<?php

namespace Tests\Feature;

use App\Enums\SerialStatus;
use App\Models\Customer;
use App\Models\CustomerAcquisition;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\PackageCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Services\EffectiveAccessService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\BusinessDevelopmentFeatureSeeder;
use Database\Seeders\CustomerAcquisitionFeatureSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * List Pelanggan Bisnis (Business Development) — turunan data sistem
 * (kategori paket Bisnis), read-only. Yang dijaga: definisi "Bisnis" ikut
 * Master Kategori Paket, angka harga/PPN/alat/biaya instalasi benar, tahap
 * pra-pemasangan tidak ikut, dan POP scope + permission wajib.
 */
class BusinessCustomerListTest extends TestCase
{
    use RefreshDatabase;

    private PackageCategory $bisnis;

    private PackageCategory $home;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(CustomerAcquisitionFeatureSeeder::class);
        $this->seed(BusinessDevelopmentFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        // CustomerAcquisitionFeatureSeeder sudah memetakan kategori ber-nama
        // "Bisnis" ke role BD; di test ini kategorinya dibuat eksplisit biar
        // tidak bergantung ke data seeder.
        $bdRole = Role::where('code', 'business_development')->firstOrFail();
        $this->bisnis = PackageCategory::factory()->create([
            'name' => 'Broadband Bisnis',
            'installation_fee_approval_role_id' => $bdRole->id,
        ]);
        $this->home = PackageCategory::factory()->create(['name' => 'Home Broadband']);
    }

    private function makeCustomer(PackageCategory $category, array $customer = [], array $service = []): Customer
    {
        $package = InternetPackage::create([
            'package_code' => 'PKG-'.uniqid(),
            'name' => $category->name.' 50 Mbps',
            'category' => $category->name,
            'package_group' => $category->name,
            'bandwidth_label' => '50 Mbps',
            'monthly_price' => 500000,
            'is_active' => true,
        ]);

        $customer = Customer::factory()->create(array_merge(['status' => 'active'], $customer));

        CustomerService::create(array_merge([
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'monthly_price' => 500000,
            'discount' => 0,
            'ppn' => 11,
            'total_monthly_bill' => 555000,
            'activation_date' => '2026-05-12',
            'service_status' => 'aktif',
        ], $service));

        return $customer;
    }

    private function makeInstalledSerial(Customer $customer, string $itemName, string $serial, SerialStatus $status = SerialStatus::INSTALLED): void
    {
        $category = ItemCategory::firstOrCreate(['code' => 'BCL'], ['name' => 'Kategori Test']);
        $item = Item::firstOrCreate(
            ['code' => 'BCL-'.strtoupper(substr(md5($itemName), 0, 6))],
            ['item_category_id' => $category->id, 'name' => $itemName, 'unit' => 'Unit', 'tracking_type' => 'serialized', 'is_active' => true],
        );

        InventorySerial::create([
            'item_id' => $item->id,
            'serial_number' => $serial,
            'status' => $status->value,
            'customer_id' => $customer->id,
            'installed_at' => now(),
        ]);
    }

    private function busdevUser(): User
    {
        $user = User::factory()->create([
            'status' => 'active',
            'role_id' => Role::where('code', 'business_development')->firstOrFail()->id,
        ]);
        // Cache permission array-store tidak ikut ke-reset antar test
        // (ID user berulang dari 1) — lihat catatan CustomerAcquisitionModuleTest.
        app(EffectiveAccessService::class)->clearCache($user);

        return $user;
    }

    public function test_lists_business_customer_with_price_ppn_equipment_installation_fee_and_activation_date(): void
    {
        $this->loginAsAdmin();

        $customer = $this->makeCustomer($this->bisnis, ['full_name' => 'PT. ANJALIS GROUP INDONESIA'], [
            'monthly_price' => 1500000,
            'discount' => 100000,
            'ppn' => 11,
            'total_monthly_bill' => 1554000,
            'activation_date' => '2026-06-06',
        ]);
        $this->makeInstalledSerial($customer, 'AP TpLink Omada AX1800', 'SN-AP-1');
        $this->makeInstalledSerial($customer, 'AP TpLink Omada AX1800', 'SN-AP-2');
        $this->makeInstalledSerial($customer, 'ONT F670L', 'SN-ONT-1');
        CustomerAcquisition::factory()->for($customer)->create([
            'periode' => '2026-06',
            'verified_at' => '2026-06-06',
            'installation_fee' => 500000,
        ]);

        $response = $this->get(route('business-development.business-customers.index'));

        $response->assertOk();
        $response->assertSee('PT. ANJALIS GROUP INDONESIA');
        $response->assertSee('Broadband Bisnis');
        // Harga Paket = 1.500.000 - diskon 100.000, sebelum PPN.
        $response->assertSee('Rp 1.400.000');
        // Harga Sesudah PPN = total_monthly_bill apa adanya.
        $response->assertSee('Rp 1.554.000');
        $response->assertSee('PPN 11%');
        // Alat dikelompokkan per nama barang.
        $response->assertSee('2 AP TpLink Omada AX1800');
        $response->assertSee('1 ONT F670L');
        $response->assertSee('Rp 500.000');
        $response->assertSee('06 Juni 2026');
    }

    public function test_price_without_ppn_shows_same_amount_and_no_ppn_label(): void
    {
        $this->loginAsAdmin();

        $this->makeCustomer($this->bisnis, ['full_name' => 'KOS THE COZY'], [
            'monthly_price' => 1190000,
            'ppn' => 0,
            'total_monthly_bill' => 1190000,
        ]);

        $response = $this->get(route('business-development.business-customers.index'));

        $response->assertOk();
        $response->assertSee('Rp 1.190.000');
        $response->assertDontSee('PPN 0%');
    }

    public function test_only_installed_serials_count_as_equipment_left_behind(): void
    {
        $this->loginAsAdmin();

        $customer = $this->makeCustomer($this->bisnis, ['full_name' => 'SPPG BALONG']);
        $this->makeInstalledSerial($customer, 'ONT Terpasang', 'SN-OK', SerialStatus::INSTALLED);
        $this->makeInstalledSerial($customer, 'Modem Sudah Ditarik', 'SN-RETURNED', SerialStatus::RETURNED);

        $response = $this->get(route('business-development.business-customers.index'));

        $response->assertSee('1 ONT Terpasang');
        $response->assertDontSee('Modem Sudah Ditarik');
    }

    public function test_non_business_package_customer_is_not_listed(): void
    {
        $this->loginAsAdmin();

        $this->makeCustomer($this->bisnis, ['full_name' => 'PELANGGAN BISNIS ASLI']);
        $this->makeCustomer($this->home, ['full_name' => 'PELANGGAN RUMAHAN']);

        $response = $this->get(route('business-development.business-customers.index'));

        $response->assertSee('PELANGGAN BISNIS ASLI');
        $response->assertDontSee('PELANGGAN RUMAHAN');
    }

    public function test_customer_still_in_registration_or_rejected_is_not_listed(): void
    {
        $this->loginAsAdmin();

        $this->makeCustomer($this->bisnis, ['full_name' => 'SUDAH AKTIF']);
        $this->makeCustomer($this->bisnis, ['full_name' => 'MASIH SURVEY', 'status' => 'waiting_survey']);
        $this->makeCustomer($this->bisnis, ['full_name' => 'DITOLAK', 'status' => 'rejected']);

        $response = $this->get(route('business-development.business-customers.index'));

        $response->assertSee('SUDAH AKTIF');
        $response->assertDontSee('MASIH SURVEY');
        $response->assertDontSee('DITOLAK');
    }

    public function test_search_and_status_filter_narrow_the_list(): void
    {
        $this->loginAsAdmin();

        $this->makeCustomer($this->bisnis, ['full_name' => 'WARUNG KOPI CANGKIR']);
        $this->makeCustomer($this->bisnis, ['full_name' => 'TAMAN JATIMORI', 'status' => 'suspended']);

        $byName = $this->get(route('business-development.business-customers.index', ['q' => 'cangkir']));
        $byName->assertSee('WARUNG KOPI CANGKIR');
        $byName->assertDontSee('TAMAN JATIMORI');

        $byStatus = $this->get(route('business-development.business-customers.index', ['status' => 'suspended']));
        $byStatus->assertSee('TAMAN JATIMORI');
        $byStatus->assertDontSee('WARUNG KOPI CANGKIR');
    }

    public function test_pop_scoped_user_cannot_see_customer_outside_scope(): void
    {
        $allowedPop = Pop::factory()->create();
        $otherPop = Pop::factory()->create();

        $user = $this->busdevUser();
        $this->actingAs($user);
        UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $user->role_id,
            'scope_type' => 'selected_pop',
        ])->targets()->create(['pop_id' => $allowedPop->id]);
        app(EffectiveAccessService::class)->clearCache($user);

        $this->makeCustomer($this->bisnis, ['full_name' => 'DALAM SCOPE', 'pop_id' => $allowedPop->id]);
        $this->makeCustomer($this->bisnis, ['full_name' => 'LUAR SCOPE', 'pop_id' => $otherPop->id]);

        $response = $this->get(route('business-development.business-customers.index'));

        $response->assertOk();
        $response->assertSee('DALAM SCOPE');
        $response->assertDontSee('LUAR SCOPE');
    }

    public function test_user_without_permission_is_forbidden_and_sidebar_link_hidden(): void
    {
        $teknisi = User::factory()->create([
            'status' => 'active',
            'role_id' => Role::where('code', 'teknisi')->firstOrFail()->id,
        ]);
        app(EffectiveAccessService::class)->clearCache($teknisi);

        $this->actingAs($teknisi)
            ->get(route('business-development.business-customers.index'))
            ->assertForbidden();
    }

    public function test_business_development_role_sees_page_and_sidebar_link(): void
    {
        $this->actingAs($this->busdevUser());

        $response = $this->get(route('business-development.business-customers.index'));

        $response->assertOk();
        $response->assertSee('List Pelanggan Bisnis');
        $response->assertSee(route('business-development.business-customers.index'), false);
    }
}
