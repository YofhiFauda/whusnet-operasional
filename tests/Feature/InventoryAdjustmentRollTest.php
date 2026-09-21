<?php

namespace Tests\Feature;

use App\Enums\RollStatus;
use App\Models\InventoryRoll;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryAdjustmentService;
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
 * `InventoryAdjustmentService::adjustRollStatus()` — Lapor Rusak/Hilang/Scrap
 * roll kabel. Guard evidence WAJIB buat LOST/DAMAGED/SCRAPPED (kontrol-
 * anti-manipulasi.md §2) TETAP berlaku, sama seperti `adjustSerialStatus()`.
 */
class InventoryAdjustmentRollTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

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

        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabel = Item::create(['code' => 'ADJ-ROLL', 'name' => 'Kabel FO Adjustment Roll', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'roll', 'meter_per_roll' => 250]);

        $this->roll = InventoryRoll::create([
            'item_id' => $kabel->id,
            'roll_code' => 'ADJ-ROLL-'.date('Ymd').'-000001',
            'length_total' => 250,
            'length_remaining' => 250,
            'status' => RollStatus::AVAILABLE,
        ]);
    }

    #[Test]
    public function lapor_hilang_tanpa_bukti_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(InventoryAdjustmentService::class)->adjustRollStatus($this->roll, RollStatus::LOST, 'lost', $this->owner);
    }

    #[Test]
    public function lapor_hilang_dengan_bukti_sukses(): void
    {
        $txn = app(InventoryAdjustmentService::class)->adjustRollStatus($this->roll, RollStatus::LOST, 'lost', $this->owner, null, 'evidence/roll-lost.jpg');

        $this->roll->refresh();
        $this->assertEquals(RollStatus::LOST, $this->roll->status);
        $this->assertEquals('evidence/roll-lost.jpg', $txn->evidence_file_path);
    }

    #[Test]
    public function quarantine_tanpa_bukti_tetap_boleh(): void
    {
        app(InventoryAdjustmentService::class)->adjustRollStatus($this->roll, RollStatus::QUARANTINE, 'dicek ulang kondisi fisik', $this->owner);

        $this->roll->refresh();
        $this->assertEquals(RollStatus::QUARANTINE, $this->roll->status);
    }

    #[Test]
    public function roll_yang_sudah_scrapped_gak_bisa_diubah_lagi(): void
    {
        $this->roll->update(['status' => RollStatus::SCRAPPED]);

        $this->expectException(InvalidArgumentException::class);
        app(InventoryAdjustmentService::class)->adjustRollStatus($this->roll, RollStatus::LOST, 'lost', $this->owner, null, 'evidence/x.jpg');
    }
}
