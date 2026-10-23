<?php

namespace Tests\Feature;

use App\Enums\RollStatus;
use App\Enums\SerialStatus;
use App\Enums\TrackingType;
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

class WarehouseIssueAvailableStockSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

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

        $this->cabang = Pop::create([
            'code' => 'TEST-CABANG-ISSUE',
            'pop_code' => 'TCI',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'Gudang Cabang Issue Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    #[Test]
    public function available_stock_returns_item_code_and_allows_searching_dc_cable(): void
    {
        $cat = ItemCategory::first();

        // 1. Kabel Dropcore bertipe roll dengan kode 'DC-1C' dan nama 'Kabel Dropcore 1 Core'
        $kabelDc = Item::create([
            'name' => 'Kabel Dropcore 1 Core',
            'code' => 'DC-1C',
            'tracking_type' => TrackingType::ROLL,
            'unit' => 'meter',
            'item_category_id' => $cat->id,
            'is_active' => true,
        ]);
        InventoryRoll::create([
            'item_id' => $kabelDc->id,
            'roll_code' => 'ROLL-DC-001',
            'current_pop_id' => $this->cabang->id,
            'status' => RollStatus::AVAILABLE->value,
            'length_total' => 1000,
            'length_remaining' => 750,
        ]);

        // 2. Splitter bertipe serialized dengan kode 'SPL-1X2' dan nama 'Splitter 1:2'
        $splitter = Item::create([
            'name' => 'Splitter 1:2',
            'code' => 'SPL-1X2',
            'tracking_type' => TrackingType::SERIALIZED,
            'unit' => 'pcs',
            'item_category_id' => $cat->id,
            'is_active' => true,
        ]);
        InventorySerial::create([
            'item_id' => $splitter->id,
            'serial_number' => 'SPL-SN-9988',
            'current_pop_id' => $this->cabang->id,
            'status' => SerialStatus::AVAILABLE->value,
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson(route('warehouse.issues.available-stock', ['pop_id' => $this->cabang->id]));

        $response->assertOk();
        $items = collect($response->json('items'));

        // Cek bahwa item kabel DC memiliki kode 'DC-1C'
        $dcPayload = $items->firstWhere('item_id', $kabelDc->id);
        $this->assertNotNull($dcPayload, 'Kabel DC harus ada dalam stok cabang');
        $this->assertEquals('DC-1C', $dcPayload['code']);
        $this->assertEquals('Kabel Dropcore 1 Core', $dcPayload['name']);
        $this->assertEquals('ROLL-DC-001', $dcPayload['rolls'][0]['roll_code']);

        // Cek bahwa splitter memiliki kode 'SPL-1X2'
        $splPayload = $items->firstWhere('item_id', $splitter->id);
        $this->assertNotNull($splPayload, 'Splitter harus ada dalam stok cabang');
        $this->assertEquals('SPL-1X2', $splPayload['code']);
        $this->assertEquals('Splitter 1:2', $splPayload['name']);
        $this->assertContains('SPL-SN-9988', $splPayload['serials']);
    }

    #[Test]
    public function issue_create_page_contains_enhanced_stock_search_and_dc_sku_support(): void
    {
        $response = $this->actingAs($this->owner)
            ->get(route('warehouse.issues.create'));

        $response->assertOk();
        $response->assertSee('x-model="stockSearchQuery"', false);
        $response->assertSee('filteredAvailableStock', false);
        $response->assertSee('filteredSerials', false);
        $response->assertSee('filteredLots', false);
        $response->assertSee('filteredRolls', false);
        $response->assertSee('Tidak ada stok yang cocok', false);
    }
}
