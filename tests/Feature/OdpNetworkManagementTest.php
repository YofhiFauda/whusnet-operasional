<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\FopUnifiedDashboardService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\NetworkNodeTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OdpNetworkManagementTest extends TestCase
{
    use RefreshDatabase;

    private int $odpTypeId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(NetworkNodeTypeSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->odpTypeId = (int) NetworkNodeType::where('code', 'odp')->value('id');
    }

    private function makeNode(int $popId, string $code, string $name): NetworkNode
    {
        return NetworkNode::create([
            'anchor_pop_id' => $popId,
            'node_type_id' => $this->odpTypeId,
            'parent_id' => null,
            'code' => $code,
            'name' => $name,
            'status' => 'active',
        ]);
    }

    public function test_odp_memiliki_port_child_dan_red_zone_dihitung_dari_status_port(): void
    {
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $pop = Pop::create(['code' => 'ODP-A', 'name' => 'POP ODP A', 'type' => 'cabang', 'status' => 'active']);

        $this->actingAs($owner)->post(route('network.nodes.store'), [
            'node_type_id' => $this->odpTypeId,
            'anchor_pop_id' => $pop->id,
            'code' => 'odp-test-01',
            'name' => 'ODP Test 01',
            'capacity' => 8,
        ])->assertRedirect(route('network.nodes.index'));

        $node = NetworkNode::where('code', 'ODP-TEST-01')->firstOrFail();
        $this->assertSame($pop->id, $node->anchor_pop_id);
        $this->assertNull($node->parent_id);
        $this->assertSame(8, $node->ports()->count());
        $this->assertSame(0, $node->ports()->where('is_used', true)->count());

        foreach ($node->ports()->orderBy('number')->take(7)->get() as $port) {
            $this->actingAs($owner)->put(route('network.node-ports.update', $port), [
                'is_used' => 1,
            ])->assertRedirect(route('network.nodes.index'));
        }

        $summary = app(FopUnifiedDashboardService::class)->getOdpSummary(true, []);
        $this->assertSame(1, $summary['total_odps']);
        $this->assertSame(1, $summary['red_zone_count']);
        $this->assertSame('ODP-TEST-01', $summary['red_zone'][0]['code']);
        $this->assertSame(7, $summary['red_zone'][0]['used']);
        $this->assertSame(8, $summary['red_zone'][0]['capacity']);
        $this->assertSame(88, $summary['red_zone'][0]['percent']);
        $this->actingAs($owner)->get(route('network.nodes.index'))->assertSee('Red Zone');
    }

    public function test_fop_hanya_bisa_mengelola_odp_di_pop_scope_nya(): void
    {
        $allowedPop = Pop::create(['code' => 'ODP-B', 'name' => 'POP ODP B', 'type' => 'cabang', 'status' => 'active']);
        $foreignPop = Pop::create(['code' => 'ODP-C', 'name' => 'POP ODP C', 'type' => 'cabang', 'status' => 'active']);
        $role = Role::where('code', 'fop')->firstOrFail();
        $fop = User::factory()->create(['role_id' => $role->id]);
        $scope = UserRoleScope::create(['user_id' => $fop->id, 'role_id' => $role->id, 'scope_type' => ScopeType::SELECTED_POP]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $allowedPop->id]);

        $this->makeNode($allowedPop->id, 'ODP-B-1', 'Lokal');
        $foreignNode = $this->makeNode($foreignPop->id, 'ODP-C-1', 'Asing');
        $foreignPort = $foreignNode->ports()->create(['number' => 1, 'is_used' => true]);

        $this->actingAs($fop)->get(route('network.nodes.index'))
            ->assertOk()
            ->assertSee('ODP-B-1')
            ->assertDontSee('ODP-C-1');

        $this->actingAs($fop)->post(route('network.nodes.store'), [
            'node_type_id' => $this->odpTypeId,
            'anchor_pop_id' => $foreignPop->id,
            'code' => 'ODP-C-2',
            'name' => 'Asing Baru',
            'capacity' => 8,
        ])->assertSessionHasErrors('anchor_pop_id');

        $this->actingAs($fop)->post(route('network.nodes.ports.add', $foreignNode), [
            'additional_capacity' => 2,
        ])->assertForbidden();
        $this->actingAs($fop)->put(route('network.node-ports.update', $foreignPort), [
            'is_used' => 0,
        ])->assertForbidden();

        $this->assertTrue($foreignPort->fresh()->is_used);
        $this->assertSame(1, $foreignNode->ports()->count());
        $this->assertSame(0, app(FopUnifiedDashboardService::class)->getOdpSummary(false, [$allowedPop->id])['red_zone_count']);
    }

    public function test_sales_tidak_dapat_mengakses_master_odp(): void
    {
        $sales = User::factory()->create(['role_id' => Role::where('code', 'sales')->firstOrFail()->id]);
        $this->actingAs($sales)->get(route('network.nodes.index'))->assertForbidden();
    }

    public function test_kapasitas_odp_bisa_ditambah_tanpa_menghapus_port_lama(): void
    {
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $pop = Pop::create(['code' => 'ODP-D', 'name' => 'POP ODP D', 'type' => 'cabang', 'status' => 'active']);
        $node = $this->makeNode($pop->id, 'ODP-D-1', 'Perlu Ekspansi');
        $usedPort = $node->ports()->create(['number' => 1, 'is_used' => true]);

        $this->actingAs($owner)->post(route('network.nodes.ports.add', $node), [
            'additional_capacity' => 3,
        ])->assertRedirect(route('network.nodes.index'));

        $this->assertSame([1, 2, 3, 4], $node->ports()->orderBy('number')->pluck('number')->all());
        $this->assertTrue($usedPort->fresh()->is_used);
    }

    public function test_atasan_dapat_melihat_odp_tetapi_tidak_dapat_mengubah_port(): void
    {
        $atasan = User::factory()->create(['role_id' => Role::where('code', 'atasan')->firstOrFail()->id]);
        $pop = Pop::create(['code' => 'ODP-E', 'name' => 'POP ODP E', 'type' => 'cabang', 'status' => 'active']);
        $node = $this->makeNode($pop->id, 'ODP-E-1', 'ODP Pantau');
        $port = $node->ports()->create(['number' => 1]);

        $this->actingAs($atasan)->get(route('network.nodes.index'))
            ->assertOk()
            ->assertSee('Master Node Jaringan &amp; Port', false)
            ->assertSee('ODP-E-1');

        $this->actingAs($atasan)->put(route('network.node-ports.update', $port), [
            'is_used' => 1,
        ])->assertForbidden();
        $this->assertFalse($port->fresh()->is_used);
    }

    /**
     * Batas tambah port sekarang dari `max_ports` tipe node, bukan 128
     * hardcode — tipe dengan batas lebih kecil harus menolak lebih awal.
     */
    public function test_tambah_port_ditolak_kalau_melebihi_max_ports_tipe_node(): void
    {
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $pop = Pop::create(['code' => 'ODP-F', 'name' => 'POP ODP F', 'type' => 'cabang', 'status' => 'active']);
        NetworkNodeType::where('id', $this->odpTypeId)->update(['max_ports' => 4]);
        $node = $this->makeNode($pop->id, 'ODP-F-1', 'Kecil');
        $node->ports()->create(['number' => 1]);
        $node->ports()->create(['number' => 2]);

        $this->actingAs($owner)->post(route('network.nodes.ports.add', $node), [
            'additional_capacity' => 3,
        ])->assertStatus(422);

        $this->assertSame(2, $node->ports()->count());
    }

    /**
     * Tipe tanpa port (`has_ports=false`, mis. Core Router) tidak boleh
     * dibuat lewat endpoint ini — form-nya membuat N port sekaligus.
     */
    public function test_store_menolak_tipe_node_tanpa_port(): void
    {
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $pop = Pop::create(['code' => 'ODP-G', 'name' => 'POP ODP G', 'type' => 'cabang', 'status' => 'active']);

        $this->actingAs($owner)->post(route('network.nodes.store'), [
            'node_type_id' => NetworkNodeType::where('code', 'core_router')->value('id'),
            'anchor_pop_id' => $pop->id,
            'code' => 'CR-01',
            'name' => 'Core',
            'capacity' => 8,
        ])->assertSessionHasErrors('node_type_id');

        $this->assertDatabaseMissing('network_nodes', ['code' => 'CR-01']);
    }

    /**
     * Ambang Red Zone dibaca per tipe (`red_zone_threshold`, persen 0-100),
     * bukan 85% hardcode: 7/10 = 70% masuk Red Zone kalau ambang tipe 70,
     * dan keluar lagi kalau ambangnya dinaikkan ke 80.
     */
    public function test_red_zone_memakai_ambang_per_tipe_node(): void
    {
        $pop = Pop::create(['code' => 'ODP-H', 'name' => 'POP ODP H', 'type' => 'cabang', 'status' => 'active']);
        $node = $this->makeNode($pop->id, 'ODP-H-1', 'Ambang');
        for ($number = 1; $number <= 10; $number++) {
            $node->ports()->create(['number' => $number, 'is_used' => $number <= 7]);
        }

        NetworkNodeType::where('id', $this->odpTypeId)->update(['red_zone_threshold' => 70]);
        $this->assertSame(1, app(FopUnifiedDashboardService::class)->getOdpSummary(true, [])['red_zone_count']);

        NetworkNodeType::where('id', $this->odpTypeId)->update(['red_zone_threshold' => 80]);
        $this->assertSame(0, app(FopUnifiedDashboardService::class)->getOdpSummary(true, [])['red_zone_count']);

        // Tipe yang tidak memantau kapasitas tidak dihitung sama sekali.
        NetworkNodeType::where('id', $this->odpTypeId)->update(['monitors_capacity' => false]);
        $this->assertSame(0, app(FopUnifiedDashboardService::class)->getOdpSummary(true, [])['total_odps']);
    }

    /**
     * Reshape data lama: tabel `odps`/`odp_ports` (file create-nya sudah
     * dihapus, jadi dibuat ulang manual di sini dengan bentuk aslinya) harus
     * pindah utuh ke `network_nodes`/`network_node_ports`, Feature + kode
     * permission `network_odps` di-rename (role_permissions tetap nempel),
     * lalu tabel lama di-drop.
     */
    public function test_migrasi_reshape_memindahkan_odps_lama_ke_network_nodes(): void
    {
        Schema::create('odps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pop_id')->constrained()->restrictOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->timestamps();
        });
        Schema::create('odp_ports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('odp_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->boolean('is_used')->default(false);
            $table->timestamps();
            $table->unique(['odp_id', 'number']);
        });

        // Simulasi DB lama: Feature `network_odps` + permission-nya menempel
        // di role, belum ada Feature `network_nodes`.
        $nodesFeatureId = DB::table('features')->where('code', 'network_nodes')->value('id');
        DB::table('role_permissions')->whereIn('permission_id', DB::table('permissions')->where('feature_id', $nodesFeatureId)->pluck('id'))->delete();
        DB::table('permissions')->where('feature_id', $nodesFeatureId)->delete();
        DB::table('features')->where('id', $nodesFeatureId)->update(['code' => 'network_odps', 'name' => 'Master ODP dan Port']);
        DB::table('permissions')->insertGetId([
            'feature_id' => $nodesFeatureId,
            'action_id' => DB::table('actions')->where('code', 'view')->value('id'),
            'code' => 'network_odps.view',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $atasan = Role::where('code', 'atasan')->firstOrFail();
        DB::table('role_permissions')->insert([
            'role_id' => $atasan->id,
            'permission_id' => DB::table('permissions')->where('code', 'network_odps.view')->value('id'),
        ]);

        $pop = Pop::create(['code' => 'ODP-M', 'name' => 'POP Migrasi', 'type' => 'cabang', 'status' => 'active']);
        $createdAt = '2026-10-01 08:00:00';
        $odpId = DB::table('odps')->insertGetId([
            'pop_id' => $pop->id, 'code' => 'ODP-LAMA-1', 'name' => 'ODP Lama',
            'created_at' => $createdAt, 'updated_at' => $createdAt,
        ]);
        DB::table('odp_ports')->insert([
            ['odp_id' => $odpId, 'number' => 1, 'is_used' => true, 'created_at' => $createdAt, 'updated_at' => $createdAt],
            ['odp_id' => $odpId, 'number' => 2, 'is_used' => false, 'created_at' => $createdAt, 'updated_at' => $createdAt],
        ]);

        (require database_path('migrations/2026_10_12_110300_reshape_odps_into_network_nodes.php'))->up();

        $node = NetworkNode::where('code', 'ODP-LAMA-1')->firstOrFail();
        $this->assertSame($pop->id, $node->anchor_pop_id);
        $this->assertNull($node->parent_id);
        $this->assertSame('odp', $node->nodeType->code);
        $this->assertSame('ODP Lama', $node->name);
        $this->assertSame('active', $node->status);
        $this->assertNull($node->capacity);
        $this->assertSame($createdAt, $node->created_at->format('Y-m-d H:i:s'));
        $this->assertSame([[1, true], [2, false]], $node->ports->map(fn ($port) => [$port->number, $port->is_used])->all());

        $this->assertFalse(Schema::hasTable('odps'));
        $this->assertFalse(Schema::hasTable('odp_ports'));

        $this->assertDatabaseMissing('features', ['code' => 'network_odps']);
        $this->assertDatabaseHas('features', ['id' => $nodesFeatureId, 'code' => 'network_nodes']);
        $this->assertDatabaseMissing('permissions', ['code' => 'network_odps.view']);
        $permissionId = DB::table('permissions')->where('code', 'network_nodes.view')->value('id');
        $this->assertNotNull($permissionId);
        $this->assertDatabaseHas('role_permissions', ['role_id' => $atasan->id, 'permission_id' => $permissionId]);
    }

    /**
     * Instalasi baru tidak pernah punya `odps` — migration harus diam saja
     * (tidak melempar walau belum ada apa-apa untuk dipindah).
     */
    public function test_migrasi_reshape_aman_di_instalasi_baru_tanpa_tabel_odps(): void
    {
        $this->assertFalse(Schema::hasTable('odps'));

        (require database_path('migrations/2026_10_12_110300_reshape_odps_into_network_nodes.php'))->up();

        $this->assertSame(1, DB::table('features')->where('code', 'network_nodes')->count());
    }
}
