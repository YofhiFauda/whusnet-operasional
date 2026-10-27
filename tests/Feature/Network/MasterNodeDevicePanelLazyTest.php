<?php

namespace Tests\Feature\Network;

use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\EffectiveAccessService;
use App\Services\InfrastructurePositionService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\NetworkNodeTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regresi pasca-merge ADHOC-213 (umpan balik user 2026-10-26): klik OLT di
 * Master Node cuma menampilkan dua tautan — panel "Manajemen Perangkat"
 * (kartu Slot/PON inline + form Tambah PON) hilang karena dicabut demi fix
 * O(N²) (final review C2). Panel dikembalikan LAZY: dirender server untuk
 * SATU perangkat lewat `network.nodes.panel`, diambil inspector saat
 * perangkat dipilih. Tes ini menjaga endpoint (isi, scope, tipe, izin) dan
 * kaitnya di index (URL dari server, partial tidak dirender saat load).
 */
class MasterNodeDevicePanelLazyTest extends TestCase
{
    use RefreshDatabase;

    private Pop $cabangA;

    private Pop $cabangB;

    private NetworkNode $oltA;

    private NetworkNode $oltB;

    private NetworkNode $ponA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([FeatureSeeder::class, ActionSeeder::class, RoleSeeder::class,
            PermissionSeeder::class, NetworkNodeTypeSeeder::class, RolePermissionSeeder::class]);

        $this->cabangA = Pop::factory()->create(['type' => 'cabang', 'name' => 'Cabang Satu']);
        $this->cabangB = Pop::factory()->create(['type' => 'cabang', 'name' => 'Cabang Dua']);
        $positions = app(InfrastructurePositionService::class);
        $this->oltA = $this->device('olt_chassis', $this->cabangA, 'OLT-SATU');
        $this->oltB = $this->device('olt_chassis', $this->cabangB, 'OLT-DUA');
        $this->ponA = $positions->createPort($positions->createSlot($this->oltA, 2), 3, ['capacity' => 64]);
        $positions->createPort($positions->createSlot($this->oltB, 5), 7);
    }

    private function device(string $typeCode, Pop $pop, string $code): NetworkNode
    {
        return NetworkNode::factory()->create(['node_type_id' => NetworkNodeType::where('code', $typeCode)->value('id'),
            'anchor_pop_id' => $pop->id, 'parent_id' => null, 'code' => $code, 'name' => $code]);
    }

    private function cabangUser(Pop $cabang, string $role = 'fop'): User
    {
        $user = User::factory()->create(['role_id' => Role::where('code', $role)->firstOrFail()->id]);
        $scope = UserRoleScope::create(['user_id' => $user->id, 'role_id' => $user->role_id, 'scope_type' => 'selected_pop']);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $cabang->id]);
        app(EffectiveAccessService::class)->clearCache($user);

        return $user;
    }

    /**
     * Putaran UI 2 (spesifikasi user 2026-10-26): panel OLT berisi HANYA
     * daftar Slot + kapasitas tiap Slot — kartu PON bersarang (versi
     * putaran 1) sekarang harus TIDAK ada; PON dibuka di panel Slot.
     */
    #[Test]
    public function panel_olt_dalam_scope_hanya_berisi_slot_milik_olt_itu_dengan_kapasitas(): void
    {
        $user = $this->cabangUser($this->cabangA);
        $slotA = $this->ponA->parent;
        $slotA->update(['capacity' => 16]);
        $emptySlot = app(InfrastructurePositionService::class)->createSlot($this->oltA, 4);

        $html = $this->actingAs($user)->get(route('network.nodes.panel', $this->oltA))
            ->assertOk()
            ->assertSee('id="device-components-'.$this->oltA->id.'"', false)
            ->assertSee('data-node-panel="device"', false)
            ->assertSee('data-panel-slot="'.$slotA->id.'"', false)
            ->assertSee('data-panel-slot="'.$emptySlot->id.'"', false)
            ->assertSee('Slot 2')
            // Form di dalam panel tetap form server biasa: target dari route(), token CSRF ada.
            ->assertSee('action="'.route('network.nodes.infrastructure-slots.store', $this->oltA).'"', false)
            ->assertSee('action="'.route('network.nodes.capacity', $this->oltA).'"', false)
            ->assertSee('name="master_node" value="'.$this->oltA->id.'"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('Tambah Slot')
            ->getContent();

        // Slot 2: 1 PON terdata / kapasitas 16 (sisa 15); Slot 4: kapasitas belum didata.
        $this->assertMatchesRegularExpression('/data-panel-slot="'.$slotA->id.'".*?data-capacity-used="1" data-capacity-total="16"/s', $html);
        $this->assertStringContainsString('>15<', $html);
        $this->assertMatchesRegularExpression('/data-panel-slot="'.$emptySlot->id.'".*?data-capacity-used="0" data-capacity-total=""/s', $html);
        $this->assertStringContainsString('Kapasitas belum didata', $html);
        // Perangkat: 2 Slot terdata, kapasitas perangkat belum didata.
        $this->assertMatchesRegularExpression('/data-capacity-used="2" data-capacity-total=""/', $html);
        // TIDAK ada kartu PON/form Tambah PON di level OLT.
        // PON kini tampil sebagai petak ringkas di kartu Slot (permintaan user 2026-10-27), tetap tanpa kartu detail/form PON.
        $this->assertStringContainsString('data-slot-tile="filled"', $html);
        $this->assertStringNotContainsString('data-panel-port=', $html);
        $this->assertStringNotContainsString('data-component-port=', $html);
        $this->assertStringNotContainsString('Tambah PON', $html);
        $this->assertStringNotContainsString(route('network.infrastructure-slots.ports.store', $slotA), $html);
        // Drill-down "Kelola" membawa panel_url Slot yang dirender server.
        $this->assertStringContainsString('data-select-position=', $html);
        $this->assertStringContainsString(e(json_encode(route('network.nodes.panel', $slotA))), $html);
        // Potongan HTML, bukan halaman penuh; tanpa atribut Alpine (disisipkan via innerHTML).
        $this->assertStringNotContainsString('<html', $html);
        $this->assertStringNotContainsString('x-data', $html);
        $this->assertStringNotContainsString('@click', $html);
        // Panel OLT lain tidak ikut.
        $this->assertStringNotContainsString('OLT-DUA', $html);
        $this->assertStringNotContainsString('Slot 5', $html);
    }

    #[Test]
    public function panel_switch_hanya_berisi_slot_tanpa_port_switch(): void
    {
        $positions = app(InfrastructurePositionService::class);
        $mini = Pop::factory()->create(['type' => 'mini_pop', 'parent_id' => $this->cabangA->id]);
        $switch = $this->device('switch_mini_pop', $mini, 'SW-SATU');
        $slot = $positions->createSlot($switch, 1);
        $port = $positions->createPort($slot, 4);

        $this->loginAsAdmin();
        $this->get(route('network.nodes.panel', $switch))
            ->assertOk()
            ->assertSee('data-panel-slot="'.$slot->id.'"', false)
            ->assertSee('Setiap Slot memuat Port Switch.')
            ->assertSee('Port Switch terdata')
            ->assertDontSee('data-panel-port="'.$port->id.'"', false)
            ->assertDontSee('Kapasitas PON (pelanggan aktif)');
    }

    #[Test]
    public function halaman_detail_perangkat_memakai_partial_level_perangkat_tanpa_kembali_ke_master_node(): void
    {
        $this->loginAsAdmin();

        $html = $this->get(route('network.nodes.show', $this->oltA))->assertOk()->getContent();

        $this->assertStringContainsString('data-panel-slot="'.$this->ponA->parent_id.'"', $html);
        $this->assertStringNotContainsString('data-panel-port=', $html);
        // Form Detail tetap PRG ke Detail: tidak membawa `master_node`.
        $this->assertStringNotContainsString('name="master_node"', $html);
        $this->assertStringNotContainsString('data-select-position=', $html);
    }

    #[Test]
    public function panel_olt_cabang_lain_403(): void
    {
        $this->actingAs($this->cabangUser($this->cabangA))
            ->get(route('network.nodes.panel', $this->oltB))
            ->assertForbidden();
    }

    #[Test]
    public function tipe_di_luar_perangkat_slot_pon_404(): void
    {
        // PON kini PUNYA panel (level PON); yang 404 adalah tipe di luar
        // {olt_chassis, switch_mini_pop, olt_slot, switch_slot, olt_pon}.
        $odp = NetworkNode::create(['node_type_id' => NetworkNodeType::where('code', 'odp')->value('id'),
            'parent_id' => $this->ponA->id, 'anchor_pop_id' => null, 'code' => 'ODP-404', 'name' => 'ODP 404', 'status' => 'active']);

        $this->actingAs($this->cabangUser($this->cabangA))
            ->get(route('network.nodes.panel', $odp))
            ->assertNotFound();
    }

    #[Test]
    public function tanpa_izin_network_nodes_view_403(): void
    {
        $user = User::factory()->create(['role_id' => Role::where('code', 'helpdesk')->firstOrFail()->id, 'status' => 'active']);

        $this->actingAs($user)->get(route('network.nodes.panel', $this->oltA))->assertForbidden();
    }

    #[Test]
    public function index_merender_kait_panel_lazy_dengan_url_server_tanpa_merender_partial(): void
    {
        $user = $this->cabangUser($this->cabangA);

        $html = $this->actingAs($user)->get(route('network.nodes.index'))->assertOk()->getContent();

        $this->assertStringContainsString('data-panel-url="'.route('network.nodes.panel', $this->oltA).'"', $html);
        $this->assertStringContainsString('Manajemen Node — OLT', $html);
        $this->assertStringContainsString(route('network.nodes.generate-structure.create', $this->oltA), $html);
        // Partial TIDAK dirender saat load (itu yang membuat index O(N²)).
        $this->assertStringNotContainsString('data-component-slot=', $html);
        $this->assertStringNotContainsString('id="device-components-', $html);
        // OLT cabang lain tidak punya kait.
        $this->assertStringNotContainsString(route('network.nodes.panel', $this->oltB), $html);
        // Klien tidak merakit path panel dari id.
        $this->assertStringNotContainsString("'/panel'", $html);
        $this->assertStringNotContainsString('+ \'/panel', $html);
        // Satu inspector posisi generik (bukan satu per Slot/PON), tanpa isi panel.
        $this->assertSame(1, substr_count($html, 'data-position-inspector'));
        $this->assertStringNotContainsString('data-panel-slot=', $html);
    }

    #[Test]
    public function index_memilih_ulang_node_dari_query_string_setelah_redirect(): void
    {
        $other = $this->device('olt_chassis', $this->cabangA, 'OLT-AAA');
        $user = $this->cabangUser($this->cabangA);

        // Tanpa parameter: node pertama terurut kode.
        $this->actingAs($user)->get(route('network.nodes.index'))->assertOk()
            ->assertViewHas('selectedNodeId', $other->id);
        // Dengan ?node= dalam scope: node itu yang terpilih.
        $this->get(route('network.nodes.index', ['node' => $this->oltA->id]))->assertOk()
            ->assertViewHas('selectedNodeId', $this->oltA->id);
        // ?node= di luar scope diabaikan (tidak membocorkan, tidak error).
        $this->get(route('network.nodes.index', ['node' => $this->oltB->id]))->assertOk()
            ->assertViewHas('selectedNodeId', $other->id);
    }
}
