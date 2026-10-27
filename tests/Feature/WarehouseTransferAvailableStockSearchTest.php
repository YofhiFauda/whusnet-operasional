<?php

namespace Tests\Feature;

use App\Enums\RollStatus;
use App\Enums\SerialStatus;
use App\Enums\TrackingType;
use App\Models\InventoryBalance;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
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

class WarehouseTransferAvailableStockSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $pusat;

    private Pop $cabang;

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

        $this->pusat = Pop::create([
            'code' => 'TEST-PUSAT-TRF',
            'pop_code' => 'TPT',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'Gudang Pusat Transfer',
            'type' => 'pusat',
            'status' => 'active',
        ]);

        $this->cabang = Pop::create([
            'code' => 'TEST-CABANG-TRF',
            'pop_code' => 'TCB',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'Gudang Cabang Transfer',
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    #[Test]
    public function available_stock_returns_item_code_for_all_tracking_types(): void
    {
        $cat = ItemCategory::first();

        // 1. Serial item
        $modem = Item::create([
            'name' => 'Modem ZTE F609',
            'code' => 'MOD-ZTE-F609',
            'tracking_type' => TrackingType::SERIALIZED,
            'unit' => 'unit',
            'item_category_id' => $cat->id,
            'is_active' => true,
        ]);
        InventorySerial::create([
            'item_id' => $modem->id,
            'serial_number' => 'ZTE-SN-123456',
            'current_pop_id' => $this->pusat->id,
            'status' => SerialStatus::AVAILABLE->value,
        ]);

        // 2. Quantity item
        $patchCord = Item::create([
            'name' => 'Patch Cord SC-UPC 3M',
            'code' => 'ACC-PC-3M',
            'tracking_type' => TrackingType::QUANTITY,
            'unit' => 'pcs',
            'item_category_id' => $cat->id,
            'is_active' => true,
        ]);
        InventoryBalance::create([
            'item_id' => $patchCord->id,
            'pop_id' => $this->pusat->id,
            'lot_no' => 'LOT-2026-001',
            'qty' => 50,
        ]);

        // 3. Roll item
        $kabel = Item::create([
            'name' => 'Kabel Dropcore 1 Core 1000M',
            'code' => 'CAB-DC-1C',
            'tracking_type' => TrackingType::ROLL,
            'unit' => 'meter',
            'item_category_id' => $cat->id,
            'is_active' => true,
        ]);
        InventoryRoll::create([
            'item_id' => $kabel->id,
            'roll_code' => 'ROLL-KBL-099',
            'current_pop_id' => $this->pusat->id,
            'status' => RollStatus::AVAILABLE->value,
            'length_total' => 1000,
            'length_remaining' => 850,
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson(route('warehouse.transfers.available-stock', ['pop_id' => $this->pusat->id]));

        $response->assertOk();
        $items = collect($response->json('items'));

        $this->assertCount(3, $items);

        $serialPayload = $items->firstWhere('item_id', $modem->id);
        $this->assertNotNull($serialPayload);
        $this->assertEquals('MOD-ZTE-F609', $serialPayload['code']);
        $this->assertEquals('Modem ZTE F609', $serialPayload['name']);
        $this->assertContains('ZTE-SN-123456', $serialPayload['serials']);

        $qtyPayload = $items->firstWhere('item_id', $patchCord->id);
        $this->assertNotNull($qtyPayload);
        $this->assertEquals('ACC-PC-3M', $qtyPayload['code']);
        $this->assertEquals('Patch Cord SC-UPC 3M', $qtyPayload['name']);
        $this->assertEquals('LOT-2026-001', $qtyPayload['lots'][0]['lot_no']);

        $rollPayload = $items->firstWhere('item_id', $kabel->id);
        $this->assertNotNull($rollPayload);
        $this->assertEquals('CAB-DC-1C', $rollPayload['code']);
        $this->assertEquals('Kabel Dropcore 1 Core 1000M', $rollPayload['name']);
        $this->assertEquals('ROLL-KBL-099', $rollPayload['rolls'][0]['roll_code']);
    }

    #[Test]
    public function transfer_create_page_contains_enhanced_stock_search(): void
    {
        $response = $this->actingAs($this->owner)
            ->get(route('warehouse.transfers.create'));

        $response->assertOk();
        $response->assertSee('x-model="stockSearchQuery"', false);
        $response->assertSee('filteredAvailableStock', false);
        $response->assertSee('filteredSerials', false);
        $response->assertSee('filteredLots', false);
        $response->assertSee('filteredRolls', false);
        $response->assertSee('Tidak ada stok yang cocok', false);
    }
}
