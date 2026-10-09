<?php

namespace Tests\Feature;

use App\Models\InventoryBalance;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryReceiveService;
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

/**
 * Analisa UI/UX gudang §A2 — "Cari Cepat" di Kelola Stok harus menemukan
 * barang lewat nomor lot, SN, dan nomor roll, bukan cuma nama/kode barang.
 * Sebelumnya user harus pindah ke Traceability hanya untuk mencari SN/roll.
 */
class WarehouseStockSearchTest extends TestCase
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

        $this->pusat = Pop::create(['code' => 'WSS-PUSAT', 'pop_code' => 'WSSP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat Search WS', 'type' => 'pusat', 'status' => 'active']);
        $this->cabang = Pop::create(['code' => 'WSS-A', 'pop_code' => 'WSSA', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Search WS', 'type' => 'cabang', 'status' => 'active']);
    }

    #[Test]
    public function search_bisa_menemukan_barang_lewat_nomor_lot(): void
    {
        $kabel = $this->buatItem('WSS-KABEL', 'Kabel Search', 'quantity', 'meter');
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $kabel, 300, 5000, $this->owner);
        $balance = InventoryBalance::where('pop_id', $this->pusat->id)->where('item_id', $kabel->id)->firstOrFail();

        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['search' => $balance->lot_no]));

        $response->assertOk();
        $this->assertTrue($response->viewData('balances')->contains(fn ($b) => $b->id === $balance->id));
    }

    #[Test]
    public function search_bisa_menemukan_unit_lewat_serial_number(): void
    {
        $modem = $this->buatItem('WSS-MODEM', 'Modem Search', 'serialized', 'pcs');
        app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $modem, ['SNWS00001', 'SNWS00002'], 350000, $this->owner);

        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['search' => 'SNWS00002']));

        $response->assertOk()->assertSee('Modem Search');
        $balances = $response->viewData('balances');
        $this->assertCount(1, $balances);
        $this->assertSame(1, (int) $balances->first()->qty);
    }

    #[Test]
    public function search_bisa_menemukan_roll_lewat_nomor_roll(): void
    {
        $fo = $this->buatItem('WSS-ROLLFO', 'Kabel FO Search', 'roll', 'meter', 1000);
        $roll = app(InventoryReceiveService::class)->receiveRoll($this->pusat, $fo, 1, null, 1500000, $this->owner)[0];

        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['search' => $roll->roll_code]));

        $response->assertOk()->assertSee('Kabel FO Search');
        $balances = $response->viewData('balances');
        $this->assertCount(1, $balances);
        $this->assertSame($fo->id, $balances->first()->item_id);
    }

    #[Test]
    public function search_nomor_tidak_ada_mengembalikan_daftar_kosong(): void
    {
        $modem = $this->buatItem('WSS-MODEM2', 'Modem Kosong', 'serialized', 'pcs');
        app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $modem, ['SNWS90001'], 350000, $this->owner);

        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index', ['search' => 'TIDAK-ADA-XYZ']));

        $response->assertOk();
        $this->assertCount(0, $response->viewData('balances'));
    }

    private function buatItem(string $code, string $name, string $tracking, string $unit, ?int $meterPerRoll = null): Item
    {
        $category = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();

        return Item::create(array_filter([
            'code' => $code,
            'name' => $name,
            'item_category_id' => $category->id,
            'unit' => $unit,
            'tracking_type' => $tracking,
            'meter_per_roll' => $meterPerRoll,
        ], fn ($v) => $v !== null));
    }
}
