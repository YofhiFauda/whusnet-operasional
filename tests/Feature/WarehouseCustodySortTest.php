<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryIssueService;
use App\Services\InventoryReceiveService;
use App\Services\InventoryTransferService;
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
 * Analisa UI/UX gudang §A5 (sisa Fase 2 — sort Custody, sebelumnya ditunda
 * karena 4 tab terpisah + sudah dipaginasi). Param sort TERPISAH per tab
 * (serial_sort/material_sort/roll_sort/return_sort) biar klik urut di satu
 * tab tidak mengganggu urutan tab lain.
 */
class WarehouseCustodySortTest extends TestCase
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

        $this->pusat = Pop::create(['code' => 'CST-PUSAT', 'pop_code' => 'CSTP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat Sort Custody', 'type' => 'pusat', 'status' => 'active']);
        $this->cabang = Pop::create(['code' => 'CST-A', 'pop_code' => 'CSTA', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Sort Custody', 'type' => 'cabang', 'status' => 'active']);
    }

    #[Test]
    public function sort_serial_by_teknisi_mengurutkan_nama_teknisi(): void
    {
        $teknisiRole = Role::where('name', 'Teknisi')->firstOrFail();
        $zaki = User::factory()->create(['role_id' => $teknisiRole->id, 'name' => 'Zaki Sort']);
        $andi = User::factory()->create(['role_id' => $teknisiRole->id, 'name' => 'Andi Sort']);

        $category = ItemCategory::where('code', 'media_converter')->firstOrFail();
        $modem = Item::create(['code' => 'CST-MODEM', 'name' => 'Modem Sort Custody', 'item_category_id' => $category->id, 'unit' => 'pcs', 'tracking_type' => 'serialized', 'ownership_mode' => 'installable']);
        app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $modem, ['CST-SN-1', 'CST-SN-2'], 300000, $this->owner);
        $t = app(InventoryTransferService::class)->createTransfer($this->pusat, $this->cabang, [['item_id' => $modem->id, 'serial_numbers' => ['CST-SN-1', 'CST-SN-2']]], $this->owner);
        app(InventoryTransferService::class)->receiveTransfer($t, ['CST-SN-1', 'CST-SN-2'], [], $this->owner);
        app(InventoryIssueService::class)->issue($this->cabang, $zaki, [['item_id' => $modem->id, 'serial_numbers' => ['CST-SN-1']]], $this->owner);
        app(InventoryIssueService::class)->issue($this->cabang, $andi, [['item_id' => $modem->id, 'serial_numbers' => ['CST-SN-2']]], $this->owner);

        $asc = $this->actingAs($this->owner)->get(route('warehouse.custody.index', ['serial_sort' => 'teknisi', 'serial_dir' => 'asc']));
        $asc->assertOk()->assertSee('aria-sort="ascending"', false);
        $this->assertSame('Andi Sort', $asc->viewData('serials')->first()->currentTechnician->name);

        $desc = $this->actingAs($this->owner)->get(route('warehouse.custody.index', ['serial_sort' => 'teknisi', 'serial_dir' => 'desc']));
        $this->assertSame('Zaki Sort', $desc->viewData('serials')->first()->currentTechnician->name);
    }

    #[Test]
    public function sort_material_by_qty_desc_menaruh_sisa_terbanyak_di_atas(): void
    {
        $teknisiRole = Role::where('name', 'Teknisi')->firstOrFail();
        $teknisi = User::factory()->create(['role_id' => $teknisiRole->id]);

        $category = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kecil = Item::create(['code' => 'CST-KECIL', 'name' => 'Connector Sort Kecil', 'item_category_id' => $category->id, 'unit' => 'pcs', 'tracking_type' => 'quantity']);
        $besar = Item::create(['code' => 'CST-BESAR', 'name' => 'Connector Sort Besar', 'item_category_id' => $category->id, 'unit' => 'pcs', 'tracking_type' => 'quantity']);

        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $kecil, 100, 1000, $this->owner);
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $besar, 100, 1000, $this->owner);
        $t1 = app(InventoryTransferService::class)->createTransfer($this->pusat, $this->cabang, [['item_id' => $kecil->id, 'qty' => 50]], $this->owner);
        app(InventoryTransferService::class)->receiveTransfer($t1, [], [$kecil->id => 50], $this->owner);
        $t2 = app(InventoryTransferService::class)->createTransfer($this->pusat, $this->cabang, [['item_id' => $besar->id, 'qty' => 50]], $this->owner);
        app(InventoryTransferService::class)->receiveTransfer($t2, [], [$besar->id => 50], $this->owner);

        app(InventoryIssueService::class)->issue($this->cabang, $teknisi, [['item_id' => $kecil->id, 'qty' => 5]], $this->owner);
        app(InventoryIssueService::class)->issue($this->cabang, $teknisi, [['item_id' => $besar->id, 'qty' => 20]], $this->owner);

        $response = $this->actingAs($this->owner)->get(route('warehouse.custody.index', ['material_sort' => 'qty', 'material_dir' => 'desc']));

        $response->assertOk();
        $this->assertSame(20.0, (float) $response->viewData('custodies')->first()->qty_remaining);
    }

    #[Test]
    public function sort_roll_by_remaining_asc_menaruh_sisa_tersedikit_di_atas(): void
    {
        $teknisiRole = Role::where('name', 'Teknisi')->firstOrFail();
        $teknisi = User::factory()->create(['role_id' => $teknisiRole->id]);

        $category = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $fo = Item::create(['code' => 'CST-ROLLFO', 'name' => 'Kabel FO Sort Custody', 'item_category_id' => $category->id, 'unit' => 'meter', 'tracking_type' => 'roll', 'meter_per_roll' => 1000]);

        $rolls = app(InventoryReceiveService::class)->receiveRoll($this->pusat, $fo, 2, null, 1500000, $this->owner);
        $t = app(InventoryTransferService::class)->createTransfer($this->pusat, $this->cabang, [['item_id' => $fo->id, 'roll_codes' => [$rolls[0]->roll_code, $rolls[1]->roll_code]]], $this->owner);
        app(InventoryTransferService::class)->receiveTransfer($t, [], [], $this->owner, [$rolls[0]->roll_code, $rolls[1]->roll_code]);
        app(InventoryIssueService::class)->issue($this->cabang, $teknisi, [
            ['item_id' => $fo->id, 'roll_codes' => [$rolls[0]->roll_code, $rolls[1]->roll_code]],
        ], $this->owner);

        $response = $this->actingAs($this->owner)->get(route('warehouse.custody.index', ['roll_sort' => 'remaining', 'roll_dir' => 'asc']));

        $response->assertOk();
        $rollsResult = $response->viewData('rolls');
        $this->assertCount(2, $rollsResult);
        $this->assertTrue($rollsResult->first()->length_remaining <= $rollsResult->last()->length_remaining);
    }

    #[Test]
    public function parameter_sort_custody_asing_diabaikan_bukan_error(): void
    {
        $response = $this->actingAs($this->owner)->get(route('warehouse.custody.index', [
            'serial_sort' => 'DROP TABLE', 'material_sort' => 'xx', 'roll_sort' => 'xx', 'return_sort' => 'xx',
        ]));

        $response->assertOk();
    }
}
