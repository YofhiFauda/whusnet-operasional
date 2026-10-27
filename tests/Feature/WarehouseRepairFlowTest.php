<?php

namespace Tests\Feature;

use App\Enums\InventoryTransactionType;
use App\Enums\ItemCondition;
use App\Enums\SerialStatus;
use App\Models\InventorySerial;
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
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WarehouseRepairFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function barang_rusak_yang_lulus_inspeksi_kembali_tersedia_dengan_harga_perolehan_semula(): void
    {
        $this->seed([FeatureSeeder::class, ActionSeeder::class, RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $pusat = Pop::create([
            'code' => 'REP-PUSAT', 'pop_code' => 'REP', 'registration_prefix' => 'C',
            'cid_prefix' => 'D', 'name' => 'Pusat Repair Test', 'type' => 'pusat', 'status' => 'active',
        ]);
        $item = Item::create([
            'code' => 'REP-MODEM', 'name' => 'Modem Repair Test',
            'item_category_id' => ItemCategory::where('equipment_class', 'aktif')->firstOrFail()->id,
            'unit' => 'unit', 'tracking_type' => 'serialized', 'ownership_mode' => 'installable',
        ]);
        [$serial] = app(InventoryReceiveService::class)->receiveSerialized($pusat, $item, ['REP-SN-1'], 200000, $owner);
        $damage = app(InventoryAdjustmentService::class)->adjustSerialStatus(
            $serial, SerialStatus::DAMAGED, 'rusak saat pemeriksaan', $owner, null, 'evidence/damaged.jpg'
        );

        $repair = app(InventoryAdjustmentService::class)->restoreRepairedSerial(
            $serial, 'Lulus inspeksi setelah perbaikan internal', $owner, 'evidence/repaired.jpg'
        );

        $serial->refresh();
        $this->assertSame(SerialStatus::AVAILABLE, $serial->status);
        $this->assertSame(ItemCondition::USED_GOOD, $serial->condition);
        $this->assertSame($owner->id, $serial->condition_checked_by);
        $this->assertSame(InventoryTransactionType::ADJUSTMENT, $repair->type);
        $this->assertSame('available', $repair->resulting_status);
        $this->assertSame($damage->id, $repair->source_inventory_transaction_id);
        $this->assertSame($pusat->id, $repair->to_pop_id);
        $this->assertEquals(200000, (float) $repair->unit_price_snapshot);
        $this->assertEquals(200000, app(WarehouseStockAsOfService::class)->balanceAsOf($pusat, $item, now()->addDay())['nilai']);
        $report = $this->actingAs($owner)->get(route('warehouse.reports.index'));
        $report->assertOk();
        $recovered = collect($report->viewData('adjustmentRows'))->firstWhere('reason', 'available');
        $this->assertNotNull($recovered);
        $this->assertEquals(-200000, $recovered['loss_value']);
        $this->assertEquals(0, $report->viewData('kpi')['total_loss_value']);

    }

    #[Test]
    public function nilai_pemulihan_hanya_membalik_rugi_unit_yang_sama_walau_ada_pembelian_baru(): void
    {
        [$owner, $serial] = $this->makeDamagedSerial();
        $pusat = $serial->currentPop;
        $item = $serial->item;

        app(InventoryReceiveService::class)->receiveSerialized($pusat, $item, ['REP-SN-2'], 250000, $owner);
        app(InventoryAdjustmentService::class)->restoreRepairedSerial(
            $serial, 'Perbaikan internal selesai', $owner, 'evidence/repaired.jpg'
        );

        $report = $this->actingAs($owner)->get(route('warehouse.reports.index'));
        $report->assertOk();
        $this->assertEquals(0, $report->viewData('kpi')['total_loss_value']);
        $damage = collect($report->viewData('adjustmentRows'))->firstWhere('reason', 'damaged');
        $this->assertEquals(200000, $damage['loss_value']);
    }

    #[Test]
    public function pemeriksaan_karantina_dan_perbaikan_dalam_bulan_yang_sama_tidak_tercampur(): void
    {
        [$owner, $serial] = $this->makeDamagedSerial();
        $pusat = $serial->currentPop;
        $item = $serial->item;
        [$inspectionSerial] = app(InventoryReceiveService::class)->receiveSerialized(
            $pusat, $item, ['REP-SN-3'], 200000, $owner
        );
        app(InventoryAdjustmentService::class)->adjustSerialStatus(
            $inspectionSerial, SerialStatus::QUARANTINE, 'Perlu cek', $owner
        );
        app(InventoryAdjustmentService::class)->restoreRepairedSerial(
            $inspectionSerial, 'Lulus pemeriksaan', $owner, 'evidence/check.jpg'
        );
        app(InventoryAdjustmentService::class)->restoreRepairedSerial(
            $serial, 'Perbaikan selesai', $owner, 'evidence/repaired.jpg'
        );

        $report = $this->actingAs($owner)->get(route('warehouse.reports.index'));
        $report->assertOk();
        $recoveries = collect($report->viewData('adjustmentRows'))->where('reason', 'available');
        $this->assertCount(2, $recoveries);
        $this->assertEquals(-200000, $recoveries->sum('loss_value'));
        $this->assertEquals(0, $report->viewData('kpi')['total_loss_value']);
    }

    #[Test]
    public function halaman_perbaikan_mewajibkan_bukti_dan_mencatat_inspeksi(): void
    {
        [$owner, $serial] = $this->makeDamagedSerial();
        Storage::fake('public');

        $this->actingAs($owner)->get(route('warehouse.adjustments.serial.repair.create', $serial))->assertOk();
        $this->post(route('warehouse.adjustments.serial.repair.store', $serial), [
            'reason' => 'Lulus pemeriksaan setelah diperbaiki',
        ])->assertSessionHasErrors('evidence');

        $this->post(route('warehouse.adjustments.serial.repair.store', $serial), [
            'reason' => 'Lulus pemeriksaan setelah diperbaiki',
            'evidence' => UploadedFile::fake()->image('hasil-inspeksi.jpg'),
        ])->assertRedirect(route('warehouse.damaged.index'));

        $this->assertSame(SerialStatus::AVAILABLE, $serial->fresh()->status);
    }

    #[Test]
    public function karantina_tanpa_kerugian_sebelumnya_tidak_menciptakan_pemulihan_rugi_semu(): void
    {
        [$owner, $serial] = $this->makeDamagedSerial(SerialStatus::QUARANTINE);

        $repair = app(InventoryAdjustmentService::class)->restoreRepairedSerial(
            $serial, 'Lulus pemeriksaan', $owner, 'evidence/inspection.jpg'
        );

        $this->assertSame('inspection_cleared', $repair->reason);
        $report = $this->actingAs($owner)->get(route('warehouse.reports.index'));
        $report->assertOk();
        $this->assertEquals(0, $report->viewData('kpi')['total_loss_value']);
    }

    /**
     * @return array{User, InventorySerial}
     */
    private function makeDamagedSerial(SerialStatus $status = SerialStatus::DAMAGED): array
    {
        $this->seed([FeatureSeeder::class, ActionSeeder::class, RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $pusat = Pop::create([
            'code' => 'REP-PUSAT', 'pop_code' => 'REP', 'registration_prefix' => 'C',
            'cid_prefix' => 'D', 'name' => 'Pusat Repair Test', 'type' => 'pusat', 'status' => 'active',
        ]);
        $item = Item::create([
            'code' => 'REP-MODEM', 'name' => 'Modem Repair Test',
            'item_category_id' => ItemCategory::where('equipment_class', 'aktif')->firstOrFail()->id,
            'unit' => 'unit', 'tracking_type' => 'serialized', 'ownership_mode' => 'installable',
        ]);
        [$serial] = app(InventoryReceiveService::class)->receiveSerialized($pusat, $item, ['REP-SN-1'], 200000, $owner);
        app(InventoryAdjustmentService::class)->adjustSerialStatus(
            $serial, $status, 'status saat pemeriksaan', $owner, null, 'evidence/damaged.jpg'
        );

        return [$owner, $serial];
    }
}
