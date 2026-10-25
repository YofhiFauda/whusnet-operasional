<?php

namespace Tests\Feature\Warehouse;

use App\Enums\CustodyStatus;
use App\Enums\InventoryTransactionType;
use App\Enums\RollStatus;
use App\Models\FopTask;
use App\Models\InfrastructureBalance;
use App\Models\InventoryRoll;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TechnicianCustody;
use App\Models\User;
use App\Services\InfrastructureLocationResolver;
use App\Services\InfrastructureMaterialService;
use App\Services\InfrastructurePositionService;
use App\Services\InventoryService;
use App\Support\InfrastructureLocation;
use Database\Seeders\NetworkNodeTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Kabel = saldo SISA roll (§11.2, §16.4 #2): pemakaian hanya mengurangi
 * `length_remaining` lewat transaksi append-only, TANPA saldo kabel terpasang
 * di lokasi kerja. Harga per meter = snapshot intake roll, tidak dihitung
 * ulang. Barang qty non-kabel tetap memakai saldo lokasi.
 */
class InfrastructureCableConsumptionTest extends TestCase
{
    use RefreshDatabase;

    private Pop $cabang;

    private Pop $miniPop;

    private User $technician;

    private FopTask $fopTask;

    private InventoryRoll $roll;

    private NetworkNode $slot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NetworkNodeTypeSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->cabang = Pop::factory()->create(['type' => 'cabang']);
        $this->miniPop = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $this->cabang->id]);
        $this->technician = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $task = Task::create([
            'task_number' => 'TASK-CBL-0001', 'pop_id' => $this->cabang->id, 'task_type' => 'INFR REQ', 'title' => 'Tarik kabel',
            'status' => 'in_progress', 'created_by' => $this->technician->id, 'updated_by' => $this->technician->id,
        ]);
        $task->teamMembers()->create(['user_id' => $this->technician->id, 'role_in_task' => 'lead']);
        $this->fopTask = FopTask::create([
            'task_number' => 'TFOP-CBL-0001', 'task_date' => now(), 'category' => 'INFR REQ', 'tugas' => 'Tarik kabel',
            'pop_id' => $this->cabang->id, 'issue' => 'Uji', 'status' => 'draft', 'priority' => 'Medium', 'task_id' => $task->id,
        ]);
        $category = ItemCategory::create(['code' => 'kabel_feeder_uji', 'name' => 'Kabel', 'default_unit' => 'meter']);
        $item = Item::create(['code' => 'FDR-24', 'name' => 'Feeder 24C', 'unit' => 'meter', 'item_category_id' => $category->id,
            'tracking_type' => 'roll', 'ownership_mode' => 'infrastructure_asset', 'meter_per_roll' => 100]);
        $this->roll = InventoryRoll::create([
            'item_id' => $item->id, 'roll_code' => 'ROLL-CBL-1', 'status' => RollStatus::ISSUED,
            'current_technician_id' => $this->technician->id, 'issued_from_pop_id' => $this->cabang->id,
            'length_total' => 100, 'length_remaining' => 100, 'unit_price_snapshot' => 1500,
        ]);
        $olt = NetworkNode::factory()->create([
            'node_type_id' => NetworkNodeType::where('code', 'olt_chassis')->firstOrFail()->id,
            'anchor_pop_id' => $this->miniPop->id,
        ]);
        $this->slot = app(InfrastructurePositionService::class)->createSlot($olt, 1);
    }

    private function loc(string $kind, int $id): InfrastructureLocation
    {
        return app(InfrastructureLocationResolver::class)->resolve($kind, $id);
    }

    private function consume(float $meters, ?InfrastructureLocation $location = null): InventoryTransaction
    {
        return app(InventoryService::class)->consumeInfrastructureRoll(
            $this->roll, $meters, $location ?? $this->loc(InfrastructureLocation::MINI_POP, $this->miniPop->id),
            [$this->technician], $this->fopTask, $this->technician,
        );
    }

    #[Test]
    public function roll_100_m_dipakai_35_m_bersisa_65_m_tanpa_saldo_terpasang(): void
    {
        $tx = $this->consume(35);

        $this->assertEquals(65, $this->roll->refresh()->length_remaining);
        $this->assertSame(RollStatus::IN_USE, $this->roll->status);
        $this->assertSame(InventoryTransactionType::DEPLOY, $tx->type);
        $this->assertSame($this->roll->id, $tx->roll_id);
        $this->assertEquals(35, $tx->qty);
        $this->assertSame($this->miniPop->id, $tx->to_mini_pop_id);
        $this->assertSame($this->cabang->id, $tx->to_pop_id);
        $this->assertSame(0, InfrastructureBalance::count());
    }

    #[Test]
    public function pemakaian_lanjut_mengurangi_sisa_dan_ledger_append_only(): void
    {
        $first = $this->consume(35);
        $this->consume(20);

        $this->assertEquals(45, $this->roll->refresh()->length_remaining);
        $this->assertSame(2, InventoryTransaction::where('roll_id', $this->roll->id)->count());
        $this->assertEquals(35, $first->fresh()->qty);
    }

    #[Test]
    public function sisa_kurang_ditolak_tanpa_mutasi(): void
    {
        $this->consume(80);

        try {
            $this->consume(25);
            $this->fail('Pemakaian melebihi sisa roll harus ditolak.');
        } catch (InvalidArgumentException) {
        }

        $this->assertEquals(20, $this->roll->refresh()->length_remaining);
        $this->assertSame(1, InventoryTransaction::where('roll_id', $this->roll->id)->count());
    }

    #[Test]
    public function roll_habis_menjadi_depleted(): void
    {
        $this->consume(100);

        $this->assertEquals(0, $this->roll->refresh()->length_remaining);
        $this->assertSame(RollStatus::DEPLETED, $this->roll->status);
    }

    #[Test]
    public function harga_per_meter_tetap_snapshot_intake_tanpa_repricing(): void
    {
        $tx = $this->consume(10);
        Item::query()->whereKey($this->roll->item_id)->update(['name' => 'Feeder 24C (harga baru)']);
        $second = $this->consume(5);

        $this->assertEquals(1500, $tx->unit_price_snapshot);
        $this->assertEquals(1500, $second->unit_price_snapshot);
        $this->assertEquals(1500, $this->roll->refresh()->unit_price_snapshot);
    }

    #[Test]
    public function kabel_boleh_dikerjakan_langsung_di_pop_cabang(): void
    {
        $tx = $this->consume(12, $this->loc(InfrastructureLocation::POP, $this->cabang->id));

        $this->assertSame($this->cabang->id, $tx->to_pop_id);
        $this->assertNull($tx->to_mini_pop_id);
        $this->assertNull($tx->to_network_node_id);
        $this->assertSame(InfrastructureLocation::POP.':'.$this->cabang->id,
            app(InventoryService::class)->transactionLocation($tx, 'to')?->key());
    }

    #[Test]
    public function kabel_di_slot_mencatat_lokasi_kerja_slot_tanpa_saldo_slot(): void
    {
        $tx = $this->consume(3, $this->loc(InfrastructureLocation::SLOT, $this->slot->id));

        $this->assertSame($this->slot->id, $tx->to_infrastructure_slot_id);
        $this->assertSame(0, InfrastructureBalance::count());
    }

    #[Test]
    public function lokasi_kerja_cabang_lain_ditolak(): void
    {
        $other = Pop::factory()->create(['type' => 'cabang']);

        $this->expectException(InvalidArgumentException::class);
        $this->consume(5, $this->loc(InfrastructureLocation::POP, $other->id));
    }

    #[Test]
    public function koreksi_jumlah_kabel_memulihkan_sisa_roll_tanpa_saldo(): void
    {
        $source = $this->consume(35);

        app(InfrastructureMaterialService::class)->correct(
            $source, $this->loc(InfrastructureLocation::MINI_POP, $this->miniPop->id), 20, $this->fopTask, $this->technician, 'Salah ukur', 'cbl-correct-1'
        );
        app(InfrastructureMaterialService::class)->correct(
            $source, $this->loc(InfrastructureLocation::MINI_POP, $this->miniPop->id), 20, $this->fopTask, $this->technician, 'Salah ukur', 'cbl-correct-1'
        );

        $this->assertEquals(80, $this->roll->refresh()->length_remaining);
        $this->assertSame(0, InfrastructureBalance::count());
        $this->assertSame(2, InventoryTransaction::where('type', InventoryTransactionType::ADJUSTMENT->value)->count());
    }

    #[Test]
    public function koreksi_lokasi_kerja_kabel_ke_pop_mencatat_lokasi_baru(): void
    {
        $source = $this->consume(35);

        app(InfrastructureMaterialService::class)->correct(
            $source, $this->loc(InfrastructureLocation::POP, $this->cabang->id), 35, $this->fopTask, $this->technician, 'Bukan di Mini POP', 'cbl-correct-2'
        );

        $positive = InventoryTransaction::where('type', InventoryTransactionType::ADJUSTMENT->value)->where('qty', '>', 0)->sole();
        $this->assertNull($positive->to_mini_pop_id);
        $this->assertSame($this->cabang->id, $positive->to_pop_id);
        $this->assertEquals(65, $this->roll->refresh()->length_remaining);
    }

    #[Test]
    public function barang_qty_non_kabel_di_pop_memakai_saldo_lokasi_pop(): void
    {
        $item = Item::create(['code' => 'KLEM', 'name' => 'Klem', 'unit' => 'pcs', 'item_category_id' => ItemCategory::firstOrFail()->id,
            'tracking_type' => 'quantity', 'ownership_mode' => 'infrastructure_asset']);
        TechnicianCustody::create(['technician_id' => $this->technician->id, 'issued_from_pop_id' => $this->cabang->id, 'item_id' => $item->id,
            'qty_remaining' => 10, 'status' => CustodyStatus::ISSUED, 'issued_at' => now()]);

        app(InventoryService::class)->deployQuantityAt([$this->technician], $item, 3, $this->loc(InfrastructureLocation::POP, $this->cabang->id), $this->fopTask, $this->technician);
        $lots = app(InventoryService::class)->deployQuantityAt([$this->technician], $item, 2, $this->loc(InfrastructureLocation::MINI_POP, $this->miniPop->id), $this->fopTask, $this->technician);

        $this->assertEquals(3, InfrastructureBalance::where('item_id', $item->id)->where('pop_id', $this->cabang->id)->whereNull('network_node_id')->value('qty'));
        $this->assertEquals(2, InfrastructureBalance::where('item_id', $item->id)->where('pop_id', $this->miniPop->id)->value('qty'));

        $source = InventoryTransaction::findOrFail($lots[0]['transaction_id']);
        app(InfrastructureMaterialService::class)->correct(
            $source, $this->loc(InfrastructureLocation::POP, $this->cabang->id), 2, $this->fopTask, $this->technician, 'Salah lokasi', 'qty-pop-1'
        );

        $this->assertEquals(0, InfrastructureBalance::where('item_id', $item->id)->where('pop_id', $this->miniPop->id)->value('qty'));
        $this->assertEquals(5, InfrastructureBalance::where('item_id', $item->id)->where('pop_id', $this->cabang->id)->value('qty'));
    }

    #[Test]
    public function port_tidak_dipakai_sebagai_lokasi_saldo_qty_pada_jalur_switch_kecuali_tercatat(): void
    {
        $switch = NetworkNode::factory()->create([
            'node_type_id' => NetworkNodeType::where('code', 'switch_mini_pop')->firstOrFail()->id,
            'anchor_pop_id' => $this->miniPop->id,
        ]);
        $positions = app(InfrastructurePositionService::class);
        $port = $positions->createPort($positions->ensureLogicalSlot($switch), 1);

        $tx = $this->consume(2, $this->loc(InfrastructureLocation::PORT, $port->id));

        $this->assertSame($port->id, $tx->to_infrastructure_port_id);
        $this->assertSame(0, InfrastructureBalance::count());
    }
}
