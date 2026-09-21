<?php

namespace Tests\Feature;

use App\Enums\RollStatus;
use App\Enums\TransferStatus;
use App\Models\InventoryRoll;
use App\Models\InventoryTransfer;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
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
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Transfer roll kabel Pusat→Cabang — dua fase (dispatch/confirm), mirror
 * `dispatchSerialized()`/confirm SN tapi buat `InventoryRoll`. Krusial: tanpa
 * ini roll yang diterima di Pusat gak akan pernah bisa sampai ke Cabang buat
 * di-Issue ke teknisi. Lihat docs/TASKS.md ADHOC kabel-per-roll.
 */
class InventoryTransferRollTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $pusat;

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

        $this->pusat = Pop::create(['code' => 'TFR-PUSAT', 'pop_code' => 'TFRP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat Transfer Roll', 'type' => 'pusat', 'status' => 'active']);
        $this->cabang = Pop::create(['code' => 'TFR-CABANG', 'pop_code' => 'TFRC', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Transfer Roll', 'type' => 'cabang', 'status' => 'active']);

        $catKabel = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $this->kabel = Item::create(['code' => 'TFR-ROLL', 'name' => 'Kabel FO Transfer Roll', 'item_category_id' => $catKabel->id, 'unit' => 'meter', 'tracking_type' => 'roll', 'meter_per_roll' => 900]);

        $this->roll = app(InventoryReceiveService::class)->receiveRoll($this->pusat, $this->kabel, 1, 'PT Vendor Transfer', 2700000, $this->owner)[0];
    }

    #[Test]
    public function transfer_full_flow_dispatch_lalu_confirm(): void
    {
        $service = app(InventoryTransferService::class);

        $transfer = $service->createTransfer($this->pusat, $this->cabang, [
            ['item_id' => $this->kabel->id, 'roll_codes' => [$this->roll->roll_code]],
        ], $this->owner);

        $this->assertSame(TransferStatus::IN_TRANSIT, $transfer->status);
        $this->roll->refresh();
        $this->assertEquals(RollStatus::TRANSFERRED, $this->roll->status);
        $this->assertNull($this->roll->current_pop_id);

        $confirmed = $service->receiveTransfer($transfer, [], [], $this->owner, [$this->roll->roll_code]);

        $this->assertSame(TransferStatus::RECEIVED, $confirmed->status);
        $this->roll->refresh();
        $this->assertEquals(RollStatus::AVAILABLE, $this->roll->status);
        $this->assertEquals($this->cabang->id, $this->roll->current_pop_id);
    }

    #[Test]
    public function roll_gak_dikonfirmasi_tetap_transferred_status_partial(): void
    {
        $service = app(InventoryTransferService::class);

        $transfer = $service->createTransfer($this->pusat, $this->cabang, [
            ['item_id' => $this->kabel->id, 'roll_codes' => [$this->roll->roll_code]],
        ], $this->owner);

        $confirmed = $service->receiveTransfer($transfer, [], [], $this->owner, []);

        $this->assertSame(TransferStatus::RECEIVED_PARTIAL, $confirmed->status);
        $this->roll->refresh();
        $this->assertEquals(RollStatus::TRANSFERRED, $this->roll->status, 'roll limbo — belum dikonfirmasi, jangan diam-diam jadi AVAILABLE');
    }

    #[Test]
    public function roll_yang_gak_available_di_pusat_ditolak_dispatch(): void
    {
        $teknisi = User::factory()->create();
        $this->roll->update(['status' => RollStatus::ISSUED, 'current_pop_id' => null, 'current_technician_id' => $teknisi->id]);

        $this->expectException(InvalidArgumentException::class);

        app(InventoryTransferService::class)->createTransfer($this->pusat, $this->cabang, [
            ['item_id' => $this->kabel->id, 'roll_codes' => [$this->roll->roll_code]],
        ], $this->owner);

        $this->assertEquals(0, InventoryTransfer::count());
    }
}
