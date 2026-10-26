<?php

namespace Tests\Feature\Network;

use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Services\InfrastructurePositionService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\NetworkNodeTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regresi final review ADHOC-213 (C2): Master Node memuat SEMUA node dalam
 * scope — termasuk ribuan Slot/PON — lalu merender satu blok inspector per
 * node dengan select relokasi berisi semua node (O(N²)), plus `find()` per
 * leluhur di `effectiveLocation()`. Penjaga: struktur 16 Slot × 16 PON (+ODP
 * di bawah PON) tidak boleh menambah node ke daftar inspector maupun ke
 * jumlah query. Kalau posisi kembali dimuat, tes ini merah lewat tiga jalur:
 * isi `$nodes`, blok inspector di HTML, dan selisih jumlah query.
 */
class MasterNodeIndexTidakMemuatPosisiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $cabang;

    /** @var array<string, int> query terbanyak di pemanggilan terakhir, untuk pesan gagal */
    private array $lastQueries = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([FeatureSeeder::class, ActionSeeder::class, RoleSeeder::class,
            PermissionSeeder::class, NetworkNodeTypeSeeder::class, RolePermissionSeeder::class]);
        $this->owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $this->cabang = Pop::factory()->create(['type' => 'cabang', 'name' => 'Cabang Skala']);
    }

    private function olt(string $code): NetworkNode
    {
        return NetworkNode::create(['node_type_id' => NetworkNodeType::where('code', 'olt_chassis')->value('id'),
            'anchor_pop_id' => $this->cabang->id, 'parent_id' => null, 'code' => $code, 'name' => $code, 'status' => 'active']);
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function indexQueryCountAndHtml(): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($this->owner)->get(route('network.nodes.index'));
        $log = DB::getQueryLog();
        DB::disableQueryLog();
        $response->assertOk();
        $this->lastQueries = collect($log)->countBy('query')->sortDesc()->take(5)->all();

        return [count($log), $response->getContent()];
    }

    #[Test]
    public function struktur_16x16_tidak_menambah_inspector_maupun_query(): void
    {
        $olt = $this->olt('OLT-SKALA');
        [$baselineQueries] = $this->indexQueryCountAndHtml();

        $positions = app(InfrastructurePositionService::class);
        $positions->generateStructure($olt, 16, 16, 64, $this->owner);
        $pons = NetworkNode::query()->whereHas('nodeType', fn ($query) => $query->where('code', 'olt_pon'))->get();
        $this->assertCount(256, $pons);
        $odpTypeId = NetworkNodeType::where('code', 'odp')->value('id');
        // NetworkNode::create, BUKAN factory: definisi factory selalu membuat
        // satu POP baru per node, dan jumlah POP memang (sah) menambah query
        // per-POP — itu akan mengaburkan apa yang diuji di sini.
        $pons->take(20)->each(fn (NetworkNode $pon, int $i) => NetworkNode::create([
            'node_type_id' => $odpTypeId, 'parent_id' => $pon->id, 'anchor_pop_id' => null,
            'code' => "ODP-SKALA-{$i}", 'name' => "ODP Skala {$i}", 'status' => 'active',
        ]));

        $response = $this->actingAs($this->owner)->get(route('network.nodes.index'));
        $response->assertOk();
        $nodes = $response->viewData('nodes');
        $this->assertSame([$olt->id], $nodes->pluck('id')->all(), 'Master Node hanya memuat perangkat, bukan Slot/PON/ODP anak PON.');
        $this->assertCount(1, $response->viewData('relocateTargets'));

        [$queries, $html] = $this->indexQueryCountAndHtml();
        $this->assertStringContainsString('inspector-node-'.$olt->id, $html);
        $this->assertStringNotContainsString('inspector-node-'.$pons->first()->id.'"', $html);
        $this->assertStringNotContainsString('ODP-SKALA-0', $html);
        // Panel Manajemen Node OLT kembali (umpan balik user 2026-10-26) tapi
        // LAZY: index hanya membawa kait URL-nya, kartu Slot/PON tidak dirender.
        $this->assertStringContainsString('data-device-panel-url="'.route('network.nodes.device-panel', $olt).'"', $html);
        $this->assertStringNotContainsString('data-component-slot=', $html);
        $this->assertStringNotContainsString('data-component-port=', $html);
        // Tambahan 16 Slot + 256 PON + 20 ODP tidak boleh menambah query
        // (paling banyak selisih kecil konstan, bukan linear terhadap ~290 node).
        $this->assertLessThanOrEqual($baselineQueries + 3, $queries,
            "Query index naik dari {$baselineQueries} ke {$queries} setelah struktur 16x16 dibuat. Teratas: ".json_encode($this->lastQueries));
        // Halaman tetap kecil: 290 node posisi tidak dirender jadi blok apa pun.
        $this->assertLessThan(400_000, strlen($html));
    }

    #[Test]
    public function inspector_tidak_naik_rantai_induk_lewat_query_per_node(): void
    {
        // 30 node bertingkat (perangkat → node anak non-posisi): effectiveLocation()
        // tiap blok harus memakai induk yang sudah dimuat, bukan find() per leluhur.
        $root = $this->olt('OLT-RANTAI');
        [$baseline] = $this->indexQueryCountAndHtml();
        $typeId = NetworkNodeType::factory()->create(['code' => 'test-rantai', 'has_ports' => false])->id;
        $parent = $root;
        for ($i = 0; $i < 30; $i++) {
            $parent = NetworkNode::create(['node_type_id' => $typeId, 'parent_id' => $parent->id,
                'anchor_pop_id' => null, 'code' => "RANTAI-{$i}", 'name' => "Rantai {$i}", 'status' => 'active']);
        }

        [$queries, $html] = $this->indexQueryCountAndHtml();
        $this->assertStringContainsString('inspector-node-'.$parent->id, $html);
        $this->assertLessThanOrEqual($baseline + 3, $queries, "Query index naik dari {$baseline} ke {$queries} untuk rantai 30 node. Teratas: ".json_encode($this->lastQueries));
    }
}
