<?php

namespace Tests\Feature\Network;

use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use App\Services\InfrastructureLocationResolver;
use App\Support\InfrastructureLocation;
use Database\Seeders\NetworkNodeTypeSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Resolver lokasi sesudah Slot/PON/Port Switch menjadi node: leluhur murni
 * dari rantai `parent_id` (ODP → PON → Slot → OLT → Mini POP → Cabang).
 */
class InfrastructureLocationResolverTest extends TestCase
{
    use RefreshDatabase;

    private Pop $cabang;

    private Pop $mini;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NetworkNodeTypeSeeder::class);
        $this->cabang = Pop::factory()->create(['type' => 'cabang', 'name' => 'Cabang C1']);
        $this->mini = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $this->cabang->id, 'name' => 'C1-M1']);
    }

    private function anchored(string $typeCode, Pop $pop, string $code): NetworkNode
    {
        return NetworkNode::factory()->create([
            'node_type_id' => NetworkNodeType::where('code', $typeCode)->firstOrFail()->id,
            'anchor_pop_id' => $pop->id, 'code' => $code, 'name' => $code,
        ]);
    }

    private function resolver(): InfrastructureLocationResolver
    {
        return app(InfrastructureLocationResolver::class);
    }

    #[Test]
    public function ancestors_odp_adalah_pon_slot_olt_lalu_pop(): void
    {
        $olt = $this->anchored('olt_chassis', $this->mini, 'OLT-A');
        $slot = NetworkNode::factory()->slot($olt, 3)->create();
        $pon = NetworkNode::factory()->pon($slot, 2)->create();
        $odp = NetworkNode::factory()->child($pon)->create([
            'node_type_id' => NetworkNodeType::where('code', 'odp')->firstOrFail()->id, 'code' => 'ODP-1', 'name' => 'ODP-1',
        ]);

        $location = $this->resolver()->resolve(InfrastructureLocation::NODE, $odp->id);

        $this->assertSame([
            'pop:'.$this->cabang->id, 'mini_pop:'.$this->mini->id, 'node:'.$olt->id,
            'slot:'.$slot->id, 'port:'.$pon->id, 'node:'.$odp->id,
        ], $location->ancestorKeys());
        $this->assertSame(['Cabang C1', 'C1-M1', 'OLT-A', 'Slot 3', 'PON 2', 'ODP-1'], array_column($location->ancestors, 'label'));
        $this->assertSame('odp', $location->nodeTypeCode());
        $this->assertSame($this->cabang->id, $location->cabangId);
    }

    #[Test]
    public function resolve_slot_dengan_id_node_non_slot_ditolak(): void
    {
        $olt = $this->anchored('olt_chassis', $this->mini, 'OLT-A');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('bukan Slot');
        $this->resolver()->resolve(InfrastructureLocation::SLOT, $olt->id);
    }

    #[Test]
    public function resolve_port_dengan_id_node_non_port_ditolak(): void
    {
        $olt = $this->anchored('olt_chassis', $this->mini, 'OLT-A');
        $slot = NetworkNode::factory()->slot($olt, 1)->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('bukan PON atau Port Switch');
        $this->resolver()->resolve(InfrastructureLocation::PORT, $slot->id);
    }

    #[Test]
    public function resolve_slot_dengan_id_tidak_ada_tetap_model_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);
        $this->resolver()->resolve(InfrastructureLocation::SLOT, 999999);
    }

    #[Test]
    public function scope_cabang_dihitung_dari_rantai_parent(): void
    {
        $olt = $this->anchored('olt_chassis', $this->mini, 'OLT-A');
        $slot = NetworkNode::factory()->slot($olt, 1)->create();
        $pon = NetworkNode::factory()->pon($slot, 1)->create();

        $slotLocation = $this->resolver()->forSlot($slot);
        $ponLocation = $this->resolver()->forPort($pon);

        $this->assertSame(InfrastructureLocation::SLOT, $slotLocation->kind);
        $this->assertSame($slot->id, $slotLocation->id);
        $this->assertSame(InfrastructureLocation::PORT, $ponLocation->kind);
        $this->assertSame($pon->id, $ponLocation->id);
        foreach ([$slotLocation, $ponLocation] as $location) {
            $this->assertSame($this->cabang->id, $location->cabangId);
            $this->assertSame($this->mini->id, $location->deepestPopId());
            $this->assertSame($olt->id, $location->ancestorId(InfrastructureLocation::NODE));
        }
        $this->assertTrue($slotLocation->contains($ponLocation));
    }

    #[Test]
    public function rantai_port_switch_naik_lewat_slot_switch_ke_mini_pop(): void
    {
        $switch = $this->anchored('switch_mini_pop', $this->mini, 'SW-A');
        $slot = NetworkNode::factory()->slot($switch, 1)->create();
        $port = NetworkNode::factory()->switchPort($slot, 4)->create();

        $location = $this->resolver()->resolve(InfrastructureLocation::PORT, $port->id);

        $this->assertSame([
            'pop:'.$this->cabang->id, 'mini_pop:'.$this->mini->id, 'node:'.$switch->id,
            'slot:'.$slot->id, 'port:'.$port->id,
        ], $location->ancestorKeys());
        $this->assertSame('Port 4', $location->label());
        $this->assertSame($this->cabang->id, $location->cabangId);
    }

    #[Test]
    public function odp_lama_yang_menempel_langsung_di_pop_tetap_terselesaikan(): void
    {
        $odp = $this->anchored('odp', $this->mini, 'ODP-LEGACY');

        $location = $this->resolver()->forNode($odp);

        $this->assertSame(['pop:'.$this->cabang->id, 'mini_pop:'.$this->mini->id, 'node:'.$odp->id], $location->ancestorKeys());
        $this->assertSame($this->cabang->id, $location->cabangId);
    }

    #[Test]
    public function rantai_parent_melingkar_ditolak(): void
    {
        $a = $this->anchored('server', $this->mini, 'ODC-A');
        $b = NetworkNode::factory()->child($a)->create(['code' => 'ODC-B', 'name' => 'ODC-B']);
        NetworkNode::withoutEvents(fn () => $a->forceFill(['parent_id' => $b->id, 'anchor_pop_id' => null])->save());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('melingkar');
        $this->resolver()->forNode($b->fresh());
    }
}
