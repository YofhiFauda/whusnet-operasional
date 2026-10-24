<?php

namespace Tests\Feature;

use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use App\Services\NetworkNodeService;
use Database\Seeders\NetworkNodeTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class NetworkNodePlacementRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_managed_type_rule_rejects_invalid_parent_and_accepts_valid_parent(): void
    {
        $pop = Pop::factory()->create(['type' => 'cabang']);
        $odcType = NetworkNodeType::create(['code' => 'odc', 'name' => 'ODC', 'has_ports' => true]);
        $odpType = NetworkNodeType::create(['code' => 'odp', 'name' => 'ODP', 'has_ports' => true]);
        $odc = NetworkNode::factory()->create(['anchor_pop_id' => $pop->id, 'node_type_id' => $odcType->id]);
        $odpParent = NetworkNode::factory()->create(['anchor_pop_id' => $pop->id, 'node_type_id' => $odpType->id]);

        DB::table('network_node_type_rules')->insert([
            'child_type_id' => $odpType->id,
            'parent_type_id' => $odcType->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(NetworkNodeService::class)->assertTypePlacement($odpType, $odc, null);

        $this->expectException(InvalidArgumentException::class);
        app(NetworkNodeService::class)->assertTypePlacement($odpType, $odpParent, null);
    }

    public function test_tipe_anak_dengan_aturan_parent_wajib_anak_bukan_anchor_pop_langsung(): void
    {
        // 'olt_card' dihapus dari seeder master 2026-10-23 (ADHOC-212, bukan
        // bagian hierarki resmi lagi). Subjek tes ini sebenarnya mesin
        // `assertTypePlacement()` menegakkan aturan "anak wajib anchor ke
        // tipe node tertentu" — dites pakai tipe+aturan buatan sendiri,
        // bentuknya identik dengan aturan lama olt_card→olt_chassis.
        $this->seed(NetworkNodeTypeSeeder::class);
        $mini = Pop::factory()->create(['type' => 'mini_pop']);
        $chassisType = NetworkNodeType::where('code', 'olt_chassis')->firstOrFail();
        $cardType = NetworkNodeType::create(['code' => 'test-card', 'name' => 'Kartu Uji', 'has_ports' => true]);
        DB::table('network_node_type_rules')->insert([
            'child_type_id' => $cardType->id,
            'parent_type_id' => $chassisType->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $chassis = NetworkNode::factory()->create(['anchor_pop_id' => $mini->id, 'node_type_id' => $chassisType->id]);

        app(NetworkNodeService::class)->assertTypePlacement($cardType, $chassis, null);

        $this->expectException(InvalidArgumentException::class);
        app(NetworkNodeService::class)->assertTypePlacement($cardType, null, $mini);
    }

    public function test_olt_chassis_boleh_anchor_mini_pop_atau_cabang_langsung(): void
    {
        $this->seed(NetworkNodeTypeSeeder::class);
        $chassisType = NetworkNodeType::where('code', 'olt_chassis')->firstOrFail();
        $mini = Pop::factory()->create(['type' => 'mini_pop']);
        $branch = Pop::factory()->create(['type' => 'cabang']);

        app(NetworkNodeService::class)->assertTypePlacement($chassisType, null, $mini);
        app(NetworkNodeService::class)->assertTypePlacement($chassisType, null, $branch);
    }

    public function test_backbone_feeder_dan_drop_cable_bukan_tipe_node_lagi(): void
    {
        // Keputusan user 21-10-2026: ketiganya kini uplink POP, koneksi
        // PON → ODP, dan assignment port ODP + pemakaian roll — bukan node.
        $this->seed(NetworkNodeTypeSeeder::class);
        $this->seed(NetworkNodeTypeSeeder::class);

        $this->assertSame(0, NetworkNodeType::whereIn('code', ['backbone', 'feeder', 'drop_cable'])->count());
        $this->assertTrue(NetworkNodeType::where('code', 'odp')->exists());
    }

    public function test_migrasi_penghapusan_tipe_lama_menolak_bila_masih_dipakai_node(): void
    {
        $migration = require database_path('migrations/2026_10_21_000008_remove_backbone_feeder_drop_cable_node_types.php');
        $migration->down();
        $feeder = NetworkNodeType::where('code', 'feeder')->firstOrFail();
        NetworkNode::factory()->create(['node_type_id' => $feeder->id]);

        $this->expectException(\RuntimeException::class);
        $migration->up();
    }

    public function test_satu_mini_pop_boleh_menaungi_beberapa_chassis(): void
    {
        $this->seed(NetworkNodeTypeSeeder::class);
        $mini = Pop::factory()->create(['type' => 'mini_pop']);
        $chassisType = NetworkNodeType::where('code', 'olt_chassis')->firstOrFail();
        NetworkNode::factory()->count(4)->create(['anchor_pop_id' => $mini->id, 'node_type_id' => $chassisType->id]);

        $this->assertSame(4, NetworkNode::where('anchor_pop_id', $mini->id)->where('node_type_id', $chassisType->id)->count());
    }

    /**
     * `NetworkNodeTypeSeeder` mencabut rule anchor Cabang/Mini POP langsung
     * untuk `odp` dan menggantinya dengan rule parent wajib `olt_pon`
     * (ADHOC-213) — ODP baru TIDAK BOLEH lagi ditambatkan ke POP langsung,
     * beda dari ODP lama yang dibiarkan migrasi reset dummy (D2, lihat
     * `odp_lama_anchor_pop_bisa_dipasang_ke_pon` di `NetworkNodeMoveTest`).
     */
    public function test_odp_baru_tanpa_pon_ditolak(): void
    {
        $this->seed(NetworkNodeTypeSeeder::class);
        $cabang = Pop::factory()->create(['type' => 'cabang']);
        $odpType = NetworkNodeType::where('code', 'odp')->firstOrFail();

        $this->expectException(InvalidArgumentException::class);
        app(NetworkNodeService::class)->assertTypePlacement($odpType, null, $cabang);
    }

    public function test_existing_types_get_default_rules_from_forward_migration(): void
    {
        $odc = NetworkNodeType::create(['code' => 'odc', 'name' => 'ODC', 'has_ports' => true]);
        $odp = NetworkNodeType::create(['code' => 'odp', 'name' => 'ODP', 'has_ports' => true]);

        $migration = require database_path('migrations/2026_10_13_140545_backfill_default_network_node_type_rules.php');
        $migration->up();
        $migration->up();

        $this->assertSame(1, DB::table('network_node_type_rules')
            ->where('child_type_id', $odp->id)
            ->where('parent_type_id', $odc->id)
            ->count());
    }
}
