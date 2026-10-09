<?php

namespace Tests\Feature;

use App\Enums\RollStatus;
use App\Models\InventoryRoll;
use App\Models\InventoryTransaction;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Jalur HTTP `WarehouseReassignController`/`WarehouseAdjustmentController`
 * buat roll kabel — pelengkap `InventoryReassignRollTest`/
 * `InventoryAdjustmentRollTest` yang nge-test Service langsung. Menutup gap
 * eksplisit yang dicatat di docs/TASKS.md ADHOC-74 ("UI Reassign/Adjustment
 * roll belum diwiring").
 */
class WarehouseReassignAdjustmentRollControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

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

        $this->cabang = Pop::create(['code' => 'RAR-CABANG', 'pop_code' => 'RARC', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Reassign Adjust Roll HTTP', 'type' => 'cabang', 'status' => 'active']);

        $teknisiRole = Role::where('code', 'teknisi')->firstOrFail();
        $teknisi = User::factory()->create(['role_id' => $teknisiRole->id]);

        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabel = Item::create(['code' => 'RAR-ROLL', 'name' => 'Kabel FO Reassign Adjust HTTP', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'roll', 'meter_per_roll' => 350]);

        $this->roll = InventoryRoll::create([
            'item_id' => $kabel->id,
            'roll_code' => 'RAR-ROLL-'.date('Ymd').'-000001',
            'length_total' => 350,
            'length_remaining' => 200,
            'unit_price_snapshot' => 1050000,
            'status' => RollStatus::IN_USE,
            'current_technician_id' => $teknisi->id,
            'issued_from_pop_id' => $this->cabang->id,
        ]);
    }

    #[Test]
    public function halaman_reassign_roll_bisa_dibuka(): void
    {
        $this->actingAs($this->owner)->get(route('warehouse.reassign.roll.create', $this->roll))
            ->assertOk()
            ->assertSee($this->roll->roll_code);
    }

    #[Test]
    public function return_roll_lewat_http_bawa_sisa_meter(): void
    {
        $response = $this->actingAs($this->owner)->post(route('warehouse.reassign.roll.store', $this->roll), [
            'action' => 'return',
            'cabang_pop_id' => $this->cabang->id,
            'reason' => 'resign',
        ]);

        $response->assertRedirect(route('warehouse.custody.index'));
        $response->assertSessionHas('success');

        $this->roll->refresh();
        $this->assertEquals(RollStatus::AVAILABLE, $this->roll->status);
        $this->assertEquals(200, $this->roll->length_remaining);
    }

    #[Test]
    public function halaman_adjustment_roll_bisa_dibuka(): void
    {
        $this->actingAs($this->owner)->get(route('warehouse.adjustments.roll.create', $this->roll))
            ->assertOk()
            ->assertSee($this->roll->roll_code);
    }

    #[Test]
    public function tandai_lost_tanpa_bukti_ditolak_validasi(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->owner)->post(route('warehouse.adjustments.roll.store', $this->roll), [
            'new_status' => 'lost',
            'reason' => 'roll_hilang_di_lapangan',
        ]);

        $response->assertSessionHasErrors('evidence');
        $this->roll->refresh();
        $this->assertNotEquals(RollStatus::LOST, $this->roll->status);
    }

    #[Test]
    public function tandai_lost_dengan_bukti_berhasil(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->owner)->post(route('warehouse.adjustments.roll.store', $this->roll), [
            'new_status' => 'lost',
            'reason' => 'roll_hilang_di_lapangan',
            'evidence' => UploadedFile::fake()->image('bap-roll-hilang.jpg'),
        ]);

        $response->assertRedirect(route('warehouse.custody.index'));
        $this->roll->refresh();
        $this->assertEquals(RollStatus::LOST, $this->roll->status);

        $txn = InventoryTransaction::where('roll_id', $this->roll->id)->where('reason', 'roll_hilang_di_lapangan')->firstOrFail();
        $this->assertNotNull($txn->evidence_file_path);
        Storage::disk('public')->assertExists($txn->evidence_file_path);
    }

    #[Test]
    public function teknisi_tanpa_permission_ditolak(): void
    {
        $teknisiRole = Role::where('code', 'teknisi')->firstOrFail();
        $teknisi = User::factory()->create(['role_id' => $teknisiRole->id]);

        $this->actingAs($teknisi)->get(route('warehouse.reassign.roll.create', $this->roll))->assertForbidden();
        $this->actingAs($teknisi)->get(route('warehouse.adjustments.roll.create', $this->roll))->assertForbidden();
    }
}
