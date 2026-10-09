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
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Issue roll kabel (App\Enums\TrackingType::ROLL) — pick roll UTUH yang
 * AVAILABLE di cabang, transisi ke ISSUED + custody teknisi. Sejalan
 * `WarehouseTransferAndIssueTest::issue_full_flow_dari_cabang_ke_teknisi`
 * tapi buat roll. Lihat docs/TASKS.md ADHOC kabel-per-roll.
 */
class InventoryIssueRollTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $cabang;

    private Item $kabel;

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

        $this->cabang = Pop::create(['code' => 'ISR-CABANG', 'pop_code' => 'ISRC', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Issue Roll', 'type' => 'cabang', 'status' => 'active']);

        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $this->kabel = Item::create(['code' => 'ISR-ROLL', 'name' => 'Kabel FO Issue Roll', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'roll', 'meter_per_roll' => 600]);

        // Transfer roll (Pusat→Cabang) belum dibangun (fase 7) — roll
        // dibuat langsung AVAILABLE di cabang buat isolasi test Issue.
        $this->roll = InventoryRoll::create([
            'item_id' => $this->kabel->id,
            'roll_code' => 'ISR-ROLL-'.date('Ymd').'-000001',
            'vendor' => 'PT Vendor Issue Roll',
            'length_total' => 600,
            'length_remaining' => 600,
            'unit_price_snapshot' => 1800000,
            'status' => RollStatus::AVAILABLE,
            'current_pop_id' => $this->cabang->id,
        ]);
    }

    #[Test]
    public function issue_roll_transisi_ke_issued_dan_custody_teknisi(): void
    {
        $teknisiRole = Role::where('name', 'Teknisi')->firstOrFail();
        $teknisi = User::factory()->create(['role_id' => $teknisiRole->id]);

        $store = $this->actingAs($this->owner)->post(route('warehouse.issues.store'), [
            'cabang_pop_id' => $this->cabang->id,
            'technician_id' => $teknisi->id,
            'lines' => [
                ['item_id' => $this->kabel->id, 'roll_codes' => $this->roll->roll_code],
            ],
        ]);

        $txn = InventoryTransaction::where('type', 'issue')->whereNotNull('roll_id')->firstOrFail();
        $store->assertRedirect(route('warehouse.issues.show', $txn->reference_number));
        $this->assertEquals(600, $txn->qty);

        $this->roll->refresh();
        $this->assertEquals(RollStatus::ISSUED, $this->roll->status);
        $this->assertEquals($teknisi->id, $this->roll->current_technician_id);
        $this->assertNull($this->roll->current_pop_id);
        $this->assertEquals($this->cabang->id, $this->roll->issued_from_pop_id);
    }

    #[Test]
    public function roll_yang_sudah_diissue_gak_bisa_diissue_lagi(): void
    {
        $teknisiRole = Role::where('name', 'Teknisi')->firstOrFail();
        $teknisiA = User::factory()->create(['role_id' => $teknisiRole->id]);
        $teknisiB = User::factory()->create(['role_id' => $teknisiRole->id]);

        $this->actingAs($this->owner)->post(route('warehouse.issues.store'), [
            'cabang_pop_id' => $this->cabang->id,
            'technician_id' => $teknisiA->id,
            'lines' => [['item_id' => $this->kabel->id, 'roll_codes' => $this->roll->roll_code]],
        ]);

        $second = $this->actingAs($this->owner)->post(route('warehouse.issues.store'), [
            'cabang_pop_id' => $this->cabang->id,
            'technician_id' => $teknisiB->id,
            'lines' => [['item_id' => $this->kabel->id, 'roll_codes' => $this->roll->roll_code]],
        ]);

        $second->assertSessionHas('error');
        $this->roll->refresh();
        $this->assertEquals($teknisiA->id, $this->roll->current_technician_id);
    }
}
