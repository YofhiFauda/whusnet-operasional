<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\InventorySerial;
use App\Models\InventorySerialSale;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WarehouseFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Laporan Barang Terjual ke Pelanggan (Ownership Modem Sewa/Beli, Task 9,
 * .superpowers/sdd/2026-10-09-ownership-modem-sewa-beli/). Fokus test ini
 * SATU hal: data `InventorySerialSale` wajib terpotong POP scope lewat
 * `customer->pop_id` (sale sendiri gak punya kolom pop_id) — sama seperti
 * "Setiap query pelanggan/task/invoice/laporan wajib lewat POP scope" di
 * CLAUDE.md. Pola setup identik `WarehouseReportMovementSummaryTest`.
 */
class WarehouseSoldToCustomerReportScopedByPopTest extends TestCase
{
    use RefreshDatabase;

    private Pop $popA;

    private Pop $popB;

    private Item $modem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(WarehouseFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->popA = Pop::create(['code' => 'SOLD-A', 'pop_code' => 'SOLA', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang A Sold Test', 'type' => 'cabang', 'status' => 'active']);
        $this->popB = Pop::create(['code' => 'SOLD-B', 'pop_code' => 'SOLB', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang B Sold Test', 'type' => 'cabang', 'status' => 'active']);

        $category = ItemCategory::create(['code' => 'modem_ont_sold_test', 'name' => 'Modem/ONT Sold Test', 'default_unit' => 'pcs']);
        $this->modem = Item::create([
            'code' => 'ONT-SOLD-TEST', 'name' => 'Modem ONT Sold Test', 'unit' => 'pcs',
            'item_category_id' => $category->id, 'tracking_type' => 'serialized', 'ownership_mode' => 'installable',
        ]);
    }

    private function createCustomerInPop(Pop $pop, string $code, string $name): Customer
    {
        return Customer::create([
            'customer_code' => $code,
            'full_name' => $name,
            'primary_phone' => '0812340005',
            'status' => 'active',
            'pop_id' => $pop->id,
            'data_completeness_status' => 'siap_billing',
            'registration_date' => now(),
        ]);
    }

    private function createSale(Customer $customer, string $serialNumber, User $actor): InventorySerialSale
    {
        $serial = InventorySerial::create([
            'item_id' => $this->modem->id,
            'serial_number' => $serialNumber,
            'status' => 'installed',
            'ownership' => 'customer',
        ]);

        return InventorySerialSale::create([
            'inventory_serial_id' => $serial->id,
            'customer_id' => $customer->id,
            'fop_task_id' => null,
            'price' => 350000,
            'created_by' => $actor->id,
        ]);
    }

    #[Test]
    public function laporan_sold_to_customer_terpotong_pop_scope(): void
    {
        $actor = User::factory()->create();

        $customerA = $this->createCustomerInPop($this->popA, 'SOLD-A-001', 'Pelanggan Cabang A');
        $customerB = $this->createCustomerInPop($this->popB, 'SOLD-B-001', 'Pelanggan Cabang B');

        $saleDiPopA = $this->createSale($customerA, 'SN-SOLD-A-001', $actor);
        $this->createSale($customerB, 'SN-SOLD-B-001', $actor);

        $popAdminRole = Role::where('code', 'pop_admin')->firstOrFail();
        $user = User::factory()->create(['role_id' => $popAdminRole->id]);
        $scope = UserRoleScope::create(['user_id' => $user->id, 'role_id' => $popAdminRole->id, 'scope_type' => 'selected_pop']);
        $scope->targets()->create(['pop_id' => $this->popA->id]);

        $response = $this->actingAs($user)->get(route('warehouse.reports.sold-to-customer'));

        $response->assertOk();
        $response->assertSee($saleDiPopA->customer->full_name);
        $response->assertDontSee('Pelanggan Cabang B');
    }

    #[Test]
    public function tanpa_permission_sold_to_customer_ditolak(): void
    {
        $teknisiRole = Role::where('code', 'teknisi')->firstOrFail();
        $userTanpaPermission = User::factory()->create(['role_id' => $teknisiRole->id]);

        $response = $this->actingAs($userTanpaPermission)->get(route('warehouse.reports.sold-to-customer'));

        $response->assertForbidden();
    }
}
