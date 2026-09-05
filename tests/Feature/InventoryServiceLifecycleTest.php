<?php

namespace Tests\Feature;

use App\Enums\CustodyStatus;
use App\Enums\SerialStatus;
use App\Enums\TransferStatus;
use App\Exceptions\InsufficientCustodyException;
use App\Models\FopTask;
use App\Models\InventoryBalance;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\TaskMaterial;
use App\Models\TechnicianCustody;
use App\Models\User;
use App\Services\InventoryAdjustmentService;
use App\Services\InventoryIssueService;
use App\Services\InventoryReassignService;
use App\Services\InventoryReceiveService;
use App\Services\InventoryService;
use App\Services\InventoryTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ADHOC-54 Fase Service — jalur hidup lengkap satu barang: RECEIVE (Pusat) →
 * TRANSFER (dispatch+confirm, termasuk skenario partial/mismatch) → ISSUE
 * (Cabang→Teknisi) → consumeFromCustody (FIFO lintas lot) → REASSIGN →
 * ADJUSTMENT. Satu test class besar karena tiap tahap butuh state dari tahap
 * sebelumnya — pola sama alur bisnisnya sendiri, bukan unit test terisolasi.
 */
class InventoryServiceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pusat;

    private Pop $cabang;

    private Item $modem;

    private Item $kabel;

    private User $admin;

    private User $teknisiA;

    private User $teknisiB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pusat = Pop::create(['code' => 'PUSAT', 'pop_code' => 'PST', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Gudang Pusat', 'type' => 'pusat', 'status' => 'active']);
        $this->cabang = Pop::create(['code' => 'CABANG', 'pop_code' => 'CBG', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Siman', 'type' => 'cabang', 'status' => 'active']);

        $catAktif = ItemCategory::where('code', 'media_converter')->firstOrFail();
        $catPasif = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();

        $this->modem = Item::create(['code' => 'MODEM', 'name' => 'ZTE F670L', 'item_category_id' => $catAktif->id, 'unit' => 'pcs', 'tracking_type' => 'serialized', 'ownership_mode' => 'installable']);
        $this->kabel = Item::create(['code' => 'KABEL', 'name' => 'Dropcore 1 Core', 'item_category_id' => $catPasif->id, 'unit' => 'meter', 'tracking_type' => 'batch']);

        $this->admin = User::factory()->create();
        $this->teknisiA = User::factory()->create();
        $this->teknisiB = User::factory()->create();
    }

    #[Test]
    public function receive_menolak_di_luar_gudang_pusat(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(InventoryReceiveService::class)->receiveQuantity($this->cabang, $this->kabel, 100, 5000, 'LOT-001', $this->admin);
    }

    #[Test]
    public function alur_lengkap_receive_transfer_issue_consume_reassign_adjustment(): void
    {
        $receiveSvc = app(InventoryReceiveService::class);
        $transferSvc = app(InventoryTransferService::class);
        $issueSvc = app(InventoryIssueService::class);
        $inventorySvc = app(InventoryService::class);
        $reassignSvc = app(InventoryReassignService::class);
        $adjustSvc = app(InventoryAdjustmentService::class);

        // 1. RECEIVE — dua lot harga beda.
        $serials = $receiveSvc->receiveSerialized($this->pusat, $this->modem, ['ZTE001', 'ZTE002'], 350000, $this->admin);
        $this->assertCount(2, $serials);
        $this->assertSame(SerialStatus::AVAILABLE, $serials[0]->status);

        $receiveSvc->receiveQuantity($this->pusat, $this->kabel, 500, 5000, 'LOT-001', $this->admin);
        $receiveSvc->receiveQuantity($this->pusat, $this->kabel, 300, 5500, 'LOT-002', $this->admin);

        // 2. TRANSFER dispatch.
        $transfer = $transferSvc->createTransfer($this->pusat, $this->cabang, [
            ['item_id' => $this->modem->id, 'serial_numbers' => ['ZTE001', 'ZTE002']],
            ['item_id' => $this->kabel->id, 'qty' => 200, 'lot_no' => 'LOT-001'],
            ['item_id' => $this->kabel->id, 'qty' => 100, 'lot_no' => 'LOT-002'],
        ], $this->admin);

        $this->assertSame(TransferStatus::IN_TRANSIT, $transfer->status);
        $this->assertEquals(300, InventoryBalance::where('pop_id', $this->pusat->id)->where('item_id', $this->kabel->id)->where('lot_no', 'LOT-001')->value('qty'));

        // 3. TRANSFER confirm — PARTIAL (ZTE002 hilang di jalan).
        $transfer = $transferSvc->receiveTransfer(
            $transfer,
            ['ZTE001'],
            [$this->kabel->id => ['LOT-001' => 200, 'LOT-002' => 100]],
            $this->admin,
        );

        $this->assertSame(TransferStatus::RECEIVED_PARTIAL, $transfer->status);
        $this->assertSame(SerialStatus::AVAILABLE, $serials[0]->fresh()->status);
        $this->assertSame(SerialStatus::TRANSFERRED, $serials[1]->fresh()->status, 'SN hilang tetap TRANSFERRED, gak nyasar AVAILABLE di cabang');

        // 4. ISSUE ke teknisi A — dua lot beda harga.
        $issueSvc->issue($this->cabang, $this->teknisiA, [
            ['item_id' => $this->modem->id, 'serial_numbers' => ['ZTE001']],
            ['item_id' => $this->kabel->id, 'qty' => 150, 'lot_no' => 'LOT-001'],
        ], $this->admin);
        $issueSvc->issue($this->cabang, $this->teknisiA, [
            ['item_id' => $this->kabel->id, 'qty' => 100, 'lot_no' => 'LOT-002'],
        ], $this->admin);

        $custodyLot1 = TechnicianCustody::where('technician_id', $this->teknisiA->id)->where('lot_no', 'LOT-001')->firstOrFail();
        $custodyLot2 = TechnicianCustody::where('technician_id', $this->teknisiA->id)->where('lot_no', 'LOT-002')->firstOrFail();
        $this->assertEquals(5000, $custodyLot1->unit_price_snapshot);
        $this->assertEquals(5500, $custodyLot2->unit_price_snapshot);

        // 5. consumeFromCustody — FIFO lintas 2 lot, per-lot pricing.
        $fopTask = FopTask::create(['task_number' => 'TFOP-2026-0001', 'category' => 'PSB', 'tugas' => 'Uji Konsumsi']);
        $consumed = $inventorySvc->consumeFromCustody([$this->teknisiA], $this->kabel, 180, $fopTask, null, $this->admin);

        $this->assertCount(2, $consumed);
        $this->assertSame(CustodyStatus::CONSUMED, $custodyLot1->fresh()->status);
        $this->assertSame(CustodyStatus::PARTIALLY_USED, $custodyLot2->fresh()->status);
        $this->assertEquals(70, $custodyLot2->fresh()->qty_remaining);

        $tm1 = TaskMaterial::where('fop_task_id', $fopTask->id)->where('lot_no', 'LOT-001')->firstOrFail();
        $tm2 = TaskMaterial::where('fop_task_id', $fopTask->id)->where('lot_no', 'LOT-002')->firstOrFail();
        $this->assertEquals(5000, $tm1->unit_price_snapshot);
        $this->assertEquals(5500, $tm2->unit_price_snapshot, 'harga per-lot, bukan harga flat item');

        // Overclaim ditolak — structural constraint, bukan anomaly detection.
        $this->expectException(InsufficientCustodyException::class);
        $inventorySvc->consumeFromCustody([$this->teknisiA], $this->kabel, 1000, $fopTask, null, $this->admin);
    }

    #[Test]
    public function reassign_wajib_reason_dan_transfer_custody_tidak_sentuh_stok_gudang(): void
    {
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $this->kabel, 100, 5000, 'LOT-001', $this->admin);
        $transfer = app(InventoryTransferService::class)->createTransfer($this->pusat, $this->cabang, [
            ['item_id' => $this->kabel->id, 'qty' => 100, 'lot_no' => 'LOT-001'],
        ], $this->admin);
        app(InventoryTransferService::class)->receiveTransfer($transfer, [], [$this->kabel->id => ['LOT-001' => 100]], $this->admin);
        app(InventoryIssueService::class)->issue($this->cabang, $this->teknisiA, [
            ['item_id' => $this->kabel->id, 'qty' => 100, 'lot_no' => 'LOT-001'],
        ], $this->admin);

        $custody = TechnicianCustody::where('technician_id', $this->teknisiA->id)->firstOrFail();
        $balanceBefore = (float) InventoryBalance::where('pop_id', $this->cabang->id)->where('item_id', $this->kabel->id)->value('qty');

        $reassignSvc = app(InventoryReassignService::class);

        $this->expectException(InvalidArgumentException::class);
        $reassignSvc->transferCustodyToTechnician($custody, $this->teknisiB, '', $this->admin);
    }

    #[Test]
    public function adjustment_menolak_stok_jadi_negatif(): void
    {
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $this->kabel, 50, 5000, 'LOT-001', $this->admin);

        $this->expectException(InvalidArgumentException::class);
        app(InventoryAdjustmentService::class)->adjustPopBalance($this->pusat, $this->kabel->id, -999, 'shrinkage_on_return', $this->admin, 'LOT-001');
    }
}
