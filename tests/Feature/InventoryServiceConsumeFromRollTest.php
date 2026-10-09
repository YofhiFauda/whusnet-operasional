<?php

namespace Tests\Feature;

use App\Enums\RollStatus;
use App\Models\FopTask;
use App\Models\InventoryRoll;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\TaskMaterial;
use App\Models\User;
use App\Services\InventoryService;
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
 * `InventoryService::consumeFromRoll()` — potong sebagian meter dari roll
 * kabel di custody teknisi. Partial (roll TETAP ISSUED/IN_USE sampai habis),
 * beda dari `installSerial()` yang atomik. Lihat docs/TASKS.md ADHOC
 * kabel-per-roll.
 */
class InventoryServiceConsumeFromRollTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $teknisi;

    private Pop $cabang;

    private InventoryRoll $roll;

    private FopTask $fopTask;

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

        $this->cabang = Pop::create(['code' => 'CFR-CABANG', 'pop_code' => 'CFRC', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Consume Roll', 'type' => 'cabang', 'status' => 'active']);

        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabel = Item::create(['code' => 'CFR-ROLL', 'name' => 'Kabel FO Consume Roll', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'roll', 'meter_per_roll' => 500]);

        $this->roll = InventoryRoll::create([
            'item_id' => $kabel->id,
            'roll_code' => 'CFR-ROLL-'.date('Ymd').'-000001',
            'length_total' => 500,
            'length_remaining' => 500,
            'unit_price_snapshot' => 1500000,
            'status' => RollStatus::ISSUED,
            'current_technician_id' => $this->teknisi->id,
            'issued_from_pop_id' => $this->cabang->id,
        ]);

        $this->fopTask = FopTask::create(['task_number' => 'TFOP-2026-9001', 'category' => 'MTN', 'tugas' => 'Uji Konsumsi Roll']);
    }

    #[Test]
    public function potong_sebagian_meter_status_jadi_in_use(): void
    {
        $material = app(InventoryService::class)->consumeFromRoll(
            $this->roll, 120, [$this->teknisi], $this->fopTask, null, $this->owner
        );

        $this->roll->refresh();
        $this->assertEquals(380, $this->roll->length_remaining);
        $this->assertEquals(RollStatus::IN_USE, $this->roll->status);
        $this->assertEquals('meter', $material->unit);
        $this->assertEquals($this->roll->roll_code, $material->lot_no);
        $this->assertEquals(120, $material->qty);
        $this->assertEquals(1500000, $material->unit_price_snapshot);
    }

    #[Test]
    public function potong_sampai_habis_status_jadi_depleted(): void
    {
        app(InventoryService::class)->consumeFromRoll($this->roll, 500, [$this->teknisi], $this->fopTask, null, $this->owner);

        $this->roll->refresh();
        $this->assertEquals(0, $this->roll->length_remaining);
        $this->assertEquals(RollStatus::DEPLETED, $this->roll->status);
    }

    #[Test]
    public function minta_melebihi_sisa_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(InventoryService::class)->consumeFromRoll($this->roll, 600, [$this->teknisi], $this->fopTask, null, $this->owner);
    }

    #[Test]
    public function teknisi_di_luar_custody_ditolak(): void
    {
        $teknisiRole = Role::where('code', 'teknisi')->firstOrFail();
        $teknisiLain = User::factory()->create(['role_id' => $teknisiRole->id]);

        $this->expectException(InvalidArgumentException::class);

        app(InventoryService::class)->consumeFromRoll($this->roll, 50, [$teknisiLain], $this->fopTask, null, $this->owner);

        $this->assertEquals(0, TaskMaterial::count());
    }
}
