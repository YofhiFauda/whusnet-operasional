<?php

namespace Tests\Feature;

use App\Enums\InventoryTransactionType;
use App\Enums\SerialStatus;
use App\Models\InventoryBalance;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\ItemCategorySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WarehouseFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WarehouseDashboardPageTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $pusat;

    private Pop $cabangSiman;

    private Item $ontItem;

    private Item $dropcoreItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(WarehouseFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(ItemCategorySeeder::class);

        $ownerRole = Role::where('code', 'owner')->firstOrFail();
        $this->owner = User::factory()->create(['role_id' => $ownerRole->id]);

        $this->pusat = Pop::create(['code' => 'WS-PUSAT', 'pop_code' => 'WSP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Gudang Pusat Ponorogo', 'type' => 'pusat', 'status' => 'active']);
        $this->cabangSiman = Pop::create(['code' => 'WS-SIMAN', 'pop_code' => 'WSS', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Gudang Cabang Siman', 'type' => 'cabang', 'status' => 'active']);

        UserRoleScope::create([
            'user_id' => $this->owner->id,
            'role_id' => $ownerRole->id,
            'scope_type' => 'all_pop',
        ]);

        $cpeCategory = ItemCategory::where('code', 'CPE')->first() ?? ItemCategory::create([
            'code' => 'CPE',
            'name' => 'Customer Premises Equipment',
        ]);

        $cableCategory = ItemCategory::where('code', 'KBL')->first() ?? ItemCategory::create([
            'code' => 'KBL',
            'name' => 'Kabel Fiber & Dropcore',
        ]);

        $this->ontItem = Item::create([
            'item_category_id' => $cpeCategory->id,
            'code' => 'ONT-HW-01',
            'name' => 'ONT Huawei HG8245H5',
            'unit' => 'Unit',
            'tracking_type' => 'serialized',
            'is_active' => true,
        ]);

        $this->dropcoreItem = Item::create([
            'item_category_id' => $cableCategory->id,
            'code' => 'DC-1C-1000',
            'name' => 'Dropcore 1 Core 1000M',
            'unit' => 'Meter',
            'tracking_type' => 'quantity',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function owner_can_render_warehouse_dashboard_successfully(): void
    {
        // Setup some balances
        InventoryBalance::create([
            'pop_id' => $this->pusat->id,
            'item_id' => $this->ontItem->id,
            'qty' => 50,
            'minimum_stock' => 10,
        ]);

        InventoryBalance::create([
            'pop_id' => $this->cabangSiman->id,
            'item_id' => $this->dropcoreItem->id,
            'lot_no' => 'LOT-2026-001',
            'qty' => 150,
            'minimum_stock' => 500, // Low stock condition
        ]);

        // Setup available serial
        InventorySerial::create([
            'item_id' => $this->ontItem->id,
            'current_pop_id' => $this->pusat->id,
            'serial_number' => 'HWTC12345678',
            'status' => SerialStatus::AVAILABLE->value,
        ]);

        // Setup ledger transaction
        InventoryTransaction::create([
            'type' => InventoryTransactionType::RECEIVE,
            'reference_number' => 'DO-88912',
            'item_id' => $this->ontItem->id,
            'qty' => 50,
            'to_pop_id' => $this->pusat->id,
            'created_by' => $this->owner->id,
        ]);

        $response = $this->actingAs($this->owner)->get(route('warehouse.index'));

        $response->assertStatus(200);
        $response->assertSee('Dasbor &amp; Riwayat Gudang', false);
        $response->assertSee('Stok Kritis', false);
        $response->assertSee('Permintaan Stok Pending', false);
        $response->assertSee('ONT / Router Siap Pasang', false);
        $response->assertSee('Custody Teknisi', false);
        $response->assertSee('Karantina', false);
        $response->assertSee('Arus Barang Hari Ini', false);
        $response->assertSee('Peringatan Stok Rendah', false);
        $response->assertSee('Aktivitas &amp; Mutasi per Gudang (POP)', false);
        $response->assertSee('Barang Masuk', false);
        $response->assertSee('Transfer ke Cabang', false);
        $response->assertSee('Diserahkan ke Teknisi', false);
        $response->assertSee('Gudang Pusat Ponorogo');
        $response->assertSee('Gudang Cabang Siman');
        $response->assertSee('ONT Huawei HG8245H5');
        $response->assertSee('DO-88912');
    }

    #[Test]
    public function dashboard_can_filter_by_pop_id(): void
    {
        InventoryBalance::create([
            'pop_id' => $this->pusat->id,
            'item_id' => $this->ontItem->id,
            'qty' => 50,
            'minimum_stock' => 10,
        ]);

        InventoryBalance::create([
            'pop_id' => $this->cabangSiman->id,
            'item_id' => $this->dropcoreItem->id,
            'qty' => 150,
            'minimum_stock' => 500,
        ]);

        // Filter for Cabang Siman only
        $response = $this->actingAs($this->owner)->get(route('warehouse.index', ['pop_id' => $this->cabangSiman->id]));

        $response->assertStatus(200);
        $response->assertSee('Dropcore 1 Core 1000M');
    }

    #[Test]
    public function user_without_permission_cannot_access_warehouse_dashboard(): void
    {
        $guestRole = Role::where('code', 'teknisi')->firstOrFail();
        $technician = User::factory()->create(['role_id' => $guestRole->id]);

        $response = $this->actingAs($technician)->get(route('warehouse.index'));

        $response->assertStatus(403);
    }
}
