<?php

namespace Tests\Feature\Network;

use App\Enums\InfrastructurePortKind;
use App\Models\InfrastructureConnection;
use App\Models\InfrastructureLegacyMapping;
use App\Models\InfrastructurePort;
use App\Models\InfrastructureSlot;
use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\OltSlot;
use App\Models\Pop;
use App\Models\User;
use App\Services\InfrastructureLegacyBackfillService;
use App\Services\InfrastructureLegacyDecisionService;
use Database\Seeders\NetworkNodeTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Data nyata: kasus ambigu dipetakan EKSPLISIT oleh operator (§7.3, §16.4) —
 * dengan alasan, pelaku, dan audit. Tidak ada pemetaan otomatis.
 */
class InfrastructureLegacyDecisionTest extends TestCase
{
    use RefreshDatabase;

    private Pop $cabang;

    private Pop $miniPop;

    private NetworkNode $chassis;

    private NetworkNode $pon;

    private NetworkNode $odp;

    private NetworkNode $agg;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NetworkNodeTypeSeeder::class);
        $this->cabang = Pop::factory()->create(['type' => 'cabang']);
        $this->miniPop = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $this->cabang->id]);
        $this->operator = User::factory()->create();
        $this->chassis = $this->node('olt_chassis', ['anchor_pop_id' => $this->miniPop->id]);
        OltSlot::create(['chassis_node_id' => $this->chassis->id, 'number' => 1]);
        $this->pon = $this->node('pon_port', ['parent_id' => $this->chassis->id]);
        $feeder = $this->node('feeder', ['parent_id' => $this->pon->id]);
        $this->odp = $this->node('odp', ['parent_id' => $feeder->id]);
        $this->agg = $this->node('aggregation_switch', ['anchor_pop_id' => $this->cabang->id]);
        app(InfrastructureLegacyBackfillService::class)->run();
    }

    private function node(string $typeCode, array $placement): NetworkNode
    {
        return NetworkNode::factory()->create(array_merge([
            'node_type_id' => NetworkNodeType::where('code', $typeCode)->firstOrFail()->id, 'parent_id' => null, 'anchor_pop_id' => null,
        ], $placement));
    }

    private function mapping(string $type, int $id): InfrastructureLegacyMapping
    {
        return InfrastructureLegacyMapping::where('legacy_type', $type)->where('legacy_id', $id)->sole();
    }

    private function decisions(): InfrastructureLegacyDecisionService
    {
        return app(InfrastructureLegacyDecisionService::class);
    }

    #[Test]
    public function operator_memetakan_pon_node_ke_pon_kanonis_dengan_jejak_keputusan(): void
    {
        $slot = InfrastructureSlot::where('network_node_id', $this->chassis->id)->sole();

        $port = $this->decisions()->mapPonNode($this->mapping('pon_port_node', $this->pon->id), $slot, 3, $this->operator, 'Label fisik PON 3');

        $this->assertSame(InfrastructurePortKind::PON, $port->kind);
        $this->assertSame($this->pon->id, $port->legacy_network_node_id);
        $mapping = $this->mapping('pon_port_node', $this->pon->id);
        $this->assertSame('mapped', $mapping->status);
        $this->assertSame($port->id, $mapping->new_id);
        $this->assertSame($this->operator->id, $mapping->decided_by);
        $this->assertSame('Label fisik PON 3', $mapping->decision);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => InfrastructureLegacyMapping::class, 'auditable_id' => $mapping->id, 'action' => 'legacy_decision']);
    }

    #[Test]
    public function slot_dari_chassis_lain_ditolak(): void
    {
        $other = $this->node('olt_chassis', ['anchor_pop_id' => $this->miniPop->id]);
        $slot = InfrastructureSlot::create(['network_node_id' => $other->id, 'number' => 9, 'kind' => 'olt', 'status' => 'belum_didata', 'capacity_unit' => 'pon']);

        $this->expectException(InvalidArgumentException::class);
        $this->decisions()->mapPonNode($this->mapping('pon_port_node', $this->pon->id), $slot, 1, $this->operator, 'Salah chassis');
    }

    #[Test]
    public function operator_menyambung_uplink_odp_ke_pon(): void
    {
        $slot = InfrastructureSlot::where('network_node_id', $this->chassis->id)->sole();
        $port = $this->decisions()->mapPonNode($this->mapping('pon_port_node', $this->pon->id), $slot, 1, $this->operator, 'PON 1');

        $connection = $this->decisions()->mapOdpUplink($this->mapping('odp_uplink', $this->odp->id), $port, $this->operator, 'Dicek di lapangan', verified: true);

        $this->assertTrue($connection->isVerified());
        $this->assertSame($this->odp->id, $connection->target_network_node_id);
        $this->assertSame('mapped', $this->mapping('odp_uplink', $this->odp->id)->status);
        $this->assertSame($this->odp->fresh()->parent_id, NetworkNode::where('node_type_id', NetworkNodeType::where('code', 'feeder')->value('id'))->value('id'),
            'Parent lama tidak diubah — koneksi terpisah dari lokasi.');
    }

    #[Test]
    public function operator_memindah_switch_agregasi_ke_mini_pop_dalam_cabang_yang_sama(): void
    {
        $node = $this->decisions()->mapAggregationSwitch($this->mapping('aggregation_switch_node', $this->agg->id), $this->miniPop, $this->operator, 'Switch fisik di C1');

        $this->assertSame('switch_mini_pop', $node->nodeType->code);
        $this->assertSame($this->miniPop->id, $node->anchor_pop_id);
        $this->assertSame(1, InfrastructureSlot::where('network_node_id', $node->id)->where('is_logical', true)->count());
        $this->assertSame('mapped', $this->mapping('aggregation_switch_node', $this->agg->id)->status);
    }

    #[Test]
    public function mini_pop_cabang_lain_ditolak_untuk_switch(): void
    {
        $otherMini = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => Pop::factory()->create(['type' => 'cabang'])->id]);

        $this->expectException(InvalidArgumentException::class);
        $this->decisions()->mapAggregationSwitch($this->mapping('aggregation_switch_node', $this->agg->id), $otherMini, $this->operator, 'Salah cabang');
    }

    #[Test]
    public function keputusan_wajib_alasan_dan_tidak_bisa_diulang(): void
    {
        $mapping = $this->mapping('odp_uplink', $this->odp->id);

        try {
            $this->decisions()->reject($mapping, $this->operator, ' ');
            $this->fail('Alasan wajib.');
        } catch (InvalidArgumentException) {
        }

        $this->decisions()->reject($mapping, $this->operator, 'ODP sudah dibongkar');
        $this->assertSame('rejected', $mapping->fresh()->status);

        $this->expectException(InvalidArgumentException::class);
        $this->decisions()->reject($mapping->fresh(), $this->operator, 'Ulang');
    }

    #[Test]
    public function command_menampilkan_daftar_ambigu(): void
    {
        $this->artisan('infrastructure:legacy-mappings')
            ->expectsOutputToContain('pon_port_node')
            ->expectsOutputToContain('aggregation_switch_node')
            ->assertSuccessful();
        $this->assertSame(0, InfrastructureConnection::count());
        $this->assertSame(0, InfrastructurePort::count());
    }

    #[Test]
    public function command_menjalankan_keputusan_eksplisit(): void
    {
        $mapping = $this->mapping('aggregation_switch_node', $this->agg->id);

        $this->artisan('infrastructure:legacy-mappings', [
            '--decide' => $mapping->id, '--action' => 'map-switch', '--mini-pop' => $this->miniPop->id,
            '--operator' => $this->operator->id, '--reason' => 'Switch di rak C1',
        ])->assertSuccessful();

        $this->assertSame('mapped', $mapping->fresh()->status);
        $this->artisan('infrastructure:legacy-mappings', [
            '--decide' => $mapping->id, '--action' => 'reject', '--operator' => $this->operator->id, '--reason' => 'Ulang',
        ])->assertFailed();
    }
}
