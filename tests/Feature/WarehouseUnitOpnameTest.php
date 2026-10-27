<?php

namespace Tests\Feature;

use App\Enums\InventoryTransactionType;
use App\Enums\SerialStatus;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryAdjustmentService;
use App\Services\InventoryReceiveService;
use App\Services\WarehouseStockAsOfService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\ItemCategorySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WarehouseFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WarehouseUnitOpnameTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $pusat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FeatureSeeder::class, ActionSeeder::class, RoleSeeder::class, PermissionSeeder::class, WarehouseFeatureSeeder::class, RolePermissionSeeder::class, ItemCategorySeeder::class]);
        $this->owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $this->pusat = Pop::create(['code' => 'UOP-PUSAT', 'pop_code' => 'UOP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat Opname Unit', 'type' => 'pusat', 'status' => 'active']);
    }

    #[Test]
    public function opname_sn_terhitung_pas_dan_hilang_masing_masing_memiliki_jejak(): void
    {
        $item = Item::create(['code' => 'UOP-MODEM', 'name' => 'Modem Opname', 'item_category_id' => ItemCategory::where('code', 'modem_ont')->firstOrFail()->id, 'unit' => 'unit', 'tracking_type' => 'serialized']);
        [$found, $missing] = app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $item, ['UOP-SN-1', 'UOP-SN-2'], 200000, $this->owner);
        $service = app(InventoryAdjustmentService::class);

        $foundTxn = $service->recordSerialStockOpname($found, $this->pusat, true, $this->owner, 'Fisik cocok', 'evidence/opname.jpg');
        $missingTxn = $service->recordSerialStockOpname($missing, $this->pusat, false, $this->owner, 'Tidak ditemukan', 'evidence/opname.jpg');

        $this->assertSame(InventoryTransactionType::STOCK_OPNAME, $foundTxn->type);
        $this->assertSame($this->pusat->id, $foundTxn->to_pop_id);
        $this->assertSame(InventoryTransactionType::STOCK_OPNAME, $missingTxn->type);
        $this->assertNull($missingTxn->to_pop_id);
        $this->assertSame(SerialStatus::AVAILABLE, $found->fresh()->status);
        $this->assertSame(SerialStatus::LOST, $missing->fresh()->status);
        $this->assertEquals(1, app(WarehouseStockAsOfService::class)->balanceAsOf($this->pusat, $item, now()->addDay())['lama']['qty']);
    }

    #[Test]
    public function opname_roll_selisih_dua_puluh_meter_mengurangi_saldo_dan_mencatat_rugi_sesuai_harga_roll(): void
    {
        $item = Item::create(['code' => 'UOP-ROLL', 'name' => 'Roll Opname', 'item_category_id' => ItemCategory::where('code', 'kabel_dropcore')->firstOrFail()->id, 'unit' => 'meter', 'tracking_type' => 'roll', 'meter_per_roll' => 1000]);
        [$roll] = app(InventoryReceiveService::class)->receiveRoll($this->pusat, $item, 1, null, 770000, $this->owner);

        $txn = app(InventoryAdjustmentService::class)->recordRollStockOpname(
            $roll, $this->pusat, 980, $this->owner, 'Sisa fisik 980 meter', 'evidence/opname-roll.jpg'
        );

        $this->assertSame(InventoryTransactionType::STOCK_OPNAME, $txn->type);
        $this->assertEquals(980, (float) $roll->fresh()->length_remaining);
        $this->assertEquals(980 * 770, app(WarehouseStockAsOfService::class)->balanceAsOf($this->pusat, $item, now()->addDay())['nilai']);
        $report = $this->actingAs($this->owner)->get(route('warehouse.reports.index', ['pop_id' => $this->pusat->id]));
        $report->assertOk();
        $loss = collect($report->viewData('adjustmentRows'))->firstWhere('reason', 'lost');
        $this->assertNotNull($loss);
        $this->assertEquals(15400, $loss['loss_value']);
    }

    #[Test]
    public function petugas_dapat_mencatat_opname_sn_dari_form_gudang(): void
    {
        $item = Item::create(['code' => 'UOP-MODEM-UI', 'name' => 'Modem Opname UI', 'item_category_id' => ItemCategory::where('code', 'modem_ont')->firstOrFail()->id, 'unit' => 'unit', 'tracking_type' => 'serialized']);
        [$serial] = app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $item, ['UOP-SN-UI'], 200000, $this->owner);
        Storage::fake('public');

        $this->actingAs($this->owner)->get(route('warehouse.adjustments.unit-opname.create'))
            ->assertOk()->assertSee('Opname SN / Roll');
        $this->post(route('warehouse.adjustments.unit-opname.store'), [
            'pop_id' => $this->pusat->id,
            'tracking_type' => 'serialized',
            'code' => $serial->serial_number,
            'is_present' => '1',
            'reason' => 'Fisik sesuai',
            'evidence' => UploadedFile::fake()->image('bukti-opname.jpg'),
        ])->assertRedirect(route('warehouse.stock.index'));

        $this->assertDatabaseHas('inventory_transactions', [
            'serial_id' => $serial->id,
            'type' => InventoryTransactionType::STOCK_OPNAME->value,
        ]);
    }
}
