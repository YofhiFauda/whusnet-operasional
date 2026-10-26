<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\Customer;
use App\Models\CustomerTechnicalDetail;
use App\Models\NetworkNode;
use App\Models\NetworkNodePort;
use App\Models\NetworkNodeType;
use App\Models\NetworkPortAssignment;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\FopUnifiedDashboardService;
use App\Services\InfrastructurePositionService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\NetworkNodeTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
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

    /**
     * ODP wajib anak langsung PON sejak Task 12 (rancangan "Slot & PON
     * sebagai node" §2.4) — bangun rantai OLT -> Slot -> PON minimal lewat
     * `InfrastructurePositionService` (satu-satunya penulis posisi) supaya
     * tes pembuatan ODP baru punya induk PON yang sah.
     */
    private function makePon(Pop $pop): NetworkNode
    {
        $chassisType = NetworkNodeType::where('code', 'olt_chassis')->firstOrFail();
        $chassis = NetworkNode::factory()->create([
            'anchor_pop_id' => $pop->id,
            'node_type_id' => $chassisType->id,
            'parent_id' => null,
        ]);
        $positions = app(InfrastructurePositionService::class);
        $slot = $positions->createSlot($chassis, 1);

        return $positions->createPort($slot, 1);
    }

    public function test_node_menyimpan_lokasi_sendiri_dan_mewarisi_lokasi_induk(): void
    {
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $pop = Pop::create([
            'code' => 'LOC-A', 'name' => 'POP Lokasi', 'type' => 'cabang', 'status' => 'active',
            'address' => 'Jalan POP 1', 'city' => 'Ponorogo', 'latitude' => -7.8650000, 'longitude' => 111.4690000,
        ]);
        // Tipe generik tanpa aturan induk khusus — tes ini menguji warisan
        // lokasi lewat `parent_id` (fitur umum NetworkNode), bukan aturan
        // "ODP wajib anak PON" (Task 12), jadi tidak dipasangkan ke odp.
        $locationTypeId = NetworkNodeType::create(['code' => 'test-location', 'name' => 'Tipe Lokasi Generik', 'has_ports' => false])->id;

        $this->actingAs($owner)->post(route('network.nodes.store'), [
            'node_type_id' => $locationTypeId,
            'anchor_pop_id' => $pop->id,
            'code' => 'ODP-LOC-A',
            'name' => 'ODP Lokasi A',
            'capacity' => 2,
            'address' => 'Tiang RT 02',
            'village' => 'Siman',
            'district' => 'Siman',
            'city' => 'Ponorogo',
            'latitude' => -7.8660000,
            'longitude' => 111.4700000,
        ])->assertRedirect(route('network.nodes.show', NetworkNode::where('code', 'ODP-LOC-A')->firstOrFail()));

        $parent = NetworkNode::where('code', 'ODP-LOC-A')->firstOrFail();
        $this->assertSame('Tiang RT 02', $parent->address);
        $this->assertSame('Siman', $parent->village);

        $child = NetworkNode::create([
            'parent_id' => $parent->id, 'node_type_id' => $locationTypeId,
            'code' => 'ODP-LOC-B', 'name' => 'ODP Lokasi B', 'status' => 'active',
        ]);
        $this->assertSame('Tiang RT 02', $child->effectiveLocation()['address']);
        $this->assertSame($parent->id, $child->effectiveLocation()['source_node_id']);

        $this->actingAs($owner)->patch(route('network.nodes.update', $child), [
            'name' => 'ODP Lokasi B', 'address' => 'Tiang RT 03',
        ])->assertRedirect(route('network.nodes.show', $child));
        $this->assertSame('Tiang RT 03', $child->refresh()->effectiveLocation()['address']);
    }

    public function test_odp_memiliki_port_child_dan_red_zone_dihitung_dari_status_port(): void
    {
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $pop = Pop::create(['code' => 'ODP-A', 'name' => 'POP ODP A', 'type' => 'cabang', 'status' => 'active']);
        // ODP wajib anak langsung PON sejak Task 12 — tidak lagi anchor POP langsung.
        $pon = $this->makePon($pop);

        $this->actingAs($owner)->post(route('network.nodes.store'), [
            'node_type_id' => $this->odpTypeId,
            'parent_id' => $pon->id,
            'code' => 'odp-test-01',
            'name' => 'ODP Test 01',
            'capacity' => 8,
        ])->assertRedirect(route('network.nodes.show', NetworkNode::where('code', 'ODP-TEST-01')->firstOrFail()));

        $node = NetworkNode::where('code', 'ODP-TEST-01')->firstOrFail();
        $this->assertSame($pon->id, $node->parent_id);
        $this->assertNull($node->anchor_pop_id);
        $this->assertSame(8, $node->ports()->count());
        $this->assertSame(0, $node->ports()->where('is_used', true)->count());

        foreach ($node->ports()->orderBy('number')->take(7)->get() as $port) {
            $this->actingAs($owner)->put(route('network.node-ports.update', $port), [
                'is_used' => 1,
            ])->assertRedirect(route('network.nodes.index'));
        }

        $this->assertSame(7, NetworkPortAssignment::where('active_key', 1)->count());

        $summary = app(FopUnifiedDashboardService::class)->getOdpSummary(true, []);
        $this->assertSame(1, $summary['total_odps']);
        $this->assertSame(1, $summary['red_zone_count']);
        $this->assertSame('ODP-TEST-01', $summary['red_zone'][0]['code']);
        $this->assertSame(7, $summary['red_zone'][0]['used']);
        $this->assertSame(8, $summary['red_zone'][0]['capacity']);
        $this->assertSame(88, $summary['red_zone'][0]['percent']);
        $this->actingAs($owner)->get(route('network.nodes.index'))->assertSee('Red Zone');
    }

    /**
     * Task 12: ODP tanpa induk PON wajib ditolak — anchor POP langsung
     * (gaya lama) maupun tanpa induk sama sekali keduanya kena.
     */
    #[Test]
    public function buat_odp_tanpa_pon_ditolak(): void
    {
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $pop = Pop::create(['code' => 'ODP-NOPON', 'name' => 'POP Tanpa PON', 'type' => 'cabang', 'status' => 'active']);

        $this->actingAs($owner)->post(route('network.nodes.store'), [
            'node_type_id' => $this->odpTypeId,
            'anchor_pop_id' => $pop->id,
            'code' => 'ODP-NOPON-1',
            'name' => 'ODP Tanpa PON',
            'capacity' => 4,
        ])->assertSessionHasErrors('parent_id');
        $this->assertDatabaseMissing('network_nodes', ['code' => 'ODP-NOPON-1']);

        // Punya parent_id tapi bukan PON (mis. Slot) juga ditolak.
        $slotParent = $this->makePon($pop)->parent;
        $this->actingAs($owner)->post(route('network.nodes.store'), [
            'node_type_id' => $this->odpTypeId,
            'parent_id' => $slotParent->id,
            'code' => 'ODP-NOPON-2',
            'name' => 'ODP Induk Salah',
            'capacity' => 4,
        ])->assertSessionHasErrors('parent_id');
        $this->assertDatabaseMissing('network_nodes', ['code' => 'ODP-NOPON-2']);
    }

    /**
     * Task 12: ODP yang dibuat di bawah PON tersimpan sebagai anak langsung
     * PON itu (`parent_id`), tanpa `anchor_pop_id` — lokasi diturunkan dari
     * rantai induk (`resolvePop()`), bukan ditambatkan sendiri.
     */
    #[Test]
    public function buat_odp_di_pon_membuat_induk_pon(): void
    {
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $pop = Pop::create(['code' => 'ODP-PON', 'name' => 'POP Dengan PON', 'type' => 'cabang', 'status' => 'active']);
        $pon = $this->makePon($pop);

        $this->actingAs($owner)->post(route('network.nodes.store'), [
            'node_type_id' => $this->odpTypeId,
            'parent_id' => $pon->id,
            'code' => 'ODP-PON-1',
            'name' => 'ODP Di Bawah PON',
            'capacity' => 4,
        ])->assertRedirect(route('network.nodes.show', NetworkNode::where('code', 'ODP-PON-1')->firstOrFail()));

        $odp = NetworkNode::where('code', 'ODP-PON-1')->firstOrFail();
        $this->assertSame($pon->id, $odp->parent_id);
        $this->assertNull($odp->anchor_pop_id);
        $this->assertSame($pop->id, $odp->resolvePop()?->id);
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
    public function test_store_menerima_tipe_node_tanpa_port_tanpa_membuat_port(): void
    {
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $pop = Pop::create(['code' => 'ODP-G', 'name' => 'POP ODP G', 'type' => 'cabang', 'status' => 'active']);
        // core_router dinonaktifkan 2026-10-23 (bukan bagian hierarki resmi,
        // §17 rancangan hierarki) — pakai tipe generik tanpa port buatan
        // sendiri supaya tes tetap menguji behavior "tipe tanpa port", bukan
        // tipe core_router itu sendiri.
        $noPortType = NetworkNodeType::create(['code' => 'test-no-port', 'name' => 'Tipe Tanpa Port', 'has_ports' => false]);

        $this->actingAs($owner)->post(route('network.nodes.store'), [
            'node_type_id' => $noPortType->id,
            'anchor_pop_id' => $pop->id,
            'code' => 'CR-01',
            'name' => 'Core',
            'capacity' => 8,
        ])->assertRedirect(route('network.nodes.show', NetworkNode::where('code', 'CR-01')->firstOrFail()));

        $core = NetworkNode::where('code', 'CR-01')->firstOrFail();
        $this->assertSame(8, $core->capacity);
        $this->assertSame(0, $core->ports()->count());
    }

    /**
     * Tipe `max_ports = null` (tanpa batas) melewati closure cap per tipe —
     * tanpa plafon datar, satu request store() bisa minta jutaan baris port.
     */
    public function test_store_tipe_tanpa_max_ports_tetap_dibatasi_plafon_datar(): void
    {
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $pop = Pop::create(['code' => 'ODP-H', 'name' => 'POP ODP H', 'type' => 'cabang', 'status' => 'active']);
        NetworkNodeType::where('id', $this->odpTypeId)->update(['max_ports' => null]);

        $this->actingAs($owner)->post(route('network.nodes.store'), [
            'node_type_id' => $this->odpTypeId,
            'anchor_pop_id' => $pop->id,
            'code' => 'ODP-BIG',
            'name' => 'ODP Besar',
            'capacity' => 1001,
        ])->assertSessionHasErrors('capacity');

        $this->assertDatabaseMissing('network_nodes', ['code' => 'ODP-BIG']);
        $this->assertSame(0, NetworkNodePort::count());
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
     * Gejala yang dijaga: badge Red Zone di halaman listing Network Node
     * dulu pakai 85% hardcode, sementara dashboard FOP pakai ambang per tipe
     * — node yang sama bisa Red Zone di satu layar dan normal di layar lain.
     * Sekarang keduanya lewat `NetworkNode::isInRedZone()`.
     */
    public function test_badge_red_zone_listing_memakai_ambang_per_tipe_sama_dengan_dashboard(): void
    {
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $pop = Pop::create(['code' => 'ODP-RZ', 'name' => 'POP ODP RZ', 'type' => 'cabang', 'status' => 'active']);
        $node = $this->makeNode($pop->id, 'ODP-RZ-1', 'Ambang Listing');
        // 5/7 = 71% — di bawah 85% hardcode lama.
        for ($number = 1; $number <= 7; $number++) {
            $node->ports()->create(['number' => $number, 'is_used' => $number <= 5]);
        }

        NetworkNodeType::where('id', $this->odpTypeId)->update(['red_zone_threshold' => 70]);
        $this->actingAs($owner)->get(route('network.nodes.index'))
            ->assertOk()
            ->assertSee('data-red-zone="1"', false)
            ->assertSee('Red Zone · 71%');
        $this->assertSame(1, app(FopUnifiedDashboardService::class)->getOdpSummary(true, [])['red_zone_count']);

        // Kebalikannya: 6/7 = 86% (di atas 85% lama) tapi ambang tipe 90 →
        // bukan Red Zone di listing maupun dashboard.
        $node->ports()->where('number', 6)->update(['is_used' => true]);
        NetworkNodeType::where('id', $this->odpTypeId)->update(['red_zone_threshold' => 90]);
        $this->actingAs($owner)->get(route('network.nodes.index'))
            ->assertOk()
            ->assertSee('data-red-zone="0"', false)
            ->assertDontSee('data-red-zone="1"', false)
            ->assertDontSee('Red Zone · 86%');
        $this->assertSame(0, app(FopUnifiedDashboardService::class)->getOdpSummary(true, [])['red_zone_count']);
    }

    public function test_is_in_red_zone_aman_tanpa_with_count(): void
    {
        $pop = Pop::create(['code' => 'ODP-NC', 'name' => 'POP ODP NC', 'type' => 'cabang', 'status' => 'active']);
        $node = $this->makeNode($pop->id, 'ODP-NC-1', 'Tanpa WithCount');
        $node->ports()->create(['number' => 1, 'is_used' => true]);

        $this->assertFalse(NetworkNode::findOrFail($node->id)->isInRedZone());
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
        [$nodesFeatureId, $atasan, $pop, $createdAt] = $this->simulateLegacyOdpDatabase();

        $this->runReshapeMigration();

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
     * Regresi grant hilang: tipe `odp` belum di-seed → copy node melempar.
     * Rename Feature/permission harus SUDAH ter-commit sebelum lemparan itu.
     * Kalau ikut ter-rollback, operator menjalankan NetworkNodeTypeSeeder
     * (yang membuat Feature `network_nodes` baru), lalu migrate ulang melihat
     * `network_nodes` sudah ada dan melewati rename — grant role tertinggal
     * di `network_odps.*` yang tidak dibaca siapa pun.
     */
    public function test_migrasi_reshape_rename_permission_tetap_jalan_walau_tipe_odp_belum_di_seed(): void
    {
        [$nodesFeatureId, $atasan, $pop] = $this->simulateLegacyOdpDatabase();
        DB::table('network_node_types')->where('code', 'odp')->delete();

        try {
            $this->runReshapeMigration();
            $this->fail('Migration harus melempar selama tipe odp belum ada.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('NetworkNodeTypeSeeder', $exception->getMessage());
        }

        // Rename sudah terjadi di percobaan pertama, data lama belum disentuh.
        $this->assertDatabaseMissing('features', ['code' => 'network_odps']);
        $this->assertDatabaseHas('features', ['id' => $nodesFeatureId, 'code' => 'network_nodes']);
        $this->assertDatabaseMissing('permissions', ['code' => 'network_odps.view']);
        $viewPermissionId = DB::table('permissions')->where('code', 'network_nodes.view')->value('id');
        $this->assertDatabaseHas('permissions', ['id' => $viewPermissionId, 'feature_id' => $nodesFeatureId]);
        $this->assertDatabaseHas('role_permissions', ['role_id' => $atasan->id, 'permission_id' => $viewPermissionId]);
        $this->assertTrue(Schema::hasTable('odps'));
        $this->assertTrue(Schema::hasTable('odp_ports'));
        $this->assertSame(0, NetworkNode::count());

        // Ikuti pesan error: seed tipe, lalu migrate ulang.
        $this->seed(NetworkNodeTypeSeeder::class);
        $this->runReshapeMigration();

        $node = NetworkNode::where('code', 'ODP-LAMA-1')->firstOrFail();
        $this->assertSame($pop->id, $node->anchor_pop_id);
        $this->assertSame(2, $node->ports()->count());
        $this->assertFalse(Schema::hasTable('odps'));
        $this->assertFalse(Schema::hasTable('odp_ports'));

        // Tidak ada Feature yatim/dobel; grant atasan tetap di permission yang sama.
        $this->assertSame([$nodesFeatureId], DB::table('features')->where('code', 'network_nodes')->pluck('id')->all());
        $this->assertSame($viewPermissionId, DB::table('permissions')->where('code', 'network_nodes.view')->value('id'));
        $this->assertDatabaseHas('role_permissions', ['role_id' => $atasan->id, 'permission_id' => $viewPermissionId]);

        $atasanUser = User::factory()->create(['role_id' => $atasan->id]);
        $this->actingAs($atasanUser)->get(route('network.nodes.index'))->assertOk()->assertSee('ODP-LAMA-1');
    }

    public function test_admin_dapat_membuat_node_tanpa_port_dan_anak_bertingkat(): void
    {
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $pop = Pop::create(['code' => 'TREE-A', 'name' => 'POP Pohon', 'type' => 'cabang', 'status' => 'active']);
        // core_router/odc dinonaktifkan 2026-10-23 (bukan bagian hierarki
        // resmi, §17 rancangan hierarki). "Core" di sini memakai tipe
        // generik tanpa port buatan sendiri, murni untuk menguji node
        // bertingkat tanpa port — bukan menguji core_router.
        $coreTypeId = NetworkNodeType::create(['code' => 'test-core', 'name' => 'Induk Generik', 'has_ports' => false])->id;

        $this->actingAs($owner)->post(route('network.nodes.store'), [
            'node_type_id' => $coreTypeId,
            'anchor_pop_id' => $pop->id,
            'code' => 'CORE-1',
            'name' => 'Core Utama',
            'capacity' => 100,
        ])->assertRedirect(route('network.nodes.show', NetworkNode::where('code', 'CORE-1')->firstOrFail()));

        $core = NetworkNode::where('code', 'CORE-1')->firstOrFail();
        $this->assertSame(0, $core->ports()->count());
        $this->assertSame(100, $core->capacity);

        // ODC sendiri sudah nonaktif (bukan sekadar "parent_id salah") —
        // request ditolak pada validasi node_type_id, sebelum aturan
        // placement lama (anak dari olt_card/pon_port) dicek.
        $this->actingAs($owner)->post(route('network.nodes.store'), [
            'node_type_id' => NetworkNodeType::where('code', 'odc')->value('id'),
            'parent_id' => $core->id,
            'code' => 'ODC-INVALID-1',
            'name' => 'ODC Tidak Valid',
            'capacity' => 4,
        ])->assertSessionHasErrors('node_type_id');

        // Backbone bukan tipe node lagi (migration 000008). pon_port
        // dinonaktifkan 2026-10-23 (ADHOC-209, representasi lama PON sebagai
        // node sudah tidak dipakai — PON sekarang infrastructure_ports) —
        // anak bertingkat tanpa port diuji pakai tipe generik buatan sendiri.
        $this->actingAs($owner)->post(route('network.nodes.store'), [
            'node_type_id' => NetworkNodeType::where('code', 'olt_chassis')->value('id'),
            'anchor_pop_id' => $pop->id,
            'code' => 'OLT-TREE-1',
            'name' => 'OLT Pohon',
        ])->assertRedirect(route('network.nodes.show', NetworkNode::where('code', 'OLT-TREE-1')->firstOrFail()));
        $olt = NetworkNode::where('code', 'OLT-TREE-1')->firstOrFail();
        $genericChildType = NetworkNodeType::create(['code' => 'test-child-no-port', 'name' => 'Anak Generik', 'has_ports' => false]);
        $this->actingAs($owner)->post(route('network.nodes.store'), [
            'node_type_id' => $genericChildType->id,
            'parent_id' => $olt->id,
            'code' => 'PON-CHILD-1',
            'name' => 'PON Anak',
            'capacity' => 4,
        ])->assertRedirect(route('network.nodes.show', NetworkNode::where('code', 'PON-CHILD-1')->firstOrFail()));

        $child = NetworkNode::where('code', 'PON-CHILD-1')->firstOrFail();
        $this->assertSame($olt->id, $child->parent_id);
        $this->assertNull($child->anchor_pop_id);
        $this->assertSame(0, $child->ports()->count());
    }

    public function test_route_pindah_node_mengikuti_scope_dan_menolak_siklus(): void
    {
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $pop = Pop::create(['code' => 'TREE-B', 'name' => 'POP Pohon B', 'type' => 'cabang', 'status' => 'active']);
        $root = $this->makeNode($pop->id, 'ROOT-B', 'Root');
        $child = NetworkNode::create([
            'parent_id' => $root->id,
            'anchor_pop_id' => null,
            'node_type_id' => $this->odpTypeId,
            'code' => 'CHILD-B',
            'name' => 'Child',
        ]);

        $this->actingAs($owner)->patch(route('network.nodes.move', $root), [
            'parent_id' => $child->id,
        ])->assertSessionHasErrors('parent_id');

        $this->assertNull($root->fresh()->parent_id);

        // Memindah ODP ke anchor POP langsung ditolak sejak Task 12 (ODP
        // wajib anak PON) — bagian "scope" tes ini sekarang dibuktikan lewat
        // pemindahan ke PON yang sah di Cabang yang sama.
        $pon = $this->makePon($pop);
        $this->actingAs($owner)->patch(route('network.nodes.move', $child), [
            'parent_id' => $pon->id,
        ])->assertRedirect(route('network.nodes.index'));
        $this->assertSame($pon->id, $child->fresh()->parent_id);
    }

    public function test_index_menampilkan_pohon_node_dengan_anak_bertingkat(): void
    {
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $pop = Pop::create(['code' => 'TREE-C', 'name' => 'Cabang Pohon', 'type' => 'cabang', 'status' => 'active']);
        $root = $this->makeNode($pop->id, 'TREE-ROOT', 'Node Induk');
        NetworkNode::create([
            'parent_id' => $root->id,
            'anchor_pop_id' => null,
            'node_type_id' => $this->odpTypeId,
            'code' => 'TREE-CHILD',
            'name' => 'Node Anak',
        ]);

        $this->actingAs($owner)->get(route('network.nodes.index'))
            ->assertOk()
            ->assertSee('data-tree-node-id="'.$root->id.'"', false)
            ->assertSee('data-tree-parent-id="'.$root->id.'"', false)
            ->assertSee('Node Anak');
    }

    public function test_index_menampilkan_server_di_atas_pop_lalu_cabang_mini_pop_dan_olt(): void
    {
        // Hierarki baru (§1): Server → POP → Mini POP → OLT/Switch. Backbone dan
        // agregasi bukan tingkat pohon lagi.
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $pusat = Pop::create(['code' => 'TREE-P', 'name' => 'WHUSNET', 'type' => 'pusat', 'status' => 'active']);
        $cabang = Pop::create(['code' => 'TREE-C', 'name' => 'Cabang Pohon', 'type' => 'cabang', 'parent_id' => $pusat->id, 'status' => 'active']);
        $mini = Pop::create(['code' => 'TREE-M', 'name' => 'C1', 'type' => 'mini_pop', 'parent_id' => $cabang->id, 'status' => 'active']);
        NetworkNode::create([
            'anchor_pop_id' => $pusat->id, 'node_type_id' => NetworkNodeType::where('code', 'server')->value('id'),
            'code' => 'TREE-SERVER', 'name' => 'Server Utama', 'status' => 'active',
        ]);
        NetworkNode::create([
            'anchor_pop_id' => $mini->id, 'node_type_id' => NetworkNodeType::where('code', 'olt_chassis')->value('id'),
            'code' => 'TREE-OLT', 'name' => 'OLT', 'status' => 'active',
        ]);

        $this->actingAs($owner)->get(route('network.nodes.index'))
            ->assertOk()
            ->assertSeeInOrder(['TREE-SERVER', 'WHUSNET', 'Cabang Pohon', 'C1', 'TREE-OLT']);
    }

    public function test_tipe_tanpa_port_tidak_bisa_diberi_port_melalui_endpoint(): void
    {
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $pop = Pop::create(['code' => 'CORE-NP', 'name' => 'POP Core', 'type' => 'cabang', 'status' => 'active']);
        // core_router DIHAPUS 2026-10-23 (ADHOC-211) — tipe generik buatan
        // sendiri, cukup buat menguji "tipe tanpa port".
        $noPortType = NetworkNodeType::create(['code' => 'test-core-np', 'name' => 'Tipe Tanpa Port', 'has_ports' => false]);
        $core = NetworkNode::create([
            'anchor_pop_id' => $pop->id,
            'node_type_id' => $noPortType->id,
            'code' => 'CORE-NP-1',
            'name' => 'Core',
            'capacity' => 100,
        ]);

        $this->actingAs($owner)->post(route('network.nodes.ports.add', $core), [
            'additional_capacity' => 1,
        ])->assertStatus(422);
        $this->assertSame(0, $core->ports()->count());
    }

    private function runReshapeMigration(): void
    {
        (require database_path('migrations/2026_10_12_110300_reshape_odps_into_network_nodes.php'))->up();
    }

    /**
     * Bangun ulang bentuk DB lama: tabel `odps`/`odp_ports` (file create-nya
     * sudah dihapus) berisi satu ODP dua port, Feature `network_odps` dengan
     * `network_odps.view` yang di-grant ke atasan, dan belum ada Feature
     * `network_nodes`.
     *
     * @return array{0: int, 1: Role, 2: Pop, 3: string}
     */
    private function simulateLegacyOdpDatabase(): array
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

        return [$nodesFeatureId, $atasan, $pop, $createdAt];
    }

    /**
     * Instalasi baru tidak pernah punya `odps` — migration harus diam saja
     * (tidak melempar walau belum ada apa-apa untuk dipindah).
     */
    public function test_migrasi_reshape_aman_di_instalasi_baru_tanpa_tabel_odps(): void
    {
        $this->assertFalse(Schema::hasTable('odps'));

        $this->runReshapeMigration();

        $this->assertSame(1, DB::table('features')->where('code', 'network_nodes')->count());
    }

    public function test_migrasi_susulan_memulihkan_grant_setelah_reshape_lama_sudah_tercatat_ran(): void
    {
        [, $atasan] = $this->simulateLegacyOdpDatabase();
        $this->seed(NetworkNodeTypeSeeder::class);
        Schema::drop('odp_ports');
        Schema::drop('odps');

        $migrationPath = glob(database_path('migrations/*_reconcile_legacy_network_odp_permissions.php'))[0];
        (require $migrationPath)->up();

        $newPermissionId = DB::table('permissions')->where('code', 'network_nodes.view')->value('id');
        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $atasan->id,
            'permission_id' => $newPermissionId,
        ]);
        $this->assertDatabaseMissing('features', ['code' => 'network_odps']);
    }

    public function test_migrasi_mempertahankan_grant_lama_jika_seeder_tipe_sudah_membuat_feature_baru(): void
    {
        [, $atasan] = $this->simulateLegacyOdpDatabase();

        $this->seed(NetworkNodeTypeSeeder::class);
        $newPermissionId = DB::table('permissions')->where('code', 'network_nodes.view')->value('id');
        $this->assertNotNull($newPermissionId);
        $this->assertDatabaseMissing('role_permissions', [
            'role_id' => $atasan->id,
            'permission_id' => $newPermissionId,
        ]);

        $this->runReshapeMigration();

        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $atasan->id,
            'permission_id' => $newPermissionId,
        ]);
        $this->assertDatabaseMissing('features', ['code' => 'network_odps']);
        $this->assertSame(1, NetworkNode::where('code', 'ODP-LAMA-1')->count());
    }

    public function test_node_kosong_dapat_diubah_kode_dan_tipenya(): void
    {
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $pop = Pop::factory()->create(['type' => 'cabang']);
        $node = $this->makeNode($pop->id, 'ODP-EDIT-01', 'Node Awal');
        // core_router dinonaktifkan 2026-10-23 — tipe tujuan ganti pakai
        // tipe generik aktif biar tes tidak bergantung ke tipe yang sudah
        // ditutup (lihat §17 rancangan hierarki).
        $coreType = NetworkNodeType::create(['code' => 'test-retype', 'name' => 'Tipe Ganti Generik', 'has_ports' => false]);

        $this->actingAs($owner)->patch(route('network.nodes.update', $node), [
            'code' => 'CORE-EDIT-01',
            'node_type_id' => $coreType->id,
            'name' => 'Core Baru',
            'status' => 'active',
        ])->assertRedirect(route('network.nodes.show', $node));

        $this->assertSame('CORE-EDIT-01', $node->fresh()->code);
        $this->assertSame($coreType->id, $node->fresh()->node_type_id);
        $this->get(route('network.nodes.show', $node))->assertOk()->assertSee('CORE-EDIT-01');
    }

    public function test_node_dengan_port_tidak_boleh_ganti_tipe_atau_kode(): void
    {
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $pop = Pop::factory()->create(['type' => 'cabang']);
        $node = $this->makeNode($pop->id, 'ODP-TERPAKAI-01', 'Node Terpakai');
        $node->ports()->create(['number' => 1]);
        // Tipe generik aktif (bukan core_router yang sudah dinonaktifkan
        // 2026-10-23) — tes ini menguji guard "punya port = tidak boleh
        // ganti tipe", bukan status aktif tipe tujuan.
        $coreType = NetworkNodeType::create(['code' => 'test-retype-locked', 'name' => 'Tipe Ganti Terkunci', 'has_ports' => false]);

        $this->actingAs($owner)->patch(route('network.nodes.update', $node), [
            'code' => 'CORE-TERPAKAI-01',
            'node_type_id' => $coreType->id,
            'name' => 'Ganti Paksa',
        ])->assertSessionHasErrors('node_type_id');

        $this->assertSame('ODP-TERPAKAI-01', $node->fresh()->code);
        $this->assertSame($this->odpTypeId, $node->fresh()->node_type_id);
    }

    public function test_kode_node_dengan_port_dan_anak_boleh_diubah_tanpa_memutus_relasi(): void
    {
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $pop = Pop::factory()->create(['type' => 'cabang']);
        $node = $this->makeNode($pop->id, 'ODP-KODE-LAMA', 'ODP Utama');
        $port = $node->ports()->create(['number' => 1]);
        $child = NetworkNode::create([
            'parent_id' => $node->id,
            'node_type_id' => $this->odpTypeId,
            'code' => 'ODP-ANAK-01',
            'name' => 'Node Anak',
            'status' => 'active',
        ]);

        $this->actingAs($owner)->patch(route('network.nodes.update', $node), [
            'code' => 'ODP-KODE-BARU',
            'name' => $node->name,
            'node_type_id' => $this->odpTypeId,
        ])->assertRedirect(route('network.nodes.show', $node));

        $this->assertSame('ODP-KODE-BARU', $node->fresh()->code);
        $this->assertSame($node->id, $port->fresh()->network_node_id);
        $this->assertSame($node->id, $child->fresh()->parent_id);
    }

    public function test_kode_odp_yang_masih_dirujuk_data_teknis_pelanggan_tidak_boleh_diubah(): void
    {
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $pop = Pop::factory()->create(['type' => 'cabang']);
        $node = $this->makeNode($pop->id, 'ODP-PELANGGAN-LAMA', 'ODP Pelanggan');
        $customer = Customer::factory()->create(['pop_id' => $pop->id]);
        CustomerTechnicalDetail::create([
            'customer_id' => $customer->id,
            'odp_number' => $node->code,
            'odp_port' => '1',
        ]);

        $this->actingAs($owner)->patch(route('network.nodes.update', $node), [
            'code' => 'ODP-PELANGGAN-BARU',
            'name' => $node->name,
        ])->assertSessionHasErrors('code');

        $this->assertSame('ODP-PELANGGAN-LAMA', $node->fresh()->code);
    }

    public function test_hapus_node_kosong_dan_nonaktifkan_node_yang_sudah_punya_port(): void
    {
        $owner = User::factory()->create(['role_id' => Role::where('code', 'owner')->firstOrFail()->id]);
        $pop = Pop::factory()->create(['type' => 'cabang']);
        $empty = $this->makeNode($pop->id, 'ODP-KOSONG-01', 'Kosong');
        $used = $this->makeNode($pop->id, 'ODP-BERPORT-01', 'Berport');
        $port = $used->ports()->create(['number' => 1]);

        $this->actingAs($owner)->delete(route('network.nodes.destroy', $empty))
            ->assertRedirect(route('network.nodes.index'));
        $this->assertModelMissing($empty);

        $this->delete(route('network.nodes.destroy', $used))
            ->assertRedirect(route('network.nodes.index'));
        $this->assertSame('inactive', $used->fresh()->status);
        $this->assertModelExists($port);
    }
}
