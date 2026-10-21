<?php

namespace Tests\Feature\Warehouse;

use App\Enums\InfrastructureLocationKind;
use App\Enums\InfrastructurePortKind;
use App\Enums\InventoryTransactionType;
use App\Enums\SerialStatus;
use App\Models\FopTask;
use App\Models\InfrastructurePort;
use App\Models\InfrastructureSlot;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Services\InfrastructureLocationResolver;
use App\Services\InfrastructurePositionService;
use App\Services\InventoryAdjustmentService;
use App\Services\InventoryService;
use App\Services\NetworkNodeService;
use App\Support\InfrastructureLocation;
use Database\Seeders\NetworkNodeTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Lokasi aset berserial tepat satu posisi (§16.1): Mini POP, node, Slot, atau
 * Port — masing-masing FK sendiri, saling eksklusif dengan Gudang, custody,
 * dan pelanggan. Ledger mencatat asal/tujuan dengan FK yang sama.
 */
class ExactInfrastructureAssetLocationTest extends TestCase
{
    use RefreshDatabase;

    private Pop $cabang;

    private Pop $miniPop;

    private User $technician;

    private FopTask $fopTask;

    private NetworkNode $olt;

    private NetworkNode $switch;

    private InfrastructureSlot $slot1;

    private InfrastructureSlot $slot2;

    private InfrastructurePort $switchPort;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NetworkNodeTypeSeeder::class);
        $this->cabang = Pop::factory()->create(['type' => 'cabang']);
        $this->miniPop = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $this->cabang->id]);
        $this->technician = User::factory()->create();
        $this->fopTask = FopTask::create([
            'task_number' => 'TFOP-LOC-0001', 'task_date' => now(), 'category' => 'INFR REQ', 'tugas' => 'Uji',
            'pop_id' => $this->cabang->id, 'issue' => 'Uji', 'status' => 'draft', 'priority' => 'Medium',
        ]);

        $positions = app(InfrastructurePositionService::class);
        $this->olt = $this->node('olt_chassis');
        $this->switch = $this->node('switch_mini_pop');
        $this->slot1 = $positions->createSlot($this->olt, 1);
        $this->slot2 = $positions->createSlot($this->olt, 2);
        $this->switchPort = $positions->createPort($positions->ensureLogicalSlot($this->switch), 4, InfrastructurePortKind::SWITCH);
    }

    private function node(string $typeCode): NetworkNode
    {
        return NetworkNode::factory()->create([
            'node_type_id' => NetworkNodeType::where('code', $typeCode)->firstOrFail()->id,
            'anchor_pop_id' => $this->miniPop->id,
        ]);
    }

    private function item(string $code, string $categoryCode, string $ownership, ?InfrastructureLocationKind $kind, ?string $nodeTypeCode = null): Item
    {
        $category = ItemCategory::firstOrCreate(['code' => $categoryCode], ['name' => $categoryCode, 'default_unit' => 'pcs']);

        return Item::create([
            'code' => $code, 'name' => "Item {$code}", 'unit' => 'pcs', 'item_category_id' => $category->id,
            'tracking_type' => 'serialized', 'ownership_mode' => $ownership,
            'infrastructure_location_kind' => $kind?->value,
            'infrastructure_node_type_id' => $nodeTypeCode ? NetworkNodeType::where('code', $nodeTypeCode)->value('id') : null,
        ]);
    }

    private function issued(Item $item, string $serial): InventorySerial
    {
        return InventorySerial::create([
            'item_id' => $item->id, 'serial_number' => $serial, 'status' => SerialStatus::ISSUED->value,
            'current_technician_id' => $this->technician->id, 'issued_from_pop_id' => $this->cabang->id,
        ]);
    }

    private function loc(string $kind, int $id): InfrastructureLocation
    {
        return app(InfrastructureLocationResolver::class)->resolve($kind, $id);
    }

    private function inventory(): InventoryService
    {
        return app(InventoryService::class);
    }

    private function deploy(InventorySerial $serial, InfrastructureLocation $location): InventoryTransaction
    {
        return $this->inventory()->deploySerialAt($serial, $location, $this->fopTask, [$this->technician], $this->technician);
    }

    #[Test]
    public function custody_ke_mini_pop_lalu_dicabut_menjaga_ledger_dua_arah(): void
    {
        $item = $this->item('RACK-1', 'infra_rack', 'infrastructure_asset', InfrastructureLocationKind::MINI_POP);
        $serial = $this->issued($item, 'SN-123');

        $deploy = $this->deploy($serial, $this->loc(InfrastructureLocation::MINI_POP, $this->miniPop->id));

        $serial->refresh();
        $this->assertSame(SerialStatus::DEPLOYED, $serial->status);
        $this->assertSame($this->miniPop->id, $serial->current_mini_pop_id);
        $this->assertNull($serial->current_pop_id);
        $this->assertNull($serial->current_network_node_id);
        $this->assertNull($serial->current_technician_id);
        $this->assertSame(InventoryTransactionType::DEPLOY, $deploy->type);
        $this->assertSame($this->miniPop->id, $deploy->to_mini_pop_id);
        $this->assertNull($deploy->to_network_node_id);
        $this->assertSame($this->cabang->id, $deploy->to_pop_id);
        $this->assertSame($this->technician->id, $deploy->from_technician_id);
        $this->assertTrue($this->miniPop->deployedSerials()->whereKey($serial->id)->exists());

        $retrieve = $this->inventory()->retrieveSerialFromLocation($serial, $this->fopTask, $this->technician,
            expectedLocation: $this->loc(InfrastructureLocation::MINI_POP, $this->miniPop->id));

        $serial->refresh();
        $this->assertSame(SerialStatus::ISSUED, $serial->status);
        $this->assertNull($serial->current_mini_pop_id);
        $this->assertSame($this->technician->id, $serial->current_technician_id);
        $this->assertSame($this->miniPop->id, $retrieve->from_mini_pop_id);
        $this->assertSame($this->cabang->id, $retrieve->from_pop_id);
    }

    #[Test]
    public function deploy_langsung_di_olt_memakai_fk_node(): void
    {
        $item = $this->item('OLT-ASSET', 'olt_module', 'infrastructure_asset', InfrastructureLocationKind::NODE, 'olt_chassis');
        $serial = $this->issued($item, 'SN-OLT');

        $tx = $this->deploy($serial, $this->loc(InfrastructureLocation::NODE, $this->olt->id));

        $this->assertSame($this->olt->id, $serial->fresh()->current_network_node_id);
        $this->assertNull($serial->fresh()->current_infrastructure_slot_id);
        $this->assertSame($this->olt->id, $tx->to_network_node_id);
    }

    #[Test]
    public function kartu_di_slot_1_tidak_bisa_dicabut_dari_slot_2(): void
    {
        $card = $this->item('CARD-1', 'olt_module', 'infrastructure_asset', InfrastructureLocationKind::SLOT);
        $serial = $this->issued($card, 'SN-CARD');
        $tx = $this->deploy($serial, $this->loc(InfrastructureLocation::SLOT, $this->slot1->id));

        $this->assertSame($this->slot1->id, $serial->fresh()->current_infrastructure_slot_id);
        $this->assertNull($serial->fresh()->current_network_node_id);
        $this->assertSame($this->slot1->id, $tx->to_infrastructure_slot_id);
        $this->assertSame(1, $this->slot1->deployedSerials()->count());

        try {
            $this->inventory()->retrieveSerialFromLocation($serial, $this->fopTask, $this->technician,
                expectedLocation: $this->loc(InfrastructureLocation::SLOT, $this->slot2->id));
            $this->fail('SN Slot 1 tidak boleh dicabut lewat aksi Slot 2.');
        } catch (InvalidArgumentException) {
        }
        $this->assertSame($this->slot1->id, $serial->fresh()->current_infrastructure_slot_id);
    }

    #[Test]
    public function slot_hanya_menerima_satu_kartu_aktif(): void
    {
        $card = $this->item('CARD-2', 'olt_module', 'infrastructure_asset', InfrastructureLocationKind::SLOT);
        $this->deploy($this->issued($card, 'SN-C1'), $this->loc(InfrastructureLocation::SLOT, $this->slot1->id));

        $this->expectException(InvalidArgumentException::class);
        $this->deploy($this->issued($card, 'SN-C2'), $this->loc(InfrastructureLocation::SLOT, $this->slot1->id));
    }

    #[Test]
    public function sfp_aset_perusahaan_di_port_switch_memakai_fk_port(): void
    {
        $sfp = $this->item('SFP-1', 'sfp_transceiver', 'company_asset', InfrastructureLocationKind::PORT);
        $serial = $this->issued($sfp, 'SN-SFP');

        $tx = $this->deploy($serial, $this->loc(InfrastructureLocation::PORT, $this->switchPort->id));

        $this->assertSame($this->switchPort->id, $serial->fresh()->current_infrastructure_port_id);
        $this->assertNull($serial->fresh()->current_network_node_id);
        $this->assertSame($this->switchPort->id, $tx->to_infrastructure_port_id);
        $this->assertSame(1, $this->switchPort->deployedSerials()->count());
    }

    #[Test]
    public function sn_tidak_bisa_terpasang_di_dua_tempat(): void
    {
        $item = $this->item('RACK-2', 'infra_rack', 'infrastructure_asset', InfrastructureLocationKind::MINI_POP);
        $serial = $this->issued($item, 'SN-DUA');
        $this->deploy($serial, $this->loc(InfrastructureLocation::MINI_POP, $this->miniPop->id));

        try {
            $this->deploy($serial, $this->loc(InfrastructureLocation::MINI_POP, $this->miniPop->id));
            $this->fail('SN DEPLOYED tidak boleh di-deploy lagi.');
        } catch (InvalidArgumentException) {
        }

        $fresh = $serial->fresh();
        $filled = array_filter([$fresh->current_pop_id, $fresh->current_technician_id, $fresh->customer_id, $fresh->current_mini_pop_id,
            $fresh->current_network_node_id, $fresh->current_infrastructure_slot_id, $fresh->current_infrastructure_port_id]);
        $this->assertCount(1, $filled);
    }

    #[Test]
    public function item_tidak_kompatibel_ditolak(): void
    {
        $cases = [
            'kartu ke mini pop' => [$this->item('X1', 'olt_module', 'infrastructure_asset', InfrastructureLocationKind::SLOT), InfrastructureLocation::MINI_POP, $this->miniPop->id],
            'slot olt bukan modul olt' => [$this->item('X2', 'sfp_transceiver', 'infrastructure_asset', InfrastructureLocationKind::SLOT), InfrastructureLocation::SLOT, $this->slot1->id],
            'modem pelanggan' => [$this->item('X3', 'modem_ont', 'installable', null), InfrastructureLocation::MINI_POP, $this->miniPop->id],
            'aset perusahaan tanpa posisi eksplisit' => [$this->item('X4', 'switch_jaringan', 'company_asset', null), InfrastructureLocation::NODE, $this->switch->id],
            'pop cabang bukan lokasi aset' => [$this->item('X5', 'infra_rack', 'infrastructure_asset', InfrastructureLocationKind::MINI_POP), InfrastructureLocation::POP, $this->cabang->id],
        ];

        foreach ($cases as $label => [$item, $kind, $id]) {
            $serial = $this->issued($item, 'SN-'.$item->code);
            try {
                $this->deploy($serial, $this->loc($kind, $id));
                $this->fail("Kasus '{$label}' seharusnya ditolak.");
            } catch (InvalidArgumentException) {
                $this->assertSame(SerialStatus::ISSUED, $serial->fresh()->status, $label);
            }
        }
    }

    #[Test]
    public function lokasi_cabang_lain_ditolak(): void
    {
        $otherMini = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => Pop::factory()->create(['type' => 'cabang'])->id]);
        $item = $this->item('RACK-3', 'infra_rack', 'infrastructure_asset', InfrastructureLocationKind::MINI_POP);

        $this->expectException(InvalidArgumentException::class);
        $this->deploy($this->issued($item, 'SN-LAIN'), $this->loc(InfrastructureLocation::MINI_POP, $otherMini->id));
    }

    #[Test]
    public function deploy_serial_lama_ke_node_tetap_berjalan(): void
    {
        $item = $this->item('OLT-LAMA', 'olt_module', 'infrastructure_asset', null, 'olt_chassis');
        $serial = $this->issued($item, 'SN-LAMA');

        $this->inventory()->deploySerial($serial, $this->olt, $this->fopTask, [$this->technician], $this->technician);

        $this->assertSame($this->olt->id, $serial->fresh()->current_network_node_id);
    }

    #[Test]
    public function adjustment_serial_slot_mengosongkan_posisi_dan_mencatat_asal(): void
    {
        $card = $this->item('CARD-3', 'olt_module', 'infrastructure_asset', InfrastructureLocationKind::SLOT);
        $serial = $this->issued($card, 'SN-ADJ');
        $this->deploy($serial, $this->loc(InfrastructureLocation::SLOT, $this->slot1->id));

        $tx = app(InventoryAdjustmentService::class)->adjustSerialStatus($serial->fresh(), SerialStatus::DAMAGED, 'jatuh_kena_air', $this->technician, null, 'warehouse/evidence/damaged/card.jpg');

        $this->assertNull($serial->fresh()->current_infrastructure_slot_id);
        $this->assertSame($this->slot1->id, $tx->from_infrastructure_slot_id);
        $this->assertSame($this->cabang->id, $tx->from_pop_id);
    }

    #[Test]
    public function olt_dengan_kartu_terpasang_di_slot_tidak_boleh_pindah_lintas_cabang(): void
    {
        $this->seed(RoleSeeder::class);
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $card = $this->item('CARD-4', 'olt_module', 'infrastructure_asset', InfrastructureLocationKind::SLOT);
        $this->deploy($this->issued($card, 'SN-MOVE'), $this->loc(InfrastructureLocation::SLOT, $this->slot1->id));
        $otherMini = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => Pop::factory()->create(['type' => 'cabang'])->id]);

        $this->expectException(InvalidArgumentException::class);
        app(NetworkNodeService::class)->move($this->olt, null, $otherMini, $owner);
    }
}
