<?php

namespace Tests\Unit;

use App\Models\AuditLog;
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
    public function resolve_pop_works_on_collection_without_eager_loading(): void
    {
        $cabang = Pop::factory()->create(['type' => 'cabang', 'name' => 'Cabang Jetis']);

        NetworkNode::factory()->create([
            'anchor_pop_id' => $cabang->id,
            'parent_id' => null,
            'name' => 'ODC-01',
        ]);
        NetworkNode::factory()->create([
            'anchor_pop_id' => $cabang->id,
            'parent_id' => null,
            'name' => 'ODC-02',
        ]);

        $nodes = NetworkNode::all();
        $this->assertCount(2, $nodes);

        foreach ($nodes as $node) {
            $this->assertSame($cabang->id, $node->resolvePop()?->id);
        }
    }

    #[Test]
    public function resolve_pop_and_breadcrumb_and_effective_location_work_on_nested_children_collection_without_eager_loading(): void
    {
        $cabang = Pop::factory()->create(['type' => 'cabang', 'name' => 'Cabang Jetis']);

        $odc = NetworkNode::factory()->create([
            'anchor_pop_id' => $cabang->id,
            'parent_id' => null,
            'name' => 'ODC-01',
        ]);
        $odp = NetworkNode::factory()->child($odc)->create(['name' => 'ODP-01']);
        $splitter = NetworkNode::factory()->child($odp)->create(['name' => 'Splitter-01']);

        $nodes = NetworkNode::whereIn('id', [$odc->id, $odp->id, $splitter->id])->get();
        $this->assertCount(3, $nodes);

        foreach ($nodes as $node) {
            $this->assertSame($cabang->id, $node->resolvePop()?->id);
            $this->assertNotEmpty($node->breadcrumbLabel());
            $this->assertNotEmpty($node->effectiveLocation());
        }
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
    public function empty_pop_scope_returns_no_nodes(): void
    {
        NetworkNode::factory()->create();

        $this->assertSame(0, NetworkNode::query()->inPops([])->count());
    }

    /**
     * Node baru wajib teraudit (padanan `Odp` lama), TAPI update generik
     * tidak — `NetworkNodeService::move()` sudah menulis audit 'move'
     * eksplisit; kalau trait ikut audit 'updated', satu pemindahan jadi dua
     * baris.
     */
    #[Test]
    public function creation_is_audited_but_generic_update_is_not(): void
    {
        $node = NetworkNode::factory()->create();

        $created = AuditLog::where('auditable_type', NetworkNode::class)->where('auditable_id', $node->id)->where('action', 'create')->sole();
        $this->assertSame('Node Jaringan', $created->module);

        $node->update(['name' => 'Nama Baru']);

        $this->assertSame(0, AuditLog::where('auditable_type', NetworkNode::class)->where('auditable_id', $node->id)->where('action', 'update')->count());
    }
}
