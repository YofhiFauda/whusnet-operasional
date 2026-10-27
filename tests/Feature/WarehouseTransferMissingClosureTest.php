<?php

namespace Tests\Feature;

use App\Enums\InventoryTransactionType;
use App\Enums\SerialStatus;
use App\Enums\TransferStatus;
use App\Models\InventoryBalance;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WarehouseTransferMissingClosureTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $pusat;

    private Pop $cabang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FeatureSeeder::class, ActionSeeder::class, RoleSeeder::class, PermissionSeeder::class, WarehouseFeatureSeeder::class, RolePermissionSeeder::class, ItemCategorySeeder::class]);
        $this->owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $this->pusat = Pop::create(['code' => 'MISS-PUSAT', 'pop_code' => 'MIP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat Missing Test', 'type' => 'pusat', 'status' => 'active']);
        $this->cabang = Pop::create(['code' => 'MISS-CABANG', 'pop_code' => 'MIC', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Missing Test', 'type' => 'cabang', 'status' => 'active']);
    }

    #[Test]
    public function sisa_quantity_yang_dipastikan_hilang_ditutup_sebagai_rugi_tanpa_memotong_stok_dua_kali(): void
    {
        $item = Item::create(['code' => 'MISS-KABEL', 'name' => 'Kabel Missing Test', 'item_category_id' => ItemCategory::where('code', 'kabel_dropcore')->firstOrFail()->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $item, 100, 770, $this->owner);
        $service = app(InventoryTransferService::class);
        $transfer = $service->createTransfer($this->pusat, $this->cabang, [['item_id' => $item->id, 'qty' => 100]], $this->owner);
        $dispatch = $transfer->transactions()->whereNotNull('from_pop_id')->firstOrFail();
        $service->receiveTransfer($transfer, [], ['_lines' => [$dispatch->id => 80]], $this->owner);

        $closed = $service->closeMissingTransfer($transfer, '20 meter hilang dalam perjalanan', 'evidence/lost-transfer.jpg', $this->owner);

        $this->assertSame(TransferStatus::CLOSED_LOST, $closed->status);
        $this->assertEquals(80, InventoryBalance::where('pop_id', $this->cabang->id)->where('item_id', $item->id)->value('qty'));
        $this->assertEquals(0, InventoryBalance::where('pop_id', $this->pusat->id)->where('item_id', $item->id)->value('qty'));
        $loss = InventoryTransaction::where('inventory_transfer_id', $transfer->id)->where('type', InventoryTransactionType::ADJUSTMENT->value)->firstOrFail();
        $this->assertSame($dispatch->id, $loss->source_inventory_transaction_id);
        $this->assertSame('lost', $loss->resulting_status);
        $this->assertEquals(-20, (float) $loss->qty);
        $this->assertEquals(770, (float) $loss->unit_price_snapshot);
        $this->assertNull($loss->from_pop_id);
        $this->assertNull($loss->to_pop_id);
        $this->assertFalse($closed->isInTransit());
        $report = $this->actingAs($this->owner)->get(route('warehouse.reports.index', ['pop_id' => $this->pusat->id]));
        $report->assertOk();
        $lossRow = collect($report->viewData('adjustmentRows'))->firstWhere('reason', 'lost');
        $this->assertNotNull($lossRow);
        $this->assertEquals(15400, $lossRow['loss_value']);
        $this->assertSame($this->pusat->id, $lossRow['pop_id']);

        $this->expectException(InvalidArgumentException::class);
        $service->closeMissingTransfer($transfer, 'ulang', 'evidence/lost-transfer.jpg', $this->owner);
    }

    #[Test]
    public function sisa_transfer_cabang_ke_pusat_dapat_ditutup_dan_ruginya_diatribusikan_ke_cabang(): void
    {
        $item = Item::create(['code' => 'MISS-REVERSE', 'name' => 'Kabel Balik Hilang', 'item_category_id' => ItemCategory::where('code', 'kabel_dropcore')->firstOrFail()->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $item, 100, 770, $this->owner);
        $service = app(InventoryTransferService::class);
        $forward = $service->createTransfer($this->pusat, $this->cabang, [['item_id' => $item->id, 'qty' => 100]], $this->owner);
        $service->receiveTransfer($forward, [], [$item->id => 100], $this->owner);
        $transfer = $service->createReverseTransfer($this->cabang, $this->pusat, [['item_id' => $item->id, 'qty' => 100]], $this->owner);
        $dispatch = $transfer->transactions()->whereNotNull('from_pop_id')->firstOrFail();
        $service->receiveTransfer($transfer, [], ['_lines' => [$dispatch->id => 80]], $this->owner);

        $this->actingAs($this->owner)->get(route('warehouse.transfers.show', $transfer))
            ->assertOk()->assertSee('Tutup Sisa Hilang');
        $service->closeMissingTransfer($transfer, '20 meter tidak tiba di Pusat', 'evidence/reverse.jpg', $this->owner);

        $this->assertSame(TransferStatus::CLOSED_LOST, $transfer->fresh()->status);
        $report = $this->actingAs($this->owner)->get(route('warehouse.reports.index', ['pop_id' => $this->cabang->id]));
        $report->assertOk();
        $loss = collect($report->viewData('adjustmentRows'))->firstWhere('reason', 'lost');
        $this->assertEquals(15400, $loss['loss_value']);
        $this->assertSame($this->cabang->id, $loss['pop_id']);
    }

    #[Test]
    public function sisa_sn_hilang_tidak_bisa_diterima_lagi_setelah_transfer_ditutup(): void
    {
        $item = Item::create(['code' => 'MISS-MODEM', 'name' => 'Modem Missing Test', 'item_category_id' => ItemCategory::where('code', 'modem_ont')->firstOrFail()->id, 'unit' => 'unit', 'tracking_type' => 'serialized']);
        app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $item, ['MISS-SN-1', 'MISS-SN-2'], 200000, $this->owner);
        $service = app(InventoryTransferService::class);
        $transfer = $service->createTransfer($this->pusat, $this->cabang, [['item_id' => $item->id, 'serial_numbers' => ['MISS-SN-1', 'MISS-SN-2']]], $this->owner);
        $service->receiveTransfer($transfer, ['MISS-SN-1'], [], $this->owner);

        $service->closeMissingTransfer($transfer, 'SN kedua tidak tiba', 'evidence/lost-transfer.jpg', $this->owner);

        $this->assertSame(SerialStatus::LOST, InventorySerial::where('serial_number', 'MISS-SN-2')->firstOrFail()->status);
        $this->assertSame(SerialStatus::AVAILABLE, InventorySerial::where('serial_number', 'MISS-SN-1')->firstOrFail()->status);
        $this->assertSame(TransferStatus::CLOSED_LOST, $transfer->fresh()->status);

        $this->expectException(InvalidArgumentException::class);
        $service->receiveTransfer($transfer, ['MISS-SN-2'], [], $this->owner);
    }

    #[Test]
    public function halaman_penutupan_transfer_mewajibkan_alasan_dan_bukti(): void
    {
        $item = Item::create(['code' => 'MISS-KABEL-UI', 'name' => 'Kabel Missing UI', 'item_category_id' => ItemCategory::where('code', 'kabel_dropcore')->firstOrFail()->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $item, 100, 770, $this->owner);
        $transfer = app(InventoryTransferService::class)->createTransfer($this->pusat, $this->cabang, [['item_id' => $item->id, 'qty' => 100]], $this->owner);
        Storage::fake('public');

        $this->actingAs($this->owner)->get(route('warehouse.transfers.show', $transfer))->assertOk()->assertSee('Tutup Sisa Hilang');
        $this->post(route('warehouse.transfers.close-missing', $transfer), ['reason' => 'Tidak tiba'])->assertSessionHasErrors('evidence');
        $this->post(route('warehouse.transfers.close-missing', $transfer), [
            'reason' => 'Tidak tiba setelah pemeriksaan',
            'evidence' => UploadedFile::fake()->image('bukti.jpg'),
        ])->assertRedirect(route('warehouse.transfers.show', $transfer));

        $this->assertSame(TransferStatus::CLOSED_LOST, $transfer->fresh()->status);
    }
}
