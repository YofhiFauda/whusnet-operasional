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
use App\Services\InfrastructureLegacyBackfillService;
use App\Services\InfrastructurePositionService;
use Database\Seeders\NetworkNodeTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Backfill legacy hanya untuk hubungan yang terbukti FK + nomor unik (§7.3,
 * Task 3). Kasus ambigu masuk daftar manual dengan ID asal, POP, alasan —
 * tidak pernah disambung otomatis, tidak membuat Mini POP fiktif.
 */
class InfrastructureLegacyMappingTest extends TestCase
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

    private function node(string $typeCode, array $placement): NetworkNode
    {
        return NetworkNode::factory()->create(array_merge([
            'node_type_id' => NetworkNodeType::where('code', $typeCode)->firstOrFail()->id,
            'parent_id' => null,
            'anchor_pop_id' => null,
        ], $placement));
    }

    /**
     * Fixture cermin data aplikasi (audit Task 1): chassis di Mini POP,
     * slot tanpa kartu, PON node langsung anak chassis, feeder → ODP, dan
     * Switch agregasi langsung di POP Cabang.
     *
     * @return array<string, NetworkNode>
     */
    private function legacyFixture(): array
    {
        $chassis = $this->node('olt_chassis', ['anchor_pop_id' => $this->miniPop->id]);
        OltSlot::create(['chassis_node_id' => $chassis->id, 'number' => 1]);
        OltSlot::create(['chassis_node_id' => $chassis->id, 'number' => 2]);
        $pon = $this->node('pon_port', ['parent_id' => $chassis->id]);
        $feeder = $this->node('feeder', ['parent_id' => $pon->id]);
        $odp = $this->node('odp', ['parent_id' => $feeder->id]);
        $agg = $this->node('aggregation_switch', ['anchor_pop_id' => $this->cabang->id]);

        return compact('chassis', 'pon', 'feeder', 'odp', 'agg');
    }

    private function backfill(): InfrastructureLegacyBackfillService
    {
        return app(InfrastructureLegacyBackfillService::class);
    }

    #[Test]
    public function slot_olt_lama_yang_terbukti_fk_dan_nomor_dipetakan(): void
    {
        $nodes = $this->legacyFixture();

        $this->backfill()->run();

        $slots = InfrastructureSlot::query()->where('network_node_id', $nodes['chassis']->id)->orderBy('number')->get();
        $this->assertSame([1, 2], $slots->pluck('number')->all());
        $legacy = OltSlot::query()->where('chassis_node_id', $nodes['chassis']->id)->orderBy('number')->pluck('id')->all();
        $this->assertSame($legacy, $slots->pluck('legacy_olt_slot_id')->all());
        $this->assertDatabaseHas('infrastructure_legacy_mappings', [
            'legacy_type' => 'olt_slot', 'legacy_id' => $legacy[0], 'new_type' => 'infrastructure_slot',
            'new_id' => $slots[0]->id, 'status' => 'mapped', 'pop_id' => $this->cabang->id,
        ]);
    }

    #[Test]
    public function backfill_idempoten(): void
    {
        $this->legacyFixture();

        $this->backfill()->run();
        $this->backfill()->run();

        $this->assertSame(2, InfrastructureSlot::count());
        $this->assertSame(1, InfrastructureLegacyMapping::query()->where('legacy_type', 'pon_port_node')->count());
    }

    #[Test]
    public function pon_node_tanpa_slot_masuk_daftar_manual_tanpa_pon_baru(): void
    {
        $nodes = $this->legacyFixture();

        $this->backfill()->run();

        $this->assertSame(0, InfrastructurePort::count());
        $mapping = InfrastructureLegacyMapping::query()->where('legacy_type', 'pon_port_node')->where('legacy_id', $nodes['pon']->id)->firstOrFail();
        $this->assertSame('ambiguous', $mapping->status);
        $this->assertNull($mapping->new_id);
        $this->assertSame($this->cabang->id, $mapping->pop_id);
        $this->assertNotEmpty($mapping->reason);
    }

    #[Test]
    public function switch_agregasi_di_cabang_tidak_dipindah_dan_tidak_membuat_mini_pop(): void
    {
        $nodes = $this->legacyFixture();
        $popCount = Pop::count();

        $this->backfill()->run();

        $agg = $nodes['agg']->fresh();
        $this->assertSame($this->cabang->id, $agg->anchor_pop_id);
        $this->assertSame('aggregation_switch', $agg->nodeType->code);
        $this->assertSame($popCount, Pop::count());
        $this->assertDatabaseHas('infrastructure_legacy_mappings', ['legacy_type' => 'aggregation_switch_node', 'legacy_id' => $agg->id, 'status' => 'ambiguous']);
    }

    #[Test]
    public function odp_lama_tidak_disambung_otomatis_ke_pon(): void
    {
        $nodes = $this->legacyFixture();

        $this->backfill()->run();

        $this->assertSame(0, InfrastructureConnection::count());
        $this->assertSame($nodes['feeder']->id, $nodes['odp']->fresh()->parent_id);
        $this->assertDatabaseHas('infrastructure_legacy_mappings', ['legacy_type' => 'odp_uplink', 'legacy_id' => $nodes['odp']->id, 'status' => 'ambiguous']);
    }

    #[Test]
    public function slot_dengan_kartu_lama_dicatat_sebagai_isi_slot(): void
    {
        $nodes = $this->legacyFixture();
        $card = $this->node('olt_card', ['parent_id' => $nodes['chassis']->id]);
        OltSlot::query()->where('chassis_node_id', $nodes['chassis']->id)->where('number', 2)->update(['card_node_id' => $card->id]);

        $this->backfill()->run();

        $slot2 = InfrastructureSlot::query()->where('network_node_id', $nodes['chassis']->id)->where('number', 2)->firstOrFail();
        $this->assertDatabaseHas('infrastructure_legacy_mappings', [
            'legacy_type' => 'olt_card_node', 'legacy_id' => $card->id, 'new_type' => 'infrastructure_slot', 'new_id' => $slot2->id, 'status' => 'mapped',
        ]);
    }

    #[Test]
    public function rollback_menghapus_hasil_backfill_dan_data_lama_tetap(): void
    {
        $nodes = $this->legacyFixture();
        $this->backfill()->run();

        $this->backfill()->rollback();

        $this->assertSame(0, InfrastructureSlot::count());
        $this->assertSame(0, InfrastructureLegacyMapping::count());
        $this->assertSame(2, OltSlot::count());
        $this->assertNotNull($nodes['pon']->fresh());
    }

    #[Test]
    public function rollback_menolak_jika_slot_backfill_sudah_dipakai(): void
    {
        $this->legacyFixture();
        $this->backfill()->run();
        app(InfrastructurePositionService::class)->createPort(InfrastructureSlot::firstOrFail(), 1, InfrastructurePortKind::PON);

        $this->expectException(RuntimeException::class);
        $this->backfill()->rollback();
    }

    #[Test]
    public function migrasi_forward_rollback_forward_aman(): void
    {
        $this->legacyFixture();
        $migration = require database_path('migrations/2026_10_21_000002_create_infrastructure_connections_and_mappings.php');
        // RefreshDatabase sudah menjalankan up() pada DB kosong — turunkan
        // dulu supaya up() berikutnya membackfill fixture legacy di atas.
        $migration->down();

        $migration->up();
        $this->assertSame(2, InfrastructureSlot::count());

        $migration->down();
        $this->assertSame(0, InfrastructureSlot::count());
        $this->assertFalse(DB::getSchemaBuilder()->hasTable('infrastructure_connections'));
        $this->assertSame(2, OltSlot::count());

        $migration->up();
        $this->assertSame(2, InfrastructureSlot::count());
    }
}
