<?php

namespace Tests\Feature\Network;

use App\Enums\InfrastructurePortKind;
use App\Enums\InfrastructureSlotKind;
use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use Database\Seeders\NetworkNodeTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Helper posisi pada NetworkNode (ADHOC-213): Slot/PON/Port Switch adalah
 * baris `network_nodes`, jadi model harus bisa mengenali dan menavigasinya.
 */
class NodePositionModelTest extends TestCase
{
    use RefreshDatabase;

    private function device(string $typeCode): NetworkNode
    {
        $this->seed(NetworkNodeTypeSeeder::class);

        return NetworkNode::factory()->create([
            'node_type_id' => NetworkNodeType::where('code', $typeCode)->value('id'),
            'anchor_pop_id' => Pop::factory()->create(['type' => 'cabang'])->id,
            'code' => strtoupper($typeCode).'-1',
        ]);
    }

    #[Test]
    public function slot_pon_dan_odp_membentuk_rantai_parent(): void
    {
        $olt = $this->device('olt_chassis');
        $slot = NetworkNode::factory()->slot($olt, 3)->create();
        $pon = NetworkNode::factory()->pon($slot, 5)->create();
        $odp = NetworkNode::factory()->create([
            'node_type_id' => NetworkNodeType::where('code', 'odp')->value('id'),
            'parent_id' => $pon->id,
            'anchor_pop_id' => null,
        ]);

        $this->assertSame('OLT_CHASSIS-1-S3', $slot->code);
        $this->assertSame('OLT_CHASSIS-1-S3-P5', $pon->code);
        $this->assertNull($slot->anchor_pop_id);
        $this->assertTrue($slot->isSlot());
        $this->assertTrue($pon->isPon());
        $this->assertTrue($pon->isPort());
        $this->assertTrue($pon->isPosition());
        $this->assertFalse($odp->isPosition());
        $this->assertSame($olt->id, $slot->parent->id);
        $this->assertSame($slot->id, $pon->parent->id);
        $this->assertSame($pon->id, $odp->parent->id);
        $this->assertSame([3], $olt->slots()->pluck('number')->all());
        $this->assertSame(InfrastructureSlotKind::OLT, $slot->slotKind());
        $this->assertSame(InfrastructurePortKind::PON, $pon->portKind());
    }

    #[Test]
    public function ports_pada_slot_olt_hanya_mengembalikan_pon(): void
    {
        $olt = $this->device('olt_chassis');
        $slot = NetworkNode::factory()->slot($olt, 1)->create();
        NetworkNode::factory()->pon($slot, 2)->create();
        NetworkNode::factory()->pon($slot, 1)->create();
        NetworkNode::factory()->create([
            'node_type_id' => NetworkNodeType::where('code', 'odp')->value('id'),
            'parent_id' => $slot->id,
            'anchor_pop_id' => null,
        ]);

        $this->assertSame([1, 2], $slot->positionPorts()->pluck('number')->all());

        $switch = $this->device('switch_mini_pop');
        $switchSlot = NetworkNode::factory()->slot($switch, 1)->create();
        $port = NetworkNode::factory()->switchPort($switchSlot, 7)->create();

        $this->assertSame('switch_slot', $switchSlot->nodeType->code);
        $this->assertSame(InfrastructureSlotKind::SWITCH, $switchSlot->slotKind());
        $this->assertSame(InfrastructurePortKind::SWITCH, $port->portKind());
        $this->assertSame([$port->id], $switchSlot->positionPorts()->pluck('id')->all());
    }

    #[Test]
    public function uplink_pon_odp_adalah_parent_bila_pon_dan_null_bila_anchor_pop(): void
    {
        $olt = $this->device('olt_chassis');
        $pon = NetworkNode::factory()->pon(NetworkNode::factory()->slot($olt, 1)->create(), 1)->create();
        $odp = NetworkNode::factory()->create([
            'node_type_id' => NetworkNodeType::where('code', 'odp')->value('id'),
            'parent_id' => $pon->id,
            'anchor_pop_id' => null,
        ]);
        $odpDiPop = NetworkNode::factory()->create([
            'node_type_id' => NetworkNodeType::where('code', 'odp')->value('id'),
        ]);

        $this->assertSame($pon->id, $odp->uplinkPon()?->id);
        $this->assertNull($odpDiPop->uplinkPon());
    }

    #[Test]
    public function display_name_per_jenis(): void
    {
        $olt = $this->device('olt_chassis');
        $slot = NetworkNode::factory()->slot($olt, 3)->create();
        $pon = NetworkNode::factory()->pon($slot, 5)->create();
        $switchSlot = NetworkNode::factory()->slot($this->device('switch_mini_pop'), 2)->create();
        $port = NetworkNode::factory()->switchPort($switchSlot, 7)->create();

        $this->assertSame('Slot 3', $slot->displayName());
        $this->assertSame('PON 5', $pon->displayName());
        $this->assertSame('Port 7', $port->displayName());
        $this->assertSame($olt->code, $olt->displayName());
    }
}
