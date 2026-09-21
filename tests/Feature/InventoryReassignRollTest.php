<?php

namespace Tests\Feature;

use App\Enums\RollStatus;
use App\Models\InventoryRoll;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryReassignService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\ItemCategorySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WarehouseFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `InventoryReassignService::returnRollToWarehouse()`/`transferRollToTechnician()`
 * — roll balik ke gudang BAWA SISA METERNYA (beda dari SN yang selalu "utuh").
 * Lihat docs/TASKS.md ADHOC kabel-per-roll.
 */
class InventoryReassignRollTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $teknisi;

    private Pop $cabang;

    private InventoryRoll $roll;

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
        $teknisiRole = Role::where('code', 'teknisi')->firstOrFail();
        $this->teknisi = User::factory()->create(['role_id' => $teknisiRole->id]);

        $this->cabang = Pop::create(['code' => 'RSR-CABANG', 'pop_code' => 'RSRC', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Reassign Roll', 'type' => 'cabang', 'status' => 'active']);

        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabel = Item::create(['code' => 'RSR-ROLL', 'name' => 'Kabel FO Reassign Roll', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'roll', 'meter_per_roll' => 300]);

        $this->roll = InventoryRoll::create([
            'item_id' => $kabel->id,
            'roll_code' => 'RSR-ROLL-'.date('Ymd').'-000001',
            'length_total' => 300,
            'length_remaining' => 180,
            'unit_price_snapshot' => 900000,
            'status' => RollStatus::IN_USE,
            'current_technician_id' => $this->teknisi->id,
            'issued_from_pop_id' => $this->cabang->id,
        ]);
    }

    #[Test]
    public function return_bawa_sisa_meter_status_jadi_available(): void
    {
        $txn = app(InventoryReassignService::class)->returnRollToWarehouse($this->roll, $this->cabang, 'resign', $this->owner);

        $this->roll->refresh();
        $this->assertEquals(RollStatus::AVAILABLE, $this->roll->status);
        $this->assertEquals($this->cabang->id, $this->roll->current_pop_id);
        $this->assertNull($this->roll->current_technician_id);
        $this->assertEquals(180, $this->roll->length_remaining, 'sisa meter TETAP 180, bukan direset');
        $this->assertEquals(180, $txn->qty);
    }

    #[Test]
    public function return_roll_yang_sudah_depleted_ditolak(): void
    {
        $this->roll->update(['length_remaining' => 0, 'status' => RollStatus::DEPLETED]);

        $this->expectException(InvalidArgumentException::class);
        app(InventoryReassignService::class)->returnRollToWarehouse($this->roll, $this->cabang, 'resign', $this->owner);
    }

    #[Test]
    public function return_tanpa_alasan_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(InventoryReassignService::class)->returnRollToWarehouse($this->roll, $this->cabang, '', $this->owner);
    }

    #[Test]
    public function alih_custody_ke_teknisi_lain(): void
    {
        $teknisiBaru = User::factory()->create(['role_id' => $this->teknisi->role_id]);

        app(InventoryReassignService::class)->transferRollToTechnician($this->roll, $teknisiBaru, 'rotasi', $this->owner);

        $this->roll->refresh();
        $this->assertEquals($teknisiBaru->id, $this->roll->current_technician_id);
        $this->assertEquals(180, $this->roll->length_remaining, 'sisa meter ikut roll yang sama, gak dipecah');
    }
}
