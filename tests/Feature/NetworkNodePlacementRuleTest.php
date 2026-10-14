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

    public function test_olt_card_wajib_anak_chassis_bukan_anchor_pop_langsung(): void
    {
        $this->seed(NetworkNodeTypeSeeder::class);
        $mini = Pop::factory()->create(['type' => 'mini_pop']);
        $cardType = NetworkNodeType::where('code', 'olt_card')->firstOrFail();
        $chassisType = NetworkNodeType::where('code', 'olt_chassis')->firstOrFail();
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

    public function test_feeder_boleh_jadi_anak_chassis_dan_odc_boleh_jadi_anak_feeder(): void
    {
        $this->seed(NetworkNodeTypeSeeder::class);
        $mini = Pop::factory()->create(['type' => 'mini_pop']);
        $chassisType = NetworkNodeType::where('code', 'olt_chassis')->firstOrFail();
        $feederType = NetworkNodeType::where('code', 'feeder')->firstOrFail();
        $odcType = NetworkNodeType::where('code', 'odc')->firstOrFail();
        $chassis = NetworkNode::factory()->create(['anchor_pop_id' => $mini->id, 'node_type_id' => $chassisType->id]);
        $feeder = NetworkNode::factory()->create(['parent_id' => $chassis->id, 'node_type_id' => $feederType->id]);

        app(NetworkNodeService::class)->assertTypePlacement($feederType, $chassis, null);
        app(NetworkNodeService::class)->assertTypePlacement($odcType, $feeder, null);
    }

    public function test_satu_mini_pop_boleh_menaungi_beberapa_chassis(): void
    {
        $this->seed(NetworkNodeTypeSeeder::class);
        $mini = Pop::factory()->create(['type' => 'mini_pop']);
        $chassisType = NetworkNodeType::where('code', 'olt_chassis')->firstOrFail();
        NetworkNode::factory()->count(4)->create(['anchor_pop_id' => $mini->id, 'node_type_id' => $chassisType->id]);

        $this->assertSame(4, NetworkNode::where('anchor_pop_id', $mini->id)->where('node_type_id', $chassisType->id)->count());
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
