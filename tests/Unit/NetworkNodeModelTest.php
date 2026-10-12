<?php

namespace Tests\Unit;

use App\Models\NetworkNode;
use App\Models\Pop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NetworkNodeModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function root_node_anchored_to_cabang_resolves_itself(): void
    {
        $cabang = Pop::factory()->create(['type' => 'cabang', 'name' => 'Cabang Jetis']);

        $node = NetworkNode::factory()->create([
            'anchor_pop_id' => $cabang->id,
            'parent_id' => null,
            'name' => 'ODC-01',
        ]);

        $this->assertSame($cabang->id, $node->resolvePop()?->id);
        $this->assertSame('Cabang Jetis → ODC-01', $node->breadcrumbLabel());
    }

    #[Test]
    public function root_node_anchored_to_mini_pop_resolves_to_parent_cabang(): void
    {
        $cabang = Pop::factory()->create(['type' => 'cabang', 'name' => 'Cabang Jetis']);
        $miniPop = Pop::factory()->create([
            'type' => 'mini_pop',
            'name' => 'Mini POP C1',
            'parent_id' => $cabang->id,
        ]);

        $node = NetworkNode::factory()->create([
            'anchor_pop_id' => $miniPop->id,
            'parent_id' => null,
            'name' => 'ODC-01',
        ]);

        $this->assertSame($cabang->id, $node->resolvePop()?->id);
        $this->assertSame('Cabang Jetis → Mini POP C1 → ODC-01', $node->breadcrumbLabel());
    }

    #[Test]
    public function bottom_node_in_three_level_chain_resolves_same_pop_as_top(): void
    {
        $cabang = Pop::factory()->create(['type' => 'cabang', 'name' => 'Cabang Jetis']);

        $odc = NetworkNode::factory()->create([
            'anchor_pop_id' => $cabang->id,
            'parent_id' => null,
            'name' => 'ODC-01',
        ]);
        $odp = NetworkNode::factory()->child($odc)->create(['name' => 'ODP-01']);
        $splitter = NetworkNode::factory()->child($odp)->create(['name' => 'Splitter-01']);

        $this->assertSame($odc->resolvePop()?->id, $splitter->resolvePop()?->id);
        $this->assertSame($cabang->id, $splitter->resolvePop()?->id);
    }

    #[Test]
    public function in_pops_scope_returns_anchor_and_all_descendants_only(): void
    {
        $cabangA = Pop::factory()->create(['type' => 'cabang', 'name' => 'Cabang A']);
        $cabangB = Pop::factory()->create(['type' => 'cabang', 'name' => 'Cabang B']);

        $odcA = NetworkNode::factory()->create(['anchor_pop_id' => $cabangA->id, 'parent_id' => null]);
        $odpA = NetworkNode::factory()->child($odcA)->create();
        $splitterA = NetworkNode::factory()->child($odpA)->create();

        $odcB = NetworkNode::factory()->create(['anchor_pop_id' => $cabangB->id, 'parent_id' => null]);

        $ids = NetworkNode::query()->inPops([$cabangA->id])->pluck('id')->all();

        $this->assertEqualsCanonicalizing(
            [$odcA->id, $odpA->id, $splitterA->id],
            $ids,
        );
        $this->assertNotContains($odcB->id, $ids);
    }

    #[Test]
    public function children_relation_is_not_recursive(): void
    {
        $cabang = Pop::factory()->create(['type' => 'cabang']);

        $odc = NetworkNode::factory()->create(['anchor_pop_id' => $cabang->id, 'parent_id' => null]);
        $odp = NetworkNode::factory()->child($odc)->create();
        NetworkNode::factory()->child($odp)->create();

        $children = $odc->children;

        $this->assertCount(1, $children);
        $this->assertSame($odp->id, $children->first()->id);
    }

    #[Test]
    public function xor_placement_rule_is_enforced_by_service_not_model(): void
    {
        // Aturan XOR (parent_id XOR anchor_pop_id — tidak boleh dua-duanya
        // terisi, tidak boleh dua-duanya kosong) SENGAJA tidak ditegakkan di
        // model/migration ini. Model ini hanya struktur data; penegakan XOR
        // adalah tanggung jawab NetworkNodeService::assertPlacement() di
        // Task 6, lengkap dengan test-nya sendiri di sana.
        $this->markTestIncomplete('XOR validation belongs to NetworkNodeService — Task 6');
    }
}
