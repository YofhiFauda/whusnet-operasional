<?php

namespace Tests\Feature\Network;

use App\Enums\InfrastructureCapacityUnit;
use App\Enums\InfrastructurePortKind;
use App\Enums\InfrastructureRecordStatus;
use App\Enums\InfrastructureSlotKind;
use App\Models\InfrastructurePort;
use App\Models\InfrastructureSlot;
use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use App\Services\InfrastructurePositionService;
use App\Services\NetworkNodeService;
use Database\Seeders\NetworkNodeTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Model posisi kanonis §2/§16.2: Slot milik OLT/Switch, PON milik Slot OLT,
 * Port Switch milik Slot Switch. Posisi berdiri sendiri — Slot boleh kosong.
 */
class InfrastructurePositionTest extends TestCase
{
    use RefreshDatabase;

    private Pop $cabang;

    private Pop $miniPop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NetworkNodeTypeSeeder::class);
        $this->cabang = Pop::factory()->create(['type' => 'cabang']);
        $this->miniPop = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $this->cabang->id]);
    }

    private function device(string $typeCode, ?Pop $anchor = null, string $code = ''): NetworkNode
    {
        $type = NetworkNodeType::where('code', $typeCode)->firstOrFail();

        return NetworkNode::factory()->create([
            'node_type_id' => $type->id,
            'anchor_pop_id' => ($anchor ?? $this->miniPop)->id,
            'code' => $code !== '' ? $code : strtoupper($typeCode).'-'.fake()->unique()->numerify('###'),
        ]);
    }

    private function service(): InfrastructurePositionService
    {
        return app(InfrastructurePositionService::class);
    }

    #[Test]
    public function tipe_switch_mini_pop_tersedia_dengan_port_dan_aggregation_switch_tidak_ditawarkan(): void
    {
        $switch = NetworkNodeType::where('code', 'switch_mini_pop')->firstOrFail();
        $this->assertTrue($switch->has_ports);
        $this->assertTrue($switch->is_active);

        $legacy = NetworkNodeType::where('code', 'aggregation_switch')->firstOrFail();
        $this->assertFalse($legacy->is_active, 'aggregation_switch tetap terbaca tetapi tidak ditawarkan.');
    }

    #[Test]
    public function satu_mini_pop_boleh_punya_banyak_olt_dan_switch(): void
    {
        $nodes = app(NetworkNodeService::class);
        foreach (['olt_chassis', 'olt_chassis', 'switch_mini_pop', 'switch_mini_pop'] as $code) {
            $nodes->assertTypePlacement(NetworkNodeType::where('code', $code)->firstOrFail(), null, $this->miniPop);
        }

        $this->expectException(InvalidArgumentException::class);
        $nodes->assertTypePlacement(NetworkNodeType::where('code', 'switch_mini_pop')->firstOrFail(), null, $this->cabang);
    }

    #[Test]
    public function nomor_slot_unik_per_perangkat_tetapi_boleh_sama_antar_perangkat(): void
    {
        $oltA = $this->device('olt_chassis');
        $oltB = $this->device('olt_chassis');

        $slotA = $this->service()->createSlot($oltA, 1);
        $slotB = $this->service()->createSlot($oltB, 1);

        $this->assertSame(InfrastructureSlotKind::OLT, $slotA->kind);
        $this->assertSame($oltB->id, $slotB->network_node_id);
        $this->assertSame(InfrastructureRecordStatus::BELUM_DIDATA, $slotA->status);
        $this->assertSame(InfrastructureCapacityUnit::PON, $slotA->capacity_unit);

        $this->expectException(InvalidArgumentException::class);
        $this->service()->createSlot($oltA, 1);
    }

    #[Test]
    public function nomor_port_unik_per_slot(): void
    {
        $slot = $this->service()->createSlot($this->device('olt_chassis'), 1);
        $pon = $this->service()->createPort($slot, 1, InfrastructurePortKind::PON);

        $this->assertSame(InfrastructurePortKind::PON, $pon->kind);
        $this->assertSame(InfrastructureCapacityUnit::PELANGGAN_AKTIF, $pon->capacity_unit);

        $this->expectException(InvalidArgumentException::class);
        $this->service()->createPort($slot, 1, InfrastructurePortKind::PON);
    }

    #[Test]
    public function pon_di_slot_switch_ditolak(): void
    {
        $slot = $this->service()->createSlot($this->device('switch_mini_pop'), 1);
        $this->assertSame(InfrastructureSlotKind::SWITCH, $slot->kind);

        $this->expectException(InvalidArgumentException::class);
        $this->service()->createPort($slot, 1, InfrastructurePortKind::PON);
    }

    #[Test]
    public function port_switch_di_slot_olt_ditolak(): void
    {
        $slot = $this->service()->createSlot($this->device('olt_chassis'), 1);

        $this->expectException(InvalidArgumentException::class);
        $this->service()->createPort($slot, 1, InfrastructurePortKind::SWITCH);
    }

    #[Test]
    public function slot_hanya_untuk_olt_atau_switch(): void
    {
        $odp = $this->device('odp');

        $this->expectException(InvalidArgumentException::class);
        $this->service()->createSlot($odp, 1);
    }

    #[Test]
    public function slot_kosong_tanpa_kartu_dan_port_sah(): void
    {
        $slot = $this->service()->createSlot($this->device('olt_chassis'), 3);

        $this->assertDatabaseHas('infrastructure_slots', ['id' => $slot->id, 'number' => 3]);
        $this->assertSame(0, $slot->ports()->count());
    }

    #[Test]
    public function switch_port_tetap_memakai_slot_1_logis(): void
    {
        $switch = $this->device('switch_mini_pop');

        $slot = $this->service()->ensureLogicalSlot($switch);
        $again = $this->service()->ensureLogicalSlot($switch);

        $this->assertSame($slot->id, $again->id);
        $this->assertSame(1, $slot->number);
        $this->assertTrue($slot->is_logical);
        $this->assertSame('Slot internal/logis', $slot->label);
        $this->assertSame(InfrastructureCapacityUnit::PORT, $slot->capacity_unit);

        $port = $this->service()->createPort($slot, 4, InfrastructurePortKind::SWITCH);
        $this->assertNull($port->capacity_unit);
    }

    #[Test]
    public function slot_logis_hanya_untuk_switch(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service()->ensureLogicalSlot($this->device('olt_chassis'));
    }

    #[Test]
    public function kode_slot_dan_port_unik_dalam_induk_saja(): void
    {
        $oltA = $this->device('olt_chassis');
        $oltB = $this->device('olt_chassis');
        $this->service()->createSlot($oltA, 1, ['code' => 'S1']);
        $slotB = $this->service()->createSlot($oltB, 1, ['code' => 'S1']);
        $this->service()->createPort($slotB, 1, InfrastructurePortKind::PON, ['code' => 'P1']);

        $this->assertSame(2, InfrastructureSlot::where('code', 'S1')->count());
        $this->assertSame(1, InfrastructurePort::where('code', 'P1')->count());

        $this->expectException(InvalidArgumentException::class);
        $this->service()->createSlot($oltA, 2, ['code' => 'S1']);
    }
}
