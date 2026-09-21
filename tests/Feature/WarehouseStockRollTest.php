<?php

namespace Tests\Feature;

use App\Enums\RollStatus;
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
 * Bug nyata 2026-09-16 (laporan user): kabel yang udah diterima ke Gudang
 * Pusat (Barang Masuk) GAK muncul di Kelola Stok sama sekali. Root cause:
 * `InventoryReceiveService::receiveRoll()` cuma nulis ke `inventory_rolls`,
 * gak pernah nyentuh `inventory_balances` — `WarehouseStockController` cuma
 * baca `inventory_balances` (+ agregat sintetis buat SERIALIZED, tapi ROLL
 * kelewat). Sama kelas bug yang dulu kejadian ke SERIALIZED (2026-09-07).
 */
class WarehouseStockRollTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $pusat;

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

        $this->pusat = Pop::create(['code' => 'WSR-PUSAT', 'pop_code' => 'WSRP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat Kelola Stok Roll Test', 'type' => 'pusat', 'status' => 'active']);
    }

    #[Test]
    public function roll_yang_baru_diterima_muncul_di_kelola_stok_dalam_meter(): void
    {
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabel = Item::create(['code' => 'WSR-ROLL', 'name' => 'Kabel FO Kelola Stok Test', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'roll', 'meter_per_roll' => 1000]);

        app(InventoryReceiveService::class)->receiveRoll($this->pusat, $kabel, 3, 'PT Vendor Stok', 2500000, $this->owner);

        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index'));

        $response->assertOk()
            ->assertSee('Kabel FO Kelola Stok Test')
            ->assertSee('ROLL KABEL')
            ->assertSee('3.000') // 3 roll x 1000 meter = 3000 meter sisa total
            ->assertSee('meter');
    }

    #[Test]
    public function roll_yang_sudah_diissue_ke_teknisi_tidak_ikut_dihitung_stok_gudang(): void
    {
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabel = Item::create(['code' => 'WSR-ROLL-2', 'name' => 'Kabel FO Kelola Stok Issued', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'roll', 'meter_per_roll' => 500]);
        $roll = app(InventoryReceiveService::class)->receiveRoll($this->pusat, $kabel, 1, null, 1500000, $this->owner)[0];

        $teknisi = User::factory()->create();
        $roll->update(['status' => RollStatus::ISSUED, 'current_pop_id' => null, 'current_technician_id' => $teknisi->id, 'issued_from_pop_id' => $this->pusat->id]);

        $response = $this->actingAs($this->owner)->get(route('warehouse.stock.index'));

        $response->assertOk()->assertDontSee('Kabel FO Kelola Stok Issued');
    }

    #[Test]
    public function endpoint_ajax_daftar_roll_mengembalikan_roll_code_dan_sisa_meter(): void
    {
        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabel = Item::create(['code' => 'WSR-ROLL-3', 'name' => 'Kabel FO AJAX Test', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'roll', 'meter_per_roll' => 750]);
        $roll = app(InventoryReceiveService::class)->receiveRoll($this->pusat, $kabel, 1, null, 2000000, $this->owner)[0];

        $response = $this->actingAs($this->owner)->getJson(route('warehouse.stock.rolls', ['pop_id' => $this->pusat->id, 'item_id' => $kabel->id]));

        $response->assertOk()->assertJson([
            'rolls' => [
                ['roll_code' => $roll->roll_code, 'length_remaining' => 750.0],
            ],
        ]);
    }
}
