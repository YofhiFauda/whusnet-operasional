<?php

namespace Tests\Feature\Warehouse;

use App\Enums\CustodyStatus;
use App\Enums\InventoryTransactionType;
use App\Enums\ItemCondition;
use App\Enums\RollStatus;
use App\Enums\SerialStatus;
use App\Exceptions\InsufficientCustodyException;
use App\Models\FopTask;
use App\Models\InfrastructureBalance;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\NetworkNode;
use App\Models\Pop;
use App\Models\Role;
use App\Models\TechnicianCustody;
use App\Models\User;
use App\Services\InventoryReassignService;
use App\Services\InventoryService;
use App\Services\NetworkNodeService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Deploy barang dari custody teknisi ke titik infrastruktur jaringan
 * (ODC/ODP/splitter) + cabut balik. Tiap baris ledger DEPLOY/RETRIEVE_INFRA
 * wajib membawa snapshot Cabang node SAAT transaksi (`to_pop_id`/
 * `from_pop_id`) — kalau node dipindah belakangan, histori lama tidak ikut
 * berubah.
 */
class InventoryServiceDeployTest extends TestCase
{
    use RefreshDatabase;

    private Pop $cabangA;

    private Pop $cabangB;

    private User $technician;

    private User $actor;

    private NetworkNode $odc;

    private FopTask $fopTask;

    private ItemCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabangA = Pop::factory()->create(['name' => 'Cabang A', 'type' => 'cabang']);
        $this->cabangB = Pop::factory()->create(['name' => 'Cabang B', 'type' => 'cabang']);

        $this->technician = User::factory()->create();
        $this->actor = User::factory()->create();

        $this->odc = NetworkNode::factory()->create(['anchor_pop_id' => $this->cabangA->id, 'name' => 'ODC Uji']);

        $this->fopTask = FopTask::create([
            'task_number' => 'TFOP-DPL-0001',
            'task_date' => now(),
            'category' => 'INFR REQ',
            'tugas' => 'Uji Deploy Infrastruktur',
            'pop_id' => $this->cabangA->id,
            'issue' => 'Uji',
            'status' => 'draft',
            'priority' => 'Medium',
        ]);

        $this->category = ItemCategory::where('code', 'infra_test')->first()
            ?? ItemCategory::create(['code' => 'infra_test', 'name' => 'Infra Test', 'default_unit' => 'pcs']);
    }

    private function makeItem(string $code, string $trackingType, string $ownershipMode, string $unit = 'pcs'): Item
    {
        return Item::create([
            'code' => $code,
            'name' => "Item {$code}",
            'unit' => $unit,
            'item_category_id' => $this->category->id,
            'tracking_type' => $trackingType,
            'ownership_mode' => $ownershipMode,
        ]);
    }

    private function issuedSerial(Item $item, string $serialNumber, ?Pop $issuedFrom = null): InventorySerial
    {
        return InventorySerial::create([
            'item_id' => $item->id,
            'serial_number' => $serialNumber,
            'status' => SerialStatus::ISSUED->value,
            'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => ($issuedFrom ?? $this->cabangA)->id,
        ]);
    }

    #[Test]
    public function aset_infrastruktur_dicabut_wajib_diperiksa_sebelum_issue(): void
    {
        $item = $this->makeItem('DPL-CHECK', 'serialized', 'infrastructure_asset');
        $serial = $this->issuedSerial($item, 'SN-DPL-CHECK');
        app(InventoryService::class)->deploySerial($serial, $this->odc, $this->fopTask, [$this->technician], $this->actor);

        $retrieval = app(InventoryService::class)->retrieveSerialFromLocation($serial, $this->fopTask, $this->actor);
        $this->assertStringStartsWith('CBT-', $retrieval->reference_number);

        $serial->refresh();
        $this->assertSame(ItemCondition::USED_GOOD, $serial->condition);
        $this->assertNull($serial->condition_checked_at);
        $this->assertFalse($serial->isClearedForIssue());

        app(InventoryReassignService::class)->returnSerialToWarehouse($serial, $this->cabangA, 'Selesai dicabut', $this->actor);
        $serial->refresh();
        $this->assertSame(SerialStatus::AVAILABLE, $serial->status);
        $this->assertFalse($serial->isClearedForIssue());
    }

    #[Test]
    public function deploy_serial_memindah_sn_ke_node_dan_mencatat_ledger_dengan_snapshot_cabang(): void
    {
        $splitter = $this->makeItem('DPL-SPLITTER', 'serialized', 'infrastructure_asset');
        $serial = $this->issuedSerial($splitter, 'SN-DPL-001');

        $transaction = app(InventoryService::class)->deploySerial($serial, $this->odc, $this->fopTask, [$this->technician], $this->actor);

        $serial->refresh();
        $this->assertSame(SerialStatus::DEPLOYED, $serial->status);
        $this->assertSame($this->odc->id, (int) $serial->current_network_node_id);
        $this->assertNull($serial->current_technician_id);

        $this->assertSame(InventoryTransactionType::DEPLOY, $transaction->type);
        $this->assertSame($this->odc->id, (int) $transaction->to_network_node_id);
        $this->assertSame($this->cabangA->id, (int) $transaction->to_pop_id);
        $this->assertSame($this->technician->id, (int) $transaction->from_technician_id);
        $this->assertSame($this->fopTask->id, (int) $transaction->fop_task_id);
        $this->assertStringStartsWith('DPL-', $transaction->reference_number);
    }

    #[Test]
    public function deploy_serial_menolak_item_yang_bukan_aset_infrastruktur(): void
    {
        $modem = $this->makeItem('DPL-MODEM', 'serialized', 'installable');
        $serial = $this->issuedSerial($modem, 'SN-DPL-002');

        try {
            app(InventoryService::class)->deploySerial($serial, $this->odc, $this->fopTask, [$this->technician], $this->actor);
            $this->fail('deploySerial() seharusnya menolak item installable.');
        } catch (InvalidArgumentException) {
            // diharapkan
        }

        $this->assertSame(SerialStatus::ISSUED, $serial->refresh()->status);
        $this->assertSame(0, InventoryTransaction::count());
    }

    #[Test]
    public function deploy_quantity_menambah_infrastructure_balance_dan_mengurangi_custody(): void
    {
        $closure = $this->makeItem('DPL-CLOSURE', 'quantity', 'infrastructure_asset');

        $lotLama = TechnicianCustody::create([
            'technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->cabangA->id,
            'item_id' => $closure->id,
            'qty_remaining' => 3,
            'unit_price_snapshot' => 10000,
            'status' => 'issued',
            'issued_at' => now()->subDay(),
        ]);
        $lotBaru = TechnicianCustody::create([
            'technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->cabangA->id,
            'item_id' => $closure->id,
            'qty_remaining' => 10,
            'unit_price_snapshot' => 12000,
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        $consumed = app(InventoryService::class)->deployQuantity([$this->technician], $closure, 5, $this->odc, $this->fopTask, $this->actor);

        $this->assertCount(2, $consumed);

        $lotLama->refresh();
        $lotBaru->refresh();
        $this->assertEquals(0, $lotLama->qty_remaining);
        $this->assertSame(CustodyStatus::CONSUMED, $lotLama->status);
        $this->assertEquals(8, $lotBaru->qty_remaining);
        $this->assertSame(CustodyStatus::PARTIALLY_USED, $lotBaru->status);

        $balances = InfrastructureBalance::where('item_id', $closure->id)->where('network_node_id', $this->odc->id)->get();
        $this->assertCount(1, $balances);
        $this->assertEquals(5, $balances->first()->qty);
        $this->assertSame('', $balances->first()->lot_no);

        $ledger = InventoryTransaction::where('type', InventoryTransactionType::DEPLOY->value)->orderBy('id')->get();
        $this->assertCount(2, $ledger);
        $this->assertEquals(3, $ledger[0]->qty);
        $this->assertEquals(10000, $ledger[0]->unit_price_snapshot);
        $this->assertEquals(2, $ledger[1]->qty);
        $this->assertEquals(12000, $ledger[1]->unit_price_snapshot);
        $this->assertSame($this->cabangA->id, (int) $ledger[0]->to_pop_id);
        $this->assertSame($this->odc->id, (int) $ledger[0]->to_network_node_id);
        $this->assertNotSame($ledger[0]->reference_number, $ledger[1]->reference_number);
    }

    #[Test]
    public function deploy_quantity_lempar_insufficient_custody_dan_tidak_membentuk_balance(): void
    {
        $closure = $this->makeItem('DPL-CLOSURE-2', 'quantity', 'infrastructure_asset');

        $lot = TechnicianCustody::create([
            'technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->cabangA->id,
            'item_id' => $closure->id,
            'qty_remaining' => 2,
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        try {
            app(InventoryService::class)->deployQuantity([$this->technician], $closure, 5, $this->odc, $this->fopTask, $this->actor);
            $this->fail('deployQuantity() seharusnya melempar InsufficientCustodyException.');
        } catch (InsufficientCustodyException) {
            // diharapkan
        }

        $this->assertSame(0, InfrastructureBalance::count());
        $this->assertSame(0, InventoryTransaction::count());
        $this->assertEquals(2, $lot->refresh()->qty_remaining);
    }

    #[Test]
    public function deploy_roll_hanya_memotong_sisa_tanpa_saldo_terpasang_dan_roll_tetap_di_custody(): void
    {
        $kabel = $this->makeItem('DPL-FEEDER', 'roll', 'infrastructure_asset', 'meter');

        $roll = InventoryRoll::create([
            'item_id' => $kabel->id,
            'roll_code' => 'ROLL-DPL-001',
            'status' => RollStatus::ISSUED,
            'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->cabangA->id,
            'length_total' => 100,
            'length_remaining' => 100,
            'unit_price_snapshot' => 500000,
        ]);

        $transaction = app(InventoryService::class)->deployRoll($roll, 40, $this->odc, [$this->technician], $this->fopTask, $this->actor);

        $roll->refresh();
        $this->assertEquals(60, $roll->length_remaining);
        $this->assertSame(RollStatus::IN_USE, $roll->status);
        $this->assertSame($this->technician->id, (int) $roll->current_technician_id);

        // Rancangan hierarki §16.4 #2: kabel tidak punya saldo terpasang —
        // saldo kabel yang sah hanya sisa roll; lokasi kerja ada di ledger.
        $this->assertSame(0, InfrastructureBalance::count());
        $this->assertSame($this->odc->id, $transaction->to_network_node_id);

        $this->assertSame(InventoryTransactionType::DEPLOY, $transaction->type);
        $this->assertEquals(40, $transaction->qty);
        $this->assertSame($roll->id, (int) $transaction->roll_id);
        $this->assertEquals(500000, $transaction->unit_price_snapshot);
        $this->assertSame($this->cabangA->id, (int) $transaction->to_pop_id);

        app(InventoryService::class)->deployRoll($roll, 60, $this->odc, [$this->technician], $this->fopTask, $this->actor);

        $roll->refresh();
        $this->assertEquals(0, $roll->length_remaining);
        $this->assertSame(RollStatus::DEPLETED, $roll->status);
        $this->assertSame(0, InfrastructureBalance::count());
    }

    #[Test]
    public function retrieve_serial_dari_lokasi_balik_ke_custody_actor_dengan_snapshot_cabang(): void
    {
        $splitter = $this->makeItem('DPL-SPLITTER-R', 'serialized', 'infrastructure_asset');
        $serial = $this->issuedSerial($splitter, 'SN-DPL-003');

        $service = app(InventoryService::class);
        $service->deploySerial($serial, $this->odc, $this->fopTask, [$this->technician], $this->actor);

        $transaction = $service->retrieveSerialFromLocation($serial, $this->fopTask, $this->actor);

        $serial->refresh();
        $this->assertSame(SerialStatus::ISSUED, $serial->status);
        $this->assertSame($this->actor->id, (int) $serial->current_technician_id);
        $this->assertNull($serial->current_network_node_id);

        $this->assertSame(InventoryTransactionType::RETRIEVE_INFRA, $transaction->type);
        $this->assertSame($this->odc->id, (int) $transaction->from_network_node_id);
        $this->assertSame($this->cabangA->id, (int) $transaction->from_pop_id);
        $this->assertSame($this->actor->id, (int) $transaction->to_technician_id);
        $this->assertStringStartsWith('CBT-', $transaction->reference_number);
    }

    #[Test]
    public function snapshot_cabang_di_ledger_tidak_berubah_saat_node_dipindah(): void
    {
        $splitter = $this->makeItem('DPL-SPLITTER-M', 'serialized', 'infrastructure_asset');
        $serial = $this->issuedSerial($splitter, 'SN-DPL-004');

        $transaction = app(InventoryService::class)->deploySerial($serial, $this->odc, $this->fopTask, [$this->technician], $this->actor);
        // Node ber-aset aktif tidak boleh pindah lintas cabang
        // (NetworkNodeService::assertCrossBranchSubtreeIsEmpty) — aset dicabut
        // dulu; yang diuji di sini snapshot ledger lama tetap Cabang A.
        app(InventoryService::class)->retrieveSerialFromLocation($serial, $this->fopTask, $this->actor, $this->odc);

        $this->seed(RoleSeeder::class);
        $this->actor->update(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $this->actor->unsetRelation('role');
        app(NetworkNodeService::class)->move($this->odc, null, $this->cabangB, $this->actor);

        $this->assertSame($this->cabangB->id, NetworkNode::find($this->odc->id)->resolvePop()?->id);
        $this->assertSame($this->cabangA->id, (int) InventoryTransaction::find($transaction->id)->to_pop_id);
    }

    #[Test]
    public function deploy_menolak_barang_yang_diissue_dari_cabang_lain(): void
    {
        $service = app(InventoryService::class);

        $splitter = $this->makeItem('DPL-SPLITTER-X', 'serialized', 'infrastructure_asset');
        $serial = $this->issuedSerial($splitter, 'SN-DPL-005', $this->cabangB);

        try {
            $service->deploySerial($serial, $this->odc, $this->fopTask, [$this->technician], $this->actor);
            $this->fail('deploySerial() seharusnya menolak SN dari cabang lain.');
        } catch (InvalidArgumentException) {
            // diharapkan
        }

        $kabel = $this->makeItem('DPL-FEEDER-X', 'roll', 'infrastructure_asset', 'meter');
        $roll = InventoryRoll::create([
            'item_id' => $kabel->id,
            'roll_code' => 'ROLL-DPL-X',
            'status' => RollStatus::ISSUED,
            'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->cabangB->id,
            'length_total' => 100,
            'length_remaining' => 100,
        ]);

        try {
            $service->deployRoll($roll, 10, $this->odc, [$this->technician], $this->fopTask, $this->actor);
            $this->fail('deployRoll() seharusnya menolak roll dari cabang lain.');
        } catch (InvalidArgumentException) {
            // diharapkan
        }

        $closure = $this->makeItem('DPL-CLOSURE-X', 'quantity', 'infrastructure_asset');
        TechnicianCustody::create([
            'technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->cabangB->id,
            'item_id' => $closure->id,
            'qty_remaining' => 50,
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        try {
            $service->deployQuantity([$this->technician], $closure, 1, $this->odc, $this->fopTask, $this->actor);
            $this->fail('deployQuantity() seharusnya mengabaikan lot dari cabang lain.');
        } catch (InsufficientCustodyException) {
            // diharapkan
        }

        $this->assertSame(SerialStatus::ISSUED, $serial->refresh()->status);
        $this->assertEquals(100, $roll->refresh()->length_remaining);
        $this->assertSame(0, InfrastructureBalance::count());
        $this->assertSame(0, InventoryTransaction::count());
    }

    #[Test]
    public function consume_from_custody_menolak_barang_aset_infrastruktur(): void
    {
        $closure = $this->makeItem('DPL-CLOSURE-C', 'quantity', 'infrastructure_asset');
        $lot = TechnicianCustody::create([
            'technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->cabangA->id,
            'item_id' => $closure->id,
            'qty_remaining' => 5,
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        try {
            app(InventoryService::class)->consumeFromCustody([$this->technician], $closure, 1, $this->fopTask, null, $this->actor);
            $this->fail('consumeFromCustody() seharusnya menolak aset infrastruktur.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('deployQuantity()', $e->getMessage());
        }

        $this->assertEquals(5, $lot->refresh()->qty_remaining);
    }

    #[Test]
    public function consume_from_roll_menolak_roll_aset_infrastruktur(): void
    {
        $kabel = $this->makeItem('DPL-FEEDER-C', 'roll', 'infrastructure_asset', 'meter');
        $roll = InventoryRoll::create([
            'item_id' => $kabel->id,
            'roll_code' => 'ROLL-DPL-C01',
            'status' => RollStatus::ISSUED,
            'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->cabangA->id,
            'length_total' => 100,
            'length_remaining' => 100,
        ]);

        try {
            app(InventoryService::class)->consumeFromRoll($roll, 10, [$this->technician], $this->fopTask, null, $this->actor);
            $this->fail('consumeFromRoll() seharusnya menolak roll aset infrastruktur.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('deployRoll()', $e->getMessage());
        }

        $this->assertEquals(100, $roll->refresh()->length_remaining);
    }
}
